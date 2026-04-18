<?php

require_once 'AppController.php';

class AnalyticsController extends AppController
{
    public function index(): void
    {
        $this->requireLogin();
        $this->render('analytics');
    }
}
