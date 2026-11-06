<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/shop.php';
page_header('Create your reader account');
?>
<div class="panel form-width">
    <p class="muted">Join Starbound Books. Registration is a classroom demonstration; account sign-in is not implemented yet.</p>
    <?php if (isset($_GET['error'])): ?>
        <p class="error" role="alert"><?= h($_GET['error']) ?></p>
    <?php endif; ?>
    <form method="post" action="process_register.php">
        <label for="name">Full name</label>
        <input id="name" type="text" name="name" maxlength="100" autocomplete="name" required>
        <label for="email">Email address</label>
        <input id="email" type="email" name="email" maxlength="100" autocomplete="email" required>
        <label for="password">Password</label>
        <input id="password" type="password" name="password" autocomplete="new-password" required>
        <button type="submit">Create reader account</button>
    </form>
</div>
<p><a href="index.php">Return to the bookshop</a></p>
<?php page_footer(); ?>
