<?php
declare(strict_types=1);

/**
 * core/LineWrapper.php
 *
 * Wraps text into lines of specified width.
 */
final class LineWrapper
{
    /**
     * Wrap text into lines.
     *
     * @param string $text
     * @param int $cols Maximum characters per line
     * @param bool $wrapLong Whether to break long words
     * @return string[] Array of lines
     */
    public function wrap(string $text, int $cols, bool $wrapLong = false): array
    {
        if ($cols <= 0) {
            return [$text];
        }

        $lines = [];
        $words = explode(' ', $text);
        $currentLine = '';

        foreach ($words as $word) {
            $wordLen = strlen($word);
            $lineLen = strlen($currentLine);

            if ($lineLen + $wordLen + 1 > $cols) { // +1 for space
                if ($currentLine !== '') {
                    $lines[] = $currentLine;
                    $currentLine = '';
                }
                if ($wordLen > $cols && $wrapLong) {
                    // Break long word
                    $remaining = $word;
                    while (strlen($remaining) > $cols) {
                        $lines[] = substr($remaining, 0, $cols);
                        $remaining = substr($remaining, $cols);
                    }
                    $currentLine = $remaining;
                } else {
                    $currentLine = $word;
                }
            } else {
                if ($currentLine !== '') {
                    $currentLine .= ' ';
                }
                $currentLine .= $word;
            }
        }

        if ($currentLine !== '') {
            $lines[] = $currentLine;
        }

        return $lines;
    }
}
?>