<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../core/DocumentEngine.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["ok" => false, "error" => "Method not allowed"], JSON_UNESCAPED_UNICODE);
    exit;
}

// Ако от JS не пратиш generate_preview, го добавяме тук,
// за да мине проверката в aggregateInput() без да я пипаме.
if (!isset($_POST['generate_preview'])) {
    $_POST['generate_preview'] = '1';
}

function json_ok(array $payload = []): void
{
    echo json_encode(["ok" => true] + $payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function json_err(int $status, string $error, array $extra = []): void
{
    http_response_code($status);
    echo json_encode(["ok" => false, "error" => $error] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
}

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

// function aggregateInput(): ?array
// {
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
//             $tmp = $_FILES['html_files']['tmp_name'][$i] ?? '';
//             $htmls[] = [
//                 "name"  => $_FILES['html_files']['name'][$i] ?? $name,
//                 "type"  => $_FILES['html_files']['type'][$i] ?? '',
//                 "html"  => $tmp ? readUploadedHtml($tmp) : '',
//                 "error" => (int)($_FILES['html_files']['error'][$i] ?? 0),
//                 "size"  => (int)($_FILES['html_files']['size'][$i] ?? 0),
//             ];
//         }
//     }

//     /* ---------------------------------
//      * Построяване на масива
//      * --------------------------------- */
//     return [
//         "htmls" => $htmls,

//         "settings" => [
//             "page" => [
//                 "page_size"    => $_POST['page_size'] ?? '',
//                 "orientation"  => $_POST['orientation'] ?? '',
//                 "page_width"   => (int)($_POST['page_width'] ?? 0),
//                 "page_height"  => (int)($_POST['page_height'] ?? 0),
//                 "margin"       => (int)($_POST['margin'] ?? 0),
//                 "font_size"    => (int)($_POST['font_size'] ?? 0),
//                 "line_spacing" => $lineSpacingValue,
//                 "words"        => (int)($_POST['words'] ?? 0),
//                 "lines"        => (int)($_POST['lines'] ?? 0),
//             ],

//             "pagination" => [
//                 "show_page_numbers"      => (int)($_POST['show_page_numbers'] ?? 0),//isset($_POST['show_page_numbers']) ? 1 : 0,
//                 "no_number_on_first"     => (int)($_POST['no_number_on_first'] ?? 0),//isset($_POST['no_number_on_first']) ? 1 : 0,
//                 "page_number_pos"        => $_POST['page_number_pos'] ?? '',
//                 "page_number_format"     => $_POST['page_number_format'] ?? '',
//                 "page_number_template"   => $_POST['page_number_template'] ?? '',
//                 "line_numbers_mode"      => $_POST['line_numbers_mode'] ?? '',
//                 "line_numbers_placement" => $_POST['line_numbers_placement'] ?? '',
//             ],

//             "sections" => [
//                 "new_page_on_header"   => (int)($_POST['new_page_on_header'] ?? 0),//isset($_POST['new_page_on_header']) ? 1 : 0,
//                 "new_page_on_file"     => (int)($_POST['new_page_on_file'] ?? 0),//isset($_POST['new_page_on_file']) ? 1 : 0,
//                 "file_name_as_section" => (int)($_POST['file_name_as_section'] ?? 0),//isset($_POST['file_name_as_section']) ? 1 : 0,
//                 "wrap_lines"           => $_POST['wrap_lines'] ?? 'yes',
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
// }

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

function process_html(): ?string
{
    $input = aggregateInput();
    if ($input === null) {
        return null;
    }

    if (empty($input['htmls'])) {
        throw new RuntimeException("Не са качени HTML файлове.");
    }

    $engine = new DocumentEngine();
    return $engine->process($input);
}

try {
    $html = process_html();
    if ($html === null) {
        json_err(400, "No preview data built");
    }
    json_ok(["html" => $html]);
} catch (Throwable $e) {
    json_err(400, $e->getMessage());
}