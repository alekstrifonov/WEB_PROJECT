<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../server/models/UserModel.php';

function flash_back(string $error, array $old = []): void
{
    $_SESSION['login_error'] = $error;
    $_SESSION['login_old'] = [
        'email' => $old['email'] ?? '',
    ];

    header('Location: ../login.php');
    exit;
}

$email    = (string)($_POST['email'] ?? '');
$password = (string)($_POST['password'] ?? '');

$emailTrim = trim($email);

if ($emailTrim === '' || $password === '') {
    flash_back('Моля, въведете имейл и парола.', [
        'email' => $emailTrim,
    ]);
}

$userModel = new UserModel($pdo);

try {
    $user = $userModel->verifyLogin($emailTrim, $password);

    if (!$user) {
        flash_back('Потребител с такъв имейл или парола не съществува.', [
            'email' => $emailTrim,
        ]);
    }

    session_regenerate_id(true);

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];

    header('Location: ../dashboard.php');
    exit;

} catch (Throwable $e) {
    flash_back('Възникна сървърна грешка. Опитайте отново.', [
        'email' => $emailTrim,
    ]);
}

?>
