<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Router;

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
session_start();

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

$config = require __DIR__ . '/../config/app.php';
$routes = require __DIR__ . '/../config/routes.php';

$db = new Database(require __DIR__ . '/../config/database.php');
$router = new Router($db, $config);
$routes($router);
$router->dispatch($_SERVER['REQUEST_METHOD'], parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
