<?php
// admin/users.php — View all registered users
$pageTitle = 'Manage Users';
require_once 'includes/admin_header.php';

$search = sanitize($_GET['search'] ?? '');
$filter = $search
    ? "WHERE role='user' AND (username LIKE '%{$conn->real_escape_string($search)}%'
       OR email LIKE '%{$conn->real_escape_string($search)}%')"
    : "WHERE role='user'";

$users = $conn->query(
    "SELECT u.*,
            (SELECT COUNT(*) FROM orders WHERE user_id=u.id) AS order_count,
            (SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE user_id=u.id AND status!='Cancelled') AS total_spent
     FROM users u $filter ORDER BY u.created_at DESC"
)->fetch_all(MYSQLI_ASSOC);
?>

<div class="admin-card">
    <div class="admin-card-header">
        <h3>Registered Users (<?= count($users) ?>)</h3>
        <form method="GET" class="table-search-form">
            <input type="text" name="search" value="<?= sanitize($search) ?>"
                   placeholder="Search by name or email…">
            <button type="submit" class="btn btn-sm btn-primary">Search</button>
            <?php if ($search): ?>
            <a href="users.php" class="btn btn-sm btn-outline">Clear</a>
            <?php endif; ?>
        </form>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th><th>Name</th><th>Email</th><th>Mobile</th>
                    <th>Orders</th><th>Total Spent</th><th>Joined</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= $u['id'] ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px">
                            <div class="user-avatar-sm">
                                <?= strtoupper(substr($u['username'],0,1)) ?>
                            </div>
                            <?= sanitize($u['username']) ?>
                        </div>
                    </td>
                    <td><?= sanitize($u['email']) ?></td>
                    <td><?= sanitize($u['mobile']) ?></td>
                    <td><?= $u['order_count'] ?></td>
                    <td>₹<?= number_format($u['total_spent'], 2) ?></td>
                    <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($users)): ?>
                <tr><td colspan="7" style="text-align:center;color:#888">No users found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/admin_footer.php'; ?>
