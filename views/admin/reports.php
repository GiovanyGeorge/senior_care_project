<?php
session_start();
require_once __DIR__ . '/../../config/auth.php';
if (!isset($_SESSION['user_id'])) { header('Location: /senior_care/views/auth/login.php'); exit(); }
requireRole(['Admin']);
require_once __DIR__ . '/../../models/Report.php';

try {
    $reports = (new Report())->getAll(300);
} catch (Throwable $e) {
    $reports = [];
    $_SESSION['error'] = 'Reports table missing. Import `carenest.sql` (includes `visit_reports`).';
}

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/navbar.php';
?>
<div class="container py-4">
    <div class="card">
        <h3 class="mb-3">Visit Reports</h3>
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if (empty($reports)): ?>
            <p class="text-muted mb-0">No reports yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Visit</th>
                        <th>Service</th>
                        <th>Senior</th>
                        <th>Pal</th>
                        <th>Summary</th>
                        <th>Date</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($reports as $r): ?>
                        <tr>
                            <td>#<?= (int)$r['report_ID'] ?></td>
                            <td>#<?= (int)$r['visit_ID'] ?> (<?= htmlspecialchars((string)($r['status'] ?? '')) ?>)</td>
                            <td><?= htmlspecialchars((string)($r['category_name'] ?? '')) ?></td>
                            <td><?= htmlspecialchars(trim((string)($r['senior_fname'] ?? '') . ' ' . (string)($r['senior_lname'] ?? ''))) ?></td>
                            <td><?= htmlspecialchars(trim((string)($r['pal_fname'] ?? '') . ' ' . (string)($r['pal_lname'] ?? ''))) ?></td>
                            <td><?= htmlspecialchars((string)($r['summary'] ?? '-')) ?></td>
                            <td><?= htmlspecialchars((string)($r['created_at'] ?? '')) ?></td>
                        </tr>
                        <tr>
                            <td colspan="7">
                                <strong>Details:</strong>
                                <div class="text-muted"><?= nl2br(htmlspecialchars((string)($r['report_text'] ?? ''))) ?></div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>

