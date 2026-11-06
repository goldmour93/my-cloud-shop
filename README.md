# MyShop / CloudCart — Azure PHP demo shop

A PHP/Azure SQL e-commerce demonstration deployed to Azure App Service **firstwebpage-cs**, with Azure SQL database **callumitaly** on **callum-sql-italy**.

## Features in this pull request

- Searchable public product catalogue with current prices and stock.
- Session-based cart stored in Azure SQL, quantity updates, removal, and stock-aware validation.
- Demonstration checkout (full name, email, shipping address) that stores orders and order-line price snapshots.
- Checkout in **one SQL transaction**: conditional stock updates prevent overselling; any failure rolls back the stock and order changes.
- Admin-only product creation, editing, stock updates and hiding products using a separate hashed administrator password.
- Session-scoped order history and order details.
- Existing registration pages are preserved. **Registration is not yet login/authentication**, and is not linked to cart/orders.
- **No payments are processed**; orders are marked payment_status = not_collected. Payment simulation and delivery tracking are future features.

## Azure setup

1. Review and merge the PR to deploy to Azure App Service. GitHub Actions in .github/workflows/main_firstwebpage-cs.yml handles the deployment after merging.
2. In **Azure App Service → firstwebpage-cs → Settings → Environment variables (App settings)** configure:

   | Setting | Value |
   | --- | --- |
   | DB_USER | SQL login username for callum-sql-italy |
   | DB_PASSWORD | SQL login password (never commit this) |
   | DB_SERVER | Optional; defaults to tcp:callum-sql-italy.database.windows.net,1433 |
   | DB_NAME | Optional; defaults to callumitaly |
   | ADMIN_PASSWORD_HASH | Required for admin access. Result of PHP password_hash (see below) |

3. Ensure App Service can reach Azure SQL with appropriately scoped firewall rules/network settings. The PHP **sqlsrv** extension and Microsoft ODBC driver are required. Check App Service SSH with: php -m | grep -i sqlsrv. The GitHub Actions runner does not verify these runtime drivers.
4. In the **callumitaly Azure SQL Query editor**, run [db/schema.sql](db/schema.sql). It creates products, cart_items, orders and order_items when missing and adds four sample products. Re-running the script preserves existing products/orders.
5. To enable product management, **generate a hash locally**, with a strong unique administrator password. Example PHP CLI command:

       php -r 'echo password_hash(readline("Admin password: "), PASSWORD_DEFAULT), PHP_EOL;'

   Paste **only the resulting hash** into Azure App Service environment variable ADMIN_PASSWORD_HASH and restart the app. Do not commit the password or hash; a hash is still a sensitive verifier.
6. Visit https://firstwebpage-cs.azurewebsites.net/catalog.php for the catalogue and /admin.php for product management.

### Existing registration feature

The existing user-registration code uses dbo.shopusers. If it is missing, run once:

    CREATE TABLE dbo.shopusers (id INT IDENTITY(1,1) PRIMARY KEY, name NVARCHAR(100) NOT NULL, email NVARCHAR(100) NOT NULL, password NVARCHAR(255) NOT NULL, created_at DATETIME2 DEFAULT GETDATE());

Registration uses password_hash() and prepared SQL parameters but **does not implement login, MFA or OAuth**. Do not claim those features in the assessment.

### Security warning

An earlier processregister.php commit included a database password in this **public repository**. Removing the file did not erase Git history. **Rotate/revoke that password in Azure immediately** if it was used. Review Git history and secret-scanning guidance, and do not post secrets in screenshots.

The new shop uses parameterized queries, encoded HTML, CSRF tokens on form changes, session-only access to order details, and a hashed admin password with a per-session login-attempt limit. It is a classroom demonstration, **not a production-ready e-commerce system**. Before real deployment, add full customer authentication, managed identity / Key Vault, stronger account-wide rate limiting, HTTPS enforcement, session lifecycle and privacy/retention policies, automated integration tests, logging/monitoring, and a real payment provider.

## How to test

1. Run db/schema.sql against the correct Azure SQL database and configure environment variables.
2. In /admin.php, sign in and add a product (SKU must be unique). Update its stock and hide/unhide it.
3. In /catalog.php, search for that product and add a quantity to the cart.
4. In /cart.php, update the quantity, remove/add an item, and check the displayed subtotal.
5. Check out through /checkout.php using **demo information only**. No payment occurs.
6. View the saved order in /orders.php from the same browser. Confirm orders and order_items records in Azure SQL.
7. Confirm product stock decreased exactly by the purchased quantity. Try ordering more than available stock to verify checkout rejects it.
8. Check concurrent orders and rollback behavior before showing scaling claims.

Run local tests (no database credentials needed): php tests/shop_helpers_test.php. GitHub Actions syntax-checks all PHP and runs helper tests on PRs; deployment only runs after a merge/push to main.

## Example SQL verification

    SELECT TOP 20 id, sku, name, price, stock FROM dbo.products ORDER BY id DESC;
    SELECT TOP 20 id, customer_email, total, status, payment_status, created_at FROM dbo.orders ORDER BY id DESC;
    SELECT TOP 20 order_id, sku, quantity, unit_price, line_total FROM dbo.order_items ORDER BY id DESC;

## Architecture / scope

Browser → Azure App Service (PHP) → Azure SQL (products, anonymous session carts, orders and line items). The order transaction performs stock checks in the database rather than trusting browser prices or cart quantities. Sessions are held by PHP and shopping-cart records use a hashed PHP session ID, not a customer account ID.

**Not included yet:** multi-factor/OAuth login, registered-user carts, payment-method simulation, dynamic delivery tracking, auto-scaling/CDN/load-balancing proof, customer self-service order management, and GDPR compliance assessment. This PR covers the product catalogue, cart, checkout and stored-order development milestone only.
