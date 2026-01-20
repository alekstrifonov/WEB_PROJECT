<?php
declare(strict_types=1);

/**
 * core/HtmlSanitizer.php
 *
 * Purpose:
 *  - Remove dangerous and irrelevant HTML parts
 *  - Make HTML safe and predictable for further processing
 *
 * Assumptions:
 *  - CSS is NOT needed (remove <style>, <link rel="stylesheet">)
 *  - JavaScript is NOT allowed (remove <script> and inline handlers)
 *  - No DOMDocument, no warnings output
 *
 * Input:
 * [
 *   ['name' => 'file.html', 'html' => '<html>...</html>'],
 *   ...
 * ]
 *
 * Output:
 * [
 *   ['name' => 'file.html', 'html' => '<html>...</html>'],
 *   ...
 * ]
 */
final class HtmlSanitizer
{
    /**
     * Sanitize a list of HTML documents.
     *
     * @param array $docs
     * @return array
     */
    public function sanitize(array $docs): array
    {
        $out = [];

        foreach ($docs as $doc) {
            if (!isset($doc['html'])) {
                continue;
            }

            $out[] = [
                'name' => $doc['name'] ?? 'unknown.html',
                'html' => $this->sanitizeHtml((string)$doc['html']),
            ];
        }

        return $out;
    }

    /**
     * Sanitize a single HTML string.
     */
    private function sanitizeHtml(string $html): string
    {
        // 1. Remove HTML comments
        $html = preg_replace('/<!--.*?-->/s', '', $html);

        // 2. Remove <script>...</script>
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);

        // 3. Remove <style>...</style>
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);

        // 4. Remove <link rel="stylesheet" ...>
        $html = preg_replace('/<link\b[^>]*rel=["\']?stylesheet["\']?[^>]*>/is', '', $html);

        // 5. Remove <iframe>, <object>, <embed>
        // $html = preg_replace('/<(iframe|object|embed)\b[^>]*>.*?<\/\1>/is', '', $html);

        // 5a. Remove paired <iframe>...</iframe>, <object>...</object>, <embed>...</embed> (if present)
        $html = preg_replace('/<(iframe|object|embed)\b[^>]*>.*?<\/\1>/is', '', $html);

        // 5b. Remove standalone/void versions like <embed ...> or <iframe ...> (no closing tag)
        $html = preg_replace('/<(iframe|object|embed)\b[^>]*\/?>/is', '', $html);


        // 6. Remove inline JavaScript handlers (onclick, onload, etc.)
        $html = preg_replace('/\son\w+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $html);

        // 7. Normalize <br> tags to <br>
        $html = preg_replace('/<br\s*\/?>/i', '<br>', $html);

        // 8. Trim excessive whitespace between tags
        $html = preg_replace('/>\s+</', '><', $html);

        return trim($html);
    }
}

?>
