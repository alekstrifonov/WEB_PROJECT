<?php
declare(strict_types=1);

/**
 * core/SectionRules.php
 *
 * Applies break rules for new pages at headings or between files.
 */
final class SectionRules
{
    /**
     * Apply break rules to documents.
     *
     * @param array<int, array{name:string, blocks:array<int, array{type:string, html:string, plain:string}>}> $documents
     * @param array{break_at_headings?:array<int>, break_between_files?:bool, ...} $settings
     * @return array<int, array{name:string, blocks:array<int, array{type:string, html:string, plain:string}>}>
     */
    public function applyBreakRules(array $documents, array $settings = []): array
    {
        $breakAtHeadings = $settings['break_at_headings'] ?? [1]; // array of heading levels to break before
        $breakBetweenFiles = $settings['break_between_files'] ?? true;

        $modified = [];

        foreach ($documents as $index => $doc) {
            $blocks = $doc['blocks'];
            $newBlocks = [];

            foreach ($blocks as $block) {
                if (preg_match('/^heading(\d+)$/', $block['type'], $m)) {
                    $level = (int)$m[1];
                    if (in_array($level, $breakAtHeadings)) {
                        $newBlocks[] = $this->createPageBreakBlock();
                    }
                }
                $newBlocks[] = $block;
            }

            if ($breakBetweenFiles && $index < count($documents) - 1) {
                $newBlocks[] = $this->createPageBreakBlock();
            }

            $modified[] = [
                'name' => $doc['name'],
                'blocks' => $newBlocks,
            ];
        }

        return $modified;
    }

    /**
     * @return array{type:string, html:string, plain:string}
     */
    private function createPageBreakBlock(): array
    {
        return [
            'type' => 'page_break',
            'html' => '',
            'plain' => '',
        ];
    }
}
?>