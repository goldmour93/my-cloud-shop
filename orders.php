<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/shop.php';

$tokens = array_values(array_filter((array) ($_SESSION['order_tokens'] ?? []), static fn ($token) => is_string($token) && preg_match('/^[a-f0-9]{32}$/D', $token)));
$requestedToken = (string) ($_GET['order'] ?? '');
$showDetail = $requestedToken !== '' && in_array($requestedToken, $tokens, true);
try {
    if ($showDetail) {
        $orders = rows(sql('SELECT id, public_token, customer_name, customer_email, shipping_address, total, status, payment_status, created_at FROM dbo.orders WHERE public_token = ?', [$requestedToken]));
        $order = $orders[0] ?? null;
        $items = $order ? rows(sql('SELECT sku, name, quantity, unit_price, line_total FROM dbo.order_items WHERE order_id = ? ORDER BY id', [(int) $order['id']])) : [];
    } elseif ($tokens) {
        $placeholders = implode(',', array_fill(0, count($tokens), '?'));
        $orders = rows(sql('SELECT id, public_token, total, status, payment_status, created_at FROM dbo.orders WHERE public_token IN (' . $placeholders . ') ORDER BY id DESC', $tokens));
    } else {
        $orders = [];
    }
} catch (Throwable $e) {
    unavailable($e);
}
page_header('My orders');
?>
<p class="muted">Orders placed in this browser session are listed here. For this demonstration, orders are not linked to registered accounts.</p>
<?php if ($requestedToken !== ''): ?>
<?php if (!$showDetail || empty($order)): ?>
    <?php http_response_code(404); ?><p>Order not found in this browser session.</p>
<?php else: ?>
<section class="panel">
    <h2>Order #<?= (int) $order['id'] ?></h2>
    <p>Customer: <?= h($order['customer_name']) ?><br>
       Email: <?= h($order['customer_email']) ?><br>
       Shipping address: <?= nl2br(h($order['shipping_address'])) ?></p>
    <p>Status: <strong><?= h($order['status']) ?></strong><br>
       Payment: <strong><?= h($order['payment_status']) ?></strong></p>
    <div class="table-wrap"><table>
    <thead><tr><th>Item</th><th>Qty</th><th>Unit price</th><th>Line total</th></tr></thead>
    <tbody><?php foreach ($items as $item): ?><tr>
      <td><?= h($item['name']) ?> (<?= h($item['sku']) ?>)</td>
      <td><?= (int) $item['quantity'] ?></td>
      <td><?= h(pounds(price_cents((string) $item['unit_price']) ?? 0)) ?></td>
      <td><?= h(pounds(price_cents((string) $item['line_total']) ?? 0)) ?></td>
    </tr><?php endforeach; ?></tbody></table></div>
    <p class="total"><?= h(pounds(price_cents((string) $order['total']) ?? 0)) ?></p>
</section>
<?php endif; ?>
<?php else: ?>
<?php if (!$orders): ?>
<p>No orders placed in this session yet.</p><a class="btn" href="catalog.php">Shop now</a>
<?php else: ?>
<div class="table-wrap"><table>
<thead><tr><th>Order</th><th>Total</th><th>Status</th><th>Payment</th><th>Details</th></tr></thead>
<tbody><?php foreach ($orders as $order): ?><tr>
<td>#<?= (int) $order['id'] ?></td>
<td><?= h(pounds(price_cents((string) $order['total']) ?? 0)) ?></td>
<td><?= h($order['status']) ?></td>
<td><?= h($order['payment_status']) ?></td>
<td><a href="orders.php?order=<?= urlencode((string) $order['public_token']) ?>">View items</a></td>
</tr><?php endforeach; ?></tbody></table></div>
<?php endif; ?>
<?php endif; ?>
<p><a href="orders.php">Back to all session orders</a></p>
<?php page_footer(); ?>
