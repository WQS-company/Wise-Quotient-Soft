<?php
require_once 'config.php';
try {
    $pdo->exec("ALTER TABLE users MODIFY role ENUM('user','agent','developer','admin','manager','sales','support','finance','ceo','secretary') DEFAULT 'user'");
    echo "Successfully updated users table ENUM for CEO and Secretary.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
