<?php
// Force PHP to operate in UTC. Display conversion happens at render time only.
date_default_timezone_set('UTC');

// ============== Site Configuration ==============
if (!defined('SITE_NAME'))         define('SITE_NAME', 'NOVEX');
if (!defined('BONUS_THRESHOLD'))   define('BONUS_THRESHOLD', 150);
if (!defined('BONUS_AMOUNT'))      define('BONUS_AMOUNT', 50);
if (!defined('TELEGRAM_SUPPORT'))  define('TELEGRAM_SUPPORT', 'https://t.me/novex_support');
if (!defined('TELEGRAM_CHANNEL'))  define('TELEGRAM_CHANNEL', 'https://t.me/novex_announcements');

// ============== Database Configuration ==============
$host    = 'localhost';
$db      = 'novex_db';
$user    = 'root';
$pass    = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $pdo->exec("SET time_zone = '+00:00'");
} catch (\PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ============== Helper Functions ==============

if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }
}

if (!function_exists('userTier')) {
    function userTier($spent) {
        if ($spent >= 1000) return ['Gold', '#fbbf24'];
        if ($spent >= 300)  return ['Silver', '#9ca3af'];
        return ['Bronze', '#b45309'];
    }
}

if (!function_exists('getWallets')) {
    function getWallets() {
        return [
            'BTC' => [
                'name'    => 'Bitcoin',
                'symbol'  => 'BTC',
                'network' => 'Bitcoin',
                'address' => 'bc1qexample0000000000000000000000000'
            ],
        ];
    }
}

if (!function_exists('countOpenTickets')) {
    function countOpenTickets($pdo) {
        try {
            return (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'open'")->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }
}

if (!function_exists('userTimezone')) {
    function userTimezone() {
        return $_SESSION['timezone'] ?? 'UTC';
    }
}

// ---------------------------------------------------------------
// novexFormatTime() — converts a UTC timestamp to user's local time.
// Prefixed with "novex" to avoid collision with built-in PHP
// functions like localTime() that exist on some installations.
// ---------------------------------------------------------------
if (!function_exists('novexFormatTime')) {
    function novexFormatTime($utcTimestamp, $format = 'M d, Y H:i') {
        if ($utcTimestamp === null || $utcTimestamp === '' || $utcTimestamp === false || $utcTimestamp === 0 || $utcTimestamp === '0') {
            return '—';
        }
        $utcTimestamp = trim((string)$utcTimestamp);
        try {
            $dt = new DateTime($utcTimestamp, new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone(userTimezone()));
            return $dt->format($format);
        } catch (Exception $e) {
            return $utcTimestamp;
        }
    }
}

if (!function_exists('timeAgo')) {
    function timeAgo($utcTimestamp) {
        if (empty($utcTimestamp)) return '';
        $ts = strtotime((string)$utcTimestamp . ' UTC');
        if ($ts === false) return '';
        $diff = time() - $ts;
        if ($diff < 0) $diff = 0;
        if ($diff < 60)          return 'just now';
        if ($diff < 3600)        return floor($diff / 60) . 'm ago';
        if ($diff < 86400)       return floor($diff / 3600) . 'h ago';
        if ($diff < 604800)      return floor($diff / 86400) . 'd ago';
        if ($diff < 2592000)     return floor($diff / 604800) . 'w ago';
        if ($diff < 31536000)    return floor($diff / 2592000) . 'mo ago';
        return floor($diff / 31536000) . 'y ago';
    }
}

if (!function_exists('timezoneOptions')) {
    function timezoneOptions() {
        return [
            'UTC' => 'UTC',
            'Africa/Nairobi' => 'Nairobi (EAT)',
            'Africa/Lagos' => 'Lagos (WAT)',
            'Africa/Cairo' => 'Cairo (EET)',
            'Africa/Johannesburg' => 'Johannesburg (SAST)',
            'Europe/London' => 'London (GMT/BST)',
            'Europe/Paris' => 'Paris (CET)',
            'Europe/Berlin' => 'Berlin (CET)',
            'Europe/Moscow' => 'Moscow (MSK)',
            'Asia/Dubai' => 'Dubai (GST)',
            'Asia/Karachi' => 'Karachi (PKT)',
            'Asia/Kolkata' => 'Mumbai (IST)',
            'Asia/Singapore' => 'Singapore (SGT)',
            'Asia/Hong_Kong' => 'Hong Kong (HKT)',
            'Asia/Tokyo' => 'Tokyo (JST)',
            'Asia/Shanghai' => 'Shanghai (CST)',
            'Australia/Sydney' => 'Sydney (AEDT)',
            'America/New_York' => 'New York (EST/EDT)',
            'America/Chicago' => 'Chicago (CST/CDT)',
            'America/Denver' => 'Denver (MST/MDT)',
            'America/Los_Angeles' => 'Los Angeles (PST/PDT)',
            'America/Sao_Paulo' => 'São Paulo (BRT)',
        ];
    }
}
?>