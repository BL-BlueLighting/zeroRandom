<?php
/**
 * OIManka - Database Layer
 *
 * SQLite PDO wrapper with migration support.
 */

class Database {
    private static ?PDO $instance = null;
    private static string $dbPath = '';

    /**
     * Get the PDO connection instance.
     */
    public static function getInstance(): PDO {
        if (self::$instance === null) {
            self::$dbPath = defined('DB_PATH') ? DB_PATH : __DIR__ . '/../data/oimanka.db';

            $dataDir = dirname(self::$dbPath);
            if (!is_dir($dataDir)) {
                mkdir($dataDir, 0755, true);
            }

            self::$instance = new PDO(
                'sqlite:' . self::$dbPath,
                null,
                null,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );

            // Enable WAL mode for better concurrent access
            self::$instance->exec('PRAGMA journal_mode=WAL');
            self::$instance->exec('PRAGMA foreign_keys=ON');
            self::$instance->exec('PRAGMA busy_timeout=5000');
        }

        return self::$instance;
    }

    /**
     * Run all migrations to create/update tables.
     */
    public static function migrate(): array {
        $db = self::getInstance();
        $results = [];

        $migrations = [
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
            'bind_verifications' => "
                CREATE TABLE IF NOT EXISTS bind_verifications (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    oj_user_id TEXT NOT NULL,
                    code TEXT NOT NULL,
                    code_md5 TEXT NOT NULL,
                    verified INTEGER DEFAULT 0,
                    expires_at DATETIME,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id)
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
        ];

        foreach ($migrations as $table => $sql) {
            try {
                $db->exec($sql);
                $results[$table] = 'ok';
            } catch (PDOException $e) {
                $results[$table] = 'error: ' . $e->getMessage();
            }
        }

        // New tables
        $newTables = [
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
        ];
        foreach ($newTables as $table => $sql) {
            try {
                $db->exec($sql);
                $results[$table] = 'ok';
            } catch (PDOException $e) {
                $results[$table] = 'error: ' . $e->getMessage();
            }
        }

        // Column migrations (add if not exist — SQLite ignores duplicate column errors with try/catch)
        $columnMigrations = [
            "ALTER TABLE card_pools ADD COLUMN is_limited INTEGER DEFAULT 0",
            "ALTER TABLE card_pools ADD COLUMN expires_at DATETIME",
            "ALTER TABLE stocks ADD COLUMN limited_edition INTEGER DEFAULT 0",
            "ALTER TABLE notifications ADD COLUMN reward_tokens REAL DEFAULT 0",
            "ALTER TABLE notifications ADD COLUMN reward_stock_id INTEGER DEFAULT 0",
            "ALTER TABLE notifications ADD COLUMN reward_stock_quantity INTEGER DEFAULT 0",
            "ALTER TABLE users ADD COLUMN last_active_at DATETIME",
        ];
        foreach ($columnMigrations as $sql) {
            try {
                $db->exec($sql);
            } catch (PDOException $e) {
                // Column already exists — ignore
            }
        }

        // New tables
        $newTables = [
            'claimed_rewards' => "
                CREATE TABLE IF NOT EXISTS claimed_rewards (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    notification_id INTEGER NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id),
                    FOREIGN KEY (notification_id) REFERENCES notifications(id),
                    UNIQUE(user_id, notification_id)
                )
            ",
        ];
        foreach ($newTables as $table => $sql) {
            try {
                $db->exec($sql);
                $results[$table] = 'ok';
            } catch (PDOException $e) {
                $results[$table] = 'error: ' . $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Check if the database is initialized.
     */
    public static function isInitialized(): bool {
        try {
            $db = self::getInstance();
            $db->query("SELECT 1 FROM users LIMIT 1");
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Reset the database (for development).
     */
    public static function reset(): void {
        $path = defined('DB_PATH') ? DB_PATH : __DIR__ . '/../data/oimanka.db';
        if (file_exists($path)) {
            unlink($path);
        }
        self::$instance = null;
    }
}
