<?php

require_once __DIR__ . '/SetType.php';

class PlanSet
{
    private string  $id;
    private string  $planExerciseId;
    private int     $setNumber;
    private string  $setType;
    /** @var float|null weight in kg — store as-is, never convert */
    private ?float  $targetWeightKg;
    private ?int    $targetReps;
    private ?int    $targetRestSec;

    public function __construct(
        string  $id,
        string  $planExerciseId,
        int     $setNumber,
        string  $setType,
        ?float  $targetWeightKg,
        ?int    $targetReps,
        ?int    $targetRestSec
    ) {
        $this->id             = $id;
        $this->planExerciseId = $planExerciseId;
        $this->setNumber      = $setNumber;
        $this->setType        = $setType;
        $this->targetWeightKg = $targetWeightKg;
        $this->targetReps     = $targetReps;
        $this->targetRestSec  = $targetRestSec;
    }

    public function getId(): string              { return $this->id; }
    public function getPlanExerciseId(): string  { return $this->planExerciseId; }
    public function getSetNumber(): int          { return $this->setNumber; }
    public function getSetType(): string         { return $this->setType; }
    public function getTargetWeightKg(): ?float  { return $this->targetWeightKg; }
    public function getTargetReps(): ?int        { return $this->targetReps; }
    public function getTargetRestSec(): ?int     { return $this->targetRestSec; }
}
