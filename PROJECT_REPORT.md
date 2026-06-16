# FreshMart — Online Grocery Store
## Project Report

**Student Name:** Archie K Gonwoe Jr
**Registration Number:** 23686/2024

---

## Submission Checklist

| # | Item | Details |
|---|------|---------|
| 1 | GitHub Repository | https://github.com/Archie-ctr/freshmart |
| 2 | Live Deployment URL | https://freshmartstore.gt.tc/ |
| 3 | Project Report | This document |
| 4 | Source Code | Available in GitHub repository |
| 5 | Database Script | `install.sql` in repository root |
| 6 | Screenshots | See Screenshots section below |

---

## 1. Introduction

FreshMart is a full-featured online grocery store web application developed as a course project. The platform enables customers to browse and purchase fresh groceries — including fruits, vegetables, dairy, beverages, bakery items, and snacks — from the comfort of their homes. Payment is processed via Rwanda's Paypack Mobile Money gateway (MTN MoMo and Airtel Money), making it contextually relevant to the East African market.

The application was built using PHP 8.2 on the backend with a MySQL 8.0 database, containerised with Docker for consistent local development and production parity, and deployed to a live server accessible at https://freshmartstore.gt.tc/.

---

## 2. Problem Statement

In Rwanda and the broader East African region, access to quality groceries often requires physical trips to markets or supermarkets, which is time-consuming and inconvenient. Existing online grocery solutions are either too expensive, require complex onboarding, or do not support local Mobile Money payment methods that the majority of the population uses.

There was a need for a lightweight, locally-relevant e-commerce platform that:
- Supports Mobile Money payments (MTN MoMo / Airtel Money) natively
- Displays prices in both USD and RWF (Rwandan Francs)
- Works on low-bandwidth connections with a simple, fast frontend
- Provides merchants with a full admin panel to manage products and orders without technical knowledge

---

## 3. Objectives

1. Build a functional online grocery store with product catalog, shopping cart, and checkout flow
2. Integrate Paypack Mobile Money API for seamless USSD-push payment collection
3. Implement role-based access control (Admin / Customer)
4. Provide an admin dashboard for product, order, collection, and settings management
5. Deploy the application to a live server using Docker and an automated CI/CD pipeline
6. Implement advanced features including wishlists, flash deals, referral rewards, loyalty points, subscription boxes, and a vendor marketplace framework

---

## 4. System Features

### Customer-Facing Features

| Feature | Description |
|---------|-------------|
| Product Listing | Browse all products with search, filter by category, sort by price/name |
| Product Detail | Full product page with image, description, price (USD + RWF), star ratings, and reviews |
| Collections | Category pages (Fruits, Vegetables, Dairy, Beverages, Bakery, Snacks) |
| Flash Deals | Time-limited discounted products with countdown visibility |
| Shopping Cart | Session-based cart with add, remove, update quantity, real-time totals |
| Checkout | Two-step checkout: delivery details → Mobile Money payment |
| Mobile Money Payment | Paypack USSD push to MTN (078/079) or Airtel (073/072/075) numbers |
| Order Confirmation | Post-payment confirmation page with order summary |
| My Orders | Order history with status tracking |
| Wishlist | Save products for later, toggle via AJAX |
| Referral Program | Unique referral codes; refer a friend and earn loyalty points |
| Loyalty Points | Earn 1 point per $1 spent; viewable in account dropdown |
| Subscription Boxes | Weekly, biweekly, or monthly grocery box subscriptions |
| Vendor Shop | Register as a vendor to sell products on the marketplace |
| Product Reviews | Authenticated customers can rate and review products |
| Newsletter | Email and SMS newsletter subscription via footer form |
| Authentication | Register, login, logout with hashed passwords; optional 2FA via email OTP |

### Admin Features

| Feature | Description |
|---------|-------------|
| Dashboard | KPI cards: revenue, orders, customers, products; recent orders; low-stock alerts |
| Product CRUD | Add, edit, delete products with image upload (drag-and-drop or URL) |
| Collections | Create and manage product categories |
| Order Management | View all orders, update order status |
| Customer List | View all registered customers |
| Analytics | 30-day revenue chart, top products, most-viewed pages, orders by status, category revenue breakdown, wishlist stats, conversion rate |
| Vendor Management | Approve or suspend vendor applications; manage payout requests |
| Flash Deals | Create and manage time-limited flash deals with discount percentages |
| Security Log | View last 100 security events (logins, failed attempts, etc.) |
| Settings | Manage store name, hero banner, announcement bar, shipping, tax, Paypack credentials, social links, SEO, and maintenance mode |

---

## 5. Technologies Used

| Layer | Technology |
|-------|-----------|
| Backend Language | PHP 8.2 (no framework — pure PHP) |
| Database | MySQL 8.0 |
| Frontend | Vanilla HTML5, CSS3, JavaScript (no framework) |
| Charts | Chart.js 4 (CDN) |
| Payment Gateway | Paypack (Rwanda Mobile Money — MTN & Airtel) |
| Web Server | Apache 2 (inside Docker container) |
| Containerisation | Docker + Docker Compose |
| CI/CD | GitHub Actions |
| Email (SMTP) | PHPMailer-style SMTP via Gmail App Password |
| Version Control | Git + GitHub |
| Hosting | InfinityFree (via FTP deployment) |
| Local Development | XAMPP or Docker Compose |

---

## 6. System Architecture

### Overview

The system follows a simple monolithic PHP architecture — no MVC framework is used intentionally, keeping the codebase approachable and portable.

```
Browser (HTML/CSS/JS)
        │
        ▼
  Apache Web Server
        │
        ▼
  PHP 8.2 Pages ──────► functions.php (auth, cart, helpers, OTP, referral, vendor)
        │
        ├──► db.php (PDO connection, retry logic for Docker)
        │
        ├──► paypack.php (Paypack cURL API wrapper)
        │
        ├──► mailer.php (SMTP email sender)
        │
        └──► ajax/ (AJAX endpoint handlers — JSON responses)
                  ├── cart_add.php
                  ├── cart_remove.php
                  ├── cart_update.php
                  ├── paypack_initiate.php
                  ├── paypack_verify.php
                  ├── wishlist_toggle.php
                  ├── upload_image.php
                  └── newsletter.php
```

### Database Schema

The MySQL database (`freshmart`) contains the following tables:

| Table | Purpose |
|-------|---------|
| `profiles` | User accounts (customers + admins) |
| `ecom_collections` | Product categories |
| `ecom_products` | Product catalog |
| `ecom_product_collections` | Product ↔ category junction |
| `ecom_customers` | Order-level customer records |
| `ecom_orders` | Order headers |
| `ecom_order_items` | Order line items |
| `product_reviews` | Product ratings and comments |
| `shop_settings` | Key-value store configuration |
| `wishlists` | Saved products per user |
| `loyalty_points` | Points earned/redeemed per user |
| `flash_deals` | Time-limited discount deals |
| `subscription_boxes` | Subscription package definitions |
| `subscriptions` | User subscription records |
| `referrals` | Referrer-referred relationships |
| `vendors` | Vendor shop applications |
| `vendor_payouts` | Payout requests from vendors |
| `rate_limit` | IP-based request rate limiting |
| `security_log` | Auth and action audit trail |
| `otp_tokens` | 2FA and password-reset OTP codes |
| `product_affinity` | AI recommendation co-purchase scores |
| `page_views` | Analytics page view tracking |
| `csrf_tokens` | CSRF token storage |

### Key PHP Files

| File | Role |
|------|------|
| `layout.php` | Shared page wrapper — `startPage()` / `endPage()` functions |
| `functions.php` | All helpers: auth, cart, price formatting, settings, CSRF, OTP, loyalty, flash deals, referrals, AI recommendations |
| `db.php` | PDO singleton with Docker-aware retry loop |
| `config.php` | Production secrets (gitignored; template in `config.example.php`) |
| `paypack.php` | Paypack REST API client (auth token, cashin, status check) |
| `admin.php` | Full admin panel — single-file with tab-based routing |
| `checkout.php` | Two-step checkout with JavaScript polling for payment status |

### Payment Flow

```
Customer fills checkout form
        │
        ▼
JS calls /ajax/paypack_initiate.php (POST)
        │
        ▼
PHP: paypack_token() → paypack_cashin(phone, amountRWF)
        │
        ▼
Paypack API sends USSD push to customer's phone
        │
        ▼
JS polls /ajax/paypack_verify.php every 5 seconds (up to 3 min)
        │
        ▼
paypack_check(ref) returns: pending | successful | failed
        │
    ┌───┴───┐
 success  failure
    │        │
 Clear     Show
 cart,    error,
 save     allow
 order    retry
    │
    ▼
/order-confirmation.php
```

---

## 7. Screenshots

> Screenshots of the live running application are available at:
> **https://freshmartstore.gt.tc/**

Recommended pages to screenshot for submission:

1. **Homepage** — Hero banner, benefits bar, category grid, flash deals, featured products
2. **Shop page** — Product grid with search and filter
3. **Product detail page** — Image, price (USD + RWF), ratings, add to cart, recommendations
4. **Shopping cart** — Cart items, quantities, totals
5. **Checkout page** — Delivery form + Mobile Money payment step
6. **Order confirmation** — Post-payment summary
7. **Admin dashboard** — KPI cards, recent orders, low stock
8. **Admin analytics** — Revenue chart, top products, category breakdown
9. **Admin products** — Product table with edit/delete
10. **Admin settings** — Settings panel with toggles

---

## 8. GitHub Repository Link

**https://github.com/Archie-ctr/freshmart**

The repository contains:
- Full PHP source code
- `install.sql` database schema and seed data
- `Dockerfile` and `docker-compose.yml` for containerised deployment
- `.github/workflows/ci-cd.yml` for automated CI/CD
- `config.example.php` as a safe configuration template
- `README.md` with setup instructions

---

## 9. Deployment Link

**https://freshmartstore.gt.tc/**

The application is deployed on InfinityFree hosting. Files are deployed via FTP using the GitHub Actions CI/CD pipeline on every push to the `main` branch.

Admin access:
- Email: `admin@freshmart.com`
- Password: `admin123`

---

## 10. CI/CD Description

The CI/CD pipeline is defined in `.github/workflows/ci-cd.yml` and runs automatically on every push or pull request to the `main` / `master` branch.

### Pipeline Stages

```
Push to main/master
        │
        ▼
 Job 1: PHP Lint
   └── php -l on all .php files (syntax check)
        │
        ▼ (on success)
 Job 2: Docker Build
   └── docker build -t freshmart:<sha> .
        │
        ▼ (on success, push to main only)
 Job 3: Deploy to InfinityFree
   └── FTP upload via SamKirkland/FTP-Deploy-Action
       (excludes: .git, node_modules, Docker files, config.php)
```

### GitHub Secrets Required

| Secret | Purpose |
|--------|---------|
| `FTP_PASSWORD` | InfinityFree FTP password for deployment |

### What gets deployed

All PHP source files, assets (CSS, JS), SQL schema, and uploads are transferred via FTP to the `/htdocs/` directory on InfinityFree. Sensitive files (`config.php`, Docker files, `.git/`) are excluded from the transfer.

---

## 11. Challenges Encountered

1. **Docker MySQL startup timing** — On `docker compose up`, the PHP container would start before MySQL was ready, causing connection failures. Resolved by implementing a retry loop in `db.php` that attempts to connect up to 10 times with 2-second intervals before giving up.

2. **Paypack USSD polling** — The payment is asynchronous (the customer approves on their phone). The frontend needed a reliable polling mechanism. Implemented a JavaScript interval that polls `/ajax/paypack_verify.php` every 5 seconds for up to 3 minutes (36 polls) before timing out and showing a failure state.

3. **Dual-currency pricing** — All prices are stored in USD cents (integer) in the database but must display in both USD and RWF. The exchange rate is configurable via Admin Settings and applied dynamically at render time using the `formatPrice()` helper, meaning no migration is needed when the rate changes.

4. **Shared config for multiple environments** — The same codebase runs on XAMPP (localhost), Docker, and InfinityFree (production), each with different database credentials and base URLs. Solved by having `db.php` read from environment variables, `config.php` (gitignored), or sensible defaults — in that priority order.

5. **Image uploads on shared hosting** — InfinityFree doesn't support Docker, so the drag-and-drop image uploader (`ajax/upload_image.php`) needed to write to `uploads/products/` with proper directory permissions. A fallback to image-URL input was also provided.

6. **CSRF and rate limiting without a framework** — Implementing CSRF protection and rate limiting from scratch in plain PHP required storing tokens in sessions and rate-limit records in the database. The `rate_limit` table is periodically cleaned of expired entries on each request to keep it lightweight.

---

## 12. Future Work

1. **SMS Notifications** — Integrate Africa's Talking or a similar SMS gateway to send order confirmation and delivery update SMSes to customers' phones.

2. **Real-time Order Tracking** — Add a map-based delivery tracking page using Google Maps or OpenStreetMap, with delivery driver location updates via WebSockets or long-polling.

3. **Multi-language Support** — Add French and Kinyarwanda language options given the Rwandan target market, using a simple key-value translation file system.

4. **Progressive Web App (PWA)** — Add a `manifest.json` and service worker so customers can install FreshMart on their phone's home screen and browse offline-cached product catalogs.

5. **Inventory Webhooks** — Emit webhooks when stock drops below a threshold so vendors can be automatically notified to restock.

6. **Full Vendor Marketplace Activation** — The vendor data model and UI are already built; the next step is enabling vendor-specific product pages, vendor dashboards, and automated commission payout calculations.

7. **AI Recommendations Enhancement** — The `product_affinity` matrix and collaborative filtering are already implemented; these could be enhanced with a proper recommendation engine or integrated with an AWS Personalize service for higher-quality suggestions.

8. **Mobile App** — Build a React Native or Flutter app that consumes a REST API layer on top of the existing PHP backend.

---

## 13. Conclusion

FreshMart demonstrates a complete, production-deployed e-commerce application built from first principles in PHP 8.2. The project covers the full software development lifecycle — from requirement gathering and database design, through feature implementation and security hardening, to containerised deployment and automated CI/CD.

Key accomplishments include:
- A working Mobile Money payment integration (Paypack) that handles the full USSD push flow asynchronously
- A comprehensive admin panel with analytics, order management, and configurable shop settings
- Advanced e-commerce features (flash deals, wishlists, loyalty points, referrals, subscription boxes) that go beyond a basic CRUD application
- A Docker-based development environment and GitHub Actions pipeline that enforces code quality and automates deployment on every push
- A live, publicly accessible deployment at https://freshmartstore.gt.tc/

The project provided practical experience with PHP session management, PDO database access, REST API consumption, asynchronous JavaScript (fetch + polling), Docker networking, and CI/CD configuration — skills directly applicable to professional web development.

---

*Report prepared by: Archie K Gonwoe Jr — Registration No. 23686/2024*
