<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function json_error(int $code, string $msg, array $extra = []): void {
    http_response_code($code);
    echo json_encode(array_merge(["ok" => false, "error" => $msg], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function json_ok(array $data = []): void {
    echo json_encode(
        array_merge(["ok" => true], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

function readUploadedHtml(string $tmpPath): string
{
    $raw = @file_get_contents($tmpPath);
    if ($raw === false) {
        return '';
    }

    $enc = mb_detect_encoding($raw, ['UTF-8', 'Windows-1251', 'ISO-8859-5'], true);
    if ($enc && $enc !== 'UTF-8') {
        $raw = mb_convert_encoding($raw, 'UTF-8', $enc);
    }

    return $raw;
}

function aggregateInput(): ?array
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['generate_preview'])) {
        return null;
    }

    $levelsRaw = $_POST['header_page_break_levels'] ?? [];
    $levels = [];

    if (is_array($levelsRaw)) {
        foreach ($levelsRaw as $v) {
            $n = (int)$v;
            if ($n >= 1 && $n <= 6) {
                $levels[$n] = true;
            }
        }
    }

    $headerLevels = array_keys($levels);
    sort($headerLevels);

    $newPageOnHeader = !empty($headerLevels) ? 1 : 0;

    $lineSpacingMap = [
        'normal' => 2,
        'wide'   => 3,
        'tight'  => 1,
    ];

    $lineSpacing = $_POST['line_spacing'] ?? 'normal';
    $lineSpacingValue = $lineSpacingMap[$lineSpacing] ?? 2;

    $htmls = [];

    if (!empty($_FILES['html_files']['name'][0])) {
        foreach ($_FILES['html_files']['name'] as $i => $name) {
            $tmp = $_FILES['html_files']['tmp_name'][$i] ?? '';
            $htmls[] = [
                "name"  => $_FILES['html_files']['name'][$i] ?? $name,
                "type"  => $_FILES['html_files']['type'][$i] ?? '',
                "html"  => $tmp ? readUploadedHtml($tmp) : '',
                "error" => (int)($_FILES['html_files']['error'][$i] ?? 0),
                "size"  => (int)($_FILES['html_files']['size'][$i] ?? 0),
            ];
        }
    }

    return [
        "htmls" => $htmls,

        "settings" => [
            "page" => [
                "page_size"    => $_POST['page_size'] ?? '',
                "orientation"  => $_POST['orientation'] ?? '',
                "page_width"   => (int)($_POST['page_width'] ?? 0),
                "page_height"  => (int)($_POST['page_height'] ?? 0),
                "margin"       => (int)($_POST['margin'] ?? 0),
                "font_size"    => (int)($_POST['font_size'] ?? 0),
                "line_spacing" => $lineSpacingValue,
                "words"        => (int)($_POST['words'] ?? 0),
                "lines"        => (int)($_POST['lines'] ?? 0),
            ],

            "pagination" => [
                "show_page_numbers"      => (int)($_POST['show_page_numbers'] ?? 0),
                "no_number_on_first"     => (int)($_POST['no_number_on_first'] ?? 0),
                "page_number_pos"        => $_POST['page_number_pos'] ?? '',
                "page_number_format"     => $_POST['page_number_format'] ?? '',
                "page_number_template"   => $_POST['page_number_template'] ?? '',
                "line_numbers_mode"      => $_POST['line_numbers_mode'] ?? '',
                "line_numbers_placement" => $_POST['line_numbers_placement'] ?? '',
            ],

            "sections" => [
                "new_page_on_header"   => $newPageOnHeader,
                "header_page_break_levels" => $headerLevels,
                "new_page_on_file"     => (int)($_POST['new_page_on_file'] ?? 0),
                "file_name_as_section" => (int)($_POST['file_name_as_section'] ?? 0),
                "wrap_lines"           => $_POST['wrap_lines'] ?? 'yes',
            ],
        ],

        "meta" => [
            "title"                => $_POST['title'] ?? '',
            "author"               => $_POST['author'] ?? '',
            "course"               => $_POST['course'] ?? '',
            "citation_template"    => $_POST['citation_template'] ?? '',
            "metadata_placement"   => $_POST['metadata_placement'] ?? '',
            "statistics_placement" => $_POST['statistics_placement'] ?? '',
        ],
    ];
}

$userId = $_SESSION['user_id'] ?? null;
if (!$userId || !is_numeric($userId)) {
    json_error(401, "Not authenticated.");
}
$userId = (int)$userId;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error(405, "Method not allowed.");
}

require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../server/models/ProjectModel.php';

$data = aggregateInput();
if ($data === null) {
    json_error(400, "Неизвесна грешка. Опитайте отново.");
}

if (empty($data['htmls'])) {
    json_error(400, "Не сте добавили документи за обработка.");
}

$projectName = trim((string)($_POST['project_name'] ?? ''));
if ($projectName === '') {
    json_error(400, "Не сте въвели име на проекта.");
}

$projectId = null;
if (isset($_POST['project_id']) && $_POST['project_id'] !== '') {
    if (!ctype_digit((string)$_POST['project_id'])) {
        json_error(400, "Неизвесна грешка. Опитайте отново.");
    }
    $projectId = (int)$_POST['project_id'];
}

$model = new ProjectModel($pdo);

try {
    if ($projectId === null) {
        $newId = $model->create($userId, $projectName, $data);
        $projectId = $newId;

        json_ok(["project_id" => $projectId, "mode" => "created"]);
    }

    $existing = $model->findById($projectId, $userId);
    if (!$existing) {
        json_error(404, "Такъв проект не съществува.");
    }

    $model->update($projectId, $userId, $projectName, $data);

    json_ok(["project_id" => $projectId, "mode" => "updated"]);

} catch (InvalidArgumentException $e) {
    json_error(400, "Невалидно име за проект.");
} catch (Throwable $e) {
    json_error(500, "Неизвесна грешка. Опитайте отново.");
}

?>