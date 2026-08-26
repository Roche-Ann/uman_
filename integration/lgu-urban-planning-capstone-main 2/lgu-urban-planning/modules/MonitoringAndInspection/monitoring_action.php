<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); 
require_once __DIR__ . '/MonitoringController.php';
require_once __DIR__ . '/../../core/Database.php'; 

$controller = new MonitoringController();
$db = Database::getInstance(); 
header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';

try {
    if ($action === 'fetch_events') {
        echo json_encode($controller->getAllInspections());
    } 
    elseif ($action === 'save_schedule') {
        $result = $controller->scheduleInspection($_POST);

        if ($result) {
            $app_id = (int)$_POST['application_id'];
            $msg_subj = "OFFICIAL NOTICE: Inspection Schedule for App #" . $app_id;
            
            $date_raw = $_POST['scheduled_at'] ?? '';
            $formatted_date = $date_raw ? date('F j, Y, g:i A', strtotime($date_raw)) : 'the assigned schedule';
            
            $msg_text = "Dear Applicant,\n\n" .
                        "This is an official notification from the Building Official's Office. An onsite inspection for your application (#" . $app_id . ") has been scheduled on " . $formatted_date . ".\n\n" .
                        "Remarks: " . ($_POST['notes'] ?: 'No specific instructions provided.') . "\n\n" .
                        "Please ensure that the project site is accessible and a representative is present during the visit. Thank you.";

            $db->query(
                "INSERT INTO messages (sender_id, receiver_id, application_id, subject, message, created_at, is_read) 
                 SELECT 1, applicant_id, id, ?, ?, NOW(), 0 
                 FROM applications WHERE id = ?", 
                [$msg_subj, $msg_text, $app_id]
            );
        }
        echo json_encode(['success' => $result]);
    }
elseif ($action === 'save_checklist') {
        $inspection_id = (int)($_POST['inspection_id'] ?? 0);
        $notes = $_POST['notes'] ?? 'Compliant with all requirements.';
        
        // A. I-save ang checklist result
        $success = $db->query(
            "UPDATE inspections SET status = 'completed', notes = CONCAT(IFNULL(notes,''), '\nChecklist Completed: ', ?) WHERE id = ?", 
            [$notes, $inspection_id]
        );

        if ($success) {
            // B. I-update ang status ng application sa 'approved'
            $db->query("UPDATE applications a JOIN inspections i ON a.id = i.application_id SET a.status = 'approved' WHERE i.id = ?", [$inspection_id]);

            // C. AUTOMATIC MESSAGE (Eto yung logic na gumagana sa save_schedule mo)
            // Gamit ang INSERT INTO ... SELECT para sigurado ang IDs at walang error sa constraints
            $db->query(
                "INSERT INTO messages (sender_id, receiver_id, application_id, subject, message, created_at, is_read) 
                 SELECT 1, a.applicant_id, a.id, 
                 'OFFICIAL NOTICE: Zoning Compliance Validation', 
                 'Dear Applicant, your project has been successfully validated as COMPLIANT. You may now download your certificate in the portal.', 
                 NOW(), 0 
                 FROM applications a 
                 JOIN inspections i ON a.id = i.application_id 
                 WHERE i.id = ?", 
                [$inspection_id]
            );
        }
        
        echo json_encode(['success' => $success]);
        exit;
    }
elseif ($action === 'send_approval_message') {
    $inspection_id = (int)($_POST['inspection_id'] ?? 0);

    // Kunin ang application_id at applicant_id (receiver)
    $info = $db->fetch(
        "SELECT i.application_id, a.applicant_id 
         FROM inspections i 
         JOIN applications a ON i.application_id = a.id 
         WHERE i.id = ?", 
        [$inspection_id]
    );

    if ($info) {
        $app_id = $info['application_id'];
        $receiver_id = $info['applicant_id'];
        $sender_id = 1; // Admin/Officer ID
        $subject = "OFFICIAL NOTICE: Approval of Zoning Compliance (App #" . $info['application_number'] . ")";
        
        $message = "Republic of the Philippines\n" .
                   "OFFICE OF THE ZONING ADMINISTRATOR\n\n" .
                   "NOTICE OF APPROVAL\n\n" .
                   "Reference No: " . $info['application_number'] . "\n" .
                   "Project: " . strtoupper($info['project_name']) . "\n" .
                   "Date: " . date('F j, Y') . "\n\n" .
                   "Dear Applicant,\n\n" .
                   "We are pleased to inform you that your application for Zoning Compliance has been officially APPROVED following a successful final inspection and review of documents.\n\n" .
                   "Your DIGITAL ZONING CERTIFICATE is now available for viewing and download.\n\n" .
                   "For any inquiries, please contact the Zoning Office during regular business hours.\n\n" .
                   "Respectfully,\n\n" .
                   "ZONING ADMINISTRATION OFFICE\n" .
                   "Local Government Unit";        
        // TAMA NA ANG PAGKAKASUNOD NG 9 COLUMNS DITO:
        // id (auto), application_id, sender_id, receiver_id, subject, message, is_read, message_type, created_at
        $sql = "INSERT INTO messages 
                (application_id, sender_id, receiver_id, subject, message, is_read, message_type, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $res = $db->query($sql, [
            $app_id,        // application_id
            $sender_id,     // sender_id
            $receiver_id,   // receiver_id
            $subject,       // subject
            $message,       // message
            0,              // is_read (0 = unread)
            'notification'  // message_type (importante ito para hindi mag-null error)
        ]);

        echo json_encode(['success' => $res]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Data mapping failed']);
    }
    exit;
}
elseif ($action === 'report_violation') {
    $ins_id = (int)$_POST['inspection_id'];
    $app_id = (int)$_POST['application_id'];
    $viol_type = $_POST['violation_type'];
    $notes = $_POST['notes'];
    
    // 1. Photo Upload (Evidence Gathering)
    $photo_name = null;
    if (isset($_FILES['violation_photo']) && $_FILES['violation_photo']['error'] == 0) {
        $upload_path = '../../uploads/violations/';
        if (!is_dir($upload_path)) mkdir($upload_path, 0777, true);
        $ext = pathinfo($_FILES['violation_photo']['name'], PATHINFO_EXTENSION);
        $photo_name = 'VIOL_PROOF_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['violation_photo']['tmp_name'], $upload_path . $photo_name);
    }

    // 2. STATUS UPDATE (Trigger legal hold)
    // Application becomes 'on-hold' or 'violation_detected'
    $db->query("UPDATE applications SET status = 'violation_detected' WHERE id = ?", [$app_id]);
    
    // Inspection becomes 'failed'
    $db->query("UPDATE inspections SET status = 'failed' WHERE id = ?", [$ins_id]);

    // 3. DOCUMENTATION (Notice of Violation)
    $app_info = $db->fetch("SELECT applicant_id, application_number, project_name FROM applications WHERE id = ?", [$app_id]);
    
    if($app_info) {
        $subj = "LEGAL NOTICE: Violation Detected - " . $app_info['application_number'];
        $msg = "NOTICE OF VIOLATION\n\n" .
               "This is an official notice regarding your project: " . strtoupper($app_info['project_name']) . ".\n\n" .
               "NATURE OF VIOLATION: $viol_type\n" .
               "DETAILED FINDINGS: $notes\n\n" .
               "RESOLUTION REQUIRED:\n" .
               "1. Immediate correction of the mentioned violation.\n" .
               "2. Payment of necessary penalties (if applicable).\n" .
               "3. Request for RE-INSPECTION once corrections are completed.\n\n" .
               "Note: No Certificate of Occupancy or further permits will be issued until this violation is resolved.\n\n" .
               "OFFICE OF THE BUILDING OFFICIAL";

        $db->query(
            "INSERT INTO messages (application_id, sender_id, receiver_id, subject, message, is_read, message_type, created_at) 
             VALUES (?, 1, ?, ?, ?, 0, 'legal_notice', NOW())",
            [$app_id, $app_info['applicant_id'], $subj, $msg]
        );
    }

    echo json_encode(['success' => true]);
    exit;
}
    elseif ($action === 'delete_event') {
        $id = $_POST['id'] ?? null;
        $db->query("DELETE FROM messages WHERE application_id = (SELECT application_id FROM inspections WHERE id = ?) AND subject LIKE 'OFFICIAL NOTICE%'", [$id]);
        $success = $db->query("DELETE FROM inspections WHERE id = ?", [$id]);
        echo json_encode(['success' => $success]);
    }
    elseif ($action === 'approve_occupancy') {
        $app_id = $_POST['application_id'] ?? null;
        $res1 = $controller->approveOccupancy($app_id);
        $res2 = $db->query("UPDATE inspections SET status = 'completed' WHERE application_id = ?", [$app_id]);
        echo json_encode(['success' => ($res1 && $res2)]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}