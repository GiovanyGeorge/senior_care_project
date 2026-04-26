<?php
session_start();
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/database.php';
if (!isset($_SESSION['user_id'])) { header('Location: /senior_care/views/auth/login.php'); exit(); }
requireRole(['Pal']);

$visitId = (int)($_GET['visit_id'] ?? 0);
$db = Database::getInstance()->getConnection();
$visit = null;
if ($visitId > 0) {
    $stmt = $db->prepare(
        "SELECT vr.visit_ID, vr.scheduled_start, vr.status, sc.category_name,
                su.Fname AS senior_fname, su.Lname AS senior_lname
         FROM visit_requests vr
         JOIN service_categories sc ON sc.category_ID = vr.category_ID
         JOIN senior_profiles sp ON sp.senior_ID = vr.senior_ID
         JOIN users su ON su.User_ID = sp.User_ID
         JOIN pal_profiles pp ON pp.pal_ID = vr.pal_ID
         WHERE vr.visit_ID = :visit_id AND pp.User_ID = :pal_user_id
         LIMIT 1"
    );
    $stmt->execute(['visit_id' => $visitId, 'pal_user_id' => (int)$_SESSION['user_id']]);
    $visit = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/navbar.php';
?>
<div class="container py-4">
    <div class="card">
        <h3 class="mb-3">Visit Report</h3>
        <?php if (!empty($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if (!$visit): ?>
            <p class="text-muted mb-0">Visit not found or not assigned to you.</p>
        <?php else: ?>
            <p class="text-muted">
                Visit #<?= (int)$visit['visit_ID'] ?> • <?= htmlspecialchars((string)$visit['category_name']) ?> •
                Senior: <?= htmlspecialchars(trim((string)$visit['senior_fname'] . ' ' . (string)$visit['senior_lname'])) ?> •
                <?= htmlspecialchars((string)$visit['scheduled_start']) ?>
            </p>

            <form method="POST" action="/senior_care/controllers/ReportController.php?action=submit">
                <input type="hidden" name="visit_id" value="<?= (int)$visit['visit_ID'] ?>">
                <div class="mb-3">
                    <label class="form-label">Summary (optional)</label>
                    <input class="form-control" name="summary" placeholder="Short summary">
                </div>
                <div class="mb-3">
                    <label class="form-label">Report Details</label>
                    <textarea class="form-control" name="report_text" rows="6" required placeholder="What happened in this visit? Any issues? Outcome?"></textarea>
                </div>
                <button class="btn btn-primary w-100" type="submit"><i class="fa-solid fa-file-signature me-2"></i>Submit Report</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>

