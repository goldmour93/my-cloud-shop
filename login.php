<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
if (customer() !== null) {
    redirect_to('account.php');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_post_csrf();
    $email = normalize_email((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $attempts = (int) ($_SESSION['reader_login_attempts'] ?? 0);
    $window = (int) ($_SESSION['reader_login_window'] ?? 0);
    if (time() - $window > 900) {
        $_SESSION['reader_login_attempts'] = 0;
        $_SESSION['reader_login_window'] = time();
        $attempts = 0;
    }
    if ($attempts >= 10) {
        message('Too many attempts. Try again later.');
        redirect_to('login.php');
    }
    $_SESSION['reader_login_attempts'] = $attempts + 1;
    try {
        $found = filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 254
            ? rows(sql('SELECT id, password_hash, mfa_enabled,
                        CASE WHEN locked_until > SYSUTCDATETIME() THEN 1 ELSE 0 END AS is_locked
                        FROM dbo.customer_accounts WHERE email = ?', [$email]))
            : [];
        $user = $found[0] ?? null;
        // Prevent differing response times from revealing which emails are registered.
        $hash = is_string($user['password_hash'] ?? null) ? $user['password_hash']
            : '$2y$10$vI8aWBnW3rJrKl2.KAgUvuHNOf3C9bkGGSKkHrh8GDFLe.d7gQgMa';
        $valid = password_verify($password, $hash);
        if (!$user || !$valid || (bool) ($user['is_locked'] ?? false)) {
            if ($user && !(bool) $user['is_locked']) {
                sql('UPDATE dbo.customer_accounts
                     SET locked_until = CASE WHEN login_failures >= 4 THEN DATEADD(MINUTE, 15, SYSUTCDATETIME()) ELSE locked_until END,
                         login_failures = CASE WHEN login_failures >= 4 THEN 0 ELSE login_failures+1 END
                     WHERE id = ?', [(int) $user['id']]);
            }
            message('Invalid login details or account temporarily locked.');
        } elseif ((bool) $user['mfa_enabled']) {
            session_regenerate_id(true);
            $_SESSION['pending_customer_id'] = (int) $user['id'];
            $_SESSION['pending_customer_at'] = time();
            $_SESSION['mfa_failures'] = 0;
            redirect_to('mfa_verify.php');
        } else {
            complete_customer_login((int) $user['id']);
            $_SESSION['reader_login_attempts'] = 0;
            message('Welcome back.');
            redirect_to('account.php');
        }
    } catch (Throwable $e) {
        error_log('Customer login error: ' . $e->getMessage());
        message('Sign-in is unavailable. Check that the Azure SQL migration has been run.');
    }
    redirect_to('login.php');
}
page_header('Reader sign in');
?>
<section class="panel form-width">
    <p>Sign in to manage your wishlist, track deliveries and check out with simulated payments.</p>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <label for="email">Email address</label>
      <input id="email" name="email" type="email" autocomplete="username" maxlength="254" required>
      <label for="password">Password</label>
      <input id="password" name="password" type="password" autocomplete="current-password" required>
      <button type="submit">Sign in</button>
    </form>
    <?php if (getenv('GOOGLE_OAUTH_CLIENT_ID') && getenv('GOOGLE_OAUTH_CLIENT_SECRET') && getenv('GOOGLE_OAUTH_REDIRECT_URI')): ?>
      <p><a class="btn secondary" href="oauth_google.php">Continue with Google</a></p>
    <?php else: ?>
      <p class="muted">Google sign-in can be enabled by the administrator using Google OAuth settings in Azure.</p>
    <?php endif; ?>
    <p>New reader? <a href="register.php">Create an account</a>.</p>
</section>
<?php page_footer(); ?>
