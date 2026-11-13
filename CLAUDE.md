# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

Thread & Press Hub: a plain PHP + MySQL apparel shop (no framework, no JS build step) with a custom Design Studio, Google Gemini AI features, PayMongo payments and PWD/Senior discounts. `README.md` is the feature-level spec and is kept accurate; update it when behaviour changes.

## Commands

```bash
composer install                                   # PHP deps (phpmailer, dompdf; phpunit dev)
php vendor/bin/phpunit                             # all tests
php vendor/bin/phpunit tests/VoucherTest.php       # one file
php vendor/bin/phpunit --filter testName           # one test
npm start                                          # http://localhost:3000 (node server.js → php -S + scripts/router.php)
```

- Locally the app also runs under XAMPP Apache at `http://localhost/thread-and-presshub/`. PHP binary is `C:\xampp\php\php.exe` (PHP 8.0).
- No linter, no bundler. CSS/JS in `css/` and `js/` are served as-is; Three.js is vendored in `js/lib/three/`.
- Database: create `threadpresshub`, import `database/schema.sql`. Older installs apply `database/migrate_*.sql`.

## PHP version constraint

Local is PHP 8.0 and `composer.json` pins `platform.php` to 8.0.30; production (Dockerfile) is `php:8.2.35-apache`. Write code that runs on **8.0**: no enums, `readonly`, `never`, first-class callable syntax, `new` in initializers, or other 8.1+ features. Note 8.1+ throws `mysqli_sql_exception` on failed queries while 8.0 returns `false` by default — handle both.

## Architecture

**Request model.** Every customer page is a root-level `*.php` file (URLs must not change); admin pages are in `admin/`. Each page starts with `require 'includes/config.php'` (or `../includes/config.php`), handles its own POST at the top, then renders with `includes/header/header.php` and `includes/footer/footer.php`. There is no router or controller layer.

**`includes/config.php` is the bootstrap.** It loads `.env`, opens the global `$conn` (mysqli; `MYSQL_URL` overrides `DB_*` on Railway, with a retry while the DB wakes up), starts the session, sets security headers, does remember-me auto-login, and defines the shared helpers: auth (`isLoggedIn`, `redirectToLogin`, `safeRedirectTarget`), CSRF (`generateCsrfToken`, `csrfTokenField`, `verifyCsrfToken`), `sanitizeInput`, `logAudit`, `aiQuotaAllows`, discount and stock helpers. It also pulls in `vouchers.php`, `exhibit.php`, `payment-config.php`, `brand.php`, `password-rules.php`.

**AJAX endpoints** live in `includes/*-ajax.php` and `admin/*-ajax.php`: they return JSON, check login (and admin role where needed), and for POST verify the `X-CSRF-Token` header against `$_SESSION['csrf_token']`. Follow `includes/custom-design-ajax.php` as the pattern (JSON exception handler, auth, CSRF, `switch ($action)`).

**Admin guard.** Each admin page/endpoint checks `$_SESSION['user_type'] === 'admin'` itself; keep that check on anything new in `admin/`.

**Schema evolves at runtime.** Besides `schema.sql`, several features create tables/columns on first use (`ai_usage` in config.php, `user_coupons` and coupon/order columns in `vouchers.php`, `orders.stock_returned`, courier columns in `couriers.php`, design columns in `custom-design-ajax.php`). Code guards optional columns with `SHOW COLUMNS` / `*TableExists()` helpers. New columns should follow the same idempotent pattern so existing deployments upgrade without manual SQL.

**Single sources of truth** (change the rule in one place; pages, emails, invoices and the chatbot read from it):
- `includes/delivery-zones.php` — delivery fee, estimated arrival, Shipping page
- `includes/vouchers.php` — voucher wallet and best-voucher pick
- `includes/apparel-config.php` — Design Studio apparel types, prices, print areas
- `includes/brand.php` — store name/logos (editable in `admin/branding.php`, stored in `uploads/branding/`)
- `includes/password-rules.php` — `passwordMeetsRules()`
- `includes/paymongo-fulfil.php` — the **only** code that marks an order paid; both `paymongo-return.php` and `paymongo-webhook.php` call it. Payment amounts are always built from the DB, never from the browser.
- `includes/gemini_api.php` — all Gemini calls; every customer AI feature must pass `aiQuotaAllows()` first (hourly limits in README).

**Checkout math:** item subtotal − PWD/Senior 20% − discount voucher → 12% VAT on the remainder → + delivery fee − shipping voucher. Custom (Design Studio) orders take no vouchers and are **pickup**, not delivery.

**Order cancel/un-cancel** returns/takes back stock and vouchers exactly once (`returnOrderStock`, `takeBackOrderStock`, `orders.stock_returned`); preserve that idempotency.

## Tests

PHPUnit 9 (`tests/`, bootstrap `tests/bootstrap.php`). Tests never touch the DB or `config.php`: they `require_once` an individual `includes/*.php` file and exercise its pure functions. Keep business rules in pure functions in those include files so they stay testable. `phpunit.xml` fails on warnings, risky tests and unexpected output.

## Deployment

Railway builds the `Dockerfile` on every push to `main` (Apache + mod_php, prefork MPM). `docker/start.sh` binds `$PORT` and symlinks writable dirs (`uploads/`, `storage/payment-proofs`, `storage/payment-config`, `images/products`, `images/payment-qr`) onto the Railway volume. Apache denies `storage/`, `vendor/`, `tests/`, `scripts/`, `docker/`, `*.sql|env|md|lock|bak` and `uploads/**/*.json`; `.htaccess` (local) and `scripts/router.php` (npm start) mirror those blocks — keep all three in sync when adding private paths.

## Conventions

- Prepared statements for all SQL; escape output; real MIME check on uploads (5MB, random names).
- User-facing copy must describe only what the system actually does; one support email (`SUPPORT_EMAIL` from `STORE_EMAIL`).
- Commit messages: short plain-English imperative summary of the user-visible change (e.g. "Say pickup, not delivery, for custom orders").
