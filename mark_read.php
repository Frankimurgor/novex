<?php
require 'db.php';
require 'security.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$uid = (int)$_SESSION['user_id'];

$pdo->prepare("
    INSERT INTO notifications_read (user_id, last_read_at) 
    VALUES (?, NOW()) 
    ON DUPLICATE KEY UPDATE last_read_at = NOW()
")->execute([$uid]);

echo json_encode(['ok' => true]);