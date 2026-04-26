<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Notification.php';
require_once __DIR__ . '/../models/BackgroundCheck.php';

class AdminController
{
    public function index(): void
    {
        requireRole(['Admin']);
        header('Location: /senior_care/views/admin/dashboard.php');
        exit();
    }

    public function updateProfile(): void
    {
        requireRole(['Admin']);

        $userModel = new User();
        $userId = (int)$_SESSION['user_id'];

        $ok = $userModel->updateAdminProfile($userId, [
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
        ]);

        if ($ok && isset($_FILES['profile_photo']) && ($_FILES['profile_photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $newPath = $this->handleProfileUpload($userId, $_FILES['profile_photo']);
            if ($newPath !== null) {
                $userModel->updateProfilePhoto($userId, $newPath);
            }
        }

        $_SESSION['name'] = trim((string)($_POST['first_name'] ?? '') . ' ' . (string)($_POST['last_name'] ?? ''));
        $_SESSION['success'] = $ok ? 'Admin profile updated successfully.' : 'Unable to update profile.';
        header('Location: /senior_care/views/admin/profile.php');
        exit();
    }

    public function changePassword(): void
    {
        requireRole(['Admin']);
        $userId = (int)$_SESSION['user_id'];
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $userModel = new User();
        $admin = $userModel->findById($userId);
        if (!$admin || !password_verify($currentPassword, (string)$admin['password_hash'])) {
            $_SESSION['error'] = 'Current password is incorrect.';
            header('Location: /senior_care/views/admin/profile.php');
            exit();
        }

        if (strlen($newPassword) < 8) {
            $_SESSION['error'] = 'New password must be at least 8 characters.';
            header('Location: /senior_care/views/admin/profile.php');
            exit();
        }

        if ($newPassword !== $confirmPassword) {
            $_SESSION['error'] = 'New password and confirmation do not match.';
            header('Location: /senior_care/views/admin/profile.php');
            exit();
        }

        $ok = $userModel->changePassword($userId, password_hash($newPassword, PASSWORD_BCRYPT));
        $_SESSION['success'] = $ok ? 'Password changed successfully.' : 'Unable to change password.';
        header('Location: /senior_care/views/admin/profile.php');
        exit();
    }

    public function setUserStatus(): void
    {
        requireRole(['Admin']);
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        $status = $_POST['status'] ?? '';

        if ($targetUserId <= 0) {
            $_SESSION['error'] = 'Invalid user selected.';
            header('Location: /senior_care/views/admin/users.php');
            exit();
        }

        $isActive = match ($status) {
            'approve', 'activate' => 1,
            'reject', 'deactivate' => 0,
            default => -1,
        };

        if ($isActive === -1) {
            $_SESSION['error'] = 'Invalid status action.';
            header('Location: /senior_care/views/admin/users.php');
            exit();
        }

        $userModel = new User();
        $target = $userModel->findById($targetUserId);
        if (!$target) {
            $_SESSION['error'] = 'User not found.';
            header('Location: /senior_care/views/admin/users.php');
            exit();
        }

        $ok = $userModel->setUserActiveStatus($targetUserId, $isActive);
        if ($ok) {
            $actionText = $isActive === 1 ? 'approved/activated' : 'rejected/deactivated';
            $_SESSION['success'] = 'User account ' . $actionText . ' successfully.';
            (new Notification())->create(
                $targetUserId,
                'Account status updated',
                'Your account status was updated by admin.'
            );
        } else {
            $_SESSION['error'] = 'Failed to update account status.';
        }

        header('Location: /senior_care/views/admin/users.php');
        exit();
    }

    public function markNotificationRead(): void
    {
        requireRole(['Admin']);
        $notificationId = (int)($_POST['notification_id'] ?? 0);
        if ($notificationId > 0) {
            (new Notification())->markAsRead($notificationId, (int)$_SESSION['user_id']);
        }
        header('Location: /senior_care/views/admin/notifications.php');
        exit();
    }

    public function deleteUser(): void
    {
        requireRole(['Admin']);
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        $currentAdminId = (int)($_SESSION['user_id'] ?? 0);

        if ($targetUserId <= 0) {
            $_SESSION['error'] = 'Invalid user selected.';
            header('Location: /senior_care/views/admin/users.php');
            exit();
        }

        if ($targetUserId === $currentAdminId) {
            $_SESSION['error'] = 'You cannot delete your own admin account.';
            header('Location: /senior_care/views/admin/users.php');
            exit();
        }

        $userModel = new User();
        $target = $userModel->findById($targetUserId);
        if (!$target) {
            $_SESSION['error'] = 'User not found.';
            header('Location: /senior_care/views/admin/users.php');
            exit();
        }

        try {
            $ok = $userModel->deleteUserAndAllRelatedData($targetUserId);
            $_SESSION['success'] = $ok ? 'User and all related data deleted permanently.' : 'Unable to delete user.';
        } catch (Throwable $e) {
            $_SESSION['error'] = 'Failed to fully delete this account and related records.';
        }

        header('Location: /senior_care/views/admin/users.php');
        exit();
    }

    public function verifyBadge(): void
    {
        requireRole(['Admin']);
        $badgeId = (int)($_POST['badge_id'] ?? 0);
        $decision = trim((string)($_POST['decision'] ?? ''));
        if ($badgeId <= 0 || !in_array($decision, ['Approved', 'Rejected'], true)) {
            $_SESSION['error'] = 'Invalid badge verification action.';
            header('Location: /senior_care/views/admin/users.php');
            exit();
        }

        $bg = new BackgroundCheck();
        $badge = $bg->getBadgeById($badgeId);
        if (!$badge) {
            $_SESSION['error'] = 'Badge not found.';
            header('Location: /senior_care/views/admin/users.php');
            exit();
        }

        $ok = $bg->setBadgeStatus($badgeId, $decision);
        if ($ok) {
            $_SESSION['success'] = 'Badge status updated to ' . $decision . '.';
            $palUserId = (int)($badge['pal_user_id'] ?? 0);
            if ($palUserId > 0) {
                (new Notification())->create(
                    $palUserId,
                    'Skill Badge Verification',
                    'Your badge "' . (string)$badge['badge_name'] . '" was marked as ' . $decision . ' by admin.'
                );
            }
        } else {
            $_SESSION['error'] = 'Unable to update badge status.';
        }

        header('Location: /senior_care/views/admin/users.php');
        exit();
    }

    private function handleProfileUpload(int $userId, array $file): ?string
    {
        if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            return null;
        }

        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            return null;
        }

        $targetDir = __DIR__ . '/../uploads/profiles/';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $fileName = $userId . '_' . time() . '.' . $ext;
        $targetPath = $targetDir . $fileName;
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return null;
        }

        return '/senior_care/uploads/profiles/' . $fileName;
    }
}

$action = $_GET['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller = new AdminController();
    if ($action === 'updateProfile') {
        $controller->updateProfile();
    } elseif ($action === 'changePassword') {
        $controller->changePassword();
    } elseif ($action === 'setUserStatus') {
        $controller->setUserStatus();
    } elseif ($action === 'deleteUser') {
        $controller->deleteUser();
    } elseif ($action === 'markNotificationRead') {
        $controller->markNotificationRead();
    } elseif ($action === 'verifyBadge') {
        $controller->verifyBadge();
    }
}
