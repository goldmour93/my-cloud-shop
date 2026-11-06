<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/shop.php';

// Public read-only JSON API to support a future Python Azure Function integration.
// No customer or administrator data is exposed.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    echo json_encode(['error' => 'Only GET is supported.']);
    exit;
}
$genre = (string) ($_GET['genre'] ?? '');
if (!in_array($genre, ['', 'Science Fiction', 'Fantasy'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Genre must be Science Fiction or Fantasy.']);
    exit;
}
try {
    $parameters = [];
    $where = 'WHERE is_active = 1';
    if ($genre !== '') {
        $where .= ' AND genre = ?';
        $parameters[] = $genre;
    }
    $records = rows(sql(
        'SELECT TOP (100) id, sku, name, author, genre, book_format, description, price, stock
         FROM dbo.products ' . $where . ' ORDER BY name ASC',
        $parameters
    ));
    $books = array_map(static fn (array $row): array => [
        'id' => (int) $row['id'],
        'sku' => (string) $row['sku'],
        'title' => (string) $row['name'],
        'author' => (string) $row['author'],
        'genre' => (string) $row['genre'],
        'format' => (string) $row['book_format'],
        'description' => (string) $row['description'],
        'price_gbp' => decimal(price_cents((string) $row['price']) ?? 0),
        'stock' => (int) $row['stock'],
    ], $records);
    echo json_encode(['count' => count($books), 'books' => $books], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    error_log('Starbound Books public API: ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(['error' => 'The book catalogue is temporarily unavailable.']);
}
