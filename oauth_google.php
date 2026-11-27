<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/google_oidc.php';
if (!oauth_configured()) {
    message('Google login has not been configured in Azure.');
    redirect_to('login.php');
}
$redirectUri = (string) getenv('GOOGLE_OAUTH_REDIRECT_URI');
$domain = parse_url($redirectUri);
if (!is_array($domain)
    || ($domain['scheme'] ?? '') !== 'https'
    || ($domain['path'] ?? '') !== '/oauth_callback.php'
    || isset($domain['query']) || isset($domain['fragment'])) {
    message('Google login redirect URI is not configured correctly.');
    redirect_to('login.php');
}
$link = (string) ($_GET['link'] ?? '') === '1';
$user = $link ? require_customer() : null;
$state = b64url(random_bytes(32));
$verifier = b64url(random_bytes(32));
$nonce = b64url(random_bytes(32));
$_SESSION['google_oauth'] = [
    'state' => $state, 'nonce' => $nonce, 'verifier' => $verifier,
    'created' => time(), 'link_id' => $user ? (int) $user['id'] : 0,
];
$params = [
    'client_id' => (string) getenv('GOOGLE_OAUTH_CLIENT_ID'),
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'state' => $state,
    'nonce' => $nonce,
    'code_challenge' => b64url(hash('sha256', $verifier, true)),
    'code_challenge_method' => 'S256',
    'prompt' => 'select_account',
];
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params), true, 302);
exit;
