<?php
declare(strict_types=1);

/**
 * MetadataBuilder
 *  - Pure core module (no DB, no $_POST/$_SESSION, no HTML output)
 *  - Input: $metaInput (array) as agreed
 *  - Output: associative array containing:
 *      - 'type'  => 'meta'
 *      - 'lines' => string[] (ready to be wrapped/paginated or inserted as a page)
 *
 * Expected $metaInput:
 * [
 *   'placement' => 'start'|'end'|'none',
 *   'title'     => string,
 *   'author'    => string,
 *   'course'    => string,
 *   'source'    => string,
 *   'context'   => ['generatedAt' => 'Y-m-d H:i']
 * ]
 */
final class MetadataBuilder
{
    /** @var int Default maximum length of a single output line (soft wrap hint). */
    private int $maxLineLength;

    public function __construct(int $maxLineLength = 90)
    {
        $this->maxLineLength = max(40, $maxLineLength);
    }

    /**
     * Build metadata lines from meta input.
     *
     * @param array $metaInput
     * @return array{type:string, lines: string[], placement:string}
     */
    public function build(array $metaInput): array
    {
        $placement = $this->normalizePlacement($metaInput['placement'] ?? 'none');

        // If metadata is disabled via placement=none, return an empty block (caller may skip).
        if ($placement === 'none') {
            return [
                'type' => 'meta',
                'placement' => 'none',
                'lines' => [],
            ];
        }

        $title  = $this->cleanValue($metaInput['title']  ?? '');
        $author = $this->cleanValue($metaInput['author'] ?? '');
        $course = $this->cleanValue($metaInput['course'] ?? '');
        $source = $this->cleanValue($metaInput['source'] ?? '');

        $generatedAtRaw = $metaInput['context']['generatedAt'] ?? '';
        $generatedAt = $this->formatGeneratedAt((string)$generatedAtRaw);

        // Build "label: value" lines (in Bulgarian, as your UI).
        $pairs = [
            'Заглавие' => $title,
            'Автор' => $author,
            'Курс/група' => $course,
            'Източник' => $source,
            'Дата на генериране' => $generatedAt,
        ];

        // Remove empty values (but keep the label if you prefer; here we skip empties).
        $lines = [];
        foreach ($pairs as $label => $value) {
            if ($value === '') {
                continue;
            }
            $lines = array_merge($lines, $this->wrapLabelValue($label, $value));
        }

        // If everything is empty, still return a minimal block.
        if (empty($lines)) {
            $lines[] = 'Метаданни';
            $lines[] = '(няма попълнени данни)';
        }

        // Add a small header and separator for nicer look in preview/print.
        $decorated = [];
        $decorated[] = 'М Е Т А Д А Н Н И';
        $decorated[] = str_repeat('─', min(40, $this->maxLineLength));
        foreach ($lines as $ln) {
            $decorated[] = $ln;
        }

        return [
            'type' => 'meta',
            'placement' => $placement,
            'lines' => $decorated,
        ];
    }

    // -----------------------
    // Helpers
    // -----------------------

    private function normalizePlacement(string $placement): string
    {
        $p = strtolower(trim($placement));
        return in_array($p, ['start', 'end', 'none'], true) ? $p : 'none';
    }

    /**
     * Basic text cleanup: remove tags, normalize whitespace.
     */
    private function cleanValue(string $value): string
    {
        $v = strip_tags($value);
        $v = str_replace(["\r\n", "\r"], "\n", $v);
        $v = preg_replace("/[ \t]+/u", " ", $v) ?? $v;
        $v = preg_replace("/\n{3,}/u", "\n\n", $v) ?? $v;
        return trim($v);
    }

    /**
     * Formats "Y-m-d H:i" (or any parseable input) into a nicer BG format.
     * If parsing fails, returns the cleaned raw string.
     */
    private function formatGeneratedAt(string $generatedAt): string
    {
        $generatedAt = trim($generatedAt);
        if ($generatedAt === '') {
            // fallback to now
            return date('d.m.Y H:i');
        }

        $ts = strtotime($generatedAt);
        if ($ts === false) {
            return $this->cleanValue($generatedAt);
        }
        return date('d.m.Y H:i', $ts);
    }

    /**
     * Wrap "Label: value" so it doesn't exceed maxLineLength too much.
     * Returns array of lines.
     */
    private function wrapLabelValue(string $label, string $value): array
    {
        $label = $this->cleanValue($label);
        $value = $this->cleanValue($value);

        $prefix = $label . ': ';
        $max = $this->maxLineLength;

        // If it already fits, return as a single line.
        if (mb_strlen($prefix . $value, 'UTF-8') <= $max) {
            return [$prefix . $value];
        }

        // Wrap value, keeping the first line with prefix.
        $wrapped = $this->wrapText($value, max(20, $max - mb_strlen($prefix, 'UTF-8')));
        $out = [];

        foreach ($wrapped as $i => $part) {
            if ($i === 0) {
                $out[] = $prefix . $part;
            } else {
                // Indent subsequent lines under the value.
                $out[] = str_repeat(' ', min(4, mb_strlen($prefix, 'UTF-8'))) . $part;
            }
        }

        return $out;
    }

    /**
     * Simple Unicode-friendly word wrap into lines with max length.
     * (No reliance on mb_* availability for wrapping besides mb_strlen/substr)
     *
     * @return string[]
     */
    private function wrapText(string $text, int $maxLen): array
    {
        $text = $this->cleanValue($text);
        if ($text === '' || $maxLen <= 0) {
            return [$text];
        }

        $words = preg_split('/\s+/u', $text) ?: [$text];
        $lines = [];
        $current = '';

        foreach ($words as $w) {
            $candidate = ($current === '') ? $w : ($current . ' ' . $w);
            if (mb_strlen($candidate, 'UTF-8') <= $maxLen) {
                $current = $candidate;
                continue;
            }

            if ($current !== '') {
                $lines[] = $current;
                $current = $w;
            } else {
                // Single word longer than maxLen: hard-split.
                $lines = array_merge($lines, $this->hardSplit($w, $maxLen));
                $current = '';
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    /**
     * Hard split a long word into chunks.
     *
     * @return string[]
     */
    private function hardSplit(string $text, int $maxLen): array
    {
        $chunks = [];
        $len = mb_strlen($text, 'UTF-8');
        for ($i = 0; $i < $len; $i += $maxLen) {
            $chunks[] = mb_substr($text, $i, $maxLen, 'UTF-8');
        }
        return $chunks;
    }
}
