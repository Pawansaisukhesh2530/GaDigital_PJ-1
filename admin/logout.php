<?php

require_once __DIR__ . '/session_config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $cookie_params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $cookie_params['path'],
        $cookie_params['domain'],
        $cookie_params['secure'],
        $cookie_params['httponly']
    );
}

session_destroy();

header('Location: login.php');
exit;
