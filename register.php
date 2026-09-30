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
    rateLimit('register_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 3, 600);

    $username = cleanString($_POST['username'] ?? '', 30);
    $email    = cleanString($_POST['email'] ?? '', 100);
    $password = $_POST['password'] ?? '';

    if (!isValidUsername($username)) {
        $error = "Username must be 3-30 characters (letters, numbers, underscore).";
    } elseif (!isValidEmail($email)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
        try {
            $stmt->execute([$username, $email, $hash]);
            header("Location: index.php");
            exit;
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'username') !== false) {
                $error = "That username is already taken.";
            } elseif (strpos($e->getMessage(), 'email') !== false) {
                $error = "That email is already registered.";
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}
include 'header.php';
?>

<div style="display: flex; justify-content: center; align-items: center; min-height: 70vh;">
    <div class="card" style="max-width: 440px; width: 100%;">

        <div style="text-align: center; margin-bottom: 24px;">
            <div style="width: 56px; height: 56px; background: linear-gradient(135deg, #22d3ee, #8b5cf6); border-radius: 14px; display: inline-flex; align-items: center; justify-content: center; font-size: 26px; margin-bottom: 14px;">◆</div>
            <h2 style="color: #22d3ee; letter-spacing: 3px;">Create Account</h2>
            <p style="color: #6b7280; font-size: 13px; margin-top: 6px;">Join <?= SITE_NAME ?> to explore rare digital items</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <?= csrfField() ?>
            <label>Username</label>
            <input type="text" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus maxlength="30" placeholder="Choose a username" pattern="[a-zA-Z0-9_]{3,30}">

            <label>Email Address</label>
            <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required maxlength="100" placeholder="you@example.com">

            <label>Password</label>
            <input type="password" name="password" required minlength="6" placeholder="Minimum 6 characters">

            <button type="submit" class="btn btn-block" style="margin-top: 20px;">Create Account →</button>
        </form>

        <p style="text-align: center; font-size: 13px; margin-top: 18px; color: #6b7280;">
            Already have an account? <a href="index.php" style="color: #22d3ee;">Sign in here</a>
        </p>

    </div>
</div>

<?php include 'footer.php'; ?>