<?php
// user/checkout.php — Checkout & Place Order
session_start();
require_once '../includes/connection.php';
require_once '../includes/mailer.php';
requireLogin();

$userId = (int)$_SESSION['user_id'];

// Fetch cart
$stmt = $conn->prepare(
    "SELECT c.id AS cart_id, c.quantity,
            p.id AS product_id, p.name, p.price, p.stock
     FROM cart c JOIN products p ON c.product_id = p.id
     WHERE c.user_id = ?"
);
$stmt->bind_param("i", $userId);
$stmt->execute();
$cartItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (empty($cartItems)) {
    setFlash('error', 'Your cart is empty.');
    redirect('user/cart.php');
}

// Subtotal
$subtotal = array_reduce($cartItems, fn($c, $i) => $c + ($i['price'] * $i['quantity']), 0);
$shipping = $subtotal >= 499 ? 0 : 49;

// Fetch user profile for prefill
$user = $conn->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
$user->bind_param("i", $userId);
$user->execute();
$profile = $user->get_result()->fetch_assoc();

$errors = [];

// ---- Handle Order Placement ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $address    = sanitize($_POST['address'] ?? '');
    $couponCode = sanitize($_POST['coupon_code'] ?? '');
    $discount   = 0;

    if (strlen($address) < 20) {
        $errors[] = 'Please enter a complete delivery address.';
    }

    // Validate coupon if provided
    if ($couponCode && empty($errors)) {
        $cStmt = $conn->prepare(
            "SELECT * FROM coupons
             WHERE code=? AND is_active=1 AND expiry_date>=CURDATE() AND used_count<max_uses"
        );
        $cStmt->bind_param("s", $couponCode);
        $cStmt->execute();
        $coupon = $cStmt->get_result()->fetch_assoc();

        if ($coupon) {
            if ($subtotal >= $coupon['min_order']) {
                $discount = $coupon['discount_type'] === 'percent'
                    ? round($subtotal * $coupon['discount_value'] / 100, 2)
                    : min($coupon['discount_value'], $subtotal);
            } else {
                $errors[] = "Min order ₹{$coupon['min_order']} required for this coupon.";
            }
        } else {
            $errors[] = 'Invalid or expired coupon code.';
        }
    }

    // Stock double-check
    if (empty($errors)) {
        foreach ($cartItems as $item) {
            if ($item['stock'] < $item['quantity']) {
                $errors[] = "'{$item['name']}' has only {$item['stock']} in stock.";
            }
        }
    }

    // Place the order
    if (empty($errors)) {
        $totalAmount = $subtotal + $shipping - $discount;

        // Begin transaction
        $conn->begin_transaction();
        try {
            // 1. Insert order
            $ins = $conn->prepare(
                "INSERT INTO orders (user_id, total_amount, status, address, coupon_code, discount)
                 VALUES (?,?,?,?,?,?)"
            );
            $status = 'Pending';
            $ins->bind_param("idsssd", $userId, $totalAmount, $status, $address, $couponCode, $discount);
            $ins->execute();
            $orderId = $conn->insert_id;

            // 2. Insert order items + reduce stock
            $insItem = $conn->prepare(
                "INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?,?,?,?)"
            );
            $decrStock = $conn->prepare("UPDATE products SET stock=stock-? WHERE id=?");

            foreach ($cartItems as $item) {
                $insItem->bind_param("iiid", $orderId, $item['product_id'],
                                             $item['quantity'], $item['price']);
                $insItem->execute();
                $decrStock->bind_param("ii", $item['quantity'], $item['product_id']);
                $decrStock->execute();
            }

            // 3. Clear cart
            $del = $conn->prepare("DELETE FROM cart WHERE user_id=?");
            $del->bind_param("i", $userId);
            $del->execute();

            // 4. Increment coupon usage
            if ($couponCode) {
                $couponUpd = $conn->prepare("UPDATE coupons SET used_count=used_count+1 WHERE code=?");
            $couponUpd->bind_param("s", $couponCode);
            $couponUpd->execute();
            }

            $conn->commit();

            // 5. Send confirmation email (non-blocking)
            orderConfirmationEmail($profile['email'], $profile['username'], $orderId, $totalAmount);

            setFlash('success', "Order #$orderId placed successfully! 🎉");
            redirect("user/orders.php");

        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = 'Order failed. Please try again.';
            error_log($e->getMessage());
        }
    }
}

$pageTitle = 'Checkout';
include '../includes/header.php';
?>

<div class="container checkout-page">
    <h1 class="page-title">Checkout</h1>

    <?php if ($errors): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $e): ?><p><?= $e ?></p><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="checkout-layout">
        <!-- LEFT: Address + Coupon Form -->
        <div class="checkout-left">
            <form method="POST" id="checkoutForm">

                <!-- Delivery Address -->
                <div class="checkout-card">
                    <h3><i class="fas fa-map-marker-alt"></i> Delivery Address</h3>
                    <textarea name="address" rows="5" required
                              placeholder="House No., Street, Area, City, State, PIN"
                              class="address-input"><?= sanitize($_POST['address'] ?? '') ?></textarea>
                </div>

                <!-- Coupon Code -->
                <div class="checkout-card">
                    <h3><i class="fas fa-tag"></i> Coupon Code</h3>
                    <div class="coupon-apply-row">
                        <input type="text" name="coupon_code" id="checkoutCoupon"
                               placeholder="SAVE10, FLAT100, WELCOME20"
                               value="<?= sanitize($_POST['coupon_code'] ?? '') ?>">
                        <button type="button" onclick="applyCouponCheckout()">Check</button>
                    </div>
                    <div id="checkoutCouponMsg"></div>
                </div>

                <!-- Payment Method (static for demo) -->
                <div class="checkout-card">
                    <h3><i class="fas fa-credit-card"></i> Payment Method</h3>
                    <label class="payment-option">
                        <input type="radio" name="payment" value="cod" checked>
                        Cash on Delivery (COD)
                    </label>
                    <label class="payment-option">
                        <input type="radio" name="payment" value="online" disabled>
                        Online Payment <span style="color:#888">(Coming soon)</span>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-place-order">
                    <i class="fas fa-check-circle"></i> Place Order
                </button>
            </form>
        </div>

        <!-- RIGHT: Order Summary -->
        <div class="checkout-right">
            <div class="checkout-card">
                <h3>Order Summary</h3>
                <?php foreach ($cartItems as $item): ?>
                <div class="checkout-item">
                    <span><?= sanitize($item['name']) ?> × <?= $item['quantity'] ?></span>
                    <span>₹<?= number_format($item['price'] * $item['quantity'], 2) ?></span>
                </div>
                <?php endforeach; ?>
                <hr>
                <div class="checkout-item">
                    <span>Subtotal</span>
                    <span>₹<?= number_format($subtotal, 2) ?></span>
                </div>
                <div class="checkout-item">
                    <span>Shipping</span>
                    <span><?= $shipping === 0 ? 'FREE' : '₹' . $shipping ?></span>
                </div>
                <div class="checkout-item" id="coDiscountRow" style="display:none">
                    <span style="color:green">Discount</span>
                    <span style="color:green" id="coDiscountVal">-₹0</span>
                </div>
                <hr>
                <div class="checkout-item total-row">
                    <strong>Total Payable</strong>
                    <strong id="coTotal">₹<?= number_format($subtotal + $shipping, 2) ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const baseSubtotal = <?= $subtotal ?>;
const baseShipping = <?= $shipping ?>;

function applyCouponCheckout() {
    const code  = document.getElementById('checkoutCoupon').value.trim();
    const msgEl = document.getElementById('checkoutCouponMsg');
    if (!code) return;

    fetch('../api/coupon.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({code: code, total: baseSubtotal})
    })
    .then(r => r.json())
    .then(d => {
        msgEl.textContent = d.message;
        msgEl.className   = d.success ? 'coupon-msg success' : 'coupon-msg error';
        if (d.success) {
            document.getElementById('coDiscountRow').style.display = 'flex';
            document.getElementById('coDiscountVal').textContent = '-₹' + d.discount;
            document.getElementById('coTotal').textContent =
                '₹' + (baseSubtotal + baseShipping - d.discount).toFixed(2);
        }
    });
}
</script>

<?php include '../includes/footer.php'; ?>
