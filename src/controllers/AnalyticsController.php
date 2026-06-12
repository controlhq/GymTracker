<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repositories/SessionsRepository.php';
require_once __DIR__ . '/../repositories/SessionExercisesRepository.php';

class AnalyticsController extends AppController
{
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

        $userId   = $_SESSION['user_id'];
        $sessions = $this->sessionsRepository->getSessionsForUser($userId);

        $this->render('analytics', [
            'sessions'       => $sessions,
            'displayName'    => $_SESSION['user_display_name'],
            'weeklyFreq'     => $this->sessionsRepository->getWeeklyFrequency($userId),
            'muscleVolume'   => $this->sessionsRepository->getMuscleGroupVolume($userId),
            'lifetime'       => $this->sessionsRepository->getLifetimeStats($userId),
        ]);
    }

    public function activeSession(?string $sessionId = null, ?string $p2 = null): void
    {
        // Live tracking lives on the dashboard; keep this URL working for old links.
        $this->requireLogin();
        $url = "http://$_SERVER[HTTP_HOST]";
        header("Location: {$url}/dashboard");
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

        if ($action === 'delete') {
            $this->sessionsRepository->deleteSession($userId, $sessionId);
            header("Location: {$url}/analytics");
            return;
        }

        if ($action === 'add-exercise') {
            $exerciseId   = $_POST['exercise_id']   ?? '';
            $exerciseName = $_POST['exercise_name'] ?? '';
            if (!empty($exerciseId) && !empty($exerciseName)) {
                $this->sessionExercisesRepository->addExerciseToSession($userId, $sessionId, $exerciseId, $exerciseName);
            }
            http_response_code(204);
            return;
        }

        if ($action === 'save-set') {
            $setId     = $_POST['set_id'] ?? '';
            $weightKg  = isset($_POST['weight_kg']) && $_POST['weight_kg'] !== '' ? (float)$_POST['weight_kg'] : null;
            $reps      = isset($_POST['reps'])      && $_POST['reps']      !== '' ? (int)$_POST['reps']      : null;
            $completed = !empty($_POST['is_completed']) && $_POST['is_completed'] !== '0' && $_POST['is_completed'] !== 'false';

            if (!empty($setId)) {
                $this->sessionExercisesRepository->updateSet($userId, $setId, $weightKg, $reps, $completed);
            }
            http_response_code(204);
            return;
        }

        if ($action === 'finish-exercise') {
            $sessionExerciseId = $_POST['session_exercise_id'] ?? '';
            if (!empty($sessionExerciseId)) {
                $this->sessionExercisesRepository->finishExercise($userId, $sessionExerciseId);
            }
            http_response_code(204);
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
            http_response_code(204);
            return;
        }

        http_response_code(204);
    }
}
