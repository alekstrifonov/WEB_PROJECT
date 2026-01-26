<?php
declare(strict_types=1);

require_once __DIR__ . '/core/DocumentEngine.php';

function build_demo_html(): string {
    $para = str_repeat('<strong>Lorem</strong> ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. ', 8);
    $long = '';
    for ($i = 1; $i <= 20; $i++) {
        $long .= "<h2>Section $i</h2>\n";
        $long .= "<p>$para</p>\n";
        $long .= "<p>$para</p>\n";
        if ($i % 3 === 0) {
            $long .= "<h3>Subsection $i.1</h3>\n";
            $long .= "<ul>\n<li>Item A $i</li>\n<li>Item B $i</li>\n<li>Item C $i</li>\n</ul>\n";
        }
    }
    return "<!DOCTYPE html>\n<html><head><meta charset=\"utf-8\"><title>Demo Long HTML</title></head><body>\n<h1>Demo Document — Long HTML</h1>\n<p><em>This is a long demo HTML to exercise the core pipeline.</em></p>\n$long\n<p>End of document.</p>\n</body></html>";
}

function default_baseline(): array {
    return [
        'settings' => [
            'page' => [
                'page_size' => 'a4',
                'orientation' => 'portrait',
                'page_width' => 210, // mm
                'page_height' => 297, // mm
                'margin' => 18, // mm
                'font_size' => 12, // pt (conceptual)
                'line_spacing' => 'normal',
                'words' => 0,
                'lines' => 0,
            ],
            'pagination' => [
                'show_page_numbers' => 1,
                'no_number_on_first' => 0,
                'page_number_pos' => 'footer',
                'page_number_format' => '123',
                'page_number_template' => '{author} • {title} • стр. {page}/{total}',
                'line_numbers_mode' => 'none',
                'line_numbers_placement' => 'margin',
            ],
            'sections' => [
                'new_page_on_header' => 1,
                'new_page_on_file' => 1,
                'file_name_as_section' => 1,
                'wrap_lines' => 'yes',
            ],
            'cols' => 80,
            'lines_per_page' => 50,
        ],
        'meta' => [
            'title' => 'Demo Document',
            'author' => 'John Doe',
            'course' => 'CS101',
            'citation_template' => '{author}. {title}. Източник: {source}. Достъп: {access}',
            'metadata_placement' => 'start',
            'statistics_placement' => 'end',
        ],
    ];
}

function coerce_post_settings(array $post): array {
    $settings = $post['settings'] ?? [];
    $page = $settings['page'] ?? [];
    $pagination = $settings['pagination'] ?? [];
    $sections = $settings['sections'] ?? [];

    $page['page_width'] = isset($page['page_width']) ? (float)$page['page_width'] : 210;
    $page['page_height'] = isset($page['page_height']) ? (float)$page['page_height'] : 297;
    $page['margin'] = isset($page['margin']) ? (float)$page['margin'] : 18;
    $page['font_size'] = isset($page['font_size']) ? (int)$page['font_size'] : 12;
    $page['words'] = isset($page['words']) ? (int)$page['words'] : 0;
    $page['lines'] = isset($page['lines']) ? (int)$page['lines'] : 0;

    $pagination['show_page_numbers'] = isset($pagination['show_page_numbers']) ? 1 : 0;
    $pagination['no_number_on_first'] = isset($pagination['no_number_on_first']) ? 1 : 0;

    $sections['new_page_on_header'] = isset($sections['new_page_on_header']) ? 1 : 0;
    $sections['new_page_on_file'] = isset($sections['new_page_on_file']) ? 1 : 0;
    $sections['file_name_as_section'] = isset($sections['file_name_as_section']) ? 1 : 0;
    $sections['wrap_lines'] = ($sections['wrap_lines'] ?? 'yes') === 'yes' ? 'yes' : 'no';

    $settings['page'] = $page;
    $settings['pagination'] = $pagination;
    $settings['sections'] = $sections;

    $settings['cols'] = isset($settings['cols']) ? (int)$settings['cols'] : 80;
    $settings['lines_per_page'] = isset($settings['lines_per_page']) ? (int)$settings['lines_per_page'] : 50;

    return $settings;
}

function coerce_post_meta(array $post): array {
    $meta = $post['meta'] ?? [];
    $meta['metadata_placement'] = $meta['metadata_placement'] ?? 'none';
    $meta['statistics_placement'] = $meta['statistics_placement'] ?? 'none';
    return $meta;
}

function safe_html_with_inline_tags(string $text): string {
    // Escape HTML entities first
    $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    
    // Unescape allowed inline tags: <strong>, </strong>, <em>, </em>, <u>, </u>, <code>, </code>, <br>
    $escaped = str_replace('&lt;strong&gt;', '<strong>', $escaped);
    $escaped = str_replace('&lt;/strong&gt;', '</strong>', $escaped);
    $escaped = str_replace('&lt;em&gt;', '<em>', $escaped);
    $escaped = str_replace('&lt;/em&gt;', '</em>', $escaped);
    $escaped = str_replace('&lt;u&gt;', '<u>', $escaped);
    $escaped = str_replace('&lt;/u&gt;', '</u>', $escaped);
    $escaped = str_replace('&lt;code&gt;', '<code>', $escaped);
    $escaped = str_replace('&lt;/code&gt;', '</code>', $escaped);
    $escaped = str_replace('&lt;br&gt;', '<br>', $escaped);
    
    return $escaped;
}

function render_pages(array $pages, array $settings): string {
    $w = (float)($settings['page']['page_width'] ?? 210);
    $h = (float)($settings['page']['page_height'] ?? 297);
    $m = (float)($settings['page']['margin'] ?? 18);
    $font = (int)($settings['page']['font_size'] ?? 12);
    $wrapLines = (($settings['sections']['wrap_lines'] ?? 'yes') === 'yes');
    $lineWhitespace = $wrapLines ? 'pre-wrap' : 'pre';
    $overflowStyle = $wrapLines ? '' : 'overflow-x:auto; overflow-y:hidden;';
    $html = '';
    foreach ($pages as $idx => $page) {
        $header = isset($page['header']) ? (string)$page['header'] : '';
        $footer = isset($page['footer']) ? (string)$page['footer'] : '';

        $html .= '<div class="page" style="width: '.$w.'mm; min-height: '.$h.'mm; padding: '.$m.'mm; box-sizing: border-box; background: #fff; color: #000; box-shadow: 0 4px 20px rgba(0,0,0,0.2); margin: 0 auto 24px; display: flex; flex-direction: column;">';
        if ($header !== '') {
            $html .= '<div class="page-header" style="text-align:center; font-family: \''."Courier New".'\', monospace; font-size: '.max(10, $font - 2).'pt; line-height: 1.2; opacity: .8; margin-bottom: 6mm;">'.htmlspecialchars($header).'</div>';
        }
        // Decide line numbers placement (fallback to inline if not set)
        $placement = $settings['line_numbers_placement'] ?? 'inline';
        $lineNums = isset($page['line_numbers']) && is_array($page['line_numbers']) ? $page['line_numbers'] : null;

        if ($placement === 'margin' && $lineNums) {
            // Split content into individual lines for proper alignment
            $contentLines = $page['lines'] ?? [];
            $numsText = implode("\n", $lineNums);
            $contentText = implode("\n", $contentLines);
            
            $html .= '<div class="page-content" style="flex: 1 1 auto; display: flex; gap: 6mm;">';
            $html .= '<div class="page-gutter" style="width: 14mm; flex: 0 0 14mm;">';
            $html .= '<pre style="white-space: pre-wrap; text-align:right; font-family: \''."Courier New".'\', monospace; font-size: '.max(10, $font - 2).'pt; line-height: 1.4; margin:0; color:#666;">'.htmlspecialchars($numsText).'</pre>';
            $html .= '</div>';
            $html .= '<div class="page-body" style="flex: 1 1 auto;">';
                $html .= "<pre style=\"white-space: {$lineWhitespace}; font-family: 'Courier New', monospace; font-size: {$font}pt; line-height: 1.4; margin:0; {$overflowStyle}\">".safe_html_with_inline_tags($contentText)."</pre>";
            $html .= '</div>';
            $html .= '</div>';
        } else {
            $html .= '<div class="page-content" style="flex: 1 1 auto;">';
            $content = implode("\n", $page['lines'] ?? []);
                $html .= "<pre style=\"white-space: {$lineWhitespace}; font-family: 'Courier New', monospace; font-size: {$font}pt; line-height: 1.4; margin:0; {$overflowStyle}\">".safe_html_with_inline_tags($content)."</pre>";
            $html .= '</div>';
        }
        if ($footer !== '') {
            $html .= '<div class="page-footer" style="text-align:center; font-family: \''."Courier New".'\', monospace; font-size: '.max(10, $font - 2).'pt; line-height: 1.2; opacity: .8; margin-top: 6mm;">'.htmlspecialchars($footer).'</div>';
        }
        $html .= '</div>';
    }
    return $html;
}

function dump_kv(array $data): string {
    $out = '<dl class="kv">';
    foreach ($data as $k => $v) {
        if (is_array($v)) {
            $out .= '<dt>'.htmlspecialchars((string)$k).'</dt><dd><pre>'.htmlspecialchars(json_encode($v, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)).'</pre></dd>';
        } else {
            $out .= '<dt>'.htmlspecialchars((string)$k).'</dt><dd>'.htmlspecialchars((string)$v).'</dd>';
        }
    }
    $out .= '</dl>';
    return $out;
}

$demoHtml = build_demo_html();
$baseline = default_baseline();

$engine = new DocumentEngine();

$baselineInput = [
    'htmls' => [
        'name' => 'demo.html',
        'type' => 'text/html',
        'html' => $demoHtml,
        'error' => 0,
        'size' => strlen($demoHtml),
    ],
    'settings' => $baseline['settings'],
    'meta' => $baseline['meta'],
];
$baselineResult = $engine->process($baselineInput);

// DEBUG: Check how many lines are in the first page
$debugLineCount = count($baselineResult['pages'][0]['lines'] ?? []);
$debugLines = isset($baselineResult['pages'][0]['lines']) ? array_slice($baselineResult['pages'][0]['lines'], 0, 10) : [];
$debugOutput = "Settings: cols=" . ($baseline['settings']['cols'] ?? 'NOT SET') . ", wrap_lines=" . ($baseline['settings']['sections']['wrap_lines'] ?? 'NOT SET') . "\n\n";
foreach ($debugLines as $i => $line) {
    $len = strlen(strip_tags($line));
    $debugOutput .= "Line " . ($i+1) . " (visible len $len): " . substr(strip_tags($line), 0, 60) . "\n";
}

$modifiedResult = null;
$modifiedSettings = $baseline['settings'];
$modifiedMeta = $baseline['meta'];
$modifiedFilePath = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $modifiedSettings = coerce_post_settings($_POST);
    $modifiedMeta = coerce_post_meta($_POST);

    $modifiedInput = [
        'htmls' => $baselineInput['htmls'],
        'settings' => $modifiedSettings,
        'meta' => $modifiedMeta,
    ];
    $modifiedResult = $engine->process($modifiedInput);

    // Write modified file into tmp/
    $outHtml = "<!DOCTYPE html>\n<html><head><meta charset=\"utf-8\"><title>Modified Demo Output</title></head><body>\n";
    $outHtml .= render_pages($modifiedResult['pages'], $modifiedSettings);
    $outHtml .= "</body></html>";
    $modifiedFilePath = __DIR__ . '/../tmp/modified_demo_output.html';
    @file_put_contents($modifiedFilePath, $outHtml);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Core Demo — DocumentEngine</title>
    <link rel="stylesheet" href="assets/styles.css">
    <style>
        body { background: #0a0a0a; color: #eaeaea; }
        .split { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; align-items: start; }
        .panel { background: #111; border: 1px solid #222; border-radius: 8px; padding: 16px; }
        .panel h2 { margin: 0 0 12px; font-size: 18px; }
        .paper-wrap { overflow-y: auto; max-height: 70vh; padding: 16px; background: #0c0c0c; border-radius: 8px; }
        .kv { display: grid; grid-template-columns: 160px 1fr; gap: 6px 12px; }
        .kv dt { color: #9aa; opacity: 0.9; }
        .kv dd { margin: 0; color: #ddd; }
        .form-grid { display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 10px; }
        .form-grid .group { display: flex; flex-direction: column; gap: 6px; }
        .form-grid label { font-size: 12px; color: #aab; }
        .form-grid input[type="text"],
        .form-grid input[type="number"],
        .form-grid select,
        .form-grid textarea { padding: 8px; border: 1px solid #333; background: #0b0b0b; color: #eee; border-radius: 6px; }
        .actions { margin-top: 12px; }
        .actions button { padding: 10px 14px; background: #1e88e5; border: none; color: #fff; border-radius: 6px; cursor: pointer; }
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .page { background: #fff; color: #000; }
        .meta-list, .settings-list { font-size: 12px; }
        .section-title { font-size: 14px; margin: 14px 0 8px; color: #9bd; }
        a.file-link { color: #9bd; }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="header-content">
            <div class="logo-area"><h1>Core Demo — DocumentEngine</h1></div>
            <div class="header-actions"></div>
        </div>
    </header>

    <main class="container">
        <section class="panel" style="margin-bottom: 24px;">
            <form method="post">
                <h2>Settings & Metadata</h2>
                <div class="section-title">Metadata</div>
                <div class="form-grid">
                    <div class="group">
                        <label>Title</label>
                        <input type="text" name="meta[title]" value="<?php echo htmlspecialchars($modifiedMeta['title'] ?? ''); ?>">
                    </div>
                    <div class="group">
                        <label>Author</label>
                        <input type="text" name="meta[author]" value="<?php echo htmlspecialchars($modifiedMeta['author'] ?? ''); ?>">
                    </div>
                    <div class="group">
                        <label>Course</label>
                        <input type="text" name="meta[course]" value="<?php echo htmlspecialchars($modifiedMeta['course'] ?? ''); ?>">
                    </div>
                    <div class="group" style="grid-column: 1 / -1;">
                        <label>Citation template</label>
                        <textarea name="meta[citation_template]" rows="2"><?php echo htmlspecialchars($modifiedMeta['citation_template'] ?? ''); ?></textarea>
                    </div>
                    <div class="group">
                        <label>Metadata placement</label>
                        <select name="meta[metadata_placement]">
                            <?php $mp = $modifiedMeta['metadata_placement'] ?? 'none'; ?>
                            <option value="none" <?php echo $mp==='none'?'selected':''; ?>>none</option>
                            <option value="start" <?php echo $mp==='start'?'selected':''; ?>>start</option>
                            <option value="end" <?php echo $mp==='end'?'selected':''; ?>>end</option>
                        </select>
                    </div>
                    <div class="group">
                        <label>Statistics placement</label>
                        <select name="meta[statistics_placement]">
                            <?php $sp = $modifiedMeta['statistics_placement'] ?? 'none'; ?>
                            <option value="none" <?php echo $sp==='none'?'selected':''; ?>>none</option>
                            <option value="start" <?php echo $sp==='start'?'selected':''; ?>>start</option>
                            <option value="end" <?php echo $sp==='end'?'selected':''; ?>>end</option>
                        </select>
                    </div>
                </div>

                <div class="section-title">Page</div>
                <div class="form-grid">
                    <div class="group">
                        <label>Page size</label>
                        <select name="settings[page][page_size]">
                            <?php $ps = $modifiedSettings['page']['page_size'] ?? 'a4'; ?>
                            <option value="a3" <?php echo $ps==='a3'?'selected':''; ?>>A3</option>
                            <option value="a4" <?php echo $ps==='a4'?'selected':''; ?>>A4</option>
                            <option value="a5" <?php echo $ps==='a5'?'selected':''; ?>>A5</option>
                            <option value="custom" <?php echo $ps==='custom'?'selected':''; ?>>custom</option>
                        </select>
                    </div>
                    <div class="group">
                        <label>Orientation</label>
                        <select name="settings[page][orientation]">
                            <?php $o = $modifiedSettings['page']['orientation'] ?? 'portrait'; ?>
                            <option value="portrait" <?php echo $o==='portrait'?'selected':''; ?>>portrait</option>
                            <option value="landscape" <?php echo $o==='landscape'?'selected':''; ?>>landscape</option>
                        </select>
                    </div>
                    <div class="group">
                        <label>Width (mm)</label>
                        <input type="number" step="0.1" name="settings[page][page_width]" value="<?php echo htmlspecialchars((string)($modifiedSettings['page']['page_width'] ?? 210)); ?>">
                    </div>
                    <div class="group">
                        <label>Height (mm)</label>
                        <input type="number" step="0.1" name="settings[page][page_height]" value="<?php echo htmlspecialchars((string)($modifiedSettings['page']['page_height'] ?? 297)); ?>">
                    </div>
                    <div class="group">
                        <label>Margin (mm)</label>
                        <input type="number" step="0.1" name="settings[page][margin]" value="<?php echo htmlspecialchars((string)($modifiedSettings['page']['margin'] ?? 18)); ?>">
                    </div>
                    <div class="group">
                        <label>Font size (pt)</label>
                        <input type="number" step="1" name="settings[page][font_size]" value="<?php echo htmlspecialchars((string)($modifiedSettings['page']['font_size'] ?? 12)); ?>">
                    </div>
                    <div class="group">
                        <label>Line spacing</label>
                        <select name="settings[page][line_spacing]">
                            <?php $ls = $modifiedSettings['page']['line_spacing'] ?? 'normal'; ?>
                            <option value="normal" <?php echo $ls==='normal'?'selected':''; ?>>normal</option>
                            <option value="wide" <?php echo $ls==='wide'?'selected':''; ?>>wide</option>
                            <option value="tight" <?php echo $ls==='tight'?'selected':''; ?>>tight</option>
                        </select>
                    </div>
                    <div class="group">
                        <label>Words limit per page</label>
                        <input type="number" step="1" name="settings[page][words]" value="<?php echo htmlspecialchars((string)($modifiedSettings['page']['words'] ?? 0)); ?>">
                    </div>
                    <div class="group">
                        <label>Lines limit per page</label>
                        <input type="number" step="1" name="settings[page][lines]" value="<?php echo htmlspecialchars((string)($modifiedSettings['page']['lines'] ?? 0)); ?>">
                    </div>
                </div>

                <div class="section-title">Pagination</div>
                <div class="form-grid">
                    <div class="group">
                        <label><input type="checkbox" name="settings[pagination][show_page_numbers]" <?php echo (($modifiedSettings['pagination']['show_page_numbers'] ?? 1) ? 'checked' : ''); ?>> Show page numbers</label>
                    </div>
                    <div class="group">
                        <label><input type="checkbox" name="settings[pagination][no_number_on_first]" <?php echo (($modifiedSettings['pagination']['no_number_on_first'] ?? 0) ? 'checked' : ''); ?>> No number on first</label>
                    </div>
                    <div class="group">
                        <label>Position</label>
                        <select name="settings[pagination][page_number_pos]">
                            <?php $pp = $modifiedSettings['pagination']['page_number_pos'] ?? 'footer'; ?>
                            <option value="footer" <?php echo $pp==='footer'?'selected':''; ?>>footer</option>
                            <option value="header" <?php echo $pp==='header'?'selected':''; ?>>header</option>
                        </select>
                    </div>
                    <div class="group">
                        <label>Format</label>
                        <select name="settings[pagination][page_number_format]">
                            <?php $pf = $modifiedSettings['pagination']['page_number_format'] ?? '123'; ?>
                            <option value="123" <?php echo $pf==='123'?'selected':''; ?>>123</option>
                            <option value="roman-upper" <?php echo $pf==='roman-upper'?'selected':''; ?>>roman-upper</option>
                            <option value="roman-lower" <?php echo $pf==='roman-lower'?'selected':''; ?>>roman-lower</option>
                            <option value="latin-upper" <?php echo $pf==='latin-upper'?'selected':''; ?>>latin-upper</option>
                            <option value="latin-lower" <?php echo $pf==='latin-lower'?'selected':''; ?>>latin-lower</option>
                            <option value="bg-upper" <?php echo $pf==='bg-upper'?'selected':''; ?>>bg-upper</option>
                            <option value="bg-lower" <?php echo $pf==='bg-lower'?'selected':''; ?>>bg-lower</option>
                        </select>
                    </div>
                    <div class="group" style="grid-column: 1 / -1;">
                        <label>Template</label>
                        <input type="text" name="settings[pagination][page_number_template]" value="<?php echo htmlspecialchars($modifiedSettings['pagination']['page_number_template'] ?? '{author} • {title} • стр. {page}/{total}'); ?>">
                    </div>
                    <div class="group">
                        <label>Line numbers</label>
                        <select name="settings[pagination][line_numbers_mode]">
                            <?php $ln = $modifiedSettings['pagination']['line_numbers_mode'] ?? 'none'; ?>
                            <option value="none" <?php echo $ln==='none'?'selected':''; ?>>none</option>
                            <option value="page" <?php echo $ln==='page'?'selected':''; ?>>page</option>
                            <option value="doc" <?php echo $ln==='doc'?'selected':''; ?>>doc</option>
                        </select>
                    </div>
                    <div class="group">
                        <label>Line numbers placement</label>
                        <select name="settings[pagination][line_numbers_placement]">
                            <?php $lp = $modifiedSettings['pagination']['line_numbers_placement'] ?? 'margin'; ?>
                            <option value="margin" <?php echo $lp==='margin'?'selected':''; ?>>margin</option>
                            <option value="inline" <?php echo $lp==='inline'?'selected':''; ?>>inline</option>
                        </select>
                    </div>
                </div>

                <div class="section-title">Sections</div>
                <div class="form-grid">
                    <div class="group">
                        <label><input type="checkbox" name="settings[sections][new_page_on_header]" <?php echo (($modifiedSettings['sections']['new_page_on_header'] ?? 1) ? 'checked' : ''); ?>> New page on header</label>
                    </div>
                    <div class="group">
                        <label><input type="checkbox" name="settings[sections][new_page_on_file]" <?php echo (($modifiedSettings['sections']['new_page_on_file'] ?? 1) ? 'checked' : ''); ?>> New page on file</label>
                    </div>
                    <div class="group">
                        <label><input type="checkbox" name="settings[sections][file_name_as_section]" <?php echo (($modifiedSettings['sections']['file_name_as_section'] ?? 1) ? 'checked' : ''); ?>> File name as section</label>
                    </div>
                    <div class="group">
                        <label>Wrap long lines</label>
                        <select name="settings[sections][wrap_lines]">
                            <?php $wr = $modifiedSettings['sections']['wrap_lines'] ?? 'yes'; ?>
                            <option value="yes" <?php echo $wr==='yes'?'selected':''; ?>>yes</option>
                            <option value="no" <?php echo $wr==='no'?'selected':''; ?>>no</option>
                        </select>
                    </div>
                </div>

                <div class="section-title">Advanced</div>
                <div class="form-grid">
                    <div class="group">
                        <label>Cols</label>
                        <input type="number" step="1" name="settings[cols]" value="<?php echo htmlspecialchars((string)($modifiedSettings['cols'] ?? 80)); ?>">
                    </div>
                    <div class="group">
                        <label>Lines per page</label>
                        <input type="number" step="1" name="settings[lines_per_page]" value="<?php echo htmlspecialchars((string)($modifiedSettings['lines_per_page'] ?? 50)); ?>">
                    </div>
                </div>

                <div class="actions"><button type="submit">Generate modified</button></div>
            </form>
        </section>
        <div class="split">
            <section class="panel">
                <h2>Original (Baseline)</h2>
                <div class="section-title">Preview</div>
                <div class="paper-wrap">
                    <?php echo render_pages($baselineResult['pages'], $baseline['settings']); ?>
                </div>
                <div class="section-title">Settings</div>
                <div style="margin-bottom: 10px; padding: 10px; background: #f0f0f0; border-radius: 4px; color: #333; font-size: 12px;">
                    <strong>Debug:</strong> Baseline page 1 has <?php echo $debugLineCount; ?> lines<br>
                    <pre style="background: white; padding: 5px; border-radius: 3px; overflow-x: auto; margin-top: 5px;"><?php echo htmlspecialchars($debugOutput); ?></pre>
                </div>
                <div class="settings-list"><?php echo dump_kv($baseline['settings']); ?></div>
                <div class="section-title">Metadata</div>
                <div class="meta-list"><?php echo dump_kv($baseline['meta']); ?></div>
            </section>
            <section class="panel">
                <h2>Modified</h2>
                <div class="section-title">Preview</div>
                <div class="paper-wrap">
                    <?php if ($modifiedResult) { echo render_pages($modifiedResult['pages'], $modifiedSettings); } else { echo '<div style="opacity:.7">Submit form to generate modified preview.</div>'; } ?>
                </div>
                <div class="section-title">Settings</div>
                <div class="settings-list"><?php echo dump_kv($modifiedSettings); ?></div>
                <div class="section-title">Metadata</div>
                <div class="meta-list"><?php echo dump_kv($modifiedMeta); ?></div>
                <?php if ($modifiedFilePath && file_exists($modifiedFilePath)): ?>
                    <div class="section-title">Output file</div>
                    <div><a class="file-link" href="<?php echo htmlspecialchars('../tmp/modified_demo_output.html'); ?>" target="_blank">Open modified_demo_output.html</a></div>
                <?php endif; ?>
            </section>
        </div>
    </main>
</body>
</html>
