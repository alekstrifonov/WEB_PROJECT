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

$name = (string)($_POST['name'] ?? '');
$name = trim($name);
$name = preg_replace('/\s+/u', ' ', $name);

if ($name === '') {
    flash_profile(
        ['name' => 'invalid'], 
        ['name' => ''], 
        ['name' => 'Въведете име.']);
}

$userModel = new UserModel($pdo);

try {
    $userModel->updateName($userId, $name);

    // синхронизираме сесията за UI
    $_SESSION['user_name'] = $name;

    flash_profile(
        ['name' => 'success'], 
        ['name' => $name]);
} catch (InvalidArgumentException $e) {
    flash_profile(
        ['name' => 'invalid'], 
        ['name' => $name], 
        ['name' => 'Невалидно име: името може да състои само от букви и интервали, и с дължина от 2 до 100 символа.']);
} catch (Throwable $e) {
    flash_profile(['name' => 'server']);
}

?>