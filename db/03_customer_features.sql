-- Starbound Books CloudCart features: Run ONCE BEFORE merging feature PR.
-- Idempotent Azure SQL migration. Does not delete products, orders, shopusers or historical orders.
-- Existing orders remain legacy session-scoped until a customer places new account orders.
SET NOCOUNT ON;

IF OBJECT_ID(N'dbo.customer_accounts', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.customer_accounts (
        id INT IDENTITY(1,1) PRIMARY KEY,
        name NVARCHAR(120) NOT NULL,
        email NVARCHAR(254) NOT NULL,
        password_hash NVARCHAR(255) NULL,
        google_subject NVARCHAR(150) NULL,
        mfa_secret NVARCHAR(1024) NULL,
        mfa_enabled BIT NOT NULL CONSTRAINT DF_customer_mfa_enabled DEFAULT (0),
        mfa_last_counter BIGINT NULL,
        login_failures INT NOT NULL CONSTRAINT DF_customer_login_failures DEFAULT (0),
        locked_until DATETIME2 NULL,
        created_at DATETIME2 NOT NULL CONSTRAINT DF_customer_created DEFAULT SYSUTCDATETIME(),
        CONSTRAINT UQ_customer_email UNIQUE(email),
        CONSTRAINT CK_customer_identity CHECK(password_hash IS NOT NULL OR google_subject IS NOT NULL)
    );
    CREATE UNIQUE INDEX UX_customer_google_subject ON dbo.customer_accounts(google_subject)
        WHERE google_subject IS NOT NULL;
END;

IF OBJECT_ID(N'dbo.wishlist_items', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.wishlist_items (
        customer_id INT NOT NULL REFERENCES dbo.customer_accounts(id) ON DELETE CASCADE,
        product_id INT NOT NULL REFERENCES dbo.products(id) ON DELETE CASCADE,
        created_at DATETIME2 NOT NULL CONSTRAINT DF_wishlist_created DEFAULT SYSUTCDATETIME(),
        CONSTRAINT PK_customer_wishlist PRIMARY KEY(customer_id,product_id)
    );
END;

IF COL_LENGTH(N'dbo.orders', N'customer_id') IS NULL
    ALTER TABLE dbo.orders ADD customer_id INT NULL REFERENCES dbo.customer_accounts(id);

IF COL_LENGTH(N'dbo.orders', N'payment_method') IS NULL
    ALTER TABLE dbo.orders ADD payment_method NVARCHAR(30) NULL;

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes WHERE object_id=OBJECT_ID(N'dbo.orders') AND name=N'IX_orders_customer'
)
    CREATE INDEX IX_orders_customer ON dbo.orders(customer_id, created_at DESC);

IF OBJECT_ID(N'dbo.payment_attempts',N'U') IS NULL
BEGIN
    CREATE TABLE dbo.payment_attempts (
        id INT IDENTITY(1,1) PRIMARY KEY,
        customer_id INT NOT NULL REFERENCES dbo.customer_accounts(id),
        order_id INT NULL REFERENCES dbo.orders(id),
        method NVARCHAR(30) NOT NULL,
        outcome NVARCHAR(24) NOT NULL CHECK (outcome IN (N'approved',N'declined')),
        amount DECIMAL(10,2) NOT NULL CHECK (amount >= 0),
        created_at DATETIME2 NOT NULL CONSTRAINT DF_payment_attempt_created DEFAULT SYSUTCDATETIME()
    );
    CREATE INDEX IX_payment_attempts_customer ON dbo.payment_attempts(customer_id, created_at DESC);
END;

IF OBJECT_ID(N'dbo.order_status_events', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.order_status_events (
        id INT IDENTITY(1,1) PRIMARY KEY,
        order_id INT NOT NULL REFERENCES dbo.orders(id),
        status NVARCHAR(24) NOT NULL
          CHECK (status IN (N'placed',N'processing',N'dispatched',N'delivered',N'cancelled')),
        note NVARCHAR(250) NOT NULL DEFAULT N'',
        changed_at DATETIME2 NOT NULL CONSTRAINT DF_order_status_event_created DEFAULT SYSUTCDATETIME()
    );
    CREATE INDEX IX_status_events_order ON dbo.order_status_events(order_id, changed_at);
END;

SELECT N'customer_accounts' AS table_name, COUNT_BIG(*) AS row_count FROM dbo.customer_accounts
UNION ALL SELECT N'wishlist_items', COUNT_BIG(*) FROM dbo.wishlist_items
UNION ALL SELECT N'orders', COUNT_BIG(*) FROM dbo.orders
UNION ALL SELECT N'order_status_events', COUNT_BIG(*) FROM dbo.order_status_events
UNION ALL SELECT N'payment_attempts', COUNT_BIG(*) FROM dbo.payment_attempts;
