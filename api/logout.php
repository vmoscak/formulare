<?php
/**
 * Odhlásenie — zmaže cur_advisor cookie a presmeruje na výber poradcu.
 * Gate cookie (gate_auth) sa nemaže, tá patrí celej appke, nie osobe.
 * GET, volané rovno z odkazu v päte navigačnej lišty (assets/shell.js).
 */
require_once __DIR__ . '/../db.php';

setcookie('cur_advisor', '', [
    'expires' => time() - 3600,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax',
]);

header('Location: /');
exit;
