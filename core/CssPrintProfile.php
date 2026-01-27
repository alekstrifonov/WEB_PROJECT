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

        // Meta / Stats pages
        $finalPages = $this->applyMetaAndStats($contentPages, $spec, $settings, $meta);

        // Render full printable HTML
        $renderer = new PrintHtmlRenderer($spec, $settings, $meta);
        return $renderer->render($finalPages);
    }

    private function applyMetaAndStats(array $contentPages, PageSpec $spec, array $settings, array $meta): array
    {
        $metaPlacement  = (string)($meta['metadata_placement'] ?? 'none');
        $statsPlacement = (string)($meta['statistics_placement'] ?? 'none');

        $final = $contentPages;

        // Stats page based on content (без meta/stats)
        $stats = DocumentStats::fromPages($contentPages);

        $metaPage  = ($metaPlacement !== 'none')  ? SpecialPages::metadataPage($spec, $meta) : null;
        $statsPage = ($statsPlacement !== 'none') ? SpecialPages::statisticsPage($spec, $stats) : null;

        // START: meta преди stats
        if ($statsPlacement === 'start' && $statsPage) {
            array_unshift($final, $statsPage);
        }
        if ($metaPlacement === 'start' && $metaPage) {
            array_unshift($final, $metaPage);
        }

        // END: stats преди meta (meta е "последна")
        if ($statsPlacement === 'end' && $statsPage) {
            $final[] = $statsPage;
        }
        if ($metaPlacement === 'end' && $metaPage) {
            $final[] = $metaPage;
        }

        return $final;
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

        error_log("fontSize={$fontSize} usableWidthMm={$usableWidthMm} maxChars={$maxChars} wrapLines=" . ($wrapLines ? 'yes' : 'no'));

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

    private function walk(DOMNode $node, array &$blocks): void
    {
        if ($node instanceof DOMText) {
            $t = trim($node->nodeValue ?? '');
            if ($t !== '') {
                $blocks[] = ['type' => 'paragraph', 'text' => $t];
                $blocks[] = ['type' => 'blank'];
            }
            return;
        }

        if (!($node instanceof DOMElement)) {
            return;
        }

        $tag = strtolower($node->tagName);

        if (preg_match('/^h[1-6]$/', $tag)) {
            $text = trim($node->textContent ?? '');
            if ($text !== '') {
                $blocks[] = [
                    'type' => 'header',
                    'level' => (int)substr($tag, 1),
                    'text' => $text,
                ];
                $blocks[] = ['type' => 'blank'];
            }
            return;
        }

        if ($tag === 'pre') {
            $text = rtrim($node->textContent ?? '', "\r\n");
            $lines = preg_split("/\r\n|\r|\n/", $text) ?: [];
            $blocks[] = ['type' => 'pre', 'lines' => $lines];
            $blocks[] = ['type' => 'blank'];
            return;
        }

        if (in_array($tag, ['p', 'li'], true)) {
            $text = $this->textWithBreaks($node);
            $text = trim($text);
            if ($text !== '') {
                $blocks[] = ['type' => 'paragraph', 'text' => $text];
                $blocks[] = ['type' => 'blank'];
            }
            return;
        }

        if ($tag === 'br') {
            $blocks[] = ['type' => 'blank'];
            return;
        }

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

            // if ($type === 'header' && $newPageOnHeader && !$this->isPageEmpty($pages[$pageIndex])) {
            //     $pages[] = $this->newPage('content');
            //     $pageIndex = count($pages) - 1;
            // }

            // new
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

            // $type = (string)($b['type'] ?? 'paragraph');
            // $isNumberedBlock = ($type === 'pre' || $type === 'code');

            // $lines = $this->blockToLines($b);
            // foreach ($lines as $line) {
            //     $this->pushLineWithLimits($pages, $line, $file, $isNumberedBlock);
            // }
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

    // private function pushLineWithLimits(array &$pages, string $line, string $file, bool $isNumbered): void
    private function pushLineWithLimits(array &$pages, string $line, string $file): void
    {
        $maxLines = $this->spec->maxLinesPerPage; // 0 => height-based
        $maxWords = $this->spec->maxWordsPerPage;

        $pageIndex = count($pages) - 1;

        $isEmptyLine = (trim($line) === '');
        $lineWords = TextUtil::countWords($line);

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

        // 3) add line (вкл. празен)
        $pages[$pageIndex]['lines'][] = $line;
        $pages[$pageIndex]['word_count'] += $lineWords;

        // 4) count non-empty lines
        if (!$isEmptyLine) {
            $pages[$pageIndex]['non_empty_line_count']++;
        }

        // 5) accumulate height (празните редове заемат място)
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

        if ($type === 'header') {
            $text = trim((string)($b['text'] ?? ''));
            $text = mb_strtoupper($text, 'UTF-8');
            return TextUtil::wrapHardOrSoft($text, $this->spec->maxCharsPerLine, $this->spec->wrapLines, true);
        }

        // paragraph (може да има \n)
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
                $totalWords += TextUtil::countWords((string)$l);
            }
        }

        $avg = $totalPages > 0 ? ($totalLines / $totalPages) : 0.0;

        return new self($totalPages, $totalLines, $totalWords, $avg);
    }
}

final class SpecialPages
{
    public static function metadataPage(PageSpec $spec, array $meta): array
    {
        $date = date('d.m.Y H:i');

        $title  = (string)($meta['title'] ?? '');
        $author = (string)($meta['author'] ?? '');
        $course = (string)($meta['course'] ?? '');
        $cite   = (string)($meta['citation_template'] ?? '');

        $sep = str_repeat('─', min(60, $spec->wrapLines ? $spec->maxCharsPerLine : 60));

        $lines = [
            'М Е Т А Д А Н Н И',
            $sep,
            'Заглавие: ' . $title,
            'Автор: ' . $author,
            'Допълнителна информация: ' . $course,
            'Дата на генериране: ' . $date,
        ];

        if (trim($cite) !== '') {
            $lines[] = '';
            $lines[] = 'Цитиране (шаблон):';
            foreach (TextUtil::wrapHardOrSoft($cite, $spec->maxCharsPerLine, $spec->wrapLines, true) as $w) {
                $lines[] = $w;
            }
        }

        return ['type' => 'meta', 'file' => '', 'lines' => $lines, 'word_count' => 0];
    }

    public static function statisticsPage(PageSpec $spec, DocumentStats $stats): array
    {
        $lines = [
            'Statistics',
            'Total Pages: ' . $stats->totalPages,
            'Total Lines: ' . $stats->totalLines,
            'Total Words: ' . $stats->totalWords,
            'Avg Lines per Page: ' . number_format($stats->avgLinesPerPage, 2, '.', ''),
        ];

        // ако wrap_lines е yes, все пак няма какво да се wrap-ва много тук
        return ['type' => 'stats', 'file' => '', 'lines' => $lines, 'word_count' => 0];
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
            foreach (($page['lines'] ?? []) as $line) {
                $line = (string)$line;

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

                $out[] = $this->renderLine($line, $num, $lnPlacement, $lnDigits);
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

    // private function renderLine(string $line, ?int $num, string $placement, int $digits): string
    // {
    //     $isEmpty = (trim($line) === '');
    //     $class = 'line' . ($isEmpty ? ' is-empty' : '');

    //     // $safe = htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    //     $safe = $isEmpty ? '&nbsp;' : htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    //     if ($num === null) {
    //         return '<div class="line"><span class="line-text">' . $safe . '</span></div>';
    //     }

    //     if ($placement === 'inline') {
    //         $prefix = str_pad((string)$num, $digits, ' ', STR_PAD_LEFT) . ' ';
    //         $safe2 = htmlspecialchars($prefix . $line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    //         return '<div class="line"><span class="line-text">' . $safe2 . '</span></div>';
    //     }

    //     // margin
    //     $n = str_pad((string)$num, $digits, ' ', STR_PAD_LEFT);
    //     $safeN = htmlspecialchars($n, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    //     return '<div class="line"><span class="line-num">' . $safeN . '</span><span class="line-text">' . $safe . '</span></div>';
    // }

    private function renderLine(string $line, ?int $num, string $placement, int $digits): string
    {
        $isEmpty = (trim($line) === '');
        $class = 'line' . ($isEmpty ? ' is-empty' : '');

        // празният ред трябва да има съдържание, иначе някои браузъри го "смачкват"
        $safeText = $isEmpty
            ? '&nbsp;'
            : htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // без номера
        if ($num === null) {
            return '<div class="'.$class.'"><span class="line-text">'.$safeText.'</span></div>';
        }

        // inline номера (номерът е част от текста)
        if ($placement === 'inline') {
            $prefix = str_pad((string)$num, $digits, ' ', STR_PAD_LEFT) . ' ';
            $safeInline = $isEmpty
                ? htmlspecialchars($prefix, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '&nbsp;'
                : htmlspecialchars($prefix . $line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            return '<div class="'.$class.'"><span class="line-text">'.$safeInline.'</span></div>';
        }

        // margin номера (отделна колона)
        $n = str_pad((string)$num, $digits, ' ', STR_PAD_LEFT);
        $safeN = htmlspecialchars($n, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<div class="'.$class.'"><span class="line-num">'.$safeN.'</span><span class="line-text">'.$safeText.'</span></div>';
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

        // $show = !empty($this->pagination['show_page_numbers']);
        // $pos = (string)($this->pagination['page_number_pos'] ?? 'footer');

        // $hfHeightMm = 6;

        // $headerReserve = ($show && $pos == 'header') ? $hfHeightMm : 0;
        // $footerReserve = ($show && $pos == 'footer') ? $hfHeightMm : 0;

        // --print-line-height: {$this->spec->lineHeight};

//         --hf-height: {$hfHeightMm}mm;
//   --header-reserve: {$headerReserve}mm;
//   --footer-reserve: {$footerReserve}mm;

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
        // 1->A ... 26->Z ... 27->AA
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