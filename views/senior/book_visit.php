<?php
session_start();
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/database.php';
if (!isset($_SESSION['user_id'])) { header('Location: /senior_care/views/auth/login.php'); exit(); }
requireRole(['Senior', 'FamilyProxy']);

$db = Database::getInstance()->getConnection();
$role = $_SESSION['role'] ?? 'Senior';
$actorUserId = (int)$_SESSION['user_id'];
$targetSeniorUserId = $role === 'FamilyProxy'
    ? (int)($_SESSION['proxy_senior_user_id'] ?? 0)
    : $actorUserId;

$catStmt = $db->query("SELECT category_ID, category_name, base_points_cost, max_duration_hours FROM service_categories WHERE is_active = 1 ORDER BY category_name");
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

$seniorStmt = $db->prepare(
    "SELECT u.User_ID, u.Fname, u.Lname, u.profile_photo_url, sp.senior_ID, sp.address, sp.comfort_profile
     FROM users u
     LEFT JOIN senior_profiles sp ON u.User_ID = sp.User_ID
     WHERE u.User_ID = ?"
);
$seniorStmt->execute([$targetSeniorUserId]);
$senior = $seniorStmt->fetch(PDO::FETCH_ASSOC);

if (!$senior || empty($senior['senior_ID'])) {
    require_once __DIR__ . '/../../models/User.php';
    $newSeniorId = (new User())->ensureSeniorProfile($targetSeniorUserId);
    $seniorStmt->execute([$targetSeniorUserId]);
    $senior = $seniorStmt->fetch(PDO::FETCH_ASSOC);
    $senior['senior_ID'] = $newSeniorId;
}

$pointsStmt = $db->prepare(
    "SELECT COALESCE(balance_after, 0) AS points_balance
     FROM silverpoints_ledger
     WHERE User_ID = ?
     ORDER BY ledger_entry_ID DESC
     LIMIT 1"
);
$payerUserId = $role === 'FamilyProxy' ? $actorUserId : $targetSeniorUserId;
$pointsStmt->execute([$payerUserId]);
$pointsBalance = (int)($pointsStmt->fetch(PDO::FETCH_ASSOC)['points_balance'] ?? 0);

$palStmt = $db->query(
    "SELECT u.Fname, u.Lname, u.profile_photo_url, pp.pal_ID, pp.rating_avg, pp.skills, pp.travel_radius_km
     FROM pal_profiles pp
     JOIN users u ON pp.User_ID = u.User_ID
     WHERE pp.verification_status = 'Approved'
     AND u.is_active = 1
     ORDER BY pp.rating_avg DESC"
);
$pals = $palStmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/navbar.php';
?>
<div class="container py-4">
    <div class="welcome-banner">
        <h3>Book a Visit</h3>
        <?php if ($role === 'FamilyProxy'): ?>
            <p>You’re booking on behalf of <strong><?= htmlspecialchars(($senior['Fname'] ?? '') . ' ' . ($senior['Lname'] ?? '')) ?></strong>.</p>
            <p class="mb-0 text-white-50">Your proxy balance will be used for payment.</p>
        <?php else: ?>
            <p>Hi <?= htmlspecialchars(($senior['Fname'] ?? '') . ' ' . ($senior['Lname'] ?? '')) ?>, choose a service and a trusted pal.</p>
        <?php endif; ?>
        <div class="d-flex flex-wrap gap-3">
            <div class="status-badge status-live">Balance: <?= $pointsBalance ?> SilverPoints</div>
            <?php if (!empty($senior['address'])): ?>
                <div class="status-badge status-pending">Address: <?= htmlspecialchars((string)$senior['address']) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <h3 class="card-header-title">Create New Visit Request</h3>
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        <form method="POST" action="/senior_care/controllers/VisitController.php?action=book">
            <div class="mb-3">
                <label class="form-label">Service Category</label>
                <select id="category_id" name="category_id" class="form-select" required>
                    <option value="">Select Service</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int)$category['category_ID'] ?>" data-cost="<?= (int)$category['base_points_cost'] ?>" data-max-hours="<?= (int)($category['max_duration_hours'] ?? 4) ?>">
                            <?= htmlspecialchars($category['category_name']) ?> (<?= (int)$category['base_points_cost'] ?> points)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Duration (Hours)</label>
                <input id="duration_hours" class="form-control" type="number" name="duration_hours" min="1" max="4" value="1" required>
                <small id="duration-help" class="text-muted">Maximum allowed for selected service: 4 hours.</small>
            </div>
            <div class="mb-3">
                <label class="form-label">Date and Time</label>
                <input class="form-control" type="datetime-local" name="scheduled_start" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Task Description</label>
                <textarea class="form-control" name="task_details" rows="4" required></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Available Pals</label>
                <select name="pal_id" class="form-select" required>
                    <option value="">Select Pal</option>
                    <?php foreach ($pals as $pal): ?>
                        <option value="<?= (int)$pal['pal_ID'] ?>">
                            <?= htmlspecialchars($pal['Fname'] . ' ' . $pal['Lname']) ?> (<?= htmlspecialchars((string)($pal['rating_avg'] ?? 'N/A')) ?>★)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="cost-box">
                <span class="cost-label">Estimated Cost</span>
                <strong id="points-cost-value" class="cost-value">0 SilverPoints</strong>
            </div>
            <button class="btn btn-primary w-100" type="submit">Confirm Booking</button>
        </form>
    </div>
</div>
<a class="panic-btn-fixed" href="/senior_care/views/senior/panic.php"><i class="fa-solid fa-triangle-exclamation"></i> Panic</a>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
<script>
    (function () {
        const category = document.getElementById('category_id');
        const duration = document.getElementById('duration_hours');
        const help = document.getElementById('duration-help');

        function syncDurationLimit() {
            if (!category || !duration) return;
            const selected = category.options[category.selectedIndex];
            const maxHours = parseInt(selected?.getAttribute('data-max-hours') || '4', 10);
            const safeMax = Number.isFinite(maxHours) && maxHours > 0 ? maxHours : 4;
            duration.max = String(safeMax);
            if (parseInt(duration.value || '1', 10) > safeMax) {
                duration.value = String(safeMax);
            }
            if (help) {
                help.textContent = 'Maximum allowed for selected service: ' + safeMax + ' hours.';
            }
        }

        if (category) category.addEventListener('change', syncDurationLimit);
        syncDurationLimit();
    })();
</script>
