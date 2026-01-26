<?php
declare(strict_types=1);

require_once __DIR__ . '/HtmlReader.php';
require_once __DIR__ . '/HtmlSanitizer.php';
require_once __DIR__ . '/HtmlToBlocks.php';
require_once __DIR__ . '/SectionRules.php';
require_once __DIR__ . '/TextNormalizer.php';
require_once __DIR__ . '/LineWrapper.php';
require_once __DIR__ . '/Paginator.php';
require_once __DIR__ . '/Numbering.php';
require_once __DIR__ . '/MetadataBuilder.php';
require_once __DIR__ . '/StatsCalculator.php';
require_once __DIR__ . '/CssPrintProfile.php';

/**
 * core/DocumentEngine.php
 *
 * Facade for processing documents into pages.
 */
final class DocumentEngine
{
    private $htmlToBlocks;
    private $sectionRules;
    private $textNormalizer;
    private $lineWrapper;
    private $paginator;
    private $numbering;
    private $metadataBuilder;
    private $statsCalculator;
    private $cssPrintProfile;

    public function __construct()
    {
        $this->htmlToBlocks = new HtmlToBlocks();
        $this->sectionRules = new SectionRules();
        $this->textNormalizer = new TextNormalizer();
        $this->lineWrapper = new LineWrapper();
        $this->paginator = new Paginator();
        $this->numbering = new Numbering();
        $this->metadataBuilder = new MetadataBuilder();
        $this->statsCalculator = new StatsCalculator();
        $this->cssPrintProfile = new CssPrintProfile();
    }

    /**
     * Process input into output.
     *
     * @param array{htmls:array<int, array{name:string, html:string}>, settings:array, meta:array} $input
     * @return array{pages:array<int, array{lines:string[]}>, stats:array, css_vars:array<string, string>}
     */

    /**
     * Accepts nested $data structure and flattens it for internal use.
     * Reads HTML content from 'tmp_name' in each htmls entry if present.
     */
    public function process(array $input): array
    {
        // 1. Prepare htmls: use 'html' string directly from input (single file)
        $htmls = [];
        if (isset($input['htmls']) && is_array($input['htmls']) && isset($input['htmls']['html']) && is_string($input['htmls']['html'])) {
            $htmls[] = [
                'name' => $input['htmls']['name'] ?? '',
                'html' => $input['htmls']['html'],
            ];
        }

        // 2. Flatten settings
        $page = $input['settings']['page'] ?? [];
        $pagination = $input['settings']['pagination'] ?? [];
        $sections = $input['settings']['sections'] ?? [];
        $settings = [
            'page_size'      => $page['page_size'] ?? '',
            'orientation'    => $page['orientation'] ?? '',
            'page_width'     => $page['page_width'] ?? 0,
            'page_height'    => $page['page_height'] ?? 0,
            'margin'         => $page['margin'] ?? 0,
            'font_size'      => $page['font_size'] ?? 0,
            'line_spacing'   => $page['line_spacing'] ?? '',
            'words'          => $page['words'] ?? 0,
            'lines'          => $page['lines'] ?? 0,
            'show_page_numbers'      => $pagination['show_page_numbers'] ?? 0,
            'no_number_on_first'     => $pagination['no_number_on_first'] ?? 0,
            'page_number_pos'        => $pagination['page_number_pos'] ?? '',
            'page_number_format'     => $pagination['page_number_format'] ?? '',
            'page_number_template'   => $pagination['page_number_template'] ?? '',
            'line_numbers_mode'      => $pagination['line_numbers_mode'] ?? '',
            'line_numbers_placement' => $pagination['line_numbers_placement'] ?? '',
            'new_page_on_header'     => $sections['new_page_on_header'] ?? 0,
            'new_page_on_file'       => $sections['new_page_on_file'] ?? 0,
            'file_name_as_section'   => $sections['file_name_as_section'] ?? 0,
            'wrap_long'              => ($sections['wrap_lines'] ?? '') === 'yes',
            'cols'                   => $input['settings']['cols'] ?? 80,
            'lines_per_page'         => $input['settings']['lines_per_page'] ?? 50,
            'include_stats_page'     => ($input['meta']['statistics_placement'] ?? '') !== 'none',
        ];

        // 3. Flatten meta
        $meta = [
            'title'     => $input['meta']['title'] ?? '',
            'author'    => $input['meta']['author'] ?? '',
            'course'    => $input['meta']['course'] ?? '',
            'citation_template' => $input['meta']['citation_template'] ?? '',
            'placement' => $input['meta']['metadata_placement'] ?? ($input['meta']['placement'] ?? 'none'),
        ];

        // ...existing code (processing pipeline)...
        $documents = $this->htmlToBlocks->convert($htmls);
        // Map UI settings to SectionRules expectations
        $settingsForBreaks = $settings;
        $settingsForBreaks['break_at_headings'] = ($settings['new_page_on_header'] ?? 0) ? [1,2,3,4,5,6] : [];
        $settingsForBreaks['break_between_files'] = ($settings['new_page_on_file'] ?? 0) ? true : false;
        $documents = $this->sectionRules->applyBreakRules($documents, $settingsForBreaks);
        $allLines = [];
        foreach ($documents as $doc) {
            foreach ($doc['blocks'] as $block) {
                if ($block['type'] === 'page_break') {
                    $allLines[] = '---PAGE_BREAK---';
                    continue;
                }
                $normalizedBlocks = $this->textNormalizer->toPlainTextBlocks([$block], $settings);
                $normBlock = $normalizedBlocks[0];
                // Use the HTML version (with inline tags) instead of plain text to preserve formatting
                if ($settings['wrap_long'] ?? false) {
                    $lines = $this->lineWrapper->wrap($block['html'], $settings['cols'] ?? 80, true);
                } else {
                    $lines = [$block['html']];
                }
                $allLines = array_merge($allLines, $lines);
            }
        }
        $contentPages = $this->paginator->paginate($allLines, $settings);
        // First apply line numbers to content lines, then add header/footer labels
        $contentPages = $this->numbering->applyLineNumbers($contentPages, $settings);
        $contentPages = $this->numbering->applyPageNumbers($contentPages, $settings, $meta);
        $stats = $this->statsCalculator->compute($contentPages);
        $metaPages = [];
        if (($meta['placement'] ?? 'none') !== 'none') {
            $metaBlock = $this->metadataBuilder->build($meta);
            if (!empty($metaBlock['lines'])) {
                $metaPages[] = ['lines' => $metaBlock['lines']];
            }
        }
        $statsPages = [];
        if ($settings['include_stats_page'] ?? false) {
            $statsLines = [
                'Statistics',
                'Total Pages: ' . $stats['total_pages'],
                'Total Lines: ' . $stats['total_lines'],
                'Avg Lines per Page: ' . round($stats['avg_lines_per_page'], 2),
            ];
            $statsPages[] = ['lines' => $statsLines];
        }
        $allPages = [];
        if (($meta['placement'] ?? 'none') === 'start') {
            $allPages = array_merge($metaPages, $contentPages);
        } else {
            $allPages = $contentPages;
        }
        if (($meta['placement'] ?? 'none') === 'end') {
            $allPages = array_merge($allPages, $metaPages);
        }
        $allPages = array_merge($allPages, $statsPages);
        $cssVars = $this->cssPrintProfile->getCssVars($settings);
        return [
            'pages' => $allPages,
            'stats' => $stats,
            'css_vars' => $cssVars,
        ];
    }
}
?>