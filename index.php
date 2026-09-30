<?php
require 'db.php';
require 'security.php';

// If already logged in, bounce to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: home.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    rateLimit('login_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 5, 300);

    $username = cleanString($_POST['username'] ?? '', 50);
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id']       = $user['id'];
        $_SESSION['username']      = $user['username'];
        $_SESSION['balance']       = $user['balance'];
        $_SESSION['role']          = $user['role'];
        $_SESSION['total_spent']   = $user['total_spent'];
        $_SESSION['fingerprint']   = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $_SESSION['last_activity'] = time();
        header("Location: home.php");
        exit;
    }
    $error = "Invalid username or password.";
}

$online = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE last_seen > NOW() - INTERVAL 5 MINUTE")->fetchColumn();
if ($online < 1) $online = 54;

include 'header.php';
?>

<div style="display: flex; justify-content: center; align-items: center; min-height: 70vh;">
    <div class="card" style="max-width: 420px; width: 100%;">
        <div style="text-align: center; margin-bottom: 24px;">
            <div style="width: 56px; height: 56px; background: linear-gradient(135deg, #22d3ee, #8b5cf6); border-radius: 14px; display: inline-flex; align-items: center; justify-content: center; font-size: 28px; margin-bottom: 14px;">◆</div>
            <h2 style="color: #22d3ee; letter-spacing: 3px;"><?= SITE_NAME ?></h2>
            <p style="color: #6b7280; font-size: 13px; margin-top: 6px;">Secure access to your vault</p>
        </div>

        <div class="alert alert-success" style="display: flex; align-items: center; gap: 8px;">
            <span>🟢</span>
            <span><?= $online ?> users online now</span>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <?= csrfField() ?>
            <label>Username</label>
            <input type="text" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus maxlength="50">

            <label>Password</label>
            <input type="password" name="password" required>

            <button type="submit" class="btn btn-block" style="margin-top: 18px;">Sign In →</button>
        </form>

        <p style="text-align: center; font-size: 13px; margin-top: 18px; color: #6b7280;">
            No account? <a href="register.php" style="color: #22d3ee;">Register here</a>
        </p>
    </div>
</div>

<?php include 'footer.php'; ?>