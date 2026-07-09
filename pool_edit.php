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
require_once __DIR__ . '/core/AutoJob.php';
AutoJob::run();
Session::requireAuth();
$user = Session::user();
if (!$user || !$user['is_admin']) { header('Location: ' . url('/')); exit; }

$poolId = (int)($_GET['id'] ?? 0);
$pool = PoolEngine::getPool($poolId);
if (!$pool) { header('Location: ' . url('/pool_manager.php')); exit; }

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stockIds = array_map('intval', $_POST['stock_ids'] ?? []);
    PoolEngine::setPoolStocks($poolId, $stockIds);
    $message = '✅ 卡池题目已保存！共 ' . count($stockIds) . ' 题。';
}

$allStocks = StockEngine::getStocks(['limit' => 99999]);
$poolStockIds = PoolEngine::getPoolStockIds($poolId);

// Group stocks by category for column selection
$categories = [];
foreach ($allStocks as $s) {
    $cat = $s['category'] ?: '未分类';
    $categories[$cat][] = $s;
}

require_once __DIR__ . '/templates/pool_edit.php';
