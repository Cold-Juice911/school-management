<?php
declare(strict_types=1);

function db(): PDO
{
    static $connection = null;
    if ($connection instanceof PDO) {
        return $connection;
    }

    $url = getenv('DATABASE_URL');
    if ($url === false || trim($url) === '') {
        throw new RuntimeException('DATABASE_URL is not configured. Copy .env.example to .env and set the database connection.');
    }

    $parts = parse_url($url);
    if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'mysql' || empty($parts['host']) || empty($parts['user']) || !isset($parts['pass'], $parts['path'])) {
        throw new RuntimeException('DATABASE_URL must be a valid MySQL service URI.');
    }

    $database = rawurldecode(ltrim((string) $parts['path'], '/'));
    $host = (string) $parts['host'];
    $port = (int) ($parts['port'] ?? 3306);
    if ($database === '' || !preg_match('/^[A-Za-z0-9_$-]+$/', $database) || !preg_match('/^[A-Za-z0-9.-]+$/', $host) || $port < 1 || $port > 65535) {
        throw new RuntimeException('DATABASE_URL must include a database name.');
    }

    $query = [];
    parse_str((string) ($parts['query'] ?? ''), $query);
    $sslMode = strtoupper((string) ($query['ssl-mode'] ?? $query['ssl_mode'] ?? $query['sslmode'] ?? 'REQUIRED'));
    if (!in_array($sslMode, ['REQUIRED', 'VERIFY_CA', 'VERIFY_IDENTITY', 'DISABLED'], true)) {
        throw new RuntimeException('Unsupported MySQL SSL mode.');
    }

    $caPath = getenv('DATABASE_SSL_CA');
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4;sslmode=%s',
        $host,
        $port,
        $database,
        $sslMode
    );
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    if ($sslMode === 'VERIFY_CA' || $sslMode === 'VERIFY_IDENTITY') {
        if ($caPath === false || trim($caPath) === '' || !is_file($caPath) || @file_get_contents($caPath) === false) {
            throw new RuntimeException('A readable DATABASE_SSL_CA certificate file is required for certificate verification.');
        }
        $quotedCaPath = str_replace("'", "\\'", str_replace('\\', '\\\\', $caPath));
        $dsn .= ";sslrootcert='" . $quotedCaPath . "'";
        if (defined('PDO::MYSQL_ATTR_SSL_CA')) {
            $options[constant('PDO::MYSQL_ATTR_SSL_CA')] = $caPath;
        }
        if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
            $options[constant('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')] = true;
        }
    }

    try {
        $connection = new PDO($dsn, rawurldecode((string) $parts['user']), rawurldecode((string) $parts['pass']), $options);
    } catch (PDOException $exception) {
        throw new RuntimeException('Unable to connect to the configured database.', 0, $exception);
    }

    return $connection;
}
