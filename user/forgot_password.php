<?php
// user/forgot_password.php — OTP-based password reset
session_start();
require_once '../includes/connection.php';
require_once '../includes/mailer.php';

$step    = $_SESSION['reset_step'] ?? 1;  // 1=email, 2=otp, 3=new password
$message = '';
$error   = '';

// ---- STEP 1: Submit email ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 1) {
    $email = sanitize($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email.';
    } else {
        $stmt = $conn->prepare("SELECT id, username FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user) {
            // Generate 6-digit OTP, valid 10 minutes
            $otp    = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

            $conn->prepare("UPDATE users SET otp=?, otp_expiry=? WHERE id=?")
                 ->execute()  // shortcut — use real prepared below:
            ;
            $upd = $conn->prepare("UPDATE users SET otp=?, otp_expiry=? WHERE id=?");
            $upd->bind_param("ssi", $otp, $expiry, $user['id']);
            $upd->execute();

            // Send OTP email
            $body = "<p>Hi <strong>{$user['username']}</strong>,</p>
                     <p>Your OTP to reset your E-Mart password is:</p>
                     <h2 style='letter-spacing:8px;color:#f90'>$otp</h2>
                     <p>This OTP expires in 10 minutes. Do not share it with anyone.</p>";
            sendMail($email, $user['username'], 'Password Reset OTP — ' . SITE_NAME, $body);

            $_SESSION['reset_email'] = $email;
            $_SESSION['reset_step']  = 2;
            $message = 'OTP sent to your email. Check your inbox.';
            $step = 2;
        } else {
            $error = 'No account found with that email.';
        }
    }
}

// ---- STEP 2: Verify OTP ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 2) {
    $otp   = sanitize($_POST['otp'] ?? '');
    $email = $_SESSION['reset_email'] ?? '';

    $stmt = $conn->prepare(
        "SELECT id FROM users WHERE email=? AND otp=? AND otp_expiry > NOW()"
    );
    $stmt->bind_param("ss", $email, $otp);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $_SESSION['reset_step'] = 3;
        $step = 3;
    } else {
        $error = 'Invalid or expired OTP. Please try again.';
    }
}

// ---- STEP 3: New password ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 3) {
    $pass    = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';
    $email   = $_SESSION['reset_email'] ?? '';

    if (strlen($pass) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($pass !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
        $upd  = $conn->prepare(
            "UPDATE users SET password=?, otp=NULL, otp_expiry=NULL WHERE email=?"
        );
        $upd->bind_param("ss", $hash, $email);
        $upd->execute();

        unset($_SESSION['reset_step'], $_SESSION['reset_email']);
        setFlash('success', 'Password reset successful! Please login.');
        redirect('user/login.php');
    }
}

$pageTitle = 'Forgot Password';
include '../includes/header.php';
?>

<div class="auth-page">
    <div class="auth-card">
        <div class="auth-header">
            <h2>Reset Password</h2>
            <!-- Step progress dots -->
            <div class="step-dots">
                <?php for ($i=1; $i<=3; $i++): ?>
                    <span class="step-dot <?= $i <= $step ? 'active' : '' ?>"><?= $i ?></span>
                    <?php if ($i < 3): ?><span class="step-line"></span><?php endif; ?>
                <?php endfor; ?>
            </div>
        </div>

        <?php if ($error): ?><div class="alert alert-danger"><?= sanitize($error) ?></div><?php endif; ?>
        <?php if ($message): ?><div class="alert alert-success"><?= sanitize($message) ?></div><?php endif; ?>

        <form method="POST" class="auth-form">
            <?php if ($step === 1): ?>
                <div class="form-group">
                    <label>Registered Email</label>
                    <input type="email" name="email" required placeholder="your@email.com">
                </div>
                <button type="submit" class="btn btn-primary btn-block">Send OTP</button>

            <?php elseif ($step === 2): ?>
                <p style="color:#555;margin-bottom:15px">
                    OTP sent to <strong><?= sanitize($_SESSION['reset_email']) ?></strong>
                </p>
                <div class="form-group">
                    <label>Enter 6-digit OTP</label>
                    <input type="text" name="otp" maxlength="6" required
                           placeholder="______" class="otp-input">
                </div>
                <button type="submit" class="btn btn-primary btn-block">Verify OTP</button>
                <a href="forgot_password.php" style="display:block;text-align:center;margin-top:10px">
                    ← Change email
                </a>

            <?php elseif ($step === 3): ?>
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="password" required placeholder="Min 8 characters">
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm" required placeholder="Re-enter new password">
                </div>
                <button type="submit" class="btn btn-primary btn-block">Reset Password</button>
            <?php endif; ?>
        </form>

        <p style="text-align:center;margin-top:15px">
            <a href="login.php">← Back to Login</a>
        </p>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
