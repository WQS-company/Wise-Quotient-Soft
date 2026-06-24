<?php
require_once 'config.php';

$sqls = [
    "CREATE TABLE IF NOT EXISTS `developer_requests` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `user_id` INT(11) NOT NULL,
        `skills` TEXT DEFAULT NULL,
        `portfolio_url` VARCHAR(500) DEFAULT NULL,
        `github_url` VARCHAR(500) DEFAULT NULL,
        `experience` TEXT DEFAULT NULL,
        `years_experience` TINYINT(3) DEFAULT 0,
        `hourly_rate_expected` DECIMAL(10,2) DEFAULT 0.00,
        `status` ENUM('pending','approved','rejected') DEFAULT 'pending',
        `admin_note` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS `developer_tasks` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `developer_id` INT(11) NOT NULL,
        `project_id` INT(11) DEFAULT NULL,
        `title` VARCHAR(255) NOT NULL,
        `description` TEXT DEFAULT NULL,
        `priority` ENUM('low','medium','high','urgent') DEFAULT 'medium',
        `status` ENUM('assigned','in_progress','review','completed','cancelled') DEFAULT 'assigned',
        `due_date` DATE DEFAULT NULL,
        `hourly_rate` DECIMAL(10,2) DEFAULT 0.00,
        `hours_worked` DECIMAL(8,2) DEFAULT 0.00,
        `developer_note` TEXT DEFAULT NULL,
        `created_by` INT(11) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS `developer_skills` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `developer_id` INT(11) NOT NULL,
        `skill_name` VARCHAR(100) NOT NULL,
        `level` ENUM('beginner','intermediate','advanced','expert') DEFAULT 'intermediate',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

foreach ($sqls as $sql) {
    if ($db->query($sql)) {
        echo "✅ OK: " . substr(trim($sql), 0, 50) . "...\n";
    } else {
        echo "❌ ERR: " . $db->error . "\n";
    }
}

// Check if users.role already supports 'developer'
$colInfo = $db->query("SHOW COLUMNS FROM users WHERE Field='role'");
if ($colInfo && $row = $colInfo->fetch_assoc()) {
    if (strpos($row['Type'], 'developer') === false) {
        if ($db->query("ALTER TABLE users MODIFY role ENUM('user','agent','developer','admin') DEFAULT 'user'")) {
            echo "✅ users.role updated to include 'developer'\n";
        } else {
            echo "⚠️  Could not alter users.role: " . $db->error . "\n";
        }
    } else {
        echo "✅ users.role already supports 'developer'\n";
    }
}

echo "\nAll done!\n";
