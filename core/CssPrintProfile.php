<?php
declare(strict_types=1);

/**
 * core/CssPrintProfile.php
 *
 * Generates CSS variables/rules for print profile.
 */
final class CssPrintProfile
{
    /**
     * Get CSS variables for print.
     *
     * @param array $settings
     * @return array<string, string> CSS variables
     */
    public function getCssVars(array $settings = []): array
    {
        $fontSize = $settings['font_size'] ?? '12pt';
        $lineHeight = $settings['line_height'] ?? '1.2';
        $margin = $settings['margin'] ?? '1in';
        $pageWidth = $settings['page_width'] ?? '8.5in';
        $pageHeight = $settings['page_height'] ?? '11in';

        return [
            '--print-font-size' => $fontSize,
            '--print-line-height' => $lineHeight,
            '--print-margin' => $margin,
            '--print-page-width' => $pageWidth,
            '--print-page-height' => $pageHeight,
            // Add more as needed
        ];
    }
}
?>