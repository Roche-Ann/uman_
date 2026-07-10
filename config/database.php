<?php
// generate_bills.php
require_once 'config/database.php';

// Enable full error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$database = new Database();
$conn = $database->getConnection();

// Example: Fetch all consumers
$consumers = [];
$result = $conn->query("SELECT * FROM consumers");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $consumers[] = $row;
    }
}

// Loop through each consumer and generate bills
foreach ($consumers as $consumer) {
    $consumer_id = $consumer['id'];
    
    // Fetch the latest meter reading for water
    $water_reading = $conn->query("SELECT * FROM meter_readings WHERE consumer_id = $consumer_id AND utility_type='water' ORDER BY reading_date DESC LIMIT 1");
    $water_units = 0;
    if ($water_reading && $water_row = $water_reading->fetch_assoc()) {
        $water_units = $water_row['current_reading'] - $water_row['previous_reading'];
    }

    // Fetch the latest meter reading for electricity
    $electric_reading = $conn->query("SELECT * FROM meter_readings WHERE consumer_id = $consumer_id AND utility_type='electricity' ORDER BY reading_date DESC LIMIT 1");
    $electric_units = 0;
    if ($electric_reading && $electric_row = $electric_reading->fetch_assoc()) {
        $electric_units = $electric_row['current_reading'] - $electric_row['previous_reading'];
    }

    // Fetch rates (example: assume single rates table)
    $water_rate = $conn->query("SELECT rate_per_unit, basic_charge FROM water_rates ORDER BY id DESC LIMIT 1")->fetch_assoc();
    $electric_rate = $conn->query("SELECT rate_per_unit, basic_charge FROM electricity_rates ORDER BY id DESC LIMIT 1")->fetch_assoc();

    // Calculate charges
    $water_charge = $water_units * $water_rate['rate_per_unit'] + $water_rate['basic_charge'];
    $electric_charge = $electric_units * $electric_rate['rate_per_unit'] + $electric_rate['basic_charge'];
    $environmental_charge = 20.00;
    $total_amount = $water_charge + $electric_charge + $environmental_charge;

    // Prepare SQL to insert billing
    $sql = "INSERT INTO billing 
        (consumer_id, bill_number, utility_type, billing_month, due_date, water_consumption, electric_consumption, water_rate_per_unit, electric_rate_per_unit, water_basic_charge, electric_basic_charge, environmental_charge, total_amount, status, generated_by, generated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $conn->prepare($sql);
    $bill_number = 'BILL-' . date('Ym') . '-' . $consumer_id; // example bill number
    $utility_type = 'both'; // since we calculate both

    $stmt->bind_param(
        "isssddddddddsi", 
        $consumer_id,
        $bill_number,
        $utility_type,
        date('Y-m-01'),      // billing month (first day of current month)
        date('Y-m-d', strtotime('+15 days')), // due date 15 days from now
        $water_units,
        $electric_units,
        $water_rate['rate_per_unit'],
        $electric_rate['rate_per_unit'],
        $water_rate['basic_charge'],
        $electric_rate['basic_charge'],
        $environmental_charge,
        $total_amount,
        $status = 'pending',
        $generated_by = 1 // admin ID or system user
    );

    if ($stmt->execute()) {
        echo "Bill generated for consumer ID $consumer_id <br>";
    } else {
        echo "Error generating bill for consumer ID $consumer_id: " . $stmt->error . "<br>";
    }

    $stmt->close();
}

$database->closeConnection();
?>