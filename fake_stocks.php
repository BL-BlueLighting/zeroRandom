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
    $action = $_POST['action'] ?? '';

    // Delete existing fake stock
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $db->prepare("DELETE FROM stocks WHERE id = ? AND adapter_name = 'fake'")->execute([$id]);
        }
        exit;
    }

    $count = 0;
    // Create new stocks
    $newNames = $_POST['new_name'] ?? [];
    $newSymbols = $_POST['new_symbol'] ?? [];
    $newRarities = $_POST['new_rarity'] ?? [];
    $newPrices = $_POST['new_price'] ?? [];
    $newCategories = $_POST['new_category'] ?? [];

    foreach ($newNames as $i => $name) {
        $name = trim($name);
        $symbol = strtoupper(trim($newSymbols[$i] ?? ''));
        if (!$name || !$symbol) continue;
        $rarity = $newRarities[$i] ?? 'claude';
        $price = max(0.01, (float)($newPrices[$i] ?? 10));
        $cat = trim($newCategories[$i] ?? '未分类');
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

    // Update existing stocks
    $existingIds = $_POST['existing_id'] ?? [];
    $existingSymbols = $_POST['existing_symbol'] ?? [];
    $existingNames = $_POST['existing_name'] ?? [];
    $existingRarities = $_POST['existing_rarity'] ?? [];
    $existingPrices = $_POST['existing_price'] ?? [];
    $existingCategories = $_POST['existing_category'] ?? [];
    $updateCount = 0;

    foreach ($existingIds as $i => $id) {
        $id = (int)$id;
        if (!$id) continue;
        $symbol = strtoupper(trim($existingSymbols[$i] ?? ''));
        $name = trim($existingNames[$i] ?? '');
        $rarity = $existingRarities[$i] ?? '';
        $price = max(0.01, (float)($existingPrices[$i] ?? 0));
        $cat = trim($existingCategories[$i] ?? '');

        try {
            $db->prepare("UPDATE stocks SET symbol = ?, name = ?, rarity = ?, current_price = ?, base_price = ?, category = ?, market_cap = ROUND(? * circulating_supply, 2) WHERE id = ? AND adapter_name = 'fake'")
                ->execute([$symbol, $name, $rarity, $price, $price, $cat, $price, $id]);
            $updateCount++;
        } catch (Exception $e) {}
    }

    $msgParts = [];
    if ($count > 0) $msgParts[] = "新增 {$count} 个";
    if ($updateCount > 0) $msgParts[] = "修改 {$updateCount} 个";
    $message = "✅ " . (implode('，', $msgParts) ?: '无变更。');
}

// Get existing fake stocks
$fakeStocks = $db->query("SELECT * FROM stocks WHERE adapter_name = 'fake' ORDER BY symbol ASC")->fetchAll();

require_once __DIR__ . '/templates/fake_stocks.php';
