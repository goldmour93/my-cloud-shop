# Starbound Books — science-fiction & fantasy bookstore

A **fictional demonstration bookstore** for the Cardiff Met CloudCart assignment, based on the existing PHP + Azure App Service + Azure SQL deployment. The existing repository name remains `my-cloud-shop`.

**Current stage:** bookstore features for demonstration and testing; **not** a production store. No real products or payments are offered. Demo authors, book titles, prices and stock are invented.

## What the bookstore does

- Dark, book-themed responsive landing page with science-fiction and fantasy shelves
- Book catalogue with title/author/SKU search, genre filters, price sorting, physical formats, price and live stock
- Admin-only book inventory management: add books; edit title, author, genre, synopsis, price and stock; show/hide books
- Server-side basket stored in Azure SQL: add, update, remove; re-check quantities
- Database-transaction checkout saves orders and price/author/title snapshots and decrements stock atomically
- Session-scoped order summary and item details (not linked to registered accounts yet)
- Read-only JSON API at **/api/books.php**, intended as a safe starting point for the *later* Python Azure Functions integration
- The earlier reader registration pages remain available (registration only; not a working sign-in system)

### Pages and endpoints

| Path | Purpose |
| --- | --- |
| `/` | Starbound Books homepage |
| `/catalog.php` | Search and filter books |
| `/catalog.php?genre=Science+Fiction` | Science-fiction books |
| `/catalog.php?genre=Fantasy` | Fantasy books |
| `/cart.php` | Shopping basket |
| `/checkout.php` | Demonstration order checkout |
| `/orders.php` | Orders created in this browser session |
| `/admin.php` | Admin-only book and stock management |
| `/register.php` | Existing reader registration (not login) |
| `/api/books.php` | Public read-only JSON list of up to 100 active books |
| `/api/books.php?genre=Fantasy` | JSON filtered by genre |

The catalogue API currently reads from Azure SQL using PHP. Later you can have a **separate** Python Azure Function call it over HTTPS, return JSON, or provide new endpoints for the storefront. Avoid replacing the working PHP SQL checkout with an untested function.

## Required Azure SQL setup

**Before** running the new site, open the existing Azure SQL database (as named in the App Service configuration; the workshop used **callumitaly**) → Query editor, and run [db/schema.sql](db/schema.sql).

- Creates `dbo.products`, `dbo.cart_items`, `dbo.orders`, `dbo.order_items` if missing.
- Adds `author`, `genre`, `book_format` to an existing generic prototype `products` table if installed.
- Adds `author` to an existing `order_items` table if installed.
- Inserts **eight fictional sample books**, four science fiction and four fantasy, only if their SKUs are absent.
- Safe to re-run without duplicating seeded books or deleting orders. Old generic sample products will remain if you previously ran the generic schema; hide them through /admin.php if needed.

Verify with:

    SELECT TOP 20 id, sku, name, author, genre, book_format, price, stock
    FROM dbo.products ORDER BY id DESC;

    SELECT TOP 20 id, customer_email, total, status, payment_status, created_at
    FROM dbo.orders ORDER BY id DESC;

    SELECT TOP 20 order_id, sku, name, author, quantity, unit_price, line_total
    FROM dbo.order_items ORDER BY id DESC;

## Azure App Service configuration

The PHP app is deployed by GitHub Actions to **firstwebpage-cs** when changes reach `main`.

In Azure Portal → App Services → firstwebpage-cs → Settings → Environment variables / Configuration:

| App setting | Value |
| --- | --- |
| `DB_USER` | Azure SQL login |
| `DB_PASSWORD` | Azure SQL password, configured **only** in Azure |
| `DB_SERVER` | Optional default: `tcp:callum-sql-italy.database.windows.net,1433` |
| `DB_NAME` | Optional default: `callumitaly` |
| `ADMIN_PASSWORD_HASH` | PHP `password_hash` of a strong admin-only password |

Create the admin password hash **locally**:

    php -r 'echo password_hash(readline("Admin password: "), PASSWORD_DEFAULT), PHP_EOL;'

Copy the resulting **hash** to the `ADMIN_PASSWORD_HASH` setting and save/restart. Never commit the password or hash to GitHub. The PHP `sqlsrv` extension and its Microsoft ODBC driver must be installed and enabled in the Azure App Service runtime (`php -m | grep -i sqlsrv`). The app must be able to reach Azure SQL over its configured network/firewall rules.

### Existing security warning

A **previous commit in the public repository exposed an Azure SQL password**. If that password was ever valid, **rotate/revoke it immediately** in Azure; removing it from current code does not erase Git history. Do not post real credentials or customer details in issues, screenshots, or workshop presentations.

## Test checklist

1. Confirm the existing deployment pipeline passes and the SQL schema has been run.
2. Open /catalog.php; verify fiction/fantasy filters and title/author search.
3. Open /admin.php, sign in with your configured admin password, add a fictional book, edit its price/stock, hide and re-enable it.
4. Add books to /cart.php; update and remove quantities.
5. Use **fake customer details** at /checkout.php. A placed order must create one `orders` row and associated `order_items`; stock falls by purchased quantity.
6. Test checkout of more books than in stock; the transaction must fail without a partial order or negative stock.
7. Revisit /orders.php from the **same browser session** to see order history (it is not a customer account history).
8. Visit /api/books.php and verify JSON contains only active books, no passwords/customer records; test ?genre=Fantasy.
9. Run a concurrent checkout integration test before making strong scalability claims.

No real payment processor is connected; every order uses `payment_status = not_collected`. **Do not enter real card data.** Shipping address is recorded for class demonstration only, so use fake information unless you have appropriate privacy controls and policies.

## Tests and deployment safety

- `php tests/shop_helpers_test.php` runs dependency-free PHP helper tests.
- The GitHub Actions pipeline lints all PHP files and runs tests for each PR.
- PR checks **do not deploy**. Azure deploy happens only on the `main` branch after merge.
- The SQL schema is **not deployed automatically**; you must run it in Azure SQL once.
- The production app/checkout **has not been tested against your Azure database through this PR**. Test manually before claiming it is working.

## Future Python Azure Functions connection

Finish the workshop's `SimpleHello` function first. Then add a Python HTTP-trigger function that makes a GET request to:

    https://<your-actual-PHP-App-Service-hostname>/api/books.php

Use the **exact App Service Default domain** shown in Azure Portal, not a guessed hostname. The function can parse/return catalogue JSON to demonstrate a Python web service alongside PHP + Azure SQL. Keep the endpoint read-only initially and do not embed database passwords or expose admin/order writes. Later consider adding authenticated order APIs, OAuth/MFA, simulated payments, delivery status, monitoring, load tests and GDPR compliance documentation.

## Academic transparency

This is an AI-assisted draft intended for you to study, configure, test and adapt. The assignment brief requires acknowledgement of AI help. Ensure you can explain the code, databases and architecture in your own recorded presentation.
