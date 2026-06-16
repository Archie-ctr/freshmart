-- FreshMart MySQL Schema
-- Run this in phpMyAdmin or: mysql -u root freshmart < install.sql

-- Users / Profiles
CREATE TABLE IF NOT EXISTS profiles (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email       VARCHAR(255) NOT NULL UNIQUE,
    full_name   VARCHAR(255) NOT NULL DEFAULT '',
    password    VARCHAR(255) NOT NULL,
    role        ENUM('customer','admin') NOT NULL DEFAULT 'customer',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Collections (categories)
CREATE TABLE IF NOT EXISTS ecom_collections (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(255) NOT NULL,
    handle      VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    is_visible  TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Products
CREATE TABLE IF NOT EXISTS ecom_products (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(255) NOT NULL,
    handle         VARCHAR(255) NOT NULL UNIQUE,
    description    TEXT,
    price          INT NOT NULL DEFAULT 0 COMMENT 'Price in cents',
    images         TEXT COMMENT 'JSON array of image URLs',
    product_type   VARCHAR(100),
    sku            VARCHAR(100),
    status         ENUM('active','inactive') NOT NULL DEFAULT 'active',
    inventory_qty  INT NOT NULL DEFAULT 0,
    has_variants   TINYINT(1) NOT NULL DEFAULT 0,
    tags           TEXT COMMENT 'JSON array of tags',
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Product ↔ Collection junction
CREATE TABLE IF NOT EXISTS ecom_product_collections (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id     INT UNSIGNED NOT NULL,
    collection_id  INT UNSIGNED NOT NULL,
    position       INT NOT NULL DEFAULT 0,
    UNIQUE KEY uq_pc (product_id, collection_id),
    FOREIGN KEY (product_id)    REFERENCES ecom_products(id)    ON DELETE CASCADE,
    FOREIGN KEY (collection_id) REFERENCES ecom_collections(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Customers (order-level, separate from auth profiles)
CREATE TABLE IF NOT EXISTS ecom_customers (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(255) NOT NULL UNIQUE,
    name       VARCHAR(255),
    phone      VARCHAR(50),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Orders
CREATE TABLE IF NOT EXISTS ecom_orders (
    id                       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id              INT UNSIGNED,
    status                   VARCHAR(50) NOT NULL DEFAULT 'paid',
    subtotal                 INT NOT NULL DEFAULT 0,
    tax                      INT NOT NULL DEFAULT 0,
    shipping                 INT NOT NULL DEFAULT 0,
    total                    INT NOT NULL DEFAULT 0,
    shipping_address         TEXT COMMENT 'JSON object',
    stripe_payment_intent_id VARCHAR(255),
    created_at               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES ecom_customers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Order Items
CREATE TABLE IF NOT EXISTS ecom_order_items (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id      INT UNSIGNED NOT NULL,
    product_id    INT UNSIGNED,
    variant_id    INT UNSIGNED,
    product_name  VARCHAR(255) NOT NULL,
    variant_title VARCHAR(255),
    sku           VARCHAR(100),
    quantity      INT NOT NULL DEFAULT 1,
    unit_price    INT NOT NULL DEFAULT 0,
    total         INT NOT NULL DEFAULT 0,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES ecom_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Product Reviews
CREATE TABLE IF NOT EXISTS product_reviews (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id  INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NOT NULL,
    author_name VARCHAR(255),
    rating      TINYINT NOT NULL DEFAULT 5,
    comment     TEXT,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_review (product_id, user_id),
    FOREIGN KEY (product_id) REFERENCES ecom_products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)    REFERENCES profiles(id)      ON DELETE CASCADE
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- Seed: Admin user  (password: admin123)
-- -------------------------------------------------------
INSERT IGNORE INTO profiles (email, full_name, password, role) VALUES
('admin@freshmart.com', 'Admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Seed: Collections
INSERT IGNORE INTO ecom_collections (title, handle, description, is_visible) VALUES
('Fruits',     'fruits',     'Fresh seasonal fruits',           1),
('Vegetables', 'vegetables', 'Farm-fresh vegetables',           1),
('Dairy',      'dairy',      'Milk, cheese, yogurt & more',     1),
('Beverages',  'beverages',  'Juices, water & soft drinks',     1),
('Bakery',     'bakery',     'Breads, pastries & baked goods',  1),
('Snacks',     'snacks',     'Chips, nuts & healthy snacks',    1);

-- Seed: Products
INSERT IGNORE INTO ecom_products (name, handle, description, price, images, product_type, status, inventory_qty, tags) VALUES
('Organic Bananas',      'organic-bananas',      'Sweet, ripe organic bananas packed with potassium.',           149, '["https://images.unsplash.com/photo-1571771894821-ce9b6c11b08e?w=600"]', 'Fruits',     'active', 50, '["featured"]'),
('Red Apples',           'red-apples',           'Crisp and delicious red apples, perfect for snacking.',         199, '["https://images.unsplash.com/photo-1560806887-1e4cd0b6cbd6?w=600"]', 'Fruits',     'active', 40, '["featured"]'),
('Fresh Strawberries',   'fresh-strawberries',   'Sun-ripened strawberries bursting with natural sweetness.',     349, '["https://images.unsplash.com/photo-1464965911861-746a04b4bca6?w=600"]', 'Fruits',     'active', 30, '["featured"]'),
('Avocados (Pack of 3)', 'avocados-pack-3',      'Creamy Hass avocados, ready to eat.',                           499, '["https://images.unsplash.com/photo-1519162808019-7de1683fa2ad?w=600"]', 'Fruits',     'active', 25, '[]'),
('Broccoli',             'broccoli',             'Fresh-cut broccoli crowns, great for steaming or roasting.',    229, '["https://images.unsplash.com/photo-1584270354949-c26b0d5b4a0c?w=600"]', 'Vegetables', 'active', 60, '["featured"]'),
('Baby Spinach',         'baby-spinach',         'Tender baby spinach leaves, washed and ready to eat.',          299, '["https://images.unsplash.com/photo-1576045057995-568f588f82fb?w=600"]', 'Vegetables', 'active', 45, '["featured"]'),
('Cherry Tomatoes',      'cherry-tomatoes',      'Sweet and juicy cherry tomatoes, perfect for salads.',          249, '["https://images.unsplash.com/photo-1607305387299-a3d9611cd469?w=600"]', 'Vegetables', 'active', 55, '[]'),
('Carrots (1 lb)',       'carrots-1lb',          'Crunchy fresh carrots, great raw or cooked.',                   179, '["https://images.unsplash.com/photo-1447175008436-054170c2e979?w=600"]', 'Vegetables', 'active', 70, '[]'),
('Whole Milk (1 gal)',   'whole-milk-1gal',      'Farm-fresh whole milk, creamy and nutritious.',                 499, '["https://images.unsplash.com/photo-1563636619-e9143da7973b?w=600"]', 'Dairy',      'active', 30, '["featured"]'),
('Greek Yogurt',         'greek-yogurt',         'Thick and creamy plain Greek yogurt, high in protein.',         349, '["https://images.unsplash.com/photo-1488477181946-6428a0291777?w=600"]', 'Dairy',      'active', 40, '["featured"]'),
('Orange Juice (52oz)',  'orange-juice-52oz',    'Fresh-squeezed style orange juice, no added sugar.',            449, '["https://images.unsplash.com/photo-1621506289937-a8e4df240d0b?w=600"]', 'Beverages',  'active', 35, '["featured"]'),
('Sparkling Water 6pk',  'sparkling-water-6pk',  'Refreshing unflavored sparkling water, 6-pack.',                399, '["https://images.unsplash.com/photo-1559839914-17aae19cec71?w=600"]', 'Beverages',  'active', 50, '[]'),
('Sourdough Bread',      'sourdough-bread',      'Artisan sourdough bread baked fresh daily.',                    599, '["https://images.unsplash.com/photo-1509440159596-0249088772ff?w=600"]', 'Bakery',     'active', 20, '["featured"]'),
('Trail Mix',            'trail-mix',            'Healthy mix of nuts, seeds and dried fruits.',                  449, '["https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=600"]', 'Snacks',     'active', 60, '[]');

-- Link products to collections
INSERT IGNORE INTO ecom_product_collections (product_id, collection_id, position)
SELECT p.id, c.id, 0
FROM ecom_products p
JOIN ecom_collections c ON c.title = p.product_type;

-- ── Shop Settings ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS shop_settings (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) NOT NULL UNIQUE,
  setting_val TEXT,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO shop_settings (setting_key, setting_val) VALUES
('store_name','FreshMart'),('store_tagline','Fresh groceries delivered to your door'),
('store_email','hello@freshmart.com'),('store_phone','+250 780 000 000'),
('store_address','Kigali, Rwanda'),('usd_to_rwf_rate','1400'),
('hero_title','Fresh Groceries, Delivered Fast'),
('hero_subtitle','Shop quality fruits, vegetables, dairy and more — delivered straight to your door with free shipping.'),
('hero_image_url','https://d64gsuwffb70l.cloudfront.net/6a2866e45ccfcbde90098277_1781032877059_964d5e34.jpg'),
('hero_btn1_text','Shop Now'),('hero_btn1_url','/shop.php'),
('hero_btn2_text','Browse Fruits'),('hero_btn2_url','/collection.php?handle=fruits'),
('announcement_text','Free shipping on all orders — Fresh groceries delivered to your door'),
('announcement_show','1'),('shipping_free','1'),('shipping_flat_rwf','0'),
('min_order_rwf','100'),('tax_enabled','1'),('maintenance_mode','0'),
('maintenance_msg','We are currently undergoing maintenance. Please check back soon.'),
('facebook_url',''),('instagram_url',''),('twitter_url',''),
('footer_about','Fresh groceries delivered to your door. Quality you can trust, prices you will love.'),
('meta_description','FreshMart — Fresh groceries delivered fast in Rwanda'),
('google_analytics',''),
('paypack_app_id','e1121f46-68eb-11f1-9d8c-deadd43720af'),
('paypack_app_secret','497bed007d31a80fa92151a870598c50da39a3ee5e6b4b0d3255bfef95601890afd80709'),
('paypack_enabled','1');
