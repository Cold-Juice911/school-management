<?php
declare(strict_types=1);
require_once __DIR__ . '/config/bootstrap.php';

if (current_user() !== null) {
    redirect('/dashboard.php');
}
$pageTitle = 'Sign in';
$lockedUntil = (int) ($_SESSION['login_locked_until'] ?? 0);
if (is_post()) {
    try {
        verify_csrf();
        if (time() < $lockedUntil) {
            throw new InvalidArgumentException('Too many attempts. Please wait a few minutes and try again.');
        }
        $email = post_string('email', 190);
        $password = post_string('password', 1024);
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || !login_user($email, $password)) {
            $failures = (int) ($_SESSION['login_failures'] ?? 0) + 1;
            $_SESSION['login_failures'] = $failures;
            if ($failures >= 5) {
                $_SESSION['login_locked_until'] = time() + 300;
            }
            throw new InvalidArgumentException('Email or password is incorrect.');
        }
        redirect('/dashboard.php');
    } catch (Throwable $exception) {
        form_error($exception);
    }
}
require __DIR__ . '/includes/header.php';
?>
<section class="auth-panel card">
    <span class="eyebrow">SCHOOL PORTAL</span>
    <h1>Welcome back</h1>
    <p class="muted">Sign in with the account provided by your school administrator.</p>
    <form method="post" class="form-stack">
        <?= csrf_field() ?>
        <label>Email address<input type="email" name="email" autocomplete="username" maxlength="190" required></label>
        <label>Password<input type="password" name="password" autocomplete="current-password" maxlength="1024" required></label>
        <button class="btn btn-primary" type="submit">Sign in</button>
    </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
