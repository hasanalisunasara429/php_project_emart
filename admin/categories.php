<?php
// admin/categories.php — Add/Edit/Delete categories
$pageTitle = 'Manage Categories';
require_once 'includes/admin_header.php';

$errors  = [];
$editing = null;

// DELETE
if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    $del = $conn->prepare("DELETE FROM categories WHERE id=?");
    $del->bind_param("i", $did);
    $del->execute();
    setFlash('success', 'Category deleted.');
    redirect('admin/categories.php');
}

// LOAD FOR EDIT
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $es  = $conn->prepare("SELECT * FROM categories WHERE id=? LIMIT 1");
    $es->bind_param("i", $eid);
    $es->execute();
    $editing = $es->get_result()->fetch_assoc();
}

// ADD / UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['category_name'] ?? '');
    $cid  = (int)($_POST['cat_id'] ?? 0);

    if (strlen($name) < 2) {
        $errors[] = 'Category name must be at least 2 characters.';
    } else {
        // Check duplicate
        $dup = $conn->prepare("SELECT id FROM categories WHERE category_name=? AND id!=?");
        $dup->bind_param("si", $name, $cid);
        $dup->execute();
        if ($dup->get_result()->num_rows > 0) {
            $errors[] = 'Category name already exists.';
        }
    }

    if (empty($errors)) {
        if ($cid) {
            $upd = $conn->prepare("UPDATE categories SET category_name=? WHERE id=?");
            $upd->bind_param("si", $name, $cid);
            $upd->execute();
            setFlash('success', 'Category updated.');
        } else {
            $ins = $conn->prepare("INSERT INTO categories (category_name) VALUES (?)");
            $ins->bind_param("s", $name);
            $ins->execute();
            setFlash('success', 'Category added.');
        }
        redirect('admin/categories.php');
    }
}

// Fetch all with product count
$categories = $conn->query(
    "SELECT c.*, COUNT(p.id) AS product_count
     FROM categories c LEFT JOIN products p ON p.category_id = c.id
     GROUP BY c.id ORDER BY c.category_name"
)->fetch_all(MYSQLI_ASSOC);
?>

<div class="admin-layout-two-col">
    <!-- Left: Add/Edit Form -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3><?= $editing ? 'Edit Category' : 'Add Category' ?></h3>
        </div>

        <?php if ($errors): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $e): ?><p><?= sanitize($e) ?></p><?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST" class="admin-form">
            <input type="hidden" name="cat_id" value="<?= $editing['id'] ?? 0 ?>">
            <div class="form-group">
                <label>Category Name *</label>
                <input type="text" name="category_name" required
                       value="<?= sanitize($editing['category_name'] ?? '') ?>"
                       placeholder="e.g. Electronics">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <?= $editing ? 'Update' : 'Add Category' ?>
                </button>
                <?php if ($editing): ?>
                <a href="categories.php" class="btn btn-outline">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Right: Category List -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3>All Categories (<?= count($categories) ?>)</h3>
        </div>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr><th>#</th><th>Name</th><th>Products</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $c): ?>
                    <tr>
                        <td><?= $c['id'] ?></td>
                        <td><?= sanitize($c['category_name']) ?></td>
                        <td>
                            <span class="badge"><?= $c['product_count'] ?></span>
                        </td>
                        <td class="action-btns">
                            <a href="categories.php?edit=<?= $c['id'] ?>"
                               class="btn btn-xs btn-primary">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="categories.php?delete=<?= $c['id'] ?>"
                               class="btn btn-xs btn-danger"
                               onclick="return confirm('Delete this category? Products will also be removed!')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/admin_footer.php'; ?>
