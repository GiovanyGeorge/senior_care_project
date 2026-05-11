<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Points.php';
require_once __DIR__ . '/../models/BackgroundCheck.php';
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
        $password = (string)($_POST['password'] ?? '');
        $passwordTrimmed = trim($password);

        $user = $this->userModel->findByEmail($email);
        if (!$user || !$this->verifyPasswordWithLegacySupport($password, $passwordTrimmed, (string)($user['password_hash'] ?? ''), (int)$user['User_ID'])) {
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
            $linkedList = $this->userModel->getProxyLinkedSeniors((int)$user['User_ID']);
            if (!$linkedList) {
                session_destroy();
                session_start();
                $_SESSION['error'] = 'Proxy account is not linked to a senior yet. Please contact admin.';
                header('Location: /senior_care/views/auth/login.php');
                exit();
            }
            $_SESSION['proxy_seniors'] = $linkedList;
            $active = $linkedList[0];
            $_SESSION['proxy_senior_id'] = (int)$active['senior_ID'];
            $_SESSION['proxy_senior_user_id'] = (int)$active['senior_user_id'];
            $_SESSION['proxy_senior_name'] = trim((string)$active['Fname'] . ' ' . (string)$active['Lname']);
            if ((int)$active['senior_user_id'] > 0) {
                $this->userModel->ensureSeniorProfile((int)$active['senior_user_id']);
            }
        }

        if ($_SESSION['role'] === 'Senior') {
            header('Location: /senior_care/views/senior/dashboard.php');
        } elseif ($_SESSION['role'] === 'FamilyProxy') {
            header('Location: /senior_care/views/proxy/dashboard.php');
        } elseif ($_SESSION['role'] === 'Pal') {
            header('Location: /senior_care/views/pal/dashboard.php');
        } else {
            header('Location: /senior_care/views/admin/dashboard.php');
        }
        exit();
    }

    private function verifyPasswordWithLegacySupport(string $rawPassword, string $trimmedPassword, string $storedHash, int $userId): bool
    {
        $storedHash = trim($storedHash);
        if ($storedHash === '') {
            return false;
        }

        // Preferred modern hash.
        if (password_verify($rawPassword, $storedHash) || password_verify($trimmedPassword, $storedHash)) {
            return true;
        }

        // Legacy fallback for older seeded/imported accounts.
        $legacyMatched =
            hash_equals($storedHash, $rawPassword) ||
            hash_equals($storedHash, $trimmedPassword) ||
            hash_equals($storedHash, md5($rawPassword)) ||
            hash_equals($storedHash, md5($trimmedPassword)) ||
            hash_equals($storedHash, sha1($rawPassword)) ||
            hash_equals($storedHash, sha1($trimmedPassword));

        if (!$legacyMatched) {
            return false;
        }

        // Auto-upgrade legacy password format to bcrypt.
        $this->userModel->changePassword($userId, password_hash($trimmedPassword, PASSWORD_BCRYPT));
        return true;
    }

    public function register(): void
    {
        $role = $this->mapFormRoleToDb($_POST['role'] ?? 'Senior');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $nationalId = trim((string)($_POST['national_id'] ?? ''));
        $age = (int)($_POST['age'] ?? 0);

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

        if ($nationalId === '') {
            $_SESSION['error'] = 'National ID is required.';
            header('Location: /senior_care/views/auth/register.php');
            exit();
        }
        if ($this->userModel->nationalIdExists($nationalId)) {
            $_SESSION['error'] = 'This national ID is already registered.';
            header('Location: /senior_care/views/auth/register.php');
            exit();
        }
        if ($age <= 0 || $age > 120) {
            $_SESSION['error'] = 'Please enter a valid age.';
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
                'age' => $age,
                'national_id' => $nationalId,
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
                } elseif (stripos($driverMessage, "for key 'national_id'") !== false) {
                    $_SESSION['error'] = 'This national ID is already registered.';
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
            $seniorId = $this->userModel->ensureSeniorProfile($userId);
            $this->userModel->updateSeniorRegistrationDetails($userId, [
                'address' => trim((string)($_POST['address'] ?? '')),
                'comfort_profile' => trim((string)($_POST['comfort_profile'] ?? '')),
                'emergency_contact_name' => trim((string)($_POST['emergency_contact_name'] ?? '')),
                'emergency_contact_phone' => trim((string)($_POST['emergency_contact_phone'] ?? '')),
            ]);
            $medicalNotes = trim((string)($_POST['medical_notes'] ?? ''));
            $allergies = trim((string)($_POST['allergies'] ?? ''));
            $this->userModel->upsertHealthRecord($seniorId, $medicalNotes, $allergies);
            $this->grantSeniorWelcomeBonus($userId);
        }

        if ($role === 'pal') {
            $palId = $this->userModel->ensurePalProfile($userId);
            $skills = trim((string)($_POST['pal_skills'] ?? ''));
            if ($skills !== '') {
                $this->userModel->updatePalProfileByUserId($userId, [
                    'first_name' => trim($_POST['first_name'] ?? ''),
                    'last_name' => trim($_POST['last_name'] ?? ''),
                    'phone' => $phone,
                    'skills' => $skills,
                    'travel_radius_km' => 5,
                    'transport_mode' => 'Walking',
                ]);
            }

            if (isset($_FILES['pal_certificate']) && ($_FILES['pal_certificate']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $certPath = $this->handleUploadTo($_FILES['pal_certificate'], __DIR__ . '/../uploads/badges/', '/senior_care/uploads/badges/');
                if ($certPath !== null) {
                    $badgeName = trim((string)($_POST['pal_badge_name'] ?? 'General Care Certification'));
                    (new BackgroundCheck())->submitSkillBadge($palId, $badgeName, null, null, $certPath);
                }
            }
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

    private function handleUploadTo(array $file, string $targetDir, string $publicPrefix): ?string
    {
        if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            return null;
        }
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            return null;
        }
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        $name = uniqid('doc_', true) . '.' . $ext;
        $target = rtrim($targetDir, '/\\') . DIRECTORY_SEPARATOR . $name;
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            return null;
        }
        return rtrim($publicPrefix, '/\\') . '/' . $name;
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
