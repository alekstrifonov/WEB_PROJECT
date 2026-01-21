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

$password  = (string)($_POST['password'] ?? '');
$password2 = (string)($_POST['password2'] ?? '');

if ($password === '') {
    flash_profile(
        ['password' => 'invalid'],
        ['password' => ''], 
        ['password' => 'Моля, въведете парола.']);
}
// Политика (същата логика като преди; коригирай ако искаш)
if (strlen($password) < 6 || strlen($password) > 200) {
    flash_profile(
        ['password' => 'weak'], 
        ['password' => ''], 
        ['password' => 'Паролата трябва да е от 6 до 200 символа.']);
}

if ($password2 === '') {
    flash_profile(
        ['password2' => 'invalid'],
        ['password2' => ''], 
        ['password2' => 'Моля, потвърдете паролата.']);
}

if ($password !== $password2) {
    flash_profile(
        ['password2' => 'mismatch'], 
        ['password2' => ''], 
        ['password2' => 'Паролите не съвпадат.']);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
if ($hash === false) {
    flash_profile(['password' => 'server']);
}

$userModel = new UserModel($pdo);

try {
    $userModel->updatePasswordHash($userId, $hash);

    flash_profile(['password2' => 'success']);
} catch (Throwable $e) {
     flash_profile(['password' => 'server']);
}

?>