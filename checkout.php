<?php
require 'db.php';
require 'security.php';
if (!isset($_SESSION['user_id']) || empty($_SESSION['cart'])) { header("Location: cart.php"); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("Location: cart.php"); exit; }
verifyCsrf();

$user_id = (int)$_SESSION['user_id'];
$cart    = array_values(array_unique(array_map('intval', $_SESSION['cart'])));

$pdo->beginTransaction();
try {
    $ids = implode(',', array_fill(0, count($cart), '?'));
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($ids) AND status = 'available' FOR UPDATE");
    $stmt->execute($cart);
    $products = $stmt->fetchAll();

    if (count($products) !== count($cart)) throw new Exception("Some items are no longer available.");

    $total = 0;
    foreach ($products as $p) $total += $p['price'];

    $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
    $stmt->execute([$user_id]);
    $balance = $stmt->fetchColumn();

    if ($balance < $total) throw new Exception("Insufficient balance.");

    $new_balance = $balance - $total;
    $pdo->prepare("UPDATE users SET balance = ?, total_spent = total_spent + ? WHERE id = ?")
        ->execute([$new_balance, $total, $user_id]);

    $stmtSold  = $pdo->prepare("UPDATE products SET status = 'sold' WHERE id = ?");
    $stmtOrder = $pdo->prepare("INSERT INTO orders (user_id, product_id, amount_paid) VALUES (?, ?, ?)");
    foreach ($products as $p) {
        $stmtSold->execute([$p['id']]);
        $stmtOrder->execute([$user_id, $p['id'], $p['price']]);
    }

    $pdo->commit();
    $_SESSION['balance'] = $new_balance;
    unset($_SESSION['cart']);
    header("Location: orders.php?success=1");
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    include 'header.php';
    echo "<div class='alert alert-error'>Checkout failed: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<a href='cart.php' class='btn'>Back to Cart</a>";
    include 'footer.php';
}