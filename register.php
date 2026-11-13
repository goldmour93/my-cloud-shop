<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
if (customer() !== null) redirect_to('account.php');
page_header('Create your reader account');
?>
<div class="panel form-width">
    <p>Register for a demonstration account. Use fictional test details for your coursework.
       Passwords are stored as one-way hashes, never as plaintext.</p>
    <form method="post" action="process_register.php">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <label for="name">Display name</label>
        <input id="name" type="text" name="name" maxlength="120" autocomplete="name" required>
        <label for="email">Email address</label>
        <input id="email" type="email" name="email" maxlength="254" autocomplete="email" required>
        <label for="password">Password (12–128 characters)</label>
        <input id="password" type="password" name="password" minlength="12" maxlength="128" autocomplete="new-password" required>
        <label for="password_confirm">Confirm password</label>
        <input id="password_confirm" type="password" name="password_confirm" minlength="12" maxlength="128" autocomplete="new-password" required>
        <button type="submit">Create reader account</button>
    </form>
    <p>Already registered? <a href="login.php">Sign in</a>.</p>
</div>
<?php page_footer(); ?>
