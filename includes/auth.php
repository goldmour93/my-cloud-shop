<?php
declare(strict_types=1);
require_once __DIR__ . '/shop.php';

/* Customer identity is separate from the existing book-administrator password.
 * Accounts created via Google OIDC can have a NULL password_hash.
 */
function normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function customer(): ?array
{
    $id = (int) ($_SESSION['customer_id'] ?? 0);
    if ($id < 1 || !($_SESSION['customer_authenticated'] ?? false)) {
        return null;
    }
    $result = rows(sql(
        'SELECT id, name, email, password_hash, google_subject, mfa_enabled, mfa_secret,
                mfa_last_counter, login_failures,
                CASE WHEN locked_until > SYSUTCDATETIME() THEN 1 ELSE 0 END AS is_locked
         FROM dbo.customer_accounts WHERE id = ?', [$id]
    ));
    if (!$result) {
        unset($_SESSION['customer_id'], $_SESSION['customer_authenticated']);
        return null;
    }
    return $result[0];
}

function require_customer(): array
{
    $user = customer();
    if ($user === null) {
        message('Sign in to access your account, wishlist and orders.');
        redirect_to('login.php');
    }
    return $user;
}

function complete_customer_login(int $id): void
{
    $oldKey = cart_key();
    session_regenerate_id(true);
    $_SESSION['customer_id'] = $id;
    $_SESSION['customer_authenticated'] = true;
    unset($_SESSION['pending_customer_id'], $_SESSION['pending_customer_at'], $_SESSION['mfa_failures']);
    $_SESSION['customer_authenticated_at'] = time();
    // Keep the basket across the session-ID regeneration, if it existed.
    $newKey = cart_key();
    if ($oldKey !== $newKey) {
        sql('UPDATE dbo.cart_items SET session_key = ? WHERE session_key = ?', [$newKey, $oldKey]);
    }
    sql('UPDATE dbo.customer_accounts SET login_failures = 0, locked_until = NULL WHERE id = ?', [$id]);
}

function logout_customer(): void
{
    unset($_SESSION['customer_id'], $_SESSION['customer_authenticated'],
          $_SESSION['pending_customer_id'], $_SESSION['pending_customer_at'],
          $_SESSION['mfa_setup_secret'], $_SESSION['mfa_setup_at']);
    session_regenerate_id(true);
}

function totp_base32_encode(string $bytes): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    for ($i = 0; $i < strlen($bytes); $i++) {
        $bits .= str_pad(decbin(ord($bytes[$i])), 8, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 5) as $chunk) {
        $out .= $alphabet[bindec(str_pad($chunk, 5, '0'))];
    }
    return $out;
}

function totp_base32_decode(string $value): ?string
{
    $value = strtoupper(preg_replace('/\s+/', '', $value) ?? '');
    if ($value === '' || !preg_match('/^[A-Z2-7]+$/D', $value)) {
        return null;
    }
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($value) as $char) {
        $bits .= str_pad(decbin(strpos($alphabet, $char)), 5, '0', STR_PAD_LEFT);
    }
    $result = '';
    foreach (str_split($bits, 8) as $chunk) {
        if (strlen($chunk) === 8) {
            $result .= chr(bindec($chunk));
        }
    }
    return $result;
}

function totp_code(string $secret, int $counter): string
{
    $binary = totp_base32_decode($secret);
    if ($binary === null || strlen($binary) < 10 || $counter < 0) {
        throw new InvalidArgumentException('Invalid authenticator secret or counter.');
    }
    $time = pack('N2', intdiv($counter, 4294967296), $counter % 4294967296);
    $hmac = hash_hmac('sha1', $time, $binary, true);
    $offset = ord($hmac[19]) & 15;
    $number = ((ord($hmac[$offset]) & 127) << 24)
        | (ord($hmac[$offset + 1]) << 16)
        | (ord($hmac[$offset + 2]) << 8)
        | ord($hmac[$offset + 3]);
    return str_pad((string) ($number % 1000000), 6, '0', STR_PAD_LEFT);
}

function totp_matching_counter(string $secret, string $code, ?int $now = null): ?int
{
    $code = trim($code);
    if (!preg_match('/^[0-9]{6}$/D', $code)) {
        return null;
    }
    $current = intdiv($now ?? time(), 30);
    for ($offset = -1; $offset <= 1; $offset++) {
        $candidate = $current + $offset;
        if ($candidate >= 0 && hash_equals(totp_code($secret, $candidate), $code)) {
            return $candidate;
        }
    }
    return null;
}

/* AES-256-GCM keeps authenticator seed encrypted in the Azure SQL table.
 * Configure MFA_ENCRYPTION_KEY in Azure as base64 of 32 random bytes.
 */
function totp_encryption_key(): ?string
{
    $key = base64_decode((string) getenv('MFA_ENCRYPTION_KEY'), true);
    return is_string($key) && strlen($key) === 32 ? $key : null;
}

function totp_encrypt(string $secret): string
{
    $key = totp_encryption_key();
    if ($key === null) {
        throw new RuntimeException('Authenticator encryption key is not configured.');
    }
    $nonce = random_bytes(12);
    $tag = '';
    $encrypted = openssl_encrypt($secret, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag);
    if (!is_string($encrypted)) {
        throw new RuntimeException('Could not encrypt authenticator secret.');
    }
    return $nonce . $tag . $encrypted;
}

function totp_decrypt(mixed $encrypted): ?string
{
    $key = totp_encryption_key();
    if ($key === null || !is_string($encrypted) || strlen($encrypted) < 29) {
        return null;
    }
    $secret = openssl_decrypt(substr($encrypted, 28), 'aes-256-gcm', $key,
        OPENSSL_RAW_DATA, substr($encrypted, 0, 12), substr($encrypted, 12, 16));
    return is_string($secret) ? $secret : null;
}

function validate_demo_payment(string $method, string $outcome): bool
{
    return in_array($method, ['demo_card','demo_wallet','demo_bank'], true)
        && in_array($outcome, ['approved','declined'], true);
}

function demo_payment_label(string $method): string
{
    return match ($method) {
        'demo_card' => 'Simulated card',
        'demo_wallet' => 'Simulated digital wallet',
        'demo_bank' => 'Simulated bank transfer',
        default => 'Unknown demonstration method',
    };
}

function status_next(string $current): array
{
    return match ($current) {
        'placed' => ['processing', 'cancelled'],
        'processing' => ['dispatched', 'cancelled'],
        'dispatched' => ['delivered'],
        default => [],
    };
}

function safe_return(string $candidate): string
{
    return in_array($candidate, ['catalog.php','wishlist.php','orders.php','checkout.php'], true)
        ? $candidate : 'catalog.php';
}
