<?php
// api/submit_service_request.php
// Handles creation of new service_requests + optional document uploads

header('Content-Type: application/json');

require_once '../includes/auth.php';
require_once '../includes/db.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$userId   = $_SESSION['user_id'] ?? null;
$userType = $_SESSION['user_type'] ?? '';

// Citizens create requests; allow employees too if needed
if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Invalid user session']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Common fields
$requestType   = trim($_POST['request_type'] ?? '');
$utilityType   = trim($_POST['utility_type'] ?? '');
$fullName      = trim($_POST['full_name'] ?? '');
$address       = trim($_POST['address'] ?? '');
$contactNumber = trim($_POST['contact_number'] ?? '');

if ($requestType === '' || $utilityType === '' || $fullName === '' || $address === '' || $contactNumber === '') {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields including contact number.']);
    exit();
}

$allowedRequestTypes = ['connection', 'disconnection', 'reconnection'];
$allowedUtilities    = ['water', 'electricity'];

if (!in_array($requestType, $allowedRequestTypes, true) ||
    !in_array($utilityType, $allowedUtilities, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request or utility type.']);
    exit();
}

// Optional, request-type-specific fields
$disconnectionReason = trim($_POST['disconnection_reason'] ?? '');
$previousAccount     = trim($_POST['previous_account'] ?? '');

try {
    // Insert base request
    $stmt = $pdo->prepare("
        INSERT INTO service_requests
            (user_id, full_name, address, contact_number, utility_type, request_type,
             status, disconnection_reason, previous_account, created_at, updated_at)
        VALUES
            (:user_id, :full_name, :address, :contact_number, :utility_type, :request_type,
             'pending', :disconnection_reason, :previous_account, NOW(), NOW())
    ");

    $stmt->execute([
        ':user_id'             => $userId,
        ':full_name'           => $fullName,
        ':address'             => $address,
        ':contact_number'      => $contactNumber,
        ':utility_type'        => $utilityType,
        ':request_type'        => $requestType,
        ':disconnection_reason'=> $requestType === 'disconnection' ? $disconnectionReason : null,
        ':previous_account'    => $requestType === 'reconnection'   ? $previousAccount     : null,
    ]);

    $requestId = (int)$pdo->lastInsertId();

    // Handle document uploads (if any)
    $savedDocs = [];

    if (!empty($_FILES['documents']) && is_array($_FILES['documents']['name'])) {
        $uploadDirRelative = 'uploads/service_requests';
        $uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . $uploadDirRelative;

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $allowedExt = ['pdf', 'jpg', 'jpeg', 'png'];

        foreach ($_FILES['documents']['name'] as $index => $name) {
            if ($_FILES['documents']['error'][$index] !== UPLOAD_ERR_OK) {
                continue;
            }

            $tmpName = $_FILES['documents']['tmp_name'][$index];
            $orig    = basename($name);
            $ext     = strtolower(pathinfo($orig, PATHINFO_EXTENSION));

            if (!in_array($ext, $allowedExt, true)) {
                continue;
            }

            $safeName   = preg_replace('/[^A-Za-z0-9_\.-]/', '_', $orig);
            $newName    = 'req_' . $requestId . '_' . time() . '_' . $index . '.' . $ext;
            $destPath   = $uploadDir . DIRECTORY_SEPARATOR . $newName;
            $publicPath = $uploadDirRelative . '/' . $newName;

            if (!move_uploaded_file($tmpName, $destPath)) {
                continue;
            }

            // Try to save document record if table exists
            try {
                $docStmt = $pdo->prepare("
                    INSERT INTO uploaded_documents (request_id, document_type, file_name, file_path, file_size, validation_status, uploaded_at)
                    VALUES (?, ?, ?, ?, ?, 'pending', NOW())
                ");
                $docStmt->execute([
                    $requestId,
                    'Uploaded Document',
                    $safeName,
                    $publicPath,
                    filesize($destPath)
                ]);
                $savedDocs[] = $publicPath;
            } catch (Exception $e) {
                // If documents table is missing, ignore but keep request
                error_log("Warning: Could not save document to database: " . $e->getMessage());
            }
        }
    }

    echo json_encode([
        'success'    => true,
        'message'    => 'Request submitted successfully.',
        'request_id' => $requestId,
        'documents'  => $savedDocs,
    ]);
} catch (Exception $e) {
    error_log("Error in submit_service_request: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error submitting request: ' . $e->getMessage()]);
}

