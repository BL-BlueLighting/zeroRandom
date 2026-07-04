<?php
/**
 * zero Random - Admin Panel
 */
$pageTitle = '管理后台';

$currentUser = Session::user();
if (!$currentUser || !$currentUser['is_admin']) {
    header('Location: /');
    exit;
}

$db = Database::getInstance();
$message = null;

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    // ── HustOJ Config ──
    if ($postAction === 'save_hustoj_config') {
        $fields = ['db_host', 'db_port', 'db_name', 'db_user', 'db_pass', 'oj_url', 'category_source'];
        foreach ($fields as $f) {
            platform_config_set('hustoj', $f, $_POST[$f] ?? '');
        }
        // Clear HydroOJ config to prevent dual activation
        platform_config_set('hydroj', 'db_host', '');
        $message = '✅ HustOJ 配置已保存！(已禁用 HydroOJ)';
    }

    // ── Sync Stocks ──
    if ($postAction === 'sync_stocks') {
        $adapter = AdapterManager::get('hustoj');
        if ($adapter && $adapter->testConnection()) {
            PoolEngine::syncLimitedEdition();
            $result = $adapter->syncStocks();
            $message = "✅ 同步完成！更新 {$result['items_synced']} 支股票。";
        } else {
            $err = $adapter ? $adapter->getConfigError() : '未知错误';
            $message = "❌ 无法连接 HustOJ：{$err}";
        }
    }

    // ── Create Fake Stock ──
    if ($postAction === 'create_fake_stock') {
        $symbol = strtoupper(trim($_POST['fake_symbol'] ?? ''));
        $name = trim($_POST['fake_name'] ?? '');
        $rarity = $_POST['fake_rarity'] ?? 'common';
        $price = max(0.01, (float)($_POST['fake_price'] ?? 10));
        $category = trim($_POST['fake_category'] ?? '未分类');
        $adapterKey = 'fake_' . time();

        if ($symbol && $name) {
            try {
                $db->prepare("
                    INSERT INTO stocks (symbol, name, adapter_key, adapter_name, category, rarity,
                        total_supply, circulating_supply, base_price, current_price, prev_price,
                        price_change_pct, volume_24h, market_cap, metadata, is_active)
                    VALUES (?, ?, ?, 'fake', ?, ?,
                        1000, 1000, ?, ?, ?, 0, 0, ROUND(? * 1000, 2), '{\"source\":\"siliconflow_is_sb\"}', 1)
                ")->execute([$symbol, $name, $adapterKey, $category, $rarity, $price, $price, $price]);
                $stockId = (int)$db->lastInsertId();
                $db->prepare("INSERT INTO stock_prices (stock_id, price, ac_ratio, submit_count, ac_count) VALUES (?, ?, 0.5, 1, 0)")
                    ->execute([$stockId, $price]);
                $message = "✅ 假题目 {$symbol} - {$name} 已创建（{$rarity}，初始价 {$price}）";
            } catch (Exception $e) {
                $message = '❌ 创建失败: ' . $e->getMessage();
            }
        } else { $message = '❌ 题号和名称不能为空。'; }
    }

    // ── Bulk Edit Stocks ──
    // ── All-in Toggle ──
    if ($postAction === 'toggle_allin') {
        platform_config_set('system', 'allin_enabled', $_POST['allin_value'] ?? '0');
        $message = '✅ 梭哈功能已' . (($_POST['allin_value'] ?? '0') === '1' ? '开启' : '关闭');
    }

    if ($postAction === 'bulk_edit_stocks') {
        $prefix = strtoupper(trim($_POST['bulk_prefix'] ?? ''));
        $action = $_POST['bulk_action'] ?? '';
        $value = trim($_POST['bulk_value'] ?? '');

        if ($prefix && $action && $value !== '') {
            $where = "symbol LIKE '{$prefix}%' AND is_active = 1";
            switch ($action) {
                case 'rarity':
                    $db->exec("UPDATE stocks SET rarity = '{$value}' WHERE {$where}");
                    $message = "✅ 已将 {$prefix}* 开头的股票稀有度设为 {$value}";
                    break;
                case 'category':
                    $db->exec("UPDATE stocks SET category = '{$value}' WHERE {$where}");
                    $message = "✅ 已将 {$prefix}* 开头的股票分类设为 {$value}";
                    break;
                case 'price':
                    $price = (float)$value;
                    $db->exec("UPDATE stocks SET prev_price = current_price, current_price = {$price}, price_change_pct = ROUND(({$price} - current_price) / current_price * 100, 2) WHERE {$where}");
                    $message = "✅ 已将 {$prefix}* 开头的股票价格设为 {$price}";
                    break;
                case 'limited':
                    $set = $value === '1' ? '1' : '0';
                    if ($set === '1') {
                        $db->exec("UPDATE stocks SET limited_edition = 1, rarity = 'legendary' WHERE {$where}");
                    } else {
                        $db->exec("UPDATE stocks SET limited_edition = 0 WHERE {$where}");
                    }
                    $message = "✅ 已将 {$prefix}* 开头的股票绝版状态设为 " . ($set === '1' ? '绝版' : '非绝版');
                    break;
                default:
                    $message = '❌ 无效操作。';
            }
        } else { $message = '❌ 请填写完整。'; }
    }

    // ── Contact Config ──
    if ($postAction === 'save_contact_config') {
        platform_config_set('system', 'contact_qq', $_POST['contact_qq'] ?? '');

        $rootDir = __DIR__ . '/..';
        $uploadDir = $rootDir . '/uploads';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        foreach (['contact_qq_qr', 'contact_wx_qr'] as $field) {
            $remove = !empty($_POST['remove_' . $field]);
            $oldPath = platform_config('system', $field, '');

            if ($remove && $oldPath) {
                $oldFile = $rootDir . '/' . $oldPath;
                if (file_exists($oldFile)) unlink($oldFile);
                platform_config_set('system', $field, '');
            }

            if (!empty($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
                if (!$remove && $oldPath) {
                    $oldFile = $rootDir . '/' . $oldPath;
                    if (file_exists($oldFile)) unlink($oldFile);
                }
                $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
                $name = $field . '_' . time() . '.' . $ext;
                $dest = $uploadDir . '/' . $name;
                if (move_uploaded_file($_FILES[$field]['tmp_name'], $dest)) {
                    platform_config_set('system', $field, 'uploads/' . $name);
                }
            }
        }
        $message = '✅ 联系方式已保存！';
    }

    // ── Gacha Weights ──
    if ($postAction === 'set_weights') {
        $weights = [
            'common' => (int)($_POST['w_common'] ?? 10),
            'rare' => (int)($_POST['w_rare'] ?? 10),
            'epic' => (int)($_POST['w_epic'] ?? 10),
            'legendary' => (int)($_POST['w_legendary'] ?? 10),
        ];
        GachaEngine::setRarityWeights($weights);
        $message = '✅ 概率已更新！';
        $db->prepare("INSERT INTO notifications (message) VALUES (?)")->execute([
            "📢 管理员已更新今日抽卡概率"
        ]);
    }

    if ($postAction === 'randomize_weights') {
        $weights = GachaEngine::randomizeWeights();
        $message = '🎲 概率已随机化！';
        $db->prepare("INSERT INTO notifications (message) VALUES (?)")->execute([
            "🎲 管理员已随机化今日抽卡概率"
        ]);
    }

    // ── Force Price ──
    if ($postAction === 'force_price') {
        $stockId = (int)($_POST['stock_id'] ?? 0);
        $direction = $_POST['force_direction'] ?? '';
        $changePct = (float)($_POST['force_change_pct'] ?? 0);
        $date = $_POST['date'] ?? date('Y-m-d');

        if ($stockId && $direction && $changePct) {
            $db->prepare("
                INSERT INTO price_overrides (stock_id, force_direction, force_change_pct, date, reason, created_by)
                VALUES (?, ?, ?, ?, ?, ?)
                ON CONFLICT(stock_id, date) DO UPDATE SET
                    force_direction = excluded.force_direction,
                    force_change_pct = excluded.force_change_pct, reason = excluded.reason
            ")->execute([$stockId, $direction, $changePct, $date, $_POST['reason'] ?? '', Session::userId()]);

            $stock = StockEngine::getStock($stockId);
            if ($stock) {
                $oldPrice = (float)$stock['current_price'];
                $newPrice = $direction === 'up' ? round($oldPrice * (1 + $changePct / 100), 2) : round($oldPrice * (1 - $changePct / 100), 2);
                StockEngine::updatePrice($stockId, max($newPrice, 0.01));
            }
            $message = "✅ 已强制调整价格！";
        }
    }

    if ($postAction === 'recalc_rarity') {
        GachaEngine::recalculateRarityByHeat();
        $message = '✅ 已按热度重新计算稀有度！';
    }

    // ── Refresh Stock Prices ──
    if ($postAction === 'refresh_stocks') {
        $adapter = AdapterManager::get('hustoj');
        if ($adapter && $adapter->testConnection()) {
            PoolEngine::syncLimitedEdition();
            $syncResult = $adapter->syncStocks();
            if ($syncResult['items_synced'] > 0) {
                $message = "✅ 同步完成！更新了 {$syncResult['items_synced']} 支股票（真实数据）。";
            } else {
                // No real changes → rarity-based probability
                $count = ['limited' => 0, 'legendary' => 0, 'epic' => 0, 'rare' => 0, 'common' => 0];
                $rarityChance = ['legendary' => 100, 'epic' => 50, 'rare' => 25, 'common' => 10];
                $allStocks = $db->query("SELECT id, current_price, rarity, limited_edition FROM stocks WHERE is_active = 1")->fetchAll();
                $update = $db->prepare("UPDATE stocks SET prev_price = current_price, current_price = ROUND(?, 2), price_change_pct = ROUND((? - current_price) / current_price * 100, 2) WHERE id = ?");
                foreach ($allStocks as $s) {
                    // 绝版: 100% up, 20~30%
                    if (!empty($s['limited_edition'])) {
                        $pct = mt_rand(20, 30);
                        $newPrice = round((float)$s['current_price'] * (1 + $pct / 100), 2);
                        if ($newPrice > 0) { $update->execute([$newPrice, $newPrice, $s['id']]); $count['limited']++; }
                        continue;
                    }
                    $r = $s['rarity'] ?: 'common';
                    $upChance = $rarityChance[$r] ?? 10;
                    $up = mt_rand(1, 100) <= $upChance;
                    $pct = mt_rand(2, 17);
                    $change = $up ? $pct : -$pct;
                    $newPrice = round((float)$s['current_price'] * (1 + $change / 100), 2);
                    if ($newPrice > 0) {
                        $update->execute([$newPrice, $newPrice, $s['id']]);
                        $count[$r]++;
                    }
                }
                $msgParts = [];
                foreach (['legendary' => '传说', 'epic' => '史诗', 'rare' => '稀有', 'common' => '普通'] as $k => $v) {
                    if ($count[$k] > 0) $msgParts[] = "{$v}{$count[$k]}";
                }
                $message = "🎲 无新数据，已按稀有度随机波动：" . implode(' / ', $msgParts) . "。";
            }
        } else {
            // No HUSTOJ connection → rarity-based random changes
            $count = ['limited' => 0, 'legendary' => 0, 'epic' => 0, 'rare' => 0, 'common' => 0];
            $rarityChance = ['legendary' => 100, 'epic' => 50, 'rare' => 25, 'common' => 10];
            $allStocks = $db->query("SELECT id, current_price, rarity, limited_edition FROM stocks WHERE is_active = 1")->fetchAll();
            $update = $db->prepare("UPDATE stocks SET prev_price = current_price, current_price = ROUND(?, 2), price_change_pct = ROUND((? - current_price) / current_price * 100, 2) WHERE id = ?");
            foreach ($allStocks as $s) {
                    // 绝版: 100% up, 20~30%
                    if (!empty($s['limited_edition'])) {
                        $pct = mt_rand(20, 30);
                        $newPrice = round((float)$s['current_price'] * (1 + $pct / 100), 2);
                        if ($newPrice > 0) { $update->execute([$newPrice, $newPrice, $s['id']]); $count['limited']++; }
                        continue;
                    }
                $r = $s['rarity'] ?: 'common';
                $upChance = $rarityChance[$r] ?? 10;
                $up = mt_rand(1, 100) <= $upChance;
                $pct = mt_rand(2, 17);
                $change = $up ? $pct : -$pct;
                $newPrice = round((float)$s['current_price'] * (1 + $change / 100), 2);
                if ($newPrice > 0) { $update->execute([$newPrice, $newPrice, $s['id']]); $count[$r]++; }
            }
            $msgParts = [];
            if ($count['limited'] > 0) $msgParts[] = "绝版{$count['limited']}";
            foreach (['legendary' => '传说', 'epic' => '史诗', 'rare' => '稀有', 'common' => '普通'] as $k => $v) {
                if ($count[$k] > 0) $msgParts[] = "{$v}{$count[$k]}";
            }
            $message = "🎲 HustOJ 未连接，已按稀有度随机波动：" . implode(' / ', $msgParts) . "。";
        }
    }

    // ── Pool Management ──
    if ($postAction === 'create_pool') {
        $name = trim($_POST['pool_name'] ?? '');
        if ($name) {
            $poolId = PoolEngine::createPool($name);
            $message = "✅ 卡池「{$name}」已创建！";
        }
    }
    if ($postAction === 'delete_pool') {
        $pid = (int)($_POST['pool_id'] ?? 0);
        if ($pid) { PoolEngine::deletePool($pid); $message = '✅ 卡池已删除。'; }
    }
    if ($postAction === 'set_pool_stocks') {
        $pid = (int)($_POST['pool_id'] ?? 0);
        $stockIds = array_map('intval', $_POST['stock_ids'] ?? []);
        if ($pid) { PoolEngine::setPoolStocks($pid, $stockIds); $message = '✅ 卡池题目已更新。'; }
    }
    if ($postAction === 'split_pool') {
        $pid = (int)($_POST['pool_id'] ?? 0);
        $newName = trim($_POST['new_pool_name'] ?? '');
        $splitAt = (int)($_POST['split_at'] ?? 0);
        if ($pid && $newName) {
            try { PoolEngine::splitPool($pid, $newName, $splitAt); $message = "✅ 已分割为新卡池「{$newName}」。"; }
            catch (Exception $e) { $message = '❌ ' . $e->getMessage(); }
        }
    }

    // ── Quest Management ──
    if ($postAction === 'add_quest') {
        QuestEngine::addQuest(
            $_POST['quest_type'] ?? 'daily',
            trim($_POST['quest_name'] ?? ''),
            trim($_POST['quest_desc'] ?? ''),
            $_POST['cond_type'] ?? '',
            (float)($_POST['cond_val'] ?? 0),
            (float)($_POST['reward'] ?? 0)
        );
        $message = '✅ 任务已添加！';
    }
    if ($postAction === 'delete_quest') {
        QuestEngine::deleteQuest((int)($_POST['quest_id'] ?? 0));
        $message = '✅ 任务已删除。';
    }
    if ($postAction === 'toggle_quest') {
        QuestEngine::toggleQuest((int)($_POST['quest_id'] ?? 0));
        $message = '✅ 任务状态已切换。';
    }

    // ── Give Card ──
    if ($postAction === 'give_card') {
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);
        $stockId = (int)($_POST['stock_id'] ?? 0);
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));
        $customRarity = trim($_POST['custom_rarity'] ?? '');
        $overwriteRarity = !empty($customRarity);

        if ($targetUserId && $stockId) {
            $stock = StockEngine::getStock($stockId);
            if ($stock) {
                try {
                    $db->beginTransaction();
                    $hold = $db->prepare("SELECT * FROM holdings WHERE user_id = ? AND stock_id = ?");
                    $hold->execute([$targetUserId, $stockId]);
                    $h = $hold->fetch();
                    if ($h) {
                        $newQty = $h['quantity'] + $quantity;
                        $newCost = ($h['avg_cost'] * $h['quantity'] + $stock['current_price'] * $quantity) / $newQty;
                        $db->prepare("UPDATE holdings SET quantity = ?, avg_cost = ? WHERE id = ?")->execute([$newQty, $newCost, $h['id']]);
                    } else {
                        $db->prepare("INSERT INTO holdings (user_id, stock_id, quantity, avg_cost) VALUES (?, ?, ?, ?)")->execute([$targetUserId, $stockId, $quantity, $stock['current_price']]);
                    }
                    if ($overwriteRarity) {
                        if ($customRarity === 'limited') {
                            $db->prepare("UPDATE stocks SET rarity = 'legendary', limited_edition = 1 WHERE id = ?")->execute([$stockId]);
                        } else {
                            $db->prepare("UPDATE stocks SET rarity = ?, limited_edition = 0 WHERE id = ?")->execute([$customRarity, $stockId]);
                        }
                    }
                    $db->commit();
                    $rarityNote = $overwriteRarity ? "（稀有度设为 {$customRarity}）" : '';
                    $message = "✅ 已赠送 {$quantity}x {$stock['symbol']} {$stock['name']} 给用户 #{$targetUserId}{$rarityNote}。";
                } catch (Exception $e) {
                    $db->rollBack();
                    $message = '❌ 发卡失败: ' . $e->getMessage();
                }
            } else { $message = '❌ 股票不存在。'; }
        } else { $message = '❌ 请选择用户和股票。'; }
    }

    // ── Execute SQL ──
    if ($postAction === 'exec_sql') {
        $sql = trim($_POST['sql_query'] ?? '');
        $setupKey = trim($_POST['setup_key'] ?? '');

        if (empty($sql)) {
            $message = '❌ SQL 不能为空。';
        } else {
            $upper = strtoupper($sql);
            $hasDrop = preg_match('/\bDROP\b/i', $sql);
            $hasDelete = preg_match('/\bDELETE\b/i', $sql);

            if (($hasDrop || $hasDelete) && $setupKey !== DB_INIT_KEY) {
                $message = '❌ 检测到 DROP/DELETE 语句，需要输入正确的初始化密钥才能执行。';
            } else {
                try {
                    $stmt = $db->query($sql);
                    if ($stmt && $stmt->columnCount() > 0) {
                        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        $_SESSION['sql_result'] = ['rows' => $rows, 'count' => count($rows)];
                        $message = "✅ SQL 执行成功，返回 {$rows} 行。";
                    } else {
                        $message = "✅ SQL 执行成功，受影响行数: " . ($stmt ? $stmt->rowCount() : 0) . "。";
                    }
                } catch (Exception $e) {
                    $message = '❌ SQL 执行失败: ' . $e->getMessage();
                }
            }
        }
    }

    // ── Post Announcement ──
    if ($postAction === 'post_announcement') {
        $msg = trim($_POST['announce_message'] ?? '');
        $tokens = min((float)($_POST['announce_tokens'] ?? 0), 100000);
        $stockId = (int)($_POST['announce_stock_id'] ?? 0);
        $stockQty = max(1, (int)($_POST['announce_stock_qty'] ?? 1));
        if ($msg) {
            $db->prepare("INSERT INTO notifications (message, reward_tokens, reward_stock_id, reward_stock_quantity) VALUES (?, ?, ?, ?)")
                ->execute([$msg, $tokens, $stockId, $stockQty]);
            $message = '✅ 公告已发布！';
        } else {
            $message = '❌ 公告内容不能为空。';
        }
    }
}

// Current data
$currentWeights = GachaEngine::getRarityWeights();
$hustojConfigured = platform_configured('hustoj');
$hustojConnected = false;
if ($hustojConfigured) {
    $adapter = AdapterManager::get('hustoj');
    $hustojConnected = $adapter && $adapter->testConnection();
}
$hustojConfig = [
    'db_host' => platform_config('hustoj', 'db_host', ''),
    'db_port' => platform_config('hustoj', 'db_port', '3306'),
    'db_name' => platform_config('hustoj', 'db_name', 'jol'),
    'db_user' => platform_config('hustoj', 'db_user', ''),
    'db_pass' => platform_config('hustoj', 'db_pass', ''),
    'oj_url' => platform_config('hustoj', 'oj_url', ''),
    'category_source' => platform_config('hustoj', 'category_source', ''),
];

$notifications = $db->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 20")->fetchAll();
$overrides = $db->query("
    SELECT po.*, s.symbol, s.name as stock_name FROM price_overrides po
    JOIN stocks s ON po.stock_id = s.id ORDER BY po.created_at DESC LIMIT 20
")->fetchAll();
$stocks = StockEngine::getStocks(['limit' => 99999]);
$pools = PoolEngine::getAllPools();
$quests = QuestEngine::getAllConfig();
$users = $db->query("SELECT id, username FROM users ORDER BY username ASC")->fetchAll();

// SQL result from session
$sqlResult = $_SESSION['sql_result'] ?? null;
unset($_SESSION['sql_result']);

include __DIR__ . '/layout/header.php';
?>

<div class="page-admin">
    <h1>⚙️ 管理后台</h1>

    <?php if ($message): ?>
    <div class="flash-message flash-success"><?= $message ?></div>
    <?php endif; ?>

    <div class="admin-grid">
        <!-- HustOJ Config -->
        <section class="admin-section">
            <h2>🔌 HustOJ 配置</h2>
            <p class="text-muted">配置 HustOJ 数据库连接信息，同步题目数据作为股票</p>
            <form method="POST" class="admin-form">
                <input type="hidden" name="action" value="save_hustoj_config">
                <div class="form-row">
                    <div class="form-group">
                        <label>MySQL 主机</label>
                        <input name="db_host" value="<?= htmlspecialchars($hustojConfig['db_host']) ?>" placeholder="localhost" class="form-input">
                    </div>
                    <div class="form-group">
                        <label>端口</label>
                        <input name="db_port" value="<?= htmlspecialchars($hustojConfig['db_port']) ?>" placeholder="3306" class="form-input" style="width:80px">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>数据库名</label>
                        <input name="db_name" value="<?= htmlspecialchars($hustojConfig['db_name']) ?>" placeholder="jol" class="form-input">
                    </div>
                    <div class="form-group">
                        <label>用户名</label>
                        <input name="db_user" value="<?= htmlspecialchars($hustojConfig['db_user']) ?>" placeholder="root" class="form-input">
                    </div>
                    <div class="form-group">
                        <label>密码</label>
                        <input name="db_pass" type="password" value="<?= htmlspecialchars($hustojConfig['db_pass']) ?>" placeholder="(留空为无密码)" class="form-input">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>OJ 网站地址</label>
                        <input name="oj_url" value="<?= htmlspecialchars($hustojConfig['oj_url']) ?>" placeholder="http://oj.example.com" class="form-input">
                    </div>
                    <div class="form-group">
                        <label>题源分类 (可选)</label>
                        <input name="category_source" value="<?= htmlspecialchars($hustojConfig['category_source']) ?>" placeholder="留空为全部题目" class="form-input">
                    </div>
                </div>
                <button class="btn btn-primary">💾 保存配置</button>
            </form>

            <div style="margin-top:12px;display:flex;gap:8px;align-items:center">
                <span>状态:
                    <?php if ($hustojConfigured && $hustojConnected): ?>
                    <span class="text-green">✅ 已连接</span>
                    <?php elseif ($hustojConfigured): ?>
                    <span class="text-red">❌ 连接失败</span>
                    <?php else: ?>
                    <span class="text-muted">⚠️ 未配置</span>
                    <?php endif; ?>
                </span>
                <form method="POST" style="display:inline">
                    <input type="hidden" name="action" value="sync_stocks">
                    <button class="btn btn-accent" <?= !$hustojConnected ? 'disabled' : '' ?>>
                        🔄 同步题目数据
                    </button>
                </form>
            </div>
        </section>

        <!-- Gacha Config -->
        <section class="admin-section">
            <h2>🎲 抽卡概率管理</h2>
            <form method="POST" class="admin-form">
                <input type="hidden" name="action" value="set_weights">
                <div class="form-row">
                    <?php foreach (GachaEngine::RARITY_ORDER as $rarity): ?>
                    <div class="form-group">
                        <label><span class="rarity-badge <?= $rarity ?>"><?= GachaEngine::RARITY_NAMES[$rarity] ?></span></label>
                        <input type="number" name="w_<?= $rarity ?>" value="<?= $currentWeights[$rarity] ?? 10 ?>"
                               min="0" max="100" class="form-input" style="width:80px"><span class="text-muted">%</span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button class="btn btn-primary">保存概率</button>
            </form>
            <form method="POST" style="margin-top:12px;display:inline">
                <input type="hidden" name="action" value="randomize_weights">
                <button class="btn btn-accent">🎲 随机化概率</button>
            </form>
            <form method="POST" style="margin-top:12px;display:inline;margin-left:8px">
                <input type="hidden" name="action" value="recalc_rarity">
                <button class="btn btn-outline">🔄 按热度重算稀有度</button>
            </form>
        </section>

        <!-- Force Price -->
        <section class="admin-section">
            <h2>📊 强制价格调整</h2>
            <form method="POST" class="admin-form">
                <input type="hidden" name="action" value="force_price">
                <div class="form-group">
                    <label>选择股票</label>
                    <select name="stock_id" class="form-input">
                        <?php foreach ($stocks as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['symbol']) ?> - <?= htmlspecialchars($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>方向</label>
                        <select name="force_direction" class="form-input">
                            <option value="up">📈 上涨</option>
                            <option value="down">📉 下跌</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>变化 (%)</label>
                        <input type="number" name="force_change_pct" value="5" min="0.1" max="100" step="0.1" class="form-input" style="width:100px">
                    </div>
                    <div class="form-group">
                        <label>日期</label>
                        <input type="date" name="date" value="<?= date('Y-m-d') ?>" class="form-input">
                    </div>
                </div>
                <div class="form-group">
                    <label>原因 (可选)</label>
                    <input type="text" name="reason" class="form-input">
                </div>
                <button class="btn btn-danger">⚡ 强制调整价格</button>
            </form>
        </section>

        <!-- Stock Refresh -->
        <section class="admin-section">
            <h2>🔄 刷新股价</h2>
            <p class="text-muted">有真实AC数据则同步，否则随机±2~17%波动</p>
            <form method="POST">
                <input type="hidden" name="action" value="refresh_stocks">
                <button class="btn btn-accent">🔄 刷新股价</button>
            </form>
        </section>

        <!-- Fake Stock Generator -->
        <section class="admin-section">
            <h2>🪪 假题目生成器</h2>
            <p class="text-muted">创建假题目作为股票，来源标记为 siliconflow_is_sb</p>
            <form method="POST" class="admin-form">
                <input type="hidden" name="action" value="create_fake_stock">
                <div class="form-row">
                    <div class="form-group">
                        <label>题号 (Symbol)</label>
                        <input type="text" name="fake_symbol" required class="form-input" placeholder="如 FAKE001" style="width:120px">
                    </div>
                    <div class="form-group" style="flex:2">
                        <label>题目名称</label>
                        <input type="text" name="fake_name" required class="form-input" placeholder="如 测试题目 A">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>稀有度</label>
                        <select name="fake_rarity" class="form-input" style="width:120px">
                            <option value="common">普通</option>
                            <option value="rare">稀有</option>
                            <option value="epic">史诗</option>
                            <option value="legendary">传说</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>初始市场价格</label>
                        <input type="number" name="fake_price" value="10" min="0.01" step="0.01" class="form-input" style="width:100px">
                    </div>
                    <div class="form-group" style="flex:1">
                        <label>分类</label>
                        <input type="text" name="fake_category" value="未分类" class="form-input" placeholder="分类">
                    </div>
                </div>
                <button class="btn btn-primary">🪪 创建假题目</button>
            </form>
        </section>

        <!-- Bulk Edit Stocks -->
        <section class="admin-section">
            <h2>📊 批量修改股票</h2>
            <p class="text-muted">按题号前缀批量修改股票属性</p>
            <form method="POST" class="admin-form">
                <input type="hidden" name="action" value="bulk_edit_stocks">
                <div class="form-row">
                    <div class="form-group">
                        <label>题号前缀</label>
                        <input type="text" name="bulk_prefix" required class="form-input" style="width:100px" placeholder="如 S">
                    </div>
                    <div class="form-group">
                        <label>操作</label>
                        <select name="bulk_action" class="form-input" style="width:140px">
                            <option value="rarity">设置稀有度</option>
                            <option value="category">设置分类</option>
                            <option value="price">设置价格</option>
                            <option value="limited">设置绝版状态</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex:1">
                        <label>值</label>
                        <input type="text" name="bulk_value" required class="form-input" placeholder="稀有度: common/rare/epic/legendary | 价格: 数字 | 绝版: 1或0">
                    </div>
                    <button class="btn btn-primary" style="align-self:flex-end">⚡ 执行</button>
                </div>
            </form>
        </section>

        <!-- Card Pools -->
        <section class="admin-section">
            <h2>🎯 卡池管理</h2>
            <p class="text-muted">管理卡池、选择题目、分割卡池</p>
            <a href="<?= url('/pool_manager.php') ?>" class="btn btn-primary">🎯 打开卡池管理</a>
        </section>

        <!-- Quest Config -->
        <section class="admin-section">
            <h2>🎯 任务配置</h2>
            <table class="stock-table"><thead><tr><th>类型</th><th>名称</th><th>条件</th><th>目标值</th><th>奖励</th><th>状态</th><th>操作</th></tr></thead><tbody>
            <?php foreach ($quests as $q): ?>
            <tr>
                <td><?= $q['type'] === 'daily' ? '📋每日' : '🏆成就' ?></td>
                <td><?= htmlspecialchars($q['name']) ?></td>
                <td class="text-muted"><?= $q['condition_type'] ?></td>
                <td><?= $q['condition_value'] ?></td>
                <td>🪙<?= $q['reward_tokens'] ?></td>
                <td><?= $q['is_active'] ? '✅开启' : '⛔关闭' ?></td>
                <td style="white-space:nowrap">
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="toggle_quest">
                        <input type="hidden" name="quest_id" value="<?= $q['id'] ?>">
                        <button class="btn btn-xs btn-outline"><?= $q['is_active'] ? '关闭' : '开启' ?></button>
                    </form>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="delete_quest">
                        <input type="hidden" name="quest_id" value="<?= $q['id'] ?>">
                        <button class="btn btn-xs btn-danger" onclick="return confirm('删除？')">×</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody></table>
            <details style="margin-top:8px">
                <summary style="cursor:pointer;color:var(--accent)">添加任务</summary>
                <form method="POST" class="admin-form" style="margin-top:8px">
                    <input type="hidden" name="action" value="add_quest">
                    <div class="form-row">
                        <select name="quest_type" class="form-input" style="width:100px">
                            <option value="daily">每日</option>
                            <option value="achievement">成就</option>
                        </select>
                        <input type="text" name="quest_name" placeholder="任务名称" class="form-input" style="flex:1">
                        <input type="text" name="quest_desc" placeholder="描述" class="form-input" style="flex:2">
                    </div>
                    <div class="form-row">
                        <select name="cond_type" class="form-input" style="width:140px">
                            <option value="token_balance">代币数</option>
                            <option value="total_earned">累计获得</option>
                            <option value="total_spent">累计消费</option>
                            <option value="gacha_single">单抽次数</option>
                            <option value="gacha_multi">十连次数</option>
                            <option value="gacha_hundred">百连次数</option>
                            <option value="holdings">持仓数</option>
                            <option value="checkin_days">签到天数</option>
                        </select>
                        <input type="number" name="cond_val" placeholder="目标值" class="form-input" style="width:100px" step="1">
                        <input type="number" name="reward" placeholder="奖励代币" class="form-input" style="width:100px" step="1">
                        <button class="btn btn-primary btn-sm">添加</button>
                    </div>
                </form>
            </details>
        </section>

        <!-- All-in Toggle -->
        <section class="admin-section">
            <h2>🔴 梭哈开关</h2>
            <form method="POST" style="display:flex;gap:12px;align-items:center">
                <input type="hidden" name="action" value="toggle_allin">
                <?php $allinEnabled = platform_config('system', 'allin_enabled', '1'); ?>
                <span class="text-muted">梭哈功能：</span>
                <button class="btn btn-sm <?= $allinEnabled === '1' ? 'btn-primary' : 'btn-outline' ?>" name="allin_value" value="<?= $allinEnabled === '1' ? '0' : '1' ?>">
                    <?= $allinEnabled === '1' ? '✅ 已开启' : '⛔ 已关闭' ?>
                </button>
            </form>
        </section>

        <!-- Contact Config -->
        <section class="admin-section">
            <h2>💬 联系方式配置</h2>
            <p class="text-muted">配置后会在用户个人中心页面展示</p>
            <form method="POST" class="admin-form" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_contact_config">
                <div class="form-group">
                    <label>QQ 群号码</label>
                    <input type="text" name="contact_qq" value="<?= htmlspecialchars(platform_config('system', 'contact_qq', '')) ?>" class="form-input" placeholder="如 123456789">
                </div>
                <div class="form-row">
                    <div class="form-group" style="flex:1">
                        <label>QQ 群二维码</label>
                        <input type="file" name="contact_qq_qr" accept="image/*" class="form-input" style="padding:6px">
                        <?php $qqQr = platform_config('system', 'contact_qq_qr', ''); if ($qqQr): ?>
                        <div style="margin-top:4px"><img src="<?= url("/" . $qqQr) ?>" style="max-height:80px;border-radius:4px"> <label style="font-size:12px;color:var(--text-muted)"><input type="checkbox" name="remove_qq_qr" value="1"> 删除</label></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group" style="flex:1">
                        <label>微信二维码</label>
                        <input type="file" name="contact_wx_qr" accept="image/*" class="form-input" style="padding:6px">
                        <?php $wxQr = platform_config('system', 'contact_wx_qr', ''); if ($wxQr): ?>
                        <div style="margin-top:4px"><img src="<?= url("/" . $wxQr) ?>" style="max-height:80px;border-radius:4px"> <label style="font-size:12px;color:var(--text-muted)"><input type="checkbox" name="remove_wx_qr" value="1"> 删除</label></div>
                        <?php endif; ?>
                    </div>
                </div>
                <button class="btn btn-primary">💾 保存</button>
            </form>
        </section>

        <!-- Recent Overrides & Notifications -->
        <section class="admin-section">
            <h2>📋 近期调整记录</h2>
            <?php if (!empty($overrides)): ?>
            <table class="stock-table"><thead><tr><th>股票</th><th>方向</th><th>幅度</th><th>日期</th><th>原因</th></tr></thead><tbody>
            <?php foreach ($overrides as $o): ?>
            <tr><td><?= htmlspecialchars($o['symbol']) ?></td><td><?= $o['force_direction'] === 'up' ? '📈' : '📉' ?></td>
            <td><?= $o['force_change_pct'] ?>%</td><td><?= $o['date'] ?></td><td><?= htmlspecialchars($o['reason'] ?? '-') ?></td></tr>
            <?php endforeach; ?>
            </tbody></table>
            <?php else: ?><p class="text-muted">暂无</p><?php endif; ?>
        </section>

        <section class="admin-section">
            <h2>📢 发布公告</h2>
            <form method="POST" class="admin-form" action="<?= url('/admin.php') ?>">
                <input type="hidden" name="action" value="post_announcement">
                <div class="form-group">
                    <label>公告内容（支持 HTML）</label>
                    <textarea name="announce_message" rows="3" class="form-input" required></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>代币奖励（≤ 100000）</label>
                        <input type="number" name="announce_tokens" value="0" min="0" max="100000" class="form-input" style="width:120px">
                    </div>
                    <div class="form-group">
                        <label>股票（可选）</label>
                        <select name="announce_stock_id" class="form-input">
                            <option value="">无</option>
                            <?php foreach ($stocks as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['symbol']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>股票数量</label>
                        <input type="number" name="announce_stock_qty" value="1" min="1" class="form-input" style="width:80px">
                    </div>
                    <button class="btn btn-primary" style="align-self:flex-end">📢 发布</button>
                </div>
            </form>
            <div style="margin-top:16px;max-height:300px;overflow-y:auto">
            <?php foreach ($notifications as $n): ?>
            <div class="tx-item" style="font-size:13px">
                <span class="note-text"><?= $n['message'] ?></span>
                <span class="tx-time" style="font-size:11px;white-space:nowrap">
                    <?php if ((float)$n['reward_tokens'] > 0): ?>🪙+<?= $n['reward_tokens'] ?> <?php endif; ?>
                    <?php if ((int)$n['reward_stock_id'] > 0): ?>📈x<?= $n['reward_stock_quantity'] ?> <?php endif; ?>
                    <?= $n['created_at'] ?>
                </span>
            </div>
            <?php endforeach; ?>
            </div>
        </section>

        <!-- Give Card -->
        <section class="admin-section">
            <h2>🎁 赠送卡牌</h2>
            <form method="POST" class="admin-form">
                <input type="hidden" name="action" value="give_card">
                <div class="form-row">
                    <div class="form-group">
                        <label>用户</label>
                        <select name="target_user_id" class="form-input" required>
                            <option value="">选择用户</option>
                            <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['username']) ?> (#<?= $u['id'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>股票</label>
                        <select name="stock_id" class="form-input" required>
                            <option value="">选择股票</option>
                            <?php foreach ($stocks as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['symbol']) ?> - <?= htmlspecialchars($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>数量</label>
                        <input type="number" name="quantity" value="1" min="1" class="form-input" style="width:80px">
                    </div>
                    <div class="form-group">
                        <label>自定义稀有度（可选）</label>
                        <select name="custom_rarity" class="form-input" style="width:120px">
                            <option value="">不修改</option>
                            <option value="common">普通</option>
                            <option value="rare">稀有</option>
                            <option value="epic">史诗</option>
                            <option value="legendary">传说</option>
                            <option value="limited">绝版</option>
                        </select>
                    </div>
                </div>
                <button class="btn btn-primary">🎁 赠送</button>
            </form>
        </section>

        <!-- SQL Console -->
        <section class="admin-section">
            <h2>🗃️ SQL 控制台</h2>
            <p class="text-muted">直接对 oimanka.db 执行 SQL 查询。DROP/DELETE 语句需要输入初始化密钥。</p>
            <form method="POST" class="admin-form">
                <input type="hidden" name="action" value="exec_sql">
                <div class="form-group">
                    <textarea name="sql_query" rows="4" class="form-input" style="font-family:monospace;font-size:13px" placeholder="SELECT * FROM users LIMIT 10" required></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>初始化密钥（DROP/DELETE 需要）</label>
                        <input type="text" name="setup_key" class="form-input" style="width:200px" autocomplete="off">
                    </div>
                    <button class="btn btn-danger">⚡ 执行</button>
                </div>
            </form>

            <?php if ($sqlResult): ?>
            <div style="margin-top:12px;max-height:400px;overflow:auto">
                <p class="text-muted">返回 <?= $sqlResult['count'] ?> 行</p>
                <table class="stock-table"><thead><tr>
                    <?php foreach (array_keys($sqlResult['rows'][0] ?? []) as $col): ?>
                    <th><?= htmlspecialchars($col) ?></th>
                    <?php endforeach; ?>
                </tr></thead><tbody>
                    <?php foreach ($sqlResult['rows'] as $row): ?>
                    <tr><?php foreach ($row as $v): ?>
                        <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars((string)$v) ?></td>
                    <?php endforeach; ?></tr>
                    <?php endforeach; ?>
                </tbody></table>
            </div>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php include __DIR__ . '/layout/footer.php'; ?>
