<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
function check_auth(bool $ok, string $what): void {
    if (!$ok) throw new RuntimeException('Auth test failed: ' . $what);
}
check_auth(normalize_email("  DEMO@EXAMPLE.ORG  ") === 'demo@example.org', 'normalized email');
check_auth(validate_demo_payment('demo_card','approved'), 'approved demo card');
check_auth(validate_demo_payment('demo_wallet','declined'), 'declined demo wallet');
check_auth(validate_demo_payment('demo_bank','approved'), 'approved demo bank');
check_auth(!validate_demo_payment('stripe','approved'), 'reject real provider');
check_auth(!validate_demo_payment('demo_card','chargeback'), 'reject unknown outcome');
check_auth(status_next('placed') === ['processing','cancelled'], 'placed transitions');
check_auth(status_next('processing') === ['dispatched','cancelled'], 'processing transitions');
check_auth(status_next('delivered') === [], 'terminal delivery state');
check_auth(safe_return('https://evil.example/') === 'catalog.php', 'reject external redirect');
check_auth(safe_return('wishlist.php') === 'wishlist.php', 'valid return');
$raw = '12345678901234567890';
$secret = totp_base32_encode($raw);
check_auth(totp_base32_decode($secret) === $raw, 'TOTP base32 roundtrip');
check_auth(totp_code($secret, 1) === '287082', 'RFC 6238 test vector at 59 seconds');
check_auth(totp_matching_counter($secret, '287082', 59) === 1, 'TOTP accepts valid step');
check_auth(totp_matching_counter($secret, '000000', 59) === null, 'TOTP rejects bad code');
putenv('MFA_ENCRYPTION_KEY=' . base64_encode(random_bytes(32)));
$sealed = totp_encrypt($secret);
check_auth(!str_contains($sealed, $secret), 'TOTP secret not stored as plaintext');
check_auth(totp_decrypt($sealed) === $secret, 'AES-256-GCM TOTP roundtrip');
check_auth(totp_decrypt('bad') === null, 'reject invalid ciphertext');
putenv('MFA_ENCRYPTION_KEY');
echo "CloudCart account/payment/MFA helper tests: OK\n";
