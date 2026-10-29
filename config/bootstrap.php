<?php
declare(strict_types=1);

const APP_ROOT = __DIR__ . '/..';
$requestUri = $_SERVER['REQUEST_URI'] ?? null;
$requestPath = is_string($requestUri) ? parse_url($requestUri, PHP_URL_PATH) : false;
if (!is_string($requestPath) || $requestPath === '') {
    $requestPath = (string) ($_SERVER['SCRIPT_NAME'] ?? '/');
}
$requestPath = str_replace('\\', '/', $requestPath);
if (str_ends_with($requestPath, '/')) {
    $requestPath .= 'index.php';
}
define('APP_REQUEST_PATH', $requestPath);

$scriptDirectory = str_replace('\\', '/', dirname(APP_REQUEST_PATH));
if (in_array(basename($scriptDirectory), ['admin', 'teacher', 'student', 'scripts'], true)) {
    $scriptDirectory = str_replace('\\', '/', dirname($scriptDirectory));
}
$basePath = trim($scriptDirectory, '/.');
define('APP_BASE_PATH', $basePath === '' ? '' : '/' . $basePath);

function load_environment(string $path): void
{
    if (!is_file($path)) {
        return;
    }

    $contents = @file_get_contents($path);
    if ($contents === false) {
        return;
    }

    $lines = preg_split('/\R/', $contents) ?: [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $trimmed, 2);
        $name = trim($name);
        $value = trim($value);
        if ($name === '' || getenv($name) !== false) {
            continue;
        }

        if (strlen($value) >= 2 && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
            $value = substr($value, 1, -1);
        }
        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
    }
}

load_environment(APP_ROOT . '/.env');

date_default_timezone_set('Asia/Kolkata');

$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off';
if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => $isHttps,
        'samesite' => 'Lax',
        'path' => APP_BASE_PATH === '' ? '/' : APP_BASE_PATH . '/',
    ]);
    session_start();
}

require_once APP_ROOT . '/includes/functions.php';
require_once APP_ROOT . '/includes/auth.php';
require_once __DIR__ . '/database.php';

set_exception_handler(static function (Throwable $exception): void {
    $stylesheet = h(app_url('/assets/css/styles.css'));
    $logPath = APP_ROOT . '/storage/logs/app.log';
    error_log(sprintf("[%s] %s in %s:%d\n%s\n", date(DATE_ATOM), $exception->getMessage(), $exception->getFile(), $exception->getLine(), $exception->getTraceAsString()), 3, $logPath);
    http_response_code(500);
    if (getenv('APP_DEBUG') === 'true') {
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Application error</title><link rel="stylesheet" href="' . $stylesheet . '"></head><body><header class="site-header card"><a class="brand" href="' . h(app_url('/')) . '">School Management</a></header><main class="error-content"><section class="card content-card"><span class="eyebrow">DEBUG MODE</span><h1>Application error</h1><pre>' . h($exception->getMessage()) . '</pre></section></main></body></html>';
        return;
    }
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Service unavailable · School Management</title><link rel="stylesheet" href="' . $stylesheet . '"></head><body><header class="site-header card"><a class="brand" href="' . h(app_url('/')) . '">School Management</a></header><main class="error-content"><section class="card content-card"><span class="eyebrow">TEMPORARY ISSUE</span><h1>Something went wrong</h1><p>Please try again. If the problem continues, contact the school administrator.</p></section></main></body></html>';
});
