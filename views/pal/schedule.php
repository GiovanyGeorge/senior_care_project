<?php
session_start();
require_once __DIR__ . '/../../config/auth.php';
if (!isset($_SESSION['user_id'])) { header('Location: /senior_care/views/auth/login.php'); exit(); }
requireRole(['Pal']);
require_once __DIR__ . '/../../models/Pal.php';

$schedule = (new Pal())->getUpcomingSchedule((int)$_SESSION['user_id']);

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/navbar.php';
?>
<div class="container py-4">
    <div class="card">
        <h3 class="mb-3">My Schedule</h3>
        <?php if (!empty($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        <?php if (empty($schedule)): ?>
            <p class="text-muted mb-0">No upcoming scheduled visits.</p>
        <?php else: ?>
            <?php foreach ($schedule as $visit): ?>
                <?php $status = (string)($visit['status'] ?? 'Pending'); ?>
                <div class="visit-item">
                    <div class="visit-avatar"><?= strtoupper(substr((string)($visit['senior_first_name'] ?? 'S'), 0, 1)) ?></div>
                    <div class="visit-info">
                        <div class="visit-name">
                            <?= htmlspecialchars((string)($visit['service_name'] ?? 'Service')) ?>
                            with
                            <?= htmlspecialchars(trim((string)($visit['senior_first_name'] ?? '') . ' ' . (string)($visit['senior_last_name'] ?? ''))) ?>
                        </div>
                        <div class="visit-details"><?= htmlspecialchars((string)($visit['scheduled_start'] ?? '')) ?></div>
                        <?php if (!empty($visit['medical_notes']) || !empty($visit['allergies'])): ?>
                            <div class="visit-details">
                                <strong>Health:</strong>
                                <?php if (!empty($visit['allergies'])): ?>
                                    Allergies: <?= htmlspecialchars((string)$visit['allergies']) ?>
                                <?php endif; ?>
                                <?php if (!empty($visit['medical_notes'])): ?>
                                    • Notes: <?= htmlspecialchars((string)$visit['medical_notes']) ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex flex-column align-items-end gap-2">
                        <span class="status-badge status-pending"><?= htmlspecialchars($status) ?></span>
                        <?php if ($status === 'Accepted'): ?>
                            <form method="POST" action="/senior_care/controllers/PalController.php?action=updateRequestStatus">
                                <input type="hidden" name="visit_id" value="<?= (int)$visit['visit_ID'] ?>">
                                <input type="hidden" name="status" value="Live">
                                <input type="hidden" name="return_to" value="/senior_care/views/pal/schedule.php">
                                <button class="btn btn-sm btn-primary" type="submit">Start Service</button>
                            </form>
                            <form method="POST" action="/senior_care/controllers/PalController.php?action=updateRequestStatus" onsubmit="return confirm('Cancel this service?');">
                                <input type="hidden" name="visit_id" value="<?= (int)$visit['visit_ID'] ?>">
                                <input type="hidden" name="status" value="Cancelled">
                                <input type="hidden" name="return_to" value="/senior_care/views/pal/schedule.php">
                                <input type="hidden" name="reason" value="Cancelled by pal from schedule.">
                                <button class="btn btn-sm btn-danger" type="submit">Cancel</button>
                            </form>
                        <?php elseif ($status === 'Live' || $status === 'En_Route'): ?>
                            <form method="POST" action="/senior_care/controllers/PalController.php?action=updateRequestStatus">
                                <input type="hidden" name="visit_id" value="<?= (int)$visit['visit_ID'] ?>">
                                <input type="hidden" name="status" value="Completed">
                                <input type="hidden" name="return_to" value="/senior_care/views/pal/schedule.php">
                                <button class="btn btn-sm btn-success" type="submit">Complete Service</button>
                            </form>
                            <a class="btn btn-sm btn-outline-primary" href="/senior_care/views/pal/report_visit.php?visit_id=<?= (int)$visit['visit_ID'] ?>">Write Report</a>
                            <form method="POST" action="/senior_care/controllers/PalController.php?action=updateRequestStatus" onsubmit="return confirm('Cancel this service?');">
                                <input type="hidden" name="visit_id" value="<?= (int)$visit['visit_ID'] ?>">
                                <input type="hidden" name="status" value="Cancelled">
                                <input type="hidden" name="return_to" value="/senior_care/views/pal/schedule.php">
                                <input type="hidden" name="reason" value="Cancelled by pal during service.">
                                <button class="btn btn-sm btn-danger" type="submit">Cancel</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
