-- Starbound Books: STEP 1 of 2 — Azure SQL schema preparation
-- Run in the same database used by the PHP app (DB_NAME; originally callumitaly).
-- This script is safe to rerun. It does NOT drop tables or erase orders/users.
-- Existing shopusers registration records are not modified.

SET NOCOUNT ON;

IF OBJECT_ID(N'dbo.products', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.products (
        id INT IDENTITY(1,1) PRIMARY KEY,
        sku NVARCHAR(64) NOT NULL UNIQUE,
        name NVARCHAR(120) NOT NULL,
        author NVARCHAR(120) NOT NULL,
        genre NVARCHAR(32) NOT NULL,
        book_format NVARCHAR(24) NOT NULL DEFAULT N'Paperback',
        description NVARCHAR(1000) NOT NULL DEFAULT N'',
        price DECIMAL(10,2) NOT NULL CHECK (price >= 0),
        stock INT NOT NULL DEFAULT 0 CHECK (stock >= 0),
        is_active BIT NOT NULL DEFAULT 1,
        created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
        updated_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
    );
END;

-- Add bookstore-specific columns to the older generic MyShop products table.
IF COL_LENGTH(N'dbo.products', N'id') IS NULL
    THROW 51000, 'dbo.products is missing its expected id column. Inspect the table before proceeding.', 1;

IF COL_LENGTH(N'dbo.products', N'sku') IS NULL
BEGIN
    ALTER TABLE dbo.products ADD sku NVARCHAR(64) NULL;
    EXEC sys.sp_executesql N'UPDATE dbo.products SET sku = N''LEGACY-'' + CONVERT(NVARCHAR(48), id) WHERE sku IS NULL';
    ALTER TABLE dbo.products ALTER COLUMN sku NVARCHAR(64) NOT NULL;
    CREATE UNIQUE INDEX UX_starbound_products_sku ON dbo.products(sku);
END;

IF COL_LENGTH(N'dbo.products', N'author') IS NULL
    ALTER TABLE dbo.products ADD author NVARCHAR(120) NOT NULL
        CONSTRAINT DF_starbound_products_author DEFAULT N'Unknown Author' WITH VALUES;

IF COL_LENGTH(N'dbo.products', N'genre') IS NULL
    ALTER TABLE dbo.products ADD genre NVARCHAR(32) NOT NULL
        CONSTRAINT DF_starbound_products_genre DEFAULT N'Science Fiction' WITH VALUES;

IF COL_LENGTH(N'dbo.products', N'book_format') IS NULL
    ALTER TABLE dbo.products ADD book_format NVARCHAR(24) NOT NULL
        CONSTRAINT DF_starbound_products_book_format DEFAULT N'Paperback' WITH VALUES;

IF OBJECT_ID(N'dbo.cart_items', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.cart_items (
        id INT IDENTITY(1,1) PRIMARY KEY,
        session_key CHAR(64) NOT NULL,
        product_id INT NOT NULL REFERENCES dbo.products(id),
        quantity INT NOT NULL CHECK (quantity > 0),
        updated_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
        CONSTRAINT UQ_cart_session_product UNIQUE (session_key, product_id)
    );
    CREATE INDEX IX_cart_session ON dbo.cart_items(session_key);
END;

IF OBJECT_ID(N'dbo.orders', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.orders (
        id INT IDENTITY(1,1) PRIMARY KEY,
        public_token CHAR(32) NOT NULL UNIQUE,
        customer_name NVARCHAR(120) NOT NULL,
        customer_email NVARCHAR(254) NOT NULL,
        shipping_address NVARCHAR(500) NOT NULL,
        total DECIMAL(10,2) NOT NULL CHECK (total >= 0),
        status NVARCHAR(24) NOT NULL DEFAULT N'placed',
        payment_status NVARCHAR(24) NOT NULL DEFAULT N'not_collected',
        created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
    );
END;

IF OBJECT_ID(N'dbo.order_items', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.order_items (
        id INT IDENTITY(1,1) PRIMARY KEY,
        order_id INT NOT NULL REFERENCES dbo.orders(id),
        product_id INT NULL REFERENCES dbo.products(id),
        sku NVARCHAR(64) NOT NULL,
        name NVARCHAR(120) NOT NULL,
        author NVARCHAR(120) NOT NULL,
        quantity INT NOT NULL CHECK (quantity > 0),
        unit_price DECIMAL(10,2) NOT NULL CHECK (unit_price >= 0),
        line_total DECIMAL(10,2) NOT NULL CHECK (line_total >= 0)
    );
    CREATE INDEX IX_order_items_order ON dbo.order_items(order_id);
END;

IF COL_LENGTH(N'dbo.order_items', N'author') IS NULL
    ALTER TABLE dbo.order_items ADD author NVARCHAR(120) NOT NULL
        CONSTRAINT DF_starbound_order_items_author DEFAULT N'Unknown Author' WITH VALUES;

-- Book cover URLs are stored as externally hosted, Open Library ISBN thumbnails.
-- Run this setup before db/02_replace_catalogue.sql; it never removes user/order data.
IF COL_LENGTH(N'dbo.products', N'cover_url') IS NULL
    ALTER TABLE dbo.products ADD cover_url NVARCHAR(500) NULL;

SELECT COLUMN_NAME, DATA_TYPE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'dbo' AND TABLE_NAME = 'products'
ORDER BY ORDINAL_POSITION;
