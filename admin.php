<?php
require 'db.php';
require 'security.php';
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }
if (!isAdmin()) { header("Location: home.php"); exit; }

$message = '';
$messageType = 'success';
$tab = $_GET['tab'] ?? 'products';

// ================ POST HANDLERS ================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // ADD PRODUCT
    if ($action === 'add_product') {
        try {
            $pdo->prepare("INSERT INTO products (category_id, title, price, balance_amount, account_type, description, delivery_notes) VALUES (?, ?, ?, ?, ?, ?, ?)")
                ->execute([
                    (int)$_POST['category_id'],
                    cleanString($_POST['title'] ?? '', 255),
                    floatval($_POST['price'] ?? 0),
                    floatval($_POST['balance_amount'] ?? 0),
                    cleanString($_POST['account_type'] ?? '', 50),
                    cleanString($_POST['description'] ?? '', 1000),
                    cleanString($_POST['delivery_notes'] ?? '', 1000),
                ]);
            $message = "Product added.";
        } catch (PDOException $e) { $message = $e->getMessage(); $messageType = 'error'; }
    }

    // DELETE PRODUCT
    if ($action === 'delete_product') {
        $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([(int)$_POST['product_id']]);
        $message = "Product deleted.";
    }

    // TOGGLE PRODUCT
    if ($action === 'toggle_product') {
        $pdo->prepare("UPDATE products SET status = IF(status='available','sold','available') WHERE id = ?")->execute([(int)$_POST['product_id']]);
        $message = "Product status updated.";
    }

    // ADD CATEGORY
    if ($action === 'add_category') {
        try {
            $name = cleanString($_POST['name'] ?? '', 100);
            $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
            $pdo->prepare("INSERT INTO categories (name, slug) VALUES (?, ?)")->execute([$name, $slug]);
            $message = "Category added.";
        } catch (PDOException $e) { $message = $e->getMessage(); $messageType = 'error'; }
    }

    // DELETE CATEGORY
    if ($action === 'delete_category') {
        $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([(int)$_POST['category_id']]);
        $message = "Category deleted.";
    }

    // ADD NEWS
    if ($action === 'add_news') {
        try {
            $pdo->prepare("INSERT INTO news (title, body, icon) VALUES (?, ?, ?)")
                ->execute([
                    cleanString($_POST['title'] ?? '', 255),
                    cleanString($_POST['body'] ?? '', 2000),
                    cleanString($_POST['icon'] ?? '📢', 4) ?: '📢'
                ]);
            $message = "News posted.";
        } catch (PDOException $e) { $message = $e->getMessage(); $messageType = 'error'; }
    }

    // DELETE NEWS
    if ($action === 'delete_news') {
        $pdo->prepare("DELETE FROM news WHERE id = ?")->execute([(int)$_POST['news_id']]);
        $message = "News deleted.";
    }

    // CONFIRM DEPOSIT
    if ($action === 'confirm_deposit') {
        $dep = $pdo->prepare("SELECT * FROM deposits WHERE id = ?");
        $dep->execute([(int)$_POST['deposit_id']]);
        $dep = $dep->fetch();
        if ($dep && $dep['status'] === 'pending') {
            $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$dep['amount_usd'], $dep['user_id']]);
            $pdo->prepare("UPDATE deposits SET status = 'confirmed' WHERE id = ?")->execute([$dep['id']]);
            $message = "Deposit confirmed and balance credited.";
        } else {
            $message = "Deposit already processed.";
            $messageType = 'error';
        }
    }

    // REJECT DEPOSIT
    if ($action === 'reject_deposit') {
        $pdo->prepare("UPDATE deposits SET status = 'rejected' WHERE id = ?")->execute([(int)$_POST['deposit_id']]);
        $message = "Deposit rejected.";
    }

    // DELETE USER (with full cleanup of related records)
    if ($action === 'delete_user') {
        $uid = (int)$_POST['user_id'];

        if ($uid === (int)$_SESSION['user_id']) {
            $message = "You cannot delete yourself.";
            $messageType = 'error';
        } else {
            $pdo->beginTransaction();
            try {
                // 1. Ticket replies on this user's tickets
                $pdo->prepare("DELETE tr FROM ticket_replies tr
                               JOIN tickets t ON tr.ticket_id = t.id
                               WHERE t.user_id = ?")->execute([$uid]);

                // 2. Any other ticket replies written by this user
                $pdo->prepare("DELETE FROM ticket_replies WHERE user_id = ?")->execute([$uid]);

                // 3. Tickets
                $pdo->prepare("DELETE FROM tickets WHERE user_id = ?")->execute([$uid]);

                // 4. Reviews
                $pdo->prepare("DELETE FROM reviews WHERE user_id = ?")->execute([$uid]);

                // 5. Notifications read marker
                $pdo->prepare("DELETE FROM notifications_read WHERE user_id = ?")->execute([$uid]);

                // 6. Orders
                $pdo->prepare("DELETE FROM orders WHERE user_id = ?")->execute([$uid]);

                // 7. Deposits
                $pdo->prepare("DELETE FROM deposits WHERE user_id = ?")->execute([$uid]);

                // 8. Finally the user
                $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);

                $pdo->commit();
                $message = "User and all related records deleted.";
            } catch (PDOException $e) {
                $pdo->rollBack();
                $message = "Failed to delete user: " . $e->getMessage();
                $messageType = 'error';
            }
        }
    }

    // REPLY TO TICKET
    if ($action === 'reply_ticket') {
        $ticket_id = (int)($_POST['ticket_id'] ?? 0);
        $reply     = cleanString($_POST['reply'] ?? '', 3000);

        if ($ticket_id > 0 && strlen($reply) >= 2) {
            $chk = $pdo->prepare("SELECT id FROM tickets WHERE id = ?");
            $chk->execute([$ticket_id]);
            if ($chk->fetch()) {
                $pdo->prepare("INSERT INTO ticket_replies (ticket_id, user_id, is_admin, body) VALUES (?, ?, 1, ?)")
                    ->execute([$ticket_id, (int)$_SESSION['user_id'], $reply]);
                $pdo->prepare("UPDATE tickets SET status = 'answered' WHERE id = ?")
                    ->execute([$ticket_id]);
                $message = "Reply sent.";
            } else {
                $message = "Ticket not found.";
                $messageType = 'error';
            }
        } else {
            $message = "Reply must be at least 2 characters.";
            $messageType = 'error';
        }
    }

    // CLOSE TICKET
    if ($action === 'close_ticket') {
        $pdo->prepare("UPDATE tickets SET status = 'closed' WHERE id = ?")
            ->execute([(int)$_POST['ticket_id']]);
        $message = "Ticket closed.";
    }

    // REOPEN TICKET
    if ($action === 'reopen_ticket') {
        $pdo->prepare("UPDATE tickets SET status = 'open' WHERE id = ?")
            ->execute([(int)$_POST['ticket_id']]);
        $message = "Ticket reopened.";
    }

    // DELETE REVIEW
    if ($action === 'delete_review') {
        $pdo->prepare("DELETE FROM reviews WHERE id = ?")->execute([(int)$_POST['review_id']]);
        $message = "Review deleted.";
    }
}

// ================ LOAD DATA ================
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$products   = $pdo->query("SELECT p.*, c.name AS cat_name FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC")->fetchAll();
$news_list  = $pdo->query("SELECT * FROM news ORDER BY id DESC")->fetchAll();
$users_list = $pdo->query("SELECT id, username, email, balance, role, tier, total_spent, created_at FROM users ORDER BY id DESC")->fetchAll();
$deposits   = $pdo->query("SELECT d.*, u.username FROM deposits d LEFT JOIN users u ON d.user_id = u.id ORDER BY d.id DESC LIMIT 30")->fetchAll();
$tickets    = $pdo->query("
    SELECT t.*, u.username AS username,
        (SELECT COUNT(*) FROM ticket_replies tr WHERE tr.ticket_id = t.id) AS reply_count
    FROM tickets t
    LEFT JOIN users u ON t.user_id = u.id
    ORDER BY 
        CASE t.status WHEN 'open' THEN 1 WHEN 'answered' THEN 2 ELSE 3 END,
        t.created_at DESC
")->fetchAll();

// Load reviews
$reviews = [];
try {
    $reviews = $pdo->query("
        SELECT r.*, u.username, p.title AS product_title
        FROM reviews r
        LEFT JOIN users u ON r.user_id = u.id
        LEFT JOIN products p ON r.product_id = p.id
        ORDER BY r.created_at DESC
        LIMIT 100
    ")->fetchAll();
} catch (Exception $e) { $reviews = []; }

// Average review rating
$avg_rating_row = $pdo->query("SELECT AVG(rating) AS avg_r, COUNT(*) AS total FROM reviews")->fetch();
$avg_rating = $avg_rating_row['avg_r'] ? round($avg_rating_row['avg_r'], 1) : 0;
$total_reviews = (int)$avg_rating_row['total'];

$stats = [
    'products' => count($products),
    'categories' => count($categories),
    'users' => count($users_list),
    'pending_deposits' => count(array_filter($deposits, function($d) { return $d['status'] === 'pending'; })),
    'reviews' => $total_reviews,
];

include 'header.php';
?>

<h1 class="page-title">⚙ Admin Panel</h1>
<p class="page-subtitle">Manage products, categories, news, users, deposits, support tickets, and reviews.</p>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="stat-grid" style="margin-bottom: 22px;">
    <div class="stat-card cyan">
        <div class="stat-label">Products</div>
        <div class="stat-value"><?= $stats['products'] ?></div>
    </div>
    <div class="stat-card purple">
        <div class="stat-label">Categories</div>
        <div class="stat-value"><?= $stats['categories'] ?></div>
    </div>
    <div class="stat-card green">
        <div class="stat-label">Users</div>
        <div class="stat-value"><?= $stats['users'] ?></div>
    </div>
    <div class="stat-card yellow">
        <div class="stat-label">Pending Deposits</div>
        <div class="stat-value"><?= $stats['pending_deposits'] ?></div>
    </div>
    <div class="stat-card purple">
        <div class="stat-label">Reviews</div>
        <div class="stat-value"><?= $stats['reviews'] ?></div>
    </div>
</div>

<div style="display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap;">
    <?php
    $tabs = [
        'products'   => '📦 Products',
        'categories' => '📁 Categories',
        'news'       => '📢 News',
        'deposits'   => '💰 Deposits',
        'users'      => '👥 Users',
        'support'    => '💬 Support',
        'reviews'    => '⭐ Reviews',
    ];
    foreach ($tabs as $key => $label):
        $cls = ($tab === $key) ? 'pill-primary' : 'pill-outline';
    ?>
        <a href="?tab=<?= $key ?>" class="pill-btn <?= $cls ?>"><?= $label ?></a>
    <?php endforeach; ?>
</div>

<!-- ================ PRODUCTS TAB ================ -->
<?php if ($tab === 'products'): ?>
    <div class="card">
        <h3 style="margin-bottom: 14px;">➕ Add Product</h3>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add_product">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                <div><label>Title</label><input type="text" name="title" required maxlength="255" placeholder="Netflix Gift Card"></div>
                <div>
                    <label>Category</label>
                    <select name="category_id" required>
                        <?php foreach($categories as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label>Price (USD)</label><input type="number" step="0.01" name="price" required min="0" placeholder="50.00"></div>
                <div><label>Balance/Value (USD)</label><input type="number" step="0.01" name="balance_amount" min="0" placeholder="100.00"></div>
                <div><label>Type</label><input type="text" name="account_type" maxlength="50" placeholder="Digital / Subscription"></div>
                <div><label>Description</label><input type="text" name="description" maxlength="1000" placeholder="Redemption code / delivery method"></div>
                <div style="grid-column: span 2;">
                    <label>Delivery Notes (optional)</label>
                    <input type="text" name="delivery_notes" maxlength="1000" placeholder="e.g. Redemption instructions, warranty info...">
                </div>
            </div>
            <button type="submit" class="btn btn-success" style="margin-top: 14px;">Add Product</button>
        </form>
    </div>

    <div class="card" style="padding: 0; overflow-x: auto;">
        <table>
            <thead>
                <tr><th>ID</th><th>Title</th><th>Category</th><th>Price</th><th>Views</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($products as $p): ?>
                    <tr>
                        <td><?= (int)$p['id'] ?></td>
                        <td><?= htmlspecialchars($p['title']) ?></td>
                        <td><?= htmlspecialchars($p['cat_name'] ?? '—') ?></td>
                        <td style="color: #22d3ee; font-weight: 600;">$<?= number_format($p['price'], 2) ?></td>
                        <td style="color: #6b7280;"><?= (int)($p['views'] ?? 0) ?></td>
                        <td>
                            <?php if ($p['status'] === 'available'): ?>
                                <span class="badge badge-green">available</span>
                            <?php else: ?>
                                <span class="badge badge-red">sold</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="toggle_product">
                                <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline">Toggle</button>
                            </form>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete?');">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete_product">
                                <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- ================ CATEGORIES TAB ================ -->
<?php if ($tab === 'categories'): ?>
    <div class="card">
        <h3 style="margin-bottom: 14px;">➕ Add Category</h3>
        <form method="POST" style="display: flex; gap: 10px; align-items: end;">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add_category">
            <div style="flex: 1;"><label>Category Name</label><input type="text" name="name" required maxlength="100" placeholder="VPN Subscriptions"></div>
            <button type="submit" class="btn btn-success">Add</button>
        </form>
    </div>

    <div class="card" style="padding: 0; overflow-x: auto;">
        <table>
            <thead><tr><th>ID</th><th>Name</th><th>Slug</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($categories as $c): ?>
                    <tr>
                        <td><?= (int)$c['id'] ?></td>
                        <td><?= htmlspecialchars($c['name']) ?></td>
                        <td style="color: #6b7280; font-size: 12px;"><?= htmlspecialchars($c['slug']) ?></td>
                        <td>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete?');">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete_category">
                                <input type="hidden" name="category_id" value="<?= (int)$c['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- ================ NEWS TAB ================ -->
<?php if ($tab === 'news'): ?>
    <div class="card">
        <h3 style="margin-bottom: 14px;">➕ Post News</h3>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add_news">
            <div style="display: grid; grid-template-columns: 100px 1fr; gap: 14px;">
                <div><label>Icon</label><input type="text" name="icon" placeholder="📢" maxlength="4"></div>
                <div><label>Title</label><input type="text" name="title" required maxlength="255" placeholder="Announcement title"></div>
            </div>
            <label>Body</label>
            <textarea name="body" rows="4" maxlength="2000" placeholder="Full announcement text..."></textarea>
            <button type="submit" class="btn btn-success" style="margin-top: 14px;">Post</button>
        </form>
    </div>

    <?php foreach ($news_list as $n): ?>
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: start;">
                <div style="display: flex; gap: 12px; flex: 1;">
                    <div class="news-icon"><?= htmlspecialchars($n['icon']) ?></div>
                    <div>
                        <h4 style="margin-bottom: 4px;"><?= htmlspecialchars($n['title']) ?></h4>
                        <p style="color: #94a3b8; font-size: 13px; margin-bottom: 8px;"><?= nl2br(htmlspecialchars($n['body'])) ?></p>
                        <span style="color: #4b5563; font-size: 11px;"><?= date("d-m-Y H:i", strtotime($n['created_at'])) ?></span>
                    </div>
                </div>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete?');">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete_news">
                    <input type="hidden" name="news_id" value="<?= (int)$n['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- ================ DEPOSITS TAB ================ -->
<?php if ($tab === 'deposits'): ?>
    <div class="card" style="padding: 0; overflow-x: auto;">
        <table>
            <thead>
                <tr><th>ID</th><th>User</th><th>Amount</th><th>Crypto</th><th>TX Hash</th><th>Status</th><th>Date</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($deposits as $d): ?>
                    <tr>
                        <td><?= (int)$d['id'] ?></td>
                        <td><?= htmlspecialchars($d['username'] ?? '?') ?></td>
                        <td style="color: #10b981; font-weight: 600;">$<?= number_format($d['amount_usd'], 2) ?></td>
                        <td><?= htmlspecialchars($d['crypto']) ?></td>
                        <td style="font-family: monospace; font-size: 11px; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            <?= htmlspecialchars($d['tx_hash'] ?? '') ?>
                        </td>
                        <td>
                            <?php if ($d['status'] === 'confirmed'): ?>
                                <span class="badge badge-green">confirmed</span>
                            <?php elseif ($d['status'] === 'rejected'): ?>
                                <span class="badge badge-red">rejected</span>
                            <?php else: ?>
                                <span class="badge badge-yellow">pending</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size: 11px; color: #6b7280;"><?= date("M d, H:i", strtotime($d['created_at'])) ?></td>
                        <td>
                            <?php if ($d['status'] === 'pending'): ?>
                                <form method="POST" style="display:inline;">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="confirm_deposit">
                                    <input type="hidden" name="deposit_id" value="<?= (int)$d['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-success">Confirm</button>
                                </form>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Reject this deposit?');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="reject_deposit">
                                    <input type="hidden" name="deposit_id" value="<?= (int)$d['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Reject</button>
                                </form>
                            <?php else: ?>
                                <span style="color: #6b7280; font-size: 11px;">Processed</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- ================ USERS TAB ================ -->
<?php if ($tab === 'users'): ?>
    <div class="card" style="padding: 0; overflow-x: auto;">
        <table>
            <thead>
                <tr><th>ID</th><th>Username</th><th>Email</th><th>Balance</th><th>Role</th><th>Tier</th><th>Spent</th><th>Joined</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($users_list as $u): ?>
                    <tr>
                        <td><?= (int)$u['id'] ?></td>
                        <td><?= htmlspecialchars($u['username']) ?></td>
                        <td style="color: #6b7280; font-size: 12px;"><?= htmlspecialchars($u['email']) ?></td>
                        <td style="color: #10b981; font-weight: 600;">$<?= number_format($u['balance'], 2) ?></td>
                        <td>
                            <?php if ($u['role'] === 'admin'): ?>
                                <span class="badge badge-yellow">admin</span>
                            <?php else: ?>
                                <span class="badge badge-cyan">user</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($u['tier'] ?? 'Bronze') ?></td>
                        <td>$<?= number_format($u['total_spent'] ?? 0, 2) ?></td>
                        <td style="font-size: 11px; color: #6b7280;"><?= date("M d, Y", strtotime($u['created_at'])) ?></td>
                        <td>
                            <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this user and all their records? This cannot be undone.');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            <?php else: ?>
                                <span style="color: #6b7280; font-size: 11px;">You</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- ================ SUPPORT TAB ================ -->
<?php if ($tab === 'support'): ?>

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div>
                <h3 style="margin-bottom: 4px;">💬 Support Tickets</h3>
                <p style="color: #6b7280; font-size: 12.5px;">Respond to user questions and issues.</p>
            </div>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <span class="badge badge-yellow">
                    <?= count(array_filter($tickets, function($t){ return $t['status'] === 'open'; })) ?> open
                </span>
                <span class="badge badge-green">
                    <?= count(array_filter($tickets, function($t){ return $t['status'] === 'answered'; })) ?> answered
                </span>
                <span class="badge badge-cyan">
                    <?= count(array_filter($tickets, function($t){ return $t['status'] === 'closed'; })) ?> closed
                </span>
            </div>
        </div>
    </div>

    <?php if (empty($tickets)): ?>
        <div class="card" style="text-align: center; padding: 60px;">
            <div style="font-size: 42px; margin-bottom: 10px;">📭</div>
            <h3 style="color: #6b7280;">No tickets yet</h3>
            <p style="color: #4b5563; font-size: 13px; margin-top: 6px;">User support requests will appear here.</p>
        </div>
    <?php else: ?>

        <?php foreach ($tickets as $t): ?>
            <?php
                $r_stmt = $pdo->prepare("
                    SELECT tr.*, u.username
                    FROM ticket_replies tr
                    LEFT JOIN users u ON tr.user_id = u.id
                    WHERE tr.ticket_id = ?
                    ORDER BY tr.id ASC
                ");
                $r_stmt->execute([(int)$t['id']]);
                $replies = $r_stmt->fetchAll();

                $border = '#fbbf24';
                if ($t['status'] === 'answered') $border = '#10b981';
                elseif ($t['status'] === 'closed') $border = '#22d3ee';
            ?>

            <div class="card" style="border-left: 4px solid <?= $border ?>;">
                <div style="display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap; gap: 12px;">
                    <div style="flex: 1; min-width: 240px;">
                        <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 8px; flex-wrap: wrap;">
                            <?php if ($t['status'] === 'open'): ?>
                                <span class="badge badge-yellow">● Open</span>
                            <?php elseif ($t['status'] === 'answered'): ?>
                                <span class="badge badge-green">● Answered</span>
                            <?php else: ?>
                                <span class="badge badge-cyan">● Closed</span>
                            <?php endif; ?>
                            <span style="color: #6b7280; font-size: 11px;">Ticket #<?= (int)$t['id'] ?></span>
                            <span style="color: #6b7280; font-size: 11px;">• from @<?= htmlspecialchars($t['username'] ?? 'unknown') ?></span>
                            <span style="color: #6b7280; font-size: 11px;">• <?= date("M d, Y H:i", strtotime($t['created_at'])) ?></span>
                        </div>

                        <h4 style="margin-bottom: 8px; color: #fff;"><?= htmlspecialchars($t['subject']) ?></h4>
                        <p style="color: #94a3b8; font-size: 13px; line-height: 1.6; white-space: pre-wrap;"><?= htmlspecialchars($t['body']) ?></p>
                    </div>
                </div>

                <?php if (!empty($replies)): ?>
                    <div style="margin-top: 18px; padding-top: 14px; border-top: 1px solid #1a2332;">
                        <p style="color: #6b7280; font-size: 10px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px;">Conversation</p>

                        <?php foreach ($replies as $r): ?>
                            <div style="display: flex; gap: 10px; margin-bottom: 12px; padding: 12px; border-radius: 8px;
                                <?= $r['is_admin'] ? 'background: rgba(34, 211, 238, 0.06); border-left: 3px solid #22d3ee;' : 'background: #0a0e17; border-left: 3px solid #4b5563;' ?>;">
                                <div style="width: 28px; height: 28px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold;
                                    <?= $r['is_admin'] ? 'background: linear-gradient(135deg, #22d3ee, #8b5cf6); color: white;' : 'background: #1a2332; color: #94a3b8;' ?>;">
                                    <?= strtoupper(substr($r['username'] ?? '?', 0, 1)) ?>
                                </div>
                                <div style="flex: 1;">
                                    <div style="display: flex; gap: 8px; align-items: center; margin-bottom: 6px; flex-wrap: wrap;">
                                        <strong style="color: #e2e8f0; font-size: 12.5px;">
                                            <?= htmlspecialchars($r['username'] ?? 'unknown') ?>
                                        </strong>
                                        <?php if ($r['is_admin']): ?>
                                            <span class="badge badge-cyan" style="font-size: 9px;">Admin</span>
                                        <?php endif; ?>
                                        <span style="color: #4b5563; font-size: 10.5px;"><?= date("M d, H:i", strtotime($r['created_at'])) ?></span>
                                    </div>
                                    <p style="color: #cbd5e1; font-size: 13px; line-height: 1.6; white-space: pre-wrap; margin: 0;"><?= htmlspecialchars($r['body']) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div style="margin-top: 18px; padding-top: 14px; border-top: 1px solid #1a2332;">
                    <?php if ($t['status'] !== 'closed'): ?>
                        <form method="POST" style="display: flex; gap: 8px; align-items: end; flex-wrap: wrap;">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="reply_ticket">
                            <input type="hidden" name="ticket_id" value="<?= (int)$t['id'] ?>">
                            <div style="flex: 1; min-width: 240px;">
                                <label style="margin-top: 0;">Reply</label>
                                <textarea name="reply" rows="2" required maxlength="3000" placeholder="Type your response..." style="resize: vertical;"></textarea>
                            </div>
                            <button type="submit" class="btn btn-success">📤 Send Reply</button>
                        </form>

                        <div style="margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap;">
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Close this ticket?');">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="close_ticket">
                                <input type="hidden" name="ticket_id" value="<?= (int)$t['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline">✓ Mark as Resolved</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                            <p style="color: #6b7280; font-size: 12px; margin: 0;">This ticket has been closed.</p>
                            <form method="POST" style="display: inline;">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="reopen_ticket">
                                <input type="hidden" name="ticket_id" value="<?= (int)$t['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline">↻ Reopen</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

    <?php endif; ?>
<?php endif; ?>

<!-- ================ REVIEWS TAB ================ -->
<?php if ($tab === 'reviews'): ?>

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div>
                <h3 style="margin-bottom: 4px;">⭐ Product Reviews</h3>
                <p style="color: #6b7280; font-size: 12.5px;">All customer reviews across your store. Delete any that violate store rules.</p>
            </div>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <span class="badge badge-purple"><?= $total_reviews ?> total</span>
                <span class="badge badge-yellow">⭐ <?= number_format($avg_rating, 1) ?> avg</span>
            </div>
        </div>
    </div>

    <?php if (empty($reviews)): ?>
        <div class="card" style="text-align: center; padding: 60px;">
            <div style="font-size: 42px; margin-bottom: 10px;">⭐</div>
            <h3 style="color: #6b7280;">No reviews yet</h3>
            <p style="color: #4b5563; font-size: 13px; margin-top: 6px;">Customer reviews will appear here once users start posting them.</p>
        </div>
    <?php else: ?>

        <?php foreach ($reviews as $r): ?>
            <div class="card">
                <div style="display: flex; gap: 14px; align-items: start; flex-wrap: wrap;">
                    <div style="width: 44px; height: 44px; border-radius: 50%; flex-shrink: 0; background: linear-gradient(135deg, #22d3ee, #8b5cf6); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 16px;">
                        <?= strtoupper(substr($r['username'] ?? '?', 0, 1)) ?>
                    </div>
                    <div style="flex: 1; min-width: 240px;">
                        <div style="display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap; gap: 10px; margin-bottom: 8px;">
                            <div>
                                <strong style="color: #e2e8f0; font-size: 14px;"><?= htmlspecialchars($r['username'] ?? 'Anonymous') ?></strong>
                                <span style="color: #6b7280; font-size: 12px; margin-left: 8px;">
                                    reviewed <span style="color: #22d3ee;"><?= htmlspecialchars($r['product_title'] ?? '(deleted product)') ?></span>
                                </span>
                            </div>
                            <span style="color: #6b7280; font-size: 11px;">
                                <?= date("M d, Y H:i", strtotime($r['created_at'])) ?>
                            </span>
                        </div>

                        <div style="margin-bottom: 8px;">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span style="color: <?= $i <= (int)$r['rating'] ? '#fbbf24' : '#4b5563' ?>; font-size: 16px;">★</span>
                            <?php endfor; ?>
                            <span style="color: #94a3b8; font-size: 12px; margin-left: 8px;"><?= (int)$r['rating'] ?>/5</span>
                        </div>

                        <?php if (!empty($r['title'])): ?>
                            <h4 style="color: #fff; font-size: 14px; margin-bottom: 6px;"><?= htmlspecialchars($r['title']) ?></h4>
                        <?php endif; ?>

                        <p style="color: #94a3b8; font-size: 13.5px; line-height: 1.6; white-space: pre-wrap; margin: 0;">
                            <?= htmlspecialchars($r['body']) ?>
                        </p>

                        <div style="margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap;">
                            <a href="product.php?id=<?= (int)$r['product_id'] ?>" target="_blank" class="btn btn-sm btn-outline">🔍 View Product</a>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this review? This cannot be undone.');">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete_review">
                                <input type="hidden" name="review_id" value="<?= (int)$r['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Delete Review</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

    <?php endif; ?>
<?php endif; ?>

<?php include 'footer.php'; ?>