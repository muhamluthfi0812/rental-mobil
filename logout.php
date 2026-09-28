<?php
/**
 * =====================================================================
 * FILE: logout.php
 * FUNGSI: Mengakhiri session secara aman lalu redirect ke login.
 * =====================================================================
 */
require_once __DIR__ . '/functions.php';

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();
session_start();
setFlash('success', 'Anda telah berhasil logout.');
redirect('login.php');
