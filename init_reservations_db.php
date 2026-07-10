<?php
require_once 'includes/db.php';

$sql = file_get_contents('sql/public_reservation_module.sql');

try {
    $pdo->exec($sql);
    echo "Database tables created successfully!";
} catch (PDOException $e) {
    echo "Error creating tables: " . $e->getMessage();
}
