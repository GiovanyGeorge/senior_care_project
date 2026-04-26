<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Points.php';
require_once __DIR__ . '/../config/auth.php';

class AuthController
{
    private User $userModel;
    private Points $pointsModel;
    private const SENIOR_WELCOME_BONUS = 100;
    private const SENIOR_WELCOME_DESC = 'Welcome bonus: Senior signup gift';

    public function __construct()
    {
        $this->userModel = new User();
        $this->pointsModel = new Points();
    }

    private function mapDbRoleToSession(string $role): string
    {
        return match (strtolower($role)) {
            'senior' => 'Senior',
            'pal' => 'Pal',
            'admin' => 'Admin',
            'proxy' => 'FamilyProxy',
            default => 'Senior',
        };
    }

    private function mapFormRoleToDb(string $role): string
    {
        return match ($role) {
            'Senior' => 'senior',
            'Pal' => 'pal',
            'FamilyProxy' => 'proxy',
            default => 'senior',
        };
    }

    public function login(): void
    {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $user = $this->userModel->findByEmail($email);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $_SESSION['error'] = 'Invalid email or password.';
            header('Location: /senior_care/views/auth/login.php');
            exit();
        }

        if ((int)($user['is_active'] ?? 0) !== 1) {
            $_SESSION['error'] = 'Your account is pending admin approval.';
            header('Location: /senior_care/views/auth/login.php');
            exit();
        }

        $_SESSION['user_id'] = (int)$user['User_ID'];
        $_SESSION['name'] = trim(($user['Fname'] ?? '') . ' ' . ($user['Lname'] ?? ''));
        $_SESSION['role'] = $this->mapDbRoleToSession((string)$user['role_type']);

        if ($_SESSION['role'] === 'Senior') {
            $this->userModel->ensureSeniorProfile((int)$user['User_ID']);
            $this->grantSeniorWelcomeBonus((int)$user['User_ID']);
        }

        if ($_SESSION['role'] === 'FamilyProxy') {
            $linked = $this->userModel->getProxyLinkedSenior((int)$user['User_ID']);
            if (!$linked) {
                session_destroy();
                session_start();
                $_SESSION['error'] = 'Proxy account is not linked to a senior yet. Please contact admin.';
                header('Location: /senior_care/views/auth/login.php');
                exit();
            }
            $_SESSION['proxy_senior_id'] = (int)$linked['senior_ID'];
            $_SESSION['proxy_senior_user_id'] = (int)$linked['senior_user_id'];
            $_SESSION['proxy_senior_name'] = trim((string)$linked['Fname'] . ' ' . (string)$linked['Lname']);
            if ((int)$linked['senior_user_id'] > 0) {
                $this->userModel->ensureSeniorProfile((int)$linked['senior_user_id']);
            }
        }

        if ($_SESSION['role'] === 'Senior' || $_SESSION['role'] === 'FamilyProxy') {
            header('Location: /senior_care/views/senior/dashboard.php');
        } elseif ($_SESSION['role'] === 'Pal') {
            header('Location: /senior_care/views/pal/dashboard.php');
        } else {
            header('Location: /senior_care/views/admin/dashboard.php');
        }
        exit();
    }

    public function register(): void
    {
        $role = $this->mapFormRoleToDb($_POST['role'] ?? 'Senior');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if ($this->userModel->emailExists($email)) {
            $_SESSION['error'] = 'This email is already registered. Please use another email.';
            header('Location: /senior_care/views/auth/register.php');
            exit();
        }

        if ($phone !== '' && $this->userModel->phoneExists($phone)) {
            $_SESSION['error'] = 'This phone number is already registered. Please use another phone number.';
            header('Location: /senior_care/views/auth/register.php');
            exit();
        }

        $photoPath = null;
        if (isset($_FILES['profile_photo']) && ($_FILES['profile_photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $photoPath = $this->handleUpload($_FILES['profile_photo']);
        }

        try {
            $userId = $this->userModel->create([
                'first_name' => trim($_POST['first_name'] ?? ''),
                'last_name' => trim($_POST['last_name'] ?? ''),
                'email' => $email,
                'password' => password_hash($_POST['password'] ?? '', PASSWORD_BCRYPT),
                'phone' => $phone,
                'role' => $role,
                'is_active' => 0,
                'profile_photo' => $photoPath,
            ]);
        } catch (PDOException $e) {
            $driverMessage = (string)($e->errorInfo[2] ?? '');
            if ((int)$e->getCode() === 23000) {
                if (stripos($driverMessage, "for key 'email'") !== false) {
                    $_SESSION['error'] = 'This email is already registered. Please use another email.';
                } elseif (stripos($driverMessage, "for key 'phone'") !== false) {
                    $_SESSION['error'] = 'This phone number is already registered. Please use another phone number.';
                } else {
                    $_SESSION['error'] = 'This account data already exists. Please change email/phone.';
                }
            } else {
                $_SESSION['error'] = 'Registration failed. Please try again.';
            }
            header('Location: /senior_care/views/auth/register.php');
            exit();
        }

        // Rename uploaded photo to required format once user id exists.
        if ($photoPath !== null) {
            $ext = pathinfo($photoPath, PATHINFO_EXTENSION);
            $newName = $userId . '_' . time() . '.' . $ext;
            $newPath = '/senior_care/uploads/profiles/' . $newName;
            @rename(__DIR__ . '/../uploads/profiles/' . basename($photoPath), __DIR__ . '/../uploads/profiles/' . $newName);
            $this->userModel->updateProfilePhoto($userId, $newPath);
        }

        // If proxy, link to selected senior.
        if ($role === 'proxy') {
            $linkedSeniorUserId = (int)($_POST['linked_senior_user_id'] ?? 0);
            $relationshipType = trim((string)($_POST['relationship_type'] ?? 'Family'));
            if ($linkedSeniorUserId <= 0) {
                $_SESSION['error'] = 'Please select the senior you are helping.';
                header('Location: /senior_care/views/auth/register.php');
                exit();
            }

            $seniorId = $this->userModel->ensureSeniorProfile($linkedSeniorUserId);
            $this->userModel->linkProxyToSenior($userId, $seniorId, $relationshipType);
        }

        if ($role === 'senior') {
            $this->grantSeniorWelcomeBonus($userId);
        }

        $this->notifyAdminsForApproval($userId, trim($_POST['first_name'] ?? '') . ' ' . trim($_POST['last_name'] ?? ''), $role);

        $_SESSION['success'] = 'Registration complete. Your account will be activated after admin approval.';
        header('Location: /senior_care/views/auth/login.php');
        exit();
    }

    private function notifyAdminsForApproval(int $newUserId, string $fullName, string $role): void
    {
        $db = Database::getInstance()->getConnection();
        $adminsStmt = $db->prepare('SELECT User_ID FROM users WHERE role_type = :role AND is_active = 1');
        $adminsStmt->execute(['role' => 'admin']);
        $adminIds = $adminsStmt->fetchAll(PDO::FETCH_COLUMN);

        if (!$adminIds) {
            return;
        }

        $title = 'New account approval request';
        $body = sprintf('New %s account pending approval: %s (User ID: %d).', $role, trim($fullName), $newUserId);
        $notifyStmt = $db->prepare(
            'INSERT INTO notifications (usersUser_ID, type, title, message_body, entity_ID, entity_type, created_at)
             VALUES (:admin_id, :type, :title, :message, :entity_id, :entity_type, NOW())'
        );

        foreach ($adminIds as $adminId) {
            $notifyStmt->execute([
                'admin_id' => (int)$adminId,
                'type' => 'Approval',
                'title' => $title,
                'message' => $body,
                'entity_id' => $newUserId,
                'entity_type' => 'users',
            ]);
        }
    }

    private function handleUpload(array $file): ?string
    {
        if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            return null;
        }

        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            return null;
        }

        $tempName = uniqid('tmp_', true) . '.' . $ext;
        $targetDir = __DIR__ . '/../uploads/profiles/';
        $targetPath = $targetDir . $tempName;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return null;
        }

        return '/senior_care/uploads/profiles/' . $tempName;
    }

    private function grantSeniorWelcomeBonus(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }
        if ($this->pointsModel->ledgerEntryExists(null, $userId, self::SENIOR_WELCOME_DESC)) {
            return;
        }
        $this->pointsModel->addLedgerEntry(
            $userId,
            self::SENIOR_WELCOME_BONUS,
            'credit',
            self::SENIOR_WELCOME_DESC,
            null
        );
    }
}

$action = $_GET['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller = new AuthController();
    if ($action === 'login') {
        $controller->login();
    } elseif ($action === 'register') {
        $controller->register();
    }
}
