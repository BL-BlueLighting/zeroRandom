<?php
/**
 * OIManka - Auto Job
 *
 * Handles periodic tasks triggered by user page loads:
 * 1. Sync expired limited pools (绝版卡池到期检测)
 * 2. Track user online activity
 * 3. Auto-refresh stock prices every 5 minutes if users are online
 */
class AutoJob {

    /**
     * Run all periodic checks.
     * Call this on every page load after Session::start().
     */
    public static function run(): void {
        // 1. Fix: sync expired pools on every request
        self::checkExpiredPools();

        // 2. Track user activity
        self::updateUserActivity();

        // 3. Auto-refresh stock prices if conditions met
        self::autoRefreshPrices();
    }

    /**
     * Sync limited_edition flags for expired pools.
     * Fixes the bug where expired pools don't automatically get 绝版 status.
     */
    private static function checkExpiredPools(): void {
        if (!class_exists('PoolEngine')) return;
        try {
            $db = Database::getInstance();
            // Find pools that just expired (never been synced before)
            $expired = $db->query("
                SELECT id FROM card_pools
                WHERE is_limited = 1 AND expires_at IS NOT NULL
                  AND expires_at <= datetime('now')
                  AND expires_at > datetime('now', '-1 day')
            ")->fetchAll(PDO::FETCH_COLUMN);

            PoolEngine::syncLimitedEdition();

            // Delete expired limited pools
            foreach ($expired as $pid) {
                $db->prepare("DELETE FROM card_pool_items WHERE pool_id = ?")->execute([$pid]);
                $db->prepare("DELETE FROM card_pools WHERE id = ?")->execute([$pid]);
            }
        } catch (Exception $e) {
            // Silently handle — table might not exist yet
        }
    }

    /**
     * Update user's last active timestamp for online tracking.
     */
    private static function updateUserActivity(): void {
        try {
            $db = Database::getInstance();
            $db->exec("UPDATE users SET last_active_at = datetime('now') WHERE id = " . (int)Session::userId());
        } catch (Exception $e) {
            // Silently handle
        }
    }

    /**
     * Auto-refresh stock prices if:
     * - At least 5 minutes since last auto-refresh
     * - There are online users (active within last 10 minutes)
     *
     * Atomic DB update ensures only one request actually performs the refresh.
     */
    private static function autoRefreshPrices(): void {
        try {
            $db = Database::getInstance();

            // Ensure config row exists (for the atomic UPDATE trick)
            $db->exec("INSERT OR IGNORE INTO platform_config (adapter_name, config_key, config_value)
                       VALUES ('system', 'last_auto_refresh', '2000-01-01 00:00:00')");

            // Atomically claim the refresh slot.
            // Only succeeds if last refresh was > 5 minutes ago.
            $stmt = $db->prepare("UPDATE platform_config SET config_value = datetime('now')
                                  WHERE adapter_name = 'system' AND config_key = 'last_auto_refresh'
                                  AND config_value <= datetime('now', '-5 minutes')");
            $stmt->execute();

            if ($stmt->rowCount() === 0) {
                return; // Not time yet, or another request already claimed it
            }

            // Count online users (active within last 10 minutes)
            $stmt = $db->query("SELECT COUNT(*) FROM users WHERE last_active_at > datetime('now', '-10 minutes')");
            $onlineCount = (int)$stmt->fetchColumn();

            if ($onlineCount === 0) {
                return; // No users online, skip refresh
            }

            // Perform the price refresh
            self::doRefreshPrices();
        } catch (Exception $e) {
            // Silently handle
        }
    }

    /**
     * Perform the actual stock price refresh.
     * Random fluctuation based on rarity (same logic as admin panel "刷新股价").
     */
    private static function doRefreshPrices(): void {
        $db = Database::getInstance();

        $rarityChance = ['legendary' => 100, 'epic' => 50, 'rare' => 25, 'common' => 10];
        $allStocks = $db->query("SELECT id, current_price, rarity, limited_edition FROM stocks WHERE is_active = 1")->fetchAll();
        $update = $db->prepare("UPDATE stocks SET prev_price = current_price, current_price = ROUND(?, 2), price_change_pct = ROUND((? - current_price) / current_price * 100, 2) WHERE id = ?");

        foreach ($allStocks as $s) {
            // 绝版: 100% up, 20~30%
            if (!empty($s['limited_edition'])) {
                $pct = mt_rand(20, 30);
                $newPrice = round((float)$s['current_price'] * (1 + $pct / 100), 2);
                if ($newPrice > 0) { $update->execute([$newPrice, $newPrice, $s['id']]); }
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
            }
        }
    }
}
