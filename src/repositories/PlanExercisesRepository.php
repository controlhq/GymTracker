<?php

require_once 'Repository.php';

class PlanExercisesRepository extends Repository
{
    public function getExercisesForPlan(string $userId, string $planId): array
    {
        return $this->withUserContext($userId, function () use ($planId) {
            $query = $this->database->connect()->prepare(
                'SELECT pe.id, pe.sets, pe.reps, pe.rest_sec, pe.notes,
                        e.name AS exercise_name, e.exercise_type,
                        mg.name AS muscle_group
                 FROM plan_exercises pe
                 JOIN exercises e ON e.id = pe.exercise_id
                 JOIN muscle_groups mg ON mg.id = e.muscle_group_id
                 WHERE pe.plan_id = ?
                 ORDER BY pe.created_at'
            );
            $query->execute([$planId]);
            return $query->fetchAll(PDO::FETCH_ASSOC);
        });
    }

    public function addExerciseToPlan(string $userId, string $planId, string $exerciseId, ?int $sets, ?int $reps, ?int $restSec): string
    {
        return $this->withUserContext($userId, function () use ($planId, $exerciseId, $sets, $reps, $restSec) {
            $query = $this->database->connect()->prepare(
                'INSERT INTO plan_exercises (plan_id, exercise_id, sets, reps, rest_sec)
                 VALUES (?, ?, ?, ?, ?)
                 RETURNING id'
            );
            $query->execute([$planId, $exerciseId, $sets, $reps, $restSec]);
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
