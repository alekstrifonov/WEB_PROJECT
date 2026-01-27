<?php

// declare(strict_types=1);

// require_once __DIR__ . '/../../config/db_connect.php';
// require_once __DIR__ . '/../../server/models/ProjectModel.php';

// header('Content-Type: application/json; charset=utf-8');

// if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
//     http_response_code(405);
//     echo 'Method Not Allowed';
//     exit;
// }


// function aggregateInput(): ?array
// {
//     /* ---------------------------------
//      * Нормализация на line_spacing
//      * --------------------------------- */
//     $lineSpacingMap = [
//         'normal' => 2,
//         'wide'   => 3,
//         'tight'  => 1,
//     ];

//     $lineSpacing = $_POST['line_spacing'] ?? 'normal';
//     $lineSpacingValue = $lineSpacingMap[$lineSpacing] ?? 2;


//     /* ---------------------------------
//      * Обработка на html файловете
//      * --------------------------------- */
//     $htmls = [];

//     if (!empty($_FILES['html_files']['name'][0])) {
//         foreach ($_FILES['html_files']['name'] as $i => $name) {
//             $htmls[] = [
//                 "name"     => $_FILES['html_files']['name'][$i],
//                 "type"     => $_FILES['html_files']['type'][$i],
//                 "tmp_name" => $_FILES['html_files']['tmp_name'][$i],
//                 "error"    => $_FILES['html_files']['error'][$i],
//                 "size"     => $_FILES['html_files']['size'][$i],
//             ];
//         }
//     }


//     /* ---------------------------------
//      * Построяване на масива
//      * --------------------------------- */
//     $data = [

//         "htmls" => $htmls,

//         "settings" => [

//             "page" => [
//                 "page_size"   => $_POST['page_size'] ?? '',
//                 "orientation" => $_POST['orientation'] ?? '',
//                 "page_width"  => (int)($_POST['page_width'] ?? 0),
//                 "page_height" => (int)($_POST['page_height'] ?? 0),
//                 "margin"      => (int)($_POST['margin'] ?? 0),
//                 "font_size"   => (int)($_POST['font_size'] ?? 0),
//                 "line_spacing"=> $lineSpacingValue,
//                 "words"       => (int)($_POST['words'] ?? 0),
//                 "lines"       => (int)($_POST['lines'] ?? 0),
//             ],

//             "pagination" => [
//                 "show_page_numbers"      => isset($_POST['show_page_numbers']) ? 1 : 0,
//                 "no_number_on_first"     => isset($_POST['no_number_on_first']) ? 1 : 0,
//                 "page_number_pos"        => $_POST['page_number_pos'] ?? '',
//                 "page_number_format"     => $_POST['page_number_format'] ?? '',
//                 "page_number_template"   => $_POST['page_number_template'] ?? '',
//                 "line_numbers_mode"      => $_POST['line_numbers_mode'] ?? '',
//                 "line_numbers_placement" => $_POST['line_numbers_placement'] ?? '',
//             ],

//             "sections" => [
//                 "new_page_on_header"  => isset($_POST['new_page_on_header']) ? 1 : 0,
//                 "new_page_on_file"    => isset($_POST['new_page_on_file']) ? 1 : 0,
//                 "file_name_as_section"=> isset($_POST['file_name_as_section']) ? 1 : 0,
//                 "wrap_lines"          => $_POST['wrap_lines'] ?? '',
//             ],
//         ],

//         "meta" => [
//             "title"                => $_POST['title'] ?? '',
//             "author"               => $_POST['author'] ?? '',
//             "course"               => $_POST['course'] ?? '',
//             "citation_template"    => $_POST['citation_template'] ?? '',
//             "metadata_placement"   => $_POST['metadata_placement'] ?? '',
//             "statistics_placement" => $_POST['statistics_placement'] ?? '',
//         ],
//     ];

//     return $data;
// }

// function flash_back(string $error) {
//     $_SESSION['save_error'] = $error;
//     header("Location: ../editor.php");
//     exit;
// }

// $userId = $_SESSION['user_id'] ?? null;
// $userId = (int)$userId;

// $project = aggregateInput();

// if ($project === null) {
//     flash_back('Неизвесна грешка. Опитайте отново.');
// }

// if (empty($project['htmls'])) {
//     flash_back('Не сте добавили документи за обработка.');
// }

// $projectName = trim((string)($_POST['project_name'] ?? ''));
// if ($projectName === '') {
//     flash_back('Въведете име на проекта.');
// }

// $projectModel = new ProjectModel($pdo);

// try {
//     $projectId = $projectModel->create($userId, $projectName, $project);

//     header('Location: ../dashboard.php');
//     exit;
// } catch (InvalidArgumentException $e) {
//     flash_back('Невалидно име за проект.');
// } catch (Throwable $e) {
//     flash_back('Възникна сървърна грешка. Опитайте отново.');
// }

declare(strict_types=1);

/**
 * api/save-project.php
 *
 * Очаква:
 * - POST multipart/form-data (FormData от JS)
 * - generate_preview=1 (можеш и тук да го добавиш автоматично, но приемам че го пращаш)
 * - project_name (име на проекта)
 * - optional: project_id (ако обновяваш съществуващ)
 * - optional: html_files[] (качени html файлове)
 *
 * Връща JSON:
 * { ok: true, project_id: 123 }
 */

header('Content-Type: application/json; charset=utf-8');

// DEV ONLY
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Връща JSON при fatal/parse/core errors (иначе fetch вижда празно)
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        http_response_code(500);
        echo json_encode([
            "ok" => false,
            "error" => "Fatal error",
            "details" => $err['message'],
            "file" => $err['file'],
            "line" => $err['line'],
        ], JSON_UNESCAPED_UNICODE);
    }
});

session_start();

// ----------------------- helpers -----------------------
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

// function aggregateInput(): ?array
// {

//     $htmlReader = new HtmlReader();

//     if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['generate_preview'])) {
//         return null;
//     }
//     /* ---------------------------------
//      * Нормализация на line_spacing
//      * --------------------------------- */
//     $lineSpacingMap = [
//         'normal' => 2,
//         'wide'   => 3,
//         'tight'  => 1,
//     ];

//     $lineSpacing = $_POST['line_spacing'] ?? 'normal';
//     $lineSpacingValue = $lineSpacingMap[$lineSpacing] ?? 2;


//     /* ---------------------------------
//      * Обработка на html файловете
//      * --------------------------------- */
//     $htmls = [];

//     if (!empty($_FILES['html_files']['name'][0])) {
//         foreach ($_FILES['html_files']['name'] as $i => $name) {
//             $htmls[] = [
//                 "name"     => $_FILES['html_files']['name'][$i],
//                 "type"     => $_FILES['html_files']['type'][$i],
//                 "html"     => $htmlReader->read_file($_FILES['html_files']['tmp_name'][$i]),
//                 "error"    => $_FILES['html_files']['error'][$i],
//                 "size"     => $_FILES['html_files']['size'][$i],
//             ];
//         }
//     }


//     /* ---------------------------------
//      * Построяване на масива
//      * --------------------------------- */
//     $data = [

//         "htmls" => $htmls,

//         "settings" => [

//             "page" => [
//                 "page_size"   => $_POST['page_size'] ?? '',
//                 "orientation" => $_POST['orientation'] ?? '',
//                 "page_width"  => (int)($_POST['page_width'] ?? 0),
//                 "page_height" => (int)($_POST['page_height'] ?? 0),
//                 "margin"      => (int)($_POST['margin'] ?? 0),
//                 "font_size"   => (int)($_POST['font_size'] ?? 0),
//                 "line_spacing"=> $lineSpacingValue,
//                 "words"       => (int)($_POST['words'] ?? 0),
//                 "lines"       => (int)($_POST['lines'] ?? 0),
//             ],

//             "pagination" => [
//                 "show_page_numbers"      => isset($_POST['show_page_numbers']) ? 1 : 0,
//                 "no_number_on_first"     => isset($_POST['no_number_on_first']) ? 1 : 0,
//                 "page_number_pos"        => $_POST['page_number_pos'] ?? '',
//                 "page_number_format"     => $_POST['page_number_format'] ?? '',
//                 "page_number_template"   => $_POST['page_number_template'] ?? '',
//                 "line_numbers_mode"      => $_POST['line_numbers_mode'] ?? '',
//                 "line_numbers_placement" => $_POST['line_numbers_placement'] ?? '',
//             ],

//             "sections" => [
//                 "new_page_on_header"  => isset($_POST['new_page_on_header']) ? 1 : 0,
//                 "new_page_on_file"    => isset($_POST['new_page_on_file']) ? 1 : 0,
//                 "file_name_as_section"=> isset($_POST['file_name_as_section']) ? 1 : 0,
//                 "wrap_lines"          => $_POST['wrap_lines'] ?? '',
//             ],
//         ],

//         "meta" => [
//             "title"                => $_POST['title'] ?? '',
//             "author"               => $_POST['author'] ?? '',
//             "course"               => $_POST['course'] ?? '',
//             "citation_template"    => $_POST['citation_template'] ?? '',
//             "metadata_placement"   => $_POST['metadata_placement'] ?? '',
//             "statistics_placement" => $_POST['statistics_placement'] ?? '',
//         ],
//     ];

//     return $data;
// }

function readUploadedHtml(string $tmpPath): string
{
    $raw = @file_get_contents($tmpPath);
    if ($raw === false) {
        return '';
    }

    // Нормализация към UTF-8 (често HTML-ите са Windows-1251)
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

    /* ---------------------------------
    * Заглавия за page-break (H1..H6)
    * --------------------------------- */
    $levelsRaw = $_POST['header_page_break_levels'] ?? []; // очакваме array от editor.php
    $levels = [];

    // ??
    if (is_array($levelsRaw)) {
        foreach ($levelsRaw as $v) {
            $n = (int)$v;
            if ($n >= 1 && $n <= 6) {
                $levels[$n] = true; // unique
            }
        }
    }

    // финален масив от int, сортиран
    $headerLevels = array_keys($levels);
    sort($headerLevels);

    // derived flag: има ли включено разделяне по заглавия
    $newPageOnHeader = !empty($headerLevels) ? 1 : 0;

    /* ---------------------------------
     * Нормализация на line_spacing
     * --------------------------------- */
    $lineSpacingMap = [
        'normal' => 2,
        'wide'   => 3,
        'tight'  => 1,
    ];

    $lineSpacing = $_POST['line_spacing'] ?? 'normal';
    $lineSpacingValue = $lineSpacingMap[$lineSpacing] ?? 2;

    /* ---------------------------------
     * Обработка на html файловете
     * --------------------------------- */
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

    /* ---------------------------------
     * Построяване на масива
     * --------------------------------- */
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
                "show_page_numbers"      => (int)($_POST['show_page_numbers'] ?? 0),//isset($_POST['show_page_numbers']) ? 1 : 0,
                "no_number_on_first"     => (int)($_POST['no_number_on_first'] ?? 0),//isset($_POST['no_number_on_first']) ? 1 : 0,
                "page_number_pos"        => $_POST['page_number_pos'] ?? '',
                "page_number_format"     => $_POST['page_number_format'] ?? '',
                "page_number_template"   => $_POST['page_number_template'] ?? '',
                "line_numbers_mode"      => $_POST['line_numbers_mode'] ?? '',
                "line_numbers_placement" => $_POST['line_numbers_placement'] ?? '',
            ],

            "sections" => [
                "new_page_on_header"   => $newPageOnHeader, //(int)($_POST['new_page_on_header'] ?? 0),//isset($_POST['new_page_on_header']) ? 1 : 0,
                "header_page_break_levels" => $headerLevels,
                "new_page_on_file"     => (int)($_POST['new_page_on_file'] ?? 0),//isset($_POST['new_page_on_file']) ? 1 : 0,
                "file_name_as_section" => (int)($_POST['file_name_as_section'] ?? 0),//isset($_POST['file_name_as_section']) ? 1 : 0,
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

// ----------------------- auth -----------------------
$userId = $_SESSION['user_id'] ?? null;
if (!$userId || !is_numeric($userId)) {
    json_error(401, "Not authenticated.");
}
$userId = (int)$userId;

// ----------------------- method -----------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error(405, "Method not allowed.");
}

// Ако от фронтенда не пращаш generate_preview, можеш да го активираш тук.
// (не променяме aggregateInput, само осигуряваме флага)
// $_POST['generate_preview'] ??= '1';

// ----------------------- include DB & aggregateInput -----------------------
// ТУК НАГЛАСИ include-ите според проекта ти:
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../server/models/ProjectModel.php';

// ----------------------- collect input -----------------------
$data = aggregateInput();
if ($data === null) {
    json_error(400, "Неизвесна грешка. Опитайте отново.");
}

if (empty($data['htmls'])) {
    json_error(400, "Не сте добавили документи за обработка.");
}

// Име на проекта (от модала)
$projectName = trim((string)($_POST['project_name'] ?? ''));
if ($projectName === '') {
    json_error(400, "Не сте въвели име на проекта.");
}

// project_id ако обновяваме
$projectId = null;
if (isset($_POST['project_id']) && $_POST['project_id'] !== '') {
    if (!ctype_digit((string)$_POST['project_id'])) {
        json_error(400, "Неизвесна грешка. Опитайте отново.");
    }
    $projectId = (int)$_POST['project_id'];
}

$model = new ProjectModel($pdo);

try {
    // CREATE
    if ($projectId === null) {
        $newId = $model->create($userId, $projectName, $data); // валидира име + JSON-ира payload :contentReference[oaicite:2]{index=2}
        $projectId = $newId;

        json_ok(["project_id" => $projectId, "mode" => "created"]);
    }

    // UPDATE
    $existing = $model->findById($projectId, $userId);
    if (!$existing) {
        json_error(404, "Такъв проект не съществува.");
    }

    // update име + payload наведнъж
    $model->update($projectId, $userId, $projectName, $data); // :contentReference[oaicite:4]{index=4}

    json_ok(["project_id" => $projectId, "mode" => "updated"]);

} catch (InvalidArgumentException $e) {
    // напр. невалидно име според ProjectModel::isValidName() (мин. 2 символа) :contentReference[oaicite:5]{index=5}
    json_error(400, "Невалидно име за проект.");
} catch (Throwable $e) {
    json_error(500, "Неизвесна грешка. Опитайте отново.");
}

?>