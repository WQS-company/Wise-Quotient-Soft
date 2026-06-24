<?php
require 'config.php';

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN theme ENUM('light', 'dark') DEFAULT 'light'");
    echo "Added 'theme' column successfully.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column 'theme' already exists.\n";
    } else {
        echo "Error adding 'theme' column: " . $e->getMessage() . "\n";
    }
}

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN session_timeout INT DEFAULT 60");
    echo "Added 'session_timeout' column successfully.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column 'session_timeout' already exists.\n";
    } else {
        echo "Error adding 'session_timeout' column: " . $e->getMessage() . "\n";
    }
}
