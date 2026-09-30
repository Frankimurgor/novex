<?php
require 'db.php';
require 'security.php';
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

$q = cleanString($_GET['q'] ?? '', 60);
$results = [];
if ($q) {
    $stmt = $pdo->prepare("SELECT p.*, c.name AS cat_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.status = 'available' AND p.title LIKE ? ORDER BY p.id DESC");
    $stmt->execute(["%$q%"]);
    $results = $stmt->fetchAll();
}
include 'header.php';
?>

<h1 class="page-title">Search Results</h1>
<p class="page-subtitle"><?= count($results) ?> result(s) for "<?= htmlspecialchars($q) ?>"</p>

<?php if (empty($results)): ?>
    <div class="card" style="text-align: center; padding: 60px;">
        <div style="font-size: 42px; margin-bottom: 10px;">🔍</div>
        <h3 style="color: #6b7280;">Nothing found</h3>
        <p style="color: #4b5563; font-size: 13px; margin-top: 6px;">Try a different keyword.</p>
    </div>
<?php else: ?>
    <div class="grid">
        <?php foreach ($results as $prod): ?>
            <a href="product.php?id=<?= (int)$prod['id'] ?>" class="card card-hover" style="text-decoration: none; color: inherit; display: block;">
                <span class="badge badge-green" style="margin-bottom: 10px; display:inline-block;">● Available</span>
                <h3 style="font-size: 15px; margin-bottom: 6px; color: #fff;"><?= htmlspecialchars($prod['title']) ?></h3>
                <p style="color: #6b7280; font-size: 11.5px; margin-bottom: 10px;"><?= htmlspecialchars($prod['cat_name']) ?></p>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: #22d3ee; font-size: 18px; font-weight: 800;">$<?= number_format($prod['price'], 2) ?></span>
                    <span class="btn btn-sm">View</span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include 'footer.php'; ?>