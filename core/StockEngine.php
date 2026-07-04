<?php
/**
 * OIManka - Stock Engine
 *
 * Handles stock price calculation, history tracking, and market queries.
 */

class StockEngine {

    /**
     * Get a single stock by ID or symbol.
     */
    public static function getStock($identifier): ?array {
        $db = Database::getInstance();

        if (is_numeric($identifier)) {
            $stmt = $db->prepare("SELECT * FROM stocks WHERE id = ?");
        } else {
            $stmt = $db->prepare("SELECT * FROM stocks WHERE symbol = ?");
        }
        $stmt->execute([$identifier]);
        $stock = $stmt->fetch();

        if (!$stock) return null;

        $stock['metadata'] = json_decode($stock['metadata'] ?? '{}', true) ?: [];
        return $stock;
    }

    /**
     * Get all active stocks, with optional filters.
     */
    public static function getStocks(array $options = []): array {
        $db = Database::getInstance();

        $limit = $options['limit'] ?? 50;
        $offset = $options['offset'] ?? 0;
        $category = $options['category'] ?? null;
        $sort = $options['sort'] ?? 'market_cap';
        $order = $options['order'] ?? 'DESC';
        $adapter = $options['adapter'] ?? null;

        $where = ['is_active = 1'];
        $params = [];

        if ($category) {
            $where[] = 'category = ?';
            $params[] = $category;
        }

        if ($adapter) {
            $where[] = 'adapter_name = ?';
            $params[] = $adapter;
        }

        $allowedSorts = ['current_price', 'price_change_pct', 'market_cap', 'name', 'rarity', 'volume_24h'];
        if (!in_array($sort, $allowedSorts)) {
            $sort = 'market_cap';
        }
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        $whereClause = implode(' AND ', $where);
        $sql = "SELECT * FROM stocks WHERE {$whereClause} ORDER BY {$sort} {$order} LIMIT ? OFFSET ?";

        $stmt = $db->prepare($sql);
        $paramIndex = 1;
        foreach ($params as $p) {
            $stmt->bindValue($paramIndex++, $p);
        }
        $stmt->bindValue($paramIndex++, (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue($paramIndex, (int)$offset, PDO::PARAM_INT);
        $stmt->execute();

        $stocks = $stmt->fetchAll();
        foreach ($stocks as &$s) {
            $s['metadata'] = json_decode($s['metadata'] ?? '{}', true) ?: [];
        }

        return $stocks;
    }

    /**
     * Get total count of active stocks.
     */
    public static function getStockCount(?string $category = null, ?string $adapter = null): int {
        $db = Database::getInstance();
        $where = ['is_active = 1'];
        $params = [];

        if ($category) {
            $where[] = 'category = ?';
            $params[] = $category;
        }
        if ($adapter) {
            $where[] = 'adapter_name = ?';
            $params[] = $adapter;
        }

        $sql = "SELECT COUNT(*) FROM stocks WHERE " . implode(' AND ', $where);
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Get all unique categories.
     */
    public static function getCategories(): array {
        $db = Database::getInstance();
        return $db->query("
            SELECT DISTINCT category FROM stocks WHERE is_active = 1 ORDER BY category
        ")->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Get price history for a stock.
     */
    public static function getPriceHistory(int $stockId, int $hours = 72): array {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT price, ac_ratio, submit_count, ac_count, recorded_at
            FROM stock_prices
            WHERE stock_id = ?
            ORDER BY recorded_at ASC
        ");
        $stmt->execute([$stockId]);

        $history = $stmt->fetchAll();

        // Sample down if too many points (> 100) for chart friendliness
        $maxPoints = 100;
        $count = count($history);
        if ($count > $maxPoints) {
            $step = ceil($count / $maxPoints);
            $sampled = [];
            for ($i = 0; $i < $count; $i += $step) {
                $sampled[] = $history[$i];
            }
            // Always include last point
            if (end($history) !== end($sampled)) {
                $sampled[] = end($history);
            }
            return $sampled;
        }

        return $history;
    }

    /**
     * Record a new price point for a stock.
     */
    public static function recordPrice(int $stockId, float $price, ?float $acRatio = null, int $submitCount = 0, int $acCount = 0): void {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            INSERT INTO stock_prices (stock_id, price, ac_ratio, submit_count, ac_count)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$stockId, $price, $acRatio, $submitCount, $acCount]);
    }

    /**
     * Update stock price (called after trades or sync).
     */
    public static function updatePrice(int $stockId, float $newPrice): void {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            UPDATE stocks SET
                prev_price = current_price,
                current_price = ?,
                price_change_pct = CASE
                    WHEN current_price > 0 THEN ROUND(((? - current_price) / current_price) * 100, 2)
                    ELSE 0
                END,
                market_cap = ROUND(? * circulating_supply, 2),
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$newPrice, $newPrice, $newPrice, $stockId]);

        // Record in history
        self::recordPrice($stockId, $newPrice);
    }

    /**
     * Get market summary statistics.
     */
    public static function getMarketSummary(): array {
        $db = Database::getInstance();

        $total = $db->query("SELECT COUNT(*) FROM stocks WHERE is_active = 1")->fetchColumn();
        $totalCap = $db->query("SELECT COALESCE(SUM(market_cap), 0) FROM stocks WHERE is_active = 1")->fetchColumn();
        $avgChange = $db->query("SELECT COALESCE(AVG(price_change_pct), 0) FROM stocks WHERE is_active = 1")->fetchColumn();
        $gainers = $db->query("SELECT COUNT(*) FROM stocks WHERE is_active = 1 AND price_change_pct > 0")->fetchColumn();
        $losers = $db->query("SELECT COUNT(*) FROM stocks WHERE is_active = 1 AND price_change_pct < 0")->fetchColumn();

        $topGainer = $db->query("SELECT symbol, name, price_change_pct FROM stocks WHERE is_active = 1 ORDER BY price_change_pct DESC LIMIT 1")->fetch();
        $topLoser = $db->query("SELECT symbol, name, price_change_pct FROM stocks WHERE is_active = 1 ORDER BY price_change_pct ASC LIMIT 1")->fetch();

        return [
            'total_stocks' => (int)$total,
            'total_market_cap' => round((float)$totalCap, 2),
            'avg_change_pct' => round((float)$avgChange, 2),
            'gainers' => (int)$gainers,
            'losers' => (int)$losers,
            'top_gainer' => $topGainer ?: null,
            'top_loser' => $topLoser ?: null,
        ];
    }

    /**
     * Apply a price impact from a trade.
     * Buying pushes price up; selling pushes price down.
     */
    public static function applyTradeImpact(int $stockId, int $quantity, string $type): float {
        $stock = self::getStock($stockId);
        if (!$stock) return 0;

        $currentPrice = (float)$stock['current_price'];
        $circulating = max((int)$stock['circulating_supply'], 1);

        // Impact is proportional to trade size relative to circulating supply
        $impactRatio = $quantity / $circulating;
        $impact = $impactRatio * PRICE_IMPACT_FACTOR * $currentPrice;

        if ($type === 'buy') {
            $newPrice = $currentPrice + $impact;
        } else {
            $newPrice = $currentPrice - $impact;
        }

        $newPrice = max($newPrice, 0.01);
        self::updatePrice($stockId, round($newPrice, 2));

        return round($newPrice, 2);
    }

    /**
     * Search stocks by name or symbol.
     */
    public static function search(string $query, int $limit = 20): array {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT * FROM stocks
            WHERE is_active = 1
            AND (name LIKE ? OR symbol LIKE ? OR category LIKE ?)
            ORDER BY market_cap DESC
            LIMIT ?
        ");
        $like = "%{$query}%";
        $stmt->execute([$like, $like, $like, $limit]);
        $results = $stmt->fetchAll();

        foreach ($results as &$s) {
            $s['metadata'] = json_decode($s['metadata'] ?? '{}', true) ?: [];
        }

        return $results;
    }
}
