<?php
declare(strict_types=1);

/**
 * core/LineWrapper.php
 *
 * Wraps text into lines of specified width while preserving inline HTML tags.
 */
final class LineWrapper
{
    /**
     * Wrap text into lines, preserving inline HTML tags like <strong>, <em>, etc.
     *
     * @param string $text
     * @param int $cols Maximum characters per line (counting only visible text, not tags)
     * @param bool $wrapLong Whether to break long words
     * @return string[] Array of lines
     */
    public function wrap(string $text, int $cols, bool $wrapLong = false): array
    {
        if ($cols <= 0) {
            return [$text];
        }

        // Split text into tokens: tags and words (preserving tags)
        $tokens = $this->tokenize($text);
        
        $lines = [];
        $currentLine = '';
        $currentLineVisibleLen = 0;

        foreach ($tokens as $token) {
            if ($this->isTag($token)) {
                // Always append tags without affecting visible length
                $currentLine .= $token;
            } else if (trim($token) === '') {
                // Whitespace token - only count as 1 space for line length
                if ($currentLineVisibleLen > 0) {
                    $currentLine .= ' ';
                    $currentLineVisibleLen += 1;
                }
            } else {
                // This is a word
                $wordLen = strlen($token);
                $spaceNeeded = $currentLineVisibleLen > 0 ? 1 : 0;

                if ($currentLineVisibleLen + $spaceNeeded + $wordLen > $cols && $currentLineVisibleLen > 0) {
                    // Current line is full, save it and start new one
                    $lines[] = rtrim($currentLine);
                    $currentLine = $token;
                    $currentLineVisibleLen = $wordLen;
                } else {
                    if ($spaceNeeded) {
                        $currentLine .= ' ';
                        $currentLineVisibleLen += 1;
                    }
                    $currentLine .= $token;
                    $currentLineVisibleLen += $wordLen;
                }
            }
        }

        if (trim($currentLine) !== '') {
            $lines[] = rtrim($currentLine);
        }

        return $lines;
    }

    /**
     * Tokenize text into tags and words.
     * Returns an array where each element is either a tag or a word.
     *
     * @param string $text
     * @return string[]
     */
    private function tokenize(string $text): array
    {
        $tokens = [];
        $pattern = '/(<[^>]+>|\S+|\s+)/u';
        
        if (preg_match_all($pattern, $text, $matches)) {
            $tokens = $matches[0];
        }
        
        return $tokens;
    }

    /**
     * Check if a token is an HTML tag.
     *
     * @param string $token
     * @return bool
     */
    private function isTag(string $token): bool
    {
        return preg_match('/^<[^>]+>$/u', $token) === 1;
    }
}
?>