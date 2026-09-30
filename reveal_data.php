<?php
require 'db.php';
require 'security.php';
header('Content-Type: application/json');

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// Rate limit: 30 reveals per minute
rateLimit('reveal_' . $_SESSION['user_id'], 30, 60);

$order_id = (int)($_GET['id'] ?? 0);
if ($order_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid order']);
    exit;
}

// Verify ownership via SQL — user_id MUST match
$stmt = $pdo->prepare("
    SELECT p.description, p.title
    FROM orders o
    JOIN products p ON o.product_id = p.id
    WHERE o.id = ? AND o.user_id = ?
    LIMIT 1
");
$stmt->execute([$order_id, (int)$_SESSION['user_id']]);
$row = $stmt->fetch();

if (!$row) {
    // Generic message so attackers can't tell if order exists or not
    http_response_code(404);
    echo json_encode(['error' => 'Not found or unauthorized']);
    exit;
}

echo json_encode([
    'title'       => $row['title'],
    'description' => $row['description'] ?? '(no data)',
]);