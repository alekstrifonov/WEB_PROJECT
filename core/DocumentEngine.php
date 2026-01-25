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
        $this->htmlReader = new HtmlReader();
        $this->htmlSanitizer = new HtmlSanitizer();
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
    public function process(array $input): array
    {
        $htmls = $input['htmls'] ?? [];
        $settings = $input['settings'] ?? [];
        $meta = $input['meta'] ?? [];

        // Read HTML
        $raw_htmls = $this->htmlReader->read($htmls);

        // Sanitize HTML
        $cleaned_htmls = $this->htmlSanitizer->convert($raw_htmls);

        // Convert HTML to blocks
        $documents = $this->htmlToBlocks->convert($cleaned_htmls);

        // Apply section rules
        $documents = $this->sectionRules->applyBreakRules($documents, $settings);

        // Collect all lines
        $allLines = [];
        foreach ($documents as $doc) {
            foreach ($doc['blocks'] as $block) {
                if ($block['type'] === 'page_break') {
                    $allLines[] = '---PAGE_BREAK---';
                    continue;
                }
                // Normalize block
                $normalizedBlocks = $this->textNormalizer->toPlainTextBlocks([$block], $settings);
                $normBlock = $normalizedBlocks[0];
                // Wrap plain
                $lines = $this->lineWrapper->wrap($normBlock['plain'], $settings['cols'] ?? 80, $settings['wrap_long'] ?? false);
                $allLines = array_merge($allLines, $lines);
            }
        }

        // Paginate
        $contentPages = $this->paginator->paginate($allLines, $settings);

        // Apply line numbers
        $contentPages = $this->numbering->applyLineNumbers($contentPages, $settings);

        // Compute stats
        $stats = $this->statsCalculator->compute($contentPages);

        // Build meta if enabled
        $metaPages = [];
        if (($meta['placement'] ?? 'none') !== 'none') {
            $metaBlock = $this->metadataBuilder->build($meta);
            if (!empty($metaBlock['lines'])) {
                $metaPages[] = ['lines' => $metaBlock['lines']];
            }
        }

        // Build stats page if enabled
        $statsPages = [];
        if ($settings['include_stats_page'] ?? false) {
            // Build stats lines
            $statsLines = [
                'Statistics',
                'Total Pages: ' . $stats['total_pages'],
                'Total Lines: ' . $stats['total_lines'],
                'Avg Lines per Page: ' . round($stats['avg_lines_per_page'], 2),
            ];
            $statsPages[] = ['lines' => $statsLines];
        }

        // Arrange pages
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

        // CSS vars
        $cssVars = $this->cssPrintProfile->getCssVars($settings);

        return [
            'pages' => $allPages,
            'stats' => $stats,
            'css_vars' => $cssVars,
        ];
    }
}
?>