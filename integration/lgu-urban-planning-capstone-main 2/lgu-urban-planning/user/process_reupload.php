<?php
// process_reupload.php

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = Database::getInstance();
    $userId = $_SESSION['user_id'] ?? null;

    if (!$userId) {
        die("Unauthorized access.");
    }

    $uploadDir = __DIR__ . '/../uploads/ids/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $idFront = $_FILES['id_front'];
    $idBack = $_FILES['id_back'];

    $frontName = 'front_' . $userId . '_' . time() . '.' . pathinfo($idFront['name'], PATHINFO_EXTENSION);
    $backName = 'back_' . $userId . '_' . time() . '.' . pathinfo($idBack['name'], PATHINFO_EXTENSION);

    $frontPath = $uploadDir . $frontName;
    $backPath = $uploadDir . $backName;

    if (move_uploaded_file($idFront['tmp_name'], $frontPath) && move_uploaded_file($idBack['tmp_name'], $backPath)) {
        
        $sql = "UPDATE users SET 
                id_front_path = ?, 
                id_back_path = ?, 
                is_verified = 0, 
                rejection_reason = NULL 
                WHERE id = ?";
        
        try {
            $stmt = $db->query($sql, [$frontName, $backName, $userId]);

            if ($stmt) {
                $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
                $_SESSION['user_data'] = $user;

                header("Location: index.php?success=reuploaded");
                exit;
            }
        } catch (PDOException $e) {
            die("Database Error: " . $e->getMessage());
        }
    } else {
        echo "File upload failed. Please check folder permissions.";
    }
} else {
    header("Location: index.php");
    exit;
}