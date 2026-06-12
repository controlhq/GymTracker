<?php

require_once 'Repository.php';

class WorkoutPlansRepository extends Repository
{
    public function getPlansForUser(string $userId): array
    {
        return $this->withUserContext($userId, function () use ($userId) {
            $query = $this->database->connect()->prepare(
                'SELECT id, name, description, status, intensity, duration_min, created_at
                 FROM workout_plans
                 WHERE user_id = ?
                 ORDER BY created_at DESC'
            );
            $query->execute([$userId]);
            return $query->fetchAll(PDO::FETCH_ASSOC);
        });
    }

    public function createPlan(string $userId, string $name, string $description, string $status, ?int $intensity, ?int $durationMin): string
    {
        return $this->withUserContext($userId, function () use ($userId, $name, $description, $status, $intensity, $durationMin) {
            $query = $this->database->connect()->prepare(
                'INSERT INTO workout_plans (user_id, name, description, status, intensity, duration_min)
                 VALUES (?, ?, ?, ?, ?, ?)
                 RETURNING id'
            );
            $query->execute([$userId, $name, $description, $status, $intensity, $durationMin]);
            return $query->fetchColumn();
        });
    }

    public function getPlanById(string $userId, string $planId): ?array
    {
        return $this->withUserContext($userId, function () use ($userId, $planId) {
            $query = $this->database->connect()->prepare(
                'SELECT id, name, description, status, intensity, duration_min, created_at
                 FROM workout_plans
                 WHERE id = ? AND user_id = ?'
            );
            $query->execute([$planId, $userId]);
            $row = $query->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        });
    }

    public function deletePlan(string $userId, string $planId): void
    {
        $this->withUserContext($userId, function () use ($userId, $planId) {
            $query = $this->database->connect()->prepare(
                'DELETE FROM workout_plans WHERE id = ? AND user_id = ?'
            );
            $query->execute([$planId, $userId]);
        });
    }

    public function updatePlanStatus(string $userId, string $planId, string $status): void
    {
        $this->withUserContext($userId, function () use ($userId, $planId, $status) {
            $pdo = $this->database->connect();

            // Only one plan may be 'active' per user (DB enforces this with a unique
            // index); demote the current active plan first so switching never fails.
            if ($status === 'active') {
                $demote = $pdo->prepare(
                    "UPDATE workout_plans SET status = 'routine'
                     WHERE user_id = ? AND status = 'active' AND id <> ?"
                );
                $demote->execute([$userId, $planId]);
            }

            $query = $pdo->prepare(
                'UPDATE workout_plans SET status = ? WHERE id = ? AND user_id = ?'
            );
            $query->execute([$status, $planId, $userId]);
        });
    }
}
