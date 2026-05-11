<?php
session_start();
require_once __DIR__ . '/../../config/auth.php';
if (!isset($_SESSION['user_id'])) { header('Location: /senior_care/views/auth/login.php'); exit(); }
requireRole(['Admin']);
require_once __DIR__ . '/../../models/BackgroundCheck.php';

$checks = (new BackgroundCheck())->getAllBackgroundChecks();

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/navbar.php';
?>
<div class="container py-4">
    <div class="card">
        <h3 class="mb-3">Background Checks</h3>
        <p class="text-muted">Verify submitted background checks and supporting files.</p>

        <?php if (!empty($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if (empty($checks)): ?>
            <p class="text-muted mb-0">No background checks found.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                    <tr>
                        <th>Check ID</th>
                        <th>Pal</th>
                        <th>Email</th>
                        <th>Type</th>
                        <th>Linked Badge</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>File</th>
                        <th>Verify</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($checks as $c): ?>
                        <tr>
                            <td>#<?= (int)$c['check_ID'] ?></td>
                            <td><?= htmlspecialchars(trim((string)$c['Fname'] . ' ' . (string)$c['Lname'])) ?></td>
                            <td><?= htmlspecialchars((string)$c['email']) ?></td>
                            <td><?= htmlspecialchars((string)($c['check_type'] ?? 'Background')) ?></td>
                            <td><?= htmlspecialchars((string)($c['badge_name'] ?? '-')) ?></td>
                            <td><?= htmlspecialchars((string)($c['status'] ?? 'Pending')) ?></td>
                            <td><?= htmlspecialchars((string)($c['created_at'] ?? '')) ?></td>
                            <td>
                                <?php if (!empty($c['certificate_url'])): ?>
                                    <a href="<?= htmlspecialchars((string)$c['certificate_url']) ?>" target="_blank">Open File</a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ((string)$c['status'] === 'Pending'): ?>
                                    <div class="d-flex flex-wrap gap-2">
                                        <form method="POST" action="/senior_care/controllers/AdminController.php?action=verifyBackgroundCheck">
                                            <input type="hidden" name="check_id" value="<?= (int)$c['check_ID'] ?>">
                                            <input type="hidden" name="decision" value="Approved">
                                            <button class="btn btn-sm btn-success" type="submit">Approve</button>
                                        </form>
                                        <form method="POST" action="/senior_care/controllers/AdminController.php?action=verifyBackgroundCheck">
                                            <input type="hidden" name="check_id" value="<?= (int)$c['check_ID'] ?>">
                                            <input type="hidden" name="decision" value="Rejected">
                                            <button class="btn btn-sm btn-danger" type="submit">Reject</button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span class="badge text-bg-secondary">Reviewed</span>
                                <?php endif; ?>
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
