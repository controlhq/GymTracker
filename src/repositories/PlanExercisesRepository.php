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
                        mg.name AS muscle_group,
                        COUNT(ps.id)             AS sets,
                        MAX(ps.target_reps)      AS reps,
                        MAX(ps.target_rest_sec)  AS rest_sec
                 FROM plan_exercises pe
                 JOIN exercises e  ON e.id  = pe.exercise_id
                 JOIN muscle_groups mg ON mg.id = e.muscle_group_id
                 LEFT JOIN plan_sets ps ON ps.plan_exercise_id = pe.id
                 WHERE pe.workout_plan_id = ?
                 GROUP BY pe.id, pe.exercise_id, pe.position, pe.notes,
                          e.name, e.exercise_type, mg.name
                 ORDER BY pe.position'
            );
            $query->execute([$planId]);
            return $query->fetchAll(PDO::FETCH_ASSOC);
        });
    }

    public function addExerciseToPlan(string $userId, string $planId, string $exerciseId, ?int $sets = null, ?int $reps = null, ?int $restSec = null): string
    {
        return $this->withUserContext($userId, function () use ($userId, $planId, $exerciseId, $sets, $reps, $restSec) {
            $posQuery = $this->database->connect()->prepare(
                'SELECT COALESCE(MAX(position), 0) + 1 FROM plan_exercises WHERE workout_plan_id = ?'
            );
            $posQuery->execute([$planId]);
            $position = (int) $posQuery->fetchColumn();

            // Insert only if the plan belongs to the caller — never touch a foreign plan.
            $query = $this->database->connect()->prepare(
                'INSERT INTO plan_exercises (workout_plan_id, exercise_id, position)
                 SELECT ?, ?, ?
                 WHERE EXISTS (SELECT 1 FROM workout_plans WHERE id = ? AND user_id = ?)
                 RETURNING id'
            );
            $query->execute([$planId, $exerciseId, $position, $planId, $userId]);
            $planExerciseId = $query->fetchColumn();

            if ($planExerciseId && $sets && $sets > 0) {
                $setStmt = $this->database->connect()->prepare(
                    'INSERT INTO plan_sets (plan_exercise_id, set_number, target_reps, target_rest_sec)
                     VALUES (?, ?, ?, ?)'
                );
                for ($i = 1; $i <= $sets; $i++) {
                    $setStmt->execute([$planExerciseId, $i, $reps, $restSec]);
                }
            }

            return $planExerciseId;
        });
    }

    public function removeExerciseFromPlan(string $userId, string $planExerciseId): void
    {
        $this->withUserContext($userId, function () use ($userId, $planExerciseId) {
            $query = $this->database->connect()->prepare(
                'DELETE FROM plan_exercises pe
                 USING workout_plans wp
                 WHERE pe.id = ?
                   AND pe.workout_plan_id = wp.id
                   AND wp.user_id = ?'
            );
            $query->execute([$planExerciseId, $userId]);
        });
    }
}
