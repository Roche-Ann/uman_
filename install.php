<?php
// install.php - One-time installation script
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            if (savedTheme === 'dark') {
                document.documentElement.classList.add('dark-theme');
            }
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LGU Portal Installation</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .success {
            color: green;
            padding: 10px;
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 5px;
        }
        .error {
            color: #721c24;
            padding: 10px;
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>LGU Portal Installation</h1>
        
        <?php
        // Check if already installed
        try {
            require_once 'config/database.php';
            global $database;
            
            $missingTables = $database->checkTables();
            
            if (empty($missingTables)) {
                echo '<div class="success">✓ Database already installed and ready!</div>';
                echo '<p><a href="login.php" class="btn">Go to Login</a></p>';
            } else {
                echo '<div class="error">✗ Database tables missing: ' . implode(', ', $missingTables) . '</div>';
                echo '<p>Please import the SQL file manually:</p>';
                echo '<ol>';
                echo '<li>Open phpMyAdmin (http://localhost/phpmyadmin)</li>';
                echo '<li>Create a new database named "lgu_portal"</li>';
                echo '<li>Import the file: <code>sql/lgu_portal.sql</code></li>';
                echo '<li><a href="install.php?check=1" class="btn">Check Again</a></li>';
                echo '</ol>';
            }
            
        } catch (Exception $e) {
            echo '<div class="error">✗ Database connection failed: ' . $e->getMessage() . '</div>';
            echo '<p>Please make sure:</p>';
            echo '<ol>';
            echo '<li>XAMPP is running</li>';
            echo '<li>Apache and MySQL are started</li>';
            echo '<li>MySQL credentials are correct in config/database.php</li>';
            echo '</ol>';
        }
        ?>
        
        <h3>Default Login Credentials:</h3>
        <ul>
            <li><strong>Employee:</strong> admin@lgu.gov.ph / admin123</li>
            <li><strong>Citizen:</strong> juan.dela.cruz@email.com / citizen123</li>
            <li><strong>Citizen:</strong> maria.santos@email.com / citizen123</li>
        </ul>
    </div>
</body>
</html>