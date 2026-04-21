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

    public function index(): void
    {
        $this->requireLogin();

        $plans = $this->plansRepository->getPlansForUser($_SESSION['user_id']);

        $this->render('workouts', [
            'plans'       => $plans,
            'displayName' => $_SESSION['user_display_name'],
        ]);
    }

    public function create(): void
    {
        $this->requireLogin();

        if (!$this->isPost()) {
            return $this->render('workouts-create', [
                'displayName' => $_SESSION['user_display_name'],
            ]);
        }

        $name        = trim($_POST['name']        ?? '');
        $description = trim($_POST['description'] ?? '');
        $status      = $_POST['status']           ?? 'draft';
        $intensity   = $_POST['intensity']        ?? 'medium';
        $durationMin = isset($_POST['duration_min']) && $_POST['duration_min'] !== ''
                       ? (int) $_POST['duration_min']
                       : null;

        if (empty($name)) {
            return $this->render('workouts-create', [
                'displayName' => $_SESSION['user_display_name'],
                'messages'    => 'Plan name is required',
            ]);
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

    public function detail(?string $planId): void
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
