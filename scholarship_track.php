<?php
$path_to_root = './';
session_start();
require_once $path_to_root . 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Application - WQS</title>
    <?php include $path_to_root . 'includes/public_header.php'; ?>
    <style>
        .track-hero {
            background: linear-gradient(135deg, #1a237e 0%, #0d47a1 50%, #01579b 100%);
            color: #fff;
            padding: 60px 0 50px;
        }
        .track-hero h1 {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .track-hero p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        .track-form-card {
            background: #fff;
            border-radius: 14px;
            padding: 40px;
            box-shadow: 0 4px 25px rgba(0,0,0,0.08);
            max-width: 550px;
            margin: -30px auto 40px;
            position: relative;
            z-index: 5;
        }
        .track-form-card h3 {
            font-weight: 700;
            color: #1a237e;
            margin-bottom: 25px;
            text-align: center;
        }
        .track-form-card .form-control {
            border-radius: 10px;
            padding: 14px 16px;
            font-size: 1rem;
            border: 2px solid #e0e0e0;
        }
        .track-form-card .form-control:focus {
            border-color: #0d47a1;
            box-shadow: 0 0 0 0.2rem rgba(13,71,161,0.15);
        }
        .track-btn {
            background: linear-gradient(135deg, #0d47a1, #1565c0);
            border: none;
            padding: 14px 40px;
            font-size: 1.05rem;
            font-weight: 700;
            border-radius: 10px;
            color: #fff;
            width: 100%;
            transition: all 0.3s ease;
        }
        .track-btn:hover {
            background: linear-gradient(135deg, #1565c0, #1976d2);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(13,71,161,0.3);
        }
        .track-btn:disabled {
            opacity: 0.7;
            transform: none;
        }

        /* Results */
        .results-container {
            max-width: 800px;
            margin: 0 auto 50px;
            display: none;
        }
        .result-card {
            background: #fff;
            border-radius: 14px;
            padding: 35px;
            box-shadow: 0 4px 25px rgba(0,0,0,0.08);
        }
        .result-header {
            text-align: center;
            padding-bottom: 25px;
            border-bottom: 2px solid #f0f0f0;
            margin-bottom: 30px;
        }
        .result-header .app-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0d47a1, #1565c0);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            color: #fff;
            font-size: 1.8rem;
        }
        .result-header h3 {
            font-weight: 700;
            color: #1a237e;
            margin-bottom: 5px;
        }
        .result-header .app-code {
            font-size: 1.3rem;
            font-weight: 800;
            color: #0d47a1;
            letter-spacing: 1px;
        }
        .result-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .result-info-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }
        .result-info-item .label {
            font-size: 0.75rem;
            text-transform: uppercase;
            color: #9e9e9e;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        .result-info-item .value {
            font-weight: 700;
            color: #1a237e;
            font-size: 1rem;
        }

        /* Status Stepper */
        .status-stepper {
            position: relative;
            padding: 20px 0;
        }
        .stepper-line {
            position: absolute;
            left: 28px;
            top: 0;
            bottom: 0;
            width: 3px;
            background: #e0e0e0;
        }
        .stepper-line-fill {
            position: absolute;
            left: 28px;
            top: 0;
            width: 3px;
            background: linear-gradient(to bottom, #4caf50, #0d47a1);
            transition: height 0.8s ease;
        }
        .stepper-step {
            display: flex;
            align-items: flex-start;
            gap: 20px;
            position: relative;
            margin-bottom: 30px;
            padding-left: 5px;
        }
        .stepper-step:last-child {
            margin-bottom: 0;
        }
        .stepper-step .step-dot {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.1rem;
            flex-shrink: 0;
            z-index: 2;
            transition: all 0.3s ease;
        }
        .stepper-step.completed .step-dot {
            background: linear-gradient(135deg, #4caf50, #2e7d32);
            box-shadow: 0 0 0 4px rgba(76,175,80,0.2);
        }
        .stepper-step.active .step-dot {
            background: linear-gradient(135deg, #0d47a1, #1565c0);
            box-shadow: 0 0 0 4px rgba(13,71,161,0.2);
            animation: pulse 2s infinite;
        }
        .stepper-step.rejected .step-dot {
            background: linear-gradient(135deg, #c62828, #b71c1c);
            box-shadow: 0 0 0 4px rgba(198,40,40,0.2);
        }
        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 4px rgba(13,71,161,0.2); }
            50% { box-shadow: 0 0 0 8px rgba(13,71,161,0.1); }
        }
        .stepper-step .step-content {
            padding-top: 8px;
        }
        .stepper-step .step-title {
            font-weight: 700;
            font-size: 1rem;
            color: #757575;
        }
        .stepper-step.completed .step-title {
            color: #2e7d32;
        }
        .stepper-step.active .step-title {
            color: #0d47a1;
        }
        .stepper-step.rejected .step-title {
            color: #c62828;
        }
        .stepper-step .step-date {
            font-size: 0.82rem;
            color: #9e9e9e;
            margin-top: 3px;
        }
        .stepper-step .step-desc {
            font-size: 0.85rem;
            color: #757575;
            margin-top: 3px;
        }

        .not-found {
            text-align: center;
            padding: 40px;
            display: none;
        }
        .not-found i {
            font-size: 4rem;
            color: #bdbdbd;
            margin-bottom: 15px;
        }

        .error-container {
            text-align: center;
            padding: 20px;
            display: none;
        }
        .error-container .alert {
            max-width: 500px;
            margin: 0 auto;
            border-radius: 10px;
        }

        @media (max-width: 768px) {
            .track-hero h1 { font-size: 1.6rem; }
            .track-form-card { padding: 25px; margin: -20px 15px 30px; }
            .result-card { padding: 20px; }
            .stepper-step .step-dot { width: 40px; height: 40px; font-size: 0.9rem; }
            .stepper-line, .stepper-line-fill { left: 24px; }
            .result-info-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <!-- Hero -->
    <section class="track-hero">
        <div class="container text-center">
            <h1><i class="fas fa-search-location me-2"></i>Track Your Application</h1>
            <p>Enter your application code and email to check the status of your scholarship application.</p>
        </div>
    </section>

    <!-- Track Form -->
    <div class="container">
        <div class="track-form-card">
            <h3><i class="fas fa-clipboard-list me-2"></i>Application Lookup</h3>
            <form id="trackForm">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Application Code <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="appCode" required
                           placeholder="e.g. WQS-2026-00001"
                           style="text-transform:uppercase; letter-spacing:1px; text-align:center; font-weight:700; font-size:1.1rem;">
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="appEmail" required
                           placeholder="Enter the email used during application">
                </div>
                <button type="submit" class="btn track-btn" id="trackBtn">
                    <i class="fas fa-search me-2"></i>Track Application
                </button>
            </form>
        </div>
    </div>

    <!-- Error -->
    <div class="container">
        <div class="error-container" id="errorContainer">
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>
                <span id="errorMessage">Something went wrong.</span>
            </div>
        </div>
    </div>

    <!-- Not Found -->
    <div class="container">
        <div class="not-found" id="notFound">
            <i class="fas fa-search d-block"></i>
            <h4>Application Not Found</h4>
            <p class="text-muted">No application matches the code and email provided. Please check and try again.</p>
        </div>
    </div>

    <!-- Results -->
    <div class="container results-container" id="resultsContainer">
        <div class="result-card">
            <!-- Header -->
            <div class="result-header">
                <div class="app-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <h3>Application Details</h3>
                <div class="app-code" id="res_code">-</div>
            </div>

            <!-- Info Grid -->
            <div class="result-info-grid">
                <div class="result-info-item">
                    <div class="label">Applicant Name</div>
                    <div class="value" id="res_name">-</div>
                </div>
                <div class="result-info-item">
                    <div class="label">Scholarship</div>
                    <div class="value" id="res_scholarship">-</div>
                </div>
                <div class="result-info-item">
                    <div class="label">Current Status</div>
                    <div class="value" id="res_status">-</div>
                </div>
                <div class="result-info-item">
                    <div class="label">Submission Date</div>
                    <div class="value" id="res_date">-</div>
                </div>
            </div>

            <!-- Status Stepper -->
            <h5 class="fw-bold mb-3" style="color:#1a237e;">
                <i class="fas fa-route me-2"></i>Application Progress
            </h5>
            <div class="status-stepper" id="stepper">
                <div class="stepper-line"></div>
                <div class="stepper-line-fill" id="stepperFill" style="height: 0%;"></div>
            </div>
        </div>
    </div>

    <?php include $path_to_root . 'includes/public_footer.php'; ?>

    <script>
        const API_BASE = './api/scholarship_api.php';

        const STEPS = [
            { key: 'submitted', label: 'Submitted', icon: 'fas fa-paper-plane', desc: 'Your application has been received.' },
            { key: 'under_review', label: 'Under Review', icon: 'fas fa-eye', desc: 'Your application is being reviewed by the committee.' },
            { key: 'shortlisted', label: 'Shortlisted', icon: 'fas fa-list-check', desc: 'Congratulations! You have been shortlisted.' },
            { key: 'interview', label: 'Interview', icon: 'fas fa-comments', desc: 'You have been invited for an interview.' },
            { key: 'approved', label: 'Approved', icon: 'fas fa-check-circle', desc: 'Congratulations! Your application has been approved.' },
            { key: 'rejected', label: 'Rejected', icon: 'fas fa-times-circle', desc: 'Unfortunately, your application was not successful.' }
        ];

        const STATUS_ORDER = ['submitted', 'under_review', 'shortlisted', 'interview', 'approved'];

        function getStepIndex(status) {
            const s = (status || '').toLowerCase().replace(/\s+/g, '_');
            if (s === 'rejected') return -1;
            const idx = STATUS_ORDER.indexOf(s);
            return idx >= 0 ? idx : 0;
        }

        function renderStepper(currentStatus) {
            const container = document.getElementById('stepper');
            const currentIdx = getStepIndex(currentStatus);
            const isRejected = (currentStatus || '').toLowerCase() === 'rejected';

            let html = '<div class="stepper-line"></div>';

            STEPS.forEach((step, index) => {
                let stateClass = '';
                if (isRejected && step.key === 'rejected') {
                    stateClass = 'rejected';
                } else if (isRejected && index <= currentIdx) {
                    stateClass = 'completed';
                } else if (!isRejected && index < currentIdx) {
                    stateClass = 'completed';
                } else if (!isRejected && index === currentIdx) {
                    stateClass = 'active';
                }

                const stepDate = (step.key === (currentStatus || '').toLowerCase().replace(/\s+/g, '_')) ?
                    document.getElementById('res_date').textContent : '';

                html += `
                    <div class="stepper-step ${stateClass}">
                        <div class="step-dot">
                            <i class="${stateClass === 'completed' ? 'fas fa-check' : step.icon}"></i>
                        </div>
                        <div class="step-content">
                            <div class="step-title">${step.label}</div>
                            <div class="step-desc">${step.desc}</div>
                            ${stepDate ? `<div class="step-date"><i class="fas fa-clock me-1"></i>${stepDate}</div>` : ''}
                        </div>
                    </div>`;
            });

            container.innerHTML = html;

            const fill = document.getElementById('stepperFill');
            if (isRejected) {
                fill.style.height = `${((currentIdx + 1) / (STEPS.length - 1)) * 100}%`;
            } else {
                fill.style.height = `${((currentIdx + 1) / STATUS_ORDER.length) * 100}%`;
            }
        }

        document.getElementById('trackForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const code = document.getElementById('appCode').value.trim().toUpperCase();
            const email = document.getElementById('appEmail').value.trim();

            if (!code || !email) {
                alert('Please enter both application code and email.');
                return;
            }

            const btn = document.getElementById('trackBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Tracking...';

            document.getElementById('resultsContainer').style.display = 'none';
            document.getElementById('notFound').style.display = 'none';
            document.getElementById('errorContainer').style.display = 'none';

            try {
                const response = await fetch(`${API_BASE}?action=track_application`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ application_code: code, email: email })
                });

                const result = await response.json();

                if (result.success && result.application) {
                    const app = result.application;

                    document.getElementById('res_code').textContent = app.application_code || code;
                    document.getElementById('res_name').textContent = app.full_name || app.name || '-';
                    document.getElementById('res_scholarship').textContent = app.scholarship_title || app.scholarship || '-';
                    document.getElementById('res_status').textContent = app.status || '-';
                    document.getElementById('res_date').textContent = app.submission_date || app.created_at || '-';

                    const statusEl = document.getElementById('res_status');
                    const statusLower = (app.status || '').toLowerCase();
                    if (statusLower === 'approved') {
                        statusEl.style.color = '#2e7d32';
                    } else if (statusLower === 'rejected') {
                        statusEl.style.color = '#c62828';
                    } else {
                        statusEl.style.color = '#0d47a1';
                    }

                    renderStepper(app.status);
                    document.getElementById('resultsContainer').style.display = 'block';
                    document.getElementById('resultsContainer').scrollIntoView({ behavior: 'smooth', block: 'start' });
                } else if (result.found === false || result.success === false) {
                    document.getElementById('notFound').style.display = 'block';
                    document.getElementById('notFound').scrollIntoView({ behavior: 'smooth', block: 'start' });
                } else {
                    document.getElementById('errorMessage').textContent = result.message || 'Application not found.';
                    document.getElementById('errorContainer').style.display = 'block';
                }
            } catch (error) {
                console.error('Error tracking:', error);
                document.getElementById('errorMessage').textContent = 'Network error. Please check your connection and try again.';
                document.getElementById('errorContainer').style.display = 'block';
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-search me-2"></i>Track Application';
            }
        });
    </script>
</body>
</html>
