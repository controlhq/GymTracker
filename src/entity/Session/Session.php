<?php

require_once __DIR__ . '/SessionStatus.php';
require_once __DIR__ . '/../Plan/WorkoutPlan.php';
require_once __DIR__ . '/../User/User.php';

class Session
{
    private string  $id;
    private string  $userId;
    private ?string $sourcePlanId;
    private string  $name;
    private string  $status;
    private string  $startedAt;
    private ?string $endedAt;

    public function __construct(
        string  $id,
        string  $userId,
        ?string $sourcePlanId,
        string  $name,
        string  $status,
        string  $startedAt,
        ?string $endedAt
    ) {
        $this->id           = $id;
        $this->userId       = $userId;
        $this->sourcePlanId = $sourcePlanId;
        $this->name         = $name;
        $this->status       = $status;
        $this->startedAt    = $startedAt;
        $this->endedAt      = $endedAt;
    }

    public function getId(): string           { return $this->id; }
    public function getUserId(): string       { return $this->userId; }
    public function getSourcePlanId(): ?string { return $this->sourcePlanId; }
    public function getName(): string         { return $this->name; }
    public function getStatus(): string       { return $this->status; }
    public function getStartedAt(): string    { return $this->startedAt; }
    public function getEndedAt(): ?string     { return $this->endedAt; }

    public function isInProgress(): bool { return $this->status === SessionStatus::IN_PROGRESS; }
    public function isCompleted(): bool  { return $this->status === SessionStatus::COMPLETED; }

    /**
     * Factory: creates a new in-progress session from a plan.
     * ID is empty — assign the value returned by DB RETURNING id after insert.
     */
    public static function startFromPlan(WorkoutPlan $plan, User $user): self
    {
        return new self(
            '',
            $user->getId(),
            $plan->getId(),
            $plan->getName() . ' — ' . date('Y-m-d H:i'),
            SessionStatus::IN_PROGRESS,
            date('Y-m-d H:i:s'),
            null
        );
    }
}
