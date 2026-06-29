<?php
$path_to_root = './';
session_start();
require_once $path_to_root . 'config.php';

// Enforce login to apply for scholarships
if (!isset($_SESSION['user']['id'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: login.php?error=" . urlencode("You must log in or create an account to apply for scholarships."));
    exit;
}

$scholarship_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scholarship Application - WQS</title>
    <?php include $path_to_root . 'includes/public_header.php'; ?>
    <style>
        .apply-hero {
            background: linear-gradient(135deg, #1a237e 0%, #0d47a1 50%, #01579b 100%);
            color: #fff;
            padding: 40px 0 30px;
        }
        .apply-hero h1 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .apply-hero .scholarship-title {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        .progress-container {
            background: #fff;
            border-radius: 12px;
            padding: 25px 30px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.06);
            margin-bottom: 30px;
            margin-top: -20px;
            position: relative;
            z-index: 5;
        }
        .step-indicator {
            display: flex;
            justify-content: space-between;
            position: relative;
        }
        .step-indicator::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 40px;
            right: 40px;
            height: 3px;
            background: #e0e0e0;
            z-index: 0;
        }
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 1;
            flex: 1;
        }
        .step .step-circle {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: #e0e0e0;
            color: #9e9e9e;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.95rem;
            margin-bottom: 8px;
            transition: all 0.3s ease;
        }
        .step.active .step-circle {
            background: #0d47a1;
            color: #fff;
            box-shadow: 0 0 0 4px rgba(13,71,161,0.2);
        }
        .step.completed .step-circle {
            background: #4caf50;
            color: #fff;
        }
        .step .step-label {
            font-size: 0.78rem;
            font-weight: 600;
            color: #9e9e9e;
            text-align: center;
        }
        .step.active .step-label {
            color: #0d47a1;
        }
        .step.completed .step-label {
            color: #4caf50;
        }
        .form-card {
            background: #fff;
            border-radius: 14px;
            padding: 35px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.06);
            min-height: 400px;
        }
        .form-card h3 {
            font-weight: 700;
            color: #1a237e;
            margin-bottom: 25px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e3f2fd;
        }
        .form-card h3 i {
            margin-right: 10px;
            color: #0d47a1;
        }
        .form-label {
            font-weight: 600;
            color: #424242;
            margin-bottom: 6px;
        }
        .form-control, .form-select {
            border-radius: 8px;
            padding: 11px 14px;
            border: 1px solid #dee2e6;
            font-size: 0.95rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: #0d47a1;
            box-shadow: 0 0 0 0.2rem rgba(13,71,161,0.15);
        }
        .file-upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 25px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #fafafa;
        }
        .file-upload-area:hover {
            border-color: #0d47a1;
            background: #e3f2fd;
        }
        .file-upload-area.has-file {
            border-color: #4caf50;
            background: #e8f5e9;
        }
        .file-upload-area i {
            font-size: 2rem;
            color: #bdbdbd;
            margin-bottom: 8px;
        }
        .file-upload-area.has-file i {
            color: #4caf50;
        }
        .file-upload-area .file-name {
            font-weight: 600;
            color: #424242;
            margin-top: 5px;
        }
        .file-upload-area .file-hint {
            font-size: 0.8rem;
            color: #9e9e9e;
            margin-top: 3px;
        }
        .btn-nav {
            padding: 12px 35px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
        }
        .btn-next {
            background: #0d47a1;
            color: #fff;
            border: none;
        }
        .btn-next:hover {
            background: #1565c0;
            color: #fff;
        }
        .btn-prev {
            background: #fff;
            color: #0d47a1;
            border: 2px solid #0d47a1;
        }
        .btn-prev:hover {
            background: #e3f2fd;
            color: #0d47a1;
        }
        .btn-submit {
            background: linear-gradient(135deg, #4caf50, #2e7d32);
            color: #fff;
            border: none;
            padding: 14px 50px;
            font-size: 1.1rem;
            font-weight: 700;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(76,175,80,0.3);
        }
        .btn-submit:hover {
            background: linear-gradient(135deg, #388e3c, #1b5e20);
            color: #fff;
            transform: translateY(-1px);
        }
        .review-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
        }
        .review-section h5 {
            font-weight: 700;
            color: #1a237e;
            font-size: 1rem;
            margin-bottom: 12px;
        }
        .review-row {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .review-row:last-child {
            border-bottom: none;
        }
        .review-label {
            font-weight: 600;
            color: #757575;
            min-width: 180px;
            font-size: 0.9rem;
        }
        .review-value {
            color: #212121;
            font-size: 0.9rem;
        }
        .step-content {
            display: none;
        }
        .step-content.active {
            display: block;
        }
        .success-container {
            text-align: center;
            padding: 50px 20px;
        }
        .success-icon {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4caf50, #2e7d32);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            color: #fff;
            font-size: 3rem;
            animation: popIn 0.5s ease;
        }
        @keyframes popIn {
            0% { transform: scale(0); }
            80% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        .app-code-box {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 10px;
            display: inline-block;
            margin: 15px 0;
        }
        .app-code-box .code {
            font-size: 1.8rem;
            font-weight: 800;
            color: #0d47a1;
            letter-spacing: 2px;
        }
        .loading-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        .loading-overlay .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #fff;
            border-top-color: #0d47a1;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        @media (max-width: 768px) {
            .apply-hero h1 { font-size: 1.4rem; }
            .form-card { padding: 20px; }
            .step .step-label { font-size: 0.7rem; }
            .step .step-circle { width: 35px; height: 35px; font-size: 0.85rem; }
            .step-indicator::before { top: 17px; left: 30px; right: 30px; }
            .review-row { flex-direction: column; }
            .review-label { min-width: auto; margin-bottom: 3px; }
        }
    </style>
</head>
<body>
    <!-- Hero -->
    <section class="apply-hero">
        <div class="container">
            <a href="scholarships.php" class="btn btn-outline-light btn-sm mb-2">
                <i class="fas fa-arrow-left me-1"></i>Back to Scholarships
            </a>
            <h1><i class="fas fa-pen-fancy me-2"></i>Apply for Scholarship</h1>
            <div class="scholarship-title" id="scholarshipTitle">Loading...</div>
        </div>
    </section>

    <!-- Progress Steps -->
    <div class="container">
        <div class="progress-container">
            <div class="step-indicator">
                <div class="step active" data-step="1">
                    <div class="step-circle">1</div>
                    <div class="step-label">Personal Info</div>
                </div>
                <div class="step" data-step="2">
                    <div class="step-circle">2</div>
                    <div class="step-label">Academic Info</div>
                </div>
                <div class="step" data-step="3">
                    <div class="step-circle">3</div>
                    <div class="step-label">Documents</div>
                </div>
                <div class="step" data-step="4">
                    <div class="step-circle">4</div>
                    <div class="step-label">Review & Submit</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Form -->
    <div class="container pb-5">
        <div class="form-card">
            <form id="applicationForm" enctype="multipart/form-data">
                <input type="hidden" name="scholarship_id" value="<?php echo $scholarship_id; ?>">

                <!-- Step 1: Personal Info -->
                <div class="step-content active" id="step1">
                    <h3><i class="fas fa-user"></i>Personal Information</h3>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="full_name" required placeholder="Enter your full name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Gender <span class="text-danger">*</span></label>
                            <select class="form-select" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date of Birth <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="dob" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" required placeholder="your@email.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" name="phone" required placeholder="+234 XXX XXX XXXX">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">State <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="state" required placeholder="Enter your state">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Country <span class="text-danger">*</span></label>
                            <select class="form-select" name="country" required>
                                <option value="">Select Country</option>
                                <option value="Nigeria">Nigeria</option>
                                <option value="Ghana">Ghana</option>
                                <option value="Kenya">Kenya</option>
                                <option value="South Africa">South Africa</option>
                                <option value="USA">USA</option>
                                <option value="UK">UK</option>
                                <option value="Canada">Canada</option>
                                <option value="Australia">Australia</option>
                                <option value="Germany">Germany</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="address" rows="2" required placeholder="Enter your full address"></textarea>
                        </div>
                    </div>
                    <div class="text-end mt-4">
                        <button type="button" class="btn btn-nav btn-next" onclick="nextStep(2)">
                            Next: Academic Info <i class="fas fa-arrow-right ms-2"></i>
                        </button>
                    </div>
                </div>

                <!-- Step 2: Academic Info -->
                <div class="step-content" id="step2">
                    <h3><i class="fas fa-graduation-cap"></i>Academic Information</h3>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Institution <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="institution" required placeholder="Name of your institution">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Faculty <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="faculty" required placeholder="Your faculty">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Department <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="department" required placeholder="Your department">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Course of Study <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="course" required placeholder="Your course/program">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Academic Level <span class="text-danger">*</span></label>
                            <select class="form-select" name="level" required>
                                <option value="">Select Level</option>
                                <option value="100 Level">100 Level</option>
                                <option value="200 Level">200 Level</option>
                                <option value="300 Level">300 Level</option>
                                <option value="400 Level">400 Level</option>
                                <option value="500 Level">500 Level</option>
                                <option value="Postgraduate">Postgraduate</option>
                                <option value="PhD">PhD</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">CGPA / Grade <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="cgpa" required placeholder="e.g. 4.5/5.0 or First Class">
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-4">
                        <button type="button" class="btn btn-nav btn-prev" onclick="prevStep(1)">
                            <i class="fas fa-arrow-left me-2"></i>Previous
                        </button>
                        <button type="button" class="btn btn-nav btn-next" onclick="nextStep(3)">
                            Next: Documents <i class="fas fa-arrow-right ms-2"></i>
                        </button>
                    </div>
                </div>

                <!-- Step 3: Documents -->
                <div class="step-content" id="step3">
                    <h3><i class="fas fa-file-upload"></i>Upload Documents</h3>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Passport Photo <span class="text-danger">*</span></label>
                            <div class="file-upload-area" onclick="triggerUpload('passport_photo')" id="passport_photo_area">
                                <i class="fas fa-cloud-upload-alt d-block"></i>
                                <div class="file-name" id="passport_photo_name">Click to upload passport photo</div>
                                <div class="file-hint">JPG, PNG (Max 2MB)</div>
                                <input type="file" name="passport_photo" id="passport_photo" accept="image/*" style="display:none" onchange="handleFile(this, 'passport_photo')">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Admission Letter <span class="text-danger">*</span></label>
                            <div class="file-upload-area" onclick="triggerUpload('admission_letter')" id="admission_letter_area">
                                <i class="fas fa-cloud-upload-alt d-block"></i>
                                <div class="file-name" id="admission_letter_name">Click to upload admission letter</div>
                                <div class="file-hint">PDF, JPG, PNG (Max 5MB)</div>
                                <input type="file" name="admission_letter" id="admission_letter" accept=".pdf,image/*" style="display:none" onchange="handleFile(this, 'admission_letter')">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Transcript <span class="text-danger">*</span></label>
                            <div class="file-upload-area" onclick="triggerUpload('transcript')" id="transcript_area">
                                <i class="fas fa-cloud-upload-alt d-block"></i>
                                <div class="file-name" id="transcript_name">Click to upload transcript</div>
                                <div class="file-hint">PDF (Max 5MB)</div>
                                <input type="file" name="transcript" id="transcript" accept=".pdf" style="display:none" onchange="handleFile(this, 'transcript')">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">ID Card</label>
                            <div class="file-upload-area" onclick="triggerUpload('id_card')" id="id_card_area">
                                <i class="fas fa-cloud-upload-alt d-block"></i>
                                <div class="file-name" id="id_card_name">Click to upload ID card</div>
                                <div class="file-hint">PDF, JPG, PNG (Max 3MB)</div>
                                <input type="file" name="id_card" id="id_card" accept=".pdf,image/*" style="display:none" onchange="handleFile(this, 'id_card')">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Recommendation Letter</label>
                            <div class="file-upload-area" onclick="triggerUpload('recommendation_letter')" id="recommendation_letter_area">
                                <i class="fas fa-cloud-upload-alt d-block"></i>
                                <div class="file-name" id="recommendation_letter_name">Click to upload recommendation letter</div>
                                <div class="file-hint">PDF (Max 5MB)</div>
                                <input type="file" name="recommendation_letter" id="recommendation_letter" accept=".pdf" style="display:none" onchange="handleFile(this, 'recommendation_letter')">
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Personal Statement <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="personal_statement" rows="5" required placeholder="Write a brief personal statement explaining why you deserve this scholarship..."></textarea>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-4">
                        <button type="button" class="btn btn-nav btn-prev" onclick="prevStep(2)">
                            <i class="fas fa-arrow-left me-2"></i>Previous
                        </button>
                        <button type="button" class="btn btn-nav btn-next" onclick="nextStep(4)">
                            Next: Review <i class="fas fa-arrow-right ms-2"></i>
                        </button>
                    </div>
                </div>

                <!-- Step 4: Review -->
                <div class="step-content" id="step4">
                    <h3><i class="fas fa-clipboard-check"></i>Review & Submit</h3>
                    <p class="text-muted mb-4">Please review your information before submitting.</p>

                    <div class="review-section">
                        <h5><i class="fas fa-user me-2" style="color:#0d47a1;"></i>Personal Information</h5>
                        <div class="review-row">
                            <span class="review-label">Full Name:</span>
                            <span class="review-value" id="rev_full_name"></span>
                        </div>
                        <div class="review-row">
                            <span class="review-label">Gender:</span>
                            <span class="review-value" id="rev_gender"></span>
                        </div>
                        <div class="review-row">
                            <span class="review-label">Date of Birth:</span>
                            <span class="review-value" id="rev_dob"></span>
                        </div>
                        <div class="review-row">
                            <span class="review-label">Email:</span>
                            <span class="review-value" id="rev_email"></span>
                        </div>
                        <div class="review-row">
                            <span class="review-label">Phone:</span>
                            <span class="review-value" id="rev_phone"></span>
                        </div>
                        <div class="review-row">
                            <span class="review-label">Address:</span>
                            <span class="review-value" id="rev_address"></span>
                        </div>
                        <div class="review-row">
                            <span class="review-label">State:</span>
                            <span class="review-value" id="rev_state"></span>
                        </div>
                        <div class="review-row">
                            <span class="review-label">Country:</span>
                            <span class="review-value" id="rev_country"></span>
                        </div>
                    </div>

                    <div class="review-section">
                        <h5><i class="fas fa-graduation-cap me-2" style="color:#0d47a1;"></i>Academic Information</h5>
                        <div class="review-row">
                            <span class="review-label">Institution:</span>
                            <span class="review-value" id="rev_institution"></span>
                        </div>
                        <div class="review-row">
                            <span class="review-label">Faculty:</span>
                            <span class="review-value" id="rev_faculty"></span>
                        </div>
                        <div class="review-row">
                            <span class="review-label">Department:</span>
                            <span class="review-value" id="rev_department"></span>
                        </div>
                        <div class="review-row">
                            <span class="review-label">Course:</span>
                            <span class="review-value" id="rev_course"></span>
                        </div>
                        <div class="review-row">
                            <span class="review-label">Level:</span>
                            <span class="review-value" id="rev_level"></span>
                        </div>
                        <div class="review-row">
                            <span class="review-label">CGPA:</span>
                            <span class="review-value" id="rev_cgpa"></span>
                        </div>
                    </div>

                    <div class="review-section">
                        <h5><i class="fas fa-file-alt me-2" style="color:#0d47a1;"></i>Documents</h5>
                        <div class="review-row">
                            <span class="review-label">Passport Photo:</span>
                            <span class="review-value" id="rev_passport_photo"></span>
                        </div>
                        <div class="review-row">
                            <span class="review-label">Admission Letter:</span>
                            <span class="review-value" id="rev_admission_letter"></span>
                        </div>
                        <div class="review-row">
                            <span class="review-label">Transcript:</span>
                            <span class="review-value" id="rev_transcript"></span>
                        </div>
                        <div class="review-row">
                            <span class="review-label">ID Card:</span>
                            <span class="review-value" id="rev_id_card"></span>
                        </div>
                        <div class="review-row">
                            <span class="review-label">Recommendation:</span>
                            <span class="review-value" id="rev_recommendation_letter"></span>
                        </div>
                        <div class="review-row">
                            <span class="review-label">Personal Statement:</span>
                            <span class="review-value" id="rev_personal_statement"></span>
                        </div>
                    </div>

                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" id="agreeTerms" required>
                        <label class="form-check-label" for="agreeTerms">
                            I confirm that all information provided is accurate and I agree to the terms and conditions.
                        </label>
                    </div>

                    <div class="d-flex justify-content-between">
                        <button type="button" class="btn btn-nav btn-prev" onclick="prevStep(3)">
                            <i class="fas fa-arrow-left me-2"></i>Previous
                        </button>
                        <button type="submit" class="btn btn-submit" id="submitBtn">
                            <i class="fas fa-paper-plane me-2"></i>Submit Application
                        </button>
                    </div>
                </div>
            </form>

            <!-- Success State -->
            <div id="successState" style="display:none;">
                <div class="success-container">
                    <div class="success-icon">
                        <i class="fas fa-check"></i>
                    </div>
                    <h2 class="fw-bold" style="color:#1a237e;">Application Submitted!</h2>
                    <p class="text-muted mb-4">Your scholarship application has been submitted successfully.</p>
                    <div class="app-code-box">
                        <div class="text-muted small mb-1">Your Application Code</div>
                        <div class="code" id="appCode">-</div>
                    </div>
                    <p class="mt-3 text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        Save this code. Use it to track your application status.
                    </p>
                    <div class="mt-4">
                        <a href="scholarship_track.php" class="btn btn-primary btn-lg me-2">
                            <i class="fas fa-search-location me-1"></i>Track Application
                        </a>
                        <a href="scholarships.php" class="btn btn-outline-secondary btn-lg">
                            <i class="fas fa-list me-1"></i>Browse More
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay" style="display:none;">
        <div class="text-center text-white">
            <div class="spinner mb-3"></div>
            <p>Submitting your application...</p>
        </div>
    </div>

    <?php include $path_to_root . 'includes/public_footer.php'; ?>

    <script>
        const API_BASE = './api/scholarship_api.php';
        const SCHOLARSHIP_ID = <?php echo $scholarship_id; ?>;
        let currentStep = 1;

        async function loadScholarshipTitle() {
            try {
                const response = await fetch(`${API_BASE}?action=public_scholarship&id=${SCHOLARSHIP_ID}`);
                const data = await response.json();
                if (data.success && data.scholarship) {
                    document.getElementById('scholarshipTitle').innerHTML =
                        `<i class="fas fa-graduation-cap me-1"></i>${data.scholarship.title}`;
                    document.title = `Apply - ${data.scholarship.title} - WQS`;
                }
            } catch (e) {
                document.getElementById('scholarshipTitle').textContent = 'Scholarship Application';
            }
        }

        function goToStep(step) {
            document.querySelectorAll('.step-content').forEach(el => el.classList.remove('active'));
            document.getElementById(`step${step}`).classList.add('active');

            document.querySelectorAll('.step').forEach(el => {
                const s = parseInt(el.dataset.step);
                el.classList.remove('active', 'completed');
                if (s < step) el.classList.add('completed');
                if (s === step) el.classList.add('active');
            });

            currentStep = step;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function nextStep(step) {
            const form = document.getElementById('applicationForm');
            const currentContent = document.getElementById(`step${currentStep}`);
            const inputs = currentContent.querySelectorAll('input[required], select[required], textarea[required]');
            let valid = true;

            inputs.forEach(input => {
                if (!input.value.trim()) {
                    input.style.borderColor = '#c62828';
                    valid = false;
                } else {
                    input.style.borderColor = '';
                }
            });

            if (!valid) {
                alert('Please fill in all required fields.');
                return;
            }

            if (step === 4) populateReview();
            goToStep(step);
        }

        function prevStep(step) {
            goToStep(step);
        }

        function populateReview() {
            const form = document.getElementById('applicationForm');
            const fields = [
                'full_name','gender','dob','email','phone','address','state','country',
                'institution','faculty','department','course','level','cgpa'
            ];
            fields.forEach(f => {
                const el = document.getElementById(`rev_${f}`);
                if (el) {
                    const input = form.querySelector(`[name="${f}"]`);
                    el.textContent = input ? (input.value || '-') : '-';
                }
            });

            const fileFields = ['passport_photo','admission_letter','transcript','id_card','recommendation_letter'];
            fileFields.forEach(f => {
                const el = document.getElementById(`rev_${f}`);
                const input = document.getElementById(f);
                if (el && input) {
                    el.textContent = input.files.length > 0 ? input.files[0].name : 'Not uploaded';
                }
            });

            const statement = form.querySelector('[name="personal_statement"]');
            const revStatement = document.getElementById('rev_personal_statement');
            if (revStatement && statement) {
                revStatement.textContent = statement.value ? statement.value.substring(0, 200) + (statement.value.length > 200 ? '...' : '') : '-';
            }
        }

        function triggerUpload(fieldId) {
            document.getElementById(fieldId).click();
        }

        function handleFile(input, fieldId) {
            const area = document.getElementById(`${fieldId}_area`);
            const nameEl = document.getElementById(`${fieldId}_name`);
            if (input.files.length > 0) {
                area.classList.add('has-file');
                nameEl.textContent = input.files[0].name;
                nameEl.style.color = '#2e7d32';
            } else {
                area.classList.remove('has-file');
            }
        }

        document.getElementById('applicationForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            if (!document.getElementById('agreeTerms').checked) {
                alert('Please agree to the terms and conditions.');
                return;
            }

            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            document.getElementById('loadingOverlay').style.display = 'flex';

            const formData = new FormData(this);

            try {
                const response = await fetch(`${API_BASE}?action=submit_application`, {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    document.getElementById('applicationForm').style.display = 'none';
                    document.querySelector('.progress-container').style.display = 'none';
                    document.getElementById('successState').style.display = 'block';
                    document.getElementById('appCode').textContent = result.application_code || result.code || 'N/A';
                } else {
                    alert(result.message || 'Submission failed. Please try again.');
                    submitBtn.disabled = false;
                }
            } catch (error) {
                console.error('Error submitting:', error);
                alert('Network error. Please check your connection and try again.');
                submitBtn.disabled = false;
            } finally {
                document.getElementById('loadingOverlay').style.display = 'none';
            }
        });

        document.addEventListener('DOMContentLoaded', loadScholarshipTitle);
    </script>
</body>
</html>
