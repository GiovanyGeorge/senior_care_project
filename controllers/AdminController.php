<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Notification.php';
require_once __DIR__ . '/../models/BackgroundCheck.php';
require_once __DIR__ . '/../models/Admin.php';

class AdminController
{
    private function mapAdminFormRoleToDb(string $role): string
    {
        return match ($role) {
            'senior' => 'senior',
            'pal' => 'pal',
            'proxy' => 'proxy',
            'admin' => 'admin',
            default => 'senior',
        };
    }

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
            // When admin activates a pal account, mark pal profile as approved so it appears in booking lists.
            if ((string)($target['role_type'] ?? '') === 'pal') {
                $userModel->ensurePalProfile($targetUserId);
                if ($isActive === 1) {
                    $userModel->setPalVerificationStatusByUserId($targetUserId, 'Approved');
                } else {
                    $userModel->setPalVerificationStatusByUserId($targetUserId, 'Pending');
                }
            }

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

    public function createUser(): void
    {
        requireRole(['Admin']);
        $userModel = new User();

        $firstName = trim((string)($_POST['first_name'] ?? ''));
        $lastName = trim((string)($_POST['last_name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $phone = trim((string)($_POST['phone'] ?? ''));
        $age = (int)($_POST['age'] ?? 0);
        $nationalId = trim((string)($_POST['national_id'] ?? ''));
        $roleType = $this->mapAdminFormRoleToDb(trim((string)($_POST['role_type'] ?? 'senior')));
        $isActive = (int)($_POST['is_active'] ?? 1) === 1 ? 1 : 0;

        if ($firstName === '' || $lastName === '' || $email === '' || $password === '') {
            $_SESSION['error'] = 'First name, last name, email, and password are required.';
            header('Location: /senior_care/views/admin/users.php');
            exit();
        }
        if (strlen($password) < 8) {
            $_SESSION['error'] = 'Password must be at least 8 characters.';
            header('Location: /senior_care/views/admin/users.php');
            exit();
        }
        if ($age <= 0 || $age > 120) {
            $_SESSION['error'] = 'Please enter a valid age.';
            header('Location: /senior_care/views/admin/users.php');
            exit();
        }
        if ($nationalId === '') {
            $_SESSION['error'] = 'National ID is required.';
            header('Location: /senior_care/views/admin/users.php');
            exit();
        }
        if ($userModel->emailExists($email)) {
            $_SESSION['error'] = 'Email is already used by another user.';
            header('Location: /senior_care/views/admin/users.php');
            exit();
        }
        if ($phone !== '' && $userModel->phoneExists($phone)) {
            $_SESSION['error'] = 'Phone is already used by another user.';
            header('Location: /senior_care/views/admin/users.php');
            exit();
        }
        if ($userModel->nationalIdExists($nationalId)) {
            $_SESSION['error'] = 'National ID is already used by another user.';
            header('Location: /senior_care/views/admin/users.php');
            exit();
        }

        try {
            $newUserId = $userModel->create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_BCRYPT),
                'phone' => $phone,
                'role' => $roleType,
                'age' => $age,
                'national_id' => $nationalId,
                'is_active' => $isActive,
                'profile_photo' => null,
            ]);

            if ($roleType === 'senior') {
                $userModel->ensureSeniorProfile($newUserId);
            } elseif ($roleType === 'pal') {
                $userModel->ensurePalProfile($newUserId);
            }

            $_SESSION['success'] = 'User created successfully.';
        } catch (Throwable $e) {
            $_SESSION['error'] = 'Unable to create user.';
        }

        header('Location: /senior_care/views/admin/users.php');
        exit();
    }

    public function updateUser(): void
    {
        requireRole(['Admin']);
        $userModel = new User();

        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId <= 0) {
            $_SESSION['error'] = 'Invalid user selected.';
            header('Location: /senior_care/views/admin/users.php');
            exit();
        }

        $target = $userModel->findById($userId);
        if (!$target) {
            $_SESSION['error'] = 'User not found.';
            header('Location: /senior_care/views/admin/users.php');
            exit();
        }

        $firstName = trim((string)($_POST['first_name'] ?? ''));
        $lastName = trim((string)($_POST['last_name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $age = (int)($_POST['age'] ?? 0);
        $nationalId = trim((string)($_POST['national_id'] ?? ''));
        $roleType = $this->mapAdminFormRoleToDb(trim((string)($_POST['role_type'] ?? 'senior')));
        $isActive = (int)($_POST['is_active'] ?? 1) === 1 ? 1 : 0;
        $newPassword = trim((string)($_POST['new_password'] ?? ''));

        if ($firstName === '' || $lastName === '' || $email === '') {
            $_SESSION['error'] = 'First name, last name, and email are required.';
            header('Location: /senior_care/views/admin/users.php');
            exit();
        }
        if ($age <= 0 || $age > 120) {
            $_SESSION['error'] = 'Please enter a valid age.';
            header('Location: /senior_care/views/admin/users.php');
            exit();
        }
        if ($nationalId === '') {
            $_SESSION['error'] = 'National ID is required.';
            header('Location: /senior_care/views/admin/users.php');
            exit();
        }
        if ($userModel->emailExistsForOther($email, $userId)) {
            $_SESSION['error'] = 'Email is already used by another user.';
            header('Location: /senior_care/views/admin/users.php');
            exit();
        }
        if ($phone !== '' && $userModel->phoneExistsForOther($phone, $userId)) {
            $_SESSION['error'] = 'Phone is already used by another user.';
            header('Location: /senior_care/views/admin/users.php');
            exit();
        }
        if ($userModel->nationalIdExistsForOther($nationalId, $userId)) {
            $_SESSION['error'] = 'National ID is already used by another user.';
            header('Location: /senior_care/views/admin/users.php');
            exit();
        }
        if ($newPassword !== '' && strlen($newPassword) < 8) {
            $_SESSION['error'] = 'New password must be at least 8 characters.';
            header('Location: /senior_care/views/admin/users.php');
            exit();
        }

        try {
            $ok = $userModel->updateUserByAdmin($userId, [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $phone,
                'age' => $age,
                'national_id' => $nationalId,
                'role_type' => $roleType,
                'is_active' => $isActive,
            ]);

            if ($ok && $newPassword !== '') {
                $userModel->changePassword($userId, password_hash($newPassword, PASSWORD_BCRYPT));
            }
            if ($ok && $roleType === 'senior') {
                $userModel->ensureSeniorProfile($userId);
            }
            if ($ok && $roleType === 'pal') {
                $userModel->ensurePalProfile($userId);
            }

            $_SESSION['success'] = $ok ? 'User updated successfully.' : 'Unable to update user.';
        } catch (Throwable $e) {
            $_SESSION['error'] = 'Unable to update user.';
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

    public function verifyBackgroundCheck(): void
    {
        requireRole(['Admin']);
        $checkId = (int)($_POST['check_id'] ?? 0);
        $decision = trim((string)($_POST['decision'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));

        if ($checkId <= 0 || !in_array($decision, ['Approved', 'Rejected'], true)) {
            $_SESSION['error'] = 'Invalid background check action.';
            header('Location: /senior_care/views/admin/background_checks.php');
            exit();
        }

        $bg = new BackgroundCheck();
        $check = $bg->getBackgroundCheckById($checkId);
        if (!$check) {
            $_SESSION['error'] = 'Background check not found.';
            header('Location: /senior_care/views/admin/background_checks.php');
            exit();
        }

        $ok = $bg->updateBackgroundCheckStatus($checkId, $decision, (int)($_SESSION['user_id'] ?? 0), $notes);
        if ($ok && (int)($check['badge_ID'] ?? 0) > 0) {
            $bg->setBadgeStatus((int)$check['badge_ID'], $decision);
        }

        if ($ok) {
            $_SESSION['success'] = 'Background check updated to ' . $decision . '.';
            $palUserId = (int)($check['pal_user_id'] ?? 0);
            if ($palUserId > 0) {
                (new Notification())->create(
                    $palUserId,
                    'Background Check Review',
                    'Your background check "' . (string)($check['check_type'] ?? 'Verification') . '" was marked as ' . $decision . '.'
                );
            }
        } else {
            $_SESSION['error'] = 'Unable to update background check.';
        }

        header('Location: /senior_care/views/admin/background_checks.php');
        exit();
    }

    public function createService(): void
    {
        requireRole(['Admin']);
        $name = trim((string)($_POST['category_name'] ?? ''));
        $cost = (int)($_POST['base_points_cost'] ?? 0);
        $maxDurationHours = (int)($_POST['max_duration_hours'] ?? 0);
        $isActive = (int)($_POST['is_active'] ?? 1) === 1 ? 1 : 0;

        if ($name === '' || $cost <= 0 || $maxDurationHours <= 0 || $maxDurationHours > 24) {
            $_SESSION['error'] = 'Service name, points cost, and max duration (1-24h) are required.';
            header('Location: /senior_care/views/admin/services.php');
            exit();
        }

        $ok = (new Admin())->createService($name, $cost, $maxDurationHours, $isActive);
        $_SESSION['success'] = $ok ? 'Service created successfully.' : 'Unable to create service.';
        header('Location: /senior_care/views/admin/services.php');
        exit();
    }

    public function updateService(): void
    {
        requireRole(['Admin']);
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $name = trim((string)($_POST['category_name'] ?? ''));
        $cost = (int)($_POST['base_points_cost'] ?? 0);
        $maxDurationHours = (int)($_POST['max_duration_hours'] ?? 0);
        $isActive = (int)($_POST['is_active'] ?? 1) === 1 ? 1 : 0;

        if ($categoryId <= 0 || $name === '' || $cost <= 0 || $maxDurationHours <= 0 || $maxDurationHours > 24) {
            $_SESSION['error'] = 'Invalid service update data.';
            header('Location: /senior_care/views/admin/services.php');
            exit();
        }

        $ok = (new Admin())->updateService($categoryId, $name, $cost, $maxDurationHours, $isActive);
        $_SESSION['success'] = $ok ? 'Service updated successfully.' : 'Unable to update service.';
        header('Location: /senior_care/views/admin/services.php');
        exit();
    }

    public function deleteService(): void
    {
        requireRole(['Admin']);
        $categoryId = (int)($_POST['category_id'] ?? 0);
        if ($categoryId <= 0) {
            $_SESSION['error'] = 'Invalid service selected.';
            header('Location: /senior_care/views/admin/services.php');
            exit();
        }

        $adminModel = new Admin();
        if ($adminModel->isServiceUsedInVisits($categoryId)) {
            $_SESSION['error'] = 'Cannot delete this service because it is linked to visit records. You can disable it instead.';
            header('Location: /senior_care/views/admin/services.php');
            exit();
        }

        $ok = $adminModel->deleteService($categoryId);
        $_SESSION['success'] = $ok ? 'Service deleted successfully.' : 'Unable to delete service.';
        header('Location: /senior_care/views/admin/services.php');
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
    } elseif ($action === 'createUser') {
        $controller->createUser();
    } elseif ($action === 'updateUser') {
        $controller->updateUser();
    } elseif ($action === 'setUserStatus') {
        $controller->setUserStatus();
    } elseif ($action === 'createService') {
        $controller->createService();
    } elseif ($action === 'updateService') {
        $controller->updateService();
    } elseif ($action === 'deleteService') {
        $controller->deleteService();
    } elseif ($action === 'deleteUser') {
        $controller->deleteUser();
    } elseif ($action === 'markNotificationRead') {
        $controller->markNotificationRead();
    } elseif ($action === 'verifyBadge') {
        $controller->verifyBadge();
    } elseif ($action === 'verifyBackgroundCheck') {
        $controller->verifyBackgroundCheck();
    }
}
