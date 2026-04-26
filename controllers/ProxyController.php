<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../models/User.php';

class ProxyController
{
    public function switchSenior(): void
    {
        requireRole(['FamilyProxy']);
        $seniorUserId = (int)($_POST['senior_user_id'] ?? 0);
        $list = $_SESSION['proxy_seniors'] ?? [];
        foreach ($list as $item) {
            if ((int)($item['senior_user_id'] ?? 0) === $seniorUserId) {
                $_SESSION['proxy_senior_id'] = (int)$item['senior_ID'];
                $_SESSION['proxy_senior_user_id'] = (int)$item['senior_user_id'];
                $_SESSION['proxy_senior_name'] = trim((string)$item['Fname'] . ' ' . (string)$item['Lname']);
                $_SESSION['success'] = 'Switched active senior.';
                header('Location: /senior_care/views/proxy/dashboard.php');
                exit();
            }
        }
        $_SESSION['error'] = 'Invalid senior selection.';
        header('Location: /senior_care/views/proxy/dashboard.php');
        exit();
    }

    public function updateProfile(): void
    {
        requireRole(['FamilyProxy']);
        $proxyUserId = (int)($_SESSION['user_id'] ?? 0);
        $userModel = new User();
        $ok = $userModel->updateBasicProfile($proxyUserId, [
            'first_name' => trim((string)($_POST['first_name'] ?? '')),
            'last_name' => trim((string)($_POST['last_name'] ?? '')),
            'phone' => trim((string)($_POST['phone'] ?? '')),
        ]);

        if ($ok && isset($_FILES['profile_photo']) && ($_FILES['profile_photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $photo = $this->uploadProfilePhoto($proxyUserId, $_FILES['profile_photo']);
            if ($photo !== null) {
                $userModel->updateProfilePhoto($proxyUserId, $photo);
            }
        }

        $_SESSION['name'] = trim((string)($_POST['first_name'] ?? '') . ' ' . (string)($_POST['last_name'] ?? ''));
        $_SESSION['success'] = $ok ? 'Proxy profile updated.' : 'Unable to update proxy profile.';
        header('Location: /senior_care/views/proxy/profile.php');
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_GET['action'] ?? '';
    $controller = new ProxyController();
    if ($action === 'switchSenior') {
        $controller->switchSenior();
    } elseif ($action === 'updateProfile') {
        $controller->updateProfile();
    }
}

