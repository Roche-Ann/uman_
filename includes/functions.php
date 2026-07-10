<?php
// includes/functions.php

// Get consumer by user ID
function getConsumerByUserId($userId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM consumers WHERE user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

// Generate payment reference
function generatePaymentRef() {
    return 'PAY-' . date('YmdHis') . '-' . strtoupper(uniqid());
}

// Generate bill number
function generateBillNumber($utilityType = 'water') {
    $prefix = ($utilityType === 'electricity') ? 'ELEC-' : '';
    return $prefix . 'BILL-' . date('Ym') . '-' . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
}

// Get water rate based on consumption
function getWaterRate($consumption) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT * FROM water_rates 
        WHERE (min_consumption <= ? AND (max_consumption IS NULL OR max_consumption >= ?))
        AND status = 'active' 
        ORDER BY min_consumption DESC 
        LIMIT 1
    ");
    $stmt->execute([$consumption, $consumption]);
    return $stmt->fetch();
}

// Get electricity rate based on consumption
function getElectricityRate($consumption) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT * FROM electricity_rates 
        WHERE (min_consumption <= ? AND (max_consumption IS NULL OR max_consumption >= ?))
        AND status = 'active' 
        ORDER BY min_consumption DESC 
        LIMIT 1
    ");
    $stmt->execute([$consumption, $consumption]);
    return $stmt->fetch();
}

// Check overdue bills
function checkOverdueBills() {
    global $pdo;
    $stmt = $pdo->prepare("
        UPDATE billing 
        SET status = 'overdue' 
        WHERE status = 'pending' 
        AND due_date < CURDATE()
    ");
    return $stmt->execute();
}

// Calculate service fee
function calculateServiceFee($amount, $paymentMethod) {
    $fees = [
        'gcash' => 0.015,  // 1.5%
        'grab_pay' => 0.02, // 2%
        'credit_card' => 0.025, // 2.5%
        'online_banking' => 0.025, // 2.5%
        'bank_transfer' => 0,
        'cash' => 0,
        'check' => 0
    ];
    
    $feePercentage = $fees[$paymentMethod] ?? 0;
    return $amount * $feePercentage;
}

// Format currency
function formatCurrency($amount) {
    return '₱ ' . number_format($amount, 2);
}