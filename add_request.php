<?php
header('Content-Type:application/json');
require_once __DIR__ . '/config.php';
$d = json_decode(file_get_contents('php://input'), true);
$stmt = $pdo->prepare("INSERT INTO requests (project_id,user_name,message) VALUES (?,?,?)");
$stmt->execute([$d['project_id'],$d['user_name'],$d['message']]);
echo json_encode(['success'=>true]);
?>