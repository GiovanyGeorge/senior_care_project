<?php
session_start();

if (($_GET['action'] ?? '') === 'logout') {
    session_destroy();
    header('Location: /senior_care/views/auth/login.php');
    exit();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /senior_care/views/auth/login.php');
    exit();
}

$role = $_SESSION['role'] ?? '';
if ($role === 'Senior' || $role === 'FamilyProxy') {
    header('Location: /senior_care/views/senior/dashboard.php');
} elseif ($role === 'Pal') {
    header('Location: /senior_care/views/pal/dashboard.php');
} else {
    header('Location: /senior_care/views/admin/dashboard.php');
}
exit();
