# 🛒 E-Mart — Complete PHP E-Commerce Website

## ✅ Tech Stack
- **Backend**: Core PHP (no frameworks), MySQLi with prepared statements
- **Frontend**: HTML5, CSS3, JavaScript (Vanilla), AJAX (Fetch API)
- **Database**: MySQL 5.7+
- **Server**: XAMPP / WAMP (Apache + PHP 8.0+)
- **Email**: PHPMailer (SMTP)
- **Charts**: Chart.js (admin dashboard)

---

## 📁 Folder Structure

```
emart/
├── index.php                 ← Homepage (products + search + filters)
├── database.sql              ← Full DB schema + seed data
│
├── includes/
│   ├── connection.php        ← DB connection + utility functions
│   ├── header.php            ← Shared top nav & category bar
│   ├── footer.php            ← Shared footer
│   └── mailer.php            ← PHPMailer email helper
│
├── user/
│   ├── register.php          ← User registration
│   ├── login.php             ← User login
│   ├── logout.php            ← Session destroy
│   ├── forgot_password.php   ← OTP-based password reset
│   ├── profile.php           ← View & edit profile + change password
│   ├── viewproduct.php       ← Product detail + reviews
│   ├── cart.php              ← Cart page (AJAX update/remove)
│   ├── checkout.php          ← Checkout + place order
│   ├── orders.php            ← Order history + tracking
│   └── wishlist.php          ← Wishlist page
│
├── admin/
│   ├── login.php             ← Admin-only login
│   ├── dashboard.php         ← KPI cards + charts + recent orders
│   ├── products.php          ← Add/Edit/Delete products + image upload
│   ├── categories.php        ← Manage categories
│   ├── orders.php            ← View & update order status
│   ├── users.php             ← List all registered users
│   ├── coupons.php           ← Create/manage discount coupons
│   └── includes/
│       ├── admin_header.php  ← Admin sidebar + topbar
│       └── admin_footer.php  ← Close tags
│
├── api/
│   ├── cart.php              ← AJAX: add/update/remove/get cart
│   ├── search.php            ← AJAX: live product search
│   ├── wishlist.php          ← AJAX: toggle wishlist
│   └── coupon.php            ← AJAX: validate coupon codes
│
└── assets/
    ├── css/
    │   ├── style.css         ← Full frontend stylesheet (Amazon-like)
    │   └── admin.css         ← Admin panel stylesheet
    ├── js/
    │   ├── main.js           ← AJAX cart, live search, slider, toasts
    │   └── admin.js          ← Admin panel JS
    └── images/
        ├── default.jpg       ← Fallback product image
        └── products/         ← Uploaded product images go here
```

---

## 🚀 Setup Instructions

### Step 1: Environment
1. Download and install **XAMPP** from https://apachefriends.org
2. Start **Apache** and **MySQL** from XAMPP control panel

### Step 2: Place Files
```
Copy the entire `emart/` folder to:
C:\xampp\htdocs\emart\          (Windows)
/Applications/XAMPP/htdocs/emart/  (macOS)
```

### Step 3: Database
1. Open browser → http://localhost/phpmyadmin
2. Click **Import** tab
3. Select `emart/database.sql` and click **Go**
4. Database `my_website_db` will be created with all tables + seed data

### Step 4: Configure Connection
Edit `includes/connection.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');    // your MySQL username
define('DB_PASS', '');        // your MySQL password
define('BASE_URL', 'http://localhost/emart/');
```

### Step 5: PHPMailer (Optional — for OTP email)
```
Option A (Composer):
  cd emart/includes
  composer require phpmailer/phpmailer

Option B (Manual):
  Download from github.com/PHPMailer/PHPMailer
  Place in emart/includes/PHPMailer/
```
Then update SMTP settings in `includes/connection.php`.

### Step 6: Image Upload Permissions
Make sure `assets/images/products/` is writable:
- Windows XAMPP: usually writable by default
- Linux: `chmod 755 assets/images/products/`

### Step 7: Access the site
- **Store frontend**: http://localhost/emart/
- **Admin panel**: http://localhost/emart/admin/login.php

---

## 🔐 Default Login Credentials

| Role  | Email             | Password  |
|-------|-------------------|-----------|
| Admin | admin@emart.com   | Admin@123 |
| User  | Register yourself | —         |

> **Important**: Change admin password immediately in production!

---

## 🎯 Features Implemented

### User Side
- ✅ Registration / Login / Logout with sessions
- ✅ Password hashing (bcrypt, cost=12)
- ✅ Forgot password with OTP email (PHPMailer)
- ✅ Product listing with pagination (12/page)
- ✅ Live search via AJAX (debounced, 280ms)
- ✅ Category filtering
- ✅ Product detail page with image + reviews
- ✅ Star ratings (1-5) with average display
- ✅ Cart: Add / Update quantity / Remove via AJAX
- ✅ Cart stored in database (user-based)
- ✅ Wishlist: Add / Remove via AJAX
- ✅ Coupon validation via AJAX
- ✅ Checkout with address + coupon
- ✅ Order placement with stock reduction (transaction-safe)
- ✅ Email confirmation after order
- ✅ Order history + tracking (4-step tracker)
- ✅ User profile: update name, mobile, password

### Admin Side
- ✅ Admin-only login (role check)
- ✅ Dashboard: 6 KPI cards + revenue chart + top products chart
- ✅ Product CRUD: add/edit/delete + image upload (2MB limit)
- ✅ Stock management + low-stock highlighting
- ✅ Category CRUD with product count
- ✅ Order management: view details, update status
- ✅ User list with order count and total spend
- ✅ Coupon management: create/edit/toggle/delete

### Security
- ✅ All DB queries use prepared statements (no SQL injection)
- ✅ XSS protection via `htmlspecialchars()` on all output
- ✅ Session fixation prevention (regenerate_id after login)
- ✅ Role-based access control (requireLogin / requireAdmin)
- ✅ CSRF protection via session (extend with CSRF token for production)
- ✅ Password hashing: bcrypt with cost factor 12

---

## 📦 API Endpoints (AJAX)

| Endpoint          | Method | Actions                        |
|-------------------|--------|--------------------------------|
| api/cart.php      | POST   | add, update, remove, get       |
| api/search.php    | GET    | Live product search (?q=)      |
| api/wishlist.php  | POST   | Toggle wishlist item           |
| api/coupon.php    | POST   | Validate & calculate discount  |

All return JSON: `{ success: true/false, message: "...", ...data }`

---

## 🛡️ Production Checklist
- [ ] Change admin password
- [ ] Set `display_errors = Off` in php.ini
- [ ] Use environment variables for DB credentials
- [ ] Enable HTTPS (SSL certificate)
- [ ] Add CSRF token to all forms
- [ ] Rate-limit login attempts
- [ ] Add real payment gateway (Razorpay/Stripe)
- [ ] Set up email SMTP with App Password
- [ ] Regular database backups

---

## 🎨 Coupon Codes (Pre-seeded)
| Code      | Type    | Value | Min Order |
|-----------|---------|-------|-----------|
| SAVE10    | percent | 10%   | ₹500      |
| FLAT100   | fixed   | ₹100  | ₹999      |
| WELCOME20 | percent | 20%   | None      |

---

Built with ❤️ — Core PHP, MySQL, HTML, CSS, JavaScript, AJAX
