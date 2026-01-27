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

function flash_back(string $generalError, array $fieldErrors, array $old): void
{
    $_SESSION['register_error'] = $generalError;
    $_SESSION['register_field_errors'] = $fieldErrors;
    $_SESSION['register_old'] = [
        'name'  => $old['name'] ?? '',
        'email' => $old['email'] ?? '',
    ];

    header('Location: ../register.php');
    exit;
}

function normalize_email_basic(string $email): string
{
    return strtolower(trim($email));
}

$name      = (string)($_POST['name'] ?? '');
$email     = (string)($_POST['email'] ?? '');
$password  = (string)($_POST['password'] ?? '');
$password2 = (string)($_POST['password2'] ?? '');

$fieldErrors = [];
$nameTrim = trim($name);
$emailNorm = normalize_email_basic($email);

if ($nameTrim === '') {
    $fieldErrors['name'] = 'Въведете име.';
} elseif (!(bool)preg_match('/^[\p{Latin}\p{Cyrillic} ]+$/u', $name)) {
    $fieldErrors['name'] = 'Името може да състои само от букви и интервали.';
} elseif (strlen($nameTrim) < 2 || strlen($nameTrim) > 100) {
    $fieldErrors['name'] = 'Името трябва да е между 2 и 100 символа.';
}

if ($emailNorm === '') {
    $fieldErrors['email'] = 'Въведете имейл.';
} elseif (!filter_var($emailNorm, FILTER_VALIDATE_EMAIL)) {
   $fieldErrors['email'] = 'Невалиден имейл адрес.';
}

if ($password === '') {
    $fieldErrors['password'] = 'Въведете парола.';
} elseif (strlen($password) < 6) {
   $fieldErrors['password'] = 'Паролата трябва да е поне 6 символа.';
} elseif (strlen($password) > 200) {
   $fieldErrors['password'] = 'Паролата е твърде дълга.';
}

if ($password2 === '') {
    $fieldErrors['password2'] = 'Потвърдете паролата.';
} elseif ($password !== $password2) {
    $fieldErrors['password2'] = 'Паролите не съвпадат.';
}

if (!empty($fieldErrors)) {
    flash_back('Има грешки във формата.', $fieldErrors, [
        'name' => $nameTrim,
        'email' => $emailNorm,
    ]);
}

$userModel = new UserModel($pdo);

try {
    $userId = $userModel->register($nameTrim, $emailNorm, $password);

    header('Location: ../login.php');
    exit;

} catch (InvalidArgumentException $e) {
    $msg = $e->getMessage();
    $mapped = [];

    if (stripos($msg, 'name') !== false) {
        $mapped['name'] = 'Невалидно име.';
    } elseif (stripos($msg, 'email') !== false) {
        $mapped['email'] = 'Невалиден имейл адрес.';
    } elseif (stripos($msg, 'password') !== false) {
        $mapped['password'] = 'Невалидна парола.';
    }

    if (empty($mapped)) {
        flash_back($msg, [], ['name' => $nameTrim, 'email' => $emailNorm]);
    }

    flash_back('Има грешки във формата.', $mapped, ['name' => $nameTrim, 'email' => $emailNorm]);

} catch (RuntimeException $e) {
    flash_back('Неуспешна регистрация.', [
        'email' => $e->getMessage(),
    ], [
        'name' => $nameTrim,
        'email' => $emailNorm,
    ]);

} catch (Throwable $e) {
    flash_back('Възникна сървърна грешка. Опитайте отново.', [], [
        'name' => $nameTrim,
        'email' => $emailNorm,
    ]);
}

?>