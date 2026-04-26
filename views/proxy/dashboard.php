<?php
session_start();
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/database.php';
if (!isset($_SESSION['user_id'])) { header('Location: /senior_care/views/auth/login.php'); exit(); }
requireRole(['FamilyProxy']);

$db = Database::getInstance()->getConnection();
$proxyUserId = (int)$_SESSION['user_id'];
$activeSeniorUserId = (int)($_SESSION['proxy_senior_user_id'] ?? 0);
$activeSeniorName = (string)($_SESSION['proxy_senior_name'] ?? '');
$linked = $_SESSION['proxy_seniors'] ?? [];

$proxyStmt = $db->prepare('SELECT User_ID, Fname, Lname, email, phone, profile_photo_url FROM users WHERE User_ID = ? LIMIT 1');
$proxyStmt->execute([$proxyUserId]);
$proxy = $proxyStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$senior = [];
$seniorId = 0;
if ($activeSeniorUserId > 0) {
    $seniorStmt = $db->prepare(
        "SELECT u.User_ID, u.Fname, u.Lname, u.phone, u.profile_photo_url,
                sp.senior_ID, sp.address, sp.emergency_contact_name, sp.emergency_contact_phone,
                hr.medical_notes, hr.allergies
         FROM users u
         LEFT JOIN senior_profiles sp ON sp.User_ID = u.User_ID
         LEFT JOIN health_records hr ON hr.senior_ID = sp.senior_ID
         WHERE u.User_ID = ? LIMIT 1"
    );
    $seniorStmt->execute([$activeSeniorUserId]);
    $senior = $seniorStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $seniorId = (int)($senior['senior_ID'] ?? 0);
}

$balanceStmt = $db->prepare(
    "SELECT COALESCE(balance_after, 0) AS points_balance
     FROM silverpoints_ledger
     WHERE User_ID = ?
     ORDER BY ledger_entry_ID DESC
     LIMIT 1"
);
$balanceStmt->execute([$proxyUserId]);
$proxyBalance = (int)($balanceStmt->fetch(PDO::FETCH_ASSOC)['points_balance'] ?? 0);

$upcomingVisits = [];
if ($seniorId > 0) {
    $visitsStmt = $db->prepare(
        "SELECT vr.*, sc.category_name, u.Fname AS pal_fname, u.Lname AS pal_lname, u.profile_photo_url AS pal_photo
         FROM visit_requests vr
         JOIN service_categories sc ON vr.category_ID = sc.category_ID
         LEFT JOIN pal_profiles pp ON vr.pal_ID = pp.pal_ID
         LEFT JOIN users u ON pp.User_ID = u.User_ID
         WHERE vr.senior_ID = ?
         ORDER BY vr.scheduled_start DESC
         LIMIT 8"
    );
    $visitsStmt->execute([$seniorId]);
    $upcomingVisits = $visitsStmt->fetchAll(PDO::FETCH_ASSOC);
}

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/navbar.php';
?>
<div class="container py-4">
    <div class="card mb-3" style="background:linear-gradient(135deg,#2F6F61,#1E5148);color:#fff;">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h3 class="mb-1">Proxy Dashboard</h3>
                <div class="text-white-50">You can gift services and manage care requests for linked seniors.</div>
            </div>
            <div class="badge text-bg-warning px-3 py-2">
                <i class="fa-solid fa-star me-1"></i>Proxy Balance: <?= (int)$proxyBalance ?>
            </div>
        </div>
        <hr class="border-white border-opacity-25">
        <form method="POST" action="/senior_care/controllers/ProxyController.php?action=switchSenior" class="row g-2 align-items-end">
            <div class="col-md-8">
                <label class="form-label text-white-50">Active Senior</label>
                <select class="form-select" name="senior_user_id" required>
                    <?php foreach ($linked as $s): ?>
                        <option value="<?= (int)$s['senior_user_id'] ?>" <?= (int)$s['senior_user_id'] === $activeSeniorUserId ? 'selected' : '' ?>>
                            <?= htmlspecialchars(trim((string)$s['Fname'] . ' ' . (string)$s['Lname'])) ?>
                            <?= !empty($s['relationship_type']) ? ' - ' . htmlspecialchars((string)$s['relationship_type']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <button class="btn btn-light w-100" type="submit"><i class="fa-solid fa-arrows-rotate me-2"></i>Switch</button>
            </div>
        </form>
    </div>

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="profile-card mb-3">
                <div class="profile-card-header">
                    <?php if (!empty($senior['profile_photo_url'])): ?>
                        <img src="<?= htmlspecialchars((string)$senior['profile_photo_url']) ?>" class="profile-avatar" style="object-fit:cover;" alt="Senior Photo">
                    <?php else: ?>
                        <div class="profile-avatar"><?= strtoupper(substr((string)($senior['Fname'] ?? 'S'), 0, 1)) ?></div>
                    <?php endif; ?>
                    <div>
                        <div class="visit-name"><?= htmlspecialchars($activeSeniorName !== '' ? $activeSeniorName : 'Senior') ?></div>
                        <div class="visit-details"><?= htmlspecialchars((string)($senior['phone'] ?? '-')) ?></div>
                    </div>
                </div>
                <div class="profile-info-row"><span class="profile-info-label">Address</span><span class="profile-info-value"><?= htmlspecialchars((string)($senior['address'] ?? '-')) ?></span></div>
                <div class="profile-info-row"><span class="profile-info-label">Allergies</span><span class="profile-info-value"><?= htmlspecialchars((string)($senior['allergies'] ?? '-')) ?></span></div>
                <div class="profile-info-row"><span class="profile-info-label">Medical Notes</span><span class="profile-info-value"><?= htmlspecialchars((string)($senior['medical_notes'] ?? '-')) ?></span></div>
            </div>

            <div class="d-grid gap-2">
                <a class="btn btn-primary" href="/senior_care/views/senior/book_visit.php"><i class="fa-solid fa-gift me-2"></i>Gift a Service (Pay as Proxy)</a>
                <a class="btn btn-secondary-outline" href="/senior_care/views/senior/visit_history.php"><i class="fa-solid fa-clock-rotate-left me-2"></i>View Senior Visit History</a>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card">
                <h4 class="card-header-title">Senior Visits (Recent)</h4>
                <?php if (empty($upcomingVisits)): ?>
                    <p class="text-muted mb-0">No visits found for this senior.</p>
                <?php else: ?>
                    <?php foreach ($upcomingVisits as $visit): ?>
                        <div class="visit-item">
                            <?php if (!empty($visit['pal_photo'])): ?>
                                <img src="<?= htmlspecialchars((string)$visit['pal_photo']) ?>" alt="Pal Photo" class="visit-avatar" style="object-fit:cover;">
                            <?php else: ?>
                                <div class="visit-avatar"><?= strtoupper(substr((string)($visit['pal_fname'] ?? 'P'), 0, 1)) ?></div>
                            <?php endif; ?>
                            <div class="visit-info">
                                <div class="visit-name"><?= htmlspecialchars((string)($visit['category_name'] ?? 'Service')) ?></div>
                                <div class="visit-details">
                                    <?= htmlspecialchars((string)($visit['scheduled_start'] ?? '')) ?> •
                                    Status: <?= htmlspecialchars((string)($visit['status'] ?? '')) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>

