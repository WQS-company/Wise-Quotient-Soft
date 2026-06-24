<?php
session_start();
$path_to_root = "../";
require_once $path_to_root . 'config.php';

if (!isset($_SESSION['user']['id']) || strtolower($_SESSION['user']['role']) !== 'admin') {
    header("Location: " . $path_to_root . "login.php");
    exit;
}

$success_msg = '';
$error_msg = '';

// Fetch all voice providers
$providers = [];
$stats = ['total' => 0, 'active_name' => 'None', 'conversations' => 0, 'success_rate' => 0];
$voice_settings = [
    'default_voice' => 'alloy',
    'auto_play' => 1,
    'voice_speed' => 1.0,
    'noise_reduction' => 1,
    'vad_enabled' => 1
];

if (isset($pdo)) {
    try {
        $providers = $pdo->query("SELECT * FROM voice_providers ORDER BY is_active DESC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
        
        // Seed default Voice Providers if empty
        if (empty($providers)) {
            $defaults = [
                ['OpenAI Realtime', 'S2S', 'wss://api.openai.com/v1/realtime', '', 'gpt-4o-realtime-preview', 'alloy', 'wss://api.openai.com/v1/realtime'],
                ['ElevenLabs', 'TTS', 'https://api.elevenlabs.io/v1/text-to-speech', '', 'eleven_monolingual_v1', 'Rachel', ''],
                ['OpenAI Whisper', 'STT', 'https://api.openai.com/v1/audio/transcriptions', '', 'whisper-1', '', ''],
                ['Vapi', 'S2S', 'https://api.vapi.ai/v1', '', 'vapi-default', 'alloy', ''],
                ['Retell AI', 'S2S', 'https://api.retellai.com/v1', '', 'retell-default', '11labs-rachel', ''],
                ['Deepgram', 'STT', 'https://api.deepgram.com/v1/listen', '', 'nova-2', '', 'wss://api.deepgram.com/v1/listen'],
                ['PlayHT', 'TTS', 'https://api.play.ht/api/v2/tts', '', 'PlayHT2.0', 'larry', ''],
                ['Azure Speech', 'STT_TTS', 'https://eastus.api.cognitive.microsoft.com/sts/v1.0/issuetoken', '', 'azure-default', 'en-US-AriaNeural', ''],
                ['Groq Whisper (Free)', 'STT', 'https://api.groq.com/openai/v1/audio/transcriptions', '', 'whisper-large-v3', '', ''],
                ['HuggingFace (Free)', 'TTS', 'https://api-inference.huggingface.co/models/facebook/mms-tts-eng', '', 'facebook/mms-tts-eng', '', ''],
                ['Cartesia (Free Tier)', 'TTS', 'wss://api.cartesia.ai/tts/websocket', '', 'sonic-english', '', 'wss://api.cartesia.ai/tts/websocket'],
                ['AssemblyAI (Free Tier)', 'STT', 'https://api.assemblyai.com/v2/transcript', '', 'assemblyai-default', '', 'wss://api.assemblyai.com/v2/realtime']
            ];
            $stmt = $pdo->prepare("INSERT INTO voice_providers (provider_name, provider_type, api_endpoint, api_key, default_model, default_voice, ws_endpoint, status, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 'untested', 0)");
            foreach ($defaults as $d) {
                $stmt->execute($d);
            }
            $providers = $pdo->query("SELECT * FROM voice_providers ORDER BY is_active DESC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        // Table may not exist yet
    }

    try {
        $row = $pdo->query("SELECT COUNT(*) FROM voice_providers")->fetch(PDO::FETCH_NUM);
        $stats['total'] = $row[0] ?? 0;
    } catch (Exception $e) {}

    foreach ($providers as $p) {
        if ((bool)$p['is_active']) {
            $stats['active_name'] = $p['provider_name'];
        }
    }

    try {
        $row = $pdo->query("SELECT COUNT(*), COALESCE(SUM(CASE WHEN status='success' THEN 1 ELSE 0 END), 0) FROM voice_conversations")->fetch(PDO::FETCH_NUM);
        $stats['conversations'] = $row[0] ?? 0;
        $stats['success_rate'] = $row[0] > 0 ? round(($row[1] / $row[0]) * 100, 1) : 0;
    } catch (Exception $e) {}

    try {
        $row = $pdo->query("SELECT * FROM voice_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $voice_settings = array_merge($voice_settings, $row);
        }
    } catch (Exception $e) {}
}

function getVoiceProviderIcon($name) {
    $n = strtolower($name);
    if (strpos($n, 'openai') !== false) return '<i class="fas fa-brain"></i>';
    if (strpos($n, 'elevenlabs') !== false) return '<i class="fas fa-wave-square"></i>';
    if (strpos($n, 'vapi') !== false) return '<i class="fas fa-phone-volume"></i>';
    if (strpos($n, 'retell') !== false) return '<i class="fas fa-headset"></i>';
    if (strpos($n, 'deepgram') !== false) return '<i class="fas fa-assistive-listening-systems"></i>';
    if (strpos($n, 'playht') !== false) return '<i class="fas fa-play-circle"></i>';
    if (strpos($n, 'azure') !== false) return '<i class="fab fa-microsoft"></i>';
    if (strpos($n, 'groq') !== false) return '<i class="fas fa-bolt"></i>';
    if (strpos($n, 'huggingface') !== false) return '<i class="far fa-smile-beam"></i>';
    if (strpos($n, 'cartesia') !== false) return '<i class="fas fa-stream"></i>';
    if (strpos($n, 'assembly') !== false) return '<i class="fas fa-closed-captioning"></i>';
    return '<i class="fas fa-microphone-alt"></i>';
}

function getVoiceProviderKeyUrl($name) {
    $n = strtolower($name);
    if (strpos($n, 'openai') !== false) return 'https://platform.openai.com/api-keys';
    if (strpos($n, 'elevenlabs') !== false) return 'https://elevenlabs.io/app/settings/api-keys';
    if (strpos($n, 'vapi') !== false) return 'https://dashboard.vapi.ai/keys';
    if (strpos($n, 'retell') !== false) return 'https://beta.retellai.com/dashboard';
    if (strpos($n, 'deepgram') !== false) return 'https://console.deepgram.com/';
    if (strpos($n, 'playht') !== false) return 'https://play.ht/studio/api-access';
    if (strpos($n, 'azure') !== false) return 'https://portal.azure.com/';
    if (strpos($n, 'groq') !== false) return 'https://console.groq.com/keys';
    if (strpos($n, 'huggingface') !== false) return 'https://huggingface.co/settings/tokens';
    if (strpos($n, 'cartesia') !== false) return 'https://play.cartesia.ai/keys';
    if (strpos($n, 'assembly') !== false) return 'https://www.assemblyai.com/dashboard/api';
    return '#';
}

function getVoiceTypeBadgeClass($type) {
    switch (strtoupper($type)) {
        case 'STT':     return 'bg-primary';
        case 'TTS':     return 'bg-purple';
        case 'S2S':     return 'bg-warning text-dark';
        case 'STT_TTS': return 'bg-success';
        default:        return 'bg-secondary';
    }
}

function getVoiceTypeBadgeLabel($type) {
    switch (strtoupper($type)) {
        case 'STT_TTS': return 'STT+TTS';
        default:        return strtoupper($type);
    }
}

$page_title = "Voice Providers - WQS Engine";
require_once $path_to_root . 'includes/dashboard_header.php';
?>

<style>
.bg-purple { background-color: #7c3aed !important; color: #fff !important; }
.text-purple { color: #7c3aed !important; }
.hero-voice {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 40%, #0f766e 100%);
    border-radius: 20px;
    padding: 3rem 2rem;
    color: white;
    position: relative;
    overflow: hidden;
    margin-bottom: 2rem;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.15);
}
.hero-voice::before {
    content: '';
    position: absolute;
    top: -50%; right: -10%;
    width: 400px; height: 400px;
    background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
    border-radius: 50%;
}
.hero-voice::after {
    content: '\f3c5';
    font-family: 'Font Awesome 5 Free';
    font-weight: 900;
    position: absolute;
    bottom: -20%; right: 5%;
    font-size: 12rem;
    color: rgba(255,255,255,0.03);
    transform: rotate(-15deg);
}
.stat-card {
    border: none;
    border-radius: 16px;
    background: #ffffff;
    transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
    box-shadow: 0 4px 15px rgba(0,0,0,0.04);
    height: 100%;
}
.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.08);
}
.stat-icon {
    width: 48px; height: 48px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem;
}
.voice-card {
    border: none;
    border-radius: 18px;
    background: #ffffff;
    transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
    box-shadow: 0 4px 15px rgba(0,0,0,0.04);
    position: relative;
    overflow: hidden;
    height: 100%;
    display: flex;
    flex-direction: column;
}
.voice-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.1);
}
.voice-card.active-card {
    border: 2px solid #0f766e;
    box-shadow: 0 8px 25px rgba(15,118,110,0.15);
}
.voice-card.active-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 6px;
    background: linear-gradient(90deg, #0f766e, #14b8a6);
}
.icon-box-voice {
    width: 56px; height: 56px;
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.8rem;
    background: #f1f5f9;
    color: #0f766e;
    box-shadow: inset 0 2px 4px rgba(0,0,0,0.03);
}
.active-card .icon-box-voice {
    background: #0f766e;
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(15,118,110,0.3);
}
.code-snippet {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 6px 12px;
    font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
    font-size: 0.78rem;
    color: #334155;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: block;
}
.btn-premium {
    background: #ffffff;
    color: #0f766e;
    font-weight: 600;
    border: none;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transition: all 0.2s ease;
}
.btn-premium:hover {
    background: #f0fdfa;
    color: #0f766e;
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(0,0,0,0.15);
}
.action-btn {
    width: 36px; height: 36px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    background: #f1f5f9;
    color: #64748b;
    border: none;
    transition: all 0.2s ease;
}
.action-btn:hover {
    background: #e2e8f0;
    color: #0f172a;
}
.action-btn.btn-test:hover { background: #dcfce7; color: #166534; }
.action-btn.btn-activate:hover { background: #d1fae5; color: #065f46; }
.action-btn.btn-edit:hover { background: #dbeafe; color: #1e40af; }
.action-btn.btn-del:hover { background: #fee2e2; color: #991b1b; }
.settings-section {
    border: none;
    border-radius: 18px;
    background: #ffffff;
    box-shadow: 0 4px 15px rgba(0,0,0,0.04);
}
.settings-section .form-check-input:checked {
    background-color: #0f766e;
    border-color: #0f766e;
}
.settings-section .form-range::-webkit-slider-thumb {
    background: #0f766e;
}
.settings-section .form-range::-moz-range-thumb {
    background: #0f766e;
}
</style>

<div class="container-fluid px-3 px-md-4 pb-5">

    <!-- Hero -->
    <div class="hero-voice">
        <div class="row align-items-center position-relative" style="z-index: 1;">
            <div class="col-lg-8">
                <div class="d-inline-flex align-items-center px-3 py-1 rounded-pill mb-3" style="background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); font-size: 0.85rem; font-weight: 600;">
                    <i class="fas fa-microphone-alt text-info me-2"></i> Voice Engine
                </div>
                <h2 class="fw-bold mb-2">AI Voice Providers</h2>
                <p class="mb-0" style="opacity: 0.9; font-size: 1.05rem; max-width: 600px;">
                    Manage voice engines for WiseBot voice agent. Configure Speech-to-Text, Text-to-Speech, and end-to-end voice providers.
                </p>
            </div>
            <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                <button class="btn btn-premium rounded-pill px-4 py-2" data-bs-toggle="modal" data-bs-target="#addProviderModal">
                    <i class="fas fa-plus me-2"></i> Add Voice Provider
                </button>
            </div>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row g-4 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="fas fa-layer-group"></i></div>
                    <div>
                        <div class="text-muted small fw-semibold">Total Providers</div>
                        <div class="h5 fw-bold mb-0 text-body-emphasis"><?= $stats['total'] ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="fas fa-check-circle"></i></div>
                    <div>
                        <div class="text-muted small fw-semibold">Active Provider</div>
                        <div class="h6 fw-bold mb-0 text-body-emphasis text-truncate" style="max-width:140px;"><?= htmlspecialchars($stats['active_name']) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="fas fa-comments"></i></div>
                    <div>
                        <div class="text-muted small fw-semibold">Total Conversations</div>
                        <div class="h5 fw-bold mb-0 text-body-emphasis"><?= number_format($stats['conversations']) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="fas fa-chart-line"></i></div>
                    <div>
                        <div class="text-muted small fw-semibold">Success Rate</div>
                        <div class="h5 fw-bold mb-0 text-body-emphasis"><?= $stats['success_rate'] ?>%</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Voice Settings -->
    <div class="settings-section p-4 mb-4">
        <h5 class="fw-bold text-body-emphasis mb-3"><i class="fas fa-sliders-h me-2 text-primary"></i> Voice Settings</h5>
        <div class="row g-4">
            <div class="col-md-4">
                <label class="form-label fw-semibold small">Default Voice</label>
                <select class="form-select rounded-3" id="voiceDefault">
                    <option value="alloy" <?= ($voice_settings['default_voice'] ?? '') === 'alloy' ? 'selected' : '' ?>>Alloy</option>
                    <option value="echo" <?= ($voice_settings['default_voice'] ?? '') === 'echo' ? 'selected' : '' ?>>Echo</option>
                    <option value="fable" <?= ($voice_settings['default_voice'] ?? '') === 'fable' ? 'selected' : '' ?>>Fable</option>
                    <option value="onyx" <?= ($voice_settings['default_voice'] ?? '') === 'onyx' ? 'selected' : '' ?>>Onyx</option>
                    <option value="nova" <?= ($voice_settings['default_voice'] ?? '') === 'nova' ? 'selected' : '' ?>>Nova</option>
                    <option value="shimmer" <?= ($voice_settings['default_voice'] ?? '') === 'shimmer' ? 'selected' : '' ?>>Shimmer</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold small">Auto Play</label>
                <div class="form-check form-switch mt-2">
                    <input class="form-check-input" type="checkbox" id="voiceAutoPlay" <?= ($voice_settings['auto_play'] ?? 1) ? 'checked' : '' ?>>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold small">Voice Speed: <span id="speedVal"><?= $voice_settings['voice_speed'] ?? '1.0' ?></span>x</label>
                <input type="range" class="form-range" min="0.5" max="2.0" step="0.1" value="<?= $voice_settings['voice_speed'] ?? '1.0' ?>" id="voiceSpeed">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold small">Noise Reduction</label>
                <div class="form-check form-switch mt-2">
                    <input class="form-check-input" type="checkbox" id="noiseReduction" <?= ($voice_settings['noise_reduction'] ?? 1) ? 'checked' : '' ?>>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold small">VAD (Voice Activity Detection)</label>
                <div class="form-check form-switch mt-2">
                    <input class="form-check-input" type="checkbox" id="vadEnabled" <?= ($voice_settings['vad_enabled'] ?? 1) ? 'checked' : '' ?>>
                </div>
            </div>
            <div class="col-md-8 d-flex align-items-end justify-content-end">
                <button class="btn btn-primary rounded-pill px-4 fw-semibold" onclick="saveVoiceSettings()" style="background:#0f766e;border:none;">
                    <i class="fas fa-save me-1"></i> Save Settings
                </button>
            </div>
        </div>
    </div>

    <!-- Provider Cards -->
    <?php if (empty($providers)): ?>
    <div class="text-center py-5">
        <div class="mb-3"><i class="fas fa-microphone-alt text-muted" style="font-size:3rem;opacity:0.3;"></i></div>
        <h5 class="text-muted">No Voice Providers Configured</h5>
        <p class="text-secondary">Click "Add Voice Provider" to integrate your first voice engine.</p>
    </div>
    <?php else: ?>
    <div class="row g-4">
        <?php foreach ($providers as $p):
            $isActive = (bool)$p['is_active'];
            $type = strtoupper($p['provider_type'] ?? 'TTS');
        ?>
        <div class="col-md-6 col-xl-4" id="provider-col-<?= $p['id'] ?>">
            <div class="voice-card <?= $isActive ? 'active-card' : '' ?>">
                <div class="card-body p-4 d-flex flex-column">

                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="icon-box-voice"><?= getVoiceProviderIcon($p['provider_name']) ?></div>
                            <div>
                                <h5 class="fw-bold text-body-emphasis mb-1" style="font-size: 1.1rem; letter-spacing: -0.3px;"><?= htmlspecialchars($p['provider_name']) ?></h5>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge <?= getVoiceTypeBadgeClass($type) ?> rounded-pill px-2 py-1" style="font-size: 0.68rem;"><?= getVoiceTypeBadgeLabel($type) ?></span>
                                    <?php if (($p['status'] ?? '') === 'healthy'): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2 py-1" style="font-size: 0.68rem;"><i class="fas fa-check-circle me-1"></i>Healthy</span>
                                    <?php elseif (($p['status'] ?? '') === 'failing'): ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-2 py-1" style="font-size: 0.68rem;"><i class="fas fa-times-circle me-1"></i>Failing</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-2 py-1" style="font-size: 0.68rem;"><i class="fas fa-clock me-1"></i>Untested</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php if ($isActive): ?>
                            <div class="text-success fw-bold small bg-success bg-opacity-10 px-3 py-1 rounded-pill">
                                <i class="fas fa-circle text-success me-1" style="font-size: 0.5rem; vertical-align: middle;"></i> ACTIVE
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="flex-grow-1">
                        <div class="mb-3">
                            <label class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.5px; font-size: 0.65rem;">API Endpoint</label>
                            <span class="code-snippet mt-1" title="<?= htmlspecialchars($p['api_endpoint'] ?? '') ?>"><?= htmlspecialchars($p['api_endpoint'] ?? '') ?></span>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.5px; font-size: 0.65rem;">Default Model</label>
                                <div class="fw-medium text-body small"><i class="fas fa-brain text-secondary opacity-50 me-1"></i> <?= htmlspecialchars($p['default_model'] ?? 'N/A') ?></div>
                            </div>
                            <div class="col-6">
                                <label class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.5px; font-size: 0.65rem;">Default Voice</label>
                                <div class="fw-medium text-body small"><i class="fas fa-user-circle text-secondary opacity-50 me-1"></i> <?= htmlspecialchars($p['default_voice'] ?? 'N/A') ?></div>
                            </div>
                        </div>
                        <div>
                            <label class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.5px; font-size: 0.65rem;">API Key</label>
                            <div class="d-flex align-items-center justify-content-between mt-1">
                                <div class="fw-medium" style="font-family: monospace;">
                                    <?php if (!empty($p['api_key'])): ?>
                                        <span class="text-success"><i class="fas fa-lock me-1"></i>&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;<?= substr($p['api_key'], -4) ?></span>
                                    <?php else: ?>
                                        <span class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>Key Required</span>
                                    <?php endif; ?>
                                </div>
                                <?php $keyUrl = getVoiceProviderKeyUrl($p['provider_name']); if ($keyUrl !== '#'): ?>
                                    <a href="<?= $keyUrl ?>" target="_blank" class="text-primary small fw-semibold text-decoration-none">Get Key <i class="fas fa-external-link-alt ms-1" style="font-size:0.7rem;"></i></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-top d-flex align-items-center gap-2">
                        <button type="button" class="action-btn btn-test" title="Test Connection" onclick="testVoiceHealth(<?= $p['id'] ?>, this)">
                            <i class="fas fa-heartbeat"></i>
                        </button>
                        <?php if (!$isActive): ?>
                            <button type="button" class="action-btn btn-activate" title="Set as Active" onclick="setActive(<?= $p['id'] ?>)">
                                <i class="fas fa-plug"></i>
                            </button>
                        <?php else: ?>
                            <button type="button" class="action-btn btn-deactivate" title="Deactivate" onclick="setInactive(<?= $p['id'] ?>)" style="background: #fee2e2; color: #dc2626;">
                                <i class="fas fa-times"></i>
                            </button>
                        <?php endif; ?>
                        <button type="button" class="action-btn btn-edit" title="Edit Provider" data-bs-toggle="modal" data-bs-target="#editProviderModal"
                                data-id="<?= $p['id'] ?>"
                                data-name="<?= htmlspecialchars($p['provider_name'] ?? '') ?>"
                                data-type="<?= htmlspecialchars($p['provider_type'] ?? 'TTS') ?>"
                                data-endpoint="<?= htmlspecialchars($p['api_endpoint'] ?? '') ?>"
                                data-model="<?= htmlspecialchars($p['default_model'] ?? '') ?>"
                                data-voice="<?= htmlspecialchars($p['default_voice'] ?? '') ?>"
                                data-key="<?= !empty($p['api_key']) ? '********' : '' ?>"
                                data-ws="<?= htmlspecialchars($p['ws_endpoint'] ?? '') ?>">
                            <i class="fas fa-pen"></i>
                        </button>
                        <?php if (!$isActive): ?>
                            <button type="button" class="action-btn btn-del" title="Delete Provider" onclick="deleteProvider(<?= $p['id'] ?>)">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Add Provider Modal -->
<div class="modal fade" id="addProviderModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="text-white p-4 pb-3" style="background: linear-gradient(135deg, #0f172a 0%, #0f766e 100%);">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="modal-title fw-bold"><i class="fas fa-microphone-alt me-2 text-info"></i> Add Voice Provider</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <p class="small mb-0 mt-2 opacity-75">Integrate a speech or voice engine into the WiseBot voice agent.</p>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-bold text-dark small">Provider Name</label>
                    <input type="text" id="add_name" class="form-control form-control-lg rounded-3 bg-light" placeholder="e.g. OpenAI Whisper, ElevenLabs" required style="font-size: 0.95rem;">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold text-dark small">Provider Type</label>
                    <select id="add_type" class="form-select form-select-lg rounded-3 bg-light" required style="font-size: 0.95rem;">
                        <option value="">Select type...</option>
                        <option value="STT">STT - Speech to Text</option>
                        <option value="TTS">TTS - Text to Speech</option>
                        <option value="S2S">S2S - Speech to Speech</option>
                        <option value="STT_TTS">STT + TTS - Combined</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold text-dark small">API Endpoint</label>
                    <input type="url" id="add_endpoint" class="form-control form-control-lg rounded-3 bg-light" placeholder="https://api.example.com/v1/audio/transcriptions" required style="font-size: 0.85rem;">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold text-dark small">API Secret Key</label>
                    <input type="password" id="add_key" class="form-control form-control-lg rounded-3 bg-light" placeholder="sk-..." required style="font-size: 0.95rem;">
                </div>
                <div class="row g-3">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-dark small">Default Model</label>
                        <input type="text" id="add_model" class="form-control form-control-lg rounded-3 bg-light" placeholder="e.g. whisper-1, eleven_monolingual_v1" style="font-size: 0.95rem;">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-dark small">Default Voice</label>
                        <input type="text" id="add_voice" class="form-control form-control-lg rounded-3 bg-light" placeholder="e.g. alloy, Rachel" style="font-size: 0.95rem;">
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label fw-bold text-dark small">WebSocket Endpoint <span class="text-muted fw-normal">(optional)</span></label>
                    <input type="url" id="add_ws" class="form-control form-control-lg rounded-3 bg-light" placeholder="wss://api.example.com/v1/realtime" style="font-size: 0.85rem;">
                </div>
            </div>
            <div class="modal-footer border-top-0 p-4 pt-0 bg-white">
                <button type="button" class="btn btn-light rounded-pill px-4 fw-semibold" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary rounded-pill px-4 fw-semibold" onclick="addProvider()" style="background:#0f766e;border:none;">Save Provider</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Provider Modal -->
<div class="modal fade" id="editProviderModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="bg-light p-4 pb-3 border-bottom">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="modal-title fw-bold text-body-emphasis"><i class="fas fa-pen me-2 text-primary"></i> Edit Voice Provider</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="edit_id">
                <div class="mb-3">
                    <label class="form-label fw-bold text-dark small">Provider Name</label>
                    <input type="text" id="edit_name" class="form-control form-control-lg rounded-3" required style="font-size: 0.95rem;">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold text-dark small">Provider Type</label>
                    <select id="edit_type" class="form-select form-select-lg rounded-3" required style="font-size: 0.95rem;">
                        <option value="STT">STT - Speech to Text</option>
                        <option value="TTS">TTS - Text to Speech</option>
                        <option value="S2S">S2S - Speech to Speech</option>
                        <option value="STT_TTS">STT + TTS - Combined</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold text-dark small">API Endpoint</label>
                    <input type="url" id="edit_endpoint" class="form-control form-control-lg rounded-3" required style="font-size: 0.85rem;">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold text-dark small">API Secret Key</label>
                    <input type="password" id="edit_key" class="form-control form-control-lg rounded-3" placeholder="Leave as ******** to keep current" style="font-size: 0.95rem;">
                </div>
                <div class="row g-3">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-dark small">Default Model</label>
                        <input type="text" id="edit_model" class="form-control form-control-lg rounded-3" style="font-size: 0.95rem;">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-dark small">Default Voice</label>
                        <input type="text" id="edit_voice" class="form-control form-control-lg rounded-3" style="font-size: 0.95rem;">
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label fw-bold text-dark small">WebSocket Endpoint <span class="text-muted fw-normal">(optional)</span></label>
                    <input type="url" id="edit_ws" class="form-control form-control-lg rounded-3" style="font-size: 0.85rem;">
                </div>
            </div>
            <div class="modal-footer border-top-0 p-4 pt-0">
                <button type="button" class="btn btn-light rounded-pill px-4 fw-semibold border" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary rounded-pill px-4 fw-semibold" onclick="editProvider()" style="background:#0f766e;border:none;">Update Provider</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const editModal = document.getElementById('editProviderModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function(event) {
            const btn = event.relatedTarget;
            document.getElementById('edit_id').value = btn.getAttribute('data-id');
            document.getElementById('edit_name').value = btn.getAttribute('data-name');
            document.getElementById('edit_type').value = btn.getAttribute('data-type');
            document.getElementById('edit_endpoint').value = btn.getAttribute('data-endpoint');
            document.getElementById('edit_model').value = btn.getAttribute('data-model');
            document.getElementById('edit_voice').value = btn.getAttribute('data-voice');
            document.getElementById('edit_key').value = btn.getAttribute('data-key');
            document.getElementById('edit_ws').value = btn.getAttribute('data-ws') || '';
        });
    }

    document.getElementById('voiceSpeed').addEventListener('input', function() {
        document.getElementById('speedVal').textContent = parseFloat(this.value).toFixed(1);
    });
});

function apiCall(action, data) {
    data.action = action;
    return fetch('<?= $path_to_root ?>api/voice_providers.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    }).then(r => r.json());
}

function addProvider() {
    const name = document.getElementById('add_name').value.trim();
    const type = document.getElementById('add_type').value;
    const endpoint = document.getElementById('add_endpoint').value.trim();
    const key = document.getElementById('add_key').value.trim();
    const model = document.getElementById('add_model').value.trim();
    const voice = document.getElementById('add_voice').value.trim();
    const ws = document.getElementById('add_ws').value.trim();

    if (!name || !type || !endpoint || !key) {
        Swal.fire({ icon: 'warning', title: 'Missing Fields', text: 'Provider name, type, endpoint, and API key are required.', confirmButtonColor: '#0f766e' });
        return;
    }

    Swal.fire({ title: 'Adding Provider...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    apiCall('add', { provider_name: name, provider_type: type, api_endpoint: endpoint, api_key: key, default_model: model, default_voice: voice, ws_endpoint: ws })
    .then(res => {
        if (res.success) {
            Swal.fire({ icon: 'success', title: 'Provider Added', text: 'Voice provider has been added successfully.', confirmButtonColor: '#0f766e' })
            .then(() => location.reload());
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: res.error || 'Failed to add provider.', confirmButtonColor: '#d33' });
        }
    })
    .catch(() => Swal.fire({ icon: 'error', title: 'Network Error', text: 'Could not reach the server.', confirmButtonColor: '#d33' }));
}

function editProvider() {
    const id = document.getElementById('edit_id').value;
    const name = document.getElementById('edit_name').value.trim();
    const type = document.getElementById('edit_type').value;
    const endpoint = document.getElementById('edit_endpoint').value.trim();
    const key = document.getElementById('edit_key').value.trim();
    const model = document.getElementById('edit_model').value.trim();
    const voice = document.getElementById('edit_voice').value.trim();
    const ws = document.getElementById('edit_ws').value.trim();

    if (!name || !type || !endpoint) {
        Swal.fire({ icon: 'warning', title: 'Missing Fields', text: 'Provider name, type, and endpoint are required.', confirmButtonColor: '#0f766e' });
        return;
    }

    Swal.fire({ title: 'Updating Provider...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    apiCall('edit', { id: id, provider_name: name, provider_type: type, api_endpoint: endpoint, api_key: key, default_model: model, default_voice: voice, ws_endpoint: ws })
    .then(res => {
        if (res.success) {
            Swal.fire({ icon: 'success', title: 'Provider Updated', text: 'Voice provider has been updated successfully.', confirmButtonColor: '#0f766e' })
            .then(() => location.reload());
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: res.error || 'Failed to update provider.', confirmButtonColor: '#d33' });
        }
    })
    .catch(() => Swal.fire({ icon: 'error', title: 'Network Error', text: 'Could not reach the server.', confirmButtonColor: '#d33' }));
}

function setActive(id) {
    Swal.fire({
        title: 'Activate this Provider?',
        text: 'Only one provider per type can be active at a time. This will switch the active provider.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0f766e',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, activate it'
    }).then(result => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'Activating...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            apiCall('set_active', { id: id })
            .then(res => {
                if (res.success) {
                    Swal.fire({ icon: 'success', title: 'Activated', text: 'Voice provider is now active.', confirmButtonColor: '#0f766e' })
                    .then(() => location.reload());
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.error || 'Failed to activate.', confirmButtonColor: '#d33' });
                }
            })
            .catch(() => Swal.fire({ icon: 'error', title: 'Network Error', text: 'Could not reach the server.', confirmButtonColor: '#d33' }));
        }
    });
}

function setInactive(id) {
    Swal.fire({
        title: 'Deactivate this Provider?',
        text: 'This will turn off this voice provider. You can reactivate it later.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, deactivate it'
    }).then(result => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'Deactivating...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            apiCall('set_inactive', { id: id })
            .then(res => {
                if (res.success) {
                    Swal.fire({ icon: 'success', title: 'Deactivated', text: 'Voice provider is now inactive.', confirmButtonColor: '#0f766e' })
                    .then(() => location.reload());
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.error || 'Failed to deactivate.', confirmButtonColor: '#d33' });
                }
            })
            .catch(() => Swal.fire({ icon: 'error', title: 'Network Error', text: 'Could not reach the server.', confirmButtonColor: '#d33' }));
        }
    });
}

function deleteProvider(id) {
    Swal.fire({
        title: 'Delete this Provider?',
        text: 'This action cannot be undone. The provider configuration will be permanently removed.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it'
    }).then(result => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'Deleting...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            apiCall('delete', { id: id })
            .then(res => {
                if (res.success) {
                    Swal.fire({ icon: 'success', title: 'Deleted', text: 'Provider has been deleted.', confirmButtonColor: '#0f766e' })
                    .then(() => location.reload());
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.error || 'Failed to delete.', confirmButtonColor: '#d33' });
                }
            })
            .catch(() => Swal.fire({ icon: 'error', title: 'Network Error', text: 'Could not reach the server.', confirmButtonColor: '#d33' }));
        }
    });
}

function testVoiceHealth(id, btn) {
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    btn.disabled = true;

    fetch('<?= $path_to_root ?>api/voice_health_check.php?id=' + id)
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Swal.fire({ icon: 'success', title: 'Connection Healthy', text: 'The voice provider endpoint is responding correctly.', confirmButtonColor: '#0f766e', customClass: { popup: 'rounded-4' } })
            .then(() => location.reload());
        } else {
            Swal.fire({ icon: 'error', title: 'Connection Failed', text: data.error || 'Provider is not responding.', confirmButtonColor: '#d33', customClass: { popup: 'rounded-4' } })
            .then(() => location.reload());
        }
    })
    .catch(() => {
        Swal.fire('Error', 'Network error during health check.', 'error');
        btn.innerHTML = orig;
        btn.disabled = false;
    });
}

function saveVoiceSettings() {
    const settings = {
        default_voice: document.getElementById('voiceDefault').value,
        auto_play: document.getElementById('voiceAutoPlay').checked ? 1 : 0,
        voice_speed: parseFloat(document.getElementById('voiceSpeed').value),
        noise_reduction: document.getElementById('noiseReduction').checked ? 1 : 0,
        vad_enabled: document.getElementById('vadEnabled').checked ? 1 : 0
    };

    Swal.fire({ title: 'Saving...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    apiCall('update_settings', settings)
    .then(res => {
        if (res.success) {
            Swal.fire({ icon: 'success', title: 'Settings Saved', text: 'Voice settings have been updated.', confirmButtonColor: '#0f766e' });
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: res.error || 'Failed to save settings.', confirmButtonColor: '#d33' });
        }
    })
    .catch(() => Swal.fire({ icon: 'error', title: 'Network Error', text: 'Could not reach the server.', confirmButtonColor: '#d33' }));
}
</script>

<?php require_once $path_to_root . 'includes/dashboard_footer.php'; ?>
