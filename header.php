<?php
require_once 'security.php';

if (isset($_SESSION['user_id']) && isset($pdo)) {
    $stmt = $pdo->prepare("SELECT balance, role, total_spent, timezone FROM users WHERE id = ?");
    $stmt->execute([(int)$_SESSION['user_id']]);
    $row = $stmt->fetch();
    if ($row) {
        $_SESSION['balance']     = $row['balance'];
        $_SESSION['role']        = $row['role'];
        $_SESSION['total_spent'] = $row['total_spent'];
        $_SESSION['timezone']    = $row['timezone'] ?: 'UTC';
    } else {
        session_unset();
        session_destroy();
        header("Location: index.php");
        exit;
    }
}

$sidebar_categories = [];
if (isset($_SESSION['user_id']) && isset($pdo)) {
    try {
        $sidebar_categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
    } catch (Exception $e) { $sidebar_categories = []; }
}

$open_ticket_count = 0;
if (isset($_SESSION['user_id']) && isset($pdo) && isAdmin()) {
    $open_ticket_count = countOpenTickets($pdo);
}

$current_page = basename($_SERVER['PHP_SELF']);

$user_tier_name = 'Bronze';
$user_tier_color = '#b45309';
if (isset($_SESSION['total_spent'])) {
    list($user_tier_name, $user_tier_color) = userTier($_SESSION['total_spent']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        html, body { overflow-x: hidden; max-width: 100vw; }

        pre, code, .notif-text {
            overflow-wrap: break-word;
            word-wrap: break-word;
            word-break: break-word;
            white-space: pre-wrap;
            max-width: 100%;
        }

        img, table, iframe { max-width: 100%; }

        body { background-color: #0a0e17; color: #e2e8f0; font-family: -apple-system, 'Segoe UI', Tahoma, Verdana, sans-serif; }
        a { text-decoration: none; }
        .layout { display: flex; min-height: 100vh; }

        .sidebar { width: 250px; background-color: #0d1420; border-right: 1px solid #1a2332; position: fixed; top: 0; left: 0; bottom: 0; overflow-y: auto; transition: transform 0.25s ease; z-index: 999; }
        .sidebar .logo { display: block; padding: 22px 24px; font-size: 22px; font-weight: 800; color: #22d3ee; letter-spacing: 2px; border-bottom: 1px solid #1a2332; }
        .sidebar h4 { font-size: 10px; text-transform: uppercase; letter-spacing: 1.5px; color: #4b5563; padding: 18px 24px 8px 24px; font-weight: 600; }
        .sidebar a { display: flex; align-items: center; gap: 10px; padding: 11px 24px; color: #94a3b8; font-size: 13.5px; border-left: 3px solid transparent; transition: 0.15s; }
        .sidebar a:hover { background-color: #131c2e; color: #22d3ee; border-left-color: #22d3ee; }
        .sidebar a.active { color: #22d3ee; border-left-color: #22d3ee; background-color: #131c2e; }
        .sidebar a.admin-link { color: #fbbf24; }
        .sidebar a.signout { color: #ef4444; }
        .sidebar a .icon { font-size: 16px; width: 22px; text-align: center; }
        .sidebar a .badge-count { margin-left: auto; background: #fbbf24; color: #0a0e17; font-size: 10px; font-weight: bold; padding: 2px 7px; border-radius: 10px; }

        .main { flex: 1; margin-left: 250px; min-width: 0; }

        .topbar { background-color: #0d1420; border-bottom: 1px solid #1a2332; padding: 12px 24px; display: flex; align-items: center; gap: 16px; position: sticky; top: 0; z-index: 100; }
        .hamburger { display: none; background: none; border: none; color: #22d3ee; font-size: 24px; cursor: pointer; padding: 4px 8px; }
        .search-box { flex: 1; max-width: 500px; }
        .search-box input { width: 100%; padding: 10px 16px; background: #131c2e; border: 1px solid #1a2332; color: white; border-radius: 20px; font-size: 13px; }
        .search-box input:focus { outline: none; border-color: #22d3ee; }
        .topbar-actions { margin-left: auto; display: flex; align-items: center; gap: 12px; }
        .pill-btn { padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 6px; transition: 0.15s; }
        .pill-primary { background: #22d3ee; color: #0a0e17; }
        .pill-primary:hover { background: #06b6d4; }
        .pill-gold { background: #fbbf24; color: #0a0e17; }
        .pill-gold:hover { background: #f59e0b; }
        .pill-outline { background: transparent; border: 1px solid #1a2332; color: #94a3b8; }
        .pill-outline:hover { border-color: #22d3ee; color: #22d3ee; }
        .balance-pill { background: #131c2e; padding: 8px 14px; border-radius: 20px; font-size: 13px; border: 1px solid #1a2332; color: #10b981; font-weight: bold; }
        .icon-btn { background: none; border: none; color: #94a3b8; font-size: 18px; cursor: pointer; padding: 6px; position: relative; }
        .icon-btn:hover { color: #22d3ee; }
        .icon-btn .dot { position: absolute; top: 4px; right: 4px; width: 8px; height: 8px; background: #ef4444; border-radius: 50%; }

        /* USER MENU */
        .user-menu { position: relative; }
        .user-btn { display: flex; align-items: center; gap: 8px; background: #131c2e; border: 1px solid #1a2332; padding: 6px 12px; border-radius: 20px; cursor: pointer; color: #e2e8f0; font-size: 13px; transition: 0.15s; }
        .user-btn:hover { border-color: #22d3ee; }
        .user-avatar { width: 26px; height: 26px; border-radius: 50%; background: linear-gradient(135deg, #22d3ee, #8b5cf6); display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 11px; color: white; position: relative; }
        .user-avatar::after {
            content: '';
            position: absolute;
            bottom: 0;
            right: 0;
            width: 8px;
            height: 8px;
            background: #10b981;
            border: 2px solid #131c2e;
            border-radius: 50%;
        }

        .dropdown {
            position: absolute;
            top: 52px;
            right: 0;
            background: #0d1420;
            border: 1px solid #1a2332;
            border-radius: 14px;
            width: 280px;
            max-width: calc(100vw - 32px);
            padding: 0;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.7);
            display: none;
            z-index: 200;
            overflow: hidden;
            animation: dropdownFadeIn 0.15s ease-out;
            left: auto;
        }
        @keyframes dropdownFadeIn {
            from { opacity: 0; transform: translateY(-6px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .dropdown.show { display: block; }

        .dropdown-header {
            padding: 16px 18px;
            background: linear-gradient(135deg, #131c2e 0%, #0d1420 100%);
            border-bottom: 1px solid #1a2332;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .dropdown-header .avatar-lg {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, #22d3ee, #8b5cf6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 17px;
            flex-shrink: 0;
            position: relative;
        }
        .dropdown-header .avatar-lg::after {
            content: '';
            position: absolute;
            bottom: 2px;
            right: 2px;
            width: 10px;
            height: 10px;
            background: #10b981;
            border: 2px solid #131c2e;
            border-radius: 50%;
        }
        .dropdown-header .user-info { flex: 1; min-width: 0; }
        .dropdown-header .name { color: #fff; font-weight: 600; font-size: 14.5px; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .dropdown-header .meta { display: flex; align-items: center; gap: 6px; font-size: 11px; flex-wrap: wrap; }
        .dropdown-header .meta .role-label { color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; }
        .dropdown-header .meta .tier-badge {
            padding: 2px 8px;
            border-radius: 8px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .dropdown-balance {
            padding: 12px 18px;
            border-bottom: 1px solid #1a2332;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .dropdown-balance .lbl { font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; }
        .dropdown-balance .val { color: #10b981; font-weight: 700; font-size: 15px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 60%; text-align: right; }

        .dropdown-section { padding: 6px 0; border-bottom: 1px solid #1a2332; }
        .dropdown-section:last-child { border-bottom: none; }
        .dropdown-section-label { padding: 8px 18px 4px 18px; font-size: 10px; color: #4b5563; text-transform: uppercase; letter-spacing: 1.2px; font-weight: 700; }

        .dropdown a, .dropdown button {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
            padding: 11px 18px;
            background: none;
            border: none;
            color: #94a3b8;
            font-size: 13.5px;
            text-align: left;
            cursor: pointer;
            transition: background 0.15s, color 0.15s;
            font-family: inherit;
            min-height: 44px;
        }
        .dropdown a:hover, .dropdown button:hover { background: #131c2e; color: #22d3ee; }
        .dropdown a .dd-icon, .dropdown button .dd-icon {
            width: 22px;
            height: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }
        .dropdown a .dd-label { flex: 1; }
        .dropdown a .dd-arrow { color: #4b5563; font-size: 12px; }
        .dropdown a:hover .dd-arrow { color: #22d3ee; }

        .dropdown a.active-page {
            background: rgba(34, 211, 238, 0.06);
            color: #22d3ee;
            border-left: 3px solid #22d3ee;
            padding-left: 15px;
        }

        .dropdown .signout-btn { color: #ef4444; }
        .dropdown .signout-btn:hover { background: rgba(239, 68, 68, 0.08); color: #ef4444; }

        /* NOTIFICATIONS */
        .notif-menu { position: relative; }
        .notif-dropdown { position: absolute; top: 50px; right: 0; background: #0d1420; border: 1px solid #1a2332; border-radius: 12px; width: 340px; max-height: 480px; overflow-y: auto; box-shadow: 0 10px 40px rgba(0,0,0,0.6); display: none; z-index: 200; }
        .notif-dropdown.show { display: block; }
        .notif-header { padding: 14px 18px; border-bottom: 1px solid #1a2332; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; background: #0d1420; z-index: 2; }
        .notif-header h4 { color: #fff; font-size: 14px; font-weight: 600; margin: 0; }
        .notif-header .count-badge { background: #ef4444; color: white; font-size: 10px; font-weight: bold; padding: 2px 8px; border-radius: 10px; }
        .notif-item { display: flex; gap: 12px; padding: 14px 18px; border-bottom: 1px solid #1a2332; text-decoration: none; color: inherit; transition: background 0.15s; }
        .notif-item:hover { background: #131c2e; }
        .notif-item .notif-icon { font-size: 20px; flex-shrink: 0; }
        .notif-item .notif-body { flex: 1; min-width: 0; }
        .notif-item .notif-text { color: #e2e8f0; font-size: 13px; line-height: 1.5; margin-bottom: 4px; word-wrap: break-word; }
        .notif-item .notif-time { color: #6b7280; font-size: 11px; }
        .notif-empty { padding: 40px 20px; text-align: center; color: #6b7280; font-size: 13px; }
        .notif-empty .empty-icon { font-size: 32px; margin-bottom: 10px; display: block; }

        .container { max-width: 1300px; margin: 0 auto; padding: 26px; width: 100%; overflow-x: hidden; }
        .card { background-color: #0d1420; border: 1px solid #1a2332; border-radius: 12px; padding: 20px; margin-bottom: 18px; }
        .card-hover:hover { border-color: #22d3ee44; }

        .btn { background-color: #22d3ee; color: #0a0e17; padding: 10px 18px; border: none; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 600; transition: 0.15s; display: inline-flex; align-items: center; justify-content: center; gap: 6px; }
        .btn:hover { background-color: #06b6d4; }
        .btn-block { width: 100%; }
        .btn-outline { background: transparent; border: 1px solid #1a2332; color: #94a3b8; }
        .btn-outline:hover { border-color: #22d3ee; color: #22d3ee; background: transparent; }
        .btn-danger { background-color: #ef4444; color: white; }
        .btn-danger:hover { background-color: #dc2626; }
        .btn-success { background-color: #10b981; color: white; }
        .btn-success:hover { background-color: #059669; }
        .btn-gold { background-color: #fbbf24; color: #0a0e17; }
        .btn-gold:hover { background-color: #f59e0b; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }

        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 14px; }

        .stat-card { background-color: #0d1420; border: 1px solid #1a2332; border-radius: 12px; padding: 18px; }
        .stat-card .stat-label { font-size: 10px; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; font-weight: 600; }
        .stat-card .stat-value { font-size: 24px; font-weight: 700; }
        .stat-card.cyan .stat-value { color: #22d3ee; }
        .stat-card.green .stat-value { color: #10b981; }
        .stat-card.yellow .stat-value { color: #fbbf24; }
        .stat-card.purple .stat-value { color: #8b5cf6; }

        .badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .badge-green { background: rgba(16, 185, 129, 0.15); color: #10b981; }
        .badge-red { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
        .badge-yellow { background: rgba(251, 191, 36, 0.15); color: #fbbf24; }
        .badge-cyan { background: rgba(34, 211, 238, 0.15); color: #22d3ee; }
        .badge-purple { background: rgba(139, 92, 246, 0.15); color: #8b5cf6; }

        input[type="text"], input[type="password"], input[type="email"], input[type="number"], select, textarea {
            width: 100%; padding: 11px 14px; margin: 4px 0; background: #0a0e17; border: 1px solid #1a2332; color: white; border-radius: 8px; font-family: inherit; font-size: 13.5px;
        }
        input:focus, select:focus, textarea:focus { outline: none; border-color: #22d3ee; }
        label { font-size: 12px; color: #94a3b8; display: block; margin-top: 12px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }

        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #1a2332; font-size: 13px; }
        th { color: #6b7280; font-weight: 600; font-size: 10px; text-transform: uppercase; letter-spacing: 1px; }
        tr:hover td { background-color: rgba(34, 211, 238, 0.03); }

        .page-title { font-size: 26px; font-weight: 700; margin-bottom: 6px; }
        .page-subtitle { color: #6b7280; font-size: 13px; margin-bottom: 24px; }

        .news-card { background-color: #0d1420; border: 1px solid #1a2332; border-radius: 12px; padding: 20px; margin-bottom: 14px; }
        .news-card h3 { font-size: 16px; margin-bottom: 8px; color: #fff; }
        .news-card p { color: #94a3b8; font-size: 13.5px; line-height: 1.6; margin-bottom: 14px; }
        .news-footer { display: flex; align-items: center; gap: 10px; padding-top: 12px; border-top: 1px solid #1a2332; color: #6b7280; font-size: 11px; }
        .news-icon { width: 32px; height: 32px; background: linear-gradient(135deg, #22d3ee, #8b5cf6); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 16px; }

        .progress-bar { background: #1a2332; height: 8px; border-radius: 4px; overflow: hidden; }
        .progress-fill { background: linear-gradient(90deg, #fbbf24, #f59e0b); height: 100%; border-radius: 4px; transition: width 0.4s; }

        .alert { padding: 12px 16px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; }
        .alert-success { background: rgba(16, 185, 129, 0.1); border-left: 3px solid #10b981; color: #10b981; }
        .alert-error { background: rgba(239, 68, 68, 0.1); border-left: 3px solid #ef4444; color: #ef4444; }
        .alert-warning { background: rgba(251, 191, 36, 0.1); border-left: 3px solid #fbbf24; color: #fbbf24; }

        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 998; }
        .sidebar-overlay.show { display: block; }

        .mobile-back { display: none; background: transparent; border: 1px solid #1a2332; color: #94a3b8; padding: 8px 14px; border-radius: 8px; font-size: 13px; cursor: pointer; margin-bottom: 16px; }
        .mobile-back:hover { border-color: #22d3ee; color: #22d3ee; }

        @media (max-width: 900px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main { margin-left: 0; }
            .hamburger { display: block; }
            .topbar { padding: 10px 14px; gap: 8px; }
            .search-box { display: none; }
            .container { padding: 16px; }
            .pill-btn span.hide-mobile { display: none; }
            .pill-btn { padding: 8px 12px; }

            .notif-dropdown { width: calc(100vw - 24px); right: -80px; }

            /* Dropdown anchored to viewport on mobile */
            .dropdown {
                position: fixed;
                top: 64px;
                right: 12px;
                left: auto;
                width: calc(100vw - 24px);
                max-width: 340px;
            }

            .mobile-back { display: inline-block; }
            .page-subtitle { font-size: 14px; }
            .stat-card .stat-value { font-size: 22px; }
        }
    </style>
</head>
<body>
<div class="layout">

    <div class="sidebar-overlay" id="sidebar-overlay" onclick="toggleSidebar()"></div>

    <aside class="sidebar" id="sidebar">
        <a href="home.php" class="logo">◆ <?= SITE_NAME ?></a>

        <?php if(isset($_SESSION['user_id'])): ?>
            <h4>Main</h4>
            <a href="home.php" class="<?= $current_page === 'home.php' ? 'active' : '' ?>"><span class="icon">🏠</span> Home</a>
            <a href="orders.php" class="<?= $current_page === 'orders.php' ? 'active' : '' ?>"><span class="icon">📦</span> My Orders</a>
            <a href="cart.php" class="<?= $current_page === 'cart.php' ? 'active' : '' ?>"><span class="icon">🛒</span> Cart</a>
            <a href="topup.php" class="<?= $current_page === 'topup.php' ? 'active' : '' ?>"><span class="icon">💳</span> Add Funds</a>

            <h4>Shops</h4>
            <?php foreach($sidebar_categories as $cat): ?>
                <a href="category.php?id=<?= (int)$cat['id'] ?>" class="<?= ($current_page === 'category.php' && (int)($_GET['id'] ?? 0) === (int)$cat['id']) ? 'active' : '' ?>">
                    <span class="icon">📁</span> <?= htmlspecialchars($cat['name']) ?>
                </a>
            <?php endforeach; ?>

            <h4>Community</h4>
            <a href="news.php" class="<?= $current_page === 'news.php' ? 'active' : '' ?>"><span class="icon">📢</span> News Feed</a>

            <?php if (isAdmin()): ?>
                <h4>Admin</h4>
                <a href="admin.php" class="admin-link <?= $current_page === 'admin.php' ? 'active' : '' ?>">
                    <span class="icon">⚙</span> Admin Panel
                    <?php if ($open_ticket_count > 0): ?>
                        <span class="badge-count"><?= (int)$open_ticket_count ?></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>

            <h4>Support</h4>
            <a href="support.php" class="<?= $current_page === 'support.php' ? 'active' : '' ?>"><span class="icon">💬</span> Support</a>
            <a href="rules.php" class="<?= $current_page === 'rules.php' ? 'active' : '' ?>"><span class="icon">📜</span> Rules</a>
            <?php if (defined('TELEGRAM_SUPPORT') && TELEGRAM_SUPPORT): ?>
                <a href="<?= htmlspecialchars(TELEGRAM_SUPPORT) ?>" target="_blank" rel="noopener noreferrer">
                    <span class="icon">📱</span> Telegram
                </a>
            <?php endif; ?>
            <?php if (defined('TELEGRAM_CHANNEL') && TELEGRAM_CHANNEL): ?>
                <a href="<?= htmlspecialchars(TELEGRAM_CHANNEL) ?>" target="_blank" rel="noopener noreferrer">
                    <span class="icon">📢</span> Announcements
                </a>
            <?php endif; ?>
            <a href="logout.php" class="signout"><span class="icon">🚪</span> Sign Out</a>
        <?php else: ?>
            <h4>Welcome</h4>
            <a href="index.php"><span class="icon">🔑</span> Login</a>
            <a href="register.php"><span class="icon">✍️</span> Register</a>
        <?php endif; ?>
    </aside>

    <div class="main">
        <?php if(isset($_SESSION['user_id'])): ?>
        <div class="topbar">
            <button class="hamburger" onclick="toggleSidebar()">☰</button>
            <form method="GET" action="search.php" class="search-box">
                <input type="text" name="q" placeholder="🔍  Search products..." maxlength="60">
            </form>
            <div class="topbar-actions">
                <span class="balance-pill">$<?= number_format($_SESSION['balance'] ?? 0, 2) ?></span>
                <a href="topup.php" class="pill-btn pill-gold">
                    <span>💳</span><span class="hide-mobile">Add Funds</span>
                </a>
                <a href="cart.php" class="icon-btn" title="Cart">🛒</a>

                <div class="notif-menu">
                    <button class="icon-btn" id="notifBtn" title="Notifications" onclick="toggleNotif()">
                        🔔<span class="dot" id="notifDot" style="display: none;"></span>
                    </button>
                    <div class="notif-dropdown" id="notifDropdown">
                        <div class="notif-header">
                            <h4>Notifications</h4>
                            <span class="count-badge" id="notifCount" style="display: none;">0</span>
                        </div>
                        <div id="notifList">
                            <div class="notif-empty">
                                <span class="empty-icon">⏳</span>
                                Loading...
                            </div>
                        </div>
                    </div>
                </div>

                <div class="user-menu">
                    <button class="user-btn" onclick="toggleDropdown()">
                        <div class="user-avatar"><?= strtoupper(substr($_SESSION['username'], 0, 1)) ?></div>
                        <span><?= htmlspecialchars($_SESSION['username']) ?></span>
                        <span style="font-size: 10px;">▼</span>
                    </button>

                    <div class="dropdown" id="userDropdown">
                        <div class="dropdown-header">
                            <div class="avatar-lg"><?= strtoupper(substr($_SESSION['username'], 0, 1)) ?></div>
                            <div class="user-info">
                                <div class="name"><?= htmlspecialchars($_SESSION['username']) ?></div>
                                <div class="meta">
                                    <span class="role-label"><?= htmlspecialchars($_SESSION['role'] ?? 'user') ?></span>
                                    <span class="tier-badge" style="background: <?= $user_tier_color ?>22; color: <?= $user_tier_color ?>;">
                                        🏅 <?= $user_tier_name ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="dropdown-balance">
                            <span class="lbl">Balance</span>
                            <span class="val">$<?= number_format($_SESSION['balance'] ?? 0, 2) ?></span>
                        </div>

                        <div class="dropdown-section">
                            <div class="dropdown-section-label">Account</div>
                            <a href="orders.php" class="<?= $current_page === 'orders.php' ? 'active-page' : '' ?>">
                                <span class="dd-icon">📦</span>
                                <span class="dd-label">My Orders</span>
                                <span class="dd-arrow">→</span>
                            </a>
                            <a href="topup.php" class="<?= $current_page === 'topup.php' ? 'active-page' : '' ?>">
                                <span class="dd-icon">💳</span>
                                <span class="dd-label">Add Funds</span>
                                <span class="dd-arrow">→</span>
                            </a>
                        </div>

                        <div class="dropdown-section">
                            <div class="dropdown-section-label">Preferences</div>
                            <a href="profile.php" class="<?= $current_page === 'profile.php' ? 'active-page' : '' ?>">
                                <span class="dd-icon">⚙</span>
                                <span class="dd-label">My Settings</span>
                                <span class="dd-arrow">→</span>
                            </a>
                            <?php if (defined('TELEGRAM_SUPPORT') && TELEGRAM_SUPPORT): ?>
                                <a href="<?= htmlspecialchars(TELEGRAM_SUPPORT) ?>" target="_blank" rel="noopener noreferrer">
                                    <span class="dd-icon">📱</span>
                                    <span class="dd-label">Telegram</span>
                                    <span class="dd-arrow">↗</span>
                                </a>
                            <?php endif; ?>
                        </div>

                        <div class="dropdown-section">
                            <div class="dropdown-section-label">Help</div>
                            <a href="support.php" class="<?= $current_page === 'support.php' ? 'active-page' : '' ?>">
                                <span class="dd-icon">💬</span>
                                <span class="dd-label">Support</span>
                                <span class="dd-arrow">→</span>
                            </a>
                        </div>

                        <div class="dropdown-section">
                            <a href="logout.php" class="signout-btn">
                                <span class="dd-icon">🚪</span>
                                <span class="dd-label">Sign Out</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <div class="container">