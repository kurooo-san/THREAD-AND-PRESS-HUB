# Thread & Press Hub — System Architecture
### E-Commerce Platform for Apparel & Custom Design
*For Capstone / Research Documentation*

---

## 1. System Overview

**Thread & Press Hub** is a full-stack e-commerce web application for selling apparel (t-shirts, hoodies, pants, dresses, accessories) with an integrated **custom apparel Design Studio** (2D canvas + live 3D preview), **AI features powered by Google Gemini** (chatbot, Virtual Try-On, AI Stylist, AI Design Generator, AI Product Generator, admin AI Insights), **online payment through PayMongo**, **vouchers given by the admin**, **sales reporting**, **one chat window for the AI assistant and live support** (with AI-to-human handoff), **live order updates without page reloads**, and an **admin dashboard**.

Live deployment: Railway (Docker, Apache + PHP 8.2). Local development: XAMPP.

---

## 2. Technology Stack

| Layer | Technology |
|-------|-----------|
| **Backend Language** | PHP (procedural), 8.0+ (XAMPP 8.0, Railway image 8.2) |
| **Database** | MySQL / MariaDB 10.4 |
| **Web Server** | Apache (XAMPP locally, `php:8.2-apache` Docker image on Railway) |
| **Frontend** | Bootstrap 5, Vanilla JavaScript, Three.js (3D garment preview), Chart.js (admin charts) |
| **Icons / Fonts** | Font Awesome, Google Fonts |
| **AI** | Google Gemini API: `gemini-2.5-flash` (text) and `gemini-2.5-flash-image` (images) |
| **Payment** | PayMongo hosted checkout (GCash, Maya, GrabPay, cards) and Cash on Delivery / Pickup |
| **Email** | Brevo HTTPS API on Railway (SMTP is blocked there); PHPMailer (SMTP) locally |
| **PDF** | Dompdf (invoices) |
| **Excel** | Built-in `.xlsx` writer (`includes/xlsx-writer.php`, uses PHP's zip extension) |
| **PWA** | Web app manifest + service worker (installable on phones and tablets) |

---

## 3. System Architecture Diagram (Textual)

```
┌─────────────────────────────────────────────────────────────────────┐
│                        CLIENT LAYER (Browser / PWA)                 │
│   Bootstrap 5 · Vanilla JS · Three.js (3D) · Chart.js · Service Worker│
└──────────────────────────┬──────────────────────────────────────────┘
                           │ HTTPS · page requests + AJAX (JSON)
                           ▼
┌─────────────────────────────────────────────────────────────────────┐
│                   APPLICATION LAYER (Apache + PHP)                  │
│                                                                     │
│  CUSTOMER MODULES                                                   │
│   Shop & Product pages · Cart & Checkout (VAT, PWD/Senior, vouchers)│
│   Accounts & Addresses · Orders & Invoices (live status) · Vouchers │
│   Design Studio (2D + 3D) · Virtual Try-On + AI Stylist             │
│   Chat window: AI assistant + Live Support · Contact · Reviews      │
│                                                                     │
│  ADMIN MODULES                                                      │
│   Dashboard · Products (+ AI Product Generator) · Orders · Users    │
│   Coupons/Vouchers · Sales Report (Print/Excel) · Payments (legacy) │
│   Custom Designs · Custom Orders · Contact · Support Chat           │
│   Chatbot FAQ · Audit Log · AI Insights / Suggest Reply             │
│                                                                     │
│  AJAX / API ENDPOINTS (see section 10)                              │
│  SHARED: includes/config.php (DB, session, CSRF, helpers),          │
│          header/footer, admin-sidebar, email-helper, paymongo       │
└──────────┬───────────────────────────┬──────────────────────────────┘
           │ MySQLi (prepared stmts)   │ HTTPS (server-side only;
           ▼                           │ API keys never reach the browser)
┌──────────────────────────────┐       ▼
│ DATA LAYER                   │  ┌──────────────────────────────────┐
│  MySQL `threadpresshub`      │  │ EXTERNAL SERVICES                │
│  (25 tables, see section 4)  │  │  Google Gemini API               │
│                              │  │  PayMongo (checkout + webhook)   │
│  FILE STORAGE                │  │  Brevo email API / SMTP          │
│  uploads/designs, design_    │  └──────────────────────────────────┘
│  assets, tryon, support,     │
│  payments · images/products  │
│  (Railway: on a volume)      │
└──────────────────────────────┘
```

---

## 4. Database Schema (Entity-Relationship)

One database, `threadpresshub`. `database/schema.sql` creates **25 tables**. The app creates two more on first use: `ai_usage` (one row per AI call, for the hourly limits) and `user_coupons` (the voucher wallet), together with the `coupons.kind` and `orders.shipping_coupon_code` / `shipping_discount` columns (`vouchersReady()` in `includes/vouchers.php`).

**Full ERD:** `docs/system-architecture/ERD.png` (source: `ERD.mmd`, also `ERD.svg`). It is generated from the live schema: solid lines are FOREIGN KEY constraints, dashed lines are links the code uses without a constraint (for example `orders.coupon_code → coupons.code`).

| Area | Tables |
|---|---|
| **Accounts** | `users` (role in `user_type`: regular, pwd, senior, admin; PWD/Senior ID), `user_addresses` (up to 3 saved addresses), `password_resets`, `remember_tokens`, `login_attempts` |
| **Catalog** | `products` (gender, colours, sizes, price, stock, image, AI print file), `product_reviews` (1–5 stars, verified purchase) |
| **Shop orders** | `orders` (subtotal, PWD/Senior discount, item and shipping vouchers + discounts, delivery fee, total, payment method/status), `order_items` |
| **Vouchers** | `coupons` (kind: discount or shipping; percent/fixed, minimum subtotal, max uses, times used, validity dates, active flag), `user_coupons` (who holds which voucher, gift note, who gave it, when it was used and on which order) |
| **Custom design** | `custom_designs` (front/back images, editor data, print files), `custom_orders`, `custom_order_payments` |
| **Payments** | `paymongo_sessions` (online checkout sessions for shop and custom orders), `payment_submissions` and `gcash_transactions` (legacy manual payments, kept for old orders) |
| **Support** | `support_conversations`, `support_messages`, `chat_history` (AI chatbot), `chatbot_faq`, `contact_messages`, `contact_messages_responses`, `contact_categories` |
| **Monitoring** | `audit_log` |
| **Unused** | `mfa_otps` (left from an earlier version; no code reads or writes it) |

Key relationships:
- `users` 1—N `orders`, `custom_designs`, `custom_orders`, `product_reviews`, `user_addresses`, `support_conversations`, `chat_history`
- `orders` 1—N `order_items`; `products` 1—N `order_items` and `product_reviews`
- `custom_designs` 1—N `custom_orders`; `custom_orders` 1—N `custom_order_payments`
- `users` 1—N `user_coupons` N—1 `coupons`: one row per voucher given; `used_at` + `order_id` are set when it is spent and cleared again if that order is cancelled
- `coupons` 1—N `orders` (by `coupon_code` and `shipping_coupon_code`); each order keeps its own copy of the codes and discounts, so editing or deleting a coupon never changes a past order
- `paymongo_sessions` → `orders` or `custom_orders` (by `order_kind` + `order_id`)

---

## 5. Module Breakdown

### 5.1 User Authentication Module
| Component | File | Description |
|-----------|------|-------------|
| Login | `login.php` | Email/password (bcrypt), rate-limited by email + device and by IP, optional "Remember me" (30 days) |
| Registration | `register.php` | Account type (Regular/PWD/Senior), live password-rule checklist |
| Profile | `profile.php` | Personal info, up to 3 saved addresses, password change |
| Forgot / Reset Password | `forgot-password.php`, `reset-password.php` | Emailed one-time link; a reset signs out every remembered device |
| Logout | `logout.php` | Session destruction |
| Access helpers | `includes/config.php` | `isLoggedIn()`, `redirectToLogin()`, admin checks, CSRF tokens |

### 5.2 Product Catalog Module
| Component | File | Description |
|-----------|------|-------------|
| Homepage | `index.php` | Hero, categories, new arrivals, AI Try-On spotlight |
| Shop | `shop.php` | Filters (Men/Women/Kids, type, colour, size), search, sort, Quick Add. Guests can browse; adding to cart asks them to sign in and finishes the add after login |
| Product page | `product.php` | Details, colours/sizes, stock, reviews (`includes/reviews.php`) |
| Promotions | `promotion.php` | Public promos (coupons not yet given to anyone) plus the viewer's own vouchers |
| Info pages | `pages.php`, `about.php`, `privacy-policy.php` | Size guide, FAQs, policies |

### 5.3 Shopping Cart & Checkout Module
| Component | File | Description |
|-----------|------|-------------|
| Cart | `cart.php` | Cart with selectable lines; Buy Now skips the cart (`js/buy-now.js`) |
| Checkout | `checkout.php` | Saved address or new one, delivery area/fee (`includes/delivery-zones.php`), PWD/Senior discount, **voucher picker** (best voucher pre-selected; one discount + one shipping voucher), 12% VAT, payment method. Prices are always recomputed from the database |
| Voucher rules | `includes/vouchers.php` | `couponCheck()` (pure rules, unit-tested), `voucherBestPick()`, wallet, claim and release |
| Online payment | `paymongo-checkout.php`, `paymongo-return.php`, `paymongo-webhook.php`, `includes/paymongo.php`, `includes/paymongo-fulfil.php` | PayMongo hosted checkout; the webhook marks the order paid |
| Confirmation | `order_confirmation.php` | Summary with the full price breakdown |

### 5.4 Order Management Module
| Component | File | Description |
|-----------|------|-------------|
| My Orders | `orders.php`, `order_details.php` | History, status, voucher/discount lines; the page updates by itself when the shop changes the status (`js/live-status.js` polling `order-status.php`) |
| Invoices | `invoice.php`, `custom-invoice.php` | PDF invoices (Dompdf) |
| Admin Orders | `admin/orders.php`, `admin/order_details.php` | Status updates and Verify/Reject payment save without reloading (`<form data-ajax>`); cancelling returns the order's vouchers |

### 5.5 Vouchers Module
| Component | File | Description |
|-----------|------|-------------|
| Admin Coupons | `admin/coupons.php` | Create, edit, activate/deactivate, delete; **Give** to selected customers or all, with a gift note (customer is emailed); shows status, holders, uses and discount given |
| Rules | `includes/vouchers.php`, `includes/config.php` | `couponCheck()`, `voucherGive()`, `voucherClaim()` + `incrementCouponUsage()` (atomic: cannot exceed max uses), `voucherRelease()` (on cancel), `couponStatus()` |
| Customer side | `vouchers.php`, `checkout.php`, `promotion.php`, chatbot | My Vouchers wallet; auto-picked at checkout; Promotions shows public promos and the viewer's own vouchers; the chatbot quotes only this customer's vouchers |

### 5.6 Sales Report Module
| Component | File | Description |
|-----------|------|-------------|
| Sales Report | `admin/reports.php` | Any date range: totals, discounts (PWD/Senior, item vouchers, shipping vouchers), delivery fees, cancelled, daily chart, top products, by payment method, order list; Print layout |
| Excel export | `includes/xlsx-writer.php` | Formatted `.xlsx`: Summary, Orders, Top Products, Daily Sales |

### 5.7 Custom Design Module
| Component | File | Description |
|-----------|------|-------------|
| Design Studio | `custom-design.php`, `js/design-3d.js` | Canvas designer with templates, sleeves, logo spot, live 3D model |
| Design AJAX | `includes/custom-design-ajax.php` | Save/list/load designs, image uploads, AI artwork |
| Apparel config | `includes/apparel-config.php` | Apparel types, prices, print areas |
| Order flow | `custom-order-summary.php`, `custom-payment.php`, `my-custom-orders.php`, `custom-order-tracking.php` | Summary → payment → tracking |
| Admin | `admin/custom-designs.php`, `admin/custom-orders.php` | Review designs, manage production status |

### 5.8 AI Module (Google Gemini)
| Component | File | Description |
|-----------|------|-------------|
| Chatbot | `js/chatbot.js`, `includes/gemini_api.php` | Store-aware assistant (products, the customer's own orders, delivery, payment, their own vouchers). When it cannot help it offers **Talk to a person**, which opens a Live Support conversation with the AI chat attached |
| Virtual Try-On | `try-on.php`, `js/try-on.js`, `includes/tryon-ajax.php`, `tryon-save.php` | AI image of the customer wearing a product |
| AI Stylist / Size finder | `includes/tryon-suggest.php`, `includes/tryon-size.php` | Product suggestions; size from height/weight (formula fallback) |
| AI Design Generator | `includes/custom-design-ajax.php` | Artwork from a text prompt |
| AI Product Generator | `admin/ai-product-ajax.php` | Drafts product details, print artwork and photo; admin approves |
| AI Insights / Suggest Reply | `admin/ai-assistant-ajax.php`, `js/admin-ai.js` | Read-only answers from a fixed data snapshot (sales, stock, discounts, and vouchers incl. shipping vouchers and wallet counts); drafts support replies that quote only that customer's vouchers |
| Hourly limits | `ai_usage` table | Per-customer, per-feature limits; admins unlimited |

### 5.9 Support & Contact Module
| Component | File | Description |
|-----------|------|-------------|
| Chat window | `includes/footer/footer.php`, `js/chatbot.js` | One floating window with two tabs: AI Assistant and Live Support (conversations, unread badge, handoff) |
| Support Chat | `support-chat.php`, `js/support-chat.js`, `includes/support-chat-ajax.php`, `includes/support-chat-config.php` | Full-page customer ↔ admin chat with images and unread counts |
| Admin Chat | `admin/support-chat.php` | Conversations, AI Suggest Reply |
| Contact Form | `contact.php`, `includes/contact-config.php`, `admin/contact-management.php` | Categorised messages, responses |
| Chatbot FAQ | `admin/chatbot-faq.php` | Admin-managed answers the chatbot uses |
| Chat History | `chat_history.php` | Past chatbot conversations |

### 5.10 Admin Dashboard Module
| Component | File | Description |
|-----------|------|-------------|
| Dashboard | `admin/dashboard.php` | KPIs with 30-day trends, revenue chart, top products, low stock |
| Products | `admin/products.php` | CRUD, stock, AI Product Generator |
| Users | `admin/users.php` | Customer accounts, ban/unlock |
| Payments | `admin/payment-verification.php`, `admin/payment-settings.php` | Legacy manual payment proofs |
| Audit Log | `admin/audit-log.php` | Logins, payments, coupon changes, report exports, and more |
| Sidebar / notifications | `includes/admin-sidebar.php`, `includes/admin-notifications.php`, `js/admin-sidebar.js` | Navigation with badge counts |

---

## 6. User Roles & Access Control

```
┌──────────────────────────────────────────────────────────────┐
│                     ACCESS CONTROL MATRIX                     │
├────────────────────────┬───────┬──────┬────────┬─────────────┤
│ Feature                │ Guest │ User │ PWD/   │ Admin       │
│                        │       │      │ Senior │             │
├────────────────────────┼───────┼──────┼────────┼─────────────┤
│ Browse shop / products │   ✓   │  ✓   │   ✓    │     ✓       │
│ Promotions page        │   ✓   │  ✓   │   ✓    │     ✓       │
│ Cart & Checkout        │   ✗   │  ✓   │   ✓    │     ✓       │
│ Vouchers (wallet)      │   ✗   │  ✓   │   ✓    │     ✗       │
│ 20% PWD/Senior discount│   ✗   │  ✗   │   ✓    │     ✗       │
│ Design Studio          │   ✗   │  ✓   │   ✓    │     ✓       │
│ Try-On (AI generation) │   ✗   │  ✓   │   ✓    │     ✓       │
│ AI Chatbot             │   ✗   │  ✓   │   ✓    │     ✓       │
│ Live Support / handoff │   ✗   │  ✓   │   ✓    │     ✓       │
│ Contact Form           │   ✓   │  ✓   │   ✓    │     ✓       │
│ Orders / Invoices      │   ✗   │  ✓   │   ✓    │     ✓       │
│ Password Reset         │   ✓   │  ✓   │   ✓    │     ✓       │
│ Admin panel (all pages)│   ✗   │  ✗   │   ✗    │     ✓       │
│ Give vouchers / Report │   ✗   │  ✗   │   ✗    │     ✓       │
│ AI Insights            │   ✗   │  ✗   │   ✗    │     ✓       │
└────────────────────────┴───────┴──────┴────────┴─────────────┘
```

---

## 7. Data Flow Diagrams

### 7.1 Standard Order Flow
```
┌──────┐   ┌────────┐   ┌──────┐   ┌───────────────────────────────┐
│ User │──>│ Browse │──>│ Cart │──>│ Checkout                      │
└──────┘   └────────┘   └──────┘   │ address → area fee            │
                                   │ PWD/Senior 20% → vouchers     │
                                   │ → 12% VAT → total             │
                                   └──────────────┬────────────────┘
                                   Server re-prices from DB and
                                   re-checks the vouchers on submit
                                                  │
                         ┌────────────────────────┴───────────────┐
                         ▼                                        ▼
               ┌───────────────────┐                   ┌──────────────────┐
               │ Pay Online        │                   │ Cash on Delivery │
               │ PayMongo checkout │                   │ / Pickup         │
               │ → webhook = paid  │                   └────────┬─────────┘
               └─────────┬─────────┘                            │
                         └──────────────┬───────────────────────┘
                                        ▼
  Pending → Confirmed → Preparing → Out for Delivery → Completed  (or Cancelled)
         (status set by admin; the customer's order page updates live;
          Cancelled returns the vouchers to the wallet)
```

### 7.2 Custom Design Order Flow
```
┌──────┐   ┌─────────────────────┐   ┌──────────────┐   ┌───────────────┐
│ User │──>│ Design Studio       │──>│ Order Summary│──>│ Payment       │
└──────┘   │ templates · AI art  │   │ price, PWD/  │   │ PayMongo or   │
           │ sleeves · logo · 3D │   │ Senior       │   │ COD           │
           └─────────────────────┘   └──────────────┘   └──────┬────────┘
                                                               ▼
  Pending Payment → Payment Uploaded / Verified → Processing → Printing → Ready for Pickup → Delivered
  (or Cancelled; status set by admin)
```

### 7.3 Voucher Flow
```
Admin creates a coupon (admin/coupons.php) ──> Give to selected / all customers
   │                                              (user_coupons row + email)
   ▼
Customer: My Vouchers · chatbot knows them · Promotions shows them
   │
   ▼ Checkout
Best discount + best shipping voucher pre-selected (voucherBestPick); can switch/remove
   │
   ▼ Place Order
Server: voucher must be in THIS customer's wallet, unused, and pass couponCheck()
   ──fail──> order stopped, reason shown
   │ ok
   ▼
Inside the order transaction: voucherClaim()
   (wallet row marked used + UPDATE ... WHERE times_used < max_uses —
    only one checkout can take the last use)
   │
   ▼
orders.coupon_code / shipping_coupon_code + discounts saved → shown on order,
invoice, email, Sales Report and AI Insights
   │
   ▼ if the admin cancels the order
voucherRelease(): voucher back in the wallet, one use freed
```

### 7.4 AI Chatbot Flow
```
User message ──> includes/gemini_api.php
                   │ builds context from the database:
                   │  products · this customer's orders only · delivery fees
                   │  payment options · this customer's vouchers · admin FAQ
                   ▼
                 Gemini API (server-side key) ──> reply ──> saved to chat_history
                   │
                   │ AI marks [HANDOFF] (or customer taps "Talk to a person")
                   ▼
                 support-chat-ajax.php: new Live Support conversation with the
                 AI chat attached ──> admin answers in admin/support-chat.php
                 (AI Suggest Reply can draft it) ──> same chat window, Live tab
```

---

## 8. Security Architecture

```
┌─────────────────────────────────────────────────────────────┐
│  1. AUTHENTICATION                                          │
│     • bcrypt password hashing; one strong-password rule     │
│     • New session ID on login; HttpOnly, SameSite=Lax cookie│
│     • Login rate limiting (login_attempts)                  │
│     • Remember-me tokens stored hashed (remember_tokens)    │
│     • One-time, expiring password reset links               │
│                                                             │
│  2. REQUEST INTEGRITY                                       │
│     • CSRF tokens on every form and every AJAX write        │
│       (X-CSRF-Token header; window.CSRF_TOKEN from footer)  │
│     • Admin check on every admin page and endpoint          │
│     • Safe post-login redirects (same site only)            │
│                                                             │
│  3. PRICING & PAYMENT                                       │
│     • Order prices recomputed from the database             │
│     • Vouchers must belong to the customer; usage claimed   │
│       atomically inside the order transaction               │
│     • PayMongo amounts built from the database; webhook     │
│       signature checked; card data never touches the site   │
│                                                             │
│  4. DATABASE                                                │
│     • Prepared statements; transactions for order + stock   │
│     • Foreign keys with CASCADE; utf8mb4                    │
│     • One connection per request, retried on cold start;    │
│       DB errors are logged, never shown to the visitor      │
│                                                             │
│  5. FILE UPLOADS                                            │
│     • Real file type checked, 5MB limit, random names       │
│     • Private folders blocked from the web                  │
│                                                             │
│  6. AI                                                      │
│     • Keys only on the server; login required               │
│     • Hourly per-customer limits (ai_usage)                 │
│     • Admin AI is read-only and never sees contact or       │
│       payment details                                       │
│                                                             │
│  7. AUDIT                                                   │
│     • audit_log: user, action, IP, user agent, details      │
└─────────────────────────────────────────────────────────────┘
```

---

## 9. File Structure Map

```
thread-and-presshub/
├── *.php                  Customer pages (index, shop, product, cart, checkout,
│                          orders, vouchers, custom-design, try-on, promotion,
│                          support-chat, order-status (live poll) ...)
├── paymongo-*.php         PayMongo checkout, return and webhook
├── admin/                 Admin pages (dashboard, products, orders, users, coupons,
│                          reports, custom-designs, custom-orders, support-chat,
│                          chatbot-faq, contact-management, audit-log ...) and
│                          admin AJAX (ai-assistant-ajax, ai-product-ajax)
├── includes/              config, helpers, AJAX endpoints, header/footer,
│                          paymongo, delivery-zones, apparel-config, xlsx-writer ...
├── js/                    chatbot (AI + Live Support window), live-status, try-on,
│                          design-3d, support-chat, admin-ai, admin-sidebar,
│                          app-shell (PWA), buy-now ...
├── css/                   Stylesheets (light + dark mode)
├── images/                Product images, 3D models (images/models/web)
├── uploads/               designs, design_assets, tryon, support, payments
│                          (Railway: moved onto a volume by docker/start.sh)
├── database/              schema.sql (25 tables; 2 more made on first use) and migrate_*.sql
├── docs/                  Guides and diagrams (system-architecture/ERD.png)
├── scripts/               Command-line maintenance (migrate, seed, cleanup)
├── tests/                 PHPUnit tests
├── docker/ + Dockerfile   Railway container
└── vendor/                Composer packages (PHPMailer, Dompdf)
```

---

## 10. API Endpoints

| Endpoint | Method | Purpose | Auth |
|----------|--------|---------|------|
| `includes/gemini_api.php` | POST | AI chatbot | Customer login |
| `includes/custom-design-ajax.php` | POST | Save/list/load designs, uploads, AI artwork | Customer login + CSRF |
| `includes/tryon-ajax.php`, `tryon-suggest.php`, `tryon-size.php`, `tryon-save.php` | POST | Try-On, AI Stylist, size finder, saved looks | Customer login |
| `includes/support-chat-ajax.php` | POST | Live Support conversations, messages, AI handoff | Login + CSRF |
| `order-status.php` | GET | Version stamp polled by `js/live-status.js` | Order owner or admin |
| `includes/product-recommendations.php` | POST | Product search & suggestions | — |
| `includes/order-lookup.php` | POST | Order status lookup (the customer's own orders only) | Customer login |
| `paymongo-webhook.php` | POST | PayMongo payment events (signature checked) | PayMongo |
| `admin/ai-assistant-ajax.php` | POST | AI Insights, Suggest Reply | Admin + CSRF |
| `admin/ai-product-ajax.php` | POST | AI Product Generator | Admin + CSRF |
| `admin/reports.php?export=xlsx` | GET | Sales report Excel download | Admin |
| `admin/orders.php` (`X-Requested-With: fetch`) | POST | Status change, Verify/Reject payment without reload | Admin + CSRF |

---

## 11. Deployment Environment

```
┌────────────────────────────┐        ┌──────────────────────────────────┐
│ LOCAL (development)        │        │ RAILWAY (live)                   │
│  XAMPP: Apache, PHP 8.0,   │  push  │  Dockerfile: php:8.2-apache      │
│  MariaDB 10.4              │ ─────> │  extensions: mysqli, gd, zip,    │
│  http://localhost/         │  main  │  mbstring, intl                  │
│   thread-and-presshub/     │        │  MySQL service via MYSQL_URL     │
│  or `npm start` (port 3000)│        │  Attach a Volume at /data so     │
└────────────────────────────┘        │  uploads survive redeploys       │
                                      │  (every push redeploys)          │
                                      └──────────────────────────────────┘
```

---

*Document for capstone/research documentation purposes.*
*Thread & Press Hub — E-Commerce Platform for Apparel & Custom Design*
