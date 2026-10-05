# Thread and Press Hub - Apparel Studio Ordering System

A PHP and MySQL apparel shop with a custom design studio, AI features powered by Google Gemini, online payment through PayMongo, and 20% discounts for PWD and Senior Citizens. On phones and tablets it works like a mobile app and can be installed from the browser.

**Live site:** https://thread-and-press-hub-production.up.railway.app

## Features

### 🛍️ Customer Features
- **User Authentication**: Register, login, and logout with secure password hashing
  - Live password checklist on sign-up (8+ characters, uppercase, lowercase, number, special character) that turns green as each rule is met
  - "Passwords match" indicator on the confirm-password field
  - Show/hide password (eye icon) on the login and register forms
  - After signing in, users go back to the page that asked them to log in (e.g. a product or checkout page)
- **Shop**: Filter by who it is for (Men, Women, Kids) and by type, plus colour, size and sort. Active filters show as removable chips
- **Quick Add**: Pick colour, size and quantity in a bottom sheet, then Add to Cart or Buy Now
- **Shopping Cart**: Add/remove items with quantity management
- **Product Reviews**: Star ratings and reviews on product pages
- **Coupons / Promo Codes**: Type a code in the "Have a coupon code?" box at checkout and the discount shows in the order summary before placing the order
  - Percent or fixed discounts, optional minimum subtotal, usage limit and validity dates, all set by the admin
  - One code per order; works together with the PWD/Senior discount
  - Checked again on the server when the order is placed, so a code that expired or ran out in the meantime stops the order with a clear message instead of charging a price the customer did not see
  - Current codes are listed on the **Promotions** page
- **Special Discounts**:
  - PWD Discount: 20% off with valid PWD ID
  - Senior Citizen Discount: 20% off with valid Senior ID
- **Payment Options**:
  - Online payment through PayMongo's hosted checkout (GCash, Maya, cards and GrabPay by default). The amount is always built from the database, never from the browser
  - Cash on Delivery: Pay when order arrives
- **Order Tracking**: View order history and current status
- **PDF Invoices**: Download an invoice for shop orders and custom orders
- **About & Contact Pages**: Learn more about us and reach out via contact form
- **User Profile**: Update personal information and manage account
- **Remember Me & Password Reset**: Stay signed in for 30 days; reset a forgotten password by email (resetting signs out every remembered device)
- **Email Notifications**: Welcome email, order and custom-order confirmations, and status updates

### 🤖 AI Features (Google Gemini)
- **AI Chatbot**: Floating shopping assistant that answers with real store data and recommends products; past conversations are saved in **Chat History**. It knows the promo codes usable right now (live from the database) and how to apply them, with a **Promos & Coupons** quick button
- **Virtual Try-On (LiveLook)**: Uses the camera (or an uploaded photo) and shows an AI-generated preview of the customer wearing the selected product. Includes an **AI Stylist** and a size suggestion from height and weight (with a formula fallback when the AI is unavailable)
- **AI Design Generator**: Describe an idea in the Design Studio and Gemini creates a print-ready graphic, for the front, back or sleeves
- **Hourly limits**: Every Gemini call costs money and anyone can register, so each customer has an hourly limit per feature. Admins have no limit

  | Feature | Limit per hour | When reached |
  |---|---|---|
  | Virtual Try-On | 15 | Friendly "limit reached" message |
  | AI Design Generator | 15 | Friendly "limit reached" message |
  | AI Stylist | 30 | Friendly "limit reached" message |
  | Size finder | 30 | Answers from the formula instead |
  | Chatbot | 60 | Friendly "limit reached" message |

### 🧵 Custom Design Studio
- **9 apparel types**: T-Shirt, Hoodie, Polo; Couple T-Shirt, Hoodie and Polo sets (Partner A and B); Corporate Polo, Long Sleeve and Vest (with a pocket logo spot)
- **45 ready-made templates** in 9 categories (Occasions, Nature, Animals, Science, Futuristic, Sports, Faith, Food, Pinoy Pride). Every template fits every apparel type: it is moved and scaled into that apparel's print area, goes on both partners for couple sets, and sits below the logo spot on corporate wear
- **Design tools**: Text (with curved text, outline and shadow), drawing, image upload with background removal, animal stamps, layers, undo/redo, zoom and keyboard shortcuts
- **Sleeves and logo**: Separate editors for the left/right sleeve and the corporate pocket logo
- **3D Garment Preview**: See the same design live on a 3D model (rotate and zoom); falls back to the 2D editor if the device has no WebGL
- **Live price estimate**: Counts the print colours and updates the price as you design
- **Drafts**: Unsaved work is kept on the device and offered back the next time; saved designs can be opened again from **My Designs**
- **Custom Orders**: Order summary, payment, and a tracking page for each custom order (My Custom Orders)

### 💬 Support Chat
- Customers can open a conversation with the store and chat with an admin, with unread-message counts and image attachments

### 📱 Mobile App Layout & Installable App (PWA)
- Can be installed on phones and tablets from the browser (manifest + service worker), in portrait or landscape
- On phones and tablets (under 992px):
  - Bottom tab bar (Home, Shop, Design, Cart, Account) with a cart badge
  - **Account sheet** that replaces the site footer: profile, orders, support, try-on, Contact & FAQs, About Us, Privacy Policy, dark mode and Install app
  - No grey tap flash or double-tap zoom on buttons; no rubber-band bounce in the installed app
- **Home (phones)**: Compact banner, swipeable rows for perks, categories and New Arrivals, and flat sections
- **Shop (phones)**: Clean product cards (picture, name, price, rating); colour, size and the cart buttons are in the Quick Add sheet
- **Design Studio (phones/tablets)**: The shirt comes first with the tools under it, and the canvas tools sit on one swipeable row
- **Product page**: Sticky "buy" bar
- Friendly offline page when there is no connection; pages are never cached, so prices, stock and orders are always live

### 👨‍💼 Admin Features
- **Dashboard**: Overview of sales, users, products, and orders
- **Product Management**: Add, edit, and manage products and stock
- **Order Management**: View all orders and update their status; the coupon and its discount show on each order
- **Coupons**: Create, edit, activate/deactivate and delete promo codes. Shows each code's status (Active, Scheduled, Expired, Used up, Inactive), how many times it was used and the total discount given. The code itself is fixed once created, since orders and the totals are matched by it
- **Sales Report**: Sales for any date range (with This month, Last month, Last 30 days and This year shortcuts): total sales, orders, average order, discounts given (PWD/Senior and coupons), delivery fees, cancelled orders, a daily sales chart, top products, sales by payment method and the full order list
  - **Export Excel**: A formatted `.xlsx` with Summary, Orders, Top Products and Daily Sales sheets (peso formatting, real dates, frozen headers, filters and totals)
  - **Print**: Printer-friendly layout with its own report header
  - Counts sales the same way as the Dashboard: non-cancelled shop and custom orders
- **User Management**: Monitor customer accounts
- **AI Assistant** (read-only):
  - **AI Insights**: Ask questions like "best seller this month?", "what needs my attention?" or "how much discount did we give this month?". Answers come from a fixed data snapshot (sales, orders, stock, coupons and discounts, reviews); the AI never writes SQL and never sees customer emails, phones, addresses or payment details
  - **Suggest Reply**: Drafts a support-chat reply that the admin reviews before sending; it can quote the promo codes usable right now
- **AI Product Generator**: Type a product idea; Gemini drafts the details, print artwork and product photo, and the admin approves before it goes live
- **Custom Orders & Designs**: Review customer designs and manage custom orders
- **Support Chat**: Reply to customer conversations
- **Chatbot FAQ**: Manage the answers the chatbot uses
- **Contact Management**: Read and handle contact-form messages
- **Payment Settings & Verification**: Configure payment options and review manual payment proofs (approve/reject with a reason; duplicate reference numbers are blocked)
- **Audit Log**: Every important action (logins, payment approvals, coupon changes, report exports, etc.) is recorded with filters and pagination

### 🎨 Design Features
- Modern fashion look with a gold accent, light and dark mode
- Fully responsive (mobile, tablet, desktop)
- Store copy describes only what the system really does (no invented claims), and a single support email is used everywhere
- **Motion** (all turned off for users who prefer reduced motion):
  - Home hero: the photo settles from a slight zoom like fabric under a press, then the badge, headline, text and buttons come in one after another
  - Stitched seam: a gold dashed "thread" under each section title, sewn from the centre out as it scrolls into view
  - Cart badge pops whenever an item is added
  - Scroll reveal for cards and sections, page-to-page crossfade, and a tactile press on buttons

## Folder Structure

| Folder | Contents |
|--------|----------|
| *(root)* | Customer-facing pages (`index.php`, `shop.php`, `checkout.php`, ...). They stay in the root so their URLs don't change |
| `admin/` | Admin panel pages |
| `includes/` | Shared PHP: config, helpers, AJAX endpoints, header/footer |
| `css/`, `js/`, `images/` | Front-end assets |
| `database/` | `schema.sql` and the `migrate_*.sql` files (`dumps/` holds local backups, never committed) |
| `scripts/` | Command-line maintenance scripts (migrations, seeding, cleanup) |
| `docs/` | Guides, setup notes and system architecture diagrams |
| `tests/` | PHPUnit tests |
| `docker/` | Railway container startup |

## Requirements

- PHP 8.0 or higher (with GD, mysqli, fileinfo and zip; zip is used by the PDF invoices and the Excel sales report)
- MySQL/MariaDB
- Apache web server (XAMPP works)
- Composer (for the PHP dependencies)
- Modern web browser

## Installation

### 1. Get the files

Put the project in `C:\xampp\htdocs\thread-and-presshub\`, then install the PHP dependencies:
```
composer install
```

### 2. Database Setup

1. Start Apache and MySQL in XAMPP and open phpMyAdmin (`http://localhost/phpmyadmin`)
2. Create a database named `threadpresshub`
3. Import `database/schema.sql` (all 25 tables)
4. If you are updating an older installation instead, run the `database/migrate_*.sql` files you are missing, or visit `scripts/migrate.php` once

### 3. Configuration (.env)

Copy `.env.example` to `.env` and fill in the values you need:

| Setting | Used for |
|---|---|
| `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`, `DB_PORT` | Database (XAMPP defaults work without changes) |
| `GEMINI_API_KEY` | Chatbot, Try-On, AI Design, AI Assistant and AI Product Generator |
| `PAYMONGO_SECRET_KEY`, `PAYMONGO_PUBLIC_KEY`, `PAYMONGO_WEBHOOK_SECRET` | Online payment |
| `BREVO_API_KEY`, `SMTP_*`, `MAIL_FROM*` | Password reset, order and welcome emails (Brevo API if the key is set, else SMTP) |
| `RECAPTCHA_SITE_KEY`, `RECAPTCHA_SECRET_KEY` | Reserved for reCAPTCHA: the helpers are in `includes/config.php`, but no form uses them yet |
| `APP_URL`, `FORCE_HTTPS` | Production URL and HTTPS redirect |
| `STORE_EMAIL` | The support email shown on the footer, contact page, privacy policy, invoices and chatbot (`js/chatbot.js` repeats it in two fallback messages) |
| `SOCIAL_FACEBOOK_URL`, `SOCIAL_INSTAGRAM_URL`, `SOCIAL_TIKTOK_URL` | Footer social icons; each icon only appears once its URL is set |

`.env` holds secrets and is never committed (see `.gitignore`).

### 4. Access the Application

Visit: `http://localhost/thread-and-presshub/`

Or run it on port 3000 without Apache: start MySQL in XAMPP, then run `npm start` (or `node server.js`) and open `http://localhost:3000`. `scripts/router.php` applies the same private-folder blocks as Apache.

The camera in Virtual Try-On only works on `localhost` or over HTTPS.

### 5. Deployment (Railway)

The live site runs on Railway from the `Dockerfile`. The database connection comes from a single `MYSQL_URL` variable (`mysql://user:pass@host:port/db`), which overrides the `DB_*` values. Every push to `main` triggers a redeploy.

## Admin Account

Register a normal account, then set its `user_type` to `admin` in the `users` table (phpMyAdmin).

## Usage

### For Customers

1. **Register**: Create an account on the register page; the password checklist shows which rules are still missing
2. **Select Account Type**: Choose Regular, PWD, or Senior Citizen
3. **Browse the Shop**: Filter by Men, Women, Kids and type, or try a product on with **Try On**
4. **Add to Cart**: Choose colour, size and quantity
5. **Or Design Your Own**: Start from a template or a blank apparel in the Design Studio, check it in 3D, and submit it as a custom order
6. **Checkout**: Review order, select payment method, and apply discount or coupon if eligible
7. **Payment**: Pay online through PayMongo or select Cash on Delivery
8. **Track Order**: View order status in "My Orders" or "My Custom Orders"

### For Admins

1. **Login**: Use an admin account
2. **Dashboard**: View key metrics, or ask the AI Assistant
3. **Manage Products**: Add new apparel items (by hand or with the AI Product Generator) and manage existing ones
4. **Manage Orders**: View and update order and custom-order statuses, and review customer designs
5. **Coupons**: Create promo codes; they appear on the Promotions page and in the chatbot right away
6. **Sales Report**: Pick a date range, then Print or Export Excel
7. **Payments**: Verify payment proofs left from the old manual payment flow
8. **Support**: Answer support chats and contact messages

## Payment Integration

### PayMongo (online payment)

Online payment goes through PayMongo's hosted checkout (`paymongo-checkout.php`), for both shop orders and custom orders. The line items and amount are built from the database. After paying, the customer returns to `paymongo-return.php`, and `paymongo-webhook.php` receives PayMongo's payment confirmation. More details in `docs/README_PAYMENT.md`.

### Legacy manual payments

The older GCash/Maya/QR payment pages (`payment_gcash.php`, `payment_maya.php`, `payment-qr.php`) are retired but kept, so old links still work and orders placed before the switch can still be verified in **Admin → Payment Verification**.

## Discount System

- **PWD (Person with Disability)**: 20% discount
  - Requires valid PWD ID during checkout
- **Senior Citizens**: 20% discount
  - Requires valid Senior ID during checkout
- **Regular Users**: No discount
- **Coupons**: Any account can add one promo code per order, on top of the PWD/Senior discount

At checkout the order is worked out as: item subtotal − PWD/Senior discount − coupon, then 12% VAT on what is left, then the delivery fee. The PWD/Senior discount and a percent coupon are both worked out on the item subtotal, and a coupon never applies to the delivery fee. Custom (Design Studio) orders do not take promo codes.

## Database Tables

`database/schema.sql` creates all 25 tables. The main ones:

| Table | Holds |
|---|---|
| `users` | Accounts with roles (customer, admin) and PWD/Senior ID for discounts |
| `products` | Apparel with category, gender, colours, sizes, price, stock and image |
| `orders`, `order_items` | Shop orders with totals, discounts, the coupon used, and the colour/size of each item |
| `coupons` | Promo codes with discount type and value, minimum subtotal, usage limit and count, and validity dates |
| `product_reviews` | Star ratings and reviews, one per customer per product |
| `user_addresses` | Up to 3 saved delivery addresses per customer |
| `paymongo_sessions` | Online checkout sessions for shop and custom orders |
| `custom_designs`, `custom_orders` | Design Studio designs and their orders |
| `audit_log` | Record of important actions |
| `ai_usage` | One row per AI call, for the hourly limits. Not in `schema.sql`: it is created automatically on the first AI request |

The full ERD of every table and relationship is `docs/system-architecture/ERD.png` (source `ERD.mmd`). See `docs/SYSTEM_ARCHITECTURE.md` for the full picture.

## Security Features

- ✅ Password hashing with bcrypt
- ✅ SQL injection prevention with prepared statements
- ✅ Input sanitization and escaped output
- ✅ Session-based authentication (new session ID on login to prevent session fixation); session cookie is HttpOnly and SameSite=Lax
- ✅ CSRF tokens on the login, register, forgot-password and reset-password forms, admin forms (products, chatbot FAQ, contact messages, custom designs, custom orders, payments, admin profile) and admin AI requests
- ✅ One strong password rule (8+ characters with uppercase, lowercase, number and special character) for sign-up, password reset and both profile pages, checked on the server by `passwordMeetsRules()` in `includes/password-rules.php`
- ✅ Password reset that cannot be abused: the same reply whether or not the email is registered, at most 3 reset emails per account per hour, the reset link is only shown on the page to someone on `localhost` when email sending fails, and a reset signs out every remembered device
- ✅ Hourly AI limit per customer, so the Gemini quota cannot be drained by one account
- ✅ Login rate limiting: 5 failed attempts per email + device, 30 per IP, in 15 minutes (so one person's typos don't lock out a whole shared network)
- ✅ Safe post-login redirect: only same-site pages are allowed, blocking open-redirect tricks like `//evil.com`
- ✅ Admin-only checks on every admin page and endpoint
- ✅ File uploads checked by their real file type (not the name), limited to 5MB, saved under random names
- ✅ Security headers (nosniff, frame protection, referrer and permissions policy; HSTS on HTTPS). Camera is allowed only on the Try-On page
- ✅ Secrets in `.env`; database dumps, uploads and customer files are kept out of Git
- ✅ Production hides PHP errors and blocks web access to `storage/`, `.sql`, `.env` and other private files

## Tests

```
php vendor/bin/phpunit
```
Covers the helpers, coupons, the size estimate and the password rule (`tests/`).

## File Permissions

Ensure these folders are writable by the web server:
```
images/products/
uploads/
storage/
```

## Troubleshooting

### Database Connection Failed
- Check if MySQL is running
- Verify the `DB_*` values in `.env` (or `MYSQL_URL` on Railway)
- Ensure database `threadpresshub` exists

### Images Not Loading
- Verify `images/products/` directory exists and is writable
- Check image file permissions

### AI Features Not Working
- Check `GEMINI_API_KEY` in `.env`
- The camera in Try-On needs `localhost` or HTTPS

### Admin Dashboard Not Loading
- Ensure logged-in user is admin type
- Check session settings in `includes/config.php`

## Customization

### Change Logo/Branding
- Edit `includes/header/header.php` and `includes/footer/footer.php`
- Update colors in `css/style.css` - Modify CSS variables

### Add New Product Categories
- Edit dropdown in `admin/products.php`
- Update category values in database

### Add or Change Apparel Types
- Edit `includes/apparel-config.php` (label, price, group, print area, logo spot)

### Modify Discount Rates
- Edit `calculateDiscount()` function in `includes/config.php`
- Update discount percentages as needed

## Future Enhancements

- SMS notifications for order updates
- Content Security Policy (CSP) header
- Loyalty rewards program
- Advanced analytics dashboard
- Multiple language support

## Support

For issues or questions, please check:
1. The database settings in `.env`
2. File permissions on `images/products/`, `uploads/` and `storage/`
3. Browser console for JavaScript errors

## License

This project is for educational and commercial use.

## Credits

Built with PHP, MySQL, Bootstrap 5, Three.js, Google Gemini, PayMongo and Font Awesome Icons.

---

**Thread and Press Hub** - Wear it ready-made, or print your own. 👕
