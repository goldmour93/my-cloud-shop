<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$id = (int) ($_SESSION['pending_customer_id'] ?? 0);
$since = (int) ($_SESSION['pending_customer_at'] ?? 0);
if ($id < 1 || $since < 1 || time() - $since > 300) {
    unset($_SESSION['pending_customer_id'], $_SESSION['pending_customer_at']);
    message('Authenticator verification expired. Sign in again.');
    redirect_to('login.php');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_post_csrf();
    $_SESSION['mfa_failures'] = (int) ($_SESSION['mfa_failures'] ?? 0) + 1;
    if ((int) $_SESSION['mfa_failures'] > 5) {
        unset($_SESSION['pending_customer_id'], $_SESSION['pending_customer_at']);
        message('Too many authenticator attempts. Sign in again.');
        redirect_to('login.php');
    }
    try {
        $rows = rows(sql('SELECT mfa_secret, mfa_enabled, mfa_last_counter
                          FROM dbo.customer_accounts WHERE id = ?', [$id]));
        $account = $rows[0] ?? null;
        $secret = $account && (bool) $account['mfa_enabled'] ? totp_decrypt($account['mfa_secret']) : null;
        $counter = $secret ? totp_matching_counter($secret, (string) ($_POST['code'] ?? '')) : null;
        if ($counter !== null) {
            // A code cannot be reused (including within its valid 30-second window).
            $stmt = sql('UPDATE dbo.customer_accounts SET mfa_last_counter = ?
                          WHERE id = ? AND (mfa_last_counter IS NULL OR mfa_last_counter < ?)',
                         [$counter, $id, $counter]);
            $affected = sqlsrv_rows_affected($stmt);
            sqlsrv_free_stmt($stmt);
            if ($affected === 1) {
                complete_customer_login($id);
                message('Authenticator verified. Signed in successfully.');
                redirect_to('account.php');
            }
        }
        message('The code was invalid, expired or already used. Please try the current code.');
    } catch (Throwable $e) {
        error_log('MFA verification failed: ' . $e->getMessage());
        message('Authenticator verification is temporarily unavailable.');
    }
    redirect_to('mfa_verify.php');
}
page_header('Verify authenticator');
?>
<section class="panel form-width">
    <p>Enter the six-digit code from your authenticator app to finish signing in.</p>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <label for="code">Six-digit verification code</label>
      <input id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>
      <button type="submit">Verify and sign in</button>
    </form>
    <p><a href="login.php">Return to sign in</a></p>
</section>
<?php page_footer(); ?>
