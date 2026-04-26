<?php
session_start();
require_once __DIR__ . '/../../config/auth.php';
if (!isset($_SESSION['user_id'])) { header('Location: /senior_care/views/auth/login.php'); exit(); }
requireRole(['Admin']);
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/BackgroundCheck.php';
$users = (new User())->getUsersForManagement();
$pendingBadges = (new BackgroundCheck())->getPendingBadges();
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/navbar.php';
?>
<div class="container py-4">
    <div class="card">
        <h3>Manage Users</h3>
        <?php if (!empty($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                <tr>
                    <th>User ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Registered At</th>
                    <th>Manage</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($users)): ?>
                    <tr><td colspan="7">No users found.</td></tr>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= (int)$user['User_ID'] ?></td>
                            <td><?= htmlspecialchars(trim(($user['Fname'] ?? '') . ' ' . ($user['Lname'] ?? ''))) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><?= htmlspecialchars($user['role_type']) ?></td>
                            <td>
                                <?php if ((int)$user['is_active'] === 1): ?>
                                    <span class="badge text-bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge text-bg-warning">Pending/Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars((string)$user['created_at']) ?></td>
                            <td>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php if ((int)$user['is_active'] === 0): ?>
                                        <form method="POST" action="/senior_care/controllers/AdminController.php?action=setUserStatus">
                                            <input type="hidden" name="user_id" value="<?= (int)$user['User_ID'] ?>">
                                            <input type="hidden" name="status" value="approve">
                                            <button class="btn btn-sm btn-success" type="submit">Approve</button>
                                        </form>
                                        <form method="POST" action="/senior_care/controllers/AdminController.php?action=setUserStatus">
                                            <input type="hidden" name="user_id" value="<?= (int)$user['User_ID'] ?>">
                                            <input type="hidden" name="status" value="reject">
                                            <button class="btn btn-sm btn-danger" type="submit">Reject</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" action="/senior_care/controllers/AdminController.php?action=setUserStatus">
                                            <input type="hidden" name="user_id" value="<?= (int)$user['User_ID'] ?>">
                                            <input type="hidden" name="status" value="deactivate">
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Deactivate</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ((int)$user['is_active'] === 0): ?>
                                        <form method="POST" action="/senior_care/controllers/AdminController.php?action=setUserStatus">
                                            <input type="hidden" name="user_id" value="<?= (int)$user['User_ID'] ?>">
                                            <input type="hidden" name="status" value="activate">
                                            <button class="btn btn-sm btn-outline-success" type="submit">Activate</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ((int)$user['User_ID'] !== (int)($_SESSION['user_id'] ?? 0)): ?>
                                        <form method="POST" action="/senior_care/controllers/AdminController.php?action=deleteUser" onsubmit="return confirm('Delete this account permanently?');">
                                            <input type="hidden" name="user_id" value="<?= (int)$user['User_ID'] ?>">
                                            <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card mt-3">
        <h4 class="card-header-title">Background Check Queue (Skill Badges)</h4>
        <?php if (empty($pendingBadges)): ?>
            <p class="text-muted mb-0">No pending badge verifications.</p>
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
                        <th>Certificate</th>
                        <th>Verify</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pendingBadges as $b): ?>
                        <tr>
                            <td>#<?= (int)$b['badge_ID'] ?></td>
                            <td><?= htmlspecialchars(trim((string)$b['Fname'] . ' ' . (string)$b['Lname'])) ?></td>
                            <td><?= htmlspecialchars((string)$b['email']) ?></td>
                            <td><?= htmlspecialchars((string)$b['badge_name']) ?></td>
                            <td><span class="status-badge status-pending"><?= htmlspecialchars((string)$b['verification_status']) ?></span></td>
                            <td>
                                <?php if (!empty($b['certificate_url'])): ?>
                                    <a href="<?= htmlspecialchars((string)$b['certificate_url']) ?>" target="_blank">Open File</a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
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
