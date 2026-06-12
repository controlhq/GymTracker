<?php

require_once 'Repository.php';

class SessionsRepository extends Repository
{
    public function getSessionsForUser(string $userId): array
    {
        return $this->withUserContext($userId, function () use ($userId) {
            $query = $this->database->connect()->prepare(
                'SELECT s.id, s.name, s.status, s.started_at, s.ended_at,
                        wp.name AS plan_name
                 FROM sessions s
                 LEFT JOIN workout_plans wp ON wp.id = s.source_plan_id
                 WHERE s.user_id = ?
                 ORDER BY s.started_at DESC'
            );
            $query->execute([$userId]);
            return $query->fetchAll(PDO::FETCH_ASSOC);
        });
    }

    // Sessions completed per week over the last 12 weeks, zero-filled so the
    // chart always has a full, evenly-spaced axis even for quiet weeks.
    public function getWeeklyFrequency(string $userId): array
    {
        return $this->withUserContext($userId, function () use ($userId) {
            $query = $this->database->connect()->prepare(
                "SELECT wk::date AS week_start, COUNT(s.id) AS session_count
                 FROM generate_series(
                        (date_trunc('week', CURRENT_DATE) - INTERVAL '11 weeks')::date,
                        date_trunc('week', CURRENT_DATE)::date,
                        INTERVAL '1 week') AS wk
                 LEFT JOIN sessions s
                        ON s.user_id = ?
                       AND s.status = 'completed'
                       AND date_trunc('week', s.started_at)::date = wk::date
                 GROUP BY wk
                 ORDER BY wk"
            );
            $query->execute([$userId]);
            return $query->fetchAll(PDO::FETCH_ASSOC);
        });
    }

    // Total lifted volume (weight x reps) and set count per muscle group,
    // across every completed set. Drives the muscle-group distribution chart.
    public function getMuscleGroupVolume(string $userId): array
    {
        return $this->withUserContext($userId, function () use ($userId) {
            $query = $this->database->connect()->prepare(
                "SELECT mg.name AS muscle_group, mg.slug,
                        COALESCE(SUM(ss.weight_kg * ss.reps), 0) AS volume,
                        COUNT(ss.id) AS sets
                 FROM session_sets ss
                 JOIN session_exercises se ON se.id = ss.session_exercise_id
                 JOIN sessions s          ON s.id  = se.session_id
                 JOIN exercises e         ON e.id  = se.exercise_id
                 JOIN muscle_groups mg    ON mg.id = e.muscle_group_id
                 WHERE s.user_id = ? AND ss.is_completed
                 GROUP BY mg.name, mg.slug
                 ORDER BY volume DESC"
            );
            $query->execute([$userId]);
            return $query->fetchAll(PDO::FETCH_ASSOC);
        });
    }

    // Lifetime totals across all completed sets.
    public function getLifetimeStats(string $userId): array
    {
        return $this->withUserContext($userId, function () use ($userId) {
            $query = $this->database->connect()->prepare(
                "SELECT COALESCE(SUM(ss.weight_kg * ss.reps), 0) AS total_volume,
                        COUNT(ss.id)                             AS total_sets,
                        COALESCE(SUM(ss.reps), 0)                AS total_reps
                 FROM session_sets ss
                 JOIN session_exercises se ON se.id = ss.session_exercise_id
                 JOIN sessions s          ON s.id  = se.session_id
                 WHERE s.user_id = ? AND ss.is_completed"
            );
            $query->execute([$userId]);
            return $query->fetch(PDO::FETCH_ASSOC) ?: [
                'total_volume' => 0, 'total_sets' => 0, 'total_reps' => 0,
            ];
        });
    }

    public function getTodayStats(string $userId): array
    {
        return $this->withUserContext($userId, function () use ($userId) {
            $query = $this->database->connect()->prepare(
                "SELECT
                    COUNT(DISTINCT s.id) FILTER (WHERE s.status = 'completed')        AS sessions_completed,
                    COUNT(DISTINCT se.id)                                             AS exercises_performed,
                    COALESCE(SUM(ss.weight_kg * ss.reps) FILTER (WHERE ss.is_completed), 0) AS total_volume
                 FROM sessions s
                 LEFT JOIN session_exercises se ON se.session_id = s.id
                 LEFT JOIN session_sets ss      ON ss.session_exercise_id = se.id
                 WHERE s.user_id = ? AND s.started_at::date = CURRENT_DATE"
            );
            $query->execute([$userId]);
            return $query->fetch(PDO::FETCH_ASSOC) ?: [
                'sessions_completed' => 0, 'exercises_performed' => 0, 'total_volume' => 0,
            ];
        });
    }

    public function getActiveSession(string $userId): ?array
    {
        return $this->withUserContext($userId, function () use ($userId) {
            $query = $this->database->connect()->prepare(
                "SELECT id, name, started_at, source_plan_id
                 FROM sessions
                 WHERE user_id = ? AND status = 'in_progress'
                 LIMIT 1"
            );
            $query->execute([$userId]);
            $row = $query->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        });
    }

    public function startSession(string $userId, string $name, ?string $planId): string
    {
        return $this->withUserContext($userId, function () use ($userId, $name, $planId) {
            $query = $this->database->connect()->prepare(
                'INSERT INTO sessions (user_id, name, source_plan_id)
                 VALUES (?, ?, ?)
                 RETURNING id'
            );
            $query->execute([$userId, $name, $planId]);
            return $query->fetchColumn();
        });
    }

    public function getActiveSessionWithPlan(string $userId): ?array
    {
        return $this->withUserContext($userId, function () use ($userId) {
            $query = $this->database->connect()->prepare(
                "SELECT s.id, s.name, s.started_at, s.source_plan_id,
                        wp.name AS plan_name
                 FROM sessions s
                 LEFT JOIN workout_plans wp ON wp.id = s.source_plan_id
                 WHERE s.user_id = ? AND s.status = 'in_progress'
                 LIMIT 1"
            );
            $query->execute([$userId]);
            $row = $query->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        });
    }

    public function endSession(string $userId, string $sessionId): void
    {
        $this->withUserContext($userId, function () use ($userId, $sessionId) {
            $query = $this->database->connect()->prepare(
                "UPDATE sessions SET status = 'completed' WHERE id = ? AND user_id = ?"
            );
            $query->execute([$sessionId, $userId]);
        });
    }

    public function deleteSession(string $userId, string $sessionId): void
    {
        $this->withUserContext($userId, function () use ($userId, $sessionId) {
            $query = $this->database->connect()->prepare(
                'DELETE FROM sessions WHERE id = ? AND user_id = ?'
            );
            $query->execute([$sessionId, $userId]);
        });
    }
}
