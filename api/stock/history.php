<?php
require_once __DIR__ . '/../../bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
$stockId = (int)($_GET['id'] ?? 0);
if ($stockId <= 0) {
    echo json_encode(['error' => 'Invalid stock ID'], JSON_UNESCAPED_UNICODE);
    exit;
}
$history = StockEngine::getPriceHistory($stockId, 72);
echo json_encode($history, JSON_UNESCAPED_UNICODE);
exit;
