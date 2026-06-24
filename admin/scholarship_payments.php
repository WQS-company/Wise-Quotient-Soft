<?php
$path_to_root = "../";
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user']['id'])) { header("Location: " . $path_to_root . "login.php"); exit; }
require_once $path_to_root . 'config.php';

$userIdCheck = $_SESSION['user']['id'];
$roleCheckStmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$roleCheckStmt->execute([$userIdCheck]);
$userRoleObj = $roleCheckStmt->fetch(PDO::FETCH_ASSOC);
if (!$userRoleObj || !in_array(strtolower($userRoleObj['role']), ['admin','developer'])) {
    header("Location: " . $path_to_root . "login.php"); exit;
}

$page_title = "Payment & Disbursement";
$current_page = "scholarship_payments.php";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';

$stats = ['total_disbursed'=>0,'total_pending'=>0,'total_processing'=>0,'total_failed'=>0,'total_count'=>0];
try {
    $stats['total_disbursed'] = (float)$pdo->query("SELECT COALESCE(SUM(award_amount),0) FROM scholarship_awards WHERE payment_status='disbursed'")->fetchColumn();
    $stats['total_pending'] = (float)$pdo->query("SELECT COALESCE(SUM(award_amount),0) FROM scholarship_awards WHERE payment_status='pending'")->fetchColumn();
    $stats['total_processing'] = (float)$pdo->query("SELECT COALESCE(SUM(award_amount),0) FROM scholarship_awards WHERE payment_status='processing'")->fetchColumn();
    $stats['total_failed'] = (float)$pdo->query("SELECT COALESCE(SUM(award_amount),0) FROM scholarship_awards WHERE payment_status='failed'")->fetchColumn();
    $stats['total_count'] = (int)$pdo->query("SELECT COUNT(*) FROM scholarship_awards")->fetchColumn();
} catch (Exception $e) {}
?>

<style>
body,.wrapper,.main-wrapper{overflow-x:hidden!important;max-width:100vw!important}
.scp-stat{background:var(--color-card-bg);border:1px solid var(--color-border);border-radius:14px;padding:1rem 1.25rem;transition:all .3s}
.scp-stat:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,0,0,.06)}
.scp-stat .scp-icon{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0}
.scp-stat .scp-val{font-size:1.5rem;font-weight:800;line-height:1}
.scp-stat .scp-lbl{font-size:.72rem;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-top:.2rem}
.scp-table-wrap{background:var(--color-card-bg);border:1px solid var(--color-border);border-radius:16px;overflow:hidden}
.scp-table{width:100%;border-collapse:collapse}
.scp-table th{padding:.6rem .7rem;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#64748b;border-bottom:1px solid var(--color-border);background:var(--color-bg);text-align:left;white-space:nowrap}
.scp-table td{padding:.6rem .7rem;font-size:.8rem;border-bottom:1px solid var(--color-border);vertical-align:middle}
.scp-table tr:last-child td{border-bottom:none}
.scp-table tr:hover td{background:var(--color-bg)}
.scp-badge{font-size:.63rem;padding:2px 8px;border-radius:50px;font-weight:600;display:inline-block;white-space:nowrap}
.scp-page-btn{padding:.35rem .8rem;border:1px solid var(--color-border);border-radius:8px;font-size:.78rem;font-weight:600;color:var(--color-text);text-decoration:none;transition:all .2s;cursor:pointer;background:var(--color-card-bg)}
.scp-page-btn:hover,.scp-page-btn.active{background:linear-gradient(135deg,#3b82f6,#1d4ed8);color:white;border-color:#3b82f6}
.detail-row{display:flex;gap:.5rem;padding:.5rem 0;border-bottom:1px solid var(--color-border)}
.detail-row:last-child{border-bottom:none}
.detail-label{font-size:.78rem;font-weight:600;color:#64748b;min-width:130px}
.detail-value{font-size:.82rem;color:var(--color-text)}
@media(max-width:991.98px){.scp-table-wrap{overflow-x:auto}}
</style>

<div class="container-fluid px-3 px-lg-4">

<div style="background:linear-gradient(135deg,#0f172a,#1e293b,#0f172a);border-radius:16px;padding:1.25rem 1.5rem;color:white;margin-bottom:1.5rem">
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h4 class="fw-bold mb-1"><i class="fas fa-money-bill-wave me-2"></i>Payment & Disbursement</h4>
            <p class="mb-0 opacity-75" style="font-size:.88rem">Track and manage scholarship fund disbursements</p>
        </div>
        <div class="mt-2 mt-md-0">
            <button class="btn btn-outline-light btn-sm rounded-pill" onclick="loadPayments()"><i class="fas fa-sync me-1"></i> Refresh</button>
        </div>
    </div>
    <div class="row g-3 mt-3">
        <?php
        $statCards = [
            ['Total Disbursed','₦' . number_format($stats['total_disbursed']),'fas fa-check-circle','#10b981','rgba(16,185,129,.12)'],
            ['Pending','₦' . number_format($stats['total_pending']),'fas fa-clock','#f59e0b','rgba(245,158,11,.12)'],
            ['Processing','₦' . number_format($stats['total_processing']),'fas fa-spinner','#3b82f6','rgba(59,130,246,.12)'],
            ['Failed','₦' . number_format($stats['total_failed']),'fas fa-times-circle','#ef4444','rgba(239,68,68,.12)'],
            ['Total Awards',number_format($stats['total_count']),'fas fa-award','#8b5cf6','rgba(139,92,246,.12)'],
        ];
        foreach ($statCards as $s): ?>
        <div class="col-6 col-md-4 col-xl">
            <div class="scp-stat">
                <div class="d-flex align-items-center gap-3">
                    <div class="scp-icon" style="background:<?= $s[4] ?>;color:<?= $s[3] ?>;"><i class="<?= $s[2] ?>"></i></div>
                    <div>
                        <div class="scp-val"><?= $s[1] ?></div>
                        <div class="scp-lbl"><?= $s[0] ?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show rounded-3" style="font-size:.88rem"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($_SESSION['success_message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<div style="background:var(--color-card-bg);border:1px solid var(--color-border);border-radius:16px;padding:1rem 1.25rem;margin-bottom:1.25rem">
    <div class="row g-3 align-items-center">
        <div class="col-12 col-md-4">
            <div class="position-relative">
                <i class="fas fa-search position-absolute text-muted" style="left:14px;top:50%;transform:translateY(-50%);font-size:.8rem"></i>
                <input type="text" id="searchInput" class="form-control" placeholder="Search student name..." style="padding-left:40px;border-radius:10px;border:1px solid var(--color-border);font-size:.85rem" oninput="loadPayments()">
            </div>
        </div>
        <div class="col-6 col-md-3">
            <select id="filterStatus" class="form-select" style="border-radius:10px;border:1px solid var(--color-border);font-size:.85rem" onchange="loadPayments()">
                <option value="">All Status</option>
                <option value="pending">Pending</option>
                <option value="processing">Processing</option>
                <option value="disbursed">Disbursed</option>
                <option value="failed">Failed</option>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <input type="date" id="filterDate" class="form-control" style="border-radius:10px;border:1px solid var(--color-border);font-size:.85rem" onchange="loadPayments()">
        </div>
        <div class="col-12 col-md-2 d-flex gap-2">
            <button class="btn btn-sm btn-primary rounded-pill flex-grow-1" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8);border:none;font-size:.85rem" onclick="loadPayments()"><i class="fas fa-filter me-1"></i> Filter</button>
        </div>
    </div>
</div>

<div class="scp-table-wrap">
    <div style="overflow-x:auto">
        <table class="scp-table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Scholarship</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="paymentsBody">
                <tr><td colspan="6" class="text-center py-5"><div class="spinner-border text-primary spinner-border-sm"></div></td></tr>
            </tbody>
        </table>
    </div>
</div>
<div style="display:flex;justify-content:center;gap:.5rem;flex-wrap:wrap;margin-top:1.5rem" id="pagination"></div>

</div>

<!-- Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-xl" style="border-radius:16px">
            <div class="modal-header" style="background:linear-gradient(135deg,#0f172a,#1e293b);color:white;border-top-left-radius:16px;border-top-right-radius:16px">
                <h6 class="modal-title fw-bold"><i class="fas fa-money-bill-wave me-2"></i>Payment Details</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="detailBody" style="max-height:70vh;overflow-y:auto"></div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-xl" style="border-radius:16px">
            <div class="modal-header" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8);color:white;border-top-left-radius:16px;border-top-right-radius:16px">
                <h6 class="modal-title fw-bold"><i class="fas fa-edit me-2"></i>Update Payment Status</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="statusAwardId">
                <div class="row g-3">
                    <div class="col-12">
                        <label style="font-size:.78rem;font-weight:600;color:#475569;margin-bottom:.3rem;display:block">Payment Status <span class="text-danger">*</span></label>
                        <select id="newPaymentStatus" class="form-select" style="border-radius:10px;border:1px solid var(--color-border)">
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="disbursed">Disbursed</option>
                            <option value="failed">Failed</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label style="font-size:.78rem;font-weight:600;color:#475569;margin-bottom:.3rem;display:block">Transaction Reference</label>
                        <input type="text" id="paymentRef" class="form-control" placeholder="e.g. TXN-2024-001" style="border-radius:10px;border:1px solid var(--color-border)">
                    </div>
                    <div class="col-12">
                        <label style="font-size:.78rem;font-weight:600;color:#475569;margin-bottom:.3rem;display:block">Notes</label>
                        <textarea id="paymentNotes" class="form-control" rows="2" placeholder="Payment notes..." style="border-radius:10px;border:1px solid var(--color-border)"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button class="btn btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary rounded-pill px-4 fw-bold" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8);border:none" onclick="savePaymentStatus()"><i class="fas fa-save me-1"></i> Update</button>
            </div>
        </div>
    </div>
</div>

<script>
const API = '../api/scholarship_api.php';
let currentPage = 0;
const limit = 20;

const statusColors = {
    pending: {bg:'#fef3c7',color:'#92400e'},
    processing: {bg:'#dbeafe',color:'#1e40af'},
    disbursed: {bg:'#d1fae5',color:'#065f46'},
    failed: {bg:'#fee2e2',color:'#991b1b'}
};

async function loadPayments(page = 0) {
    currentPage = page;
    const body = document.getElementById('paymentsBody');
    body.innerHTML = '<tr><td colspan="6" class="text-center py-4"><div class="spinner-border text-primary spinner-border-sm"></div></td></tr>';

    try {
        const params = { action: 'get_awards', limit, offset: page * limit };
        const search = document.getElementById('searchInput').value.trim();
        const status = document.getElementById('filterStatus').value;
        const filterDate = document.getElementById('filterDate').value;
        if (search) params.search = search;
        if (status) params.payment_status = status;
        if (filterDate) params.filter_date = filterDate;

        const resp = await fetch(API, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(params) });
        const result = await resp.json();

        if (!result.success) { body.innerHTML = `<tr><td colspan="6" class="text-center py-5 text-muted">${result.error || 'Error'}</td></tr>`; return; }

        const data = result.data || [];
        const total = result.total || 0;

        if (data.length === 0) {
            body.innerHTML = '<tr><td colspan="6" class="text-center py-5"><i class="fas fa-inbox fa-2x mb-3 d-block" style="color:#cbd5e1"></i><p class="text-muted mb-0">No payment records found</p></td></tr>';
            document.getElementById('pagination').innerHTML = '';
            return;
        }

        body.innerHTML = data.map(a => {
            const st = statusColors[a.payment_status] || {bg:'#f1f5f9',color:'#475569'};
            const date = a.created_at ? new Date(a.created_at).toLocaleDateString('en-NG',{day:'2-digit',month:'short',year:'numeric'}) : '—';
            const amount = a.currency === 'USD' ? '$' + number_format(a.award_amount) : '₦' + number_format(a.award_amount);

            return `<tr>
                <td>
                    <div style="min-width:0">
                        <div class="fw-semibold" style="font-size:.82rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:160px">${escapeHtml(a.full_name || '')}</div>
                        <div style="font-size:.68rem;color:#94a3b8">${escapeHtml(a.email || '')}</div>
                    </div>
                </td>
                <td style="font-size:.78rem;max-width:150px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="${escapeHtml(a.scholarship_title || '')}">${escapeHtml(a.scholarship_title || '—')}</td>
                <td style="font-size:.82rem;font-weight:700">${amount}</td>
                <td><span class="scp-badge" style="background:${st.bg};color:${st.color}">${capitalizeFirst(a.payment_status || 'pending')}</span></td>
                <td style="font-size:.75rem;color:#94a3b8">${date}</td>
                <td>
                    <div class="d-flex gap-1 flex-wrap">
                        <button class="btn btn-sm" style="font-size:.6rem;padding:2px 8px;background:#dbeafe;color:#1d4ed8;border:none;border-radius:6px" onclick='viewPayment(${JSON.stringify(a).replace(/'/g,"&#39;")})' title="View"><i class="fas fa-eye"></i></button>
                        <button class="btn btn-sm" style="font-size:.6rem;padding:2px 8px;background:#ede9fe;color:#7c3aed;border:none;border-radius:6px" onclick="openStatus(${a.id},'${a.payment_status}')" title="Update Status"><i class="fas fa-edit"></i></button>
                    </div>
                </td>
            </tr>`;
        }).join('');

        renderPagination(total, page);
    } catch (err) {
        body.innerHTML = `<tr><td colspan="6" class="text-center py-5 text-danger">${err.message}</td></tr>`;
    }
}

function renderPagination(total, page) {
    const pages = Math.ceil(total / limit);
    if (pages <= 1) { document.getElementById('pagination').innerHTML = ''; return; }
    let html = '';
    if (page > 0) html += `<button class="scp-page-btn" onclick="loadPayments(${page-1})"><i class="fas fa-chevron-left"></i></button>`;
    for (let i = 0; i < pages; i++) {
        if (i === page) html += `<button class="scp-page-btn active">${i+1}</button>`;
        else if (Math.abs(i - page) <= 2 || i === 0 || i === pages - 1) html += `<button class="scp-page-btn" onclick="loadPayments(${i})">${i+1}</button>`;
        else if (Math.abs(i - page) === 3) html += `<span class="scp-page-btn" style="border:none;cursor:default">...</span>`;
    }
    if (page < pages - 1) html += `<button class="scp-page-btn" onclick="loadPayments(${page+1})"><i class="fas fa-chevron-right"></i></button>`;
    document.getElementById('pagination').innerHTML = html;
}

function viewPayment(a) {
    const amount = a.currency === 'USD' ? '$' + number_format(a.award_amount) : '₦' + number_format(a.award_amount);
    const st = statusColors[a.payment_status] || {bg:'#f1f5f9',color:'#475569'};
    const rows = [
        ['Student', a.full_name],
        ['Email', a.email],
        ['Scholarship', a.scholarship_title],
        ['Award Amount', `<strong>${amount}</strong>`],
        ['Currency', a.currency || 'NGN'],
        ['Payment Status', `<span class="scp-badge" style="background:${st.bg};color:${st.color}">${capitalizeFirst(a.payment_status || 'pending')}</span>`],
        ['Transaction Ref', a.transaction_ref || '—'],
        ['Notes', a.payment_notes || '—'],
        ['Created', a.created_at ? new Date(a.created_at).toLocaleString() : '—'],
        ['Updated', a.updated_at ? new Date(a.updated_at).toLocaleString() : '—'],
    ];

    document.getElementById('detailBody').innerHTML = rows.map(([label, value]) =>
        `<div class="detail-row"><div class="detail-label">${label}</div><div class="detail-value">${value || '—'}</div></div>`
    ).join('');

    new bootstrap.Modal(document.getElementById('detailModal')).show();
}

function openStatus(awardId, currentStatus) {
    document.getElementById('statusAwardId').value = awardId;
    document.getElementById('newPaymentStatus').value = currentStatus || 'pending';
    document.getElementById('paymentRef').value = '';
    document.getElementById('paymentNotes').value = '';
    new bootstrap.Modal(document.getElementById('statusModal')).show();
}

async function savePaymentStatus() {
    const awardId = document.getElementById('statusAwardId').value;
    const params = {
        action: 'update_payment_status',
        id: parseInt(awardId),
        payment_status: document.getElementById('newPaymentStatus').value,
        transaction_ref: document.getElementById('paymentRef').value.trim(),
        notes: document.getElementById('paymentNotes').value.trim()
    };

    try {
        const resp = await fetch(API, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(params) });
        const result = await resp.json();
        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('statusModal')).hide();
            loadPayments(currentPage);
        } else alert(result.error || 'Failed to update status');
    } catch (err) { alert('Error: ' + err.message); }
}

function number_format(n) { return parseFloat(n || 0).toLocaleString('en-NG', {minimumFractionDigits: 2, maximumFractionDigits: 2}); }
function escapeHtml(t) { if (!t) return ''; const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
function capitalizeFirst(s) { if (!s) return ''; return s.charAt(0).toUpperCase() + s.slice(1).replace(/_/g, ' '); }

document.addEventListener('DOMContentLoaded', () => loadPayments());
</script>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
