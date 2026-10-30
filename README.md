# MyShop — Azure PHP registration workshop

This repository deploys a PHP registration website to the Azure App Service **firstwebpage-cs**. Its Azure SQL database is **callumitaly** on **callum-sql-italy**.

## Required Azure App Service settings

In Azure Portal, open **App Services → firstwebpage-cs → Settings → Environment variables (or Configuration) → App settings**, add the following settings and save/restart the app:

| Name | Value |
| --- | --- |
| `DB_USER` | SQL administrator/login username for `callum-sql-italy` |
| `DB_PASSWORD` | Password for that SQL login (do **not** commit it to GitHub) |
| `DB_SERVER` | `tcp:callum-sql-italy.database.windows.net,1433` *(optional; already the code's default)* |
| `DB_NAME` | `callumitaly` *(optional; already the code's default)* |

The PHP `sqlsrv` extension and its Microsoft ODBC driver must be available in the App Service runtime. If registration returns "temporarily unavailable", use **App Service → SSH** to run `php -m | grep -i sqlsrv`. If `sqlsrv` does not appear, the SQL Server PHP driver must be installed/enabled in the hosting environment. See [Microsoft SQLSRV driver troubleshooting](https://learn.microsoft.com/en-us/sql/connect/php/troubleshooting-php-sql-driver).

In Azure SQL server **callum-sql-italy → Networking**, ensure your Web App can reach the database, using an appropriately scoped firewall/networking configuration.

In the **callumitaly → Query editor**, create the workshop table if it does not exist:

```sql
CREATE TABLE dbo.shopusers (
    id INT IDENTITY(1,1) PRIMARY KEY,
    name NVARCHAR(100) NOT NULL,
    email NVARCHAR(100) NOT NULL,
    password NVARCHAR(255) NOT NULL,
    created_at DATETIME2 DEFAULT GETDATE()
);
```

This SQL is for **initial setup only**; do not run it again if the table already exists. Passwords submitted through the form are hashed using `password_hash()`; the database stores the hash, not the raw registration password.

## Test

1. Open `https://firstwebpage-cs.azurewebsites.net/` (or the default domain shown in App Service → Overview).
2. Click **Create Your Account**. You should see Name, Email, and Password fields.
3. Submit a test registration. A successful database insert redirects to `success.php`.
4. In the database's Query editor, verify with:

   ```sql
   SELECT id, name, email, created_at FROM dbo.shopusers ORDER BY id DESC;
   ```

## Security note

An earlier `processregister.php` committed a database password to the **public repository**. Removing the file from the current branch does **not** erase Git history. If that password is (or was) in use, **reset/rotate it immediately in Azure**, then use only the new password in App Service `DB_PASSWORD`. Consider repository history cleanup and secret-scanning guidance; do not post credentials in issues, commits, or screenshots.
