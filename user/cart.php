<?php
// user/cart.php — Shopping Cart Page
session_start();
require_once '../includes/connection.php';
requireLogin();

$userId = (int)$_SESSION['user_id'];

// Fetch cart items with product details
$stmt = $conn->prepare(
    "SELECT c.id AS cart_id, c.quantity,
            p.id AS product_id, p.name, p.price, p.image, p.stock
     FROM cart c
     JOIN products p ON c.product_id = p.id
     WHERE c.user_id = ?"
);
$stmt->bind_param("i", $userId);
$stmt->execute();
$cartItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate totals
$subtotal = array_reduce($cartItems, fn($carry, $item) =>
    $carry + ($item['price'] * $item['quantity']), 0);
$shipping = $subtotal >= 499 ? 0 : 49;
$total    = $subtotal + $shipping;

$pageTitle = 'My Cart';
include '../includes/header.php';
?>

<div class="container cart-page">
    <h1 class="page-title">🛒 Shopping Cart <small>(<?= count($cartItems) ?> items)</small></h1>

    <?php if (empty($cartItems)): ?>
    <div class="empty-state">
        <div class="empty-icon">🛒</div>
        <h2>Your cart is empty!</h2>
        <p>Add items to your cart to checkout.</p>
        <a href="../index.php" class="btn btn-primary">Browse Products</a>
    </div>
    <?php else: ?>

    <div class="cart-layout">
        <!-- ===== CART ITEMS ===== -->
        <div class="cart-items" id="cartItemsWrap">
            <?php foreach ($cartItems as $item): ?>
            <div class="cart-item" id="cartRow_<?= $item['cart_id'] ?>">
                <div class="cart-item-img">
                    <img src="../assets/images/products/<?= sanitize($item['image']) ?>"
                         alt="<?= sanitize($item['name']) ?>"
                         onerror="this.src='../assets/images/default.jpg'">
                </div>
                <div class="cart-item-info">
                    <a href="viewproduct.php?id=<?= $item['product_id'] ?>" class="cart-item-name">
                        <?= sanitize($item['name']) ?>
                    </a>
                    <div class="cart-item-price">₹<?= number_format($item['price'], 2) ?></div>

                    <!-- Quantity controls -->
                    <div class="cart-qty-controls">
                        <button onclick="updateCart(<?= $item['cart_id'] ?>, -1)">−</button>
                        <span class="qty-display" id="qty_<?= $item['cart_id'] ?>">
                            <?= $item['quantity'] ?>
                        </span>
                        <button onclick="updateCart(<?= $item['cart_id'] ?>, 1)">+</button>
                    </div>

                    <div class="cart-item-subtotal">
                        Subtotal: <strong id="sub_<?= $item['cart_id'] ?>">
                            ₹<?= number_format($item['price'] * $item['quantity'], 2) ?>
                        </strong>
                    </div>
                </div>
                <div class="cart-item-actions">
                    <button class="btn-remove"
                            onclick="removeCart(<?= $item['cart_id'] ?>)">
                        <i class="fas fa-trash"></i> Remove
                    </button>
                    <button class="btn-wishlist-sm"
                            onclick="moveToWishlist(<?= $item['product_id'] ?>, <?= $item['cart_id'] ?>)">
                        <i class="fas fa-heart"></i> Save
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ===== ORDER SUMMARY ===== -->
        <div class="cart-summary">
            <h3>Order Summary</h3>
            <div class="summary-row">
                <span>Subtotal (<?= count($cartItems) ?> items)</span>
                <span id="summarySubtotal">₹<?= number_format($subtotal, 2) ?></span>
            </div>
            <div class="summary-row">
                <span>Shipping</span>
                <span id="summaryShipping">
                    <?= $shipping === 0 ? '<span class="free-shipping">FREE</span>'
                        : '₹' . $shipping ?>
                </span>
            </div>
            <div class="summary-row discount-row" id="discountRow" style="display:none">
                <span>Coupon Discount</span>
                <span id="discountAmt" style="color:green">-₹0</span>
            </div>
            <hr>
            <div class="summary-row total-row">
                <strong>Total</strong>
                <strong id="summaryTotal">₹<?= number_format($total, 2) ?></strong>
            </div>

            <!-- Coupon Box -->
            <div class="coupon-box">
                <input type="text" id="couponInput" placeholder="Enter coupon code">
                <button onclick="applyCoupon()">Apply</button>
            </div>
            <div id="couponMsg" class="coupon-msg"></div>

            <a href="checkout.php" class="btn btn-primary btn-block btn-checkout">
                Proceed to Checkout <i class="fas fa-arrow-right"></i>
            </a>
            <a href="../index.php" class="btn btn-outline btn-block" style="margin-top:8px">
                Continue Shopping
            </a>

            <!-- Free shipping note -->
            <?php if ($shipping > 0): ?>
            <p class="free-shipping-note">
                Add ₹<?= number_format(499 - $subtotal, 2) ?> more for <strong>FREE shipping</strong>!
            </p>
            <?php endif; ?>
        </div>
    </div>

    <?php endif; ?>
</div>

<script>
let cartSubtotal = <?= $subtotal ?>;
let cartShipping = <?= $shipping ?>;
let appliedDiscount = 0;

function updateCart(cartId, delta) {
    const qtyEl  = document.getElementById('qty_' + cartId);
    const newQty = Math.max(1, parseInt(qtyEl.textContent) + delta);
    qtyEl.textContent = newQty;

    fetch('../api/cart.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'update', cart_id: cartId, quantity: newQty})
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            document.getElementById('sub_' + cartId).textContent = '₹' + d.subtotal;
            document.getElementById('cartBadge').textContent = d.cart_count;
            // Reload for accurate totals (simple approach)
            location.reload();
        }
    });
}

function removeCart(cartId) {
    if (!confirm('Remove this item?')) return;
    fetch('../api/cart.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'remove', cart_id: cartId})
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            document.getElementById('cartRow_' + cartId).remove();
            document.getElementById('cartBadge').textContent = d.cart_count;
            showToast('Item removed', 'success');
            // Check if cart empty
            if (d.cart_count === 0) location.reload();
        }
    });
}

function moveToWishlist(productId, cartId) {
    fetch('../api/wishlist.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({product_id: productId})
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            showToast('Saved to Wishlist!', 'success');
            removeCart(cartId);
        }
    });
}

function applyCoupon() {
    const code = document.getElementById('couponInput').value.trim();
    const msgEl = document.getElementById('couponMsg');
    fetch('../api/coupon.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({code: code, total: cartSubtotal})
    })
    .then(r => r.json())
    .then(d => {
        msgEl.textContent = d.message;
        msgEl.className   = 'coupon-msg ' + (d.success ? 'success' : 'error');
        if (d.success) {
            appliedDiscount = d.discount;
            document.getElementById('discountRow').style.display = 'flex';
            document.getElementById('discountAmt').textContent = '-₹' + d.discount;
            document.getElementById('summaryTotal').textContent = '₹' + d.new_total;
            // Store coupon for checkout
            sessionStorage.setItem('coupon', code);
            sessionStorage.setItem('discount', d.discount);
        }
    });
}
</script>

<?php include '../includes/footer.php'; ?>
