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
require_once __DIR__ . '/core/MarketEngine.php';
require_once __DIR__ . '/adapters/manager.php';
Session::start();
Session::requireAuth();
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$poolId = (int)($input['pool_id'] ?? 0);
$userId = Session::userId();

$balance = TokenSystem::getBalance($userId);
// Keep ones digit, spend the rest
$spendable = $balance - ($balance % 10);
$cost = GACHA_SINGLE_COST;

if ($spendable < $cost) {
    echo json_encode(['success' => false, 'message' => '代币不足，至少需要 ' . $cost . ' 枚代币才能梭哈。']);
    exit;
}

$count = floor($spendable / $cost);
$totalCost = $count * $cost;

// Deduct tokens directly
$db = Database::getInstance();
$db->prepare("UPDATE users SET token_balance = token_balance - ?, total_spent = total_spent + ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
    ->execute([$totalCost, $totalCost, $userId]);

$results = [];
$legendaryCount = 0;

for ($i = 0; $i < $count; $i++) {
    if ($poolId > 0) {
        $stock = PoolEngine::getRandomStockFromPool($poolId);
    } else {
        $weights = GachaEngine::getRarityWeights();
        $rarity = GachaEngine::rollFromWeights($weights);
        $stock = GachaEngine::selectStockByRarity($rarity);
        if (!$stock) $stock = GachaEngine::selectStockByRarity('common');
    }
    if (!$stock) continue;

    $stockId = (int)$stock['id'];
    $rarity = $stock['rarity'] ?? 'common';

    GachaEngine::creditHolding($userId, $stockId);
    $db->prepare("INSERT INTO gacha_logs (user_id, stock_id, rarity, pull_type, cost) VALUES (?, ?, ?, 'single', ?)")
        ->execute([$userId, $stockId, $rarity, $cost]);

    if ($rarity === 'legendary') $legendaryCount++;
    $results[] = [
        'id' => $stockId,
        'symbol' => $stock['symbol'] ?? '?',
        'name' => $stock['name'] ?? '?',
        'rarity' => $rarity,
        'rarity_name' => GachaEngine::RARITY_NAMES[$rarity] ?? 'Common',
        'rarity_color' => GachaEngine::RARITY_COLORS[$rarity] ?? '#aaa',
        'price' => (float)($stock['current_price'] ?? 0),
    ];
}

$newBalance = TokenSystem::getBalance($userId);
$msg = "梭哈完成！共抽 {$count} 次，消耗 {$totalCost} 代币。";
if ($legendaryCount > 0) $msg .= " 🌟 获得 {$legendaryCount} 张传说卡牌！";

echo json_encode([
    'success' => true,
    'results' => $results,
    'balance_remaining' => $newBalance,
    'message' => $msg,
]);
exit;
