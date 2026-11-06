# Starbound Books — sci-fi & fantasy demonstration bookshop

PHP and Azure SQL bookstore deployed to **Azure App Service** using GitHub Actions. This is a **university coursework demonstration, not a real retailer**. The eight book titles, authors and ISBNs refer to **real published editions**; GBP prices, quantities, availability and checkout are **fictional simulations**. **No payment is collected.**

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
- Session-scoped demonstration order history
- Read-only JSON API at `/api/books.php`; includes a validated `cover_url` per book for a later Python Azure Functions integration
- Existing reader-registration exercise remains separate from customer login

### Test URLs

- `/` — homepage
- `/catalog.php` — all active books
- `/catalog.php?genre=Science+Fiction` — four science-fiction titles
- `/catalog.php?genre=Fantasy` — four fantasy titles
- `/cart.php` — demo basket
- `/checkout.php` — **simulated checkout; no payment**
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
8. Only after this works, connect your separate `SimpleHello` Python Azure Function as a **read-only HTTP client** to the PHP books API.

**Limitations:** No real payment processing, OAuth/MFA or persistent customer account login; order history currently lives in the browser session. Simulated availability is not real retailer inventory. These features need further work and testing before a real e-commerce launch.

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
