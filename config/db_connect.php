<?php

declare(strict_types=1);

require_once __DIR__ . '/db_ini.php';

$DB_HOST = 'localhost';
$DB_NAME = 'test';
$DB_USER = 'root';
$DB_PASS = '';
$DB_CHARSET = 'utf8mb4';

$options = [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = db_connect_and_init(
        $DB_HOST,
        $DB_NAME,
        $DB_USER,
        $DB_PASS,
        $DB_CHARSET,
        $options
    );
} catch (PDOException $e) {
  http_response_code(500);
  exit('Database Error: ' . $e->getMessage());
}
