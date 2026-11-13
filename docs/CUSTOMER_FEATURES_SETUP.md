# CloudCart customer features — Azure SQL and security setup

> **Read before merging the PR.** This change replaces the old demonstration
> registration and checkout behaviour with real customer accounts, MFA support,
> saved wishlists, account-owned order tracking, and **virtual** payments.
> **Existing books, stock, old orders, shopusers and Python recommendations stay intact.**

## DEPLOY IN THIS ORDER (do not skip SQL)

**0. Back up data first.** Azure Portal → SQL database `callumitaly`. If you have
valued demo data, export or record relevant tables first. Review the SQL file
before executing it; never paste SQL containing passwords into GitHub.

**1. Run the migration while the previous website version is still deployed.**

Open **Azure Portal → SQL database callumitaly → Query editor → New query**.
Copy the entire file [db/03_customer_features.sql](../db/03_customer_features.sql)
and run. It is **idempotent**: safe to rerun, creates new tables
`customer_accounts`, `wishlist_items`, `payment_attempts`,
`order_status_events`, and adds nullable `customer_id`/`payment_method`
columns to existing `orders`. It does **not delete** book data or orders.

If you have previously registered readers in the old `shopusers` table,
you may *optionally* run [db/04_import_legacy_readers_optional.sql](../db/04_import_legacy_readers_optional.sql)
as a **separate new query**. This imports only compatible existing PHP
bcrypt/Argon password hashes and skips duplicate emails. Leave this optional
script out if you want entirely new demonstration accounts.

**2. Configure MFA key on the PHP WEBSITE App Service**, not the Python Function.

Azure Portal → App Services → `firstwebpage-cs` → Settings → Environment
variables → App settings → add:

```text
Name:  MFA_ENCRYPTION_KEY
Value: <base64 encoding of 32 cryptographically random bytes>
```

Generate a key in Azure App Service's existing **SSH** browser terminal:

```bash
php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'
```

Copy the generated value directly into Azure's App setting, click Apply/Save.
**Do not send the key in chat, log it in GitHub, or commit it.** Do not rotate
or delete it without planning migration of stored MFA seeds: replacing
the key will make previously enabled authenticators unusable.
This key is only for AES-256-GCM-encrypted TOTP seeds; it is separate from
`ADMIN_PASSWORD_HASH` and the Azure SQL password. Users can sign in even
without the key but cannot enrol MFA, so configure it before MFA testing.

**3. OAuth / Google sign-in is OPTIONAL until credentials are configured.**

Google OAuth is distinct from Azure's GitHub deployment identity.
To enable it, an authorised administrator must create a Google Cloud project,
set up the OAuth consent screen and create an **OAuth client for Web
application**. Register this exact redirect URI in Google Cloud Console:

```text
https://firstwebpage-cs-gbgsh8dzdjg4d0aw.italynorth-01.azurewebsites.net/oauth_callback.php
```

Set these App settings on the **PHP** `firstwebpage-cs` App Service:

```text
GOOGLE_OAUTH_CLIENT_ID       = <Google OAuth web client ID>
GOOGLE_OAUTH_CLIENT_SECRET   = <Google OAuth web client secret>
GOOGLE_OAUTH_REDIRECT_URI    = https://firstwebpage-cs-gbgsh8dzdjg4d0aw.italynorth-01.azurewebsites.net/oauth_callback.php
```

**Keep the client secret out of the repository and screenshots.** If Google
access is unavailable on a university account, you can demonstrate local
hashed-password login and authenticator MFA; clearly state that OAuth is
**implemented but not live-configured**. It does not count as a verified
live OAuth demo until the Google client is connected and tested.

Implementation: Google OpenID Connect authorization code + PKCE S256, state,
nonce, HTTPS token exchange, and verification of Google's RS256 ID-token
signature against the provider JWKS plus `iss`, `aud`, `exp`, `iat`,
`nonce`, `sub` and verified email. Users must explicitly link Google from
their signed-in reader account if an account with that email already exists;
no automatic merge by email.

Docs: https://developers.google.com/identity/openid-connect/openid-connect

**4. Merge the GitHub PR** after CI checks pass. GitHub Actions automatically
deploys the PHP site. No separate deployment of Python Function is required
for these additions, and **no changes to the existing Python recommendations**
are needed.

**5. Test one feature at a time.** Open live URL in a browser:

https://firstwebpage-cs-gbgsh8dzdjg4d0aw.italynorth-01.azurewebsites.net/

### Test checklist (use fictional data ONLY)

- **Customer login:** Visit `register.php`, create a fictional test reader
  with a unique email and strong password, sign out and back in via `login.php`.
  Passwords are hashed using PHP `password_hash`, not plaintext.
- **MFA:** In `account.php` click Set up authenticator. Enter the
  displayed secret manually in Microsoft or Google Authenticator, verify
  the six-digit code, log out and log in again. You must now enter an
  authenticator code at `mfa_verify.php`. Test a wrong code.
- **Google OAuth (if credentials exist):** Test Continue with Google with
  a Google test account. Confirm redirect and that it returns to My account.
  To link an existing password account, log in first and choose Link Google
  account; Google must return the *same verified email*. OAuth is optional
  until configured and verified.
- **Wishlist:** Sign in, open `catalog.php`, select Save to wishlist on a
  book, open `wishlist.php`, remove it, then re-add and log out/in to confirm
  it persists across sessions.
- **Payment APPROVED:** Add an in-stock book to basket, sign in and visit
  `checkout.php`. Select simulated card / wallet / bank transfer, choose
  **Approve**, and use a **fictional** shipping address. Confirm new order,
  `simulated_paid`, and stock decrement in the catalogue/Azure SQL.
- **Payment DECLINED:** On another cart choose **Decline**. No order is
  placed, the basket remains, stock must **not** be decremented. Check
  SQL `payment_attempts` for a declined record (contains no card data).
- **Order tracking:** Sign in, visit `orders.php`, open the order's
  timeline. Separately sign in to existing `admin.php` using
  `ADMIN_PASSWORD_HASH`; open `admin_orders.php` and change placed →
  processing → dispatched → delivered. Refresh the reader's order page and
  show the timestamped timeline. Attempt to access someone else's order:
  it must display Not found. The administrator can cancel early orders;
  no actual shipping or refund occurs.
- **Regression:** Confirm browsing books, PHP `/api/books.php`,
  Python recommendations, original admin book editor, and checkout stock
  concurrency still work.
- **Failure/security:** Test five incorrect password attempts, invalid
  CSRF, duplicate email registration, invalid virtual payment method, a
  declined payment, and duplicate TOTP code. Collect screenshots without
  revealing any secrets or authenticator keys.

## Architecture and security boundaries

- **Web:** PHP 8.2 Azure App Service + HTTPS, encrypted SQL connection,
  session cookies, CSRF protections, parameterized SQL.
- **Database:** Azure SQL. Customer ID, wishlist, payment attempts, status
  timeline. Historical orders remain; older guest orders were session-based
  and are **not automatically linked** to new customer accounts.
- **Auth:** Local password hashes with throttling/account lockout.
  Authenticator TOTP secrets AES-256-GCM encrypted at rest with an Azure
  environment key; accepted codes cannot be replayed once used.
  Google OAuth/OpenID Connect optional, with cryptographic token checks.
- **Authorization:** Order access by `customer_id`, wishlist by
  `customer_id`, order status changes by existing separate book admin.
- **Payments:** `demo_card`, `demo_wallet`, `demo_bank` plus explicit
  approved/declined test scenarios. Neither collects card numbers nor
  contacts a payment provider. An approved outcome is a **simulation**
  and is never real money.
- **Delivery:** Order statuses are manual simulations only, not real-time
  carrier integrations. "Real-time" means the customer sees new database
  status on page refresh.

**Known limitations before any production launch:**
- Password registration doesn't verify mailbox ownership; add an email
  verification flow before production.
- No recovery codes or authenticated MFA-reset workflow: losing a TOTP
  device requires a verified manual administrator process.
- Admin login uses a single shared environment-configured secret and does
  not have MFA/OAuth; secure the admin panel separately for production.
- OAuth depends on registering, testing and maintaining an external provider.
- PHP's default filesystem session handler isn't suitable for multi-instance
  scale-out; use a shared session store if scaling PHP App Service.
- Data retention, deletion/export requests, privacy notice, and monitoring
  policies need further work for GDPR production readiness.
- Do not use real customer data, card data or real home addresses in coursework.

This is a **coursework demonstrator**, not a production payment or delivery system.

## Assessment evidence to capture

Screenshots of migration success, a newly registered reader, a successful
local/MFA login, Google sign-in only when actually configured, a persistent
wishlist, approved/declined virtual payment attempts, order status timeline,
the separate admin tracking screen, Azure resource overview and GitHub
deployment checks. Report clearly which settings and features were *tested*
versus only *implemented*.
