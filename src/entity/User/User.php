<?php

class User
{
    private string $id;
    private string $email;
    private string $displayName;
    private string $passwordHash;
    private string $role;
    private string $unitSystem;
    private bool   $isActive;
    private string $createdAt;

    public function __construct(
        string $id,
        string $email,
        string $displayName,
        string $passwordHash,
        string $role,
        string $unitSystem,
        bool   $isActive,
        string $createdAt
    ) {
        $this->id           = $id;
        $this->email        = $email;
        $this->displayName  = $displayName;
        $this->passwordHash = $passwordHash;
        $this->role         = $role;
        $this->unitSystem   = $unitSystem;
        $this->isActive     = $isActive;
        $this->createdAt    = $createdAt;
    }

    public function getId(): string          { return $this->id; }
    public function getEmail(): string       { return $this->email; }
    public function getDisplayName(): string { return $this->displayName; }
    public function getPasswordHash(): string { return $this->passwordHash; }
    public function getRole(): string        { return $this->role; }
    public function getUnitSystem(): string  { return $this->unitSystem; }
    public function isActive(): bool         { return $this->isActive; }
    public function getCreatedAt(): string   { return $this->createdAt; }
}
