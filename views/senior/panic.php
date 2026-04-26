<?php
session_start();
require_once __DIR__ . '/../../config/auth.php';
if (!isset($_SESSION['user_id'])) { header('Location: /senior_care/views/auth/login.php'); exit(); }
requireRole(['Senior', 'FamilyProxy']);
require_once __DIR__ . '/../layouts/header.php';
?>
<div class="container-fluid min-vh-100 d-flex align-items-center justify-content-center" style="background:#E63946;color:#fff;">
    <div class="text-center">
        <h1 class="mb-4">Emergency Assistance</h1>
        <p class="mb-4">Press confirm to alert your emergency contacts and admin team.</p>
        <form action="/senior_care/controllers/EmergencyController.php?action=panic" method="POST" onsubmit="return confirm('Send emergency alert now?');">
            <input type="hidden" name="message" value="Panic alert triggered by senior.">
            <button class="btn btn-light btn-lg px-5" type="submit">Send Alert</button>
        </form>
        <?php if (!empty($_SESSION['success'])): ?>
            <div class="alert alert-success mt-4"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="alert alert-danger mt-4"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
