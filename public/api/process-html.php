<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["ok" => false, "error" => "Method not allowed"], JSON_UNESCAPED_UNICODE);
    exit;
}

// Ако от JS не пратиш generate_preview, го добавяме тук,
// за да мине проверката във buildPreviewData() без да я пипаме.
if (!isset($_POST['generate_preview'])) {
    $_POST['generate_preview'] = '1';
}

function aggregateInput(): ?array
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['generate_preview'])) {
        return null;
    }

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
            $htmls[] = [
                "name"     => $_FILES['html_files']['name'][$i],
                "type"     => $_FILES['html_files']['type'][$i],
                "tmp_name" => $_FILES['html_files']['tmp_name'][$i],
                "error"    => $_FILES['html_files']['error'][$i],
                "size"     => $_FILES['html_files']['size'][$i],
            ];
        }
    }


    /* ---------------------------------
     * Построяване на масива
     * --------------------------------- */
    $data = [

        "htmls" => $htmls,

        "settings" => [

            "page" => [
                "page_size"   => $_POST['page_size'] ?? '',
                "orientation" => $_POST['orientation'] ?? '',
                "page_width"  => (int)($_POST['page_width'] ?? 0),
                "page_height" => (int)($_POST['page_height'] ?? 0),
                "margin"      => (int)($_POST['margin'] ?? 0),
                "font_size"   => (int)($_POST['font_size'] ?? 0),
                "line_spacing"=> $lineSpacingValue,
                "words"       => (int)($_POST['words'] ?? 0),
                "lines"       => (int)($_POST['lines'] ?? 0),
            ],

            "pagination" => [
                "show_page_numbers"      => isset($_POST['show_page_numbers']) ? 1 : 0,
                "no_number_on_first"     => isset($_POST['no_number_on_first']) ? 1 : 0,
                "page_number_pos"        => $_POST['page_number_pos'] ?? '',
                "page_number_format"     => $_POST['page_number_format'] ?? '',
                "page_number_template"   => $_POST['page_number_template'] ?? '',
                "line_numbers_mode"      => $_POST['line_numbers_mode'] ?? '',
                "line_numbers_placement" => $_POST['line_numbers_placement'] ?? '',
            ],

            "sections" => [
                "new_page_on_header"  => isset($_POST['new_page_on_header']) ? 1 : 0,
                "new_page_on_file"    => isset($_POST['new_page_on_file']) ? 1 : 0,
                "file_name_as_section"=> isset($_POST['file_name_as_section']) ? 1 : 0,
                "wrap_lines"          => $_POST['wrap_lines'] ?? '',
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

    return $data;
}

function process_html()
{
    $input = aggregateInput();
    // $document_engine = new DocumentEngine();
    // $html_to_render = $document_engine->process($input);
    // РЕНДЕРВАМЕ HTML СТРАНИЦАТА И Я ВРЪЩАМЕ НА ФРОНТ-ЕНДА.
    // КАК СЕ РЕНДЕРВА?
    // АЙДЕ ДА ГО РЕНДЕРВАМЕ В JAVASCRIPT-А

    // if problems with $html_to_render -> return null

    return $input;
}

$response = process_html();

if ($response === null) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "No preview data built"], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(["ok" => true, "data" => $response], JSON_UNESCAPED_UNICODE);

?>