<?php
header('Content-Type:application/json');
require_once __DIR__ . '/config.php';
$data = json_decode(file_get_contents('php://input'), true);
$stmt = $pdo->prepare("INSERT INTO comments (project_id,commenter_name,comment) VALUES (?,?,?)");
$stmt->execute([$data['project_id'],$data['commenter_name'],$data['comment']]);
echo json_encode(['success'=>true]);
?>