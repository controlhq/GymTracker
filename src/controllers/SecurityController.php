<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repositories/UsersRepository.php';

class SecurityController extends AppController
{
    private UsersRepository $userRepository;

    public function __construct()
    {
        $this->userRepository = new UsersRepository();
    }

    public function login()
    {
        if (!$this->isPost()) {
            return $this->render('login');
        }

        $email    = $_POST['email']    ?? '';
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            return $this->render('login', ['messages' => 'Fill all fields']);
        }

        $user = $this->userRepository->getUserByEmail($email);

        if (!$user) {
            return $this->render('login', ['messages' => 'User not found']);
        }

        if (!password_verify($password, $user->getPassword())) {
            return $this->render('login', ['messages' => 'Wrong password']);
        }

        session_regenerate_id(true);
        $_SESSION['user_id']       = $user->getId();
        $_SESSION['user_email']    = $user->getEmail();
        $_SESSION['user_username'] = $user->getUsername();
        $_SESSION['is_logged_in']  = true;

        $url = "http://$_SERVER[HTTP_HOST]";
        header("Location: {$url}/dashboard");
    }

    public function register()
    {
        if (!$this->isPost()) {
            return $this->render('register');
        }

        $email     = trim($_POST['email']     ?? '');
        $password  = $_POST['password']       ?? '';
        $password2 = $_POST['password2']      ?? '';
        $username  = $_POST['username']       ?? '';

        if (empty($email) || empty($password) || empty($username)) {
            return $this->render('register', ['messages' => 'Fill all fields']);
        }

        // TODO: compare password === password2
        // if ($password !== $password2) {
        //     return $this->render('register', ['messages' => 'Passwords do not match']);
        // }

        $user = $this->userRepository->getUserByEmail($email);
        if ($user) {
            return $this->render('register', ['messages' => 'User exists']);
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $this->userRepository->createUser($email, $hashedPassword, $username);

        $url = "http://$_SERVER[HTTP_HOST]";
        header("Location: {$url}/login");
    }

    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();

        $url = "http://$_SERVER[HTTP_HOST]";
        header("Location: {$url}/login");
    }
}
