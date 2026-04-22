<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repositories/WorkoutPlansRepository.php';
require_once __DIR__ . '/../repositories/PlanExercisesRepository.php';
require_once __DIR__ . '/../repositories/ExercisesRepository.php';

class WorkoutsController extends AppController
{
    private WorkoutPlansRepository $plansRepository;
    private PlanExercisesRepository $planExercisesRepository;
    private ExercisesRepository $exercisesRepository;

    public function __construct()
    {
        $this->plansRepository         = new WorkoutPlansRepository();
        $this->planExercisesRepository = new PlanExercisesRepository();
        $this->exercisesRepository     = new ExercisesRepository();
    }

    public function index(?string $p1 = null, ?string $p2 = null): void
    {
        $this->requireLogin();

        $plans = $this->plansRepository->getPlansForUser($_SESSION['user_id']);

        $this->render('workouts', [
            'plans'       => $plans,
            'displayName' => $_SESSION['user_display_name'],
        ]);
    }

    public function create(?string $p1 = null, ?string $p2 = null): void
    {
        $this->requireLogin();

        if (!$this->isPost()) {
            $this->render('workouts-create', [
                'displayName' => $_SESSION['user_display_name'],
            ]);
            return;
        }

        $name        = trim($_POST['name']        ?? '');
        $description = trim($_POST['description'] ?? '');
        $status      = $_POST['status']    ?? 'draft';
        $intensity   = isset($_POST['intensity']) && $_POST['intensity'] !== ''
                       ? (int) $_POST['intensity']
                       : null;
        $durationMin = isset($_POST['duration_min']) && $_POST['duration_min'] !== ''
                       ? (int) $_POST['duration_min']
                       : null;

        if (empty($name)) {
            $this->render('workouts-create', [
                'displayName' => $_SESSION['user_display_name'],
                'messages'    => 'Plan name is required',
            ]);
            return;
        }

        $this->plansRepository->createPlan(
            $_SESSION['user_id'],
            $name,
            $description,
            $status,
            $intensity,
            $durationMin
        );

        $url = "http://$_SERVER[HTTP_HOST]";
        header("Location: {$url}/workouts");
    }

    public function handleExercise(?string $planId, ?string $action): void
    {
        $this->requireLogin();

        if (!$planId) {
            include 'public/views/404.html';
            return;
        }

        $userId = $_SESSION['user_id'];
        $url    = "http://$_SERVER[HTTP_HOST]";

        if ($action === 'add-exercise') {
            $exerciseId = $_POST['exercise_id'] ?? '';
            $sets       = isset($_POST['sets'])     && $_POST['sets']     !== '' ? (int)$_POST['sets']     : null;
            $reps       = isset($_POST['reps'])     && $_POST['reps']     !== '' ? (int)$_POST['reps']     : null;
            $restSec    = isset($_POST['rest_sec']) && $_POST['rest_sec'] !== '' ? (int)$_POST['rest_sec'] : null;

            if (!empty($exerciseId)) {
                $this->planExercisesRepository->addExerciseToPlan($userId, $planId, $exerciseId, $sets, $reps, $restSec);
            }
        } elseif ($action === 'remove-exercise') {
            $planExerciseId = $_POST['plan_exercise_id'] ?? '';

            if (!empty($planExerciseId)) {
                $this->planExercisesRepository->removeExerciseFromPlan($userId, $planExerciseId);
            }
        }

        header("Location: {$url}/workouts/{$planId}");
    }

    public function detail(?string $planId, ?string $p2 = null): void
    {
        $this->requireLogin();

        if (!$planId) {
            include 'public/views/404.html';
            return;
        }

        $userId = $_SESSION['user_id'];
        $plan   = $this->plansRepository->getPlanById($userId, $planId);

        if (!$plan) {
            include 'public/views/404.html';
            return;
        }

        $planExercises     = $this->planExercisesRepository->getExercisesForPlan($userId, $planId);
        $allExercises      = $this->exercisesRepository->getAll();

        $this->render('workout-detail', [
            'plan'          => $plan,
            'planExercises' => $planExercises,
            'allExercises'  => $allExercises,
            'displayName'   => $_SESSION['user_display_name'],
        ]);
    }
}
