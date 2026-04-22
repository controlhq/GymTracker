<?php

require_once 'Repository.php';

class SessionsRepository extends Repository
{
    public function getSessionsForUser(string $userId): array
    {
        return $this->withUserContext($userId, function () {
            $query = $this->database->connect()->prepare(
                'SELECT s.id, s.name, s.status, s.started_at, s.ended_at,
                        wp.name AS plan_name
                 FROM sessions s
                 LEFT JOIN workout_plans wp ON wp.id = s.source_plan_id
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
                "SELECT id, name, started_at, source_plan_id
                 FROM sessions
                 WHERE status = 'in_progress'
                 LIMIT 1"
            );
            $query->execute();
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

    public function endSession(string $userId, string $sessionId): void
    {
        $this->withUserContext($userId, function () use ($sessionId) {
            $query = $this->database->connect()->prepare(
                "UPDATE sessions SET status = 'completed', ended_at = NOW() WHERE id = ?"
            );
            $query->execute([$sessionId]);
        });
    }
}
