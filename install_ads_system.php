<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$path_to_root = './';
require_once $path_to_root . 'config.php';

echo "<h2>Installing Ad & Notification System...</h2>";

$sql = <<<SQL
-- 1. ADS TABLE: Stores all ad content and targeting info
CREATE TABLE IF NOT EXISTS ads (
    id INT(11) NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    headline VARCHAR(255) DEFAULT NULL,
    subtitle VARCHAR(255) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    image_url VARCHAR(500) DEFAULT NULL,
    background_image VARCHAR(500) DEFAULT NULL,
    button_text VARCHAR(100) DEFAULT 'Learn More',
    button_url VARCHAR(500) DEFAULT '#',
    primary_color VARCHAR(20) DEFAULT '#10b981',
    secondary_color VARCHAR(20) DEFAULT '#059669',
    text_color VARCHAR(20) DEFAULT '#ffffff',
    display_type ENUM('modal', 'top_bar', 'bottom_bar', 'side_panel') DEFAULT 'modal',
    target_audience ENUM('all', 'users', 'developers', 'partners') DEFAULT 'all',
    start_date DATETIME DEFAULT NULL,
    end_date DATETIME DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    show_close_btn TINYINT(1) DEFAULT 1,
    priority INT(11) DEFAULT 0,
    created_by INT(11) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_active (is_active),
    KEY idx_target (target_audience)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. USER NOTIFICATION SETTINGS TABLE
CREATE TABLE IF NOT EXISTS user_notification_settings (
    id INT(11) NOT NULL AUTO_INCREMENT,
    user_id INT(11) NOT NULL,
    enable_ads TINYINT(1) DEFAULT 1,
    enable_push_notifications TINYINT(1) DEFAULT 1,
    enable_email_notifications TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. AD DISPLAY TRACKING TABLE
CREATE TABLE IF NOT EXISTS ad_displays (
    id INT(11) NOT NULL AUTO_INCREMENT,
    ad_id INT(11) NOT NULL,
    user_id INT(11) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    clicked_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_ad_id (ad_id),
    KEY idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- No default sample ads inserted. Ads must be created via Admin > Manage Ads.
SQL;

try {
    // Execute multi-query
    $pdo->exec($sql);
    echo "<div style='background: #d1fae5; color: #065f46; padding: 20px; border-radius: 8px; margin: 10px 0; font-weight: 600;'>✅ Tables created successfully! No default ads inserted — create them via Admin &gt; Manage Ads.</div>";
    echo "<p style='font-size: 0.95rem;'>Installation complete! You can now:</p>";
    echo "<ul style='margin: 10px 0 0 20px;'>";
    echo "<li><a href='admin/manage_ads.php' style='color: #3b82f6; font-weight: 600;'>Go to Manage Ads (admin)</a></li>";
    echo "<li><a href='index.php' style='color: #3b82f6; font-weight: 600;'>View homepage with ads</a></li>";
    echo "</ul>";
    echo "<p style='margin-top: 20px; color: #64748b;'><strong>For security:</strong> Delete this <code>install_ads_system.php</code> file now!</p>";
} catch (Exception $e) {
    echo "<div style='background: #fee2e2; color: #991b1b; padding: 20px; border-radius: 8px; margin: 10px 0;'><strong>Error:</strong> " . $e->getMessage() . "</div>";
}
?>