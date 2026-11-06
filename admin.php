<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/shop.php';

$authenticated = ($_SESSION['shop_admin'] ?? false) === true;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_post_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'login') {
        $hash = getenv('ADMIN_PASSWORD_HASH');
        $start = (int) ($_SESSION['admin_login_window'] ?? 0);
        if (time() - $start > 900) {
            $_SESSION['admin_login_window'] = time();
            $_SESSION['admin_login_count'] = 0;
        }
        $attempts = (int) ($_SESSION['admin_login_count'] ?? 0);
        if ($attempts >= 5) {
            message('Too many login attempts. Try again in 15 minutes.');
        } elseif (is_string($hash) && $hash !== ''
            && password_verify((string) ($_POST['password'] ?? ''), $hash)) {
            session_regenerate_id(true);
            $_SESSION['shop_admin'] = true;
            $_SESSION['admin_login_count'] = 0;
            message('Administrator signed in.');
        } else {
            $_SESSION['admin_login_count'] = $attempts + 1;
            message('Incorrect admin password or admin access not configured.');
        }
        redirect_to('admin.php');
    }

    if (!$authenticated) {
        http_response_code(403);
        exit('Administrator login required.');
    }
    if ($action === 'logout') {
        unset($_SESSION['shop_admin']);
        session_regenerate_id(true);
        message('Signed out.');
        redirect_to('admin.php');
    }
    if ($action !== 'save_product') {
        http_response_code(400);
        exit('Unknown action.');
    }

    $productId = (string) ($_POST['product_id'] ?? 'new');
    $sku = trim((string) ($_POST['sku'] ?? ''));
    $name = trim((string) ($_POST['name'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $price = price_cents((string) ($_POST['price'] ?? ''));
    $stock = filter_var($_POST['stock'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1000000]]);
    $active = isset($_POST['is_active']) ? 1 : 0;
    if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.-]{1,63}$/D', $sku)
        || $name === '' || strlen($name) > 120
        || strlen($description) > 1000
        || $price === null || $stock === false) {
        message('Check SKU (2–64 letters/numbers/-/_.), name, description, price and stock.');
        redirect_to('admin.php');
    }

    try {
        if ($productId === 'new') {
            sql('INSERT INTO dbo.products (sku, name, description, price, stock, is_active)
                 VALUES (?, ?, ?, ?, ?, ?)', [$sku, $name, $description, decimal($price), $stock, $active]);
        } else {
            $id = filter_var($productId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) {
                throw new DomainException('Invalid product identifier.');
            }
            $statement = sql('UPDATE dbo.products SET sku = ?, name = ?, description = ?, price = ?, stock = ?, is_active = ?, updated_at = SYSUTCDATETIME()
                              WHERE id = ?', [$sku, $name, $description, decimal($price), $stock, $active, $id]);
            $affected = sqlsrv_rows_affected($statement);
            sqlsrv_free_stmt($statement);
            if ($affected !== 1) {
                throw new DomainException('Product not found.');
            }
        }
        message('Product saved.');
    } catch (DomainException $e) {
        message($e->getMessage());
    } catch (Throwable $e) {
        error_log('MyShop admin product update: ' . $e->getMessage());
        message('Could not save the product. Ensure the SKU is unique and Azure SQL is available.');
    }
    redirect_to('admin.php');
}

page_header('Product management');
if (!$authenticated):
?>
<div class="panel form-width">
    <h2>Administrator login</h2>
    <p>Product creation, editing and stock changes are restricted to the shop administrator.</p>
    <?php if (!getenv('ADMIN_PASSWORD_HASH')): ?>
    <p class="error">Admin access is not configured. Set ADMIN_PASSWORD_HASH in Azure App Service settings. See README.md.</p>
    <?php endif; ?>
    <form method="post">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="login">
        <label for="password">Administrator password</label>
        <input id="password" type="password" name="password" required autocomplete="current-password">
        <button type="submit">Sign in</button>
    </form>
</div>
<?php
    page_footer();
    exit;
endif;
try {
    $products = rows(sql('SELECT TOP (200) id, sku, name, description, price, stock, is_active FROM dbo.products ORDER BY id DESC'));
} catch (Throwable $e) {
    unavailable($e);
}
?>
<form method="post" class="row">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="logout">
    <button class="secondary" type="submit">Sign out</button>
</form>
<h2>Add a product</h2>
<section class="panel form-width">
<form method="post">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="save_product">
    <input type="hidden" name="product_id" value="new">
    <label for="new-sku">SKU (unique)</label><input id="new-sku" name="sku" required maxlength="64" placeholder="CLOUD-ITEM">
    <label for="new-name">Name</label><input id="new-name" name="name" required maxlength="120">
    <label for="new-description">Description</label><textarea id="new-description" name="description" maxlength="1000"></textarea>
    <div class="form-grid">
        <div><label for="new-price">Price (GBP)</label><input id="new-price" name="price" type="text" inputmode="decimal" required placeholder="19.99"></div>
        <div><label for="new-stock">Stock quantity</label><input id="new-stock" name="stock" type="number" min="0" max="1000000" value="0" required></div>
    </div>
    <label><input type="checkbox" name="is_active" value="1" checked> Available for sale</label>
    <button type="submit">Create product</button>
</form>
</section>
<h2>Edit products</h2>
<p class="muted">Change stock, prices or details. Uncheck “Available for sale” to hide a product; orders keep their product snapshots. Up to 200 products are shown.</p>
<?php foreach ($products as $product): $id = (int) $product['id']; ?>
<details class="panel product-admin">
    <summary><?= h($product['name']) ?> · <?= h($product['sku']) ?> · <?= (int) $product['stock'] ?> in stock<?= (bool) $product['is_active'] ? '' : ' · hidden' ?></summary>
    <form method="post">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_product">
        <input type="hidden" name="product_id" value="<?= $id ?>">
        <label for="sku-<?= $id ?>">SKU (unique)</label>
        <input id="sku-<?= $id ?>" name="sku" maxlength="64" required value="<?= h($product['sku']) ?>">
        <label for="name-<?= $id ?>">Name</label>
        <input id="name-<?= $id ?>" name="name" maxlength="120" required value="<?= h($product['name']) ?>">
        <label for="description-<?= $id ?>">Description</label>
        <textarea id="description-<?= $id ?>" name="description" maxlength="1000"><?= h($product['description']) ?></textarea>
        <div class="form-grid">
            <div><label for="price-<?= $id ?>">Price (GBP)</label><input id="price-<?= $id ?>" name="price" inputmode="decimal" required value="<?= h(decimal(price_cents((string) $product['price']) ?? 0)) ?>"></div>
            <div><label for="stock-<?= $id ?>">Stock</label><input id="stock-<?= $id ?>" type="number" name="stock" min="0" max="1000000" required value="<?= (int) $product['stock'] ?>"></div>
        </div>
        <label><input type="checkbox" name="is_active" value="1" <?= (bool) $product['is_active'] ? 'checked' : '' ?>> Available for sale</label>
        <button type="submit">Save changes</button>
    </form>
</details>
<?php endforeach; ?>
<?php page_footer(); ?>
