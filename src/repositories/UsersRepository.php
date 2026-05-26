<?php

require_once 'Repository.php';
require_once __DIR__ . '/../Entity/User/User.php';

class UsersRepository extends Repository
{
    public function findByEmail(string $email): ?User
    {
        $query = $this->database->connect()->prepare(
            'SELECT id, email, display_name, role, unit_system, is_active, created_at FROM users WHERE email = :email'
        );
        $query->bindParam(':email', $email);
        $query->execute();

        $row = $query->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapToUser($row) : null;
    }

    public function findForAuth(string $email): ?User
    {
        $query = $this->database->connect()->prepare(
            'SELECT id, email, display_name, password_hash, role, unit_system, is_active, created_at FROM users WHERE email = :email'
        );
        $query->bindParam(':email', $email);
        $query->execute();

        $row = $query->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapToUser($row) : null;
    }

    public function createUser(string $email, string $hashedPassword, string $displayName): string
    {
        $query = $this->database->connect()->prepare(
            'INSERT INTO users (email, password_hash, display_name) VALUES (?, ?, ?) RETURNING id'
        );
        $query->execute([$email, $hashedPassword, $displayName]);

        return $query->fetchColumn();
    }

    private function mapToUser(array $row): User
    {
        return new User(
            $row['id'],
            $row['email'],
            $row['display_name'],
            $row['password_hash'] ?? '',
            $row['role'],
            $row['unit_system'],
            (bool) $row['is_active'],
            $row['created_at']
        );
    }
}
