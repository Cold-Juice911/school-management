<?php
declare(strict_types=1);
require_once __DIR__ . '/config/bootstrap.php';

if (current_user() === null) {
    redirect('/login.php');
}
redirect('/dashboard.php');
