<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireLogin(): void
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: /senior_care/views/auth/login.php');
        exit();
    }
}

function requireRole(array $roles): void
{
    requireLogin();
    $currentRole = normalizeRole($_SESSION['role'] ?? '');
    $normalizedAllowed = array_map('normalizeRole', $roles);
    if (!in_array($currentRole, $normalizedAllowed, true)) {
        header('Location: /senior_care/views/shared/error.php?message=Access+Denied');
        exit();
    }
}

function normalizeRole(string $role): string
{
    $value = strtolower(trim($role));
    return match ($value) {
        'senior' => 'senior',
        'pal' => 'pal',
        'admin' => 'admin',
        'familyproxy', 'family_proxy', 'proxy' => 'familyproxy',
        default => $value,
    };
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
