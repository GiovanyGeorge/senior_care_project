<?php
$role = $_SESSION['role'] ?? null;
$name = $_SESSION['name'] ?? null;
$current = $_SERVER['REQUEST_URI'] ?? '';
$GLOBALS['layout_has_main_wrapper'] = true;
$silverPoints = null;
$notifCount = 0;

if ($role !== null) {
    require_once __DIR__ . '/../../config/database.php';
    try {
        $db = Database::getInstance()->getConnection();
        $sessionUserId = (int)($_SESSION['user_id'] ?? 0);
        if ($sessionUserId > 0) {
            $countStmt = $db->prepare('SELECT COUNT(*) FROM notifications WHERE usersUser_ID = :user_id AND is_read = 0');
            $countStmt->execute(['user_id' => $sessionUserId]);
            $notifCount = (int)$countStmt->fetchColumn();
        }

        if ($role !== 'Admin' && $sessionUserId > 0) {
            $balanceUserId = $sessionUserId;
            $stmt = $db->prepare(
                'SELECT COALESCE(balance_after, 0) AS points_balance
                 FROM silverpoints_ledger
                 WHERE User_ID = :user_id
                 ORDER BY ledger_entry_ID DESC
                 LIMIT 1'
            );
            $stmt->execute(['user_id' => $balanceUserId]);
            $silverPoints = (int)($stmt->fetch(PDO::FETCH_ASSOC)['points_balance'] ?? 0);
        }
    } catch (Throwable $e) {
        $silverPoints = 0;
        $notifCount = 0;
    }
}

function navActive(string $path, string $current): string
{
    return str_contains($current, $path) ? ' active' : '';
}
?>
<?php if ($role === null): ?>
    <div class="topbar">
        <div class="topbar-title">CareNest</div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-secondary-outline" href="/senior_care/views/shared/about.php"><i class="fa-solid fa-circle-info me-2"></i>About</a>
            <a class="btn btn-secondary-outline" href="/senior_care/views/auth/login.php"><i class="fa-solid fa-right-to-bracket me-2"></i>Login</a>
            <a class="btn btn-primary" href="/senior_care/views/auth/register.php"><i class="fa-solid fa-user-plus me-2"></i>Register</a>
        </div>
    </div>
    <div class="main-content" style="margin-left:0;">
<?php else: ?>
    <aside class="sidebar">
        <div class="sidebar-brand">
            <h4>Care<span>Nest</span></h4>
            <div class="text-white-50 small mt-1">
                <i class="fa-solid fa-user me-2"></i><?= htmlspecialchars((string)$name) ?>
            </div>
        </div>
        <nav class="nav flex-column">
            <?php if ($role === 'Senior'): ?>
                <a class="nav-link<?= navActive('/views/senior/dashboard.php', $current) ?>" href="/senior_care/views/senior/dashboard.php"><i class="fa-solid fa-house"></i>Dashboard</a>
                <a class="nav-link<?= navActive('/views/senior/book_visit.php', $current) ?>" href="/senior_care/views/senior/book_visit.php"><i class="fa-solid fa-calendar-plus"></i>Book Visit</a>
                <a class="nav-link<?= navActive('/views/senior/visit_history.php', $current) ?>" href="/senior_care/views/senior/visit_history.php"><i class="fa-solid fa-clock-rotate-left"></i>My Visits</a>
                <a class="nav-link<?= navActive('/views/senior/wallet.php', $current) ?>" href="/senior_care/views/senior/wallet.php"><i class="fa-solid fa-star"></i>SilverPoints</a>
                <a class="nav-link<?= navActive('/views/shared/messages.php', $current) ?>" href="/senior_care/views/shared/messages.php"><i class="fa-solid fa-bell"></i>Notifications<?php if ($notifCount > 0): ?> (<?= $notifCount ?>)<?php endif; ?></a>
                <a class="nav-link<?= navActive('/views/senior/profile.php', $current) ?>" href="/senior_care/views/senior/profile.php"><i class="fa-solid fa-user"></i>Profile</a>
                <a class="nav-link" href="/senior_care/views/senior/panic.php"><i class="fa-solid fa-triangle-exclamation"></i>Panic</a>
            <?php elseif ($role === 'FamilyProxy'): ?>
                <a class="nav-link<?= navActive('/views/proxy/dashboard.php', $current) ?>" href="/senior_care/views/proxy/dashboard.php"><i class="fa-solid fa-house"></i>Dashboard</a>
                <a class="nav-link<?= navActive('/views/senior/book_visit.php', $current) ?>" href="/senior_care/views/senior/book_visit.php"><i class="fa-solid fa-gift"></i>Gift a Service</a>
                <a class="nav-link<?= navActive('/views/senior/visit_history.php', $current) ?>" href="/senior_care/views/senior/visit_history.php"><i class="fa-solid fa-clock-rotate-left"></i>Senior Visits</a>
                <a class="nav-link<?= navActive('/views/senior/wallet.php', $current) ?>" href="/senior_care/views/senior/wallet.php"><i class="fa-solid fa-star"></i>My SilverPoints</a>
                <a class="nav-link<?= navActive('/views/shared/messages.php', $current) ?>" href="/senior_care/views/shared/messages.php"><i class="fa-solid fa-bell"></i>Notifications<?php if ($notifCount > 0): ?> (<?= $notifCount ?>)<?php endif; ?></a>
                <a class="nav-link<?= navActive('/views/proxy/profile.php', $current) ?>" href="/senior_care/views/proxy/profile.php"><i class="fa-solid fa-id-card"></i>Proxy Profile</a>
            <?php elseif ($role === 'Pal'): ?>
                <a class="nav-link<?= navActive('/views/pal/dashboard.php', $current) ?>" href="/senior_care/views/pal/dashboard.php"><i class="fa-solid fa-house"></i>Dashboard</a>
                <a class="nav-link<?= navActive('/views/pal/requests.php', $current) ?>" href="/senior_care/views/pal/requests.php"><i class="fa-solid fa-inbox"></i>Requests</a>
                <a class="nav-link<?= navActive('/views/pal/schedule.php', $current) ?>" href="/senior_care/views/pal/schedule.php"><i class="fa-solid fa-calendar-check"></i>My Schedule</a>
                <a class="nav-link<?= navActive('/views/pal/earnings.php', $current) ?>" href="/senior_care/views/pal/earnings.php"><i class="fa-solid fa-coins"></i>Earnings</a>
                <a class="nav-link<?= navActive('/views/shared/messages.php', $current) ?>" href="/senior_care/views/shared/messages.php"><i class="fa-solid fa-bell"></i>Notifications<?php if ($notifCount > 0): ?> (<?= $notifCount ?>)<?php endif; ?></a>
                <a class="nav-link<?= navActive('/views/pal/profile.php', $current) ?>" href="/senior_care/views/pal/profile.php"><i class="fa-solid fa-id-card"></i>Profile</a>
            <?php elseif ($role === 'Admin'): ?>
                <a class="nav-link<?= navActive('/views/admin/dashboard.php', $current) ?>" href="/senior_care/views/admin/dashboard.php"><i class="fa-solid fa-gauge-high"></i>Dashboard</a>
                <a class="nav-link<?= navActive('/views/admin/users.php', $current) ?>" href="/senior_care/views/admin/users.php"><i class="fa-solid fa-users"></i>Users</a>
                <a class="nav-link<?= navActive('/views/admin/visits.php', $current) ?>" href="/senior_care/views/admin/visits.php"><i class="fa-solid fa-list-check"></i>Visits</a>
                <a class="nav-link<?= navActive('/views/admin/reports.php', $current) ?>" href="/senior_care/views/admin/reports.php"><i class="fa-solid fa-file-lines"></i>Reports</a>
                <a class="nav-link<?= navActive('/views/admin/broadcasts.php', $current) ?>" href="/senior_care/views/admin/broadcasts.php"><i class="fa-solid fa-bullhorn"></i>Broadcasts</a>
                <a class="nav-link<?= navActive('/views/admin/notifications.php', $current) ?>" href="/senior_care/views/admin/notifications.php"><i class="fa-solid fa-bell"></i>Notifications</a>
                <a class="nav-link<?= navActive('/views/admin/profile.php', $current) ?>" href="/senior_care/views/admin/profile.php"><i class="fa-solid fa-user-gear"></i>Profile</a>
            <?php endif; ?>

            <hr class="text-white-50 my-2">
            <a class="nav-link<?= navActive('/views/shared/about.php', $current) ?>" href="/senior_care/views/shared/about.php"><i class="fa-solid fa-circle-info"></i>About</a>
            <a class="nav-link" href="/senior_care/index.php?action=logout"><i class="fa-solid fa-right-from-bracket"></i>Logout</a>
        </nav>
    </aside>
    <div class="main-content">
        <div class="topbar">
            <div class="topbar-title"><?= htmlspecialchars((string)($role === 'FamilyProxy' ? 'Proxy' : $role)) ?></div>
            <div class="d-flex align-items-center gap-3">
                <?php if ($silverPoints !== null): ?>
                    <div class="badge text-bg-warning px-3 py-2">
                        <i class="fa-solid fa-star me-1"></i>SilverPoints: <?= (int)$silverPoints ?>
                    </div>
                <?php endif; ?>
                <a class="btn btn-secondary-outline" href="<?= $role === 'Admin' ? '/senior_care/views/admin/notifications.php' : '/senior_care/views/shared/messages.php' ?>">
                    <i class="fa-solid fa-bell me-1"></i>Noti<?php if ($notifCount > 0): ?> (<?= $notifCount ?>)<?php endif; ?>
                </a>
                <div class="text-muted small">
                    <i class="fa-solid fa-shield-heart me-2"></i>Senior-friendly UI • Secure Sessions • PDO
                </div>
            </div>
        </div>
<?php endif; ?>
