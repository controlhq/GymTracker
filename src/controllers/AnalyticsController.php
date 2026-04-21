<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repositories/SessionsRepository.php';
require_once __DIR__ . '/../repositories/SessionExercisesRepository.php';
require_once __DIR__ . '/../repositories/ExercisesRepository.php';

class AnalyticsController extends AppController
{
    private SessionsRepository $sessionsRepository;
    private SessionExercisesRepository $sessionExercisesRepository;
    private ExercisesRepository $exercisesRepository;

    public function __construct()
    {
        $this->sessionsRepository         = new SessionsRepository();
        $this->sessionExercisesRepository = new SessionExercisesRepository();
        $this->exercisesRepository        = new ExercisesRepository();
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

    public function startSession(?string $p1 = null, ?string $p2 = null): void
    {
        $this->requireLogin();

        $userId    = $_SESSION['user_id'];
        $sessionId = $this->sessionsRepository->startSession($userId, null);

        $url = "http://$_SERVER[HTTP_HOST]";
        header("Location: {$url}/analytics/session/{$sessionId}");
    }

    public function activeSession(?string $sessionId = null, ?string $p2 = null): void
    {
        $this->requireLogin();

        if (!$sessionId) {
            include 'public/views/404.html';
            return;
        }

        $userId            = $_SESSION['user_id'];
        $sessionExercises  = $this->sessionExercisesRepository->getExercisesForSession($userId, $sessionId);
        $allExercises      = $this->exercisesRepository->getAll();

        $exercisesWithSets = [];
        foreach ($sessionExercises as $se) {
            $sets = $this->sessionExercisesRepository->getSetsForSessionExercise($userId, $se['id']);
            $exercisesWithSets[] = array_merge($se, ['sets' => $sets]);
        }

        $this->render('session-active', [
            'sessionId'    => $sessionId,
            'exercises'    => $exercisesWithSets,
            'allExercises' => $allExercises,
            'displayName'  => $_SESSION['user_display_name'],
        ]);
    }

    public function handleSession(?string $sessionId = null, ?string $action = null): void
    {
        $this->requireLogin();

        if (!$sessionId) {
            include 'public/views/404.html';
            return;
        }

        $userId = $_SESSION['user_id'];
        $url    = "http://$_SERVER[HTTP_HOST]";

        if ($action === 'end') {
            $this->sessionsRepository->endSession($userId, $sessionId);
            header("Location: {$url}/analytics");
            return;
        }

        if ($action === 'add-exercise') {
            $exerciseId = $_POST['exercise_id'] ?? '';
            if (!empty($exerciseId)) {
                $this->sessionExercisesRepository->addExerciseToSession($userId, $sessionId, $exerciseId);
            }
            header("Location: {$url}/analytics/session/{$sessionId}");
            return;
        }

        if ($action === 'log-set') {
            $sessionExerciseId = $_POST['session_exercise_id'] ?? '';
            $setNumber         = isset($_POST['set_number']) ? (int)$_POST['set_number'] : 1;
            $weightKg          = isset($_POST['weight_kg'])  && $_POST['weight_kg']  !== '' ? (float)$_POST['weight_kg']  : null;
            $reps              = isset($_POST['reps'])        && $_POST['reps']        !== '' ? (int)$_POST['reps']        : null;
            $rpe               = isset($_POST['rpe'])         && $_POST['rpe']         !== '' ? (int)$_POST['rpe']         : null;

            if (!empty($sessionExerciseId)) {
                $this->sessionExercisesRepository->logSet($userId, $sessionExerciseId, $setNumber, $weightKg, $reps, $rpe);
            }
            header("Location: {$url}/analytics/session/{$sessionId}");
            return;
        }

        header("Location: {$url}/analytics/session/{$sessionId}");
    }
}
