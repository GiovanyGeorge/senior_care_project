<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../models/User.php';

class SeniorController
{
    public function index(): void
    {
        requireRole(['Senior', 'FamilyProxy']);
        header('Location: /senior_care/views/senior/dashboard.php');
        exit();
    }

    public function updateProfile(): void
    {
        requireRole(['Senior', 'FamilyProxy']);
        $actorUserId = (int)($_SESSION['user_id'] ?? 0);
        $role = $_SESSION['role'] ?? 'Senior';
        $targetSeniorUserId = $role === 'FamilyProxy'
            ? (int)($_SESSION['proxy_senior_user_id'] ?? 0)
            : $actorUserId;

        if ($targetSeniorUserId <= 0) {
            $_SESSION['error'] = 'Unable to update profile.';
            header('Location: /senior_care/views/senior/profile.php');
            exit();
        }

        $userModel = new User();
        $ok = $userModel->updateSeniorProfileByUserId($targetSeniorUserId, [
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'comfort_profile' => trim($_POST['comfort_profile'] ?? ''),
            'emergency_contact_name' => trim($_POST['emergency_contact_name'] ?? ''),
            'emergency_contact_phone' => trim($_POST['emergency_contact_phone'] ?? ''),
        ]);

        $seniorId = $userModel->ensureSeniorProfile($targetSeniorUserId);
        $healthOk = $userModel->upsertHealthRecord(
            $seniorId,
            trim((string)($_POST['medical_notes'] ?? '')),
            trim((string)($_POST['allergies'] ?? ''))
        );
        $ok = $ok && $healthOk;

        if ($ok && isset($_FILES['profile_photo']) && ($_FILES['profile_photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $photo = $this->uploadProfilePhoto($targetSeniorUserId, $_FILES['profile_photo']);
            if ($photo !== null) {
                $userModel->updateProfilePhoto($targetSeniorUserId, $photo);
            }
        }

        if ($role === 'Senior') {
            $_SESSION['name'] = trim((string)($_POST['first_name'] ?? '') . ' ' . (string)($_POST['last_name'] ?? ''));
        } else {
            $_SESSION['proxy_senior_name'] = trim((string)($_POST['first_name'] ?? '') . ' ' . (string)($_POST['last_name'] ?? ''));
        }

        $_SESSION['success'] = $ok ? 'Profile updated successfully.' : 'Unable to update profile.';
        header('Location: /senior_care/views/senior/profile.php');
        exit();
    }

    private function uploadProfilePhoto(int $userId, array $file): ?string
    {
        if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            return null;
        }
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            return null;
        }
        $dir = __DIR__ . '/../uploads/profiles/';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $name = $userId . '_' . time() . '.' . $ext;
        $target = $dir . $name;
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            return null;
        }
        return '/senior_care/uploads/profiles/' . $name;
    }
}

if (($_GET['action'] ?? '') === 'updateProfile' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    (new SeniorController())->updateProfile();
}
