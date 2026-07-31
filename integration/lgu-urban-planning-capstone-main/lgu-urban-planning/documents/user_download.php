<?php
/**
 * Document Download / View Handler
 *
 * ?file=<file_path_or_name>&name=<original_name>          → forces download
 * ?file=<file_path_or_name>&name=<original_name>&view=1   → serves inline (PDF/image preview)
 */

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';

$auth = new Auth();
$auth->requireRole('applicant');

/* ── 1. Get parameters ───────────────────────────────────────────────────── */
$fileParam    = $_GET['file'] ?? '';
$originalName = $_GET['name'] ?? '';

if (empty($fileParam)) {
    http_response_code(400);
    exit('Missing file parameter.');
}

/* ── 2. Resolve the physical file path ──────────────────────────────────── */
$uploadBase = 'C:\\xampp\\htdocs\\lgu-urban-planning\\uploads\\permits';

// Try 1: treat $fileParam as-is (may already be a full path)
$filePath = $fileParam;

if (!file_exists($filePath)) {
    // Try 2: base + stored value
    $filePath = rtrim($uploadBase, '/\\') . DIRECTORY_SEPARATOR . ltrim($fileParam, '/\\');
}

if (!file_exists($filePath)) {
    // Try 3: base + just the filename portion
    $filePath = rtrim($uploadBase, '/\\') . DIRECTORY_SEPARATOR . basename($fileParam);
}

if (!file_exists($filePath)) {
    http_response_code(404);
    exit('File not found on server.');
}

/* ── 3. Path traversal guard ─────────────────────────────────────────────── */
$realBase = realpath($uploadBase);
$realFile = realpath($filePath);

if ($realBase === false || $realFile === false || strpos($realFile, $realBase) !== 0) {
    http_response_code(403);
    exit('Access denied.');
}

/* ── 4. Determine file name and MIME type ────────────────────────────────── */
$fileName = !empty($originalName) ? basename($originalName) : basename($realFile);
$ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

$mimeMap = [
    'pdf'  => 'application/pdf',
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls'  => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
];
$mime = $mimeMap[$ext] ?? 'application/octet-stream';

/* ── 5. Stream the file ──────────────────────────────────────────────────── */
$isView      = isset($_GET['view']) && $_GET['view'] == '1';
$disposition = $isView ? 'inline' : 'attachment';
$safeFileName = rawurlencode($fileName);

if (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: '        . $mime);
header('Content-Disposition: ' . $disposition . '; filename="' . $fileName . '"; filename*=UTF-8\'\'' . $safeFileName);
header('Content-Length: '      . filesize($realFile));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

readfile($realFile);
exit;