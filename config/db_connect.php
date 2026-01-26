<?php

declare(strict_types=1);

$DB_HOST = 'localhost';
$DB_NAME = 'test'; // Your project database name
$DB_USER = 'root';
$DB_PASS = '';     // Empty for XAMPP
$DB_CHARSET = 'utf8mb4';

// 1. Connect to the SERVER first (without dbname) to check/create the DB
$setup_dsn = "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=$DB_CHARSET";
$options = [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
  $setup_pdo = new PDO($setup_dsn, $DB_USER, $DB_PASS, $options);

  // 2. Create the database if it doesn't exist
  $setup_pdo->exec("CREATE DATABASE IF NOT EXISTS `$DB_NAME` CHARACTER SET $DB_CHARSET COLLATE utf8mb4_unicode_ci");

  // 3. Now connect to the actual database
  $dsn = "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=$DB_CHARSET";
  $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);

  $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;";

  $pdo->exec($sql);

  $sql = "CREATE TABLE IF NOT EXISTS projects (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

        user_id INT NOT NULL,

        name VARCHAR(255) NOT NULL,
        payload JSON NOT NULL,

        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        CONSTRAINT fk_projects_user
            FOREIGN KEY (user_id) REFERENCES users(id)
            ON DELETE CASCADE,

        INDEX idx_projects_user_id (user_id),

        CHECK (JSON_VALID(payload))
    ) ENGINE=InnoDB;";

    $pdo->exec($sql);
} catch (PDOException $e) {
  // During a presentation, it's helpful to see the actual error if it fails
  http_response_code(500);
  exit('Database Error: ' . $e->getMessage());
}
