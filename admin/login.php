<?php
// admin/login.php — Admin login (separate from user login)
session_start();
require_once '../includes/connection.php';

// If already admin, redirect
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    redirect('admin/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare(
        "SELECT id, username, password, role FROM users
         WHERE email=? AND role='admin' LIMIT 1"
    );
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();

    if ($admin && password_verify($password, $admin['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id']  = $admin['id'];
        $_SESSION['username'] = $admin['username'];
        $_SESSION['role']     = 'admin';
        redirect('admin/dashboard.php');
    } else {
        $error = 'Invalid admin credentials.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login — E-Mart</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { display:flex; align-items:center; justify-content:center;
               min-height:100vh; background:#1a1a2e; }
        .admin-login-card {
            background:#fff; border-radius:12px; padding:40px;
            width:100%; max-width:400px; box-shadow:0 20px 60px rgba(0,0,0,.4);
        }
        .admin-login-card h2 { text-align:center; margin-bottom:8px; color:#1a1a2e; }
        .admin-login-card .subtitle { text-align:center; color:#888; margin-bottom:30px; }
        .admin-badge {
            display:block; text-align:center; font-size:3rem; margin-bottom:15px;
        }
    </style>
</head>
<body>
<div class="admin-login-card">
    <span class="admin-badge">🛡️</span>
    <h2>Admin Panel</h2>
    <p class="subtitle">E-Mart Administration</p>

    <?php if ($error): ?>
    <div class="alert alert-danger"><?= sanitize($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Admin Email</label>
            <input type="email" name="email" required
                   placeholder="admin@emart.com"
                   value="<?= sanitize($_POST['email'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required placeholder="Password">
        </div>
        <button type="submit" class="btn btn-primary btn-block">
            <i class="fas fa-lock"></i> Login to Admin Panel
        </button>
    </form>
    <p style="text-align:center;margin-top:15px">
        <a href="../index.php">← Back to Store</a>
    </p>
</div>
</body>
</html>
