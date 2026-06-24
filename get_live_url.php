<?php
require_once __DIR__ . '/config.php';
$id = isset($_GET['id']) ? wqs_decrypt_id($_GET['id']) : 0;
$device = $_GET['device'];
$row = $pdo->prepare("SELECT live_url FROM projects WHERE id=? AND is_visible=1");
$row->execute([$id]);
echo $row->fetchColumn();
