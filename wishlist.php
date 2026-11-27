<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$user = require_customer();
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_post_csrf();
    $productId = filter_var($_POST['product_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $action = (string) ($_POST['action'] ?? '');
    $return = safe_return((string) ($_POST['return_to'] ?? 'wishlist.php'));
    if ($productId === false || !in_array($action, ['save','remove'], true)) {
        message('Invalid wishlist request.');
        redirect_to($return);
    }
    try {
        if ($action === 'remove') {
            sql('DELETE FROM dbo.wishlist_items WHERE customer_id = ? AND product_id = ?',
                [(int) $user['id'], $productId]);
            message('Removed from your wishlist.');
        } else {
            $product = rows(sql('SELECT id FROM dbo.products WHERE id = ? AND is_active = 1', [$productId]));
            if (!$product) {
                throw new DomainException('This book is not available to save.');
            }
            sql('IF NOT EXISTS(SELECT 1 FROM dbo.wishlist_items WHERE customer_id=? AND product_id=?)
                 INSERT INTO dbo.wishlist_items(customer_id,product_id) VALUES(?,?)',
                [(int) $user['id'],$productId,(int) $user['id'],$productId]);
            message('Book saved to your wishlist.');
        }
    } catch (DomainException $e) {
        message($e->getMessage());
    } catch (Throwable $e) {
        error_log('Wishlist error: ' . $e->getMessage());
        message('Wishlist could not be updated. Verify the Azure SQL migration.');
    }
    redirect_to($return);
}
try {
    $books = rows(sql('SELECT p.id, p.name, p.author, p.genre, p.cover_url, p.price,
                            p.stock, p.is_active
                       FROM dbo.wishlist_items w JOIN dbo.products p ON p.id = w.product_id
                       WHERE w.customer_id = ? ORDER BY w.created_at DESC', [(int) $user['id']]));
} catch (Throwable $e) {
    unavailable($e);
}
page_header('My favourite books');
?>
<p>Saved books belong to your reader account and are available when you sign in again.</p>
<?php if (!$books): ?><div class="empty-state">
    <h2>No favourites yet</h2><p>Browse the books and add some to your wishlist.</p>
    <a class="btn" href="catalog.php">Browse books</a></div>
<?php else: ?>
<div class="book-grid">
<?php foreach ($books as $book): ?>
<article class="panel wishlist-card">
    <?php $cover = open_library_cover($book['cover_url'] ?? null); ?>
    <?php if ($cover !== ''): ?>
      <img class="wishlist-cover" src="<?= h($cover) ?>" alt="Cover of <?= h($book['name']) ?>" loading="lazy">
    <?php endif; ?>
    <h2><?= h($book['name']) ?></h2>
    <p>by <?= h($book['author']) ?> · <?= h($book['genre']) ?></p>
    <p><?= h(pounds(price_cents((string) $book['price']) ?? 0)) ?>
       · <?= (bool) $book['is_active'] && (int) $book['stock'] > 0 ? 'In stock' : 'Unavailable' ?></p>
    <form action="wishlist.php" method="post">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="remove">
      <input type="hidden" name="product_id" value="<?= (int) $book['id'] ?>">
      <input type="hidden" name="return_to" value="wishlist.php">
      <button type="submit" class="secondary">Remove favourite</button>
    </form>
    <a href="catalog.php?q=<?= rawurlencode((string) $book['name']) ?>">View this book</a>
</article>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php page_footer(); ?>
