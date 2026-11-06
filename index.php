<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/shop.php';
page_header('Your next great escape');
?>
<section class="hero">
    <div class="hero-copy">
        <p class="eyebrow">SCIENCE FICTION & FANTASY · INDEPENDENT DEMO BOOKSHOP</p>
        <h2>Other worlds.<br>One bookshop.</h2>
        <p>Discover celebrated science-fiction and fantasy novels from authors including Brandon Sanderson, Frank Herbert and Andy Weir.</p>
        <div class="hero-actions">
            <a class="btn" href="catalog.php">Explore all books</a>
            <a class="btn outline" href="catalog.php?genre=Science+Fiction">Shop sci-fi</a>
            <a class="btn outline" href="catalog.php?genre=Fantasy">Shop fantasy</a>
        </div>
    </div>
    <div class="hero-art" aria-hidden="true">
        <div class="orbit orbit-one"></div><div class="orbit orbit-two"></div>
        <div class="hero-book"><span>STARBOUND</span><strong>THE<br>STORIES<br>AWAIT</strong><small>✦ ✦ ✦</small></div>
    </div>
</section>
<section class="section-heading"><div><p class="eyebrow">BROWSE BY UNIVERSE</p><h2>Find your genre</h2></div></section>
<div class="genre-grid">
    <a class="genre-tile space" href="catalog.php?genre=Science+Fiction">
        <span class="genre-symbol" aria-hidden="true">✧</span>
        <span class="genre-text"><strong>Science fiction</strong><small>Beyond the stars and into tomorrow</small></span>
        <span aria-hidden="true">↗</span>
    </a>
    <a class="genre-tile magic" href="catalog.php?genre=Fantasy">
        <span class="genre-symbol" aria-hidden="true">✦</span>
        <span class="genre-text"><strong>Fantasy</strong><small>Myths, magic and forgotten realms</small></span>
        <span aria-hidden="true">↗</span>
    </a>
</div>
<section class="info-strip">
    <div><strong>Curated worlds</strong><span>Discover real novels with demo prices and simulated stock.</span></div>
    <div><strong>Stock you can see</strong><span>Availability comes from your Azure SQL catalogue.</span></div>
    <div><strong>Practice checkout</strong><span>Place demo orders. No real payment is collected.</span></div>
</section>
<?php page_footer(); ?>
