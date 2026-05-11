<?php
session_start();
require_once __DIR__ . '/../../config/auth.php';
if (!isset($_SESSION['user_id'])) { header('Location: /senior_care/views/auth/login.php'); exit(); }
requireRole(['Admin']);
require_once __DIR__ . '/../../models/Notification.php';

$notifications = (new Notification())->getLatestByUser((int)$_SESSION['user_id'], 50);

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/navbar.php';
?>
<div class="container py-4">
    <div class="card">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h3 class="mb-1">Notifications</h3>
                <p class="text-muted mb-0">Latest admin alerts and account approval requests.</p>
            </div>
            <form method="POST" action="/senior_care/controllers/NotificationController.php?action=markAllRead">
                <input type="hidden" name="return_to" value="/senior_care/views/admin/notifications.php">
                <button class="btn btn-sm btn-outline-primary" type="submit">Read All</button>
            </form>
        </div>
        <?php if (empty($notifications)): ?>
            <p class="mb-0">No notifications yet.</p>
        <?php else: ?>
            <?php foreach ($notifications as $note): ?>
                <div class="border rounded p-3 mb-3 <?= (int)$note['is_read'] === 1 ? '' : 'border-primary' ?>">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div>
                            <h5 class="mb-1"><?= htmlspecialchars((string)$note['title']) ?></h5>
                            <p class="mb-1"><?= htmlspecialchars((string)$note['message']) ?></p>
                            <small class="text-muted"><?= htmlspecialchars((string)$note['created_at']) ?></small>
                        </div>
                        <?php if ((int)$note['is_read'] === 0): ?>
                            <form method="POST" action="/senior_care/controllers/NotificationController.php?action=markRead">
                                <input type="hidden" name="notification_id" value="<?= (int)$note['notification_ID'] ?>">
                                <input type="hidden" name="return_to" value="/senior_care/views/admin/notifications.php">
                                <button class="btn btn-sm btn-primary" type="submit">Read</button>
                            </form>
                        <?php else: ?>
                            <span class="badge text-bg-secondary">Read</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
