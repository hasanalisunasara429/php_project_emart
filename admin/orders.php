<?php
// admin/orders.php — View all orders, update status
$pageTitle = 'Manage Orders';
require_once 'includes/admin_header.php';

// Update order status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $oid    = (int)$_POST['order_id'];
    $status = sanitize($_POST['status']);
    $allowed = ['Pending','Processing','Shipped','Delivered','Cancelled'];
    if (in_array($status, $allowed)) {
        $upd = $conn->prepare("UPDATE orders SET status=? WHERE id=?");
        $upd->bind_param("si", $status, $oid);
        $upd->execute();
        setFlash('success', "Order #$oid status updated to $status.");
    }
    redirect('admin/orders.php' . (isset($_GET['view']) ? '?view=' . (int)$_GET['view'] : ''));
}

// Single order detail view
$orderDetail = null;
$orderItems  = [];
if (!empty($_GET['view'])) {
    $vid = (int)$_GET['view'];
    $od  = $conn->prepare(
        "SELECT o.*, u.username, u.email, u.mobile FROM orders o
         JOIN users u ON o.user_id = u.id WHERE o.id=? LIMIT 1"
    );
    $od->bind_param("i", $vid);
    $od->execute();
    $orderDetail = $od->get_result()->fetch_assoc();

    if ($orderDetail) {
        $oi = $conn->prepare(
            "SELECT oi.*, p.name, p.image FROM order_items oi
             JOIN products p ON oi.product_id = p.id WHERE oi.order_id=?"
        );
        $oi->bind_param("i", $vid);
        $oi->execute();
        $orderItems = $oi->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

// Filter & list
$statusFilter = sanitize($_GET['status'] ?? '');
$filterSQL    = $statusFilter ? "AND o.status = '$statusFilter'" : '';

$orders = $conn->query(
    "SELECT o.*, u.username FROM orders o
     JOIN users u ON o.user_id = u.id
     WHERE 1=1 $filterSQL
     ORDER BY o.created_at DESC"
)->fetch_all(MYSQLI_ASSOC);

$statuses = ['Pending','Processing','Shipped','Delivered','Cancelled'];
?>

<!-- Status Filter Tabs -->
<div class="filter-tabs">
    <a href="orders.php" class="<?= !$statusFilter ? 'active' : '' ?>">All</a>
    <?php foreach ($statuses as $st): ?>
    <a href="orders.php?status=<?= $st ?>"
       class="<?= $statusFilter === $st ? 'active' : '' ?>">
        <?= $st ?>
    </a>
    <?php endforeach; ?>
</div>

<?php if ($orderDetail): ?>
<!-- ===== ORDER DETAIL ===== -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3>Order #<?= $orderDetail['id'] ?> Details</h3>
        <a href="orders.php" class="btn btn-sm btn-outline">← All Orders</a>
    </div>

    <div class="order-detail-admin">
        <div class="oda-section">
            <h4>Customer Info</h4>
            <p><strong>Name:</strong> <?= sanitize($orderDetail['username']) ?></p>
            <p><strong>Email:</strong> <?= sanitize($orderDetail['email']) ?></p>
            <p><strong>Mobile:</strong> <?= sanitize($orderDetail['mobile']) ?></p>
            <p><strong>Address:</strong><br><?= nl2br(sanitize($orderDetail['address'])) ?></p>
        </div>
        <div class="oda-section">
            <h4>Order Info</h4>
            <p><strong>Date:</strong> <?= date('d M Y, h:i A', strtotime($orderDetail['created_at'])) ?></p>
            <p><strong>Coupon:</strong> <?= $orderDetail['coupon_code'] ?: '—' ?></p>
            <p><strong>Discount:</strong> ₹<?= number_format($orderDetail['discount'], 2) ?></p>
            <p><strong>Total:</strong> ₹<?= number_format($orderDetail['total_amount'], 2) ?></p>
        </div>
        <div class="oda-section">
            <h4>Update Status</h4>
            <form method="POST">
                <input type="hidden" name="order_id" value="<?= $orderDetail['id'] ?>">
                <select name="status" class="status-select">
                    <?php foreach ($statuses as $st): ?>
                    <option value="<?= $st ?>"
                        <?= $orderDetail['status'] === $st ? 'selected' : '' ?>>
                        <?= $st ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="update_status" class="btn btn-primary" style="margin-top:10px">
                    Update Status
                </button>
            </form>
        </div>
    </div>

    <!-- Items -->
    <h4 style="margin:20px 0 10px">Ordered Items</h4>
    <table class="admin-table">
        <thead><tr><th>Product</th><th>Image</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr></thead>
        <tbody>
            <?php foreach ($orderItems as $oi): ?>
            <tr>
                <td><?= sanitize($oi['name']) ?></td>
                <td><img src="../assets/images/products/<?= sanitize($oi['image']) ?>"
                         style="height:40px;width:40px;object-fit:cover;border-radius:4px"
                         onerror="this.src='../assets/images/default.jpg'"></td>
                <td><?= $oi['quantity'] ?></td>
                <td>₹<?= number_format($oi['price'], 2) ?></td>
                <td>₹<?= number_format($oi['price'] * $oi['quantity'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" style="text-align:right"><strong>Total</strong></td>
                <td><strong>₹<?= number_format($orderDetail['total_amount'], 2) ?></strong></td>
            </tr>
        </tfoot>
    </table>
</div>

<?php else: ?>
<!-- ===== ORDERS LIST ===== -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3>Orders (<?= count($orders) ?>)</h3>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#ID</th><th>Customer</th><th>Amount</th>
                    <th>Status</th><th>Date</th><th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $o): ?>
                <tr>
                    <td>#<?= $o['id'] ?></td>
                    <td><?= sanitize($o['username']) ?></td>
                    <td>₹<?= number_format($o['total_amount'], 2) ?></td>
                    <td>
                        <span class="status-badge status-<?= strtolower($o['status']) ?>">
                            <?= $o['status'] ?>
                        </span>
                    </td>
                    <td><?= date('d M Y', strtotime($o['created_at'])) ?></td>
                    <td>
                        <a href="orders.php?view=<?= $o['id'] ?>"
                           class="btn btn-xs btn-primary">
                            <i class="fas fa-eye"></i> View
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($orders)): ?>
                <tr><td colspan="6" style="text-align:center;color:#888">No orders found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once 'includes/admin_footer.php'; ?>
