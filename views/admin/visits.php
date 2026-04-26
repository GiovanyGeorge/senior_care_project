<?php
session_start();
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/database.php';
if (!isset($_SESSION['user_id'])) { header('Location: /senior_care/views/auth/login.php'); exit(); }
requireRole(['Admin']);

$db = Database::getInstance()->getConnection();
$statusFilter = $_GET['status'] ?? '';

$sql = "SELECT vr.visit_ID, vr.status, vr.scheduled_start, vr.scheduled_end, vr.points_reserved, vr.points_paid,
               sc.category_name,
               su.Fname AS senior_fname, su.Lname AS senior_lname,
               pu.Fname AS pal_fname, pu.Lname AS pal_lname
        FROM visit_requests vr
        JOIN service_categories sc ON sc.category_ID = vr.category_ID
        JOIN senior_profiles sp ON sp.senior_ID = vr.senior_ID
        JOIN users su ON su.User_ID = sp.User_ID
        LEFT JOIN pal_profiles pp ON pp.pal_ID = vr.pal_ID
        LEFT JOIN users pu ON pu.User_ID = pp.User_ID";

$params = [];
if ($statusFilter !== '') {
    $sql .= " WHERE vr.status = ?";
    $params[] = $statusFilter;
}
$sql .= " ORDER BY vr.visit_ID DESC LIMIT 200";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/navbar.php';
?>
<div class="container py-4">
    <div class="card">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h3 class="mb-0">Manage Visits</h3>
            <form class="d-flex gap-2" method="GET">
                <select name="status" class="form-select" style="min-width: 200px;">
                    <option value="">All Statuses</option>
                    <?php foreach (['Pending','Accepted','En_Route','Live','Completed','Rejected','Cancelled','Rated'] as $st): ?>
                        <option value="<?= htmlspecialchars($st) ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= htmlspecialchars($st) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-primary" type="submit">Filter</button>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Service</th>
                    <th>Senior</th>
                    <th>Pal</th>
                    <th>Schedule</th>
                    <th>Status</th>
                    <th>Points</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($visits)): ?>
                    <tr><td colspan="7">No visits found.</td></tr>
                <?php else: ?>
                    <?php foreach ($visits as $v): ?>
                        <tr>
                            <td>#<?= (int)$v['visit_ID'] ?></td>
                            <td><?= htmlspecialchars((string)$v['category_name']) ?></td>
                            <td><?= htmlspecialchars(trim((string)$v['senior_fname'] . ' ' . (string)$v['senior_lname'])) ?></td>
                            <td><?= htmlspecialchars(trim((string)($v['pal_fname'] ?? '') . ' ' . (string)($v['pal_lname'] ?? ''))) ?: '-' ?></td>
                            <td>
                                <div><strong><?= htmlspecialchars((string)$v['scheduled_start']) ?></strong></div>
                                <small class="text-muted"><?= htmlspecialchars((string)$v['scheduled_end']) ?></small>
                            </td>
                            <td><span class="status-badge status-pending"><?= htmlspecialchars((string)$v['status']) ?></span></td>
                            <td>
                                <div>Reserved: <?= htmlspecialchars((string)$v['points_reserved']) ?></div>
                                <small class="text-muted">Paid: <?= htmlspecialchars((string)$v['points_paid']) ?></small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
