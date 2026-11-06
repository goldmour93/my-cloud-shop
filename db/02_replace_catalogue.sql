-- Starbound Books: STEP 2 of 2 — REPLACE DEMONSTRATION CATALOGUE
-- Requires db/01_prepare_real_books.sql to have succeeded first.
-- WARNING: This resets ALL current baskets and deletes products without historical orders.
-- Products linked to order_items are retained as inactive to preserve order history.
-- Does NOT delete orders, order_items, dbo.shopusers, or dbo.Users.
-- The 8 selected titles are REAL published books. GBP prices and stock are FICTIONAL demo data.
-- External Open Library cover thumbnails may differ from the edition or be temporarily unavailable.

SET XACT_ABORT ON;
SET NOCOUNT ON;

IF COL_LENGTH(N'dbo.products', N'cover_url') IS NULL OR COL_LENGTH(N'dbo.products', N'author') IS NULL
    THROW 51011, 'First run db/01_prepare_real_books.sql to add the required book columns.', 1;

BEGIN TRY
    BEGIN TRANSACTION;

    DECLARE @Books TABLE (
        sku NVARCHAR(64) PRIMARY KEY,
        name NVARCHAR(120) NOT NULL,
        author NVARCHAR(120) NOT NULL,
        genre NVARCHAR(32) NOT NULL,
        book_format NVARCHAR(24) NOT NULL,
        description NVARCHAR(1000) NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        stock INT NOT NULL,
        cover_url NVARCHAR(500) NOT NULL
    );
    INSERT INTO @Books(sku, name, author, genre, book_format, description, price, stock, cover_url)
    VALUES
    (N'9780441172719', N'Dune', N'Frank Herbert', N'Science Fiction', N'Paperback', N'A desert world, a powerful resource and the struggle for the future of Arrakis.', CAST(12.99 AS DECIMAL(10,2)), 18, N'https://covers.openlibrary.org/b/isbn/9780441172719-M.jpg?default=false'),
    (N'9780593135204', N'Project Hail Mary', N'Andy Weir', N'Science Fiction', N'Hardback', N'A lone astronaut races to solve a threat that could end life on Earth.', CAST(19.95 AS DECIMAL(10,2)), 14, N'https://covers.openlibrary.org/b/isbn/9780593135204-M.jpg?default=false'),
    (N'9780316129084', N'Leviathan Wakes', N'James S. A. Corey', N'Science Fiction', N'Paperback', N'A missing person case uncovers a conspiracy across the Solar System.', CAST(13.99 AS DECIMAL(10,2)), 22, N'https://covers.openlibrary.org/b/isbn/9780316129084-M.jpg?default=false'),
    (N'9780441569595', N'Neuromancer', N'William Gibson', N'Science Fiction', N'Paperback', N'A washed-up hacker is drawn into a dangerous mission in cyberspace.', CAST(10.99 AS DECIMAL(10,2)), 18, N'https://covers.openlibrary.org/b/isbn/9780441569595-M.jpg?default=false'),
    (N'9781250318541', N'Mistborn: The Final Empire', N'Brandon Sanderson', N'Fantasy', N'Paperback', N'A young thief discovers extraordinary powers in an empire of ash and mist.', CAST(11.99 AS DECIMAL(10,2)), 25, N'https://covers.openlibrary.org/b/isbn/9781250318541-M.jpg?default=false'),
    (N'9780765326355', N'The Way of Kings', N'Brandon Sanderson', N'Fantasy', N'Hardback', N'Warriors and scholars confront storms, ancient orders and extraordinary destinies.', CAST(24.99 AS DECIMAL(10,2)), 10, N'https://covers.openlibrary.org/b/isbn/9780765326355-M.jpg?default=false'),
    (N'9780547928227', N'The Hobbit', N'J. R. R. Tolkien', N'Fantasy', N'Paperback', N'Bilbo Baggins joins a quest to reclaim a treasure guarded by a dragon.', CAST(10.50 AS DECIMAL(10,2)), 28, N'https://covers.openlibrary.org/b/isbn/9780547928227-M.jpg?default=false'),
    (N'9780756404741', N'The Name of the Wind', N'Patrick Rothfuss', N'Fantasy', N'Paperback', N'A legendary musician and magician recounts the events that shaped his life.', CAST(12.99 AS DECIMAL(10,2)), 16, N'https://covers.openlibrary.org/b/isbn/9780756404741-M.jpg?default=false');

    -- Empty abandoned baskets, since old products are being replaced.
    DELETE FROM dbo.cart_items;

    -- Hide all old products, including original CloudCart merchandise and fictional books.
    UPDATE dbo.products SET is_active = 0, updated_at = SYSUTCDATETIME();

    -- Physically delete obsolete products only if no saved order points to them.
    -- This protects historical purchases and their foreign-key relationships.
    DELETE p
    FROM dbo.products AS p
    WHERE NOT EXISTS (SELECT 1 FROM @Books AS b WHERE b.sku = p.sku)
      AND NOT EXISTS (SELECT 1 FROM dbo.order_items AS oi WHERE oi.product_id = p.id);

    -- Reuse already-existing ISBN products without resetting stock after any completed orders.
    UPDATE p SET
        p.name = b.name, p.author = b.author, p.genre = b.genre,
        p.book_format = b.book_format, p.description = b.description,
        p.price = b.price, p.cover_url = b.cover_url,
        p.is_active = 1, p.updated_at = SYSUTCDATETIME()
    FROM dbo.products AS p
    INNER JOIN @Books AS b ON p.sku = b.sku;

    -- Insert only the titles that are not present yet; stock is set once.
    INSERT INTO dbo.products (sku, name, author, genre, book_format, description, price, stock, cover_url, is_active)
    SELECT b.sku, b.name, b.author, b.genre, b.book_format, b.description,
           b.price, b.stock, b.cover_url, 1
    FROM @Books AS b
    WHERE NOT EXISTS (SELECT 1 FROM dbo.products AS p WHERE p.sku = b.sku);

    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0 ROLLBACK TRANSACTION;
    THROW;
END CATCH;

-- After the transaction, confirm exactly 8 books are visible.
SELECT sku AS ISBN13, name AS Title, author, genre, book_format, price, stock, cover_url
FROM dbo.products
WHERE is_active = 1
ORDER BY genre, name;
