<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/Visit.php';
require_once __DIR__ . '/../models/Points.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Notification.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/events.php';
require_once __DIR__ . '/../patterns/State/VisitStateRegistry.php';

class VisitController
{
    private Visit $visitModel;
    private Points $pointsModel;
    private Notification $notificationModel;

    public function __construct()
    {
        $this->visitModel = new Visit();
        $this->pointsModel = new Points();
        $this->notificationModel = new Notification();
    }

    public function getPointsCost(): void
    {
        header('Content-Type: application/json');
        $categoryId = (int)($_GET['category_id'] ?? 0);
        echo json_encode(['cost' => $this->visitModel->getPointsCost($categoryId)]);
        exit();
    }

    public function bookVisit(): void
    {
        requireRole(['Senior', 'FamilyProxy']);
        $role = $_SESSION['role'] ?? 'Senior';
        $actorUserId = (int)$_SESSION['user_id']; // payer if proxy
        $seniorUserId = $role === 'FamilyProxy'
            ? (int)($_SESSION['proxy_senior_user_id'] ?? 0)
            : $actorUserId;

        $seniorId = $this->visitModel->getSeniorIdByUserId($seniorUserId);
        $categoryId = (int)($_POST['service_category_id'] ?? $_POST['category_id'] ?? 0);
        $palId = (int)($_POST['pal_user_id'] ?? $_POST['pal_id'] ?? 0);
        $scheduledAt = $_POST['scheduled_at'] ?? $_POST['scheduled_start'] ?? date('Y-m-d H:i:s');
        $taskDescription = trim($_POST['task_description'] ?? $_POST['task_details'] ?? '');
        $durationHours = (int)($_POST['duration_hours'] ?? 1);

        if ($seniorId === null) {
            // Auto-create missing senior profile to avoid blocking bookings.
            $seniorId = (new User())->ensureSeniorProfile($seniorUserId);
        }

        $cost = $this->visitModel->getPointsCost($categoryId);
        $payerUserId = $role === 'FamilyProxy' ? $actorUserId : $seniorUserId;
        $balance = $this->pointsModel->getBalance($payerUserId);

        if ($cost <= 0 || $balance < $cost) {
            $_SESSION['error'] = 'Insufficient SilverPoints for this booking.';
            header('Location: /senior_care/views/senior/book_visit.php');
            exit();
        }

        $maxServiceHours = $this->visitModel->getServiceMaxDurationHours($categoryId);
        if ($durationHours <= 0 || $durationHours > $maxServiceHours) {
            $_SESSION['error'] = 'This service allows maximum ' . $maxServiceHours . ' hours.';
            header('Location: /senior_care/views/senior/book_visit.php');
            exit();
        }

        if (!$this->visitModel->canPalTakeVisitOnDate($palId, (string)$scheduledAt, (float)$durationHours, null)) {
            $maxHours = $this->visitModel->getPalMaxDailyHours($palId);
            $_SESSION['error'] = 'Selected pal reached the daily limit (' . $maxHours . ' hours). Please choose another time or pal.';
            header('Location: /senior_care/views/senior/book_visit.php');
            exit();
        }

        $visitId = $this->visitModel->createVisit([
            'senior_user_id' => $seniorId,
            'pal_user_id' => $palId,
            'service_category_id' => $categoryId,
            'proxy_id' => $role === 'FamilyProxy' ? $actorUserId : null,
            'request_type' => $role === 'FamilyProxy' ? 'Proxy' : 'Direct',
            'scheduled_at' => $scheduledAt,
            'duration_hours' => $durationHours,
            'task_description' => $taskDescription,
            'points_reserved' => $cost,
        ]);

        $this->pointsModel->addLedgerEntry($payerUserId, $cost, 'debit', 'Points reserved in escrow', $visitId);
        $this->createEscrowHold($visitId, $payerUserId, $cost);
        $palUserId = $this->visitModel->getPalUserIdByPalId($palId);
        if ($palUserId !== null) {
            $this->notificationModel->create($palUserId, 'New Visit Request', 'A new visit request is waiting for your response.');
        }

        // Notify all linked proxies when a senior requests a service (except the actor if they are proxy).
        $proxyUserIds = (new User())->getProxyUserIdsForSeniorId((int)$seniorId);
        foreach ($proxyUserIds as $proxyUserId) {
            if ($proxyUserId > 0 && $proxyUserId !== $actorUserId) {
                $this->notificationModel->create(
                    $proxyUserId,
                    'Senior Requested Service',
                    'A linked senior requested a new service. Visit #' . $visitId . ' is pending.'
                );
            }
        }

        $_SESSION['success'] = 'Visit requested. Points reserved in escrow.';
        header('Location: /senior_care/views/senior/dashboard.php');
        exit();
    }

    public function cancel(): void
    {
        requireRole(['Senior', 'FamilyProxy']);
        $userId = (int)$_SESSION['user_id'];
        $visitId = (int)($_POST['visit_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? 'Cancelled by senior.');
        $returnTo = $_POST['return_to'] ?? '/senior_care/views/senior/dashboard.php';

        if ($visitId <= 0 || !$this->visitModel->isVisitOwnedBySeniorUser($visitId, $userId)) {
            $_SESSION['error'] = 'You cannot cancel this service.';
            header('Location: ' . $returnTo);
            exit();
        }

        $visit = $this->visitModel->getVisitForCancel($visitId);
        if (!$visit) {
            $_SESSION['error'] = 'Service not found.';
            header('Location: ' . $returnTo);
            exit();
        }

        if (!VisitStateRegistry::canCancel((string)($visit['status'] ?? ''))) {
            $_SESSION['error'] = 'This visit cannot be cancelled.';
            header('Location: ' . $returnTo);
            exit();
        }

        $ok = $this->visitModel->cancelVisit($visitId, $reason);
        if (!$ok) {
            $_SESSION['error'] = 'Unable to cancel service.';
            header('Location: ' . $returnTo);
            exit();
        }

        $this->applyCancellationRules($visit, 'senior');

        $_SESSION['success'] = 'Service cancelled.';
        header('Location: ' . $returnTo);
        exit();
    }

    private function applyCancellationRules(array $visit, string $cancelledBy): void
    {
        $db = Database::getInstance()->getConnection();
        $pointsModel = $this->pointsModel;

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

        // Refund rules:
        // - If PAL cancels: senior always refunded.
        // - If SENIOR cancels: refunded only if NOT late.
        $shouldRefundSenior = $cancelledBy === 'pal' ? true : !$late;

        if ($reserved > 0 && $shouldRefundSenior) {
            $desc = 'Refund: visit cancelled';
            if (!$pointsModel->ledgerEntryExists($visitId, $seniorUserId, $desc)) {
                $pointsModel->addLedgerEntry($seniorUserId, $reserved, 'credit', $desc, $visitId);
            }
        }

        // Late cancellation penalty:
        // - If senior cancels late: no refund (penalty is losing reserved points already debited earlier).
        // - If pal cancels late: pal pays penalty equal to reserved points to platform/admin.
        if ($late && $cancelledBy === 'pal' && $reserved > 0 && $palUserId > 0) {
            $penaltyDesc = 'Late cancellation fee (12h+)';
            if (!$pointsModel->ledgerEntryExists($visitId, $palUserId, $penaltyDesc)) {
                $pointsModel->addLedgerEntry($palUserId, $reserved, 'debit', $penaltyDesc, $visitId);
            }
            if ($adminId > 0 && !$pointsModel->ledgerEntryExists($visitId, $adminId, $penaltyDesc)) {
                $pointsModel->addLedgerEntry($adminId, $reserved, 'credit', $penaltyDesc, $visitId);
            }
        }

        EventDispatcher::getInstance()->dispatch(new DomainEvent(AppEvents::VISIT_CANCELLED, [
            'visit_id' => $visitId,
            'pal_user_id' => $palUserId,
            'senior_user_id' => $seniorUserId,
            'cancelled_by' => $cancelledBy,
        ]));
    }

    private function createEscrowHold(int $visitId, int $userId, int $points): void
    {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare(
                "INSERT INTO escrow_holds (visit_ID, user_ID, points_amount, hold_status, created_at)
                 VALUES (:visit_id, :user_id, :points_amount, 'Held', NOW())"
            );
            $stmt->execute([
                'visit_id' => $visitId,
                'user_id' => $userId,
                'points_amount' => $points,
            ]);
        } catch (Throwable $e) {
            // Keep booking flow running even if escrow table is not migrated yet.
        }
    }
}

$action = $_GET['action'] ?? '';
$controller = new VisitController();
if ($action === 'getPointsCost') {
    $controller->getPointsCost();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'book') {
    $controller->bookVisit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'cancel') {
    $controller->cancel();
}
