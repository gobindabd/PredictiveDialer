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

        try {
            $controller->$method();
        } catch (\Throwable $e) {
            $this->handleException($e, $httpMethod);
        }
    }

    private function handleException(\Throwable $e, string $httpMethod): void
    {
        $isApi = str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/');
        $isDev = (getenv('APP_ENV') ?: 'production') === 'development';

        // Log internally — never expose stack traces in production.
        error_log('[Router] Unhandled exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

        if (http_response_code() === 200) {
            http_response_code(500);
        }

        if ($isApi) {
            header('Content-Type: application/json');
            echo json_encode([
                'ok'    => false,
                'error' => $isDev ? $e->getMessage() : 'An internal error occurred.',
            ]);
            return;
        }

        if ($isDev) {
            echo '<pre style="margin:2rem;font-family:monospace;font-size:13px;">'
                . '<strong>' . htmlspecialchars(get_class($e)) . '</strong>: '
                . htmlspecialchars($e->getMessage()) . "\n\n"
                . htmlspecialchars($e->getTraceAsString())
                . '</pre>';
        } else {
            echo '<div style="font-family:sans-serif;margin:2rem;">'
                . '<h2>Something went wrong</h2>'
                . '<p>An unexpected error occurred. Please try again or contact support.</p>'
                . '</div>';
        }
    }
}
