<?php
/**
 * NOVEX Security Core
 * Centralizes: session hardening, CSRF, rate limiting, input sanitization.
 * Include this file at the TOP of every PHP file (after db.php).
 */

// ============================================================
// 1. SESSION HARDENING (must run before session_start)
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    // Prevent JS from reading the cookie (mitigates XSS)
    ini_set('session.cookie_httponly', 1);
    // Only send cookie over HTTPS (set to 1 in production; 0 for localhost)
    ini_set('session.cookie_secure', 0);
    // Prevent CSRF via cross-site cookie submission
    ini_set('session.cookie_samesite', 'Lax');
    // Use strict mode so uninitialized sessions are invalid
    ini_set('session.use_strict_mode', 1);
    // Only use cookies (no URL-based sessions)
    ini_set('session.use_only_cookies', 1);
    // Use a strong hash for session IDs
    ini_set('session.hash_function', 'sha256');

    session_start();
}

// ============================================================
// 2. SESSION FINGERPRINTING (mitigates session hijacking)
// ============================================================
function checkSessionFingerprint() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ipHash = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . $userAgent);

    if (!isset($_SESSION['fingerprint'])) {
        $_SESSION['fingerprint'] = $ipHash;
    } elseif ($_SESSION['fingerprint'] !== $ipHash) {
        // Session hijacked — destroy and log out
        session_unset();
        session_destroy();
        header("Location: index.php?err=session");
        exit;
    }
}
if (isset($_SESSION['user_id'])) {
    checkSessionFingerprint();
}

// ============================================================
// 3. SESSION TIMEOUT (auto-logout after 30 min inactivity)
// ============================================================
$timeout = 1800; // 30 minutes
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
        session_unset();
        session_destroy();
        header("Location: index.php?err=timeout");
        exit;
    }
    $_SESSION['last_activity'] = time();
}

// ============================================================
// 4. CSRF TOKEN GENERATION + VERIFICATION
// ============================================================
function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

function verifyCsrf() {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Security error: invalid CSRF token. Please refresh the page and try again.');
    }
}

// ============================================================
// 5. RATE LIMITING (prevents brute force + spam)
// ============================================================
function rateLimit($key, $maxAttempts = 5, $windowSeconds = 300) {
    $bucketKey = 'rl_' . md5($key);
    $now = time();

    if (!isset($_SESSION[$bucketKey])) {
        $_SESSION[$bucketKey] = ['count' => 0, 'reset' => $now + $windowSeconds];
    }

    if ($now > $_SESSION[$bucketKey]['reset']) {
        $_SESSION[$bucketKey] = ['count' => 0, 'reset' => $now + $windowSeconds];
    }

    $_SESSION[$bucketKey]['count']++;

    if ($_SESSION[$bucketKey]['count'] > $maxAttempts) {
        $wait = $_SESSION[$bucketKey]['reset'] - $now;
        http_response_code(429);
        die("Too many attempts. Please wait {$wait} seconds and try again.");
    }
}

// ============================================================
// 6. INPUT VALIDATION HELPERS
// ============================================================
function cleanString($str, $maxLen = 255) {
    $str = trim((string)$str);
    $str = preg_replace('/[\x00-\x1F\x7F]/u', '', $str); // strip control chars
    return mb_substr($str, 0, $maxLen);
}

function isValidUsername($u) {
    return preg_match('/^[a-zA-Z0-9_]{3,30}$/', $u) === 1;
}

function isValidEmail($e) {
    return filter_var($e, FILTER_VALIDATE_EMAIL) !== false;
}

function isValidTxHash($h) {
    // Alphanumeric + common hash chars, 10-128 long
    return preg_match('/^[a-zA-Z0-9_\-:]{10,128}$/', $h) === 1;
}

// ============================================================
// 7. SECURE HEADERS (mitigates XSS, clickjacking, MIME sniffing)
// ============================================================
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    // CSP — blocks inline scripts from untrusted sources.
    // Note: allows our own inline scripts, use nonces in production for stricter CSP.
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https://api.qrserver.com; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; connect-src 'self';");
}