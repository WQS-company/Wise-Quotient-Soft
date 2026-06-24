<?php
session_start();
if (!isset($_SESSION['user']['id'])) {
    echo "<div class='alert alert-danger'>Session expired. Please login again.</div>";
    exit;
}

require_once dirname(__DIR__) . '/config.php';

$user_id = $_SESSION['user']['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $desc = $_POST['description'];
    
    // Compile client info and booking details
    $company = isset($_POST['company_name']) ? trim($_POST['company_name']) : '';
    $contact = isset($_POST['contact_person']) ? trim($_POST['contact_person']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';
    $start_date = isset($_POST['start_date']) ? trim($_POST['start_date']) : '';
    $completion_date = isset($_POST['completion_date']) ? trim($_POST['completion_date']) : '';
    $budget = isset($_POST['budget']) ? trim($_POST['budget']) : '';
    $currency = isset($_POST['currency']) ? trim($_POST['currency']) : '';
    $deployment = isset($_POST['deployment']) ? trim($_POST['deployment']) : '';
    $client_signature = isset($_POST['client_signature']) ? trim($_POST['client_signature']) : '';
    $client_date = isset($_POST['client_date']) ? trim($_POST['client_date']) : '';

    if (!empty($company) || !empty($contact)) {
        $compiled_desc = "PROJECT DETAILS & DESCRIPTION:\n" . $desc . "\n\n" .
                         "--- CLIENT INFORMATION ---\n" .
                         "Company/Client Name: " . $company . "\n" .
                         "Contact Person: " . $contact . "\n" .
                         "Phone Number: " . $phone . "\n" .
                         "Address: " . $address . "\n\n" .
                         "--- TIMELINE & BUDGET ---\n" .
                         "Preferred Start Date: " . $start_date . "\n" .
                         "Expected Completion Date: " . $completion_date . "\n" .
                         "Estimated Budget: " . $budget . " (" . $currency . ")\n" .
                         "Deployment Preference: " . $deployment . "\n\n" .
                         "--- SIGNATURES & COMPLIANCE ---\n" .
                         "Client Signature: " . $client_signature . "\n" .
                         "Signature Date: " . $client_date;
    } else {
        $compiled_desc = $desc;
    }

    $description = $compiled_desc;
    $category = $_POST['category'];
    $software_type = isset($_POST['software_type']) ? $_POST['software_type'] : '';
    $features = isset($_POST['features']) ? implode(",", $_POST['features']) : '';
    $recommendations = isset($_POST['recommendations']) ? $_POST['recommendations'] : '';

    $query = $pdo->prepare("INSERT INTO client_requests (user_id, title, description, categories, software_type, features, recommendations) 
              VALUES (?, ?, ?, ?, ?, ?, ?)");

    if ($query->execute([$user_id, $title, $description, $category, $software_type, $features, $recommendations])) {
        $request_id = $pdo->lastInsertId();

        // Handle multiple file uploads
        if (!empty($_FILES['files']['name'][0])) {
            $uploadDir = "uploads/projects/";
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            require_once dirname(__DIR__) . '/includes/cloudinary.php';
            foreach ($_FILES['files']['name'] as $key => $filename) {
                $tmpName = $_FILES['files']['tmp_name'][$key];
                if (!is_uploaded_file($tmpName)) continue;
                $fileType = mime_content_type($tmpName);
                $type = (strpos($fileType, 'video') !== false) ? 'video' : ((strpos($fileType, 'image') !== false) ? 'image' : 'raw');
                
                $cloudUrl = uploadToCloudinary($tmpName, 'project_requests', $type === 'image' || $type === 'video' ? $type : 'auto');

                if ($cloudUrl) {
                    $stmt = $pdo->prepare("INSERT INTO client_request_files (request_id, file_path, file_type) VALUES (?, ?, ?)");
                    $stmt->execute([$request_id, $cloudUrl, $type]);
                }
            }
        }
        
        // Send notification to Client
        add_notification($user_id, "Project Request Submitted", "Your project request '{$title}' has been successfully submitted and is pending review.", 'project', '../user/my_requests.php', $request_id);

        // Send notification to Administrators
        add_notification_to_admins("New Client Project Request", "A new project request '{$title}' has been submitted by user ID {$user_id} and is pending review.", 'project', '../admin/client_requests.php');

        echo "<div class='alert alert-success text-center'>✅ Project request and booking form submitted successfully!</div>";
    } else {
        echo "<div class='alert alert-danger text-center'>❌ Failed to submit request. Please try again.</div>";
    }
}
?>
