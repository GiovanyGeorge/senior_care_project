<?php
session_start();
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/database.php';
if (!isset($_SESSION['user_id'])) { header('Location: /senior_care/views/auth/login.php'); exit(); }
requireRole(['Senior', 'FamilyProxy']);

$db = Database::getInstance()->getConnection();
$role = $_SESSION['role'] ?? 'Senior';
$actorUserId = (int)$_SESSION['user_id'];
$userId = $role === 'FamilyProxy'
    ? (int)($_SESSION['proxy_senior_user_id'] ?? 0)
    : $actorUserId;
$stmt = $db->prepare(
    "SELECT u.*, sp.*, hr.medical_notes, hr.allergies
     FROM users u
     JOIN senior_profiles sp ON u.User_ID = sp.User_ID
     LEFT JOIN health_records hr ON hr.senior_ID = sp.senior_ID
     WHERE u.User_ID = ?"
);
$stmt->execute([$userId]);
$seniorData = $stmt->fetch(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/navbar.php';
?>
<div class="container py-4">
    <div class="card">
        <h3 class="mb-3">My Profile</h3>
        <?php if (!empty($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <form action="/senior_care/controllers/SeniorController.php?action=updateProfile" method="POST" enctype="multipart/form-data">
            <div class="profile-card-header">
                <?php if (!empty($seniorData['profile_photo_url'])): ?>
                    <img src="<?= htmlspecialchars((string)$seniorData['profile_photo_url']) ?>" alt="Profile Photo" class="profile-avatar" style="object-fit:cover;">
                <?php else: ?>
                    <div class="profile-avatar"><?= strtoupper(substr((string)($seniorData['Fname'] ?? 'S'), 0, 1)) ?></div>
                <?php endif; ?>
                <div>
                    <h3 class="mb-0"><?= htmlspecialchars(trim((string)($seniorData['Fname'] ?? '') . ' ' . (string)($seniorData['Lname'] ?? ''))) ?></h3>
                    <small class="text-muted"><?= htmlspecialchars((string)($seniorData['email'] ?? '')) ?></small>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">First Name</label>
                    <input type="text" name="first_name" class="form-control" required value="<?= htmlspecialchars((string)($seniorData['Fname'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Last Name</label>
                    <input type="text" name="last_name" class="form-control" required value="<?= htmlspecialchars((string)($seniorData['Lname'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars((string)($seniorData['phone'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Profile Photo</label>
                    <input type="file" name="profile_photo" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                </div>
                <div class="col-12">
                    <label class="form-label">Address</label>
                    <input type="text" name="address" class="form-control" value="<?= htmlspecialchars((string)($seniorData['address'] ?? '')) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Comfort Profile</label>
                    <input type="text" name="comfort_profile" class="form-control" value="<?= htmlspecialchars((string)($seniorData['comfort_profile'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Emergency Contact Name</label>
                    <input type="text" name="emergency_contact_name" class="form-control" value="<?= htmlspecialchars((string)($seniorData['emergency_contact_name'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Emergency Contact Phone</label>
                    <input type="text" name="emergency_contact_phone" class="form-control" value="<?= htmlspecialchars((string)($seniorData['emergency_contact_phone'] ?? '')) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Medical Notes</label>
                    <textarea name="medical_notes" class="form-control" rows="3" placeholder="Optional"><?= htmlspecialchars((string)($seniorData['medical_notes'] ?? '')) ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Allergies</label>
                    <input type="text" name="allergies" class="form-control" value="<?= htmlspecialchars((string)($seniorData['allergies'] ?? '')) ?>">
                </div>
            </div>

            <button class="btn btn-primary w-100 mt-4" type="submit">Save Profile</button>
        </form>
    </div>
</div>
<a class="panic-btn-fixed" href="/senior_care/views/senior/panic.php"><i class="fa-solid fa-triangle-exclamation"></i> Panic</a>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
