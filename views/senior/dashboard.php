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

$actorName = $_SESSION['name'] ?? '';
if ($role === 'FamilyProxy') {
    $actorStmt = $db->prepare("SELECT Fname, Lname FROM users WHERE User_ID = ? LIMIT 1");
    $actorStmt->execute([$actorUserId]);
    $actorRow = $actorStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $actorName = trim((string)($actorRow['Fname'] ?? '') . ' ' . (string)($actorRow['Lname'] ?? ''));
}

$stmt = $db->prepare(
    "SELECT u.Fname, u.Lname, u.phone, u.profile_photo_url,
            sp.senior_ID, sp.address, sp.emergency_contact_name, sp.emergency_contact_phone,
            hr.medical_notes, hr.allergies
     FROM users u
     LEFT JOIN senior_profiles sp ON u.User_ID = sp.User_ID
     LEFT JOIN health_records hr ON sp.senior_ID = hr.senior_ID
     WHERE u.User_ID = ?"
);
$stmt->execute([$userId]);
$senior = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$seniorId = (int)($senior['senior_ID'] ?? 0);

$balanceStmt = $db->prepare(
    "SELECT COALESCE(balance_after, 0) AS points_balance
     FROM silverpoints_ledger
     WHERE User_ID = ?
     ORDER BY ledger_entry_ID DESC
     LIMIT 1"
);
$payerUserId = $role === 'FamilyProxy' ? $actorUserId : $userId;
$balanceStmt->execute([$payerUserId]);
$balance = (int)($balanceStmt->fetch(PDO::FETCH_ASSOC)['points_balance'] ?? 0);

$upcomingVisits = [];
if ($seniorId > 0) {
    $visitsStmt = $db->prepare(
        "SELECT vr.*, sc.category_name, u.Fname AS pal_fname, u.Lname AS pal_lname, u.profile_photo_url AS pal_photo,
                pp.skills AS pal_skills, pp.rating_avg AS pal_rating
         FROM visit_requests vr
         JOIN service_categories sc ON vr.category_ID = sc.category_ID
         LEFT JOIN pal_profiles pp ON vr.pal_ID = pp.pal_ID
         LEFT JOIN users u ON pp.User_ID = u.User_ID
         WHERE vr.senior_ID = ?
         AND vr.status NOT IN ('Completed', 'Cancelled')
         ORDER BY vr.scheduled_start ASC
         LIMIT 5"
    );
    $visitsStmt->execute([$seniorId]);
    $upcomingVisits = $visitsStmt->fetchAll(PDO::FETCH_ASSOC);
}

$totalVisits = 0;
$completedCount = 0;
if ($seniorId > 0) {
    $totalStmt = $db->prepare("SELECT COUNT(*) AS count FROM visit_requests WHERE senior_ID = ?");
    $totalStmt->execute([$seniorId]);
    $totalVisits = (int)($totalStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

    $completedStmt = $db->prepare("SELECT COUNT(*) AS count FROM visit_requests WHERE senior_ID = ? AND status = 'Completed'");
    $completedStmt->execute([$seniorId]);
    $completedCount = (int)($completedStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
}

$notifStmt = $db->prepare(
    "SELECT notification_ID, title, message_body AS message, is_read, created_at
     FROM notifications
     WHERE usersUser_ID = ?
     ORDER BY created_at DESC
     LIMIT 6"
);
$notifStmt->execute([$userId]);
$notifications = $notifStmt->fetchAll(PDO::FETCH_ASSOC);

$notifCountStmt = $db->prepare("SELECT COUNT(*) AS count FROM notifications WHERE usersUser_ID = ? AND is_read = 0");
$notifCountStmt->execute([$userId]);
$notifTotal = (int)($notifCountStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/navbar.php';
?>
<div class="container py-4">
    <div class="welcome-banner">
        <h3>Welcome, <?= htmlspecialchars($role === 'FamilyProxy' ? (string)$actorName : trim(($senior['Fname'] ?? '') . ' ' . ($senior['Lname'] ?? 'Senior'))) ?></h3>
        <?php if ($role === 'FamilyProxy'): ?>
            <p class="mb-0">You are assisting <strong><?= htmlspecialchars((string)($_SESSION['proxy_senior_name'] ?? 'a senior')) ?></strong>. Your proxy balance is used for payments.</p>
        <?php else: ?>
            <p class="mb-0">Your CareNest support network is ready to help.</p>
        <?php endif; ?>
        <a class="btn-request mt-3 d-inline-block" href="/senior_care/views/senior/book_visit.php"><?= $role === 'FamilyProxy' ? 'Gift a Visit' : 'Request New Visit' ?></a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon green"><i class="fa-solid fa-calendar-check"></i></div>
                <div>
                    <div class="stat-number"><?= $totalVisits ?></div>
                    <div class="stat-label">Total Visits</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fa-solid fa-circle-check"></i></div>
                <div>
                    <div class="stat-number"><?= $completedCount ?></div>
                    <div class="stat-label">Completed Visits</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fa-solid fa-bell"></i></div>
                <div>
                    <div class="stat-number"><?= $notifTotal ?></div>
                    <div class="stat-label">Unread Notifications</div>
                </div>
            </div>
        </div>
    </div>

    <div class="points-card mb-4">
        <div class="points-label">SilverPoints Balance</div>
        <div class="points-number"><?= (int)$balance ?><span class="points-star">★</span></div>
        <div class="points-sub">Use points to book support visits</div>
        <a class="btn btn-primary mt-3" href="/senior_care/views/senior/wallet.php"><i class="fa-solid fa-plus me-2"></i>Add Points</a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><a class="quick-action-card" href="/senior_care/views/senior/book_visit.php"><div class="quick-action-icon"><i class="fa-solid fa-calendar-plus"></i></div><div class="quick-action-label">Book Visit</div></a></div>
        <div class="col-md-4"><a class="quick-action-card" href="/senior_care/views/senior/visit_history.php"><div class="quick-action-icon"><i class="fa-solid fa-clock-rotate-left"></i></div><div class="quick-action-label">My Visits</div></a></div>
        <div class="col-md-4"><a class="quick-action-card" href="/senior_care/views/senior/profile.php"><div class="quick-action-icon"><i class="fa-solid fa-user"></i></div><div class="quick-action-label">My Profile</div></a></div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card">
                <h4 class="card-header-title">Upcoming Visits</h4>
                <?php if (empty($upcomingVisits)): ?>
                    <p class="text-muted mb-0">No upcoming visits yet.</p>
                <?php else: ?>
                    <?php foreach ($upcomingVisits as $visit): ?>
                        <?php
                        $statusRaw = (string)($visit['status'] ?? 'Pending');
                        $statusClass = match (strtolower($statusRaw)) {
                            'accepted', 'confirmed' => 'accepted',
                            'completed' => 'completed',
                            'cancelled', 'rejected' => 'cancelled',
                            'live' => 'live',
                            default => 'pending',
                        };
                        ?>
                        <div class="visit-item">
                            <?php if (!empty($visit['pal_photo'])): ?>
                                <img src="<?= htmlspecialchars((string)$visit['pal_photo']) ?>" alt="Pal Photo" class="visit-avatar" style="object-fit:cover;">
                            <?php else: ?>
                                <div class="visit-avatar"><?= strtoupper(substr((string)($visit['pal_fname'] ?? 'P'), 0, 1)) ?></div>
                            <?php endif; ?>
                            <div class="visit-info">
                                <div class="visit-name"><?= htmlspecialchars((string)($visit['category_name'] ?? 'Service')) ?> with <?= htmlspecialchars(trim(($visit['pal_fname'] ?? '') . ' ' . ($visit['pal_lname'] ?? ''))) ?></div>
                                <div class="visit-details">
                                    <?= htmlspecialchars((string)($visit['scheduled_start'] ?? '')) ?>
                                    <?php if (!empty($visit['pal_rating'])): ?>
                                        • Rating: <?= htmlspecialchars((string)$visit['pal_rating']) ?>★
                                    <?php endif; ?>
                                    <?php if (!empty($visit['pal_skills'])): ?>
                                        • Skills: <?= htmlspecialchars((string)$visit['pal_skills']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="d-flex flex-column align-items-end gap-2">
                                <span class="status-badge status-<?= $statusClass ?>"><?= htmlspecialchars($statusRaw) ?></span>
                                <?php if (!in_array($statusRaw, ['Completed','Cancelled'], true)): ?>
                                    <form method="POST" action="/senior_care/controllers/VisitController.php?action=cancel" onsubmit="return confirm('Cancel this service?');">
                                        <input type="hidden" name="visit_id" value="<?= (int)$visit['visit_ID'] ?>">
                                        <input type="hidden" name="return_to" value="/senior_care/views/senior/dashboard.php">
                                        <input type="hidden" name="reason" value="Cancelled by senior from dashboard.">
                                        <button class="btn btn-sm btn-danger" type="submit">Cancel</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="profile-card mb-3">
                <div class="profile-card-header">
                    <?php if (!empty($senior['profile_photo_url'])): ?>
                        <img src="<?= htmlspecialchars((string)$senior['profile_photo_url']) ?>" alt="Senior Photo" class="profile-avatar" style="object-fit:cover;">
                    <?php else: ?>
                        <div class="profile-avatar"><?= strtoupper(substr((string)($senior['Fname'] ?? 'S'), 0, 1)) ?></div>
                    <?php endif; ?>
                    <div>
                        <div class="visit-name"><?= htmlspecialchars(trim(($senior['Fname'] ?? '') . ' ' . ($senior['Lname'] ?? ''))) ?></div>
                        <div class="visit-details"><?= htmlspecialchars((string)($senior['phone'] ?? '-')) ?></div>
                    </div>
                </div>
                <div class="profile-info-row"><span class="profile-info-label">Emergency Contact</span><span class="profile-info-value"><?= htmlspecialchars((string)($senior['emergency_contact_name'] ?? '-')) ?></span></div>
                <div class="profile-info-row"><span class="profile-info-label">Emergency Phone</span><span class="profile-info-value"><?= htmlspecialchars((string)($senior['emergency_contact_phone'] ?? '-')) ?></span></div>
                <div class="profile-info-row"><span class="profile-info-label">Allergies</span><span class="profile-info-value"><?= htmlspecialchars((string)($senior['allergies'] ?? '-')) ?></span></div>
            </div>

            <div class="card">
                <h4 class="card-header-title">Recent Notifications</h4>
                <?php if (empty($notifications)): ?>
                    <p class="text-muted mb-0">No notifications.</p>
                <?php else: ?>
                    <?php foreach ($notifications as $note): ?>
                        <div class="notif-item <?= (int)$note['is_read'] === 0 ? 'unread' : '' ?>">
                            <div>
                                <strong><?= htmlspecialchars((string)($note['title'] ?? 'Notice')) ?></strong><br>
                                <span><?= htmlspecialchars((string)($note['message'] ?? '')) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<a class="panic-btn-fixed" href="/senior_care/views/senior/panic.php"><i class="fa-solid fa-triangle-exclamation"></i> Panic</a>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
