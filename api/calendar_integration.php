<?php
/**
 * API: Calendar Integration
 * Creates Google Calendar events from chat
 */
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/config.php';

if (!isset($_SESSION['user']['id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']); exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$userId = $_SESSION['user']['id'];

if ($action === 'create_event') {
    $title = trim($_POST['title'] ?? '');
    $date = trim($_POST['date'] ?? '');
    $time = trim($_POST['time'] ?? '09:00');
    $description = trim($_POST['description'] ?? '');
    $duration = (int)($_POST['duration'] ?? 60);

    if (empty($title) || empty($date)) {
        echo json_encode(['success' => false, 'error' => 'Title and date required']); exit;
    }

    $startDateTime = $date . 'T' . $time . ':00';
    $endDateTime = date('Y-m-d\TH:i:s', strtotime("$startDateTime + $duration minutes"));

    // Generate Google Calendar URL
    $gcalUrl = 'https://calendar.google.com/calendar/render?action=TEMPLATE'
        . '&text=' . urlencode($title)
        . '&dates=' . str_replace(['-',':'], '', $startDateTime) . '/' . str_replace(['-',':'], '', $endDateTime)
        . '&details=' . urlencode($description)
        . '&location=' . urlencode('Wise Quotient Soft - Virtual Meeting');

    // Generate iCal data
    $ical = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//WQS//Bot//EN\r\n";
    $ical .= "BEGIN:VEVENT\r\n";
    $ical .= "DTSTART:" . str_replace(['-','T',':'], '', $startDateTime) . "Z\r\n";
    $ical .= "DTEND:" . str_replace(['-','T',':'], '', $endDateTime) . "Z\r\n";
    $ical .= "SUMMARY:" . $title . "\r\n";
    $ical .= "DESCRIPTION:" . $description . "\r\n";
    $ical .= "LOCATION:Wise Quotient Soft - Virtual Meeting\r\n";
    $ical .= "END:VEVENT\r\nEND:VCALENDAR";

    $icalPath = __DIR__ . '/../temp/event_' . $userId . '_' . time() . '.ics';
    @mkdir(__DIR__ . '/../temp', 0755, true);
    @file_put_contents($icalPath, $ical);

    echo json_encode([
        'success' => true,
        'gcal_url' => $gcalUrl,
        'ical_download' => 'api/calendar_integration.php?action=download_ical&file=' . basename($icalPath),
        'event' => [
            'title' => $title,
            'start' => $startDateTime,
            'end' => $endDateTime,
            'description' => $description
        ]
    ]);
} elseif ($action === 'download_ical') {
    $file = basename($_GET['file'] ?? '');
    $path = __DIR__ . '/../temp/' . $file;
    if ($file && file_exists($path) && preg_match('/\.ics$/', $file)) {
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        readfile($path);
        @unlink($path);
        exit;
    }
    http_response_code(404);
    echo 'File not found';
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
