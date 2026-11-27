<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

/* Google OIDC: HTTPS authorization code + PKCE (S256), state, nonce, local
 * RS256 signature verification using Google's published X.509 JWKS.
 * No tokeninfo debugging endpoint, no trusting unsigned JWT payloads.
 */
function oauth_configured(): bool
{
    return (string) getenv('GOOGLE_OAUTH_CLIENT_ID') !== ''
        && (string) getenv('GOOGLE_OAUTH_CLIENT_SECRET') !== ''
        && (string) getenv('GOOGLE_OAUTH_REDIRECT_URI') !== '';
}

function b64url(string $binary): string
{
    return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
}

function b64url_decode(string $encoded): string|false
{
    if (!preg_match('/^[A-Za-z0-9_-]+$/D', $encoded)) {
        return false;
    }
    return base64_decode(strtr($encoded, '-_', '+/') . str_repeat('=', (4 - strlen($encoded) % 4) % 4), true);
}

/** HTTP client is limited to fixed, known Google URLs. */
function google_http_json(string $url, ?array $form = null): array
{
    if (!in_array($url, ['https://oauth2.googleapis.com/token',
                          'https://www.googleapis.com/oauth2/v3/certs'], true)
        || !function_exists('curl_init')) {
        throw new RuntimeException('Google OAuth HTTPS client is unavailable.');
    }
    $ch = curl_init($url);
    if ($ch === false) throw new RuntimeException('Could not open HTTPS request.');
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ];
    if ($form !== null) {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = http_build_query($form);
        $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/x-www-form-urlencoded';
    }
    curl_setopt_array($ch, $options);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!is_string($raw) || strlen($raw) > 262144 || $status !== 200) {
        throw new RuntimeException('Google OAuth provider HTTP request failed.');
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) throw new RuntimeException('Invalid Google OAuth response.');
    return $data;
}

/**
 * Verify provider signature AND all relevant JWT claims before using any
 * personal data. The token is only used for this one callback and not stored.
 */
function google_verify_id_token(string $jwt, string $expectedNonce, string $clientId): array
{
    if (strlen($jwt) > 20000) throw new DomainException('Unexpected Google token size.');
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) throw new DomainException('Invalid Google identity token.');
    [$h,$p,$s] = $parts;
    $decodedHeader = b64url_decode($h);
    $decodedPayload = b64url_decode($p);
    $signature = b64url_decode($s);
    if (!is_string($decodedHeader) || !is_string($decodedPayload) || !is_string($signature)) {
        throw new DomainException('Invalid Google token encoding.');
    }
    $header = json_decode($decodedHeader, true);
    $claims = json_decode($decodedPayload, true);
    if (!is_array($header) || !is_array($claims)
        || ($header['alg'] ?? null) !== 'RS256'
        || !is_string($header['kid'] ?? null) || strlen($header['kid']) > 200) {
        throw new DomainException('Unsupported Google signing algorithm.');
    }
    $keys = google_http_json('https://www.googleapis.com/oauth2/v3/certs');
    $validSignature = false;
    foreach (($keys['keys'] ?? []) as $key) {
        if (!is_array($key) || ($key['kid'] ?? null) !== $header['kid']
            || ($key['kty'] ?? null) !== 'RSA'
            || !is_array($key['x5c'] ?? null) || !is_string($key['x5c'][0] ?? null)) {
            continue;
        }
        $pem = "-----BEGIN CERTIFICATE-----\n"
            . chunk_split($key['x5c'][0], 64, "\n") . "-----END CERTIFICATE-----\n";
        $public = openssl_pkey_get_public($pem);
        if ($public !== false && openssl_verify($h . '.' . $p, $signature, $public, OPENSSL_ALGO_SHA256) === 1) {
            $validSignature = true;
            break;
        }
    }
    if (!$validSignature
        || !in_array($claims['iss'] ?? null, ['https://accounts.google.com', 'accounts.google.com'], true)
        || !is_string($claims['aud'] ?? null) || !hash_equals($clientId, $claims['aud'])
        || (isset($claims['azp']) && $claims['azp'] !== $clientId)
        || !is_int($claims['exp'] ?? null) || $claims['exp'] <= time()
        || !is_int($claims['iat'] ?? null) || $claims['iat'] > time() + 300
        || !is_string($claims['nonce'] ?? null) || !hash_equals($expectedNonce, $claims['nonce'])
        || !is_string($claims['sub'] ?? null) || strlen($claims['sub']) < 1 || strlen($claims['sub']) > 150
        || !is_string($claims['email'] ?? null)
        || !in_array($claims['email_verified'] ?? null, [true,'true'], true)) {
        throw new DomainException('Google identity token failed verification.');
    }
    return $claims;
}
