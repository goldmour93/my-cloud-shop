-- Run once in the Azure SQL 'callumitaly' Query editor (safe to re-run).
-- Existing dbo.shopusers from the registration workshop is left untouched.
IF OBJECT_ID(N'dbo.products', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.products (
        id INT IDENTITY(1,1) PRIMARY KEY,
        sku NVARCHAR(64) NOT NULL UNIQUE,
        name NVARCHAR(120) NOT NULL,
        description NVARCHAR(1000) NOT NULL DEFAULT N'',
        price DECIMAL(10,2) NOT NULL CHECK (price >= 0),
        stock INT NOT NULL DEFAULT 0 CHECK (stock >= 0),
        is_active BIT NOT NULL DEFAULT 1,
        created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
        updated_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
    );
END;
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
IF COL_LENGTH('dbo.order_items', 'author') IS NULL
    ALTER TABLE dbo.order_items ADD author NVARCHAR(120) NOT NULL CONSTRAINT DF_order_items_author DEFAULT N'Unknown Author' WITH VALUES;
-- Seed sample catalogue. Existing products are not overwritten.
IF NOT EXISTS (SELECT 1 FROM dbo.products WHERE sku = N'CLOUD-TEE')
    INSERT INTO dbo.products (sku, name, description, price, stock)
    VALUES (N'CLOUD-TEE', N'CloudCart T-shirt', N'Soft cotton branded shirt.', 19.99, 30);
IF NOT EXISTS (SELECT 1 FROM dbo.products WHERE sku = N'CLOUD-MUG')
    INSERT INTO dbo.products (sku, name, description, price, stock)
    VALUES (N'CLOUD-MUG', N'CloudCart mug', N'Ceramic coffee mug.', 9.50, 50);
IF NOT EXISTS (SELECT 1 FROM dbo.products WHERE sku = N'CLOUD-BAG')
    INSERT INTO dbo.products (sku, name, description, price, stock)
    VALUES (N'CLOUD-BAG', N'Canvas tote bag', N'Reusable shopping bag.', 12.00, 25);
IF NOT EXISTS (SELECT 1 FROM dbo.products WHERE sku = N'CLOUD-NOTE')
    INSERT INTO dbo.products (sku, name, description, price, stock)
    VALUES (N'CLOUD-NOTE', N'Notebook', N'A5 notebook for your ideas.', 6.75, 45);
