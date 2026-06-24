<?php
session_start();
header('Content-Type: application/json');

require_once dirname(__DIR__) . '/config.php';

try {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['ajax'])) {
        throw new Exception('Invalid request');
    }

    $projectId = (int)$_POST['project_id'];
    if (!$projectId) throw new Exception('Project ID is missing.');

    // Cloudinary Helper
    require_once dirname(__DIR__) . '/includes/cloudinary.php';

    // ——— Gather form inputs ——————————————————————————————————————————
    $title           = trim($_POST['title']);
    $description     = trim($_POST['description']);
    $live_url        = trim($_POST['live_url']);
    $download_url    = trim($_POST['download_url']);
    $doc_url         = trim($_POST['doc_url']);
    $video_url       = trim($_POST['video_url']);
    $enable_download = isset($_POST['enable_download']) ? 1 : 0;

    $expected_amount   = $_POST['expected_amount'] ?? null;
    $actual_amount     = $_POST['actual_amount'] ?? null;
    $start_date        = $_POST['start_date'] ?? null;
    $end_date          = $_POST['end_date'] ?? null;
    $num_features      = $_POST['num_features'] ?? null;
    $assigned_user_id  = $_POST['assigned_user_id'] ?? null;

    $team_members            = $_POST['team_members'] ?? [];
    $project_manager_index   = $_POST['project_manager_index'] ?? null;

    // Fetch existing files to not overwrite if empty
    $stmt = $pdo->prepare("SELECT download_url, doc_url, video_url FROM projects WHERE id = ?");
    $stmt->execute([$projectId]);
    $existing = $stmt->fetch();

    if (!$download_url && $existing['download_url']) $download_url = $existing['download_url'];
    if (!$doc_url && $existing['doc_url']) $doc_url = $existing['doc_url'];
    if (!$video_url && $existing['video_url']) $video_url = $existing['video_url'];

    // ——— Handle Download File or URL ———————————————————————————————————
    if (!empty($_FILES['download_file']['name']) && $_FILES['download_file']['error'] === UPLOAD_ERR_OK) {
        $tmp = $_FILES['download_file']['tmp_name'];
        $cloudUrl = uploadToCloudinary($tmp, 'project_files', 'raw');
        if ($cloudUrl) {
            $download_url = $cloudUrl;
        }
    }

    // ——— Handle Documentation File or URL ——————————————————————————————
    if (!empty($_FILES['doc_file']['name']) && $_FILES['doc_file']['error'] === UPLOAD_ERR_OK) {
        $tmp = $_FILES['doc_file']['tmp_name'];
        $cloudUrl = uploadToCloudinary($tmp, 'project_docs', 'raw');
        if ($cloudUrl) {
            $doc_url = $cloudUrl;
        }
    }

    // ——— Handle Video File or URL ——————————————————————————————————————
    if (!empty($_FILES['video_file']['name']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
        $tmp = $_FILES['video_file']['tmp_name'];
        $cloudUrl = uploadToCloudinary($tmp, 'project_videos', 'video');
        if ($cloudUrl) {
            $video_url = $cloudUrl;
        }
    }

    // ——— Update Project Record ——————————————————————————————————————
    $stmt = $pdo->prepare(
        "UPDATE projects SET 
         title = ?, description = ?, live_url = ?, download_url = ?, doc_url = ?, video_url = ?, enable_download = ?,
         expected_amount = ?, actual_amount = ?, start_date = ?, end_date = ?, num_features = ?, assigned_user_id = ?
         WHERE id = ?"
    );
    $stmt->execute([
        $title, $description, $live_url,
        $download_url, $doc_url, $video_url, $enable_download,
        $expected_amount, $actual_amount, $start_date, $end_date,
        $num_features, $assigned_user_id, $projectId
    ]);

    // ——— Handle Tech Stacks ————————————————————————————————————————
    $pdo->prepare("DELETE FROM project_tech_stacks WHERE project_id = ?")->execute([$projectId]);
    if (!empty($_POST['tech_stacks']) && is_array($_POST['tech_stacks'])) {
        $stmtStack = $pdo->prepare("INSERT INTO project_tech_stacks (project_id, stack_name) VALUES (?, ?)");
        foreach ($_POST['tech_stacks'] as $stack) {
            if (!empty(trim($stack))) {
                $stmtStack->execute([$projectId, trim($stack)]);
            }
        }
    }

    // ——— Handle Project Features ————————————————————————————————————
    $pdo->prepare("DELETE FROM project_features WHERE project_id = ?")->execute([$projectId]);
    if (!empty($_POST['features']) && is_array($_POST['features'])) {
        $stmtFeature = $pdo->prepare("INSERT INTO project_features (project_id, feature_name) VALUES (?, ?)");
        foreach ($_POST['features'] as $feat) {
            if (!empty(trim($feat))) {
                $stmtFeature->execute([$projectId, trim($feat)]);
            }
        }
    }

    // ——— Handle Team Members ——————————————————————————————————————
    $pdo->prepare("DELETE FROM project_teams WHERE project_id = ?")->execute([$projectId]);
    $stmtTeam = $pdo->prepare("INSERT INTO project_teams (project_id, user_id, is_manager) VALUES (?, ?, ?)");
    if (!empty($team_members) && is_array($team_members)) {
        foreach ($team_members as $index => $uid) {
            if (empty($uid)) continue;
            $isManager = ($index == $project_manager_index) ? 1 : 0;
            $stmtTeam->execute([$projectId, $uid, $isManager]);
        }
    }

    // ——— Handle Existing Image Updates/Deletions ————————————————————————————————
    if (!empty($_POST['delete_images']) && is_array($_POST['delete_images'])) {
        foreach ($_POST['delete_images'] as $delId) {
            $pdo->prepare("DELETE FROM project_images WHERE id = ? AND project_id = ?")->execute([$delId, $projectId]);
        }
    }

    if (!empty($_POST['existing_image_captions']) && is_array($_POST['existing_image_captions'])) {
        $stmtUpdateCap = $pdo->prepare("UPDATE project_images SET caption = ? WHERE id = ? AND project_id = ?");
        foreach ($_POST['existing_image_captions'] as $imgId => $cap) {
            // Only update if not deleted
            if (empty($_POST['delete_images']) || !in_array($imgId, $_POST['delete_images'])) {
                $stmtUpdateCap->execute([trim($cap), $imgId, $projectId]);
            }
        }
    }

    // ——— Handle New Project Images & Captions ————————————————————————————
    if (!empty($_FILES['project_images']['name'][0])) {
        foreach ($_FILES['project_images']['tmp_name'] as $i => $tmpName) {
            if ($_FILES['project_images']['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }

            $caption   = $_POST['image_captions'][$i] ?? '';
            $cloudUrl = uploadToCloudinary($tmpName, 'project_images', 'image');

            if ($cloudUrl) {
                $pdo->prepare("INSERT INTO project_images (project_id, image_path, caption) VALUES (?, ?, ?)")
                    ->execute([$projectId, $cloudUrl, $caption]);
            }
        }
    }

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
