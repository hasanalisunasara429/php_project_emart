<?php
// user/profile.php — View & edit user profile
session_start();
require_once '../includes/connection.php';
requireLogin();

$userId = (int)$_SESSION['user_id'];
$errors = [];
$success = '';

// Fetch current profile
$stmt = $conn->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $username = sanitize($_POST['username'] ?? '');
    $mobile   = sanitize($_POST['mobile'] ?? '');

    if (strlen($username) < 3) $errors[] = 'Name must be at least 3 characters.';
    if (!preg_match('/^[0-9]{10}$/', $mobile)) $errors[] = 'Mobile must be 10 digits.';

    if (empty($errors)) {
        $upd = $conn->prepare("UPDATE users SET username=?, mobile=? WHERE id=?");
        $upd->bind_param("ssi", $username, $mobile, $userId);
        $upd->execute();
        $_SESSION['username'] = $username;
        $success = 'Profile updated successfully!';
        $profile['username'] = $username;
        $profile['mobile']   = $mobile;
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!password_verify($current, $profile['password'])) {
        $errors[] = 'Current password is incorrect.';
    } elseif (strlen($new) < 8) {
        $errors[] = 'New password must be at least 8 characters.';
    } elseif ($new !== $confirm) {
        $errors[] = 'New passwords do not match.';
    } else {
        $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]);
        $upd  = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $upd->bind_param("si", $hash, $userId);
        $upd->execute();
        $success = 'Password changed successfully!';
    }
}

$pageTitle = 'My Profile';
include '../includes/header.php';
?>

<div class="container profile-page">
    <h1 class="page-title">My Profile</h1>

    <?php if ($errors): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $e): ?><p><?= sanitize($e) ?></p><?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="alert alert-success"><?= sanitize($success) ?></div>
    <?php endif; ?>

    <div class="profile-layout">

        <!-- Left sidebar -->
        <div class="profile-sidebar">
            <div class="profile-avatar">
                <?= strtoupper(substr($profile['username'], 0, 1)) ?>
            </div>
            <div class="profile-name"><?= sanitize($profile['username']) ?></div>
            <div class="profile-email"><?= sanitize($profile['email']) ?></div>
            <div class="profile-member">
                Member since <?= date('M Y', strtotime($profile['created_at'])) ?>
            </div>
            <div class="profile-links">
                <a href="orders.php"><i class="fas fa-box"></i> My Orders</a>
                <a href="wishlist.php"><i class="fas fa-heart"></i> Wishlist</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>

        <!-- Right: forms -->
        <div class="profile-forms">

            <!-- Update Profile -->
            <div class="profile-card">
                <h3>Update Profile</h3>
                <form method="POST">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="username" required
                               value="<?= sanitize($profile['username']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Email <small>(cannot change)</small></label>
                        <input type="email" value="<?= sanitize($profile['email']) ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Mobile</label>
                        <input type="tel" name="mobile" maxlength="10" required
                               value="<?= sanitize($profile['mobile']) ?>">
                    </div>
                    <button type="submit" name="update_profile" class="btn btn-primary">
                        Save Changes
                    </button>
                </form>
            </div>

            <!-- Change Password -->
            <div class="profile-card">
                <h3>Change Password</h3>
                <form method="POST">
                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" name="current_password" required
                               placeholder="Enter current password">
                    </div>
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" required
                               placeholder="Min 8 characters">
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" required
                               placeholder="Repeat new password">
                    </div>
                    <button type="submit" name="change_password" class="btn btn-primary">
                        Change Password
                    </button>
                </form>
            </div>

        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
