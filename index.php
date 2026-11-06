<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/shop.php';
page_header('Welcome to MyShop');
?>
<section class="hero">
    <h2>Shop the CloudCart collection</h2>
    <p>Browse products, build a cart and place a demonstration order with live stock checks.</p>
    <a class="btn" href="catalog.php">Browse products</a>
    <a class="btn secondary" href="register.php">Create an account</a>
</section>
<div class="grid">
    <div class="card"><h2>Product catalogue</h2><p>Current products, prices and stock levels from Azure SQL.</p><a href="catalog.php">Open catalogue</a></div>
    <div class="card"><h2>Your shopping cart</h2><p>Update quantities before checkout.</p><a href="cart.php">View cart</a></div>
    <div class="card"><h2>Order tracking</h2><p>View orders you placed in this browser session.</p><a href="orders.php">My orders</a></div>
</div>
<?php page_footer(); ?>
