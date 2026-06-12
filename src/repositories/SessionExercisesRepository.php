<?php

require_once 'Repository.php';

class SessionExercisesRepository extends Repository
{
    public function getExercisesForSession(string $userId, string $sessionId): array
    {
        return $this->withUserContext($userId, function () use ($sessionId) {
            $query = $this->database->connect()->prepare(
                'SELECT se.id, se.exercise_id, se.exercise_name_snapshot, se.position, se.is_completed,
                        mg.name AS muscle_group
                 FROM session_exercises se
                 LEFT JOIN exercises e ON e.id = se.exercise_id
                 LEFT JOIN muscle_groups mg ON mg.id = e.muscle_group_id
                 WHERE se.session_id = ?
                 ORDER BY se.position'
            );
            $query->execute([$sessionId]);
            return $query->fetchAll(PDO::FETCH_ASSOC);
        });
    }

    public function addExerciseToSession(string $userId, string $sessionId, string $exerciseId, string $exerciseName): string
    {
        return $this->withUserContext($userId, function () use ($userId, $sessionId, $exerciseId, $exerciseName) {
            $posQuery = $this->database->connect()->prepare(
                'SELECT COALESCE(MAX(position), 0) + 1 FROM session_exercises WHERE session_id = ?'
            );
            $posQuery->execute([$sessionId]);
            $position = (int) $posQuery->fetchColumn();

            // Insert only into a session owned by the caller.
            $query = $this->database->connect()->prepare(
                'INSERT INTO session_exercises (session_id, exercise_id, exercise_name_snapshot, position)
                 SELECT ?, ?, ?, ?
                 WHERE EXISTS (SELECT 1 FROM sessions WHERE id = ? AND user_id = ?)
                 RETURNING id'
            );
            $query->execute([$sessionId, $exerciseId, $exerciseName, $position, $sessionId, $userId]);
            return $query->fetchColumn();
        });
    }

    public function copyExercisesFromPlan(string $userId, string $sessionId, string $planId): void
    {
        $this->withUserContext($userId, function () use ($sessionId, $planId) {
            $pdo = $this->database->connect();

            // Plan exercises in order.
            $selEx = $pdo->prepare(
                'SELECT pe.id AS plan_exercise_id, pe.exercise_id, e.name, pe.position
                 FROM plan_exercises pe
                 JOIN exercises e ON e.id = pe.exercise_id
                 WHERE pe.workout_plan_id = ?
                 ORDER BY pe.position'
            );
            $selEx->execute([$planId]);
            $exercises = $selEx->fetchAll(PDO::FETCH_ASSOC);

            // The sets defined for a plan exercise, carrying the rest period.
            $selSets = $pdo->prepare(
                'SELECT set_number, target_rest_sec
                 FROM plan_sets
                 WHERE plan_exercise_id = ?
                 ORDER BY set_number'
            );

            $insEx = $pdo->prepare(
                'INSERT INTO session_exercises (session_id, exercise_id, exercise_name_snapshot, position)
                 VALUES (?, ?, ?, ?) RETURNING id'
            );
            $insSet = $pdo->prepare(
                'INSERT INTO session_sets (session_exercise_id, set_number, rest_sec) VALUES (?, ?, ?)'
            );

            foreach ($exercises as $ex) {
                $insEx->execute([$sessionId, $ex['exercise_id'], $ex['name'], (int) $ex['position']]);
                $sessionExerciseId = $insEx->fetchColumn();

                $selSets->execute([$ex['plan_exercise_id']]);
                foreach ($selSets->fetchAll(PDO::FETCH_ASSOC) as $set) {
                    $insSet->execute([$sessionExerciseId, (int) $set['set_number'], $set['target_rest_sec']]);
                }
            }
        });
    }

    public function getPreviousSetsForExercise(string $userId, ?string $exerciseId, string $excludeSessionId): array
    {
        if ($exerciseId === null) {
            return [];
        }

        return $this->withUserContext($userId, function () use ($userId, $exerciseId, $excludeSessionId) {
            $query = $this->database->connect()->prepare(
                'SELECT ss.set_number, ss.weight_kg, ss.reps
                 FROM session_sets ss
                 WHERE ss.session_exercise_id = (
                     SELECT se.id
                     FROM session_exercises se
                     JOIN sessions s ON s.id = se.session_id
                     WHERE se.exercise_id = ?
                       AND se.session_id <> ?
                       AND s.user_id = ?
                     ORDER BY s.started_at DESC
                     LIMIT 1
                 )
                 ORDER BY ss.set_number'
            );
            $query->execute([$exerciseId, $excludeSessionId, $userId]);
            return $query->fetchAll(PDO::FETCH_ASSOC);
        });
    }

    public function getSetsForSessionExercise(string $userId, string $sessionExerciseId): array
    {
        return $this->withUserContext($userId, function () use ($sessionExerciseId) {
            $query = $this->database->connect()->prepare(
                'SELECT id, set_number, weight_kg, reps, rpe, rest_sec, is_completed
                 FROM session_sets
                 WHERE session_exercise_id = ?
                 ORDER BY set_number'
            );
            $query->execute([$sessionExerciseId]);
            return $query->fetchAll(PDO::FETCH_ASSOC);
        });
    }

    public function logSet(string $userId, string $sessionExerciseId, int $setNumber, ?float $weightKg, ?int $reps, ?int $rpe): string
    {
        return $this->withUserContext($userId, function () use ($userId, $sessionExerciseId, $setNumber, $weightKg, $reps, $rpe) {
            // Insert only against an exercise in a session owned by the caller.
            $query = $this->database->connect()->prepare(
                'INSERT INTO session_sets (session_exercise_id, set_number, weight_kg, reps, rpe)
                 SELECT ?, ?, ?, ?, ?
                 WHERE EXISTS (SELECT 1 FROM session_exercises se
                               JOIN sessions s ON s.id = se.session_id
                               WHERE se.id = ? AND s.user_id = ?)
                 RETURNING id'
            );
            $query->execute([$sessionExerciseId, $setNumber, $weightKg, $reps, $rpe, $sessionExerciseId, $userId]);
            return $query->fetchColumn();
        });
    }

    public function updateSet(string $userId, string $setId, ?float $weightKg, ?int $reps, bool $isCompleted): void
    {
        $this->withUserContext($userId, function () use ($userId, $setId, $weightKg, $reps, $isCompleted) {
            $completed = $isCompleted ? 'true' : 'false';
            $query = $this->database->connect()->prepare(
                'UPDATE session_sets
                 SET weight_kg = ?, reps = ?, is_completed = ?,
                     completed_at = CASE WHEN ? THEN NOW() ELSE NULL END
                 WHERE id = ?
                   AND EXISTS (SELECT 1 FROM session_exercises se
                               JOIN sessions s ON s.id = se.session_id
                               WHERE se.id = session_sets.session_exercise_id
                                 AND s.user_id = ?)'
            );
            $query->execute([$weightKg, $reps, $completed, $completed, $setId, $userId]);
        });
    }

    public function finishExercise(string $userId, string $sessionExerciseId): void
    {
        $this->withUserContext($userId, function () use ($userId, $sessionExerciseId) {
            $query = $this->database->connect()->prepare(
                'UPDATE session_exercises se
                 SET is_completed = true
                 WHERE se.id = ?
                   AND EXISTS (SELECT 1 FROM sessions s
                               WHERE s.id = se.session_id AND s.user_id = ?)'
            );
            $query->execute([$sessionExerciseId, $userId]);
        });
    }
}
