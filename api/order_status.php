<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');

function status_json(int $code, array $body): never
{
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    status_json(405, ['error'=>'GET required']);
}
$token = (string) ($_GET['order'] ?? '');
if (!preg_match('/^[a-f0-9]{32}$/D', $token)) status_json(400, ['error'=>'Invalid order token']);
try {
    $user = customer();
    if (!$user) status_json(401, ['error'=>'Reader sign-in required']);
    $order = rows(sql('SELECT id, status, payment_status FROM dbo.orders
                       WHERE public_token=? AND customer_id=?',
                       [$token, (int)$user['id']]));
    if (!$order) status_json(404, ['error'=>'Order not found']);
    $events = rows(sql('SELECT status, note, changed_at FROM dbo.order_status_events
                         WHERE order_id=? ORDER BY changed_at, id',
                         [(int)$order[0]['id']]));
    status_json(200, [
        'status'=>(string)$order[0]['status'],
        'payment_status'=>(string)$order[0]['payment_status'],
        'events'=>array_map(static fn(array $event): array => [
            'status'=>(string)$event['status'],
            'note'=>(string)$event['note'],
            'changed_at'=>$event['changed_at'] instanceof DateTimeInterface
                ? $event['changed_at']->format('d M Y H:i') . ' UTC' : '',
        ], $events),
    ]);
} catch (Throwable $e) {
    error_log('Account order status API failed: ' . $e->getMessage());
    status_json(503, ['error'=>'Tracking temporarily unavailable']);
}
