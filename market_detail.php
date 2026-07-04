<?php
/**
 * OIManka - Stock Detail
 */
require_once __DIR__ . '/bootstrap.php';

$stockId = (int)($_GET['id'] ?? 0);
if ($stockId <= 0) {
    header('Location: ' . url('/market.php'));
    exit;
}
require_once __DIR__ . '/templates/market_detail.php';
