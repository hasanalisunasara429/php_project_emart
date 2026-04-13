<?php
// admin/products.php — Full CRUD for products with image upload
$pageTitle = 'Manage Products';
require_once 'includes/admin_header.php';

$errors  = [];
$editing = null;

// ---- DELETE ----
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    // Get image to delete file
    $img = $conn->prepare("SELECT image FROM products WHERE id=?");
    $img->bind_param("i", $del_id);
    $img->execute();
    $row = $img->get_result()->fetch_assoc();
    if ($row && $row['image'] !== 'default.jpg') {
        $path = '../assets/images/products/' . $row['image'];
        if (file_exists($path)) unlink($path);
    }
    $conn->prepare("DELETE FROM products WHERE id=?")->bind_param("i", $del_id) && true;
    $d = $conn->prepare("DELETE FROM products WHERE id=?");
    $d->bind_param("i", $del_id);
    $d->execute();
    setFlash('success', 'Product deleted.');
    redirect('admin/products.php');
}

// ---- LOAD FOR EDIT ----
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $es = $conn->prepare("SELECT * FROM products WHERE id=? LIMIT 1");
    $es->bind_param("i", $edit_id);
    $es->execute();
    $editing = $es->get_result()->fetch_assoc();
}

// ---- ADD / UPDATE ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = sanitize($_POST['name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $price       = (float)($_POST['price'] ?? 0);
    $category_id = (int)($_POST['category_id'] ?? 0);
    $stock       = (int)($_POST['stock'] ?? 0);
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $product_id  = (int)($_POST['product_id'] ?? 0);

    if (strlen($name) < 2)    $errors[] = 'Product name is required.';
    if ($price <= 0)           $errors[] = 'Price must be greater than 0.';
    if (!$category_id)         $errors[] = 'Select a category.';
    if ($stock < 0)            $errors[] = 'Stock cannot be negative.';

    // Handle image upload
    $imageName = $editing['image'] ?? 'default.jpg';
    if (!empty($_FILES['image']['name'])) {
        $allowed   = ['jpg','jpeg','png','webp'];
        $ext       = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $maxSize   = 2 * 1024 * 1024; // 2MB

        if (!in_array($ext, $allowed)) {
            $errors[] = 'Image must be JPG, PNG, or WebP.';
        } elseif ($_FILES['image']['size'] > $maxSize) {
            $errors[] = 'Image must be under 2MB.';
        } else {
            $imageName = uniqid('prod_') . '.' . $ext;
            $dest      = '../assets/images/products/' . $imageName;
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
                $errors[] = 'Image upload failed. Check folder permissions.';
                $imageName = $editing['image'] ?? 'default.jpg';
            }
        }
    }

    if (empty($errors)) {
        if ($product_id) {
            // UPDATE
            $upd = $conn->prepare(
                "UPDATE products SET name=?,description=?,price=?,category_id=?,
                 image=?,stock=?,is_featured=? WHERE id=?"
            );
            $upd->bind_param("ssdisiii", $name, $description, $price,
                             $category_id, $imageName, $stock, $is_featured, $product_id);
            $upd->execute();
            setFlash('success', 'Product updated successfully.');
        } else {
            // INSERT
            $ins = $conn->prepare(
                "INSERT INTO products (name,description,price,category_id,image,stock,is_featured)
                 VALUES (?,?,?,?,?,?,?)"
            );
            $ins->bind_param("ssdisii", $name, $description, $price,
                             $category_id, $imageName, $stock, $is_featured);
            $ins->execute();
            setFlash('success', 'Product added successfully.');
        }
        redirect('admin/products.php');
    }
}

// ---- Fetch all products ----
$search = sanitize($_GET['search'] ?? '');
$filter = "WHERE 1=1" . ($search ? " AND p.name LIKE '%" . $conn->real_escape_string($search) . "%'" : '');
$products = $conn->query(
    "SELECT p.*, c.category_name FROM products p
     JOIN categories c ON p.category_id = c.id
     $filter ORDER BY p.created_at DESC"
)->fetch_all(MYSQLI_ASSOC);

$categories = $conn->query("SELECT * FROM categories ORDER BY category_name")->fetch_all(MYSQLI_ASSOC);
?>

<!-- ADD/EDIT Form -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3><?= $editing ? 'Edit Product' : 'Add New Product' ?></h3>
        <?php if ($editing): ?>
            <a href="products.php" class="btn btn-sm btn-outline">+ Add New</a>
        <?php endif; ?>
    </div>

    <?php if ($errors): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $e): ?><p><?= sanitize($e) ?></p><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="admin-form">
        <input type="hidden" name="product_id" value="<?= $editing['id'] ?? 0 ?>">

        <div class="form-row">
            <div class="form-group">
                <label>Product Name *</label>
                <input type="text" name="name" required
                       value="<?= sanitize($editing['name'] ?? '') ?>"
                       placeholder="e.g. Samsung Galaxy S24">
            </div>
            <div class="form-group">
                <label>Category *</label>
                <select name="category_id" required>
                    <option value="">-- Select Category --</option>
                    <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id'] ?>"
                        <?= ($editing['category_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                        <?= sanitize($c['category_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Price (₹) *</label>
                <input type="number" name="price" min="1" step="0.01" required
                       value="<?= $editing['price'] ?? '' ?>" placeholder="999.00">
            </div>
            <div class="form-group">
                <label>Stock Quantity *</label>
                <input type="number" name="stock" min="0" required
                       value="<?= $editing['stock'] ?? 0 ?>">
            </div>
        </div>

        <div class="form-group">
            <label>Description</label>
            <textarea name="description" rows="4"
                      placeholder="Product description..."><?= sanitize($editing['description'] ?? '') ?></textarea>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Product Image (JPG/PNG/WebP, max 2MB)</label>
                <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp">
                <?php if (!empty($editing['image']) && $editing['image'] !== 'default.jpg'): ?>
                <div style="margin-top:8px">
                    <img src="../assets/images/products/<?= sanitize($editing['image']) ?>"
                         style="height:60px;border-radius:6px">
                    <small>Current image</small>
                </div>
                <?php endif; ?>
            </div>
            <div class="form-group" style="display:flex;align-items:center;gap:10px;padding-top:30px">
                <label class="toggle-label">
                    <input type="checkbox" name="is_featured"
                           <?= ($editing['is_featured'] ?? 0) ? 'checked' : '' ?>>
                    <span>Feature on Homepage</span>
                </label>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i>
                <?= $editing ? 'Update Product' : 'Add Product' ?>
            </button>
            <?php if ($editing): ?>
            <a href="products.php" class="btn btn-outline">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Products Table -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3>All Products (<?= count($products) ?>)</h3>
        <form method="GET" class="table-search-form">
            <input type="text" name="search" value="<?= sanitize($search) ?>"
                   placeholder="Search products…">
            <button type="submit" class="btn btn-sm btn-primary">Search</button>
            <?php if ($search): ?>
            <a href="products.php" class="btn btn-sm btn-outline">Clear</a>
            <?php endif; ?>
        </form>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th><th>Image</th><th>Name</th><th>Category</th>
                    <th>Price</th><th>Stock</th><th>Featured</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $p): ?>
                <tr class="<?= $p['stock'] == 0 ? 'row-danger' : ($p['stock'] < 5 ? 'row-warning' : '') ?>">
                    <td>#<?= $p['id'] ?></td>
                    <td>
                        <img src="../assets/images/products/<?= sanitize($p['image']) ?>"
                             style="width:45px;height:45px;object-fit:cover;border-radius:6px"
                             onerror="this.src='../assets/images/default.jpg'">
                    </td>
                    <td><?= sanitize($p['name']) ?></td>
                    <td><?= sanitize($p['category_name']) ?></td>
                    <td>₹<?= number_format($p['price'], 2) ?></td>
                    <td>
                        <span class="stock-badge <?= $p['stock']==0?'danger':($p['stock']<5?'warning':'ok') ?>">
                            <?= $p['stock'] ?>
                        </span>
                    </td>
                    <td><?= $p['is_featured'] ? '⭐' : '—' ?></td>
                    <td class="action-btns">
                        <a href="products.php?edit=<?= $p['id'] ?>"
                           class="btn btn-xs btn-primary">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <a href="products.php?delete=<?= $p['id'] ?>"
                           class="btn btn-xs btn-danger"
                           onclick="return confirm('Delete this product?')">
                            <i class="fas fa-trash"></i> Delete
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($products)): ?>
                <tr><td colspan="8" style="text-align:center;color:#888">No products found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/admin_footer.php'; ?>
