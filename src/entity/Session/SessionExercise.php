<?php

class SessionExercise
{
    private string  $id;
    private string  $sessionId;
    private ?string $exerciseId;
    private string  $exerciseNameSnapshot;
    private int     $position;

    public function __construct(
        string  $id,
        string  $sessionId,
        ?string $exerciseId,
        string  $exerciseNameSnapshot,
        int     $position
    ) {
        $this->id                   = $id;
        $this->sessionId            = $sessionId;
        $this->exerciseId           = $exerciseId;
        $this->exerciseNameSnapshot = $exerciseNameSnapshot;
        $this->position             = $position;
    }

    public function getId(): string                  { return $this->id; }
    public function getSessionId(): string           { return $this->sessionId; }
    public function getExerciseId(): ?string         { return $this->exerciseId; }
    public function getExerciseNameSnapshot(): string { return $this->exerciseNameSnapshot; }
    public function getPosition(): int               { return $this->position; }
}
