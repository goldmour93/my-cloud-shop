<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$user = customer();
page_header($user ? 'Welcome to Starbound Books' : 'Reader account');
?>
<div class="panel form-width">
    <?php if ($user): ?>
        <h2>You're signed in ✦</h2>
        <p>Your reader account is ready. You can save favourites, view orders and set up authenticator MFA.</p>
        <p><a class="btn" href="account.php">Manage my account</a>
           <a class="btn secondary" href="catalog.php">Explore books</a></p>
    <?php else: ?>
        <h2>Get started</h2>
        <p>Create a reader account or sign in to manage favourites and view your simulated orders.</p>
        <p><a class="btn" href="register.php">Create account</a>
           <a class="btn secondary" href="login.php">Sign in</a></p>
    <?php endif; ?>
</div>
<?php page_footer(); ?>
