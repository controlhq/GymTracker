<?php

require_once 'Repository.php';

class WorkoutPlansRepository extends Repository
{
    public function getPlansForUser(string $userId): array
    {
        return $this->withUserContext($userId, function () {
            $query = $this->database->connect()->prepare(
                'SELECT id, name, description, status, intensity, duration_min, created_at
                 FROM workout_plans
                 ORDER BY created_at DESC'
            );
            $query->execute();
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
        return $this->withUserContext($userId, function () use ($planId) {
            $query = $this->database->connect()->prepare(
                'SELECT id, name, description, status, intensity, duration_min, created_at
                 FROM workout_plans
                 WHERE id = ?'
            );
            $query->execute([$planId]);
            $row = $query->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        });
    }

    public function deletePlan(string $userId, string $planId): void
    {
        $this->withUserContext($userId, function () use ($planId) {
            $query = $this->database->connect()->prepare(
                'DELETE FROM workout_plans WHERE id = ?'
            );
            $query->execute([$planId]);
        });
    }

    public function updatePlanStatus(string $userId, string $planId, string $status): void
    {
        $this->withUserContext($userId, function () use ($planId, $status) {
            $query = $this->database->connect()->prepare(
                'UPDATE workout_plans SET status = ? WHERE id = ?'
            );
            $query->execute([$status, $planId]);
        });
    }
}
