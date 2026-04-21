<?php

class MuscleGroup
{
    private int    $id;
    private string $name;
    private string $slug;

    public function __construct(
        int    $id,
        string $name,
        string $slug
    ) {
        $this->id   = $id;
        $this->name = $name;
        $this->slug = $slug;
    }

    public function getId(): int      { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getSlug(): string { return $this->slug; }
}
