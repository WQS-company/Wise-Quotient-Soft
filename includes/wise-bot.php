<?php
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
if (!isset($path_to_root)) {
    $path_to_root = './';
}
// Compute web-relative path for JavaScript fetch URLs
$_botScript = $_SERVER['SCRIPT_NAME'] ?? '/';
if (strpos($_botScript, '/admin/') !== false) {
    $_botWebPath = '../';
} elseif (strpos($_botScript, '/user/') !== false) {
    $_botWebPath = '../';
} else {
    $_botWebPath = './';
}
require_once rtrim($path_to_root, '/\\') . DIRECTORY_SEPARATOR . 'config.php';

$user_id = $_SESSION['user']['id'] ?? null;
$session_id = session_id();

// Bridge past guest messages under the same session to this user if logged in
if ($user_id) {
    try {
        $bridgeStmt = $pdo->prepare("UPDATE bot_chats SET user_id = ? WHERE session_id = ? AND user_id IS NULL");
        $bridgeStmt->execute([$user_id, $session_id]);
        $bridgeSessStmt = $pdo->prepare("UPDATE bot_chat_sessions SET user_id = ? WHERE session_id = ? AND user_id IS NULL");
        $bridgeSessStmt->execute([$user_id, $session_id]);
    } catch (Exception $e) {
        // Fail-safe
    }
}

// Fetch chat history (last 30 messages) — scoped to current user only
$chatHistory = [];
try {
    if ($user_id) {
        $histStmt = $pdo->prepare("
            SELECT role, message, created_at FROM (
                SELECT role, message, created_at FROM bot_chats 
                WHERE user_id = ? AND is_deleted_by_user = 0
                ORDER BY created_at DESC LIMIT 30
            ) tmp ORDER BY created_at ASC
        ");
        $histStmt->execute([$user_id]);
    } else {
        $histStmt = $pdo->prepare("
            SELECT role, message, created_at FROM (
                SELECT role, message, created_at FROM bot_chats 
                WHERE session_id = ? AND user_id IS NULL AND is_deleted_by_user = 0
                ORDER BY created_at DESC LIMIT 30
            ) tmp ORDER BY created_at ASC
        ");
        $histStmt->execute([$session_id]);
    }
    $chatHistory = $histStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Fail-safe
}

// Personalized greeting
$greetingName = $user_id ? htmlspecialchars($_SESSION['user']['name']) : '';
$greetingText = $greetingName 
    ? "👋 Hello <strong>" . $greetingName . "</strong>! I'm <strong>WiseBot</strong>. How can I help you today?" 
    : "👋 Hello! I'm <strong>WiseBot</strong>. How can I help you today?";

// Message formatting function
if (!function_exists('formatChatMessage')) {
    function formatChatMessage($text, $path_to_root = './') {
        $escaped = htmlspecialchars($text);
        
        // Convert Markdown ```code blocks```
        $escaped = preg_replace('/```([\s\S]*?)```/s', '<pre style="background:rgba(0,0,0,0.05); padding:8px; border-radius:6px; font-size:0.85rem; font-family:monospace; white-space:pre-wrap; margin:8px 0;"><code>$1</code></pre>', $escaped);
        
        // Convert `inline code`
        $escaped = preg_replace('/`(.*?)`/', '<code style="background:rgba(0,0,0,0.05); padding:2px 4px; border-radius:4px; font-family:monospace; font-size:0.9rem;">$1</code>', $escaped);
        
        // Convert Markdown **bold** to <strong>bold</strong> with multiline modifier
        $escaped = preg_replace('/\*\*([\s\S]*?)\*\*/s', '<strong>$1</strong>', $escaped);

        // Convert sub-headings
        $escaped = preg_replace('/### (.*?)\n/', '<h6 class="fw-bold my-2" style="color:#0A2D5E;">$1</h6>', $escaped);
        $escaped = preg_replace('/## (.*?)\n/', '<h5 class="fw-bold my-2" style="color:#0A2D5E;">$1</h5>', $escaped);
        $escaped = preg_replace('/# (.*?)\n/', '<h4 class="fw-bold my-2" style="color:#0A2D5E;">$1</h4>', $escaped);
        
        // Parse Markdown Images: ![alt](url)
        $escaped = preg_replace_callback(
            '/!\[(.*?)\]\((.*?)\)/',
            function($matches) use ($path_to_root) {
                $alt = $matches[1];
                $path = $matches[2];
                $fullPath = (strpos($path, 'http') === 0) ? $path : $path_to_root . $path;
                return '<br><img src="' . $fullPath . '" style="max-width:100%; max-height:220px; border-radius:8px; margin-top:6px; box-shadow:0 2px 6px rgba(0,0,0,0.15);" alt="' . htmlspecialchars($alt) . '" />';
            },
            $escaped
        );

        // Parse Markdown Links: [text](url)
        $escaped = preg_replace_callback(
            '/\[(.*?)\]\((.*?)\)/',
            function($matches) use ($path_to_root) {
                $text = $matches[1];
                $path = $matches[2];
                // Decode &amp; to & if present in raw URL matching
                $path_clean = str_replace('&amp;', '&', $path);
                $fullPath = (strpos($path_clean, 'http') === 0) ? $path_clean : $path_to_root . $path_clean;
                return '<a href="' . $fullPath . '" target="_blank" style="color:#ff6600; font-weight:600; text-decoration:underline;">' . $text . '</a>';
            },
            $escaped
        );

        // Parse [Attached Image: PATH]
        $escaped = preg_replace_callback(
            '/\[Attached Image:\s*(.*?)\]/', 
            function($matches) use ($path_to_root) {
                $path = $matches[1];
                $fullPath = (strpos($path, 'http') === 0) ? $path : $path_to_root . $path;
                return '<br><img src="' . $fullPath . '" style="max-width:100%; max-height:150px; border-radius:8px; margin-top:6px; box-shadow:0 2px 6px rgba(0,0,0,0.15);" alt="Image Attachment" />';
            },
            $escaped
        );
        
        // Parse [Attached File: NAME | PATH]
        $escaped = preg_replace_callback(
            '/\[Attached File:\s*(.*?)\s*\|\s*(.*?)\]/', 
            function($matches) use ($path_to_root) {
                $name = $matches[1];
                $path = $matches[2];
                $fullPath = (strpos($path, 'http') === 0) ? $path : $path_to_root . $path;
                return '<br><a href="' . $fullPath . '" target="_blank" style="display:inline-flex; align-items:center; gap:6px; background:rgba(255,102,0,0.1); color:#ff6600; padding:6px 12px; border-radius:8px; font-weight:600; text-decoration:none; margin-top:6px; border:1px solid rgba(255,102,0,0.25); font-size:0.85rem;"><i class="fas fa-file-pdf"></i> ' . $name . '</a>';
            },
            $escaped
        );
        
        return nl2br($escaped);
    }
}
?>
<!-- WiseQuotient Smart Assistant -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/emoji-button@4.6.4/dist/index.min.css" />
<style>
  :root {
    --chat-primary: #00264d;
    --chat-accent: #ff8800;
    --chat-bg: #ffffff;
    --chat-dark-bg: #1f1f1f;
    --chat-dark-text: #eaeaea;
    --chat-light-text: #1a1a1a;
    --chat-border: #ddd;
    --chat-radius: 16px;
    --chat-shadow: 0 12px 30px rgba(0, 0, 0, 0.25);
  }

  * {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    font-family: "Inter", "Segoe UI", Roboto, sans-serif;
  }

  .assistant-wrapper {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 9000;
  }

  @media (max-width: 575.98px) {
    .assistant-wrapper {
      bottom: 100px;
      right: 16px;
    }
  }

  .assistant-toggle {
    width: 60px;
    height: 60px;
    background: var(--chat-accent);
    border: none;
    border-radius: 50%;
    color: #fff;
    font-size: 1.6rem;
    box-shadow: var(--chat-shadow);
    cursor: pointer;
    position: relative;
    transition: transform 0.3s ease;
  }

  .assistant-toggle:hover {
    transform: scale(1.1);
  }

  .assistant-badge {
    position: absolute;
    top: -6px;
    right: -6px;
    background: #dc3545;
    color: #fff;
    font-size: 0.75rem;
    padding: 2px 6px;
    border-radius: 50%;
    box-shadow: 0 0 6px rgba(0, 0, 0, 0.2);
  }

  .assistant-box {
    position: fixed;
    inset: 0;
    background: var(--chat-bg);
    color: var(--chat-light-text);
    display: none;
    flex-direction: column;
    z-index: 1000;
    animation: fadeIn 0.4s ease;
    transition: all 0.3s ease;
    overflow: hidden;
  }

  .assistant-box.show {
    display: flex !important;
  }

  @media (min-width: 769px) {
    .assistant-box {
      width: 400px;
      height: 520px;
      bottom: 90px;
      right: 20px;
      top: auto;
      left: auto;
      border-radius: var(--chat-radius);
      box-shadow: var(--chat-shadow);
    }
  }

  .assistant-dark .assistant-box {
    background: var(--chat-dark-bg);
    color: var(--chat-dark-text);
  }

  .assistant-header {
    background: var(--chat-primary);
    color: #fff;
    padding: 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top-left-radius: var(--chat-radius);
    border-top-right-radius: var(--chat-radius);
  }

  .assistant-header .btn {
    background: none;
    border: none;
    color: #fff;
    font-size: 1rem;
    cursor: pointer;
    margin-left: 10px;
  }

  .assistant-body {
    padding: 16px;
    flex: 1;
    overflow-y: auto;
    background: #f9f9f9;
    scroll-behavior: smooth;
  }

  .assistant-body::-webkit-scrollbar {
    width: 6px;
  }

  .assistant-body::-webkit-scrollbar-thumb {
    background: rgba(0, 0, 0, 0.15);
    border-radius: 8px;
  }

  .assistant-dark .assistant-body {
    background: #2a2a2a;
  }

  .assistant-footer {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 8px;
    padding: 12px;
    background: #fff;
    border-top: 1px solid var(--chat-border);
    border-bottom-left-radius: var(--chat-radius);
    border-bottom-right-radius: var(--chat-radius);
  }

  .assistant-dark .assistant-footer {
    background: #1c1c1c;
    border-color: #444;
  }

  .chat-input-container {
    flex: 1;
    display: flex;
    align-items: center;
    padding: 8px 12px;
    background-color: #f1f1f1;
    border-radius: 30px;
    gap: 10px;
    position: relative;
  }

  .assistant-dark .chat-input-container {
    background-color: #333;
  }

  .chat-icon {
    background: none;
    border: none;
    font-size: 1.2rem;
    color: var(--chat-primary);
    cursor: pointer;
    padding: 6px;
    border-radius: 50%;
    transition: background 0.2s ease;
  }

  .chat-icon:hover {
    background: rgba(0, 0, 0, 0.08);
  }

  .assistant-dark .chat-icon {
    color: var(--chat-accent);
  }

  #assistantInput {
    flex: 1;
    border: none;
    background: transparent;
    outline: none;
    font-size: 0.95rem;
    resize: none;
    min-height: 38px;
    max-height: 120px;
    overflow-y: hidden;
    line-height: 1.4;
    padding-right: 35px;
    color: inherit;
  }

  .chat-icon:focus,
  #sendBtn:focus {
    outline: 2px solid var(--chat-accent);
    outline-offset: 2px;
  }

  #sendBtn i {
    font-size: 1.3rem;
  }

  .bubble-wrapper {
    display: flex;
    flex-direction: column;
    margin: 6px 0;
    max-width: 80%;
    animation: bubbleIn 0.35s cubic-bezier(0.34, 1.56, 0.64, 1) both;
  }

  .bubble-wrapper.user {
    margin-left: auto;
    align-items: flex-end;
  }

  .bubble-wrapper.bot {
    margin-right: auto;
    align-items: flex-start;
  }

  .bubble {
    padding: 10px 16px;
    word-wrap: break-word;
    word-break: break-word;
    white-space: pre-wrap;
    font-size: 0.88rem;
    line-height: 1.55;
    position: relative;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
  }

  .bubble:hover {
    transform: translateY(-1px);
  }

  .bubble.user {
    background: linear-gradient(135deg, #0A2D5E 0%, #1e4f8f 100%);
    color: #fff;
    border-radius: 18px 18px 4px 18px;
    box-shadow: 0 2px 12px rgba(10, 45, 94, 0.25);
  }

  .bubble.user:hover {
    box-shadow: 0 4px 18px rgba(10, 45, 94, 0.35);
  }

  .assistant-dark .bubble.user {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    box-shadow: 0 2px 12px rgba(59, 130, 246, 0.3);
  }

  .bubble.bot {
    background: #fff;
    color: #1e293b;
    border-radius: 18px 18px 18px 4px;
    box-shadow: 0 1px 8px rgba(0, 0, 0, 0.06);
    border: 1px solid rgba(0, 0, 0, 0.04);
  }

  .bubble.bot:hover {
    box-shadow: 0 3px 14px rgba(0, 0, 0, 0.09);
  }

  .assistant-dark .bubble.bot {
    background: #1e293b;
    color: #e2e8f0;
    border-color: rgba(255, 255, 255, 0.06);
    box-shadow: 0 1px 8px rgba(0, 0, 0, 0.2);
  }

  .msg-time {
    font-size: 0.65rem;
    color: #94a3b8;
    margin-top: 4px;
    padding: 0 4px;
    display: flex;
    align-items: center;
    gap: 4px;
  }

  .bubble.user + .msg-time {
    justify-content: flex-end;
    color: rgba(255, 255, 255, 0.5);
  }

  @keyframes bubbleIn {
    0% {
      opacity: 0;
      transform: translateY(12px) scale(0.96);
    }
    100% {
      opacity: 1;
      transform: translateY(0) scale(1);
    }
  }

  /* History Panel CSS */
  .history-panel {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: var(--chat-bg);
    z-index: 1001;
    transform: translateX(100%);
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), visibility 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    flex-direction: column;
    border-radius: var(--chat-radius);
    overflow: hidden;
    visibility: hidden;
  }
  .history-panel.open {
    transform: translateX(0);
    visibility: visible;
  }
  .history-header {
    background: var(--chat-primary);
    color: #fff;
    padding: 16px;
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .history-list {
    flex: 1;
    overflow-y: auto;
    padding: 12px;
  }
  .history-item {
    padding: 12px;
    border-radius: 8px;
    background: #f8f9fa;
    margin-bottom: 8px;
    cursor: pointer;
    border: 1px solid #eee;
    transition: background 0.2s;
  }
  .history-item:hover { background: #e9ecef; }
  .history-item-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 4px; }
  .history-item .topic { font-weight: 600; font-size: 0.9rem; color: #333; line-height: 1.3; }
  .history-item .date { font-size: 0.75rem; color: #888; white-space: nowrap; margin-left: 8px; }
  .history-del-btn { color: #dc3545; background: none; border: none; padding: 4px; cursor: pointer; border-radius: 4px; float: right; margin-top: -4px;}
  .history-del-btn:hover { background: rgba(220,53,69,0.1); }
  
  .assistant-dark .history-panel { background: var(--chat-dark-bg); }
  .assistant-dark .history-item { background: #2a2a2a; border-color: #444; }
  .assistant-dark .history-item:hover { background: #333; }
  .assistant-dark .history-item .topic { color: #eaeaea; }

  .typing-indicator {
    width: 50px;
    height: 15px;
    margin-top: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .typing-indicator span {
    width: 8px;
    height: 8px;
    background-color: #999;
    border-radius: 50%;
    animation: blink 1.4s infinite;
  }

  .typing-indicator span:nth-child(2) {
    animation-delay: 0.2s;
  }

  .typing-indicator span:nth-child(3) {
    animation-delay: 0.4s;
  }

  /* Agent typing indicator — shows when the human agent is typing */
  .agent-typing-indicator {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 14px;
    margin: 6px 0;
    font-size: 0.78rem;
    color: #94a3b8;
    background: rgba(148,163,184,0.08);
    border-radius: 16px;
    width: fit-content;
    animation: fadeIn 0.3s ease;
  }
  .agent-typing-indicator .dots {
    display: flex;
    gap: 3px;
  }
  .agent-typing-indicator .dots span {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #059669;
    animation: bounce 1s infinite alternate;
  }
  .agent-typing-indicator .dots span:nth-child(2) { animation-delay: 0.15s; }
  .agent-typing-indicator .dots span:nth-child(3) { animation-delay: 0.3s; }

  @keyframes blink {
    0% { opacity: 0.2; }
    20% { opacity: 1; }
    100% { opacity: 0.2; }
  }

  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
  }

  .quick-btns {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 12px;
  }

  .quick-btns button {
    flex: 1;
    border: none;
    background: var(--chat-accent);
    color: #fff;
    padding: 8px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    cursor: pointer;
    transition: background 0.3s ease;
  }

  .quick-btns button:hover {
    background: #e46d00;
  }
  @media (max-width: 768px) {
  .assistant-box {
    border-radius: 0 !important;
  }

  .assistant-header {
    border-top-left-radius: 0 !important;
    border-top-right-radius: 0 !important;
  }

  .assistant-footer {
    border-bottom-left-radius: 0 !important;
    border-bottom-right-radius: 0 !important;
  }
}
 #sendBtn {
     width: 36px;
      height:36px;
    position: absolute;
    right: 10px;
    bottom: 10px;
    z-index: 20;
    font-size: 1.2rem;
    color:white;
    background:var(--chat-accent);
    border: none;
    cursor: pointer;
    display: none;
    border-radius: 50%;
  }
#voiceBtn {
  position: absolute;
   width: 36px;
      height:36px;
  right: 10px;
  bottom: 10px;
  z-index: 20;
  font-size: 1.2rem;
  color:white;
  background:var(--chat-accent);
  border: none;
  cursor: pointer;
  display: block;
  border-radius: 50%;
}

#voiceBtn i {
  font-size: 1.3rem;
}

/* ====== Voice Agent Styles ====== */
@keyframes voicePulse {
  0% { box-shadow: 0 0 0 0 rgba(255,102,0,0.5); }
  70% { box-shadow: 0 0 0 14px rgba(255,102,0,0); }
  100% { box-shadow: 0 0 0 0 rgba(255,102,0,0); }
}
@keyframes voiceGlow {
  0% { box-shadow: 0 0 8px rgba(255,60,60,0.6); }
  50% { box-shadow: 0 0 20px rgba(255,60,60,0.9), 0 0 40px rgba(255,60,60,0.3); }
  100% { box-shadow: 0 0 8px rgba(255,60,60,0.6); }
}
@keyframes waveBar {
  0%, 100% { height: 4px; }
  50% { height: var(--wave-h, 20px); }
}
@keyframes fadeSlideUp {
  from { opacity: 0; transform: translateY(6px); }
  to { opacity: 1; transform: translateY(0); }
}
@keyframes thinkingSpin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

#voiceBtn.listening {
  background: #ff3c3c !important;
  animation: voicePulse 1.5s infinite, voiceGlow 1.2s infinite;
}
#voiceBtn.listening i { animation: none; }

.voice-wave-container {
  display: none;
  align-items: center;
  justify-content: center;
  gap: 3px;
  height: 32px;
  padding: 4px 12px;
  background: rgba(255,60,60,0.08);
  border-radius: 20px;
  margin: 0 8px;
}
.voice-wave-container.active { display: flex; }
.voice-wave-bar {
  width: 3px;
  height: 4px;
  background: #ff3c3c;
  border-radius: 2px;
  animation: waveBar 0.6s ease-in-out infinite;
}
.voice-wave-bar:nth-child(1) { --wave-h: 14px; animation-delay: 0s; }
.voice-wave-bar:nth-child(2) { --wave-h: 22px; animation-delay: 0.1s; }
.voice-wave-bar:nth-child(3) { --wave-h: 18px; animation-delay: 0.2s; }
.voice-wave-bar:nth-child(4) { --wave-h: 26px; animation-delay: 0.3s; }
.voice-wave-bar:nth-child(5) { --wave-h: 16px; animation-delay: 0.4s; }
.voice-wave-bar:nth-child(6) { --wave-h: 22px; animation-delay: 0.15s; }
.voice-wave-bar:nth-child(7) { --wave-h: 14px; animation-delay: 0.25s; }

.voice-status-bar {
  display: none;
  align-items: center;
  gap: 8px;
  padding: 6px 14px;
  font-size: 0.78rem;
  font-weight: 600;
  border-radius: 10px;
  margin: 0 8px 6px;
  animation: fadeSlideUp 0.3s ease;
  white-space: nowrap;
}
.voice-status-bar.active { display: flex; }
.voice-status-bar.listening { background: rgba(255,60,60,0.1); color: #dc2626; }
.voice-status-bar.processing { background: rgba(59,130,246,0.1); color: #2563eb; }
.voice-status-bar.thinking { background: rgba(168,85,247,0.1); color: #9333ea; }
.voice-status-bar.speaking { background: rgba(16,185,129,0.1); color: #059669; }
.voice-status-bar.error { background: rgba(239,68,68,0.1); color: #dc2626; }

.thinking-dots {
  display: inline-flex; gap: 3px; align-items: center;
}
.thinking-dots span {
  width: 5px; height: 5px; border-radius: 50%;
  background: currentColor;
  animation: bounce 1s infinite alternate;
}
.thinking-dots span:nth-child(2) { animation-delay: 0.2s; }
.thinking-dots span:nth-child(3) { animation-delay: 0.4s; }

#speakerToggle {
  position: relative;
  width: 32px;
  height: 32px;
  border: none;
  background: rgba(0,0,0,0.05);
  color: #666;
  border-radius: 50%;
  cursor: pointer;
  font-size: 0.9rem;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
  flex-shrink: 0;
}
#speakerToggle:hover { background: rgba(0,0,0,0.1); }
#speakerToggle.muted { color: #dc2626; }
.assistant-dark #speakerToggle { background: rgba(255,255,255,0.08); color: #aaa; }
.assistant-dark #speakerToggle:hover { background: rgba(255,255,255,0.15); }
.assistant-dark #speakerToggle.muted { color: #ef4444; }

.voice-playing-indicator {
  display: none;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  background: rgba(16,185,129,0.1);
  border-radius: 12px;
  font-size: 0.75rem;
  color: #059669;
  font-weight: 600;
  animation: fadeSlideUp 0.3s ease;
}
.voice-playing-indicator.active { display: inline-flex; }
.voice-playing-indicator .bar {
  width: 2px; height: 8px; background: #059669; border-radius: 1px;
  animation: waveBar 0.5s ease-in-out infinite;
}
.voice-playing-indicator .bar:nth-child(2) { --wave-h: 12px; animation-delay: 0.15s; }
.voice-playing-indicator .bar:nth-child(3) { --wave-h: 6px; animation-delay: 0.3s; }

.assistant-dark #voiceBtn.listening {
  background: #ef4444 !important;
}
.voice-status-bar.listening .assistant-dark { color: #fca5a5; }
.assistant-dark .voice-wave-container { background: rgba(239,68,68,0.15); }
.assistant-dark .voice-wave-bar { background: #ef4444; }

/* ----- Support Mode Styles ----- */
.assistant-header.support-mode {
  background: linear-gradient(135deg, #059669, #10b981);
}
.support-agent-info {
  display: flex; align-items: center; gap: 10px; flex: 1;
}
.support-agent-avatar {
  width: 36px; height: 36px; border-radius: 50%; background: rgba(255,255,255,0.2);
  display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.9rem;
  overflow: hidden; flex-shrink: 0;
}
.support-agent-avatar img { width: 100%; height: 100%; object-fit: cover; }
.support-agent-name { font-weight: 600; font-size: 0.85rem; line-height: 1.2; }
.support-agent-status { font-size: 0.7rem; opacity: 0.8; display: flex; align-items: center; gap: 4px; }
.support-agent-status .dot { width: 6px; height: 6px; border-radius: 50%; background: #34d399; display: inline-block; }
.ticket-badge {
  background: rgba(255,255,255,0.15); border-radius: 50px; padding: 2px 10px; font-size: 0.7rem; font-weight: 600;
  white-space: nowrap;
}
.support-msg-bubble {
  display: inline-block; margin: 8px 0; padding: 10px 14px; border-radius: 16px;
  max-width: 85%; word-wrap: break-word; font-size: 0.9rem; line-height: 1.4;
}
.support-msg-bubble.client { background: #dbeafe; margin-left: auto; border-bottom-right-radius: 4px; }
.support-msg-bubble.agent { background: #f0fdf4; margin-right: auto; border-bottom-left-radius: 4px; }
.assistant-dark .support-msg-bubble.client { background: #1e3a5f; color: #e2e8f0; }
.assistant-dark .support-msg-bubble.agent { background: #064e3b; color: #d1fae5; }
.support-mode-indicator { display: flex; align-items: center; gap: 6px; font-size: 0.75rem; padding: 6px 12px; background: rgba(5,150,105,0.1); border-radius: 8px; margin-bottom: 8px; color: #059669; }
.link-person-btn {
  display: inline-flex; align-items: center; gap: 8px;
  background: linear-gradient(135deg, #059669, #10b981); color: #fff; border: none;
  padding: 10px 18px; border-radius: 12px; font-size: 0.85rem; font-weight: 600;
  cursor: pointer; transition: all 0.3s; margin: 6px 0; width: 100%; justify-content: center;
}
.link-person-btn:hover { transform: scale(1.02); box-shadow: 0 4px 12px rgba(5,150,105,0.3); }
.support-resolve-badge { display: inline-flex; align-items: center; gap: 6px; background: #f0fdf4; color: #15803d; padding: 8px 16px; border-radius: 12px; font-size: 0.85rem; font-weight: 600; margin: 8px 0; border: 1px solid #86efac; }

/* ----- Guest Auth Quick Action Buttons ----- */
.auth-quick-actions {
  display: flex; gap: 8px; flex-wrap: wrap; margin: 10px 0;
  animation: fadeIn 0.3s ease;
}
.auth-quick-actions .auth-btn {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 8px 14px; border-radius: 10px; font-size: 0.8rem; font-weight: 600;
  border: none; cursor: pointer; transition: all 0.25s ease;
  color: #fff; white-space: nowrap;
}
.auth-quick-actions .auth-btn.login-btn { background: linear-gradient(135deg, #2563eb, #3b82f6); }
.auth-quick-actions .auth-btn.register-btn { background: linear-gradient(135deg, #059669, #10b981); }
.auth-quick-actions .auth-btn.forgot-btn { background: linear-gradient(135deg, #d97706, #f59e0b); }
.auth-quick-actions .auth-btn:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
.auth-quick-actions .auth-btn:active { transform: scale(0.97); }
.assistant-dark .auth-quick-actions .auth-btn { opacity: 0.9; }

/* ----- Auth Progress Indicator ----- */
.auth-progress-bar {
  display: flex; align-items: center; gap: 8px; margin: 8px 0; padding: 8px 12px;
  background: rgba(37, 99, 235, 0.08); border-radius: 10px; font-size: 0.78rem; color: #1e40af;
}
.assistant-dark .auth-progress-bar { background: rgba(96, 165, 250, 0.12); color: #93c5fd; }
.auth-progress-bar .progress-track {
  flex: 1; height: 6px; background: rgba(37, 99, 235, 0.15); border-radius: 3px; overflow: hidden;
}
.auth-progress-bar .progress-fill {
  height: 100%; background: linear-gradient(90deg, #2563eb, #3b82f6); border-radius: 3px;
  transition: width 0.4s ease;
}
.auth-progress-bar .step-label { font-weight: 600; white-space: nowrap; }

/* ----- Auth Success Banner ----- */
.auth-success-banner {
  display: flex; align-items: center; gap: 10px; padding: 12px 16px;
  background: linear-gradient(135deg, #dcfce7, #bbf7d0); border-radius: 12px;
  margin: 8px 0; animation: fadeIn 0.3s ease;
}
.auth-success-banner .success-icon { font-size: 1.5rem; }
.auth-success-banner .success-text { font-weight: 600; color: #166534; font-size: 0.9rem; }
.auth-success-banner .success-btn {
  margin-top: 8px; padding: 8px 16px; background: linear-gradient(135deg, #059669, #10b981);
  color: #fff; border: none; border-radius: 8px; font-weight: 600; font-size: 0.82rem;
  cursor: pointer; transition: all 0.2s;
}
.auth-success-banner .success-btn:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(5,150,105,0.3); }
.assistant-dark .auth-success-banner { background: linear-gradient(135deg, #064e3b, #065f46); }
.assistant-dark .auth-success-banner .success-text { color: #86efac; }

/* ----- Professional Auth Loading Card ----- */
.auth-loading-card {
  background: linear-gradient(135deg, rgba(255,255,255,0.95), rgba(240,244,248,0.95));
  border: 1px solid rgba(37, 99, 235, 0.15);
  border-radius: 16px;
  padding: 20px;
  margin: 12px 0;
  box-shadow: 0 10px 25px rgba(0,0,0,0.05);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  text-align: center;
  animation: slideUpFade 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}
.assistant-dark .auth-loading-card {
  background: linear-gradient(135deg, rgba(31,31,31,0.95), rgba(26,26,26,0.95));
  border-color: rgba(96, 165, 250, 0.2);
}
.auth-loading-spinner {
  width: 40px;
  height: 40px;
  border: 3px solid rgba(37, 99, 235, 0.1);
  border-top-color: #2563eb;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
.assistant-dark .auth-loading-spinner {
  border-color: rgba(96, 165, 250, 0.1);
  border-top-color: #60a5fa;
}
.auth-loading-title {
  font-weight: 700;
  font-size: 0.95rem;
  color: #1e3a8a;
}
.assistant-dark .auth-loading-title {
  color: #93c5fd;
}
.auth-loading-desc {
  font-size: 0.82rem;
  color: #4b5563;
}
.assistant-dark .auth-loading-desc {
  color: #9ca3af;
}
.auth-progress-track {
  width: 100%;
  height: 5px;
  background: rgba(0,0,0,0.05);
  border-radius: 10px;
  overflow: hidden;
  margin-top: 4px;
}
.assistant-dark .auth-progress-track {
  background: rgba(255,255,255,0.05);
}
.auth-progress-fill {
  height: 100%;
  width: 0%;
  background: linear-gradient(90deg, #2563eb, #3b82f6);
  border-radius: 10px;
  transition: width 0.3s ease;
}
.assistant-dark .auth-progress-fill {
  background: linear-gradient(90deg, #60a5fa, #3b82f6);
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
@keyframes slideUpFade {
  from { opacity: 0; transform: translateY(12px); }
  to { opacity: 1; transform: translateY(0); }
}

/* ----- Premium Custom Confirm Modal ----- */
.custom-confirm-overlay {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 2000;
  animation: fadeIn 0.2s ease;
}
.custom-confirm-card {
  background: var(--chat-bg);
  color: var(--chat-light-text);
  border-radius: 16px;
  padding: 20px;
  width: 85%;
  max-width: 300px;
  box-shadow: 0 10px 30px rgba(0,0,0,0.3);
  text-align: center;
  animation: scaleUp 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.assistant-dark .custom-confirm-card {
  background: var(--chat-dark-bg);
  color: var(--chat-dark-text);
}
.custom-confirm-text {
  font-size: 0.95rem;
  font-weight: 600;
  margin-bottom: 20px;
  line-height: 1.4;
}
.custom-confirm-buttons {
  display: flex;
  justify-content: center;
  gap: 12px;
}
.custom-confirm-btn {
  padding: 8px 20px;
  border-radius: 30px;
  border: none;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  flex: 1;
}
.custom-confirm-btn.btn-cancel {
  background: #f1f5f9;
  color: #475569;
}
.custom-confirm-btn.btn-cancel:hover {
  background: #e2e8f0;
}
.assistant-dark .custom-confirm-btn.btn-cancel {
  background: #334155;
  color: #cbd5e1;
}
.assistant-dark .custom-confirm-btn.btn-cancel:hover {
  background: #475569;
}
.custom-confirm-btn.btn-confirm {
  background: linear-gradient(135deg, #ef4444, #dc2626);
  color: #fff;
  box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
}
.custom-confirm-btn.btn-confirm:hover {
  transform: translateY(-1px);
  box-shadow: 0 6px 16px rgba(239, 68, 68, 0.3);
}
.custom-confirm-btn:active {
  transform: scale(0.97);
}

@keyframes scaleUp {
  from { opacity: 0; transform: scale(0.9); }
  to { opacity: 1; transform: scale(1); }
}
</style>
  
<div class="assistant-wrapper" id="assistantWrapper">
  <button class="assistant-toggle" id="openAssistant">
    <i class="fas fa-robot"></i>
    <span class="assistant-badge" id="newBadge">1</span>
  </button>
<br><br>  <br><br><br><br>  <br>
  <div class="assistant-box" id="assistantBox">
    <div class="assistant-header" id="botHeader">
      <span><strong>WiseBot</strong> Assistant</span>
      <div>
        <?php if($user_id): ?>
        <button id="historyToggleBtn" class="btn btn-sm text-white" title="Chat History"><i class="fas fa-history"></i></button>
        <?php endif; ?>
        <button id="darkToggle" class="btn btn-sm text-white"><i class="fas fa-adjust"></i></button>
        <button id="closeAssistant" class="btn btn-sm text-white"><i class="fas fa-times"></i></button>
      </div>
    </div>
    
    <!-- Chat History Panel -->
    <div class="history-panel" id="historyPanel">
      <div class="history-header">
        <button id="closeHistoryBtn" class="btn btn-sm text-white"><i class="fas fa-arrow-left"></i></button>
        <span class="fw-bold">Chat History</span>
      </div>
      <div class="history-list" id="historyList">
        <!-- JS dynamically populates this -->
        <div class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
      </div>
    </div>
    <!-- Support Mode Header (hidden by default) -->
    <div class="assistant-header support-mode" id="supportHeader" style="display:none;">
      <div class="support-agent-info">
        <div class="support-agent-avatar" id="agentAvatar">
          <i class="fas fa-user"></i>
        </div>
        <div>
          <div class="support-agent-name" id="agentName">Connecting to Live Person...</div>
          <div class="support-agent-status"><span class="dot" id="onlineDot"></span> <span id="supportStatusText">Searching for available agent</span></div>
        </div>
      </div>
      <div class="ticket-badge" id="ticketBadge">#WQS-0000</div>
      <div>
        <!-- Admin leave chat button -->
        <button id="leaveChatAdminBtn" class="btn btn-sm" style="display:none; background:transparent; border:1px solid rgba(255,255,255,0.4); color:#fff; border-radius:50px; font-size:0.75rem; padding:2px 10px; margin-right:6px; transition:all 0.2s; white-space:nowrap;" onclick="adminLeaveChat()" title="Leave Chat"><i class="fas fa-sign-out-alt me-1"></i> Leave</button>
        <button id="closeSupportBtn" class="btn btn-sm text-white" onclick="exitSupportMode()" title="Back to Bot"><i class="fas fa-robot"></i></button>
        <button id="closeAssistant2" class="btn btn-sm text-white" onclick="closeChat()"><i class="fas fa-times"></i></button>
      </div>
    </div>
    <div class="assistant-body" id="assistantBody">
      <div class="bubble bot">
        <?= $greetingText ?>
      </div>
      
      <?php foreach ($chatHistory as $chat): 
          $roleClass = $chat['role'] === 'user' ? 'user' : 'bot';
          $timeStr = date('h:i A', strtotime($chat['created_at']));
      ?>
          <div class="bubble-wrapper <?= $roleClass ?>">
            <div class="bubble <?= $roleClass ?>"><?= formatChatMessage($chat['message'], $path_to_root) ?></div>
            <div class="msg-time"><?= $timeStr ?></div>
          </div>
      <?php endforeach; ?>

      <div class="quick-btns">
        <button onclick="sendQuick('Payment Issue')">Payment Issue</button>
        <button onclick="sendQuick('Change Email')">Change Email</button>
        <button onclick="sendQuick('Contact Support')">Contact Support</button>
        <?php if ($user_id): ?>
        <button onclick="startProjectRequest()" style="background:linear-gradient(135deg,#00264d,#004080);color:white;border-color:#00264d;"><i class="fas fa-plus-circle"></i> Create Project</button>
        <button onclick="requestHumanSupport()" class="link-person-btn" style="background:linear-gradient(135deg,#059669,#10b981);color:white;"><i class="fas fa-headset"></i> Link me with a person</button>
        <?php endif; ?>
      </div>
    </div>
    <!-- Voice Status Bar -->
    <div class="voice-status-bar" id="voiceStatusBar">
      <div class="thinking-dots" id="thinkingDots" style="display:none;"><span></span><span></span><span></span></div>
      <i class="fas" id="voiceStatusIcon"></i>
      <span id="voiceStatusText">Listening...</span>
      <div class="voice-playing-indicator" id="voicePlayingIndicator">
        <div class="bar"></div><div class="bar"></div><div class="bar"></div>
        <span>Playing</span>
      </div>
    </div>
    <style>
        .assistant-footer .chat-input-container{
             width:100%;
            display:flex;
            justify-content:center;
            align-items: center;
            flex-direction: column;
            padding:10px;
        }
         .assistant-footer .chat-input-container .Icons{
             width:100%;
            display:flex;
            justify-content:space-between;
            align-items: center;
            flex-direction:row;
            padding: 10px 0;
            position: relative;
            z-index: 5;
        }
         .assistant-footer .chat-input-container .attatch-option-bnts,
         .assistant-footer .chat-input-container .chat-option-btnts{
            display: flex;
            align-items: center;
            gap: 4px;
            position: relative;
            z-index: 5;
        }
         .assistant-footer .chat-input-container .user-input textarea{
             width:100%;
             border: none;
             background: transparent;
             outline: none;
             font-size: 0.95rem;
             resize: none;
             min-height: 38px;
             max-height: 120px;
             overflow-y: hidden;
             line-height: 1.4;
             color: inherit;
         }
        /* Custom Native Emoji Picker - Premium UI */
        .native-emoji-picker {
            position: absolute;
            bottom: calc(100% + 10px);
            left: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 16px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
            width: 290px;
            padding: 14px;
            display: none;
            grid-template-columns: repeat(6, 1fr);
            gap: 8px;
            z-index: 99999;
            max-height: 220px;
            overflow-y: auto;
            scrollbar-width: none;
            -ms-overflow-style: none;
            transform-origin: bottom left;
        }
        .native-emoji-picker::-webkit-scrollbar {
            display: none;
            width: 0;
        }
        /* Little arrow pointing down */
        .native-emoji-picker::after {
            content: '';
            position: absolute;
            bottom: -6px;
            left: 20px;
            width: 12px;
            height: 12px;
            background: rgba(255, 255, 255, 0.95);
            border-right: 1px solid rgba(0,0,0,0.05);
            border-bottom: 1px solid rgba(0,0,0,0.05);
            transform: rotate(45deg);
            z-index: -1;
            transition: background 0.3s ease;
        }
        .native-emoji-picker.show {
            display: grid !important;
            animation: popInEmoji 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
        }
        @keyframes popInEmoji {
            from { opacity: 0; transform: scale(0.9) translateY(15px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        .native-emoji-picker button.native-emoji-btn {
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
            font-size: 1.4rem !important;
            cursor: pointer;
            text-align: center;
            border-radius: 8px !important;
            transition: all 0.2s cubic-bezier(0.2, 0, 0, 1) !important;
            padding: 6px 0 !important;
            margin: 0 !important;
            min-width: 0 !important;
            line-height: 1 !important;
            outline: none !important;
        }
        .native-emoji-picker button.native-emoji-btn:hover {
            background: rgba(0,0,0,0.06) !important;
            transform: scale(1.2) !important;
        }
        .native-emoji-picker button.native-emoji-btn:active {
            transform: scale(0.95) !important;
        }
        .assistant-dark .native-emoji-picker {
            background: rgba(30, 41, 59, 0.85);
            border-color: rgba(255,255,255,0.1);
            box-shadow: 0 12px 40px rgba(0,0,0,0.3);
        }
        .assistant-dark .native-emoji-picker::after {
            background: rgba(30, 41, 59, 0.95);
            border-color: rgba(255,255,255,0.1);
        }
        .assistant-dark .native-emoji-picker button.native-emoji-btn:hover {
            background: rgba(255,255,255,0.1) !important;
        }
    </style>
    <div class="assistant-footer">
        
      <!-- File Preview Container -->
      <div id="filePreview" style="display:none; padding: 8px 12px; background: rgba(0,0,0,0.05); border-radius: 8px; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 6px;">
         <div id="filePreviewInfo" style="display: flex; align-items: center; gap: 8px; font-size: 0.85rem; color: #444; width: 85%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
            <!-- Thumbnail or icon dynamically populated -->
         </div>
         <button id="clearFileBtn" style="background: none; border: none; color: #dc3545; cursor: pointer; font-size: 1.1rem; padding: 2px;"><i class="fas fa-times-circle"></i></button>
      </div>
        
      <div class="chat-input-container" style="position:relative;">
       <!-- The custom emoji picker container -->
       <div id="customEmojiPicker" class="native-emoji-picker"></div>
       <div class="user-input">
          <textarea id="assistantInput" placeholder="Type your message..." rows="1" cols="100"></textarea>
       </div>
       <div class="Icons">
        <div class="attatch-option-bnts">
          <button id="emoji-btn" class="chat-icon" title="Emoji">😊</button>
          <button id="file-btn" class="chat-icon" title="Attach File">📎</button>
          <input type="file" id="file-input" style="display: none" accept="image/*,application/pdf" />
        </div>
        <div class="chat-option-btnts">
          <!-- Call Button -->
          <button id="callBtn" class="chat-icon call-btn" title="Voice Call">
            <i class="fas fa-phone"></i>
          </button>
        </div>
       </div>
       <!-- Send Button with Up Arrow Icon -->
       <button id="sendBtn" class="chat-icon send-btn" title="Send">
         <i class="fas fa-arrow-up"></i>
       </button>
      </div>
    </div>

  <!-- Calling UI Overlay -->
  <div id="callingOverlay" style="display:none;position:absolute;inset:0;z-index:9998;background:linear-gradient(135deg,#0f172a 0%,#1e293b 50%,#0f172a 100%);border-radius:var(--chat-radius);flex-direction:column;align-items:center;justify-content:center;color:white;overflow:hidden;">
    <div class="calling-waves">
      <div class="call-wave call-wave-1"></div>
      <div class="call-wave call-wave-2"></div>
      <div class="call-wave call-wave-3"></div>
    </div>
    <div style="position:relative;z-index:2;text-align:center;">
      <div id="callAvatar" style="width:80px;height:80px;border-radius:50%;background:rgba(255,255,255,0.1);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;border:3px solid rgba(255,255,255,0.2);">
        <i class="fas fa-robot" style="font-size:2rem;color:var(--chat-accent);"></i>
      </div>
      <div id="callName" style="font-size:1.1rem;font-weight:700;margin-bottom:4px;">WiseBot</div>
      <div id="callStatus" style="font-size:0.78rem;color:rgba(255,255,255,0.6);margin-bottom:8px;">Calling...</div>
      <div id="callDuration" style="font-size:1.5rem;font-weight:700;font-variant-numeric:tabular-nums;margin-bottom:24px;display:none;">00:00</div>
      <button id="endCallBtn" style="width:56px;height:56px;border-radius:50%;background:#ef4444;border:none;color:white;font-size:1.3rem;cursor:pointer;box-shadow:0 4px 20px rgba(239,68,68,0.5);transition:transform 0.2s;">
        <i class="fas fa-phone-slash"></i>
      </button>
      <div style="font-size:0.7rem;color:rgba(255,255,255,0.4);margin-top:10px;">End Call</div>
    </div>
  </div>

  </div>
</div>

<!-- Removed external EmojiButton Library -->

<style>
@keyframes callWave {
  0% { transform: scale(0.8); opacity: 0.6; }
  100% { transform: scale(1.6); opacity: 0; }
}
.calling-waves {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 140px;
  height: 140px;
}
.call-wave {
  position: absolute;
  inset: 0;
  border-radius: 50%;
  border: 1px solid rgba(255, 255, 255, 0.3);
  opacity: 0;
  animation: callWave 2s ease-out infinite;
}
.call-wave-2 { animation-delay: 0.6s; }
.call-wave-3 { animation-delay: 1.2s; }
#callBtn {
  color: #22c55e !important;
}
#callBtn:hover {
  background: rgba(34,197,94,0.15) !important;
}
#endCallBtn:hover {
  transform: scale(1.1);
}

/* Rich Interactive Cards */
.wb-rich-card {
  background: #fff;
  border: 1.5px solid #e2e8f0;
  border-radius: 14px;
  padding: 1rem;
  margin: 8px 0;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
  transition: all 0.2s;
  cursor: default;
}
.wb-rich-card:hover {
  border-color: #3b82f6;
  box-shadow: 0 4px 16px rgba(59,130,246,0.1);
  transform: translateY(-1px);
}
.wb-rich-card .rc-header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 8px;
}
.wb-rich-card .rc-icon {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 0.85rem;
  flex-shrink: 0;
}
.wb-rich-card .rc-title {
  font-weight: 700;
  font-size: 0.88rem;
  color: #1e293b;
}
.wb-rich-card .rc-subtitle {
  font-size: 0.72rem;
  color: #94a3b8;
}
.wb-rich-card .rc-body {
  font-size: 0.82rem;
  color: #475569;
  margin-bottom: 8px;
}
.wb-rich-card .rc-price {
  font-size: 1.2rem;
  font-weight: 900;
  color: #0f172a;
}
.wb-rich-card .rc-features {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin: 6px 0;
}
.wb-rich-card .rc-feature-tag {
  background: #f1f5f9;
  color: #475569;
  font-size: 0.68rem;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 50px;
}
.wb-rich-card .rc-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: #0f172a;
  color: #fff;
  border: none;
  border-radius: 8px;
  padding: 6px 14px;
  font-size: 0.78rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  text-decoration: none;
}
.wb-rich-card .rc-btn:hover {
  background: #1e40af;
  transform: translateY(-1px);
}

/* Quick Reply Suggestions */
.wb-quick-replies {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  padding: 4px 0 8px;
}
.wb-quick-reply-btn {
  background: #fff;
  border: 1.5px solid #e2e8f0;
  border-radius: 50px;
  padding: 5px 14px;
  font-size: 0.76rem;
  font-weight: 600;
  color: #334155;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
}
.wb-quick-reply-btn:hover {
  border-color: #3b82f6;
  color: #3b82f6;
  background: #eff6ff;
  transform: translateY(-1px);
}

/* Proactive Trigger Banner */
.wb-proactive-banner {
  background: linear-gradient(135deg, #0f172a, #1e3a5f);
  color: #fff;
  border-radius: 12px;
  padding: 12px 16px;
  margin: 8px 0;
  display: flex;
  align-items: center;
  gap: 10px;
  animation: wbSlideUp 0.3s ease;
  cursor: pointer;
}
.wb-proactive-banner:hover {
  opacity: 0.95;
}
.wb-proactive-banner .pb-icon {
  width: 32px;
  height: 32px;
  background: rgba(255,255,255,0.15);
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.85rem;
  flex-shrink: 0;
}
.wb-proactive-banner .pb-text {
  font-size: 0.8rem;
  font-weight: 500;
  line-height: 1.3;
}
.wb-proactive-banner .pb-close {
  margin-left: auto;
  background: rgba(255,255,255,0.15);
  border: none;
  color: #fff;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 0.65rem;
}

/* Ad Banner */
.wb-ad-banner {
  background: linear-gradient(135deg, #fef3c7, #fde68a);
  border: 1px solid #f59e0b;
  border-radius: 12px;
  padding: 12px 16px;
  margin: 8px 0;
  font-size: 0.82rem;
  color: #92400e;
  font-weight: 500;
}
.wb-ad-banner strong {
  color: #78350f;
}

/* Sentiment indicator */
.wb-sentiment-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  display: inline-block;
  margin-left: 4px;
}
.wb-sentiment-positive { background: #22c55e; }
.wb-sentiment-neutral { background: #94a3b8; }
.wb-sentiment-negative { background: #ef4444; }

@keyframes wbSlideUp {
  from { opacity: 0; transform: translateY(8px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>

<script>
  const CURRENT_USER_ID = <?= json_encode($_SESSION['user']['id'] ?? null) ?>;
  const CURRENT_USER_ROLE = <?= json_encode($_SESSION['user']['role'] ?? '') ?>;

  const toggleBtn = document.getElementById('openAssistant');
  const chatBox = document.getElementById('assistantBox');
  const closeBtn = document.getElementById('closeAssistant');
  const darkToggle = document.getElementById('darkToggle');
  const inputBox = document.getElementById('assistantInput');
  const bodyBox = document.getElementById('assistantBody');
  const badge = document.getElementById('newBadge');
  const wrapper = document.getElementById('assistantWrapper');
  const sendBtn = document.getElementById('sendBtn');
  const voiceBtn = document.getElementById('voiceBtn');
  const body = document.body;

  const sendSound = new Audio('https://assets.mixkit.co/sfx/preview/mixkit-fast-small-sweep-transition-166.wav');
  const receiveSound = new Audio('https://assets.mixkit.co/sfx/preview/mixkit-positive-interface-beep-221.wav');

  const pathToRoot = '<?= $_botWebPath ?>';

  let attachedFile = null; // Store base64 data and metadata of attached file

  const savedTheme = localStorage.getItem('assistant-theme');
  if (savedTheme === 'dark') {
    wrapper.classList.add('assistant-dark');
  } else {
    wrapper.classList.remove('assistant-dark');
  }

  darkToggle.addEventListener('click', () => {
    const isDark = wrapper.classList.toggle('assistant-dark');
    localStorage.setItem('assistant-theme', isDark ? 'dark' : 'light');
  });

  function lockBodyScroll(lock) {
    body.style.overflow = lock ? 'hidden' : '';
  }

  toggleBtn.addEventListener('click', () => {
    const isOpen = chatBox.classList.toggle('show');
    if (isOpen) {
      chatBox.classList.remove('fade-out');
      chatBox.classList.add('fade-in');
      // Load proactive triggers on first open
      if (!window._proactiveLoaded) {
        window._proactiveLoaded = true;
        loadProactiveTriggers();
      }
    } else {
      chatBox.classList.remove('fade-in');
      chatBox.classList.add('fade-out');
    }

    badge.style.display = 'none';
    lockBodyScroll(isOpen);

    const icon = toggleBtn.querySelector('i');
    icon.classList.add('rotate');
    setTimeout(() => {
      if (isOpen) {
        icon.classList.remove('fa-robot');
        icon.classList.add('fa-chevron-down');
      } else {
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-robot');
      }
    }, 150);
    setTimeout(() => icon.classList.remove('rotate'), 300);
  });

  closeBtn.addEventListener('click', () => {
    chatBox.classList.remove('fade-in');
    chatBox.classList.add('fade-out');
    chatBox.classList.remove('show');
    lockBodyScroll(false);

    const icon = toggleBtn.querySelector('i');
    icon.classList.add('rotate');
    icon.classList.remove('fa-chevron-down');
    icon.classList.add('fa-robot');
    setTimeout(() => icon.classList.remove('rotate'), 300);
  });

  // Dynamic input triggers
  function updateInputButtons() {
    const hasText = inputBox.value.trim().length > 0;
    const hasFile = attachedFile !== null;
    const callBtn = document.getElementById('callBtn');
    const callBtnWrap = callBtn ? callBtn.closest('.chat-option-btnts') : null;
    
    if (hasText || hasFile) {
      sendBtn.style.display = 'block';
      if (voiceBtn) voiceBtn.style.display = 'none';
      if (callBtnWrap) callBtnWrap.style.display = 'none';
    } else {
      sendBtn.style.display = 'none';
      if (voiceBtn) voiceBtn.style.display = 'block';
      if (callBtnWrap) callBtnWrap.style.display = 'block';
    }
  }

  inputBox.addEventListener('input', () => {
    updateInputButtons();
    inputBox.style.height = 'auto';
    inputBox.style.height = inputBox.scrollHeight + 'px';
  });

  inputBox.addEventListener('keypress', e => {
    if (e.key === 'Enter' && !e.shiftKey && (inputBox.value.trim() || attachedFile)) {
      e.preventDefault();
      sendMessage();
    }
  });

  sendBtn.addEventListener('click', sendMessage);

  // Message Formatter in JavaScript
  function formatMessageText(text) {
    let escaped = text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
        
    // Parse ```code blocks```
    escaped = escaped.replace(/```([\s\S]*?)```/g, '<pre style="background:rgba(0,0,0,0.05); padding:8px; border-radius:6px; font-size:0.85rem; font-family:monospace; white-space:pre-wrap; margin:8px 0;"><code>$1</code></pre>');

    // Parse `inline code`
    escaped = escaped.replace(/`(.*?)`/g, '<code style="background:rgba(0,0,0,0.05); padding:2px 4px; border-radius:4px; font-family:monospace; font-size:0.9rem;">$1</code>');

    // Parse Markdown **bold** to <strong>bold</strong> (multiline support)
    escaped = escaped.replace(/\*\*([\s\S]*?)\*\*/g, '<strong>$1</strong>');
    
    // Parse sub-headings
    escaped = escaped.replace(/### (.*?)\n/g, '<h6 class="fw-bold my-2" style="color:#0A2D5E;">$1</h6>');
    escaped = escaped.replace(/## (.*?)\n/g, '<h5 class="fw-bold my-2" style="color:#0A2D5E;">$1</h5>');
    escaped = escaped.replace(/# (.*?)\n/g, '<h4 class="fw-bold my-2" style="color:#0A2D5E;">$1</h4>');

    // Parse Markdown Images: ![alt](url)
    escaped = escaped.replace(
        /!\[(.*?)\]\((.*?)\)/g,
        (match, alt, path) => {
            const fullPath = path.startsWith('http') ? path : pathToRoot + path;
            return `<br><img src="${fullPath}" style="max-width:100%; max-height:220px; border-radius:8px; margin-top:6px; box-shadow:0 2px 6px rgba(0,0,0,0.15);" alt="${alt}" />`;
        }
    );

    // Parse Markdown Links: [text](url)
    escaped = escaped.replace(
        /\[(.*?)\]\((.*?)\)/g,
        (match, text, path) => {
            // Decode &amp; to & if present in raw URL matching
            const pathClean = path.replace(/&amp;/g, '&');
            const fullPath = pathClean.startsWith('http') ? pathClean : pathToRoot + pathClean;
            return `<a href="${fullPath}" target="_blank" style="color:#ff6600; font-weight:600; text-decoration:underline;">${text}</a>`;
        }
    );

    // Parse [Attached Image: PATH]
    escaped = escaped.replace(
        /\[Attached Image:\s*(.*?)\]/g, 
        (match, p1) => {
            const fullPath = p1.startsWith('http') ? p1 : pathToRoot + p1;
            return `<br><img src="${fullPath}" style="max-width:100%; max-height:150px; border-radius:8px; margin-top:6px; box-shadow:0 2px 6px rgba(0,0,0,0.15);" alt="Image Attachment" />`;
        }
    );
    
    // Parse [Attached File: NAME | PATH]
    escaped = escaped.replace(
        /\[Attached File:\s*(.*?)\s*\|\s*(.*?)\]/g, 
        (match, p1, p2) => {
            const fullPath = p2.startsWith('http') ? p2 : pathToRoot + p2;
            return `<br><a href="${fullPath}" target="_blank" style="display:inline-flex; align-items:center; gap:6px; background:rgba(255,102,0,0.1); color:#ff6600; padding:6px 12px; border-radius:8px; font-weight:600; text-decoration:none; margin-top:6px; border:1px solid rgba(255,102,0,0.25); font-size:0.85rem;"><i class="fas fa-file-pdf"></i> ${p1}</a>`;
        }
    );
    
    return escaped.replace(/\n/g, '<br>');
  }

  function sendMessage() {
    const message = inputBox.value.trim();
    if (!message && !attachedFile) return;
    
    // Build display message with inline preview (not full base64)
    let displayParts = [];
    if (message) displayParts.push(formatMessageText(message));
    if (attachedFile) {
      if (attachedFile.type.startsWith('image/')) {
        displayParts.push(`<div style="margin-top:6px;"><img src="${attachedFile.data}" style="max-width:100%; max-height:150px; border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,0.15);" alt="Uploaded Image" /></div>`);
      } else {
        displayParts.push(`<div style="margin-top:6px; display:flex; align-items:center; gap:6px; background:rgba(255,102,0,0.1); color:#ff6600; padding:6px 12px; border-radius:8px; font-size:0.85rem; border:1px solid rgba(255,102,0,0.25); width:fit-content;"><i class="fas fa-file-pdf"></i> ${attachedFile.name}</div>`);
      }
    }
    
    const now = new Date();
    const timeStr = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    
    const userWrapper = document.createElement('div');
    userWrapper.className = 'bubble-wrapper user';
    userWrapper.innerHTML = `
      <div class="bubble user">${displayParts.join('')}</div>
      <div class="msg-time">${timeStr} <i class="fas fa-check-double" style="font-size:0.6rem;"></i></div>
    `;
    bodyBox.appendChild(userWrapper);
    bodyBox.scrollTop = bodyBox.scrollHeight;
    sendSound.play();
    
    simulateBotReply(message, attachedFile);
    
    // Reset inputs
    inputBox.value = '';
    clearAttachedFile();
    updateInputButtons();
    inputBox.style.height = '38px';
  }

  function appendMessage(type, msg) {
    const wrapper = document.createElement('div');
    wrapper.className = `bubble-wrapper ${type}`;
    
    const bubble = document.createElement('div');
    bubble.className = `bubble ${type}`;
    bubble.innerHTML = formatMessageText(msg);
    wrapper.appendChild(bubble);
    
    // Add timestamp
    const time = document.createElement('div');
    time.className = 'msg-time';
    const now = new Date();
    const timeStr = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    time.innerHTML = type === 'user' ? `${timeStr} <i class="fas fa-check-double" style="font-size:0.6rem;color:rgba(255,255,255,0.5);"></i>` : timeStr;
    wrapper.appendChild(time);
    
    bodyBox.appendChild(wrapper);

    // Process rich cards
    bubble.querySelectorAll('.wb-rich-card[data-card]').forEach(card => {
      try {
        const data = JSON.parse(card.dataset.card);
        let html = '';
        const iconBg = data.type === 'quote' ? '#0f172a' : data.type === 'portfolio' ? '#1e40af' : data.type === 'invoice' ? '#047857' : '#7c3aed';
        const iconClass = data.type === 'quote' ? 'fas fa-calculator' : data.type === 'portfolio' ? 'fas fa-image' : data.type === 'invoice' ? 'fas fa-file-invoice' : 'fas fa-user';
        html += `<div class="rc-header"><div class="rc-icon" style="background:${iconBg}"><i class="${iconClass}"></i></div><div><div class="rc-title">${escH(data.title || data.name || '')}</div><div class="rc-subtitle">${escH(data.tier || data.designation || data.status || data.type || '')}</div></div></div>`;
        if (data.description) html += `<div class="rc-body">${escH(data.description)}</div>`;
        if (data.price) html += `<div class="rc-price">${escH(data.price)}</div>`;
        if (data.features) {
          const feats = data.features.split(',').map(f => f.trim());
          html += `<div class="rc-features">${feats.map(f => `<span class="rc-feature-tag">${escH(f)}</span>`).join('')}</div>`;
        }
        if (data.image) html += `<img src="${escH(data.image)}" style="width:100%;border-radius:8px;margin:6px 0;" alt="">`;
        if (data.link) html += `<a href="${escH(data.link)}" target="_blank" class="rc-btn"><i class="fas fa-external-link-alt"></i> View Details</a>`;
        if (data.amount) html += `<div style="margin-top:6px;font-size:0.9rem;font-weight:700;color:#0f172a;">Amount: ${escH(data.amount)}</div>`;
        card.innerHTML = html;
      } catch(e) { card.style.display = 'none'; }
    });

    // Process quick replies
    bubble.querySelectorAll('.wb-quick-reply-suggestions[data-options]').forEach(el => {
      try {
        const options = JSON.parse(el.dataset.options);
        const wrap = document.createElement('div');
        wrap.className = 'wb-quick-replies';
        options.forEach(opt => {
          const btn = document.createElement('button');
          btn.className = 'wb-quick-reply-btn';
          btn.textContent = opt;
          btn.onclick = () => {
            wrap.remove();
            handleUserInput(opt);
          };
          wrap.appendChild(btn);
        });
        el.replaceWith(wrap);
      } catch(e) { el.style.display = 'none'; }
    });

    // Process ad banners
    bubble.querySelectorAll('.wb-ad-banner[data-ad]').forEach(el => {
      try {
        const ad = JSON.parse(el.dataset.ad);
        el.innerHTML = `<strong>🎯 Recommended for you:</strong> ${escH(ad.content)}`;
      } catch(e) { el.style.display = 'none'; }
    });

    bodyBox.scrollTop = bodyBox.scrollHeight;
  }

  function escH(s) { if (!s) return ''; const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

  function simulateBotReply(userMsg, fileObj) {
    const typing = document.createElement('div');
    typing.className = 'typing-indicator';
    typing.innerHTML = '<span></span><span></span><span></span>';
    bodyBox.appendChild(typing);
    bodyBox.scrollTop = bodyBox.scrollHeight;

    fetch('<?= $_botWebPath ?>agent-server.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ message: userMsg, file: fileObj })
    })
    .then(res => {
      if (!res.ok) throw new Error('Server responded with ' + res.status);
      return res.json();
    })
    .then(data => {
      typing.remove();

      // Apply theme change immediately if returned
      if (data.theme) {
        document.documentElement.setAttribute('data-bs-theme', data.theme);
        const ttBtn = document.getElementById('themeToggleBtn');
        if (ttBtn) ttBtn.innerHTML = data.theme === 'dark' ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
      }

      // Update profile picture on page if returned
      if (data.avatar) {
        document.querySelectorAll('.user-profile-img, #profileAvatarDisplay').forEach(function(img) {
          img.src = data.avatar;
        });
      }

      let replyText = data.reply || '';

      // Handle auth success marker — redirect to dashboard
      const authSuccessMatch = replyText.match(/\[CHAT_AUTH_SUCCESS:\s*([^\]]+)\]/);
      if (authSuccessMatch) {
        let authRedirect = authSuccessMatch[1].trim();
        // Sanitize redirect: only allow relative paths (no protocol, no double dots)
        authRedirect = authRedirect.replace(/[^a-zA-Z0-9_\-\/\.]/g, '');
        if (!authRedirect.match(/^[a-zA-Z0-9_\-\/]+\.php$/)) authRedirect = 'user/dashboard.php';
        replyText = replyText.replace(authSuccessMatch[0], '');
        
        // Append message
        appendMessage('bot', replyText);
        receiveSound.play();

        const isRegister = replyText.toLowerCase().includes('congratulations') || replyText.toLowerCase().includes('created');

        // Show professional auth loading card
        const loadingCard = document.createElement('div');
        loadingCard.className = 'auth-loading-card';

        const spinner = document.createElement('div');
        spinner.className = 'auth-loading-spinner';

        const title = document.createElement('div');
        title.className = 'auth-loading-title';
        title.textContent = isRegister ? 'Creating Account' : 'Authenticating';

        const desc = document.createElement('div');
        desc.className = 'auth-loading-desc';
        desc.textContent = isRegister ? 'Setting up your secure workspace...' : 'Verifying secure credentials...';

        const progressTrack = document.createElement('div');
        progressTrack.className = 'auth-progress-track';
        const progressFill = document.createElement('div');
        progressFill.className = 'auth-progress-fill';
        progressTrack.appendChild(progressFill);

        loadingCard.appendChild(spinner);
        loadingCard.appendChild(title);
        loadingCard.appendChild(desc);
        loadingCard.appendChild(progressTrack);

        bodyBox.appendChild(loadingCard);
        bodyBox.scrollTop = bodyBox.scrollHeight;

        // Steps simulation
        let progress = 0;
        const intervalTime = 40; // update frequency
        const duration = 2800; // total duration in ms
        const steps = duration / intervalTime;
        const stepIncrement = 100 / steps;

        const loaderInterval = setInterval(() => {
          progress += stepIncrement;
          if (progress >= 100) {
            progress = 100;
            clearInterval(loaderInterval);
            // Redirect
            window.location.href = authRedirect;
          }

          progressFill.style.width = progress + '%';

          // Dynamic status messages based on progress
          if (isRegister) {
            if (progress < 30) {
              title.textContent = 'Verifying Details...';
              desc.textContent = 'Validating registration parameters...';
            } else if (progress < 65) {
              title.textContent = 'Creating Account...';
              desc.textContent = 'Initializing secure workspace database...';
            } else if (progress < 90) {
              title.textContent = 'Securing Session...';
              desc.textContent = 'Generating encrypted credentials...';
            } else {
              title.textContent = 'Welcome Aboard!';
              desc.textContent = 'Redirecting to your dashboard...';
            }
          } else {
            if (progress < 40) {
              title.textContent = 'Verifying Credentials...';
              desc.textContent = 'Establishing secure handshake...';
            } else if (progress < 75) {
              title.textContent = 'Authenticating User...';
              desc.textContent = 'Checking database records...';
            } else if (progress < 92) {
              title.textContent = 'Session Established...';
              desc.textContent = 'Preparing your customized interface...';
            } else {
              title.textContent = 'Authorized!';
              desc.textContent = 'Taking you to dashboard...';
            }
          }
        }, intervalTime);

        return;
      }

      // Handle auto-trigger markers from bot
      if (replyText.includes('[TRIGGER_FILE_UPLOAD]')) {
        replyText = replyText.replace('[TRIGGER_FILE_UPLOAD]', '');
        setTimeout(() => { fileBtn.click(); }, 500);
      }
      if (replyText.includes('[HIGHLIGHT_UPLOAD]')) {
        replyText = replyText.replace('[HIGHLIGHT_UPLOAD]', '');
        fileBtn.classList.add('upload-pulse');
        setTimeout(() => fileBtn.classList.remove('upload-pulse'), 5000);
      }
      if (replyText.includes('[FOCUS_INPUT]')) {
        replyText = replyText.replace('[FOCUS_INPUT]', '');
        setTimeout(() => { inputBox.focus(); }, 300);
      }

      // Handle CREATE_TICKET command from bot
      if (replyText.includes('[CREATE_TICKET:')) {
        const ticketMatch = replyText.match(/\[CREATE_TICKET:(\{.*?\})\]/);
        if (ticketMatch) {
          replyText = replyText.replace(/\[CREATE_TICKET:(\{.*?\})\]/, '');
          try {
            const ticketData = JSON.parse(ticketMatch[1]);
            const fd = new URLSearchParams();
            fd.append('subject', ticketData.subject || 'Support Ticket');
            fd.append('description', ticketData.message || ticketData.description || 'Created via chatbot');
            fd.append('category', ticketData.category || 'general');
            fd.append('priority', ticketData.priority || 'medium');
            fd.append('origin', 'chatbot');
            fd.append('message', ticketData.message || '');

            fetch(pathToRoot + 'api/ticket_api.php?action=create_ticket', {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: fd.toString()
            })
            .then(r => r.json())
            .then(resData => {
              if (resData.success) {
                appendBotMessage('🎟️ Support Ticket <strong>#' + resData.ticket_number + '</strong> has been created successfully! You are now connected to the Support Queue. An agent will join you shortly.');
                supportTicketId = resData.ticket_id;
                supportTicketNumber = resData.ticket_number;
                setTimeout(() => {
                  enterSupportMode({ id: resData.ticket_id, ticket_number: resData.ticket_number, status: 'waiting' });
                  showSupportButton();
                  startPollingForAgent();
                }, 1500);
              } else {
                appendBotMessage('❌ Failed to create support ticket: ' + (resData.error || 'Unknown error'));
              }
            })
            .catch(() => {
              appendBotMessage('❌ System error occurred while creating support ticket.');
            });
          } catch(e) {
            console.error('JSON parse error in ticket creation command:', e);
          }
        }
      }

      // Check for auto-handoff trigger
      if (replyText.indexOf('[TRIGGER_HANDOFF]') !== -1) {
        replyText = replyText.replace('[TRIGGER_HANDOFF]', '');
        appendMessage('bot', replyText);
        receiveSound.play();
        // Auto-trigger human handoff
        setTimeout(() => requestHumanSupport(), 1000);
      } else {
        appendMessage('bot', replyText);
        receiveSound.play();
      }
    })
    .catch((err) => {
      console.error('Chat fetch error:', err);
      typing.remove();
      appendMessage('bot', '⚠️ Sorry, something went wrong. Please try again later.');
    });
  }

  function sendQuick(text) {
    inputBox.value = text;
    inputBox.dispatchEvent(new Event('input'));
    sendMessage();
  }

  function loadProactiveTriggers() {
    const page = window.location.pathname.split('/').pop() || 'index.php';
    fetch('<?= $_botWebPath ?>api/proactive_triggers.php?page=' + encodeURIComponent(page))
      .then(r => r.json())
      .then(data => {
        if (data.success && data.triggers && data.triggers.length > 0) {
          data.triggers.forEach(trigger => {
            setTimeout(() => {
              const banner = document.createElement('div');
              banner.className = 'wb-proactive-banner';
              banner.innerHTML = `<div class="pb-icon"><i class="fas fa-magic"></i></div><div class="pb-text">${trigger.message}</div><button class="pb-close" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>`;
              banner.onclick = (e) => {
                if (e.target.closest('.pb-close')) return;
                banner.remove();
                handleUserInput(trigger.message);
              };
              bodyBox.appendChild(banner);
              bodyBox.scrollTop = bodyBox.scrollHeight;
            }, (trigger.delay_seconds || 3) * 1000);
          });
        }
      })
      .catch(() => {});
  }

  function sendAuthQuick(type) {
    const messages = {
      'login': 'I want to log in to my account.',
      'register': 'I want to create a new account. Register me.',
      'forgot': 'I forgot my password. Can you help me reset it?'
    };
    sendQuick(messages[type] || type);
  }

  function startProjectRequest() {
    inputBox.value = 'I want to create a new project request. Can you help me set one up?';
    inputBox.dispatchEvent(new Event('input'));
    sendMessage();
  }
  // Native Emoji Picker Logic
  const emojiBtn = document.querySelector('#emoji-btn');
  const customEmojiPicker = document.getElementById('customEmojiPicker');
  const commonEmojis = ['😀','😂','😅','😊','😍','😘','😜','🤔','🙄','😴','😷','🤢','🤯','🤠','🥳','😎','🤓','🧐','🤫','🤥','👍','👎','👏','🤝','🙏','💪','🦵','🦶','👂','👃','🧠','🦷','🦴','👀','👁️','👅','👄','💋','🩸','❤️','💔','💯','💢','💥','💫','💦','💨','🕳️','💣','💬','🗨️','🗯️','💭','💤','🚀','✨','🎉','⭐','🎯','👋','🔥'];
  
  // Build picker
  if (customEmojiPicker && emojiBtn) {
      commonEmojis.forEach(em => {
          const btn = document.createElement('button');
          btn.className = 'native-emoji-btn';
          btn.innerText = em;
          btn.onclick = (e) => {
              e.stopPropagation();
              inputBox.value += em;
              inputBox.focus();
              inputBox.dispatchEvent(new Event('input'));
          };
          customEmojiPicker.appendChild(btn);
      });

      emojiBtn.addEventListener('click', (e) => {
          e.stopPropagation();
          customEmojiPicker.classList.toggle('show');
      });
      
      // Close when clicking outside
      document.addEventListener('click', (e) => {
          if (!customEmojiPicker.contains(e.target) && e.target !== emojiBtn) {
              customEmojiPicker.classList.remove('show');
          }
      });
  }

  // ========== Chat History Panel Logic ==========
  const historyToggleBtn = document.getElementById('historyToggleBtn');
  const closeHistoryBtn = document.getElementById('closeHistoryBtn');
  const historyPanel = document.getElementById('historyPanel');
  const historyList = document.getElementById('historyList');

  if (historyToggleBtn) {
    historyToggleBtn.addEventListener('click', () => {
      historyPanel.classList.add('open');
      loadHistorySessions();
    });
  }

  if (closeHistoryBtn) {
    closeHistoryBtn.addEventListener('click', () => {
      historyPanel.classList.remove('open');
    });
  }

  async function loadHistorySessions() {
    historyList.innerHTML = '<div class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
    try {
      const res = await fetch(pathToRoot + 'agent-server.php', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'fetch_sessions' })
      });
      const data = await res.json();
      if (data.success && data.sessions.length > 0) {
        historyList.innerHTML = '';
        data.sessions.forEach(sess => {
           const dateObj = new Date(sess.created_at);
           const dateStr = dateObj.toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' });
           
           const item = document.createElement('div');
           item.className = 'history-item';
           item.innerHTML = `
             <div style="flex:1;">
               <div class="history-item-top">
                 <div class="topic">${sess.topic}</div>
                 <div class="date">${dateStr}</div>
               </div>
             </div>
             <button class="history-del-btn" onclick="deleteSession(event, '${sess.session_id}')" title="Delete Chat"><i class="fas fa-trash-alt"></i></button>
           `;
           item.addEventListener('click', () => loadSessionHistory(sess.session_id));
           historyList.appendChild(item);
        });
      } else {
        historyList.innerHTML = '<div class="text-center py-4 text-muted" style="font-size:0.9rem;">No past chats found.</div>';
      }
    } catch (e) {
      historyList.innerHTML = '<div class="text-center py-4 text-danger">Failed to load history</div>';
    }
  }

  function showConfirm(message, onConfirm) {
    const overlay = document.createElement('div');
    overlay.className = 'custom-confirm-overlay';

    const card = document.createElement('div');
    card.className = 'custom-confirm-card';

    const text = document.createElement('div');
    text.className = 'custom-confirm-text';
    text.textContent = message;

    const buttons = document.createElement('div');
    buttons.className = 'custom-confirm-buttons';

    const cancelBtn = document.createElement('button');
    cancelBtn.className = 'custom-confirm-btn btn-cancel';
    cancelBtn.textContent = 'Cancel';
    cancelBtn.onclick = () => { overlay.remove(); };

    const confirmBtn = document.createElement('button');
    confirmBtn.className = 'custom-confirm-btn btn-confirm';
    confirmBtn.textContent = 'Delete';
    confirmBtn.onclick = () => {
      overlay.remove();
      onConfirm();
    };

    buttons.appendChild(cancelBtn);
    buttons.appendChild(confirmBtn);
    card.appendChild(text);
    card.appendChild(buttons);
    overlay.appendChild(card);

    const assistantBox = document.getElementById('assistantBox');
    if (assistantBox) {
      assistantBox.appendChild(overlay);
    } else {
      document.body.appendChild(overlay);
    }
  }

  async function deleteSession(e, sessionId) {
    e.stopPropagation();
    showConfirm("Are you sure you want to delete this chat session?", async () => {
      try {
        await fetch(pathToRoot + 'agent-server.php', {
          method: 'POST', headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({ action: 'delete_session', target_session: sessionId })
        });
        loadHistorySessions(); // refresh list
      } catch (err) {}
    });
  }

  async function loadSessionHistory(sessionId) {
    try {
      const res = await fetch(pathToRoot + 'agent-server.php', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'fetch_session_history', target_session: sessionId })
      });
      const data = await res.json();
      if (data.success) {
        // Clear current chat
        const botGreeting = bodyBox.firstElementChild; // Keep greeting
        const quickBtns = bodyBox.lastElementChild; // Keep quick btns
        bodyBox.innerHTML = '';
        if(botGreeting && botGreeting.classList.contains('bubble')) bodyBox.appendChild(botGreeting);
        
        data.history.forEach(chat => {
          const roleClass = chat.role === 'user' ? 'user' : 'bot';
          const timeStr = new Date(chat.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
          const wrapper = document.createElement('div');
          wrapper.className = `bubble-wrapper ${roleClass}`;
          wrapper.innerHTML = `
            <div class="bubble ${roleClass}">${formatMessageText(chat.message)}</div>
            <div class="msg-time">${timeStr}</div>
          `;
          bodyBox.appendChild(wrapper);
        });
        
        if(quickBtns && quickBtns.classList.contains('quick-btns')) bodyBox.appendChild(quickBtns);
        
        historyPanel.classList.remove('open');
        bodyBox.scrollTop = bodyBox.scrollHeight;
      }
    } catch (err) {}
  }

  // Handle "Resume previous chat" prompt for logged in users
  document.addEventListener('DOMContentLoaded', async () => {
    const isLogged = <?= $user_id ? 'true' : 'false' ?>;
    if (isLogged) {
      try {
        const res = await fetch(pathToRoot + 'agent-server.php', {
          method: 'POST', headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({ action: 'fetch_sessions' })
        });
        const data = await res.json();
        // If there's a recent session (e.g. today), and it's not the current one
        if (data.success && data.sessions.length > 0) {
            // Check if current loaded history is empty (meaning new session)
            const chatCount = <?= count($chatHistory) ?>;
            if (chatCount === 0) {
                const lastSession = data.sessions[0];
                const lastDate = new Date(lastSession.created_at);
                const now = new Date();
                const diffHours = (now - lastDate) / (1000 * 60 * 60);
                
                if (diffHours < 24) { // Only prompt if within last 24 hours
                    const promptWrapper = document.createElement('div');
                    promptWrapper.className = 'bubble-wrapper bot fade-in';
                    promptWrapper.innerHTML = `
                      <div class="bubble bot">
                        Welcome back! Would you like to continue our previous chat about <strong>"${lastSession.topic}"</strong>?<br><br>
                        <button onclick="loadSessionHistory('${lastSession.session_id}')" style="background:var(--chat-accent); color:white; border:none; padding:6px 12px; border-radius:12px; font-size:0.85rem; cursor:pointer; margin-top:8px;">Resume Chat</button>
                      </div>
                      <div class="msg-time">Just now</div>
                    `;
                    // Insert right after greeting
                    if (bodyBox.children.length > 1) {
                        bodyBox.insertBefore(promptWrapper, bodyBox.children[1]);
                    } else {
                        bodyBox.appendChild(promptWrapper);
                    }
                }
            }
        }
      } catch (e) {}
    }
  });

  // File Upload Preview Pipeline
  const fileBtn = document.getElementById('file-btn');
  const fileInput = document.getElementById('file-input');
  const filePreview = document.getElementById('filePreview');
  const filePreviewInfo = document.getElementById('filePreviewInfo');
  const clearFileBtn = document.getElementById('clearFileBtn');

  fileBtn.addEventListener('click', () => fileInput.click());
  fileInput.addEventListener('change', () => {
    const file = fileInput.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = function(e) {
         attachedFile = {
            data: e.target.result,
            type: file.type,
            name: file.name
         };
         
         // Build Preview Info HTML
         if (file.type.startsWith('image/')) {
            filePreviewInfo.innerHTML = `<img src="${e.target.result}" style="width:30px; height:30px; object-fit:cover; border-radius:4px; border:1px solid #ccc;" /> <span>${file.name}</span>`;
         } else {
            filePreviewInfo.innerHTML = `<i class="fas fa-file-pdf" style="font-size:1.4rem; color:#dc3545;"></i> <span>${file.name}</span>`;
         }
         
         filePreview.style.display = 'flex';
         updateInputButtons();
      };
      reader.readAsDataURL(file);
    }
  });

  function clearAttachedFile() {
     attachedFile = null;
     fileInput.value = '';
     filePreview.style.display = 'none';
     filePreviewInfo.innerHTML = '';
  }

  clearFileBtn.addEventListener('click', () => {
     clearAttachedFile();
     updateInputButtons();
  });

  bodyBox.addEventListener('wheel', e => e.stopPropagation(), { passive: false });
  bodyBox.addEventListener('touchmove', e => e.stopPropagation(), { passive: false });

  // ====== SUPPORT MODE (Human Handoff) ======
  let supportTicketId = null;
  let supportTicketNumber = null;
  let supportPollInterval = null;

  function requestHumanSupport() {
    appendBotMessage('🔄 Transferring you to a live support agent. One moment please...');
    // Show connecting header immediately
    document.getElementById('botHeader').style.display = 'none';
    const sHeader = document.getElementById('supportHeader');
    sHeader.style.display = 'flex';
    document.getElementById('agentName').textContent = 'Connecting to Live Person...';
    document.getElementById('supportStatusText').textContent = 'Creating your ticket';

    fetch(pathToRoot + 'api/support_handoff.php?action=create_ticket', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'subject=Chat Support Request&message=User requested to speak with a human agent.'
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        supportTicketId = data.ticket_id;
        supportTicketNumber = data.ticket_number;
        document.getElementById('ticketBadge').textContent = '#' + supportTicketNumber;
        document.getElementById('supportStatusText').textContent = 'Waiting for an available agent';
        appendBotMessage('✅ Your ticket <strong>#' + supportTicketNumber + '</strong> has been created. An agent will join shortly.');
        showSupportButton();
        startPollingForAgent();
      } else {
        appendBotMessage('❌ Sorry, we couldn\'t create a support ticket. ' + (data.error || ''));
        exitSupportMode();
      }
    })
    .catch(() => {
      appendBotMessage('❌ Connection error. Please try again.');
      exitSupportMode();
    });
  }

  function showSupportButton() {
    const btn = document.createElement('div');
    btn.className = 'quick-btns';
    btn.innerHTML = '<button class="link-person-btn" onclick="checkSupportStatus()"><i class="fas fa-sync-alt me-2"></i>Check if agent has joined</button>';
    bodyBox.appendChild(btn);
    bodyBox.scrollTop = bodyBox.scrollHeight;
  }

  function startPollingForAgent() {
    // Check every 5 seconds if agent assigned
    if (supportPollInterval) clearInterval(supportPollInterval);
    supportPollInterval = setInterval(() => {
      if (!supportTicketId) return;
      fetch(pathToRoot + 'api/support_handoff.php?action=my_active_ticket')
        .then(r => r.json())
        .then(data => {
          if (data.success && data.ticket && data.ticket.assigned_to) {
            clearInterval(supportPollInterval);
            supportPollInterval = null;
            enterSupportMode(data.ticket);
          } else if (data.resolved_ticket) {
            clearInterval(supportPollInterval);
            supportPollInterval = null;
            const rt = data.resolved_ticket;
            appendBotMessage('✅ Your ticket <strong>#' + rt.ticket_number + '</strong> has been resolved by <strong>' + (rt.agent_name || 'the support team') + '. Thank you for reaching out!');
          }
        })
        .catch(() => {});
    }, 5000);
  }

  function checkSupportStatus() {
    if (!supportTicketId) {
      requestHumanSupport();
      return;
    }
    fetch(pathToRoot + 'api/support_handoff.php?action=my_active_ticket')
      .then(r => r.json())
      .then(data => {
        if (data.success && data.ticket) {
          if (data.ticket.assigned_to) {
            clearInterval(supportPollInterval);
            supportPollInterval = null;
            enterSupportMode(data.ticket);
          } else {
            appendBotMessage('⏳ No agent has picked up your ticket yet. Please wait...');
          }
        } else if (data.resolved_ticket) {
          clearInterval(supportPollInterval);
          supportPollInterval = null;
          const rt = data.resolved_ticket;
          appendBotMessage('✅ Your ticket <strong>#' + rt.ticket_number + '</strong> has been resolved by <strong>' + (rt.agent_name || 'the support team') + '. Thank you for your patience!');
        } else {
          appendBotMessage('ℹ️ No active support ticket found. Creating a new one...');
          requestHumanSupport();
        }
      })
      .catch(() => {
        appendBotMessage('❌ Error checking status.');
      });
  }

  function enterSupportMode(ticket) {
    supportTicketId = ticket.id;
    supportTicketNumber = ticket.ticket_number;
    document.getElementById('ticketBadge').textContent = '#' + supportTicketNumber;

    // Switch header — hide WiseBot completely, show human agent
    document.getElementById('botHeader').style.display = 'none';
    const sHeader = document.getElementById('supportHeader');
    sHeader.style.display = 'flex';

    // Set agent or client info
    let displayName = ticket.agent_name || 'Support Agent';
    let displayPic = ticket.agent_picture || '';
    
    // If the current user is the assigned agent, display the client's info instead
    if (CURRENT_USER_ID && ticket.assigned_to == CURRENT_USER_ID) {
        displayName = ticket.client_name || 'Client';
        displayPic = ticket.client_picture || '';
        document.getElementById('leaveChatAdminBtn').style.display = 'inline-block';
    } else {
        document.getElementById('leaveChatAdminBtn').style.display = 'none';
    }

    document.getElementById('agentName').textContent = displayName;
    document.getElementById('supportStatusText').textContent = 'Searching...';
    
    const avatarEl = document.getElementById('agentAvatar');
    if (displayPic) {
      avatarEl.innerHTML = '<img src="' + pathToRoot + displayPic + '" alt="User">';
    } else {
      const initial = displayName.charAt(0).toUpperCase();
      avatarEl.textContent = initial;
      avatarEl.style.background = 'rgba(255,255,255,0.2)';
      avatarEl.style.display = 'flex';
      avatarEl.style.alignItems = 'center';
      avatarEl.style.justifyContent = 'center';
      avatarEl.style.fontWeight = '700';
      avatarEl.style.fontSize = '1.2rem';
    }

    // Switch to support mode — WiseBot is now OFF
    supportMode = true;

    // CLEAR ALL old bot messages — only human conversation from now on
    const allBubbles = bodyBox.querySelectorAll('.bubble, .support-msg-bubble, .support-mode-indicator, .quick-btns, .link-person-btn');
    allBubbles.forEach(el => el.remove());

    // Show mode indicator
    const indicator = document.createElement('div');
    indicator.className = 'support-mode-indicator';
    indicator.innerHTML = '<i class="fas fa-headset"></i> Live Support · <strong>' + displayName + '</strong> · Ticket #' + supportTicketNumber;
    bodyBox.appendChild(indicator);

    // Load existing conversation from the ticket
    renderSupportMessages();

    // Start polling for new replies
    if (supportPollInterval) clearInterval(supportPollInterval);
    supportPollInterval = setInterval(() => {
      if (!supportTicketId) return;
      // Check ticket status for resolution
      fetch(pathToRoot + 'api/support_handoff.php?action=my_active_ticket')
        .then(r => r.json())
        .then(statusData => {
          if (statusData.resolved_ticket || (!statusData.success && !statusData.ticket)) {
            clearInterval(supportPollInterval);
            supportPollInterval = null;
            appendSupportMessage('system', '✅ <strong>This ticket has been resolved.</strong> Thank you for reaching out!');
            supportMode = false;
            setTimeout(() => {
              document.getElementById('botHeader').style.display = 'flex';
              document.getElementById('supportHeader').style.display = 'none';
            }, 3000);
            return;
          }
          // Update agent info if changed
          if (statusData.ticket) {
            let displayName = statusData.ticket.agent_name || 'Support Agent';
            let isOnline = true; // Assume agent online by default
            
            if (CURRENT_USER_ID && statusData.ticket.assigned_to == CURRENT_USER_ID) {
                displayName = statusData.ticket.client_name || 'Client';
                isOnline = false;
                if (statusData.ticket.client_last_login) {
                    const lastActive = new Date(statusData.ticket.client_last_login).getTime();
                    if ((Date.now() - lastActive) < 300000) { // 5 minutes
                        isOnline = true;
                    }
                }
            }
            
            document.getElementById('agentName').textContent = displayName;
            
            if (isOnline) {
                document.getElementById('supportStatusText').innerHTML = '<span style="color:#22c55e;">🟢 Online</span>';
            } else {
                document.getElementById('supportStatusText').innerHTML = '<span style="color:#94a3b8;">⚪ Offline</span>';
            }
          }
          // Still active — fetch new replies
          return fetch(pathToRoot + 'api/support_handoff.php?action=get_replies&ticket_id=' + supportTicketId);
        })
        .then(r => r ? r.json() : null)
        .then(data => {
          if (data && data.success) {
            renderSupportMessages(data.replies);
          }
        })
        .catch(() => {});

      // Poll agent typing status
      fetch(pathToRoot + 'api/support_handoff.php?action=get_typing&ticket_id=' + supportTicketId)
        .then(r => r.json())
        .then(tData => {
          if (tData.success && tData.typers && tData.typers.length > 0) {
            renderTypingIndicators(tData.typers);
          } else {
            renderTypingIndicators([]);
          }
        })
        .catch(() => {});
    }, 4000);
  }

  function adminLeaveChat() {
    if (!supportTicketId) return;
    Swal.fire({
        title: 'Leave this chat?',
        text: 'This will put the ticket back in the available queue.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="fas fa-sign-out-alt me-1"></i> Yes, leave'
    }).then((result) => {
        if (result.isConfirmed) {
            const fd = new URLSearchParams();
            fd.append('action', 'leave_chat');
            fd.append('ticket_id', supportTicketId);
            
            // Use ticket_api for admin operations
            fetch(pathToRoot + 'api/ticket_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: fd.toString()
            }).then(r => r.json())
            .then(data => {
                if(data.success) {
                    exitSupportMode();
                    Swal.fire({icon:'success', title:'Left Chat', text:'Ticket returned to queue.', timer:1500, showConfirmButton:false});
                } else {
                    Swal.fire({icon:'error', title:'Error', text: data.message || "Unknown error"});
                }
            }).catch(e => {
                console.error(e);
                exitSupportMode(); // Fallback
            });
        }
    });
  }

  function startVoiceCallUI() {
      // Get the overlay element
      const overlay = document.getElementById('callingOverlay');
      if (overlay) {
          overlay.style.display = 'flex';
          
          // Optionally grab the actual user's info to display
          const agentNameEl = document.getElementById('agentName');
          const callNameEl = document.getElementById('callName');
          if(agentNameEl && callNameEl && agentNameEl.textContent !== 'Connecting to Live Person...') {
              callNameEl.textContent = agentNameEl.textContent;
          }
          
          // Close button logic for the call overlay
          const endBtn = document.getElementById('endCallBtn');
          if (endBtn) {
              endBtn.onclick = function() {
                  overlay.style.display = 'none';
              };
          }
      }
  }

  function exitSupportMode() {
    supportMode = false;
    supportTicketId = null;
    supportTicketNumber = null;

    // Clear all support messages
    const allSupport = bodyBox.querySelectorAll('.support-msg-bubble, .support-mode-indicator');
    allSupport.forEach(el => el.remove());

    // Restore bot
    document.getElementById('botHeader').style.display = 'flex';
    document.getElementById('supportHeader').style.display = 'none';

    if (supportPollInterval) {
      clearInterval(supportPollInterval);
      supportPollInterval = null;
    }

    // Show quick buttons again
    const qb = bodyBox.querySelector('.quick-btns');
    if (qb) qb.style.display = 'flex';
    updateInputButtons();
  }

  let supportMode = false;
  let lastReplyCount = 0;
  let typingTimeout = null;
  let agentTypingEl = null;
  let activeTypers = {};

  /* ─── Send typing status to server ─── */
  function sendTypingStatus(isTyping) {
    if (!supportMode || !supportTicketId) return;
    var agentName = document.getElementById('agentName').textContent || 'You';
    var agentPic = document.getElementById('agentAvatar').querySelector('img');
    var picUrl = agentPic ? agentPic.src : '';
    var fd = new URLSearchParams();
    fd.append('ticket_id', supportTicketId);
    fd.append('is_typing', isTyping ? 1 : 0);
    fd.append('display_name', agentName);
    fd.append('avatar_url', picUrl);
    fetch(pathToRoot + 'api/support_handoff.php?action=typing', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: fd.toString()
    }).catch(() => {});
  }

  /* ─── Show/hide multi-participant typing indicators ─── */
  function renderTypingIndicators(typers) {
    /* Remove old typing elements */
    if (agentTypingEl) { agentTypingEl.remove(); agentTypingEl = null; }

    if (!typers || typers.length === 0) return;

    var names = typers.map(function(t) {
      return t.name || 'Someone';
    });
    var label = names.length === 1 ? names[0] : names.join(' & ');

    agentTypingEl = document.createElement('div');
    agentTypingEl.className = 'agent-typing-indicator';
    agentTypingEl.innerHTML = '<div class="dots"><span></span><span></span><span></span></div><span>' + label + ' is typing</span>';
    bodyBox.appendChild(agentTypingEl);
    bodyBox.scrollTop = bodyBox.scrollHeight;
  }

  /* ─── Input event: detect user typing ─── */
  inputBox.addEventListener('input', function() {
    if (!supportMode) return;
    sendTypingStatus(true);
    clearTimeout(typingTimeout);
    typingTimeout = setTimeout(() => sendTypingStatus(false), 2000);
  });

  function renderSupportMessages(replies) {
    if (!supportMode) return;

    // Load existing replies from the ticket (no bot messages mixed in)
    if (!replies || replies.length === 0) {
      // First load — fetch from API
      fetch(pathToRoot + 'api/support_handoff.php?action=get_replies&ticket_id=' + supportTicketId)
        .then(r => r.json())
        .then(data => {
          if (data.success && data.replies && data.replies.length > 0) {
            data.replies.forEach(reply => {
              const isMine = (reply.user_id == CURRENT_USER_ID);
              const type = isMine ? 'client' : 'agent'; // client=right side, agent=left side
              const name = isMine ? 'You' : (reply.sender_name || 'User');
              appendSupportMessage(type, reply.message, name, true);
            });
            bodyBox.scrollTop = bodyBox.scrollHeight;
          }
        })
        .catch(() => {});
      return;
    }

    // Re-render all replies (called by polling)
    const oldSupport = bodyBox.querySelectorAll('.support-msg-bubble');
    oldSupport.forEach(el => el.remove());

    replies.forEach(reply => {
      const isMine = (reply.user_id == CURRENT_USER_ID);
      const type = isMine ? 'client' : 'agent'; // client=right side, agent=left side
      const name = isMine ? 'You' : (reply.sender_name || 'User');
      appendSupportMessage(type, reply.message, name, true);
    });
    bodyBox.scrollTop = bodyBox.scrollHeight;
  }

  function appendSupportMessage(type, text, senderName, skipScroll) {
    const bubble = document.createElement('div');
    bubble.className = 'support-msg-bubble ' + type;
    const nameHtml = senderName ? '<div style="font-size:0.7rem;font-weight:700;margin-bottom:2px;opacity:0.7;">' + senderName + '</div>' : '';
    bubble.innerHTML = nameHtml + text;
    bodyBox.appendChild(bubble);
    if (!skipScroll) bodyBox.scrollTop = bodyBox.scrollHeight;
  }

  function appendBotMessage(text) {
    const wrapper = document.createElement('div');
    wrapper.className = 'bubble-wrapper bot';
    const bubble = document.createElement('div');
    bubble.className = 'bubble bot';
    bubble.innerHTML = text;
    wrapper.appendChild(bubble);
    const time = document.createElement('div');
    time.className = 'msg-time';
    time.textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    wrapper.appendChild(time);
    bodyBox.appendChild(wrapper);
    bodyBox.scrollTop = bodyBox.scrollHeight;
  }

  function closeChat() {
    chatBox.classList.remove('fade-in');
    chatBox.classList.add('fade-out');
    chatBox.classList.remove('show');
    lockBodyScroll(false);
    const icon = toggleBtn.querySelector('i');
    icon.classList.add('rotate');
    icon.classList.remove('fa-chevron-down');
    icon.classList.add('fa-robot');
    setTimeout(() => icon.classList.remove('rotate'), 300);
  }

  // Override sendMessage to handle support mode
  const originalSendMessage = sendMessage;
  sendMessage = function() {
    if (supportMode && supportTicketId) {
      const message = inputBox.value.trim();
      if (!message) return;
      appendSupportMessage('client', message, 'You');
      renderTypingIndicators([]);
      sendTypingStatus(false);
      clearTimeout(typingTimeout);
      inputBox.value = '';
      updateInputButtons();
      inputBox.style.height = '38px';

      fetch(pathToRoot + 'api/support_handoff.php?action=send_reply', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'ticket_id=' + supportTicketId + '&message=' + encodeURIComponent(message)
      })
      .then(r => r.json())
      .then(data => {
        if (!data.success) {
          appendBotMessage('❌ Failed to send: ' + (data.error || ''));
        }
      })
      .catch(() => {});
      return;
    }
    originalSendMessage();
  };

  // Check for active bot-origin ticket on open
  document.addEventListener('DOMContentLoaded', function() {
    fetch(pathToRoot + 'api/support_handoff.php?action=my_active_ticket')
      .then(r => r.json())
      .then(data => {
        if (data.success && data.ticket && data.ticket.assigned_to) {
          supportTicketId = data.ticket.id;
          supportTicketNumber = data.ticket.ticket_number;
          enterSupportMode(data.ticket);
        } else if (data.resolved_ticket) {
          // Show resolved ticket message
          const rt = data.resolved_ticket;
          appendBotMessage('✅ Your ticket <strong>#' + rt.ticket_number + '</strong> has been resolved by <strong>' + (rt.agent_name || 'the support team') + '. If you need further help, feel free to open a new chat.');
        }
      })
      .catch(() => {});
  });

  const style = document.createElement('style');
  style.textContent = `
    .bubble {
      animation: fadeIn 0.3s ease-in-out;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .typing-indicator {
      display: flex;
      gap: 4px;
      margin: 8px 0;
    }
    .typing-indicator span {
      width: 8px;
      height: 8px;
      background: gray;
      border-radius: 50%;
      animation: bounce 1s infinite alternate;
    }
    .typing-indicator span:nth-child(2) {
      animation-delay: 0.2s;
    }
    .typing-indicator span:nth-child(3) {
      animation-delay: 0.4s;
    }
    @keyframes bounce {
      from { transform: translateY(0); opacity: 0.3; }
      to { transform: translateY(-8px); opacity: 1; }
    }
    .rotate {
      transition: transform 0.3s ease;
      transform: rotate(180deg);
    }
    .fade-in {
      animation: fadeInBox 0.4s ease forwards;
    }
    .fade-out {
      animation: fadeOutBox 0.4s ease forwards;
    }
    @keyframes fadeInBox {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    @keyframes fadeOutBox {
      from { opacity: 1; transform: translateY(0); }
      to { opacity: 0; transform: translateY(20px); }
    }
  `;
  document.head.appendChild(style);

  // ====== VOICE AGENT SYSTEM ======
  (function() {
    const voiceBtnEl = document.getElementById('voiceBtn');
    const speakerToggleEl = document.getElementById('speakerToggle');
    const voiceWaveEl = document.getElementById('voiceWave');
    const voiceStatusBar = document.getElementById('voiceStatusBar');
    const voiceStatusIcon = document.getElementById('voiceStatusIcon');
    const voiceStatusText = document.getElementById('voiceStatusText');
    const thinkingDotsEl = document.getElementById('thinkingDots');
    const voicePlayingIndicator = document.getElementById('voicePlayingIndicator');

    if (!voiceBtnEl) return;

    let voiceState = 'idle'; // idle, listening, processing, thinking, speaking, muted, disconnected, error
    let mediaRecorder = null;
    let audioChunks = [];
    let audioContext = null;
    let analyser = null;
    let currentAudio = null;
    let speakerEnabled = localStorage.getItem('wisebot-speaker') !== 'off';
    let isRecording = false;
    let recordingTimeout = null;

    // Initialize speaker state
    if (!speakerEnabled) {
      speakerToggleEl.classList.add('muted');
      speakerToggleEl.querySelector('i').className = 'fas fa-volume-mute';
    }

    function setVoiceState(state, message) {
      voiceState = state;
      voiceStatusBar.classList.remove('active', 'listening', 'processing', 'thinking', 'speaking', 'error');
      voiceWaveEl.classList.remove('active');
      voiceBtnEl.classList.remove('listening');
      thinkingDotsEl.style.display = 'none';
      voicePlayingIndicator.classList.remove('active');

      switch(state) {
        case 'idle':
          voiceStatusBar.classList.remove('active');
          break;
        case 'listening':
          voiceStatusBar.classList.add('active', 'listening');
          voiceStatusIcon.className = 'fas fa-microphone';
          voiceStatusText.textContent = message || 'Listening...';
          voiceWaveEl.classList.add('active');
          voiceBtnEl.classList.add('listening');
          break;
        case 'processing':
          voiceStatusBar.classList.add('active', 'processing');
          voiceStatusIcon.className = 'fas fa-cog fa-spin';
          voiceStatusText.textContent = message || 'Processing speech...';
          break;
        case 'thinking':
          voiceStatusBar.classList.add('active', 'thinking');
          voiceStatusIcon.className = 'fas fa-brain';
          voiceStatusText.textContent = message || 'Thinking...';
          thinkingDotsEl.style.display = 'inline-flex';
          break;
        case 'speaking':
          voiceStatusBar.classList.add('active', 'speaking');
          voiceStatusIcon.className = 'fas fa-volume-up';
          voiceStatusText.textContent = message || 'Speaking...';
          voicePlayingIndicator.classList.add('active');
          break;
        case 'error':
          voiceStatusBar.classList.add('active', 'error');
          voiceStatusIcon.className = 'fas fa-exclamation-triangle';
          voiceStatusText.textContent = message || 'Error occurred';
          setTimeout(() => setVoiceState('idle'), 3000);
          break;
        case 'muted':
          voiceStatusBar.classList.add('active', 'error');
          voiceStatusIcon.className = 'fas fa-volume-mute';
          voiceStatusText.textContent = 'Voice responses muted';
          setTimeout(() => setVoiceState('idle'), 2000);
          break;
      }
    }

    async function startRecording() {
      try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true } });
        audioContext = new (window.AudioContext || window.webkitAudioContext)();
        analyser = audioContext.createAnalyser();
        const source = audioContext.createMediaStreamSource(stream);
        source.connect(analyser);
        analyser.fftSize = 256;

        mediaRecorder = new MediaRecorder(stream, { mimeType: MediaRecorder.isTypeSupported('audio/webm;codecs=opus') ? 'audio/webm;codecs=opus' : 'audio/webm' });
        audioChunks = [];

        mediaRecorder.ondataavailable = (e) => { if (e.data.size > 0) audioChunks.push(e.data); };

        mediaRecorder.onstop = async () => {
          stream.getTracks().forEach(t => t.stop());
          if (audioContext) { audioContext.close(); audioContext = null; }
          if (!isRecording) return;
          isRecording = false;

          setVoiceState('processing');
          const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
          const reader = new FileReader();
          reader.onloadend = async () => {
            const base64Audio = reader.result.split(',')[1];
            const format = audioBlob.type.includes('webm') ? 'webm' : 'webm';

            try {
              setVoiceState('thinking');

              const response = await fetch(pathToRoot + 'api/voice_chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                  action: 'voice_chat',
                  audio: base64Audio,
                  format: format,
                  language: 'en',
                  auto_tts: speakerEnabled
                })
              });

              const data = await response.json();
              if (data.success) {
                // Show user's transcribed text
                if (data.user_text) {
                  const userWrapper = document.createElement('div');
                  userWrapper.className = 'bubble-wrapper user';
                  const userBubble = document.createElement('div');
                  userBubble.className = 'bubble user';
                  userBubble.innerHTML = '<span style="font-size:0.7rem;opacity:0.7;"><i class="fas fa-microphone"></i> Voice</span><br>' + formatMessageText(data.user_text);
                  userWrapper.appendChild(userBubble);
                  const timeEl = document.createElement('div');
                  timeEl.className = 'msg-time';
                  timeEl.innerHTML = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) + ' <i class="fas fa-check-double" style="font-size:0.6rem;"></i>';
                  userWrapper.appendChild(timeEl);
                  bodyBox.appendChild(userWrapper);
                  bodyBox.scrollTop = bodyBox.scrollHeight;
                }

                // Show bot reply
                if (data.reply) {
                  appendMessage('bot', data.reply);
                  receiveSound.play();
                }

                // Play audio response
                if (data.audio && speakerEnabled) {
                  setVoiceState('speaking');
                  playAudioResponse(data.audio, data.audio_format || 'mp3');
                } else {
                  setVoiceState('idle');
                }
              } else {
                setVoiceState('error', data.error || 'Voice processing failed');
                appendMessage('bot', '⚠️ ' + (data.error || 'Could not process voice message.'));
              }
            } catch (err) {
              console.error('Voice chat error:', err);
              setVoiceState('error', 'Connection error');
              appendMessage('bot', '⚠️ Voice service unavailable. Please try text chat.');
            }
          };
          reader.readAsDataURL(audioBlob);
        };

        mediaRecorder.start();
        isRecording = true;
        setVoiceState('listening');

        // Auto-stop after 30 seconds
        recordingTimeout = setTimeout(() => { if (isRecording && mediaRecorder && mediaRecorder.state === 'recording') { stopRecording(); } }, 30000);

      } catch (err) {
        console.error('Microphone access error:', err);
        setVoiceState('error', 'Microphone access denied');
        appendMessage('bot', '⚠️ Please allow microphone access to use voice chat. Click the lock icon in your browser address bar.');
      }
    }

    function stopRecording() {
      if (recordingTimeout) { clearTimeout(recordingTimeout); recordingTimeout = null; }
      if (mediaRecorder && mediaRecorder.state === 'recording') {
        mediaRecorder.stop();
      }
    }

    function playAudioResponse(base64Audio, format) {
      if (currentAudio) { currentAudio.pause(); currentAudio = null; }
      try {
        const audioSrc = 'data:audio/' + format + ';base64,' + base64Audio;
        currentAudio = new Audio(audioSrc);
        currentAudio.onended = () => { setVoiceState('idle'); currentAudio = null; };
        currentAudio.onerror = () => { setVoiceState('idle'); currentAudio = null; };
        currentAudio.play().catch(() => { setVoiceState('idle'); });
      } catch (e) { setVoiceState('idle'); }
    }

    // Voice button click handler
    voiceBtnEl.addEventListener('click', () => {
      if (supportMode) return; // Don't record in support mode

      if (voiceState === 'listening') {
        stopRecording();
      } else if (voiceState === 'idle' || voiceState === 'error') {
        // Stop any playing audio before recording
        if (currentAudio) { currentAudio.pause(); currentAudio = null; setVoiceState('idle'); }
        startRecording();
      }
    });

    // Speaker toggle handler
    speakerToggleEl.addEventListener('click', () => {
      speakerEnabled = !speakerEnabled;
      localStorage.setItem('wisebot-speaker', speakerEnabled ? 'on' : 'off');
      if (speakerEnabled) {
        speakerToggleEl.classList.remove('muted');
        speakerToggleEl.querySelector('i').className = 'fas fa-volume-up';
      } else {
        speakerToggleEl.classList.add('muted');
        speakerToggleEl.querySelector('i').className = 'fas fa-volume-mute';
        if (currentAudio) { currentAudio.pause(); currentAudio = null; setVoiceState('idle'); }
      }
    });

    // Expose voice functions globally
    window.wisebotVoice = { state: () => voiceState, stop: stopRecording, setSpeaker: (on) => { speakerEnabled = on; localStorage.setItem('wisebot-speaker', on ? 'on' : 'off'); } };
  })();

  // ====== CALLING UI ======
  (function() {
    const callBtn = document.getElementById('callBtn');
    const callingOverlay = document.getElementById('callingOverlay');
    const endCallBtn = document.getElementById('endCallBtn');
    const callStatus = document.getElementById('callStatus');
    const callDuration = document.getElementById('callDuration');
    const callName = document.getElementById('callName');
    const assistantBox = document.getElementById('assistantBox');

    if (!callBtn || !callingOverlay) return;

    let callActive = false;
    let callTimer = null;
    let callSeconds = 0;
    let callStream = null;
    let callAudioContext = null;
    let callAnalyser = null;
    let callAnimFrame = null;
    
    // VAD & Recording state
    let isSpeaking = false;
    let silenceStart = 0;
    let callMediaRecorder = null;
    let callAudioChunks = [];
    let currentResponseAudio = null;
    let isWaitingForResponse = false;
    let currentVADLoop = null;

    function formatTime(s) {
      const m = Math.floor(s / 60).toString().padStart(2, '0');
      const sec = (s % 60).toString().padStart(2, '0');
      return m + ':' + sec;
    }

    function startCallTimer() {
      callSeconds = 0;
      callDuration.style.display = 'block';
      callDuration.textContent = '00:00';
      callTimer = setInterval(function() {
        callSeconds++;
        callDuration.textContent = formatTime(callSeconds);
      }, 1000);
    }

    function stopCallTimer() {
      if (callTimer) { clearInterval(callTimer); callTimer = null; }
      callDuration.style.display = 'none';
    }

    function stopCallStream() {
      if (callMediaRecorder && callMediaRecorder.state === 'recording') callMediaRecorder.stop();
      if (currentVADLoop) cancelAnimationFrame(currentVADLoop);
      if (currentResponseAudio) { currentResponseAudio.pause(); currentResponseAudio = null; }
      if (callStream) { callStream.getTracks().forEach(t => t.stop()); callStream = null; }
      if (callAudioContext) { callAudioContext.close(); callAudioContext = null; }
    }

    async function sendCallAudio() {
        if (!callActive) return;
        isWaitingForResponse = true;
        callStatus.textContent = 'Thinking...';
        
        const audioBlob = new Blob(callAudioChunks, { type: 'audio/webm' });
        const reader = new FileReader();
        reader.onloadend = async () => {
            const base64Audio = reader.result.split(',')[1];
            try {
                const response = await fetch(pathToRoot + 'api/voice_chat.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'voice_chat',
                        audio: base64Audio,
                        format: 'webm',
                        language: 'en',
                        auto_tts: true
                    })
                });
                const data = await response.json();
                if (data.success && data.audio && callActive) {
                    callStatus.textContent = 'Agent Speaking...';
                    const audioSrc = 'data:audio/' + (data.audio_format || 'mp3') + ';base64,' + data.audio;
                    currentResponseAudio = new Audio(audioSrc);
                    currentResponseAudio.onended = () => {
                        currentResponseAudio = null;
                        if (callActive) startVADRecording();
                    };
                    currentResponseAudio.onerror = () => {
                        currentResponseAudio = null;
                        if (callActive) startVADRecording();
                    };
                    currentResponseAudio.play().catch(() => {
                        currentResponseAudio = null;
                        if (callActive) startVADRecording();
                    });
                } else {
                    console.error("Voice API Error:", data.error);
                    callStatus.textContent = data.error || 'Failed to process audio';
                    setTimeout(() => { if (callActive) startVADRecording(); }, 3000);
                }
            } catch (err) {
                console.error(err);
                callStatus.textContent = 'Connection Error';
                setTimeout(() => { if (callActive) startVADRecording(); }, 3000);
            }
        };
        reader.readAsDataURL(audioBlob);
    }

    function startVADRecording() {
        if (!callActive || callStream == null) return;
        isWaitingForResponse = false;
        callStatus.textContent = 'Listening...';
        callAudioChunks = [];
        
        callMediaRecorder = new MediaRecorder(callStream, { mimeType: MediaRecorder.isTypeSupported('audio/webm;codecs=opus') ? 'audio/webm;codecs=opus' : 'audio/webm' });
        callMediaRecorder.ondataavailable = e => { if (e.data.size > 0) callAudioChunks.push(e.data); };
        callMediaRecorder.onstop = () => {
            if (callActive && callAudioChunks.length > 0) {
                sendCallAudio();
            }
        };
        callMediaRecorder.start();
        
        isSpeaking = false;
        silenceStart = Date.now();
        let recordingStart = Date.now();
        
        const dataArray = new Uint8Array(callAnalyser.frequencyBinCount);
        
        function detectSilence() {
            if (!callActive || isWaitingForResponse) return;
            callAnalyser.getByteTimeDomainData(dataArray);
            let maxVol = 0;
            for(let i=0; i<dataArray.length; i++) {
                let v = Math.abs(dataArray[i] - 128);
                if (v > maxVol) maxVol = v;
            }
            
            // Force cutoff after 10s of speaking OR 15s of silence
            if (isSpeaking && (Date.now() - recordingStart > 10000)) {
                isSpeaking = false;
                if (callMediaRecorder.state === 'recording') {
                    callMediaRecorder.stop();
                    return; 
                }
            } else if (!isSpeaking && (Date.now() - recordingStart > 15000)) {
                if (callMediaRecorder.state === 'recording') {
                    callMediaRecorder.stop();
                    return; 
                }
            }
            
            // Amplitude threshold (3 is very low, catches almost any mic input)
            if (maxVol > 3) {
                if (!isSpeaking) {
                    isSpeaking = true;
                    recordingStart = Date.now();
                }
                silenceStart = Date.now();
                callStatus.textContent = 'You are speaking...';
            } else {
                if (isSpeaking) {
                    if (Date.now() - silenceStart > 1500) {
                        isSpeaking = false;
                        if (callMediaRecorder.state === 'recording') {
                            callMediaRecorder.stop();
                            return; 
                        }
                    }
                } else {
                    silenceStart = Date.now(); 
                }
            }
            currentVADLoop = requestAnimationFrame(detectSilence);
        }
        detectSilence();
    }

    async function initCall() {
      try {
          const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
          
          callStream = stream;
          callAudioContext = new (window.AudioContext || window.webkitAudioContext)();
          callAnalyser = callAudioContext.createAnalyser();
          callAnalyser.fftSize = 256;
          var source = callAudioContext.createMediaStreamSource(stream);
          source.connect(callAnalyser);

          callStatus.textContent = 'Connected';
          startCallTimer();
          startVADRecording();
      } catch (err) {
          console.error('Microphone access error:', err);
          callStatus.textContent = 'Mic Error';
          
          let errorMsg = 'Could not access microphone. Please ensure it is connected and permissions are granted.';
          if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
              errorMsg = 'Microphone permission was blocked. Please click the lock icon in your browser address bar and allow microphone access.';
          } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
              errorMsg = 'No microphone device was found on this computer.';
          } else if (err.message) {
              errorMsg = err.message;
          } else if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
              errorMsg = 'Your browser does not support microphone access on this page (HTTPS or localhost required).';
          }

          if (typeof Swal !== 'undefined') {
              Swal.fire({ icon: 'error', title: 'Microphone Required', text: errorMsg, confirmButtonColor: '#0f766e' });
          } else {
              alert(errorMsg);
          }

          setTimeout(() => {
              callActive = false;
              callingOverlay.style.display = 'none';
              callStatus.textContent = 'Calling...';
          }, 500);
      }
    }

    callBtn.addEventListener('click', function() {
      if (callActive) return;
      callActive = true;
      callingOverlay.style.display = 'flex';
      initCall();
    });

    endCallBtn.addEventListener('click', function() {
      callActive = false;
      stopCallTimer();
      stopCallStream();
      callStatus.textContent = 'Call Ended';
      setTimeout(function() {
        callingOverlay.style.display = 'none';
        callStatus.textContent = 'Calling...';
        callSeconds = 0;
      }, 1000);
    });
  })();
</script>
