<?php
session_start();
require_once __DIR__ . '/../../config/auth.php';
if (!isset($_SESSION['user_id'])) { header('Location: /senior_care/views/auth/login.php'); exit(); }
requireRole(['Admin']);
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/BackgroundCheck.php';
$userModel = new User();
$users = $userModel->getUsersForManagement();
$allBadges = (new BackgroundCheck())->getAllBadges();
$userSnapshots = [];
foreach ($users as $u) {
    $id = (int)($u['User_ID'] ?? 0);
    if ($id > 0) {
        $userSnapshots[$id] = $userModel->getUserFullSnapshot($id);
    }
}
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

        <div class="border rounded p-3 mb-3" style="background: var(--bg-secondary);">
            <h5 class="mb-3">Create User (Admin)</h5>
            <form method="POST" action="/senior_care/controllers/AdminController.php?action=createUser">
                <div class="row g-2">
                    <div class="col-md-3"><input class="form-control" name="first_name" placeholder="First Name" required></div>
                    <div class="col-md-3"><input class="form-control" name="last_name" placeholder="Last Name" required></div>
                    <div class="col-md-3"><input class="form-control" type="email" name="email" placeholder="Email" required></div>
                    <div class="col-md-3"><input class="form-control" type="password" name="password" placeholder="Password (min 8)" required></div>
                    <div class="col-md-3"><input class="form-control" name="phone" placeholder="Phone"></div>
                    <div class="col-md-2"><input class="form-control" type="number" min="1" max="120" name="age" placeholder="Age" required></div>
                    <div class="col-md-3"><input class="form-control" name="national_id" placeholder="National ID" required></div>
                    <div class="col-md-2">
                        <select class="form-select" name="role_type" required>
                            <option value="senior">Senior</option>
                            <option value="pal">Pal</option>
                            <option value="proxy">Family Proxy</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="is_active">
                            <option value="1">Active</option>
                            <option value="0">Pending</option>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <button class="btn btn-primary" type="submit">Create User</button>
                    </div>
                </div>
            </form>
        </div>

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
                        <?php
                        $uid = (int)$user['User_ID'];
                        $snapshot = $userSnapshots[$uid] ?? null;
                        $seniorData = $snapshot['senior_profile'] ?? null;
                        $palData = $snapshot['pal_profile'] ?? null;
                        $proxyData = $snapshot['proxy_profile'] ?? null;
                        $healthData = $snapshot['health_record'] ?? null;
                        $badgeData = $snapshot['skill_badges'] ?? [];
                        ?>
                        <tr>
                            <td><?= $uid ?></td>
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
                                            <input type="hidden" name="user_id" value="<?= $uid ?>">
                                            <input type="hidden" name="status" value="approve">
                                            <button class="btn btn-sm btn-success" type="submit">Approve</button>
                                        </form>
                                        <form method="POST" action="/senior_care/controllers/AdminController.php?action=setUserStatus">
                                            <input type="hidden" name="user_id" value="<?= $uid ?>">
                                            <input type="hidden" name="status" value="reject">
                                            <button class="btn btn-sm btn-danger" type="submit">Reject</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" action="/senior_care/controllers/AdminController.php?action=setUserStatus">
                                            <input type="hidden" name="user_id" value="<?= $uid ?>">
                                            <input type="hidden" name="status" value="deactivate">
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Deactivate</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ((int)$user['is_active'] === 0): ?>
                                        <form method="POST" action="/senior_care/controllers/AdminController.php?action=setUserStatus">
                                            <input type="hidden" name="user_id" value="<?= $uid ?>">
                                            <input type="hidden" name="status" value="activate">
                                            <button class="btn btn-sm btn-outline-success" type="submit">Activate</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($uid !== (int)($_SESSION['user_id'] ?? 0)): ?>
                                        <form method="POST" action="/senior_care/controllers/AdminController.php?action=deleteUser" onsubmit="return confirm('Delete this account permanently?');">
                                            <input type="hidden" name="user_id" value="<?= $uid ?>">
                                            <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-outline-primary" type="button" data-about-toggle="about-row-<?= $uid ?>">
                                        Show About
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr id="about-row-<?= $uid ?>" style="display:none;">
                            <td colspan="7">
                                <div class="border rounded p-3" style="background: var(--bg-secondary);">
                                    <h5 class="mb-3">User About #<?= $uid ?></h5>
                                    <div class="row g-2 mb-3">
                                        <div class="col-md-6"><strong>Name:</strong> <?= htmlspecialchars(trim((string)$user['Fname'] . ' ' . (string)$user['Lname'])) ?></div>
                                        <div class="col-md-6"><strong>Email:</strong> <?= htmlspecialchars((string)$user['email']) ?></div>
                                        <div class="col-md-4"><strong>Role:</strong> <?= htmlspecialchars((string)$user['role_type']) ?></div>
                                        <div class="col-md-4"><strong>Phone:</strong> <?= htmlspecialchars((string)($user['phone'] ?? '-')) ?></div>
                                        <div class="col-md-4"><strong>Age:</strong> <?= htmlspecialchars((string)($user['age'] ?? '-')) ?></div>
                                        <div class="col-md-6"><strong>National ID:</strong> <?= htmlspecialchars((string)($user['national_id'] ?? '-')) ?></div>
                                        <div class="col-md-6"><strong>Created At:</strong> <?= htmlspecialchars((string)($user['created_at'] ?? '')) ?></div>
                                    </div>

                                    <?php if (!empty($seniorData)): ?>
                                        <div class="mb-3">
                                            <h6>Senior Profile</h6>
                                            <p class="mb-1"><strong>Address:</strong> <?= htmlspecialchars((string)($seniorData['address'] ?? '-')) ?></p>
                                            <p class="mb-1"><strong>Comfort Profile:</strong> <?= htmlspecialchars((string)($seniorData['comfort_profile'] ?? '-')) ?></p>
                                            <p class="mb-1"><strong>Emergency Contact:</strong> <?= htmlspecialchars((string)($seniorData['emergency_contact_name'] ?? '-')) ?> - <?= htmlspecialchars((string)($seniorData['emergency_contact_phone'] ?? '-')) ?></p>
                                            <?php if (!empty($healthData)): ?>
                                                <p class="mb-1"><strong>Medical Notes:</strong> <?= htmlspecialchars((string)($healthData['medical_notes'] ?? '-')) ?></p>
                                                <p class="mb-0"><strong>Allergies:</strong> <?= htmlspecialchars((string)($healthData['allergies'] ?? '-')) ?></p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($palData)): ?>
                                        <div class="mb-3">
                                            <h6>Pal Profile</h6>
                                            <p class="mb-1"><strong>Skills:</strong> <?= htmlspecialchars((string)($palData['skills'] ?? '-')) ?></p>
                                            <p class="mb-1"><strong>Verification Status:</strong> <?= htmlspecialchars((string)($palData['verification_status'] ?? '-')) ?></p>
                                            <p class="mb-0"><strong>Travel / Transport:</strong> <?= htmlspecialchars((string)($palData['travel_radius_km'] ?? '-')) ?> km / <?= htmlspecialchars((string)($palData['transport_mode'] ?? '-')) ?></p>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($proxyData)): ?>
                                        <div class="mb-3">
                                            <h6>Proxy Profile</h6>
                                            <pre class="mb-0"><?= htmlspecialchars(json_encode($proxyData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($badgeData)): ?>
                                        <div>
                                            <h6>Skill Badges</h6>
                                            <div class="table-responsive">
                                                <table class="table table-sm align-middle mb-0">
                                                    <thead><tr><th>Badge</th><th>Status</th><th>Issued</th><th>Expires</th><th>Certificate</th></tr></thead>
                                                    <tbody>
                                                    <?php foreach ($badgeData as $bd): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars((string)$bd['badge_name']) ?></td>
                                                            <td><?= htmlspecialchars((string)$bd['verification_status']) ?></td>
                                                            <td><?= htmlspecialchars((string)($bd['issued_at'] ?? '-')) ?></td>
                                                            <td><?= htmlspecialchars((string)($bd['expires_at'] ?? '-')) ?></td>
                                                            <td><?php if (!empty($bd['certificate_url'])): ?><a href="<?= htmlspecialchars((string)$bd['certificate_url']) ?>" target="_blank">Open</a><?php else: ?>-<?php endif; ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <hr>
                                    <h6 class="mb-2">Update User</h6>
                                    <form method="POST" action="/senior_care/controllers/AdminController.php?action=updateUser">
                                        <input type="hidden" name="user_id" value="<?= $uid ?>">
                                        <div class="row g-2">
                                            <div class="col-md-3">
                                                <label class="form-label small mb-1">First Name</label>
                                                <input class="form-control form-control-sm" name="first_name" value="<?= htmlspecialchars((string)$user['Fname']) ?>" required>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label small mb-1">Last Name</label>
                                                <input class="form-control form-control-sm" name="last_name" value="<?= htmlspecialchars((string)$user['Lname']) ?>" required>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label small mb-1">Email</label>
                                                <input class="form-control form-control-sm" type="email" name="email" value="<?= htmlspecialchars((string)$user['email']) ?>" required>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label small mb-1">Phone</label>
                                                <input class="form-control form-control-sm" name="phone" value="<?= htmlspecialchars((string)($user['phone'] ?? '')) ?>">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label small mb-1">Age</label>
                                                <input class="form-control form-control-sm" type="number" min="1" max="120" name="age" value="<?= htmlspecialchars((string)($user['age'] ?? '')) ?>" required>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label small mb-1">National ID</label>
                                                <input class="form-control form-control-sm" name="national_id" value="<?= htmlspecialchars((string)($user['national_id'] ?? '')) ?>" required>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label small mb-1">Role</label>
                                                <select class="form-select form-select-sm" name="role_type" required>
                                                    <option value="senior" <?= (string)$user['role_type'] === 'senior' ? 'selected' : '' ?>>Senior</option>
                                                    <option value="pal" <?= (string)$user['role_type'] === 'pal' ? 'selected' : '' ?>>Pal</option>
                                                    <option value="proxy" <?= (string)$user['role_type'] === 'proxy' ? 'selected' : '' ?>>Family Proxy</option>
                                                    <option value="admin" <?= (string)$user['role_type'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label small mb-1">Status</label>
                                                <select class="form-select form-select-sm" name="is_active" required>
                                                    <option value="1" <?= (int)$user['is_active'] === 1 ? 'selected' : '' ?>>Active</option>
                                                    <option value="0" <?= (int)$user['is_active'] === 0 ? 'selected' : '' ?>>Pending</option>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label small mb-1">New Password (optional)</label>
                                                <input class="form-control form-control-sm" type="password" name="new_password" placeholder="Leave blank to keep">
                                            </div>
                                            <div class="col-md-12">
                                                <button class="btn btn-sm btn-primary" type="submit">Update User</button>
                                            </div>
                                        </div>
                                    </form>
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
        <?php if (empty($allBadges)): ?>
            <p class="text-muted mb-0">No skill badge submissions found.</p>
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
                    <?php foreach ($allBadges as $b): ?>
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
<script>
    (function () {
        const buttons = document.querySelectorAll('[data-about-toggle]');
        buttons.forEach((btn) => {
            btn.addEventListener('click', function () {
                const id = this.getAttribute('data-about-toggle');
                const row = document.getElementById(id);
                if (!row) return;
                const hidden = row.style.display === 'none' || row.style.display === '';
                row.style.display = hidden ? 'table-row' : 'none';
                this.textContent = hidden ? 'Hide About' : 'Show About';
            });
        });
    })();
</script>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
