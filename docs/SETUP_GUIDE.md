# Thread & Press Hub — Setup Guide

Local setup on XAMPP. For the full feature list see [`../README.md`](../README.md); for how the parts fit together see [`SYSTEM_ARCHITECTURE.md`](SYSTEM_ARCHITECTURE.md).

## Requirements

- XAMPP with PHP 8.0+ (extensions: mysqli, gd, fileinfo, zip, mbstring) and MySQL/MariaDB
- Composer
- A modern browser (the Try-On camera needs `localhost` or HTTPS)

## 1. Files and dependencies

Put the project in `C:\xampp\htdocs\thread-and-presshub\`, then in that folder run:

```
composer install
```

## 2. Database

1. Start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Open `http://localhost/phpmyadmin`, create a database named `threadpresshub`.
3. Import `database/schema.sql` (25 tables).
4. Optional sample products: run `php scripts/insert_sample_products.php`.

Two more tables are created by the app on first use: `ai_usage` (AI hourly limits) and `user_coupons` (the voucher wallet).
Updating an older database instead? Run the `database/migrate_*.sql` files you are missing, or open `scripts/migrate.php` once.

## 3. Configuration

Copy `.env.example` to `.env`. XAMPP's database defaults work as is. Fill in what you need:

| Setting | For |
|---|---|
| `GEMINI_API_KEY` | Chatbot, Try-On, AI Stylist, AI Design, AI Insights, AI Product Generator |
| `PAYMONGO_SECRET_KEY`, `PAYMONGO_PUBLIC_KEY`, `PAYMONGO_WEBHOOK_SECRET` | Pay Online (GCash, Maya, GrabPay, card) |
| `BREVO_API_KEY` or `SMTP_*`, `MAIL_FROM*` | Emails (order updates, vouchers, password reset) |
| `STORE_EMAIL` | Support email shown on the site |

Test email sending locally by opening `http://localhost/thread-and-presshub/scripts/test_email.php?to=you@example.com`.

## 4. Admin account

Register a normal account, then set its `user_type` to `admin` in the `users` table (phpMyAdmin).

## 5. Open the site

- Shop: `http://localhost/thread-and-presshub/`
- Admin: sign in with the admin account; you land on the dashboard.

## Default settings

| Setting | Value | Where |
|---|---|---|
| Delivery fee | By area (Rizal/Metro Manila, nearby provinces, rest of PH); Store Pickup is free | `includes/delivery-zones.php` |
| VAT | 12%, added at checkout | `checkout.php` |
| PWD / Senior discount | 20% with a verified ID | `includes/config.php` (`calculateDiscount()`) |
| Vouchers | Given by the admin (Admin → Coupons → Give) | `includes/vouchers.php` |
| Currency / timezone | Philippine peso, Asia/Manila | `includes/config.php` |

## Tests

```
php vendor/bin/phpunit
```

## Going live

The live site runs on Railway from the `Dockerfile`; see [`PRODUCTION_DEPLOYMENT.md`](PRODUCTION_DEPLOYMENT.md) and the Deployment section of the README.
