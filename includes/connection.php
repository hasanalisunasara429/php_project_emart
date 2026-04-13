<?php
// ============================================================
// includes/connection.php
// Central database connection using MySQLi + environment config
// ============================================================

// --- Environment Settings ---
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // Change for production
define('DB_PASS', '');           // Change for production
define('DB_NAME', 'my_website_db');
define('BASE_URL', 'http://localhost/emart/');
define('SITE_NAME', 'E-Mart');
define('ADMIN_EMAIL', 'admin@emart.com');

// PHPMailer SMTP config (update with your real credentials)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USER', 'youremail@gmail.com');
define('SMTP_PASS', 'your_16_digit_app_password');
define('SMTP_PORT', 587);

// --- Establish Connection ---
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    // In production: log this instead of dying with details
    die(json_encode(['error' => 'Database connection failed. Please try later.']));
}

// Force UTF-8 encoding
$conn->set_charset('utf8mb4');

// ============================================================
// Utility: Sanitize input to prevent XSS
// ============================================================
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// ============================================================
// Utility: Redirect helper
// ============================================================
function redirect($url) {
    header("Location: " . BASE_URL . $url);
    exit();
}

// ============================================================
// Utility: Flash message (store in session, show once)
// ============================================================
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// ============================================================
// Utility: Check if user is logged in
// ============================================================
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// ============================================================
// Utility: Require login — redirect if not authenticated
// ============================================================
function requireLogin() {
    if (!isLoggedIn()) {
        redirect('user/login.php');
    }
}

// ============================================================
// Utility: Require admin — redirect if not admin
// ============================================================
function requireAdmin() {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        redirect('admin/login.php');
    }
}

// ============================================================
// Utility: Get cart item count for logged-in user
// ============================================================
function getCartCount($conn) {
    if (!isLoggedIn()) return 0;
    $uid  = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    return $res['total'] ?? 0;
}
