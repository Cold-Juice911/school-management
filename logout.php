<?php
declare(strict_types=1);
require_once __DIR__ . '/config/bootstrap.php';
if (!is_post()) {
    render_error_page(405, 'Sign out requires a form', 'Use the sign out button in the page header to end your session.');
}
verify_csrf();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'] ?? 'Lax',
    ]);
}
session_destroy();
redirect('/login.php');
