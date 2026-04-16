<?php

namespace App\Services;

use App\Core\Database;

class AuthService
{
    public function __construct(private Database $db)
    {
    }

    public function attempt(string $username, string $password): ?array
    {
        $user = $this->db->fetch(
            "SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1",
            [$username, $username]
        );

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return null;
        }

        unset($user['password_hash']);
        return $user;
    }
}
