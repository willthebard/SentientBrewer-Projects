<?php
session_start();
require_once __DIR__ . '/../lib/DB.php';

function isAdminLoggedIn(): bool {
    return !empty($_SESSION['admin_id']);
}

function requireAdmin(): void {
    if (!isAdminLoggedIn()) {
        header('Location: /admin/login.php');
        exit;
    }
}

function adminLogin(string $username, string $password): bool {
    $pdo = DB::getInstance();
    $stmt = $pdo->prepare('SELECT id, password_hash FROM admin_users WHERE username = ? AND is_active = 1');
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        return false;
    }

    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_user'] = $username;

    $pdo->prepare('UPDATE admin_users SET last_login = NOW() WHERE id = ?')->execute([$admin['id']]);
    return true;
}

function adminLogout(): void {
    session_destroy();
    header('Location: /admin/login.php');
    exit;
}
