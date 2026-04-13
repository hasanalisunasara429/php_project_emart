<?php
// user/login.php — User login with session
session_start();
require_once '../includes/connection.php';

if (isLoggedIn()) redirect('index.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = $conn->prepare(
            "SELECT id, username, password, role FROM users WHERE email = ? LIMIT 1"
        );
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);

            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];

            // Redirect admin vs user
            if ($user['role'] === 'admin') {
                redirect('admin/dashboard.php');
            } else {
                $goto = $_SESSION['redirect_after_login'] ?? 'index.php';
                unset($_SESSION['redirect_after_login']);
                redirect($goto);
            }
        } else {
            $error = 'Invalid email or password.';
        }
    }
}

$pageTitle = 'Sign In';
include '../includes/header.php';
?>

<div class="auth-page">
    <div class="auth-card">
        <div class="auth-header">
            <h2>Sign in to E-Mart</h2>
            <p>New here? <a href="register.php">Create account</a></p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger"><?= sanitize($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="auth-form">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" required
                       placeholder="Enter your email"
                       value="<?= sanitize($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>
                    Password
                    <a href="forgot_password.php" class="float-right">Forgot?</a>
                </label>
                <div class="input-eye">
                    <input type="password" name="password" id="loginPass" required
                           placeholder="Enter your password">
                    <span class="eye-toggle" onclick="toggleEye('loginPass',this)">
                        <i class="fas fa-eye"></i>
                    </span>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-block">
                Sign In <i class="fas fa-sign-in-alt"></i>
            </button>
        </form>

        <div class="auth-divider"><span>Demo credentials</span></div>
        <div class="demo-creds">
            <code>admin@emart.com</code> / <code>Admin@123</code> (Admin)<br>
            Register a new account for user access.
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
