<?php
$path_to_root = './';
session_start();
require_once $path_to_root . 'config.php';
$page_title = 'Scholarship Opportunities - WQS';
$seo = [
    'title'       => 'Scholarship Opportunities - Apply Now | Wise Quotient Soft',
    'description' => 'Apply for scholarship opportunities at Wise Quotient Soft. We support talented students and professionals in technology, software development, and innovation.',
    'keywords'    => 'WQS scholarship, tech scholarship Nigeria, software development scholarship, innovation scholarship, student funding, education grant',
    'canonical'   => 'https://wisequotientsoft.com/scholarships.php',
    'og_image'    => 'https://wisequotientsoft.com/images/og-scholarships.jpg',
    'breadcrumb'  => [
        ['name' => 'Home', 'url' => '/'],
        ['name' => 'Scholarships', 'url' => '/scholarships.php'],
    ],
];
include $path_to_root . 'includes/public_header.php';
?>
    <style>
        .hero-scholarships {
            background: linear-gradient(135deg, #1a237e 0%, #0d47a1 50%, #01579b 100%);
            color: #fff;
            padding: 80px 0 60px;
            margin-bottom: 40px;
        }
        .hero-scholarships h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 15px;
        }
        .hero-scholarships p {
            font-size: 1.15rem;
            opacity: 0.9;
            max-width: 700px;
        }
        .filter-bar {
            background: #fff;
            border-radius: 12px;
            padding: 20px 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 40px;
            position: relative;
            z-index: 10;
            margin-top: -30px;
        }
        .filter-bar select, .filter-bar input {
            border-radius: 8px;
            border: 1px solid #dee2e6;
            padding: 10px 14px;
            font-size: 0.9rem;
            width: 100%;
        }
        .filter-bar select:focus, .filter-bar input:focus {
            border-color: #0d47a1;
            box-shadow: 0 0 0 0.2rem rgba(13,71,161,0.15);
        }
        .scholarship-card {
            border: none;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 2px 15px rgba(0,0,0,0.07);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
            background: #fff;
        }
        .scholarship-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }
        .scholarship-card .card-img-top {
            height: 180px;
            object-fit: cover;
        }
        .scholarship-card .gradient-placeholder {
            height: 180px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: rgba(255,255,255,0.8);
        }
        .scholarship-card .card-body {
            padding: 20px;
        }
        .scholarship-card .card-title {
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 10px;
            color: #1a237e;
        }
        .scholarship-card .card-title a {
            color: inherit;
            text-decoration: none;
        }
        .scholarship-card .card-title a:hover {
            text-decoration: underline;
        }
        .badge-category {
            background: #e3f2fd;
            color: #0d47a1;
            font-weight: 600;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 0.75rem;
        }
        .badge-type {
            background: #e8f5e9;
            color: #2e7d32;
            font-weight: 600;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 0.75rem;
        }
        .badge-type.partial {
            background: #fff3e0;
            color: #e65100;
        }
        .badge-type.merit {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        .scholarship-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin: 12px 0;
            font-size: 0.85rem;
            color: #555;
        }
        .scholarship-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .deadline-countdown {
            background: #fff3e0;
            color: #e65100;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 600;
            display: inline-block;
        }
        .deadline-countdown.urgent {
            background: #ffebee;
            color: #c62828;
        }
        .deadline-countdown.expired {
            background: #eeeeee;
            color: #757575;
        }
        .section-title {
            font-weight: 700;
            color: #1a237e;
            margin-bottom: 25px;
            position: relative;
            padding-bottom: 10px;
        }
        .section-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background: #0d47a1;
            border-radius: 2px;
        }
        .featured-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: linear-gradient(135deg, #ff6f00, #ff8f00);
            color: #fff;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            z-index: 2;
        }
        .closing-soon-card {
            border-left: 4px solid #c62828;
        }
        .pagination .page-link {
            border-radius: 8px;
            margin: 0 3px;
            color: #0d47a1;
            border: 1px solid #dee2e6;
        }
        .pagination .page-item.active .page-link {
            background-color: #0d47a1;
            border-color: #0d47a1;
        }
        .no-results {
            text-align: center;
            padding: 60px 20px;
            color: #757575;
        }
        .no-results i {
            font-size: 4rem;
            margin-bottom: 15px;
            color: #bdbdbd;
        }
        .loading-spinner {
            text-align: center;
            padding: 40px;
        }
        .loading-spinner .spinner-border {
            width: 3rem;
            height: 3rem;
            color: #0d47a1;
        }
        @media (max-width: 768px) {
            .hero-scholarships h1 { font-size: 1.8rem; }
            .hero-scholarships { padding: 50px 0 40px; }
            .filter-bar { padding: 15px; }
            .scholarship-card .card-img-top,
            .scholarship-card .gradient-placeholder { height: 150px; }
        }
    </style>
</head>
<body>
    <!-- Hero Section -->
    <section class="hero-scholarships">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1><i class="fas fa-graduation-cap me-2"></i>Scholarship Opportunities</h1>
                    <p>Discover life-changing scholarship opportunities from top sponsors around the world. Fund your education and achieve your academic dreams.</p>
                </div>
                <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                    <a href="scholarship_track.php" class="btn btn-outline-light btn-lg">
                        <i class="fas fa-search-location me-2"></i>Track Application
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Filter Bar -->
    <div class="container">
        <div class="filter-bar">
            <div class="row g-3">
                <div class="col-lg-2 col-md-4 col-6">
                    <label class="form-label fw-semibold small">Category</label>
                    <select id="filterCategory" class="form-select">
                        <option value="">All Categories</option>
                        <option value="Undergraduate">Undergraduate</option>
                        <option value="Postgraduate">Postgraduate</option>
                        <option value="PhD">PhD</option>
                        <option value="Masters">Masters</option>
                        <option value="Diploma">Diploma</option>
                        <option value="Vocational">Vocational</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-4 col-6">
                    <label class="form-label fw-semibold small">Country</label>
                    <select id="filterCountry" class="form-select">
                        <option value="">All Countries</option>
                        <option value="Nigeria">Nigeria</option>
                        <option value="USA">USA</option>
                        <option value="UK">UK</option>
                        <option value="Canada">Canada</option>
                        <option value="Australia">Australia</option>
                        <option value="Germany">Germany</option>
                        <option value="Ghana">Ghana</option>
                        <option value="Kenya">Kenya</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-4 col-6">
                    <label class="form-label fw-semibold small">Academic Level</label>
                    <select id="filterLevel" class="form-select">
                        <option value="">All Levels</option>
                        <option value="100 Level">100 Level</option>
                        <option value="200 Level">200 Level</option>
                        <option value="300 Level">300 Level</option>
                        <option value="400 Level">400 Level</option>
                        <option value="500 Level">500 Level</option>
                        <option value="Postgraduate">Postgraduate</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-4 col-6">
                    <label class="form-label fw-semibold small">Sponsor</label>
                    <select id="filterSponsor" class="form-select">
                        <option value="">All Sponsors</option>
                        <option value="Federal Government">Federal Government</option>
                        <option value="State Government">State Government</option>
                        <option value="NGO">NGO</option>
                        <option value="International">International</option>
                        <option value="Private">Private</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-4 col-6">
                    <label class="form-label fw-semibold small">Status</label>
                    <select id="filterStatus" class="form-select">
                        <option value="">All Status</option>
                        <option value="Open" selected>Open</option>
                        <option value="Closing Soon">Closing Soon</option>
                        <option value="Closed">Closed</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-4 col-6">
                    <label class="form-label fw-semibold small">Search</label>
                    <input type="text" id="filterSearch" class="form-control" placeholder="Search...">
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-12 text-end">
                    <button class="btn btn-primary btn-sm px-4" onclick="applyFilters()">
                        <i class="fas fa-filter me-1"></i>Apply Filters
                    </button>
                    <button class="btn btn-outline-secondary btn-sm px-3" onclick="resetFilters()">
                        <i class="fas fa-redo me-1"></i>Reset
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Featured Scholarships -->
    <section class="container mb-5" id="featuredSection">
        <h2 class="section-title"><i class="fas fa-star me-2" style="color:#ff8f00;"></i>Featured Scholarships</h2>
        <div class="row g-4" id="featuredGrid">
            <div class="loading-spinner">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3 text-muted">Loading featured scholarships...</p>
            </div>
        </div>
    </section>

    <!-- Closing Soon -->
    <section class="container mb-5" id="closingSoonSection">
        <h2 class="section-title"><i class="fas fa-clock me-2" style="color:#c62828;"></i>Closing Soon</h2>
        <div class="row g-4" id="closingSoonGrid">
            <div class="loading-spinner">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        </div>
    </section>

    <!-- All Scholarships -->
    <section class="container mb-5">
        <h2 class="section-title"><i class="fas fa-list me-2"></i>All Scholarships</h2>
        <div class="row g-4" id="scholarshipGrid">
            <div class="loading-spinner">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3 text-muted">Loading scholarships...</p>
            </div>
        </div>

        <!-- Pagination -->
        <nav class="mt-4" id="paginationNav" style="display:none;">
            <ul class="pagination justify-content-center" id="pagination"></ul>
        </nav>
    </section>

    <?php include $path_to_root . 'includes/public_footer.php'; ?>

    <script>
        const API_BASE = './api/scholarship_api.php';
        let currentPage = 1;
        const ITEMS_PER_PAGE = 9;

        const gradients = [
            'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
            'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
            'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
            'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)',
            'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
            'linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%)',
            'linear-gradient(135deg, #fccb90 0%, #d57eeb 100%)',
            'linear-gradient(135deg, #e0c3fc 0%, #8ec5fc 100%)',
        ];

        function getGradient(index) {
            return gradients[index % gradients.length];
        }

        function calculateCountdown(deadline) {
            if (!deadline) return { text: 'No deadline', class: '' };
            const now = new Date();
            const end = new Date(deadline);
            const diff = end - now;
            if (diff <= 0) return { text: 'Deadline passed', class: 'expired' };
            const days = Math.floor(diff / (1000 * 60 * 60 * 24));
            const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            if (days > 30) return { text: `${days} days left`, class: '' };
            if (days > 7) return { text: `${days} days left`, class: '' };
            if (days > 0) return { text: `${days}d ${hours}h left`, class: 'urgent' };
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            return { text: `${hours}h ${minutes}m left`, class: 'urgent' };
        }

        function renderScholarshipCard(scholarship, index) {
            const countdown = calculateCountdown(scholarship.deadline);
            const typeClass = scholarship.type === 'Fully Funded' ? '' :
                            scholarship.type === 'Partial' ? 'partial' : 'merit';
            const imgContent = scholarship.banner_image ?
                `<img src="${scholarship.banner_image}" class="card-img-top" alt="${scholarship.title}">` :
                `<div class="gradient-placeholder" style="background:${getGradient(index)}">
                    <i class="fas fa-graduation-cap"></i>
                </div>`;

            const featuredBadge = scholarship.is_featured ?
                '<span class="featured-badge"><i class="fas fa-star me-1"></i>Featured</span>' : '';

            return `
                <div class="col-lg-4 col-md-6">
                    <div class="card scholarship-card ${scholarship.is_closing_soon ? 'closing-soon-card' : ''}">
                        <div class="position-relative">
                            ${imgContent}
                            ${featuredBadge}
                        </div>
                        <div class="card-body">
                            <div class="mb-2">
                                <span class="badge-category">${scholarship.category || 'General'}</span>
                                <span class="badge-type ${typeClass} ms-1">${scholarship.type || 'Standard'}</span>
                            </div>
                            <h5 class="card-title">
                                <a href="scholarship.php?id=${scholarship.id}">${scholarship.title}</a>
                            </h5>
                            <div class="scholarship-meta">
                                <span><i class="fas fa-coins"></i> ${scholarship.amount || 'Varies'}</span>
                                <span><i class="fas fa-users"></i> ${scholarship.slots || 'Multiple'} slots</span>
                                <span><i class="fas fa-building"></i> ${scholarship.sponsor || 'N/A'}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-auto pt-2" style="border-top: 1px solid #eee;">
                                <span class="deadline-countdown ${countdown.class}">
                                    <i class="fas fa-clock me-1"></i>${countdown.text}
                                </span>
                                <a href="scholarship.php?id=${scholarship.id}" class="btn btn-sm btn-outline-primary">
                                    View Details <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        function renderNoResults() {
            return `
                <div class="col-12 no-results">
                    <i class="fas fa-search d-block"></i>
                    <h4>No scholarships found</h4>
                    <p>Try adjusting your filters or search terms.</p>
                </div>
            `;
        }

        function renderLoading() {
            return `
                <div class="col-12 loading-spinner">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            `;
        }

        function renderPagination(total, perPage, currentPageNum) {
            const totalPages = Math.ceil(total / perPage);
            if (totalPages <= 1) {
                document.getElementById('paginationNav').style.display = 'none';
                return;
            }
            document.getElementById('paginationNav').style.display = 'block';
            let html = '';
            html += `<li class="page-item ${currentPageNum === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="goToPage(${currentPageNum - 1}); return false;">
                    <i class="fas fa-chevron-left"></i>
                </a>
            </li>`;
            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= currentPageNum - 2 && i <= currentPageNum + 2)) {
                    html += `<li class="page-item ${i === currentPageNum ? 'active' : ''}">
                        <a class="page-link" href="#" onclick="goToPage(${i}); return false;">${i}</a>
                    </li>`;
                } else if (i === currentPageNum - 3 || i === currentPageNum + 3) {
                    html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                }
            }
            html += `<li class="page-item ${currentPageNum === totalPages ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="goToPage(${currentPageNum + 1}); return false;">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </li>`;
            document.getElementById('pagination').innerHTML = html;
        }

        function goToPage(page) {
            currentPage = page;
            loadScholarships();
            window.scrollTo({ top: document.getElementById('scholarshipGrid').offsetTop - 100, behavior: 'smooth' });
        }

        function getFilters() {
            return {
                category: document.getElementById('filterCategory').value,
                country: document.getElementById('filterCountry').value,
                level: document.getElementById('filterLevel').value,
                sponsor: document.getElementById('filterSponsor').value,
                status: document.getElementById('filterStatus').value,
                search: document.getElementById('filterSearch').value
            };
        }

        function applyFilters() {
            currentPage = 1;
            loadScholarships();
        }

        function resetFilters() {
            document.getElementById('filterCategory').value = '';
            document.getElementById('filterCountry').value = '';
            document.getElementById('filterLevel').value = '';
            document.getElementById('filterSponsor').value = '';
            document.getElementById('filterStatus').value = 'Open';
            document.getElementById('filterSearch').value = '';
            currentPage = 1;
            loadScholarships();
        }

        async function loadScholarships() {
            const grid = document.getElementById('scholarshipGrid');
            grid.innerHTML = renderLoading();

            const filters = getFilters();
            const params = new URLSearchParams({
                action: 'public_scholarships',
                page: currentPage,
                limit: ITEMS_PER_PAGE,
                ...filters
            });

            try {
                const response = await fetch(`${API_BASE}?${params}`);
                const data = await response.json();

                if (data.success && data.scholarships && data.scholarships.length > 0) {
                    grid.innerHTML = data.scholarships.map((s, i) =>
                        renderScholarshipCard(s, (currentPage - 1) * ITEMS_PER_PAGE + i)
                    ).join('');
                    renderPagination(data.total || data.scholarships.length, ITEMS_PER_PAGE, currentPage);
                } else {
                    grid.innerHTML = renderNoResults();
                    document.getElementById('paginationNav').style.display = 'none';
                }
            } catch (error) {
                console.error('Error loading scholarships:', error);
                grid.innerHTML = `
                    <div class="col-12 no-results">
                        <i class="fas fa-exclamation-triangle d-block" style="color:#ffc107;"></i>
                        <h4>Unable to load scholarships</h4>
                        <p>Please check your connection and try again.</p>
                        <button class="btn btn-primary" onclick="loadScholarships()">
                            <i class="fas fa-redo me-1"></i>Retry
                        </button>
                    </div>
                `;
            }
        }

        async function loadFeatured() {
            const grid = document.getElementById('featuredGrid');
            try {
                const response = await fetch(`${API_BASE}?action=public_scholarships&featured=1&limit=3`);
                const data = await response.json();
                if (data.success && data.scholarships && data.scholarships.length > 0) {
                    grid.innerHTML = data.scholarships.map((s, i) =>
                        renderScholarshipCard(s, i)
                    ).join('');
                } else {
                    document.getElementById('featuredSection').style.display = 'none';
                }
            } catch (error) {
                document.getElementById('featuredSection').style.display = 'none';
            }
        }

        async function loadClosingSoon() {
            const grid = document.getElementById('closingSoonGrid');
            try {
                const response = await fetch(`${API_BASE}?action=public_scholarships&closing_soon=1&limit=3`);
                const data = await response.json();
                if (data.success && data.scholarships && data.scholarships.length > 0) {
                    grid.innerHTML = data.scholarships.map((s, i) =>
                        renderScholarshipCard(s, i)
                    ).join('');
                } else {
                    document.getElementById('closingSoonSection').style.display = 'none';
                }
            } catch (error) {
                document.getElementById('closingSoonSection').style.display = 'none';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            loadFeatured();
            loadClosingSoon();
            loadScholarships();
        });

        document.getElementById('filterSearch').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') applyFilters();
        });
    </script>
</body>
</html>
