<?php
/**
 * UserModel.php
 * Mini ORM за таблица users
 *
 * Колони:
 * - id
 * - name
 * - email
 * - password_hash
 * - created_at
 */

declare(strict_types=1);

class UserModel
{
    /** @var PDO */
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* =========================================================
       READ
       ========================================================= */

    /**
     * Намира потребител по ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, email, password_hash, created_at
             FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Намира потребител по email
     */
    public function findByEmail(string $email): ?array
    {
        $email = $this->normalizeEmail($email);

        $stmt = $this->pdo->prepare(
            'SELECT id, name, email, password_hash, created_at
             FROM users WHERE email = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Проверява дали email вече съществува
     */
    public function emailExists(string $email): bool
    {
        $email = $this->normalizeEmail($email);

        $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);

        return (bool)$stmt->fetchColumn();
    }

    /* =========================================================
       CREATE / REGISTER
       ========================================================= */

    /**
     * Ниско-нивов create (приема вече hash-ната парола)
     * @return int new user id
     */
    public function create(string $name, string $email, string $passwordHash): int
    {
        $name  = $this->normalizeName($name);
        $email = $this->normalizeEmail($email);

        $stmt = $this->pdo->prepare(
            'INSERT INTO users (name, email, password_hash)
             VALUES (?, ?, ?)'
        );

        try {
            $stmt->execute([$name, $email, $passwordHash]);
        } catch (PDOException $e) {
            if ($this->isDuplicateKey($e)) {
                throw new RuntimeException('Този имейл вече е зает.');
            }
            throw $e;
        }

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Високо-нивов register (plain password)
     */
    public function register(string $name, string $email, string $plainPassword): int
    {
        $name  = $this->normalizeName($name);
        $email = $this->normalizeEmail($email);

        if (!$this->isValidName($name)) {
            throw new InvalidArgumentException('Invalid name.');
        }
        if (!$this->isValidEmail($email)) {
            throw new InvalidArgumentException('Invalid email.');
        }
        if (!$this->isValidPassword($plainPassword)) {
            throw new InvalidArgumentException('Invalid password.');
        }

        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
        if ($hash === false) {
            throw new RuntimeException('Could not hash password.');
        }

        return $this->create($name, $email, $hash);
    }

    /* =========================================================
       AUTH
       ========================================================= */

    /**
     * Проверка за логин
     * @return array|null user data при успех
     */
    public function verifyLogin(string $email, string $plainPassword): ?array
    {
        $user = $this->findByEmail($email);
        if (!$user) {
            return null;
        }

        if (!password_verify($plainPassword, $user['password_hash'])) {
            return null;
        }

        // Автоматичен rehash при нужда
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($plainPassword, PASSWORD_DEFAULT);
            if ($newHash !== false) {
                $this->updatePasswordHash((int)$user['id'], $newHash);
                $user['password_hash'] = $newHash;
            }
        }

        return $user;
    }

    /* =========================================================
       UPDATE
       ========================================================= */

    public function updateName(int $id, string $newName): bool
    {
        $newName = $this->normalizeName($newName);

        if ($this->isValidName($newName) != 0) {
            throw new InvalidArgumentException('Invalid name.');
        }

        $stmt = $this->pdo->prepare(
            'UPDATE users SET name = ? WHERE id = ?'
        );
        $stmt->execute([$newName, $id]);

        return $stmt->rowCount() > 0;
    }

    public function updateEmail(int $id, string $newEmail): bool
    {
        $newEmail = $this->normalizeEmail($newEmail);

        if (!$this->isValidEmail($newEmail)) {
            throw new InvalidArgumentException('Invalid email.');
        }

        $stmt = $this->pdo->prepare(
            'UPDATE users SET email = ? WHERE id = ?'
        );

        try {
            $stmt->execute([$newEmail, $id]);
        } catch (PDOException $e) {
            if ($this->isDuplicateKey($e)) {
                throw new RuntimeException('Email already exists.');
            }
            throw $e;
        }

        return $stmt->rowCount() > 0;
    }

    public function updatePasswordHash(int $id, string $passwordHash): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET password_hash = ? WHERE id = ?'
        );
        $stmt->execute([$passwordHash, $id]);

        return $stmt->rowCount() > 0;
    }

    /* =========================================================
       DELETE / LIST
       ========================================================= */

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    public function list(int $limit = 50, int $offset = 0): array
    {
        $limit  = max(1, min(200, $limit));
        $offset = max(0, $offset);

        $sql = "
            SELECT id, name, email, created_at
            FROM users
            ORDER BY id DESC
            LIMIT {$limit} OFFSET {$offset}
        ";

        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =========================================================
       HELPERS
       ========================================================= */

    private function normalizeName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/\s+/u', ' ', $name);
        return $name;
    }


    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function isValidName(string $name): bool
    {
        $len = strlen($name);
        if ($len < 2 || $len > 100) {
            return 1;
        }
        
        // Само латински букви и интервали
        return (bool)preg_match('/^[\p{Latin}\p{Cyrillic} ]+$/u', $name);
    }

    private function isValidEmail(string $email): bool
    {
        return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    private function isValidPassword(string $plainPassword): bool
    {
        $len = strlen($plainPassword);
        return $len >= 6 && $len <= 200;
    }

    private function isDuplicateKey(PDOException $e): bool
    {
        $info = $e->errorInfo;
        return isset($info[0], $info[1])
            && $info[0] === '23000'
            && (int)$info[1] === 1062;
    }
}

?>