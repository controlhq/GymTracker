<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repositories/ExercisesRepository.php';

class ExercisesController extends AppController
{
    private ExercisesRepository $exercisesRepository;

    public function __construct()
    {
        $this->exercisesRepository = new ExercisesRepository();
    }

    public function index(): void
    {
        $this->requireLogin();

        $exercises = $this->exercisesRepository->getAll();

        $this->render('exercises', [
            'exercises'   => $exercises,
            'displayName' => $_SESSION['user_display_name'],
        ]);
    }
}
