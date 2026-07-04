<?php
require_once __DIR__ . '/bootstrap.php';
Session::requireAuth();
$stockId = (int)($_POST['stock_id'] ?? 0);
$quantity = (int)($_POST['quantity'] ?? 0);
$result = TradingEngine::sell(Session::userId(), $stockId, $quantity);
Session::flash($result['success'] ? 'success' : 'error', $result['message']);
header('Location: ' . url('/portfolio.php'));
exit;
