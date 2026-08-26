<?php
$pageTitle = 'Dashboard';
?>

<div class="p-4">
    <h2 class="mb-4">Dashboard</h2>
    
    <?php if (in_array($_SESSION['role'], ['admin', 'zoning_officer', 'building_official'])): ?>
        <!-- Staff Dashboard -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5>Total Applications</h5>
                        <h2><?php echo $dashboardData['stats']['total_applications']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <h5>Pending Review</h5>
                        <h2><?php echo $dashboardData['stats']['pending_review']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5>Approved</h5>
                        <h2><?php echo $dashboardData['stats']['approved']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <h5>Rejected</h5>
                        <h2><?php echo $dashboardData['stats']['rejected']; ?></h2>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5>Recent Applications</h5>
            </div>
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Application #</th>
                            <th>Project Name</th>
                            <th>Applicant</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dashboardData['recent_applications'] as $app): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($app['application_number']); ?></td>
                            <td><?php echo htmlspecialchars($app['project_name']); ?></td>
                            <td><?php echo htmlspecialchars($app['applicant_first_name'] . ' ' . $app['applicant_last_name']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo Helper::getStatusBadge($app['status']); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $app['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo Helper::formatDate($app['created_at']); ?></td>
                            <td>
                                <a href="/lgu-urban-planning/permit/view.php?id=<?php echo $app['id']; ?>" class="btn btn-sm btn-primary">View</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
    <?php else: ?>
        <!-- Applicant Dashboard -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5>My Applications</h5>
                        <h2><?php echo count($dashboardData['my_applications']); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5>Unread Messages</h5>
                        <h2><?php echo $dashboardData['unread_messages']; ?></h2>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5>My Applications</h5>
            </div>
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Application #</th>
                            <th>Project Name</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($dashboardData['my_applications'])): ?>
                            <tr>
                                <td colspan="5" class="text-center">No applications yet. <a href="/lgu-urban-planning/applicant/apply.php">Submit your first application</a></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($dashboardData['my_applications'] as $app): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($app['application_number']); ?></td>
                                <td><?php echo htmlspecialchars($app['project_name']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo Helper::getStatusBadge($app['status']); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $app['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo Helper::formatDate($app['created_at']); ?></td>
                                <td>
                                    <a href="/lgu-urban-planning/applicant/view.php?id=<?php echo $app['id']; ?>" class="btn btn-sm btn-primary">View</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

