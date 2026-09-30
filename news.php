<?php
require 'db.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

$news = $pdo->query("SELECT * FROM news ORDER BY created_at DESC")->fetchAll();
include 'header.php';
?>

<h1 class="page-title">News Feed</h1>
<p class="page-subtitle">Announcements and updates from <?= SITE_NAME ?>.</p>

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

<?php if (empty($news)): ?>
    <div class="card" style="text-align: center; padding: 40px;">
        <p style="color: #6b7280;">No announcements yet.</p>
    </div>
<?php endif; ?>

<?php include 'footer.php'; ?>