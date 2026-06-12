<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repositories/SessionsRepository.php';
require_once __DIR__ . '/../repositories/SessionExercisesRepository.php';
require_once __DIR__ . '/../repositories/WorkoutPlansRepository.php';
require_once __DIR__ . '/../repositories/PlanExercisesRepository.php';
require_once __DIR__ . '/../repositories/ExercisesRepository.php';

class DashboardController extends AppController {

    private SessionsRepository $sessionsRepository;
    private SessionExercisesRepository $sessionExercisesRepository;
    private WorkoutPlansRepository $workoutPlansRepository;
    private PlanExercisesRepository $planExercisesRepository;
    private ExercisesRepository $exercisesRepository;

    public function __construct()
    {
        $this->sessionsRepository         = new SessionsRepository();
        $this->sessionExercisesRepository = new SessionExercisesRepository();
        $this->workoutPlansRepository     = new WorkoutPlansRepository();
        $this->planExercisesRepository    = new PlanExercisesRepository();
        $this->exercisesRepository        = new ExercisesRepository();
    }

    public function index(?string $p1 = null, ?string $p2 = null): void
    {
        $this->requireLogin();

        $userId = $_SESSION['user_id'];

        $this->render('dashboard', [
            'displayName'    => $_SESSION['user_display_name'],
            'todayStats'     => $this->sessionsRepository->getTodayStats($userId),
            'recentSessions' => array_slice($this->sessionsRepository->getSessionsForUser($userId), 0, 3),
        ]);
    }

    public function startSession(?string $p1 = null, ?string $p2 = null): void
    {
        $this->requireLogin();

        $userId = $_SESSION['user_id'];
        $url    = "http://$_SERVER[HTTP_HOST]";

        if (!$this->isPost()) {
            $plans = $this->workoutPlansRepository->getPlansForUser($userId);
            foreach ($plans as &$plan) {
                $plan['exercises'] = $this->planExercisesRepository->getExercisesForPlan($userId, $plan['id']);
            }
            unset($plan);

            $this->render('session-start', [
                'plans'       => $plans,
                'displayName' => $_SESSION['user_display_name'],
                'csrf_token'  => $this->generateCsrfToken(),
            ]);
            return;
        }

        if (!$this->validateCsrfToken()) {
            http_response_code(403);
            return;
        }

        // Only one active session is allowed per user — resume it on the dashboard instead of starting another.
        $active = $this->sessionsRepository->getActiveSession($userId);
        if ($active) {
            header("Location: {$url}/dashboard");
            return;
        }

        $planId = ($_POST['plan_id'] ?? '') !== '' ? $_POST['plan_id'] : null;
        $name   = date('Y-m-d H:i') . ' session';

        if ($planId !== null) {
            $plan = $this->workoutPlansRepository->getPlanById($userId, $planId);
            if (!$plan) {
                $planId = null; // not owned / not found → fall back to a free session
            } else {
                $name = $plan['name'];
            }
        }

        $sessionId = $this->sessionsRepository->startSession($userId, $name, $planId);

        if ($planId !== null) {
            $this->sessionExercisesRepository->copyExercisesFromPlan($userId, $sessionId, $planId);
        }

        header("Location: {$url}/dashboard");
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

            // Attach what was lifted last time for this exercise (per set number).
            $previous = $this->sessionExercisesRepository->getPreviousSetsForExercise($userId, $ex['exercise_id'] ?? null, $session['id']);
            $previousMap = [];
            foreach ($previous as $p) {
                $previousMap[(int)$p['set_number']] = $p;
            }
            foreach ($sets as &$set) {
                $prev = $previousMap[(int)$set['set_number']] ?? null;
                $set['previous_weight'] = $prev['weight_kg'] ?? null;
                $set['previous_reps']   = $prev['reps'] ?? null;
            }
            unset($set);

            $exercisesWithSets[] = array_merge($ex, ['sets' => $sets]);
        }
        $session['exercises'] = $exercisesWithSets;

        echo json_encode([
            'active'    => true,
            'session'   => $session,
            'catalogue' => $this->exercisesRepository->getAll(),
        ]);
    }
}
