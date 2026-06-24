<?php
header('Content-Type:application/json');
require_once __DIR__ . '/config.php';
$comments = $pdo->prepare("SELECT commenter_name,comment FROM comments WHERE project_id=? ORDER BY created_at DESC");
$comments->execute([intval($_GET['project_id'])]);
echo json_encode($comments->fetchAll(PDO::FETCH_ASSOC));
