<?php
declare(strict_types=1);

/**
 * core/HtmlToBlocks.php
 *
 * Goal (as agreed):
 *  - Input: sanitized HTML documents (from HtmlSanitizer)
 *  - Output: blocks with ONLY:
 *      - type  (paragraph | heading1..heading6 | list_item | pre)
 *      - html  (keeps allowed inline tags for print)
 *      - plain (same content, without tags; used for counting/wrap/pagination)
 *
 * Notes:
 *  - No DOMDocument
 *  - PHP 7+ compatible
 *  - We keep only a small allowlist of inline tags in block html:
 *      <strong>, <em>, <u>, <code>, <br>
 *    (also accept <b> and <i> but normalize them)
 *  - We remove attributes from allowed tags (e.g. <strong class="x"> -> <strong>)
 */
final class HtmlToBlocks
{
    /** @var string[] */
    /*private array $allowedInlineTags = ['strong','em','u','code','br','b','i'];i*/

    /**
     * Convert list of sanitized HTML docs into blocks.
     *
     * Input $docs:
     * [
     *   ['name' => 'file.html', 'html' => '<html>...</html>'],
     *   ...
     * ]
     *
     * Output:
     * [
     *   [
     *     'name' => 'file.html',
     *     'blocks' => [
     *        ['type'=>'heading1','html'=>'...', 'plain'=>'...'],
     *        ['type'=>'paragraph','html'=>'...', 'plain'=>'...'],
     *        ...
     *     ]
     *   ],
     *   ...
     * ]
     */
    public function convert(array $docs): array
    {
        $out = [];

        foreach ($docs as $doc) {
            $name = isset($doc['name']) ? (string)$doc['name'] : 'unknown.html';
            $html = isset($doc['html']) ? (string)$doc['html'] : '';

            $out[] = [
                'name' => $name,
                'blocks' => $this->convertOne($html),
            ];
        }

        return $out;
    }

    // -------------------------
    // Internal
    // -------------------------

    /**
     * @return array<int, array{type:string, html:string, plain:string}>
     */
    private function convertOne(string $html): array
    {
        $html = $this->normalizeLineEndings($html);

        // If <body> exists, use only its content
        $body = $this->extractBodyIfPresent($html);
        if ($body !== null) {
            $html = $body;
        }

        // Normalize <br> to <br>
        $html = preg_replace('/<br\s*\/?>/i', '<br>', $html) ?? $html;

        // Match blocks in order: headings, p, li, pre
        // Everything else becomes "text chunks" which we treat as paragraphs.
        $pattern = '/(<\s*(h[1-6]|p|li|pre)\b[^>]*>.*?<\s*\/\s*\2\s*>)/is';
        $parts = preg_split($pattern, $html, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false) {
            $parts = [$html];
        }

        $blocks = [];

        for ($i = 0; $i < count($parts); $i++) {
            $part = $parts[$i];
            if ($part === null || $part === '') {
                continue;
            }

            // Because of PREG_SPLIT_DELIM_CAPTURE, the array looks like:
            // [text, fullmatch, tagname, text, fullmatch, tagname, ...]
            // We'll detect fullmatch by checking if it starts with '<' and looks like a block tag.
            if ($this->looksLikeBlockTag($part)) {
                $tagName = isset($parts[$i + 1]) ? strtolower((string)$parts[$i + 1]) : '';
                $i++; // skip tagname capture on next position

                $blocksFromTag = $this->blockFromTag($tagName, $part);
                foreach ($blocksFromTag as $b) {
                    $blocks[] = $b;
                }
                continue;
            }

            // Non-tag chunk: treat as paragraph-ish content
            $b = $this->makeParagraphBlock($part);
            if ($b !== null) {
                $blocks[] = $b;
            }
        }

        return $blocks;
    }

    private function looksLikeBlockTag(string $s): bool
    {
        return preg_match('/^\s*<\s*(h[1-6]|p|li|pre)\b/i', $s) === 1;
    }

    /**
     * Turn a matched block tag into one or more blocks.
     *
     * @return array<int, array{type:string, html:string, plain:string}>
     */
    private function blockFromTag(string $tagName, string $fullTagHtml): array
    {
        $inner = $this->extractInnerHtml($fullTagHtml, $tagName);

        if ($tagName === 'pre') {
            return [$this->makePreBlock($inner)];
        }

        if ($tagName === 'li') {
            $block = $this->makeInlineBlock('list_item', $inner);
            return $block ? [$block] : [];
        }

        if (preg_match('/^h([1-6])$/', $tagName, $m)) {
            $level = (int)$m[1];
            $type = 'heading' . $level;
            $block = $this->makeInlineBlock($type, $inner);
            return $block ? [$block] : [];
        }

        // <p>
        if ($tagName === 'p') {
            $block = $this->makeInlineBlock('paragraph', $inner);
            return $block ? [$block] : [];
        }

        return [];
    }

    /**
     * Create a paragraph block from a text chunk that is outside known block tags.
     *
     * @return array{type:string, html:string, plain:string}|null
     */
    private function makeParagraphBlock(string $chunk): ?array
    {
        $block = $this->makeInlineBlock('paragraph', $chunk);
        return $block;
    }

    /**
     * Builds a normal inline-capable block:
     *  - html: keeps allowed inline tags
     *  - plain: strips tags and decodes entities
     *
     * @return array{type:string, html:string, plain:string}|null
     */
    private function makeInlineBlock(string $type, string $htmlFragment): ?array
    {
        $safeHtml = $this->sanitizeInlineHtml($htmlFragment);
        $plain = $this->plainFromInlineHtml($safeHtml);

        // Skip empty blocks
        if ($plain === '') {
            return null;
        }

        return [
            'type' => $type,
            'html' => $safeHtml,
            'plain' => $plain,
        ];
    }

    /**
     * Pre block: treat content as text (no inline tags),
     * preserve whitespace/newlines in plain.
     *
     * html is escaped so it can't inject markup.
     *
     * @return array{type:string, html:string, plain:string}
     */
    private function makePreBlock(string $innerHtml): array
    {
        // Convert <br> into newlines first (common in pasted code)
        $innerHtml = preg_replace('/<br\s*\/?>/i', "\n", $innerHtml) ?? $innerHtml;

        // Strip all tags inside pre and decode entities
        $text = strip_tags($innerHtml);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $text = $this->normalizeLineEndings($text);

        // Keep whitespace; only trim outer newlines/spaces lightly
        $plain = rtrim(ltrim($text, "\n"), "\n");

        // Escape for safe HTML rendering (renderer can wrap this in <pre>)
        $html = htmlspecialchars($plain, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return [
            'type' => 'pre',
            'html' => $html,
            'plain' => $plain,
        ];
    }

    /**
     * Keep only allowed inline tags and remove their attributes.
     * Also normalize <b>-><strong>, <i>-><em>.
     */
    private function sanitizeInlineHtml(string $html): string
    {
        $html = $this->normalizeLineEndings($html);

        // Normalize br forms
        $html = preg_replace('/<br\s*\/?>/i', '<br>', $html) ?? $html;

        // Keep only allowlisted inline tags
        $allowed = '<strong><em><u><code><br><b><i>';
        $html = strip_tags($html, $allowed);

        // Normalize <b> -> <strong>, </b> -> </strong>
        $html = preg_replace('/<\s*b(\s[^>]*)?>/i', '<strong>', $html) ?? $html;
        $html = preg_replace('/<\s*\/\s*b\s*>/i', '</strong>', $html) ?? $html;

        // Normalize <i> -> <em>, </i> -> </em>
        $html = preg_replace('/<\s*i(\s[^>]*)?>/i', '<em>', $html) ?? $html;
        $html = preg_replace('/<\s*\/\s*i\s*>/i', '</em>', $html) ?? $html;

        // Remove attributes from allowed tags (keep tag name only)
        $html = preg_replace('/<\s*(strong|em|u|code)\b[^>]*>/i', '<$1>', $html) ?? $html;
        $html = preg_replace('/<\s*br\b[^>]*>/i', '<br>', $html) ?? $html;

        // Normalize whitespace around tags a bit (don’t be too aggressive)
        $html = preg_replace('/[ \t]+/u', ' ', $html) ?? $html;
        $html = trim($html);

        return $html;
    }

    /**
     * Plain text that corresponds to sanitizedInlineHtml:
     *  - <br> becomes "\n"
     *  - tags are stripped
     *  - entities decoded
     *  - whitespace normalized lightly (keeps newlines)
     */
    private function plainFromInlineHtml(string $sanitizedInlineHtml): string
    {
        $tmp = str_replace('<br>', "\n", $sanitizedInlineHtml);
        $tmp = strip_tags($tmp);
        $tmp = html_entity_decode($tmp, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $tmp = $this->normalizeLineEndings($tmp);

        // Convert NBSP to normal space
        $tmp = str_replace("\xC2\xA0", ' ', $tmp);

        // Collapse multiple spaces (keep newlines)
        $tmp = preg_replace('/[ ]{2,}/u', ' ', $tmp) ?? $tmp;

        // Trim lines
        $lines = explode("\n", $tmp);
        foreach ($lines as &$ln) {
            $ln = trim($ln);
        }
        unset($ln);
        $tmp = implode("\n", $lines);

        // Collapse too many blank lines
        $tmp = preg_replace("/\n{3,}/u", "\n\n", $tmp) ?? $tmp;

        return trim($tmp);
    }

    private function extractBodyIfPresent(string $html): ?string
    {
        if (preg_match('/<body\b[^>]*>(.*?)<\/body>/is', $html, $m)) {
            return (string)($m[1] ?? '');
        }
        return null;
    }

    private function extractInnerHtml(string $fullTagHtml, string $tagName): string
    {
        $tagName = preg_quote($tagName, '/');
        if (preg_match('/<\s*' . $tagName . '\b[^>]*>(.*?)<\s*\/\s*' . $tagName . '\s*>/is', $fullTagHtml, $m)) {
            return (string)($m[1] ?? '');
        }
        return '';
    }

    private function normalizeLineEndings(string $text): string
    {
        return str_replace(["\r\n", "\r"], "\n", $text);
    }
}

?>
