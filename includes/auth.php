<?php
declare(strict_types=1);

function current_user(): ?array
{
    $userId = $_SESSION['user_id'] ?? null;
    if (!is_int($userId)) {
        return null;
    }
    $user = fetch_user_profile($userId);
    if ($user === null || (int) $user['is_active'] !== 1) {
        unset($_SESSION['user_id']);
        return null;
    }
    return $user;
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        flash('error', 'Please sign in to continue.');
        redirect('/login.php');
    }
    return $user;
}

function require_roles(string ...$roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        render_error_page(403, 'Access denied', 'Your account does not have permission to view this page.');
    }
    return $user;
}

function login_user(string $email, string $password): bool
{
    $statement = db()->prepare('SELECT id, password, is_active FROM users WHERE email = :email AND role IN (\'admin\', \'teacher\', \'student\') LIMIT 1');
    $statement->execute(['email' => strtolower(trim($email))]);
    $account = $statement->fetch();
    if (!is_array($account) || (int) $account['is_active'] !== 1 || !password_verify($password, (string) $account['password'])) {
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $account['id'];
    unset($_SESSION['login_failures'], $_SESSION['login_locked_until'], $_SESSION['csrf_token']);
    return true;
}
