<?php
// user/wishlist.php — Wishlist page
session_start();
require_once '../includes/connection.php';
requireLogin();

$userId = (int)$_SESSION['user_id'];

$stmt = $conn->prepare(
    "SELECT w.id AS wish_id, p.id AS product_id, p.name, p.price, p.image, p.stock,
            c.category_name
     FROM wishlist w
     JOIN products p ON w.product_id = p.id
     JOIN categories c ON p.category_id = c.id
     WHERE w.user_id = ? ORDER BY w.id DESC"
);
$stmt->bind_param("i", $userId);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'My Wishlist';
include '../includes/header.php';
?>

<div class="container wishlist-page">
    <h1 class="page-title">❤️ My Wishlist <small>(<?= count($items) ?> items)</small></h1>

    <?php if (empty($items)): ?>
    <div class="empty-state">
        <div class="empty-icon">❤️</div>
        <h2>Your wishlist is empty</h2>
        <p>Save items you love and buy them later.</p>
        <a href="../index.php" class="btn btn-primary">Explore Products</a>
    </div>
    <?php else: ?>
    <div class="product-grid">
        <?php foreach ($items as $item): ?>
        <div class="product-card" id="wishCard_<?= $item['wish_id'] ?>">
            <button class="wishlist-toggle active"
                    onclick="removeWish(<?= $item['product_id'] ?>, <?= $item['wish_id'] ?>)"
                    title="Remove from Wishlist">
                <i class="fas fa-heart"></i>
            </button>
            <a href="viewproduct.php?id=<?= $item['product_id'] ?>">
                <div class="product-img-wrap">
                    <img src="../assets/images/products/<?= sanitize($item['image']) ?>"
                         alt="<?= sanitize($item['name']) ?>"
                         onerror="this.src='../assets/images/default.jpg'">
                </div>
            </a>
            <div class="product-info">
                <span class="product-category"><?= sanitize($item['category_name']) ?></span>
                <a href="viewproduct.php?id=<?= $item['product_id'] ?>" class="product-name">
                    <?= sanitize($item['name']) ?>
                </a>
                <div class="product-price">
                    <span class="price-current">₹<?= number_format($item['price'], 2) ?></span>
                </div>
                <?php if ($item['stock'] > 0): ?>
                <button class="btn btn-add-cart"
                        onclick="addToCart(<?= $item['product_id'] ?>, this)">
                    <i class="fas fa-cart-plus"></i> Add to Cart
                </button>
                <?php else: ?>
                <button class="btn btn-disabled" disabled>Out of Stock</button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script>
function removeWish(productId, wishId) {
    fetch('../api/wishlist.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({product_id: productId})
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            document.getElementById('wishCard_' + wishId).remove();
            showToast('Removed from wishlist', 'success');
        }
    });
}
</script>

<?php include '../includes/footer.php'; ?>
