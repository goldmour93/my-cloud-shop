<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/google_oidc.php';
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
$state = $_SESSION['google_oauth'] ?? null;
unset($_SESSION['google_oauth']);
if (!is_array($state) || !is_string($state['state'] ?? null)
    || time() - (int) ($state['created'] ?? 0) > 300
    || !is_string($_GET['state'] ?? null)
    || !hash_equals($state['state'], $_GET['state'])
    || !is_string($_GET['code'] ?? null)
    || strlen($_GET['code']) > 4000) {
    message('Google login request expired or was cancelled. Try again.');
    redirect_to('login.php');
}
try {
    $token = google_http_json('https://oauth2.googleapis.com/token', [
        'client_id' => (string) getenv('GOOGLE_OAUTH_CLIENT_ID'),
        'client_secret' => (string) getenv('GOOGLE_OAUTH_CLIENT_SECRET'),
        'code' => $_GET['code'],
        'code_verifier' => (string) $state['verifier'],
        'grant_type' => 'authorization_code',
        'redirect_uri' => (string) getenv('GOOGLE_OAUTH_REDIRECT_URI'),
    ]);
    $claims = google_verify_id_token((string) ($token['id_token'] ?? ''),
       (string) $state['nonce'], (string) getenv('GOOGLE_OAUTH_CLIENT_ID'));
    $googleSub = (string) $claims['sub'];
    $email = normalize_email((string) $claims['email']);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
        throw new DomainException('Google did not return a valid verified email.');
    }
    $existing = rows(sql('SELECT id, email, google_subject, mfa_enabled
                          FROM dbo.customer_accounts WHERE google_subject = ?', [$googleSub]));
    $linked = $existing[0] ?? null;
    $linkId = (int) ($state['link_id'] ?? 0);
    if ($linkId > 0) {
        $current = require_customer();
        if ((int) $current['id'] !== $linkId || $email !== normalize_email((string) $current['email'])
            || ($linked !== null && (int) $linked['id'] !== $linkId)) {
            throw new DomainException('Google account linking requires the same verified email and current reader.');
        }
        sql('UPDATE dbo.customer_accounts SET google_subject = ? WHERE id = ? AND (google_subject IS NULL OR google_subject = ?)',
            [$googleSub,$linkId,$googleSub]);
        message('Google sign-in linked successfully.');
        redirect_to('account.php');
    }
    if ($linked === null) {
        // Never silently attach Google to an existing password account.
        $match = rows(sql('SELECT id FROM dbo.customer_accounts WHERE email = ?', [$email]));
        if ($match) {
            throw new DomainException('An account already uses this email. Sign in with your password and link Google from My account.');
        }
        $displayName = trim((string) ($claims['name'] ?? ''));
        if ($displayName === '') $displayName = strstr($email, '@', true) ?: 'Reader';
        $created = rows(sql(
            'INSERT INTO dbo.customer_accounts(name,email,google_subject)
             OUTPUT INSERTED.id, INSERTED.mfa_enabled VALUES(?,?,?)',
            [substr($displayName, 0, 120),$email,$googleSub]
        ));
        $linked = $created[0];
    }
    $id = (int) $linked['id'];
    if ((bool) $linked['mfa_enabled']) {
        // Only regenerate session once the second factor is verified.
        $_SESSION['pending_customer_id'] = $id;
        $_SESSION['pending_customer_at'] = time();
        $_SESSION['mfa_failures'] = 0;
        redirect_to('mfa_verify.php');
    }
    complete_customer_login($id);
    message('Signed in using Google.');
    redirect_to('account.php');
} catch (Throwable $e) {
    error_log('Google OAuth sign-in failed: ' . $e->getMessage());
    message($e instanceof DomainException ? $e->getMessage() : 'Google login failed. Confirm your OAuth settings and retry.');
    redirect_to('login.php');
}
