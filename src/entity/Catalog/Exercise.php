<?php

class Exercise
{
    private string  $id;
    private int     $muscleGroupId;
    private string  $name;
    private ?string $description;
    private string  $exerciseType;
    private ?string $animationUrl;
    private ?string $thumbnailUrl;
    private string  $animationFormat;
    private bool    $isActive;
    private string  $createdAt;

    public function __construct(
        string  $id,
        int     $muscleGroupId,
        string  $name,
        ?string $description,
        string  $exerciseType,
        ?string $animationUrl,
        ?string $thumbnailUrl,
        string  $animationFormat,
        bool    $isActive,
        string  $createdAt
    ) {
        $this->id              = $id;
        $this->muscleGroupId   = $muscleGroupId;
        $this->name            = $name;
        $this->description     = $description;
        $this->exerciseType    = $exerciseType;
        $this->animationUrl    = $animationUrl;
        $this->thumbnailUrl    = $thumbnailUrl;
        $this->animationFormat = $animationFormat;
        $this->isActive        = $isActive;
        $this->createdAt       = $createdAt;
    }

    public function getId(): string             { return $this->id; }
    public function getMuscleGroupId(): int     { return $this->muscleGroupId; }
    public function getName(): string           { return $this->name; }
    public function getDescription(): ?string   { return $this->description; }
    public function getExerciseType(): string   { return $this->exerciseType; }
    public function getAnimationUrl(): ?string  { return $this->animationUrl; }
    public function getThumbnailUrl(): ?string  { return $this->thumbnailUrl; }
    public function getAnimationFormat(): string { return $this->animationFormat; }
    public function isActive(): bool            { return $this->isActive; }
    public function getCreatedAt(): string      { return $this->createdAt; }
}
