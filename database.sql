-- ============================================================
-- E-MART DATABASE SCHEMA
-- Run this in phpMyAdmin or MySQL CLI: mysql -u root -p < database.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS my_website_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE my_website_db;

-- ============================================================
-- TABLE: users
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(100)        NOT NULL,
    email       VARCHAR(150)        NOT NULL UNIQUE,
    mobile      VARCHAR(15)         NOT NULL,
    password    VARCHAR(255)        NOT NULL,       -- bcrypt hash
    role        ENUM('user','admin') DEFAULT 'user',
    otp         VARCHAR(10)         DEFAULT NULL,   -- for forgot-password
    otp_expiry  DATETIME            DEFAULT NULL,
    created_at  TIMESTAMP           DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Seed admin account (password: Admin@123)
INSERT INTO users (username, email, mobile, password, role) VALUES
('Admin', 'admin@emart.com', '9000000000',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- ============================================================
-- TABLE: categories
-- ============================================================
CREATE TABLE IF NOT EXISTS categories (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

INSERT INTO categories (category_name) VALUES
('Electronics'),('Clothing'),('Books'),('Home & Kitchen'),
('Sports'),('Grocery'),('Toys'),('Beauty');

-- ============================================================
-- TABLE: products
-- ============================================================
CREATE TABLE IF NOT EXISTS products (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(200)   NOT NULL,
    description TEXT,
    price       DECIMAL(10,2)  NOT NULL,
    category_id INT            NOT NULL,
    image       VARCHAR(300)   DEFAULT 'default.jpg',
    stock       INT            DEFAULT 0,
    is_featured TINYINT(1)     DEFAULT 0,
    created_at  TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: cart
-- ============================================================
CREATE TABLE IF NOT EXISTS cart (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    product_id  INT NOT NULL,
    quantity    INT NOT NULL DEFAULT 1,
    UNIQUE KEY unique_cart (user_id, product_id),
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: orders
-- ============================================================
CREATE TABLE IF NOT EXISTS orders (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT            NOT NULL,
    total_amount DECIMAL(10,2)  NOT NULL,
    status       ENUM('Pending','Processing','Shipped','Delivered','Cancelled')
                               DEFAULT 'Pending',
    address      TEXT           NOT NULL,
    coupon_code  VARCHAR(50)    DEFAULT NULL,
    discount     DECIMAL(10,2)  DEFAULT 0.00,
    created_at   TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: order_items
-- ============================================================
CREATE TABLE IF NOT EXISTS order_items (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    order_id    INT            NOT NULL,
    product_id  INT            NOT NULL,
    quantity    INT            NOT NULL,
    price       DECIMAL(10,2)  NOT NULL,
    FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: reviews
-- ============================================================
CREATE TABLE IF NOT EXISTS reviews (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT  NOT NULL,
    product_id  INT  NOT NULL,
    rating      TINYINT(1) NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment     TEXT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_review (user_id, product_id),
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: wishlist
-- ============================================================
CREATE TABLE IF NOT EXISTS wishlist (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    product_id  INT NOT NULL,
    UNIQUE KEY unique_wishlist (user_id, product_id),
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: coupons
-- ============================================================
CREATE TABLE IF NOT EXISTS coupons (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    code            VARCHAR(50)    NOT NULL UNIQUE,
    discount_type   ENUM('percent','fixed') DEFAULT 'percent',
    discount_value  DECIMAL(10,2)  NOT NULL,
    min_order       DECIMAL(10,2)  DEFAULT 0,
    max_uses        INT            DEFAULT 100,
    used_count      INT            DEFAULT 0,
    expiry_date     DATE           NOT NULL,
    is_active       TINYINT(1)     DEFAULT 1
) ENGINE=InnoDB;

INSERT INTO coupons (code, discount_type, discount_value, min_order, expiry_date) VALUES
('SAVE10', 'percent', 10, 500, '2026-12-31'),
('FLAT100', 'fixed', 100, 999, '2026-12-31'),
('WELCOME20', 'percent', 20, 0, '2026-12-31');
