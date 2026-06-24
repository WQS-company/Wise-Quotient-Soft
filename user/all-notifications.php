<?php
$path_to_root = "../";
$page_title = "All Notifications";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';
$userId = $_SESSION['user']['id'];
$apiPath = $_headerWebPath;
?>

<style>
.notif-page-card { background: var(--color-card-bg); border: 1.5px solid var(--color-border); border-radius: 16px; overflow: hidden; }
.notif-page-header { padding: 1.5rem 2rem; border-bottom: 1px solid var(--color-border); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; }
.notif-filter-btn {
    padding: 6px 16px; border-radius: 50px; border: 1.5px solid var(--color-border);
    font-size: 0.78rem; font-weight: 600; color: var(--color-text-light);
    background: var(--color-card-bg); cursor: pointer; transition: all 0.15s;
}
.notif-filter-btn:hover { border-color: var(--color-accent); color: var(--color-accent); }
.notif-filter-btn.active { background: var(--color-accent); color: white; border-color: var(--color-accent); }
.notif-page-item {
    display: flex; gap: 1rem; padding: 1.1rem 2rem; border-bottom: 1px solid var(--color-border);
    text-decoration: none; color: var(--color-text) !important; transition: all 0.15s;
    cursor: pointer; position: relative;
}
.notif-page-item:hover { background: var(--color-bg); }
.notif-page-item:active { transform: scale(0.995); }
.notif-page-item.unread { background: linear-gradient(90deg, rgba(99,102,241,0.04) 0%, transparent 100%); border-left: 3px solid #6366f1; }
.notif-page-item.unread .notif-page-title { font-weight: 800; }
.notif-page-icon {
    width: 44px; height: 44px; border-radius: 12px; display: flex;
    align-items: center; justify-content: center; flex-shrink: 0; font-size: 1rem;
}
.notif-page-content { flex: 1; min-width: 0; }
.notif-page-title { font-size: 0.88rem; font-weight: 700; color: var(--color-text-body); margin-bottom: 2px; }
.notif-page-msg { font-size: 0.82rem; color: var(--color-text-light); line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.notif-page-meta { font-size: 0.72rem; color: var(--color-text-light); display: flex; align-items: center; gap: 1rem; margin-top: 4px; }
.notif-page-dot { width: 8px; height: 8px; border-radius: 50%; background: #6366f1; position: absolute; top: 1.3rem; right: 2rem; }
.notif-page-empty { text-align: center; padding: 4rem 2rem; color: var(--color-text-light); }
.notif-page-empty i { font-size: 3rem; color: var(--color-border); margin-bottom: 1rem; display: block; }
.notif-page-pagination { padding: 1rem 2rem; display: flex; align-items: center; justify-content: space-between; border-top: 1px solid var(--color-border); }
.notif-page-pagination button {
    padding: 6px 16px; border-radius: 8px; border: 1px solid var(--color-border);
    font-size: 0.8rem; font-weight: 600; cursor: pointer; transition: all 0.15s;
    background: var(--color-card-bg); color: var(--color-text-body);
}
.notif-page-pagination button:hover:not(:disabled) { border-color: var(--color-accent); color: var(--color-accent); }
.notif-page-pagination button:disabled { opacity: 0.4; cursor: not-allowed; }
</style>

<div class="container-fluid px-4 py-4" style="max-width: 900px;">

    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bold text-body mb-1" style="font-size:1.3rem;"><i class="fas fa-bell me-2" style="color:#6366f1;"></i>Notifications</h4>
            <p class="text-muted small mb-0">Stay updated with your latest activities.</p>
        </div>
        <button class="btn btn-sm btn-outline-primary rounded-pill fw-semibold" id="markAllReadPage">
            <i class="fas fa-check-double me-1"></i> Mark all read
        </button>
    </div>

    <!-- Search & Filters -->
    <div class="notif-page-card mb-4">
        <div style="padding:1rem 1.5rem;border-bottom:1px solid var(--color-border);">
            <div class="input-group" style="max-width:400px;">
                <span class="input-group-text bg-transparent"><i class="fas fa-search text-muted"></i></span>
                <input type="text" id="notifSearch" class="form-control" placeholder="Search notifications..." style="border-radius:0 8px 8px 0;font-size:0.88rem;">
            </div>
        </div>
        <div style="padding:0.75rem 1.5rem;display:flex;gap:0.5rem;flex-wrap:wrap;" id="notifFilters">
            <button class="notif-filter-btn active" data-filter="all">All</button>
            <button class="notif-filter-btn" data-filter="unread">Unread</button>
            <button class="notif-filter-btn" data-filter="project">Projects</button>
            <button class="notif-filter-btn" data-filter="invoice">Invoices</button>
            <button class="notif-filter-btn" data-filter="payment">Payments</button>
            <button class="notif-filter-btn" data-filter="partner">Partners</button>
            <button class="notif-filter-btn" data-filter="meeting">Meetings</button>
            <button class="notif-filter-btn" data-filter="message">Messages</button>
            <button class="notif-filter-btn" data-filter="scholarship">Scholarships</button>
            <button class="notif-filter-btn" data-filter="support">Support</button>
        </div>
    </div>

    <!-- Notification List -->
    <div class="notif-page-card">
        <div id="notifPageList">
            <div class="text-center py-5"><div class="spinner-border text-primary"></div></div>
        </div>
        <div class="notif-page-pagination" id="notifPagePagination" style="display:none;">
            <button id="prevPage" disabled><i class="fas fa-chevron-left me-1"></i> Previous</button>
            <span id="pageInfo" class="text-muted small"></span>
            <button id="nextPage" disabled>Next <i class="fas fa-chevron-right ms-1"></i></button>
        </div>
    </div>
</div>

<script>
(function() {
    const listEl = document.getElementById('notifPageList');
    const paginationEl = document.getElementById('notifPagePagination');
    const prevBtn = document.getElementById('prevPage');
    const nextBtn = document.getElementById('nextPage');
    const pageInfo = document.getElementById('pageInfo');
    const searchInput = document.getElementById('notifSearch');
    const filtersContainer = document.getElementById('notifFilters');

    let currentPage = 1;
    let currentFilter = 'all';
    let currentSearch = '';
    let totalPages = 1;
    let searchTimeout = null;

    const typeIcons = {
        project: { icon: 'fa-folder-open', color: '#3b82f6', bg: '#eff6ff' },
        invoice: { icon: 'fa-file-invoice-dollar', color: '#f59e0b', bg: '#fffbeb' },
        payment: { icon: 'fa-naira-sign', color: '#16a34a', bg: '#f0fdf4' },
        partner: { icon: 'fa-handshake', color: '#8b5cf6', bg: '#f5f3ff' },
        meeting: { icon: 'fa-calendar-check', color: '#06b6d4', bg: '#ecfeff' },
        message: { icon: 'fa-comment-dots', color: '#ec4899', bg: '#fdf2f8' },
        announcement: { icon: 'fa-bullhorn', color: '#f97316', bg: '#fff7ed' },
        scholarship: { icon: 'fa-graduation-cap', color: '#6366f1', bg: '#eef2ff' },
        support: { icon: 'fa-headset', color: '#14b8a6', bg: '#f0fdfa' },
        welcome: { icon: 'fa-door-open', color: '#0ea5e9', bg: '#f0f9ff' },
        success: { icon: 'fa-circle-check', color: '#16a34a', bg: '#f0fdf4' },
        danger: { icon: 'fa-circle-xmark', color: '#ef4444', bg: '#fef2f2' },
        warning: { icon: 'fa-triangle-exclamation', color: '#f59e0b', bg: '#fffbeb' },
        info: { icon: 'fa-bell', color: '#6b7280', bg: '#f9fafb' },
    };

    function loadPage() {
        listEl.innerHTML = '<div class="text-center py-5"><div class="spinner-border spinner-border-sm text-primary"></div></div>';
        paginationEl.style.display = 'none';

        let url = `<?= $apiPath ?>notifications_api.php?action=all&page=${currentPage}&filter=${currentFilter}&search=${encodeURIComponent(currentSearch)}&per_page=15`;

        fetch(url)
            .then(r => r.json())
            .then(data => {
                if (!data.success) { listEl.innerHTML = '<div class="notif-page-empty"><i class="fas fa-exclamation-circle"></i><p>Failed to load.</p></div>'; return; }
                renderList(data.notifications);
                updatePagination(data.pagination);
            })
            .catch(() => { listEl.innerHTML = '<div class="notif-page-empty"><i class="fas fa-wifi"></i><p>Network error.</p></div>'; });
    }

    function renderList(items) {
        if (!items.length) {
            listEl.innerHTML = '<div class="notif-page-empty"><i class="far fa-bell"></i><h6 class="fw-bold">No notifications found</h6><p class="small text-muted mb-0">Try adjusting your filters or check back later.</p></div>';
            return;
        }
        listEl.innerHTML = items.map(n => {
            const ic = n.icon || typeIcons[n.type] || typeIcons.info;
            let url = n.target_url || '#';
            if (url !== '#' && n.target_id) url += (url.includes('?') ? '&' : '?') + 'id=' + n.target_id;
            return `
            <div class="notif-page-item ${n.is_read === 0 ? 'unread' : ''}" onclick="handlePageNotifClick(${n.id}, '${url.replace(/'/g, "\\'")}', ${n.is_read})" data-id="${n.id}">
                <div class="notif-page-icon" style="background:${ic.bg};color:${ic.color};"><i class="fas ${ic.icon}"></i></div>
                <div class="notif-page-content">
                    <div class="notif-page-title">${escHtml(n.title)}</div>
                    <div class="notif-page-msg">${escHtml(n.message)}</div>
                    <div class="notif-page-meta">
                        <span><i class="far fa-clock me-1"></i>${n.time_ago}</span>
                        <span class="text-capitalize" style="font-weight:600;color:${ic.color};">${escHtml(n.type)}</span>
                    </div>
                </div>
                ${n.is_read === 0 ? '<div class="notif-page-dot"></div>' : ''}
            </div>`;
        }).join('');
    }

    function updatePagination(p) {
        totalPages = p.total_pages;
        if (p.total === 0) { paginationEl.style.display = 'none'; return; }
        paginationEl.style.display = 'flex';
        prevBtn.disabled = p.page <= 1;
        nextBtn.disabled = p.page >= p.total_pages;
        pageInfo.textContent = `Page ${p.page} of ${p.total_pages} (${p.total} total)`;
    }

    window.handlePageNotifClick = function(id, url, isRead) {
        if (url === '#') return;
        if (!isRead) {
            const fd = new FormData();
            fd.append('action', 'mark_single_read');
            fd.append('id', id);
            fetch('<?= $apiPath ?>notifications_api.php', { method: 'POST', body: fd })
                .then(() => {
                    const el = document.querySelector(`[data-id="${id}"]`);
                    if (el) { el.classList.remove('unread'); const dot = el.querySelector('.notif-page-dot'); if (dot) dot.remove(); }
                    if (typeof updateBadgeCount === 'function') updateBadgeCount();
                }).catch(() => {});
        }
        window.location.href = url;
    };

    window.markAllReadPageFn = function() {
        fetch('<?= $apiPath ?>notifications_api.php?action=mark_read', { method: 'POST' })
            .then(r => r.json())
            .then(d => { if (d.success) { document.querySelectorAll('.notif-page-item.unread').forEach(el => { el.classList.remove('unread'); const dot = el.querySelector('.notif-page-dot'); if (dot) dot.remove(); }); if (typeof updateBadgeCount === 'function') updateBadgeCount(); } });
    };

    // Filter clicks
    filtersContainer.addEventListener('click', function(e) {
        const btn = e.target.closest('.notif-filter-btn');
        if (!btn) return;
        filtersContainer.querySelectorAll('.notif-filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentFilter = btn.dataset.filter;
        currentPage = 1;
        loadPage();
    });

    // Search
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => { currentSearch = searchInput.value.trim(); currentPage = 1; loadPage(); }, 300);
    });

    // Pagination
    prevBtn.addEventListener('click', () => { if (currentPage > 1) { currentPage--; loadPage(); } });
    nextBtn.addEventListener('click', () => { if (currentPage < totalPages) { currentPage++; loadPage(); } });

    // Mark all read
    document.getElementById('markAllReadPage').addEventListener('click', markAllReadPageFn);

    function escHtml(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

    loadPage();
})();
</script>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
