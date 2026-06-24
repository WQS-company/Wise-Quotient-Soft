<?php
require_once __DIR__ . '/../config.php';

try {
    $sqls = [
        "CREATE TABLE IF NOT EXISTS `user_fcm_tokens` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NULL,
            `fcm_token` VARCHAR(500) NOT NULL UNIQUE,
            `device_type` VARCHAR(50) DEFAULT 'desktop',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY `idx_user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS `fcm_notification_history` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `title` VARCHAR(255) NOT NULL,
            `message` TEXT NOT NULL,
            `recipient_count` INT DEFAULT 0,
            `status` VARCHAR(50) DEFAULT 'success',
            `response_log` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS `firebase_analytics_events` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `session_id` VARCHAR(100) DEFAULT NULL,
            `user_id` INT DEFAULT NULL,
            `event_name` VARCHAR(100) NOT NULL,
            `event_value` TEXT DEFAULT NULL,
            `ip_address` VARCHAR(45) DEFAULT NULL,
            `country` VARCHAR(100) DEFAULT 'Unknown',
            `state` VARCHAR(100) DEFAULT 'Unknown',
            `user_agent` TEXT DEFAULT NULL,
            `referrer` VARCHAR(255) DEFAULT 'Direct',
            `device_type` VARCHAR(50) DEFAULT 'Desktop',
            `browser` VARCHAR(50) DEFAULT 'Unknown',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY `idx_event_name` (`event_name`),
            KEY `idx_session_id` (`session_id`),
            KEY `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
    ];

    foreach ($sqls as $sql) {
        $pdo->exec($sql);
        echo "✅ Executed: " . substr(trim($sql), 0, 50) . "...\n";
    }

    // Check if scholarships has is_published_notification_sent
    $checkCol = $pdo->query("SHOW COLUMNS FROM `scholarships` LIKE 'is_published_notification_sent'");
    if (!$checkCol->fetch()) {
        $pdo->exec("ALTER TABLE `scholarships` ADD COLUMN `is_published_notification_sent` TINYINT(1) DEFAULT 0");
        echo "✅ Added 'is_published_notification_sent' to 'scholarships' table\n";
    } else {
        echo "✅ 'is_published_notification_sent' already exists in 'scholarships'\n";
    }

    echo "All migrations completed successfully!\n";
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
}
