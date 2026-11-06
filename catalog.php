<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/shop.php';
try {
    $search = trim((string) ($_GET['q'] ?? ''));
    $search = substr($search, 0, 100);
    $products = $search === ''
        ? rows(sql('SELECT TOP (100) id, sku, name, description, price, stock FROM dbo.products WHERE is_active = 1 ORDER BY name'))
        : rows(sql('SELECT TOP (100) id, sku, name, description, price, stock FROM dbo.products WHERE is_active = 1 AND (name LIKE ? OR sku LIKE ?) ORDER BY name', ['%' . $search . '%', '%' . $search . '%']));
} catch (Throwable $e) {
    unavailable($e);
}
page_header('Product catalogue');
?>
<form method="get" class="row" action="catalog.php">
    <label for="q">Search products</label>
    <input id="q" name="q" style="max-width:300px" value="<?= h($search) ?>" placeholder="Name or SKU">
    <button type="submit">Search</button>
</form>
<p class="muted"><?= count($products) ?> product(s) shown.</p>
<div class="grid">
<?php foreach ($products as $product): ?>
    <article class="card">
        <p class="muted"><?= h($product['sku']) ?></p>
        <h2><?= h($product['name']) ?></h2>
        <p><?= nl2br(h($product['description'])) ?></p>
        <p class="price"><?= h(pounds(price_cents((string) $product['price']) ?? 0)) ?></p>
        <p><?= (int) $product['stock'] > 0 ? h((string) $product['stock']) . ' in stock' : 'Out of stock' ?></p>
        <?php if ((int) $product['stock'] > 0): ?>
        <form action="cart.php" method="post">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
            <label for="qty-<?= (int) $product['id'] ?>">Quantity</label>
            <input id="qty-<?= (int) $product['id'] ?>" type="number" name="quantity" min="1" max="<?= (int) $product['stock'] ?>" value="1" required>
            <button type="submit">Add to cart</button>
        </form>
        <?php endif; ?>
    </article>
<?php endforeach; ?>
</div>
<?php if (!$products): ?><p>No products found.</p><?php endif; ?>
<?php page_footer(); ?>
