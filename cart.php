<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/shop.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_post_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $productId = filter_var($_POST['product_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    try {
        if ($productId === false || !in_array($action, ['add', 'update', 'remove'], true)) {
            throw new DomainException('Invalid cart request.');
        }
        if ($action === 'remove') {
            sql('DELETE FROM dbo.cart_items WHERE session_key = ? AND product_id = ?', [cart_key(), $productId]);
            message('Item removed from your cart.');
        } else {
            $quantity = filter_var($_POST['quantity'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
            if ($quantity === false) {
                throw new DomainException('Quantity must be between 1 and 1,000.');
            }
            $products = rows(sql('SELECT id, stock, is_active FROM dbo.products WHERE id = ?', [$productId]));
            if (!$products || !(bool) $products[0]['is_active']) {
                throw new DomainException('This product is no longer available.');
            }
            $existing = rows(sql('SELECT quantity FROM dbo.cart_items WHERE session_key = ? AND product_id = ?', [cart_key(), $productId]));
            $current = $existing ? (int) $existing[0]['quantity'] : 0;
            $desired = $action === 'add' ? $current + $quantity : $quantity;
            if ($desired > (int) $products[0]['stock'] || $desired > 1000) {
                throw new DomainException('Not enough copies available for that quantity.');
            }
            if ($existing) {
                sql('UPDATE dbo.cart_items SET quantity = ?, updated_at = SYSUTCDATETIME() WHERE session_key = ? AND product_id = ?', [$desired, cart_key(), $productId]);
            } else {
                sql('INSERT INTO dbo.cart_items (session_key, product_id, quantity) VALUES (?, ?, ?)', [cart_key(), $productId, $desired]);
            }
            message('Cart updated.');
        }
    } catch (DomainException $e) {
        message($e->getMessage());
    } catch (Throwable $e) {
        error_log('MyShop cart: ' . $e->getMessage());
        message('Could not update your cart. Check the database configuration and try again.');
    }
    redirect_to('cart.php');
}

try {
    $items = cart_lines();
} catch (Throwable $e) {
    unavailable($e);
}
$total = 0;
page_header('Your book basket');
?>
<?php if (!$items): ?>
    <p>Your basket is empty.</p>
    <p><a class="btn" href="catalog.php">Browse more books</a></p>
<?php else: ?>
<div class="table-wrap">
<table>
<thead><tr><th>Product</th><th>Unit price</th><th>Quantity</th><th>Subtotal</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach ($items as $item):
    $unit = price_cents((string) $item['price']) ?? 0;
    $subtotal = $unit * (int) $item['quantity'];
    $total += $subtotal;
?>
<tr>
    <td><strong><?= h($item['name']) ?></strong><br><span class="muted">by <?= h($item['author']) ?> · <?= h($item['book_format']) ?> · <?= h($item['sku']) ?></span>
    <?php if (!(bool) $item['is_active'] || (int) $item['quantity'] > (int) $item['stock']): ?>
        <p class="error">Unavailable or insufficient stock. Update or remove this item.</p>
    <?php endif; ?>
    </td>
    <td><?= h(pounds($unit)) ?></td>
    <td><?= (int) $item['quantity'] ?></td>
    <td><?= h(pounds($subtotal)) ?></td>
    <td>
      <div class="row">
       <form method="post">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="product_id" value="<?= (int) $item['product_id'] ?>">
        <label class="muted" for="qty-<?= (int) $item['product_id'] ?>">Qty</label>
        <input id="qty-<?= (int) $item['product_id'] ?>" type="number" min="1" max="1000" name="quantity" value="<?= (int) $item['quantity'] ?>" required>
        <button type="submit">Update</button>
       </form>
       <form method="post">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="remove">
        <input type="hidden" name="product_id" value="<?= (int) $item['product_id'] ?>">
        <button type="submit" class="danger">Remove</button>
       </form>
      </div>
    </td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<p class="total">Total: <?= h(pounds($total)) ?></p>
<p class="row"><a class="btn secondary" href="catalog.php">Browse more books</a> <a class="btn" href="checkout.php">Checkout books</a></p>
<?php endif; ?>
<?php page_footer(); ?>
