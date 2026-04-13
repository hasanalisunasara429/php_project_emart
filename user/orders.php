<?php
// user/orders.php — Order history + tracking status
session_start();
require_once '../includes/connection.php';
requireLogin();

$userId = (int)$_SESSION['user_id'];

// Fetch all orders for this user
$stmt = $conn->prepare(
    "SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC"
);
$stmt->bind_param("i", $userId);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Detail view: single order items
$orderDetail = null;
$orderItems  = [];
if (!empty($_GET['order_id'])) {
    $oid = (int)$_GET['order_id'];
    $od  = $conn->prepare(
        "SELECT * FROM orders WHERE id=? AND user_id=? LIMIT 1"
    );
    $od->bind_param("ii", $oid, $userId);
    $od->execute();
    $orderDetail = $od->get_result()->fetch_assoc();

    if ($orderDetail) {
        $oi = $conn->prepare(
            "SELECT oi.*, p.name, p.image FROM order_items oi
             JOIN products p ON oi.product_id = p.id
             WHERE oi.order_id = ?"
        );
        $oi->bind_param("i", $oid);
        $oi->execute();
        $orderItems = $oi->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

$pageTitle = 'My Orders';
include '../includes/header.php';

// Status → step mapping for tracker
$statusSteps = ['Pending'=>1, 'Processing'=>2, 'Shipped'=>3, 'Delivered'=>4, 'Cancelled'=>0];
?>

<div class="container orders-page">
    <h1 class="page-title">My Orders</h1>

    <?php if ($orderDetail): ?>
    <!-- ===== ORDER DETAIL VIEW ===== -->
    <a href="orders.php" class="btn btn-outline" style="margin-bottom:20px">
        ← Back to Orders
    </a>

    <div class="order-detail-card">
        <div class="order-detail-header">
            <div>
                <h2>Order #<?= $orderDetail['id'] ?></h2>
                <p>Placed on <?= date('d M Y, h:i A', strtotime($orderDetail['created_at'])) ?></p>
            </div>
            <span class="status-badge status-<?= strtolower($orderDetail['status']) ?>">
                <?= $orderDetail['status'] ?>
            </span>
        </div>

        <!-- Status Tracker -->
        <?php
        $step = $statusSteps[$orderDetail['status']] ?? 1;
        $cancelled = $orderDetail['status'] === 'Cancelled';
        ?>
        <?php if (!$cancelled): ?>
        <div class="order-tracker">
            <?php
            $steps = [
                ['icon'=>'fas fa-clipboard-check', 'label'=>'Order Placed'],
                ['icon'=>'fas fa-cog',              'label'=>'Processing'],
                ['icon'=>'fas fa-truck',            'label'=>'Shipped'],
                ['icon'=>'fas fa-home',             'label'=>'Delivered'],
            ];
            foreach ($steps as $i => $s):
                $num = $i + 1;
            ?>
            <div class="tracker-step <?= $num <= $step ? 'done' : '' ?> <?= $num === $step ? 'current' : '' ?>">
                <div class="tracker-icon"><i class="<?= $s['icon'] ?>"></i></div>
                <div class="tracker-label"><?= $s['label'] ?></div>
            </div>
            <?php if ($num < 4): ?>
            <div class="tracker-line <?= $num < $step ? 'done' : '' ?>"></div>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="alert alert-danger">
            ❌ This order was cancelled.
        </div>
        <?php endif; ?>

        <!-- Items -->
        <h3 style="margin:20px 0 10px">Items in this order</h3>
        <?php foreach ($orderItems as $oi): ?>
        <div class="order-item-row">
            <img src="../assets/images/products/<?= sanitize($oi['image']) ?>"
                 onerror="this.src='../assets/images/default.jpg'">
            <div class="oi-info">
                <strong><?= sanitize($oi['name']) ?></strong>
                <span>Qty: <?= $oi['quantity'] ?></span>
            </div>
            <div class="oi-price">
                ₹<?= number_format($oi['price'] * $oi['quantity'], 2) ?>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Totals -->
        <div class="order-totals">
            <div class="ot-row"><span>Subtotal</span>
                <span>₹<?= number_format($orderDetail['total_amount'] + $orderDetail['discount'], 2) ?></span>
            </div>
            <?php if ($orderDetail['discount'] > 0): ?>
            <div class="ot-row"><span>Discount (<?= $orderDetail['coupon_code'] ?>)</span>
                <span style="color:green">-₹<?= number_format($orderDetail['discount'], 2) ?></span>
            </div>
            <?php endif; ?>
            <div class="ot-row total"><strong>Total Paid</strong>
                <strong>₹<?= number_format($orderDetail['total_amount'], 2) ?></strong>
            </div>
        </div>

        <!-- Delivery Address -->
        <div class="delivery-address">
            <h4><i class="fas fa-map-marker-alt"></i> Delivery Address</h4>
            <p><?= nl2br(sanitize($orderDetail['address'])) ?></p>
        </div>
    </div>

    <?php elseif (empty($orders)): ?>
    <!-- ===== EMPTY STATE ===== -->
    <div class="empty-state">
        <div class="empty-icon">📦</div>
        <h2>No orders yet</h2>
        <p>Start shopping and your orders will appear here.</p>
        <a href="../index.php" class="btn btn-primary">Shop Now</a>
    </div>

    <?php else: ?>
    <!-- ===== ORDERS LIST ===== -->
    <div class="orders-list">
        <?php foreach ($orders as $order): ?>
        <div class="order-card">
            <div class="order-card-header">
                <div>
                    <strong>Order #<?= $order['id'] ?></strong>
                    <span class="order-date">
                        <?= date('d M Y', strtotime($order['created_at'])) ?>
                    </span>
                </div>
                <span class="status-badge status-<?= strtolower($order['status']) ?>">
                    <?= $order['status'] ?>
                </span>
            </div>
            <div class="order-card-body">
                <div>Total: <strong>₹<?= number_format($order['total_amount'], 2) ?></strong></div>
                <a href="orders.php?order_id=<?= $order['id'] ?>" class="btn btn-outline btn-sm">
                    View Details <i class="fas fa-chevron-right"></i>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
