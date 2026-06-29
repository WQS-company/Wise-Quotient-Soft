<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

$json_input = json_decode(file_get_contents('php://input'), true) ?? [];
if (!is_array($json_input)) $json_input = [];
$input = array_merge($_POST, $_GET, $json_input);
$action = $input['action'] ?? '';

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function generateCode($prefix, $len = 8) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $code = $prefix;
    for ($i = 0; $i < $len; $i++) $code .= $chars[random_int(0, strlen($chars) - 1)];
    return $code;
}

function uploadFile($file, $dir) {
    $uploadDir = __DIR__ . '/../uploads/' . $dir . '/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $name = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9.-]/', '_', $file['name']);
    $path = $uploadDir . $name;
    if (move_uploaded_file($file['tmp_name'], $path)) {
        return 'uploads/' . $dir . '/' . $name;
    }
    return null;
}

switch ($action) {

    // ─── DASHBOARD STATS ───
    case 'dashboard_stats':
        try {
            $stats = [];
            $stats['total_scholarships'] = $pdo->query("SELECT COUNT(*) FROM scholarships")->fetchColumn();
            $stats['active_scholarships'] = $pdo->query("SELECT COUNT(*) FROM scholarships WHERE is_active=1 AND status='published'")->fetchColumn();
            $stats['closed_scholarships'] = $pdo->query("SELECT COUNT(*) FROM scholarships WHERE status='closed'")->fetchColumn();
            $stats['total_applicants'] = $pdo->query("SELECT COUNT(*) FROM scholarship_applications")->fetchColumn();
            $stats['pending_applications'] = $pdo->query("SELECT COUNT(*) FROM scholarship_applications WHERE status='submitted'")->fetchColumn();
            $stats['approved_applications'] = $pdo->query("SELECT COUNT(*) FROM scholarship_applications WHERE status='approved'")->fetchColumn();
            $stats['rejected_applications'] = $pdo->query("SELECT COUNT(*) FROM scholarship_applications WHERE status='rejected'")->fetchColumn();
            $stats['shortlisted'] = $pdo->query("SELECT COUNT(*) FROM scholarship_applications WHERE status='shortlisted'")->fetchColumn();
            $stats['male_applicants'] = $pdo->query("SELECT COUNT(*) FROM scholarship_applications WHERE gender='male'")->fetchColumn();
            $stats['female_applicants'] = $pdo->query("SELECT COUNT(*) FROM scholarship_applications WHERE gender='female'")->fetchColumn();
            $stats['total_funds'] = $pdo->query("SELECT COALESCE(SUM(award_amount),0) FROM scholarship_awards WHERE payment_status='disbursed'")->fetchColumn();
            $stats['upcoming_interviews'] = $pdo->query("SELECT COUNT(*) FROM application_interviews WHERE interview_date >= CURDATE() AND status='scheduled'")->fetchColumn();

            $recent = $pdo->query("SELECT sa.*, s.title as scholarship_title FROM scholarship_applications sa JOIN scholarships s ON sa.scholarship_id=s.id ORDER BY sa.submitted_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

            $monthly = $pdo->query("SELECT DATE_FORMAT(submitted_at,'%Y-%m') as month, COUNT(*) as count FROM scholarship_applications WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH) GROUP BY month ORDER BY month")->fetchAll(PDO::FETCH_ASSOC);

            $byStatus = $pdo->query("SELECT status, COUNT(*) as count FROM scholarship_applications GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

            jsonResponse(['success' => true, 'stats' => $stats, 'recent' => $recent, 'monthly' => $monthly, 'by_status' => $byStatus]);
        } catch (Exception $e) { jsonResponse(['success' => false, 'error' => $e->getMessage()], 500); }
        break;

    // ─── CREATE SCHOLARSHIP ───
    case 'create_scholarship':
        try {
            $title = $input['title'] ?? '';
            $code = $input['code'] ?: generateCode('SCH');
            if (empty($title)) jsonResponse(['success' => false, 'error' => 'Title is required'], 400);

            $bannerPath = null;
            if (!empty($_FILES['banner']['tmp_name'])) {
                $bannerPath = uploadFile($_FILES['banner'], 'scholarships');
            }

            $stmt = $pdo->prepare("INSERT INTO scholarships (title, code, category_id, sponsor_id, scholarship_type, description, eligibility, benefits, slots, amount, currency, academic_level, country, state, institution, course_restrictions, start_date, closing_date, interview_date, banner, terms, is_active, is_featured, status, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $title, $code, $input['category_id'] ?? null, $input['sponsor_id'] ?? null,
                $input['scholarship_type'] ?? 'fully_funded', $input['description'] ?? '',
                $input['eligibility'] ?? '', $input['benefits'] ?? '',
                $input['slots'] ?? 0, $input['amount'] ?? 0, $input['currency'] ?? 'NGN',
                $input['academic_level'] ?? '', $input['country'] ?? '', $input['state'] ?? '',
                $input['institution'] ?? '', $input['course_restrictions'] ?? '',
                $input['start_date'] ?? null, $input['closing_date'] ?? null,
                $input['interview_date'] ?? null, $bannerPath,
                $input['terms'] ?? '', $input['is_active'] ?? 0, $input['is_featured'] ?? 0,
                $input['status'] ?? 'draft', $_SESSION['user']['id'] ?? null
            ]);
            $schId = $pdo->lastInsertId();

            // Notify users if scholarship is published instantly
            if (($input['status'] ?? 'draft') === 'published' && (int)($input['is_active'] ?? 0) === 1) {
                $fcmTitle = "🎓 New Scholarship Published!";
                $fcmBody = "Apply now: " . $title . ". slots: " . ($input['slots'] ?? 0);
                require_once __DIR__ . '/../includes/fcm_helper.php';
                FCMHelper::sendNotificationToAll($fcmTitle, $fcmBody, ['click_action' => '/dashboard/wqs/scholarship.php?id=' . $schId]);
                $pdo->prepare("UPDATE scholarships SET is_published_notification_sent = 1 WHERE id = ?")->execute([$schId]);
            }

            jsonResponse(['success' => true, 'id' => $schId, 'code' => $code]);
        } catch (Exception $e) { jsonResponse(['success' => false, 'error' => $e->getMessage()], 500); }
        break;

    // ─── UPDATE SCHOLARSHIP ───
    case 'update_scholarship':
        try {
            $id = $input['id'] ?? 0;
            if (!$id) jsonResponse(['success' => false, 'error' => 'ID required'], 400);

            $bannerPath = null;
            if (!empty($_FILES['banner']['tmp_name'])) {
                $bannerPath = uploadFile($_FILES['banner'], 'scholarships');
            }

            // Fetch current state to check status transitions
            $checkNotif = $pdo->prepare("SELECT is_published_notification_sent, title, status, is_active, slots FROM scholarships WHERE id = ?");
            $checkNotif->execute([$id]);
            $sch = $checkNotif->fetch();

            $sql = "UPDATE scholarships SET title=?, category_id=?, sponsor_id=?, scholarship_type=?, description=?, eligibility=?, benefits=?, slots=?, amount=?, currency=?, academic_level=?, country=?, state=?, institution=?, course_restrictions=?, start_date=?, closing_date=?, interview_date=?, terms=?, is_active=?, is_featured=?, status=?";
            $params = [
                $input['title'] ?? '', $input['category_id'] ?? null, $input['sponsor_id'] ?? null,
                $input['scholarship_type'] ?? 'fully_funded', $input['description'] ?? '',
                $input['eligibility'] ?? '', $input['benefits'] ?? '',
                $input['slots'] ?? 0, $input['amount'] ?? 0, $input['currency'] ?? 'NGN',
                $input['academic_level'] ?? '', $input['country'] ?? '', $input['state'] ?? '',
                $input['institution'] ?? '', $input['course_restrictions'] ?? '',
                $input['start_date'] ?? null, $input['closing_date'] ?? null,
                $input['interview_date'] ?? null, $input['terms'] ?? '',
                $input['is_active'] ?? 0, $input['is_featured'] ?? 0, $input['status'] ?? 'draft'
            ];
            if ($bannerPath) { $sql .= ", banner=?"; $params[] = $bannerPath; }
            $sql .= " WHERE id=?";
            $params[] = $id;
            $pdo->prepare($sql)->execute($params);

            // Notify users if scholarship is updated to published from draft/closed
            if ($sch && (int)$sch['is_published_notification_sent'] === 0) {
                $newStatus = $input['status'] ?? $sch['status'];
                $newActive = isset($input['is_active']) ? (int)$input['is_active'] : (int)$sch['is_active'];
                if ($newStatus === 'published' && $newActive === 1) {
                    $schTitle = $input['title'] ?? $sch['title'];
                    $schSlots = isset($input['slots']) ? $input['slots'] : $sch['slots'];
                    $fcmTitle = "🎓 New Scholarship Published!";
                    $fcmBody = "Apply now: " . $schTitle . ". slots: " . $schSlots;
                    require_once __DIR__ . '/../includes/fcm_helper.php';
                    FCMHelper::sendNotificationToAll($fcmTitle, $fcmBody, ['click_action' => '/dashboard/wqs/scholarship.php?id=' . $id]);
                    $pdo->prepare("UPDATE scholarships SET is_published_notification_sent = 1 WHERE id = ?")->execute([$id]);
                }
            }

            jsonResponse(['success' => true]);
        } catch (Exception $e) { jsonResponse(['success' => false, 'error' => $e->getMessage()], 500); }
        break;

    // ─── DELETE SCHOLARSHIP ───
    case 'delete_scholarship':
        try {
            $id = $input['id'] ?? 0;
            $pdo->prepare("DELETE FROM scholarships WHERE id=?")->execute([$id]);
            jsonResponse(['success' => true]);
        } catch (Exception $e) { jsonResponse(['success' => false, 'error' => $e->getMessage()], 500); }
        break;

    // ─── TOGGLE STATUS ───
    case 'toggle_scholarship':
        try {
            $id = $input['id'] ?? 0;
            $field = $input['field'] ?? 'is_active';
            $val = (int)($input['value'] ?? 0);
            $pdo->prepare("UPDATE scholarships SET $field=? WHERE id=?")->execute([$val, $id]);
            jsonResponse(['success' => true]);
        } catch (Exception $e) { jsonResponse(['success' => false, 'error' => $e->getMessage()], 500); }
        break;

    // ─── GET SCHOLARSHIPS ───
    case 'get_scholarships':
        try {
            $where = "1=1";
            $params = [];
            if (!empty($input['status'])) { $where .= " AND status=?"; $params[] = $input['status']; }
            if (!empty($input['category_id'])) { $where .= " AND category_id=?"; $params[] = $input['category_id']; }
            if (!empty($input['search'])) { $where .= " AND (title LIKE ? OR code LIKE ?)"; $params[] = "%{$input['search']}%"; $params[] = "%{$input['search']}%"; }
            $limit = min((int)($input['limit'] ?? 20), 100);
            $offset = (int)($input['offset'] ?? 0);
            $stmt = $pdo->prepare("SELECT s.*, sc.name as category_name, ss.name as sponsor_name FROM scholarships s LEFT JOIN scholarship_categories sc ON s.category_id=sc.id LEFT JOIN scholarship_sponsors ss ON s.sponsor_id=ss.id WHERE $where ORDER BY s.created_at DESC LIMIT $limit OFFSET $offset");
            $stmt->execute($params);
            $scholarships = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $total = $pdo->prepare("SELECT COUNT(*) FROM scholarships s WHERE $where");
            $total->execute($params);
            jsonResponse(['success' => true, 'data' => $scholarships, 'total' => $total->fetchColumn()]);
        } catch (Exception $e) { jsonResponse(['success' => false, 'error' => $e->getMessage()], 500); }
        break;

    // ─── GET SINGLE SCHOLARSHIP ───
    case 'get_scholarship':
        try {
            $id = $input['id'] ?? $_GET['id'] ?? 0;
            $stmt = $pdo->prepare("SELECT s.*, sc.name as category_name, ss.name as sponsor_name FROM scholarships s LEFT JOIN scholarship_categories sc ON s.category_id=sc.id LEFT JOIN scholarship_sponsors ss ON s.sponsor_id=ss.id WHERE s.id=?");
            $stmt->execute([$id]);
            $scholarship = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$scholarship) jsonResponse(['success' => false, 'error' => 'Not found'], 404);
            jsonResponse(['success' => true, 'data' => $scholarship]);
        } catch (Exception $e) { jsonResponse(['success' => false, 'error' => $e->getMessage()], 500); }
        break;

    // ─── SUBMIT APPLICATION ───
    case 'submit_application':
        try {
            $scholarshipId = $input['scholarship_id'] ?? 0;
            if (!$scholarshipId) jsonResponse(['success' => false, 'error' => 'Scholarship ID required'], 400);

            $appCode = generateCode('APP');
            $passportPath = null;
            $admissionPath = null;
            $transcriptPath = null;
            $idCardPath = null;
            $recLetterPath = null;

            if (!empty($_FILES['passport_photo']['tmp_name'])) $passportPath = uploadFile($_FILES['passport_photo'], 'scholarships/docs');
            if (!empty($_FILES['admission_letter']['tmp_name'])) $admissionPath = uploadFile($_FILES['admission_letter'], 'scholarships/docs');
            if (!empty($_FILES['academic_transcript']['tmp_name'])) $transcriptPath = uploadFile($_FILES['academic_transcript'], 'scholarships/docs');
            if (!empty($_FILES['id_card']['tmp_name'])) $idCardPath = uploadFile($_FILES['id_card'], 'scholarships/docs');
            if (!empty($_FILES['recommendation_letter']['tmp_name'])) $recLetterPath = uploadFile($_FILES['recommendation_letter'], 'scholarships/docs');

            $userId = $_SESSION['user']['id'] ?? null;

            $stmt = $pdo->prepare("INSERT INTO scholarship_applications (scholarship_id, user_id, application_code, full_name, gender, date_of_birth, email, phone, address, state, country, institution, faculty, department, course, level, cgpa, passport_photo, admission_letter, academic_transcript, id_card, recommendation_letter, personal_statement, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $scholarshipId, $userId, $appCode,
                $input['full_name'] ?? '', $input['gender'] ?? null, $input['date_of_birth'] ?? null,
                $input['email'] ?? '', $input['phone'] ?? '', $input['address'] ?? '',
                $input['state'] ?? '', $input['country'] ?? '',
                $input['institution'] ?? '', $input['faculty'] ?? '', $input['department'] ?? '',
                $input['course'] ?? '', $input['level'] ?? '', $input['cgpa'] ?? null,
                $passportPath, $admissionPath, $transcriptPath, $idCardPath, $recLetterPath,
                $input['personal_statement'] ?? '', 'submitted'
            ]);

            $pdo->prepare("UPDATE scholarships SET total_applications = total_applications + 1 WHERE id=?")->execute([$scholarshipId]);

            jsonResponse(['success' => true, 'application_code' => $appCode, 'id' => $pdo->lastInsertId()]);
        } catch (Exception $e) { jsonResponse(['success' => false, 'error' => $e->getMessage()], 500); }
        break;

    // ─── GET APPLICATIONS ───
    case 'get_applications':
        try {
            $where = "1=1";
            $params = [];
            if (!empty($input['scholarship_id'])) { $where .= " AND sa.scholarship_id=?"; $params[] = $input['scholarship_id']; }
            if (!empty($input['status'])) { $where .= " AND sa.status=?"; $params[] = $input['status']; }
            if (!empty($input['search'])) { $where .= " AND (sa.full_name LIKE ? OR sa.email LIKE ? OR sa.application_code LIKE ?)"; $params[] = "%{$input['search']}%"; $params[] = "%{$input['search']}%"; $params[] = "%{$input['search']}%"; }
            $limit = min((int)($input['limit'] ?? 20), 100);
            $offset = (int)($input['offset'] ?? 0);
            $stmt = $pdo->prepare("SELECT sa.*, s.title as scholarship_title, s.code as scholarship_code FROM scholarship_applications sa JOIN scholarships s ON sa.scholarship_id=s.id WHERE $where ORDER BY sa.submitted_at DESC LIMIT $limit OFFSET $offset");
            $stmt->execute($params);
            $apps = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $total = $pdo->prepare("SELECT COUNT(*) FROM scholarship_applications sa WHERE $where");
            $total->execute($params);
            jsonResponse(['success' => true, 'data' => $apps, 'total' => $total->fetchColumn()]);
        } catch (Exception $e) { jsonResponse(['success' => false, 'error' => $e->getMessage()], 500); }
        break;

    // ─── UPDATE APPLICATION STATUS ───
    case 'update_application_status':
        try {
            $id = $input['id'] ?? 0;
            $status = $input['status'] ?? '';
            $notes = $input['admin_notes'] ?? null;
            $sql = "UPDATE scholarship_applications SET status=?";
            $params = [$status];
            if ($notes !== null) { $sql .= ", admin_notes=?"; $params[] = $notes; }
            if (in_array($status, ['approved', 'rejected', 'reviewed'])) { $sql .= ", reviewed_at=NOW()"; }
            $sql .= " WHERE id=?";
            $params[] = $id;
            $pdo->prepare($sql)->execute($params);

            // Fetch applicant user_id and details to notify them
            $appStmt = $pdo->prepare("SELECT sa.user_id, sa.full_name, s.title FROM scholarship_applications sa JOIN scholarships s ON sa.scholarship_id = s.id WHERE sa.id = ?");
            $appStmt->execute([$id]);
            $app = $appStmt->fetch();
            if ($app && $app['user_id']) {
                $notifTitle = "Scholarship Application Update";
                $notifMsg = "Dear " . $app['full_name'] . ", your application for the scholarship '" . $app['title'] . "' has been updated to: " . ucfirst($status) . ".";
                    add_notification($app['user_id'], $notifTitle, $notifMsg, 'scholarship', '../user/scholarships.php', $id);
            }

            jsonResponse(['success' => true]);
        } catch (Exception $e) { jsonResponse(['success' => false, 'error' => $e->getMessage()], 500); }
        break;

    // ─── BULK ACTION ───
    case 'bulk_action':
        try {
            $ids = $input['ids'] ?? [];
            $status = $input['status'] ?? '';
            if (empty($ids) || !$status) jsonResponse(['success' => false, 'error' => 'IDs and status required'], 400);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $params = array_merge([$status], $ids);
            $pdo->prepare("UPDATE scholarship_applications SET status=? WHERE id IN ($placeholders)")->execute($params);

            // Notify all updated applicants
            foreach ($ids as $id) {
                $appStmt = $pdo->prepare("SELECT sa.user_id, sa.full_name, s.title FROM scholarship_applications sa JOIN scholarships s ON sa.scholarship_id = s.id WHERE sa.id = ?");
                $appStmt->execute([$id]);
                $app = $appStmt->fetch();
                if ($app && $app['user_id']) {
                    $notifTitle = "Scholarship Application Update";
                    $notifMsg = "Dear " . $app['full_name'] . ", your application for the scholarship '" . $app['title'] . "' has been updated to: " . ucfirst($status) . ".";
                add_notification($app['user_id'], $notifTitle, $notifMsg, 'scholarship', '../user/scholarships.php', $id);
                }
            }

            jsonResponse(['success' => true, 'affected' => count($ids)]);
        } catch (Exception $e) { jsonResponse(['success' => false, 'error' => $e->getMessage()], 500); }
        break;

    // ─── SAVE SCORE ───
    case 'save_score':
        try {
            $appId = $input['application_id'] ?? 0;
            $academic = $input['academic_score'] ?? 0;
            $financial = $input['financial_score'] ?? 0;
            $leadership = $input['leadership_score'] ?? 0;
            $community = $input['community_score'] ?? 0;
            $statement = $input['statement_score'] ?? 0;
            $total = $academic + $financial + $leadership + $community + $statement;
            $scoredBy = $_SESSION['user']['id'] ?? null;

            $existing = $pdo->prepare("SELECT id FROM application_scores WHERE application_id=?");
            $existing->execute([$appId]);
            if ($existing->fetch()) {
                $pdo->prepare("UPDATE application_scores SET academic_score=?, financial_score=?, leadership_score=?, community_score=?, statement_score=?, total_score=?, scored_by=?, notes=? WHERE application_id=?")->execute([$academic, $financial, $leadership, $community, $statement, $total, $scoredBy, $input['notes'] ?? null, $appId]);
            } else {
                $pdo->prepare("INSERT INTO application_scores (application_id, academic_score, financial_score, leadership_score, community_score, statement_score, total_score, scored_by, notes) VALUES (?,?,?,?,?,?,?,?,?)")->execute([$appId, $academic, $financial, $leadership, $community, $statement, $total, $scoredBy, $input['notes'] ?? null]);
            }
            $pdo->prepare("UPDATE scholarship_applications SET total_score=? WHERE id=?")->execute([$total, $appId]);
            jsonResponse(['success' => true, 'total_score' => $total]);
        } catch (Exception $e) { jsonResponse(['success' => false, 'error' => $e->getMessage()], 500); }
        break;

    // ─── SCHEDULE INTERVIEW ───
    case 'schedule_interview':
        try {
            $stmt = $pdo->prepare("INSERT INTO application_interviews (application_id, scholarship_id, interview_date, interview_time, venue, meeting_link, panel_members, notes) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $input['application_id'], $input['scholarship_id'],
                $input['interview_date'], $input['interview_time'] ?? null,
                $input['venue'] ?? '', $input['meeting_link'] ?? null,
                json_encode($input['panel_members'] ?? []), $input['notes'] ?? null
            ]);
            $pdo->prepare("UPDATE scholarship_applications SET status='interview_scheduled' WHERE id=?")->execute([$input['application_id']]);
            jsonResponse(['success' => true, 'id' => $pdo->lastInsertId()]);
        } catch (Exception $e) { jsonResponse(['success' => false, 'error' => $e->getMessage()], 500); }
        break;

    // ─── GET INTERVIEWS ───
    case 'get_interviews':
        try {
            $where = "1=1";
            $params = [];
            if (!empty($input['scholarship_id'])) { $where .= " AND ai.scholarship_id=?"; $params[] = $input['scholarship_id']; }
            if (!empty($input['date'])) { $where .= " AND ai.interview_date=?"; $params[] = $input['date']; }
            $stmt = $pdo->prepare("SELECT ai.*, sa.full_name, sa.email, sa.phone, s.title as scholarship_title FROM application_interviews ai JOIN scholarship_applications sa ON ai.application_id=sa.id JOIN scholarships s ON ai.scholarship_id=s.id WHERE $where ORDER BY ai.interview_date, ai.interview_time");
            $stmt->execute($params);
            jsonResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (Exception $e) { jsonResponse(['success' => false, 'error' => $e->getMessage()], 500); }
        break;

    // ─── UPDATE INTERVIEW ───
    case 'update_interview':
        try {
            $id = $input['id'] ?? 0;
            $pdo->prepare("UPDATE application_interviews SET status=?, outcome=?, feedback=? WHERE id=?")->execute([$input['status'] ?? 'scheduled', $input['outcome'] ?? 'pending', $input['feedback'] ?? null, $id]);
            jsonResponse(['success' => true]);
        } catch (Exception $e) { jsonResponse(['success' => false, 'error' => $e->getMessage()], 500); }
        break;

    // ─── APPROVE AWARD ───
    case 'approve_award':
        try {
            $stmt = $pdo->prepare("INSERT INTO scholarship_awards (application_id, scholarship_id, award_amount, currency, award_date, approved_by) VALUES (?,?,?,?,?,?)");
            $stmt->execute([
                $input['application_id'], $input['scholarship_id'],
                $input['award_amount'] ?? 0, $input['currency'] ?? 'NGN',
                date('Y-m-d'), $_SESSION['user']['id'] ?? null
            ]);
            $pdo->prepare("UPDATE scholarship_applications SET status='awarded' WHERE id=?")->execute([$input['application_id']]);
            jsonResponse(['success' => true, 'id' => $pdo->lastInsertId()]);
        } catch (Exception $e) { jsonResponse(['success' => false, 'error' => $e->getMessage()], 500); }
        break;

    // ─── UPDATE PAYMENT ───
    case 'update_payment':
        try {
            $id = $input['id'] ?? 0;
            $pdo->prepare("UPDATE scholarship_awards SET payment_status=?, disbursement_date=?, disbursement_notes=?, payment_reference=? WHERE id=?")->execute([
                $input['payment_status'] ?? 'pending', $input['disbursement_date'] ?? null,
                $input['disbursement_notes'] ?? null, $input['payment_reference'] ?? null, $id
            ]);
            jsonResponse(['success' => true]);
        } catch (Exception $e) { jsonResponse(['success' => false, 'error' => $e->getMessage()], 500); }
        break;

    // ─── GET AWARDS ───
    case 'get_awards':
        try {
            $where = "1=1";
            $params = [];
            if (!empty($input['scholarship_id'])) { $where .= " AND sa.scholarship_id=?"; $params[] = $input['scholarship_id']; }
            if (!empty($input['payment_status'])) { $where .= " AND sa.payment_status=?"; $params[] = $input['payment_status']; }
            $stmt = $pdo->prepare("SELECT sa.*, sap.full_name, sap.email, s.title as scholarship_title FROM scholarship_awards sa JOIN scholarship_applications sap ON sa.application_id=sap.id JOIN scholarships s ON sa.scholarship_id=s.id WHERE $where ORDER BY sa.created_at DESC");
            $stmt->execute($params);
            jsonResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (Exception $e) { jsonResponse(['success' => false, 'error' => $e->getMessage()], 500); }
        break;

    // ─── GENERATE CERTIFICATE ───
    case 'generate_certificate':
        try {
            $appId = $input['application_id'] ?? 0;
            $app = $pdo->prepare("SELECT sa.*, s.title as sch_title, ss.name as sponsor_name FROM scholarship_applications sa JOIN scholarships s ON sa.scholarship_id=s.id LEFT JOIN scholarship_sponsors ss ON s.sponsor_id=ss.id WHERE sa.id=?");
            $app->execute([$appId]);
            $app = $app->fetch(PDO::FETCH_ASSOC);
            if (!$app) jsonResponse(['success' => false, 'error' => 'Application not found'], 404);

            $certNum = 'CERT-' . date('Y') . '-' . strtoupper(substr(uniqid(), -8));
            $stmt = $pdo->prepare("INSERT INTO scholarship_certificates (application_id, scholarship_id, certificate_number, student_name, scholarship_name, sponsor_name, issue_date, status) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([$appId, $app['scholarship_id'], $certNum, $app['full_name'], $app['sch_title'], $app['sponsor_name'] ?? null, date('Y-m-d'), 'generated']);
            jsonResponse(['success' => true, 'certificate_number' => $certNum, 'id' => $pdo->lastInsertId()]);
        } catch (Exception $e) { jsonResponse(['success' => false, 'error' => $e->getMessage()], 500); }
        break;

    // ─── CATEGORIES CRUD ───
    case 'get_categories':
        try {
            $cats = $pdo->query("SELECT * FROM scholarship_categories ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
            jsonResponse(['success' => true, 'data' => $cats]);
        } catch (Exception $e) { jsonResponse(['success' => false, 'error' => $e->getMessage()], 500); }
        break;

    case 'create_category':
        try {
            $name = $input['name'] ?? '';
            $slug = $input['slug'] ?: strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
            $pdo->prepare("INSERT INTO scholarship_categories (name, slug, description, icon, color) VALUES (?,?,?,?,?)")->execute([$name, $slug, $input['description'] ?? '', $input['icon'] ?? 'fas fa-tag', $input['color'] ?? '#3b82f6']);
            jsonResponse(['success' => true, 'id' => $pdo->lastInsertId()]);
        } catch (Exception $e) { jsonResponse(['success' => false, 'error' => $e->getMessage()], 500); }
        break;

    case 'update_category':
        try {
            $id = $input['id'] ?? 0;
            $pdo->prepare("UPDATE scholarship_categories SET name=?, description=?, icon=?, color=?, is_active=? WHERE id=?")->execute([$input['name'] ?? '', $input['description'] ?? '', $input['icon'] ?? 'fas fa-tag', $input['color'] ?? '#3b82f6', $input['is_active'] ?? 1, $id]);
            jsonResponse(['success' => true]);
        } catch (Exception $e) { jsonResponse(['success' => false, 'error' => $e->getMessage()], 500); }
        break;

    case 'delete_category':
        try {
            $id = $input['id'] ?? 0;
            $pdo->prepare("DELETE FROM scholarship_categories WHERE id=?")->execute([$id]);
            jsonResponse(['success' => true]);
        } catch (Exception $e) { jsonResponse(['success' => false, 'error' => $e->getMessage()], 500); }
        break;

    // ─── SPONSORS CRUD ───
    case 'get_sponsors':
        try {
            $sponsors = $pdo->query("SELECT * FROM scholarship_sponsors ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
            jsonResponse(['success' => true, 'data' => $sponsors]);
        } catch (Exception $e) { jsonResponse(['success' => false, 'error' => $e->getMessage()], 500); }
        break;

    case 'create_sponsor':
        try {
            $logoPath = null;
            if (!empty($_FILES['logo']['tmp_name'])) $logoPath = uploadFile($_FILES['logo'], 'scholarships/sponsors');
            $pdo->prepare("INSERT INTO scholarship_sponsors (name, logo, contact_person, email, phone, website, address, description) VALUES (?,?,?,?,?,?,?,?)")->execute([
                $input['name'] ?? '', $logoPath, $input['contact_person'] ?? '',
                $input['email'] ?? '', $input['phone'] ?? '', $input['website'] ?? '',
                $input['address'] ?? '', $input['description'] ?? ''
            ]);
            jsonResponse(['success' => true, 'id' => $pdo->lastInsertId()]);
        } catch (Exception $e) { jsonResponse(['success' => false, 'error' => $e->getMessage()], 500); }
        break;

    case 'update_sponsor':
        try {
            $id = $input['id'] ?? 0;
            $logoPath = null;
            if (!empty($_FILES['logo']['tmp_name'])) $logoPath = uploadFile($_FILES['logo'], 'scholarships/sponsors');
            $sql = "UPDATE scholarship_sponsors SET name=?, contact_person=?, email=?, phone=?, website=?, address=?, description=?";
            $params = [$input['name'] ?? '', $input['contact_person'] ?? '', $input['email'] ?? '', $input['phone'] ?? '', $input['website'] ?? '', $input['address'] ?? '', $input['description'] ?? ''];
            if ($logoPath) { $sql .= ", logo=?"; $params[] = $logoPath; }
            $sql .= " WHERE id=?";
            $params[] = $id;
            $pdo->prepare($sql)->execute($params);
            jsonResponse(['success' => true]);
        } catch (Exception $e) { jsonResponse(['success' => false, 'error' => $e->getMessage()], 500); }
        break;

    case 'delete_sponsor':
        try {
            $id = $input['id'] ?? 0;
            $pdo->prepare("DELETE FROM scholarship_sponsors WHERE id=?")->execute([$id]);
            jsonResponse(['success' => true]);
        } catch (Exception $e) { jsonResponse(['success' => false, 'error' => $e->getMessage()], 500); }
        break;

    // ─── PUBLIC: GET ACTIVE SCHOLARSHIPS ───
    case 'public_scholarships':
        try {
            $where = "s.is_active=1 AND s.status='published' AND (s.closing_date IS NULL OR s.closing_date >= CURDATE())";
            $params = [];
            if (!empty($input['category_id'])) { $where .= " AND s.category_id=?"; $params[] = $input['category_id']; }
            if (!empty($input['country'])) { $where .= " AND s.country=?"; $params[] = $input['country']; }
            if (!empty($input['state'])) { $where .= " AND s.state=?"; $params[] = $input['state']; }
            if (!empty($input['academic_level'])) { $where .= " AND s.academic_level LIKE ?"; $params[] = "%{$input['academic_level']}%"; }
            if (!empty($input['sponsor_id'])) { $where .= " AND s.sponsor_id=?"; $params[] = $input['sponsor_id']; }
            if (!empty($input['search'])) { $where .= " AND (s.title LIKE ? OR s.description LIKE ?)"; $params[] = "%{$input['search']}%"; $params[] = "%{$input['search']}%"; }
            $limit = min((int)($input['limit'] ?? 12), 50);
            $offset = (int)($input['offset'] ?? 0);
            $stmt = $pdo->prepare("SELECT s.*, sc.name as category_name, ss.name as sponsor_name FROM scholarships s LEFT JOIN scholarship_categories sc ON s.category_id=sc.id LEFT JOIN scholarship_sponsors ss ON s.sponsor_id=ss.id WHERE $where ORDER BY s.is_featured DESC, s.created_at DESC LIMIT $limit OFFSET $offset");
            $stmt->execute($params);
            $scholarships = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $total = $pdo->prepare("SELECT COUNT(*) FROM scholarships s WHERE $where");
            $total->execute($params);
            jsonResponse(['success' => true, 'data' => $scholarships, 'total' => $total->fetchColumn()]);
        } catch (Exception $e) { jsonResponse(['success' => false, 'error' => $e->getMessage()], 500); }
        break;

    // ─── PUBLIC: GET SINGLE SCHOLARSHIP ───
    case 'public_scholarship':
        try {
            $id = $input['id'] ?? $_GET['id'] ?? 0;
            $code = $input['code'] ?? $_GET['code'] ?? '';
            if ($id) {
                $stmt = $pdo->prepare("SELECT s.*, sc.name as category_name, ss.name as sponsor_name FROM scholarships s LEFT JOIN scholarship_categories sc ON s.category_id=sc.id LEFT JOIN scholarship_sponsors ss ON s.sponsor_id=ss.id WHERE s.id=? AND s.is_active=1");
                $stmt->execute([$id]);
            } else {
                $stmt = $pdo->prepare("SELECT s.*, sc.name as category_name, ss.name as sponsor_name FROM scholarships s LEFT JOIN scholarship_categories sc ON s.category_id=sc.id LEFT JOIN scholarship_sponsors ss ON s.sponsor_id=ss.id WHERE s.code=? AND s.is_active=1");
                $stmt->execute([$code]);
            }
            $s = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$s) jsonResponse(['success' => false, 'error' => 'Not found'], 404);
            jsonResponse(['success' => true, 'data' => $s]);
        } catch (Exception $e) { jsonResponse(['success' => false, 'error' => $e->getMessage()], 500); }
        break;

    // ─── TRACK APPLICATION ───
    case 'track_application':
        try {
            $code = $input['application_code'] ?? '';
            $email = $input['email'] ?? '';
            if (!$code || !$email) jsonResponse(['success' => false, 'error' => 'Code and email required'], 400);
            $stmt = $pdo->prepare("SELECT sa.*, s.title as scholarship_title FROM scholarship_applications sa JOIN scholarships s ON sa.scholarship_id=s.id WHERE sa.application_code=? AND sa.email=?");
            $stmt->execute([$code, $email]);
            $app = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$app) jsonResponse(['success' => false, 'error' => 'Application not found'], 404);
            jsonResponse(['success' => true, 'data' => $app, 'application' => $app]);
        } catch (Exception $e) { jsonResponse(['success' => false, 'error' => $e->getMessage()], 500); }
        break;

    // ─── REPORTS ───
    case 'reports':
        try {
            $reportType = $input['report_type'] ?? 'monthly';
            $data = [];
            switch ($reportType) {
                case 'monthly':
                    $data = $pdo->query("SELECT DATE_FORMAT(submitted_at,'%Y-%m') as label, COUNT(*) as count FROM scholarship_applications GROUP BY label ORDER BY label DESC LIMIT 12")->fetchAll(PDO::FETCH_ASSOC);
                    break;
                case 'by_state':
                    $data = $pdo->query("SELECT state as label, COUNT(*) as count FROM scholarship_applications WHERE state IS NOT NULL AND state != '' GROUP BY state ORDER BY count DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
                    break;
                case 'by_institution':
                    $data = $pdo->query("SELECT institution as label, COUNT(*) as count FROM scholarship_applications WHERE institution IS NOT NULL AND institution != '' GROUP BY institution ORDER BY count DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
                    break;
                case 'gender':
                    $data = $pdo->query("SELECT gender as label, COUNT(*) as count FROM scholarship_applications WHERE gender IS NOT NULL GROUP BY gender")->fetchAll(PDO::FETCH_ASSOC);
                    break;
                case 'by_scholarship':
                    $data = $pdo->query("SELECT s.title as label, COUNT(sa.id) as count FROM scholarships s LEFT JOIN scholarship_applications sa ON s.id=sa.scholarship_id GROUP BY s.id ORDER BY count DESC")->fetchAll(PDO::FETCH_ASSOC);
                    break;
                case 'approval_rate':
                    $total = $pdo->query("SELECT COUNT(*) FROM scholarship_applications")->fetchColumn();
                    $approved = $pdo->query("SELECT COUNT(*) FROM scholarship_applications WHERE status='approved' OR status='awarded'")->fetchColumn();
                    $data = [['label' => 'Approved', 'count' => (int)$approved], ['label' => 'Not Approved', 'count' => (int)($total - $approved)]];
                    break;
            }
            jsonResponse(['success' => true, 'data' => $data]);
        } catch (Exception $e) { jsonResponse(['success' => false, 'error' => $e->getMessage()], 500); }
        break;

    default:
        jsonResponse(['success' => false, 'error' => 'Invalid action'], 400);
}
