<?php
/**
 * zero Random - Configuration
 */

define('APP_NAME', 'zero Random');
define('APP_URL', 'http://localhost:8000');
define('APP_VERSION', '1.0.0');

// Database - SQLite
define('DB_PATH', __DIR__ . '/data/oimanka.db');

// Session
define('SESSION_LIFETIME', 86400);

// Gacha settings
define('GACHA_SINGLE_COST', 10);
define('GACHA_MULTI_COST', 90);
define('GACHA_MULTI_COUNT', 10);
define('GACHA_HUNDRED_COST', 850);
define('GACHA_HUNDRED_COUNT', 100);
define('GACHA_LEGENDARY_PITY_100', 3);

// Trading
define('TRADE_FEE_PCT', 1.0);
define('PRICE_IMPACT_FACTOR', 0.001);

// Starter tokens
define('STARTER_TOKENS', 100);

// Rarity (heat-based)
define('RARITY_LEGENDARY_PCT', 5);
define('RARITY_EPIC_PCT', 15);
define('RARITY_RARE_PCT', 40);

// Token conversion: 1 AC = 10 tokens
define('TOKENS_PER_AC', 10);
define('SYNC_COOLDOWN_MINUTES', 10);

// Default adapter (stocks only come from adapters — no sample data)
define('DEFAULT_ADAPTER', 'hustoj');

// Debug
define('DEBUG_MODE', true);

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

date_default_timezone_set('Asia/Shanghai');

// Database initialization key
// If the database is not initialized, visit setup.php and enter this key to run migrations.
define('DB_INIT_KEY', 'oimankaconfigkey');

/**
 * Get a platform config value from DB.
 * Falls back to the provided default if not set.
 */
function platform_config(string $adapter, string $key, $default = null) {
    static $cache = [];
    $cacheKey = "{$adapter}:{$key}";

    if (isset($cache[$cacheKey])) return $cache[$cacheKey];

    try {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT config_value FROM platform_config WHERE adapter_name = ? AND config_key = ?");
        $stmt->execute([$adapter, $key]);
        $val = $stmt->fetchColumn();
        $cache[$cacheKey] = ($val !== false) ? $val : $default;
        return $cache[$cacheKey];
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Set a platform config value in DB.
 */
function platform_config_set(string $adapter, string $key, $value): void {
    $db = Database::getInstance();
    $stmt = $db->prepare("
        INSERT INTO platform_config (adapter_name, config_key, config_value)
        VALUES (?, ?, ?)
        ON CONFLICT(adapter_name, config_key) DO UPDATE SET config_value = excluded.config_value
    ");
    $stmt->execute([$adapter, $key, (string)$value]);
}

/**
 * Check if an adapter is configured.
 */
function platform_configured(string $adapter): bool {
    return !empty(platform_config($adapter, 'db_host'));
}

/**
 * Get OJ URL for the frontend (from whichever adapter is active).
 */
function oj_url(): string {
    $adapter = platform_configured('hustoj') ? 'hustoj' : (platform_configured('hydroj') ? 'hydroj' : null);
    if (!$adapter) return '';
    return platform_config($adapter, 'oj_url', '');
}
define('OJ_URL_FN', true); // signal that oj_url() is available

/**
 * Get the base path for URL generation.
 * Extracted from APP_URL (e.g. APP_URL = "http://noiclub.cn/zeroran" → "/zeroran").
 */
define('BASE_PATH', rtrim(parse_url(APP_URL, PHP_URL_PATH) ?? '', '/') ?: '');

/**
 * Generate a URL with the correct base path prepended.
 * Use this for all internal links and redirects.
 *
 * @param string $path Path starting with /, e.g. '/login'
 * @return string Full path with base, e.g. '/zeroran/login'
 */
function url(string $path): string {
    return BASE_PATH . '/' . ltrim($path, '/');
}
