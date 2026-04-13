<?php
// api/search.php — AJAX live search API
session_start();
require_once '../includes/connection.php';

header('Content-Type: application/json');

$q = sanitize($_GET['q'] ?? '');

if (strlen($q) < 2) {
    echo json_encode(['results' => []]);
    exit();
}

$keyword = "%$q%";

$stmt = $conn->prepare(
    "SELECT p.id, p.name, p.price, p.image, c.category_name
     FROM products p
     JOIN categories c ON p.category_id = c.id
     WHERE (p.name LIKE ? OR c.category_name LIKE ?)
       AND p.stock > 0
     LIMIT 8"
);
$stmt->bind_param("ss", $keyword, $keyword);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Build response
$results = array_map(function ($r) {
    return [
        'id'            => $r['id'],
        'name'          => htmlspecialchars($r['name'], ENT_QUOTES),
        'price'         => '₹' . number_format($r['price'], 2),
        'category_name' => htmlspecialchars($r['category_name'], ENT_QUOTES),
        'image'         => BASE_URL . 'assets/images/products/' . htmlspecialchars($r['image']),
        'url'           => BASE_URL . 'user/viewproduct.php?id=' . $r['id'],
    ];
}, $rows);

echo json_encode(['results' => $results]);
