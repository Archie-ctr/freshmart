# FreshMart — Online Grocery Store
## Project Report

**Student Name:** Archie K Gonwoe Jr
**Registration Number:** 23686/2024
**Course:** Web Application Development
**Institution:** [Your Institution Name]

---

## Submission Checklist

| # | Item | Link / Location |
|---|------|----------------|
| 1 | GitHub Repository | https://github.com/Archie-ctr/freshmart |
| 2 | Live Deployment | https://freshmartstore.gt.tc/ |
| 3 | Project Report | This document |
| 4 | Source Code | GitHub repository |
| 5 | Database Script | `install.sql` in repository root |
| 6 | Screenshots | See Screenshots section |

---

## 1. Introduction

FreshMart is a fully functional online grocery store web application built from scratch using PHP 8.2 and MySQL 8.0. The platform allows customers in Rwanda to browse fresh groceries across six product categories, manage a shopping cart, and pay securely using Mobile Money — either MTN MoMo or Airtel Money — through the Paypack payment gateway. Prices are displayed in both USD and Rwandan Francs (RWF) in real time.

The project was built without any PHP framework, demonstrating a strong command of core PHP, SQL, HTML, CSS, and JavaScript. It is containerised with Docker for local development, deployed live on InfinityFree hosting at https://freshmartstore.gt.tc/, and ships with a fully automated CI/CD pipeline via GitHub Actions that lints, builds, and deploys on every push to the main branch.

Beyond the core store functionality, FreshMart includes a comprehensive set of advanced features: a full admin dashboard with analytics, automated email notifications for every key event, a loyalty points system, flash deals, product subscriptions, a referral program, a vendor marketplace framework, AI-powered product recommendations, CSRF protection, rate limiting, OTP-based 2FA, and a security audit log.

---

## 2. Problem Statement

Rwanda's retail grocery market is largely physical — customers visit markets or shops in person, which is time-consuming, especially in urban areas like Kigali. Existing digital solutions either do not support local Mobile Money payments, are too expensive for small vendors, or are built on foreign platforms that do not reflect the East African context.

Specifically, the following problems existed:

- No affordable, locally-relevant online grocery platform supporting MTN MoMo and Airtel Money natively
- No unified platform where small grocery vendors could reach customers online
- Prices on imported platforms are shown only in USD, which is unfamiliar to most Rwandan shoppers
- No automated communication between store and customers — no order receipts, no tracking updates, no new product announcements

FreshMart was built to directly solve all of these problems.

---

## 3. Objectives

1. Build a complete, production-ready online grocery store with product catalog, cart, and checkout
2. Integrate Paypack Mobile Money API supporting both MTN MoMo (078/079) and Airtel Money (072/073/075) via USSD push
3. Display all prices in both USD and RWF with a configurable exchange rate
4. Implement a full admin panel covering products, orders, customers, analytics, vendors, and settings
5. Build a complete automated email notification system covering all customer and admin touchpoints
6. Implement security best practices: CSRF tokens, rate limiting, password hashing, OTP 2FA, security audit logging
7. Containerise the application with Docker and deploy via an automated GitHub Actions CI/CD pipeline
8. Deliver advanced features: loyalty points, referrals, flash deals, subscriptions, AI recommendations, and a vendor marketplace

---

## 4. System Features

### 4.1 Customer Features

| Feature | Description |
|---------|-------------|
| Product Catalog | Browse all products with search, category filter, and sort by price/name |
| Product Detail Page | Full page with image, dual-currency price, star ratings, reviews, and AI-powered recommendations |
| Category Collections | Six categories: Fruits, Vegetables, Dairy, Beverages, Bakery, Snacks |
| Flash Deals | Time-limited discounted products with percentage-off badge shown on homepage and shop |
| Shopping Cart | Session-based cart — add, remove, update quantities, real-time totals in USD and RWF |
| Checkout | Two-step flow: delivery details form → Mobile Money payment |
| Mobile Money Payment | Paypack USSD push to MTN or Airtel number, with 3-minute polling loop |
| Order Confirmation | Post-payment confirmation page with order ID, items, total, and delivery address |
| My Orders | Full order history with status badges for each order |
| Wishlist | Save and remove products; toggled via AJAX without page reload |
| Loyalty Points | Earn 1 point per $1 spent; points shown in account dropdown |
| Referral Program | Unique referral code per user; referring a friend earns 100 loyalty points |
| Subscription Boxes | Weekly, biweekly, or monthly curated grocery boxes |
| Vendor Shop | Register as a vendor to sell products on the marketplace |
| Product Reviews | Authenticated customers can submit a star rating and written review |
| Newsletter | Email and optional SMS subscription from the site footer |
| User Authentication | Register, login, logout with bcrypt password hashing |
| Forgot Password | Email a 6-digit OTP code; enter code → set new password |
| 2FA (Optional) | Email OTP two-factor authentication, toggled per-account |
| Password Eye Toggle | Show/hide password on login, register, and reset password pages |

### 4.2 Automated Email Notifications

Every key event in the system triggers an automatic email. All emails are sent from `archiekgonwoe@gmail.com` via Gmail SMTP with STARTTLS encryption, using a custom raw-socket PHP mailer with no external libraries.

| Trigger | Recipient | Email Content |
|---------|-----------|---------------|
| Customer registers | Customer | Welcome email with "Start Shopping" button and referral tip |
| Payment confirmed | Customer | Order receipt with order number, item list, total (USD + RWF), delivery estimate |
| Payment confirmed | Admin | New order alert with customer name, email, phone, address, items, total, and "View in Admin" link |
| Admin changes order status | Customer | Tracking update with new status icon, human-readable message, "View My Orders" link |
| Admin adds new product | All customers | New arrival announcement with product image, name, description, price, and "Shop Now" link |
| Forgot password requested | Customer | 6-digit reset code valid for 10 minutes |
| 2FA login (if enabled) | Customer | 6-digit OTP verification code valid for 10 minutes |

### 4.3 Admin Features

| Feature | Description |
|---------|-------------|
| Dashboard | KPI cards: total revenue (USD + RWF), total orders, customers, products; recent orders table; low-stock alert |
| Product Management | Add, edit, delete products; drag-and-drop image upload or URL; featured tag; inventory tracking |
| Collections | Create and manage product categories with visibility toggle |
| Order Management | View all orders; update status (pending → paid → processing → shipped → delivered → cancelled) |
| Customer List | View all registered customers with email, phone, and join date |
| Analytics | 30-day revenue bar chart + orders line chart; top products by revenue; most-viewed products; orders by status; revenue by category; wishlist stats; conversion rate; average order value; new customers this month |
| Vendor Management | Approve or suspend vendor applications; manage payout requests |
| Flash Deals | Create time-limited deals with discount %; live/upcoming/expired status display |
| Security Log | Last 100 security events: logins, failed attempts, registrations, password resets |
| Shop Settings | General info, hero banner, announcement bar, shipping & tax, Paypack credentials, social/SEO, maintenance mode |

---

## 5. Technologies Used

| Layer | Technology | Purpose |
|-------|-----------|---------|
| Backend | PHP 8.2 (no framework) | All server-side logic, routing, and data processing |
| Database | MySQL 8.0 | Relational data storage with foreign key constraints |
| Frontend | HTML5, CSS3, Vanilla JavaScript | UI rendering, AJAX interactions, form validation |
| Charts | Chart.js 4 (CDN) | Admin analytics revenue and orders chart |
| Payment | Paypack API (Rwanda) | MTN MoMo and Airtel Money USSD push payments |
| Email | Gmail SMTP via raw PHP socket | All automated transactional emails |
| Web Server | Apache 2 | HTTP server inside Docker container |
| Containerisation | Docker + Docker Compose | Local development environment with MySQL service |
| CI/CD | GitHub Actions | Automated lint → build → FTP deploy pipeline |
| Hosting | InfinityFree | Live production server |
| Version Control | Git + GitHub | Source code management and collaboration |

---

## 6. System Architecture

### 6.1 Application Structure

FreshMart uses a flat-file PHP architecture — each URL maps to a PHP file. There is no MVC framework, making the codebase highly portable and easy to understand.

```
Browser
   │  HTTP Request
   ▼
Apache (InfinityFree / Docker)
   │
   ▼
PHP Page (index.php, shop.php, checkout.php, etc.)
   │
   ├── layout.php        startPage() / endPage() — shared header, nav, footer
   ├── functions.php     All helpers: auth, cart, price, settings, CSRF,
   │                     OTP, loyalty, referral, flash deals, AI recs
   ├── db.php            PDO singleton — Docker-aware retry loop
   ├── config.php        Production secrets (gitignored, FTP-uploaded)
   ├── paypack.php       Paypack REST API client
   ├── mailer.php        Raw-socket SMTP mailer + 7 email templates
   └── ajax/             JSON endpoints for AJAX calls
         ├── cart_add.php
         ├── cart_remove.php
         ├── cart_update.php
         ├── paypack_initiate.php
         ├── paypack_verify.php    ← fires all post-payment emails
         ├── wishlist_toggle.php
         ├── upload_image.php
         └── newsletter.php
```

### 6.2 Database Schema

The database contains 23 tables covering every feature of the platform:

| Table | Purpose |
|-------|---------|
| `profiles` | User accounts (customers + admins) |
| `ecom_collections` | Product categories |
| `ecom_products` | Product catalog |
| `ecom_product_collections` | Product ↔ category junction |
| `ecom_customers` | Order-level customer records |
| `ecom_orders` | Order headers with status and totals |
| `ecom_order_items` | Individual line items per order |
| `product_reviews` | Star ratings and comments |
| `shop_settings` | Key-value store for all admin-configurable settings |
| `wishlists` | Saved products per user |
| `loyalty_points` | Points earned and redeemed per user |
| `flash_deals` | Time-limited discount deals |
| `subscription_boxes` | Curated box definitions |
| `subscriptions` | User subscription records |
| `referrals` | Referrer-referred relationships |
| `vendors` | Vendor shop applications |
| `vendor_payouts` | Payout requests from vendors |
| `rate_limit` | IP-based request throttling |
| `security_log` | Auth and action audit trail |
| `otp_tokens` | 2FA and password reset OTP codes |
| `product_affinity` | AI recommendation co-purchase score matrix |
| `page_views` | Analytics page view tracking |
| `csrf_tokens` | CSRF token storage |

### 6.3 Payment Flow

```
Customer fills delivery form (name, email, address, city)
                │
                ▼
Customer enters MTN/Airtel phone number and clicks Pay
                │
                ▼
JS → POST /ajax/paypack_initiate.php
  Creates pending order in DB
  Calls Paypack cashin API → USSD push sent to phone
                │
                ▼
Customer approves payment on their phone
                │
                ▼
JS polls /ajax/paypack_verify.php every 5 seconds (max 3 min)
  paypack_check(ref) → pending | successful | failed
                │
        ┌───────┴────────┐
     successful        failed
        │                │
  Mark order paid    Mark order cancelled
  Award loyalty pts  Clear pending session
  Send emails (×2):  Allow retry
   - Customer receipt
   - Admin new order alert
  Redirect to
  /order-confirmation.php
```

### 6.4 Email Architecture

All emails are sent through a custom raw-socket SMTP implementation in `mailer.php` — no third-party library such as PHPMailer or SwiftMailer is used. The mailer opens a TCP socket to `smtp.gmail.com:587`, negotiates STARTTLS encryption, authenticates with an App Password, and sends RFC-compliant MIME multipart messages with both plain-text and HTML parts.

```
Any PHP file calls sendXxxEmail()
        │
        ▼
sendMail($to, $subject, $html)   [mailer.php]
        │
        ▼
TCP socket → smtp.gmail.com:587
EHLO → STARTTLS → AUTH LOGIN → MAIL FROM → RCPT TO → DATA → QUIT
        │
        ▼
Gmail delivers to recipient inbox
  From: archiekgonwoe@gmail.com
  Name: FreshMart
```

---

## 7. Screenshots

Screenshots of the live application are available at **https://freshmartstore.gt.tc/**

Key pages to screenshot for the submission:

| # | Page | URL |
|---|------|-----|
| 1 | Homepage | / |
| 2 | Shop / Product Listing | /shop.php |
| 3 | Product Detail + Reviews | /product.php?handle=organic-bananas |
| 4 | Shopping Cart | /cart.php |
| 5 | Checkout — Delivery Step | /checkout.php |
| 6 | Checkout — Payment Step | /checkout.php (step 2) |
| 7 | Order Confirmation | /order-confirmation.php |
| 8 | Login (with eye toggle + forgot password link) | /login.php |
| 9 | Forgot Password | /forgot-password.php |
| 10 | Admin Dashboard | /admin.php?tab=dashboard |
| 11 | Admin Analytics | /admin.php?tab=analytics |
| 12 | Admin Products | /admin.php?tab=products |
| 13 | Admin Orders | /admin.php?tab=orders |
| 14 | Admin Flash Deals | /admin.php?tab=flash |
| 15 | Admin Settings | /admin.php?tab=settings |

---

## 8. GitHub Repository

**https://github.com/Archie-ctr/freshmart**

The repository contains:
- Complete PHP source code for all pages and AJAX endpoints
- `install.sql` — full database schema with seed data (14 products, 6 categories, 3 subscription boxes)
- `Dockerfile` and `docker-compose.yml` — containerised local development
- `.github/workflows/ci-cd.yml` — automated CI/CD pipeline
- `config.example.php` — safe configuration template (real `config.php` is gitignored)
- `README.md` — full setup instructions for Docker and manual deployment

---

## 9. Live Deployment

**https://freshmartstore.gt.tc/**

Hosted on InfinityFree with MySQL 8.0. The application is deployed automatically via FTP on every push to the `main` branch through GitHub Actions.

Admin credentials:
- Email: `admin@freshmart.com`
- Password: `admin123`

---

## 10. CI/CD Pipeline

The pipeline is defined in `.github/workflows/ci-cd.yml` and runs automatically on every push or pull request to `main` or `master`.

```
Developer pushes code to GitHub (main branch)
                │
                ▼
        Job 1: PHP Lint
          php -l on every .php file
          Fails the pipeline on any syntax error
                │
                ▼ (on success)
        Job 2: Docker Build
          docker build -t freshmart:<sha> .
          Verifies the image compiles and runs
                │
                ▼ (on push to main only)
        Job 3: Deploy to InfinityFree via FTP
          SamKirkland/FTP-Deploy-Action@v4.3.5
          Uploads all PHP/CSS/JS/SQL files to /htdocs/
          Excludes: .git, Docker files, config.php, README.md
```

The `config.php` file containing database and email credentials is permanently excluded from both Git (via `.gitignore`) and FTP deployment (via the `exclude` list in the workflow). It is manually uploaded to the server via FTP using `curl` when credentials change, keeping secrets completely out of version control.

### Required GitHub Secret

| Secret | Value |
|--------|-------|
| `FTP_PASSWORD` | InfinityFree FTP account password |

---

## 11. Challenges Encountered

**1. Docker MySQL startup race condition**
The PHP Apache container starts faster than MySQL, causing connection failures on `docker compose up`. Resolved by implementing a retry loop in `db.php` that attempts to connect up to 10 times with 2-second waits before giving up, printing a friendly error instead of a blank page.

**2. Asynchronous Paypack USSD payment**
Paypack works by pushing a USSD prompt to the customer's phone — the payment is approved on their device, not in the browser. The checkout page cannot simply wait for a response. Solved with a JavaScript polling loop that calls `/ajax/paypack_verify.php` every 5 seconds for up to 3 minutes (36 polls), showing a live countdown timer, then displaying success or failure states.

**3. Gmail SMTP FROM address mismatch**
The initial configuration had `CFG_MAIL_FROM` set to a different address from `CFG_MAIL_USER`. Gmail's SMTP server rejects `MAIL FROM` commands that do not match the authenticated account, causing all emails to silently fail. Fixed by setting both constants to `archiekgonwoe@gmail.com`.

**4. config.php not deployable via CI/CD**
Since `config.php` contains production secrets it must never be committed to Git. But the CI/CD pipeline deploys via FTP from the Git repository, so `config.php` is also excluded from FTP. The solution was to upload `config.php` separately using a direct `curl` FTP command whenever credentials change, keeping it permanently out of version control while still being present on the live server.

**5. Dual-currency pricing across all templates**
Prices are stored in USD cents (integers) in the database but must display in both USD and RWF. Rather than storing RWF prices separately, the `formatPrice()` helper reads the `usd_to_rwf_rate` setting at runtime and formats both currencies in a single HTML snippet, meaning the admin can update the exchange rate in Settings and all prices across the site update instantly with no database migration.

**6. Email item rendering with two data shapes**
Order items exist in two forms: session cart items (keys: `name`, `price`) and database order items (keys: `product_name`, `unit_price`). The `sendOrderConfirmationEmail()` function in `mailer.php` was initially written for only one shape. Fixed by using the null coalescing operator — `$it['name'] ?? $it['product_name']` and `$it['price'] ?? $it['unit_price']` — making the function handle both shapes transparently.

**7. CSRF and rate limiting without a framework**
PHP frameworks provide CSRF and rate limiting out of the box. Building them from scratch required generating a cryptographically secure token with `random_bytes(32)`, storing it in the session, and verifying it with `hash_equals()` on every POST. Rate limiting is implemented with a `rate_limit` database table keyed by hashed IP and action name, with automatic cleanup of expired entries on each request.

---

## 12. Future Work

1. **SMS order notifications** — Integrate Africa's Talking SMS gateway to send order status updates via text message, complementing the email notifications for customers with limited internet access.

2. **Real-time delivery tracking** — Add a map-based tracking page with live driver location updates using WebSockets or Server-Sent Events, integrated with Google Maps or OpenStreetMap.

3. **Progressive Web App (PWA)** — Add a `manifest.json` and service worker so customers can install FreshMart on their phone's home screen and browse the product catalog offline.

4. **Full vendor marketplace activation** — The vendor data model, application flow, admin approval panel, and payout management are already built. The next step is enabling vendor-specific product pages and automated commission calculation from each sale.

5. **Multi-language support** — Add French and Kinyarwanda translations using a simple PHP key-value translation file, given that Rwanda's official languages include both alongside English.

6. **Enhanced AI recommendations** — The current recommendation engine uses a purchase-affinity matrix and view-based collaborative filtering. This could be upgraded to use AWS Personalize for real-time personalised recommendations at scale.

7. **Mobile application** — Build a React Native or Flutter app consuming a REST API layer on top of the existing PHP backend, providing a native mobile shopping experience.

8. **Inventory management with supplier alerts** — Automatically email suppliers when stock drops below a configurable threshold, and allow bulk inventory updates via CSV import in the admin panel.

---

## 13. Conclusion

FreshMart is a complete, production-deployed e-commerce application that demonstrates the full software development lifecycle applied to a real-world problem relevant to the Rwandan market. Starting from a blank PHP file, the project grew into a platform with 23 database tables, 35+ PHP files, 7 automated email types, a full admin panel with analytics, and an automated CI/CD deployment pipeline.

The project's key technical achievements are:

- A working Mobile Money payment integration handling the full asynchronous USSD push-and-poll flow
- A custom raw-socket SMTP mailer that sends 7 different transactional email types with no external dependencies
- A dual-currency pricing system (USD + RWF) configurable at runtime through admin settings
- A Docker-based local development environment with production parity
- A GitHub Actions pipeline that lints, builds, and deploys to a live server on every code push
- Security hardening with CSRF protection, bcrypt password hashing, IP rate limiting, OTP-based password reset and 2FA, and a full security audit log
- Advanced features including loyalty points, referral rewards, flash deals, subscription boxes, a vendor marketplace framework, and AI-powered product recommendations

The result is a live, publicly accessible grocery store that a real business in Rwanda could use today.

---

*Report prepared by: Archie K Gonwoe Jr — Registration No. 23686/2024*
