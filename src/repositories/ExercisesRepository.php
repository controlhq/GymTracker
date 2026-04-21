<?php

require_once 'Repository.php';

class ExercisesRepository extends Repository
{
    public function getAll(): array
    {
        $query = $this->database->connect()->prepare(
            'SELECT e.id, e.name, e.exercise_type, e.description,
                    mg.name AS muscle_group
             FROM exercises e
             JOIN muscle_groups mg ON mg.id = e.muscle_group_id
             WHERE e.is_active = TRUE
             ORDER BY mg.name, e.name'
        );
        $query->execute();

        return $query->fetchAll(PDO::FETCH_ASSOC);
    }
}
