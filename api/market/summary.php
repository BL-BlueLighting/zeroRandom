<?php
require_once __DIR__ . '/../../bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
echo json_encode(StockEngine::getMarketSummary(), JSON_UNESCAPED_UNICODE);
exit;
