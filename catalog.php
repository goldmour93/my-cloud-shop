<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

$search = trim((string) ($_GET['q'] ?? ''));
$search = substr($search, 0, 100);
$genre = (string) ($_GET['genre'] ?? '');
if (!in_array($genre, ['', 'Science Fiction', 'Fantasy'], true)) {
    $genre = '';
}
$sort = (string) ($_GET['sort'] ?? 'featured');
$orderBy = match ($sort) {
    'price_asc' => 'p.price ASC, p.id ASC',
    'price_desc' => 'p.price DESC, p.id ASC',
    'newest' => 'p.id DESC',
    default => 'p.name ASC',
};
try {
    $where = ['p.is_active = 1'];
    $parameters = [];
    if ($genre !== '') {
        $where[] = 'p.genre = ?';
        $parameters[] = $genre;
    }
    if ($search !== '') {
        $where[] = '(p.name LIKE ? OR p.author LIKE ? OR p.sku LIKE ?)';
        array_push($parameters, '%' . $search . '%', '%' . $search . '%', '%' . $search . '%');
    }
    $books = rows(sql(
        'SELECT TOP (100) p.id, p.sku, p.name, p.author, p.genre, p.book_format, p.description, p.price, p.stock, p.cover_url
         FROM dbo.products AS p WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $orderBy,
        $parameters
    ));
} catch (Throwable $e) {
    unavailable($e);
}
page_header('Explore the shelves');
?>
<p class="intro">Famous science-fiction and fantasy books by authors including Brandon Sanderson, Frank Herbert and Andy Weir. Titles are real; prices, availability and checkout are simulated for coursework.</p>
<form method="get" action="catalog.php" class="filters">
    <div><label for="q">Search titles or authors</label><input id="q" name="q" value="<?= h($search) ?>" placeholder="Search the shelves..." maxlength="100"></div>
    <div><label for="genre">Genre</label><select id="genre" name="genre">
        <option value="">All genres</option>
        <option value="Science Fiction" <?= $genre === 'Science Fiction' ? 'selected' : '' ?>>Science fiction</option>
        <option value="Fantasy" <?= $genre === 'Fantasy' ? 'selected' : '' ?>>Fantasy</option>
    </select></div>
    <div><label for="sort">Sort by</label><select id="sort" name="sort">
        <option value="featured" <?= $sort === 'featured' ? 'selected' : '' ?>>Title A–Z</option>
        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Recently added</option>
        <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: low to high</option>
        <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: high to low</option>
    </select></div>
    <button type="submit">Apply filters</button>
</form>
<div class="shelf-heading">
    <p><?= count($books) ?> book<?= count($books) === 1 ? '' : 's' ?> found</p>
    <a href="catalog.php">Clear filters</a>
</div>
<div class="book-grid">
<?php foreach ($books as $book):
    $id = (int) $book['id'];
    $isFantasy = $book['genre'] === 'Fantasy';
    $cover = open_library_cover($book['cover_url'] ?? null);
?>
<article class="book-card">
    <div class="book-cover <?= $isFantasy ? 'fantasy' : 'scifi' ?>">
        <?php if ($cover !== ''): ?>
          <img class="book-cover-image" src="<?= h($cover) ?>"
               alt="Cover of <?= h($book['name']) ?> by <?= h($book['author']) ?>"
               loading="lazy" referrerpolicy="no-referrer" onerror="this.remove()">
        <?php endif; ?>
        <div class="cover-top"><span><?= h($book['genre']) ?></span><span aria-hidden="true">✦</span></div>
        <div class="cover-ornament" aria-hidden="true"><?= $isFantasy ? '❖' : '✧' ?></div>
        <div class="cover-bottom"><strong><?= h($book['name']) ?></strong><small><?= h($book['author']) ?></small></div>
    </div>
    <div class="book-details">
        <p class="book-format"><?= h($book['book_format']) ?> · <?= h($book['genre']) ?></p>
        <h2><?= h($book['name']) ?></h2>
        <p class="author">by <?= h($book['author']) ?></p>
        <?php if (preg_match('/^[0-9]{13}$/D', (string) $book['sku'])): ?>
          <a class="edition-link" href="https://openlibrary.org/isbn/<?= h($book['sku']) ?>" target="_blank" rel="noopener noreferrer">View edition on Open Library</a>
        <?php endif; ?>
        <p class="description"><?= h($book['description']) ?></p>
        <div class="price-line"><strong><?= h(pounds(price_cents((string) $book['price']) ?? 0)) ?></strong>
           <span class="<?= (int) $book['stock'] > 0 ? 'available' : 'sold-out' ?>"><?= (int) $book['stock'] > 0 ? (int) $book['stock'] . ' available' : 'Sold out' ?></span></div>
        <?php if ((int) $book['stock'] > 0): ?>
        <form method="post" action="cart.php" class="add-cart-form">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="product_id" value="<?= $id ?>">
            <label for="qty-<?= $id ?>">Quantity</label>
            <input id="qty-<?= $id ?>" type="number" name="quantity" min="1" max="<?= (int) $book['stock'] ?>" value="1" required>
            <button type="submit">Add to basket</button>
        </form>
        <?php else: ?><p class="muted">Currently unavailable.</p><?php endif; ?>
        <?php if (customer() !== null): ?>
        <form method="post" action="wishlist.php" class="wishlist-inline-form">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="product_id" value="<?= $id ?>">
            <input type="hidden" name="return_to" value="catalog.php">
            <button type="submit" class="secondary">♡ Save to wishlist</button>
        </form>
        <?php else: ?>
            <p><a href="login.php">Sign in to save favourites</a></p>
        <?php endif; ?>
    </div>
</article>
<?php endforeach; ?>
</div>
<?php if (!$books): ?><div class="empty-state"><h2>No books found</h2><p>Try another title, author or genre.</p><a class="btn" href="catalog.php">View all books</a></div><?php endif; ?>
<?php page_footer(); ?>
