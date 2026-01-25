<?php
declare(strict_types=1);

/**
 * core/StatsCalculator.php
 *
 * Computes statistics from pages.
 */
final class StatsCalculator
{
    /**
     * Compute statistics.
     *
     * @param array<int, array{lines:string[]}> $pages
     * @return array{total_pages:int, total_lines:int, avg_lines_per_page:float, ...}
     */
    public function compute(array $pages): array
    {
        $totalPages = count($pages);
        $totalLines = 0;
        $linesPerPage = [];

        foreach ($pages as $page) {
            $lineCount = count($page['lines']);
            $totalLines += $lineCount;
            $linesPerPage[] = $lineCount;
        }

        $avgLinesPerPage = $totalPages > 0 ? $totalLines / $totalPages : 0;

        return [
            'total_pages' => $totalPages,
            'total_lines' => $totalLines,
            'avg_lines_per_page' => $avgLinesPerPage,
            'lines_per_page' => $linesPerPage,
        ];
    }

    /**
     * Compute normalized stats.
     *
     * @param array $stats
     * @param array $norm Normalization settings
     * @return array
     */
    public function computeNorm(array $stats, array $norm = []): array
    {
        // Example: normalize to percentages or something
        $normalized = $stats;
        if (isset($norm['per_page'])) {
            // Perhaps divide by total_pages
            $normalized['avg_lines_per_page_norm'] = $stats['avg_lines_per_page'] / ($stats['total_pages'] ?: 1);
        }
        return $normalized;
    }
}
?>