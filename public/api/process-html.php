<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../core/DocumentEngine.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["ok" => false, "error" => "Method not allowed"], JSON_UNESCAPED_UNICODE);
    exit;
}

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

    $enc = mb_detect_encoding($raw, ['UTF-8', 'Windows-1251', 'ISO-8859-5'], true);
    if ($enc && $enc !== 'UTF-8') {
        $raw = mb_convert_encoding($raw, 'UTF-8', $enc);
    }

    return $raw;
}

function isZipName(string $name): bool
{
    return (bool)preg_match('/\.zip$/i', $name);
}

function isHtmlName(string $name): bool
{
    return (bool)preg_match('/\.(html?|xhtml)$/i', $name);
}

/**
 * Извлича HTML файлове от ZIP upload (само html/htm/xhtml).
 * Връща масив елементи със структурата като останалите htmls[].
 */
function extractHtmlsFromZip(string $zipTmpPath, string $zipOriginalName): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException("ZipArchive не е наличен. Активирай php_zip разширението.");
    }

    $zip = new ZipArchive();
    if ($zip->open($zipTmpPath) !== true) {
        throw new RuntimeException("Неуспешно отваряне на ZIP файла: {$zipOriginalName}");
    }

    $out = [];

    // Сортираме имената за стабилен ред
    $names = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        if (!$stat || empty($stat['name'])) continue;
        $names[] = $stat['name'];
    }
    sort($names, SORT_NATURAL | SORT_FLAG_CASE);

    foreach ($names as $entryName) {
        // skip директории
        if (substr($entryName, -1) === '/') continue;

        // zip-slip защита: забраняваме абсолютни и ../
        if (strpos($entryName, '..') !== false || strpos($entryName, ':') !== false || substr($entryName, 0, 1) === '/') {
            continue;
        }

        if (!isHtmlName($entryName)) {
            // картинки и други файлове засега ги игнорираме (следваща стъпка ще ги ползваме)
            continue;
        }

        $raw = $zip->getFromName($entryName);
        if ($raw === false) {
            continue;
        }

        // нормализиране на encoding като при readUploadedHtml()
        $enc = mb_detect_encoding($raw, ['UTF-8', 'Windows-1251', 'ISO-8859-5'], true);
        if ($enc && $enc !== 'UTF-8') {
            $raw = mb_convert_encoding($raw, 'UTF-8', $enc);
        }

        $out[] = [
            "name"  => $zipOriginalName . "::" . $entryName, // за да знаеш откъде идва
            "type"  => "text/html",
            "html"  => $raw,
            "error" => 0,
            "size"  => strlen($raw),
        ];
    }

    $zip->close();

    if (empty($out)) {
        throw new RuntimeException("ZIP файлът не съдържа HTML документи: {$zipOriginalName}");
    }

    return $out;
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
            $name = (string)$name;
            $tmp  = $_FILES['html_files']['tmp_name'][$i] ?? '';
            $err  = (int)($_FILES['html_files']['error'][$i] ?? 0);

            if ($err !== UPLOAD_ERR_OK) {
                // пропускаме счупени upload-и
                continue;
            }

            if ($tmp && isZipName($name)) {
                // ✅ ZIP: вади HTML-ите вътре
                $fromZip = extractHtmlsFromZip($tmp, $name);
                foreach ($fromZip as $z) {
                    $htmls[] = $z;
                }
                continue;
            }

            // ✅ Обикновен HTML upload както досега
            $htmls[] = [
                "name"  => $_FILES['html_files']['name'][$i] ?? $name,
                "type"  => $_FILES['html_files']['type'][$i] ?? '',
                "html"  => $tmp ? readUploadedHtml($tmp) : '',
                "error" => 0,
                "size"  => (int)($_FILES['html_files']['size'][$i] ?? 0),
            ];
        }
    }

    // if (!empty($_FILES['html_files']['name'][0])) {
    //     foreach ($_FILES['html_files']['name'] as $i => $name) {
    //         $tmp = $_FILES['html_files']['tmp_name'][$i] ?? '';
    //         $htmls[] = [
    //             "name"  => $_FILES['html_files']['name'][$i] ?? $name,
    //             "type"  => $_FILES['html_files']['type'][$i] ?? '',
    //             "html"  => $tmp ? readUploadedHtml($tmp) : '',
    //             "error" => (int)($_FILES['html_files']['error'][$i] ?? 0),
    //             "size"  => (int)($_FILES['html_files']['size'][$i] ?? 0),
    //         ];
    //     }
    // }

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