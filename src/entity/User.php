<?php

class User
{
    private int $id;
    private string $username;
    private string $email;
    private string $password;
    private string $createdAt;
    private bool $isActive;

    public function __construct(
        string $username,
        string $email,
        string $password,
        string $createdAt,
        bool $isActive,
        int $id = 0
    ) {
        $this->username  = $username;
        $this->email     = $email;
        $this->password  = $password;
        $this->createdAt = $createdAt;
        $this->isActive  = $isActive;
        $this->id        = $id;
    }

    public function getId(): int       { return $this->id; }
    public function getUsername(): string { return $this->username; }
    public function getEmail(): string    { return $this->email; }
    public function getPassword(): string { return $this->password; }
    public function getCreatedAt(): string { return $this->createdAt; }
    public function isActive(): bool   { return $this->isActive; }
}
