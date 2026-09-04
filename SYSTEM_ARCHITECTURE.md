# Thread & Press Hub — System Architecture
### E-Commerce Platform for Apparel & Custom Design
*For Capstone / Research Documentation*

---

## 1. System Overview

**Thread & Press Hub** is a full-stack e-commerce web application for selling apparel products (t-shirts, hoodies, pants, dresses, accessories) with an integrated **custom apparel design system**, **AI-powered chatbot**, **real-time support chat**, **contact management system**, and **admin management dashboard**.

---

## 2. Technology Stack

| Layer | Technology | Version |
|-------|-----------|---------|
| **Backend Language** | PHP (Procedural) | 8.2.12 |
| **Database** | MySQL / MariaDB | 10.4.32 |
| **Web Server** | Apache (XAMPP) | — |
| **Frontend Framework** | Bootstrap | 5.3.0 |
| **JavaScript** | Vanilla JS (ES6) | — |
| **Icons** | Font Awesome | 6.4.0 |
| **Font** | Google Fonts (Inter) | — |
| **AI Integration** | Google Gemini 2.0 Flash API | — |
| **Email** | PHP mail() / PHPMailer (SMTP) | — |
| **Payment** | GCash / Maya / Cash on Delivery | — |

---

## 3. System Architecture Diagram (Textual)

```
┌─────────────────────────────────────────────────────────────────────┐
│                        CLIENT LAYER (Browser)                       │
│  ┌─────────────┐  ┌──────────────┐  ┌───────────┐  ┌────────────┐ │
│  │  Bootstrap 5 │  │  Vanilla JS  │  │ Font      │  │ CSS Custom │ │
│  │  (UI Layout) │  │  (ES6)       │  │ Awesome   │  │ Properties │ │
│  └─────────────┘  └──────────────┘  └───────────┘  └────────────┘ │
└──────────────────────────┬──────────────────────────────────────────┘
                           │ HTTP / AJAX (POST/GET)
                           ▼
┌─────────────────────────────────────────────────────────────────────┐
│                     APPLICATION LAYER (Apache/PHP)                   │
│                                                                     │
│  ┌──────────────────────────────────────────────────────────────┐   │
│  │                     PUBLIC MODULES                            │   │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌────────────────┐  │   │
│  │  │ Shop &   │ │ Cart &   │ │ User     │ │ Custom Design  │  │   │
│  │  │ Catalog  │ │ Checkout │ │ Auth     │ │ System         │  │   │
│  │  └──────────┘ └──────────┘ └──────────┘ └────────────────┘  │   │
│  │  ┌──────────┐ ┌──────────┐ ┌────────────────┐              │   │
│  │  │ Order    │ │ Contact  │ │ AI Chatbot     │              │   │
│  │  │ Tracking │ │ Form     │ │ (Gemini)       │              │   │
│  │  └──────────┘ └──────────┘ └────────────────┘              │   │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐                   │   │
│  │  │ Support  │ │ Password │ │ Pages /  │                   │   │
│  │  │ Chat     │ │ Reset    │ │ Info Hub │                   │   │
│  │  └──────────┘ └──────────┘ └──────────┘                   │   │
│  └──────────────────────────────────────────────────────────────┘   │
│                                                                     │
│  ┌──────────────────────────────────────────────────────────────┐   │
│  │                     ADMIN MODULES                             │   │
│  │  ┌───────────┐ ┌──────────┐ ┌──────────┐ ┌───────────────┐  │   │
│  │  │ Dashboard │ │ Product  │ │ Order    │ │ User          │  │   │
│  │  │ & Stats   │ │ Mgmt     │ │ Mgmt     │ │ Mgmt          │  │   │
│  │  └───────────┘ └──────────┘ └──────────┘ └───────────────┘  │   │
│  │  ┌───────────┐ ┌──────────┐ ┌───────────┐ ┌─────────────┐  │   │
│  │  │ Custom    │ │ Contact  │ │ Support   │ │ Audit       │  │   │
│  │  │ Orders    │ │ Mgmt     │ │ Chat Mgmt │ │ Log         │  │   │
│  │  └───────────┘ └──────────┘ └───────────┘ └─────────────┘  │   │
│  └──────────────────────────────────────────────────────────────┘   │
│                                                                     │
│  ┌──────────────────────────────────────────────────────────────┐   │
│  │                     AJAX / API ENDPOINTS                      │   │
│  │  • gemini_api.php          → AI Chatbot Responses             │   │
│  │  • product-recommendations.php → Smart Product Search         │   │
│  │  • design-ai-suggest.php   → AI Design Suggestions            │   │
│  │  • custom-design-ajax.php  → Save/Load Custom Designs         │   │
│  │  • order-lookup.php        → Order Status Lookup              │   │
│  │  • support-chat-ajax.php   → Real-time Support Messages       │   │
│  └──────────────────────────────────────────────────────────────┘   │
│                                                                     │
│  ┌──────────────────────────────────────────────────────────────┐   │
│  │                     SHARED COMPONENTS                         │   │
│  │  • includes/header/header.php  → Shared navigation bar        │   │
│  │  • includes/footer/footer.php  → Shared footer                │   │
│  │  • includes/admin-sidebar.php  → Admin sidebar with badges    │   │
│  │  • includes/email-helper.php   → Email notification system    │   │
│  └──────────────────────────────────────────────────────────────┘   │
└──────────────────────────┬──────────────────────────────────────────┘
                           │ MySQLi (Prepared Statements)
                           ▼
┌─────────────────────────────────────────────────────────────────────┐
│                       DATA LAYER (MySQL/MariaDB)                    │
│                                                                     │
│  ┌─────────────────────┐  ┌───────────────────────────────────────┐ │
│  │  threadpresshub DB  │  │  threadpresshub_contact DB            │ │
│  │  ├─ users           │  │  ├─ contact_messages                  │ │
│  │  ├─ products        │  │  ├─ contact_categories                │ │
│  │  ├─ orders          │  │  └─ contact_messages_responses        │ │
│  │  ├─ order_items     │  └───────────────────────────────────────┘ │
│  │  ├─ gcash_trans     │                                            │
│  │  ├─ custom_designs  │  ┌───────────────────────────────────────┐ │
│  │  ├─ custom_orders   │  │  FILE STORAGE                         │ │
│  │  ├─ custom_order_   │  │  ├─ /images/products/  (Product imgs) │ │
│  │  │  payments        │  │  ├─ /images/hero/      (Banner imgs)  │ │
│  │  ├─ chat_history    │  │  ├─ /uploads/designs/  (Custom design)│ │
│  │  ├─ support_        │  │  ├─ /uploads/payments/ (Payment proof)│ │
│  │  │  conversations   │  │  └─ /uploads/support/  (Chat images)  │ │
│  │  ├─ support_        │  └───────────────────────────────────────┘ │
│  │  │  messages        │                                            │
│  │  ├─ audit_log       │                                            │
│  │  ├─ password_resets │                                            │
│  │  └─ login_attempts  │                                            │
│  └─────────────────────┘                                            │
└─────────────────────────────────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────────┐
│                    EXTERNAL SERVICES                                 │
│  ┌─────────────────────────────┐  ┌──────────────────────────────┐  │
│  │ Google Gemini 2.0 Flash API │  │ GCash / Maya Payment         │  │
│  │ • Chatbot responses         │  │ • Reference number           │  │
│  │ • Product recommendations   │  │   verification               │  │
│  │ • Design suggestions        │  │ • Payment proof upload       │  │
│  └─────────────────────────────┘  └──────────────────────────────┘  │
│  ┌─────────────────────────────┐                                    │
│  │ SMTP / PHP mail()           │                                    │
│  │ • Password reset emails     │                                    │
│  │ • Order notifications       │                                    │
│  └─────────────────────────────┘                                    │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 4. Database Schema (Entity-Relationship)

### 4.1 Main Database: `threadpresshub`

```
┌──────────────┐       ┌──────────────────┐       ┌───────────────┐
│    users     │       │     orders       │       │  order_items   │
│──────────────│       │──────────────────│       │───────────────│
│ PK id        │──┐    │ PK id            │──┐    │ PK id          │
│ fullname     │  │    │ FK user_id       │  │    │ FK order_id    │
│ email        │  ├───>│ subtotal         │  ├───>│ FK product_id  │
│ phone        │  │    │ discount_amount  │  │    │ quantity       │
│ password     │  │    │ discount_type    │  │    │ unit_price     │
│ user_type    │  │    │ delivery_fee     │  │    │ subtotal       │
│ (regular/pwd/│  │    │ total            │  │    │ color          │
│  senior/     │  │    │ payment_method   │  │    │ size           │
│  admin)      │  │    │ (gcash/maya/cod) │  │    │ custom_design  │
│ pwd_id       │  │    │ payment_reference│  │    │  _id           │
│ senior_id    │  │    │ delivery_address │  │    └───────────────┘
│ street_      │  │    │ notes            │  │
│  address     │  │    │ status           │  │    ┌───────────────────┐
│ barangay     │  │    │ created_at       │  │    │ gcash_transactions│
│ city         │  │    └──────────────────┘  │    │───────────────────│
│ province     │  │                          └───>│ PK id             │
│ zipcode      │  │                               │ FK order_id       │
│ created_at   │  │                               │ reference_number  │
└──────────────┘  │                               │ amount            │
       │          │                               │ status            │
       │          │    ┌──────────────────┐       └───────────────────┘
       │          │    │  custom_designs  │
       │          │    │──────────────────│       ┌───────────────────┐
       │          ├───>│ PK id            │       │   products        │
       │          │    │ FK user_id       │       │───────────────────│
       │          │    │ product_type     │       │ PK id             │
       │          │    │ design_image     │       │ name              │
       │          │    │ design_image_back│       │ description       │
       │          │    │ design_data(JSON)│       │ price             │
       │          │    │ notes            │       │ category          │
       │          │    │ status           │       │ gender (mens/     │
       │          │    │ admin_notes      │       │  womens/kids)     │
       │          │    │ order_id         │       │ available_colors  │
       │          │    └──────────────────┘       │ available_sizes   │
       │          │           │                   │ image             │
       │          │           ▼                   │ status            │
       │          │    ┌──────────────────┐       └───────────────────┘
       │          │    │  custom_orders   │
       │          │    │──────────────────│
       │          ├───>│ PK id            │
       │          │    │ FK user_id       │
       │          │    │ FK design_id     │
       │          │    │ design_image     │
       │          │    │ product_type     │
       │          │    │ apparel_color    │
       │          │    │ size             │
       │          │    │ quantity         │
       │          │    │ base_price       │
       │          │    │ print_cost       │
       │          │    │ color_cost       │
       │          │    │ subtotal         │
       │          │    │ discount_type    │
       │          │    │ discount_amount  │
       │          │    │ total_price      │
       │          │    │ status           │
       │          │    └──────────────────┘
       │          │           │
       │          │           ▼
       │          │    ┌──────────────────────┐
       │          │    │ custom_order_payments │
       │          │    │──────────────────────│
       │          │    │ PK id                │
       │          │    │ FK custom_order_id   │
       │          │    │ payment_method       │
       │          │    │ payment_proof        │
       │          │    │ reference_number     │
       │          │    │ amount               │
       │          │    │ payment_status       │
       │          │    └──────────────────────┘
       │          │
       │          │    ┌──────────────────┐       ┌──────────────────┐
       │          │    │  chat_history    │       │ support_messages │
       │          │    │──────────────────│       │──────────────────│
       │          └───>│ PK id            │   ┌──>│ PK id            │
       │               │ FK user_id       │   │   │ FK conversation  │
       │               │ user_message     │   │   │    _id           │
       │               │ bot_response     │   │   │ FK sender_id     │
       │               │ created_at       │   │   │ sender_type      │
       │               └──────────────────┘   │   │ message          │
       │                                      │   │ image_path       │
       │          ┌──────────────────────┐    │   │ is_read          │
       │          │ support_conversations│    │   │ created_at       │
       │          │──────────────────────│    │   └──────────────────┘
       ├─────────>│ PK id               │────┘
       │          │ FK user_id          │
       │          │ subject             │
       │          │ status (open/closed)│
       │          └──────────────────────┘
       │
       │          ┌──────────────────┐  ┌──────────────────┐  ┌──────────────────┐
       │          │  audit_log       │  │ password_resets   │  │ login_attempts   │
       │          │──────────────────│  │──────────────────│  │──────────────────│
       ├─────────>│ PK id            │  │ PK id            │  │ PK id            │
                  │ FK user_id       │  │ FK user_id       │  │ email            │
                  │ action           │  │ token            │  │ ip_address       │
                  │ entity_type      │  │ expires_at       │  │ attempted_at     │
                  │ entity_id        │  │ used             │  └──────────────────┘
                  │ details          │  │ created_at       │
                  │ ip_address       │  └──────────────────┘
                  │ user_agent       │
                  │ created_at       │
                  └──────────────────┘
```

### 4.2 Contact Database: `threadpresshub_contact` (Separate DB)

```
┌──────────────────────┐       ┌────────────────────────────┐
│  contact_messages    │       │ contact_messages_responses  │
│──────────────────────│       │────────────────────────────│
│ PK id                │──────>│ PK id                      │
│ name                 │       │ FK contact_id              │
│ email                │       │ FK admin_id                │
│ phone                │       │ response_message           │
│ subject              │       │ attachments (JSON)         │
│ message              │       │ created_at                 │
│ category             │       └────────────────────────────┘
│ priority (low/normal/│
│  high/urgent)        │       ┌────────────────────────────┐
│ status (new/read/    │       │  contact_categories        │
│  responded/closed)   │       │────────────────────────────│
│ ip_address           │       │ PK id                      │
│ user_agent           │       │ name                       │
│ admin_notes          │       │ description                │
│ assigned_to          │       │ active                     │
│ timestamps           │       └────────────────────────────┘
└──────────────────────┘
```

---

## 5. Module Breakdown

### 5.1 User Authentication Module
| Component | File | Description |
|-----------|------|-------------|
| Login | `login.php` | Email/password authentication with bcrypt |
| Registration | `register.php` | Account creation with type selection (Regular/PWD/Senior) |
| Profile | `profile.php` | Update personal info, address, password |
| Forgot Password | `forgot-password.php` | Request password reset token via email |
| Reset Password | `reset-password.php` | Validate reset token and update password |
| Logout | `logout.php` | Session destruction |
| Admin Gate | `includes/config.php` | Role-based access control |

### 5.2 Product Catalog Module
| Component | File | Description |
|-----------|------|-------------|
| Homepage | `index.php` | Featured products, promotions |
| Shop Page | `shop.php` | Product grid with gender/category/color/size filters |
| Promotions | `promotion.php` | Promotions & deals page |
| Pages | `pages.php` | Static information pages (About Us, Shipping Info, etc.) |
| Product Images | `images/products/` | Product image storage |

### 5.3 Shopping Cart & Checkout Module
| Component | File | Description |
|-----------|------|-------------|
| Cart | `cart.php` | localStorage-based cart with dynamic pricing |
| Checkout | `checkout.php` | Address, delivery method, payment selection |
| GCash Payment | `payment_gcash.php` | GCash reference number submission |
| Maya Payment | `payment_maya.php` | Maya reference number submission |
| Order Confirmation | `order_confirmation.php` | Post-purchase summary |

### 5.4 Order Management Module
| Component | File | Description |
|-----------|------|-------------|
| User Orders | `orders.php` | Order history for customers |
| Order Details | `order_details.php` | Individual order breakdown |
| Admin Orders | `admin/orders.php` | Status management, verification |
| Admin Order Details | `admin/order_details.php` | Detailed order info with user data |

### 5.5 Custom Design Module
| Component | File | Description |
|-----------|------|-------------|
| Design Tool | `custom-design.php` | Canvas-based apparel designer |
| Design AJAX | `includes/custom-design-ajax.php` | Save/list/fetch designs |
| AI Suggestions | `includes/design-ai-suggest.php` | Gemini-powered design ideas |
| Order Summary | `custom-order-summary.php` | Custom order review |
| Custom Payment | `custom-payment.php` | Custom order payment |
| Custom Orders | `my-custom-orders.php` | User's custom order list |
| Order Tracking | `custom-order-tracking.php` | Real-time status tracking |
| Admin Designs | `admin/custom-designs.php` | Review submitted designs |
| Admin Orders | `admin/custom-orders.php` | Manage custom order workflow |

### 5.6 AI Chatbot Module
| Component | File | Description |
|-----------|------|-------------|
| Chat UI | `js/chatbot.js` | Floating chat widget, conversation flow |
| Gemini API | `includes/gemini_api.php` | Google Gemini 2.0 Flash integration |
| Recommendations | `includes/product-recommendations.php` | Context-aware product suggestions |
| Order Lookup | `includes/order-lookup.php` | Order status via chatbot |
| Chat History | `chat_history.php` | Past conversation viewer |

### 5.7 Contact Form Module
| Component | File | Description |
|-----------|------|-------------|
| Contact Page | `contact.php` | Multi-category contact form |
| Contact Config | `includes/contact-config.php` | Separate DB connection |
| Admin Mgmt | `admin/contact-management.php` | View, assign, respond |

### 5.8 Support Chat Module
| Component | File | Description |
|-----------|------|-------------|
| User Chat | `support-chat.php` | Live support chat interface for customers |
| Chat AJAX | `includes/support-chat-ajax.php` | Real-time message handling |
| Chat Config | `includes/support-chat-config.php` | Support chat DB configuration & helpers |
| Chat JS | `js/support-chat.js` | Client-side chat functionality |
| Admin Chat | `admin/support-chat.php` | Admin support chat management |

### 5.9 Admin Dashboard Module
| Component | File | Description |
|-----------|------|-------------|
| Dashboard | `admin/dashboard.php` | Stats, recent orders, quick actions |
| Products | `admin/products.php` | CRUD operations for products |
| Users | `admin/users.php` | Customer account management |
| Audit Log | `admin/audit-log.php` | Security & activity logging viewer |
| Admin Profile | `admin/profile.php` | Admin profile management |
| Admin Sidebar | `includes/admin-sidebar.php` | Navigation sidebar with badge counts |
| Sidebar JS | `js/admin-sidebar.js` | Responsive sidebar toggle |
| Admin Logout | `admin/logout.php` | Admin session destruction |

---

## 6. User Roles & Access Control

```
┌─────────────────────────────────────────────────────────┐
│                    ACCESS CONTROL MATRIX                  │
├──────────────────┬──────────┬─────┬────────┬────────────┤
│ Feature          │ Guest    │ User│ PWD/   │ Admin      │
│                  │          │     │ Senior │            │
├──────────────────┼──────────┼─────┼────────┼────────────┤
│ Browse Products  │    ✓     │  ✓  │   ✓    │     ✓      │
│ Add to Cart      │    ✓     │  ✓  │   ✓    │     ✓      │
│ Checkout         │    ✗     │  ✓  │   ✓    │     ✓      │
│ 20% Discount     │    ✗     │  ✗  │   ✓    │     ✗      │
│ Custom Design    │    ✗     │  ✓  │   ✓    │     ✓      │
│ AI Chatbot       │    ✓     │  ✓  │   ✓    │     ✓      │
│ Contact Form     │    ✓     │  ✓  │   ✓    │     ✓      │
│ Support Chat     │    ✗     │  ✓  │   ✓    │     ✓      │
│ Order History    │    ✗     │  ✓  │   ✓    │     ✓      │
│ Password Reset   │    ✓     │  ✓  │   ✓    │     ✓      │
│ Admin Dashboard  │    ✗     │  ✗  │   ✗    │     ✓      │
│ Manage Products  │    ✗     │  ✗  │   ✗    │     ✓      │
│ Manage Orders    │    ✗     │  ✗  │   ✗    │     ✓      │
│ Manage Users     │    ✗     │  ✗  │   ✗    │     ✓      │
│ Verify Payments  │    ✗     │  ✗  │   ✗    │     ✓      │
│ Audit Log        │    ✗     │  ✗  │   ✗    │     ✓      │
│ Support Chat Mgmt│    ✗     │  ✗  │   ✗    │     ✓      │
│ Contact Mgmt     │    ✗     │  ✗  │   ✗    │     ✓      │
└──────────────────┴──────────┴─────┴────────┴────────────┘
```

---

## 7. Data Flow Diagrams

### 7.1 Standard Order Flow
```
┌──────┐    ┌──────────┐    ┌──────────┐    ┌─────────┐    ┌───────────┐
│ User │───>│ Browse   │───>│ Add to   │───>│Checkout │───>│ Payment   │
│      │    │ Products │    │ Cart     │    │         │    │ (GCash/   │
└──────┘    └──────────┘    └──────────┘    └─────────┘    │ Maya/COD) │
                                                           └─────┬─────┘
                                                                 │
  ┌─────────────┐    ┌─────────────┐    ┌──────────┐            │
  │ Delivered / │<───│ Out for     │<───│Preparing │<───────────┘
  │ Completed   │    │ Delivery    │    │          │  Admin confirms
  └─────────────┘    └─────────────┘    └──────────┘
```

### 7.2 Custom Design Order Flow
```
┌──────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐
│ User │───>│ Design   │───>│ Save     │───>│ Create   │───>│ Upload   │
│      │    │ Apparel  │    │ Design   │    │ Custom   │    │ Payment  │
└──────┘    └──────────┘    └──────────┘    │ Order    │    │ Proof    │
                 │                          └──────────┘    └────┬─────┘
                 │ AI Suggest                                    │
                 ▼                                               │
          ┌──────────────┐                                       │
          │ Gemini API   │                                       ▼
          │ Suggestions  │    ┌──────────┐    ┌──────────┐  ┌──────────┐
          └──────────────┘    │ Delivered │<───│ Ready    │<─│ Printing │
                              └──────────┘    │ Pickup   │  └────┬─────┘
                                              └──────────┘       │
                                                            Admin verifies
                                                            payment first
```

### 7.3 AI Chatbot Flow
```
┌──────┐    ┌──────────┐    ┌──────────────┐    ┌──────────────────┐
│ User │───>│ Chat     │───>│ Send to      │───>│ Gemini API       │
│ asks │    │ Widget   │    │ Gemini API   │    │ processes with   │
└──────┘    └──────────┘    │ with context │    │ system context   │
                            └──────────────┘    └────────┬─────────┘
                                                         │
                                            ┌────────────┼────────────┐
                                            │            │            │
                                            ▼            ▼            ▼
                                      ┌──────────┐ ┌─────────┐ ┌──────────┐
                                      │ Product  │ │ Order   │ │ General  │
                                      │ Catalog  │ │ History │ │ Response │
                                      │ Context  │ │ Context │ │          │
                                      └──────────┘ └─────────┘ └──────────┘
                                                         │
                                                         ▼
                                                  ┌──────────────┐
                                                  │ Save to      │
                                                  │ chat_history │
                                                  └──────────────┘
```

---

## 8. Security Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    SECURITY LAYERS                           │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  1. AUTHENTICATION                                          │
│     • bcrypt password hashing (PASSWORD_BCRYPT)             │
│     • Session-based auth ($_SESSION)                        │
│     • Login redirect guards                                 │
│     • Admin role verification                               │
│     • Login attempt tracking (login_attempts table)         │
│     • Token-based password reset (password_resets table)    │
│                                                             │
│  2. INPUT VALIDATION                                        │
│     • sanitizeInput() function (htmlspecialchars + trim)     │
│     • Email format validation (FILTER_VALIDATE_EMAIL)       │
│     • File type whitelisting (JPEG, PNG, GIF, WebP)         │
│     • File size limits (5MB max)                            │
│                                                             │
│  3. DATABASE SECURITY                                       │
│     • Prepared statements with parameterized queries        │
│     • mysqli_real_escape_string for dynamic queries         │
│     • Foreign key constraints with CASCADE                  │
│     • UTF8MB4 charset encoding                              │
│                                                             │
│  4. DISCOUNT VALIDATION                                     │
│     • Client-side: Discount dropdown filtered by user_type  │
│     • Server-side: Discount type verified against DB        │
│     • Only PWD users can apply PWD discount                 │
│     • Only Senior users can apply Senior discount           │
│                                                             │
│  5. FILE UPLOAD SECURITY                                    │
│     • Random filename generation (uniqid + hex)             │
│     • Extension whitelisting                                │
│     • Directory isolation (/uploads/)                       │
│     • Base64 validation for canvas designs                  │
│                                                             │
│  6. AUDIT & MONITORING                                      │
│     • Audit log tracking (user actions, IP, user agent)     │
│     • Admin audit log viewer with filters                   │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## 9. File Structure Map

```
thread-and-presshub/
│
├── index.php                    # Homepage
├── shop.php                     # Product catalog with filters
├── cart.php                     # Shopping cart
├── checkout.php                 # Checkout flow
├── payment_gcash.php            # GCash payment processing
├── payment_maya.php             # Maya payment processing
├── order_confirmation.php       # Order success page
├── orders.php                   # User order history
├── order_details.php            # Single order details
│
├── login.php                    # User login
├── register.php                 # User registration
├── forgot-password.php          # Password reset request
├── reset-password.php           # Password reset with token
├── profile.php                  # User profile management
├── logout.php                   # Session logout
│
├── custom-design.php            # Canvas-based design tool
├── custom-order-summary.php     # Custom order review
├── custom-order-tracking.php    # Track custom order status
├── custom-payment.php           # Custom order payment
├── my-custom-orders.php         # User's custom order list
│
├── chat_history.php             # AI chatbot history viewer
├── contact.php                  # Contact form
├── support-chat.php             # Live support chat
├── about.php                    # About page
├── promotion.php                # Promotions & deals
├── pages.php                    # Static information pages
│
├── admin/                       # Admin panel
│   ├── dashboard.php            # Admin dashboard with stats
│   ├── products.php             # Product CRUD
│   ├── orders.php               # Order management
│   ├── order_details.php        # Detailed order view
│   ├── users.php                # User management
│   ├── custom-designs.php       # Design review
│   ├── custom-orders.php        # Custom order management
│   ├── contact-management.php   # Contact form management
│   ├── support-chat.php         # Admin support chat management
│   ├── audit-log.php            # Security & activity log viewer
│   ├── profile.php              # Admin profile
│   └── logout.php               # Admin session logout
│
├── includes/                    # Backend logic
│   ├── config.php               # DB connection, session, helpers
│   ├── contact-config.php       # Contact DB connection
│   ├── gemini_api.php           # Gemini AI chatbot API
│   ├── product-recommendations.php  # AI product search
│   ├── design-ai-suggest.php    # AI design suggestions
│   ├── custom-design-ajax.php   # Custom design AJAX handler
│   ├── order-lookup.php         # Order status API
│   ├── support-chat-ajax.php    # Support chat AJAX handler
│   ├── support-chat-config.php  # Support chat DB config & helpers
│   ├── email-helper.php         # Email notification system (mail/SMTP)
│   ├── admin-sidebar.php        # Admin sidebar navigation component
│   ├── header/header.php        # Shared header/navbar
│   └── footer/footer.php        # Shared footer
│
├── js/                          # Client-side scripts
│   ├── chatbot.js               # AI chatbot widget
│   ├── support-chat.js          # Support chat client
│   ├── admin-sidebar.js         # Admin sidebar toggle
│   └── animations.js            # UI animations
│
├── css/
│   └── style.css                # Main stylesheet
│
├── images/
│   ├── hero/                    # Banner/promo images
│   └── products/                # Product catalog images
│
└── uploads/
    ├── designs/                 # Custom design files
    ├── payments/                # Payment proof uploads
    └── support/                 # Support chat attachments
```

---

## 10. API Endpoints

| Endpoint | Method | Purpose | Auth Required |
|----------|--------|---------|---------------|
| `includes/gemini_api.php` | POST | AI chatbot conversation | Optional |
| `includes/product-recommendations.php` | POST | Product search & suggestions | No |
| `includes/design-ai-suggest.php` | POST | AI design idea generation | Yes |
| `includes/custom-design-ajax.php` | POST | Save/list/fetch custom designs | Yes |
| `includes/order-lookup.php` | POST | Order status by order ID | No |
| `includes/support-chat-ajax.php` | POST | Real-time support chat messages | Yes |

---

## 11. Deployment Environment

```
┌───────────────────────────────┐
│         XAMPP Stack           │
│  ┌─────────────────────────┐  │
│  │ Apache Web Server       │  │
│  │ Port: 80                │  │
│  │ DocumentRoot: /htdocs/  │  │
│  └─────────────────────────┘  │
│  ┌─────────────────────────┐  │
│  │ PHP 8.2.12              │  │
│  │ Extensions: mysqli,     │  │
│  │ json, curl, gd, mbstring│  │
│  └─────────────────────────┘  │
│  ┌─────────────────────────┐  │
│  │ MariaDB 10.4.32         │  │
│  │ Port: 3306              │  │
│  │ Databases:              │  │
│  │  • threadpresshub       │  │
│  │  • threadpresshub_contact│ │
│  └─────────────────────────┘  │
└───────────────────────────────┘
```

---

*Document generated for capstone/research documentation purposes.*
*Thread & Press Hub — E-Commerce Platform for Apparel & Custom Design*
