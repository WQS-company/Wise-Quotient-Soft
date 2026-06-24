<?php
session_start();
$path_to_root = "../";
require_once $path_to_root . 'config.php';

// Access control
if (!isset($_SESSION['user']['id']) || strtolower($_SESSION['user']['role']) !== 'admin') {
    header("Location: " . $path_to_root . "login.php");
    exit;
}

$success_msg = '';
$error_msg = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add') {
            $name = trim($_POST['provider_name']);
            $endpoint = trim($_POST['api_endpoint']);
            $key = trim($_POST['api_key']);
            $model = trim($_POST['default_model']);
            
            if (!$name || !$endpoint || !$model) {
                throw new Exception("Name, endpoint, and model are required.");
            }

            $stmt = $pdo->prepare("INSERT INTO ai_providers (provider_name, api_endpoint, api_key, default_model, status) VALUES (?, ?, ?, ?, 'untested')");
            $stmt->execute([$name, $endpoint, $key, $model]);
            $success_msg = "New AI Provider added successfully.";
        }
        elseif ($action === 'edit') {
            $id = (int)$_POST['id'];
            $name = trim($_POST['provider_name']);
            $endpoint = trim($_POST['api_endpoint']);
            $key = trim($_POST['api_key']);
            $model = trim($_POST['default_model']);

            if (!$name || !$endpoint || !$model) {
                throw new Exception("Name, endpoint, and model are required.");
            }

            if (!empty($key) && $key !== '********') {
                $stmt = $pdo->prepare("UPDATE ai_providers SET provider_name=?, api_endpoint=?, api_key=?, default_model=?, status='untested' WHERE id=?");
                $stmt->execute([$name, $endpoint, $key, $model, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE ai_providers SET provider_name=?, api_endpoint=?, default_model=?, status='untested' WHERE id=?");
                $stmt->execute([$name, $endpoint, $model, $id]);
            }
            $success_msg = "Provider updated successfully.";
        }
        elseif ($action === 'set_active') {
            $id = (int)$_POST['id'];
            $pdo->exec("UPDATE ai_providers SET is_active = 0");
            $pdo->prepare("UPDATE ai_providers SET is_active = 1 WHERE id = ?")->execute([$id]);
            $success_msg = "Active AI Provider switched successfully.";
        }
        elseif ($action === 'delete') {
            $id = (int)$_POST['id'];
            $check = $pdo->prepare("SELECT is_active FROM ai_providers WHERE id=?");
            $check->execute([$id]);
            if ($check->fetchColumn()) {
                throw new Exception("Cannot delete the currently active provider. Switch to another one first.");
            }
            $pdo->prepare("DELETE FROM ai_providers WHERE id=?")->execute([$id]);
            $success_msg = "Provider deleted.";
        }
    } catch (Exception $e) {
        $error_msg = $e->getMessage();
    }
}

// Fetch all providers
$providers = $pdo->query("SELECT * FROM ai_providers ORDER BY is_active DESC, id ASC")->fetchAll(PDO::FETCH_ASSOC);

// Helper function to map provider name to a URL for getting the API key
function getProviderKeyUrl($name) {
    $n = strtolower($name);
    if (strpos($n, 'openrouter') !== false) return 'https://openrouter.ai/keys';
    if (strpos($n, 'groq') !== false) return 'https://console.groq.com/keys';
    if (strpos($n, 'together') !== false) return 'https://api.together.xyz/settings/api-keys';
    if (strpos($n, 'gemini') !== false) return 'https://aistudio.google.com/app/apikey';
    if (strpos($n, 'sambanova') !== false) return 'https://cloud.sambanova.ai/apis';
    if (strpos($n, 'huggingface') !== false) return 'https://huggingface.co/settings/tokens';
    if (strpos($n, 'mistral') !== false) return 'https://console.mistral.ai/api-keys';
    if (strpos($n, 'github') !== false) return 'https://github.com/marketplace/models';
    if (strpos($n, 'deepseek') !== false) return 'https://platform.deepseek.com/api_keys';
    if (strpos($n, 'cerebras') !== false) return 'https://cloud.cerebras.ai/';
    if (strpos($n, 'nvidia') !== false) return 'https://build.nvidia.com/';
    if (strpos($n, 'fireworks') !== false) return 'https://fireworks.ai/api-keys';
    if (strpos($n, 'hyperbolic') !== false) return 'https://app.hyperbolic.xyz/settings';
    if (strpos($n, 'nebius') !== false) return 'https://studio.nebius.ai/';
    if (strpos($n, 'alibaba') !== false || strpos($n, 'qwen') !== false) return 'https://dashscope.console.aliyun.com/apiKey';
    if (strpos($n, 'perplexity') !== false) return 'https://www.perplexity.ai/settings/api';
    if (strpos($n, 'ollama') !== false) return 'https://ollama.com/';
    if (strpos($n, 'lm studio') !== false) return 'https://lmstudio.ai/';
    if (strpos($n, 'glhf') !== false) return 'https://glhf.chat/';
    if (strpos($n, 'chatanywhere') !== false) return 'https://github.com/chatanywhere/GPT_API_free';
    return '#';
}

function getProviderIcon($name) {
    $n = strtolower($name);
    if (strpos($n, 'github') !== false) return '<i class="fab fa-github"></i>';
    if (strpos($n, 'google') !== false || strpos($n, 'gemini') !== false) return '<i class="fab fa-google"></i>';
    if (strpos($n, 'huggingface') !== false) return '<i class="far fa-smile-beam"></i>';
    if (strpos($n, 'mistral') !== false) return '<i class="fas fa-wind"></i>';
    if (strpos($n, 'groq') !== false) return '<i class="fas fa-bolt"></i>';
    if (strpos($n, 'openrouter') !== false) return '<i class="fas fa-route"></i>';
    if (strpos($n, 'deepseek') !== false) return '<i class="fas fa-search-location"></i>';
    if (strpos($n, 'nvidia') !== false) return '<i class="fas fa-desktop"></i>';
    if (strpos($n, 'cerebras') !== false) return '<i class="fas fa-brain"></i>';
    if (strpos($n, 'fireworks') !== false) return '<i class="fas fa-fire"></i>';
    if (strpos($n, 'hyperbolic') !== false) return '<i class="fas fa-chart-area"></i>';
    if (strpos($n, 'alibaba') !== false) return '<i class="fas fa-shopping-cart"></i>';
    if (strpos($n, 'nebius') !== false) return '<i class="fas fa-cloud"></i>';
    if (strpos($n, 'perplexity') !== false) return '<i class="fas fa-compass"></i>';
    if (strpos($n, 'ollama') !== false) return '<i class="fas fa-laptop-code"></i>';
    if (strpos($n, 'lm studio') !== false) return '<i class="fas fa-tv"></i>';
    if (strpos($n, 'glhf') !== false) return '<i class="fas fa-gamepad"></i>';
    if (strpos($n, 'chatanywhere') !== false) return '<i class="fas fa-globe-asia"></i>';
    return '<i class="fas fa-microchip"></i>';
}

$page_title = "Agent Setup - WQS Engine";
require_once $path_to_root . 'includes/dashboard_header.php';
?>

<style>
/* Premium Agent Setup Styling */
.agent-hero {
    background: linear-gradient(135deg, #0A2D5E 0%, #1e3a5f 100%);
    border-radius: 20px;
    padding: 3rem 2rem;
    color: white;
    position: relative;
    overflow: hidden;
    margin-bottom: 2rem;
    box-shadow: 0 10px 30px rgba(10, 45, 94, 0.15);
}
.agent-hero::before {
    content: '';
    position: absolute;
    top: -50%; right: -10%;
    width: 400px; height: 400px;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    border-radius: 50%;
}
.agent-hero::after {
    content: '\f544';
    font-family: 'Font Awesome 5 Free';
    font-weight: 900;
    position: absolute;
    bottom: -20%; right: 5%;
    font-size: 12rem;
    color: rgba(255,255,255,0.03);
    transform: rotate(-15deg);
}

.provider-card {
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
.provider-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(10,45,94,0.1);
}
.provider-card.active-card {
    border: 2px solid #0A2D5E;
    box-shadow: 0 8px 25px rgba(10,45,94,0.15);
}
.provider-card.active-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 6px;
    background: linear-gradient(90deg, #0A2D5E, #3b82f6);
}

.icon-box {
    width: 56px; height: 56px;
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.8rem;
    background: #f1f5f9;
    color: #0A2D5E;
    box-shadow: inset 0 2px 4px rgba(0,0,0,0.03);
}
.active-card .icon-box {
    background: #0A2D5E;
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(10,45,94,0.3);
}

.code-snippet {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 6px 12px;
    font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
    font-size: 0.8rem;
    color: #334155;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: block;
}

.btn-premium {
    background: #ffffff;
    color: #0A2D5E;
    font-weight: 600;
    border: none;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transition: all 0.2s ease;
}
.btn-premium:hover {
    background: #f8fafc;
    color: #0A2D5E;
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
.action-btn.btn-edit:hover { background: #dbeafe; color: #1e40af; }
.action-btn.btn-del:hover { background: #fee2e2; color: #991b1b; }
</style>

<div class="container-fluid px-3 px-md-4 pb-5">
    
    <!-- Premium Hero Section -->
    <div class="agent-hero">
        <div class="row align-items-center position-relative" style="z-index: 1;">
            <div class="col-lg-8">
                <div class="d-inline-flex align-items-center px-3 py-1 rounded-pill mb-3" style="background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); font-size: 0.85rem; font-weight: 600;">
                    <i class="fas fa-sparkles text-warning me-2"></i> Engine Configuration
                </div>
                <h2 class="fw-bold mb-2">WiseBot AI Providers</h2>
                <p class="mb-0" style="opacity: 0.9; font-size: 1.05rem; max-width: 600px;">
                    Seamlessly switch between multiple state-of-the-art AI models. Add your own API keys for free-tier providers to keep the platform running efficiently.
                </p>
            </div>
            <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                <button class="btn btn-premium rounded-pill px-4 py-2" data-bs-toggle="modal" data-bs-target="#addProviderModal">
                    <i class="fas fa-plus me-2"></i> Add Custom API
                </button>
            </div>
        </div>
    </div>

    <?php if ($success_msg): ?>
    <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm border-0 d-flex align-items-center" role="alert">
        <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width:28px;height:28px;"><i class="fas fa-check"></i></div>
        <div class="fw-medium"><?= htmlspecialchars($success_msg) ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm border-0 d-flex align-items-center" role="alert">
        <div class="bg-danger text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width:28px;height:28px;"><i class="fas fa-exclamation"></i></div>
        <div class="fw-medium"><?= htmlspecialchars($error_msg) ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <div class="row g-4 mt-1">
        <?php foreach ($providers as $p): 
            $isActive = (bool)$p['is_active'];
            $keyUrl = getProviderKeyUrl($p['provider_name']);
        ?>
        <div class="col-md-6 col-xl-4">
            <div class="provider-card <?= $isActive ? 'active-card' : '' ?>">
                <div class="card-body p-4 d-flex flex-column">
                    
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="icon-box">
                                <?= getProviderIcon($p['provider_name']) ?>
                            </div>
                            <div>
                                <h5 class="fw-bold text-body-emphasis mb-1" style="font-size: 1.1rem; letter-spacing: -0.3px;"><?= htmlspecialchars($p['provider_name']) ?></h5>
                                <?php if ($p['status'] === 'healthy'): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2 py-1" style="font-size: 0.7rem;"><i class="fas fa-check-circle me-1"></i>Healthy</span>
                                <?php elseif ($p['status'] === 'failing'): ?>
                                    <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-2 py-1" style="font-size: 0.7rem;"><i class="fas fa-times-circle me-1"></i>Failing</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-2 py-1" style="font-size: 0.7rem;"><i class="fas fa-clock me-1"></i>Untested</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($isActive): ?>
                            <div class="text-primary fw-bold small bg-primary bg-opacity-10 px-3 py-1 rounded-pill">
                                <i class="fas fa-circle text-primary me-1" style="font-size: 0.5rem; vertical-align: middle;"></i> ACTIVE
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Details -->
                    <div class="flex-grow-1">
                        <div class="mb-3">
                            <label class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.5px; font-size: 0.65rem;">API Endpoint</label>
                            <span class="code-snippet mt-1" title="<?= htmlspecialchars($p['api_endpoint']) ?>"><?= htmlspecialchars($p['api_endpoint']) ?></span>
                        </div>
                        <div class="mb-3">
                            <label class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.5px; font-size: 0.65rem;">Default Model</label>
                            <div class="fw-medium text-body d-flex align-items-center gap-2">
                                <i class="fas fa-brain text-secondary opacity-50"></i> <?= htmlspecialchars($p['default_model']) ?>
                            </div>
                        </div>
                        <div>
                            <label class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.5px; font-size: 0.65rem;">Authentication Key</label>
                            <div class="d-flex align-items-center justify-content-between mt-1">
                                <div class="fw-medium" style="font-family: monospace;">
                                    <?php if (!empty($p['api_key'])): ?>
                                        <span class="text-success"><i class="fas fa-lock me-1"></i>••••••••<?= substr($p['api_key'], -4) ?></span>
                                    <?php else: ?>
                                        <span class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>Key Required</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($keyUrl !== '#'): ?>
                                    <a href="<?= $keyUrl ?>" target="_blank" class="text-primary small fw-semibold text-decoration-none">Get Key <i class="fas fa-external-link-alt ms-1" style="font-size:0.7rem;"></i></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Actions -->
                    <div class="mt-4 pt-3 border-top d-flex align-items-center gap-2">
                        <?php if (!$isActive): ?>
                            <form method="POST" class="flex-grow-1 m-0">
                                <input type="hidden" name="action" value="set_active">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <button class="btn btn-outline-primary w-100 rounded-pill fw-semibold" <?= empty($p['api_key']) ? 'disabled' : '' ?>>
                                    <i class="fas fa-plug me-1"></i> Connect API
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="flex-grow-1">
                                <button class="btn btn-primary w-100 rounded-pill fw-semibold" disabled style="opacity: 1; background: #0A2D5E; border-color: #0A2D5E;">
                                    <i class="fas fa-satellite-dish me-1"></i> Engine Running
                                </button>
                            </div>
                        <?php endif; ?>
                        
                        <button type="button" class="action-btn btn-test" title="Test Connection" onclick="testHealth(<?= $p['id'] ?>, this)">
                            <i class="fas fa-heartbeat"></i>
                        </button>
                        
                        <button type="button" class="action-btn btn-edit" title="Edit Configuration" data-bs-toggle="modal" data-bs-target="#editProviderModal" 
                                data-id="<?= $p['id'] ?>"
                                data-name="<?= htmlspecialchars($p['provider_name']) ?>"
                                data-endpoint="<?= htmlspecialchars($p['api_endpoint']) ?>"
                                data-model="<?= htmlspecialchars($p['default_model']) ?>"
                                data-key="<?= !empty($p['api_key']) ? '********' : '' ?>">
                            <i class="fas fa-pen"></i>
                        </button>

                        <?php if (!$isActive): ?>
                        <form method="POST" class="m-0" onsubmit="return confirm('Delete this API configuration completely?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <button type="submit" class="action-btn btn-del" title="Delete API"><i class="fas fa-trash-alt"></i></button>
                        </form>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Add Provider Modal -->
<div class="modal fade" id="addProviderModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow-lg rounded-4 overflow-hidden" method="POST">
            <div class="bg-primary text-white p-4 pb-3" style="background: linear-gradient(135deg, #0A2D5E 0%, #1e3a5f 100%);">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="modal-title fw-bold"><i class="fas fa-robot me-2 text-warning"></i> Add Custom API</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <p class="small mb-0 mt-2 opacity-75">Integrate any OpenAI-compatible endpoint into the WiseBot engine.</p>
            </div>
            <input type="hidden" name="action" value="add">
            <div class="modal-body p-4">
                <div class="mb-4">
                    <label class="form-label fw-bold text-dark small">Provider Name</label>
                    <input type="text" name="provider_name" class="form-control form-control-lg rounded-3 bg-light" placeholder="e.g. Claude Wrapper, AnyScale" required style="font-size: 0.95rem;">
                </div>
                <div class="mb-4">
                    <label class="form-label fw-bold text-dark small">API Endpoint URL</label>
                    <input type="url" name="api_endpoint" class="form-control form-control-lg rounded-3 bg-light text-monospace" placeholder="https://api.example.com/v1/chat/completions" required style="font-size: 0.85rem;">
                    <div class="form-text small mt-1"><i class="fas fa-info-circle me-1"></i>Must be an OpenAI-compatible /chat/completions endpoint.</div>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-bold text-dark small">Model Identifier</label>
                    <input type="text" name="default_model" class="form-control form-control-lg rounded-3 bg-light" placeholder="e.g. gpt-4, llama-3" required style="font-size: 0.95rem;">
                </div>
                <div class="mb-2">
                    <label class="form-label fw-bold text-dark small">API Secret Key</label>
                    <input type="password" name="api_key" class="form-control form-control-lg rounded-3 bg-light" placeholder="sk-..." required style="font-size: 0.95rem;">
                </div>
            </div>
            <div class="modal-footer border-top-0 p-4 pt-0 bg-white">
                <button type="button" class="btn btn-light rounded-pill px-4 fw-semibold" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold" style="background: #0A2D5E; border: none;">Save Integration</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Provider Modal -->
<div class="modal fade" id="editProviderModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 shadow-lg rounded-4 overflow-hidden" method="POST">
            <div class="bg-light p-4 pb-3 border-bottom">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="modal-title fw-bold text-body-emphasis"><i class="fas fa-pen me-2 text-primary"></i> Edit API Config</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-body p-4">
                <div class="mb-4">
                    <label class="form-label fw-bold text-dark small">Provider Name</label>
                    <input type="text" name="provider_name" id="edit_name" class="form-control form-control-lg rounded-3" required style="font-size: 0.95rem;">
                </div>
                <div class="mb-4">
                    <label class="form-label fw-bold text-dark small">API Endpoint URL</label>
                    <input type="url" name="api_endpoint" id="edit_endpoint" class="form-control form-control-lg rounded-3 text-monospace" required style="font-size: 0.85rem;">
                </div>
                <div class="mb-4">
                    <label class="form-label fw-bold text-dark small">Model Identifier</label>
                    <input type="text" name="default_model" id="edit_model" class="form-control form-control-lg rounded-3" required style="font-size: 0.95rem;">
                </div>
                <div class="mb-2">
                    <label class="form-label fw-bold text-dark small">API Secret Key</label>
                    <input type="password" name="api_key" id="edit_key" class="form-control form-control-lg rounded-3" placeholder="Leave unchanged (********) to keep current key" style="font-size: 0.95rem;">
                </div>
            </div>
            <div class="modal-footer border-top-0 p-4 pt-0">
                <button type="button" class="btn btn-light rounded-pill px-4 fw-semibold border" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold">Update Config</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const editModal = document.getElementById('editProviderModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            document.getElementById('edit_id').value = button.getAttribute('data-id');
            document.getElementById('edit_name').value = button.getAttribute('data-name');
            document.getElementById('edit_endpoint').value = button.getAttribute('data-endpoint');
            document.getElementById('edit_model').value = button.getAttribute('data-model');
            document.getElementById('edit_key').value = button.getAttribute('data-key');
        });
    }
});

function testHealth(id, btn) {
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    btn.disabled = true;

    fetch('<?= $path_to_root ?>api/ai_health_check.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + id
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Connection Successful!',
                text: 'The API key is valid and the endpoint is responding correctly.',
                confirmButtonColor: '#0A2D5E',
                customClass: { popup: 'rounded-4' }
            }).then(() => location.reload());
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Connection Failed',
                text: data.error,
                confirmButtonColor: '#d33',
                customClass: { popup: 'rounded-4' }
            }).then(() => location.reload());
        }
    })
    .catch(err => {
        Swal.fire('Error', 'Network error occurred during the health check.', 'error');
        btn.innerHTML = originalHtml;
        btn.disabled = false;
    });
}
</script>

<?php require_once $path_to_root . 'includes/dashboard_footer.php'; ?>
