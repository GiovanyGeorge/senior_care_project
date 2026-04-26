<?php
session_start();
require_once __DIR__ . '/../../config/auth.php';
if (!isset($_SESSION['user_id'])) { header('Location: /senior_care/views/auth/login.php'); exit(); }
requireRole(['Admin']);
require_once __DIR__ . '/../../models/User.php';

$admin = (new User())->findById((int)$_SESSION['user_id']);

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/navbar.php';
?>
<div class="container py-4">
    <div class="card">
        <h3 class="mb-3">Admin Profile</h3>
        <?php if (!empty($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="profile-card-header mb-3">
            <?php if (!empty($admin['profile_photo_url'])): ?>
                <img src="<?= htmlspecialchars((string)$admin['profile_photo_url']) ?>" alt="Admin Photo" class="profile-avatar" style="object-fit:cover;">
            <?php else: ?>
                <div class="profile-avatar"><?= strtoupper(substr((string)($admin['Fname'] ?? 'A'), 0, 1)) ?></div>
            <?php endif; ?>
            <div>
                <h4 class="mb-0"><?= htmlspecialchars(trim((string)($admin['Fname'] ?? '') . ' ' . (string)($admin['Lname'] ?? ''))) ?></h4>
                <small class="text-muted"><?= htmlspecialchars((string)($admin['email'] ?? '')) ?></small>
            </div>
        </div>

        <form action="/senior_care/controllers/AdminController.php?action=updateProfile" method="POST" enctype="multipart/form-data">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">First Name</label>
                    <input type="text" name="first_name" class="form-control" required value="<?= htmlspecialchars((string)($admin['Fname'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Last Name</label>
                    <input type="text" name="last_name" class="form-control" required value="<?= htmlspecialchars((string)($admin['Lname'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars((string)($admin['email'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars((string)($admin['phone'] ?? '')) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Profile Photo</label>
                    <input type="file" name="profile_photo" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100 mt-4">Save Profile</button>
        </form>
    </div>

    <div class="card mt-3">
        <h4 class="mb-3">Change Password</h4>
        <form action="/senior_care/controllers/AdminController.php?action=changePassword" method="POST">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Current Password</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">New Password</label>
                    <input type="password" name="new_password" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100 mt-4">Update Password</button>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
