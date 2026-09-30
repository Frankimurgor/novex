<?php
require 'db.php';
require 'security.php';
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

$cat_id = (int)($_GET['id'] ?? 1);
$search = cleanString($_GET['q'] ?? '', 60);

$stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
$stmt->execute([$cat_id]);
$category = $stmt->fetch();

$sql = "SELECT * FROM products WHERE category_id = ? AND status = 'available'";
$params = [$cat_id];
if ($search) {
    $sql .= " AND title LIKE ?";
    $params[] = "%$search%";
}
$sql .= " ORDER BY id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

include 'header.php';
?>

<h1 class="page-title"><?= htmlspecialchars($category['name'] ?? 'Category') ?></h1>
<p class="page-subtitle"><?= count($products) ?> items available</p>

<form method="GET" style="display: flex; gap: 10px; margin-bottom: 22px; max-width: 500px;">
    <input type="hidden" name="id" value="<?= (int)$cat_id ?>">
    <input type="text" name="q" placeholder="🔍 Search items..." value="<?= htmlspecialchars($search) ?>" style="margin: 0;" maxlength="60">
    <button type="submit" class="btn">Search</button>
</form>

<div class="grid">
    <?php foreach ($products as $prod): ?>
        <a href="product.php?id=<?= (int)$prod['id'] ?>" class="card card-hover" style="text-decoration: none; color: inherit; display: block;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 12px;">
                <span class="badge badge-green">● Available</span>
                <span class="badge badge-cyan"><?= htmlspecialchars($prod['account_type'] ?? 'Digital') ?></span>
            </div>
            <h3 style="font-size: 15px; margin-bottom: 12px; color: #fff;"><?= htmlspecialchars($prod['title']) ?></h3>

            <div style="display: flex; justify-content: space-between; padding: 7px 0; border-bottom: 1px solid #1a2332;">
                <span style="color: #6b7280; font-size: 11.5px;">Balance/Value</span>
                <span style="color: #10b981; font-weight: 600; font-size: 13px;">$<?= number_format($prod['balance_amount'], 2) ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 7px 0; border-bottom: 1px solid #1a2332;">
                <span style="color: #6b7280; font-size: 11.5px;">Type</span>
                <span style="color: #e2e8f0; font-size: 13px;"><?= htmlspecialchars($prod['account_type'] ?? '—') ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 7px 0;">
                <span style="color: #6b7280; font-size: 11.5px;">Price</span>
                <span style="color: #22d3ee; font-weight: 800; font-size: 18px;">$<?= number_format($prod['price'], 2) ?></span>
            </div>

            <div class="btn btn-block" style="margin-top: 14px; text-align: center;">View Details →</div>
        </a>
    <?php endforeach; ?>
</div>

<?php if (empty($products)): ?>
    <div class="card" style="text-align: center; padding: 60px;">
        <div style="font-size: 42px; margin-bottom: 10px;">🔍</div>
        <h3 style="color: #6b7280;">No items found</h3>
    </div>
<?php endif; ?>

<?php include 'footer.php'; ?>