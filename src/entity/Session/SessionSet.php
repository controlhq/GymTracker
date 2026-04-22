<?php

require_once __DIR__ . '/../Plan/SetType.php';

class SessionSet
{
    private string  $id;
    private string  $sessionExerciseId;
    private int     $setNumber;
    private string  $setType;
    /** @var float|null weight in kg — store as-is, never convert */
    private ?float  $weightKg;
    private ?int    $reps;
    private ?int    $rpe;
    private ?int    $restSec;
    private bool    $isCompleted;
    private ?string $completedAt;
    private ?string $notes;

    public function __construct(
        string  $id,
        string  $sessionExerciseId,
        int     $setNumber,
        string  $setType,
        ?float  $weightKg,
        ?int    $reps,
        ?int    $rpe,
        ?int    $restSec,
        bool    $isCompleted,
        ?string $completedAt,
        ?string $notes
    ) {
        $this->id                = $id;
        $this->sessionExerciseId = $sessionExerciseId;
        $this->setNumber         = $setNumber;
        $this->setType           = $setType;
        $this->weightKg          = $weightKg;
        $this->reps              = $reps;
        $this->rpe               = $rpe;
        $this->restSec           = $restSec;
        $this->isCompleted       = $isCompleted;
        $this->completedAt       = $completedAt;
        $this->notes             = $notes;
    }

    public function getId(): string              { return $this->id; }
    public function getSessionExerciseId(): string { return $this->sessionExerciseId; }
    public function getSetNumber(): int          { return $this->setNumber; }
    public function getSetType(): string         { return $this->setType; }
    public function getWeightKg(): ?float        { return $this->weightKg; }
    public function getReps(): ?int              { return $this->reps; }
    public function getRpe(): ?int               { return $this->rpe; }
    public function getRestSec(): ?int           { return $this->restSec; }
    public function isCompleted(): bool          { return $this->isCompleted; }
    public function getCompletedAt(): ?string    { return $this->completedAt; }
    public function getNotes(): ?string          { return $this->notes; }

    public function markCompleted(string $at): void
    {
        $this->isCompleted = true;
        $this->completedAt = $at;
    }
}
