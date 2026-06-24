<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action === 'fetch_projects') {
  $projects = $pdo->query("SELECT * FROM projects WHERE is_visible = 1 ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

  foreach ($projects as &$project) {
    // Fetch image_path and caption
    $stmt = $pdo->prepare("SELECT image_path, caption FROM project_images WHERE project_id = ?");
    $stmt->execute([$project['id']]);
    $raw_images = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $project['images'] = [];

    foreach ($raw_images as $row) {
      $paths = explode(';', $row['image_path']);
      foreach ($paths as $img) {
        $cleaned = trim($img);
        if (!empty($cleaned)) {
          $isHttp = strpos($cleaned, 'http') === 0;
          $project['images'][] = [
            'path' => $isHttp ? $cleaned : 'admin/' . $cleaned,
            'caption' => $row['caption'] ?? ''
          ];
        }
      }
    }

    $project['enable_download'] = (bool)$project['enable_download'];

    // Fetch tech stacks
    $stack_stmt = $pdo->prepare("SELECT stack_name FROM project_tech_stacks WHERE project_id = ?");
    $stack_stmt->execute([$project['id']]);
    $project['tech_stacks'] = $stack_stmt->fetchAll(PDO::FETCH_COLUMN);

    $project['encrypted_id'] = wqs_encrypt_id($project['id']);
  }

  echo json_encode($projects);
  exit;
}

if ($action === 'get_comments' && isset($_GET['project_id'])) {
  $stmt = $pdo->prepare("SELECT commenter_name, comment FROM comments WHERE project_id = ? ORDER BY id DESC");
  $stmt->execute([$_GET['project_id']]);
  echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
  exit;
}

if ($action === 'add_comment') {
  $data = json_decode(file_get_contents('php://input'), true);
  $stmt = $pdo->prepare("INSERT INTO comments (project_id, commenter_name, comment) VALUES (?, ?, ?)");
  $stmt->execute([$data['project_id'], $data['commenter_name'], $data['comment']]);
  echo json_encode(['success' => true]);
  exit;
}

if ($action === 'add_request') {
  $data = json_decode(file_get_contents('php://input'), true);
  $stmt = $pdo->prepare("INSERT INTO project_requests (project_id, user_name, message) VALUES (?, ?, ?)");
  $stmt->execute([$data['project_id'], $data['user_name'], $data['message']]);
  echo json_encode(['success' => true]);
  exit;
}

echo json_encode(['error' => 'Invalid request']);
