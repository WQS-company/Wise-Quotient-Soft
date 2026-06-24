<?php
require_once 'config.php';
$stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'");
print_r($stmt->fetch(PDO::FETCH_ASSOC));
?>
