<?php
require_once dirname(__DIR__) . '/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$name = trim($_POST['name'] ?? '');
$company = trim($_POST['company'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$service = trim($_POST['service'] ?? '');
$budget = trim($_POST['budget'] ?? '');
$timeline = trim($_POST['timeline'] ?? '');
$message = trim($_POST['message'] ?? '');

if (empty($name) || empty($email) || empty($service) || empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}

try {
    // Generate a unique reference number
    $ref_number = 'WQS-' . date('Y') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));

    $stmt = $pdo->prepare("
        INSERT INTO contact_messages 
        (name, company, email, phone, service, budget, timeline, message, ref_number, status, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'new', NOW())
    ");

    $stmt->execute([
        $name,
        $company,
        $email,
        $phone,
        $service,
        $budget,
        $timeline,
        $message,
        $ref_number
    ]);

    // Send notification to admins
    if (function_exists('add_notification_to_admins')) {
        add_notification_to_admins(
            "New Contact Submission",
            "A new contact form submission has been received from {$name} ({$service}).",
            "message",
            "../admin/contact_messages.php"
        );
    }

    echo json_encode(['success' => true, 'ref_number' => $ref_number]);
} catch (Exception $e) {
    error_log("Contact Form Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while saving your message.']);
}
