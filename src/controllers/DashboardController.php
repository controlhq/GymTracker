<?php

require_once 'AppController.php';

class DashboardController extends AppController {

    public function index() {
        $this->requireLogin();

        return $this->render('dashboard', [
            'displayName' => $_SESSION['user_display_name']
        ]);
    }
}
