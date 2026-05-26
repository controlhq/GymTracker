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
        $csrfToken = $this->generateCsrfToken();

        if (!$this->isPost()) {
            return $this->render('login', ['csrf_token' => $csrfToken]);
        }

        if (!$this->validateCsrfToken()) {
            http_response_code(403);
            return $this->render('login', ['messages' => 'Nieprawidłowe żądanie.', 'csrf_token' => $csrfToken]);
        }

        $email    = trim($_POST['email']    ?? '');
        $password = $_POST['password']      ?? '';

        if (empty($email) || empty($password)) {
            http_response_code(400);
            return $this->render('login', ['messages' => 'Wypełnij wszystkie pola.', 'csrf_token' => $csrfToken]);
        }

        if (strlen($email) > 254 || strlen($password) > 72) {
            http_response_code(400);
            return $this->render('login', ['messages' => 'Nieprawidłowe dane wejściowe.', 'csrf_token' => $csrfToken]);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            return $this->render('login', ['messages' => 'Nieprawidłowy format adresu email.', 'csrf_token' => $csrfToken]);
        }

        $user = $this->userRepository->findForAuth($email);

        if (!$user || !$user->isActive() || !password_verify($password, $user->getPasswordHash())) {
            error_log("[AUTH_FAIL] email={$email} ip={$_SERVER['REMOTE_ADDR']}");
            http_response_code(401);
            return $this->render('login', ['messages' => 'Email lub hasło niepoprawne.', 'csrf_token' => $csrfToken]);
        }

        session_regenerate_id(true);
        $_SESSION['user_id']           = $user->getId();
        $_SESSION['user_email']        = $user->getEmail();
        $_SESSION['user_display_name'] = $user->getDisplayName();
        $_SESSION['is_logged_in']      = true;

        $url = "http://$_SERVER[HTTP_HOST]";
        header("Location: {$url}/dashboard");
    }

    public function register()
    {
        $csrfToken = $this->generateCsrfToken();

        if (!$this->isPost()) {
            return $this->render('register', ['csrf_token' => $csrfToken]);
        }

        if (!$this->validateCsrfToken()) {
            http_response_code(403);
            return $this->render('register', ['messages' => 'Nieprawidłowe żądanie.', 'csrf_token' => $csrfToken]);
        }

        $email       = trim($_POST['email']        ?? '');
        $password    = $_POST['password']          ?? '';
        $password2   = $_POST['password2']         ?? '';
        $displayName = trim($_POST['display_name'] ?? '');

        if (empty($email) || empty($password) || empty($displayName)) {
            http_response_code(400);
            return $this->render('register', ['messages' => 'Wypełnij wszystkie pola.', 'csrf_token' => $csrfToken]);
        }

        if (strlen($email) > 254) {
            http_response_code(400);
            return $this->render('register', ['messages' => 'Adres email jest zbyt długi.', 'csrf_token' => $csrfToken]);
        }

        if (strlen($password) > 72) {
            http_response_code(400);
            return $this->render('register', ['messages' => 'Hasło jest zbyt długie (max 72 znaki).', 'csrf_token' => $csrfToken]);
        }

        if (strlen($displayName) > 100) {
            http_response_code(400);
            return $this->render('register', ['messages' => 'Nazwa wyświetlana jest zbyt długa (max 100 znaków).', 'csrf_token' => $csrfToken]);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            return $this->render('register', ['messages' => 'Nieprawidłowy format adresu email.', 'csrf_token' => $csrfToken]);
        }

        if (strlen($password) < 8) {
            http_response_code(400);
            return $this->render('register', ['messages' => 'Hasło musi mieć co najmniej 8 znaków.', 'csrf_token' => $csrfToken]);
        }

        if ($password !== $password2) {
            http_response_code(400);
            return $this->render('register', ['messages' => 'Hasła nie są zgodne.', 'csrf_token' => $csrfToken]);
        }

        $user = $this->userRepository->findByEmail($email);
        if ($user) {
            http_response_code(400);
            return $this->render('register', ['messages' => 'Konto z tym adresem email już istnieje.', 'csrf_token' => $csrfToken]);
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $this->userRepository->createUser($email, $hashedPassword, $displayName);

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
