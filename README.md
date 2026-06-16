# 🌿 FreshMart — Online Grocery Store

A full-featured e-commerce web application built with **PHP 8.2**, **MySQL**, and **Docker**.  
FreshMart lets customers browse fresh groceries, manage a cart, and pay via **MTN / Airtel Mobile Money** (Paypack).

---

## 🚀 Live Deployment

> **URL:** https://freshmartstore.gt.tc/

> **GitHub:** https://github.com/Archie-ctr

---

## 📋 Features

| Area | Features |
|---|---|
| **UI** | Responsive design, mobile-friendly, hero banner, navigation, footer |
| **Products** | Listing, detail page, categories/collections, search, sort, ratings |
| **Cart** | Add/remove items, update quantities, real-time totals (USD + RWF) |
| **Checkout** | Customer details form, order summary, Mobile Money payment (USSD push) |
| **Orders** | Order confirmation page, "My Orders" history |
| **Admin** | Dashboard, product CRUD, collections, order management, settings |
| **Auth** | Register, login, logout, role-based access (admin/customer) |
| **Payment** | Paypack integration — MTN MoMo (078/079) & Airtel Money (072/073/075) |

---

## 🛠 Technologies Used

- **Backend:** PHP 8.2 (no framework)
- **Database:** MySQL 8.0
- **Frontend:** Vanilla HTML/CSS/JS (no framework)
- **Payment:** Paypack (Rwanda Mobile Money)
- **Container:** Docker + Docker Compose
- **CI/CD:** GitHub Actions
- **Web Server:** Apache (inside Docker)

---

## 🐳 Docker Setup (Local Development)

### Prerequisites
- [Docker Desktop](https://www.docker.com/products/docker-desktop/)

### Run with Docker Compose

```bash
# 1. Clone the repository
git clone https://github.com/Archie-ctr/freshmart.git
cd freshmart

# 2. Start all services (app + MySQL + phpMyAdmin)
docker compose up -d

# 3. Open the app
open http://localhost:8080/store-php/
```

| Service | URL |
|---|---|
| App | http://localhost:8080/store-php/ |
| phpMyAdmin | http://localhost:8081 (dev profile only) |

To start with phpMyAdmin:
```bash
docker compose --profile dev up -d
```

### Stop & clean up
```bash
docker compose down -v
```

---

## ⚙️ CI/CD Pipeline (GitHub Actions)

The pipeline in `.github/workflows/ci-cd.yml` runs automatically on every push to `main`/`master`:

```
Push to main
    │
    ├─► Job 1: PHP Lint
    │       └── php -l on all .php files
    │
    ├─► Job 2: Docker Build & Smoke Test
    │       ├── docker build
    │       ├── docker compose up
    │       ├── curl health check (HTTP 200)
    │       └── Check all key pages respond
    │
    └─► Job 3: Push & Deploy  (main branch only)
            ├── Push image to Docker Hub
            └── SSH deploy to production server
```

### Required GitHub Secrets

| Secret | Description |
|---|---|
| `DOCKERHUB_USERNAME` | Your Docker Hub username |
| `DOCKERHUB_TOKEN` | Docker Hub access token |
| `DEPLOY_HOST` | Production server IP/hostname (optional) |
| `DEPLOY_USER` | SSH username on production server (optional) |
| `DEPLOY_KEY` | SSH private key for deployment (optional) |

> The deploy job is skipped if `DEPLOY_HOST` is not set.

---

## 🗄️ Database

The database schema and seed data are in **`install.sql`**.

It creates:
- `profiles` — user accounts
- `ecom_collections` — product categories
- `ecom_products` — product catalog
- `ecom_product_collections` — product ↔ category junction
- `ecom_customers` — order-level customer records
- `ecom_orders` — order headers
- `ecom_order_items` — order line items
- `product_reviews` — product ratings & reviews
- `shop_settings` — key-value store settings

### Import manually
```bash
mysql -u root freshmart < install.sql
```

---

## 🔐 Admin Access

| Email | Password |
|---|---|
| admin@freshmart.com | admin123 |

---

## 📁 Project Structure

```
store-php/
├── .github/workflows/ci-cd.yml   # CI/CD pipeline
├── ajax/                          # AJAX endpoint handlers
│   ├── cart_add.php
│   ├── cart_remove.php
│   ├── cart_update.php
│   ├── paypack_initiate.php
│   └── paypack_verify.php
├── assets/                        # CSS & JS
│   ├── style.css
│   ├── admin.css
│   └── app.js
├── docker/                        # Docker config
│   ├── apache.conf
│   └── php.ini
├── uploads/products/              # Uploaded product images
├── admin.php                      # Admin dashboard
├── cart.php                       # Shopping cart
├── checkout.php                   # Checkout + payment
├── collection.php                 # Category page
├── config.example.php             # Config template (copy → config.php)
├── db.php                         # PDO connection
├── docker-compose.yml             # Multi-service Docker setup
├── Dockerfile                     # PHP 8.2 + Apache image
├── functions.php                  # Helpers (auth, cart, price)
├── index.php                      # Homepage
├── install.sql                    # Database schema + seed data
├── layout.php                     # Shared header/footer
├── login.php                      # Sign in
├── logout.php                     # Sign out
├── order-confirmation.php         # Post-payment confirmation
├── orders.php                     # My orders history
├── paypack.php                    # Paypack API integration
├── product.php                    # Product detail + reviews
├── register.php                   # Account creation
└── shop.php                       # Product listing + search
```

---

## 📸 Screenshots

> Add screenshots of your running application here.

---

## 📄 License

MIT — free to use for educational purposes.
