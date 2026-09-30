<?php
require 'db.php';
require 'security.php';
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

$message = '';
$messageType = 'success';

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    verifyCsrf();
    $current = $_POST['current'] ?? '';
    $new = $_POST['new'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if (strlen($new) < 6) {
        $message = "New password must be at least 6 characters.";
        $messageType = 'error';
    } elseif ($new !== $confirm) {
        $message = "New passwords do not match.";
        $messageType = 'error';
    } else {
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([(int)$_SESSION['user_id']]);
        $hash = $stmt->fetchColumn();
        if (password_verify($current, $hash)) {
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")
                ->execute([password_hash($new, PASSWORD_DEFAULT), (int)$_SESSION['user_id']]);
            $message = "✓ Password updated successfully.";
        } else {
            $message = "Current password is incorrect.";
            $messageType = 'error';
        }
    }
}

// Handle timezone update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_timezone') {
    verifyCsrf();
    $tz = cleanString($_POST['timezone'] ?? 'UTC', 64);
    $allowed = array_keys(timezoneOptions());
    if (in_array($tz, $allowed, true)) {
        $pdo->prepare("UPDATE users SET timezone = ? WHERE id = ?")->execute([$tz, (int)$_SESSION['user_id']]);
        $_SESSION['timezone'] = $tz;
        $message = "✓ Timezone updated.";
        $messageType = 'success';
    } else {
        $message = "Invalid timezone.";
        $messageType = 'error';
    }
}

// Load user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([(int)$_SESSION['user_id']]);
$user = $stmt->fetch();

// Stats
$orders = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
$orders->execute([(int)$_SESSION['user_id']]);
$orders = (int)$orders->fetchColumn();

$deposits = $pdo->prepare("SELECT COUNT(*) FROM deposits WHERE user_id = ?");
$deposits->execute([(int)$_SESSION['user_id']]);
$deposits = (int)$deposits->fetchColumn();

list($tier, $tier_color) = userTier($user['total_spent'] ?? 0);

include 'header.php';
?>

<h1 class="page-title">My Profile</h1>
<p class="page-subtitle">Manage your account settings and view your stats.</p>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="profile-grid">

    <div>
        <div class="card">
            <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 22px; flex-wrap: wrap;">
                <div style="width: 64px; height: 64px; border-radius: 50%; background: linear-gradient(135deg, #22d3ee, #8b5cf6); display: flex; align-items: center; justify-content: center; font-size: 26px; font-weight: bold; color: white; flex-shrink: 0;">
                    <?= strtoupper(substr($user['username'], 0, 1)) ?>
                </div>
                <div style="min-width: 0;">
                    <h2 style="margin-bottom: 4px;"><?= htmlspecialchars($user['username']) ?></h2>
                    <p style="color: #6b7280; font-size: 13px; word-break: break-word;"><?= htmlspecialchars($user['email']) ?></p>
                    <span class="badge" style="background: <?= $tier_color ?>22; color: <?= $tier_color ?>; margin-top: 6px;">
                        🏅 <?= $tier ?>
                    </span>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div style="background: #0a0e17; padding: 14px; border-radius: 8px;">
                    <div style="font-size: 10px; color: #6b7280; text-transform: uppercase; letter-spacing: 1px;">Balance</div>
                    <div style="color: #10b981; font-size: 20px; font-weight: 700; margin-top: 4px; word-break: break-all;">$<?= number_format($user['balance'], 2) ?></div>
                </div>
                <div style="background: #0a0e17; padding: 14px; border-radius: 8px;">
                    <div style="font-size: 10px; color: #6b7280; text-transform: uppercase; letter-spacing: 1px;">Total Spent</div>
                    <div style="color: #22d3ee; font-size: 20px; font-weight: 700; margin-top: 4px; word-break: break-all;">$<?= number_format($user['total_spent'] ?? 0, 2) ?></div>
                </div>
                <div style="background: #0a0e17; padding: 14px; border-radius: 8px;">
                    <div style="font-size: 10px; color: #6b7280; text-transform: uppercase; letter-spacing: 1px;">Orders</div>
                    <div style="color: #8b5cf6; font-size: 20px; font-weight: 700; margin-top: 4px;"><?= $orders ?></div>
                </div>
                <div style="background: #0a0e17; padding: 14px; border-radius: 8px;">
                    <div style="font-size: 10px; color: #6b7280; text-transform: uppercase; letter-spacing: 1px;">Deposits</div>
                    <div style="color: #fbbf24; font-size: 20px; font-weight: 700; margin-top: 4px;"><?= $deposits ?></div>
                </div>
            </div>
        </div>

        <div class="card">
            <h3 style="margin-bottom: 12px;">Account Info</h3>
            <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #1a2332;">
                <span style="color: #6b7280; font-size: 13px;">User ID</span>
                <span style="color: #e2e8f0; font-size: 13px;">#<?= (int)$user['id'] ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #1a2332;">
                <span style="color: #6b7280; font-size: 13px;">Role</span>
                <span class="badge <?= $user['role'] === 'admin' ? 'badge-yellow' : 'badge-cyan' ?>"><?= htmlspecialchars($user['role']) ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 8px 0;">
                <span style="color: #6b7280; font-size: 13px;">Member Since</span>
                <span style="color: #e2e8f0; font-size: 13px;"><?= novexFormatTime($user['created_at'], 'M d, Y') ?></span>
            </div>
        </div>
    </div>

    <div>
        <div class="card">
            <h3 style="margin-bottom: 16px;">🔐 Change Password</h3>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="change_password">

                <label>Current Password</label>
                <input type="password" name="current" required autocomplete="current-password">

                <label>New Password</label>
                <input type="password" name="new" required minlength="6" autocomplete="new-password">

                <label>Confirm New Password</label>
                <input type="password" name="confirm" required minlength="6" autocomplete="new-password">

                <button type="submit" class="btn btn-block" style="margin-top: 18px;">Update Password</button>
            </form>
        </div>

        <div class="card">
            <h3 style="margin-bottom: 12px;">🌍 Timezone</h3>
            <p style="color: #6b7280; font-size: 13px; margin-bottom: 14px;">
                All dates and times will be shown in this timezone.
                Currently: <strong style="color: #22d3ee;"><?= htmlspecialchars($_SESSION['timezone'] ?? 'UTC') ?></strong>
            </p>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="update_timezone">
                <label>Select Timezone</label>
                <select name="timezone" required>
                    <?php foreach (timezoneOptions() as $tz_val => $tz_label): ?>
                        <option value="<?= htmlspecialchars($tz_val) ?>" <?= (($_SESSION['timezone'] ?? 'UTC') === $tz_val) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tz_label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-block" style="margin-top: 14px;">Save Timezone</button>
            </form>
        </div>

        <div class="card">
            <h3 style="margin-bottom: 12px;">🏅 Membership Tiers</h3>
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <div style="display: flex; justify-content: space-between; padding: 10px; background: #0a0e17; border-radius: 8px; <?= (($user['total_spent'] ?? 0) < 300) ? 'border: 1px solid #b45309;' : '' ?>">
                    <span style="color: #b45309; font-weight: 600;">🥉 Bronze</span>
                    <span style="color: #6b7280; font-size: 12px;">$0 - $299 spent</span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 10px; background: #0a0e17; border-radius: 8px; <?= (($user['total_spent'] ?? 0) >= 300 && ($user['total_spent'] ?? 0) < 1000) ? 'border: 1px solid #9ca3af;' : '' ?>">
                    <span style="color: #9ca3af; font-weight: 600;">🥈 Silver</span>
                    <span style="color: #6b7280; font-size: 12px;">$300 - $999 spent</span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 10px; background: #0a0e17; border-radius: 8px; <?= (($user['total_spent'] ?? 0) >= 1000) ? 'border: 1px solid #fbbf24;' : '' ?>">
                    <span style="color: #fbbf24; font-weight: 600;">🥇 Gold</span>
                    <span style="color: #6b7280; font-size: 12px;">$1000+ spent</span>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.profile-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    align-items: start;
}

@media (max-width: 900px) {
    .profile-grid {
        grid-template-columns: 1fr;
        gap: 16px;
    }
}
</style>

<?php include 'footer.php'; ?>