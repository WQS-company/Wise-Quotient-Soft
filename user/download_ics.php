<?php
require_once dirname(__DIR__) . '/config.php';

// Auth check
if (!isset($_SESSION['user']['id'])) {
    die("Access denied. Please log in.");
}

$userId = $_SESSION['user']['id'];
$bookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

if (!$bookingId) {
    die("Invalid booking ID.");
}

try {
    // Fetch booking
    $stmt = $pdo->prepare("SELECT mb.*, u.name as client_name, u.email as client_email FROM meeting_bookings mb JOIN users u ON mb.user_id = u.id WHERE mb.id = ?");
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        die("Booking not found.");
    }

    // Admins, agents (if referred client), or the user themselves can download
    $userRole = $_SESSION['user']['role'] ?? 'user';
    if ($userRole !== 'admin' && $booking['user_id'] !== $userId) {
        // If agent, check if they referred the client
        if ($userRole === 'agent') {
            $refChk = $pdo->prepare("SELECT referred_by FROM users WHERE id = ?");
            $refChk->execute([$booking['user_id']]);
            $referredBy = $refChk->fetchColumn();
            if ($referredBy != $userId) {
                die("Access denied.");
            }
        } else {
            die("Access denied.");
        }
    }

    $meetingTypes = [
        'consultation' => 'Strategy Consultation',
        'project_review' => 'Project Review',
        'demo' => 'Platform Demo',
        'onboarding' => 'Onboarding Session',
    ];
    $durations = [
        'consultation' => 30,
        'project_review' => 45,
        'demo' => 20,
        'onboarding' => 60,
    ];

    $type = $booking['meeting_type'];
    $summary = $meetingTypes[$type] ?? 'WQS Meeting';
    $duration = $durations[$type] ?? 30;

    $dateTimeStr = $booking['preferred_date'] . ' ' . $booking['preferred_time'];
    $tz = $booking['timezone'] ?? 'Africa/Lagos';

    $tzObj = new DateTimeZone($tz);
    $dt = new DateTime($dateTimeStr, $tzObj);
    
    $startUtc = clone $dt;
    $startUtc->setTimezone(new DateTimeZone('UTC'));
    $startDateStr = $startUtc->format('Ymd\THis\Z');

    $endUtc = clone $startUtc;
    $endUtc->add(new DateInterval("PT{$duration}M"));
    $endDateStr = $endUtc->format('Ymd\THis\Z');

    $stampStr = gmdate('Ymd\THis\Z');
    $uid = "booking_" . $booking['id'] . "_" . strtotime($booking['created_at']) . "@wisequotientsoft.com";
    $notes = str_replace(["\r\n", "\r", "\n"], "\\n", $booking['notes'] ?? '');
    $location = $booking['meeting_link'] ?: "Online Meeting";

    // Set headers
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="wqs_booking_' . $booking['id'] . '.ics"');

    // Output ICS format
    echo "BEGIN:VCALENDAR\r\n";
    echo "VERSION:2.0\r\n";
    echo "PRODID:-//WiseQuotient Soft//Meeting Booking//EN\r\n";
    echo "CALSCALE:GREGORIAN\r\n";
    echo "METHOD:REQUEST\r\n";
    echo "BEGIN:VEVENT\r\n";
    echo "UID:" . $uid . "\r\n";
    echo "DTSTAMP:" . $stampStr . "\r\n";
    echo "DTSTART:" . $startDateStr . "\r\n";
    echo "DTEND:" . $endDateStr . "\r\n";
    echo "SUMMARY:WQS - " . $summary . "\r\n";
    echo "DESCRIPTION:Client: " . $booking['client_name'] . " (" . $booking['client_email'] . ")\\nNotes: " . $notes . "\r\n";
    echo "LOCATION:" . $location . "\r\n";
    echo "STATUS:CONFIRMED\r\n";
    echo "END:VEVENT\r\n";
    echo "END:VCALENDAR\r\n";
    exit;

} catch (Exception $e) {
    die("Error generating calendar file: " . $e->getMessage());
}
