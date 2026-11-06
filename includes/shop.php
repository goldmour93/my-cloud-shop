<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    session_name('MYSHOPSESSID');
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'secure' => $secure,
        'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_start();
}

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function price_cents(string $value): ?int
{
    $value = trim($value);
    if (!preg_match('/^\d{1,7}(?:\.\d{1,2})?$/D', $value)) {
        return null;
    }
    [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '0');
    return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
}

function pounds(int $cents): string
{
    return '£' . number_format($cents / 100, 2, '.', ',');
}

function decimal(int $cents): string
{
    return number_format($cents / 100, 2, '.', '');
}

function db(): mixed
{
    static $connection = null;
    if ($connection !== null) {
        return $connection;
    }
    if (!function_exists('sqlsrv_connect')) {
        throw new RuntimeException('The PHP sqlsrv extension is missing.');
    }
    $user = getenv('DB_USER');
    $password = getenv('DB_PASSWORD');
    if (!$user || !$password) {
        throw new RuntimeException('Azure SQL credentials are not configured.');
    }
    $connection = sqlsrv_connect(
        getenv('DB_SERVER') ?: 'tcp:callum-sql-italy.database.windows.net,1433',
        [
            'Database' => getenv('DB_NAME') ?: 'callumitaly',
            'UID' => $user,
            'PWD' => $password,
            'Encrypt' => true,
            'TrustServerCertificate' => false,
            'CharacterSet' => 'UTF-8',
        ]
    );
    if ($connection === false) {
        throw new RuntimeException('Could not connect to Azure SQL.');
    }
    return $connection;
}

function sql(string $statement, array $params = []): mixed
{
    $result = sqlsrv_query(db(), $statement, $params);
    if ($result === false) {
        $errors = function_exists('sqlsrv_errors') ? sqlsrv_errors(SQLSRV_ERR_ERRORS) : [];
        foreach (is_array($errors) ? $errors : [] as $error) {
            error_log('MyShop SQL error code: ' . ($error['code'] ?? 'unknown'));
        }
        throw new RuntimeException('Database operation failed.');
    }
    return $result;
}

function rows(mixed $statement): array
{
    $result = [];
    while ($row = sqlsrv_fetch_array($statement, SQLSRV_FETCH_ASSOC)) {
        $result[] = $row;
    }
    sqlsrv_free_stmt($statement);
    return $result;
}

function cart_lines(): array
{
    return rows(sql(
        'SELECT c.product_id, c.quantity, p.sku, p.name, p.price, p.stock, p.is_active
         FROM dbo.cart_items AS c
         INNER JOIN dbo.products AS p ON p.id = c.product_id
         WHERE c.session_key = ? ORDER BY p.name',
        [cart_key()]
    ));
}

function cart_key(): string
{
    return hash('sha256', session_id());
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['csrf_token'];
}

function require_post_csrf(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
        || !hash_equals(csrf_token(), (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Invalid or expired form token.');
    }
}

function redirect_to(string $path): never
{
    header('Location: ' . $path, true, 303);
    exit;
}

function message(string $text): void
{
    $_SESSION['shop_message'] = $text;
}

function page_header(string $title): void
{
    $safeTitle = h($title);
    $notice = $_SESSION['shop_message'] ?? '';
    unset($_SESSION['shop_message']);
    echo <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>$safeTitle · MyShop</title>
<link rel="stylesheet" href="assets/shop.css">
</head>
<body>
<header class="site-header"><div class="wrap topbar">
<a class="brand" href="index.php">MyShop</a>
<nav aria-label="Main navigation"><a href="catalog.php">Shop</a><a href="cart.php">Cart</a><a href="orders.php">My orders</a><a href="admin.php">Manage products</a></nav>
</div></header>
<main class="wrap"><h1>$safeTitle</h1>
HTML;
    if ($notice !== '') {
        echo '<p class="notice" role="status">' . h($notice) . '</p>';
    }
}

function page_footer(): void
{
    echo '</main><footer class="site-footer"><div class="wrap">MyShop · CloudCart workshop · Prices in GBP. No payments are collected.</div></footer></body></html>';
}

function unavailable(Throwable $error): never
{
    error_log('MyShop shop error: ' . $error->getMessage());
    http_response_code(503);
    page_header('Shop temporarily unavailable');
    echo '<p>The shop database is not ready. Check the Azure SQL setup and try again.</p>';
    page_footer();
    exit;
}
