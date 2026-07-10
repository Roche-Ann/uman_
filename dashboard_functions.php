<?php
// dashboard_functions.php - Additional dashboard functions

/**
 * Calculate growth percentage compared to previous period
 */
function calculateGrowth($current, $previous) {
    if ($previous == 0) return 100;
    return (($current - $previous) / $previous) * 100;
}

/**
 * Get color based on status
 */
function getStatusColor($status) {
    $colors = [
        'success' => '#28a745',
        'warning' => '#ffc107',
        'danger' => '#dc3545',
        'info' => '#17a2b8',
        'primary' => '#007bff'
    ];
    
    return $colors[$status] ?? '#6c757d';
}

/**
 * Format large numbers
 */
function formatNumber($number, $decimals = 0) {
    if ($number >= 1000000) {
        return number_format($number / 1000000, 1) . 'M';
    } elseif ($number >= 1000) {
        return number_format($number / 1000, 1) . 'K';
    }
    return number_format($number, $decimals);
}

/**
 * Get time ago format
 */
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' min' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M d, Y', $time);
    }
}

/**
 * Generate performance indicator
 */
function getPerformanceIndicator($value, $thresholds = [50, 70, 90]) {
    if ($value >= $thresholds[2]) {
        return ['Excellent', 'success'];
    } elseif ($value >= $thresholds[1]) {
        return ['Good', 'info'];
    } elseif ($value >= $thresholds[0]) {
        return ['Fair', 'warning'];
    } else {
        return ['Poor', 'danger'];
    }
}

/**
 * Get dashboard quick stats
 */
function getDashboardQuickStats($pdo) {
    $stats = [];
    
    // Total active consumers
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM consumers WHERE status = 'active'");
    $stats['total_consumers'] = $stmt->fetch()['count'];
    
    // Total revenue this month
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(amount_paid), 0) as total 
        FROM payments 
        WHERE status = 'paid' 
        AND MONTH(payment_date) = MONTH(CURRENT_DATE())
        AND YEAR(payment_date) = YEAR(CURRENT_DATE())
    ");
    $stats['monthly_revenue'] = $stmt->fetch()['total'];
    
    // Pending actions
    $stmt = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM meter_readings WHERE status = 'pending') as pending_readings,
            (SELECT COUNT(*) FROM billing WHERE status = 'pending' AND due_date < CURDATE()) as overdue_bills,
            (SELECT COUNT(*) FROM requests WHERE status = 'pending') as pending_requests
    ");
    $pending = $stmt->fetch();
    $stats['pending_actions'] = $pending['pending_readings'] + $pending['overdue_bills'] + $pending['pending_requests'];
    
    // Collection rate
    $stmt = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM billing WHERE status = 'paid') as paid_bills,
            (SELECT COUNT(*) FROM billing WHERE status IN ('pending', 'overdue')) as unpaid_bills
    ");
    $rates = $stmt->fetch();
    $totalBills = $rates['paid_bills'] + $rates['unpaid_bills'];
    $stats['collection_rate'] = $totalBills > 0 ? ($rates['paid_bills'] / $totalBills) * 100 : 0;
    
    return $stats;
}
?>