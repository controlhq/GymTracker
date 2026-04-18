<?php

require_once 'AppController.php';

class ExercisesController extends AppController
{
    public function index(): void
    {
        $this->requireLogin();
        $this->render('exercises');
    }
}
