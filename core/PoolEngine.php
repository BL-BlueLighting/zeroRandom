<?php
/**
 * OIManka - Card Pool Engine
 *
 * Manages gacha card pools: create pools, assign stocks, pull from pools.
 */
class PoolEngine {

    /**
     * Get all pools.
     */
    public static function getAllPools(): array {
        $db = Database::getInstance();
        return $db->query("SELECT * FROM card_pools ORDER BY sort_order ASC, id ASC")->fetchAll();
    }

    /**
     * Get a pool by ID.
     */
    public static function getPool(int $poolId): ?array {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM card_pools WHERE id = ?");
        $stmt->execute([$poolId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Get stocks in a pool.
     */
    public static function getPoolStocks(int $poolId): array {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT s.* FROM card_pool_items cpi
            JOIN stocks s ON cpi.stock_id = s.id
            WHERE cpi.pool_id = ?
            ORDER BY s.symbol ASC
        ");
        $stmt->execute([$poolId]);
        return $stmt->fetchAll();
    }

    /**
     * Get stock IDs in a pool.
     */
    public static function getPoolStockIds(int $poolId): array {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT stock_id FROM card_pool_items WHERE pool_id = ?");
        $stmt->execute([$poolId]);
        return array_column($stmt->fetchAll(), 'stock_id');
    }

    /**
     * Create a pool.
     */
    public static function createPool(string $name, array $stockIds = []): int {
        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("INSERT INTO card_pools (name) VALUES (?)");
            $stmt->execute([$name]);
            $poolId = (int)$db->lastInsertId();

            if (!empty($stockIds)) {
                $insert = $db->prepare("INSERT INTO card_pool_items (pool_id, stock_id) VALUES (?, ?)");
                foreach ($stockIds as $sid) {
                    $insert->execute([$poolId, (int)$sid]);
                }
            }
            $db->commit();
            return $poolId;
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Update pool name.
     */
    public static function updatePool(int $poolId, string $name): void {
        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE card_pools SET name = ? WHERE id = ?");
        $stmt->execute([$name, $poolId]);
    }

    /**
     * Delete a pool.
     */
    public static function deletePool(int $poolId): void {
        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            $db->prepare("DELETE FROM card_pool_items WHERE pool_id = ?")->execute([$poolId]);
            $db->prepare("DELETE FROM card_pools WHERE id = ?")->execute([$poolId]);
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
        }
    }

    /**
     * Set stocks for a pool (replaces all).
     */
    public static function setPoolStocks(int $poolId, array $stockIds): void {
        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            $db->prepare("DELETE FROM card_pool_items WHERE pool_id = ?")->execute([$poolId]);
            $insert = $db->prepare("INSERT INTO card_pool_items (pool_id, stock_id) VALUES (?, ?)");
            foreach ($stockIds as $sid) {
                $insert->execute([$poolId, (int)$sid]);
            }
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Split a pool into two at a given stock index.
     * Stocks [0..splitIndex] stay in pool A, [splitIndex+1..] go to new pool B.
     */
    public static function splitPool(int $poolId, string $newPoolName, int $splitIndex): int {
        $stockIds = self::getPoolStockIds($poolId);
        if ($splitIndex < 0 || $splitIndex >= count($stockIds) - 1) {
            throw new RuntimeException('分割位置无效');
        }

        $keepIds = array_slice($stockIds, 0, $splitIndex + 1);
        $moveIds = array_slice($stockIds, $splitIndex + 1);

        $pool = self::getPool($poolId);
        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            // Create new pool
            $stmt = $db->prepare("INSERT INTO card_pools (name, sort_order) VALUES (?, ?)");
            $stmt->execute([$newPoolName, ($pool['sort_order'] ?? 0) + 1]);
            $newPoolId = (int)$db->lastInsertId();

            // Move stocks to new pool
            $insert = $db->prepare("INSERT INTO card_pool_items (pool_id, stock_id) VALUES (?, ?)");
            foreach ($moveIds as $sid) {
                $insert->execute([$newPoolId, $sid]);
            }

            // Update original pool stocks
            $db->prepare("DELETE FROM card_pool_items WHERE pool_id = ?")->execute([$poolId]);
            foreach ($keepIds as $sid) {
                $db->prepare("INSERT INTO card_pool_items (pool_id, stock_id) VALUES (?, ?)")->execute([$poolId, $sid]);
            }

            $db->commit();
            return $newPoolId;
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Get a random stock from a pool for gacha pulls.
     * Returns null if pool empty.
     */
    public static function getRandomStockFromPool(int $poolId): ?array {
        $stocks = self::getPoolStocks($poolId);
        if (empty($stocks)) return null;
        // Weighted by rarity heat (lower submit = rarer = lower weight)
        $weights = [];
        foreach ($stocks as $s) {
            $rarity = $s['rarity'] ?? 'common';
            $rarityWeights = GachaEngine::getRarityWeights();
            $weights[] = $rarityWeights[$rarity] ?? 10;
        }
        $total = array_sum($weights);
        $rand = mt_rand(1, max(1, $total));
        $cum = 0;
        foreach ($stocks as $i => $s) {
            $cum += $weights[$i];
            if ($rand <= $cum) return $s;
        }
        return $stocks[0] ?? null;
    }

    /**
     * Ensure default pool exists.
     * Creates "常驻卡池" with all stocks if no pools exist.
     */
    public static function ensureDefaultPool(): void {
        $pools = self::getAllPools();
        if (!empty($pools)) return;

        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO card_pools (name, is_default, sort_order) VALUES ('常驻卡池', 1, 0)");
        $stmt->execute();
        $poolId = (int)$db->lastInsertId();

        // Add all active stocks
        $stocks = $db->query("SELECT id FROM stocks WHERE is_active = 1")->fetchAll();
        $insert = $db->prepare("INSERT INTO card_pool_items (pool_id, stock_id) VALUES (?, ?)");
        foreach ($stocks as $s) {
            $insert->execute([$poolId, (int)$s['id']]);
        }
    }

    // ─── Limited Edition Pool Support ───

    /**
     * Mark a pool as limited edition with an expiry date.
     */
    public static function setLimited(int $poolId, string $expiresAt): void {
        $db = Database::getInstance();
        $db->prepare("UPDATE card_pools SET is_limited = 1, expires_at = ? WHERE id = ?")
            ->execute([$expiresAt, $poolId]);
    }

    /**
     * Remove limited status from a pool.
     */
    public static function unsetLimited(int $poolId): void {
        $db = Database::getInstance();
        $db->prepare("UPDATE card_pools SET is_limited = 0, expires_at = NULL WHERE id = ?")
            ->execute([$poolId]);
        // Clear limited_edition from stocks in this pool (only if not also in another expired pool)
        $db->exec("
            UPDATE stocks SET limited_edition = 0 WHERE id IN (
                SELECT cpi.stock_id FROM card_pool_items cpi
                WHERE cpi.pool_id = {$poolId}
            ) AND id NOT IN (
                SELECT cpi2.stock_id FROM card_pool_items cpi2
                JOIN card_pools cp2 ON cpi2.pool_id = cp2.id
                WHERE cp2.is_limited = 1 AND cp2.expires_at IS NOT NULL AND cp2.expires_at <= datetime('now')
            )
        ");
    }

    /**
     * Sync limited_edition flag on stocks based on pool expiry.
     * Stocks in expired limited pools get limited_edition = 1.
     * Does NOT clear manually set limited_edition from admin tool.
     */
    public static function syncLimitedEdition(): void {
        $db = Database::getInstance();
        $db->exec("
            UPDATE stocks SET limited_edition = 1 WHERE id IN (
                SELECT cpi.stock_id FROM card_pool_items cpi
                JOIN card_pools cp ON cpi.pool_id = cp.id
                WHERE cp.is_limited = 1 AND cp.expires_at IS NOT NULL AND cp.expires_at <= datetime('now')
            )
        ");
    }

    /**
     * Check if a stock is limited edition.
     */
    public static function isLimitedEdition(int $stockId): bool {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT limited_edition FROM stocks WHERE id = ?");
        $stmt->execute([$stockId]);
        return (bool)$stmt->fetchColumn();
    }
}
