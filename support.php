<?php
require 'db.php';
require 'security.php';
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

$message = '';
$messageType = 'success';

// Ensure tickets table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS tickets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        subject VARCHAR(200) NOT NULL,
        body TEXT NOT NULL,
        status ENUM('open','answered','closed') DEFAULT 'open',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");
} catch (Exception $e) {}

// Submit new ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_ticket') {
    verifyCsrf();
    rateLimit('support_' . $_SESSION['user_id'], 5, 600);

    $subject = cleanString($_POST['subject'] ?? '', 200);
    $body    = cleanString($_POST['body'] ?? '', 3000);

    if (strlen($subject) < 3) {
        $message = "Please enter a subject (at least 3 characters).";
        $messageType = 'error';
    } elseif (strlen($body) < 10) {
        $message = "Please describe your issue (at least 10 characters).";
        $messageType = 'error';
    } else {
        $pdo->prepare("INSERT INTO tickets (user_id, subject, body) VALUES (?, ?, ?)")
            ->execute([(int)$_SESSION['user_id'], $subject, $body]);
        $message = "✓ Ticket submitted. Our team will respond within 24 hours.";
    }
}

// User reply to their own ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reply_ticket_user') {
    verifyCsrf();
    rateLimit('support_reply_' . $_SESSION['user_id'], 20, 600);

    $ticket_id = (int)($_POST['ticket_id'] ?? 0);
    $reply     = cleanString($_POST['reply'] ?? '', 3000);

    $chk = $pdo->prepare("SELECT id FROM tickets WHERE id = ? AND user_id = ?");
    $chk->execute([$ticket_id, (int)$_SESSION['user_id']]);

    if ($chk->fetch() && strlen($reply) >= 2) {
        $pdo->prepare("INSERT INTO ticket_replies (ticket_id, user_id, is_admin, body) VALUES (?, ?, 0, ?)")
            ->execute([$ticket_id, (int)$_SESSION['user_id'], $reply]);
        $pdo->prepare("UPDATE tickets SET status = 'open' WHERE id = ?")->execute([$ticket_id]);
        $message = "✓ Reply sent. Support will respond shortly.";
    } else {
        $message = "Could not send reply.";
        $messageType = 'error';
    }
}

// Load user's tickets
$my_tickets = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM tickets WHERE user_id = ? ORDER BY id DESC LIMIT 20");
    $stmt->execute([(int)$_SESSION['user_id']]);
    $my_tickets = $stmt->fetchAll();
} catch (Exception $e) {}

include 'header.php';
?>

<h1 class="page-title">Support Center</h1>
<p class="page-subtitle">We're here to help. Average response time: under 24 hours.</p>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="grid" style="margin-bottom: 30px;">
    <a href="#new-ticket" class="card card-hover" style="text-decoration: none; color: inherit;">
        <div style="font-size: 26px; margin-bottom: 10px;">💬</div>
        <h3 style="margin-bottom: 4px;">Submit a Ticket</h3>
        <p style="color: #6b7280; font-size: 12.5px;">Describe your issue and we'll respond.</p>
    </a>
    <?php if (defined('TELEGRAM_SUPPORT') && TELEGRAM_SUPPORT): ?>
        <a href="<?= htmlspecialchars(TELEGRAM_SUPPORT) ?>" target="_blank" rel="noopener noreferrer" class="card card-hover" style="text-decoration: none; color: inherit;">
            <div style="font-size: 26px; margin-bottom: 10px;">📱</div>
            <h3 style="margin-bottom: 4px;">Telegram Support</h3>
            <p style="color: #6b7280; font-size: 12.5px;">Chat with our team in real time.</p>
        </a>
    <?php endif; ?>
    <a href="#faq" class="card card-hover" style="text-decoration: none; color: inherit;">
        <div style="font-size: 26px; margin-bottom: 10px;">❓</div>
        <h3 style="margin-bottom: 4px;">FAQ</h3>
        <p style="color: #6b7280; font-size: 12.5px;">Quick answers to common questions.</p>
    </a>
    <a href="rules.php" class="card card-hover" style="text-decoration: none; color: inherit;">
        <div style="font-size: 26px; margin-bottom: 10px;">📜</div>
        <h3 style="margin-bottom: 4px;">Rules</h3>
        <p style="color: #6b7280; font-size: 12.5px;">Read our store rules & guidelines.</p>
    </a>
</div>

<div class="card" id="new-ticket">
    <h3 style="margin-bottom: 14px;">📝 Submit a New Ticket</h3>
    <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="submit_ticket">

        <label>Subject</label>
        <input type="text" name="subject" required maxlength="200" placeholder="Short summary of your issue" value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>">

        <label>Message</label>
        <textarea name="body" rows="6" required maxlength="3000" placeholder="Describe your issue in detail. Include order IDs if relevant."><?= htmlspecialchars($_POST['body'] ?? '') ?></textarea>

        <button type="submit" class="btn btn-block btn-success" style="margin-top: 16px;">Submit Ticket</button>
    </form>
</div>

<?php if (defined('TELEGRAM_SUPPORT') && TELEGRAM_SUPPORT): ?>
    <div class="card" style="background: linear-gradient(135deg, rgba(0, 136, 204, 0.15) 0%, #0d1420 100%); border-color: rgba(0, 136, 204, 0.4); text-align: center;">
        <div style="font-size: 32px; margin-bottom: 8px;">📱</div>
        <h3 style="margin-bottom: 6px;">Prefer Telegram?</h3>
        <p style="color: #94a3b8; font-size: 13px; margin-bottom: 14px;">Chat with our support team for fast responses.</p>
        <a href="<?= htmlspecialchars(TELEGRAM_SUPPORT) ?>" target="_blank" rel="noopener noreferrer"
           class="btn" style="background: #0088cc; color: white;">
            Open Telegram →
        </a>
    </div>
<?php endif; ?>

<?php if (!empty($my_tickets)): ?>
    <h3 style="color: #22d3ee; font-size: 13px; letter-spacing: 2px; text-transform: uppercase; margin: 30px 0 14px;">// Your Tickets</h3>

    <?php foreach ($my_tickets as $t): ?>
        <?php
            $r_stmt = $pdo->prepare("
                SELECT tr.*, u.username
                FROM ticket_replies tr
                LEFT JOIN users u ON tr.user_id = u.id
                WHERE tr.ticket_id = ?
                ORDER BY tr.id ASC
            ");
            $r_stmt->execute([(int)$t['id']]);
            $t_replies = $r_stmt->fetchAll();

            $border = '#fbbf24';
            if ($t['status'] === 'answered') $border = '#10b981';
            elseif ($t['status'] === 'closed') $border = '#22d3ee';
        ?>
        <div class="card" style="border-left: 4px solid <?= $border ?>;">

            <div style="display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap; gap: 12px;">
                <div style="flex: 1; min-width: 240px;">
                    <h4 style="margin-bottom: 6px;"><?= htmlspecialchars($t['subject']) ?></h4>
                    <p style="color: #94a3b8; font-size: 13px; line-height: 1.6; white-space: pre-wrap;"><?= htmlspecialchars($t['body']) ?></p>
                    <span style="color: #4b5563; font-size: 11px; display: block; margin-top: 8px;">
                        Ticket #<?= (int)$t['id'] ?> · <?= date("M d, Y H:i", strtotime($t['created_at'])) ?>
                    </span>
                </div>
                <div>
                    <?php if ($t['status'] === 'open'): ?>
                        <span class="badge badge-yellow">open</span>
                    <?php elseif ($t['status'] === 'answered'): ?>
                        <span class="badge badge-green">answered</span>
                    <?php else: ?>
                        <span class="badge badge-cyan">closed</span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($t_replies)): ?>
                <div style="margin-top: 16px; padding-top: 14px; border-top: 1px solid #1a2332;">
                    <p style="color: #6b7280; font-size: 10px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px;">Conversation</p>

                    <?php foreach ($t_replies as $r): ?>
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
                                        <span class="badge badge-cyan" style="font-size: 9px;">Support</span>
                                    <?php endif; ?>
                                    <span style="color: #4b5563; font-size: 10.5px;"><?= date("M d, H:i", strtotime($r['created_at'])) ?></span>
                                </div>
                                <p style="color: #cbd5e1; font-size: 13px; line-height: 1.6; white-space: pre-wrap; margin: 0;"><?= htmlspecialchars($r['body']) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($t['status'] !== 'closed'): ?>
                <div style="margin-top: 16px; padding-top: 14px; border-top: 1px solid #1a2332;">
                    <form method="POST" style="display: flex; gap: 8px; align-items: end; flex-wrap: wrap;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="reply_ticket_user">
                        <input type="hidden" name="ticket_id" value="<?= (int)$t['id'] ?>">
                        <div style="flex: 1; min-width: 240px;">
                            <label style="margin-top: 0;">Add a reply</label>
                            <textarea name="reply" rows="2" required maxlength="3000" placeholder="Type your message..." style="resize: vertical;"></textarea>
                        </div>
                        <button type="submit" class="btn btn-success">📤 Send</button>
                    </form>
                </div>
            <?php endif; ?>

        </div>
    <?php endforeach; ?>
<?php endif; ?>

<h3 id="faq" style="color: #22d3ee; font-size: 13px; letter-spacing: 2px; text-transform: uppercase; margin: 30px 0 14px;">// Frequently Asked Questions</h3>

<div class="card">
    <details style="cursor: pointer; margin-bottom: 16px;">
        <summary style="font-weight: 600; color: #e2e8f0; font-size: 14px; padding: 6px 0;">How do I add funds to my account?</summary>
        <p style="color: #94a3b8; font-size: 13px; line-height: 1.7; margin-top: 10px; padding-left: 16px;">
            Go to <a href="topup.php" style="color: #22d3ee;">Add Funds</a>, choose your crypto, send the exact amount to the displayed address, and paste your transaction hash. Funds are credited after admin confirmation.
        </p>
    </details>

    <details style="cursor: pointer; margin-bottom: 16px;">
        <summary style="font-weight: 600; color: #e2e8f0; font-size: 14px; padding: 6px 0;">How long does delivery take?</summary>
        <p style="color: #94a3b8; font-size: 13px; line-height: 1.7; margin-top: 10px; padding-left: 16px;">
            Instantly. Once your payment is confirmed, purchased items appear on your <a href="orders.php" style="color: #22d3ee;">Orders page</a>. Click "Reveal Details" to view them.
        </p>
    </details>

    <details style="cursor: pointer; margin-bottom: 16px;">
        <summary style="font-weight: 600; color: #e2e8f0; font-size: 14px; padding: 6px 0;">What if an item doesn't work?</summary>
        <p style="color: #94a3b8; font-size: 13px; line-height: 1.7; margin-top: 10px; padding-left: 16px;">
            Submit a ticket within 24 hours of purchase with your order ID and a description of the issue. We'll investigate and, if valid, issue a replacement or refund.
        </p>
    </details>

    <details style="cursor: pointer; margin-bottom: 16px;">
        <summary style="font-weight: 600; color: #e2e8f0; font-size: 14px; padding: 6px 0;">Can I get a refund?</summary>
        <p style="color: #94a3b8; font-size: 13px; line-height: 1.7; margin-top: 10px; padding-left: 16px;">
            Refunds are only issued if an item is undelivered or completely invalid. Please see our <a href="rules.php" style="color: #22d3ee;">Rules page</a> for details.
        </p>
    </details>

    <details style="cursor: pointer;">
        <summary style="font-weight: 600; color: #e2e8f0; font-size: 14px; padding: 6px 0;">How do I contact you?</summary>
        <p style="color: #94a3b8; font-size: 13px; line-height: 1.7; margin-top: 10px; padding-left: 16px;">
            Submit a ticket using the form above, or reach out via Telegram for faster responses.
        </p>
    </details>
</div>

<?php include 'footer.php'; ?>