<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/Emergency.php';
require_once __DIR__ . '/../models/Notification.php';
require_once __DIR__ . '/../config/auth.php';

class EmergencyController
{
    public function panic(): void
    {
        requireRole(['Senior', 'FamilyProxy']);
        $message = trim($_POST['message'] ?? 'Emergency alert raised from panic button.');
        $actorUserId = (int)$_SESSION['user_id'];
        $targetSeniorUserId = ($_SESSION['role'] ?? '') === 'FamilyProxy'
            ? (int)($_SESSION['proxy_senior_user_id'] ?? 0)
            : $actorUserId;

        $emergencyModel = new Emergency();
        $seniorId = $emergencyModel->getSeniorIdByUserId($targetSeniorUserId);
        if ($seniorId === null) {
            $_SESSION['error'] = 'Unable to send alert because senior profile is missing.';
            header('Location: /senior_care/views/senior/dashboard.php');
            exit();
        }

        $threadId = $emergencyModel->createThread($seniorId, $actorUserId, $message);
        $notification = new Notification();

        // Notify the actor (confirmation).
        $notification->create($actorUserId, 'Emergency Alert', 'Your alert has been sent. Help is on the way.');

        // Notify linked proxies for this senior (if any).
        foreach ($emergencyModel->getProxyUserIdsForSenior($seniorId) as $proxyUserId) {
            if ($proxyUserId > 0 && $proxyUserId !== $actorUserId) {
                $notification->create($proxyUserId, 'Emergency Alert', 'An emergency alert was triggered for your linked senior. Please respond ASAP.');
            }
        }

        // Notify nearby pals only (approximated by approved + active pals).
        foreach ($emergencyModel->getNearbyPalUserIds(6) as $palUserId) {
            if ($palUserId > 0) {
                $notification->create($palUserId, 'Emergency Nearby', 'Emergency alert in your area. If available, please check your schedule and respond.');
            }
        }

        $_SESSION['success'] = 'Alert sent to your emergency contacts. Thread #' . $threadId;
        header('Location: /senior_care/views/senior/dashboard.php');
        exit();
    }
}

if (($_GET['action'] ?? '') === 'panic' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    (new EmergencyController())->panic();
}
