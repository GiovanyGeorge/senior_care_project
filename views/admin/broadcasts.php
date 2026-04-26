<?php
session_start();
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/database.php';
if (!isset($_SESSION['user_id'])) { header('Location: /senior_care/views/auth/login.php'); exit(); }
requireRole(['Admin']);

$db = Database::getInstance()->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $db->prepare(
        'INSERT INTO admin_broadcasts (admin_ID, title, message_body, target_role, severity_level, expires_at)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        (int)$_SESSION['user_id'],
        trim($_POST['title'] ?? ''),
        trim($_POST['message_body'] ?? ''),
        $_POST['target_role'] ?? 'all',
        $_POST['severity_level'] ?? 'Info',
        $_POST['expires_at'] ?? null,
    ]);
    $_SESSION['success'] = 'Broadcast created.';
    header('Location: /senior_care/views/admin/broadcasts.php');
    exit();
}

$broadcasts = $db->query(
    'SELECT broadcast_ID, title, message_body, target_role, severity_level, created_at, expires_at
     FROM admin_broadcasts
     ORDER BY broadcast_ID DESC
     LIMIT 50'
)->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/navbar.php';
?>
<div class="container py-4">
    <div class="card">
        <h3 class="mb-3">Broadcasts</h3>
        <?php if (!empty($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <form method="POST" class="mb-4">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Title</label>
                    <input class="form-control" name="title" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Expires At (optional)</label>
                    <input class="form-control" type="datetime-local" name="expires_at">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Target Role</label>
                    <select class="form-select" name="target_role">
                        <option value="all">All</option>
                        <option value="senior">Senior</option>
                        <option value="pal">Pal</option>
                        <option value="proxy">Proxy</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Severity</label>
                    <select class="form-select" name="severity_level">
                        <option value="Info">Info</option>
                        <option value="Warning">Warning</option>
                        <option value="Critical">Critical</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Message</label>
                    <textarea class="form-control" name="message_body" rows="4" required></textarea>
                </div>
            </div>
            <button class="btn btn-primary w-100 mt-3" type="submit">Create Broadcast</button>
        </form>

        <h4 class="card-header-title">Recent Broadcasts</h4>
        <?php if (empty($broadcasts)): ?>
            <p class="text-muted mb-0">No broadcasts yet.</p>
        <?php else: ?>
            <?php foreach ($broadcasts as $b): ?>
                <div class="border rounded p-3 mb-3">
                    <div class="d-flex justify-content-between gap-3">
                        <div>
                            <strong><?= htmlspecialchars((string)$b['title']) ?></strong>
                            <div class="text-muted small">
                                Target: <?= htmlspecialchars((string)$b['target_role']) ?> |
                                Severity: <?= htmlspecialchars((string)$b['severity_level']) ?> |
                                Created: <?= htmlspecialchars((string)$b['created_at']) ?>
                            </div>
                        </div>
                        <span class="badge text-bg-secondary">#<?= (int)$b['broadcast_ID'] ?></span>
                    </div>
                    <div class="mt-2"><?= nl2br(htmlspecialchars((string)$b['message_body'])) ?></div>
                    <?php if (!empty($b['expires_at'])): ?>
                        <div class="text-muted small mt-2">Expires: <?= htmlspecialchars((string)$b['expires_at']) ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
