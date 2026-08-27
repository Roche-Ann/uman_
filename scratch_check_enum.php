<?php
require 'db.php';
$stmt = $pdo->query("SHOW COLUMNS FROM external_asset_requests LIKE 'status'");
print_r($stmt->fetch(PDO::FETCH_ASSOC));
