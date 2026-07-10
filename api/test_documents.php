<?php
// api/test_documents.php - Debug document uploads and viewing
header('Content-Type: application/json');

require_once '../includes/auth.php';
require_once '../includes/db.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$userId = $_SESSION['user_id'];
$isEmp = isEmployee();

// Test what documents exist for this user's requests
$tests = [];

try {
    // Test 1: Check if uploaded_documents table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'uploaded_documents'");
    $tableExists = $stmt->fetch();
    $tests['table_exists'] = $tableExists ? 'YES' : 'NO - Table missing!';
    
    // Test 2: Get user's requests
    if ($isEmp) {
        $stmt = $pdo->query("SELECT id FROM service_requests ORDER BY id DESC LIMIT 1");
    } else {
        $stmt = $pdo->prepare("SELECT id FROM service_requests WHERE user_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$userId]);
    }
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    $testRequestId = $request['id'] ?? null;
    $tests['latest_request_id'] = $testRequestId;
    
    // Test 3: Check documents in database for latest request
    if ($testRequestId) {
        $stmt = $pdo->prepare("SELECT id, request_id, document_type, file_name, file_path, file_size, validation_status FROM uploaded_documents WHERE request_id = ?");
        $stmt->execute([$testRequestId]);
        $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $tests['documents_in_db'] = count($docs);
        $tests['documents'] = [];
        
        foreach ($docs as $doc) {
            $filePath = dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . $doc['file_path'];
            $fileExists = file_exists($filePath);
            $fileSize = $fileExists ? filesize($filePath) : null;
            
            $tests['documents'][] = [
                'id' => $doc['id'],
                'request_id' => $doc['request_id'],
                'type' => $doc['document_type'],
                'file_name' => $doc['file_name'],
                'file_path' => $doc['file_path'],
                'db_size' => $doc['file_size'],
                'disk_size' => $fileSize,
                'exists_on_disk' => $fileExists,
                'full_path' => $filePath,
                'validation_status' => $doc['validation_status']
            ];
        }
    }
    
    // Test 4: Check permissions
    $tests['user_info'] = [
        'user_id' => $userId,
        'is_employee' => $isEmp,
        'role' => $isEmp ? 'ADMIN' : 'CITIZEN'
    ];
    
    // Test 5: Check uploads directory
    $uploadDir = dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'service_requests';
    $uploadDirExists = is_dir($uploadDir);
    $tests['upload_directory'] = [
        'path' => $uploadDir,
        'exists' => $uploadDirExists,
        'writable' => $uploadDirExists ? is_writable($uploadDir) : false
    ];
    
    if ($uploadDirExists) {
        $files = scandir($uploadDir);
        $files = array_diff($files, ['.', '..']);
        $tests['files_on_disk'] = count($files);
        $tests['file_list'] = array_slice($files, 0, 10); // First 10 files
    }
    
    echo json_encode([
        'success' => true,
        'tests' => $tests
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'tests' => $tests
    ]);
}
?>
