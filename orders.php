<?php
require 'db.php';
require 'security.php';
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

$user_id = (int)$_SESSION['user_id'];
$stmt = $pdo->prepare("
    SELECT o.id AS order_id, o.amount_paid, o.purchased_at,
           p.title, p.balance_amount, p.account_type,
           c.name AS category_name
    FROM orders o
    JOIN products p ON o.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE o.user_id = ?
    ORDER BY o.purchased_at DESC
");
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll();

$total_spent = 0;
foreach ($orders as $o) { $total_spent += $o['amount_paid']; }

include 'header.php';
?>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">✓ Purchase successful! Click "Reveal Details" to view your purchased data.</div>
<?php endif; ?>

<h1 class="page-title">My Orders</h1>
<p class="page-subtitle">Complete purchase history</p>

<div class="stat-grid" style="margin-bottom: 22px;">
    <div class="stat-card cyan">
        <div class="stat-label">Total Orders</div>
        <div class="stat-value"><?= count($orders) ?></div>
    </div>
    <div class="stat-card green">
        <div class="stat-label">Completed</div>
        <div class="stat-value"><?= count($orders) ?></div>
    </div>
    <div class="stat-card yellow">
        <div class="stat-label">Processing</div>
        <div class="stat-value">0</div>
    </div>
    <div class="stat-card purple">
        <div class="stat-label">Total Spent</div>
        <div class="stat-value">$<?= number_format($total_spent, 2) ?></div>
    </div>
</div>

<?php if (empty($orders)): ?>
    <div class="card" style="text-align: center; padding: 60px;">
        <div style="font-size: 42px; margin-bottom: 10px;">📦</div>
        <h3 style="color: #6b7280;">No orders yet</h3>
        <p style="color: #4b5563; font-size: 13px; margin: 6px 0 14px;">Your purchases will appear here.</p>
        <a href="home.php" class="btn">Browse Shop</a>
    </div>
<?php else: ?>
    <?php foreach ($orders as $o): ?>
        <div class="card">
            <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
                <div style="flex: 1; min-width: 260px;">
                    <span class="badge badge-green">● Completed</span>
                    <h3 style="margin: 10px 0 4px 0;"><?= htmlspecialchars($o['title']) ?></h3>
                    <p style="color: #6b7280; font-size: 11.5px; margin-bottom: 10px;"><?= htmlspecialchars($o['category_name'] ?? 'N/A') ?></p>
                    <p style="color: #10b981; font-size: 13px; font-weight: 600;">Value: $<?= number_format($o['balance_amount'], 2) ?></p>
                    <p style="color: #6b7280; font-size: 12px; margin-bottom: 12px;">Type: <?= htmlspecialchars($o['account_type']) ?></p>

                    <button onclick="revealData(<?= (int)$o['order_id'] ?>, this)" class="btn btn-outline btn-sm" data-loaded="0">
                        👁 Reveal Details
                    </button>
                    <div id="data-<?= (int)$o['order_id'] ?>" style="display: none; background: #0a0e17; padding: 14px; border-radius: 8px; margin-top: 10px; border: 1px solid #1a2332;">
                        <p style="font-size: 10px; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px;">Purchased Data</p>
                        <pre id="data-content-<?= (int)$o['order_id'] ?>" style="color: #22d3ee; font-family: monospace; font-size: 12.5px; white-space: pre-wrap; margin: 0;">Loading...</pre>
                    </div>
                </div>
                <div style="text-align: right; min-width: 140px;">
                    <p style="color: #6b7280; font-size: 10px; text-transform: uppercase; letter-spacing: 1px;">Paid</p>
                    <p style="color: #22d3ee; font-weight: 800; font-size: 22px; margin: 4px 0;">$<?= number_format($o['amount_paid'], 2) ?></p>
                    <p style="color: #4b5563; font-size: 11px;"><?= date("M d, Y H:i", strtotime($o['purchased_at'])) ?></p>
                    <p style="color: #4b5563; font-size: 11px; margin-top: 4px;">Order #<?= (int)$o['order_id'] ?></p>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<script>
function revealData(orderId, btn) {
    const container = document.getElementById('data-' + orderId);
    const content   = document.getElementById('data-content-' + orderId);

    // Toggle OFF if already revealed
    if (container.style.display === 'block' && btn.dataset.loaded === '1') {
        container.style.display = 'none';
        btn.textContent = '👁 Reveal Details';
        return;
    }

    // Show container + loading state
    container.style.display = 'block';
    btn.textContent = '⏳ Loading...';

    // Fetch from secure endpoint
    fetch('reveal_data.php?id=' + encodeURIComponent(orderId), {
        credentials: 'same-origin'
    })
        .then(r => {
            if (!r.ok) throw new Error('Server error');
            return r.json();
        })
        .then(data => {
            if (data.error) {
                content.textContent = 'Error: ' + data.error;
                btn.textContent = '👁 Reveal Details';
            } else {
                content.textContent = data.description;
                btn.dataset.loaded = '1';
                btn.textContent = '👁 Hide Details';
            }
        })
        .catch(() => {
            content.textContent = 'Network error. Please try again.';
            btn.textContent = '👁 Reveal Details';
        });
}
</script>

<?php include 'footer.php'; ?>