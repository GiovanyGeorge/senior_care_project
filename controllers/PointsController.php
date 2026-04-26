<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/Points.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../models/Notification.php';

class PointsController
{
    public function balance(): void
    {
        requireLogin();
        header('Content-Type: application/json');
        echo json_encode(['balance' => (new Points())->getBalance((int)$_SESSION['user_id'])]);
        exit();
    }

    public function add(): void
    {
        requireRole(['Senior', 'FamilyProxy']);
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $pointsModel = new Points();
        $packages = $pointsModel->getTopupPackages();
        $packageId = trim((string)($_POST['package_id'] ?? ''));
        $paymentMethod = trim((string)($_POST['payment_method'] ?? ''));
        $paymentRef = trim((string)($_POST['payment_reference'] ?? ''));
        $returnTo = '/senior_care/views/senior/wallet.php';

        if (!isset($packages[$packageId])) {
            $_SESSION['error'] = 'Please choose a valid points package.';
            header('Location: ' . $returnTo);
            exit();
        }

        if (!in_array($paymentMethod, ['card', 'wallet', 'bank'], true)) {
            $_SESSION['error'] = 'Please select a valid payment method.';
            header('Location: ' . $returnTo);
            exit();
        }

        if ($paymentRef === '' || strlen($paymentRef) < 4) {
            $_SESSION['error'] = 'Enter a valid payment reference.';
            header('Location: ' . $returnTo);
            exit();
        }

        $selected = $packages[$packageId];
        $amount = (int)$selected['points'];
        $price = (int)$selected['price'];
        $description = sprintf(
            'Paid package %s (%d EGP) via %s, ref: %s',
            $selected['name'],
            $price,
            ucfirst($paymentMethod),
            $paymentRef
        );
        $ok = $pointsModel->addLedgerEntry($userId, $amount, 'credit', $description, null);
        if ($ok) {
            (new Notification())->create($userId, 'SilverPoints Added', sprintf('Payment successful. %d points added to your wallet.', $amount));
            $_SESSION['success'] = sprintf('Payment successful. You received %d SilverPoints.', $amount);
        } else {
            $_SESSION['error'] = 'Unable to complete payment right now.';
        }

        header('Location: ' . $returnTo);
        exit();
    }
}

$action = $_GET['action'] ?? '';
$controller = new PointsController();
if ($action === 'balance') {
    $controller->balance();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    $controller->add();
}
