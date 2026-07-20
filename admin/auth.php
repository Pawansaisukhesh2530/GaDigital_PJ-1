<?php
require_once __DIR__ . '/session_config.php';

if (session_status() === PHP_SESSION_NONE) {
    $cookie_params = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookie_params['path'] ?: '/',
        'domain' => $cookie_params['domain'] ?? '',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (empty($_SESSION['admin_id'])) {

    header('Location: login.php');
    exit;
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (!function_exists('cpvia_current_admin_name')) {
    function cpvia_current_admin_name(): string
    {
        return $_SESSION['admin_name'] ?? 'Admin';
    }
}

if (!function_exists('cpvia_csrf_token')) {
    function cpvia_csrf_token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('cpvia_csrf_check')) {
    function cpvia_csrf_check(?string $token): bool
    {
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }
}
