<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/require_login.php';
require_once __DIR__ . '/../core/DocumentEngine.php';
require_once __DIR__ . '/../core/HtmlSanitizer.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Adjust for security
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if ($input === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// Validate input
if (!isset($input['htmls']) || !is_array($input['htmls'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing or invalid htmls']);
    exit;
}

$settings = $input['settings'] ?? [];
$meta = $input['meta'] ?? [];

// Sanitize HTMLs
$sanitizer = new HtmlSanitizer();
$sanitizedHtmls = $sanitizer->sanitize($input['htmls']);

$input['htmls'] = $sanitizedHtmls;

try {
    $engine = new DocumentEngine();
    $output = $engine->process($input);

    echo json_encode($output);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Processing failed: ' . $e->getMessage()]);
}
?>