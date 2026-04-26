<?php
session_start();
require_once __DIR__ . '/../../config/auth.php';
if (!isset($_SESSION['user_id'])) { header('Location: /senior_care/views/auth/login.php'); exit(); }
requireRole(['Pal']);
require_once __DIR__ . '/../../models/Pal.php';
require_once __DIR__ . '/../../models/Points.php';

$palModel = new Pal();
$pending = $palModel->getPendingRequests((int)$_SESSION['user_id']);
$today = $palModel->getTodaySchedule((int)$_SESSION['user_id']);
$earned = (new Points())->getBalance((int)$_SESSION['user_id']);

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/navbar.php';
?>
<div class="container py-4">
    <div class="welcome-banner">
        <h2>Welcome, <?= htmlspecialchars($_SESSION['name'] ?? 'Pal') ?></h2>
        <p class="mb-0">You are making neighbors safer every day.</p>
    </div>
    <div class="points-card mb-4">
        <p class="mb-1">SilverPoints Earned</p>
        <h2><?= (int)$earned ?></h2>
    </div>
    <div class="card">
        <h4>Pending Requests</h4>
        <p class="mb-0"><?= count($pending) ?> request(s) waiting.</p>
    </div>
    <div class="card">
        <h4>Today's Scheduled Visits</h4>
        <?php foreach ($today as $visit): ?>
            <div class="border-bottom py-2">
                <?= htmlspecialchars(($visit['senior_first_name'] ?? '') . ' ' . ($visit['senior_last_name'] ?? '')) ?> -
                <?= htmlspecialchars((string)$visit['scheduled_start']) ?>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="card">
        <h4>Rating & Skill Badges</h4>
        <div class="rating-stars mb-2">★★★★★</div>
        <span class="badge-card">Companionship</span>
        <span class="badge-card">Tech Support</span>
    </div>
</div>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
