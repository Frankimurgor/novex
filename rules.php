<?php
require 'db.php';
require 'security.php';
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

include 'header.php';
?>

<h1 class="page-title">Rules & Guidelines</h1>
<p class="page-subtitle">Please read carefully before making any purchase.</p>

<div class="card" style="border-left: 4px solid #fbbf24;">
    <div style="display: flex; gap: 14px; align-items: start;">
        <span style="font-size: 26px;">⚠️</span>
        <div>
            <h3 style="color: #fbbf24; margin-bottom: 6px;">Important Notice</h3>
            <p style="color: #94a3b8; font-size: 13.5px; line-height: 1.7;">
                By using <?= SITE_NAME ?>, you agree to abide by all rules listed below. Violation of any rule may result in account suspension or permanent ban without refund.
            </p>
        </div>
    </div>
</div>

<h3 style="color: #22d3ee; font-size: 13px; letter-spacing: 2px; text-transform: uppercase; margin: 30px 0 14px;">// General Rules</h3>

<div class="card">
    <div style="display: flex; gap: 14px; margin-bottom: 18px;">
        <div style="width: 34px; height: 34px; background: #131c2e; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #22d3ee; font-weight: bold; flex-shrink: 0;">1</div>
        <div>
            <h4 style="margin-bottom: 6px;">No Sharing of Account Details</h4>
            <p style="color: #94a3b8; font-size: 13px; line-height: 1.6;">
                Your purchases are for personal use only. Sharing, reselling, or redistributing any item purchased from <?= SITE_NAME ?> is strictly prohibited.
            </p>
        </div>
    </div>

    <div style="display: flex; gap: 14px; margin-bottom: 18px;">
        <div style="width: 34px; height: 34px; background: #131c2e; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #22d3ee; font-weight: bold; flex-shrink: 0;">2</div>
        <div>
            <h4 style="margin-bottom: 6px;">One Account Per User</h4>
            <p style="color: #94a3b8; font-size: 13px; line-height: 1.6;">
                Multiple accounts created to exploit bonuses, promotions, or referral rewards will be terminated without notice.
            </p>
        </div>
    </div>

    <div style="display: flex; gap: 14px; margin-bottom: 18px;">
        <div style="width: 34px; height: 34px; background: #131c2e; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #22d3ee; font-weight: bold; flex-shrink: 0;">3</div>
        <div>
            <h4 style="margin-bottom: 6px;">Respect Support Staff</h4>
            <p style="color: #94a3b8; font-size: 13px; line-height: 1.6;">
                Abusive language, threats, or harassment directed at our team will result in immediate account termination.
            </p>
        </div>
    </div>

    <div style="display: flex; gap: 14px;">
        <div style="width: 34px; height: 34px; background: #131c2e; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #22d3ee; font-weight: bold; flex-shrink: 0;">4</div>
        <div>
            <h4 style="margin-bottom: 6px;">Accurate Information</h4>
            <p style="color: #94a3b8; font-size: 13px; line-height: 1.6;">
                Provide truthful registration details. Fraudulent or misleading information may lead to account lockout.
            </p>
        </div>
    </div>
</div>

<h3 style="color: #22d3ee; font-size: 13px; letter-spacing: 2px; text-transform: uppercase; margin: 30px 0 14px;">// Payments & Deposits</h3>

<div class="card">
    <div style="display: flex; gap: 14px; margin-bottom: 18px;">
        <div style="width: 34px; height: 34px; background: #131c2e; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #10b981; font-weight: bold; flex-shrink: 0;">$</div>
        <div>
            <h4 style="margin-bottom: 6px;">Crypto Deposits Only</h4>
            <p style="color: #94a3b8; font-size: 13px; line-height: 1.6;">
                All deposits are processed in cryptocurrency (BTC, USDT, ETH). Ensure you send the correct coin to the correct address.
            </p>
        </div>
    </div>

    <div style="display: flex; gap: 14px; margin-bottom: 18px;">
        <div style="width: 34px; height: 34px; background: #131c2e; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #10b981; font-weight: bold; flex-shrink: 0;">$</div>
        <div>
            <h4 style="margin-bottom: 6px;">Confirmations Required</h4>
            <p style="color: #94a3b8; font-size: 13px; line-height: 1.6;">
                Deposits are credited after 3 network confirmations. This typically takes 10–60 minutes depending on network congestion.
            </p>
        </div>
    </div>

    <div style="display: flex; gap: 14px;">
        <div style="width: 34px; height: 34px; background: #131c2e; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #10b981; font-weight: bold; flex-shrink: 0;">$</div>
        <div>
            <h4 style="margin-bottom: 6px;">No Chargebacks</h4>
            <p style="color: #94a3b8; font-size: 13px; line-height: 1.6;">
                Because crypto transactions are irreversible, all sales are final. Only request a refund if the item is undelivered or invalid.
            </p>
        </div>
    </div>
</div>

<h3 style="color: #22d3ee; font-size: 13px; letter-spacing: 2px; text-transform: uppercase; margin: 30px 0 14px;">// Purchases & Delivery</h3>

<div class="card">
    <div style="display: flex; gap: 14px; margin-bottom: 18px;">
        <div style="width: 34px; height: 34px; background: #131c2e; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #8b5cf6; font-weight: bold; flex-shrink: 0;">✓</div>
        <div>
            <h4 style="margin-bottom: 6px;">Instant Digital Delivery</h4>
            <p style="color: #94a3b8; font-size: 13px; line-height: 1.6;">
                All purchases are delivered instantly to your <a href="orders.php" style="color: #22d3ee;">Orders page</a> after payment. Click "Reveal Details" to view your item.
            </p>
        </div>
    </div>

    <div style="display: flex; gap: 14px; margin-bottom: 18px;">
        <div style="width: 34px; height: 34px; background: #131c2e; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #8b5cf6; font-weight: bold; flex-shrink: 0;">✓</div>
        <div>
            <h4 style="margin-bottom: 6px;">Report Issues Within 24 Hours</h4>
            <p style="color: #94a3b8; font-size: 13px; line-height: 1.6;">
                If a purchased item is invalid or does not work, contact support within 24 hours of purchase. Claims after this period may not be honored.
            </p>
        </div>
    </div>

    <div style="display: flex; gap: 14px;">
        <div style="width: 34px; height: 34px; background: #131c2e; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #8b5cf6; font-weight: bold; flex-shrink: 0;">✓</div>
        <div>
            <h4 style="margin-bottom: 6px;">No Warranty Beyond Delivery</h4>
            <p style="color: #94a3b8; font-size: 13px; line-height: 1.6;">
                Once an item is delivered and confirmed working, no further warranty is offered. Test immediately after purchase.
            </p>
        </div>
    </div>
</div>

<h3 style="color: #22d3ee; font-size: 13px; letter-spacing: 2px; text-transform: uppercase; margin: 30px 0 14px;">// Prohibited Activities</h3>

<div class="card" style="border-left: 4px solid #ef4444;">
    <ul style="list-style: none; padding-left: 0; color: #94a3b8; font-size: 13.5px; line-height: 2;">
        <li>❌ Attempting to hack, exploit, or DDoS the platform</li>
        <li>❌ Using automated bots or scrapers on the site</li>
        <li>❌ Uploading malware or malicious code</li>
        <li>❌ Impersonating other users or staff</li>
        <li>❌ Attempting to reverse-engineer the codebase</li>
        <li>❌ Using stolen payment methods to fund your account</li>
    </ul>
</div>

<div class="card" style="background: #131c2e; border-color: #22d3ee44; text-align: center;">
    <p style="color: #94a3b8; font-size: 13.5px; margin-bottom: 14px;">Questions about these rules? Our support team is here to help.</p>
    <a href="support.php" class="btn">💬 Contact Support</a>
</div>

<?php include 'footer.php'; ?>