<?php

namespace App\Core;

class Router
{
    private array $routes = [];

    public function __construct(private Database $db, private array $config)
    {
    }

    public function get(string $path, string $controller, string $method): void
    {
        $this->routes['GET'][$path] = [$controller, $method];
    }

    public function post(string $path, string $controller, string $method): void
    {
        $this->routes['POST'][$path] = [$controller, $method];
    }

    public function dispatch(string $httpMethod, string $path): void
    {
        $target = $this->routes[$httpMethod][$path] ?? null;
        if (!$target) {
            http_response_code(404);
            echo 'Not found';
            return;
        }

        [$controllerClass, $method] = $target;
        $controller = new $controllerClass($this->db, $this->config);
        $controller->$method();
    }
}
