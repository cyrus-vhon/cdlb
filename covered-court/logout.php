<?php
/**
 * logout.php
 * -----------------------------------------------------------
 * PHASE: Accounts & Authentication
 *
 * WHAT THIS FILE IS FOR:
 * Ends the current session completely (clears session data,
 * expires the session cookie, destroys the session) and sends
 * the person back to the login page. No page content — this
 * file only ever redirects.
 * -----------------------------------------------------------
 */
require_once __DIR__ . '/config/config.php';

// Tatlong hakbang para tuluyan nang ma-logout ang user:
// 1. Alisin lahat ng laman ng session array (user_id, role, atbp.)
$_SESSION = [];
// 2. Palitan ang session cookie ng browser ng expired na oras
//    (nakalipas na sa "time() - 42000"), para agad itong matanggal
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
// 3. Sirain nang tuluyan ang session sa server side
session_destroy();

// Ibalik sa login page
header('Location: ' . BASE_URL . 'login.php');
exit;
