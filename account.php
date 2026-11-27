<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$user = require_customer();
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_post_csrf();
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'start_mfa') {
            if ((bool) $user['mfa_enabled'] || totp_encryption_key() === null) {
                throw new DomainException('Authenticator setup is unavailable.');
            }
            if (time() - (int) ($_SESSION['customer_authenticated_at'] ?? 0) > 900) {
                throw new DomainException('Sign out and sign back in before configuring MFA.');
            }
            $_SESSION['mfa_setup_secret'] = totp_base32_encode(random_bytes(20));
            $_SESSION['mfa_setup_at'] = time();
            message('Copy the secret into your authenticator app, then confirm a current code.');
        } elseif ($action === 'enable_mfa') {
            $secret = (string) ($_SESSION['mfa_setup_secret'] ?? '');
            if ($secret === '' || time() - (int) ($_SESSION['mfa_setup_at'] ?? 0) > 600
                || (bool) $user['mfa_enabled']) {
                throw new DomainException('Authenticator setup expired. Start again.');
            }
            $counter = totp_matching_counter($secret, (string) ($_POST['code'] ?? ''));
            if ($counter === null) {
                throw new DomainException('Invalid authenticator code. Try the current six-digit code.');
            }
            sql('UPDATE dbo.customer_accounts SET mfa_secret = ?, mfa_enabled = 1, mfa_last_counter = ?
                 WHERE id = ? AND mfa_enabled = 0',
                [totp_encrypt($secret), $counter, (int) $user['id']]);
            unset($_SESSION['mfa_setup_secret'], $_SESSION['mfa_setup_at']);
            message('Authenticator MFA enabled. Future sign-ins will require a code.');
        } elseif ($action === 'disable_mfa') {
            if (!(bool) $user['mfa_enabled']) {
                throw new DomainException('MFA is not enabled.');
            }
            if ($user['password_hash'] !== null
                && !password_verify((string) ($_POST['password'] ?? ''), (string) $user['password_hash'])) {
                throw new DomainException('Current password is incorrect.');
            }
            $secret = totp_decrypt($user['mfa_secret']);
            $counter = $secret ? totp_matching_counter($secret, (string) ($_POST['code'] ?? '')) : null;
            if ($counter === null || ($user['mfa_last_counter'] !== null && $counter <= (int) $user['mfa_last_counter'])) {
                throw new DomainException('The authenticator code is invalid or already used.');
            }
            sql('UPDATE dbo.customer_accounts SET mfa_enabled = 0, mfa_secret = NULL, mfa_last_counter = NULL
                 WHERE id = ? AND mfa_enabled = 1 AND (mfa_last_counter IS NULL OR mfa_last_counter < ?)',
                [(int) $user['id'], $counter]);
            message('Authenticator MFA disabled. You can set it up again later.');
        } else {
            throw new DomainException('Invalid account action.');
        }
    } catch (DomainException $e) {
        message($e->getMessage());
    } catch (Throwable $e) {
        error_log('Account settings error: ' . $e->getMessage());
        message('Could not update your account. Confirm Azure SQL and MFA settings.');
    }
    redirect_to('account.php');
}
$setup = (string) ($_SESSION['mfa_setup_secret'] ?? '');
if ($setup !== '' && time() - (int) ($_SESSION['mfa_setup_at'] ?? 0) > 600) {
    unset($_SESSION['mfa_setup_secret'], $_SESSION['mfa_setup_at']);
    $setup = '';
}
$uri = $setup !== '' ? 'otpauth://totp/' . rawurlencode('Starbound Books:' . $user['email'])
    . '?secret=' . rawurlencode($setup) . '&issuer=' . rawurlencode('Starbound Books')
    . '&algorithm=SHA1&digits=6&period=30' : '';
page_header('My reader account');
?>
<section class="panel form-width">
    <h2>Welcome, <?= h($user['name']) ?></h2>
    <p>Signed in as <?= h($user['email']) ?></p>
    <p class="row"><a class="btn" href="wishlist.php">My wishlist</a>
        <a class="btn secondary" href="orders.php">My orders</a></p>
    <form action="logout.php" method="post">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <button class="secondary" type="submit">Sign out</button>
    </form>
</section>
<section class="panel form-width">
    <h2>Authenticator multi-factor authentication</h2>
    <?php if ((bool) $user['mfa_enabled']): ?>
        <p class="available">MFA is enabled. Future sign-ins require a six-digit authenticator code.</p>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="action" value="disable_mfa">
          <?php if ($user['password_hash'] !== null): ?>
            <label for="password">Current account password</label>
            <input id="password" type="password" name="password" required autocomplete="current-password">
          <?php endif; ?>
          <label for="disable_code">Current six-digit authenticator code</label>
          <input id="disable_code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>
          <button type="submit" class="secondary">Disable MFA</button>
        </form>
    <?php elseif (totp_encryption_key() === null): ?>
        <p class="error">Authenticator enrolment needs the MFA_ENCRYPTION_KEY setting in your PHP Azure App Service.</p>
    <?php elseif ($setup === ''): ?>
        <p>Protect your reader account with Google Authenticator, Microsoft Authenticator or another TOTP app.
           No SMS or external QR service is needed.</p>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="action" value="start_mfa">
          <button type="submit">Set up authenticator</button>
        </form>
    <?php else: ?>
        <p>In your authenticator app, choose <strong>Add account → Enter setup key</strong>, then enter:</p>
        <p>Account: <strong><?= h($user['email']) ?></strong></p>
        <label for="setup_secret">Secret key (keep private; shown only during setup)</label>
        <input id="setup_secret" readonly value="<?= h($setup) ?>">
        <details><summary>Authenticator URI for manual import</summary>
            <p class="muted" style="overflow-wrap:anywhere"><?= h($uri) ?></p>
        </details>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="action" value="enable_mfa">
          <label for="confirm_code">Enter the current six-digit code to confirm</label>
          <input id="confirm_code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>
          <button type="submit">Confirm and enable MFA</button>
        </form>
    <?php endif; ?>
</section>
<section class="panel form-width">
  <h2>Google sign-in (OAuth / OpenID Connect)</h2>
  <?php if ($user['google_subject'] !== null): ?>
    <p class="available">Your Google account is linked.</p>
  <?php elseif (getenv('GOOGLE_OAUTH_CLIENT_ID') && getenv('GOOGLE_OAUTH_CLIENT_SECRET') && getenv('GOOGLE_OAUTH_REDIRECT_URI')): ?>
    <p>Connect your existing reader account to Google. You must remain signed in while linking.</p>
    <p><a class="btn secondary" href="oauth_google.php?link=1">Link Google account</a></p>
  <?php else: ?>
    <p class="muted">Google sign-in is optional and becomes available after its Azure application settings are configured.</p>
  <?php endif; ?>
</section>
<?php page_footer(); ?>
