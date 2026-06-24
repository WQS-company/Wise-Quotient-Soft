<?php
$path_to_root = "../";
$page_title = "Book a Meeting";
require_once dirname(__DIR__) . '/includes/dashboard_header.php';
$userId = $headerUser['id'];

// AJAX
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $act = $_POST['ajax_action'];

    if ($act === 'book_meeting') {
        $type  = in_array($_POST['meeting_type']??'',['consultation','project_review','demo','onboarding']) ? $_POST['meeting_type'] : 'consultation';
        $date  = trim($_POST['preferred_date']??'');
        $time  = trim($_POST['preferred_time']??'');
        $tz    = trim($_POST['timezone']??'Africa/Lagos');
        $notes = trim($_POST['notes']??'');
        
        $targetUserId = $userId;
        $bookedByUserId = null;
        $behalfUserId = isset($_POST['behalf_user_id']) ? (int)$_POST['behalf_user_id'] : 0;
        
        $userRole = $headerUser['role'] ?? 'user';
        if ($behalfUserId > 0 && $behalfUserId !== $userId) {
            if ($userRole === 'admin') {
                $targetUserId = $behalfUserId;
                $bookedByUserId = $userId;
            } elseif ($userRole === 'agent') {
                // Verify the agent referred the client
                $chkRef = $pdo->prepare("SELECT referred_by FROM users WHERE id = ?");
                $chkRef->execute([$behalfUserId]);
                $referredBy = $chkRef->fetchColumn();
                if ($referredBy == $userId) {
                    $targetUserId = $behalfUserId;
                    $bookedByUserId = $userId;
                } else {
                    echo json_encode(['success' => false, 'message' => 'Access denied. You can only book on behalf of your referred clients.']);
                    exit;
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Unauthorized operation. Only agents or admins can book on behalf of clients.']);
                exit;
            }
        }
        
        if (!$date||!$time) { echo json_encode(['success'=>false,'message'=>'Date and time required.']); exit; }
        if (strtotime($date) < strtotime(date('Y-m-d'))) { echo json_encode(['success'=>false,'message'=>'Please choose a future date.']); exit; }
        
        try {
            // Get target user details
            $tUserStmt = $pdo->prepare("SELECT name, email, phone FROM users WHERE id = ?");
            $tUserStmt->execute([$targetUserId]);
            $tUser = $tUserStmt->fetch(PDO::FETCH_ASSOC);
            $clientName = $tUser['name'] ?? 'Client';
            $clientEmail = $tUser['email'] ?? '';
            
            // Append booking status notes
            if ($bookedByUserId !== null) {
                $notes = trim($notes . "\n[Booked on behalf of client by " . ucfirst($userRole) . ": " . $headerUser['name'] . "]");
            }
            
            $pdo->prepare("INSERT INTO meeting_bookings (user_id,booked_by_user_id,meeting_type,preferred_date,preferred_time,timezone,notes) VALUES (?,?,?,?,?,?,?)")
                ->execute([$targetUserId,$bookedByUserId,$type,$date,$time,$tz,$notes]);
            $bookingId = $pdo->lastInsertId();
            
            // Notify admins
            $admins = $pdo->query("SELECT id, name, email, phone FROM users WHERE role='admin'")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($admins as $adm) {
                $msg = $clientName . " booked a $type meeting for $date at $time ($tz).";
                if ($bookedByUserId !== null) {
                    $msg = $headerUser['name'] . " booked a $type meeting on behalf of " . $clientName . " for $date at $time ($tz).";
                }
                add_notification($adm['id'], "New Meeting Booking", $msg, 'meeting', '../admin/dashboard.php');
                
                // SMTP Email Notification featuring clinic/company branding
                $meetingTypeLabel = [
                    'consultation' => 'Strategy Consultation',
                    'project_review' => 'Project Review',
                    'demo' => 'Platform Demo',
                    'onboarding' => 'Onboarding Session',
                ][$type] ?? 'Meeting';

                $emailSubject = "New WQS Meeting Scheduled: " . $meetingTypeLabel;
                $emailBody = "
                    <div style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; max-width: 600px; margin: auto; border: 1px solid #e2e8f0; border-radius: 16px; padding: 32px; background: #ffffff; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);'>
                        <div style='text-align: center; margin-bottom: 24px; border-bottom: 2px solid #f1f5f9; padding-bottom: 20px;'>
                            <h2 style='color: #0A2D5E; margin: 0; font-size: 1.6rem; font-weight: 800; letter-spacing: -0.02em;'>WiseQuotient Soft</h2>
                            <p style='color: #E15501; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; margin: 4px 0 0;'>Premium Meeting Alert</p>
                        </div>
                        
                        <div style='margin-bottom: 24px;'>
                            <p style='color: #334155; font-size: 0.95rem; line-height: 1.6; margin: 0 0 16px;'>Hello Administrator,</p>
                            <p style='color: #334155; font-size: 0.95rem; line-height: 1.6; margin: 0;'>A new project consultation meeting has been scheduled on the platform. Please find the appointment details below:</p>
                        </div>

                        <div style='background: #f8fafc; border-radius: 12px; border: 1px solid #f1f5f9; padding: 20px; margin-bottom: 24px;'>
                            <table style='width: 100%; border-collapse: collapse;'>
                                <tr>
                                    <td style='padding: 8px 0; color: #64748b; font-size: 0.85rem; font-weight: 600; width: 35%;'>Meeting Type</td>
                                    <td style='padding: 8px 0; color: #0A2D5E; font-size: 0.9rem; font-weight: 700;'>" . htmlspecialchars($meetingTypeLabel) . "</td>
                                </tr>
                                <tr>
                                    <td style='padding: 8px 0; color: #64748b; font-size: 0.85rem; font-weight: 600;'>Date & Time</td>
                                    <td style='padding: 8px 0; color: #0f172a; font-size: 0.9rem; font-weight: 600;'>" . date('l, F j, Y', strtotime($date)) . " @ " . htmlspecialchars($time) . "</td>
                                </tr>
                                <tr>
                                    <td style='padding: 8px 0; color: #64748b; font-size: 0.85rem; font-weight: 600;'>Timezone</td>
                                    <td style='padding: 8px 0; color: #0f172a; font-size: 0.9rem;'>" . htmlspecialchars($tz) . "</td>
                                </tr>
                                <tr>
                                    <td style='padding: 8px 0; color: #64748b; font-size: 0.85rem; font-weight: 600;'>Client Name</td>
                                    <td style='padding: 8px 0; color: #0f172a; font-size: 0.9rem; font-weight: 600;'>" . htmlspecialchars($clientName) . "</td>
                                </tr>
                            </table>
                        </div>
                        
                        " . ($bookedByUserId !== null ? "
                        <div style='background: #eff6ff; border-radius: 8px; padding: 12px; margin-bottom: 24px; border: 1px solid #bfdbfe; font-size: 0.85rem; color: #1e3a8a;'>
                            <strong>Booked on behalf of the client by:</strong> " . htmlspecialchars($headerUser['name']) . " (" . ucfirst($userRole) . ")
                        </div>
                        " : "") . "

                        " . (!empty($notes) ? "
                        <div style='margin-bottom: 28px;'>
                            <h4 style='color: #0A2D5E; margin: 0 0 8px; font-size: 0.9rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;'>Additional Notes</h4>
                            <div style='background: #fdfefe; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; color: #475569; font-size: 0.88rem; line-height: 1.5; white-space: pre-wrap;'>" . htmlspecialchars($notes) . "</div>
                        </div>
                        " : "") . "

                        <div style='text-align: center; margin-top: 32px;'>
                            <a href='http://localhost/dashboard/wqs/admin/dashboard.php' style='background: linear-gradient(135deg, #0A2D5E 0%, #163f7a 100%); color: #ffffff; text-decoration: none; padding: 12px 32px; border-radius: 50px; font-size: 0.9rem; font-weight: 700; display: inline-block; box-shadow: 0 4px 10px rgba(10,45,94,0.25);'>Manage Bookings</a>
                        </div>

                        <hr style='border: 0; border-top: 1px solid #f1f5f9; margin: 32px 0 20px;'>
                        <div style='text-align: center; color: #94a3b8; font-size: 0.78rem;'>
                            This is an automated notification from WiseQuotient Soft.<br>
                            &copy; " . date('Y') . " WiseQuotient Soft. All rights reserved.
                        </div>
                    </div>
                ";
                send_smtp_email($adm['email'], $emailSubject, $emailBody, $pdo);
                
                // Termii SMS Notification
                if (!empty($adm['phone']) && $adm['phone'] !== 'N/A') {
                    $smsText = "WQS Alert: New " . $meetingTypeLabel . " booked for " . $date . " at " . $time . ". Client: " . $clientName;
                    if ($bookedByUserId !== null) {
                        $smsText = "WQS Alert: " . $headerUser['name'] . " booked " . $meetingTypeLabel . " on behalf of " . $clientName . " for " . $date . " at " . $time;
                    }
                    send_termii_sms($adm['phone'], $smsText, $pdo);
                }
            }
            
            // Notify client
            add_notification($targetUserId, "Meeting Booked!", "Your $type meeting request for $date at $time is confirmed pending admin approval.", 'meeting', '../user/book_meeting.php');
            echo json_encode(['success'=>true]);
        } catch (Exception $ex) { echo json_encode(['success'=>false,'message'=>$ex->getMessage()]); }
        exit;
    }

    if ($act === 'cancel_booking') {
        $bid = (int)$_POST['booking_id'];
        try {
            $pdo->prepare("UPDATE meeting_bookings SET status='cancelled' WHERE id=? AND user_id=?")->execute([$bid,$userId]);
            echo json_encode(['success'=>true]);
        } catch (Exception $ex) { echo json_encode(['success'=>false,'message'=>$ex->getMessage()]); }
        exit;
    }

    echo json_encode(['success'=>false,'message'=>'Unknown action.']); exit;
}

// Fetch behalf clients
$behalfClients = [];
$userRole = $headerUser['role'] ?? 'user';
if ($userRole === 'admin') {
    try {
        $cStmt = $pdo->prepare("SELECT id, name, email FROM users WHERE role != 'admin' ORDER BY name ASC");
        $cStmt->execute();
        $behalfClients = $cStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
} elseif ($userRole === 'agent') {
    try {
        $cStmt = $pdo->prepare("SELECT id, name, email FROM users WHERE referred_by = ? ORDER BY name ASC");
        $cStmt->execute([$userId]);
        $behalfClients = $cStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// Fetch bookings
try {
    if ($userRole === 'admin') {
        $bookings = $pdo->prepare("SELECT mb.*, u.name as client_name FROM meeting_bookings mb JOIN users u ON mb.user_id = u.id ORDER BY mb.preferred_date DESC, mb.created_at DESC");
        $bookings->execute();
    } elseif ($userRole === 'agent') {
        $bookings = $pdo->prepare("SELECT mb.*, u.name as client_name FROM meeting_bookings mb JOIN users u ON mb.user_id = u.id WHERE mb.user_id = ? OR mb.booked_by_user_id = ? ORDER BY mb.preferred_date DESC, mb.created_at DESC");
        $bookings->execute([$userId, $userId]);
    } else {
        $bookings = $pdo->prepare("SELECT mb.*, u.name as client_name FROM meeting_bookings mb JOIN users u ON mb.user_id = u.id WHERE mb.user_id = ? ORDER BY mb.preferred_date DESC, mb.created_at DESC");
        $bookings->execute([$userId]);
    }
    $bookings = $bookings->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $bookings=[]; }

$meetingTypes = [
    'consultation' => ['💼 Strategy Consultation','Discuss your project requirements, goals and approach.',30],
    'project_review' => ['📊 Project Review','Review ongoing project progress, timelines and deliverables.',45],
    'demo' => ['🖥️ Platform Demo','A guided walkthrough of the WQS platform and features.',20],
    'onboarding' => ['🚀 Onboarding Session','Get started with the platform, meet your team, and set expectations.',60],
];

$timeSlots = ['09:00','09:30','10:00','10:30','11:00','11:30','12:00','13:00','13:30','14:00','14:30','15:00','15:30','16:00','16:30','17:00'];

$statusColors = [
    'pending'  => ['#fef3c7','#92400e','⏳'],
    'confirmed'=> ['#dcfce7','#15803d','✅'],
    'cancelled'=> ['#fef2f2','#dc2626','❌'],
    'completed'=> ['#e0e7ff','#3730a3','☑️'],
];
?>

<style>
.meeting-hero { background:linear-gradient(135deg,#0f2857,#1a3f80); border-radius:20px; padding:1.75rem 2rem; color:white; position:relative; overflow:hidden; margin-bottom:1.75rem; }
.meeting-hero::before { content:''; position:absolute; top:-60px; right:-60px; width:220px; height:220px; background:rgba(225,85,1,0.15); border-radius:50%; }
.meeting-type-card { border-radius:16px; border:2px solid #e2e8f0; padding:1.25rem; cursor:pointer; transition:all 0.2s; text-align:center; background:white; }
.meeting-type-card:hover { border-color:#0A2D5E; background:#f0f7ff; transform:translateY(-2px); }
.meeting-type-card.selected { border-color:#0A2D5E; background:rgba(10,45,94,0.06); box-shadow:0 0 0 3px rgba(10,45,94,0.12); }
.time-slot { padding:0.45rem 0.8rem; border:1.5px solid #e2e8f0; border-radius:8px; cursor:pointer; font-size:0.82rem; font-weight:600; color:#64748b; transition:all 0.15s; text-align:center; }
.time-slot:hover { border-color:#0A2D5E; color:#0A2D5E; background:#eff6ff; }
.time-slot.selected { background:#0A2D5E; color:white; border-color:#0A2D5E; }
.booking-card { background:white; border-radius:14px; border:1.5px solid rgba(0,0,0,0.06); box-shadow:0 4px 14px rgba(0,0,0,0.04); padding:1.25rem; margin-bottom:0.85rem; }
</style>

<!-- Hero -->
<div class="meeting-hero">
    <div style="position:relative;z-index:1;" class="d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2">
                <span style="background:rgba(225,85,1,0.25);color:#ffb380;border:1px solid rgba(225,85,1,0.4);padding:0.2rem 0.7rem;border-radius:50px;font-size:0.72rem;font-weight:700;text-transform:uppercase;"><i class="fas fa-calendar-alt me-1"></i>Scheduling</span>
            </div>
            <h1 style="font-size:1.5rem;font-weight:800;color:white;margin-bottom:0.3rem;">Book a Meeting</h1>
            <p style="color:rgba(255,255,255,0.6);font-size:0.85rem;margin:0;">Schedule a call with our team — strategy sessions, project reviews, demos, and more.</p>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Booking Form -->
    <div class="col-lg-7">
        <div style="background:white;border-radius:20px;border:1.5px solid rgba(0,0,0,0.06);box-shadow:0 4px 20px rgba(0,0,0,0.04);padding:2rem;">
            <h5 class="fw-bold text-body mb-4"><i class="fas fa-calendar-plus me-2 text-primary"></i>Schedule New Meeting</h5>

            <!-- Dropdown for book-on-behalf -->
            <?php if (!empty($behalfClients)): ?>
            <div class="mb-4">
                <label class="form-label fw-bold text-muted" style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.05em;">Book on Behalf of Client</label>
                <select id="behalf_user_id" class="form-select" style="border-radius:10px;border-color:#e2e8f0;padding:0.6rem 1rem;font-weight:600;color:#0A2D5E;background-color:#f8fafc;">
                    <option value="">-- Book for Yourself (<?= htmlspecialchars($headerUser['name']) ?>) --</option>
                    <?php foreach ($behalfClients as $client): ?>
                        <option value="<?= $client['id'] ?>"><?= htmlspecialchars($client['name']) ?> (<?= htmlspecialchars($client['email']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <!-- Meeting Type -->
            <div class="mb-4">
                <label class="form-label fw-bold text-muted" style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.05em;">Meeting Type</label>
                <div class="row g-3">
                    <?php foreach ($meetingTypes as $key => [$label, $desc, $duration]): ?>
                    <div class="col-6">
                        <div class="meeting-type-card<?= $key==='consultation'?' selected':'' ?>" onclick="selectMeetingType('<?=$key?>', this)">
                            <div style="font-size:1.5rem;margin-bottom:0.5rem;"><?= substr($label,0,2) ?></div>
                            <div class="fw-bold" style="font-size:0.82rem;color:#0A2D5E;"><?= htmlspecialchars(substr($label,2)) ?></div>
                            <div class="text-muted" style="font-size:0.7rem;"><?= $duration ?> min</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" id="selected_type" value="consultation">
                <div class="mt-2 p-3 rounded-3" style="background:var(--color-bg);border:1px solid #e2e8f0;" id="type_desc">
                    <div class="text-muted" style="font-size:0.82rem;"><?= $meetingTypes['consultation'][1] ?></div>
                </div>
            </div>

            <!-- Date & Time -->
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-bold text-muted" style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.05em;">Preferred Date</label>
                    <input type="date" id="meet_date" class="form-control" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" style="border-radius:10px;border-color:#e2e8f0;">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold text-muted" style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.05em;">Timezone</label>
                    <select id="meet_tz" class="form-select" style="border-radius:10px;border-color:#e2e8f0;">
                        <option value="Africa/Lagos">🇳🇬 WAT (Lagos, UTC+1)</option>
                        <option value="UTC">🌐 UTC</option>
                        <option value="Europe/London">🇬🇧 GMT (London)</option>
                        <option value="America/New_York">🇺🇸 EST (New York)</option>
                        <option value="America/Los_Angeles">🇺🇸 PST (LA)</option>
                    </select>
                </div>
            </div>

            <!-- Time Slots -->
            <div class="mb-4">
                <label class="form-label fw-bold text-muted" style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.05em;">Time Slot</label>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(80px,1fr));gap:0.5rem;" id="time_slots_grid">
                    <?php foreach ($timeSlots as $slot): ?>
                    <div class="time-slot" onclick="selectTimeSlot('<?=$slot?>',this)"><?=$slot?></div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" id="selected_time" value="">
            </div>

            <!-- Notes -->
            <div class="mb-4">
                <label class="form-label fw-bold text-muted" style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.05em;">Additional Notes (optional)</label>
                <textarea id="meet_notes" class="form-control" rows="3" placeholder="Briefly describe what you'd like to discuss or any specific questions..." style="border-radius:10px;border-color:#e2e8f0;"></textarea>
            </div>

            <button class="btn w-100 py-3 fw-bold rounded-pill" style="background:linear-gradient(135deg,#0A2D5E,#163f7a);border:none;color:white;font-size:1rem;" onclick="bookMeeting()">
                <i class="fas fa-calendar-check me-2"></i>Confirm Booking
            </button>
            <p class="text-center text-muted mt-3 mb-0" style="font-size:0.78rem;"><i class="fas fa-info-circle me-1"></i>You'll receive a confirmation notification once admin approves your booking.</p>
        </div>
    </div>

    <!-- My Bookings -->
    <div class="col-lg-5">
        <div style="background:white;border-radius:20px;border:1.5px solid rgba(0,0,0,0.06);box-shadow:0 4px 20px rgba(0,0,0,0.04);padding:1.5rem;">
            <h5 class="fw-bold text-body mb-3"><i class="fas fa-history me-2 text-muted"></i>My Bookings</h5>
            <?php if (empty($bookings)): ?>
            <div class="text-center py-4 text-muted">
                <i class="fas fa-calendar d-block mb-3 text-secondary" style="font-size:2.5rem;"></i>
                <p class="small">No meetings booked yet.</p>
            </div>
            <?php else: ?>
            <?php foreach ($bookings as $b):
                [$stBg,$stCl,$stIc] = $statusColors[$b['status']??'pending'];
                $mtLabel = substr($meetingTypes[$b['meeting_type']][0]??'Meeting',2);
            ?>
            <div class="booking-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="fw-bold text-body" style="font-size:0.9rem;"><?= htmlspecialchars($mtLabel) ?></div>
                        <?php if ($b['user_id'] != $userId): ?>
                            <div style="font-size: 0.75rem; color: #0284c7; font-weight: 600; margin-bottom: 2px;">
                                <i class="fas fa-user-friends me-1"></i>For: <?= htmlspecialchars($b['client_name']) ?>
                            </div>
                        <?php endif; ?>
                        <div class="text-muted" style="font-size:0.78rem;"><i class="fas fa-calendar me-1"></i><?= date('D, M d Y', strtotime($b['preferred_date'])) ?></div>
                        <div class="text-muted" style="font-size:0.78rem;"><i class="fas fa-clock me-1"></i><?= htmlspecialchars($b['preferred_time']) ?> · <?= htmlspecialchars($b['timezone']) ?></div>
                        <?php if ($b['meeting_link']): ?><a href="<?= htmlspecialchars($b['meeting_link']) ?>" target="_blank" class="btn btn-sm btn-link p-0 mt-1" style="font-size:0.75rem;display:block;"><i class="fas fa-video me-1"></i>Join Meeting</a><?php endif; ?>

                        <?php if ($b['status'] === 'confirmed'): ?>
                            <div class="d-flex gap-2 mt-2">
                                <?php
                                $gType = $b['meeting_type'];
                                $gSummary = $meetingTypes[$gType][0] ? substr($meetingTypes[$gType][0], 2) : 'WQS Meeting';
                                $gDuration = $meetingTypes[$gType][2] ?? 30;
                                
                                $gDateTimeStr = $b['preferred_date'] . ' ' . $b['preferred_time'];
                                $gTz = $b['timezone'] ?? 'Africa/Lagos';
                                
                                try {
                                    $gDt = new DateTime($gDateTimeStr, new DateTimeZone($gTz));
                                    $gStartUtc = clone $gDt;
                                    $gStartUtc->setTimezone(new DateTimeZone('UTC'));
                                    $gStartStr = $gStartUtc->format('Ymd\THis\Z');
                                    
                                    $gEndUtc = clone $gStartUtc;
                                    $gEndUtc->add(new DateInterval("PT{$gDuration}M"));
                                    $gEndStr = $gEndUtc->format('Ymd\THis\Z');
                                } catch (Exception $e) {
                                    $gStartStr = '';
                                    $gEndStr = '';
                                }
                                
                                $gDetails = "WQS " . $gSummary . " - Client: " . ($b['client_name'] ?? '');
                                $gLocation = $b['meeting_link'] ?: "Online Meeting";
                                
                                $gCalUrl = "https://www.google.com/calendar/render?action=TEMPLATE"
                                    . "&text=" . urlencode("WQS - " . $gSummary)
                                    . "&dates=" . $gStartStr . "/" . $gEndStr
                                    . "&details=" . urlencode($gDetails)
                                    . "&location=" . urlencode($gLocation);
                                ?>
                                <a href="<?= $gCalUrl ?>" target="_blank" class="btn btn-xs btn-outline-success py-0.5 px-2 rounded-2" style="font-size: 0.65rem; font-weight: 600; text-decoration:none;">
                                    <i class="fab fa-google me-1"></i>Google Cal
                                </a>
                                <a href="download_ics.php?booking_id=<?= $b['id'] ?>" class="btn btn-xs btn-outline-primary py-0.5 px-2 rounded-2" style="font-size: 0.65rem; font-weight: 600; text-decoration:none;">
                                    <i class="fas fa-calendar-alt me-1"></i>Outlook / iCal
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="text-end">
                        <span style="background:<?=$stBg?>;color:<?=$stCl?>;padding:0.2rem 0.7rem;border-radius:50px;font-size:0.7rem;font-weight:700;"><?=$stIc?> <?= ucfirst($b['status']) ?></span>
                        <?php if ($b['status']==='pending'): ?>
                        <button class="btn btn-sm btn-link text-danger p-0 d-block ms-auto mt-1" style="font-size:0.72rem;" onclick="cancelBooking(<?=$b['id']?>)">Cancel</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const typeDescs = <?= json_encode(array_map(fn($v)=>$v[1], $meetingTypes)) ?>;

function selectMeetingType(type, el) {
    document.getElementById('selected_type').value = type;
    document.querySelectorAll('.meeting-type-card').forEach(c=>c.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('type_desc').innerHTML = `<div class="text-muted" style="font-size:0.82rem;">${typeDescs[type]||''}</div>`;
}
function selectTimeSlot(time, el) {
    document.getElementById('selected_time').value = time;
    document.querySelectorAll('.time-slot').forEach(s=>s.classList.remove('selected'));
    el.classList.add('selected');
}
function bookMeeting() {
    const type  = document.getElementById('selected_type').value;
    const date  = document.getElementById('meet_date').value;
    const time  = document.getElementById('selected_time').value;
    const tz    = document.getElementById('meet_tz').value;
    const notes = document.getElementById('meet_notes').value.trim();
    const behalfEl = document.getElementById('behalf_user_id');
    const behalfUserId = behalfEl ? behalfEl.value : '';

    if (!date) { Swal.fire({icon:'warning',title:'Required',text:'Please select a date.',confirmButtonColor:'#0A2D5E'}); return; }
    if (!time) { Swal.fire({icon:'warning',title:'Required',text:'Please select a time slot.',confirmButtonColor:'#0A2D5E'}); return; }
    fetch('book_meeting.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`ajax_action=book_meeting&meeting_type=${type}&preferred_date=${date}&preferred_time=${encodeURIComponent(time)}&timezone=${encodeURIComponent(tz)}&notes=${encodeURIComponent(notes)}&behalf_user_id=${behalfUserId}`})
    .then(r=>r.json()).then(d=>{
        if(d.success) Swal.fire({icon:'success',title:'Meeting Booked!',html:'Your meeting request has been submitted.<br>You\'ll be notified once confirmed.',confirmButtonColor:'#0A2D5E',timer:4000}).then(()=>location.reload());
        else Swal.fire({icon:'error',title:'Failed',text:d.message||'Could not book meeting.',confirmButtonColor:'#dc3545'});
    });
}
function cancelBooking(id) {
    Swal.fire({title:'Cancel this meeting?',icon:'question',showCancelButton:true,confirmButtonText:'Yes, Cancel',confirmButtonColor:'#dc2626'})
    .then(r=>{if(!r.isConfirmed)return;
        fetch('book_meeting.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`ajax_action=cancel_booking&booking_id=${id}`})
        .then(r=>r.json()).then(d=>{if(d.success)Swal.fire({icon:'success',title:'Cancelled',confirmButtonColor:'#0A2D5E',timer:2000}).then(()=>location.reload());
        else Swal.fire({icon:'error',title:'Error',text:d.message,confirmButtonColor:'#dc3545'});});
    });
}
</script>

<?php require_once dirname(__DIR__) . '/includes/dashboard_footer.php'; ?>
