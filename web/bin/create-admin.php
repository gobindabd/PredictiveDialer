<?php

declare(strict_types=1);

use App\Core\Database;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from CLI.\n");
    exit(1);
}

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $path = __DIR__ . '/../app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

\App\Core\Env::load(dirname(__DIR__, 2) . '/.env');

$username = $argv[1] ?? null;
$email = $argv[2] ?? null;
$password = $argv[3] ?? null;

if (!$username || !$email || !$password) {
    fwrite(STDERR, "Usage: php web/bin/create-admin.php USERNAME EMAIL PASSWORD\n");
    exit(1);
}

$db = new Database(require __DIR__ . '/../config/database.php');
$hash = password_hash($password, PASSWORD_ARGON2ID);

$db->execute(
    "INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, 'admin')",
    [$username, $email, $hash]
);

echo "Admin user created.\n";
