<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/admin_recovery.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    $sessionPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sessions';
    if (!is_dir($sessionPath)) {
        mkdir($sessionPath, 0775, true);
    }
    session_save_path($sessionPath);
    session_start();
}

function admin_user(): ?array
{
    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    try {
        ensure_admin_recovery_schema();
        $stmt = db()->prepare('SELECT id, name, email, session_version FROM admin_users WHERE id = ?');
        $stmt->execute([$_SESSION['admin_id']]);
        $admin=$stmt->fetch();
        if(!$admin || (int)$admin['session_version']!==(int)($_SESSION['admin_session_version'] ?? 1)){
            unset($_SESSION['admin_id'],$_SESSION['admin_session_version']);
            return null;
        }
        return $admin;
    } catch (Throwable $e) {
        return null;
    }
}

function require_admin(): array
{
    $user = admin_user();
    if (!$user) {
        redirect('login.php');
    }
    return $user;
}
