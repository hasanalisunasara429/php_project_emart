<?php
// admin/coupons.php — Manage discount coupons
$pageTitle = 'Manage Coupons';
require_once 'includes/admin_header.php';

$errors  = [];
$editing = null;

// DELETE
if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    $conn->prepare("DELETE FROM coupons WHERE id=?")->bind_param("i",$did);
    $d = $conn->prepare("DELETE FROM coupons WHERE id=?");
    $d->bind_param("i", $did);
    $d->execute();
    setFlash('success', 'Coupon deleted.');
    redirect('admin/coupons.php');
}

// TOGGLE ACTIVE
if (isset($_GET['toggle'])) {
    $tid = (int)$_GET['toggle'];
    $conn->query("UPDATE coupons SET is_active = NOT is_active WHERE id=$tid");
    redirect('admin/coupons.php');
}

// EDIT LOAD
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $es  = $conn->prepare("SELECT * FROM coupons WHERE id=? LIMIT 1");
    $es->bind_param("i", $eid);
    $es->execute();
    $editing = $es->get_result()->fetch_assoc();
}

// ADD / UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code           = strtoupper(sanitize($_POST['code'] ?? ''));
    $discount_type  = sanitize($_POST['discount_type'] ?? 'percent');
    $discount_value = (float)($_POST['discount_value'] ?? 0);
    $min_order      = (float)($_POST['min_order'] ?? 0);
    $max_uses       = (int)($_POST['max_uses'] ?? 100);
    $expiry_date    = sanitize($_POST['expiry_date'] ?? '');
    $cid            = (int)($_POST['coupon_id'] ?? 0);

    if (strlen($code) < 3) $errors[] = 'Coupon code must be at least 3 characters.';
    if ($discount_value <= 0) $errors[] = 'Discount value must be positive.';
    if (!$expiry_date) $errors[] = 'Expiry date is required.';
    if ($discount_type === 'percent' && $discount_value > 100) $errors[] = 'Percent discount cannot exceed 100.';

    if (empty($errors)) {
        if ($cid) {
            $upd = $conn->prepare(
                "UPDATE coupons SET code=?,discount_type=?,discount_value=?,
                 min_order=?,max_uses=?,expiry_date=? WHERE id=?"
            );
            $upd->bind_param("ssddiis", $code,$discount_type,$discount_value,$min_order,$max_uses,$expiry_date,$cid);
            $upd->execute();
            setFlash('success', 'Coupon updated.');
        } else {
            // Check duplicate code
            $dup = $conn->prepare("SELECT id FROM coupons WHERE code=?");
            $dup->bind_param("s",$code);
            $dup->execute();
            if ($dup->get_result()->num_rows > 0) {
                $errors[] = 'Coupon code already exists.';
            } else {
                $ins = $conn->prepare(
                    "INSERT INTO coupons (code,discount_type,discount_value,min_order,max_uses,expiry_date)
                     VALUES (?,?,?,?,?,?)"
                );
                $ins->bind_param("ssddis", $code,$discount_type,$discount_value,$min_order,$max_uses,$expiry_date);
                $ins->execute();
                setFlash('success', 'Coupon created.');
            }
        }
        if (empty($errors)) redirect('admin/coupons.php');
    }
}

$coupons = $conn->query("SELECT * FROM coupons ORDER BY created_at DESC 2>/dev/null")->fetch_all(MYSQLI_ASSOC) ?? [];
// Safe fallback
$coupons = $conn->query("SELECT * FROM coupons ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
?>

<div class="admin-layout-two-col">

    <!-- Form -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3><?= $editing ? 'Edit Coupon' : 'Create Coupon' ?></h3>
            <?php if ($editing): ?>
            <a href="coupons.php" class="btn btn-sm btn-outline">+ New</a>
            <?php endif; ?>
        </div>

        <?php if ($errors): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $e): ?><p><?= sanitize($e) ?></p><?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST" class="admin-form">
            <input type="hidden" name="coupon_id" value="<?= $editing['id'] ?? 0 ?>">
            <div class="form-group">
                <label>Coupon Code *</label>
                <input type="text" name="code" required style="text-transform:uppercase"
                       value="<?= sanitize($editing['code'] ?? '') ?>"
                       placeholder="SAVE10">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Discount Type</label>
                    <select name="discount_type">
                        <option value="percent" <?= ($editing['discount_type']??'percent')==='percent'?'selected':'' ?>>Percentage (%)</option>
                        <option value="fixed"   <?= ($editing['discount_type']??'')==='fixed'?'selected':'' ?>>Fixed Amount (₹)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Discount Value *</label>
                    <input type="number" name="discount_value" min="1" step="0.01" required
                           value="<?= $editing['discount_value'] ?? '' ?>"
                           placeholder="e.g. 10 or 100">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Min Order Amount (₹)</label>
                    <input type="number" name="min_order" min="0" step="0.01"
                           value="<?= $editing['min_order'] ?? 0 ?>">
                </div>
                <div class="form-group">
                    <label>Max Uses</label>
                    <input type="number" name="max_uses" min="1"
                           value="<?= $editing['max_uses'] ?? 100 ?>">
                </div>
            </div>
            <div class="form-group">
                <label>Expiry Date *</label>
                <input type="date" name="expiry_date" required
                       value="<?= $editing['expiry_date'] ?? '' ?>"
                       min="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <?= $editing ? 'Update Coupon' : 'Create Coupon' ?>
                </button>
                <?php if ($editing): ?>
                <a href="coupons.php" class="btn btn-outline">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Table -->
    <div class="admin-card">
        <div class="admin-card-header"><h3>All Coupons</h3></div>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr><th>Code</th><th>Type</th><th>Value</th><th>Used</th><th>Expiry</th><th>Active</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($coupons as $c): ?>
                    <tr class="<?= strtotime($c['expiry_date']) < time() ? 'row-danger' : '' ?>">
                        <td><strong><?= sanitize($c['code']) ?></strong></td>
                        <td><?= $c['discount_type'] === 'percent' ? '%' : '₹' ?></td>
                        <td><?= $c['discount_type'] === 'percent' ? $c['discount_value'].'%' : '₹'.$c['discount_value'] ?></td>
                        <td><?= $c['used_count'] ?>/<?= $c['max_uses'] ?></td>
                        <td><?= date('d M Y', strtotime($c['expiry_date'])) ?></td>
                        <td>
                            <a href="coupons.php?toggle=<?= $c['id'] ?>"
                               class="toggle-switch <?= $c['is_active'] ? 'on' : 'off' ?>">
                                <?= $c['is_active'] ? 'ON' : 'OFF' ?>
                            </a>
                        </td>
                        <td class="action-btns">
                            <a href="coupons.php?edit=<?= $c['id'] ?>"
                               class="btn btn-xs btn-primary"><i class="fas fa-edit"></i></a>
                            <a href="coupons.php?delete=<?= $c['id'] ?>"
                               class="btn btn-xs btn-danger"
                               onclick="return confirm('Delete coupon?')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($coupons)): ?>
                    <tr><td colspan="7" style="text-align:center;color:#888">No coupons yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/admin_footer.php'; ?>
