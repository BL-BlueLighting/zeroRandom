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

$userId = Session::userId();
$db = Database::getInstance();

// Check if all-in is enabled
if (platform_config('system', 'allin_enabled', '1') !== '1') {
    echo json_encode(['success' => false, 'message' => '梭哈功能已关闭。']);
    exit;
}

// Cooldown check: 30 minutes
$stmt = $db->prepare("SELECT created_at FROM gacha_logs WHERE user_id = ? AND pull_type = 'allin' ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$userId]);
$lastAllin = $stmt->fetch();
if ($lastAllin) {
    $lastTime = strtotime($lastAllin['created_at']);
    $elapsed = time() - $lastTime;
    if ($elapsed < 1800) {
        $remaining = ceil((1800 - $elapsed) / 60);
        echo json_encode(['success' => false, 'message' => "梭哈冷却中，请 {$remaining} 分钟后再试。"]);
        exit;
    }
}

$balance = TokenSystem::getBalance($userId);
// Keep ones digit, spend the rest
$spendable = $balance - ($balance % 10);
// Cap at 5,000,000
$spendable = min($spendable, 5000000);
$cost = GACHA_SINGLE_COST;

if ($spendable < $cost) {
    echo json_encode(['success' => false, 'message' => '代币不足，至少需要 ' . $cost . ' 枚代币才能梭哈。']);
    exit;
}

$count = floor($spendable / $cost);
$totalCost = $count * $cost;

// Deduct tokens directly
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
    $db->prepare("INSERT INTO gacha_logs (user_id, stock_id, rarity, pull_type, cost) VALUES (?, ?, ?, 'allin', ?)")
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
