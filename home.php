<?php
require 'db.php';
require 'security.php';
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

$stmt = $pdo->prepare("SELECT balance, role, total_spent FROM users WHERE id = ?");
$stmt->execute([(int)$_SESSION['user_id']]);
$u = $stmt->fetch();
if (!$u) { session_destroy(); header("Location: index.php"); exit; }
$_SESSION['balance']     = $u['balance'];
$_SESSION['role']        = $u['role'];
$_SESSION['total_spent'] = $u['total_spent'];

$total_orders = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
$total_orders->execute([(int)$_SESSION['user_id']]);
$total_orders = (int)$total_orders->fetchColumn();

$items_available = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE status = 'available'")->fetchColumn();

$pending = $pdo->prepare("SELECT COUNT(*) FROM deposits WHERE user_id = ? AND status = 'pending'");
$pending->execute([(int)$_SESSION['user_id']]);
$pending_deposits = (int)$pending->fetchColumn();

list($tier, $tier_color) = userTier($_SESSION['total_spent']);
$news = $pdo->query("SELECT * FROM news ORDER BY created_at DESC LIMIT 3")->fetchAll();
$recent = $pdo->query("SELECT p.*, c.name AS cat_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.status='available' ORDER BY p.id DESC LIMIT 8")->fetchAll();

include 'header.php';
?>

<div class="card" style="background: linear-gradient(135deg, #0d1420 0%, #131c2e 100%); border-color: #22d3ee44;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
        <div style="flex: 1; min-width: 260px;">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px;">
                <span style="color: #22d3ee; font-size: 11px; letter-spacing: 2px; text-transform: uppercase;">● Secure Connection</span>
            </div>
            <h1 style="font-size: 28px; margin-bottom: 6px;">Welcome back, <?= htmlspecialchars($_SESSION['username']) ?></h1>
            <p style="color: #94a3b8; font-size: 14px; line-height: 1.6;">
                Your command center for premium digital products. Browse verified listings, track orders, and top up in minutes.
            </p>
            <div style="margin-top: 14px; display: flex; align-items: center; gap: 10px;">
                <span class="badge" style="background: <?= $tier_color ?>22; color: <?= $tier_color ?>;">
                    🏅 <?= $tier ?> Member
                </span>
                <span style="color: #6b7280; font-size: 12px;">$<?= number_format($_SESSION['total_spent'], 2) ?> spent total</span>
            </div>
        </div>
        <div style="text-align: right;">
            <div style="font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: 1px;">Wallet Balance</div>
            <div style="font-size: 34px; font-weight: 800; color: #10b981; margin: 6px 0;">$<?= number_format($_SESSION['balance'], 2) ?></div>
            <a href="topup.php" class="btn btn-gold btn-sm">💳 Add Funds</a>
        </div>
    </div>
</div>

<div class="stat-grid" style="margin-bottom: 22px;">
    <div class="stat-card cyan">
        <div class="stat-label">Available Balance</div>
        <div class="stat-value">$<?= number_format($_SESSION['balance'], 2) ?></div>
    </div>
    <div class="stat-card green">
        <div class="stat-label">Total Orders</div>
        <div class="stat-value"><?= $total_orders ?></div>
    </div>
    <div class="stat-card purple">
        <div class="stat-label">Market Items</div>
        <div class="stat-value"><?= $items_available ?></div>
    </div>
    <div class="stat-card yellow">
        <div class="stat-label">Pending Deposits</div>
        <div class="stat-value"><?= $pending_deposits ?></div>
    </div>
</div>

<h3 style="color: #22d3ee; font-size: 13px; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 14px;">// Quick Actions</h3>
<div class="grid" style="margin-bottom: 30px;">
    <a href="topup.php" class="card card-hover" style="text-decoration: none; color: inherit;">
        <div style="font-size: 26px; margin-bottom: 10px;">💳</div>
        <h3 style="margin-bottom: 4px;">Fund Account</h3>
        <p style="color: #6b7280; font-size: 12.5px;">Add balance via crypto</p>
    </a>
    <a href="orders.php" class="card card-hover" style="text-decoration: none; color: inherit;">
        <div style="font-size: 26px; margin-bottom: 10px;">📦</div>
        <h3 style="margin-bottom: 4px;">My Orders</h3>
        <p style="color: #6b7280; font-size: 12.5px;">View purchase history</p>
    </a>
    <a href="cart.php" class="card card-hover" style="text-decoration: none; color: inherit;">
        <div style="font-size: 26px; margin-bottom: 10px;">🛒</div>
        <h3 style="margin-bottom: 4px;">Open Cart</h3>
        <p style="color: #6b7280; font-size: 12.5px;">Complete pending purchase</p>
    </a>
</div>

<h3 style="color: #22d3ee; font-size: 13px; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 14px;">// Latest News</h3>
<?php foreach ($news as $n): ?>
    <div class="news-card">
        <div style="display: flex; gap: 14px;">
            <div class="news-icon"><?= htmlspecialchars($n['icon']) ?></div>
            <div style="flex: 1;">
                <h3><?= htmlspecialchars($n['title']) ?></h3>
                <p><?= nl2br(htmlspecialchars($n['body'])) ?></p>
                <div class="news-footer">
                    <span>◉ <?= date("d-m-Y H:i", strtotime($n['created_at'])) ?></span>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<h3 style="color: #22d3ee; font-size: 13px; letter-spacing: 2px; text-transform: uppercase; margin: 30px 0 14px;">// Recently Added</h3>
<div class="grid">
    <?php foreach ($recent as $p): ?>
        <a href="product.php?id=<?= (int)$p['id'] ?>" class="card card-hover" style="text-decoration: none; color: inherit; display: block;">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px;">
                <span class="badge badge-green">● Available</span>
                <span class="badge badge-cyan"><?= htmlspecialchars($p['account_type'] ?? 'Digital') ?></span>
            </div>
            <h3 style="font-size: 15px; margin-bottom: 8px; color: #fff;"><?= htmlspecialchars($p['title']) ?></h3>
            <p style="color: #6b7280; font-size: 11.5px; margin-bottom: 10px;"><?= htmlspecialchars($p['cat_name'] ?? 'General') ?></p>
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="color: #22d3ee; font-size: 20px; font-weight: 800;">$<?= number_format($p['price'], 2) ?></span>
                <span class="btn btn-sm">View</span>
            </div>
        </a>
    <?php endforeach; ?>
</div>

<?php include 'footer.php'; ?>