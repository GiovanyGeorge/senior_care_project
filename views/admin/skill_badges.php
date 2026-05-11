<?php
session_start();
require_once __DIR__ . '/../../config/auth.php';
if (!isset($_SESSION['user_id'])) { header('Location: /senior_care/views/auth/login.php'); exit(); }
requireRole(['Admin']);
require_once __DIR__ . '/../../models/BackgroundCheck.php';

$allBadges = (new BackgroundCheck())->getAllBadges();

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/navbar.php';
?>
<div class="container py-4">
    <div class="card">
        <h3 class="mb-3">Skill Badges Verification</h3>
        <p class="text-muted">Review uploaded certificates and verify each badge.</p>

        <?php if (!empty($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if (empty($allBadges)): ?>
            <p class="text-muted mb-0">No badge submissions found.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                    <tr>
                        <th>Badge ID</th>
                        <th>Pal</th>
                        <th>Email</th>
                        <th>Badge</th>
                        <th>Status</th>
                        <th>Issued</th>
                        <th>Certificate</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($allBadges as $b): ?>
                        <tr>
                            <td>#<?= (int)$b['badge_ID'] ?></td>
                            <td><?= htmlspecialchars(trim((string)$b['Fname'] . ' ' . (string)$b['Lname'])) ?></td>
                            <td><?= htmlspecialchars((string)$b['email']) ?></td>
                            <td><?= htmlspecialchars((string)$b['badge_name']) ?></td>
                            <td><?= htmlspecialchars((string)$b['verification_status']) ?></td>
                            <td><?= htmlspecialchars((string)($b['issued_at'] ?? '-')) ?></td>
                            <td>
                                <?php if (!empty($b['certificate_url'])): ?>
                                    <a href="<?= htmlspecialchars((string)$b['certificate_url']) ?>" target="_blank">Open File</a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ((string)$b['verification_status'] === 'Pending'): ?>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <form method="POST" action="/senior_care/controllers/AdminController.php?action=verifyBadge">
                                            <input type="hidden" name="badge_id" value="<?= (int)$b['badge_ID'] ?>">
                                            <input type="hidden" name="decision" value="Approved">
                                            <button class="btn btn-sm btn-success" type="submit">Approve</button>
                                        </form>
                                        <form method="POST" action="/senior_care/controllers/AdminController.php?action=verifyBadge">
                                            <input type="hidden" name="badge_id" value="<?= (int)$b['badge_ID'] ?>">
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
