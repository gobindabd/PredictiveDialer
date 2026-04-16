<?php

namespace App\Core;

class Controller
{
    public function __construct(protected Database $db, protected array $config)
    {
    }

    protected function view(string $name, array $data = [], string $layout = 'main'): void
    {
        extract($data, EXTR_SKIP);
        $viewFile = __DIR__ . '/../Views/' . $name . '.php';
        require __DIR__ . '/../Views/layouts/' . $layout . '.php';
    }

    protected function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_THROW_ON_ERROR);
    }

    protected function requireAuth(): void
    {
        if (empty($_SESSION['user'])) {
            header('Location: /login');
            exit;
        }
    }

    protected function requireRole(array $roles): void
    {
        $this->requireAuth();
        if (!in_array($_SESSION['user']['role'] ?? '', $roles, true)) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
    }

    protected function currentUser(): ?array
    {
        return $_SESSION['user'] ?? null;
    }
}
