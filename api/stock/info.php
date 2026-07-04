<?php
require_once __DIR__ . '/../../bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
$stockId = (int)($_GET['id'] ?? 0);
if ($stockId <= 0) {
    echo json_encode(['error' => 'Invalid stock ID'], JSON_UNESCAPED_UNICODE);
    exit;
}
$stock = StockEngine::getStock($stockId);
echo json_encode($stock ?: ['error' => 'Not found'], JSON_UNESCAPED_UNICODE);
exit;
