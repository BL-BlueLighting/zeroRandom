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
$user = Session::user();
if (!$user || !$user['is_admin']) { header('Location: ' . url('/')); exit; }

$isKs = is_kaleidoscope();
$message = null;
$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $names = $_POST['name'] ?? [];
    $symbols = $_POST['symbol'] ?? [];
    $rarities = $_POST['rarity'] ?? [];
    $prices = $_POST['price'] ?? [];
    $categories = $_POST['category'] ?? [];
    $count = 0;

    foreach ($names as $i => $name) {
        $name = trim($name);
        $symbol = strtoupper(trim($symbols[$i] ?? ''));
        if (!$name || !$symbol) continue;
        $rarity = $rarities[$i] ?? 'claude';
        $price = max(0.01, (float)($prices[$i] ?? 10));
        $cat = trim($categories[$i] ?? '未分类');
        $adapterKey = 'fake_' . time() . '_' . $i;

        try {
            $db->prepare("
                INSERT INTO stocks (symbol, name, adapter_key, adapter_name, category, rarity,
                    total_supply, circulating_supply, base_price, current_price, prev_price,
                    price_change_pct, volume_24h, market_cap, metadata, is_active)
                VALUES (?, ?, ?, 'fake', ?, ?, 1000, 1000, ?, ?, ?, 0, 0, ROUND(? * 1000, 2), '{\"source\":\"siliconflow_is_sb\"}', 1)
            ")->execute([$symbol, $name, $adapterKey, $cat, $rarity, $price, $price, $price]);
            $stockId = (int)$db->lastInsertId();
            $db->prepare("INSERT INTO stock_prices (stock_id, price, ac_ratio, submit_count, ac_count) VALUES (?, ?, 0.5, 1, 0)")
                ->execute([$stockId, $price]);
            $count++;
        } catch (Exception $e) {}
    }
    $message = "✅ 已创建 {$count} 个假题目。";
}

// Get existing fake stocks
$fakeStocks = $db->query("SELECT * FROM stocks WHERE adapter_name = 'fake' ORDER BY symbol ASC")->fetchAll();

require_once __DIR__ . '/templates/fake_stocks.php';
