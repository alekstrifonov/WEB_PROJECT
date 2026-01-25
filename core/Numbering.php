<?php
declare(strict_types=1);

/**
 * core/Numbering.php
 *
 * Handles page and line numbering.
 */
final class Numbering
{
    /**
     * Format page number.
     *
     * @param int $n
     * @param string $format e.g. "Page %d"
     * @return string
     */
    public function formatPageNo(int $n, string $format = 'Page %d'): string
    {
        return sprintf($format, $n);
    }

    /**
     * Apply line numbers to pages.
     *
     * @param array<int, array{lines:string[]}> $pages
     * @param array{start_line?:int, line_format?:string, line_numbers_mode?:string, ...} $settings
     * @return array<int, array{lines:string[]}>
     */
    public function applyLineNumbers(array $pages, array $settings = []): array
    {
        $mode = $settings['line_numbers_mode'] ?? 'none';
        
        if ($mode === 'none') {
            return $pages;
        }

        $startLine = $settings['start_line'] ?? 1;
        $lineFormat = $settings['line_format'] ?? '%d: %s';

        $numberedPages = [];
        $currentLineNo = $startLine;

        foreach ($pages as $page) {
            if ($mode === 'page') {
                // Reset line number for each page
                $currentLineNo = $startLine;
            }

            $numberedLines = [];
            foreach ($page['lines'] as $line) {
                $numberedLines[] = sprintf($lineFormat, $currentLineNo, $line);
                $currentLineNo++;
            }
            $numberedPages[] = ['lines' => $numberedLines];
        }

        return $numberedPages;
    }
}
?>