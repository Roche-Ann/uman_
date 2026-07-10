<?php
require_once dirname(__DIR__) . '/db.php';
$pdo = db();
$sql = file_get_contents(dirname(__DIR__) . '/sql/road_utility_coordination.sql');
$pdo->exec($sql);
echo "SQL executed successfully.\n";
