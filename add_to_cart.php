<?php
require 'db.php';
require 'security.php';
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("Location: home.php"); exit; }
verifyCsrf();

$id = (int)($_POST['product_id'] ?? 0);
if ($id > 0) {
    // Confirm it exists and is available
    $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND status = 'available'");
    $stmt->execute([$id]);
    if ($stmt->fetch()) {
        if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
        if (!in_array($id, $_SESSION['cart'], true)) $_SESSION['cart'][] = $id;
    }
}
header("Location: cart.php");
exit;