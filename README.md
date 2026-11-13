# Starbound Books — sci-fi & fantasy demonstration bookshop

PHP and Azure SQL bookstore deployed to **Azure App Service** using GitHub Actions. This is a **university coursework demonstration, not a real retailer**. The eight book titles, authors and ISBNs refer to **real published editions**; GBP prices, quantities, availability and checkout are **fictional simulations**. **No payment is collected.**

## NEW: Customer login, MFA, Google OAuth, wishlist, virtual payments and order tracking

**Mandatory Azure SQL migration BEFORE merging/deploying this feature:**
[db/03_customer_features.sql](db/03_customer_features.sql). Run in
Azure SQL `callumitaly` Query editor as a separate query (safe to re-run).
For previously registered demo readers from `shopusers`, optionally run
[db/04_import_legacy_readers_optional.sql](db/04_import_legacy_readers_optional.sql)
after the migration.

**Complete setup, Azure environment settings, Google OAuth client
configuration, tests, and GDPR limitations:**
[docs/CUSTOMER_FEATURES_SETUP.md](docs/CUSTOMER_FEATURES_SETUP.md).

New customer URLs: `/register.php`, `/login.php`, `/account.php`,
`/mfa_verify.php`, `/wishlist.php`, `/orders.php`, and `/checkout.php`.
The existing `/admin.php` remains a separate administrator sign-in; new
`/admin_orders.php` provides administrator-only simulated delivery updates.

**Virtual payments never handle real cards or money.** Three mock methods
(card, wallet, bank transfer) and explicit approved/declined outcomes are
available, with attempts recorded in Azure SQL. Approved new orders are
linked to customer accounts and include a status timeline. Old guest-session
orders remain preserved but are not automatically attached to accounts.

**OAuth is implemented but NOT live-enabled until you register a Google
OAuth web app, verify its redirect URI, and set Google client credentials
on the PHP App Service. Authenticator MFA likewise needs an Azure-only
`MFA_ENCRYPTION_KEY`.** Do not claim these are verified live features
without testing them.

---

## The 8 real books shown in the catalogue

| Genre | Book | Author | ISBN-13 (used as the product SKU) | Format |
| --- | --- | --- | --- | --- |
| Science Fiction | Dune | Frank Herbert | 9780441172719 | Paperback |
| Science Fiction | Project Hail Mary | Andy Weir | 9780593135204 | Hardback |
| Science Fiction | Leviathan Wakes | James S. A. Corey | 9780316129084 | Paperback |
| Science Fiction | Neuromancer | William Gibson | 9780441569595 | Paperback |
| Fantasy | Mistborn: The Final Empire | Brandon Sanderson | 9781250318541 | Paperback |
| Fantasy | The Way of Kings | Brandon Sanderson | 9780765326355 | Hardback |
| Fantasy | The Hobbit | J. R. R. Tolkien | 9780547928227 | Paperback |
| Fantasy | The Name of the Wind | Patrick Rothfuss | 9780756404741 | Paperback |

**Cover-image policy:** The catalogue displays the published book covers from [Open Library's cover service](https://openlibrary.org/dev/docs/api/covers), using ISBN URLs such as https://covers.openlibrary.org/b/isbn/9781250318541-M.jpg?default=false. These are **externally served images**, not copied binary cover files in this repository. Open Library recommends linking directly to its cover service for public-facing book listings and appreciates backlinks. The catalogue links each ISBN to its Open Library edition page. Some editions may not have a cover, or Open Library may occasionally be unavailable: the CSS-designed cover acts as a visual fallback. Artwork remains copyrighted by the respective owners; discoverability does not imply permission to reproduce or sell artwork independently. Do not substitute these for confirmed publisher-licensed assets in a commercial launch.

## IMPORTANT: Azure SQL setup (run in this exact order)

The GitHub pull request only updates code. **It cannot run SQL in the Azure Portal.** In your existing `callumitaly` Azure SQL database, open **Query editor → New query** and run the two scripts below as **two separate executions**:

**1. Prepare the database structure** — [db/01_prepare_real_books.sql](db/01_prepare_real_books.sql).

This creates shop tables when missing, or adds missing book fields `author`, `genre`, `book_format` and `cover_url` to the existing `dbo.products` table. Also adds the author snapshot column to order items. Does **not** delete books or orders. `db/schema.sql` contains the same setup for compatibility.

**2. Replace the catalogue** — [db/02_replace_catalogue.sql](db/02_replace_catalogue.sql).

**Destructive to old demo inventory:** Deletes all current shopping-basket rows. Deactivates all existing products. Physically removes obsolete products *only when no saved order references them*, so old order history survives. Keeps referenced historical products hidden. Inserts/activates the eight published books with their ISBNs and Open Library cover URLs. Can be rerun without duplicating book SKUs or resetting stock on already-present books.

**Do not run Script 2 until Script 1 has succeeded.** If Azure Query editor reports a missing column, open a *new query* after completing Script 1; Azure SQL can compile column references early within the same query batch.

Then verify:

```sql
SELECT sku AS ISBN13, name AS Title, author, genre, book_format,
       price, stock, is_active, cover_url
FROM dbo.products
WHERE is_active = 1
ORDER BY genre, name;
```

Expected result: **eight active ISBN listings**, four science fiction and four fantasy. The schema and catalogue are **not** changed merely by merging the PR.

## Site features

- Responsive homepage and image-led science-fiction/fantasy catalogue
- Search by book title, author or ISBN; filter by genre; sort by title, price or date added
- Open Library ISBN edition links and fallback artwork for unavailable covers
- Password-protected book administrator: edit/add books, covers (trusted Open Library URLs only), prices, descriptions and stock
- Azure SQL shopping basket with quantity validation
- Transactional order checkout with conditional stock decrements and order-item snapshots
- Account-linked order history and admin-driven delivery tracking for new checkout orders; legacy guest orders preserved in SQL
- Read-only JSON API at `/api/books.php`; includes a validated `cover_url` per book for a later Python Azure Functions integration
- Customer signup and login with hashed passwords, optional encrypted TOTP MFA and Google OAuth/OIDC provider support

### Test URLs

- `/` — homepage
- `/catalog.php` — all active books
- `/catalog.php?genre=Science+Fiction` — four science-fiction titles
- `/catalog.php?genre=Fantasy` — four fantasy titles
- `/cart.php` — demo basket
- `/checkout.php` — **three virtual payment methods and approved/declined scenarios; no real payments**
- `/orders.php` — session-scoped orders
- `/admin.php` — admin inventory control
- `/api/books.php` — read-only JSON suitable for a later Python Azure Function

## Hosting prerequisites

GitHub Actions runs PHP lint and helper tests on pull requests; it deploys to the Azure App Service **firstwebpage-cs** on merge to `main`. After the PR merges, the deployed PHP code will query the new `cover_url` column; **run the SQL migration promptly** to avoid temporary catalogue errors.

Azure App Service application settings (Configuration / Environment variables):

| Name | Purpose |
| --- | --- |
| `DB_USER` | Azure SQL login; never commit it |
| `DB_PASSWORD` | Azure SQL password; never commit it |
| `DB_SERVER` | Optional, defaults to `tcp:callum-sql-italy.database.windows.net,1433` |
| `DB_NAME` | Optional, defaults to `callumitaly` |
| `ADMIN_PASSWORD_HASH` | Output of PHP `password_hash` using a strong admin password |

The App Service must support PHP and Microsoft's `sqlsrv` extension and have network access to SQL. Example local PHP command to generate an admin password hash:

```bash
php -r 'echo password_hash(readline("Admin password: "), PASSWORD_DEFAULT), PHP_EOL;'
```

A previous public commit reportedly exposed an Azure SQL credential. **Rotate/revoke it if it was ever valid**; changing README or deleting the file does not remove secrets from Git history.

## What to test for your coursework

1. Load the homepage, book covers, the two genre filters, author search and price sort.
2. Confirm eight active ISBN records in SQL. Broken cover fetches should fall back to CSS artwork; an unavailable ISBN cover is not a database error.
3. Add books to the basket and update quantities.
4. Complete checkout using **fake** name, email and shipping address, check that a saved order appears and SQL stock falls.
5. Verify that ordering more books than exist cannot oversell stock.
6. Configure a strong admin password hash and edit a book's price/stock, then confirm changes appear on the site.
7. Call `/api/books.php` and confirm JSON contains only published active products, no credentials or customer data.
8. Test the existing Python Azure Function read-only recommendations API; then run the additional customer-feature checks in `docs/CUSTOMER_FEATURES_SETUP.md`.

**Limitations:** No real payments, fulfilment provider or real retailer inventory. Google OAuth needs external provider credentials before it works. MFA requires a separate Azure encryption key and lacks recovery-code self-service. Legacy guest orders are not automatically linked to accounts. Verification, GDPR rights workflows, production identity assurance and scale-out sessions still require more work before a real e-commerce launch.

## Code checks

Run `php tests/shop_helpers_test.php`. The CI pipeline checks PHP syntax and helper tests, **not live Azure SQL or Open Library cover availability**. Review the code, test Azure yourself, and disclose AI assistance as required by your assessment brief.

## Book information / edition references

- [Mistborn ISBN / edition](https://www.brownsbfs.co.uk/Product/Sanderson-Brandon/Mistborn/9781250318541)
- [The Way of Kings hardcover ISBN](https://www.brownsbfs.co.uk/Product/Sanderson-Brandon/The-Way-of-Kings/9780765326355)
- [Dune publisher details](https://www.penguinrandomhouseretail.com/book/?isbn=9780441172719)
- [Project Hail Mary publisher details](https://www.penguinrandomhouseretail.com/book/?isbn=9780593135204)
- [Leviathan Wakes publisher details](https://www.hbgacademic.com/titles/james-s-a-corey/leviathan-wakes/9780316129084/)
- [Neuromancer ISBN](https://www.brownsbfs.co.uk/Product/Gibson-William/Neuromancer/9780441569595)
- [The Hobbit edition details](https://www.tolkienbooks.us/hob/us/tpb/the-hobbit-2012)
- [The Name of the Wind publisher details](https://astrapublishinghouse.com/product/the-name-of-the-wind-9780756404741/)


## Azure Functions book recommendations

A separate **Python Azure Function** is now included in [python-function/](python-function/).
It has both the workshop's original `/api/simplehello` route and
the new **`GET /api/recommendations?genre=Fantasy`** route. The Python
function makes an outbound HTTPS call to this PHP app's public
`/api/books.php` JSON endpoint; it returns up to three currently in-stock
books ordered by **simulated stock**. This is transparent rule-based
selection, not AI/ML or customer-specific personalisation.

### Cloud request flow

```text
Visitor to Starbound Books homepage
    │
    ├── GET /api/recommendations-proxy.php?genre=Fantasy (PHP App Service)
    │      │
    │      └── GET /api/recommendations?genre=Fantasy (Python Azure Function)
    │              │
    │              └── GET /api/books.php?genre=Fantasy (PHP App Service)
    │                      │
    │                      └── SELECT active books FROM Azure SQL
    │
    └── Recommended book cards (async; cart/checkout unaffected)
```

The browser only requests its own PHP domain, so no Azure Functions CORS
setting is needed. The proxy reads the **`RECOMMENDATIONS_FUNCTION_URL`**
app setting and accepts only HTTPS Azure Functions default-hostname URLs,
not user-provided arbitrary URLs. The Python Function reads
**`BOOKS_API_URL`** in its own Azure Function App environment settings.

### Configure and deploy, in this order

1. **Find the actual Azure-hosted URLs**: Azure Portal → App Services →
   `firstwebpage-cs` → Overview → **Default domain**, and Function Apps →
   `python-function-cs` → Overview → **Default domain**.
   Some domains include unique suffixes; **never guess** URLs.
2. Confirm the PHP public endpoint works by visiting
   `https://<PHP-DEFAULT-DOMAIN>/api/books.php?genre=Fantasy`. It
   should return JSON with a `books` array.
3. In the **Function App `python-function-cs`** → Settings →
   Environment variables, create:
   **`BOOKS_API_URL=https://<PHP-DEFAULT-DOMAIN>/api/books.php`**
   (no `?genre` in this setting). Save / restart.
4. Deploy the Python project in `python-function/` to **`python-function-cs`**.
   From that folder in a VS Code terminal, after Azure Functions Core Tools
   is installed and you have signed into Azure, run:
   ```powershell
   func azure functionapp publish python-function-cs
   ```
   This requests a **remote Python dependency build**, recommended on
   Windows when the Function App runs Linux. The deployed files must include
   `function_app.py`, `recommendations_core.py`, `requirements.txt`, and
   root-level `host.json`. Deploying the `python-function/` folder itself
   as the ZIP root is incorrect. See
   [python-function/README.md](python-function/README.md) for the exact
   commands, manual ZIP alternative, and local testing.
5. Check the Function App → Functions: **SimpleHello** and
   **BookRecommendations** should both appear. Test in a browser:
   `https://<FUNCTION-DEFAULT-DOMAIN>/api/recommendations?genre=Fantasy`
   The JSON should contain `recommendations`, `count`, and `source`.
6. In the **PHP App Service `firstwebpage-cs`** → Settings →
   Environment variables, create:
   **`RECOMMENDATIONS_FUNCTION_URL=https://<FUNCTION-DEFAULT-DOMAIN>/api/recommendations`**
   (no genre query or key; this classroom endpoint is anonymous and
   returns only public book data). Save / restart.
7. Refresh the bookstore homepage, choose **Fantasy** or
   **Science fiction** under **Recommended for you**, and verify the book
   suggestions appear. You can inspect the Network tab in browser developer
   tools and the Function App's monitoring logs for the request flow.

### Troubleshooting

- **"Recommendations are not configured"**: confirm the exact
  `RECOMMENDATIONS_FUNCTION_URL` app setting is saved on the **PHP App
  Service**, not the Python Function App.
- **HTTP 503 from Function**: set `BOOKS_API_URL` on the **Function App**
  and verify the public PHP `/api/books.php` endpoint returns JSON.
- **HTTP 502**: the Python Function cannot reach PHP, or PHP returned an
  error. Check Function App Log Stream and the PHP /api/books.php response.
- **404**: check each app's **Default domain** and the path
  `/api/recommendations`; make sure the Python project was deployed with
  `host.json` at ZIP root.
- **Homepage still works but recommendations fail**: intentional graceful
  degradation. Basket, checkout, catalogue, and SQL are independent.
- **No images**: Open Library may not have a cover for every ISBN.
- **No CORS setup required**: the browser talks only to PHP; PHP contacts
  the Python function on the server.
- No SQL schema changes needed for this integration.

### Tests

GitHub Actions verifies Python syntax, Python recommendation unit tests,
the JavaScript syntax, PHP lint, and existing PHP helper tests on PRs.
**GitHub Actions only deploys the PHP website**; publishing the Python
Function App is a **separate manual deployment**. Tests mock the catalogue
HTTP response and do not prove Azure networking or live function availability.
