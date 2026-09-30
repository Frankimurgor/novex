<?php
require 'db.php';
require 'security.php';
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

$wallets = getWallets();
$btc_wallet = $wallets['BTC'];
$message = '';
$messageType = 'success';

// Handle deposit submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'deposit') {
    verifyCsrf();
    rateLimit('topup_' . $_SESSION['user_id'], 10, 300);

    $amount = floatval($_POST['amount'] ?? 0);
    $crypto = 'BTC';
    $txhash = cleanString($_POST['tx_hash'] ?? '', 128);

    if ($amount < 10) {
        $message = "Minimum deposit is $10.00.";
        $messageType = 'error';
    } elseif ($amount > 100000) {
        $message = "Maximum deposit is $100,000.00.";
        $messageType = 'error';
    } elseif (!isValidTxHash($txhash)) {
        $message = "Invalid transaction hash. Use 10-128 alphanumeric characters.";
        $messageType = 'error';
    } else {
        $bonus_applied = 0;
        if ($amount >= BONUS_THRESHOLD) {
            $bonus_applied = BONUS_AMOUNT;
        }
        $stmt = $pdo->prepare("INSERT INTO deposits (user_id, amount_usd, crypto, tx_hash, address, status) VALUES (?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$_SESSION['user_id'], $amount, $crypto, $txhash, $btc_wallet['address']]);
        $message = "✓ Deposit of $" . number_format($amount, 2) . " submitted!";
        if ($bonus_applied) {
            $message .= " A $" . number_format($bonus_applied, 2) . " bonus will be applied upon confirmation.";
        }
    }
}

// Cart-based suggestion
$cart_total = 0;
if (!empty($_SESSION['cart'])) {
    $ids = implode(',', array_fill(0, count($_SESSION['cart']), '?'));
    $stmt = $pdo->prepare("SELECT SUM(price) FROM products WHERE id IN ($ids)");
    $stmt->execute($_SESSION['cart']);
    $cart_total = floatval($stmt->fetchColumn());
}

// Recent deposits
$recent = $pdo->prepare("SELECT * FROM deposits WHERE user_id = ? ORDER BY id DESC LIMIT 5");
$recent->execute([$_SESSION['user_id']]);
$recent = $recent->fetchAll();

// Bonus progress
$progress = min(100, ($cart_total / BONUS_THRESHOLD) * 100);

include 'header.php';
?>

<h1 class="page-title">Top Up Balance</h1>
<p class="page-subtitle">Add funds using Bitcoin. Minimum deposit $10. Funds credited after 3 network confirmations.</p>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if ($cart_total > 0 && $_SESSION['balance'] < $cart_total): ?>
    <div class="card" style="border-color: #fbbf2444; background: rgba(251, 191, 36, 0.04);">
        <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
            <span style="font-size: 26px;">🛒</span>
            <div style="flex: 1; min-width: 200px;">
                <strong style="color: #fbbf24;">Deposit amount set from your cart ($<?= number_format($cart_total, 2) ?>)</strong>
                <p style="color: #94a3b8; font-size: 12.5px; margin-top: 4px;">You need about $<?= number_format($cart_total - $_SESSION['balance'], 2) ?> more to cover your cart.</p>
            </div>
            <a href="cart.php" class="btn btn-outline btn-sm">View Cart</a>
        </div>
    </div>
<?php endif; ?>

<form method="POST" id="depositForm">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="deposit">

    <div class="topup-grid">

        <!-- LEFT COLUMN -->
        <div>
            <div class="card">
                <h3 style="margin-bottom: 14px;">1. Choose Amount</h3>

                <label>Quick Select</label>
                <div style="display: flex; gap: 8px; flex-wrap: wrap; margin: 10px 0 16px 0;">
                    <?php foreach ([25, 50, 100, 150, 250, 500] as $amt): ?>
                        <button type="button" class="pill-btn pill-outline quick-amount" data-amount="<?= $amt ?>">
                            $<?= $amt ?>
                            <?php if ($amt >= BONUS_THRESHOLD): ?>
                                <span style="color: #fbbf24; font-size: 10px; margin-left: 4px;">+$<?= BONUS_AMOUNT ?></span>
                            <?php endif; ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <label>Custom Amount (USD)</label>
                <input type="number" name="amount" id="amountField" min="10" max="100000" step="0.01" placeholder="Enter amount..." value="<?= $cart_total ?: '' ?>">
                <p style="color: #6b7280; font-size: 11px; margin-top: 6px;">Minimum: $10.00</p>

                <div style="margin-top: 18px; background: #0a0e17; padding: 14px; border-radius: 8px; border: 1px solid #1a2332;">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                        <span style="font-size: 18px;">🎁</span>
                        <div>
                            <strong style="color: #fbbf24; font-size: 13px;">Get $<?= BONUS_AMOUNT ?> Bonus</strong>
                            <p style="color: #6b7280; font-size: 11px; margin-top: 2px;">Deposit $<?= BONUS_THRESHOLD ?> or more for instant bonus</p>
                        </div>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= $progress ?>%;" id="bonusProgress"></div>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-top: 6px; font-size: 11px; color: #6b7280;">
                        <span id="progressText">$<?= number_format($cart_total ?: 0, 2) ?> / $<?= BONUS_THRESHOLD ?></span>
                        <span id="progressRemaining">
                            <?php if ($cart_total < BONUS_THRESHOLD): ?>
                                Add $<?= number_format(BONUS_THRESHOLD - $cart_total, 2) ?> more
                            <?php else: ?>
                                ✓ Bonus unlocked!
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="card" style="border-color: #f7931a44; background: rgba(247, 147, 26, 0.04);">
                <div style="display: flex; align-items: center; gap: 14px;">
                    <div style="width: 44px; height: 44px; background: #f7931a; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 22px; color: white; font-weight: bold; flex-shrink: 0;">₿</div>
                    <div>
                        <h3 style="margin-bottom: 4px; color: #f7931a;">Bitcoin (BTC) Only</h3>
                        <p style="color: #94a3b8; font-size: 12.5px;">Send <strong>only Bitcoin</strong> to this address. Sending any other crypto will result in permanent loss of funds.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN -->
        <div>
            <div class="card">
                <h3 style="margin-bottom: 14px;">2. Send Payment</h3>

                <div style="text-align: center; padding: 14px; background: #0a0e17; border-radius: 10px; margin-bottom: 14px;">
                    <div style="background: white; padding: 12px; display: inline-block; border-radius: 10px;">
                        <img id="qrCode" src="https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=<?= urlencode($btc_wallet['address']) ?>"
                            alt="QR" style="width: 160px; height: 160px; display: block; max-width: 100%;">
                    </div>
                    <p style="color: #6b7280; font-size: 11px; margin-top: 8px;">Scan to copy address</p>
                </div>

                <label>Bitcoin Deposit Address</label>
                <div style="display: flex; gap: 8px; margin-bottom: 14px;">
                    <input type="text" id="walletAddress" readonly value="<?= htmlspecialchars($btc_wallet['address']) ?>" style="font-family: monospace; font-size: 11px;">
                    <button type="button" class="btn btn-sm" onclick="copyAddress()">📋</button>
                </div>

                <div class="alert alert-warning" style="font-size: 11.5px;">
                    ⚠ Send only Bitcoin (BTC) to this address. Other assets will be lost permanently.
                </div>

                <label>Transaction Hash / ID</label>
                <input type="text" name="tx_hash" placeholder="Paste your BTC transaction hash..." required maxlength="128" style="font-family: monospace; font-size: 12px;">

                <button type="submit" class="btn btn-block btn-success" style="margin-top: 18px;">
                    ✓ I've Sent the Payment
                </button>

                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-top: 18px; text-align: center;">
                    <div style="padding: 10px; background: #0a0e17; border-radius: 8px;">
                        <div style="font-size: 20px;">📤</div>
                        <div style="font-size: 10px; color: #6b7280; text-transform: uppercase; margin-top: 4px;">Send</div>
                    </div>
                    <div style="padding: 10px; background: #0a0e17; border-radius: 8px;">
                        <div style="font-size: 20px;">🛡</div>
                        <div style="font-size: 10px; color: #6b7280; text-transform: uppercase; margin-top: 4px;">Confirm</div>
                    </div>
                    <div style="padding: 10px; background: #0a0e17; border-radius: 8px;">
                        <div style="font-size: 20px;">✓</div>
                        <div style="font-size: 10px; color: #6b7280; text-transform: uppercase; margin-top: 4px;">Credit</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<h3 style="color: #22d3ee; font-size: 13px; letter-spacing: 2px; text-transform: uppercase; margin: 30px 0 14px;">// Recent Deposits</h3>

<?php if (empty($recent)): ?>
    <div class="card" style="text-align: center; padding: 30px;">
        <p style="color: #6b7280; font-size: 13px;">No deposits submitted yet.</p>
    </div>
<?php else: ?>
    <div class="card" style="padding: 0; overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Amount</th>
                    <th>Crypto</th>
                    <th>TX Hash</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent as $d): ?>
                    <tr>
                        <td><?= (int)$d['id'] ?></td>
                        <td style="color: #10b981; font-weight: 600;">$<?= number_format($d['amount_usd'], 2) ?></td>
                        <td><?= htmlspecialchars($d['crypto']) ?></td>
                        <td style="font-family: monospace; font-size: 11px;"><?= htmlspecialchars(substr($d['tx_hash'] ?? '', 0, 20)) ?>...</td>
                        <td>
                            <?php if ($d['status'] === 'confirmed'): ?>
                                <span class="badge badge-green">confirmed</span>
                            <?php elseif ($d['status'] === 'rejected'): ?>
                                <span class="badge badge-red">rejected</span>
                            <?php else: ?>
                                <span class="badge badge-yellow">pending</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size: 11px; color: #6b7280;"><?= novexFormatTime($d['created_at'], 'M d, H:i') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<style>
.topup-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 22px;
}
@media (max-width: 900px) {
    .topup-grid {
        grid-template-columns: 1fr;
        gap: 16px;
    }
}
</style>

<script>
document.querySelectorAll('.quick-amount').forEach(btn => {
    btn.addEventListener('click', function() {
        const amt = this.dataset.amount;
        document.getElementById('amountField').value = amt;
        updateProgress(amt);
    });
});

document.getElementById('amountField').addEventListener('input', function() {
    updateProgress(this.value);
});

function updateProgress(val) {
    val = parseFloat(val) || 0;
    const pct = Math.min(100, (val / <?= BONUS_THRESHOLD ?>) * 100);
    document.getElementById('bonusProgress').style.width = pct + '%';
    document.getElementById('progressText').textContent = '$' + val.toFixed(2) + ' / $<?= BONUS_THRESHOLD ?>';

    const rem = <?= BONUS_THRESHOLD ?> - val;
    document.getElementById('progressRemaining').textContent = rem > 0
        ? 'Add $' + rem.toFixed(2) + ' more'
        : '✓ Bonus unlocked!';
}

function copyAddress() {
    const input = document.getElementById('walletAddress');
    input.select();
    document.execCommand('copy');
    alert('Address copied!');
}
</script>

<?php include 'footer.php'; ?>