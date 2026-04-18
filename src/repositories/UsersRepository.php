<?php

require_once 'Repository.php';
require_once __DIR__ . '/../entity/User.php';

class UsersRepository extends Repository
{
    public function getUsers(): array
    {
        $query = $this->database->connect()->prepare('SELECT * FROM users');
        $query->execute();

        $rows  = $query->fetchAll(PDO::FETCH_ASSOC);
        $users = [];

        foreach ($rows as $row) {
            $users[] = $this->mapToUser($row);
        }

        return $users;
    }

    public function getUserByEmail(string $email): ?User
    {
        $query = $this->database->connect()->prepare(
            'SELECT * FROM users WHERE email = :email'
        );
        $query->bindParam(':email', $email);
        $query->execute();

        $row = $query->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->mapToUser($row);
    }

    public function createUser(string $email, string $hashedPassword, string $username): void
    {
        $query = $this->database->connect()->prepare(
            'INSERT INTO users (username, email, password) VALUES (?, ?, ?)'
        );
        $query->execute([$username, $email, $hashedPassword]);
    }

    private function mapToUser(array $row): User
    {
        return new User(
            $row['username'],
            $row['email'],
            $row['password'],
            $row['created_at'],
            (bool) $row['is_active'],
            (int) $row['id']
        );
    }
}
