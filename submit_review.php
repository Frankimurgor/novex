<?php
require 'db.php';
require 'security.php';
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("Location: home.php"); exit; }
verifyCsrf();
rateLimit('review_' . $_SESSION['user_id'], 10, 3600);

$product_id = (int)($_POST['product_id'] ?? 0);
$rating     = (int)($_POST['rating'] ?? 0);
$title      = cleanString($_POST['title'] ?? '', 200);
$body       = cleanString($_POST['body'] ?? '', 2000);

// Validate
if ($product_id <= 0) { header("Location: home.php"); exit; }
if ($rating < 1 || $rating > 5) {
    header("Location: product.php?id=$product_id&review_error=rating");
    exit;
 }
if (strlen($body) < 10) {
    header("Location: product.php?id=$product_id&review_error=body");
    exit;
}

// Verify user purchased this product
$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND product_id = ?");
$stmt->execute([(int)$_SESSION['user_id'], $product_id]);
if ((int)$stmt->fetchColumn() === 0) {
    header("Location: product.php?id=$product_id&review_error=not_bought");
    exit;
}

// Insert review (fail silently if already reviewed)
try {
    $pdo->prepare("INSERT INTO reviews (product_id, user_id, rating, title, body) VALUES (?, ?, ?, ?, ?)")
        ->execute([$product_id, (int)$_SESSION['user_id'], $rating, $title ?: null, $body]);
    header("Location: product.php?id=$product_id&review_posted=1#reviews");
    exit;
} catch (PDOException $e) {
    header("Location: product.php?id=$product_id&review_error=already_reviewed#reviews");
    exit;
}