<?php
/**
 * Database Configuration
 *
 * Copy this file to `database.php` in the same folder and fill in your
 * local credentials. `database.php` is gitignored — never commit real
 * credentials.
 */

return [
    'host' => 'localhost',
    'dbname' => 'upad_lgu_urban_planning',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];
