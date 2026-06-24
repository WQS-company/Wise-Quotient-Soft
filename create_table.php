<?php
require 'config.php';
$sql = "
CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    company VARCHAR(255) NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NULL,
    service VARCHAR(100) NOT NULL,
    budget VARCHAR(100) NULL,
    timeline VARCHAR(100) NULL,
    message TEXT NOT NULL,
    ref_number VARCHAR(50) NOT NULL,
    status ENUM('new', 'read', 'replied') DEFAULT 'new',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
$pdo->exec($sql);
echo "Table created successfully.";
?>
