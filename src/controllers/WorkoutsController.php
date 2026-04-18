<?php

require_once 'AppController.php';

class WorkoutsController extends AppController
{
    public function index(): void
    {
        $this->requireLogin();
        $this->render('workouts');
    }
}
