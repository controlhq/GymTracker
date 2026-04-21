<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repositories/SessionsRepository.php';
require_once __DIR__ . '/../repositories/WorkoutPlansRepository.php';

class DashboardController extends AppController {

    private SessionsRepository $sessionsRepository;
    private WorkoutPlansRepository $plansRepository;

    public function __construct()
    {
        $this->sessionsRepository = new SessionsRepository();
        $this->plansRepository    = new WorkoutPlansRepository();
    }

    public function index(?string $p1 = null, ?string $p2 = null) {
        $this->requireLogin();

        $userId        = $_SESSION['user_id'];
        $activeSession = $this->sessionsRepository->getActiveSession($userId);
        $plans         = $this->plansRepository->getPlansForUser($userId);

        return $this->render('dashboard', [
            'displayName'   => $_SESSION['user_display_name'],
            'activeSession' => $activeSession,
            'planCount'     => count($plans),
        ]);
    }
}
