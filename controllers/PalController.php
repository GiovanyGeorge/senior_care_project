<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/Visit.php';
require_once __DIR__ . '/../models/Notification.php';
require_once __DIR__ . '/../models/Points.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/BackgroundCheck.php';
require_once __DIR__ . '/../models/Pal.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

class PalController
{
    private const CASHOUT_RATE = 1.0; // 1 point = 1 EGP

    public function updateRequestStatus(): void
    {
        requireRole(['Pal']);
        $palUserId = (int)($_SESSION['user_id'] ?? 0);
        $visitId = (int)($_POST['visit_id'] ?? 0);
        $status = $_POST['status'] ?? 'Rejected';
        $returnTo = $_POST['return_to'] ?? '/senior_care/views/pal/requests.php';
        $reason = trim($_POST['reason'] ?? '');

        $visitModel = new Visit();
        if (!$visitModel->isVisitAssignedToPalUser($visitId, $palUserId)) {
            $_SESSION['error'] = 'You cannot update this service.';
            header('Location: ' . $returnTo);
            exit();
        }

        if ($status === 'Cancelled') {
            $ok = $visitModel->cancelVisit($visitId, $reason !== '' ? $reason : 'Cancelled by pal.');
        } else {
            $ok = $visitModel->setStatus($visitId, $status);
        }
        if ($ok && $status === 'Completed') {
            $this->settleVisitPayout($visitId);
        }
        if ($ok && $status === 'Cancelled') {
            $this->settleCancellation($visitId);
        }

        $_SESSION['success'] = $ok ? 'Service status updated.' : 'Unable to update status.';
        header('Location: ' . $returnTo);
        exit();
    }

    public function updateProfile(): void
    {
        requireRole(['Pal']);
        $palUserId = (int)($_SESSION['user_id'] ?? 0);
        $userModel = new User();
        $ok = $userModel->updatePalProfileByUserId($palUserId, [
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'skills' => trim($_POST['skills'] ?? ''),
            'travel_radius_km' => (int)($_POST['travel_radius_km'] ?? 5),
            'transport_mode' => trim($_POST['transport_mode'] ?? 'Walking'),
        ]);

        if ($ok && isset($_FILES['profile_photo']) && ($_FILES['profile_photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $photo = $this->uploadFile($_FILES['profile_photo'], __DIR__ . '/../uploads/profiles/', $palUserId);
            if ($photo !== null) {
                $userModel->updateProfilePhoto($palUserId, '/senior_care/uploads/profiles/' . basename($photo));
            }
        }

        $_SESSION['name'] = trim((string)($_POST['first_name'] ?? '') . ' ' . (string)($_POST['last_name'] ?? ''));
        $_SESSION['success'] = $ok ? 'Pal profile updated.' : 'Unable to update pal profile.';
        header('Location: /senior_care/views/pal/profile.php');
        exit();
    }

    public function uploadBadge(): void
    {
        requireRole(['Pal']);
        $palUserId = (int)($_SESSION['user_id'] ?? 0);
        $badgeName = trim($_POST['badge_name'] ?? '');
        $issuedAt = trim((string)($_POST['issued_at'] ?? ''));
        $expiresAt = trim((string)($_POST['expires_at'] ?? ''));
        if ($badgeName === '' || !isset($_FILES['certificate'])) {
            $_SESSION['error'] = 'Badge name and certificate are required.';
            header('Location: /senior_care/views/pal/profile.php');
            exit();
        }

        $filePath = $this->uploadFile($_FILES['certificate'], __DIR__ . '/../uploads/badges/', $palUserId);
        if ($filePath === null) {
            $_SESSION['error'] = 'Invalid badge file. Allowed: jpg, jpeg, png, pdf (max 5MB).';
            header('Location: /senior_care/views/pal/profile.php');
            exit();
        }

        $bg = new BackgroundCheck();
        $palId = $bg->getPalIdByUserId($palUserId);
        if ($palId === null) {
            $_SESSION['error'] = 'Pal profile missing. Contact admin.';
            header('Location: /senior_care/views/pal/profile.php');
            exit();
        }

        $issuedValue = $issuedAt !== '' ? $issuedAt . ' 00:00:00' : null;
        $expiresValue = $expiresAt !== '' ? $expiresAt . ' 00:00:00' : null;
        $ok = $bg->submitSkillBadge(
            $palId,
            $badgeName,
            $issuedValue,
            $expiresValue,
            '/senior_care/uploads/badges/' . basename($filePath)
        );
        $_SESSION['success'] = $ok ? 'Skill badge uploaded and pending background check approval.' : 'Unable to upload skill badge.';
        header('Location: /senior_care/views/pal/profile.php');
        exit();
    }

    public function requestCashout(): void
    {
        requireRole(['Pal']);
        $palUserId = (int)($_SESSION['user_id'] ?? 0);
        $pointsRequested = (int)($_POST['points_requested'] ?? 0);
        $destinationType = trim((string)($_POST['destination_type'] ?? 'Wallet'));
        $providerName = trim((string)($_POST['provider_name'] ?? ''));
        $accountIdentifier = trim((string)($_POST['account_identifier'] ?? ''));
        $returnTo = '/senior_care/views/pal/earnings.php';

        if ($pointsRequested <= 0) {
            $_SESSION['error'] = 'Enter a valid points amount for cashout.';
            header('Location: ' . $returnTo);
            exit();
        }
        if ($providerName === '' || $accountIdentifier === '') {
            $_SESSION['error'] = 'Provider and account details are required.';
            header('Location: ' . $returnTo);
            exit();
        }

        $pointsModel = new Points();
        $currentBalance = $pointsModel->getBalance($palUserId);
        if ($currentBalance < $pointsRequested) {
            $_SESSION['error'] = 'Insufficient SilverPoints balance for cashout.';
            header('Location: ' . $returnTo);
            exit();
        }

        $palModel = new Pal();
        $palId = $palModel->getPalIdByUserId($palUserId);
        if ($palId === null) {
            $_SESSION['error'] = 'Pal profile not found.';
            header('Location: ' . $returnTo);
            exit();
        }

        $cashEquivalent = round($pointsRequested * self::CASHOUT_RATE, 2);
        $db = Database::getInstance()->getConnection();
        $db->beginTransaction();
        try {
            $destinationId = $palModel->upsertCashoutDestination($palId, $destinationType, $providerName, $accountIdentifier);
            $requestId = $palModel->createCashoutRequest($destinationId, $palId, (float)$pointsRequested, $cashEquivalent);
            $pointsModel->addLedgerEntry($palUserId, $pointsRequested, 'debit', 'Cashout request #' . $requestId, null);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $_SESSION['error'] = 'Unable to submit cashout request right now.';
            header('Location: ' . $returnTo);
            exit();
        }

        $_SESSION['success'] = 'Cashout request submitted successfully.';
        header('Location: ' . $returnTo);
        exit();
    }

    private function uploadFile(array $file, string $directory, int $userId): ?string
    {
        if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            return null;
        }
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            return null;
        }
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        $name = $userId . '_' . time() . '.' . $ext;
        $target = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . $name;
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            return null;
        }
        return $target;
    }

    private function settleCancellation(int $visitId): void
    {
        $visitModel = new Visit();
        $visit = $visitModel->getVisitForCancel($visitId);
        if (!$visit) {
            return;
        }

        // Reuse the same rules as senior cancellation, but cancelledBy = 'pal'
        $controller = new VisitControllerShim();
        $controller->apply($visit);
    }

    private function settleVisitPayout(int $visitId): void
    {
        $visitModel = new Visit();
        $pointsModel = new Points();
        $notificationModel = new Notification();
        $db = Database::getInstance()->getConnection();

        $visit = $visitModel->getVisitSettlementData($visitId);
        if (!$visit) {
            return;
        }

        if ($visitModel->isVisitSettled($visitId)) {
            return;
        }

        $reserved = (int)round((float)($visit['points_reserved'] ?? 0));
        if ($reserved <= 0) {
            return;
        }

        $siteFee = (int)round($reserved * 0.05);
        $palNet = max($reserved - $siteFee, 0);
        $adminId = (int)($db->query("SELECT User_ID FROM users WHERE role_type = 'admin' ORDER BY User_ID ASC LIMIT 1")->fetchColumn() ?: 0);
        $palUserId = (int)$visit['pal_user_id'];
        $seniorUserId = (int)$visit['senior_user_id'];

        $db->beginTransaction();
        try {
            if ($palNet > 0) {
                $pointsModel->addLedgerEntry($palUserId, $palNet, 'credit', 'Visit earning credit to pal', $visitId);
            }

            if ($siteFee > 0 && $adminId > 0) {
                $pointsModel->addLedgerEntry($adminId, $siteFee, 'credit', 'Platform insurance/site fee from visit', $visitId);
            }

            $visitModel->markVisitPaid($visitId, (float)$reserved);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return;
        }

        $notificationModel->create($palUserId, 'Payout Completed', 'Your service payout was added to your SilverPoints balance.');
        $notificationModel->create($seniorUserId, 'Visit Completed', 'Your visit has been completed successfully.');
    }
}

/**
 * Small shim to reuse cancellation rules without circular controller includes.
 */
class VisitControllerShim
{
    public function apply(array $visit): void
    {
        $db = Database::getInstance()->getConnection();
        $pointsModel = new Points();
        $notificationModel = new Notification();

        $visitId = (int)$visit['visit_ID'];
        $reserved = (int)round((float)($visit['points_reserved'] ?? 0));
        $seniorUserId = (int)$visit['senior_user_id'];
        $palUserId = (int)($visit['pal_user_id'] ?? 0);

        $scheduledStart = $visit['scheduled_start'] ?? null;
        $createdAt = $visit['created_at'] ?? null;
        $baseTime = $scheduledStart ?: $createdAt;
        $late = false;
        if ($baseTime) {
            try {
                $t = new DateTime((string)$baseTime);
                $late = (new DateTime('now')) > (clone $t)->modify('+12 hours');
            } catch (Throwable $e) {
                $late = false;
            }
        }

        $adminId = (int)($db->query("SELECT User_ID FROM users WHERE role_type = 'admin' ORDER BY User_ID ASC LIMIT 1")->fetchColumn() ?: 0);

        // Pal cancels => senior always refunded.
        if ($reserved > 0) {
            $refundDesc = 'Refund: visit cancelled';
            if (!$pointsModel->ledgerEntryExists($visitId, $seniorUserId, $refundDesc)) {
                $pointsModel->addLedgerEntry($seniorUserId, $reserved, 'credit', $refundDesc, $visitId);
            }
        }

        // If late => pal pays fee to platform.
        if ($late && $reserved > 0 && $palUserId > 0) {
            $feeDesc = 'Late cancellation fee (12h+)';
            if (!$pointsModel->ledgerEntryExists($visitId, $palUserId, $feeDesc)) {
                $pointsModel->addLedgerEntry($palUserId, $reserved, 'debit', $feeDesc, $visitId);
            }
            if ($adminId > 0 && !$pointsModel->ledgerEntryExists($visitId, $adminId, $feeDesc)) {
                $pointsModel->addLedgerEntry($adminId, $reserved, 'credit', $feeDesc, $visitId);
            }
        }

        if ($palUserId > 0) {
            $notificationModel->create($palUserId, 'Service Cancelled', 'You cancelled a service.');
        }
        $notificationModel->create($seniorUserId, 'Service Cancelled', 'A pal cancelled your service.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_GET['action'] ?? '';
    $controller = new PalController();
    if ($action === 'updateRequestStatus') {
        $controller->updateRequestStatus();
    } elseif ($action === 'updateProfile') {
        $controller->updateProfile();
    } elseif ($action === 'uploadBadge') {
        $controller->uploadBadge();
    } elseif ($action === 'requestCashout') {
        $controller->requestCashout();
    }
}
