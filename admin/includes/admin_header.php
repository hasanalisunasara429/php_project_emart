<?php
// admin/includes/admin_header.php — Admin panel header & sidebar
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../includes/connection.php';
requireAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — <?= $pageTitle ?? 'Dashboard' ?> | <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="admin-body">

<!-- Sidebar -->
<aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-brand">
        <span>🛒</span> E-Mart Admin
    </div>
    <nav class="sidebar-nav">
        <a href="<?= BASE_URL ?>admin/dashboard.php"
           class="<?= basename($_SERVER['PHP_SELF'])==='dashboard.php'?'active':'' ?>">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
        <a href="<?= BASE_URL ?>admin/products.php"
           class="<?= basename($_SERVER['PHP_SELF'])==='products.php'?'active':'' ?>">
            <i class="fas fa-box"></i> Products
        </a>
        <a href="<?= BASE_URL ?>admin/categories.php"
           class="<?= basename($_SERVER['PHP_SELF'])==='categories.php'?'active':'' ?>">
            <i class="fas fa-tags"></i> Categories
        </a>
        <a href="<?= BASE_URL ?>admin/orders.php"
           class="<?= basename($_SERVER['PHP_SELF'])==='orders.php'?'active':'' ?>">
            <i class="fas fa-shopping-bag"></i> Orders
        </a>
        <a href="<?= BASE_URL ?>admin/users.php"
           class="<?= basename($_SERVER['PHP_SELF'])==='users.php'?'active':'' ?>">
            <i class="fas fa-users"></i> Users
        </a>
        <a href="<?= BASE_URL ?>admin/coupons.php"
           class="<?= basename($_SERVER['PHP_SELF'])==='coupons.php'?'active':'' ?>">
            <i class="fas fa-percent"></i> Coupons
        </a>
        <div class="sidebar-divider"></div>
        <a href="<?= BASE_URL ?>index.php" target="_blank">
            <i class="fas fa-store"></i> View Store
        </a>
        <a href="<?= BASE_URL ?>user/logout.php">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>
</aside>

<!-- Main content wrapper -->
<div class="admin-main">

    <!-- Top bar -->
    <header class="admin-topbar">
        <button class="sidebar-toggle" onclick="document.getElementById('adminSidebar').classList.toggle('collapsed')">
            <i class="fas fa-bars"></i>
        </button>
        <div class="topbar-title"><?= $pageTitle ?? 'Dashboard' ?></div>
        <div class="topbar-user">
            <i class="fas fa-user-circle"></i>
            <?= sanitize($_SESSION['username']) ?>
        </div>
    </header>

    <div class="admin-content">

    <!-- Flash Message -->
    <?php $flash = getFlash(); if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?>" id="adminFlash">
        <?= sanitize($flash['message']) ?>
        <button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;float:right">✕</button>
    </div>
    <?php endif; ?>
