<?php
require 'db.php';
require 'security.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$uid = (int)$_SESSION['user_id'];

// Get last read timestamp
$stmt = $pdo->prepare("SELECT last_read_at FROM notifications_read WHERE user_id = ?");
$stmt->execute([$uid]);
$last_read = $stmt->fetchColumn() ?: '1970-01-01 00:00:00';

// Build notification list
$notifications = [];

// 1. Deposits (last 30 days)
$stmt = $pdo->prepare("
    SELECT id, amount_usd, crypto, status, created_at
    FROM deposits
    WHERE user_id = ? AND created_at > NOW() - INTERVAL 30 DAY
    ORDER BY created_at DESC LIMIT 5
");
$stmt->execute([$uid]);
foreach ($stmt->fetchAll() as $d) {
    $icon = '💰';
    $msg = "Deposit of $" . number_format($d['amount_usd'], 2) . " (" . $d['crypto'] . ")";
    if ($d['status'] === 'confirmed') {
        $msg .= " was confirmed";
        $icon = '✅';
    } elseif ($d['status'] === 'rejected') {
        $msg .= " was rejected";
        $icon = '❌';
    } else {
        $msg .= " is pending review";
        $icon = '⏳';
    }

    $notifications[] = [
        'icon'  => $icon,
        'text'  => $msg,
        'time'  => $d['created_at'],
        'link'  => 'topup.php',
        'type'  => 'deposit',
    ];
}

// 2. Orders (last 30 days)
$stmt = $pdo->prepare("
    SELECT o.id, o.amount_paid, o.purchased_at, p.title
    FROM orders o
    JOIN products p ON o.product_id = p.id
    WHERE o.user_id = ? AND o.purchased_at > NOW() - INTERVAL 30 DAY
    ORDER BY o.purchased_at DESC LIMIT 5
");
$stmt->execute([$uid]);
foreach ($stmt->fetchAll() as $o) {
    $notifications[] = [
        'icon'  => '📦',
        'text'  => "Order #" . $o['id'] . " completed — " . $o['title'],
        'time'  => $o['purchased_at'],
        'link'  => 'orders.php',
        'type'  => 'order',
    ];
}

// 3. Support ticket replies from admin (last 30 days)
$stmt = $pdo->prepare("
    SELECT tr.id, tr.body, tr.created_at, t.id AS ticket_id, t.subject
    FROM ticket_replies tr
    JOIN tickets t ON tr.ticket_id = t.id
    WHERE t.user_id = ? AND tr.is_admin = 1 AND tr.created_at > NOW() - INTERVAL 30 DAY
    ORDER BY tr.created_at DESC LIMIT 5
");
$stmt->execute([$uid]);
foreach ($stmt->fetchAll() as $r) {
    $notifications[] = [
        'icon'  => '💬',
        'text'  => "Support replied to ticket #" . $r['ticket_id'] . " — " . $r['subject'],
        'time'  => $r['created_at'],
        'link'  => 'support.php',
        'type'  => 'ticket',
    ];
}

// 4. News announcements (last 30 days)
$stmt = $pdo->query("
    SELECT id, title, icon, created_at
    FROM news
    WHERE created_at > NOW() - INTERVAL 30 DAY
    ORDER BY created_at DESC LIMIT 3
");
foreach ($stmt->fetchAll() as $n) {
    $notifications[] = [
        'icon'  => $n['icon'] ?: '📢',
        'text'  => "New announcement: " . $n['title'],
        'time'  => $n['created_at'],
        'link'  => 'news.php',
        'type'  => 'news',
    ];
}

// Sort all by time, newest first
usort($notifications, function($a, $b) {
    return strtotime($b['time'] . ' UTC') - strtotime($a['time'] . ' UTC');
});

// Limit to latest 10
$notifications = array_slice($notifications, 0, 10);

// Count unread (newer than last_read)
$unread_count = 0;
foreach ($notifications as $n) {
    if (strtotime($n['time'] . ' UTC') > strtotime($last_read . ' UTC')) {
        $unread_count++;
    }
}

// Format relative time
foreach ($notifications as &$n) {
    $n['ago'] = timeAgo($n['time']);
}

echo json_encode([
    'notifications' => $notifications,
    'unread_count'  => $unread_count,
]);