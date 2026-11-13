<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_post_csrf();
if (customer() !== null) redirect_to('account.php');

$name = trim((string) ($_POST['name'] ?? ''));
$email = normalize_email((string) ($_POST['email'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$confirm = (string) ($_POST['password_confirm'] ?? '');
if ($name === '' || strlen($name) > 120
    || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254
    || strlen($password) < 12 || strlen($password) > 128
    || !hash_equals($password, $confirm)) {
    message('Enter a valid name, email, matching passwords of at least 12 characters.');
    redirect_to('register.php');
}
try {
    $already = rows(sql('SELECT id FROM dbo.customer_accounts WHERE email = ?', [$email]));
    if ($already) {
        message('An account with this email already exists. Sign in instead.');
        redirect_to('login.php');
    }
    $created = rows(sql(
        'INSERT INTO dbo.customer_accounts (name, email, password_hash)
         OUTPUT INSERTED.id VALUES (?, ?, ?)',
        [$name, $email, password_hash($password, PASSWORD_DEFAULT)]
    ));
    complete_customer_login((int) $created[0]['id']);
    message('Registration successful. You can enable authenticator MFA in My account.');
    redirect_to('account.php');
} catch (Throwable $e) {
    error_log('Customer registration error: ' . $e->getMessage());
    message('Could not create your account. Check the email or Azure SQL migration.');
    redirect_to('register.php');
}
