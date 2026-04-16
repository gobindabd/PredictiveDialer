<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Services\AuthService;

class AuthController extends Controller
{
    public function loginForm(): void
    {
        if (!empty($_SESSION['user'])) {
            header('Location: /');
            return;
        }

        $this->view('auth/login', [], 'auth');
    }

    public function login(): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $service = new AuthService($this->db);
        $user = $service->attempt((string) ($_POST['username'] ?? ''), (string) ($_POST['password'] ?? ''));
        if (!$user) {
            $this->view('auth/login', ['error' => 'Invalid username or password.'], 'auth');
            return;
        }
        session_regenerate_id(true);
        $_SESSION['user'] = $user;
        header('Location: /');
    }

    public function logout(): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $_SESSION = [];
        session_destroy();
        header('Location: /login');
    }
}
