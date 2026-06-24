<?php
require_once 'config.php';
try {
    $pdo->exec("ALTER TABLE users MODIFY role ENUM('user','agent','developer','admin','manager','sales','support','finance') DEFAULT 'user'");
    echo "Successfully updated users table ENUM.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
