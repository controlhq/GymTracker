<?php

require_once 'Repository.php';

class PlanExercisesRepository extends Repository
{
    public function getExercisesForPlan(string $userId, string $planId): array
    {
        return $this->withUserContext($userId, function () use ($planId) {
            $query = $this->database->connect()->prepare(
                'SELECT pe.id, pe.exercise_id, pe.position, pe.notes,
                        e.name AS exercise_name, e.exercise_type,
                        mg.name AS muscle_group
                 FROM plan_exercises pe
                 JOIN exercises e ON e.id = pe.exercise_id
                 JOIN muscle_groups mg ON mg.id = e.muscle_group_id
                 WHERE pe.workout_plan_id = ?
                 ORDER BY pe.position'
            );
            $query->execute([$planId]);
            return $query->fetchAll(PDO::FETCH_ASSOC);
        });
    }

    public function addExerciseToPlan(string $userId, string $planId, string $exerciseId): string
    {
        return $this->withUserContext($userId, function () use ($planId, $exerciseId) {
            $posQuery = $this->database->connect()->prepare(
                'SELECT COALESCE(MAX(position), 0) + 1 FROM plan_exercises WHERE workout_plan_id = ?'
            );
            $posQuery->execute([$planId]);
            $position = (int) $posQuery->fetchColumn();

            $query = $this->database->connect()->prepare(
                'INSERT INTO plan_exercises (workout_plan_id, exercise_id, position)
                 VALUES (?, ?, ?)
                 RETURNING id'
            );
            $query->execute([$planId, $exerciseId, $position]);
            return $query->fetchColumn();
        });
    }

    public function removeExerciseFromPlan(string $userId, string $planExerciseId): void
    {
        $this->withUserContext($userId, function () use ($planExerciseId) {
            $query = $this->database->connect()->prepare(
                'DELETE FROM plan_exercises WHERE id = ?'
            );
            $query->execute([$planExerciseId]);
        });
    }
}
