<?php
// api/cart.php — AJAX Cart API (add / update / remove / fetch)
session_start();
require_once '../includes/connection.php';

header('Content-Type: application/json');

// All responses as JSON
function respond($data) {
    echo json_encode($data);
    exit();
}

// Require login
if (!isLoggedIn()) {
    respond(['success' => false, 'message' => 'Please login first.',
             'redirect' => BASE_URL . 'user/login.php']);
}

$userId = (int)$_SESSION['user_id'];

// Parse JSON body for POST
$input = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw   = file_get_contents('php://input');
    $input = json_decode($raw, true) ?? $_POST;
}

$action = $input['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    // ---- Add or increment item in cart ----
    case 'add':
        $productId = (int)($input['product_id'] ?? 0);
        $qty       = max(1, (int)($input['quantity'] ?? 1));

        if (!$productId) respond(['success' => false, 'message' => 'Invalid product.']);

        // Check product stock
        $ps = $conn->prepare("SELECT stock FROM products WHERE id=? LIMIT 1");
        $ps->bind_param("i", $productId);
        $ps->execute();
        $prod = $ps->get_result()->fetch_assoc();

        if (!$prod || $prod['stock'] < 1) {
            respond(['success' => false, 'message' => 'Out of stock.']);
        }

        // Upsert: if exists, add qty; else insert
        $check = $conn->prepare(
            "SELECT id, quantity FROM cart WHERE user_id=? AND product_id=?"
        );
        $check->bind_param("ii", $userId, $productId);
        $check->execute();
        $existing = $check->get_result()->fetch_assoc();

        if ($existing) {
            $newQty = min($existing['quantity'] + $qty, $prod['stock']);
            $upd = $conn->prepare("UPDATE cart SET quantity=? WHERE id=?");
            $upd->bind_param("ii", $newQty, $existing['id']);
            $upd->execute();
        } else {
            $ins = $conn->prepare(
                "INSERT INTO cart (user_id, product_id, quantity) VALUES (?,?,?)"
            );
            $ins->bind_param("iii", $userId, $productId, $qty);
            $ins->execute();
        }

        respond(['success' => true,
                 'message'    => 'Added to cart!',
                 'cart_count' => getCartCount($conn)]);

    // ---- Update quantity of an item ----
    case 'update':
        $cartId = (int)($input['cart_id'] ?? 0);
        $qty    = max(1, (int)($input['quantity'] ?? 1));

        $upd = $conn->prepare(
            "UPDATE cart SET quantity=? WHERE id=? AND user_id=?"
        );
        $upd->bind_param("iii", $qty, $cartId, $userId);
        $upd->execute();

        // Return new subtotal for this item
        $row = $conn->prepare(
            "SELECT c.quantity, p.price FROM cart c
             JOIN products p ON c.product_id = p.id
             WHERE c.id=? AND c.user_id=?"
        );
        $row->bind_param("ii", $cartId, $userId);
        $row->execute();
        $r = $row->get_result()->fetch_assoc();

        respond(['success'    => true,
                 'subtotal'   => number_format(($r['quantity'] * $r['price']), 2),
                 'cart_count' => getCartCount($conn),
                 'cart_total' => getCartTotal($conn, $userId)]);

    // ---- Remove an item from cart ----
    case 'remove':
        $cartId = (int)($input['cart_id'] ?? 0);
        $del    = $conn->prepare("DELETE FROM cart WHERE id=? AND user_id=?");
        $del->bind_param("ii", $cartId, $userId);
        $del->execute();

        respond(['success'    => true,
                 'message'    => 'Item removed.',
                 'cart_count' => getCartCount($conn),
                 'cart_total' => getCartTotal($conn, $userId)]);

    // ---- Get full cart contents (for cart page render) ----
    case 'get':
        $stmt = $conn->prepare(
            "SELECT c.id AS cart_id, c.quantity,
                    p.id AS product_id, p.name, p.price, p.image, p.stock
             FROM cart c
             JOIN products p ON c.product_id = p.id
             WHERE c.user_id = ?"
        );
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        respond(['success' => true, 'items' => $items,
                 'cart_count' => getCartCount($conn)]);

    default:
        respond(['success' => false, 'message' => 'Invalid action.']);
}

// Helper: calculate total for user's cart
function getCartTotal($conn, $userId) {
    $stmt = $conn->prepare(
        "SELECT SUM(c.quantity * p.price) as total
         FROM cart c JOIN products p ON c.product_id=p.id
         WHERE c.user_id=?"
    );
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    return number_format($r['total'] ?? 0, 2);
}
