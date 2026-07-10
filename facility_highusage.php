<?php
// facility_highusage.php
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$search = trim($_GET['search'] ?? '');

$conditions = ["b.booking_date >= CURDATE()"];
$params = [];

if (!empty($search)) {
    $conditions[] = "(b.event_name LIKE ? OR f.name LIKE ?)";
    $searchWildcard = '%' . $search . '%';
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
}

$whereClause = 'WHERE ' . implode(' AND ', $conditions);

// Retrieve upcoming bookings overlaid with utility status checklist
$query = "
    SELECT 
        b.*,
        f.name as facility_name,
        f.facility_type,
        f.utility_status,
        s.water_available,
        s.electricity_available,
        s.drainage_ok,
        s.lighting_ok
    FROM facility_bookings b
    JOIN public_facilities f ON b.public_facility_id = f.id
    JOIN facility_utility_status s ON f.id = s.public_facility_id
    $whereClause
    ORDER BY b.booking_date ASC, b.start_time ASC
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$bookingsList = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>High-Usage Reservation Overlay</title>
    <link rel="icon" type="image/png" href="assets/images/logocityhall.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            background: url("assets/images/cityhall.jpeg") center/cover no-repeat fixed;
            position: relative;
        }

        body::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            backdrop-filter: blur(6px);
            background: rgba(0, 0, 0, 0.35);
            z-index: 0;
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px 40px;
            transition: margin-left 0.25s ease;
            z-index: 1;
            position: relative;
        }

        .main-content.collapsed {
            margin-left: 90px;
        }

        .card {
            width: 100%;
            max-width: 1700px;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(15px);
            border-radius: 18px;
            padding: 40px;
            color: #000;
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
            border: 1px solid rgba(255,255,255,0.25);
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .dashboard-header h1 {
            color: #2c3e50;
            font-size: 32px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .dashboard-header h1 i { color: #3762c8; }

        .btn {
            padding: 11px 22px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary { background: #3762c8; color: white; }
        .btn-primary:hover { background: #2851b0; }

        .btn-outline { background: transparent; border: 1px solid #cbd5e1; color: #64748b; }
        .btn-outline:hover { background: #f8f9fa; color: #2c3e50; }

        /* Search Bar */
        .search-panel {
            background: white;
            padding: 15px 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            gap: 15px;
            align-items: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .form-control { padding: 10px 14px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 14px; outline: none; flex-grow: 1; }

        /* Table Section */
        .table-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .table-container { overflow-x: auto; width: 100%; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th {
            background: #f8f9fa;
            color: #475569;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            padding: 12px 16px;
            border-bottom: 2px solid #e2e8f0;
        }
        td { padding: 14px 16px; border-bottom: 1px solid #edf2f7; font-size: 14px; color: #2c3e50; }
        tr:hover td { background: #fcfcfc; }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 99px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-fullyready { background: #e2fbe8; color: #1e7e34; }
        .badge-partiallyready { background: #fff4e5; color: #b45309; }
        .badge-notready { background: #fde8e8; color: #bd2130; }

        .check-icon {
            margin-right: 5px;
        }
        .check-icon.ok { color: #1e7e34; }
        .check-icon.fail { color: #bd2130; }
    </style>
</head>
<body>

<?php include 'includes/utilities_sidebar.php'; ?>

<main class="main-content" id="mainContent">
    <div class="card">
        
        <!-- Header -->
        <div class="dashboard-header">
            <div>
                <h1><i class="fas fa-calendar-alt"></i> Booking Readiness Overlay</h1>
                <p style="color: #64748b; font-size: 14px; margin-top: 5px;">Verify utility readiness status for upcoming high-traffic facility reservations.</p>
            </div>
            <div>
                <a href="facility_dashboard.php" class="btn btn-outline"><i class="fas fa-chevron-left"></i> Dashboard</a>
            </div>
        </div>

        <!-- Search Bar -->
        <form method="GET" class="search-panel">
            <input type="text" name="search" class="form-control" placeholder="Search by event name or facility name..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter Schedules</button>
            <a href="facility_highusage.php" class="btn btn-outline">Clear</a>
        </form>

        <!-- Bookings Overlay Table -->
        <div class="table-section">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Upcoming Event</th>
                            <th>Public Facility Venue</th>
                            <th>Booking Date</th>
                            <th>Time Period</th>
                            <th>Expected Attendance</th>
                            <th>Overall Utility Readiness</th>
                            <th style="text-align:right;">Utility checklist Indicators</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bookingsList)): ?>
                            <tr><td colspan="7" style="text-align:center; padding:30px; color:#64748b;">No upcoming facility bookings found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($bookingsList as $bk): 
                                $badgeClass = strtolower(str_replace(' ', '', $bk['utility_status']));
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($bk['event_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($bk['facility_name']); ?> <span style="font-size:11px; color:#64748b;">(<?php echo htmlspecialchars($bk['facility_type']); ?>)</span></td>
                                <td><?php echo date('F d, Y', strtotime($bk['booking_date'])); ?></td>
                                <td><?php echo date('h:i A', strtotime($bk['start_time'])) . ' - ' . date('h:i A', strtotime($bk['end_time'])); ?></td>
                                <td><strong><?php echo number_format($bk['expected_attendance']); ?></strong> guests</td>
                                <td><span class="badge badge-<?php echo $badgeClass; ?>"><?php echo htmlspecialchars($bk['utility_status']); ?></span></td>
                                <td style="text-align:right; font-size:12px; white-space:nowrap;">
                                    <span class="check-icon <?php echo $bk['water_available'] ? 'ok' : 'fail'; ?>"><i class="fas <?php echo $bk['water_available'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i> Water</span> | 
                                    <span class="check-icon <?php echo $bk['electricity_available'] ? 'ok' : 'fail'; ?>"><i class="fas <?php echo $bk['electricity_available'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i> Elec</span> | 
                                    <span class="check-icon <?php echo $bk['drainage_ok'] ? 'ok' : 'fail'; ?>"><i class="fas <?php echo $bk['drainage_ok'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i> Drain</span> | 
                                    <span class="check-icon <?php echo $bk['lighting_ok'] ? 'ok' : 'fail'; ?>"><i class="fas <?php echo $bk['lighting_ok'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i> Light</span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>

</body>
</html>
