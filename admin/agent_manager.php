<?php
$path_to_root = "../";
$page_title = "Agent Manager";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    ob_clean();
    header('Content-Type: application/json');
    $action = $_POST['ajax_action'];

    if ($action === 'toggle_agent') {
        $id = (int)$_POST['agent_id'];
        $active = (int)$_POST['is_active'];
        try {
            $pdo->prepare("UPDATE bot_agent_configs SET is_active = ? WHERE id = ?")->execute([$active, $id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } elseif ($action === 'update_priority') {
        $id = (int)$_POST['agent_id'];
        $priority = (int)$_POST['priority'];
        try {
            $pdo->prepare("UPDATE bot_agent_configs SET priority = ? WHERE id = ?")->execute([$priority, $id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } elseif ($action === 'add_kb') {
        $category = trim($_POST['category'] ?? '');
        $question = trim($_POST['question'] ?? '');
        $answer = trim($_POST['answer'] ?? '');
        $keywords = trim($_POST['keywords'] ?? '');
        if ($category && $question && $answer) {
            try {
                $kwArr = array_filter(array_map('trim', explode(',', $keywords)));
                $pdo->prepare("INSERT INTO bot_knowledge_base (category, question, answer, keywords) VALUES (?, ?, ?, ?)")
                    ->execute([$category, $question, $answer, json_encode($kwArr)]);
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'All fields required']);
        }
    } elseif ($action === 'delete_kb') {
        $id = (int)$_POST['kb_id'];
        try {
            $pdo->prepare("DELETE FROM bot_knowledge_base WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } elseif ($action === 'get_agents') {
        try {
            $agents = $pdo->query("SELECT * FROM bot_agent_configs ORDER BY priority DESC, agent_name ASC")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($agents);
        } catch (Exception $e) {
            echo json_encode([]);
        }
    } elseif ($action === 'get_kb') {
        try {
            $kb = $pdo->query("SELECT * FROM bot_knowledge_base ORDER BY category, question ASC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($kb);
        } catch (Exception $e) {
            echo json_encode([]);
        }
    } elseif ($action === 'get_stats') {
        try {
            $stats = [
                'leads' => (int)$pdo->query("SELECT COUNT(*) FROM bot_lead_qualification")->fetchColumn(),
                'qualified' => (int)$pdo->query("SELECT COUNT(*) FROM bot_lead_qualification WHERE qualification_status = 'qualified'")->fetchColumn(),
                'proposals' => (int)$pdo->query("SELECT COUNT(*) FROM bot_proposals")->fetchColumn(),
                'invoices' => (int)$pdo->query("SELECT COUNT(*) FROM bot_invoices_chat")->fetchColumn(),
                'bugs' => (int)$pdo->query("SELECT COUNT(*) FROM bot_bug_reports")->fetchColumn(),
                'feedback' => (int)$pdo->query("SELECT COUNT(*) FROM bot_feedback_surveys")->fetchColumn(),
                'avg_nps' => (float)$pdo->query("SELECT COALESCE(AVG(nps_score), 0) FROM bot_feedback_surveys WHERE nps_score IS NOT NULL")->fetchColumn(),
                'kyc_pending' => (int)$pdo->query("SELECT COUNT(*) FROM bot_kyc_documents WHERE verification_status = 'pending'")->fetchColumn(),
                'contracts' => (int)$pdo->query("SELECT COUNT(*) FROM bot_contracts")->fetchColumn(),
                'onboarding' => (int)$pdo->query("SELECT COUNT(*) FROM bot_onboarding WHERE onboarding_complete = 0")->fetchColumn(),
                'milestones' => (int)$pdo->query("SELECT COUNT(*) FROM bot_project_milestones WHERE status = 'delayed'")->fetchColumn(),
                'kb_count' => (int)$pdo->query("SELECT COUNT(*) FROM bot_knowledge_base")->fetchColumn(),
            ];
            echo json_encode($stats);
        } catch (Exception $e) {
            echo json_encode([]);
        }
    }
    exit;
}
?>

<style>
.ag-wrap{font-family:'Inter',system-ui,-apple-system,sans-serif}
.ag-hero{background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 50%,#0f4c75 100%);border-radius:24px;padding:2rem 2.5rem;color:#fff;position:relative;overflow:hidden;margin-bottom:2rem}
.ag-hero::before{content:'';position:absolute;top:-80px;right:-60px;width:300px;height:300px;background:radial-gradient(circle,rgba(16,185,129,0.2) 0%,transparent 70%);border-radius:50%}
.ag-hero h1{font-size:1.75rem;font-weight:800;margin:0;background:linear-gradient(135deg,#fff,#6ee7b7);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.ag-hero p{color:rgba(255,255,255,0.5);font-size:0.85rem;margin:0}
.ag-card{background:#fff;border:1px solid rgba(0,0,0,0.04);border-radius:16px;overflow:hidden}
.ag-stat{background:#fff;border:1px solid rgba(0,0,0,0.04);border-radius:14px;padding:1rem;transition:all 0.3s}
.ag-stat:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,0,0,0.06)}
.ag-stat .num{font-size:1.5rem;font-weight:900;line-height:1}
.ag-stat .label{font-size:0.68rem;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;margin-top:0.15rem}
.ag-agent-card{background:#fff;border:1.5px solid rgba(0,0,0,0.04);border-radius:14px;padding:1.25rem;transition:all 0.3s;position:relative;overflow:hidden}
.ag-agent-card:hover{border-color:#3b82f6;box-shadow:0 4px 16px rgba(59,130,246,0.08)}
.ag-agent-card.inactive{opacity:0.5}
.ag-agent-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:#fff;margin-bottom:0.75rem}
.ag-toggle{position:relative;width:44px;height:24px;background:#e2e8f0;border-radius:12px;cursor:pointer;transition:all 0.2s}
.ag-toggle.active{background:#10b981}
.ag-toggle::after{content:'';position:absolute;top:2px;left:2px;width:20px;height:20px;background:#fff;border-radius:50%;transition:transform 0.2s;box-shadow:0 1px 3px rgba(0,0,0,0.15)}
.ag-toggle.active::after{transform:translateX(20px)}
.ag-tabs{display:flex;border-bottom:1px solid rgba(0,0,0,0.04);padding:0 1.5rem;gap:0}
.ag-tab{padding:0.75rem 1rem;font-size:0.8rem;font-weight:600;color:#94a3b8;cursor:pointer;border-bottom:2px solid transparent;transition:all 0.2s}
.ag-tab:hover{color:#334155}
.ag-tab.active{color:#0f172a;border-bottom-color:#10b981}
.ag-tab-content{display:none;padding:1.5rem}
.ag-tab-content.active{display:block}
.ag-kb-item{background:#f8fafc;border:1px solid rgba(0,0,0,0.04);border-radius:10px;padding:0.85rem 1rem;margin-bottom:0.5rem;transition:all 0.2s}
.ag-kb-item:hover{border-color:#e2e8f0}
.ag-kb-cat{display:inline-flex;padding:0.15rem 0.5rem;border-radius:50px;font-size:0.65rem;font-weight:700;background:#eff6ff;color:#1d4ed8;margin-right:6px}
</style>

<div class="ag-wrap">
<div class="ag-hero">
    <div style="position:relative;z-index:1">
        <div style="display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,0.1);backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,0.15);padding:0.35rem 0.9rem;border-radius:50px;font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:rgba(255,255,255,0.8);margin-bottom:0.75rem"><i class="fas fa-robot"></i> Agent Control</div>
        <h1>Specialized Agent Manager</h1>
        <p>Configure 12 AI agents: Lead Qualification, Proposals, Upsell, Onboarding, Milestones, Invoices, Bug Reports, Knowledge Base, Feedback, Referrals, KYC, and Contracts</p>
    </div>
</div>

<div class="row g-3 mb-4" id="statsRow">
    <div class="col-6 col-md-3"><div class="ag-stat"><div class="num" style="color:#3b82f6;" id="statLeads">—</div><div class="label" style="color:#3b82f6;">Leads</div></div></div>
    <div class="col-6 col-md-3"><div class="ag-stat"><div class="num" style="color:#8b5cf6;" id="statProposals">—</div><div class="label" style="color:#8b5cf6;">Proposals</div></div></div>
    <div class="col-6 col-md-3"><div class="ag-stat"><div class="num" style="color:#10b981;" id="statInvoices">—</div><div class="label" style="color:#10b981;">Invoices</div></div></div>
    <div class="col-6 col-md-3"><div class="ag-stat"><div class="num" style="color:#ef4444;" id="statBugs">—</div><div class="label" style="color:#ef4444;">Bug Reports</div></div></div>
</div>

<div class="ag-card mb-4">
    <div class="ag-tabs">
        <div class="ag-tab active" onclick="switchTab(this,'tab-agents')"><i class="fas fa-robot me-1"></i> Agents</div>
        <div class="ag-tab" onclick="switchTab(this,'tab-kb')"><i class="fas fa-book me-1"></i> Knowledge Base</div>
        <div class="ag-tab" onclick="switchTab(this,'tab-analytics')"><i class="fas fa-chart-bar me-1"></i> Analytics</div>
    </div>

    <div class="ag-tab-content active" id="tab-agents">
        <div class="row g-3" id="agentsGrid"></div>
    </div>

    <div class="ag-tab-content" id="tab-kb">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold mb-0" style="font-size:0.88rem;">Knowledge Base Articles</h6>
            <button class="btn btn-sm btn-primary" style="background:#0f172a;border-color:#0f172a;border-radius:10px;padding:0.4rem 1rem;font-size:0.78rem;" onclick="showAddKB()"><i class="fas fa-plus me-1"></i>Add Article</button>
        </div>
        <div id="kbList"></div>
    </div>

    <div class="ag-tab-content" id="tab-analytics">
        <div class="row g-3" id="analyticsGrid"></div>
    </div>
</div>
</div>

<!-- Add KB Modal -->
<div class="modal fade" id="addKBModal" tabindex="-1"><div class="modal-dialog modal-md modal-dialog-centered"><div class="modal-content" style="border-radius:16px;border:none;">
    <div class="modal-header" style="border-bottom:1px solid rgba(0,0,0,0.04);"><h6 class="fw-bold"><i class="fas fa-plus-circle me-2 text-primary"></i>Add Knowledge Base Article</h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body p-3">
        <div class="mb-2"><label class="form-label fw-bold small">Category</label><select class="form-select form-select-sm" id="kbCategory"><option>General</option><option>Pricing</option><option>Support</option><option>Account</option><option>Technical</option><option>Billing</option><option>Partnership</option><option>Scholarship</option></select></div>
        <div class="mb-2"><label class="form-label fw-bold small">Question</label><input type="text" class="form-control form-control-sm" id="kbQuestion" placeholder="What do users ask?"></div>
        <div class="mb-2"><label class="form-label fw-bold small">Answer</label><textarea class="form-control form-control-sm" id="kbAnswer" rows="3" placeholder="The definitive answer..."></textarea></div>
        <div class="mb-2"><label class="form-label fw-bold small">Keywords (comma separated)</label><input type="text" class="form-control form-control-sm" id="kbKeywords" placeholder="price, cost, how much"></div>
    </div>
    <div class="modal-footer border-top-0"><button class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-sm btn-primary" style="background:#0f172a;border-color:#0f172a;" onclick="saveKB()">Save Article</button></div>
</div></div></div>

<script>
let addKBModal;
document.addEventListener('DOMContentLoaded', function() {
    addKBModal = new bootstrap.Modal(document.getElementById('addKBModal'));
    loadAgents();
    loadKB();
    loadStats();
});

function switchTab(el, tabId) {
    el.closest('.ag-card').querySelectorAll('.ag-tab').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    el.closest('.ag-card').querySelectorAll('.ag-tab-content').forEach(c => c.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
}

function loadStats() {
    fetch('bot_intelligence.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'ajax_action=get_stats'})
    .then(r=>r.json()).then(d=>{
        document.getElementById('statLeads').textContent = d.leads || 0;
        document.getElementById('statProposals').textContent = d.proposals || 0;
        document.getElementById('statInvoices').textContent = d.invoices || 0;
        document.getElementById('statBugs').textContent = d.bugs || 0;
    }).catch(()=>{});

    // Analytics tab
    fetch('bot_intelligence.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'ajax_action=get_stats'})
    .then(r=>r.json()).then(d=>{
        const grid = document.getElementById('analyticsGrid');
        const items = [
            {label:'Qualified Leads',num:d.qualified||0,color:'#10b981',icon:'fas fa-check-circle'},
            {label:'Pending KYC',num:d.kyc_pending||0,color:'#f59e0b',icon:'fas fa-id-card'},
            {label:'Contracts',num:d.contracts||0,color:'#8b5cf6',icon:'fas fa-file-contract'},
            {label:'Avg NPS Score',num:parseFloat(d.avg_nps||0).toFixed(1),color:'#3b82f6',icon:'fas fa-star'},
            {label:'Delayed Milestones',num:d.milestones||0,color:'#ef4444',icon:'fas fa-exclamation-triangle'},
            {label:'KB Articles',num:d.kb_count||0,color:'#06b6d4',icon:'fas fa-book'},
            {label:'Feedback Collected',num:d.feedback||0,color:'#ec4899',icon:'fas fa-comment'},
            {label:'Incomplete Onboarding',num:d.onboarding||0,color:'#f97316',icon:'fas fa-user-plus'},
        ];
        grid.innerHTML = items.map(i=>`<div class="col-6 col-md-3"><div class="ag-stat"><div class="num" style="color:${i.color}"><i class="${i.icon}" style="font-size:0.9rem;"></i> ${i.num}</div><div class="label" style="color:${i.color};">${i.label}</div></div></div>`).join('');
    }).catch(()=>{});
}

const agentIcons = {
    lead_qualification:{icon:'fas fa-bullseye',color:'#ef4444'},
    proposal_generator:{icon:'fas fa-file-invoice',color:'#3b82f6'},
    upsell_crosssell:{icon:'fas fa-arrow-up',color:'#10b981'},
    onboarding:{icon:'fas fa-handshake',color:'#8b5cf6'},
    milestone_tracker:{icon:'fas fa-tasks',color:'#f59e0b'},
    invoice:{icon:'fas fa-receipt',color:'#06b6d4'},
    technical_debug:{icon:'fas fa-bug',color:'#ef4444'},
    knowledge_base:{icon:'fas fa-book',color:'#3b82f6'},
    feedback:{icon:'fas fa-star',color:'#f59e0b'},
    referral:{icon:'fas fa-share-nodes',color:'#10b981'},
    kyc_verification:{icon:'fas fa-shield-halved',color:'#8b5cf6'},
    contract:{icon:'fas fa-file-signature',color:'#ec4899'},
};

function loadAgents() {
    fetch('bot_intelligence.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'ajax_action=get_agents'})
    .then(r=>r.json()).then(agents=>{
        const grid = document.getElementById('agentsGrid');
        grid.innerHTML = agents.map(a=>{
            const ai = agentIcons[a.agent_key]||{icon:'fas fa-robot',color:'#64748b'};
            const triggers = JSON.parse(a.triggers||'[]');
            return `<div class="col-md-4 col-lg-3"><div class="ag-agent-card ${a.is_active?'':'inactive'}">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div class="ag-agent-icon" style="background:${ai.color};"><i class="${ai.icon}"></i></div>
                    <div class="ag-toggle ${a.is_active?'active':''}" onclick="toggleAgent(${a.id},${a.is_active?0:1},this)" title="${a.is_active?'Deactivate':'Activate'}"></div>
                </div>
                <h6 class="fw-bold mb-1" style="font-size:0.85rem;">${a.agent_name}</h6>
                <p style="font-size:0.72rem;color:#64748b;margin:0 0 0.5rem;">${a.description||''}</p>
                <div style="font-size:0.68rem;color:#94a3b8;"><i class="fas fa-sort-numeric-up me-1"></i>Priority: ${a.priority}</div>
                <div style="margin-top:0.5rem;display:flex;flex-wrap:wrap;gap:3px;">
                    ${triggers.slice(0,3).map(t=>`<span style="font-size:0.6rem;background:#f1f5f9;color:#64748b;padding:1px 6px;border-radius:50px;">${t}</span>`).join('')}
                    ${triggers.length>3?`<span style="font-size:0.6rem;background:#f1f5f9;color:#64748b;padding:1px 6px;border-radius:50px;">+${triggers.length-3}</span>`:''}
                </div>
            </div></div>`;
        }).join('');
    }).catch(()=>{});
}

function toggleAgent(id, active, el) {
    fetch('bot_intelligence.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`ajax_action=toggle_agent&agent_id=${id}&is_active=${active}`})
    .then(r=>r.json()).then(d=>{
        if(d.success){el.classList.toggle('active');el.closest('.ag-agent-card').classList.toggle('inactive');}
    });
}

function loadKB() {
    fetch('bot_intelligence.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'ajax_action=get_kb'})
    .then(r=>r.json()).then(kb=>{
        const list = document.getElementById('kbList');
        if(!kb.length){list.innerHTML='<div class="text-center py-4 text-muted" style="font-size:0.85rem;">No knowledge base articles yet</div>';return;}
        list.innerHTML = kb.map(k=>`<div class="ag-kb-item">
            <div class="d-flex justify-content-between align-items-start">
                <div><span class="ag-kb-cat">${k.category}</span><span class="fw-bold" style="font-size:0.85rem;">${k.question}</span>
                <p style="font-size:0.78rem;color:#64748b;margin:4px 0 0;">${k.answer.substring(0,150)}${k.answer.length>150?'...':''}</p>
                <div style="font-size:0.68rem;color:#94a3b8;margin-top:4px;"><i class="fas fa-thumbs-up me-1"></i>${k.helpful_count} helpful · <i class="fas fa-thumbs-down me-1"></i>${k.not_helpful_count} not helpful</div></div>
                <button class="btn btn-sm btn-outline-danger" style="font-size:0.7rem;padding:0.2rem 0.5rem;border-radius:6px;" onclick="deleteKB(${k.id})"><i class="fas fa-trash"></i></button>
            </div>
        </div>`).join('');
    }).catch(()=>{});
}

function showAddKB() { addKBModal.show(); }

function saveKB() {
    const params = new URLSearchParams();
    params.append('ajax_action','add_kb');
    params.append('category', document.getElementById('kbCategory').value);
    params.append('question', document.getElementById('kbQuestion').value);
    params.append('answer', document.getElementById('kbAnswer').value);
    params.append('keywords', document.getElementById('kbKeywords').value);
    fetch('bot_intelligence.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:params.toString()})
    .then(r=>r.json()).then(d=>{
        if(d.success){addKBModal.hide();Swal.fire({icon:'success',title:'Added!',timer:1500,showConfirmButton:false});loadKB();}
        else{Swal.fire({icon:'error',title:'Error',text:d.message});}
    });
}

function deleteKB(id) {
    Swal.fire({title:'Delete this article?',icon:'warning',showCancelButton:true,confirmButtonText:'Delete',confirmButtonColor:'#dc2626'}).then(r=>{
        if(!r.isConfirmed)return;
        fetch('bot_intelligence.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`ajax_action=delete_kb&kb_id=${id}`})
        .then(r=>r.json()).then(d=>{if(d.success)loadKB();});
    });
}
</script>
<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>