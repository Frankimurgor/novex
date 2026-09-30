<?php
require 'security.php';
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

$id = (int)($_GET['id'] ?? 0);
if ($id > 0 && isset($_SESSION['cart'])) {
    if (($k = array_search($id, $_SESSION['cart'], true)) !== false) {
        unset($_SESSION['cart'][$k]);
        $_SESSION['cart'] = array_values($_SESSION['cart']);
    }
}
header("Location: cart.php");
exit;