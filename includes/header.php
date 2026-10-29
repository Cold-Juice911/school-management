<?php
declare(strict_types=1);

$pageTitle = isset($pageTitle) && is_string($pageTitle) ? $pageTitle : 'School Management';
$signedInUser = current_user();
$currentNavigationPath = isset($activeNavigationHref) && is_string($activeNavigationHref)
    ? APP_BASE_PATH . $activeNavigationHref
    : (string) ($_SERVER['SCRIPT_NAME'] ?? '');
$navigation = [];
if ($signedInUser !== null) {
    $navigation[] = ['Dashboard', '/dashboard.php'];
    if ($signedInUser['role'] === 'admin') {
        $navigation = array_merge($navigation, [
            ['Students', '/admin/students.php'], ['Teachers', '/admin/teachers.php'], ['Classes', '/admin/classes.php'],
            ['Attendance', '/admin/attendance.php'], ['Timetable', '/admin/timetable.php'], ['Fees', '/admin/fees.php'],
        ]);
    } elseif ($signedInUser['role'] === 'teacher') {
        $navigation = array_merge($navigation, [['Attendance', '/teacher/attendance.php'], ['Timetable', '/teacher/timetable.php']]);
    } else {
        $navigation = array_merge($navigation, [
            ['My profile', '/student/profile.php'], ['My attendance', '/student/attendance.php'],
            ['My timetable', '/student/timetable.php'], ['My fees', '/student/fees.php'],
        ]);
    }
}
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pageTitle) ?> · School Management</title>
    <link rel="stylesheet" href="<?= h(app_url('/assets/css/styles.css')) ?>">
    <script src="<?= h(app_url('/assets/js/app.js')) ?>" defer></script>
</head>
<body>
<header class="site-header card">
    <a class="brand" href="<?= h(app_url('/dashboard.php')) ?>">School Management</a>
    <?php if ($signedInUser !== null): ?>
        <div class="account-area"><span class="user-label"><?= h($signedInUser['name']) ?> <span class="badge badge-active"><?= h(ucfirst((string) $signedInUser['role'])) ?></span></span>
            <form method="post" action="<?= h(app_url('/logout.php')) ?>" class="inline-form" data-confirm="Are you sure you want to sign out?" data-confirm-title="Sign out?" data-confirm-label="Sign out" data-confirm-variant="warning"><?= csrf_field() ?><button class="btn btn-secondary" type="submit">Sign out</button></form>
        </div>
    <?php endif; ?>
</header>
<div class="app-shell">
    <?php if ($signedInUser !== null): ?>
        <nav class="sidebar card" aria-label="Main navigation">
            <span class="eyebrow">MENU</span>
            <?php foreach ($navigation as [$label, $href]): ?>
                <a class="nav-link<?= str_starts_with($currentNavigationPath, APP_BASE_PATH . $href) ? ' is-active' : '' ?>" href="<?= h(app_url($href)) ?>"><?= h($label) ?></a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>
    <main class="main-content">
        <?php foreach (take_flashes() as $message): ?>
            <?php $type = in_array($message['type'] ?? '', ['success', 'warning', 'error'], true) ? $message['type'] : 'info'; ?>
            <div class="notice notice-<?= h($type) ?>" role="status"><?= h($message['message'] ?? '') ?></div>
        <?php endforeach; ?>
