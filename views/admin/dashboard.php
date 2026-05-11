<?php
session_start();
require_once __DIR__ . '/../../config/auth.php';
if (!isset($_SESSION['user_id'])) { header('Location: /senior_care/views/auth/login.php'); exit(); }
requireRole(['Admin']);
require_once __DIR__ . '/../../models/Admin.php';
$stats = (new Admin())->getDashboardStats();
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/navbar.php';
?>
<div class="container py-4">
    <div class="welcome-banner">
        <h2>Admin Dashboard</h2>
        <p class="mb-0">Monitor network safety and activity in real time.</p>
    </div>
    <div class="row g-3">
        <div class="col-md-4"><div class="card stat-card"><div class="number"><?= (int)$stats['users'] ?></div><div>Total Users</div></div></div>
        <div class="col-md-4"><div class="card stat-card"><div class="number"><?= (int)$stats['today_visits'] ?></div><div>Visits Today</div></div></div>
        <div class="col-md-4"><div class="card stat-card"><div class="number"><?= (int)$stats['pending_approvals'] ?></div><div>Pending Approvals</div></div></div>
        <div class="col-md-6"><div class="card stat-card"><div class="number"><?= (int)$stats['open_emergencies'] ?></div><div>Emergency Alerts</div></div></div>
        <div class="col-md-6"><div class="card stat-card"><div class="number"><?= number_format((float)$stats['platform_revenue'], 2) ?></div><div>Platform Revenue (Points)</div></div></div>
    </div>
    <div class="card mt-3">
        <h4>Quick Links</h4>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-primary" href="/senior_care/views/admin/users.php">Manage Users</a>
            <a class="btn btn-primary" href="/senior_care/views/admin/services.php">Manage Services</a>
            <a class="btn btn-primary" href="/senior_care/views/admin/background_checks.php">Background Checks</a>
            <a class="btn btn-primary" href="/senior_care/views/admin/skill_badges.php">Skill Badges</a>
            <a class="btn btn-primary" href="/senior_care/views/admin/visits.php">Manage Visits</a>
            <a class="btn btn-primary" href="/senior_care/views/admin/broadcasts.php">Broadcasts</a>
            <a class="btn btn-primary" href="/senior_care/views/admin/notifications.php">Notifications</a>
            <a class="btn btn-primary" href="/senior_care/views/admin/profile.php">My Profile</a>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
