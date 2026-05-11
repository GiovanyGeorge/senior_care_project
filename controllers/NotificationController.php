<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../models/Notification.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /senior_care/views/auth/login.php');
    exit();
}

$userId = (int)$_SESSION['user_id'];
$action = $_GET['action'] ?? '';
$returnTo = $_POST['return_to'] ?? '/senior_care/views/shared/messages.php';
$notificationModel = new Notification();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'markRead') {
    $notificationId = (int)($_POST['notification_id'] ?? 0);
    if ($notificationId > 0) {
        $notificationModel->markAsRead($notificationId, $userId);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'markAllRead') {
    $notificationModel->markAllAsRead($userId);
}

header('Location: ' . $returnTo);
exit();
