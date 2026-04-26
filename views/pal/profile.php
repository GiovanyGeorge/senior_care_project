<?php
session_start();
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/BackgroundCheck.php';
if (!isset($_SESSION['user_id'])) { header('Location: /senior_care/views/auth/login.php'); exit(); }
requireRole(['Pal']);

$db = Database::getInstance()->getConnection();
$palUserId = (int)$_SESSION['user_id'];

$stmt = $db->prepare(
    "SELECT u.User_ID, u.Fname, u.Lname, u.email, u.phone, u.profile_photo_url,
            pp.pal_ID, pp.skills, pp.rating_avg, pp.verification_status, pp.travel_radius_km, pp.transport_mode
     FROM users u
     LEFT JOIN pal_profiles pp ON pp.User_ID = u.User_ID
     WHERE u.User_ID = ?"
);
$stmt->execute([$palUserId]);
$pal = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$badges = [];
if (!empty($pal['pal_ID'])) {
    $badges = (new BackgroundCheck())->getPalBadges((int)$pal['pal_ID']);
}

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/navbar.php';
?>
<div class="container py-4">
    <div class="card">
        <h3 class="mb-3">Pal Profile</h3>
        <?php if (!empty($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <form action="/senior_care/controllers/PalController.php?action=updateProfile" method="POST" enctype="multipart/form-data">
        <div class="profile-card-header">
            <?php if (!empty($pal['profile_photo_url'])): ?>
                <img src="<?= htmlspecialchars((string)$pal['profile_photo_url']) ?>" alt="Profile Photo" class="profile-avatar" style="object-fit:cover;">
            <?php else: ?>
                <div class="profile-avatar"><?= strtoupper(substr((string)($pal['Fname'] ?? 'P'), 0, 1)) ?></div>
            <?php endif; ?>
            <div>
                <h3 class="mb-0"><?= htmlspecialchars(trim((string)($pal['Fname'] ?? '') . ' ' . (string)($pal['Lname'] ?? ''))) ?></h3>
                <small class="text-muted"><?= htmlspecialchars((string)($pal['email'] ?? '')) ?></small>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">First Name</label>
                <input class="form-control" name="first_name" value="<?= htmlspecialchars((string)($pal['Fname'] ?? '')) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Last Name</label>
                <input class="form-control" name="last_name" value="<?= htmlspecialchars((string)($pal['Lname'] ?? '')) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Phone</label>
                <input class="form-control" name="phone" value="<?= htmlspecialchars((string)($pal['phone'] ?? '')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Profile Photo</label>
                <input class="form-control" type="file" name="profile_photo" accept=".jpg,.jpeg,.png,.pdf">
            </div>
            <div class="col-md-6">
                <label class="form-label">Travel Radius (km)</label>
                <input class="form-control" type="number" min="1" max="100" name="travel_radius_km" value="<?= htmlspecialchars((string)($pal['travel_radius_km'] ?? '5')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Transport Mode</label>
                <input class="form-control" name="transport_mode" value="<?= htmlspecialchars((string)($pal['transport_mode'] ?? 'Walking')) ?>">
            </div>
            <div class="col-12">
                <label class="form-label">Skills</label>
                <textarea class="form-control" name="skills" rows="3"><?= htmlspecialchars((string)($pal['skills'] ?? '')) ?></textarea>
            </div>
            <div class="col-md-6">
                <div class="profile-info-row"><span class="profile-info-label"><i class="fa-solid fa-circle-check"></i> Verification</span><span class="profile-info-value"><?= htmlspecialchars((string)($pal['verification_status'] ?? 'Pending')) ?></span></div>
            </div>
            <div class="col-md-6">
                <div class="profile-info-row"><span class="profile-info-label"><i class="fa-solid fa-star"></i> Rating</span><span class="profile-info-value"><?= htmlspecialchars((string)($pal['rating_avg'] ?? 'N/A')) ?></span></div>
            </div>
        </div>

        <button class="btn btn-primary w-100 mt-4" type="submit">Save Profile</button>
        </form>
    </div>

    <div class="card mt-3">
        <h4 class="mb-3">Upload Skill Badge (Background Check)</h4>
        <form action="/senior_care/controllers/PalController.php?action=uploadBadge" method="POST" enctype="multipart/form-data">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Badge Name</label>
                    <input class="form-control" name="badge_name" placeholder="e.g. First Aid Certified" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Issued At</label>
                    <input class="form-control" type="date" name="issued_at">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Expires At</label>
                    <input class="form-control" type="date" name="expires_at">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Certificate File</label>
                    <input class="form-control" type="file" name="certificate" accept=".jpg,.jpeg,.png,.pdf" required>
                </div>
            </div>
            <button class="btn btn-primary w-100 mt-3" type="submit">Submit For Verification</button>
        </form>

        <h5 class="mt-4 mb-2">My Badge Submissions</h5>
        <?php if (empty($badges)): ?>
            <p class="text-muted mb-0">No badge submissions yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                    <tr><th>Badge</th><th>Status</th><th>Issued</th><th>Expires</th><th>Certificate</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($badges as $b): ?>
                        <tr>
                            <td><?= htmlspecialchars((string)$b['badge_name']) ?></td>
                            <td><span class="status-badge status-pending"><?= htmlspecialchars((string)$b['verification_status']) ?></span></td>
                            <td><?= htmlspecialchars((string)($b['issued_at'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string)($b['expires_at'] ?? '')) ?></td>
                            <td>
                                <?php if (!empty($b['certificate_url'])): ?>
                                    <a href="<?= htmlspecialchars((string)$b['certificate_url']) ?>" target="_blank">View</a>
                                <?php else: ?>
                                    -
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
