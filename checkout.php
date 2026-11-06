<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/shop.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_post_csrf();
    $name = trim((string) ($_POST['customer_name'] ?? ''));
    $email = trim((string) ($_POST['customer_email'] ?? ''));
    $address = trim((string) ($_POST['shipping_address'] ?? ''));
    if ($name === '' || strlen($name) > 120
        || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254
        || $address === '' || strlen($address) > 500) {
        message('Enter a name, valid email and shipping address (within the field limits).');
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
        // Hold cart rows stable while calculating totals and purchasing stock.
        $items = rows(sql(
            'SELECT c.product_id, c.quantity, p.sku, p.name, p.price, p.is_active
             FROM dbo.cart_items AS c WITH (UPDLOCK, HOLDLOCK)
             JOIN dbo.products AS p ON p.id = c.product_id
             WHERE c.session_key = ? ORDER BY c.product_id',
            [cart_key()]
        ));
        if (!$items) {
            throw new DomainException('Your cart is empty.');
        }
        $totalCents = 0;
        foreach ($items as $item) {
            if (!(bool) $item['is_active'] || (int) $item['quantity'] < 1) {
                throw new DomainException('A product in your cart is no longer available.');
            }
            $unitCents = price_cents((string) $item['price']);
            if ($unitCents === null) {
                throw new RuntimeException('Invalid stored product price.');
            }
            $totalCents += $unitCents * (int) $item['quantity'];
            if ($totalCents > 9999999999) {
                throw new DomainException('Order total exceeds the checkout limit.');
            }
        }
        // Atomic conditional stock decrements prevent overselling under concurrent orders.
        foreach ($items as $item) {
            $stmt = sql(
                'UPDATE dbo.products SET stock = stock - ?, updated_at = SYSUTCDATETIME()
                 WHERE id = ? AND is_active = 1 AND stock >= ?',
                [(int) $item['quantity'], (int) $item['product_id'], (int) $item['quantity']]
            );
            $affected = sqlsrv_rows_affected($stmt);
            sqlsrv_free_stmt($stmt);
            if ($affected !== 1) {
                throw new DomainException('Stock changed while checking out. Please review your cart.');
            }
        }

        $token = bin2hex(random_bytes(16));
        $inserted = rows(sql(
            'INSERT INTO dbo.orders (public_token, customer_name, customer_email, shipping_address, total, status, payment_status)
             OUTPUT INSERTED.id VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$token, $name, $email, $address, decimal($totalCents), 'placed', 'not_collected']
        ));
        if (!$inserted) {
            throw new RuntimeException('Order insert returned no identifier.');
        }
        $orderId = (int) $inserted[0]['id'];
        foreach ($items as $item) {
            $unitCents = price_cents((string) $item['price']) ?? 0;
            $lineCents = $unitCents * (int) $item['quantity'];
            sql(
                'INSERT INTO dbo.order_items (order_id, product_id, sku, name, quantity, unit_price, line_total)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$orderId, (int) $item['product_id'], $item['sku'], $item['name'],
                 (int) $item['quantity'], decimal($unitCents), decimal($lineCents)]
            );
        }
        sql('DELETE FROM dbo.cart_items WHERE session_key = ?', [cart_key()]);
        if (!sqlsrv_commit($connection)) {
            throw new RuntimeException('Could not commit checkout.');
        }
        $transactionStarted = false;
        $_SESSION['order_tokens'] = array_slice(
            array_merge((array) ($_SESSION['order_tokens'] ?? []), [$token]),
            -20
        );
        redirect_to('orders.php?order=' . urlencode($token));
    } catch (Throwable $e) {
        if ($transactionStarted && $connection !== null) {
            sqlsrv_rollback($connection);
        }
        if ($e instanceof DomainException) {
            message($e->getMessage());
        } else {
            error_log('MyShop checkout: ' . $e->getMessage());
            message('Checkout could not be completed. No order was placed. Please try again.');
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
foreach ($items as $item) {
    $total += (price_cents((string) $item['price']) ?? 0) * (int) $item['quantity'];
}
page_header('Checkout');
?>
<?php if (!$items): ?>
<p>Your cart is empty. <a href="catalog.php">Browse products</a>.</p>
<?php else: ?>
<div class="panel form-width">
    <h2>Review your order</h2>
    <?php foreach ($items as $item): ?>
    <p><?= h($item['name']) ?> × <?= (int) $item['quantity'] ?> —
       <?= h(pounds((price_cents((string) $item['price']) ?? 0) * (int) $item['quantity'])) ?></p>
    <?php endforeach; ?>
    <p class="total">Total: <?= h(pounds($total)) ?></p>
    <p class="notice">Demo checkout only. No payment is taken or recorded as paid.</p>
    <form method="post">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <label for="customer_name">Full name</label>
        <input id="customer_name" name="customer_name" maxlength="120" autocomplete="name" required>
        <label for="customer_email">Email</label>
        <input id="customer_email" name="customer_email" type="email" maxlength="254" autocomplete="email" required>
        <label for="shipping_address">Shipping address</label>
        <textarea id="shipping_address" name="shipping_address" rows="4" maxlength="500" autocomplete="street-address" required></textarea>
        <button type="submit">Place demonstration order</button>
    </form>
</div>
<?php endif; ?>
<?php page_footer(); ?>
