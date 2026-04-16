<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Env;
use App\Services\AmiClient;
use App\Services\PhpDialerEngine;

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

Env::load(dirname(__DIR__, 2) . '/.env');

$db = new Database(require __DIR__ . '/../config/database.php');
$ami = new AmiClient(
    getenv('AMI_HOST') ?: '127.0.0.1',
    (int) (getenv('AMI_PORT') ?: 5038),
    getenv('AMI_USERNAME') ?: 'predictive_engine',
    getenv('AMI_PASSWORD') ?: ''
);

$engine = new PhpDialerEngine($db, $ami, getenv('ENGINE_ID') ?: 'php-engine-1');

if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGTERM, fn () => $engine->stop());
    pcntl_signal(SIGINT, fn () => $engine->stop());
}

$engine->run();
