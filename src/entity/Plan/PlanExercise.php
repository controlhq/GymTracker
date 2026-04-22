<?php

class PlanExercise
{
    private string  $id;
    private string  $workoutPlanId;
    private string  $exerciseId;
    private int     $position;
    private ?string $notes;

    public function __construct(
        string  $id,
        string  $workoutPlanId,
        string  $exerciseId,
        int     $position,
        ?string $notes
    ) {
        $this->id            = $id;
        $this->workoutPlanId = $workoutPlanId;
        $this->exerciseId    = $exerciseId;
        $this->position      = $position;
        $this->notes         = $notes;
    }

    public function getId(): string           { return $this->id; }
    public function getWorkoutPlanId(): string { return $this->workoutPlanId; }
    public function getExerciseId(): string   { return $this->exerciseId; }
    public function getPosition(): int        { return $this->position; }
    public function getNotes(): ?string       { return $this->notes; }
}
