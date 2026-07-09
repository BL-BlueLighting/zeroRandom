<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/init_check.php';
require_once __DIR__ . '/core/Session.php';
require_once __DIR__ . '/core/TokenSystem.php';
require_once __DIR__ . '/core/StockEngine.php';
require_once __DIR__ . '/core/MarketEngine.php';
Session::start();
require_once __DIR__ . '/core/AutoJob.php';
AutoJob::run();
Session::requireAuth();

$stockId = (int)($_POST['stock_id'] ?? 0);
$quantity = (int)($_POST['quantity'] ?? 1);
$price = (float)($_POST['price'] ?? 0);

$result = MarketEngine::listCard(Session::userId(), $stockId, $quantity, $price);
Session::flash($result['success'] ? 'success' : 'error', $result['message']);
header('Location: ' . url('/card_market.php'));
exit;
