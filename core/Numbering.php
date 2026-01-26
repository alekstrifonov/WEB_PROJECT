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
     * Convert page number to formatted string based on setting.
     */
    private function formatPageNumberValue(int $n, string $format): string
    {
        switch ($format) {
            case 'roman-upper':
                return strtoupper($this->toRoman($n));
            case 'roman-lower':
                return strtolower($this->toRoman($n));
            case '123':
            default:
                return (string)$n;
        }
    }

    /**
     * Apply page numbers to each page as header or footer line.
     *
     * @param array<int, array{lines:string[]}> $pages
     * @param array{show_page_numbers?:int,no_number_on_first?:int,page_number_pos?:string,page_number_format?:string,page_number_template?:string} $settings
     * @param array{title?:string,author?:string} $meta
     * @return array<int, array{lines:string[]}>
     */
    public function applyPageNumbers(array $pages, array $settings = [], array $meta = []): array
    {
        $show = (int)($settings['show_page_numbers'] ?? 0) === 1;
        if (!$show) {
            return $pages;
        }

        $skipFirst = (int)($settings['no_number_on_first'] ?? 0) === 1;
        $pos = $settings['page_number_pos'] ?? 'footer';
        $fmt = $settings['page_number_format'] ?? '123';
        $tpl = $settings['page_number_template'] ?? '{author} • {title} • стр. {page}/{total}';

        $total = count($pages);
        $title = (string)($meta['title'] ?? '');
        $author = (string)($meta['author'] ?? '');
        $date = date('Y-m-d');

        $result = [];
        foreach ($pages as $i => $page) {
            $pageNo = $i + 1;
            $entry = $page; // preserve any existing fields

            if (!($skipFirst && $pageNo === 1)) {
                $displayNo = $this->formatPageNumberValue($pageNo, $fmt);
                $displayTotal = $this->formatPageNumberValue($total, $fmt);
                $label = strtr($tpl, [
                    '{page}' => $displayNo,
                    '{total}' => $displayTotal,
                    '{title}' => $title,
                    '{author}' => $author,
                    '{date}' => $date,
                ]);

                if ($pos === 'header') {
                    $entry['header'] = $label;
                } else { // footer
                    $entry['footer'] = $label;
                }
            }

            $result[] = $entry;
        }

        return $result;
    }

    /**
     * Convert integer to Roman numerals (basic form up to 3999).
     */
    private function toRoman(int $num): string
    {
        $map = [
            'M' => 1000,
            'CM' => 900,
            'D' => 500,
            'CD' => 400,
            'C' => 100,
            'XC' => 90,
            'L' => 50,
            'XL' => 40,
            'X' => 10,
            'IX' => 9,
            'V' => 5,
            'IV' => 4,
            'I' => 1,
        ];
        $res = '';
        foreach ($map as $roman => $value) {
            while ($num >= $value) {
                $res .= $roman;
                $num -= $value;
            }
        }
        return $res;
    }
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

        $placement = $settings['line_numbers_placement'] ?? 'inline';
        $startLine = $settings['start_line'] ?? 1;
        $lineFormat = $settings['line_format'] ?? '%d: %s';

        $result = [];
        $currentLineNo = $startLine;

        foreach ($pages as $page) {
            if ($mode === 'page') {
                // Reset line number for each page
                $currentLineNo = $startLine;
            }

            $outPage = $page; // preserve header/footer etc.
            $lines = $page['lines'] ?? [];

            if ($placement === 'inline') {
                $numberedLines = [];
                foreach ($lines as $line) {
                    $numberedLines[] = sprintf($lineFormat, $currentLineNo, $line);
                    $currentLineNo++;
                }
                $outPage['lines'] = $numberedLines;
            } else { // margin
                $gutter = [];
                foreach ($lines as $_) {
                    $gutter[] = (string)$currentLineNo;
                    $currentLineNo++;
                }
                $outPage['line_numbers'] = $gutter;
                // lines remain unchanged
            }

            $result[] = $outPage;
        }

        return $result;
    }
}
?>