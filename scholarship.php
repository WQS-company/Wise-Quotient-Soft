<?php
$path_to_root = './';
session_start();
require_once $path_to_root . 'config.php';
$scholarship_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$page_title = 'Scholarship Details - WQS';
$seo = [
    'title'       => 'Scholarship Details - Wise Quotient Soft',
    'description' => 'View scholarship details, eligibility, and application requirements from Wise Quotient Soft.',
    'keywords'    => 'scholarship details, WQS scholarship, tech scholarship application',
    'canonical'   => 'https://wisequotientsoft.com/scholarship.php',
    'og_image'    => 'https://wisequotientsoft.com/images/og-scholarships.jpg',
];
include $path_to_root . 'includes/public_header.php';
?>
    <style>
        .scholarship-hero {
            position: relative;
            height: 400px;
            overflow: hidden;
            display: flex;
            align-items: flex-end;
        }
        .scholarship-hero .hero-bg {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background-size: cover;
            background-position: center;
            z-index: 0;
        }
        .scholarship-hero .hero-overlay {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0.3) 50%, rgba(0,0,0,0.1) 100%);
            z-index: 1;
        }
        .scholarship-hero .hero-content {
            position: relative;
            z-index: 2;
            padding: 40px;
            width: 100%;
            color: #fff;
        }
        .scholarship-hero .hero-content h1 {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .scholarship-hero .hero-content .meta-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }
        .scholarship-hero .hero-content .meta-badges .badge {
            font-size: 0.8rem;
            padding: 6px 14px;
            border-radius: 20px;
            font-weight: 600;
        }
        .badge-cat {
            background: rgba(255,255,255,0.2);
            color: #fff;
            backdrop-filter: blur(10px);
        }
        .badge-type-hero {
            background: #4caf50;
            color: #fff;
        }
        .badge-type-hero.partial {
            background: #ff9800;
        }
        .badge-type-hero.merit {
            background: #9c27b0;
        }
        .detail-section {
            background: #fff;
            border-radius: 14px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.06);
        }
        .detail-section h3 {
            font-weight: 700;
            color: #1a237e;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e3f2fd;
        }
        .detail-section h3 i {
            margin-right: 10px;
            color: #0d47a1;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        .info-item {
            background: #f8f9fa;
            padding: 18px;
            border-radius: 10px;
            text-align: center;
        }
        .info-item .info-icon {
            font-size: 1.8rem;
            color: #0d47a1;
            margin-bottom: 8px;
        }
        .info-item .info-label {
            font-size: 0.8rem;
            color: #757575;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        .info-item .info-value {
            font-weight: 700;
            color: #1a237e;
            font-size: 1.05rem;
        }
        .countdown-box {
            background: linear-gradient(135deg, #1a237e, #0d47a1);
            color: #fff;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 25px;
        }
        .countdown-box h4 {
            margin-bottom: 15px;
            font-weight: 600;
        }
        .countdown-timer {
            display: flex;
            justify-content: center;
            gap: 15px;
        }
        .countdown-timer .time-block {
            background: rgba(255,255,255,0.15);
            padding: 12px 18px;
            border-radius: 10px;
            min-width: 70px;
        }
        .countdown-timer .time-block .number {
            font-size: 2rem;
            font-weight: 700;
            display: block;
        }
        .countdown-timer .time-block .label {
            font-size: 0.75rem;
            text-transform: uppercase;
            opacity: 0.8;
        }
        .countdown-box.expired {
            background: linear-gradient(135deg, #757575, #9e9e9e);
        }
        .requirements-list {
            list-style: none;
            padding: 0;
        }
        .requirements-list li {
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        .requirements-list li:last-child {
            border-bottom: none;
        }
        .requirements-list li i {
            color: #4caf50;
            margin-top: 3px;
            flex-shrink: 0;
        }
        .benefits-list {
            list-style: none;
            padding: 0;
        }
        .benefits-list li {
            padding: 10px 0;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .benefits-list li i {
            color: #0d47a1;
            margin-top: 3px;
        }
        .terms-content {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            font-size: 0.95rem;
            line-height: 1.8;
            color: #424242;
        }
        .apply-btn {
            background: linear-gradient(135deg, #4caf50, #2e7d32);
            border: none;
            padding: 16px 50px;
            font-size: 1.15rem;
            font-weight: 700;
            border-radius: 50px;
            color: #fff;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(76,175,80,0.3);
        }
        .apply-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(76,175,80,0.4);
            color: #fff;
        }
        .related-card {
            border: none;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
            transition: transform 0.3s ease;
            height: 100%;
        }
        .related-card:hover {
            transform: translateY(-4px);
        }
        .related-card .card-img-top {
            height: 140px;
            object-fit: cover;
        }
        .related-card .gradient-placeholder {
            height: 140px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255,255,255,0.7);
            font-size: 2rem;
        }
        .sponsor-info {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        .sponsor-info .sponsor-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #0d47a1;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.2rem;
            font-weight: 700;
        }
        .loading-container {
            text-align: center;
            padding: 100px 20px;
        }
        .loading-container .spinner-border {
            width: 3rem;
            height: 3rem;
            color: #0d47a1;
        }
        @media (max-width: 768px) {
            .scholarship-hero { height: 300px; }
            .scholarship-hero .hero-content { padding: 20px; }
            .scholarship-hero .hero-content h1 { font-size: 1.5rem; }
            .countdown-timer .time-block { min-width: 55px; padding: 8px 12px; }
            .countdown-timer .time-block .number { font-size: 1.5rem; }
            .detail-section { padding: 20px; }
        }
    </style>
</head>
<body>
    <div id="scholarshipContent">
        <div class="loading-container">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3 text-muted">Loading scholarship details...</p>
        </div>
    </div>

    <?php include $path_to_root . 'includes/public_footer.php'; ?>

    <script>
        const API_BASE = './api/scholarship_api.php';
        const SCHOLARSHIP_ID = <?php echo $scholarship_id; ?>;

        const gradients = [
            'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
            'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
            'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
            'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)',
            'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
            'linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%)',
        ];

        function calculateCountdown(deadline) {
            if (!deadline) return null;
            const now = new Date();
            const end = new Date(deadline);
            const diff = end - now;
            if (diff <= 0) return { expired: true, days: 0, hours: 0, minutes: 0, seconds: 0 };
            return {
                expired: false,
                days: Math.floor(diff / (1000 * 60 * 60 * 24)),
                hours: Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60)),
                minutes: Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60)),
                seconds: Math.floor((diff % (1000 * 60)) / 1000)
            };
        }

        function renderCountdownHTML(deadline) {
            const cd = calculateCountdown(deadline);
            if (!cd) return '';
            if (cd.expired) {
                return `
                    <div class="countdown-box expired">
                        <h4><i class="fas fa-hourglass-end me-2"></i>Application Deadline</h4>
                        <p class="mb-0">This application deadline has passed.</p>
                    </div>`;
            }
            return `
                <div class="countdown-box" id="countdownBox">
                    <h4><i class="fas fa-hourglass-half me-2"></i>Application Deadline</h4>
                    <div class="countdown-timer">
                        <div class="time-block">
                            <span class="number" id="cdDays">${cd.days}</span>
                            <span class="label">Days</span>
                        </div>
                        <div class="time-block">
                            <span class="number" id="cdHours">${cd.hours}</span>
                            <span class="label">Hours</span>
                        </div>
                        <div class="time-block">
                            <span class="number" id="cdMinutes">${cd.minutes}</span>
                            <span class="label">Minutes</span>
                        </div>
                        <div class="time-block">
                            <span class="number" id="cdSeconds">${cd.seconds}</span>
                            <span class="label">Seconds</span>
                        </div>
                    </div>
                </div>`;
        }

        function startCountdown(deadline) {
            const interval = setInterval(() => {
                const cd = calculateCountdown(deadline);
                if (!cd || cd.expired) {
                    clearInterval(interval);
                    const box = document.getElementById('countdownBox');
                    if (box) {
                        box.classList.add('expired');
                        box.querySelector('h4').innerHTML = '<i class="fas fa-hourglass-end me-2"></i>Application Deadline';
                        box.querySelector('.countdown-timer').innerHTML = '<p class="mb-0">This application deadline has passed.</p>';
                    }
                    return;
                }
                document.getElementById('cdDays').textContent = cd.days;
                document.getElementById('cdHours').textContent = cd.hours;
                document.getElementById('cdMinutes').textContent = cd.minutes;
                document.getElementById('cdSeconds').textContent = cd.seconds;
            }, 1000);
        }

        function getTypeClass(type) {
            if (type === 'Fully Funded') return '';
            if (type === 'Partial') return 'partial';
            return 'merit';
        }

        function renderRelatedCard(s, index) {
            const grad = gradients[index % gradients.length];
            const imgContent = s.banner_image ?
                `<img src="${s.banner_image}" class="card-img-top" alt="${s.title}">` :
                `<div class="gradient-placeholder" style="background:${grad}">
                    <i class="fas fa-graduation-cap"></i>
                </div>`;
            return `
                <div class="col-lg-4 col-md-6 mb-3">
                    <div class="card related-card">
                        ${imgContent}
                        <div class="card-body">
                            <span class="badge bg-primary mb-2" style="font-size:0.7rem;">${s.category || 'General'}</span>
                            <h6 class="card-title fw-bold">
                                <a href="scholarship.php?id=${s.id}" class="text-decoration-none" style="color:#1a237e;">${s.title}</a>
                            </h6>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <small class="text-muted"><i class="fas fa-coins me-1"></i>${s.amount || 'Varies'}</small>
                                <a href="scholarship.php?id=${s.id}" class="btn btn-sm btn-outline-primary">View</a>
                            </div>
                        </div>
                    </div>
                </div>`;
        }

        async function loadScholarship() {
            if (!SCHOLARSHIP_ID) {
                document.getElementById('scholarshipContent').innerHTML = `
                    <div class="container py-5 text-center">
                        <i class="fas fa-exclamation-triangle fa-4x text-warning mb-3"></i>
                        <h3>Invalid Scholarship</h3>
                        <p class="text-muted">No scholarship ID provided.</p>
                        <a href="scholarships.php" class="btn btn-primary mt-3">Browse Scholarships</a>
                    </div>`;
                return;
            }

            try {
                const response = await fetch(`${API_BASE}?action=public_scholarship&id=${SCHOLARSHIP_ID}`);
                const data = await response.json();

                if (!data.success || !data.scholarship) {
                    document.getElementById('scholarshipContent').innerHTML = `
                        <div class="container py-5 text-center">
                            <i class="fas fa-search fa-4x text-muted mb-3"></i>
                            <h3>Scholarship Not Found</h3>
                            <p class="text-muted">The scholarship you're looking for doesn't exist or has been removed.</p>
                            <a href="scholarships.php" class="btn btn-primary mt-3">Browse Scholarships</a>
                        </div>`;
                    return;
                }

                const s = data.scholarship;
                const typeClass = getTypeClass(s.type);
                const grad = gradients[s.id % gradients.length];

                const heroBg = s.banner_image ?
                    `background-image: url('${s.banner_image}')` :
                    `background: ${grad}`;

                const requirements = s.requirements ?
                    (Array.isArray(s.requirements) ? s.requirements : s.requirements.split('\n').filter(r => r.trim())) : [];
                const benefits = s.benefits ?
                    (Array.isArray(s.benefits) ? s.benefits : s.benefits.split('\n').filter(b => b.trim())) : [];

                let relatedHTML = '';
                if (data.related && data.related.length > 0) {
                    relatedHTML = `
                        <section class="detail-section">
                            <h3><i class="fas fa-link"></i>Related Scholarships</h3>
                            <div class="row g-3">
                                ${data.related.map((r, i) => renderRelatedCard(r, i)).join('')}
                            </div>
                        </section>`;
                }

                document.getElementById('scholarshipContent').innerHTML = `
                    <!-- Hero Banner -->
                    <div class="scholarship-hero">
                        <div class="hero-bg" style="${heroBg};"></div>
                        <div class="hero-overlay"></div>
                        <div class="hero-content">
                            <div class="container">
                                <a href="scholarships.php" class="btn btn-outline-light btn-sm mb-3">
                                    <i class="fas fa-arrow-left me-1"></i>Back to Scholarships
                                </a>
                                <h1>${s.title}</h1>
                                <div class="meta-badges">
                                    <span class="badge badge-cat"><i class="fas fa-tag me-1"></i>${s.category || 'General'}</span>
                                    <span class="badge badge-type-hero ${typeClass}"><i class="fas fa-award me-1"></i>${s.type || 'Standard'}</span>
                                    ${s.code ? `<span class="badge badge-cat"><i class="fas fa-code me-1"></i>${s.code}</span>` : ''}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="container py-4">
                        <div class="row">
                            <!-- Main Content -->
                            <div class="col-lg-8">
                                <!-- Sponsor Info -->
                                ${s.sponsor ? `
                                <div class="detail-section">
                                    <div class="sponsor-info">
                                        <div class="sponsor-avatar">
                                            <i class="fas fa-building"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold">${s.sponsor}</div>
                                            <small class="text-muted">Scholarship Sponsor</small>
                                        </div>
                                    </div>
                                </div>` : ''}

                                <!-- Info Grid -->
                                <div class="detail-section">
                                    <h3><i class="fas fa-info-circle"></i>Scholarship Overview</h3>
                                    <div class="info-grid">
                                        <div class="info-item">
                                            <div class="info-icon"><i class="fas fa-coins"></i></div>
                                            <div class="info-label">Amount</div>
                                            <div class="info-value">${s.amount || 'Varies'}</div>
                                        </div>
                                        <div class="info-item">
                                            <div class="info-icon"><i class="fas fa-users"></i></div>
                                            <div class="info-label">Available Slots</div>
                                            <div class="info-value">${s.slots || 'Multiple'}</div>
                                        </div>
                                        <div class="info-item">
                                            <div class="info-icon"><i class="fas fa-calendar-alt"></i></div>
                                            <div class="info-label">Deadline</div>
                                            <div class="info-value">${s.deadline ? new Date(s.deadline).toLocaleDateString('en-US', {year:'numeric', month:'long', day:'numeric'}) : 'Ongoing'}</div>
                                        </div>
                                        <div class="info-item">
                                            <div class="info-icon"><i class="fas fa-globe"></i></div>
                                            <div class="info-label">Country</div>
                                            <div class="info-value">${s.country || 'Multiple'}</div>
                                        </div>
                                        <div class="info-item">
                                            <div class="info-icon"><i class="fas fa-book"></i></div>
                                            <div class="info-label">Academic Level</div>
                                            <div class="info-value">${s.level || 'All Levels'}</div>
                                        </div>
                                        <div class="info-item">
                                            <div class="info-icon"><i class="fas fa-award"></i></div>
                                            <div class="info-label">Type</div>
                                            <div class="info-value">${s.type || 'Standard'}</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Description -->
                                ${s.description ? `
                                <div class="detail-section">
                                    <h3><i class="fas fa-align-left"></i>Description</h3>
                                    <div style="line-height:1.9; color:#424242;">
                                        ${s.description}
                                    </div>
                                </div>` : ''}

                                <!-- Eligibility -->
                                ${s.eligibility ? `
                                <div class="detail-section">
                                    <h3><i class="fas fa-check-circle"></i>Eligibility Requirements</h3>
                                    <div style="line-height:1.9; color:#424242;">
                                        ${s.eligibility}
                                    </div>
                                </div>` : ''}

                                <!-- Benefits -->
                                ${benefits.length > 0 ? `
                                <div class="detail-section">
                                    <h3><i class="fas fa-gift"></i>Benefits</h3>
                                    <ul class="benefits-list">
                                        ${benefits.map(b => `<li><i class="fas fa-check-circle"></i><span>${b.trim()}</span></li>`).join('')}
                                    </ul>
                                </div>` : ''}

                                <!-- Requirements/Documents -->
                                ${requirements.length > 0 ? `
                                <div class="detail-section">
                                    <h3><i class="fas fa-file-alt"></i>Required Documents</h3>
                                    <ul class="requirements-list">
                                        ${requirements.map(r => `<li><i class="fas fa-check-circle"></i><span>${r.trim()}</span></li>`).join('')}
                                    </ul>
                                </div>` : ''}

                                <!-- Terms & Conditions -->
                                ${s.terms ? `
                                <div class="detail-section">
                                    <h3><i class="fas fa-gavel"></i>Terms & Conditions</h3>
                                    <div class="terms-content">
                                        ${s.terms}
                                    </div>
                                </div>` : ''}

                                ${relatedHTML}
                            </div>

                            <!-- Sidebar -->
                            <div class="col-lg-4">
                                <div class="detail-section" style="position:sticky; top:100px;">
                                    ${renderCountdownHTML(s.deadline)}

                                    <div class="text-center mb-4">
                                        <a href="scholarship_apply.php?id=${SCHOLARSHIP_ID}" class="btn apply-btn w-100">
                                            <i class="fas fa-paper-plane me-2"></i>Apply Now
                                        </a>
                                    </div>

                                    <div class="info-grid" style="grid-template-columns: 1fr;">
                                        <div class="info-item text-start">
                                            <div class="d-flex align-items-center gap-3">
                                                <i class="fas fa-tag fa-lg" style="color:#0d47a1;"></i>
                                                <div>
                                                    <div class="info-label">Category</div>
                                                    <div class="info-value">${s.category || 'General'}</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="info-item text-start">
                                            <div class="d-flex align-items-center gap-3">
                                                <i class="fas fa-award fa-lg" style="color:#4caf50;"></i>
                                                <div>
                                                    <div class="info-label">Type</div>
                                                    <div class="info-value">${s.type || 'Standard'}</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="info-item text-start">
                                            <div class="d-flex align-items-center gap-3">
                                                <i class="fas fa-map-marker-alt fa-lg" style="color:#e91e63;"></i>
                                                <div>
                                                    <div class="info-label">Country</div>
                                                    <div class="info-value">${s.country || 'Multiple'}</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <hr>
                                    <div class="text-center">
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Questions? Contact the sponsor directly.
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>`;

                if (s.deadline) startCountdown(s.deadline);

            } catch (error) {
                console.error('Error loading scholarship:', error);
                document.getElementById('scholarshipContent').innerHTML = `
                    <div class="container py-5 text-center">
                        <i class="fas fa-exclamation-triangle fa-4x text-warning mb-3"></i>
                        <h3>Unable to Load Scholarship</h3>
                        <p class="text-muted">Please check your connection and try again.</p>
                        <button class="btn btn-primary mt-3" onclick="loadScholarship()">
                            <i class="fas fa-redo me-1"></i>Retry
                        </button>
                    </div>`;
            }
        }

        document.addEventListener('DOMContentLoaded', loadScholarship);
    </script>
</body>
</html>
