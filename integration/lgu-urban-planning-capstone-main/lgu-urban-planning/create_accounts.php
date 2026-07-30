<?php
require_once __DIR__ . '/core/Database.php';

$db = Database::getInstance();

//////////////////////////////////////////////////////////////
// SUPER ADMIN
//////////////////////////////////////////////////////////////

$username = "superadmin";
$email = "superadmin@lgu.gov.ph";
$password = "superadmin123";
$first_name = "Super";
$last_name = "Admin";
$role = "superadmin";
$is_verified = 1;
$is_active = 1;

$password_hash = password_hash($password, PASSWORD_DEFAULT);

$sql = "INSERT INTO users (
            username, 
            email, 
            password_hash, 
            first_name, 
            last_name, 
            role, 
            is_verified, 
            is_active
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

try {
    $db->query($sql, [
        $username, 
        $email, 
        $password_hash, 
        $first_name, 
        $last_name, 
        $role, 
        $is_verified, 
        $is_active
    ]);
    echo "<h3>Super Admin account created successfully!</h3>";
    echo "Username: <b>superadmin</b><br>";
    echo "Password: <b>superadmin123</b><br><br>";
} catch (PDOException $e) {
    echo "Super Admin Error: " . $e->getMessage() . "<br><br>";
}


//////////////////////////////////////////////////////////////
// ZONING OFFICER
//////////////////////////////////////////////////////////////

$username = "zoningofficer";
$email = "zoningofficer@lgu.gov.ph";
$password = "zoningofficer123";
$first_name = "Zoning";
$last_name = "Officer";
$role = "zoning_officer";
$is_verified = 1;
$is_active = 1;

$password_hash = password_hash($password, PASSWORD_DEFAULT);

$sql = "INSERT INTO users (
            username, 
            email, 
            password_hash, 
            first_name, 
            last_name, 
            role, 
            is_verified, 
            is_active
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

try {
    $db->query($sql, [
        $username, 
        $email, 
        $password_hash, 
        $first_name, 
        $last_name, 
        $role, 
        $is_verified, 
        $is_active
    ]);
    echo "<h3>Zoning Officer account created successfully!</h3>";
    echo "Username: <b>zoningofficer</b><br>";
    echo "Password: <b>zoningofficer123</b><br><br>";
} catch (PDOException $e) {
    echo "Zoning Officer Error: " . $e->getMessage() . "<br><br>";
}

//////////////////////////////////////////////////////////////
// BUILDING OFFICIAL
//////////////////////////////////////////////////////////////

$username = "buildingofficial";
$email = "buildingofficial@lgu.gov.ph";
$password = "buildingofficial123";
$first_name = "Building";
$last_name = "Official";
$role = "building_official";
$is_verified = 1;
$is_active = 1;

$password_hash = password_hash($password, PASSWORD_DEFAULT);

$sql = "INSERT INTO users (
            username, 
            email, 
            password_hash, 
            first_name, 
            last_name, 
            role, 
            is_verified, 
            is_active
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

try {
    $db->query($sql, [
        $username, 
        $email, 
        $password_hash, 
        $first_name, 
        $last_name, 
        $role, 
        $is_verified, 
        $is_active
    ]);
    echo "<h3>Building Official account created successfully!</h3>";
    echo "Username: <b>buildingofficial</b><br>";
    echo "Password: <b>buildingofficial123</b><br><br>";
} catch (PDOException $e) {
    echo "Building Official Error: " . $e->getMessage() . "<br><br>";
}

//////////////////////////////////////////////////////////////
// ASSESSOR
//////////////////////////////////////////////////////////////

$username = "assessor";
$email = "assessor@lgu.gov.ph";
$password = "assessor123";
$first_name = "Assessor";
$last_name = "Juan";
$role = "assessor";
$is_verified = 1;
$is_active = 1;

$password_hash = password_hash($password, PASSWORD_DEFAULT);

$sql = "INSERT INTO users (
            username, 
            email, 
            password_hash, 
            first_name, 
            last_name, 
            role, 
            is_verified, 
            is_active
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

try {
    $db->query($sql, [
        $username, 
        $email, 
        $password_hash, 
        $first_name, 
        $last_name, 
        $role, 
        $is_verified, 
        $is_active
    ]);
    echo "<h3>Assessor account created successfully!</h3>";
    echo "Username: <b>assessor</b><br>";
    echo "Password: <b>assessor123</b><br><br>";
} catch (PDOException $e) {
    echo "Assessor Error: " . $e->getMessage() . "<br><br>";
}

echo "<p style='color:red;'><b>Please delete this file after running it.</b></p>";
?>