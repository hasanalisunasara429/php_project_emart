<?php
// user/viewproduct.php — Single product detail page
session_start();
require_once '../includes/connection.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { setFlash('error', 'Invalid product.'); redirect('index.php'); }

// Fetch product with category
$stmt = $conn->prepare(
    "SELECT p.*, c.category_name,
            COALESCE((SELECT AVG(rating) FROM reviews WHERE product_id=p.id),0) AS avg_rating,
            (SELECT COUNT(*) FROM reviews WHERE product_id=p.id) AS review_count
     FROM products p
     JOIN categories c ON p.category_id = c.id
     WHERE p.id = ? LIMIT 1"
);
$stmt->bind_param("i", $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
if (!$product) { setFlash('error', 'Product not found.'); redirect('index.php'); }

// Fetch reviews for this product
$revStmt = $conn->prepare(
    "SELECT r.*, u.username FROM reviews r
     JOIN users u ON r.user_id = u.id
     WHERE r.product_id = ? ORDER BY r.created_at DESC"
);
$revStmt->bind_param("i", $id);
$revStmt->execute();
$reviews = $revStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Check if current user already reviewed
$userReview = null;
if (isLoggedIn()) {
    $myRev = $conn->prepare(
        "SELECT * FROM reviews WHERE user_id=? AND product_id=?"
    );
    $myRev->bind_param("ii", $_SESSION['user_id'], $id);
    $myRev->execute();
    $userReview = $myRev->get_result()->fetch_assoc();
}

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn() && isset($_POST['submit_review'])) {
    $rating  = max(1, min(5, (int)$_POST['rating']));
    $comment = sanitize($_POST['comment'] ?? '');

    if ($userReview) {
        // Update existing
        $upd = $conn->prepare(
            "UPDATE reviews SET rating=?, comment=? WHERE user_id=? AND product_id=?"
        );
        $upd->bind_param("isii", $rating, $comment, $_SESSION['user_id'], $id);
        $upd->execute();
    } else {
        // New review
        $ins = $conn->prepare(
            "INSERT INTO reviews (user_id, product_id, rating, comment) VALUES (?,?,?,?)"
        );
        $ins->bind_param("iiis", $_SESSION['user_id'], $id, $rating, $comment);
        $ins->execute();
    }
    setFlash('success', 'Review submitted!');
    redirect("user/viewproduct.php?id=$id");
}

// Related products
$related = $conn->prepare(
    "SELECT p.*, c.category_name FROM products p
     JOIN categories c ON p.category_id = c.id
     WHERE p.category_id = ? AND p.id != ? AND p.stock > 0
     LIMIT 4"
);
$related->bind_param("ii", $product['category_id'], $id);
$related->execute();
$relatedProducts = $related->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = sanitize($product['name']);
include '../includes/header.php';
?>

<div class="container product-detail-page">
    <!-- Breadcrumb -->
    <nav class="breadcrumb">
        <a href="../index.php">Home</a> ›
        <a href="../index.php?category=<?= $product['category_id'] ?>">
            <?= sanitize($product['category_name']) ?>
        </a> ›
        <span><?= sanitize($product['name']) ?></span>
    </nav>

    <!-- ===== PRODUCT MAIN SECTION ===== -->
    <div class="product-detail-grid">

        <!-- Left: Image Gallery -->
        <div class="product-gallery">
            <div class="main-image">
                <img id="mainImg"
                     src="../assets/images/products/<?= sanitize($product['image']) ?>"
                     alt="<?= sanitize($product['name']) ?>"
                     onerror="this.src='../assets/images/default.jpg'">
            </div>
            <!-- Thumbnails (if you add multiple product images, list them here) -->
            <div class="thumb-row">
                <img class="thumb active"
                     src="../assets/images/products/<?= sanitize($product['image']) ?>"
                     onclick="switchImg(this.src)">
            </div>
        </div>

        <!-- Right: Product Info -->
        <div class="product-info-panel">
            <h1 class="product-title"><?= sanitize($product['name']) ?></h1>

            <!-- Rating summary -->
            <div class="rating-row">
                <?php $avg = round($product['avg_rating'], 1); ?>
                <div class="stars-display">
                    <?php for ($s=1;$s<=5;$s++): ?>
                        <i class="fas fa-star <?= $s<=$avg?'filled':'' ?>"></i>
                    <?php endfor; ?>
                </div>
                <span class="rating-val"><?= $avg ?></span>
                <span class="rating-count">(<?= $product['review_count'] ?> reviews)</span>
            </div>

            <!-- Price -->
            <div class="price-block">
                <span class="price-big">₹<?= number_format($product['price'], 2) ?></span>
                <span class="price-mrp">MRP ₹<?= number_format($product['price']*1.2, 2) ?></span>
                <span class="discount-tag">20% off</span>
            </div>
            <p class="inclusive-tax">Inclusive of all taxes. Free delivery on orders above ₹499.</p>

            <!-- Stock Status -->
            <div class="stock-status <?= $product['stock']>0?'in-stock':'out-stock' ?>">
                <?= $product['stock'] > 5 ? '✅ In Stock'
                    : ($product['stock'] > 0 ? "⚠️ Only {$product['stock']} left!" : '❌ Out of Stock') ?>
            </div>

            <!-- Quantity selector + buttons -->
            <?php if ($product['stock'] > 0): ?>
            <div class="qty-cart-row">
                <div class="qty-selector">
                    <button onclick="changeQty(-1)">−</button>
                    <input type="number" id="qty" value="1" min="1"
                           max="<?= min($product['stock'], 10) ?>">
                    <button onclick="changeQty(1)">+</button>
                </div>
                <button class="btn btn-add-cart"
                        onclick="addToCartQty(<?= $id ?>, parseInt(document.getElementById('qty').value))">
                    <i class="fas fa-cart-plus"></i> Add to Cart
                </button>
                <button class="btn btn-buy-now"
                        onclick="buyNow(<?= $id ?>)">
                    <i class="fas fa-bolt"></i> Buy Now
                </button>
            </div>
            <?php endif; ?>

            <!-- Wishlist -->
            <button class="btn btn-wishlist-lg" onclick="toggleWishlist(<?= $id ?>, this)">
                <i class="fas fa-heart"></i> Add to Wishlist
            </button>

            <!-- Description -->
            <div class="product-description">
                <h3>Product Description</h3>
                <p><?= nl2br(sanitize($product['description'] ?? 'No description available.')) ?></p>
            </div>

            <!-- Delivery info -->
            <div class="delivery-info">
                <div><i class="fas fa-truck"></i> Free delivery by tomorrow</div>
                <div><i class="fas fa-undo"></i> 7-day easy returns</div>
                <div><i class="fas fa-shield-alt"></i> 1 year warranty</div>
            </div>
        </div>
    </div>

    <!-- ===== REVIEWS SECTION ===== -->
    <div class="reviews-section">
        <h2>Customer Reviews</h2>

        <!-- Write a review (logged-in users) -->
        <?php if (isLoggedIn()): ?>
        <div class="write-review">
            <h3><?= $userReview ? 'Update Your Review' : 'Write a Review' ?></h3>
            <form method="POST">
                <div class="star-picker" id="starPicker">
                    <?php for ($s=1;$s<=5;$s++): ?>
                        <i class="fas fa-star <?= ($userReview && $userReview['rating']>=$s)?'filled':'' ?>"
                           data-val="<?= $s ?>"
                           onclick="setRating(<?= $s ?>)"></i>
                    <?php endfor; ?>
                    <input type="hidden" name="rating" id="ratingInput"
                           value="<?= $userReview['rating'] ?? 0 ?>">
                </div>
                <textarea name="comment" rows="4" placeholder="Share your experience..."
                          class="review-textarea"><?= sanitize($userReview['comment'] ?? '') ?></textarea>
                <button type="submit" name="submit_review" class="btn btn-primary">
                    Submit Review
                </button>
            </form>
        </div>
        <?php else: ?>
        <p class="login-to-review">
            <a href="login.php">Login</a> to write a review.
        </p>
        <?php endif; ?>

        <!-- Existing reviews -->
        <div class="review-list">
            <?php if (empty($reviews)): ?>
                <p>No reviews yet. Be the first to review!</p>
            <?php endif; ?>
            <?php foreach ($reviews as $rv): ?>
            <div class="review-card">
                <div class="review-header">
                    <strong><?= sanitize($rv['username']) ?></strong>
                    <div class="stars-sm">
                        <?php for ($s=1;$s<=5;$s++): ?>
                            <i class="fas fa-star <?= $s<=$rv['rating']?'filled':'' ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <span class="review-date"><?= date('d M Y', strtotime($rv['created_at'])) ?></span>
                </div>
                <p><?= sanitize($rv['comment']) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ===== RELATED PRODUCTS ===== -->
    <?php if ($relatedProducts): ?>
    <div class="related-section">
        <h2>Related Products</h2>
        <div class="product-grid">
            <?php foreach ($relatedProducts as $rp): ?>
            <div class="product-card">
                <a href="viewproduct.php?id=<?= $rp['id'] ?>">
                    <div class="product-img-wrap">
                        <img src="../assets/images/products/<?= sanitize($rp['image']) ?>"
                             onerror="this.src='../assets/images/default.jpg'">
                    </div>
                </a>
                <div class="product-info">
                    <a href="viewproduct.php?id=<?= $rp['id'] ?>" class="product-name">
                        <?= sanitize($rp['name']) ?>
                    </a>
                    <div class="product-price">
                        <span class="price-current">₹<?= number_format($rp['price'],2) ?></span>
                    </div>
                    <button class="btn btn-add-cart"
                            onclick="addToCart(<?= $rp['id'] ?>, this)">
                        Add to Cart
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function switchImg(src) {
    document.getElementById('mainImg').src = src;
    document.querySelectorAll('.thumb').forEach(t => t.classList.remove('active'));
    event.target.classList.add('active');
}
function changeQty(delta) {
    const inp = document.getElementById('qty');
    const max = parseInt(inp.max);
    inp.value = Math.min(max, Math.max(1, parseInt(inp.value) + delta));
}
function setRating(val) {
    document.getElementById('ratingInput').value = val;
    document.querySelectorAll('#starPicker .fa-star').forEach((s, i) => {
        s.classList.toggle('filled', i < val);
    });
}
function addToCartQty(productId, qty) {
    fetch('../api/cart.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action:'add', product_id: productId, quantity: qty})
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            document.getElementById('cartBadge').textContent = d.cart_count;
            showToast('Added to cart!', 'success');
        } else {
            showToast(d.message || 'Login required', 'error');
            if (d.redirect) window.location.href = d.redirect;
        }
    });
}
function buyNow(productId) {
    fetch('../api/cart.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action:'add', product_id: productId, quantity: 1})
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) window.location.href = '../user/cart.php';
        else showToast(d.message || 'Login required', 'error');
    });
}
</script>

<?php include '../includes/footer.php'; ?>
