<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/shop.php';

function assert_same(mixed $expected, mixed $actual, string $description): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($description . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

assert_same(1299, price_cents('12.99'), 'book price in pence');
assert_same(1200, price_cents('12'), 'whole-pound price');
assert_same(1200, price_cents('12.0'), 'one decimal');
assert_same(null, price_cents('12.999'), 'reject excessive precision');
assert_same(null, price_cents('-1'), 'reject negative price');
assert_same(null, price_cents('2e4'), 'reject scientific notation');
assert_same('12.99', decimal(1299), 'decimal formatting');
assert_same('&lt;script&gt;', h('<script>'), 'HTML escaping');
assert_same(64, strlen(cart_key()), 'hashed session cart key');
assert_same(64, strlen(csrf_token()), 'CSRF token length');
assert_same(csrf_token(), csrf_token(), 'CSRF stable within request');
echo "Starbound Books helper tests: OK\n";
