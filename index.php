<?php
// index.php — E-Mart Homepage
session_start();
require_once 'includes/connection.php';

$pageTitle = 'Shop Online — Best Deals';

// --- Build product query with optional filters ---
$where    = ['p.stock > 0'];
$params   = [];
$types    = '';

if (!empty($_GET['category'])) {
    $where[]  = 'p.category_id = ?';
    $params[] = (int)$_GET['category'];
    $types   .= 'i';
}
if (!empty($_GET['search'])) {
    $where[]  = '(p.name LIKE ? OR c.category_name LIKE ?)';
    $kw       = '%' . $conn->real_escape_string($_GET['search']) . '%';
    $params[] = $kw;
    $params[] = $kw;
    $types   .= 'ss';
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);

// Pagination
$perPage  = 12;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $perPage;

// Count total
$countSQL = "SELECT COUNT(*) FROM products p
             JOIN categories c ON p.category_id = c.id $whereSQL";
$countStmt = $conn->prepare($countSQL);
if ($types) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalProducts = $countStmt->get_result()->fetch_row()[0];
$totalPages    = ceil($totalProducts / $perPage);

// Fetch products
$sql  = "SELECT p.*, c.category_name,
                COALESCE((SELECT AVG(rating) FROM reviews WHERE product_id=p.id),0) AS avg_rating
         FROM products p
         JOIN categories c ON p.category_id = c.id
         $whereSQL ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$allParams = array_merge($params, [$perPage, $offset]);
$allTypes  = $types . 'ii';
$stmt->bind_param($allTypes, ...$allParams);
$stmt->execute();
$products = $stmt->get_result();

// Fetch featured products for hero slider
$featuredStmt = $conn->prepare(
    "SELECT p.*, c.category_name FROM products p
     JOIN categories c ON p.category_id = c.id
     WHERE p.is_featured = 1 AND p.stock > 0 LIMIT 5"
);
$featuredStmt->execute();
$featured = $featuredStmt->get_result()->fetch_all(MYSQLI_ASSOC);

include 'includes/header.php';
?>

<!-- ===== HERO BANNER / SLIDER ===== -->
<section class="hero-slider">
    <?php if ($featured): ?>
        <?php foreach ($featured as $i => $fp): ?>
        <div class="slide <?= $i === 0 ? 'active' : '' ?>">
            <div class="slide-content">
                <span class="slide-category"><?= sanitize($fp['category_name']) ?></span>
                <h1><?= sanitize($fp['name']) ?></h1>
                <p><?= sanitize(substr($fp['description'] ?? '', 0, 120)) ?>…</p>
                <div class="slide-price">₹<?= number_format($fp['price'], 2) ?></div>
                <a href="user/viewproduct.php?id=<?= $fp['id'] ?>" class="btn btn-hero">
                    Shop Now <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            <div class="slide-image">
                <img src="assets/images/products/<?= sanitize($fp['image']) ?>"
                     alt="<?= sanitize($fp['name']) ?>"
                     onerror="this.src='assets/images/default.jpg'">
            </div>
        </div>
        <?php endforeach; ?>
        <button class="slider-btn prev" onclick="changeSlide(-1)">‹</button>
        <button class="slider-btn next" onclick="changeSlide(1)">›</button>
        <div class="slider-dots">
            <?php foreach ($featured as $i => $_): ?>
                <span class="dot <?= $i===0?'active':'' ?>"
                      onclick="goSlide(<?= $i ?>)"></span>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="slide active hero-default">
            <div class="slide-content">
                <h1>Welcome to E-Mart 🛒</h1>
                <p>Discover millions of products at the best prices.</p>
                <a href="#products" class="btn btn-hero">Browse All Products</a>
            </div>
        </div>
    <?php endif; ?>
</section>

<!-- ===== CATEGORY STRIP ===== -->
<section class="cat-strip container">
    <h2 class="section-title">Shop by Category</h2>
    <div class="cat-grid">
        <?php
        $icons = ['💻','👕','📚','🏠','⚽','🛒','🧸','💄'];
        $cats2 = $conn->query("SELECT * FROM categories LIMIT 8");
        $idx = 0;
        while ($c = $cats2->fetch_assoc()):
        ?>
        <a href="index.php?category=<?= $c['id'] ?>" class="cat-card">
            <div class="cat-icon"><?= $icons[$idx++ % 8] ?></div>
            <span><?= sanitize($c['category_name']) ?></span>
        </a>
        <?php endwhile; ?>
    </div>
</section>

<!-- ===== FILTER BAR ===== -->
<section class="products-section container" id="products">
    <div class="products-header">
        <h2 class="section-title">
            <?= !empty($_GET['search']) ? 'Results for: "' . sanitize($_GET['search']) . '"'
                : (!empty($_GET['category']) ? 'Category Products' : 'All Products') ?>
            <small>(<?= $totalProducts ?> items)</small>
        </h2>
        <div class="sort-filter">
            <select id="sortSelect" onchange="applySort()">
                <option value="">Sort By</option>
                <option value="price_asc">Price: Low to High</option>
                <option value="price_desc">Price: High to Low</option>
                <option value="newest">Newest First</option>
            </select>
        </div>
    </div>

    <!-- Product Grid -->
    <div class="product-grid" id="productGrid">
        <?php while ($p = $products->fetch_assoc()): ?>
        <div class="product-card" data-id="<?= $p['id'] ?>">
            <!-- Wishlist toggle -->
            <button class="wishlist-toggle <?= isLoggedIn() ? '' : 'needs-login' ?>"
                    onclick="toggleWishlist(<?= $p['id'] ?>, this)"
                    title="Add to Wishlist">
                <i class="fas fa-heart"></i>
            </button>

            <!-- Product Image -->
            <a href="user/viewproduct.php?id=<?= $p['id'] ?>">
                <div class="product-img-wrap">
                    <img src="assets/images/products/<?= sanitize($p['image']) ?>"
                         alt="<?= sanitize($p['name']) ?>"
                         onerror="this.src='assets/images/default.jpg'">
                    <?php if ($p['stock'] < 5 && $p['stock'] > 0): ?>
                        <span class="badge badge-warning">Only <?= $p['stock'] ?> left!</span>
                    <?php elseif ($p['stock'] == 0): ?>
                        <span class="badge badge-danger">Out of Stock</span>
                    <?php endif; ?>
                </div>
            </a>

            <div class="product-info">
                <span class="product-category"><?= sanitize($p['category_name']) ?></span>
                <a href="user/viewproduct.php?id=<?= $p['id'] ?>" class="product-name">
                    <?= sanitize($p['name']) ?>
                </a>

                <!-- Star Rating -->
                <div class="star-rating">
                    <?php
                    $rating = round($p['avg_rating']);
                    for ($s = 1; $s <= 5; $s++):
                    ?>
                        <i class="fas fa-star <?= $s <= $rating ? 'filled' : '' ?>"></i>
                    <?php endfor; ?>
                    <small>(<?= $rating ?>)</small>
                </div>

                <div class="product-price">
                    <span class="price-current">₹<?= number_format($p['price'], 2) ?></span>
                    <span class="price-original">₹<?= number_format($p['price'] * 1.2, 2) ?></span>
                    <span class="price-discount">20% off</span>
                </div>

                <?php if ($p['stock'] > 0): ?>
                <button class="btn btn-add-cart"
                        onclick="addToCart(<?= $p['id'] ?>, this)">
                    <i class="fas fa-cart-plus"></i> Add to Cart
                </button>
                <?php else: ?>
                <button class="btn btn-disabled" disabled>Out of Stock</button>
                <?php endif; ?>
            </div>
        </div>
        <?php endwhile; ?>

        <?php if ($totalProducts === 0): ?>
        <div class="no-products">
            <i class="fas fa-search" style="font-size:4rem;color:#ccc"></i>
            <h3>No products found</h3>
            <a href="index.php" class="btn btn-primary">Browse All</a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php
        $base = '?' . http_build_query(array_merge($_GET, ['page' => '']));
        for ($p = 1; $p <= $totalPages; $p++):
        ?>
            <a href="<?= $base . $p ?>"
               class="page-btn <?= $p == $page ? 'active' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</section>

<?php include 'includes/footer.php'; ?>
