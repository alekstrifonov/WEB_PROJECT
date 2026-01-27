<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function json_fail(int $code, string $error, array $extra = []): void
{
    http_response_code($code);
    echo json_encode(array_merge([
        "ok" => false,
        "error" => $error,
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function json_ok(array $data = []): void
{
    echo json_encode(array_merge([
        "ok" => true,
    ], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_fail(405, "Method not allowed");
}

$userId = $_SESSION['user_id'] ?? null;
if (!$userId || !is_numeric($userId)) {
    json_fail(401, "Not authenticated");
}
$userId = (int)$userId;

require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../server/models/ProjectModel.php';

$projectIdRaw = $_POST['project_id'] ?? '';
if (!is_string($projectIdRaw) || $projectIdRaw === '' || !ctype_digit($projectIdRaw)) {
    json_fail(400, "Invalid project_id");
}
$projectId = (int)$projectIdRaw;

try {
    $model = new ProjectModel($pdo);

    $deleted = $model->delete($projectId, $userId);

    if (!$deleted) {
        json_fail(404, "Project not found");
    }

    json_ok([
        "project_id" => $projectId,
        "mode" => "deleted",
    ]);

} catch (Throwable $e) {
    json_fail(500, "Server error");
}

?>