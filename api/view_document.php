<?php
// api/view_document.php
// Secure document viewer with permission checks
header('X-Content-Type-Options: nosniff');

require_once '../includes/auth.php';
require_once '../includes/db.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo "Unauthorized";
    exit();
}

$docId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$userId = $_SESSION['user_id'];
$isEmp = isEmployee();

if (!$docId || $docId <= 0) {
    http_response_code(400);
    echo "Invalid document ID";
    exit();
}

try {
    // Get document info
    $stmt = $pdo->prepare("
        SELECT ud.*, sr.user_id as request_user_id 
        FROM uploaded_documents ud
        JOIN service_requests sr ON ud.request_id = sr.id
        WHERE ud.id = ?
    ");
    $stmt->execute([$docId]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$doc) {
        http_response_code(404);
        echo "Document not found";
        exit();
    }

    // Permission check: User must be employee OR the document owner
    if (!$isEmp && $doc['request_user_id'] != $userId) {
        http_response_code(403);
        echo "Access denied - this document does not belong to you";
        exit();
    }

    // Security: Prevent directory traversal
    $filePath = $doc['file_path'];
    
    // Normalize the path to prevent traversal attacks
    $basePath = dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR;
    $realPath = realpath($basePath . $filePath);
    
    if ($realPath === false || strpos($realPath, $basePath) !== 0) {
        http_response_code(400);
        echo "Invalid file path";
        exit();
    }

    if (!file_exists($realPath)) {
        http_response_code(404);
        echo "File not found";
        exit();
    }

    // Get file info for proper mime type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $realPath);
    finfo_close($finfo);

    // Allow only safe file types
    $safeMimes = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/jpg',
        'application/octet-stream'
    ];

    if (!in_array($mimeType, $safeMimes)) {
        $mimeType = 'application/octet-stream';
    }

    // Log document access
    error_log("Document accessed: ID=$docId, RequestID={$doc['request_id']}, UserID=$userId, IsEmployee=$isEmp");

    // Serve the document
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . filesize($realPath));
    header('Content-Disposition: inline; filename="' . basename($doc['file_name']) . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    
    readfile($realPath);
    exit();

} catch (Exception $e) {
    error_log("view_document error: " . $e->getMessage());
    http_response_code(500);
    echo "Error retrieving document";
    exit();
}
?>
