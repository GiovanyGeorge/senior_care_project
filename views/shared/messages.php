<?php
session_start();
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../models/Notification.php';
if (!isset($_SESSION['user_id'])) { header('Location: /senior_care/views/auth/login.php'); exit(); }

$userId = (int)$_SESSION['user_id'];
$notifications = (new Notification())->getLatestByUser($userId, 100);

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/navbar.php';
?>
<div class="container py-4">
    <div class="card">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <h3 class="mb-0"><i class="fa-solid fa-bell me-2"></i>Messages & Notifications</h3>
            <form method="POST" action="/senior_care/controllers/NotificationController.php?action=markAllRead">
                <input type="hidden" name="return_to" value="/senior_care/views/shared/messages.php">
                <button class="btn btn-sm btn-outline-primary" type="submit">Read All</button>
            </form>
        </div>
        <?php if (empty($notifications)): ?>
            <p class="text-muted mb-0">No messages yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                    <tr>
                        <th>Title</th>
                        <th>Message</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($notifications as $n): ?>
                        <tr>
                            <td><?= htmlspecialchars((string)($n['title'] ?? 'Notification')) ?></td>
                            <td><?= htmlspecialchars((string)($n['message'] ?? '')) ?></td>
                            <td>
                                <?php if ((int)($n['is_read'] ?? 0) === 0): ?>
                                    <span class="status-badge status-pending">Unread</span>
                                <?php else: ?>
                                    <span class="status-badge status-completed">Read</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars((string)($n['created_at'] ?? '')) ?></td>
                            <td>
                                <?php if ((int)($n['is_read'] ?? 0) === 0): ?>
                                    <form method="POST" action="/senior_care/controllers/NotificationController.php?action=markRead">
                                        <input type="hidden" name="notification_id" value="<?= (int)($n['notification_ID'] ?? 0) ?>">
                                        <input type="hidden" name="return_to" value="/senior_care/views/shared/messages.php">
                                        <button class="btn btn-sm btn-primary" type="submit">Read</button>
                                    </form>
                                <?php else: ?>
                                    <span class="badge text-bg-secondary">-</span>
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
