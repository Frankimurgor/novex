<?php
require 'db.php';
require 'security.php';
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

$cart_items = [];
$total = 0;
if (!empty($_SESSION['cart'])) {
    $ids = implode(',', array_fill(0, count($_SESSION['cart']), '?'));
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($ids)");
    $stmt->execute($_SESSION['cart']);
    $cart_items = $stmt->fetchAll();
    foreach ($cart_items as $i) $total += $i['price'];
}

$balance = $_SESSION['balance'];
$needed = max(0, $total - $balance);
include 'header.php';
?>

<h1 class="page-title">Your Cart</h1>
<p class="page-subtitle">Review items before checkout.</p>

<?php if (empty($cart_items)): ?>
    <div class="card" style="text-align: center; padding: 60px;">
        <div style="font-size: 42px; margin-bottom: 10px;">🛒</div>
        <h3 style="color: #6b7280;">Your cart is empty</h3>
        <a href="home.php" class="btn" style="margin-top: 14px;">Browse Store</a>
    </div>
<?php else: ?>
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">

        <div>
            <?php foreach ($cart_items as $item): ?>
                <div class="card" style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h4 style="margin-bottom: 4px;"><?= htmlspecialchars($item['title']) ?></h4>
                        <span style="font-size: 12px; color: #6b7280;">Value: $<?= number_format($item['balance_amount'], 2) ?></span>
                    </div>
                    <div style="text-align: right;">
                        <div style="color: #22d3ee; font-weight: 800; font-size: 17px;">$<?= number_format($item['price'], 2) ?></div>
                        <a href="remove_from_cart.php?id=<?= (int)$item['id'] ?>" style="color: #ef4444; font-size: 11px;">Remove</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div>
            <div class="card">
                <h3 style="margin-bottom: 14px;">Order Summary</h3>
                <div style="display: flex; justify-content: space-between; padding: 8px 0;">
                    <span style="color: #94a3b8; font-size: 13px;">Subtotal</span>
                    <span style="color: #e2e8f0; font-weight: 600;">$<?= number_format($total, 2) ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 8px 0; border-top: 1px solid #1a2332;">
                    <span style="color: #94a3b8; font-size: 13px;">Your Balance</span>
                    <span style="color: #10b981; font-weight: 600;">$<?= number_format($balance, 2) ?></span>
                </div>

                <?php if ($needed > 0): ?>
                    <div class="alert alert-warning" style="margin-top: 12px; font-size: 12px;">
                        ⚠ You need $<?= number_format($needed, 2) ?> more to complete this purchase.
                    </div>
                    <a href="topup.php" class="btn btn-block btn-gold">💳 Top Up Balance</a>
                <?php else: ?>
                    <form action="checkout.php" method="POST" style="margin-top: 12px;">
                        <?= csrfField() ?>
                        <button type="submit" class="btn btn-block btn-success">✓ Complete Purchase</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include 'footer.php'; ?>