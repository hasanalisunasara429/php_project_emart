<?php
// api/wishlist.php — AJAX toggle wishlist
session_start();
require_once '../includes/connection.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Login required.',
                      'redirect' => BASE_URL . 'user/login.php']);
    exit();
}

$raw       = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$productId = (int)($raw['product_id'] ?? 0);
$userId    = (int)$_SESSION['user_id'];

if (!$productId) {
    echo json_encode(['success' => false, 'message' => 'Invalid product.']);
    exit();
}

// Check if already in wishlist
$check = $conn->prepare(
    "SELECT id FROM wishlist WHERE user_id=? AND product_id=?"
);
$check->bind_param("ii", $userId, $productId);
$check->execute();
$exists = $check->get_result()->fetch_assoc();

if ($exists) {
    // Remove
    $del = $conn->prepare("DELETE FROM wishlist WHERE user_id=? AND product_id=?");
    $del->bind_param("ii", $userId, $productId);
    $del->execute();
    echo json_encode(['success' => true, 'action' => 'removed', 'message' => 'Removed from wishlist.']);
} else {
    // Add
    $ins = $conn->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?,?)");
    $ins->bind_param("ii", $userId, $productId);
    $ins->execute();
    echo json_encode(['success' => true, 'action' => 'added', 'message' => 'Added to wishlist!']);
}
