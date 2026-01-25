<?php
declare(strict_types=1);

/**
 * core/TextNormalizer.php
 *
 * Normalizes plain text in blocks: whitespace, tabs, newlines.
 */
final class TextNormalizer
{
    /**
     * Normalize plain text in blocks.
     *
     * @param array<int, array{type:string, html:string, plain:string}> $blocks
     * @param array{normalize_whitespace?:bool, normalize_tabs?:bool, normalize_newlines?:bool} $options
     * @return array<int, array{type:string, html:string, plain:string}>
     */
    public function toPlainTextBlocks(array $blocks, array $options = []): array
    {
        $normalizeWhitespace = $options['normalize_whitespace'] ?? true;
        $normalizeTabs = $options['normalize_tabs'] ?? true;
        $normalizeNewlines = $options['normalize_newlines'] ?? true;

        $normalized = [];
        foreach ($blocks as $block) {
            $plain = $block['plain'];

            if ($normalizeTabs) {
                $plain = str_replace("\t", ' ', $plain);
            }

            if ($normalizeWhitespace) {
                // Collapse multiple spaces
                $plain = preg_replace('/ {2,}/', ' ', $plain) ?? $plain;
            }

            if ($normalizeNewlines) {
                // Normalize line endings
                $plain = str_replace(["\r\n", "\r"], "\n", $plain);
                // Collapse multiple newlines to double
                $plain = preg_replace("/\n{3,}/", "\n\n", $plain) ?? $plain;
            }

            $normalized[] = [
                'type' => $block['type'],
                'html' => $block['html'],
                'plain' => trim($plain),
            ];
        }

        return $normalized;
    }
}
?>