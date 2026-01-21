<?php
declare(strict_types=1);

require_once __DIR__ . '/../../core/require_login.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../server/models/UserModel.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

function flash_profile(array $error = [], array $old = [], array $fieldErrors = []): void
{
    if (!empty($error))   { $_SESSION['profile_error'] = $error; }
    if (!empty($old)) { $_SESSION['profile_field_old'] = $old; }
    if (!empty($fieldErrors)) { $_SESSION['profile_field_errors'] = $fieldErrors; }

    header('Location: ../profile-settings.php');
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    flash_profile('auth');
}

$email = (string)($_POST['email'] ?? '');
$email = strtolower(trim($email));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash_profile(
        ['email' => 'invalid'], 
        ['email' => $email], 
        ['email' => 'Невалиден имейл адрес.']);
}

$userModel = new UserModel($pdo);

try {
    $userModel->updateEmail($userId, $email);

    // синхронизираме сесията за UI
    $_SESSION['user_email'] = $email;

    flash_profile(
        ['email' => 'success'], 
        ['email' => $email]);
} catch (RuntimeException $e) {
    // напр. Email already exists.
    flash_profile(
        ['email' => 'exists'], 
        ['email' => $email], 
        ['email' => 'Този имейл вече е зает.']);
} catch (InvalidArgumentException $e) {
     flash_profile(
        ['email' => 'invalid'], 
        ['email' => $email], 
        ['email' => 'Невалиден имейл адрес.']);
} catch (Throwable $e) {
    flash_profile(['email' => 'server']);
}