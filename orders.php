<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$user = require_customer();
$requestedToken = (string) ($_GET['order'] ?? '');
try {
    if ($requestedToken !== '') {
        $orders = rows(sql(
            'SELECT id, public_token, customer_name, customer_email, shipping_address,
                    total, status, payment_status, payment_method, created_at
             FROM dbo.orders WHERE public_token = ? AND customer_id = ?',
            [$requestedToken, (int) $user['id']]
        ));
        $order = $orders[0] ?? null;
        $items = $order ? rows(sql(
            'SELECT sku, name, author, quantity, unit_price, line_total
             FROM dbo.order_items WHERE order_id = ? ORDER BY id', [(int)$order['id']]
        )) : [];
        $events = $order ? rows(sql(
            'SELECT status, note, changed_at FROM dbo.order_status_events
             WHERE order_id = ? ORDER BY changed_at, id', [(int)$order['id']]
        )) : [];
    } else {
        $orders = rows(sql(
            'SELECT TOP (100) id, public_token, total, status,
                    payment_status, payment_method, created_at
             FROM dbo.orders WHERE customer_id = ? ORDER BY created_at DESC, id DESC',
            [(int)$user['id']]
        ));
    }
} catch (Throwable $e) {
    unavailable($e);
}
if ($requestedToken !== '' && !$order) http_response_code(404);
page_header('My orders and delivery tracking');
?>
<p class="muted">Order tracking is a classroom simulation. Delivery states are changed by the book administrator,
   not by a real shipping company. Only your signed-in reader account can view its orders.</p>
<?php if ($requestedToken !== ''): ?>
  <?php if (!$order): ?>
    <p>Order not found for this account.</p>
  <?php else: ?>
    <section class="panel" id="order-tracking" data-order-token="<?= h($requestedToken) ?>">
      <h2>Order #<?= (int)$order['id'] ?></h2>
      <p>Recipient: <?= h($order['customer_name']) ?></p>
      <p>Fictional delivery address: <?= nl2br(h($order['shipping_address'])) ?></p>
      <p>Status: <strong id="order-status-live"><?= h(ucfirst((string)$order['status'])) ?></strong>
         · Payment: <strong><?= h((string)$order['payment_status']) ?></strong>
         · Method: <?= h(demo_payment_label((string)($order['payment_method'] ?? ''))) ?></p>
      <p class="total">Total: <?= h(pounds(price_cents((string)$order['total']) ?? 0)) ?></p>
      <div class="table-wrap"><table>
        <thead><tr><th>Book</th><th>Qty</th><th>Unit</th><th>Line total</th></tr></thead>
        <tbody><?php foreach ($items as $item): ?>
          <tr><td><?= h($item['name']) ?> — <?= h($item['author']) ?></td>
          <td><?= (int)$item['quantity'] ?></td>
          <td><?= h(pounds(price_cents((string)$item['unit_price']) ?? 0)) ?></td>
          <td><?= h(pounds(price_cents((string)$item['line_total']) ?? 0)) ?></td></tr>
        <?php endforeach; ?></tbody>
      </table></div>
      <h3>Order tracking timeline</h3>
      <p id="tracking-last-checked" class="muted">Checking for tracking updates…</p>
      <?php if (!$events): ?><p>Legacy order: initial status only.</p>
      <?php else: ?><ol id="tracking-list" class="timeline">
        <?php foreach ($events as $event): ?>
          <li><strong><?= h(ucfirst((string)$event['status'])) ?></strong>
            — <?= h((string)$event['note']) ?>
            <?php if ($event['changed_at'] instanceof DateTimeInterface): ?>
              <small><?= h($event['changed_at']->format('d M Y H:i')) ?> UTC</small>
            <?php endif; ?></li>
        <?php endforeach; ?>
      </ol><?php endif; ?>
    </section>
  <?php endif; ?>
<?php else: ?>
  <?php if (!$orders): ?>
    <div class="empty-state"><h2>No account orders yet</h2>
      <p>Previous guest-session demonstration orders are not automatically linked to reader accounts.</p>
      <a class="btn" href="catalog.php">Browse books</a></div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>Order</th><th>Total</th><th>Delivery</th><th>Payment</th><th>Track</th></tr></thead>
      <tbody><?php foreach ($orders as $o): ?>
        <tr><td>#<?= (int)$o['id'] ?></td>
        <td><?= h(pounds(price_cents((string)$o['total']) ?? 0)) ?></td>
        <td><?= h(ucfirst((string)$o['status'])) ?></td>
        <td><?= h((string)$o['payment_status']) ?></td>
        <td><a href="orders.php?order=<?= rawurlencode((string)$o['public_token']) ?>">View timeline</a></td></tr>
      <?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
<?php endif; ?>
<p><a href="orders.php">Back to my orders</a></p>
<?php if ($requestedToken !== '' && !empty($order)): ?>
  <script defer src="assets/order_tracking.js"></script>
<?php endif; ?>
<?php page_footer(); ?>
