<?php

class Database {
    private string $username;
    private string $password;
    private string $host;
    private string $database;
    private ?PDO $pdo = null;

    public function __construct()
    {
        $this->username = getenv('DB_USERNAME');
        $this->password = getenv('DB_PASSWORD');
        $this->host     = getenv('DB_HOST');
        $this->database = getenv('DB_DATABASE');
    }

    public function connect(): PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        try {
            $this->pdo = new PDO(
                "pgsql:host={$this->host};port=5432;dbname={$this->database}",
                $this->username,
                $this->password,
                ["sslmode" => "prefer"]
            );
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $this->pdo;
        } catch (PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }

    public function beginTransaction(): void
    {
        $this->connect()->beginTransaction();
    }

    public function commit(): void
    {
        $this->connect()->commit();
    }

    public function rollback(): void
    {
        $this->connect()->rollBack();
    }

    public function setUserContext(string $uuid): void
    {
        $pdo = $this->connect();
        $safe = $pdo->quote($uuid);
        $pdo->exec("SET LOCAL app.current_user_id = {$safe}");
    }
}
