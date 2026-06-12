<?php

require_once 'Repository.php';

class AdminRepository extends Repository
{
    public function getUserStats(): array
    {
        $query = $this->database->connect()->prepare(
            "SELECT * FROM user_stats ORDER BY created_at DESC"
        );
        $query->execute();
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    public function setUserActive(string $userId, bool $active): void
    {
        $query = $this->database->connect()->prepare(
            "UPDATE users SET is_active = ? WHERE id = ? AND role <> 'admin'"
        );
        $query->execute([$active ? 'true' : 'false', $userId]);
    }
}
