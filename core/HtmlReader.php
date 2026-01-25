<?php
declare(strict_types=1);

/**
 * core/HtmlReader.php
 *
 * Reads uploaded HTML files from PHP's temporary upload location (tmp_name),
 * WITHOUT persisting them on the server (no move_uploaded_file()).
 *
 * - No DOMDocument usage
 * - No mbstring required
 * - Uses iconv if available (optional) to fix common encodings (e.g. Windows-1251)
 * - Normalizes line endings to "\n"
 *
 * Expected input (multiple upload):
 *   $_FILES['files'] where name="files[]" in the form
 *
 * Output (per file):
 * [
 *   'name'      => 'file.html',
 *   'html'      => '<!doctype html>...',
 *   'size'      => 12345,
 *   'mime'      => 'text/html',
 *   'warnings'  => string[],
 * ]
 *
 * If a file fails validation, it's returned with:
 * [
 *   'name' => 'file.html',
 *   'error' => '...',
 *   'size' => ...,
 *   'mime' => ...,
 * ]
 */
final class HtmlReader
{
    private int $maxFiles;
    private int $maxBytesPerFile;

    /** @var string[] */
    private array $allowedExtensions;

    public function __construct(
        int $maxFiles = 10,
        int $maxBytesPerFile = 2_000_000, // ~2MB
        array $allowedExtensions = ['html', 'htm']
    ) {
        $this->maxFiles = max(1, $maxFiles);
        $this->maxBytesPerFile = max(1_000, $maxBytesPerFile);
        $this->allowedExtensions = array_values(array_map('strtolower', $allowedExtensions));
    }

    /**
     * Read uploaded files from $_FILES['files'] (multiple) or $_FILES['file'] (single).
     *
     * @param array $files The subarray from $_FILES, e.g. $_FILES['files']
     * @return array<int, array<string, mixed>>
    */
    public function read(array $files): array
    {
        $normalized = $this->normalizeFilesArray($files);

        if (count($normalized) > $this->maxFiles) {
            // Truncate defensively; caller can show a warning.
            $normalized = array_slice($normalized, 0, $this->maxFiles);
        }

        $out = [];
        foreach ($normalized as $f) {
            $out[] = $this->readOne($f);
        }

        return $out;
    }

    // -------------------------
    // Internal
    // -------------------------

    /**
     * Normalize PHP's weird $_FILES structure into a list of file descriptors.
     *
     * @return array<int, array{name:string, type:string, tmp_name:string, error:int, size:int}>
     */
    private function normalizeFilesArray(array $files): array
    {
        // Multiple upload: ['name' => [..], 'tmp_name' => [..], ...]
        if (isset($files['name']) && is_array($files['name'])) {
            $list = [];
            $count = count($files['name']);

            for ($i = 0; $i < $count; $i++) {
                $list[] = [
                    'name' => (string)($files['name'][$i] ?? ''),
                    'type' => (string)($files['type'][$i] ?? ''),
                    'tmp_name' => (string)($files['tmp_name'][$i] ?? ''),
                    'error' => (int)($files['error'][$i] ?? UPLOAD_ERR_NO_FILE),
                    'size' => (int)($files['size'][$i] ?? 0),
                ];
            }
            return $list;
        }

        // Single upload: ['name' => '...', 'tmp_name' => '...', ...]
        if (isset($files['name']) && is_string($files['name'])) {
            return [[
                'name' => (string)($files['name'] ?? ''),
                'type' => (string)($files['type'] ?? ''),
                'tmp_name' => (string)($files['tmp_name'] ?? ''),
                'error' => (int)($files['error'] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int)($files['size'] ?? 0),
            ]];
        }

        return [];
    }

    /**
     * @param array{name:string, type:string, tmp_name:string, error:int, size:int} $f
     * @return array<string, mixed>
     */
    private function readOne(array $f): array
    {
        $name = $this->sanitizeFilename($f['name'] ?? '');
        $mime = (string)($f['type'] ?? '');
        $size = (int)($f['size'] ?? 0);
        $tmp  = (string)($f['tmp_name'] ?? '');
        $err  = (int)($f['error'] ?? UPLOAD_ERR_NO_FILE);

        $base = [
            'name' => $name !== '' ? $name : 'unknown.html',
            'size' => $size,
            'mime' => $mime,
        ];

        // Upload errors
        if ($err !== UPLOAD_ERR_OK) {
            return $base + ['error' => $this->uploadErrorMessage($err)];
        }

        // Basic validation
        if ($name === '' || !$this->hasAllowedExtension($name)) {
            return $base + ['error' => 'Неподдържан файл. Разрешени са .html и .htm'];
        }

        if ($size <= 0) {
            return $base + ['error' => 'Файлът е празен.'];
        }

        if ($size > $this->maxBytesPerFile) {
            return $base + ['error' => 'Файлът е твърде голям за обработка (лимит: ' . $this->maxBytesPerFile . ' bytes).'];
        }

        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return $base + ['error' => 'Липсва временен файл (tmp_name) или upload не е валиден.'];
        }

        $raw = @file_get_contents($tmp);
        if ($raw === false) {
            return $base + ['error' => 'Неуспешно прочитане на временния файл.'];
        }

        // Normalize to UTF-8 text
        $warnings = [];
        $html = $this->toUtf8($raw, $warnings);

        // Normalize line endings
        $html = $this->normalizeLineEndings($html);

        return $base + [
            'html' => $html,
            'warnings' => $warnings,
        ];
    }

    private function sanitizeFilename(string $name): string
    {
        $name = str_replace("\0", '', $name);
        $name = basename($name); // prevent path tricks
        $name = trim($name);

        // Optional: limit length
        if (strlen($name) > 180) {
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $base = pathinfo($name, PATHINFO_FILENAME);
            $base = substr($base, 0, 160);
            $name = $base . ($ext ? '.' . $ext : '');
        }

        return $name;
    }

    private function hasAllowedExtension(string $name): bool
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        return $ext !== '' && in_array($ext, $this->allowedExtensions, true);
    }

    private function normalizeLineEndings(string $text): string
    {
        // Windows CRLF -> LF, old Mac CR -> LF
        return str_replace(["\r\n", "\r"], "\n", $text);
    }

    /**
     * Convert bytes to UTF-8 string with minimal dependencies.
     * Strategy:
     *  1) If BOM UTF-8 -> strip BOM
     *  2) If looks like valid UTF-8 -> keep
     *  3) Try charset from <meta charset=...> or http-equiv
     *  4) If iconv exists, try iconv(from, 'UTF-8//IGNORE', ...)
     *  5) Fallback: assume Windows-1251 -> UTF-8 (common for BG), if iconv exists
     */
    private function toUtf8(string $raw, array &$warnings): string
    {
        // Strip UTF-8 BOM
        if (strncmp($raw, "\xEF\xBB\xBF", 3) === 0) {
            $raw = substr($raw, 3);
            $warnings[] = 'Открит UTF-8 BOM и е премахнат.';
        }

        // If already valid UTF-8, return as-is
        if ($this->isValidUtf8($raw)) {
            return $raw;
        }

        // Try detect from meta charset
        $declared = $this->detectCharsetFromMeta($raw);
        if ($declared !== null) {
            $converted = $this->iconvConvert($raw, $declared, $warnings);
            if ($converted !== null && $this->isValidUtf8($converted)) {
                $warnings[] = "Конвертирано от {$declared} към UTF-8 (по meta charset).";
                return $converted;
            }
        }

        // Common Bulgarian legacy encoding
        $converted1251 = $this->iconvConvert($raw, 'Windows-1251', $warnings);
        if ($converted1251 !== null && $this->isValidUtf8($converted1251)) {
            $warnings[] = 'Конвертирано от Windows-1251 към UTF-8 (fallback).';
            return $converted1251;
        }

        // Last resort: return raw (may display odd chars)
        $warnings[] = 'Неуспешно конвертиране към UTF-8. Използва се суров текст.';
        return $raw;
    }

    private function detectCharsetFromMeta(string $raw): ?string
    {
        // Search only in the beginning for performance and safety
        $head = substr($raw, 0, 20_000);

        // <meta charset="utf-8">
        if (preg_match('/<meta\s+[^>]*charset\s*=\s*["\']?\s*([a-zA-Z0-9\-_]+)\s*["\']?/i', $head, $m)) {
            return $this->normalizeCharsetName($m[1]);
        }

        // <meta http-equiv="Content-Type" content="text/html; charset=windows-1251">
        if (preg_match('/charset\s*=\s*([a-zA-Z0-9\-_]+)/i', $head, $m2)) {
            return $this->normalizeCharsetName($m2[1]);
        }

        return null;
    }

    private function normalizeCharsetName(string $cs): string
    {
        $cs = trim($cs);
        $cs = str_replace('_', '-', $cs);
        return $cs;
    }

    private function iconvConvert(string $raw, string $fromCharset, array &$warnings): ?string
    {
        if (!function_exists('iconv')) {
            $warnings[] = 'iconv не е наличен; пропуснато конвертиране на encoding.';
            return null;
        }

        $fromCharset = trim($fromCharset);
        if ($fromCharset === '') {
            return null;
        }

        $out = @iconv($fromCharset, 'UTF-8//IGNORE', $raw);
        if ($out === false) {
            return null;
        }
        return $out;
    }

    /**
     * UTF-8 validity check without mbstring.
     */
    private function isValidUtf8(string $s): bool
    {
        // preg_match with 'u' fails if invalid UTF-8
        return preg_match('//u', $s) === 1;
    }

    private function uploadErrorMessage(int $code): string
    {
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'Файлът е твърде голям според настройките на сървъра.';

            case UPLOAD_ERR_PARTIAL:
                return 'Файлът е качен частично.';

            case UPLOAD_ERR_NO_FILE:
                return 'Няма качен файл.';

            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Липсва временна директория на сървъра.';

            case UPLOAD_ERR_CANT_WRITE:
                return 'Неуспешно записване на временния файл.';

            case UPLOAD_ERR_EXTENSION:
                return 'Качването е спряно от PHP разширение.';

            default:
                return 'Непозната грешка при качване.';
        }
    }
}

?>