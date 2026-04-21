<?php

class AuthProvider
{
    private string $id;
    private string $userId;
    private string $provider;
    private string $providerUserId;
    private string $linkedAt;

    public function __construct(
        string $id,
        string $userId,
        string $provider,
        string $providerUserId,
        string $linkedAt
    ) {
        $this->id             = $id;
        $this->userId         = $userId;
        $this->provider       = $provider;
        $this->providerUserId = $providerUserId;
        $this->linkedAt       = $linkedAt;
    }

    public function getId(): string             { return $this->id; }
    public function getUserId(): string         { return $this->userId; }
    public function getProvider(): string       { return $this->provider; }
    public function getProviderUserId(): string { return $this->providerUserId; }
    public function getLinkedAt(): string       { return $this->linkedAt; }
}
