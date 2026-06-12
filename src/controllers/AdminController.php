<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repositories/AdminRepository.php';

class AdminController extends AppController
{
    private AdminRepository $adminRepository;

    public function __construct()
    {
        $this->adminRepository = new AdminRepository();
    }

    public function index(?string $p1 = null, ?string $p2 = null): void
    {
        $this->requireAdmin();
        $this->render('admin-stats', [
            'displayName'   => $_SESSION['user_display_name'],
            'users'         => $this->adminRepository->getUserStats(),
            'currentUserId' => $_SESSION['user_id'],
            'csrf_token'    => $this->generateCsrfToken(),
        ]);
    }

    public function handleUser(?string $userId = null, ?string $action = null): void
    {
        $this->requireAdmin();

        if (!$this->isPost() || !$this->validateCsrfToken()) {
            http_response_code(403);
            return;
        }

        // Nie pozwalamy adminowi zbanować samego siebie (ochrona innych adminów jest w SQL).
        if ($userId !== $_SESSION['user_id']) {
            $this->adminRepository->setUserActive($userId, $action === 'unban');
        }

        $url = "http://$_SERVER[HTTP_HOST]";
        header("Location: {$url}/admin/stats");
    }
}
