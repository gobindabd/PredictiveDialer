<?php

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private PDO $pdo;
    private string $dsn;
    private string $username;
    private string $password;

    public function __construct(array $config)
    {
        $this->dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );
        $this->username = $config['username'];
        $this->password = $config['password'];

        $this->connect();
    }

    private function connect(): void
    {
        $this->pdo = new PDO($this->dsn, $this->username, $this->password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    /**
     * Re-establish the connection if MySQL closed it (e.g. wait_timeout).
     * Only called on error codes 2006 (server has gone away) and 2013 (lost connection).
     */
    private function reconnectIfGoneAway(PDOException $e): bool
    {
        $code = (int) $e->errorInfo[1];
        if (in_array($code, [2006, 2013], true)) {
            $this->connect();
            return true;
        }
        return false;
    }

    public function fetch(string $sql, array $params = []): ?array
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch();
            return $row === false ? null : $row;
        } catch (PDOException $e) {
            if ($this->reconnectIfGoneAway($e)) {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
                $row = $stmt->fetch();
                return $row === false ? null : $row;
            }
            throw $e;
        }
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            if ($this->reconnectIfGoneAway($e)) {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
                return $stmt->fetchAll();
            }
            throw $e;
        }
    }

    public function execute(string $sql, array $params = []): int
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            if ($this->reconnectIfGoneAway($e)) {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
                return $stmt->rowCount();
            }
            throw $e;
        }
    }

    public function insert(string $sql, array $params = []): int
    {
        $this->execute($sql, $params);
        return (int) $this->pdo->lastInsertId();
    }

    public function transaction(callable $callback): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $callback($this);
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
