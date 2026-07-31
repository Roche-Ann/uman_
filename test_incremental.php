<?php
// Test incremental inclusion of actual file content
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing incremental inclusion of external_asset_requests.php...<br><br>";

// Read the actual file
$filePath = __DIR__ . '/external_asset_requests.php';
$content = file_get_contents($filePath);

// Split into chunks
$chunks = str_split($content, 10000);
echo "Total file size: " . strlen($content) . " bytes<br>";
echo "Number of chunks: " . count($chunks) . "<br><br>";

// Test each chunk
for ($i = 0; $i < count($chunks); $i++) {
    echo "Testing chunk $i (" . strlen($chunks[$i]) . " bytes)... ";
    
    // Check for problematic patterns
    if (strpos($chunks[$i], '<?php') !== false) {
        echo "contains PHP tag<br>";
    } elseif (strpos($chunks[$i], '?>') !== false) {
        echo "contains PHP closing tag<br>";
    } else {
        echo "OK<br>";
    }
}

echo "<br>Now testing actual file execution...<br>";

try {
    include $filePath;
    echo "✓ File executed successfully";
} catch (Throwable $e) {
    echo "✗ Error: " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "File: " . htmlspecialchars($e->getFile()) . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
}
?>
