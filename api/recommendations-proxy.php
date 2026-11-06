<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/shop.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function recommendation_response(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    recommendation_response(405, ['error' => 'Only GET is supported.']);
}

$genre = (string) ($_GET['genre'] ?? 'Fantasy');
if (!in_array($genre, ['Fantasy', 'Science Fiction'], true)) {
    recommendation_response(400, ['error' => 'Unknown book genre.']);
}

// The browser calls this same-origin PHP proxy; only the server contacts Azure
// Functions. This avoids cross-origin browser configuration and keeps the
// Functions URL under Azure App Service configuration rather than user input.
$functionUrl = (string) (getenv('RECOMMENDATIONS_FUNCTION_URL') ?: '');
$parts = parse_url($functionUrl);
$hostname = is_array($parts) ? strtolower((string) ($parts['host'] ?? '')) : '';
if (!is_array($parts)
    || ($parts['scheme'] ?? '') !== 'https'
    || !str_ends_with($hostname, '.azurewebsites.net')
    || ($parts['path'] ?? '') !== '/api/recommendations'
    || isset($parts['user']) || isset($parts['pass'])
    || isset($parts['fragment'])
    || isset($parts['port'])
    || isset($parts['query'])) {
    recommendation_response(503, [
        'error' => 'Recommendations are not configured yet. The book catalogue is still available.',
    ]);
}
$url = $functionUrl . '?genre=' . rawurlencode($genre) . '&limit=3';

$body = false;
$status = 0;
if (function_exists('curl_init')) {
    $handle = curl_init($url);
    if ($handle !== false) {
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 7,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => 'StarboundBooks-PHP-Proxy/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
    }
} elseif (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
    $context = stream_context_create(['http' => [
        'method' => 'GET',
        'header' => "Accept: application/json\r\n",
        'timeout' => 7,
        'follow_location' => 0,
        'ignore_errors' => true,
    ]]);
    $body = @file_get_contents($url, false, $context, 0, 262145);
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('/^HTTP\/[\d.]+ (\d{3})/', $header, $matches)) {
            $status = (int) $matches[1];
            break;
        }
    }
}

if (!is_string($body) || strlen($body) > 262144 || $status !== 200) {
    error_log('Starbound Books recommendations request failed (HTTP ' . $status . ')');
    recommendation_response(503, ['error' => 'Recommendations are temporarily unavailable. Try again later.']);
}
try {
    $decoded = json_decode($body, true, 10, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    recommendation_response(502, ['error' => 'The recommendation response was invalid.']);
}
if (!is_array($decoded) || !isset($decoded['recommendations']) || !is_array($decoded['recommendations'])) {
    recommendation_response(502, ['error' => 'The recommendation response was incomplete.']);
}

// Return only the known public fields and revalidate the external image host.
$books = [];
foreach (array_slice($decoded['recommendations'], 0, 3) as $entry) {
    if (!is_array($entry)
        || !is_string($entry['title'] ?? null)
        || !is_string($entry['author'] ?? null)
        || !is_string($entry['sku'] ?? null)) {
        continue;
    }
    $books[] = [
        'title' => substr($entry['title'], 0, 120),
        'author' => substr($entry['author'], 0, 120),
        'sku' => substr($entry['sku'], 0, 64),
        'genre' => $genre,
        'price_gbp' => (string) ($entry['price_gbp'] ?? ''),
        'cover_url' => open_library_cover(is_string($entry['cover_url'] ?? null) ? $entry['cover_url'] : null),
    ];
}
recommendation_response(200, [
    'genre' => $genre,
    'count' => count($books),
    'recommendations' => $books,
    'strategy' => 'In-stock books ranked by simulated stock; not personalised',
]);
