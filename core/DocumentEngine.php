<?php
declare(strict_types=1);

final class DocumentEngine
{
    public function process(array $input): string
    {
        $settings = $input['settings'] ?? [];
        $meta     = $input['meta'] ?? [];
        $files    = $input['htmls'] ?? [];

        if (!is_array($files) || count($files) === 0) {
            throw new RuntimeException("Няма входни HTML документи.");
        }

        $spec = PageSpec::fromSettings($settings);

        $parser = new HtmlBlockParser();
        $blocks = [];

        $sections = $settings['sections'] ?? [];
        $newPageOnFile     = !empty($sections['new_page_on_file']);
        $fileNameAsSection = !empty($sections['file_name_as_section']);

        foreach ($files as $idx => $f) {
            $fileName = (string)($f['name'] ?? ('file_' . ($idx + 1) . '.html'));
            $html     = (string)($f['html'] ?? '');

            if ($idx > 0 && $newPageOnFile) {
                $blocks[] = ['type' => 'pagebreak'];
            }

            if ($fileNameAsSection) {
                $blocks[] = [
                    'type' => 'header',
                    'level' => 2,
                    'text' => pathinfo($fileName, PATHINFO_FILENAME),
                    'file' => $fileName,
                    'is_file_title' => true,
                ];
                $blocks[] = ['type' => 'blank', 'file' => $fileName];
            }

            foreach ($parser->parse($html) as $b) {
                $b['file'] = $fileName;
                $blocks[] = $b;
            }
        }

        $paginator = new Paginator($spec, $settings, $meta);
        $contentPages = $paginator->paginate($blocks);
        // Stats page based on content (без meta/stats)
        $stats = DocumentStats::fromPages($contentPages);

        $blocks = $this->applyMetaAndStats($blocks, $spec, $meta, $stats);
        $finalPages = $paginator->paginate($blocks);

        // Meta / Stats pages
        // $finalPages = $this->applyMetaAndStats($contentPages, $spec, $settings, $meta);

        // Render full printable HTML
        $renderer = new PrintHtmlRenderer($spec, $settings, $meta);
        return $renderer->render($finalPages);
    }

    private function applyMetaAndStats(array $blocks, PageSpec $spec, array $meta, DocumentStats $stats): array
    {
        $metaPlacement  = (string)($meta['metadata_placement'] ?? 'none');     // start|end|none
        $statsPlacement = (string)($meta['statistics_placement'] ?? 'none');   // start|end|none

        // Ако са изключени – директно връщаме
        $metaBlocks  = [];
        $statsBlocks = [];

        if ($metaPlacement !== 'none') {
            $metaBlocks = SpecialPages::metadataBlocks($spec, $meta);
        }

        if ($statsPlacement !== 'none') {
            $statsBlocks = SpecialPages::statisticsBlocks($spec, $stats);
        }

        // helper: добавя pagebreak след секция, за да е като отделна "страница/част"
        $withBreak = function (array $sectionBlocks): array {
            if (empty($sectionBlocks)) return [];
            // гарантираме да не останем с pagebreak без нищо
            return array_merge($sectionBlocks, [
                ['type' => 'pagebreak'],
            ]);
        };

        // Важно: ако metadata/stats са "start", искаме да са преди основния документ
        $prefix = [];
        if ($metaPlacement === 'start') {
            $prefix = array_merge($prefix, $withBreak($metaBlocks));
        }
        if ($statsPlacement === 'start') {
            $prefix = array_merge($prefix, $withBreak($statsBlocks));
        }

        // "end" секции – след основния документ
        $suffix = [];
        if ($metaPlacement === 'end') {
            $suffix = array_merge($suffix, $withBreak($metaBlocks));
        }
        if ($statsPlacement === 'end') {
            $suffix = array_merge($suffix, $withBreak($statsBlocks));
        }

        // Сглобяване
        if (!empty($prefix)) {
            $blocks = array_merge($prefix, $blocks);
        }
        if (!empty($suffix)) {
            $blocks = array_merge($blocks, $suffix);
        }

        // Ако накрая има pagebreak, махаме го (за да не прави празна последна страница)
        for ($i = count($blocks) - 1; $i >= 0; $i--) {
            if (!is_array($blocks[$i])) break;
            if (($blocks[$i]['type'] ?? '') === 'pagebreak') {
                array_pop($blocks);
                continue;
            }
            break;
        }

        return $blocks;
    }
}

final class PageSpec
{
    public int $pageWidthMm;
    public int $pageHeightMm;
    public int $marginMm;
    public int $fontSizePt;

    /**
     * Ако потребителят зададе лимит за НЕпразни редове на страница (lines > 0),
     * ще използваме този лимит. Ако lines = 0 -> ще страницираме по височина (mm).
     */
    public int $maxLinesPerPage;

    /** 0 = без лимит */
    public int $maxWordsPerPage;

    /** max chars per line (включва корекции за line numbers) */
    public int $maxCharsPerLine;

    public bool $wrapLines;

    /** line-height multiplier за редове с текст (НЕ зависи от line_spacing) */
    public float $textLineHeight;

    /** multiplier за празни редове спрямо текстов ред (зависи от line_spacing) */
    public float $blankLineMult;

    /** usable content height (между горен/долен марж), в mm */
    public float $usableHeightMm;

    /** височина на текстов ред в mm */
    public float $textLineHeightMm;

    /** височина на празен ред в mm */
    public float $blankLineHeightMm;

    public int $lineSpacing;


    public static function fromSettings(array $settings): self
    {
        $page = $settings['page'] ?? [];
        $pagination = $settings['pagination'] ?? [];
        $sections = $settings['sections'] ?? [];

        $pageSize = (string)($page['page_size'] ?? 'a4');
        $orientation = (string)($page['orientation'] ?? 'portrait');

        [$w, $h] = self::pageSizeToMm($pageSize);

        $customW = (int)($page['page_width'] ?? 0);
        $customH = (int)($page['page_height'] ?? 0);

        if ($pageSize === 'custom') {
            $w = $customW > 0 ? $customW : $w;
            $h = $customH > 0 ? $customH : $h;
        } else {
            // Ако input-ът е 0 (disabled inputs), пак имаме стойности от page_size
            // и не разчитаме на page_width/page_height
        }

        if ($orientation === 'landscape') {
            [$w, $h] = [$h, $w];
        }

        $margin = (int)($page['margin'] ?? 18);
        if ($margin < 0) $margin = 0;

        $fontSize = (int)($page['font_size'] ?? 12);
        if ($fontSize <= 0) $fontSize = 12;

        $lineSpacing = (int)($page['line_spacing'] ?? 2);

        $textLineHeight = 1.2;

        $blankMultMap = [
            1 => 1.0, // tight
            2 => 1.5, // normal
            3 => 2.00, // wide
        ];
        $blankMult = $blankMultMap[$lineSpacing] ?? 1.5;

        $wrapLines = ((string)($sections['wrap_lines'] ?? 'yes')) === 'yes';

        $maxWords = (int)($page['words'] ?? 0);
        $maxLines = (int)($page['lines'] ?? 0); // 0 => height-based режим

        // usable height (content area). При layout-а ни content е между горен/долен марж.
        $usableHeightMm = max(1.0, $h - 2 * $margin);

        // pt -> mm: 1pt = 0.3527778mm
        $textLineHeightMm  = max(0.1, ($fontSize * 0.3527778) * $textLineHeight);
        $blankLineHeightMm = max(0.1, $textLineHeightMm * $blankMult);

        // ако user е задал lines > 0, ще броим НЕпразните редове.
        // ако lines = 0, Paginator ще работи по височина (used_height_mm vs usableHeightMm).
        $maxLines = max(0, $maxLines);

        // chars/line (приблизително, но достатъчно стабилно за “машинописен” вид) (приблизително, но достатъчно стабилно за “машинописен” вид)
        $usableWidthMm = max(1, $w - 2 * $margin);
        $usableWidthPt = $usableWidthMm * 2.83465;

        $charWidthPt = max(1.0, $fontSize * 0.60); // приблизително за Courier/mono
        $maxChars = (int)floor($usableWidthPt / $charWidthPt);

        // Ако показваме номера на редове вляво, оставяме място
        $lineNumbersMode = (string)($pagination['line_numbers_mode'] ?? 'none');
        $lineNumbersPlacement = (string)($pagination['line_numbers_placement'] ?? 'margin');
        if ($lineNumbersMode !== 'none') {
            // 6 символа за " 1234 " + малко буфер
            $maxChars -= ($lineNumbersPlacement === 'margin') ? 6 : 6;
        }

        if ($maxChars < 20) $maxChars = 20;

        $spec = new self();
        $spec->pageWidthMm = $w;
        $spec->pageHeightMm = $h;
        $spec->marginMm = $margin;
        $spec->fontSizePt = $fontSize;

        $spec->maxLinesPerPage = $maxLines; // 0 => height-based в Paginator
        $spec->maxWordsPerPage = max(0, $maxWords);
        $spec->maxCharsPerLine = $wrapLines ? $maxChars : PHP_INT_MAX;
        $spec->wrapLines = $wrapLines;

        $spec->textLineHeight = $textLineHeight;
        $spec->blankLineMult  = $blankMult;

        $spec->usableHeightMm = $usableHeightMm;
        $spec->textLineHeightMm = $textLineHeightMm;
        $spec->blankLineHeightMm = $blankLineHeightMm;

        $spec->lineSpacing = $lineSpacing;

        return $spec;
    }

    private static function pageSizeToMm(string $pageSize): array
    {
        switch ($pageSize) {
            case 'a3': return [297, 420];
            case 'a5': return [148, 210];
            case 'a4':
            default:   return [210, 297];
        }
    }
}

final class HtmlBlockParser
{
    public function parse(string $html): array
    {
        $html = trim($html);
        if ($html === '') return [];

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();

        self::pruneIgnoredNodes($doc);

        $body = $doc->getElementsByTagName('body')->item(0);
        if (!$body) {
            $body = $doc;
        }

        $blocks = [];
        foreach ($body->childNodes as $child) {
            $this->walk($child, $blocks);
        }

        return $this->compactBlanks($blocks);
    }

    private static function pruneIgnoredNodes(\DOMDocument $doc): void
    {
        $xpath = new \DOMXPath($doc);

        // Тагове, които искаме да игнорираме И съдържанието им
        $ignore = [
            'script', 'style', 'noscript',
            'img', 'svg', 'canvas',
            'video', 'audio',
            'iframe', 'object', 'embed',
            'form', 'input', 'textarea', 'select', 'button',
            // важно за твоя пример:
            'figcaption', // маха и figcaption
            'caption',
            // по желание (ако не ги искаш в печата):
            'nav', 'aside'
        ];

        foreach ($ignore as $tag) {
            /** @var \DOMNodeList $nodes */
            $nodes = $xpath->query('//' . $tag);
            if (!$nodes) continue;

            // DOMNodeList е "live", затова махаме отзад напред
            for ($i = $nodes->length - 1; $i >= 0; $i--) {
                $node = $nodes->item($i);
                if ($node && $node->parentNode) {
                    $node->parentNode->removeChild($node);
                }
            }
        }
    }


    private function hasBlockChildren(\DOMElement $node): bool
    {
        foreach ($node->childNodes as $ch) {
            if (!($ch instanceof \DOMElement)) continue;
            $t = strtolower($ch->tagName);
            if (preg_match('/^h[1-6]$/', $t)) return true;
            if (in_array($t, ['p','div','pre','ul','ol','li','hr','table','section','article','header','footer','blockquote'], true)) {
                return true;
            }
        }
        return false;
    }

    private function walk(DOMNode $node, array &$blocks): void
    {
        // Raw text node (fallback)
        if ($node instanceof DOMText) {
            $t = trim($node->nodeValue ?? '');
            if ($t !== '') {
                $blocks[] = ['type' => 'paragraph', 'html' => InlineHtml::renderText($t), 'is_html' => 1];
                $blocks[] = ['type' => 'blank'];
            }
            return;
        }

        if (!($node instanceof DOMElement)) {
            return;
        }

        $tag = strtolower($node->tagName);

        // Headers h1..h6
        if (preg_match('/^h[1-6]$/', $tag)) {
            $html = InlineHtml::renderChildren($node);
            if ($html !== '') {
                $blocks[] = [
                    'type'  => 'header',
                    'level' => (int)substr($tag, 1),
                    'html'  => $html,
                    'is_html' => 1,
                ];
                $blocks[] = ['type' => 'blank'];
            }
            return;
        }

        // Preformatted text
        if ($tag === 'pre') {
            $text = rtrim($node->textContent ?? '', "\r\n");
            $lines = preg_split("/\r\n|\r|\n/", $text) ?: [];
            $blocks[] = ['type' => 'pre', 'lines' => $lines];
            $blocks[] = ['type' => 'blank'];
            return;
        }

        // Standalone <code> (not inside <pre>) -> treat as pre
        if ($tag === 'code') {
            $parentTag = '';
            if ($node->parentNode instanceof DOMElement) {
                $parentTag = strtolower($node->parentNode->tagName);
            }
            if ($parentTag !== 'pre') {
                $text = rtrim($node->textContent ?? '', "\r\n");
                $lines = preg_split("/\r\n|\r|\n/", $text) ?: [$text];
                $blocks[] = ['type' => 'pre', 'lines' => $lines];
                $blocks[] = ['type' => 'blank'];
                return;
            }
            // иначе ще се обработи от <pre>
        }

        // Horizontal rule
        if ($tag === 'hr') {
            $blocks[] = ['type' => 'hr'];
            $blocks[] = ['type' => 'blank'];
            return;
        }

        // Lists: render each <li> as bullet item; ul/ol are just containers
        if ($tag === 'ul' || $tag === 'ol') {
            foreach ($node->childNodes as $child) {
                $this->walk($child, $blocks);
            }
            // Separate list from following content
            $blocks[] = ['type' => 'blank'];
            return;
        }

        // List item
        if ($tag === 'li') {
            $html = InlineHtml::renderChildren($node);
            if ($html !== '') {
                $blocks[] = ['type' => 'li', 'html' => $html, 'is_html' => 1];
            }
            return;
        }

        // Paragraph-like containers
        if ($tag === 'p') {
            $html = InlineHtml::renderChildren($node);
            if ($html !== '') {
                $blocks[] = ['type' => 'paragraph', 'html' => $html, 'is_html' => 1];
                $blocks[] = ['type' => 'blank'];
            }
            return;
        }

        // <div> може да е контейнер с други блокови елементи (h1/h2/ul/pre/...)
        // Ако има блокови деца, НЕ го третираме като paragraph, а обхождаме децата му.
        if ($tag === 'div') {
            if ($this->hasBlockChildren($node)) {
                foreach ($node->childNodes as $child) {
                    $this->walk($child, $blocks);
                }
                return;
            }

            $html = InlineHtml::renderChildren($node);
            if ($html !== '') {
                $blocks[] = ['type' => 'paragraph', 'html' => $html, 'is_html' => 1];
                $blocks[] = ['type' => 'blank'];
            }
            return;
        }

        // Line break
        if ($tag === 'br') {
            $blocks[] = ['type' => 'blank'];
            return;
        }

        // Default: walk children
        foreach ($node->childNodes as $child) {
            $this->walk($child, $blocks);
        }
    }


    private function textWithBreaks(DOMElement $el): string
    {
        $out = '';
        foreach ($el->childNodes as $ch) {
            if ($ch instanceof DOMElement && strtolower($ch->tagName) === 'br') {
                //$out .= "\n";
                $out .= " ";
                continue;
            }
            $out .= $ch->textContent ?? '';
        }
        $out = preg_replace('/\s+/u', ' ', $out);
        return $out;
    }

    private function compactBlanks(array $blocks): array
    {
        $out = [];
        $prevBlank = false;

        foreach ($blocks as $b) {
            if (($b['type'] ?? '') === 'blank') {
                if ($prevBlank) continue;
                $prevBlank = true;
                $out[] = $b;
                continue;
            }
            $prevBlank = false;
            $out[] = $b;
        }

        return $out;
    }
}


final class InlineHtml
{
    private static array $allowed = [
        'strong' => true,
        'em'     => true,
        'i'      => true,
        'u'      => true,
    ];

    public static function renderText(string $text): string
    {
        // NBSP -> space
        $text = str_replace("\xC2\xA0", ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function renderChildren(\DOMElement $el): string
    {
        $out = '';
        for ($ch = $el->firstChild; $ch; $ch = $ch->nextSibling) {
            $out .= self::renderNode($ch);
        }
        // collapse whitespace (safe because we only output minimal inline tags)
        $out = str_replace("\xC2\xA0", ' ', $out);
        $out = preg_replace('/\s+/u', ' ', $out) ?? $out;
        return trim($out);
    }

    private static function renderNode(\DOMNode $node): string
    {
        if ($node instanceof \DOMText) {
            return htmlspecialchars($node->nodeValue ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        if (!($node instanceof \DOMElement)) {
            return '';
        }

        $tag = strtolower($node->tagName);

        // Keep <br> as a soft space (reflow-friendly)
        if ($tag === 'br') {
            return ' ';
        }

        $inner = '';
        for ($ch = $node->firstChild; $ch; $ch = $ch->nextSibling) {
            $inner .= self::renderNode($ch);
        }

        if (!isset(self::$allowed[$tag])) {
            // Unknown tags: ignore the tag but keep its content
            return $inner;
        }

        // No attributes allowed (whitelist only)
        return '<' . $tag . '>' . $inner . '</' . $tag . '>';
    }
}

final class InlineWrap
{
    /**
     * Wrap safe inline HTML (only <strong>, <em>, <i>, <u>) by visible text length.
     * Returns array of HTML lines (without surrounding block tags).
     */
    public static function wrap(string $html, int $maxChars, bool $wrap): array
    {
        $html = trim($html);
        if ($html === '') return [''];

        if (!$wrap || $maxChars === PHP_INT_MAX) {
            return [$html];
        }

        // Tokenize tags vs text
        $tokens = preg_split('/(<\/?(?:strong|em|i|u)>)/i', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        if (!$tokens) $tokens = [$html];

        $lines = [];
        $buf = '';
        $visible = 0;
        $open = [];

        $flush = function() use (&$lines, &$buf, &$visible, &$open) {
            // Close any open tags
            $close = '';
            for ($i = count($open) - 1; $i >= 0; $i--) {
                $close .= '</' . $open[$i] . '>';
            }
            $lines[] = trim($buf . $close);
            // Re-open tags for next line
            $reopen = '';
            foreach ($open as $t) {
                $reopen .= '<' . $t . '>';
            }
            $buf = $reopen;
            $visible = 0;
        };

        $appendText = function(string $text) use (&$buf, &$visible, $maxChars, &$flush) {
            $text = str_replace("\xC2\xA0", ' ', $text);
            $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

            // split into words keeping spaces
            $parts = preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
            if (!$parts) $parts = [$text];

            foreach ($parts as $p) {
                $isSpace = preg_match('/^\s+$/u', $p) === 1;
                $pLen = mb_strlen($p, 'UTF-8');

                if ($isSpace) {
                    // don't start a line with spaces
                    if ($visible === 0) continue;
                    $buf .= ' ';
                    $visible += 1;
                    continue;
                }

                if ($visible > 0 && ($visible + $pLen) > $maxChars) {
                    $flush();
                }

                // If a single "word" is longer than maxChars -> hard wrap
                if ($pLen > $maxChars) {
                    $chunks = TextUtil::hardWrapUtf8($p, $maxChars);
                    foreach ($chunks as $ci => $chunk) {
                        if ($ci > 0) $flush();
                        $buf .= $chunk;
                        $visible = mb_strlen($chunk, 'UTF-8');
                    }
                } else {
                    $buf .= $p;
                    $visible += $pLen;
                }
            }
        };

        foreach ($tokens as $tok) {
            if (preg_match('/^<\s*\/\s*(strong|em|i|u)\s*>$/i', $tok, $m)) {
                $tag = strtolower($m[1]);
                // pop if matches
                for ($j = count($open) - 1; $j >= 0; $j--) {
                    if ($open[$j] === $tag) {
                        array_splice($open, $j, 1);
                        break;
                    }
                }
                $buf .= '</' . $tag . '>';
                continue;
            }

            if (preg_match('/^<\s*(strong|em|i|u)\s*>$/i', $tok, $m)) {
                $tag = strtolower($m[1]);
                $open[] = $tag;
                $buf .= '<' . $tag . '>';
                continue;
            }

            $appendText($tok);
        }

        if (trim(strip_tags($buf)) !== '' || trim($buf) !== '') {
            $flush();
        }

        // Remove possible empty last line
        $lines = array_values(array_filter($lines, static function($x){ return $x !== ''; }));
        return $lines ?: [''];
    }

    public static function wrapWithBullet(string $html, int $maxChars, bool $wrap): array
    {
        $bullet = '• ';
        $indent = '  ';

        $lines = self::wrap($html, $maxChars - 2, $wrap); // reserve for bullet/indent
        if (!$lines) return [''];

        $out = [];
        foreach ($lines as $i => $ln) {
            $prefix = ($i === 0) ? $bullet : $indent;
            $out[] = $prefix . $ln;
        }
        return $out;
    }
}


final class Paginator
{
    /** @var PageSpec */
    private $spec;
    /** @var array */
    private $settings;
    /** @var array */
    private $meta;

    public function __construct(PageSpec $spec, array $settings, array $meta)
    {
        $this->spec = $spec;
        $this->settings = $settings;
        $this->meta = $meta;
    }

    public function paginate(array $blocks): array
    {
        $pages = [];
        $pages[] = $this->newPage('content'); // първа страница

        $sections = $this->settings['sections'] ?? [];
        $newPageOnHeader = !empty($sections['new_page_on_header']);

        $levelsRaw = $sections['header_page_break_levels'] ?? null;

        // normalize -> set: [level => true]
        $levelSet = [];
        if (is_array($levelsRaw)) {
            foreach ($levelsRaw as $v) {
                $n = (int)$v;
                if ($n >= 1 && $n <= 6) {
                    $levelSet[$n] = true;
                }
            }
        }

        foreach ($blocks as $b) {
            $type = (string)($b['type'] ?? 'paragraph');

            // текущата страница е последната
            $pageIndex = count($pages) - 1;

            if ($type === 'pagebreak') {
                if (!$this->isPageEmpty($pages[$pageIndex])) {
                    $pages[] = $this->newPage('content');
                }
                continue;
            }

            if ($type === 'header' && $newPageOnHeader && !$this->isPageEmpty($pages[$pageIndex])) {
                $lvl = (int)($b['level'] ?? 0);
                
                $shouldBreak = ($lvl >= 1 && $lvl <= 6) && (isset($levelSet[$lvl]));

                if ($shouldBreak) {
                    $pages[] = $this->newPage('content');
                    $pageIndex = count($pages) - 1;
                }
            }

            $file = (string)($b['file'] ?? '');
            if ($file !== '') {
                $pages[$pageIndex]['file'] = $file;
            }

            $lines = $this->blockToLines($b);
            foreach ($lines as $line) {
                $this->pushLineWithLimits($pages, $line, $file);
            }
        }

        // trim trailing blank lines per page
        foreach ($pages as $i => $p) {
            while (!empty($pages[$i]['lines']) && end($pages[$i]['lines']) === '') {
                array_pop($pages[$i]['lines']);
            }
        }

        return $pages;
    }


    private function newPage(string $type): array
    {
        return [
            'type' => $type,
            'file' => '',
            'lines' => [],
            'word_count' => 0,
            'non_empty_line_count' => 0,
            'used_height_mm' => 0.0,
        ];
    }

    private function isPageEmpty(array $page): bool
    {
        return empty($page['non_empty_line_count']);
    }

    private function pushLineWithLimits(array &$pages, $row, string $file): void
    {
        // Normalize row -> plain text for counting, plus store original row for rendering
        $isHtmlRow = is_array($row) && !empty($row['is_html']);
        if ($isHtmlRow) {
            $plain = (string)($row['html'] ?? '');
            $plain = trim(strip_tags($plain));
            $lineForCounts = $plain;
        } else {
            $lineForCounts = is_array($row) ? (string)($row['text'] ?? '') : (string)$row;
        }

        $maxLines = $this->spec->maxLinesPerPage; // 0 => height-based
        $maxWords = $this->spec->maxWordsPerPage;

        $pageIndex = count($pages) - 1;

        $isEmptyLine = (trim($lineForCounts) === '');
        $lineWords   = TextUtil::countWords($lineForCounts);

        // 1) word limit (0 = no limit)
        if ($maxWords > 0
            && ($pages[$pageIndex]['word_count'] + $lineWords) > $maxWords
            && !$this->isPageEmpty($pages[$pageIndex])
        ) {
            $pages[] = $this->newPage('content');
            $pageIndex = count($pages) - 1;
            $pages[$pageIndex]['file'] = $file;
        }

        // 2) lines limit (по НЕпразни редове) или height-based
        if ($maxLines > 0) {
            if (!$isEmptyLine
                && $pages[$pageIndex]['non_empty_line_count'] >= $maxLines
                && !$this->isPageEmpty($pages[$pageIndex])
            ) {
                $pages[] = $this->newPage('content');
                $pageIndex = count($pages) - 1;
                $pages[$pageIndex]['file'] = $file;
            }
        } else {
            // height-based (mm)
            $lineHeightMm = $isEmptyLine ? $this->spec->blankLineHeightMm : $this->spec->textLineHeightMm;

            if (($pages[$pageIndex]['used_height_mm'] + $lineHeightMm) > $this->spec->usableHeightMm
                && !$this->isPageEmpty($pages[$pageIndex])
            ) {
                $pages[] = $this->newPage('content');
                $pageIndex = count($pages) - 1;
                $pages[$pageIndex]['file'] = $file;
            }
        }

        // 3) add row (вкл. празен)
        if (!is_array($row)) {
            $row = ['text' => (string)$row, 'is_html' => 0];
        } else {
            // ensure keys exist
            if (!array_key_exists('is_html', $row)) $row['is_html'] = 0;
        }

        $pages[$pageIndex]['lines'][] = $row;
        $pages[$pageIndex]['word_count'] += $lineWords;

        // 4) count non-empty lines (празните редове не се броят)
        if (!$isEmptyLine) {
            $pages[$pageIndex]['non_empty_line_count']++;
        }

        // 5) accumulate height
        $pages[$pageIndex]['used_height_mm'] += $isEmptyLine
            ? $this->spec->blankLineHeightMm
            : $this->spec->textLineHeightMm;
    }

private function blockToLines(array $b): array
    {
        $type = (string)($b['type'] ?? 'paragraph');

        if ($type === 'blank') {
            return [''];
        }

        if ($type === 'hr') {
            $n = max(10, $this->spec->maxCharsPerLine);
            return [str_repeat('─', $n)];
        }

        if ($type === 'pre') {
            $lines = $b['lines'] ?? [];
            $out = [];
            foreach ($lines as $l) {
                $l = rtrim((string)$l, "\r\n");
                foreach (TextUtil::wrapHardOrSoft($l, $this->spec->maxCharsPerLine, $this->spec->wrapLines, false) as $w) {
                    $out[] = $w;
                }
            }
            return $out;
        }

        if ($type === 'li') {
            $html = trim((string)($b['html'] ?? ''));
            $lines = InlineWrap::wrapWithBullet($html, $this->spec->maxCharsPerLine, $this->spec->wrapLines);
            $out = [];
            foreach ($lines as $ln) {
                $out[] = ['html' => $ln, 'is_html' => 1];
            }
            return $out;
        }

        if ($type === 'header') {
            // Preserve allowed inline tags. (Uppercasing HTML safely is non-trivial; we keep original.)
            $html = trim((string)($b['html'] ?? ''));
            if ($html !== '') {
                $lines = InlineWrap::wrap($html, $this->spec->maxCharsPerLine, $this->spec->wrapLines);
                $out = [];
                foreach ($lines as $ln) {
                    $out[] = ['html' => $ln, 'is_html' => 1, 'is_header' => 1];
                }
                return $out;
            }

            // fallback (legacy)
            $text = trim((string)($b['text'] ?? ''));
            $text = mb_strtoupper($text, 'UTF-8');
            return TextUtil::wrapHardOrSoft($text, $this->spec->maxCharsPerLine, $this->spec->wrapLines, true);
        }

        // paragraph
        if (isset($b['html'])) {
            $html = (string)$b['html'];
            $lines = InlineWrap::wrap($html, $this->spec->maxCharsPerLine, $this->spec->wrapLines);
            $out = [];
            foreach ($lines as $ln) {
                $out[] = ['html' => $ln, 'is_html' => 1];
            }
            return $out;
        }

        // legacy paragraph (може да има \n)
        $text = (string)($b['text'] ?? '');
        $parts = preg_split("/\r\n|\r|\n/", $text) ?: [$text];

        $out = [];
        foreach ($parts as $p) {
            $p = TextUtil::normalizeSpaces((string)$p);
            if ($p === '') {
                $out[] = '';
                continue;
            }
            foreach (TextUtil::wrapHardOrSoft($p, $this->spec->maxCharsPerLine, $this->spec->wrapLines, true) as $w) {
                $out[] = $w;
            }
        }
        return $out;
    }
}

final class TextUtil
{
    public static function normalizeSpaces(string $s): string
    {
        $s = trim($s);

        // new
        $s = str_replace("\xC2\xA0", ' ', $s);

        $s = preg_replace('/[ \t]+/u', ' ', $s) ?? $s;
        return $s;
    }

    public static function hardWrapUtf8(string $s, int $maxChars): array
    {
        $s = (string)$s;
        if ($maxChars <= 0) return [$s];

        $out = [];
        $len = mb_strlen($s, 'UTF-8');
        for ($i = 0; $i < $len; $i += $maxChars) {
            $out[] = mb_substr($s, $i, $maxChars, 'UTF-8');
        }
        return $out ?: [''];
    }


    public static function countWords(string $s): int
    {
        $s = trim($s);
        if ($s === '') return 0;
        if (preg_match_all('/\p{L}[\p{L}\p{N}_-]*/u', $s, $m)) {
            return count($m[0]);
        }
        return 0;
    }

    public static function wrapHardOrSoft(string $line, int $maxChars, bool $wrap, bool $softWrapByWords): array
    {
        if (!$wrap || $maxChars === PHP_INT_MAX) {
            return [$line];
        }

        $lineLen = mb_strlen($line, 'UTF-8');
        if ($lineLen <= $maxChars) return [$line];

        if (!$softWrapByWords) {
            return self::hardSplit($line, $maxChars);
        }

        // soft wrap by words, with hard fallback for very long tokens
        $words = preg_split('/\s+/u', $line) ?: [$line];
        $out = [];
        $cur = '';

        foreach ($words as $w) {
            $w = (string)$w;
            if ($w === '') continue;

            $cand = ($cur === '') ? $w : ($cur . ' ' . $w);

            if (mb_strlen($cand, 'UTF-8') <= $maxChars) {
                $cur = $cand;
                continue;
            }

            if ($cur !== '') {
                $out[] = $cur;
                $cur = '';
            }

            if (mb_strlen($w, 'UTF-8') > $maxChars) {
                $chunks = self::hardSplit($w, $maxChars);
                foreach (array_slice($chunks, 0, -1) as $c) {
                    $out[] = $c;
                }
                $cur = (string)end($chunks);
            } else {
                $cur = $w;
            }
        }

        if ($cur !== '') $out[] = $cur;
        return $out;
    }

    public static function rowToPlainText($row): string
    {
        if (is_array($row)) {
            if (!empty($row['is_html'])) {
                return strip_tags((string)($row['html'] ?? ''));
            }
            return (string)($row['text'] ?? '');
        }
        return (string)$row;
    }

    private static function hardSplit(string $s, int $maxChars): array
    {
        $out = [];
        $len = mb_strlen($s, 'UTF-8');
        for ($i = 0; $i < $len; $i += $maxChars) {
            $out[] = mb_substr($s, $i, $maxChars, 'UTF-8');
        }
        return $out ?: [''];
    }
}

final class DocumentStats
{
    /** @var int */
    public int $totalPages;
    /** @var int */
    public int $totalLines;
    /** @var int */
    public int $totalWords;
    /** @var float */
    public float $avgLinesPerPage;

    public function __construct(int $totalPages, int $totalLines, int $totalWords, float $avgLinesPerPage) 
    {
        $this->totalPages = $totalPages;
        $this->totalLines = $totalLines;
        $this->totalWords = $totalWords;
        $this->avgLinesPerPage = $avgLinesPerPage;
    }

    public static function fromPages(array $pages): self
    {
        $totalPages = count($pages);
        $totalLines = 0;
        $totalWords = 0;

        foreach ($pages as $p) {
            $lines = $p['lines'] ?? [];
            $totalLines += count($lines);
            foreach ($lines as $l) {
                $totalWords += TextUtil::countWords(TextUtil::rowToPlainText($l));
            }
        }

        $avg = $totalPages > 0 ? ($totalLines / $totalPages) : 0.0;

        return new self($totalPages, $totalLines, $totalWords, $avg);
    }
}

final class SpecialPages
{
    public static function metadataBlocks(PageSpec $spec, array $meta): array
    {
        $date = date('d.m.Y H:i');

        $title  = (string)($meta['title'] ?? '');
        $author = (string)($meta['author'] ?? '');
        $course = (string)($meta['course'] ?? '');
        $cite   = (string)($meta['citation_template'] ?? '');

        $blocks = [];

        // заглавие (може да е header block)
        $blocks[] = ['type' => 'header', 'level' => 1, 'text' => 'М Е Т А Д А Н Н И'];

        // hr
        $blocks[] = ['type' => 'hr'];

        // нормални paragraph-и (те ще се wrap-ват от engine-а)
        $blocks[] = ['type' => 'paragraph', 'text' => 'Заглавие: ' . $title];
        $blocks[] = ['type' => 'paragraph', 'text' => 'Автор: ' . $author];
        $blocks[] = ['type' => 'paragraph', 'text' => 'Допълнителна информация: ' . $course];
        $blocks[] = ['type' => 'paragraph', 'text' => 'Дата на генериране: ' . $date];

        return $blocks;
    }

    public static function statisticsBlocks(PageSpec $spec, DocumentStats $stats): array
    {

        $spacing_map = [
            1 => "сгъстена",
            2 => "нормална",
            3 => "разредена",
        ];
        
        return [
            ['type' => 'header', 'level' => 1, 'text' => 'С Т А Т И С Т И К И'],
            ['type' => 'hr'],
            ['type' => 'paragraph', 'text' => 'Страници: ' . $stats->totalPages],
            ['type' => 'paragraph', 'text' => 'Редове: ' . $stats->totalLines],
            ['type' => 'paragraph', 'text' => 'Думи: ' . $stats->totalWords],
            ['type' => 'paragraph', 'text' => 'Среден брой редове на страница: ' . number_format($stats->avgLinesPerPage, 2, '.', '')],
            ['type' => 'paragraph', 'text' => 'Тип машинописна страница: ' . $spacing_map[$spec->lineSpacing]],
            ['type' => 'paragraph', 'text' => 'Височина на редовете: ' . number_format($spec->textLineHeightMm, 2, '.', '')],
            ['type' => 'paragraph', 'text' => 'Интервал между редовете: ' . number_format($spec->blankLineHeightMm, 2, '.', '')],
            
        ];
    }
}

final class PrintHtmlRenderer
{
    private PageSpec $spec;
    private array $settings;
    private array $meta;

    private array $pagination;
    private string $title;
    private string $author;

    public function __construct(PageSpec $spec, array $settings, array $meta) 
    {
        $this->spec = $spec;
        $this->settings = $settings;
        $this->meta = $meta;

        $this->pagination = $settings['pagination'] ?? [];
        $this->title  = (string)($meta['title'] ?? '');
        $this->author = (string)($meta['author'] ?? '');
    }

    public function render(array $pages): string
    {
        $totalPages = count($pages);

        $showPageNumbers  = !empty($this->pagination['show_page_numbers']);
        $noOnFirst        = !empty($this->pagination['no_number_on_first']);
        $pos              = (string)($this->pagination['page_number_pos'] ?? 'footer');
        $format           = (string)($this->pagination['page_number_format'] ?? '123');
        $tpl              = (string)($this->pagination['page_number_template'] ?? '{author} • {title} • стр. {page}/{total}');

        $lnMode      = (string)($this->pagination['line_numbers_mode'] ?? 'none');        // none/page/doc
        $lnPlacement = (string)($this->pagination['line_numbers_placement'] ?? 'margin'); // margin/inline

        // digits for line numbers
        $totalDocLines = 0;
        foreach ($pages as $p) {
            $totalDocLines += count($p['lines'] ?? []);
        }
        $lnDigits = max(2, strlen((string)max(1, $totalDocLines)));

        $css = $this->buildCss($lnPlacement, $lnDigits);

        $out = [];
        $out[] = '<!doctype html>';
        $out[] = '<html lang="bg">';
        $out[] = '<head>';
        $out[] = '  <meta charset="utf-8">';
        $out[] = '  <meta name="viewport" content="width=device-width, initial-scale=1">';
        $out[] = '  <title>Print Preview</title>';
        $out[] = '  <style>' . $css . '</style>';
        $out[] = '</head>';
        $out[] = '<body>';
        $out[] = '<div class="print-preview">';

        $docLineNo = 0;

        for ($i = 0; $i < $totalPages; $i++) {
            $pageIndex1 = $i + 1;
            $page = $pages[$i];

            $pageFile = (string)($page['file'] ?? '');
            $fn = $pageFile ? pathinfo($pageFile, PATHINFO_FILENAME) : '';

            $pageLabel = '';
            if ($showPageNumbers && !($noOnFirst && $pageIndex1 === 1)) {
                $pageLabel = $this->renderPageLabel($tpl, $format, $pageIndex1, $totalPages, $fn, $pageFile);
            }

            $out[] = '<section class="print-page">';

            $headerText = '';
            $footerText = '';

            if ($pageLabel !== '') {
                if ($pos === 'header') {
                    $headerText = $pageLabel;
                } else { // footer (default)
                    $footerText = $pageLabel;
                }
            }

            // Винаги имаме header/footer (може да са празни) – маржът "стиска" от всички страни.
            $out[] = '<div class="page-header">' . htmlspecialchars($headerText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>';
            $out[] = '<div class="page-footer">' . htmlspecialchars($footerText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>';

            $out[] = '<div class="page-inner' . ($lnPlacement === 'inline' ? ' ln-inline' : '') . '">';
            $out[] = '<div class="lines">';

            $pageLineNo = 0;
            foreach (($page['lines'] ?? []) as $row) {
                $isHtml = is_array($row) && !empty($row['is_html']);
                $lineText = '';

                if ($isHtml) {
                    $lineText = trim(strip_tags((string)($row['html'] ?? '')));
                } else {
                    $lineText = is_array($row) ? (string)($row['text'] ?? '') : (string)$row;
                }

                $num = null;
                if ($lnMode !== 'none') {
                    if ($lnMode === 'page') {
                        $pageLineNo++;
                        $num = $pageLineNo;
                    } else { // doc
                        $docLineNo++;
                        $num = $docLineNo;
                    }
                }

                $out[] = $this->renderLine($row, $lineText, $num, $lnPlacement, $lnDigits);
            }

            $out[] = '</div>';
            $out[] = '</div>';
            $out[] = '</section>';
        }

        $out[] = '</div>';
        $out[] = '</body>';
        $out[] = '</html>';

        return implode("\n", $out);
    }

    private function renderLine($row, string $plainText, ?int $num, string $placement, int $digits): string
    {
        $isEmpty = (trim($plainText) === '');
        $class = 'line' . ($isEmpty ? ' is-empty' : '');

        $isHtml = is_array($row) && !empty($row['is_html']);
        if ($isEmpty) {
            $content = '&nbsp;';
        } else if ($isHtml) {
            // Already sanitized by InlineHtml (only strong/em/i/u)
            $content = (string)($row['html'] ?? '');
        } else {
            $text = is_array($row) ? (string)($row['text'] ?? '') : (string)$row;
            $content = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        if ($num === null) {
            return '<div class="' . $class . '"><span class="line-text">' . $content . '</span></div>';
        }

        if ($placement === 'inline') {
            $prefix = str_pad((string)$num, $digits, ' ', STR_PAD_LEFT) . ' ';
            // For inline numbering we always output as plain text to avoid mixing markup with prefix
            $textLine = $prefix . ($plainText === '' ? '' : $plainText);
            $safe = $isEmpty ? '&nbsp;' : htmlspecialchars($textLine, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            return '<div class="' . $class . '"><span class="line-text">' . $safe . '</span></div>';
        }

        // margin numbering
        $numStr = str_pad((string)$num, $digits, ' ', STR_PAD_LEFT);

        return '<div class="' . $class . '">'
            . '<span class="line-num">' . htmlspecialchars($numStr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>'
            . '<span class="line-text">' . $content . '</span>'
            . '</div>';
    }

    private function renderPageLabel(string $tpl, string $format, int $page, int $total, string $fn, string $file): string
    {
        $date = date('d.m.Y H:i');

        $p = NumberFormat::format($page, $format);
        $t = NumberFormat::format($total, $format);

        $map = [
            '{page}'   => $p,
            '{total}'  => $t,
            '{title}'  => $this->title,
            '{author}' => $this->author,
            '{fn}'     => $fn,
            '{file}'   => $file,
            '{date}'   => $date,
        ];

        return strtr($tpl, $map);
    }

    private function buildCss(string $lnPlacement, int $lnDigits): string
    {
        $w = $this->spec->pageWidthMm;
        $h = $this->spec->pageHeightMm;

        $textLH = $this->spec->textLineHeight;
        $blankMult = $this->spec->blankLineMult;

        return "
:root{
  --print-font-size: {$this->spec->fontSizePt}pt;
  --text-line-height: {$textLH};
  --blank-mult: {$blankMult};
  --print-margin: {$this->spec->marginMm}mm;
  --print-page-width: {$w}mm;
  --print-page-height: {$h}mm;
  --ln-digits: {$lnDigits};
}
*{ box-sizing:border-box; }
html, body{ height:100%; }
body{
  margin:0;
  background:#0b1220;
  color:#000;
  font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
}
.print-preview{
  padding:32px 0;
  display:flex;
  flex-direction:column;
  gap:22px;
  align-items:center;
}
.print-page{
  width: var(--print-page-width);
  height: var(--print-page-height);
  background:#fff;
  box-shadow: 0 12px 30px rgba(0,0,0,.35);
  position:relative;
  overflow:hidden;
}
.page-inner{
  position:absolute;
  left: var(--print-margin);
  right: var(--print-margin);
  top: var(--print-margin);
  bottom: var(--print-margin);
  font-family: \"Courier New\", ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: var(--print-font-size);
}
.lines{ display:flex; flex-direction:column; }
.line{ display:flex; align-items:flex-start; min-height: calc(1em * var(--text-line-height)); }
.line-num{
  width: calc(var(--ln-digits) * 1ch + 1ch);
  padding-right: 1ch;
  text-align:right;
  opacity:.55;
  user-select:none;
  flex: 0 0 auto;
}
.line-text{
  flex:1 1 auto;
  white-space: pre;
  line-height: var(--text-line-height);
}
.ln-inline .line-num{ display:none; }
.line.is-empty{ min-height: calc(1em * var(--text-line-height) * var(--blank-mult)); }

.page-header, .page-footer{
  position:absolute;
  left: 0;
  right: 0;
  height: var(--print-margin);
  padding: 0 var(--print-margin);

  font-family: \"Courier New\", ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  line-height: 1.2;
  opacity: .75;

  white-space: nowrap;
  overflow:hidden;
  text-overflow: ellipsis;

  display:flex;
  align-items:center;
  justify-content:center;
}
.page-header{ top: 0; }
.page-footer{ bottom: 0; }

@page{
  size: var(--print-page-width) var(--print-page-height);
  margin: 0;
}

@media print{
  body{ background:#fff; }
  .print-preview{ padding:0; gap:0; }
  .print-page{
    box-shadow:none;
    page-break-after: always;
  }
}
";
    }
}

final class NumberFormat
{
    public static function format(int $n, string $format): string
    {
        if ($n <= 0) return (string)$n;

        switch ($format) {
            case 'roman-upper': return self::toRoman($n, true);
            case 'roman-lower': return self::toRoman($n, false);
            case 'latin-upper': return self::toLatin($n, true);
            case 'latin-lower': return self::toLatin($n, false);
            case 'bg-upper':    return self::toBgLetters($n, true);
            case 'bg-lower':    return self::toBgLetters($n, false);
            default:            return (string)$n; // 123
        }
    }

    private static function toRoman(int $n, bool $upper): string
    {
        $map = [
            1000 => 'M', 900 => 'CM', 500 => 'D', 400 => 'CD',
            100  => 'C', 90  => 'XC', 50  => 'L', 40  => 'XL',
            10   => 'X', 9   => 'IX', 5   => 'V', 4   => 'IV',
            1    => 'I',
        ];

        $res = '';
        foreach ($map as $val => $sym) {
            while ($n >= $val) {
                $res .= $sym;
                $n -= $val;
            }
        }
        return $upper ? $res : strtolower($res);
    }

    private static function toLatin(int $n, bool $upper): string
    {
        $res = '';
        while ($n > 0) {
            $n--;
            $res = chr(($n % 26) + 65) . $res;
            $n = intdiv($n, 26);
        }
        return $upper ? $res : strtolower($res);
    }

    private static function toBgLetters(int $n, bool $upper): string
    {
        $letters = ['А','Б','В','Г','Д','Е','Ж','З','И','Й','К','Л','М','Н','О','П','Р','С','Т','У','Ф','Х','Ц','Ч','Ш','Щ','Ъ','Ь','Ю','Я'];
        $base = count($letters);

        $res = '';
        while ($n > 0) {
            $n--;
            $res = $letters[$n % $base] . $res;
            $n = intdiv($n, $base);
        }
        return $upper ? $res : mb_strtolower($res, 'UTF-8');
    }
}