<?php
session_start();
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/database.php';
if (!isset($_SESSION['user_id'])) { header('Location: /senior_care/views/auth/login.php'); exit(); }
requireRole(['Senior', 'FamilyProxy']);

$db = Database::getInstance()->getConnection();
$userId = (int)($_SESSION['user_id'] ?? 0);
require_once __DIR__ . '/../../models/Points.php';
$packages = (new Points())->getTopupPackages();

$balanceStmt = $db->prepare(
    "SELECT COALESCE(balance_after, 0) AS points_balance
     FROM silverpoints_ledger
     WHERE User_ID = ?
     ORDER BY ledger_entry_ID DESC
     LIMIT 1"
);
$balanceStmt->execute([$userId]);
$balance = (int)($balanceStmt->fetch(PDO::FETCH_ASSOC)['points_balance'] ?? 0);

$ledgerStmt = $db->prepare(
    "SELECT entry_type, points_amount, description, created_at
     FROM silverpoints_ledger
     WHERE User_ID = ?
     ORDER BY ledger_entry_ID DESC
     LIMIT 20"
);
$ledgerStmt->execute([$userId]);
$entries = $ledgerStmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/navbar.php';
?>
<div class="container py-4">
    <div class="card mb-3">
        <h3 class="mb-2"><i class="fa-solid fa-star me-2"></i>My SilverPoints Wallet</h3>
        <div class="points-number"><?= $balance ?><span class="points-star">★</span></div>
        <p class="text-muted mb-0">Buy paid packages to top up your points and use them for bookings.</p>
    </div>

    <div class="card mb-3">
        <h4 class="mb-3">Buy SilverPoints Package</h4>
        <?php if (!empty($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        <form method="POST" action="/senior_care/controllers/PointsController.php?action=add" class="row g-3 align-items-end">
            <div class="col-md-8">
                <label class="form-label">Choose Package</label>
                <select class="form-select" name="package_id" required>
                    <option value="">Select package</option>
                    <?php foreach ($packages as $id => $pack): ?>
                        <option value="<?= htmlspecialchars((string)$id) ?>">
                            <?= htmlspecialchars((string)$pack['name']) ?> - <?= (int)$pack['points'] ?> points (<?= (int)$pack['price'] ?> EGP)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Payment Method</label>
                <select class="form-select" name="payment_method" required>
                    <option value="">Select method</option>
                    <option value="card">Bank Card</option>
                    <option value="wallet">E-Wallet</option>
                    <option value="bank">Bank Transfer</option>
                </select>
            </div>
            <div class="col-md-8">
                <label class="form-label">Payment Reference</label>
                <input type="text" class="form-control" name="payment_reference" placeholder="Transaction/receipt reference" required>
            </div>
            <div class="col-md-4">
                <button class="btn btn-primary w-100" type="submit"><i class="fa-solid fa-credit-card me-2"></i>Pay & Get Points</button>
            </div>
        </form>
        <small class="text-muted d-block mt-2">Points are added only after payment submission.</small>
    </div>

    <div class="card">
        <h4 class="mb-3">Recent Transactions</h4>
        <?php if (empty($entries)): ?>
            <p class="text-muted mb-0">No transactions yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                    <tr>
                        <th>Type</th>
                        <th>Points</th>
                        <th>Description</th>
                        <th>Date</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($entries as $e): ?>
                        <tr>
                            <td><?= htmlspecialchars((string)$e['entry_type']) ?></td>
                            <td><?= (int)$e['points_amount'] ?></td>
                            <td><?= htmlspecialchars((string)($e['description'] ?? '-')) ?></td>
                            <td><?= htmlspecialchars((string)($e['created_at'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
