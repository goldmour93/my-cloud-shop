<?php
declare(strict_types=1);

// The form posts here; direct visits return to the registration page.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: register.php', true, 303);
    exit;
}

function registrationError(string $message): void
{
    header('Location: register.php?error=' . rawurlencode($message), true, 303);
    exit;
}

function logSqlErrors(string $context): void
{
    error_log('MyShop registration: ' . $context);
    if (!function_exists('sqlsrv_errors')) {
        return;
    }

    $errors = sqlsrv_errors(SQLSRV_ERR_ERRORS);
    if (is_array($errors)) {
        foreach ($errors as $error) {
            // Log codes, not credentials or database details in browser responses.
            error_log(sprintf(
                'MyShop SQL error: state=%s code=%s',
                (string) ($error['SQLSTATE'] ?? 'unknown'),
                (string) ($error['code'] ?? 'unknown')
            ));
        }
    }
}

$name = trim((string) ($_POST['name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

if ($name === '' || $password === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    registrationError('Please enter your name, a valid email address and a password.');
}

// These values are not passwords; Azure App Service settings may override them.
$server = getenv('DB_SERVER') ?: 'tcp:callum-sql-italy.database.windows.net,1433';
$database = getenv('DB_NAME') ?: 'callumitaly';
$username = getenv('DB_USER');
$dbPassword = getenv('DB_PASSWORD');

if (!$username || !$dbPassword) {
    error_log('MyShop registration: DB_USER or DB_PASSWORD is missing from App Service settings.');
    registrationError('Registration is not configured yet. Please contact the site administrator.');
}

if (!function_exists('sqlsrv_connect')) {
    error_log('MyShop registration: the PHP sqlsrv extension is not available.');
    registrationError('Registration is temporarily unavailable. Please try again later.');
}

$conn = sqlsrv_connect($server, [
    'Database' => $database,
    'UID' => $username,
    'PWD' => $dbPassword,
    'Encrypt' => true,
    'TrustServerCertificate' => false,
    'CharacterSet' => 'UTF-8',
]);

if ($conn === false) {
    logSqlErrors('Could not connect to Azure SQL.');
    registrationError('Could not connect to the database. Please try again later.');
}

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$sql = 'INSERT INTO dbo.shopusers (name, email, password) VALUES (?, ?, ?)';
$stmt = sqlsrv_query($conn, $sql, [$name, $email, $hashedPassword]);

if ($stmt === false) {
    logSqlErrors('Failed to insert registration.');
    sqlsrv_close($conn);
    registrationError('Could not save your registration. Please try again later.');
}

sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);

header('Location: success.php', true, 303);
exit;
