<?php
session_start();
require_once __DIR__ . '/../core/Database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $db = Database::getInstance();
    
    $appointmentId = $_POST['appointment_id']; 
    $newDate = $_POST['new_date'];
    $reason = $_POST['reason'];
    $senderId = $_SESSION['user_id'];

    try {
        $inspection = $db->fetchOne("
            SELECT i.application_id, a.application_number, a.project_name 
            FROM inspections i 
            JOIN applications a ON i.application_id = a.id 
            WHERE i.id = ?", [$appointmentId]);

        if ($inspection) {
            $appId = $inspection['application_id'];
            $appNum = $inspection['application_number'];
            $projectName = $inspection['project_name'];

            $admin = $db->fetchOne("SELECT id FROM users WHERE role IN ('admin', 'building_official') LIMIT 1");
            $receiverId = $admin ? $admin['id'] : 1; 

            $subject = "Reschedule Request: Application #$appNum";
            $messageBody = "The applicant is requesting to reschedule the site inspection for project: $projectName.\n\n" .
                           "Preferred New Date: " . date('F d, Y', strtotime($newDate)) . "\n" .
                           "Reason: $reason";

            $db->query("
                INSERT INTO messages (sender_id, receiver_id, application_id, subject, message, is_read, created_at) 
                VALUES (?, ?, ?, ?, ?, 0, NOW())", 
                [$senderId, $receiverId, $appId, $subject, $messageBody]
            );

            $db->query("UPDATE inspections SET notes = CONCAT(IFNULL(notes,''), '\n[Reschedule Requested for $newDate]') WHERE id = ?", [$appointmentId]);

            header("Location: index.php?success=rescheduled");
            exit;
        }
    } catch (Exception $e) {
        die("Error: " . $e->getMessage());
    }
} else {
    header("Location: index.php");
    exit;
}