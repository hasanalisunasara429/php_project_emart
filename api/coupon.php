<?php
// api/coupon.php — AJAX coupon validation
session_start();
require_once '../includes/connection.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Login required.']);
    exit();
}

$raw    = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$code   = strtoupper(sanitize($raw['code'] ?? ''));
$total  = (float)($raw['total'] ?? 0);

if (!$code) {
    echo json_encode(['success' => false, 'message' => 'Enter a coupon code.']);
    exit();
}

$stmt = $conn->prepare(
    "SELECT * FROM coupons
     WHERE code=? AND is_active=1
       AND expiry_date >= CURDATE()
       AND used_count < max_uses
     LIMIT 1"
);
$stmt->bind_param("s", $code);
$stmt->execute();
$coupon = $stmt->get_result()->fetch_assoc();

if (!$coupon) {
    echo json_encode(['success' => false, 'message' => 'Invalid or expired coupon.']);
    exit();
}

if ($total < $coupon['min_order']) {
    echo json_encode([
        'success' => false,
        'message' => "Minimum order ₹{$coupon['min_order']} required for this coupon."
    ]);
    exit();
}

// Calculate discount
if ($coupon['discount_type'] === 'percent') {
    $discount = round($total * $coupon['discount_value'] / 100, 2);
} else {
    $discount = min((float)$coupon['discount_value'], $total);
}

echo json_encode([
    'success'  => true,
    'discount' => $discount,
    'new_total'=> number_format($total - $discount, 2),
    'message'  => "Coupon applied! You save ₹{$discount}",
]);
