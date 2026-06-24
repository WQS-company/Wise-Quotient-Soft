<?php
require 'config.php';
$stmt = $pdo->query('DESCRIBE users');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
