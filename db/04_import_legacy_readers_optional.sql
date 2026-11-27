-- OPTIONAL: Import securely hashed registrations from old dbo.shopusers.
-- Use AFTER db/03_customer_features.sql only if you previously registered demo readers.
-- Skips duplicate emails and non-bcrypt/non-Argon hashes; never deletes legacy rows.
-- Existing accounts' login credentials are NOT overwritten.
SET NOCOUNT ON;
IF OBJECT_ID(N'dbo.shopusers', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.shopusers',N'name') IS NOT NULL
   AND COL_LENGTH(N'dbo.shopusers',N'email') IS NOT NULL
   AND COL_LENGTH(N'dbo.shopusers',N'password') IS NOT NULL
BEGIN
    EXEC sys.sp_executesql N'
      ;WITH eligible AS (
        SELECT
          LEFT(LTRIM(RTRIM(CONVERT(NVARCHAR(120),name))),120) AS display_name,
          LOWER(LTRIM(RTRIM(CONVERT(NVARCHAR(254),email)))) AS normalized_email,
          CONVERT(NVARCHAR(255),password) AS stored_hash,
          ROW_NUMBER() OVER (PARTITION BY LOWER(LTRIM(RTRIM(CONVERT(NVARCHAR(254),email))))
              ORDER BY (SELECT NULL)) AS rn
        FROM dbo.shopusers
        WHERE (CONVERT(NVARCHAR(255),password) LIKE N''$2y$%''
               OR CONVERT(NVARCHAR(255),password) LIKE N''$2a$%''
               OR CONVERT(NVARCHAR(255),password) LIKE N''$argon2%'')
      )
      INSERT INTO dbo.customer_accounts(name,email,password_hash)
      SELECT display_name, normalized_email, stored_hash FROM eligible e
      WHERE rn=1 AND display_name<>N'''' AND normalized_email LIKE N''%_@_%._%''
        AND LEN(normalized_email)<=254
        AND NOT EXISTS(SELECT 1 FROM dbo.customer_accounts c WHERE c.email=e.normalized_email);';
END;
SELECT COUNT(*) AS customer_accounts_total FROM dbo.customer_accounts;
