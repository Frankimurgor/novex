<?php
require 'db.php';
require 'security.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$tz = $_GET['tz'] ?? '';

// Basic validation: timezone must be in our approved list
$allowed = array_keys(timezoneOptions());
if (!in_array($tz, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid timezone', 'received' => $tz]);
    exit;
}

// Only save if different from current (avoids needless writes)
$current = $_SESSION['timezone'] ?? 'UTC';
if ($tz !== $current) {
    $pdo->prepare("UPDATE users SET timezone = ? WHERE id = ?")->execute([$tz, (int)$_SESSION['user_id']]);
    $_SESSION['timezone'] = $tz;
}

echo json_encode(['ok' => true, 'timezone' => $tz]);