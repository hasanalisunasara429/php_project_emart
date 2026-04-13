<?php
// includes/header.php — shared top navigation for all user pages
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/connection.php';
$cartCount = getCartCount($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?> — <?= $pageTitle ?? 'Shop Online' ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<!-- ===== TOP NAV BAR ===== -->
<nav class="navbar">
    <div class="nav-brand">
        <a href="<?= BASE_URL ?>index.php">
            <span class="brand-logo">🛒</span>
            <span class="brand-name">E-Mart</span>
        </a>
    </div>

    <!-- Live Search Box -->
    <div class="search-wrapper">
        <input type="text" id="searchInput" placeholder="Search for products, brands and more…"
               autocomplete="off">
        <button class="search-btn"><i class="fas fa-search"></i></button>
        <div id="searchResults" class="search-dropdown"></div>
    </div>

    <!-- Nav Links -->
    <div class="nav-links">
        <?php if (isLoggedIn()): ?>
            <a href="<?= BASE_URL ?>user/wishlist.php" class="nav-icon" title="Wishlist">
                <i class="fas fa-heart"></i>
            </a>
            <a href="<?= BASE_URL ?>user/cart.php" class="nav-icon cart-icon" title="Cart">
                <i class="fas fa-shopping-cart"></i>
                <span class="cart-badge" id="cartBadge"><?= $cartCount ?></span>
            </a>
            <div class="nav-dropdown">
                <button class="nav-user-btn">
                    <i class="fas fa-user-circle"></i>
                    <?= sanitize($_SESSION['username']) ?>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="dropdown-menu">
                    <a href="<?= BASE_URL ?>user/orders.php"><i class="fas fa-box"></i> My Orders</a>
                    <a href="<?= BASE_URL ?>user/profile.php"><i class="fas fa-user"></i> Profile</a>
                    <a href="<?= BASE_URL ?>user/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        <?php else: ?>
            <a href="<?= BASE_URL ?>user/login.php" class="btn btn-outline">Login</a>
            <a href="<?= BASE_URL ?>user/register.php" class="btn btn-primary">Sign Up</a>
        <?php endif; ?>
    </div>
</nav>

<!-- ===== CATEGORY BAR ===== -->
<div class="category-bar">
    <?php
    $cats = $conn->query("SELECT * FROM categories LIMIT 8");
    while ($c = $cats->fetch_assoc()):
    ?>
        <a href="<?= BASE_URL ?>index.php?category=<?= $c['id'] ?>"
           class="cat-link"><?= sanitize($c['category_name']) ?></a>
    <?php endwhile; ?>
</div>

<!-- Flash Message -->
<?php $flash = getFlash(); if ($flash): ?>
<div class="flash flash-<?= $flash['type'] ?>" id="flashMsg">
    <?= sanitize($flash['message']) ?>
    <span class="flash-close" onclick="this.parentElement.remove()">✕</span>
</div>
<?php endif; ?>

<script src="<?= BASE_URL ?>assets/js/main.js" defer></script>
