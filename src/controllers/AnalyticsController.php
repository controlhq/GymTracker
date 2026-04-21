<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repositories/SessionsRepository.php';

class AnalyticsController extends AppController
{
    private SessionsRepository $sessionsRepository;

    public function __construct()
    {
        $this->sessionsRepository = new SessionsRepository();
    }

    public function index(?string $p1 = null, ?string $p2 = null): void
    {
        $this->requireLogin();

        $userId        = $_SESSION['user_id'];
        $sessions      = $this->sessionsRepository->getSessionsForUser($userId);
        $activeSession = $this->sessionsRepository->getActiveSession($userId);

        $this->render('analytics', [
            'sessions'      => $sessions,
            'activeSession' => $activeSession,
            'displayName'   => $_SESSION['user_display_name'],
        ]);
    }
}
