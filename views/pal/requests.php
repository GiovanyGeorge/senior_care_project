<?php
session_start();
require_once __DIR__ . '/../../config/auth.php';
if (!isset($_SESSION['user_id'])) { header('Location: /senior_care/views/auth/login.php'); exit(); }
requireRole(['Pal']);
require_once __DIR__ . '/../../models/Pal.php';

$requests = (new Pal())->getPendingRequests((int)$_SESSION['user_id']);
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/navbar.php';
?>
<div class="container py-4">
    <div class="card">
        <h3>Pending Requests</h3>
        <?php if (empty($requests)): ?>
            <p class="text-muted mb-0">No pending requests yet.</p>
        <?php else: ?>
            <?php foreach ($requests as $request): ?>
                <div class="border rounded p-3 mb-3">
                    <strong><?= htmlspecialchars($request['service_name'] ?? 'Service') ?></strong><br>
                    Senior: <?= htmlspecialchars(($request['senior_first_name'] ?? '') . ' ' . ($request['senior_last_name'] ?? '')) ?><br>
                    <small class="text-muted">Scheduled: <?= htmlspecialchars((string)($request['scheduled_start'] ?? '')) ?></small>
                    <?php if (!empty($request['medical_notes']) || !empty($request['allergies'])): ?>
                        <div class="mt-2 p-2 border rounded bg-light">
                            <strong>Health Info</strong><br>
                            <?php if (!empty($request['allergies'])): ?>
                                <div><strong>Allergies:</strong> <?= htmlspecialchars((string)$request['allergies']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($request['medical_notes'])): ?>
                                <div class="text-muted"><strong>Notes:</strong> <?= htmlspecialchars((string)$request['medical_notes']) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div class="d-flex gap-2 mt-2">
                        <form method="POST" action="/senior_care/controllers/PalController.php?action=updateRequestStatus">
                            <input type="hidden" name="visit_id" value="<?= (int)$request['visit_ID'] ?>">
                            <input type="hidden" name="status" value="Accepted">
                            <button class="btn btn-primary" type="submit">Accept</button>
                        </form>
                        <form method="POST" action="/senior_care/controllers/PalController.php?action=updateRequestStatus">
                            <input type="hidden" name="visit_id" value="<?= (int)$request['visit_ID'] ?>">
                            <input type="hidden" name="status" value="Rejected">
                            <button class="btn btn-danger" type="submit">Reject</button>
                        </form>
                        <form method="POST" action="/senior_care/controllers/PalController.php?action=updateRequestStatus">
                            <input type="hidden" name="visit_id" value="<?= (int)$request['visit_ID'] ?>">
                            <input type="hidden" name="status" value="Live">
                            <input type="hidden" name="return_to" value="/senior_care/views/pal/requests.php">
                            <button class="btn btn-outline-primary" type="submit">Start</button>
                        </form>
                        <form method="POST" action="/senior_care/controllers/PalController.php?action=updateRequestStatus">
                            <input type="hidden" name="visit_id" value="<?= (int)$request['visit_ID'] ?>">
                            <input type="hidden" name="status" value="Completed">
                            <input type="hidden" name="return_to" value="/senior_care/views/pal/requests.php">
                            <button class="btn btn-outline-success" type="submit">Complete</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
