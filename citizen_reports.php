<?php
// citizen_reports.php
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['user_id'] ?? 3;
$userName = $_SESSION['user_name'] ?? $_SESSION['full_name'] ?? 'Citizen';

$error = '';
$success = '';

// Handle Submit Report
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $category_id = intval($_POST['category_id'] ?? 0);
        $asset_id = intval($_POST['asset_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $latitude = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null;
        $longitude = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;

        if (empty($description) || empty($location) || $category_id <= 0) {
            $error = 'Please fill in all required fields (Category, Location, and Description).';
        } else {
            try {
                // Generate unique Incident ID
                $prefix = 'INC-' . date('Ym') . '-';
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM utility_incidents WHERE incident_id LIKE ?");
                $stmt->execute([$prefix . '%']);
                $count = $stmt->fetchColumn() + 1;
                $incident_id = $prefix . str_pad($count, 4, '0', STR_PAD_LEFT);

                // Insert Incident
                $stmt = $pdo->prepare("
                    INSERT INTO utility_incidents (incident_id, category_id, description, location, latitude, longitude, status, priority, resident_id) 
                    VALUES (?, ?, ?, ?, ?, ?, 'Submitted', 'Medium', ?)
                ");
                $stmt->execute([$incident_id, $category_id, $description, $location, $latitude, $longitude, $userId]);
                $uiid = $pdo->lastInsertId();

                // Link to asset if selected
                if ($asset_id > 0) {
                    $pdo->prepare("INSERT INTO incident_asset_links (utility_incident_id, utility_asset_id) VALUES (?, ?)")->execute([$uiid, $asset_id]);
                }

                // Image Upload Handling
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $targetDir = 'uploads/incidents/';
                    if (!is_dir($targetDir)) {
                        mkdir($targetDir, 0755, true);
                    }
                    $fileExtension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
                    if (in_array($fileExtension, $allowedExtensions)) {
                        $fileName = uniqid('inc_', true) . '.' . $fileExtension;
                        $targetFilePath = $targetDir . $fileName;
                        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFilePath)) {
                            $pdo->prepare("INSERT INTO incident_images (utility_incident_id, image_path) VALUES (?, ?)")->execute([$uiid, $targetFilePath]);
                        }
                    }
                }

                // Log status
                $pdo->prepare("
                    INSERT INTO incident_status_logs (utility_incident_id, old_status, new_status, changed_by, notes) 
                    VALUES (?, NULL, 'Submitted', ?, 'Report submitted by resident.')
                ")->execute([$uiid, $userId]);

                // Create LGU notifications
                $pdo->prepare("
                    INSERT INTO incident_notifications (user_id, message) 
                    VALUES (1, ?)
                ")->execute(["New incident {$incident_id} reported: " . substr($description, 0, 50) . "..."]);

                $success = "Incident Report {$incident_id} successfully submitted!";
            } catch (PDOException $e) {
                $error = "Failed to submit report: " . $e->getMessage();
            }
        }
    } elseif ($action === 'submit_feedback') {
        $id = intval($_POST['id'] ?? 0);
        $rating = intval($_POST['rating'] ?? 5);
        $comments = trim($_POST['feedback_comments'] ?? '');

        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE utility_incidents 
                    SET rating = ?, feedback_comments = ? 
                    WHERE id = ? AND resident_id = ?
                ");
                $stmt->execute([$rating, $comments, $id, $userId]);
                $success = "Thank you! Your feedback has been recorded successfully.";
            } catch (PDOException $e) {
                $error = "Failed to submit feedback: " . $e->getMessage();
            }
        }
    }
}

// Retrieve resident's reports
$myReports = [];
try {
    $stmt = $pdo->prepare("
        SELECT i.*, c.name as category_name, link.utility_asset_id, a.asset_id as asset_code
        FROM utility_incidents i 
        JOIN incident_categories c ON i.category_id = c.id
        LEFT JOIN incident_asset_links link ON i.id = link.utility_incident_id
        LEFT JOIN utility_assets a ON link.utility_asset_id = a.id
        WHERE i.resident_id = ?
        ORDER BY i.created_at DESC
    ");
    $stmt->execute([$userId]);
    $myReports = $stmt->fetchAll();
} catch (Exception $e) {
    // Fallback
}

// Fetch categories for form selection
$categories = $pdo->query("SELECT * FROM incident_categories ORDER BY name ASC")->fetchAll();
// Fetch assets for selection
$assets = $pdo->query("SELECT id, name, asset_id FROM utility_assets ORDER BY asset_id ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Report Tracking</title>
    <link rel="icon" type="image/png" href="assets/images/logocityhall.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Leaflet CSS & JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

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
            flex-wrap: wrap;
            gap: 20px;
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

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error { background-color: #fde8e8; color: #c0392b; border: 1px solid #f8b4b4; }
        .alert-success { background-color: #e2fbe8; color: #1e7e34; border: 1px solid #b8f0c5; }

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

        .badge-submitted { background: #e2e8f0; color: #475569; }
        .badge-underreview { background: #e0f2fe; color: #0369a1; }
        .badge-verified { background: #fef3c7; color: #b45309; }
        .badge-forwardedtomaintenancesystem { background: #e0e7ff; color: #4338ca; }
        .badge-inprogress { background: #fffbeb; color: #d97706; }
        .badge-resolved { background: #dcfce7; color: #15803d; }
        .badge-closed { background: #f1f5f9; color: #64748b; }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(4px);
        }
        .modal.open { display: flex; }
        .modal-content {
            background: white;
            width: 90%;
            max-width: 600px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            animation: modalFadeIn 0.3s ease;
        }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .modal-header { padding: 20px 24px; background: #f8f9fa; border-bottom: 1px solid #edf2f7; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { font-size: 18px; color: #2c3e50; }
        .modal-close { background: transparent; border: none; font-size: 18px; cursor: pointer; color: #64748b; }
        .modal-body { padding: 24px; max-height: 75vh; overflow-y: auto; }
        .modal-footer { padding: 16px 24px; background: #f8f9fa; border-top: 1px solid #edf2f7; display: flex; justify-content: flex-end; gap: 12px; }

        #map { width: 100%; height: 200px; border-radius: 8px; margin-top: 5px; border: 1px solid #cbd5e1; }

        .form-control { padding: 10px 14px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 14px; outline: none; width:100%; }
        .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 15px; }
        .form-group label { font-size: 12px; font-weight: 600; color: #64748b; }

        /* Timeline UI */
        .timeline {
            position: relative;
            padding-left: 30px;
            margin-top: 20px;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 5px;
            top: 5px;
            width: 2px;
            height: 90%;
            background: #e2e8f0;
        }
        .timeline-step {
            position: relative;
            margin-bottom: 20px;
        }
        .timeline-step::before {
            content: '';
            position: absolute;
            left: -30px;
            top: 4px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #cbd5e1;
            border: 2px solid white;
        }
        .timeline-step.active::before {
            background: #3762c8;
        }
        .timeline-title { font-size: 13px; font-weight: 600; color: #2c3e50; }
        .timeline-desc { font-size: 11px; color: #64748b; margin-top: 2px; }

        /* Star Rating */
        .stars { display: flex; gap: 8px; margin: 10px 0; }
        .star-btn { font-size: 24px; color: #cbd5e1; cursor: pointer; border: none; background: transparent; }
        .star-btn.selected { color: #f1c40f; }
    </style>
</head>
<body>

<?php include 'includes/utilities_sidebar.php'; ?>

<main class="main-content" id="mainContent">
    <div class="card">
        
        <!-- Header -->
        <div class="dashboard-header">
            <div>
                <h1><i class="fas fa-file-alt"></i> My Incident Reports</h1>
                <p style="color: #64748b; font-size: 14px; margin-top: 5px;">File new utility breakdowns and track resolution progress timelines.</p>
            </div>
            <div>
                <button class="btn btn-primary" onclick="openCreateModal()"><i class="fas fa-plus"></i> File Incident Report</button>
            </div>
        </div>

        <!-- Alerts -->
        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- Reports list table -->
        <div class="table-section">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Report ID</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Location</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Date Filed</th>
                            <th style="text-align:right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($myReports)): ?>
                            <tr><td colspan="8" style="text-align:center; padding:30px; color:#64748b;">You have not filed any utility incident reports yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($myReports as $rep): 
                                $statusBadge = strtolower(str_replace(' ', '', $rep['status']));
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($rep['incident_id']); ?></strong></td>
                                <td><?php echo htmlspecialchars($rep['category_name']); ?></td>
                                <td><?php echo htmlspecialchars(substr($rep['description'], 0, 45)) . (strlen($rep['description']) > 45 ? '...' : ''); ?></td>
                                <td><?php echo htmlspecialchars($rep['location']); ?></td>
                                <td><span class="badge" style="background:#f1f5f9; color:#475569;"><?php echo htmlspecialchars($rep['priority']); ?></span></td>
                                <td><span class="badge badge-<?php echo $statusBadge; ?>"><?php echo htmlspecialchars($rep['status']); ?></span></td>
                                <td><?php echo date('M d, Y', strtotime($rep['created_at'])); ?></td>
                                <td style="text-align:right;">
                                    <button class="btn btn-outline" style="padding:4px 8px; font-size:12px;" onclick='openTrackModal(<?php echo json_encode($rep); ?>)'><i class="fas fa-route"></i> Track progress</button>
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

<!-- CREATE INCIDENT REPORT MODAL -->
<div id="createModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>File Incident Report</h3>
            <button class="modal-close" onclick="closeModal('createModal')">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                    <div class="form-group">
                        <label>Issue Category *</label>
                        <select name="category_id" class="form-control" required>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Dangling / Link Asset (Optional)</label>
                        <select name="asset_id" class="form-control">
                            <option value="">Unknown / None</option>
                            <?php foreach ($assets as $ast): ?>
                                <option value="<?php echo $ast['id']; ?>"><?php echo htmlspecialchars($ast['name'] . ' ('.$ast['asset_id'].')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Brief Description *</label>
                    <textarea name="description" class="form-control" rows="3" required placeholder="Describe the utility fault in detail..."></textarea>
                </div>

                <div class="form-group">
                    <label>Location Landmark / Address *</label>
                    <input type="text" name="location" class="form-control" required placeholder="e.g. Near Brgy 386 Plaza Gate">
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                    <div class="form-group">
                        <label>GPS Latitude (Optional)</label>
                        <input type="number" step="any" name="latitude" id="latInput" class="form-control" readonly>
                    </div>
                    <div class="form-group">
                        <label>GPS Longitude (Optional)</label>
                        <input type="number" step="any" name="longitude" id="lngInput" class="form-control" readonly>
                    </div>
                </div>

                <div class="form-group">
                    <label>Pinpoint Coordinates on Map</label>
                    <div id="map"></div>
                </div>

                <div class="form-group" style="margin-top:15px;">
                    <label>Attach Photo Proof (Optional)</label>
                    <input type="file" name="image" accept="image/*" class="form-control">
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('createModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">File Report</button>
            </div>
        </form>
    </div>
</div>

<!-- TRACK PROGRESS TIMELINE MODAL -->
<div id="trackModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="track-title">Track Report</h3>
            <button class="modal-close" onclick="closeModal('trackModal')">&times;</button>
        </div>
        <div class="modal-body">
            
            <h4 style="color:#2c3e50; font-size:14px; font-weight:600;">Incident Description:</h4>
            <p id="track-desc" style="font-size:12px; color:#64748b; margin-top:5px;"></p>

            <h4 style="color:#2c3e50; font-size:14px; font-weight:600; margin-top:20px;">Resolution Tracking Timeline:</h4>
            
            <div class="timeline" id="track-timeline">
                <!-- Javascript populated steps -->
            </div>

            <!-- Feedback section -->
            <div id="feedback-section" style="border-top:1px solid #edf2f7; margin-top:20px; padding-top:15px; display:none;">
                <h4 style="color:#2c3e50; font-size:14px; font-weight:600;">Rate LGU Resolution Service</h4>
                
                <form method="POST" id="feedback-form">
                    <input type="hidden" name="action" value="submit_feedback">
                    <input type="hidden" id="feedback-id" name="id">
                    <input type="hidden" id="feedback-rating-val" name="rating" value="5">

                    <div class="stars">
                        <button type="button" class="star-btn selected" onclick="setRating(1)"><i class="fas fa-star"></i></button>
                        <button type="button" class="star-btn selected" onclick="setRating(2)"><i class="fas fa-star"></i></button>
                        <button type="button" class="star-btn selected" onclick="setRating(3)"><i class="fas fa-star"></i></button>
                        <button type="button" class="star-btn selected" onclick="setRating(4)"><i class="fas fa-star"></i></button>
                        <button type="button" class="star-btn selected" onclick="setRating(5)"><i class="fas fa-star"></i></button>
                    </div>

                    <div class="form-group">
                        <label>Resolution Comments</label>
                        <textarea id="feedback-comments" name="feedback_comments" class="form-control" rows="2" placeholder="Leave a comment regarding response times or LGU service..."></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="padding:6px 12px; font-size:12px;">Submit Feedback</button>
                </form>

                <div id="feedback-saved-box" style="display:none; font-size:13px; color:#2ecc71;">
                    <i class="fas fa-check-circle"></i> Feedback submitted! Rating: <span id="feedback-stars-count"></span> stars.
                    <p style="color:#64748b; font-style:italic; font-size:12px; margin-top:5px;" id="feedback-comments-text"></p>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
    let map = null;
    let marker = null;

    function closeModal(id) {
        document.getElementById(id).classList.remove('open');
    }

    function openCreateModal() {
        document.getElementById('createModal').classList.add('open');
        
        // Initialize map asynchronously when opening modal
        if (!map) {
            setTimeout(() => {
                map = L.map('map').setView([14.5995, 120.9842], 13);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors'
                }).addTo(map);

                map.on('click', function(e) {
                    document.getElementById('latInput').value = e.latlng.lat.toFixed(6);
                    document.getElementById('lngInput').value = e.latlng.lng.toFixed(6);

                    if (marker) {
                        marker.setLatLng(e.latlng);
                    } else {
                        marker = L.marker(e.latlng).addTo(map);
                    }
                });
            }, 200);
        }
    }

    function openTrackModal(rep) {
        document.getElementById('track-title').innerText = "Track Report " + rep.incident_id;
        document.getElementById('track-desc').innerText = rep.description;

        const timeline = document.getElementById('track-timeline');
        timeline.innerHTML = '';

        // Status Timeline array
        const steps = ['Submitted', 'Under Review', 'Verified', 'Forwarded to Maintenance System', 'In Progress', 'Resolved', 'Closed'];
        const currentIdx = steps.indexOf(rep.status);

        steps.forEach((step, idx) => {
            const stepEl = document.createElement('div');
            stepEl.className = 'timeline-step' + (idx <= currentIdx ? ' active' : '');
            stepEl.innerHTML = `
                <div class="timeline-title">${step}</div>
                <div class="timeline-desc">${idx <= currentIdx ? 'Completed status validation step.' : 'Awaiting progress verification.'}</div>
            `;
            timeline.appendChild(stepEl);
        });

        // Feedback section toggling
        const feedbackSection = document.getElementById('feedback-section');
        const feedbackForm = document.getElementById('feedback-form');
        const feedbackSaved = document.getElementById('feedback-saved-box');

        if (rep.status === 'Resolved' || rep.status === 'Closed') {
            feedbackSection.style.display = 'block';
            if (rep.rating) {
                feedbackForm.style.display = 'none';
                feedbackSaved.style.display = 'block';
                document.getElementById('feedback-stars-count').innerText = rep.rating;
                document.getElementById('feedback-comments-text').innerText = rep.feedback_comments || 'No comments left.';
            } else {
                feedbackForm.style.display = 'block';
                feedbackSaved.style.display = 'none';
                document.getElementById('feedback-id').value = rep.id;
                setRating(5);
            }
        } else {
            feedbackSection.style.display = 'none';
        }

        document.getElementById('trackModal').classList.add('open');
    }

    function setRating(rating) {
        document.getElementById('feedback-rating-val').value = rating;
        const starBtns = document.querySelectorAll('.star-btn');
        starBtns.forEach((btn, idx) => {
            if (idx < rating) {
                btn.classList.add('selected');
            } else {
                btn.classList.remove('selected');
            }
        });
    }
</script>

</body>
</html>
