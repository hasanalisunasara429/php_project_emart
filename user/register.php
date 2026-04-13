<?php
// user/register.php — New user registration
session_start();
require_once '../includes/connection.php';

if (isLoggedIn()) redirect('index.php');

$errors = [];
$old    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Read & sanitize inputs
    $username = sanitize($_POST['username'] ?? '');
    $email    = sanitize($_POST['email'] ?? '');
    $mobile   = sanitize($_POST['mobile'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    $old = compact('username', 'email', 'mobile');

    // --- Validation ---
    if (strlen($username) < 3)
        $errors[] = 'Username must be at least 3 characters.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Invalid email address.';
    if (!preg_match('/^[0-9]{10}$/', $mobile))
        $errors[] = 'Mobile must be 10 digits.';
    if (strlen($password) < 8)
        $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm)
        $errors[] = 'Passwords do not match.';

    // --- Check duplicate email ---
    if (empty($errors)) {
        $dup = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $dup->bind_param("s", $email);
        $dup->execute();
        if ($dup->get_result()->num_rows > 0)
            $errors[] = 'Email is already registered. <a href="login.php">Login</a>';
    }

    // --- Insert user ---
    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $ins  = $conn->prepare(
            "INSERT INTO users (username, email, mobile, password) VALUES (?, ?, ?, ?)"
        );
        $ins->bind_param("ssss", $username, $email, $mobile, $hash);
        if ($ins->execute()) {
            setFlash('success', 'Account created! Please log in.');
            redirect('user/login.php');
        } else {
            $errors[] = 'Registration failed. Please try again.';
        }
    }
}

$pageTitle = 'Create Account';
include '../includes/header.php';
?>

<div class="auth-page">
    <div class="auth-card">
        <div class="auth-header">
            <h2>Create your account</h2>
            <p>Already have an account? <a href="login.php">Sign in</a></p>
        </div>

        <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul style="margin:0;padding-left:20px">
                <?php foreach ($errors as $e): ?>
                    <li><?= $e ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form method="POST" class="auth-form" novalidate>
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="username" required
                       value="<?= sanitize($old['username'] ?? '') ?>"
                       placeholder="John Doe">
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" required
                       value="<?= sanitize($old['email'] ?? '') ?>"
                       placeholder="john@example.com">
            </div>
            <div class="form-group">
                <label>Mobile Number</label>
                <input type="tel" name="mobile" required
                       value="<?= sanitize($old['mobile'] ?? '') ?>"
                       placeholder="10-digit mobile" maxlength="10">
            </div>
            <div class="form-group">
                <label>Password</label>
                <div class="input-eye">
                    <input type="password" name="password" id="regPass" required
                           placeholder="Min 8 characters">
                    <span class="eye-toggle" onclick="toggleEye('regPass',this)">
                        <i class="fas fa-eye"></i>
                    </span>
                </div>
            </div>
            <div class="form-group">
                <label>Confirm Password</label>
                <div class="input-eye">
                    <input type="password" name="confirm_password" id="confPass" required
                           placeholder="Re-enter password">
                    <span class="eye-toggle" onclick="toggleEye('confPass',this)">
                        <i class="fas fa-eye"></i>
                    </span>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-block">
                Create Account <i class="fas fa-user-plus"></i>
            </button>
        </form>

        <p class="auth-legal">
            By creating an account you agree to E-Mart's
            <a href="#">Terms</a> &amp; <a href="#">Privacy Policy</a>.
        </p>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
