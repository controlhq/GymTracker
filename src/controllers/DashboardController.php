<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repositories/SessionsRepository.php';
require_once __DIR__ . '/../repositories/SessionExercisesRepository.php';

class DashboardController extends AppController {

    private SessionsRepository $sessionsRepository;
    private SessionExercisesRepository $sessionExercisesRepository;

    public function __construct()
    {
        $this->sessionsRepository         = new SessionsRepository();
        $this->sessionExercisesRepository = new SessionExercisesRepository();
    }

    public function index(?string $p1 = null, ?string $p2 = null): void
    {
        $this->requireLogin();
        $this->render('dashboard', [
            'displayName' => $_SESSION['user_display_name'],
        ]);
    }

    public function apiActiveSession(?string $p1 = null, ?string $p2 = null): void
    {
        $this->requireLogin();
        header('Content-Type: application/json');

        $userId  = $_SESSION['user_id'];
        $session = $this->sessionsRepository->getActiveSessionWithPlan($userId);

        if (!$session) {
            echo json_encode(['active' => false]);
            return;
        }

        // Normalize timestamp to ISO 8601 for JS Date parsing
        if (!empty($session['started_at'])) {
            $session['started_at'] = (new DateTime($session['started_at']))->format('c');
        }

        $exercises = $this->sessionExercisesRepository->getExercisesForSession($userId, $session['id']);
        $exercisesWithSets = [];
        foreach ($exercises as $ex) {
            $sets = $this->sessionExercisesRepository->getSetsForSessionExercise($userId, $ex['id']);
            $exercisesWithSets[] = array_merge($ex, ['sets' => $sets]);
        }
        $session['exercises'] = $exercisesWithSets;

        echo json_encode(['active' => true, 'session' => $session]);
    }
}
