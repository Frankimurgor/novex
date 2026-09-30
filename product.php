<?php
require 'db.php';
require 'security.php';
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header("Location: home.php"); exit; }

// Increment view counter
$pdo->prepare("UPDATE products SET views = views + 1 WHERE id = ?")->execute([$id]);

// Load product
$stmt = $pdo->prepare("
    SELECT p.*, c.name AS cat_name, c.id AS cat_id
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) { header("Location: home.php"); exit; }

// Related products
$rel_stmt = $pdo->prepare("
    SELECT * FROM products
    WHERE category_id = ? AND id != ? AND status = 'available'
    ORDER BY RAND()
    LIMIT 4
");
$rel_stmt->execute([$product['category_id'], $id]);
$related = $rel_stmt->fetchAll();

// Cart check
$in_cart = isset($_SESSION['cart']) && in_array($id, $_SESSION['cart']);

// Owned check
$owned_stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND product_id = ?");
$owned_stmt->execute([(int)$_SESSION['user_id'], $id]);
$already_owned = (int)$owned_stmt->fetchColumn() > 0;

// Has user reviewed?
$rev_check = $pdo->prepare("SELECT id FROM reviews WHERE user_id = ? AND product_id = ?");
$rev_check->execute([(int)$_SESSION['user_id'], $id]);
$has_reviewed = (bool)$rev_check->fetch();

// Review stats
$rev_stats = $pdo->prepare("SELECT COUNT(*) AS total, AVG(rating) AS avg_rating FROM reviews WHERE product_id = ?");
$rev_stats->execute([$id]);
$stats_row = $rev_stats->fetch();
$total_reviews = (int)$stats_row['total'];
$avg_rating = $stats_row['avg_rating'] ? round($stats_row['avg_rating'], 1) : 0;

// Rating breakdown (how many 5-star, 4-star, etc.)
$breakdown = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
$bd_stmt = $pdo->prepare("SELECT rating, COUNT(*) AS cnt FROM reviews WHERE product_id = ? GROUP BY rating");
$bd_stmt->execute([$id]);
foreach ($bd_stmt->fetchAll() as $row) {
    $breakdown[(int)$row['rating']] = (int)$row['cnt'];
}

// All reviews (with pagination)
$rev_stmt = $pdo->prepare("
    SELECT r.*, u.username
    FROM reviews r
    LEFT JOIN users u ON r.user_id = u.id
    WHERE r.product_id = ?
    ORDER BY r.created_at DESC
    LIMIT 20
");
$rev_stmt->execute([$id]);
$reviews = $rev_stmt->fetchAll();

$can_afford = $_SESSION['balance'] >= $product['price'];
$is_available = $product['status'] === 'available';

// Helper: render stars
function renderStars($rating, $size = '14px') {
    $out = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $rating) {
            $out .= '<span style="color: #fbbf24; font-size: ' . $size . ';">★</span>';
        } else {
            $out .= '<span style="color: #4b5563; font-size: ' . $size . ';">★</span>';
        }
    }
    return $out;
}

include 'header.php';
?>

<a href="javascript:history.back()" class="mobile-back">← Back</a>

<!-- Breadcrumbs -->
<div style="font-size: 12.5px; color: #6b7280; margin-bottom: 20px;">
    <a href="home.php" style="color: #6b7280;">Home</a>
    <span style="margin: 0 8px;">/</span>
    <a href="category.php?id=<?= (int)$product['cat_id'] ?>" style="color: #6b7280;"><?= htmlspecialchars($product['cat_name'] ?? 'Category') ?></a>
    <span style="margin: 0 8px;">/</span>
    <span style="color: #94a3b8;"><?= htmlspecialchars($product['title']) ?></span>
</div>

<?php if (isset($_GET['added'])): ?>
    <div class="alert alert-success">✓ Added to cart. <a href="cart.php" style="color: #10b981; text-decoration: underline;">View cart →</a></div>
<?php endif; ?>
<?php if (isset($_GET['exists'])): ?>
    <div class="alert alert-warning">⚠ This item is already in your cart. <a href="cart.php" style="color: #fbbf24; text-decoration: underline;">View cart →</a></div>
<?php endif; ?>
<?php if (isset($_GET['review_posted'])): ?>
    <div class="alert alert-success">✓ Thank you! Your review has been posted.</div>
<?php endif; ?>
<?php if (isset($_GET['review_error'])): ?>
    <?php
        $err_map = [
            'rating' => 'Please select a rating (1-5 stars).',
            'body'   => 'Please write at least 10 characters in your review.',
            'not_bought' => 'You can only review products you have purchased.',
            'already_reviewed' => 'You have already reviewed this product.',
        ];
        $err_msg = $err_map[$_GET['review_error']] ?? 'There was a problem with your review.';
    ?>
    <div class="alert alert-error"><?= htmlspecialchars($err_msg) ?></div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">

    <!-- LEFT: Product Info -->
    <div>
        <div class="card">
            <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px;">
                <?php if ($is_available): ?>
                    <span class="badge badge-green">● Available</span>
                <?php else: ?>
                    <span class="badge badge-red">● Sold Out</span>
                <?php endif; ?>
                <span class="badge badge-cyan"><?= htmlspecialchars($product['account_type'] ?? 'Digital') ?></span>
                <?php if ($product['views'] > 50): ?>
                    <span class="badge badge-yellow">🔥 Popular</span>
                <?php endif; ?>
                <span class="badge badge-purple">👁 <?= (int)$product['views'] ?> views</span>
            </div>

            <h1 style="font-size: 26px; font-weight: 800; margin-bottom: 12px; line-height: 1.3;">
                <?= htmlspecialchars($product['title']) ?>
            </h1>

            <!-- Rating summary -->
            <?php if ($total_reviews > 0): ?>
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 16px;">
                    <div><?= renderStars(round($avg_rating), '18px') ?></div>
                    <span style="color: #e2e8f0; font-weight: 600;"><?= number_format($avg_rating, 1) ?></span>
                    <a href="#reviews" style="color: #6b7280; font-size: 13px; text-decoration: underline;"><?= $total_reviews ?> review<?= $total_reviews !== 1 ? 's' : '' ?></a>
                </div>
            <?php endif; ?>

            <p style="color: #94a3b8; font-size: 14.5px; line-height: 1.7; margin-bottom: 24px;">
                <?= htmlspecialchars($product['description'] ?: 'No additional description provided.') ?>
            </p>

            <div style="background: #0a0e17; border-radius: 10px; padding: 18px; border: 1px solid #1a2332;">
                <p style="color: #6b7280; font-size: 10px; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 14px;">Product Details</p>

                <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #1a2332;">
                    <span style="color: #6b7280; font-size: 13px;">Category</span>
                    <span style="color: #e2e8f0; font-size: 13px;"><?= htmlspecialchars($product['cat_name'] ?? 'N/A') ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #1a2332;">
                    <span style="color: #6b7280; font-size: 13px;">Account Type</span>
                    <span style="color: #e2e8f0; font-size: 13px;"><?= htmlspecialchars($product['account_type'] ?? 'Digital') ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #1a2332;">
                    <span style="color: #6b7280; font-size: 13px;">Balance / Value</span>
                    <span style="color: #10b981; font-size: 13px; font-weight: 600;">$<?= number_format($product['balance_amount'], 2) ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 8px 0;">
                    <span style="color: #6b7280; font-size: 13px;">Delivery Method</span>
                    <span style="color: #e2e8f0; font-size: 13px;">Instant (Orders page)</span>
                </div>

                <?php if (!empty($product['delivery_notes'])): ?>
                    <div style="padding-top: 12px; margin-top: 12px; border-top: 1px solid #1a2332;">
                        <p style="color: #6b7280; font-size: 10px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px;">Special Notes</p>
                        <p style="color: #cbd5e1; font-size: 13px; line-height: 1.6; white-space: pre-wrap;"><?= htmlspecialchars($product['delivery_notes']) ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 20px; text-align: center;">
                <div style="background: #0a0e17; padding: 14px; border-radius: 8px; border: 1px solid #1a2332;">
                    <div style="font-size: 22px;">⚡</div>
                    <div style="font-size: 11px; color: #94a3b8; margin-top: 6px;">Instant Delivery</div>
                </div>
                <div style="background: #0a0e17; padding: 14px; border-radius: 8px; border: 1px solid #1a2332;">
                    <div style="font-size: 22px;">🛡️</div>
                    <div style="font-size: 11px; color: #94a3b8; margin-top: 6px;">Secure Payment</div>
                </div>
                <div style="background: #0a0e17; padding: 14px; border-radius: 8px; border: 1px solid #1a2332;">
                    <div style="font-size: 22px;">💬</div>
                    <div style="font-size: 11px; color: #94a3b8; margin-top: 6px;">24/7 Support</div>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT: Purchase Box -->
    <div>
        <div class="card" style="position: sticky; top: 90px;">
            <div style="text-align: center; margin-bottom: 20px;">
                <p style="color: #6b7280; font-size: 11px; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 6px;">Price</p>
                <div style="font-size: 38px; font-weight: 800; color: #22d3ee; line-height: 1;">
                    $<?= number_format($product['price'], 2) ?>
                </div>
                <p style="color: #6b7280; font-size: 12px; margin-top: 6px;">USD · paid from wallet</p>
            </div>

            <?php if ($already_owned): ?>
                <div class="alert alert-success" style="font-size: 12.5px; text-align: center; margin-bottom: 12px;">
                    ✓ You already purchased this item
                </div>
                <a href="orders.php" class="btn btn-block btn-outline">📦 View in Orders</a>
            <?php elseif (!$is_available): ?>
                <div class="alert alert-error" style="font-size: 12.5px; text-align: center; margin-bottom: 12px;">
                    ⚠ This item is sold out
                </div>
                <button class="btn btn-block" disabled style="background: #1a2332; color: #4b5563; cursor: not-allowed;">Unavailable</button>
            <?php elseif ($in_cart): ?>
                <div class="alert alert-warning" style="font-size: 12.5px; text-align: center; margin-bottom: 12px;">
                    ⚠ Already in your cart
                </div>
                <a href="cart.php" class="btn btn-block btn-gold">🛒 Go to Cart</a>
            <?php elseif (!$can_afford): ?>
                <div class="alert alert-error" style="font-size: 12.5px;">
                    <p style="margin-bottom: 4px;">Insufficient balance</p>
                    <p style="font-size: 11px; opacity: 0.8;">You need $<?= number_format($product['price'] - $_SESSION['balance'], 2) ?> more</p>
                </div>
                <a href="topup.php" class="btn btn-block btn-gold">💳 Add Funds</a>
            <?php else: ?>
                <form action="add_to_cart.php" method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                    <button type="submit" class="btn btn-block">🛒 Buy Now</button>
                </form>
            <?php endif; ?>

            <div style="margin-top: 16px; padding-top: 14px; border-top: 1px solid #1a2332; font-size: 12px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                    <span style="color: #6b7280;">Your balance</span>
                    <span style="color: #10b981; font-weight: 600;">$<?= number_format($_SESSION['balance'], 2) ?></span>
                </div>
                <?php if (!$already_owned && $is_available && $can_afford): ?>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: #6b7280;">After purchase</span>
                        <span style="color: #22d3ee; font-weight: 600;">$<?= number_format($_SESSION['balance'] - $product['price'], 2) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card" style="text-align: center;">
            <p style="color: #6b7280; font-size: 12px; margin-bottom: 10px;">Questions about this item?</p>
            <a href="support.php" class="btn btn-outline btn-sm">💬 Contact Support</a>
        </div>
    </div>

</div>

<!-- ============ REVIEWS SECTION ============ -->
<div id="reviews" style="margin-top: 40px;">

    <h3 style="color: #22d3ee; font-size: 13px; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 18px;">// Customer Reviews</h3>

    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">

        <!-- Left: Summary -->
        <div class="card">
            <div style="text-align: center; padding: 10px 0 20px 0; border-bottom: 1px solid #1a2332;">
                <div style="font-size: 48px; font-weight: 800; color: #fbbf24; line-height: 1;">
                    <?= number_format($avg_rating, 1) ?>
                </div>
                <div style="margin: 8px 0;"><?= renderStars(round($avg_rating), '20px') ?></div>
                <p style="color: #6b7280; font-size: 12.5px;">Based on <?= $total_reviews ?> review<?= $total_reviews !== 1 ? 's' : '' ?></p>
            </div>

            <!-- Rating breakdown bars -->
            <div style="padding-top: 16px;">
                <?php for ($i = 5; $i >= 1; $i--): 
                    $count = $breakdown[$i];
                    $pct = $total_reviews > 0 ? ($count / $total_reviews) * 100 : 0;
                ?>
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px; font-size: 12px;">
                        <span style="color: #94a3b8; width: 40px;"><?= $i ?> ★</span>
                        <div style="flex: 1; background: #1a2332; height: 6px; border-radius: 3px; overflow: hidden;">
                            <div style="width: <?= $pct ?>%; height: 100%; background: #fbbf24; border-radius: 3px;"></div>
                        </div>
                        <span style="color: #6b7280; width: 30px; text-align: right;"><?= $count ?></span>
                    </div>
                <?php endfor; ?>
            </div>

            <?php if ($already_owned && !$has_reviewed): ?>
                <a href="#write-review" class="btn btn-block btn-success" style="margin-top: 20px;">✍️ Write a Review</a>
            <?php elseif ($has_reviewed): ?>
                <div class="alert alert-success" style="margin-top: 20px; font-size: 12.5px; text-align: center; margin-bottom: 0;">
                    ✓ You've reviewed this product
                </div>
            <?php elseif (!$already_owned): ?>
                <p style="color: #6b7280; font-size: 11.5px; text-align: center; margin-top: 20px; line-height: 1.5;">
                    Only verified buyers can leave reviews.
                </p>
            <?php endif; ?>
        </div>

        <!-- Right: Review list + write form -->
        <div>
            <?php if ($already_owned && !$has_reviewed): ?>
                <div class="card" id="write-review" style="border-color: #10b98144; background: rgba(16, 185, 129, 0.03);">
                    <h4 style="margin-bottom: 14px; color: #10b981;">✍️ Write Your Review</h4>
                    <form method="POST" action="submit_review.php">
                        <?= csrfField() ?>
                        <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">

                        <label>Your Rating</label>
                        <div style="display: flex; gap: 8px; margin: 8px 0 14px 0;">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <label style="cursor: pointer; margin: 0; padding: 0; text-transform: none; letter-spacing: 0;">
                                    <input type="radio" name="rating" value="<?= $i ?>" required style="display: none;" class="star-radio">
                                    <span class="star-label" data-value="<?= $i ?>" style="font-size: 28px; color: #4b5563; transition: color 0.15s;">★</span>
                                </label>
                            <?php endfor; ?>
                        </div>

                        <label>Title (optional)</label>
                        <input type="text" name="title" maxlength="200" placeholder="Sum up your experience in a few words">

                        <label>Your Review</label>
                        <textarea name="body" rows="4" required minlength="10" maxlength="2000" placeholder="What did you think of this product? (at least 10 characters)"></textarea>

                        <button type="submit" class="btn btn-block btn-success" style="margin-top: 14px;">Post Review</button>
                    </form>
                </div>
            <?php endif; ?>

            <?php if (empty($reviews)): ?>
                <div class="card" style="text-align: center; padding: 50px;">
                    <div style="font-size: 36px; margin-bottom: 10px;">💬</div>
                    <h3 style="color: #6b7280; margin-bottom: 6px;">No reviews yet</h3>
                    <p style="color: #4b5563; font-size: 13px;">Be the first to share your experience.</p>
                </div>
            <?php else: ?>
                <?php foreach ($reviews as $r): ?>
                    <div class="card">
                        <div style="display: flex; gap: 12px; align-items: start;">
                            <div style="width: 40px; height: 40px; border-radius: 50%; flex-shrink: 0; background: linear-gradient(135deg, #22d3ee, #8b5cf6); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 15px;">
                                <?= strtoupper(substr($r['username'] ?? '?', 0, 1)) ?>
                            </div>
                            <div style="flex: 1; min-width: 0;">
                                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 4px;">
                                    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                        <strong style="color: #e2e8f0; font-size: 13.5px;">
                                            <?= htmlspecialchars($r['username'] ?? 'Anonymous') ?>
                                        </strong>
                                        <span class="badge badge-green" style="font-size: 9px;">✓ Verified Purchase</span>
                                    </div>
                                    <span style="color: #6b7280; font-size: 11px;">
                                        <?= date("M d, Y", strtotime($r['created_at'])) ?>
                                    </span>
                                </div>

                                <div style="margin: 6px 0;"><?= renderStars((int)$r['rating'], '15px') ?></div>

                                <?php if (!empty($r['title'])): ?>
                                    <h4 style="color: #fff; font-size: 14px; margin: 8px 0 6px 0;"><?= htmlspecialchars($r['title']) ?></h4>
                                <?php endif; ?>

                                <p style="color: #94a3b8; font-size: 13.5px; line-height: 1.6; white-space: pre-wrap; margin: 0;">
                                    <?= htmlspecialchars($r['body']) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- RELATED PRODUCTS -->
<?php if (!empty($related)): ?>
    <h3 style="color: #22d3ee; font-size: 13px; letter-spacing: 2px; text-transform: uppercase; margin: 40px 0 16px;">// You May Also Like</h3>
    <div class="grid">
        <?php foreach ($related as $r): ?>
            <a href="product.php?id=<?= (int)$r['id'] ?>" class="card card-hover" style="text-decoration: none; color: inherit; display: block;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <span class="badge badge-green">● Available</span>
                    <span class="badge badge-cyan"><?= htmlspecialchars($r['account_type'] ?? 'Digital') ?></span>
                </div>
                <h4 style="font-size: 15px; margin-bottom: 8px; color: #fff;"><?= htmlspecialchars($r['title']) ?></h4>
                <p style="color: #6b7280; font-size: 11.5px; margin-bottom: 10px;">Value: $<?= number_format($r['balance_amount'], 2) ?></p>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: #22d3ee; font-size: 18px; font-weight: 800;">$<?= number_format($r['price'], 2) ?></span>
                    <span class="btn btn-sm">View</span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Star rating interactive JS -->
<script>
document.querySelectorAll('.star-radio').forEach(radio => {
    radio.addEventListener('change', function() {
        const val = parseInt(this.value);
        document.querySelectorAll('.star-label').forEach(star => {
            const sv = parseInt(star.dataset.value);
            star.style.color = sv <= val ? '#fbbf24' : '#4b5563';
        });
    });
});

// Hover effect on stars
document.querySelectorAll('.star-label').forEach(star => {
    star.addEventListener('mouseenter', function() {
        const val = parseInt(this.dataset.value);
        document.querySelectorAll('.star-label').forEach(s => {
            const sv = parseInt(s.dataset.value);
            s.style.color = sv <= val ? '#fbbf24' : '#4b5563';
        });
    });
});

document.querySelectorAll('.star-radio').forEach(radio => {
    radio.addEventListener('mouseleave', function() {
        const checked = document.querySelector('.star-radio:checked');
        const val = checked ? parseInt(checked.value) : 0;
        document.querySelectorAll('.star-label').forEach(star => {
            const sv = parseInt(star.dataset.value);
            star.style.color = sv <= val ? '#fbbf24' : '#4b5563';
        });
    });
});
</script>

<?php include 'footer.php'; ?>