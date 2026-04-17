<?php

require_once 'AppController.php';
require_once __DIR__.'/../repositories/UsersRepository.php';

class DashboardController extends AppController {

    public function index() {
        $this->requireLogin();

        $title = "INDEX";
        $usersRepository = new UsersRepository();
        $users = $usersRepository->getUsers();

        return $this->render("index", ["title" => $title, "users" => $users]);
    }
}