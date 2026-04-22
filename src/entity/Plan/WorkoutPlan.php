<?php

require_once __DIR__ . '/PlanStatus.php';

class WorkoutPlan
{
    private string  $id;
    private string  $userId;
    private string  $name;
    private ?string $description;
    private string  $status;
    private ?int    $intensity;
    private ?int    $durationMin;
    private string  $createdAt;
    private string  $updatedAt;

    public function __construct(
        string  $id,
        string  $userId,
        string  $name,
        ?string $description,
        string  $status,
        ?int    $intensity,
        ?int    $durationMin,
        string  $createdAt,
        string  $updatedAt
    ) {
        $this->id          = $id;
        $this->userId      = $userId;
        $this->name        = $name;
        $this->description = $description;
        $this->status      = $status;
        $this->intensity   = $intensity;
        $this->durationMin = $durationMin;
        $this->createdAt   = $createdAt;
        $this->updatedAt   = $updatedAt;
    }

    public function getId(): string          { return $this->id; }
    public function getUserId(): string      { return $this->userId; }
    public function getName(): string        { return $this->name; }
    public function getDescription(): ?string { return $this->description; }
    public function getStatus(): string      { return $this->status; }
    public function getIntensity(): ?int     { return $this->intensity; }
    public function getDurationMin(): ?int   { return $this->durationMin; }
    public function getCreatedAt(): string   { return $this->createdAt; }
    public function getUpdatedAt(): string   { return $this->updatedAt; }

    public function isActive(): bool  { return $this->status === PlanStatus::ACTIVE; }
    public function isDraft(): bool   { return $this->status === PlanStatus::DRAFT; }

    public function activate(): void
    {
        $this->status = PlanStatus::ACTIVE;
    }

    public function archive(): void
    {
        $this->status = PlanStatus::ARCHIVED;
    }
}
