<?php
// admin/dashboard.php — Admin dashboard with KPI cards + recent data
$pageTitle = 'Dashboard';
require_once 'includes/admin_header.php';

// ---- KPI stats ----
$totalUsers    = $conn->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetch_row()[0];
$totalProducts = $conn->query("SELECT COUNT(*) FROM products")->fetch_row()[0];
$totalOrders   = $conn->query("SELECT COUNT(*) FROM orders")->fetch_row()[0];
$totalRevenue  = $conn->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status!='Cancelled'")->fetch_row()[0];
$pendingOrders = $conn->query("SELECT COUNT(*) FROM orders WHERE status='Pending'")->fetch_row()[0];
$lowStock      = $conn->query("SELECT COUNT(*) FROM products WHERE stock < 5")->fetch_row()[0];

// ---- Monthly revenue (last 6 months for chart) ----
$monthlyRevenue = $conn->query(
    "SELECT DATE_FORMAT(created_at,'%b %Y') AS month,
            SUM(total_amount) AS revenue
     FROM orders
     WHERE status != 'Cancelled'
       AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
     GROUP BY YEAR(created_at), MONTH(created_at)
     ORDER BY created_at ASC"
)->fetch_all(MYSQLI_ASSOC);

// ---- Recent orders ----
$recentOrders = $conn->query(
    "SELECT o.*, u.username, u.email FROM orders o
     JOIN users u ON o.user_id = u.id
     ORDER BY o.created_at DESC LIMIT 8"
)->fetch_all(MYSQLI_ASSOC);

// ---- Top products by sales ----
$topProducts = $conn->query(
    "SELECT p.name, SUM(oi.quantity) AS sold
     FROM order_items oi JOIN products p ON oi.product_id = p.id
     GROUP BY oi.product_id ORDER BY sold DESC LIMIT 5"
)->fetch_all(MYSQLI_ASSOC);
?>

<!-- KPI Cards -->
<div class="kpi-grid">
    <div class="kpi-card blue">
        <div class="kpi-icon"><i class="fas fa-users"></i></div>
        <div class="kpi-info">
            <div class="kpi-value"><?= number_format($totalUsers) ?></div>
            <div class="kpi-label">Total Users</div>
        </div>
    </div>
    <div class="kpi-card green">
        <div class="kpi-icon"><i class="fas fa-box"></i></div>
        <div class="kpi-info">
            <div class="kpi-value"><?= number_format($totalProducts) ?></div>
            <div class="kpi-label">Products</div>
        </div>
    </div>
    <div class="kpi-card orange">
        <div class="kpi-icon"><i class="fas fa-shopping-bag"></i></div>
        <div class="kpi-info">
            <div class="kpi-value"><?= number_format($totalOrders) ?></div>
            <div class="kpi-label">Total Orders</div>
        </div>
    </div>
    <div class="kpi-card purple">
        <div class="kpi-icon"><i class="fas fa-rupee-sign"></i></div>
        <div class="kpi-info">
            <div class="kpi-value">₹<?= number_format($totalRevenue, 0) ?></div>
            <div class="kpi-label">Total Revenue</div>
        </div>
    </div>
    <div class="kpi-card red">
        <div class="kpi-icon"><i class="fas fa-clock"></i></div>
        <div class="kpi-info">
            <div class="kpi-value"><?= $pendingOrders ?></div>
            <div class="kpi-label">Pending Orders</div>
        </div>
    </div>
    <div class="kpi-card yellow">
        <div class="kpi-icon"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="kpi-info">
            <div class="kpi-value"><?= $lowStock ?></div>
            <div class="kpi-label">Low Stock Items</div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="dashboard-charts">
    <div class="chart-card">
        <h3>Monthly Revenue (Last 6 Months)</h3>
        <canvas id="revenueChart" height="120"></canvas>
    </div>
    <div class="chart-card">
        <h3>Top Selling Products</h3>
        <canvas id="topProductsChart" height="120"></canvas>
    </div>
</div>

<!-- Recent Orders Table -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3>Recent Orders</h3>
        <a href="orders.php" class="btn btn-sm btn-outline">View All</a>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#ID</th><th>Customer</th><th>Email</th>
                    <th>Amount</th><th>Status</th><th>Date</th><th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentOrders as $o): ?>
                <tr>
                    <td>#<?= $o['id'] ?></td>
                    <td><?= sanitize($o['username']) ?></td>
                    <td><?= sanitize($o['email']) ?></td>
                    <td>₹<?= number_format($o['total_amount'], 2) ?></td>
                    <td>
                        <span class="status-badge status-<?= strtolower($o['status']) ?>">
                            <?= $o['status'] ?>
                        </span>
                    </td>
                    <td><?= date('d M Y', strtotime($o['created_at'])) ?></td>
                    <td>
                        <a href="orders.php?view=<?= $o['id'] ?>"
                           class="btn btn-xs btn-primary">View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Chart.js CDN + init -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
// Revenue Chart
const revCtx = document.getElementById('revenueChart').getContext('2d');
new Chart(revCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($monthlyRevenue, 'month')) ?>,
        datasets: [{
            label: 'Revenue (₹)',
            data: <?= json_encode(array_column($monthlyRevenue, 'revenue')) ?>,
            backgroundColor: 'rgba(255,153,0,0.7)',
            borderColor: '#ff9900',
            borderWidth: 2,
            borderRadius: 6
        }]
    },
    options: { responsive:true, plugins:{legend:{display:false}} }
});

// Top Products Chart
const prodCtx = document.getElementById('topProductsChart').getContext('2d');
new Chart(prodCtx, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($topProducts, 'name')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($topProducts, 'sold')) ?>,
            backgroundColor: ['#ff9900','#232f3e','#37475a','#f90','#146eb4']
        }]
    },
    options: { responsive:true, plugins:{legend:{position:'bottom'}} }
});
</script>

<?php require_once 'includes/admin_footer.php'; ?>
