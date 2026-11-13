<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$user = require_customer();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_post_csrf();
    $name = trim((string) ($_POST['customer_name'] ?? ''));
    $address = trim((string) ($_POST['shipping_address'] ?? ''));
    $method = (string) ($_POST['payment_method'] ?? '');
    $outcome = (string) ($_POST['demo_outcome'] ?? '');
    if ($name === '' || strlen($name) > 120 || $address === '' || strlen($address) > 500
        || !validate_demo_payment($method, $outcome)) {
        message('Complete your fictional delivery details and choose a valid simulated payment method/outcome.');
        redirect_to('checkout.php');
    }

    $connection = null;
    $transactionStarted = false;
    try {
        $connection = db();
        if (!sqlsrv_begin_transaction($connection)) {
            throw new RuntimeException('Could not start checkout transaction.');
        }
        $transactionStarted = true;
        $items = rows(sql(
            'SELECT c.product_id, c.quantity, p.sku, p.name, p.author, p.price, p.is_active
             FROM dbo.cart_items c WITH (UPDLOCK, HOLDLOCK)
             JOIN dbo.products p ON p.id = c.product_id
             WHERE c.session_key = ? ORDER BY c.product_id',
            [cart_key()]
        ));
        if (!$items) throw new DomainException('Your basket is empty.');
        $totalCents = 0;
        foreach ($items as $item) {
            if (!(bool) $item['is_active'] || (int) $item['quantity'] < 1) {
                throw new DomainException('A basket item is no longer available.');
            }
            $price = price_cents((string) $item['price']);
            if ($price === null) throw new RuntimeException('Invalid product price.');
            $totalCents += $price * (int) $item['quantity'];
            if ($totalCents > 9999999999) throw new DomainException('Basket exceeds checkout limit.');
        }

        // The simulated processor is deterministic. No card numbers, real API
        // calls, bank credentials or real payment authorisations are collected.
        if ($outcome === 'declined') {
            sql('INSERT INTO dbo.payment_attempts(customer_id, order_id, method, outcome, amount)
                 VALUES(?, NULL, ?, ?, ?)',
                 [(int) $user['id'], $method, 'declined', decimal($totalCents)]);
            if (!sqlsrv_commit($connection)) throw new RuntimeException('Could not record declined simulation.');
            $transactionStarted = false;
            message('Simulated payment declined. No order was placed; no stock was changed. Try approval to complete a demo order.');
            redirect_to('checkout.php');
        }

        // Stock changes and order inserts are one SQL transaction.
        foreach ($items as $item) {
            $stmt = sql('UPDATE dbo.products SET stock = stock - ?, updated_at=SYSUTCDATETIME()
                         WHERE id = ? AND is_active=1 AND stock >= ?',
                        [(int)$item['quantity'], (int)$item['product_id'], (int)$item['quantity']]);
            $affected = sqlsrv_rows_affected($stmt);
            sqlsrv_free_stmt($stmt);
            if ($affected !== 1) throw new DomainException('Stock changed during checkout. Please review your basket.');
        }
        $token = bin2hex(random_bytes(16));
        $new = rows(sql(
            'INSERT INTO dbo.orders
             (public_token, customer_id, customer_name, customer_email,
              shipping_address, total, status, payment_status, payment_method)
             OUTPUT INSERTED.id
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$token, (int) $user['id'], $name, (string) $user['email'],
             $address, decimal($totalCents), 'placed', 'simulated_paid', $method]
        ));
        if (!$new) throw new RuntimeException('Order could not be created.');
        $orderId = (int) $new[0]['id'];
        foreach ($items as $item) {
            $unit = price_cents((string) $item['price']) ?? 0;
            sql('INSERT INTO dbo.order_items
                 (order_id, product_id, sku, name, author, quantity, unit_price, line_total)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$orderId, (int) $item['product_id'], (string)$item['sku'],
                 (string)$item['name'], (string)$item['author'], (int)$item['quantity'],
                 decimal($unit), decimal($unit * (int)$item['quantity'])]);
        }
        sql('INSERT INTO dbo.payment_attempts(customer_id,order_id,method,outcome,amount)
             VALUES(?,?,?,?,?)',
             [(int) $user['id'],$orderId,$method,'approved',decimal($totalCents)]);
        sql('INSERT INTO dbo.order_status_events(order_id,status,note) VALUES(?,?,?)',
             [$orderId, 'placed', 'Demonstration order created and virtual payment approved']);
        sql('DELETE FROM dbo.cart_items WHERE session_key=?', [cart_key()]);
        if (!sqlsrv_commit($connection)) throw new RuntimeException('Could not complete checkout.');
        $transactionStarted = false;
        message('Demonstration order placed. Payment simulated as approved; no money was taken.');
        redirect_to('orders.php?order=' . rawurlencode($token));
    } catch (Throwable $e) {
        if ($transactionStarted && $connection !== null) sqlsrv_rollback($connection);
        if ($e instanceof DomainException) message($e->getMessage());
        else {
            error_log('Simulated checkout error: ' . $e->getMessage());
            message('Checkout could not complete. No order was placed; please try again.');
        }
        redirect_to('cart.php');
    }
}
try {
    $items = cart_lines();
} catch (Throwable $e) {
    unavailable($e);
}
$total = 0;
foreach ($items as $item) $total += (price_cents((string) $item['price']) ?? 0) * (int) $item['quantity'];
page_header('Simulated checkout');
?>
<?php if (!$items): ?>
  <p>Your basket is empty. <a href="catalog.php">Browse books</a>.</p>
<?php else: ?>
<section class="panel form-width">
  <h2>Review your order</h2>
  <?php foreach ($items as $item): ?>
    <p><strong><?= h($item['name']) ?></strong> × <?= (int)$item['quantity'] ?>
       — <?= h(pounds((price_cents((string)$item['price']) ?? 0) * (int)$item['quantity'])) ?></p>
  <?php endforeach; ?>
  <p class="total">Total: <?= h(pounds($total)) ?></p>
  <p class="notice"><strong>Coursework simulation only.</strong> No real card data, real payments, bank transfers or deliveries.
    Please enter fictional shipping information.</p>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <label for="customer_name">Fictional recipient name</label>
    <input id="customer_name" name="customer_name" required maxlength="120" value="<?= h($user['name']) ?>">
    <p>Receipt account: <strong><?= h($user['email']) ?></strong></p>
    <label for="shipping_address">Fictional shipping address</label>
    <textarea id="shipping_address" name="shipping_address" rows="3" maxlength="500" required placeholder="123 Demo Street, Test City"></textarea>
    <label for="payment_method">Virtual payment method</label>
    <select id="payment_method" name="payment_method" required>
      <option value="demo_card">Simulated card</option>
      <option value="demo_wallet">Simulated digital wallet</option>
      <option value="demo_bank">Simulated bank transfer</option>
    </select>
    <label for="demo_outcome">Simulation outcome</label>
    <select id="demo_outcome" name="demo_outcome" required>
      <option value="approved">Approve virtual payment and place order</option>
      <option value="declined">Decline virtual payment (no order; stock unchanged)</option>
    </select>
    <button type="submit">Run virtual payment &amp; checkout</button>
  </form>
</section>
<?php endif; ?>
<?php page_footer(); ?>
