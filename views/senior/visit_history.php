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

$seniorIdStmt = $db->prepare('SELECT senior_ID FROM senior_profiles WHERE User_ID = ? LIMIT 1');
$seniorIdStmt->execute([$userId]);
$seniorId = (int)($seniorIdStmt->fetchColumn() ?: 0);

$visits = [];
if ($seniorId > 0) {
    $stmt = $db->prepare(
        "SELECT vr.visit_ID, vr.status, vr.scheduled_start, vr.scheduled_end, vr.task_details, vr.points_reserved, vr.points_paid, vr.pal_ID,
                sc.category_name,
                pu.Fname AS pal_fname, pu.Lname AS pal_lname,
                r.rating_ID, r.rating_score, r.comment AS rating_comment
         FROM visit_requests vr
         JOIN service_categories sc ON sc.category_ID = vr.category_ID
         LEFT JOIN pal_profiles pp ON pp.pal_ID = vr.pal_ID
         LEFT JOIN users pu ON pu.User_ID = pp.User_ID
         LEFT JOIN ratings r ON r.visit_ID = vr.visit_ID
         WHERE vr.senior_ID = ?
         ORDER BY vr.created_at DESC"
    );
    $stmt->execute([$seniorId]);
    $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/navbar.php';
?>
<div class="container py-4">
    <div class="card">
        <h3 class="mb-3">Visit History</h3>
        <?php if ($seniorId === 0): ?>
            <p class="text-muted mb-0">No senior profile found for your account yet.</p>
        <?php elseif (empty($visits)): ?>
            <p class="text-muted mb-0">No visits found yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                    <tr>
                        <th>Visit</th>
                        <th>Service</th>
                        <th>Pal</th>
                        <th>Schedule</th>
                        <th>Status</th>
                        <th>Points</th>
                        <th>Rating</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($visits as $v): ?>
                        <tr>
                            <td>#<?= (int)$v['visit_ID'] ?></td>
                            <td><?= htmlspecialchars((string)$v['category_name']) ?></td>
                            <td><?= htmlspecialchars(trim((string)($v['pal_fname'] ?? '') . ' ' . (string)($v['pal_lname'] ?? ''))) ?: '-' ?></td>
                            <td>
                                <div><strong><?= htmlspecialchars((string)$v['scheduled_start']) ?></strong></div>
                                <small class="text-muted"><?= htmlspecialchars((string)$v['scheduled_end']) ?></small>
                            </td>
                            <td><span class="status-badge status-pending"><?= htmlspecialchars((string)$v['status']) ?></span></td>
                            <td>
                                <div>Reserved: <?= (int)$v['points_reserved'] ?></div>
                                <small class="text-muted">Paid: <?= (int)$v['points_paid'] ?></small>
                            </td>
                            <td>
                                <?php if ((string)$v['status'] === 'Completed' && !empty($v['pal_ID'])): ?>
                                    <?php if (!empty($v['rating_ID'])): ?>
                                        <span class="status-badge status-completed"><?= htmlspecialchars((string)$v['rating_score']) ?>★</span>
                                    <?php else: ?>
                                        <form method="POST" action="/senior_care/controllers/RatingController.php?action=submit" class="d-flex flex-column gap-2">
                                            <input type="hidden" name="visit_id" value="<?= (int)$v['visit_ID'] ?>">
                                            <select class="form-select form-select-sm" name="rating_score" required>
                                                <option value="">Rate</option>
                                                <option value="5">5 - Excellent</option>
                                                <option value="4">4 - Good</option>
                                                <option value="3">3 - OK</option>
                                                <option value="2">2 - Poor</option>
                                                <option value="1">1 - Bad</option>
                                            </select>
                                            <input class="form-control form-control-sm" name="comment" placeholder="Optional comment" maxlength="225">
                                            <button class="btn btn-sm btn-primary" type="submit">Submit</button>
                                        </form>
                                    <?php endif; ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if (!empty($v['task_details'])): ?>
                            <tr>
                                <td colspan="7" class="pt-0">
                                    <div class="text-muted"><strong>Task:</strong> <?= htmlspecialchars((string)$v['task_details']) ?></div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<a class="panic-btn-fixed" href="/senior_care/views/senior/panic.php"><i class="fa-solid fa-triangle-exclamation"></i> Panic</a>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
