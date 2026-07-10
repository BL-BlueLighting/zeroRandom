<?php
/**
 * OIManka - Database Setup / Migration
 *
 * Browser: enter DB_INIT_KEY to run migrations.
 * CLI:
 *   php setup.php setup  — 检查数据库状态
 *   php setup.php enter  — 自动执行迁移修复
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';

if (PHP_SAPI === 'cli') {
    $cmd = $argv[1] ?? '';
    if ($cmd === 'setup') {
        // Check status
        $db = Database::getInstance();
        $existing = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
        $existing = array_map('strtolower', $existing);
        $expectedTables = ['users','stocks','stock_prices','holdings','transactions','gacha_logs','sync_logs','gacha_config','notifications','card_placements','user_hustoj_bindings','price_overrides','platform_config','bind_verifications','card_pools','card_pool_items','card_market_listings','daily_checkins','quest_config','user_quests','claimed_rewards','user_messages','ks_holdings','ks_transactions','ks_gacha_logs','ks_card_placements','ks_card_market_listings','ks_daily_checkins','ks_card_pools','ks_card_pool_items'];
        foreach ($expectedTables as $tbl) {
            $ok = in_array(strtolower($tbl), $existing);
            echo ($ok ? '✅' : '❌') . ' ' . $tbl . "\n";
        }
        $missing = count(array_filter($expectedTables, fn($t) => !in_array(strtolower($t), $existing)));
        echo "\n" . ($missing > 0 ? "⚠️ 缺 {$missing} 个表，执行 php setup.php enter 修复" : "✅ 全部表就绪") . "\n";
        exit(0);
    }
    if ($cmd === 'enter') {
        // Auto-migrate
        $results = Database::migrate();
        $ok = count(array_filter($results, fn($v) => $v === 'ok'));
        $total = count($results);
        echo "✅ 迁移完成！{$ok}/{$total} 个表已就绪。\n";
        // Ensure default pools
        require_once __DIR__ . '/core/Session.php';
        require_once __DIR__ . '/core/StockEngine.php';
        require_once __DIR__ . '/core/GachaEngine.php';
        require_once __DIR__ . '/core/PoolEngine.php';
        PoolEngine::ensureDefaultPool();
        echo "✅ 默认卡池已就绪。\n";
        exit(0);
    }
    echo "用法: php setup.php [setup|enter]\n";
    exit(0);
}

// Browser mode — always need key or use CLI
$verified = false;

$message = null;
$error = null;
$dbOk = false;
$tables = [];
$missingTables = [];
$brokenTables = [];

// Expected schema: table_name => [label, [expected_columns...]]
$expectedSchema = [
    'users' => ['用户表', ['id', 'username', 'password_hash', 'platform_user_id', 'platform_name', 'token_balance', 'total_earned', 'total_spent', 'is_admin', 'created_at', 'updated_at', 'last_active_at', 'number_style', 'kaleidoscope_balance', 'kaleidoscope_expires_at']],
    'stocks' => ['股票表', ['id', 'symbol', 'name', 'adapter_key', 'adapter_name', 'category', 'rarity', 'total_supply', 'circulating_supply', 'base_price', 'current_price', 'prev_price', 'price_change_pct', 'volume_24h', 'market_cap', 'metadata', 'is_active', 'created_at', 'updated_at', 'limited_edition']],
    'stock_prices' => ['价格历史表', ['id', 'stock_id', 'price', 'ac_ratio', 'submit_count', 'ac_count', 'recorded_at']],
    'holdings' => ['持仓表', ['id', 'user_id', 'stock_id', 'quantity', 'avg_cost']],
    'transactions' => ['交易记录表', ['id', 'user_id', 'stock_id', 'type', 'quantity', 'price', 'total_amount', 'fee', 'notes', 'created_at']],
    'gacha_logs' => ['抽卡记录表', ['id', 'user_id', 'stock_id', 'rarity', 'pull_type', 'cost', 'created_at']],
    'sync_logs' => ['同步日志表', ['id', 'adapter_name', 'status', 'items_synced', 'error_message', 'started_at', 'finished_at', 'created_at']],
    'gacha_config' => ['抽卡配置表', ['id', 'date', 'rarity', 'weight', 'created_at']],
    'notifications' => ['通知表', ['id', 'message', 'reward_tokens', 'reward_stock_id', 'reward_stock_quantity', 'is_active', 'created_at']],
    'card_placements' => ['卡牌放置表', ['id', 'user_id', 'stock_id', 'slot', 'placed_at']],
    'user_hustoj_bindings' => ['OJ绑定表', ['id', 'user_id', 'oj_user_id', 'oj_username', 'total_ac', 'last_synced_at', 'created_at']],
    'price_overrides' => ['价格覆盖表', ['id', 'stock_id', 'force_direction', 'force_change_pct', 'date', 'reason', 'created_by', 'created_at']],
    'platform_config' => ['平台配置表', ['id', 'adapter_name', 'config_key', 'config_value', 'created_at']],
    'bind_verifications' => ['绑定验证表', ['id', 'user_id', 'oj_user_id', 'code', 'code_md5', 'verified', 'expires_at', 'created_at']],
    'card_pools' => ['卡池表', ['id', 'name', 'is_default', 'is_limited', 'expires_at', 'sort_order', 'created_at']],
    'card_pool_items' => ['卡池题目表', ['id', 'pool_id', 'stock_id']],
    'card_market_listings' => ['卡牌市场表', ['id', 'seller_id', 'stock_id', 'quantity', 'price', 'status', 'created_at', 'sold_at', 'buyer_id']],
    'daily_checkins' => ['每日签到表', ['id', 'user_id', 'checkin_date', 'created_at']],
    'quest_config' => ['任务配置表', ['id', 'type', 'name', 'description', 'condition_type', 'condition_value', 'reward_tokens', 'is_active', 'created_at']],
    'user_quests' => ['用户任务进度表', ['id', 'user_id', 'quest_id', 'progress', 'completed', 'completed_at']],
    'claimed_rewards' => ['领取记录表', ['id', 'user_id', 'notification_id', 'created_at']],
    'user_messages' => ['用户消息表', ['id', 'to_user', 'from_user', 'title', 'content', 'is_read', 'created_at']],
    'ks_tables' => ['天界数据表组', ['id']],
];

// Check data dir writable
$dataDir = dirname(defined('DB_PATH') ? DB_PATH : __DIR__ . '/data/oimanka.db');
if (!is_writable(dirname($dataDir)) && !is_writable($dataDir)) {
    $error = '! 程序 data 目录不可写，请联系管理人员或刷新页面 !';
} else {
    try {
        $db = Database::getInstance();
        $existing = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
        $existing = array_map('strtolower', $existing);

        foreach ($expectedSchema as $tbl => [$label, $expectedCols]) {
            $exists = in_array(strtolower($tbl), $existing);
            $missingCols = [];
            $extraCols = [];

            if ($exists) {
                // Check columns via PRAGMA
                $cols = $db->query("PRAGMA table_info(`{$tbl}`)")->fetchAll(PDO::FETCH_COLUMN, 1);
                $cols = array_map('strtolower', $cols);
                $expectedLower = array_map('strtolower', $expectedCols);
                foreach ($expectedLower as $c) {
                    if (!in_array($c, $cols)) $missingCols[] = $c;
                }
                foreach ($cols as $c) {
                    if (!in_array($c, $expectedLower)) $extraCols[] = $c;
                }
            }

            $tables[$tbl] = [
                'label' => $label,
                'exists' => $exists,
                'missing_cols' => $missingCols,
                'extra_cols' => $extraCols,
                'total_cols' => count($expectedCols),
                'actual_cols' => $exists ? count($expectedCols) - count($missingCols) : 0,
            ];

            if (!$exists) $missingTables[] = $tbl;
            if (!empty($missingCols)) $brokenTables[] = $tbl;
        }

        $dbOk = !in_array('users', $missingTables);
    } catch (Exception $e) {
        $error = '数据库连接失败: ' . $e->getMessage();
    }
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $key = $_POST['key'] ?? '';
    if (!$verified && $key !== DB_INIT_KEY) {
        $error = '❌ 初始化密钥错误！';
    } else {
        try {
            $results = Database::migrate();
            $ok = count(array_filter($results, fn($v) => $v === 'ok'));
            $total = count($results);
            $message = "✅ 迁移完成！{$ok}/{$total} 个表已就绪。";

            // Refresh status
            $db = Database::getInstance();
            foreach ($expectedSchema as $tbl => [$label, $expectedCols]) {
                $exists = in_array(strtolower($tbl), array_map('strtolower', $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN)));
                $missingCols = [];
                if ($exists) {
                    $cols = $db->query("PRAGMA table_info(`{$tbl}`)")->fetchAll(PDO::FETCH_COLUMN, 1);
                    $cols = array_map('strtolower', $cols);
                    foreach (array_map('strtolower', $expectedCols) as $c) {
                        if (!in_array($c, $cols)) $missingCols[] = $c;
                    }
                }
                $tables[$tbl]['exists'] = $exists;
                $tables[$tbl]['missing_cols'] = $missingCols;
                $tables[$tbl]['actual_cols'] = $exists ? count($expectedCols) - count($missingCols) : 0;
            }
            $missingTables = array_filter(array_keys($tables), fn($t) => !$tables[$t]['exists']);
            $brokenTables = array_filter(array_keys($tables), fn($t) => !empty($tables[$t]['missing_cols']));
            $dbOk = !in_array('users', $missingTables);

            // Ensure default card pool
            if ($dbOk) {
                require_once __DIR__ . '/core/Session.php';
                require_once __DIR__ . '/core/StockEngine.php';
                require_once __DIR__ . '/core/GachaEngine.php';
                require_once __DIR__ . '/core/PoolEngine.php';
                PoolEngine::ensureDefaultPool();
            }
        } catch (Exception $e) {
            $error = '❌ 迁移失败: ' . $e->getMessage();
        }
    }
}

header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<html lang="zh-CN">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>数据库管理 - <?= APP_NAME ?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Noto Sans SC",sans-serif;background:#0a0a1a;color:#e0e0e0;min-height:100vh;display:flex;align-items:center;justify-content:center}
.card{background:#12122a;border-radius:12px;padding:40px;max-width:680px;width:94%;border:1px solid #2a2a4a}
h1{color:#4da6ff;margin-bottom:12px;font-size:24px;text-align:center}
p{color:#a0a0c0;margin-bottom:16px;line-height:1.6;font-size:14px}
.success{background:#103a20;border:1px solid #4ade80;color:#86efac;padding:12px;border-radius:8px;margin:12px 0}
.error{background:#3a1010;border:1px solid #f87171;color:#fca5a5;padding:12px;border-radius:8px;margin:12px 0}
.warning{background:#2a1a0a;border:1px solid #f59e0b;color:#fbbf24;padding:12px;border-radius:8px;margin:12px 0}
input{padding:10px 14px;border-radius:8px;background:#1a1a3a;border:1px solid #3a3a5a;color:#fff;font-size:16px;width:100%;margin-bottom:12px;text-align:center;outline:none}
input:focus{border-color:#4da6ff}
.btn{display:inline-block;padding:12px 32px;border-radius:8px;font-size:16px;font-weight:bold;cursor:pointer;text-decoration:none;border:none;background:#4da6ff;color:#fff;width:100%}
.btn:hover{background:#3399ff}
.btn-warning{background:#f59e0b;color:#0a0a1a}
.btn-success{background:#4ade80;color:#0a0a1a;width:auto;display:inline-block}
.mono{font-family:monospace;color:#f59e0b;font-size:12px}
table{width:100%;border-collapse:collapse;margin:12px 0;font-size:13px}
th,td{padding:5px 8px;border-bottom:1px solid #2a2a4a;text-align:left;vertical-align:top}
th{color:#6666a0;font-size:11px;text-transform:uppercase;white-space:nowrap}
.tbl-ok{color:#4ade80}
.tbl-missing{color:#f87171}
.tbl-fixed{color:#f59e0b}
.summary{padding:8px 12px;background:#1a1a2a;border-radius:6px;font-size:14px;margin:12px 0}
.col-missing{color:#f87171;font-size:11px;display:block}
</style>
</head>
<body>
<div class="card" style="text-align:left">
    <h1>🔧 <?= APP_NAME ?> — 数据库管理</h1>

    <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($message): ?>
    <div class="success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- Summary -->
    <div class="summary">
        <strong>表结构状态：</strong>
        <?php
        $total = count($tables);
        $okCount = count(array_filter($tables, fn($t) => $t['exists'] && empty($t['missing_cols'])));
        $missingCount = count($missingTables);
        $brokenCount = count($brokenTables);
        if ($missingCount + $brokenCount === 0):
        ?>
        <span class="tbl-ok">✅ 全部 <?= $total ?> 个表结构正确</span>
        <?php else: ?>
        <span class="tbl-missing">⚠️ 缺失 <?= $missingCount ?> 个表，<?= $brokenCount ?> 个表缺少字段</span>
        <?php endif; ?>
    </div>

    <!-- Table Status -->
    <div style="max-height:50vh;overflow-y:auto">
    <table>
        <thead><tr><th>表名</th><th>说明</th><th>字段</th><th>状态</th></tr></thead>
        <tbody>
        <?php foreach ($tables as $tbl => $info): ?>
        <tr>
            <td class="mono"><?= $tbl ?></td>
            <td style="font-size:12px;color:#a0a0c0"><?= $info['label'] ?></td>
            <td style="font-size:11px">
                <?= $info['exists'] ? "{$info['actual_cols']}/{$info['total_cols']}" : '-' ?>
                <?php if (!empty($info['missing_cols'])): ?>
                <span class="col-missing">缺: <?= implode(', ', $info['missing_cols']) ?></span>
                <?php endif; ?>
            </td>
            <td>
                <?php if (!$info['exists']): ?>
                <span class="tbl-missing">❌ 缺失</span>
                <?php elseif (!empty($info['missing_cols'])): ?>
                <span class="tbl-fixed">⚠️ 缺字段</span>
                <?php else: ?>
                <span class="tbl-ok">✅</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <?php if ($missingCount + $brokenCount > 0): ?>
    <div class="warning" style="text-align:center">
        ⚠️ 检测到 <?= $missingCount ?> 个缺失表 + <?= $brokenCount ?> 个表缺少字段
    </div>
    <form method="POST">
        <?php if ($verified): ?>
        <p style="color:#4ade80;font-size:13px;text-align:center;margin-bottom:12px">✅ 已验证，可直接执行迁移</p>
        <button class="btn btn-warning">🛠️ 执行迁移修复</button>
        <?php else: ?>
        <p style="color:#6666a0;font-size:13px;text-align:center;margin-bottom:12px">输入密钥或执行 <span class="mono">php setup.php enter</span> 验证</p>
        <input type="text" name="key" placeholder="输入初始化密钥" autocomplete="off">
        <button class="btn btn-warning">🛠️ 执行迁移修复</button>
        <p style="font-size:12px;text-align:center;margin-top:8px;color:#6666a0">
            密钥在 <span class="mono">config.php</span> 或终端执行 <span class="mono">php setup.php enter</span>
        </p>
        <?php endif; ?>
    </form>
    <?php elseif ($dbOk): ?>
    <div style="text-align:center;margin-top:16px">
        <a href="<?= APP_URL ?>/" class="btn btn-success" style="padding:12px 40px">🚀 进入程序</a>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
