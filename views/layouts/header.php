<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$isGuestLayout = !isset($_SESSION['role']);
$isProxyTheme = isset($_SESSION['role']) && ($_SESSION['role'] === 'FamilyProxy');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CareNest</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/senior_care/public/css/style.css">
</head>
<body class="<?= $isGuestLayout ? 'guest-layout' : 'app-layout' ?><?= $isProxyTheme ? ' proxy-theme' : '' ?>">
