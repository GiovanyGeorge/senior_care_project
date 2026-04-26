<?php
session_start();
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/database.php';
if (!isset($_SESSION['user_id'])) { header('Location: /senior_care/views/auth/login.php'); exit(); }
requireRole(['FamilyProxy']);

$db = Database::getInstance()->getConnection();
$proxyUserId = (int)$_SESSION['user_id'];
$stmt = $db->prepare('SELECT User_ID, Fname, Lname, email, phone, profile_photo_url FROM users WHERE User_ID = ? LIMIT 1');
$stmt->execute([$proxyUserId]);
$proxy = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$linked = $_SESSION['proxy_seniors'] ?? [];

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/navbar.php';
?>
<div class="container py-4">
    <div class="card">
        <h3 class="mb-3">Proxy Profile</h3>
        <?php if (!empty($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <form action="/senior_care/controllers/ProxyController.php?action=updateProfile" method="POST" enctype="multipart/form-data">
            <div class="profile-card-header">
                <?php if (!empty($proxy['profile_photo_url'])): ?>
                    <img src="<?= htmlspecialchars((string)$proxy['profile_photo_url']) ?>" alt="Profile Photo" class="profile-avatar" style="object-fit:cover;">
                <?php else: ?>
                    <div class="profile-avatar"><?= strtoupper(substr((string)($proxy['Fname'] ?? 'P'), 0, 1)) ?></div>
                <?php endif; ?>
                <div>
                    <h3 class="mb-0"><?= htmlspecialchars(trim((string)($proxy['Fname'] ?? '') . ' ' . (string)($proxy['Lname'] ?? ''))) ?></h3>
                    <small class="text-muted"><?= htmlspecialchars((string)($proxy['email'] ?? '')) ?></small>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">First Name</label>
                    <input class="form-control" name="first_name" required value="<?= htmlspecialchars((string)($proxy['Fname'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Last Name</label>
                    <input class="form-control" name="last_name" required value="<?= htmlspecialchars((string)($proxy['Lname'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone</label>
                    <input class="form-control" name="phone" value="<?= htmlspecialchars((string)($proxy['phone'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Profile Photo</label>
                    <input class="form-control" type="file" name="profile_photo" accept=".jpg,.jpeg,.png,.pdf">
                </div>
            </div>

            <button class="btn btn-primary w-100 mt-4" type="submit">Save Profile</button>
        </form>
    </div>

    <div class="card mt-3">
        <h4 class="card-header-title">Linked Seniors</h4>
        <?php if (empty($linked)): ?>
            <p class="text-muted mb-0">No linked seniors.</p>
        <?php else: ?>
            <ul class="mb-0">
                <?php foreach ($linked as $s): ?>
                    <li>
                        <?= htmlspecialchars(trim((string)$s['Fname'] . ' ' . (string)$s['Lname'])) ?>
                        <?= !empty($s['relationship_type']) ? ' (' . htmlspecialchars((string)$s['relationship_type']) . ')' : '' ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>

