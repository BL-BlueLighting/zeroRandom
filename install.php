<?php
/**
 * zero Random - Installation Script
 *
 * Creates database tables with live progress updates.
 * No seed data — stocks come from adapters only.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';

$step = $_GET['step'] ?? 'info';

// ── Step 2: Run installation with live progress ──
if ($step === 'run') {
    // Disable output buffering for live streaming
    if (ob_get_level()) ob_end_clean();
    ini_set('output_buffering', '0');
    ini_set('zlib.output_compression', '0');
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-cache');

    echo '<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>安装中 - zero Random</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Noto Sans SC",sans-serif;background:#0a0a1a;color:#e0e0e0;min-height:100vh;display:flex;align-items:center;justify-content:center}
.container{background:#12122a;border-radius:12px;padding:40px;max-width:600px;width:90%;border:1px solid #2a2a4a}
h1{color:#4da6ff;margin-bottom:8px;font-size:24px}
h2{color:#a0a0c0;font-size:14px;font-weight:normal;margin-bottom:20px}
.steps{display:flex;flex-direction:column;gap:6px;margin-bottom:20px}
.step{display:flex;align-items:center;gap:10px;padding:8px 12px;background:#16163a;border-radius:6px;font-size:14px;transition:all .2s}
.step .icon{width:24px;text-align:center;font-size:14px}
.step.pending{color:#6666a0}
.step.running{color:#f59e0b;background:#1a1a2a;border-left:3px solid #f59e0b}
.step.done{color:#4ade80}
.step.error{color:#f87171;background:#3a1010}
.progress-bar{height:4px;background:#1a1a3a;border-radius:2px;margin-bottom:20px;overflow:hidden}
.progress-fill{height:100%;background:linear-gradient(90deg,#4da6ff,#4ade80);border-radius:2px;transition:width .3s ease}
.final{text-align:center;padding:16px;border-radius:8px;margin-top:12px}
.final.success{background:#103a20;border:1px solid #4ade80;color:#86efac}
.final.error{background:#3a1010;border:1px solid #f87171;color:#fca5a5}
a{color:#4da6ff}
.btn{display:inline-block;padding:10px 24px;border-radius:6px;font-size:14px;font-weight:600;text-decoration:none;margin-top:8px}
.btn-primary{background:#4da6ff;color:#fff}
.btn-primary:hover{background:#3399ff}
.hidden{display:none}
</style>
</head>
<body>
<div class="container">
<h1>🔧 正在安装...</h1>
<h2>请稍候，正在创建数据库表</h2>
<div class="progress-bar"><div class="progress-fill" id="progress" style="width:0%"></div></div>
<div class="steps" id="steps">';
    flush();

    $migrations = [
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
        'claimed_rewards' => '领取记录表',
        'ks_tables' => '天界数据表组',
        'card_pools' => '卡池表',
        'card_pool_items' => '卡池题目表',
        'card_market_listings' => '卡牌市场表',
        'daily_checkins' => '每日签到表',
        'quest_config' => '任务配置表',
        'user_quests' => '用户任务进度表',
        'user_messages' => '用户消息表',
    ];

    $total = count($migrations);
    $done = 0;
    $errors = [];

    $db = Database::getInstance();

    foreach ($migrations as $table => $label) {
        // Show running
        echo '<div class="step running" id="step-' . $table . '"><span class="icon">⏳</span>' . $label . ' (' . $table . ')</div>';
        echo '<script>document.getElementById("progress").style.width="' . round(($done / $total) * 100) . '%"</script>';
        flush();

        // Small delay so user can see progress (10ms is imperceptible but ensures DOM paints)
        usleep(50000);

        try {
            Database::getInstance()->exec(getMigrationSQL($table));
            echo '<script>document.getElementById("step-' . $table . '").className="step done";document.getElementById("step-' . $table . '").querySelector(".icon").textContent="✅";</script>';
        } catch (Exception $e) {
            $errors[$table] = $e->getMessage();
            echo '<script>document.getElementById("step-' . $table . '").className="step error";document.getElementById("step-' . $table . '").querySelector(".icon").textContent="❌";</script>';
        }

        $done++;
        flush();
    }

    echo '<script>document.getElementById("progress").style.width="' . round(($done / $total) * 100) . '%"</script>';

    runColumnMigrations(Database::getInstance());

    if (empty($errors)) {
        echo '</div><div class="final success"><strong>✅ 安装成功！</strong><br>现在去注册，第一位用户将成为管理员。<br><a href="' . url('/register') . '" class="btn btn-primary" style="margin-top:12px">前往注册</a></div>';
    } else {
        echo '</div><div class="final error"><strong>❌ 部分表创建失败</strong><br>';
        foreach ($errors as $t => $e) {
            echo htmlspecialchars("$t: $e") . '<br>';
        }
        echo '</div>';
    }

    echo '</div></body></html>';
    exit;
}

// ── Helper: get SQL for each migration ──
function getMigrationSQL(string $table): string {
    $sqls = [
        'users' => "
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password_hash TEXT,
                platform_user_id TEXT,
                platform_name TEXT,
                token_balance REAL DEFAULT 100.0,
                total_earned REAL DEFAULT 0.0,
                total_spent REAL DEFAULT 0.0,
                is_admin INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ",
        'stocks' => "
            CREATE TABLE IF NOT EXISTS stocks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                symbol TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                adapter_key TEXT NOT NULL,
                adapter_name TEXT NOT NULL,
                category TEXT,
                rarity TEXT DEFAULT 'common',
                total_supply INTEGER DEFAULT 1000,
                circulating_supply INTEGER DEFAULT 0,
                base_price REAL DEFAULT 10.0,
                current_price REAL DEFAULT 10.0,
                prev_price REAL DEFAULT 10.0,
                price_change_pct REAL DEFAULT 0.0,
                volume_24h INTEGER DEFAULT 0,
                market_cap REAL DEFAULT 0.0,
                metadata TEXT,
                is_active INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ",
        'stock_prices' => "
            CREATE TABLE IF NOT EXISTS stock_prices (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                stock_id INTEGER NOT NULL,
                price REAL NOT NULL,
                ac_ratio REAL,
                submit_count INTEGER DEFAULT 0,
                ac_count INTEGER DEFAULT 0,
                recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (stock_id) REFERENCES stocks(id)
            )
        ",
        'holdings' => "
            CREATE TABLE IF NOT EXISTS holdings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                stock_id INTEGER NOT NULL,
                quantity INTEGER NOT NULL DEFAULT 0,
                avg_cost REAL NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (stock_id) REFERENCES stocks(id),
                UNIQUE(user_id, stock_id)
            )
        ",
        'transactions' => "
            CREATE TABLE IF NOT EXISTS transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                stock_id INTEGER,
                type TEXT NOT NULL,
                quantity INTEGER,
                price REAL,
                total_amount REAL,
                fee REAL DEFAULT 0.0,
                notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ",
        'gacha_logs' => "
            CREATE TABLE IF NOT EXISTS gacha_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                stock_id INTEGER NOT NULL,
                rarity TEXT NOT NULL,
                pull_type TEXT DEFAULT 'single',
                cost REAL NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (stock_id) REFERENCES stocks(id)
            )
        ",
        'sync_logs' => "
            CREATE TABLE IF NOT EXISTS sync_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                adapter_name TEXT NOT NULL,
                status TEXT NOT NULL,
                items_synced INTEGER DEFAULT 0,
                error_message TEXT,
                started_at DATETIME,
                finished_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ",
        'gacha_config' => "
            CREATE TABLE IF NOT EXISTS gacha_config (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                date TEXT NOT NULL,
                rarity TEXT NOT NULL,
                weight INTEGER NOT NULL DEFAULT 10,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(date, rarity)
            )
        ",
        'notifications' => "
            CREATE TABLE IF NOT EXISTS notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                message TEXT NOT NULL,
                is_active INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ",
        'card_placements' => "
            CREATE TABLE IF NOT EXISTS card_placements (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                stock_id INTEGER NOT NULL,
                slot INTEGER NOT NULL DEFAULT 1,
                placed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (stock_id) REFERENCES stocks(id),
                UNIQUE(user_id, slot)
            )
        ",
        'user_hustoj_bindings' => "
            CREATE TABLE IF NOT EXISTS user_hustoj_bindings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL UNIQUE,
                oj_user_id TEXT NOT NULL,
                oj_username TEXT NOT NULL,
                total_ac INTEGER DEFAULT 0,
                last_synced_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ",
        'price_overrides' => "
            CREATE TABLE IF NOT EXISTS price_overrides (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                stock_id INTEGER NOT NULL,
                force_direction TEXT,
                force_change_pct REAL,
                date TEXT NOT NULL,
                reason TEXT,
                created_by INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (stock_id) REFERENCES stocks(id),
                UNIQUE(stock_id, date)
            )
        ",
        'platform_config' => "
            CREATE TABLE IF NOT EXISTS platform_config (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                adapter_name TEXT NOT NULL,
                config_key TEXT NOT NULL,
                config_value TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(adapter_name, config_key)
            )
        ",
        'card_pools' => "
            CREATE TABLE IF NOT EXISTS card_pools (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                is_default INTEGER DEFAULT 0,
                sort_order INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ",
        'card_pool_items' => "
            CREATE TABLE IF NOT EXISTS card_pool_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                pool_id INTEGER NOT NULL,
                stock_id INTEGER NOT NULL,
                FOREIGN KEY (pool_id) REFERENCES card_pools(id) ON DELETE CASCADE,
                FOREIGN KEY (stock_id) REFERENCES stocks(id)
            )
        ",
        'card_market_listings' => "
            CREATE TABLE IF NOT EXISTS card_market_listings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                seller_id INTEGER NOT NULL,
                stock_id INTEGER NOT NULL,
                quantity INTEGER NOT NULL DEFAULT 1,
                price REAL NOT NULL,
                status TEXT NOT NULL DEFAULT 'listed',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                sold_at DATETIME,
                buyer_id INTEGER,
                FOREIGN KEY (seller_id) REFERENCES users(id),
                FOREIGN KEY (buyer_id) REFERENCES users(id),
                FOREIGN KEY (stock_id) REFERENCES stocks(id)
            )
        ",
        'daily_checkins' => "
            CREATE TABLE IF NOT EXISTS daily_checkins (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                checkin_date TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id),
                UNIQUE(user_id, checkin_date)
            )
        ",
        'quest_config' => "
            CREATE TABLE IF NOT EXISTS quest_config (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                type TEXT NOT NULL,
                name TEXT NOT NULL,
                description TEXT,
                condition_type TEXT NOT NULL,
                condition_value REAL NOT NULL DEFAULT 0,
                reward_tokens REAL NOT NULL DEFAULT 0,
                is_active INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ",
        'user_quests' => "
            CREATE TABLE IF NOT EXISTS user_quests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                quest_id INTEGER NOT NULL,
                progress REAL DEFAULT 0,
                completed INTEGER DEFAULT 0,
                completed_at DATETIME,
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (quest_id) REFERENCES quest_config(id),
                UNIQUE(user_id, quest_id)
            )
        ",
        'user_messages' => "
            CREATE TABLE IF NOT EXISTS user_messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                to_user INTEGER NOT NULL,
                from_user TEXT NOT NULL DEFAULT 'system',
                title TEXT NOT NULL,
                content TEXT,
                is_read INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ",
        'ks_tables' => "
            CREATE TABLE IF NOT EXISTS ks_holdings (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, stock_id INTEGER NOT NULL, quantity INTEGER NOT NULL DEFAULT 0, avg_cost REAL NOT NULL, UNIQUE(user_id, stock_id));
            CREATE TABLE IF NOT EXISTS ks_transactions (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, stock_id INTEGER, type TEXT NOT NULL, quantity INTEGER, price REAL, total_amount REAL, fee REAL DEFAULT 0, notes TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
            CREATE TABLE IF NOT EXISTS ks_gacha_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, stock_id INTEGER NOT NULL, rarity TEXT NOT NULL, pull_type TEXT DEFAULT 'single', cost REAL NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
            CREATE TABLE IF NOT EXISTS ks_card_placements (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, stock_id INTEGER NOT NULL, slot INTEGER NOT NULL DEFAULT 1, placed_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE(user_id, slot));
            CREATE TABLE IF NOT EXISTS ks_card_market_listings (id INTEGER PRIMARY KEY AUTOINCREMENT, seller_id INTEGER NOT NULL, stock_id INTEGER NOT NULL, quantity INTEGER NOT NULL DEFAULT 1, price REAL NOT NULL, status TEXT NOT NULL DEFAULT 'listed', created_at DATETIME DEFAULT CURRENT_TIMESTAMP, sold_at DATETIME, buyer_id INTEGER);
            CREATE TABLE IF NOT EXISTS ks_daily_checkins (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, checkin_date TEXT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE(user_id, checkin_date));
        ",
    ];
    return $sqls[$table] ?? '';
}

// Column migrations (run after table creation)
function runColumnMigrations(PDO $db): void {
    $cmds = [
        "ALTER TABLE card_pools ADD COLUMN is_limited INTEGER DEFAULT 0",
        "ALTER TABLE card_pools ADD COLUMN expires_at DATETIME",
        "ALTER TABLE stocks ADD COLUMN limited_edition INTEGER DEFAULT 0",
        "ALTER TABLE notifications ADD COLUMN reward_tokens REAL DEFAULT 0",
        "ALTER TABLE notifications ADD COLUMN reward_stock_id INTEGER DEFAULT 0",
        "ALTER TABLE notifications ADD COLUMN reward_stock_quantity INTEGER DEFAULT 0",
        "CREATE TABLE IF NOT EXISTS claimed_rewards (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            notification_id INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (notification_id) REFERENCES notifications(id),
            UNIQUE(user_id, notification_id)
        )",
        "CREATE TABLE IF NOT EXISTS user_messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            to_user INTEGER NOT NULL,
            from_user TEXT NOT NULL DEFAULT 'system',
            title TEXT NOT NULL,
            content TEXT,
            is_read INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        "ALTER TABLE users ADD COLUMN last_active_at DATETIME",
        "ALTER TABLE users ADD COLUMN number_style TEXT DEFAULT 'wan'",
        "ALTER TABLE users ADD COLUMN kaleidoscope_balance REAL DEFAULT 0",
        "ALTER TABLE users ADD COLUMN kaleidoscope_expires_at DATETIME",
        "CREATE TABLE IF NOT EXISTS ks_holdings (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, stock_id INTEGER NOT NULL, quantity INTEGER NOT NULL DEFAULT 0, avg_cost REAL NOT NULL, UNIQUE(user_id, stock_id)); CREATE TABLE IF NOT EXISTS ks_transactions (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, stock_id INTEGER, type TEXT NOT NULL, quantity INTEGER, price REAL, total_amount REAL, fee REAL DEFAULT 0, notes TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP); CREATE TABLE IF NOT EXISTS ks_gacha_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, stock_id INTEGER NOT NULL, rarity TEXT NOT NULL, pull_type TEXT DEFAULT 'single', cost REAL NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP); CREATE TABLE IF NOT EXISTS ks_card_placements (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, stock_id INTEGER NOT NULL, slot INTEGER NOT NULL DEFAULT 1, placed_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE(user_id, slot)); CREATE TABLE IF NOT EXISTS ks_card_market_listings (id INTEGER PRIMARY KEY AUTOINCREMENT, seller_id INTEGER NOT NULL, stock_id INTEGER NOT NULL, quantity INTEGER NOT NULL DEFAULT 1, price REAL NOT NULL, status TEXT NOT NULL DEFAULT 'listed', created_at DATETIME DEFAULT CURRENT_TIMESTAMP, sold_at DATETIME, buyer_id INTEGER); CREATE TABLE IF NOT EXISTS ks_daily_checkins (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, checkin_date TEXT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE(user_id, checkin_date))",
    ];
    foreach ($cmds as $sql) {
        try { $db->exec($sql); } catch (PDOException $e) { /* ignore if exists */ }
    }
}

// ── Step 1: Info page ──

$results = [];
$error = null;

if ($step === 'info') {
    $checks = [
        'php_version' => version_compare(PHP_VERSION, '7.0', '>='),
        'pdo_sqlite' => extension_loaded('pdo_sqlite'),
        'data_dir_writable' => is_writable(__DIR__ . '/data') || is_writable(__DIR__),
    ];
    $optionals = [
        'pdo_mysql' => extension_loaded('pdo_mysql'),
    ];
    $allOk = !in_array(false, $checks, true);
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>安装 - zero Random</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Noto Sans SC", sans-serif; background: #0a0a1a; color: #e0e0e0; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .container { background: #12122a; border-radius: 12px; padding: 40px; max-width: 600px; width: 90%; border: 1px solid #2a2a4a; }
        h1 { color: #4da6ff; margin-bottom: 16px; font-size: 28px; }
        p { margin-bottom: 12px; color: #a0a0c0; line-height: 1.6; }
        .checks { margin: 20px 0; }
        .check { display: flex; align-items: center; padding: 8px 0; border-bottom: 1px solid #1a1a3a; }
        .check .label { flex: 1; }
        .check .status { font-weight: bold; }
        .pass { color: #4ade80; }
        .fail { color: #f87171; }
        .btn { display: inline-block; padding: 12px 32px; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; text-decoration: none; border: none; margin-top: 16px; margin-right: 8px; background: #4da6ff; color: #fff; }
        .btn:hover { background: #3399ff; }
        .btn-secondary { background: #2a2a4a; color: #a0a0c0; }
        .error { background: #3a1010; border: 1px solid #f87171; color: #fca5a5; padding: 12px; border-radius: 8px; margin: 16px 0; }
        .success { background: #103a20; border: 1px solid #4ade80; color: #86efac; padding: 12px; border-radius: 8px; margin: 16px 0; }
        .text-muted { color: #6666a0; font-size: 13px; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔧 <i>zero</i> Random 安装</h1>

    <?php if (Database::isInitialized()): ?>
        <div class="success">✅ 数据库已初始化。</div>
        <a href="<?= url('/') ?>" class="btn">前往首页</a>
    <?php else: ?>
        <p>环境检查：</p>
        <div class="checks">
            <?php foreach ($checks as $name => $ok): ?>
            <div class="check">
                <span class="label"><?= $name ?></span>
                <span class="status <?= $ok ? 'pass' : 'fail' ?>">
                    <?= $ok ? '✅ 通过' : '❌ 未满足' ?>
                </span>
            </div>
            <?php endforeach; ?>
            <?php foreach ($optionals as $name => $ok): ?>
            <div class="check">
                <span class="label"><?= $name ?> <span class="text-muted">(HustOJ需要)</span></span>
                <span class="status <?= $ok ? 'pass' : 'fail' ?>">
                    <?= $ok ? '✅ 可用' : '⚠️ 未安装' ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if ($allOk): ?>
            <p>💡 安装后：注册 → 首位用户自动管理员 → 后台配置 HustOJ → 同步题目。</p>
            <a href="?step=run" class="btn">开始安装</a>
        <?php else: ?>
            <div class="error">请先满足所有环境要求后再安装。</div>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
