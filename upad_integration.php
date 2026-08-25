<?php
/**
 * UMAN Staff: Urban Planning (UPAD) Integration Hub
 */
require_once 'includes/auth.php';
require_once 'includes/db.php';
define('UMAN_HTML_PAGE', true);
require_once __DIR__ . '/api/integration_config.php';

if (!isLoggedIn() || !isEmployee()) {
    header('Location: login.php');
    exit();
}

$errors = [];
$successes = [];

// Ensure table exists
try {
    $sql = file_get_contents(__DIR__ . '/sql/upad_integration.sql');
    $pdo->exec($sql);
} catch (Throwable $e) {}

// --- Handle Callback Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_callback') {
    // ... (your existing callback logic – unchanged)
    // It's already complete in your code.
}

// --- Handle AI Validation ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'run_ai_validation') {
    $refId = trim($_POST['reference_id'] ?? '');
    if (empty($refId)) {
        $errors[] = "Reference ID is required.";
    } else {
        require_once __DIR__ . '/api/v1/inspection_ai.php';
        $aiResult = runInspectionAIValidation($refId, $pdo);
        if ($aiResult['success']) {
            if ($aiResult['approved']) {
                $successes[] = "AI Validation Success: Request $refId was automatically approved based on real asset inventory and closed incidents!";
            } else {
                $successes[] = "AI Validation: $refId decision: {$aiResult['decision']} (Score: {$aiResult['score']}).";
            }
        } else {
            $errors[] = "AI System Error: " . $aiResult['error'];
        }
    }
}

// --- Seed Test Request ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'seed_test_request') {
    // ... (your existing code for creating test request – it's correct)
    // It uses the same table, so it's fine.
}

// --- Statistics ---
$totalCount = (int) $pdo->query("SELECT COUNT(*) FROM upad_inspection_requests")->fetchColumn();
$pendingCount = (int) $pdo->query("SELECT COUNT(*) FROM upad_inspection_requests WHERE status = 'pending'")->fetchColumn();
$completedCount = (int) $pdo->query("SELECT COUNT(*) FROM upad_inspection_requests WHERE status IN ('approved','conditional','rejected','delivered')")->fetchColumn();
$failedCount = (int) $pdo->query("SELECT COUNT(*) FROM upad_inspection_requests WHERE status = 'failed'")->fetchColumn();

// Fetch requests
$requests = $pdo->query("SELECT * FROM upad_inspection_requests ORDER BY created_at DESC LIMIT 100")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Urban Planning Integration Hub</title>
    <link rel="icon" type="image/png" href="assets/images/logocityhall.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Your existing CSS styles – keep them as they are -->
    <style>
        /* ... (your complete style block) ... */
    </style>
</head>
<body>
    <?php include 'includes/utilities_sidebar.php'; ?>
    <main class="main-content">
        <div class="page-wrapper">
            <!-- Header, alerts, stats, API info, table, modals – all as in your current code -->
            <!-- ... (keep your existing HTML structure) ... -->
        </div>
    </main>
    <!-- Modals and scripts – keep them -->
</body>
</html>