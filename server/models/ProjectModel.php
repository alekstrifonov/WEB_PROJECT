<?php

declare(strict_types=1);

class ProjectModel
{
    /** @var PDO */
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(int $projectId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, name, payload, created_at, updated_at
             FROM projects
             WHERE id = ? AND user_id = ?
             LIMIT 1'
        );
        $stmt->execute([$projectId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function listByUser(int $userId, int $limit = 50, int $offset = 0): array
    {
        $limit  = max(1, min(200, $limit));
        $offset = max(0, $offset);

        $sql = "
            SELECT id, user_id, name, created_at, updated_at
            FROM projects
            WHERE user_id = {$userId}
            ORDER BY updated_at DESC, id DESC
            LIMIT {$limit} OFFSET {$offset}
        ";

        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(int $userId, string $name, $payload): int
    {
        $name = $this->normalizeName($name);
        if (!$this->isValidName($name)) {
            throw new InvalidArgumentException('Invalid project name.');
        } 

        $payloadJson = $this->normalizePayloadToJson($payload);

        $stmt = $this->pdo->prepare(
            'INSERT INTO projects (user_id, name, payload)
             VALUES (?, ?, ?)'
        );
        $stmt->execute([$userId, $name, $payloadJson]);

        return (int)$this->pdo->lastInsertId();
    }

    public function updateName(int $projectId, int $userId, string $newName): bool
    {
        $newName = $this->normalizeName($newName);
        if (!$this->isValidName($newName)) {
            throw new InvalidArgumentException('Invalid project name.');
        }

        $stmt = $this->pdo->prepare(
            'UPDATE projects
             SET name = ?
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$newName, $projectId, $userId]);

        return $stmt->rowCount() > 0;
    }

    public function updatePayload(int $projectId, int $userId, $payload): bool
    {
        $payloadJson = $this->normalizePayloadToJson($payload);

        $stmt = $this->pdo->prepare(
            'UPDATE projects
             SET payload = ?
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$payloadJson, $projectId, $userId]);

        return $stmt->rowCount() > 0;
    }

    public function update(int $projectId, int $userId, string $name, $payload): bool
    {
        $name = $this->normalizeName($name);
        if (!$this->isValidName($name)) {
            throw new InvalidArgumentException('Invalid project name.');
        }

        $payloadJson = $this->normalizePayloadToJson($payload);

        $stmt = $this->pdo->prepare(
            'UPDATE projects
             SET name = ?, payload = ?
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$name, $payloadJson, $projectId, $userId]);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $projectId, int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM projects
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$projectId, $userId]);

        return $stmt->rowCount() > 0;
    }

    private function normalizeName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/\s+/u', ' ', $name);
        return $name;
    }

    private function utf8_len(string $s): int
    {
        if ($s === '') return 0;
        preg_match_all('/./us', $s, $m);
        return count($m[0]);
    }

    private function isValidName(string $name): bool
    {
        $len = $this->utf8_len($name);
        if ($len < 2 || $len > 255) {
            return false;
        }

        return (bool)preg_match('/^[\p{Latin}\p{Cyrillic}\p{N}\s\-_.,()]+$/u', $name);
    }

    private function normalizePayloadToJson($payload): string
    {
        if (is_array($payload)) {
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                throw new RuntimeException('Could not JSON-encode payload.');
            }
            if (json_decode($json, true) === null && json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException('JSON-encoded payload is invalid.');
            }
            return $json;
        }

        if (is_string($payload)) {
            $payload = trim($payload);
            if ($payload === '') {
                throw new InvalidArgumentException('Payload JSON is empty.');
            }
            json_decode($payload, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new InvalidArgumentException('Payload is not valid JSON.');
            }
            return $payload;
        }

        throw new InvalidArgumentException('Payload must be array or JSON string.');
    }
}

?>
