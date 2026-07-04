<?php
/**
 * OIManka - Database Setup / Migration
 *
 * Enter DB_INIT_KEY to run migrations and initialize/repair the database.
 * Detects missing tables and structure issues.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';

$message = null;
$error = null;
$dbOk = false;
$tables = [];
$missingTables = [];
$brokenTables = [];

// All expected tables (from Database::migrate())
$expectedTables = [
    'users' => '用户表',
    'stocks' => '股票表',
    'stock_prices' => '价格历史表',
    'holdings' => '持仓表',
    'transactions' => '交易记录表',
    'gacha_logs' => '抽卡记录表',
    'sync_logs' => '同步日志表',
    'gacha_config' => '抽卡配置表',
    'notifications' => '通知表',
    'card_placements' => '卡牌放置表',
    'user_hustoj_bindings' => 'OJ绑定表',
    'price_overrides' => '价格覆盖表',
    'platform_config' => '平台配置表',
    'bind_verifications' => '绑定验证表',
    'card_pools' => '卡池表',
    'card_pool_items' => '卡池题目表',
    'card_market_listings' => '卡牌市场表',
    'daily_checkins' => '每日签到表',
    'quest_config' => '任务配置表',
    'user_quests' => '用户任务进度表',
];

// Check data dir writable
$dataDir = dirname(defined('DB_PATH') ? DB_PATH : __DIR__ . '/data/oimanka.db');
if (!is_writable(dirname($dataDir)) && !is_writable($dataDir)) {
    $error = '! 程序 data 目录不可写，请联系管理人员或刷新页面 !';
} else {
    // Check table status
    try {
        $db = Database::getInstance();
        $existing = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
        $existing = array_map('strtolower', $existing);

        foreach ($expectedTables as $tbl => $label) {
            $exists = in_array(strtolower($tbl), $existing);
            $tables[$tbl] = ['label' => $label, 'exists' => $exists];
            if (!$exists) $missingTables[] = $tbl;
        }

        // Check if core tables exist (users = initialized)
        if (in_array('users', $missingTables)) {
            $dbOk = false;
        } else {
            $dbOk = true;
        }
    } catch (Exception $e) {
        $error = '数据库连接失败: ' . $e->getMessage();
    }
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $key = $_POST['key'] ?? '';
    if ($key !== DB_INIT_KEY) {
        $error = '❌ 初始化密钥错误！';
    } else {
        try {
            $results = Database::migrate();
            $ok = count(array_filter($results, fn($v) => $v === 'ok'));
            $total = count($results);
            $message = "✅ 迁移完成！{$ok}/{$total} 个表已就绪。";

            // Refresh table status
            $db = Database::getInstance();
            $existing = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
            $existing = array_map('strtolower', $existing);
            $missingTables = [];
            foreach ($expectedTables as $tbl => $label) {
                $exists = in_array(strtolower($tbl), $existing);
                $tables[$tbl] = ['label' => $label, 'exists' => $exists];
                if (!$exists) $missingTables[] = $tbl;
            }
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
.card{background:#12122a;border-radius:12px;padding:40px;max-width:560px;width:94%;border:1px solid #2a2a4a;text-align:center}
h1{color:#4da6ff;margin-bottom:12px;font-size:24px}
p{color:#a0a0c0;margin-bottom:16px;line-height:1.6;font-size:14px}
.success{background:#103a20;border:1px solid #4ade80;color:#86efac;padding:12px;border-radius:8px;margin:12px 0}
.error{background:#3a1010;border:1px solid #f87171;color:#fca5a5;padding:12px;border-radius:8px;margin:12px 0}
input{padding:10px 14px;border-radius:8px;background:#1a1a3a;border:1px solid #3a3a5a;color:#fff;font-size:16px;width:100%;margin-bottom:12px;text-align:center;outline:none}
input:focus{border-color:#4da6ff}
.btn{display:inline-block;padding:12px 32px;border-radius:8px;font-size:16px;font-weight:bold;cursor:pointer;text-decoration:none;border:none;background:#4da6ff;color:#fff;width:100%}
.btn:hover{background:#3399ff}
.btn-warning{background:#f59e0b;color:#0a0a1a}
.btn-success{background:#4ade80;color:#0a0a1a}
.btn-sm{display:inline-block;padding:6px 16px;border-radius:6px;font-size:13px;font-weight:bold;cursor:pointer;border:none;background:#4da6ff;color:#fff;text-decoration:none}
.mono{font-family:monospace;color:#f59e0b;font-size:13px;background:#1a1a2a;padding:4px 8px;border-radius:4px;display:inline-block}
table{width:100%;border-collapse:collapse;margin:12px 0;font-size:13px}
th,td{padding:6px 8px;border-bottom:1px solid #2a2a4a;text-align:left}
th{color:#6666a0;font-size:11px;text-transform:uppercase}
.tbl-ok{color:#4ade80}
.tbl-missing{color:#f87171}
.summary{margin:12px 0;padding:8px;background:#1a1a2a;border-radius:6px;font-size:13px}
</style>
</head>
<body>
<div class="card" style="text-align:left">
    <h1 style="text-align:center">🔧 <?= APP_NAME ?> — 数据库管理</h1>

    <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($message): ?>
    <div class="success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- Table Status -->
    <div class="summary">
        <strong>表结构状态：</strong>
        <?php if (empty($missingTables)): ?>
        <span class="tbl-ok">✅ 全部 <?= count($tables) ?> 个表就绪</span>
        <?php else: ?>
        <span class="tbl-missing">⚠️ 缺少 <?= count($missingTables) ?> 个表</span>
        <?php endif; ?>
    </div>

    <table>
        <thead><tr><th>表名</th><th>说明</th><th>状态</th></tr></thead>
        <tbody>
        <?php foreach ($tables as $tbl => $info): ?>
        <tr>
            <td class="mono" style="font-size:12px"><?= $tbl ?></td>
            <td style="font-size:12px;color:#a0a0c0"><?= $info['label'] ?></td>
            <td><?= $info['exists'] ? '<span class="tbl-ok">✅</span>' : '<span class="tbl-missing">❌ 缺失</span>' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php if (!empty($missingTables)): ?>
    <div class="summary tbl-missing" style="text-align:center">
        ⚠️ 检测到 <?= count($missingTables) ?> 个表缺失，请输入密钥执行迁移修复。
    </div>
    <?php if (!$error): ?>
    <form method="POST">
        <input type="text" name="key" placeholder="输入初始化密钥" autocomplete="off">
        <button class="btn btn-warning">🛠️ 执行迁移修复</button>
    </form>
    <p class="text-muted" style="font-size:12px;text-align:center;margin-top:8px">
        密钥在 <span class="mono">config.php</span> 中的 <span class="mono">DB_INIT_KEY</span>
    </p>
    <?php endif; ?>
    <?php elseif ($dbOk): ?>
    <div style="text-align:center;margin-top:16px">
        <a href="<?= APP_URL ?>/" class="btn btn-success" style="width:auto;padding:12px 40px;display:inline-block">🚀 进入程序</a>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
