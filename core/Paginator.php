<?php
declare(strict_types=1);

/**
 * core/Paginator.php
 *
 * Paginates lines into pages.
 */
final class Paginator
{
    /**
     * Paginate lines into pages.
     *
     * @param string[] $lines
     * @param array{lines_per_page?:int, page_break_marker?:string, ...} $settings
     * @return array<int, array{lines:string[]}>
     */
    public function paginate(array $lines, array $settings = []): array
    {
        $linesPerPage = $settings['lines_per_page'] ?? 50;
        $pageBreakMarker = $settings['page_break_marker'] ?? '---PAGE_BREAK---';

        $pages = [];
        $currentPage = [];

        foreach ($lines as $line) {
            if ($line === $pageBreakMarker) {
                if (!empty($currentPage)) {
                    $pages[] = ['lines' => $currentPage];
                    $currentPage = [];
                }
                // Start new page after break
                continue;
            }

            $currentPage[] = $line;

            if (count($currentPage) >= $linesPerPage) {
                $pages[] = ['lines' => $currentPage];
                $currentPage = [];
            }
        }

        if (!empty($currentPage)) {
            $pages[] = ['lines' => $currentPage];
        }

        return $pages;
    }
}
?>