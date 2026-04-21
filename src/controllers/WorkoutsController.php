<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repositories/WorkoutPlansRepository.php';

class WorkoutsController extends AppController
{
    private WorkoutPlansRepository $plansRepository;

    public function __construct()
    {
        $this->plansRepository = new WorkoutPlansRepository();
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
}
