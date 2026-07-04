<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/init_check.php';
require_once __DIR__ . '/core/Session.php';
require_once __DIR__ . '/core/TokenSystem.php';
require_once __DIR__ . '/core/StockEngine.php';
require_once __DIR__ . '/core/GachaEngine.php';
require_once __DIR__ . '/core/TradingEngine.php';
require_once __DIR__ . '/core/PoolEngine.php';
require_once __DIR__ . '/core/QuestEngine.php';
require_once __DIR__ . '/core/MarketEngine.php';
require_once __DIR__ . '/adapters/manager.php';
Session::start();
Session::requireAuth();
header('Content-Type: application/json; charset=utf-8');
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$type = $input['type'] ?? 'single';
$poolId = (int)($input['pool_id'] ?? 0);

if ($poolId > 0) {
    // Pull from specific pool
    $result = GachaEngine::pullFromPool(Session::userId(), $type, $poolId);
} else {
    $result = GachaEngine::pull(Session::userId(), $type);
}
echo json_encode($result, JSON_UNESCAPED_UNICODE);
exit;
