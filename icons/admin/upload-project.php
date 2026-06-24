<?php
session_start();
header('Content-Type: application/json');

require_once dirname(dirname(__DIR__)) . '/config.php';

try {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['ajax'])) {
        throw new Exception('Invalid request');
    }

    // ——— Ensure upload directories exist —————————————————————————————————
    $baseUpload = __DIR__ . '/uploads';
    $dirs = [
        'files'  => "$baseUpload/files",
        'docs'   => "$baseUpload/docs",
        'videos' => "$baseUpload/videos",
        'images' => "$baseUpload/images"
    ];
    foreach ($dirs as $sub) {
        if (!is_dir($sub)) {
            mkdir($sub, 0755, true);
        }
    }

    // ——— Gather form inputs ——————————————————————————————————————————
    $title           = $_POST['title'];
    $description     = $_POST['description'];
    $live_url        = $_POST['live_url'];
    $download_url    = $_POST['download_url'];
    $doc_url         = $_POST['doc_url'];
    $video_url       = $_POST['video_url'];
    $enable_download = isset($_POST['enable_download']) ? 1 : 0;

    $expected_amount   = $_POST['expected_amount'] ?? null;
    $actual_amount     = $_POST['actual_amount'] ?? null;
    $start_date        = $_POST['start_date'] ?? null;
    $end_date          = $_POST['end_date'] ?? null;
    $num_features      = $_POST['num_features'] ?? null;
    $assigned_user_id  = $_POST['assigned_user_id'] ?? null;

    $team_members            = $_POST['team_members'] ?? [];
    $project_manager_index   = $_POST['project_manager'] ?? null;

    // ——— Handle Download File or URL ———————————————————————————————————
    if (!empty($_FILES['download_file']['name']) && $_FILES['download_file']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['download_file']['name'], PATHINFO_EXTENSION);
        $tmp = $_FILES['download_file']['tmp_name'];
        $newName = uniqid('dl_') . '.' . $ext;
        move_uploaded_file($tmp, $dirs['files'] . "/$newName");
        $download_url = 'uploads/files/' . $newName;
    }

    // ——— Handle Documentation File or URL ——————————————————————————————
    if (!empty($_FILES['doc_file']['name']) && $_FILES['doc_file']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['doc_file']['name'], PATHINFO_EXTENSION);
        $tmp = $_FILES['doc_file']['tmp_name'];
        $newName = uniqid('doc_') . '.' . $ext;
        move_uploaded_file($tmp, $dirs['docs'] . "/$newName");
        $doc_url = 'uploads/docs/' . $newName;
    }

    // ——— Handle Video File or URL ——————————————————————————————————————
    if (!empty($_FILES['video_file']['name']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['video_file']['name'], PATHINFO_EXTENSION);
        $tmp = $_FILES['video_file']['tmp_name'];
        $newName = uniqid('vid_') . '.' . $ext;
        move_uploaded_file($tmp, $dirs['videos'] . "/$newName");
        $video_url = 'uploads/videos/' . $newName;
    }

    // ——— Insert Project Record ——————————————————————————————————————
    $stmt = $pdo->prepare(
        "INSERT INTO projects 
        (title, description, live_url, download_url, doc_url, video_url, enable_download,
         expected_amount, actual_amount, start_date, end_date, num_features, assigned_user_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $title, $description, $live_url,
        $download_url, $doc_url, $video_url, $enable_download,
        $expected_amount, $actual_amount, $start_date, $end_date,
        $num_features, $assigned_user_id
    ]);
    $projectId = $pdo->lastInsertId();

    // ——— Handle Tech Stacks ————————————————————————————————————————
    if (!empty($_POST['tech_stacks']) && is_array($_POST['tech_stacks'])) {
        $stmtStack = $pdo->prepare(
            "INSERT INTO project_tech_stacks (project_id, stack_name) VALUES (?, ?)"
        );
        foreach ($_POST['tech_stacks'] as $stack) {
            if (!empty(trim($stack))) {
                $stmtStack->execute([$projectId, trim($stack)]);
            }
        }
    }

    // ——— Handle Project Images & Captions ————————————————————————————
    if (!empty($_FILES['project_images']['name'][0])) {
        foreach ($_FILES['project_images']['tmp_name'] as $i => $tmpName) {
            if ($_FILES['project_images']['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }

            $caption   = $_POST['image_captions'][$i] ?? '';
            $origName  = $_FILES['project_images']['name'][$i];
            $ext       = pathinfo($origName, PATHINFO_EXTENSION);
            $newName   = $projectId . '_' . uniqid() . '.' . $ext;
            move_uploaded_file($tmpName, $dirs['images'] . "/$newName");

            $pdo->prepare(
                "INSERT INTO project_images 
                 (project_id, image_path, caption) 
                 VALUES (?, ?, ?)"
            )->execute([
                $projectId,
                'uploads/images/' . $newName,
                $caption
            ]);
        }
    }

    // ——— Handle Team Members with is_manager flag ——————————————————————
    $stmtTeam = $pdo->prepare(
        "INSERT INTO project_teams (project_id, user_id, is_manager) VALUES (?, ?, ?)"
    );

    if (!empty($team_members) && is_array($team_members)) {
        foreach ($team_members as $index => $uid) {
            $isManager = ($index == $project_manager_index) ? 1 : 0;
            $stmtTeam->execute([$projectId, $uid, $isManager]);
        }
    }

    // ——— Simulate Upload Delay (for UX) ——————————————————————————————
    usleep(300000); // 300ms

    echo json_encode(['success' => true]);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
    exit;
}
