<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/config.php';

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

$userId = $_SESSION['user']['id'] ?? null;
$sessionId = session_id();

// Auth check for actions that need it
if (in_array($action, ['voice_chat']) && !$userId) {
    echo json_encode(['success' => false, 'error' => 'Please log in to use voice chat.']);
    exit;
}

// === Get voice settings ===
function getVoiceSetting($pdo, $key, $default = null) {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM voice_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        return $stmt->fetchColumn() ?: $default;
    } catch (Exception $e) { return $default; }
}

// === Get active voice provider by type ===
function getActiveVoiceProvider($pdo, $type = 'stt_tts') {
    try {
        $stmt = $pdo->prepare("SELECT * FROM voice_providers WHERE is_active = 1 AND (provider_type = ? OR provider_type = 'stt_tts') ORDER BY FIELD(provider_type, ?, 'stt_tts') LIMIT 1");
        $stmt->execute([$type, $type]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) { return null; }
}

// === Log voice request ===
function logVoiceRequest($pdo, $userId, $provider, $reqType, $reqData, $respData, $status, $durationMs, $errorMsg = null) {
    try {
        $stmt = $pdo->prepare("INSERT INTO voice_logs (user_id, provider, request_type, request_data, response_data, status, duration_ms, error_message) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $provider, $reqType, $reqData, $respData, $status, $durationMs, $errorMsg]);
    } catch (Exception $e) {}
}

// === Action: STT (Speech to Text) ===
if ($action === 'stt') {
    $audioData = $input['audio'] ?? '';
    $audioFormat = $input['format'] ?? 'webm';
    $language = $input['language'] ?? 'en';

    if (empty($audioData)) {
        echo json_encode(['success' => false, 'error' => 'No audio data provided']);
        exit;
    }

    $provider = getActiveVoiceProvider($pdo, 'stt');
    if (!$provider) {
        echo json_encode(['success' => false, 'error' => 'No active STT provider configured. Please add one in Voice Providers admin.']);
        exit;
    }

    $apiKey = $provider['api_key'];
    $startTime = microtime(true);

    try {
        $audioBytes = base64_decode($audioData);
        $tmpFile = tempnam(sys_get_temp_dir(), 'voice_stt_');
        
        // Determine file extension
        $ext = $audioFormat;
        if (strpos($audioFormat, 'webm') !== false) $ext = 'webm';
        elseif (strpos($audioFormat, 'ogg') !== false) $ext = 'ogg';
        elseif (strpos($audioFormat, 'wav') !== false) $ext = 'wav';
        elseif (strpos($audioFormat, 'mp4') !== false || strpos($audioFormat, 'm4a') !== false) $ext = 'mp4';
        rename($tmpFile, $tmpFile . '.' . $ext);
        $tmpFile = $tmpFile . '.' . $ext;
        file_put_contents($tmpFile, $audioBytes);

        $providerName = strtolower($provider['provider_name']);

        if (strpos($providerName, 'openai') !== false) {
            // OpenAI Whisper API
            $ch = curl_init('https://api.openai.com/v1/audio/transcriptions');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey],
                CURLOPT_POSTFIELDS => [
                    'file' => new CURLFile($tmpFile, 'audio/' . $ext, 'audio.' . $ext),
                    'model' => $provider['default_model'] ?: 'whisper-1',
                    'language' => $language
                ],
                CURLOPT_TIMEOUT => 60
            ]);
            $response = curl_exec($ch);
            $curlErr = curl_error($ch);
            curl_close($ch);
            @unlink($tmpFile);

            $decoded = json_decode($response, true);
            $text = $decoded['text'] ?? null;
            $duration = round((microtime(true) - $startTime) * 1000);

            if ($text) {
                logVoiceRequest($pdo, $userId, $provider['provider_name'], 'stt', json_encode(['language' => $language]), $text, 'success', $duration);
                echo json_encode(['success' => true, 'text' => $text, 'provider' => $provider['provider_name'], 'duration_ms' => $duration]);
            } else {
                $err = $decoded['error']['message'] ?? $curlErr ?: 'STT failed';
                logVoiceRequest($pdo, $userId, $provider['provider_name'], 'stt', null, $response, 'failed', $duration, $err);
                echo json_encode(['success' => false, 'error' => $err]);
            }
        } elseif (strpos($providerName, 'deepgram') !== false) {
            // Deepgram STT
            $ch = curl_init('https://api.deepgram.com/v1/listen');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Token ' . $apiKey,
                    'Content-Type: audio/' . $ext
                ],
                CURLOPT_POSTFIELDS => $audioBytes,
                CURLOPT_TIMEOUT => 60
            ]);
            $response = curl_exec($ch);
            $curlErr = curl_error($ch);
            curl_close($ch);
            @unlink($tmpFile);

            $decoded = json_decode($response, true);
            $text = $decoded['results']['channels'][0]['alternatives'][0]['transcript'] ?? null;
            $duration = round((microtime(true) - $startTime) * 1000);

            if ($text) {
                logVoiceRequest($pdo, $userId, $provider['provider_name'], 'stt', json_encode(['language' => $language]), $text, 'success', $duration);
                echo json_encode(['success' => true, 'text' => $text, 'provider' => $provider['provider_name'], 'duration_ms' => $duration]);
            } else {
                logVoiceRequest($pdo, $userId, $provider['provider_name'], 'stt', null, $response, 'failed', $duration, $curlErr ?: 'Deepgram STT failed');
                echo json_encode(['success' => false, 'error' => $curlErr ?: 'STT failed']);
            }
        } elseif (strpos($providerName, 'assemblyai') !== false) {
            // AssemblyAI STT - Upload then transcribe
            // Step 1: Upload audio to AssemblyAI
            $uploadCh = curl_init('https://api.assemblyai.com/v2/upload');
            curl_setopt_array($uploadCh, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Authorization: ' . $apiKey, 'Content-Type: audio/' . $ext],
                CURLOPT_POSTFIELDS => $audioBytes,
                CURLOPT_TIMEOUT => 60
            ]);
            $uploadResp = curl_exec($uploadCh);
            $uploadErr = curl_error($uploadCh);
            curl_close($uploadCh);

            $uploadData = json_decode($uploadResp, true);
            $audioUrl = $uploadData['upload_url'] ?? null;

            if (!$audioUrl) {
                @unlink($tmpFile);
                logVoiceRequest($pdo, $userId, 'AssemblyAI', 'stt', null, $uploadResp, 'failed', round((microtime(true) - $startTime) * 1000), $uploadErr ?: 'Upload failed');
                echo json_encode(['success' => false, 'error' => $uploadErr ?: 'AssemblyAI upload failed']);
                exit;
            }

            // Step 2: Request transcription
            $transCh = curl_init('https://api.assemblyai.com/v2/transcript');
            curl_setopt_array($transCh, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Authorization: ' . $apiKey, 'Content-Type: application/json'],
                CURLOPT_POSTFIELDS => json_encode(['audio_url' => $audioUrl, 'language_code' => $language === 'en' ? 'en_us' : $language]),
                CURLOPT_TIMEOUT => 10
            ]);
            $transResp = curl_exec($transCh);
            curl_close($transCh);

            $transData = json_decode($transResp, true);
            $transcriptId = $transData['id'] ?? null;

            if (!$transcriptId) {
                @unlink($tmpFile);
                logVoiceRequest($pdo, $userId, 'AssemblyAI', 'stt', null, $transResp, 'failed', round((microtime(true) - $startTime) * 1000), 'Transcription request failed');
                echo json_encode(['success' => false, 'error' => 'AssemblyAI transcription request failed']);
                exit;
            }

            // Step 3: Poll for completion (max 30s)
            $text = null;
            $maxPolls = 30;
            for ($i = 0; $i < $maxPolls; $i++) {
                sleep(1);
                $pollCh = curl_init('https://api.assemblyai.com/v2/transcript/' . $transcriptId);
                curl_setopt_array($pollCh, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => ['Authorization: ' . $apiKey],
                    CURLOPT_TIMEOUT => 5
                ]);
                $pollResp = curl_exec($pollCh);
                curl_close($pollCh);

                $pollData = json_decode($pollResp, true);
                $status = $pollData['status'] ?? '';

                if ($status === 'completed') {
                    $text = $pollData['text'] ?? '';
                    break;
                } elseif ($status === 'error') {
                    $err = $pollData['error'] ?? 'Transcription failed';
                    @unlink($tmpFile);
                    logVoiceRequest($pdo, $userId, 'AssemblyAI', 'stt', null, $pollResp, 'failed', round((microtime(true) - $startTime) * 1000), $err);
                    echo json_encode(['success' => false, 'error' => $err]);
                    exit;
                }
            }

            @unlink($tmpFile);
            $duration = round((microtime(true) - $startTime) * 1000);

            if ($text !== null) {
                logVoiceRequest($pdo, $userId, 'AssemblyAI', 'stt', json_encode(['language' => $language]), $text, 'success', $duration);
                echo json_encode(['success' => true, 'text' => $text, 'provider' => 'AssemblyAI', 'duration_ms' => $duration]);
            } else {
                logVoiceRequest($pdo, $userId, 'AssemblyAI', 'stt', null, 'timeout', 'failed', $duration, 'Transcription timed out');
                echo json_encode(['success' => false, 'error' => 'AssemblyAI transcription timed out']);
            }
        } else {
            // Generic OpenAI-compatible STT
            $ch = curl_init($provider['api_endpoint']);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey],
                CURLOPT_POSTFIELDS => [
                    'file' => new CURLFile($tmpFile, 'audio/' . $ext, 'audio.' . $ext),
                    'model' => $provider['default_model'] ?: 'whisper-1',
                    'language' => $language
                ],
                CURLOPT_TIMEOUT => 60
            ]);
            $response = curl_exec($ch);
            $curlErr = curl_error($ch);
            curl_close($ch);
            @unlink($tmpFile);

            $decoded = json_decode($response, true);
            $text = $decoded['text'] ?? null;
            $duration = round((microtime(true) - $startTime) * 1000);

            if ($text) {
                logVoiceRequest($pdo, $userId, $provider['provider_name'], 'stt', json_encode(['language' => $language]), $text, 'success', $duration);
                echo json_encode(['success' => true, 'text' => $text, 'provider' => $provider['provider_name'], 'duration_ms' => $duration]);
            } else {
                logVoiceRequest($pdo, $userId, $provider['provider_name'], 'stt', null, $response, 'failed', $duration, $curlErr ?: 'STT failed');
                echo json_encode(['success' => false, 'error' => $curlErr ?: 'STT failed']);
            }
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'STT Error: ' . $e->getMessage()]);
    }
    exit;
}

// === Action: TTS (Text to Speech) ===
if ($action === 'tts') {
    $text = trim($input['text'] ?? '');
    $voice = $input['voice'] ?? getVoiceSetting($pdo, 'default_voice', 'alloy');
    $speed = (float)($input['speed'] ?? getVoiceSetting($pdo, 'voice_speed', '1.0'));

    if (empty($text)) {
        echo json_encode(['success' => false, 'error' => 'No text provided']);
        exit;
    }

    $provider = getActiveVoiceProvider($pdo, 'tts');
    if (!$provider) {
        echo json_encode(['success' => false, 'error' => 'No active TTS provider configured.']);
        exit;
    }

    $apiKey = $provider['api_key'];
    $startTime = microtime(true);

    try {
        $providerName = strtolower($provider['provider_name']);

        if (strpos($providerName, 'openai') !== false) {
            // OpenAI TTS API
            $ch = curl_init('https://api.openai.com/v1/audio/speech');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json'
                ],
                CURLOPT_POSTFIELDS => json_encode([
                    'model' => $provider['default_model'] ?: 'tts-1',
                    'input' => $text,
                    'voice' => $voice,
                    'speed' => $speed,
                    'response_format' => 'mp3'
                ]),
                CURLOPT_TIMEOUT => 60
            ]);
            $audioData = curl_exec($ch);
            $curlErr = curl_error($ch);
            curl_close($ch);

            $duration = round((microtime(true) - $startTime) * 1000);

            if ($audioData && !$curlErr && strlen($audioData) > 1000) {
                $b64 = base64_encode($audioData);
                logVoiceRequest($pdo, $userId, $provider['provider_name'], 'tts', json_encode(['text' => mb_strimwidth($text, 0, 200), 'voice' => $voice]), 'audio_response', 'success', $duration);
                echo json_encode(['success' => true, 'audio' => $b64, 'format' => 'mp3', 'provider' => $provider['provider_name'], 'duration_ms' => $duration]);
            } else {
                logVoiceRequest($pdo, $userId, $provider['provider_name'], 'tts', null, $audioData, 'failed', $duration, $curlErr ?: 'TTS failed');
                echo json_encode(['success' => false, 'error' => $curlErr ?: 'TTS generation failed']);
            }
        } elseif (strpos($providerName, 'elevenlabs') !== false) {
            // ElevenLabs TTS
            $voiceId = $provider['default_voice'] ?: '21m00Tcm4TlvDq8ikWAM';
            $ch = curl_init("https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'xi-api-key: ' . $apiKey,
                    'Content-Type: application/json',
                    'Accept: audio/mpeg'
                ],
                CURLOPT_POSTFIELDS => json_encode([
                    'text' => $text,
                    'model_id' => $provider['default_model'] ?: 'eleven_monolingual_v1',
                    'voice_settings' => ['stability' => 0.5, 'similarity_boost' => 0.75]
                ]),
                CURLOPT_TIMEOUT => 60
            ]);
            $audioData = curl_exec($ch);
            $curlErr = curl_error($ch);
            curl_close($ch);

            $duration = round((microtime(true) - $startTime) * 1000);

            if ($audioData && !$curlErr && strlen($audioData) > 1000) {
                $b64 = base64_encode($audioData);
                logVoiceRequest($pdo, $userId, $provider['provider_name'], 'tts', json_encode(['text' => mb_strimwidth($text, 0, 200)]), 'audio_response', 'success', $duration);
                echo json_encode(['success' => true, 'audio' => $b64, 'format' => 'mp3', 'provider' => $provider['provider_name'], 'duration_ms' => $duration]);
            } else {
                logVoiceRequest($pdo, $userId, $provider['provider_name'], 'tts', null, substr($audioData ?? '', 0, 500), 'failed', $duration, $curlErr ?: 'ElevenLabs TTS failed');
                echo json_encode(['success' => false, 'error' => $curlErr ?: 'TTS generation failed']);
            }
        } else {
            // Generic TTS endpoint
            $ch = curl_init($provider['api_endpoint']);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json'
                ],
                CURLOPT_POSTFIELDS => json_encode([
                    'model' => $provider['default_model'] ?: 'tts-1',
                    'input' => $text,
                    'voice' => $voice,
                    'speed' => $speed,
                    'response_format' => 'mp3'
                ]),
                CURLOPT_TIMEOUT => 60
            ]);
            $audioData = curl_exec($ch);
            $curlErr = curl_error($ch);
            curl_close($ch);

            $duration = round((microtime(true) - $startTime) * 1000);

            if ($audioData && !$curlErr && strlen($audioData) > 1000) {
                $b64 = base64_encode($audioData);
                logVoiceRequest($pdo, $userId, $provider['provider_name'], 'tts', json_encode(['text' => mb_strimwidth($text, 0, 200)]), 'audio_response', 'success', $duration);
                echo json_encode(['success' => true, 'audio' => $b64, 'format' => 'mp3', 'provider' => $provider['provider_name'], 'duration_ms' => $duration]);
            } else {
                logVoiceRequest($pdo, $userId, $provider['provider_name'], 'tts', null, '', 'failed', $duration, $curlErr ?: 'TTS failed');
                echo json_encode(['success' => false, 'error' => $curlErr ?: 'TTS generation failed']);
            }
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'TTS Error: ' . $e->getMessage()]);
    }
    exit;
}

// === Action: voice_chat (STT + AI + TTS pipeline) ===
if ($action === 'voice_chat') {
    $audioData = $input['audio'] ?? '';
    $audioFormat = $input['format'] ?? 'webm';
    $language = $input['language'] ?? 'en';
    $autoTts = $input['auto_tts'] ?? true;

    if (empty($audioData)) {
        echo json_encode(['success' => false, 'error' => 'No audio data']);
        exit;
    }

    // Step 1: STT
    $sttProvider = getActiveVoiceProvider($pdo, 'stt');
    if (!$sttProvider) {
        echo json_encode(['success' => false, 'error' => 'No active STT provider.']);
        exit;
    }

    $sttStartTime = microtime(true);
    try {
        $audioBytes = base64_decode($audioData);
        $tmpFile = tempnam(sys_get_temp_dir(), 'voice_stt_');
        $ext = 'webm';
        if (strpos($audioFormat, 'ogg') !== false) $ext = 'ogg';
        elseif (strpos($audioFormat, 'wav') !== false) $ext = 'wav';
        elseif (strpos($audioFormat, 'mp4') !== false || strpos($audioFormat, 'm4a') !== false) $ext = 'mp4';
        rename($tmpFile, $tmpFile . '.' . $ext);
        $tmpFile = $tmpFile . '.' . $ext;
        file_put_contents($tmpFile, $audioBytes);

        $pName = strtolower($sttProvider['provider_name']);
        if (strpos($pName, 'deepgram') !== false) {
            $ch = curl_init('https://api.deepgram.com/v1/listen');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Authorization: Token ' . $sttProvider['api_key'], 'Content-Type: audio/' . $ext],
                CURLOPT_POSTFIELDS => $audioBytes,
                CURLOPT_TIMEOUT => 60
            ]);
            $resp = curl_exec($ch);
            curl_close($ch);
            $dec = json_decode($resp, true);
            $userText = $dec['results']['channels'][0]['alternatives'][0]['transcript'] ?? '';
        } else {
            $ch = curl_init($sttProvider['api_endpoint'] ?: 'https://api.openai.com/v1/audio/transcriptions');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $sttProvider['api_key']],
                CURLOPT_POSTFIELDS => [
                    'file' => new CURLFile($tmpFile, 'audio/' . $ext, 'audio.' . $ext),
                    'model' => $sttProvider['default_model'] ?: 'whisper-1',
                    'language' => $language
                ],
                CURLOPT_TIMEOUT => 60
            ]);
            $resp = curl_exec($ch);
            curl_close($ch);
            $dec = json_decode($resp, true);
            $userText = $dec['text'] ?? '';
        }
        @unlink($tmpFile);
        $sttDuration = round((microtime(true) - $sttStartTime) * 1000);

        if (empty($userText)) {
            echo json_encode(['success' => false, 'error' => 'Could not understand the audio. Please try again.']);
            exit;
        }

        logVoiceRequest($pdo, $userId, $sttProvider['provider_name'], 'stt', json_encode(['lang' => $language]), $userText, 'success', $sttDuration);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'STT failed: ' . $e->getMessage()]);
        exit;
    }

    // Step 2: Send transcribed text to existing WiseBot backend
    $aiStartTime = microtime(true);
    try {
        $chatPayload = json_encode(['message' => $userText]);
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $basePath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/\\');
        $agentUrl = $protocol . '://' . $host . $basePath . '/agent-server.php';

        // Build cookie string from current session
        $cookieStr = '';
        foreach ($_COOKIE as $k => $v) { $cookieStr .= $k . '=' . $v . '; '; }

        $ch = curl_init($agentUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Cookie: ' . $cookieStr
            ],
            CURLOPT_POSTFIELDS => $chatPayload,
            CURLOPT_TIMEOUT => 60
        ]);
        $agentResponse = curl_exec($ch);
        $curlErr = curl_error($ch);
        curl_close($ch);

        $aiDuration = round((microtime(true) - $aiStartTime) * 1000);

        if ($agentResponse === false || empty($agentResponse)) {
            echo json_encode(['success' => false, 'error' => 'AI backend unreachable: ' . ($curlErr ?: 'empty response')]);
            exit;
        }

        $agentData = json_decode($agentResponse, true);
        $botReply = $agentData['reply'] ?? 'I could not process that request.';

        // Strip markdown formatting for TTS
        $ttsText = preg_replace('/\[.*?\]/', '', $botReply);
        $ttsText = preg_replace('/```[\s\S]*?```/', '', $ttsText);
        $ttsText = preg_replace('/\*\*(.*?)\*\*/', '$1', $ttsText);
        $ttsText = preg_replace('/`([^`]+)`/', '$1', $ttsText);
        $ttsText = strip_tags($ttsText);
        $ttsText = preg_replace('/\s+/', ' ', trim($ttsText));
        if (strlen($ttsText) > 2000) $ttsText = substr($ttsText, 0, 2000) . '...';

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'AI processing failed: ' . $e->getMessage()]);
        exit;
    }

    // Step 3: TTS (if auto_tts enabled)
    $ttsAudio = null;
    $ttsDuration = 0;
    if ($autoTts && !empty($ttsText)) {
        $ttsProvider = getActiveVoiceProvider($pdo, 'tts');
        if ($ttsProvider) {
            $ttsStartTime = microtime(true);
            try {
                $voice = getVoiceSetting($pdo, 'default_voice', 'alloy');
                $speed = (float)getVoiceSetting($pdo, 'voice_speed', '1.0');
                $pName = strtolower($ttsProvider['provider_name']);

                if (strpos($pName, 'elevenlabs') !== false) {
                    $voiceId = $ttsProvider['default_voice'] ?: '21m00Tcm4TlvDq8ikWAM';
                    $ch = curl_init("https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}");
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_POST => true,
                        CURLOPT_HTTPHEADER => ['xi-api-key: ' . $ttsProvider['api_key'], 'Content-Type: application/json', 'Accept: audio/mpeg'],
                        CURLOPT_POSTFIELDS => json_encode(['text' => $ttsText, 'model_id' => $ttsProvider['default_model'] ?: 'eleven_monolingual_v1', 'voice_settings' => ['stability' => 0.5, 'similarity_boost' => 0.75]]),
                        CURLOPT_TIMEOUT => 60
                    ]);
                } else {
                    $ch = curl_init($ttsProvider['api_endpoint'] ?: 'https://api.openai.com/v1/audio/speech');
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_POST => true,
                        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $ttsProvider['api_key'], 'Content-Type: application/json'],
                        CURLOPT_POSTFIELDS => json_encode(['model' => $ttsProvider['default_model'] ?: 'tts-1', 'input' => $ttsText, 'voice' => $voice, 'speed' => $speed, 'response_format' => 'mp3']),
                        CURLOPT_TIMEOUT => 60
                    ]);
                }

                $audioResp = curl_exec($ch);
                curl_close($ch);
                $ttsDuration = round((microtime(true) - $ttsStartTime) * 1000);

                if ($audioResp && strlen($audioResp) > 1000) {
                    $ttsAudio = base64_encode($audioResp);
                    logVoiceRequest($pdo, $userId, $ttsProvider['provider_name'], 'tts', mb_strimwidth($ttsText, 0, 200), 'audio_response', 'success', $ttsDuration);
                }
            } catch (Exception $e) {}
        }
    }

    // Step 4: Log the voice conversation
    try {
        $convStmt = $pdo->prepare("INSERT INTO voice_conversations (user_id, session_id, provider_name, conversation_text, audio_duration, stt_duration, tts_duration, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'completed')");
        $convStmt->execute([$userId, $sessionId, $sttProvider['provider_name'], "USER: {$userText}\nBOT: {$botReply}", 0, $sttDuration, $ttsDuration]);
    } catch (Exception $e) {}

    // Save to bot_chats for chat history
    try {
        $pdo->prepare("INSERT INTO bot_chats (session_id, user_id, role, message) VALUES (?, ?, 'user', ?)")
            ->execute([$sessionId, $userId, '[Voice] ' . $userText]);
        $pdo->prepare("INSERT INTO bot_chats (session_id, user_id, role, message) VALUES (?, ?, 'bot', ?)")
            ->execute([$sessionId, $userId, $botReply]);
    } catch (Exception $e) {}

    echo json_encode([
        'success' => true,
        'user_text' => $userText,
        'reply' => $botReply,
        'audio' => $ttsAudio,
        'audio_format' => 'mp3',
        'stt_ms' => $sttDuration,
        'tts_ms' => $ttsDuration,
        'ai_ms' => $aiDuration
    ]);
    exit;
}

// === Action: get_stats ===
if ($action === 'get_stats') {
    if (!isset($_SESSION['user']['role']) || strtolower($_SESSION['user']['role']) !== 'admin') {
        echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
        exit;
    }
    try {
        $totalChats = $pdo->query("SELECT COUNT(*) FROM voice_conversations")->fetchColumn();
        $totalMinutes = $pdo->query("SELECT COALESCE(SUM(audio_duration), 0) FROM voice_conversations")->fetchColumn();
        $todayChats = $pdo->query("SELECT COUNT(*) FROM voice_conversations WHERE DATE(created_at) = CURDATE()")->fetchColumn();
        $providerUsage = $pdo->query("SELECT provider_name, COUNT(*) as cnt FROM voice_conversations GROUP BY provider_name ORDER BY cnt DESC")->fetchAll(PDO::FETCH_ASSOC);
        $errorCount = $pdo->query("SELECT COUNT(*) FROM voice_logs WHERE status = 'failed'")->fetchColumn();
        $successCount = $pdo->query("SELECT COUNT(*) FROM voice_logs WHERE status = 'success'")->fetchColumn();

        echo json_encode([
            'success' => true,
            'stats' => [
                'total_chats' => (int)$totalChats,
                'total_minutes' => round((float)$totalMinutes / 60, 1),
                'today_chats' => (int)$todayChats,
                'provider_usage' => $providerUsage,
                'total_errors' => (int)$errorCount,
                'total_successes' => (int)$successCount,
                'success_rate' => ($successCount + $errorCount) > 0 ? round($successCount / ($successCount + $errorCount) * 100, 1) : 100
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action']);
?>