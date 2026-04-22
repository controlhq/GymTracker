<?php

require_once 'Repository.php';

class SessionExercisesRepository extends Repository
{
    public function getExercisesForSession(string $userId, string $sessionId): array
    {
        return $this->withUserContext($userId, function () use ($sessionId) {
            $query = $this->database->connect()->prepare(
                'SELECT se.id, se.exercise_id, se.exercise_name_snapshot, se.position,
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
        return $this->withUserContext($userId, function () use ($sessionId, $exerciseId, $exerciseName) {
            $posQuery = $this->database->connect()->prepare(
                'SELECT COALESCE(MAX(position), 0) + 1 FROM session_exercises WHERE session_id = ?'
            );
            $posQuery->execute([$sessionId]);
            $position = (int) $posQuery->fetchColumn();

            $query = $this->database->connect()->prepare(
                'INSERT INTO session_exercises (session_id, exercise_id, exercise_name_snapshot, position)
                 VALUES (?, ?, ?, ?)
                 RETURNING id'
            );
            $query->execute([$sessionId, $exerciseId, $exerciseName, $position]);
            return $query->fetchColumn();
        });
    }

    public function getSetsForSessionExercise(string $userId, string $sessionExerciseId): array
    {
        return $this->withUserContext($userId, function () use ($sessionExerciseId) {
            $query = $this->database->connect()->prepare(
                'SELECT id, set_number, weight_kg, reps, rpe, is_completed
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
        return $this->withUserContext($userId, function () use ($sessionExerciseId, $setNumber, $weightKg, $reps, $rpe) {
            $query = $this->database->connect()->prepare(
                'INSERT INTO session_sets (session_exercise_id, set_number, weight_kg, reps, rpe)
                 VALUES (?, ?, ?, ?, ?)
                 RETURNING id'
            );
            $query->execute([$sessionExerciseId, $setNumber, $weightKg, $reps, $rpe]);
            return $query->fetchColumn();
        });
    }
}
