<?php
session_start();
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/database.php';
if (!isset($_SESSION['user_id'])) { header('Location: /senior_care/views/auth/login.php'); exit(); }
requireRole(['Pal']);

$db = Database::getInstance()->getConnection();
$palUserId = (int)$_SESSION['user_id'];

$palIdStmt = $db->prepare('SELECT pal_ID FROM pal_profiles WHERE User_ID = ? LIMIT 1');
$palIdStmt->execute([$palUserId]);
$palId = (int)($palIdStmt->fetchColumn() ?: 0);

$ledgerStmt = $db->prepare(
    "SELECT ledger_entry_ID, visit_ID, entry_type, points_amount, balance_after, description, created_at
     FROM silverpoints_ledger
     WHERE User_ID = ?
     ORDER BY ledger_entry_ID DESC
     LIMIT 30"
);
$ledgerStmt->execute([$palUserId]);
$ledger = $ledgerStmt->fetchAll(PDO::FETCH_ASSOC);

$balanceStmt = $db->prepare(
    "SELECT COALESCE(balance_after, 0) AS points_balance
     FROM silverpoints_ledger
     WHERE User_ID = ?
     ORDER BY ledger_entry_ID DESC
     LIMIT 1"
);
$balanceStmt->execute([$palUserId]);
$balance = (int)($balanceStmt->fetch(PDO::FETCH_ASSOC)['points_balance'] ?? 0);

$cashouts = [];
if ($palId > 0) {
    $cashStmt = $db->prepare(
        "SELECT cr.cashout_request_ID, cr.points_requested, cr.cash_equivalent, cr.status, cr.requested_at, cr.processed_at,
                cd.provider_name, cd.account_identifier
         FROM cashout_requests cr
         JOIN cashout_destinations cd ON cd.destination_ID = cr.destination_ID
         WHERE cr.pal_ID = ?
         ORDER BY cr.cashout_request_ID DESC
         LIMIT 20"
    );
    $cashStmt->execute([$palId]);
    $cashouts = $cashStmt->fetchAll(PDO::FETCH_ASSOC);
}

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/navbar.php';
?>
<div class="container py-4">
    <div class="card mb-3">
        <h4 class="mb-2">Current SilverPoints Balance</h4>
        <div class="points-number"><?= $balance ?><span class="points-star">★</span></div>
    </div>

    <div class="card mb-3">
        <h4 class="mb-3">Request Cashout</h4>
        <?php if (!empty($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        <form method="POST" action="/senior_care/controllers/PalController.php?action=requestCashout" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Points to Cashout</label>
                <input type="number" class="form-control" name="points_requested" min="1" max="<?= max($balance, 1) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Destination Type</label>
                <select class="form-select" name="destination_type" required>
                    <option value="Wallet">Wallet</option>
                    <option value="Bank">Bank</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Provider Name</label>
                <input type="text" class="form-control" name="provider_name" placeholder="Vodafone Cash / Bank Name" required>
            </div>
            <div class="col-md-8">
                <label class="form-label">Account Identifier</label>
                <input type="text" class="form-control" name="account_identifier" placeholder="Wallet number / IBAN" required>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-money-bill-transfer me-2"></i>Submit Cashout</button>
            </div>
        </form>
        <small class="text-muted d-block mt-2">Current conversion: 1 point = 1 EGP. Requests are reviewed by admin.</small>
    </div>

    <div class="card">
        <h3 class="mb-3">Earnings</h3>
        <p class="text-muted mb-0">Your last 30 SilverPoints ledger entries.</p>
        <div class="table-responsive mt-3">
            <table class="table align-middle">
                <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Points</th>
                    <th>Balance After</th>
                    <th>Visit</th>
                    <th>Description</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($ledger)): ?>
                    <tr><td colspan="6">No ledger activity yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($ledger as $l): ?>
                        <tr>
                            <td><?= htmlspecialchars((string)$l['created_at']) ?></td>
                            <td><?= htmlspecialchars((string)$l['entry_type']) ?></td>
                            <td><?= htmlspecialchars((string)$l['points_amount']) ?></td>
                            <td><?= htmlspecialchars((string)$l['balance_after']) ?></td>
                            <td><?= !empty($l['visit_ID']) ? '#' . (int)$l['visit_ID'] : '-' ?></td>
                            <td><?= htmlspecialchars((string)($l['description'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h4 class="card-header-title">Cashout Requests</h4>
        <?php if ($palId === 0): ?>
            <p class="text-muted mb-0">No pal profile found yet.</p>
        <?php elseif (empty($cashouts)): ?>
            <p class="text-muted mb-0">No cashout requests yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Points</th>
                        <th>Cash</th>
                        <th>Status</th>
                        <th>Destination</th>
                        <th>Requested</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($cashouts as $c): ?>
                        <tr>
                            <td>#<?= (int)$c['cashout_request_ID'] ?></td>
                            <td><?= htmlspecialchars((string)$c['points_requested']) ?></td>
                            <td><?= htmlspecialchars((string)($c['cash_equivalent'] ?? '-')) ?></td>
                            <td><span class="status-badge status-pending"><?= htmlspecialchars((string)$c['status']) ?></span></td>
                            <td><?= htmlspecialchars(trim((string)($c['provider_name'] ?? '') . ' ' . (string)($c['account_identifier'] ?? ''))) ?></td>
                            <td><?= htmlspecialchars((string)$c['requested_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
