<?php
declare(strict_types=1);

session_start();

if (empty($_SESSION['user_id'])) {
    // не е логнат
    header('Location: ../public/login.php');
    exit;
}

?>