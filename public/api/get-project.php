<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function json_fail(int $code, string $msg): void {
  http_response_code($code);
  echo json_encode(["ok"=>false,"error"=>$msg], JSON_UNESCAPED_UNICODE);
  exit;
}
function json_ok(array $data): void {
  echo json_encode(["ok"=>true] + $data, JSON_UNESCAPED_UNICODE);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') 
    json_fail(405, "Method not allowed");

$userId = $_SESSION['user_id'] ?? null;
if (!$userId || !is_numeric($userId)) 
    json_fail(401, "Not authenticated");
$userId = (int)$userId;

$projectIdRaw = $_GET['project_id'] ?? '';
if (!is_string($projectIdRaw) || $projectIdRaw === '' || !ctype_digit($projectIdRaw)) {
  json_fail(400, "Invalid project_id");
}
$projectId = (int)$projectIdRaw;

require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../server/models/ProjectModel.php';

if (!isset($pdo) || !($pdo instanceof PDO)) json_fail(500, "PDO not available");

$model = new ProjectModel($pdo);
$row = $model->findById($projectId, $userId);
if (!$row) json_fail(404, "Project not found");

$payload = json_decode((string)$row['payload'], true);
if (!is_array($payload)) json_fail(500, "Invalid payload in DB");

json_ok([
  "project" => [
    "id" => (int)$row['id'],
    "name" => (string)$row['name'],
    "payload" => $payload
  ]
]);

?>