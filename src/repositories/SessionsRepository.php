<?php

require_once 'Repository.php';

class SessionsRepository extends Repository
{
    public function getSessionsForUser(string $userId): array
    {
        return $this->withUserContext($userId, function () {
            $query = $this->database->connect()->prepare(
                'SELECT s.id, s.started_at, s.ended_at, s.notes,
                        wp.name AS plan_name
                 FROM workout_sessions s
                 LEFT JOIN workout_plans wp ON wp.id = s.plan_id
                 ORDER BY s.started_at DESC'
            );
            $query->execute();
            return $query->fetchAll(PDO::FETCH_ASSOC);
        });
    }

    public function getActiveSession(string $userId): ?array
    {
        return $this->withUserContext($userId, function () {
            $query = $this->database->connect()->prepare(
                'SELECT id, started_at, plan_id
                 FROM workout_sessions
                 WHERE ended_at IS NULL
                 ORDER BY started_at DESC
                 LIMIT 1'
            );
            $query->execute();
            $row = $query->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        });
    }

    public function startSession(string $userId, ?string $planId): string
    {
        return $this->withUserContext($userId, function () use ($userId, $planId) {
            $query = $this->database->connect()->prepare(
                'INSERT INTO workout_sessions (user_id, plan_id)
                 VALUES (?, ?)
                 RETURNING id'
            );
            $query->execute([$userId, $planId]);
            return $query->fetchColumn();
        });
    }

    public function endSession(string $userId, string $sessionId): void
    {
        $this->withUserContext($userId, function () use ($sessionId) {
            $query = $this->database->connect()->prepare(
                'UPDATE workout_sessions SET ended_at = NOW() WHERE id = ?'
            );
            $query->execute([$sessionId]);
        });
    }
}
