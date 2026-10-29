<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found.\n");
}

require_once dirname(__DIR__) . '/config/bootstrap.php';

if (!function_exists('read_cli_value')) {
    function read_cli_value(string $prompt): string
    {
        fwrite(STDOUT, $prompt);
        $value = fgets(STDIN);
        return $value === false ? '' : trim($value);
    }
}

try {
    $existing = (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    if ($existing > 0) {
        fwrite(STDERR, "An administrator already exists. No changes were made.\n");
        exit(1);
    }

    $name = read_cli_value('Administrator name: ');
    $email = strtolower(read_cli_value('Administrator email: '));
    $password = read_cli_value('Password (at least 12 characters): ');
    $confirmation = read_cli_value('Confirm password: ');
    if ($name === '' || strlen($name) > 160 || filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($password) < 12 || !hash_equals($password, $confirmation)) {
        fwrite(STDERR, "Input is invalid. Use a valid email and matching password of at least 12 characters.\n");
        exit(1);
    }

    $insert = db()->prepare('INSERT INTO users (name, email, password, role) VALUES (:name, :email, :password, \'admin\')');
    $insert->execute(['name' => $name, 'email' => $email, 'password' => password_hash($password, PASSWORD_DEFAULT)]);
    fwrite(STDOUT, "Administrator account created. Sign in using the email you entered.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, "Administrator setup failed. Check the database configuration and schema, then try again.\n");
    exit(1);
}
