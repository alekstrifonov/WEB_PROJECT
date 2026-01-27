<?php

function db_connect_and_init(
    string $host,
    string $dbName,
    string $username,
    string $password,
    string $dbCharSet,
    array $options = []
): PDO {
    $dsnServer = "mysql:host=$host;charset=$dbCharSet";
    $pdoServer = new PDO($dsnServer, $username, $password, $options);

    db_ensure_database_exists($pdoServer, $dbName, $dbCharSet);

    $dsnDatabase = "mysql:host={$host};dbname={$dbName};charset={$dbCharSet}";
    $pdo = new PDO($dsnDatabase, $username, $password, $options);

    db_ensure_tables_exist($pdo);

    return $pdo;
}

function db_ensure_database_exists(PDO $pdo, string $dbName, string $dbCharSet): void
{
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` 
                CHARACTER SET `{$dbCharSet}` 
                COLLATE utf8mb4_unicode_ci");
}

function db_ensure_tables_exist(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS projects (
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
    ) ENGINE=InnoDB;"
    );
}
