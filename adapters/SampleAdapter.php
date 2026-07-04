<?php
/**
 * OIManka - Sample Adapter
 *
 * A built-in demo adapter that uses the local SQLite database
 * to provide stock data without needing an external platform.
 * Useful for development, testing, and demo purposes.
 */

class SampleAdapter implements AdapterInterface {

    public function getName(): string {
        return 'sample';
    }

    public function getDisplayName(): string {
        return '示例数据';
    }

    public function getDescription(): string {
        return '使用本地示例数据，无需连接外部平台。适用于开发和演示。';
    }

    public function fetchStocks(array $options = []): array {
        // Sample adapter reads from local stocks table
        $db = Database::getInstance();

        $limit = $options['limit'] ?? 200;
        $offset = $options['offset'] ?? 0;
        $category = $options['category'] ?? null;

        $where = "adapter_name = 'sample' AND is_active = 1";
        $params = [];

        if ($category) {
            $where .= ' AND category = ?';
            $params[] = $category;
        }

        $sql = "SELECT * FROM stocks WHERE {$where} LIMIT ? OFFSET ?";
        $stmt = $db->prepare($sql);
        $paramIndex = 1;
        foreach ($params as $p) {
            $stmt->bindValue($paramIndex++, $p);
        }
        $stmt->bindValue($paramIndex++, (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue($paramIndex, (int)$offset, PDO::PARAM_INT);
        $stmt->execute();

        $stocks = [];
        while ($row = $stmt->fetch()) {
            $metadata = json_decode($row['metadata'] ?? '{}', true) ?: [];

            $stocks[] = [
                'symbol' => $row['symbol'],
                'name' => $row['name'],
                'adapter_key' => $row['adapter_key'],
                'category' => $row['category'],
                'ac_count' => (int)($metadata['ac_count'] ?? 0),
                'submit_count' => (int)($metadata['submit_count'] ?? 0),
                'metadata' => $metadata,
            ];
        }

        return $stocks;
    }

    public function calculatePrice(array $stockData): float {
        $acCount = max((int)($stockData['ac_count'] ?? 0), 0);
        $submitCount = max((int)($stockData['submit_count'] ?? 1), 1);
        $acRatio = $acCount / $submitCount;

        // Simulated price: harder problems (lower AC ratio) are more expensive
        $basePrice = (float)($stockData['base_price'] ?? 10.0);
        $difficultyMultiplier = 1.0 + (1.0 - $acRatio) * 2.0;

        return round($basePrice * $difficultyMultiplier, 2);
    }

    public function calculateTokens(array $platformUserData): float {
        return (float)($platformUserData['total_ac'] ?? 0) * 5.0;
    }

    public function fetchUserData(string $platformUserId): ?array {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([(int)$platformUserId]);
        $user = $stmt->fetch();

        if (!$user) return null;

        return [
            'platform_user_id' => (string)$user['id'],
            'username' => $user['username'],
            'total_ac' => (int)($user['total_earned'] / 5), // Rough estimate
            'total_submit' => (int)($user['total_earned'] / 2),
            'metadata' => [],
        ];
    }

    public function resolveUser(string $identifier): ?array {
        $db = Database::getInstance();

        if (is_numeric($identifier)) {
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([(int)$identifier]);
        } else {
            $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$identifier]);
        }

        $user = $stmt->fetch();
        if (!$user) return null;

        return $this->fetchUserData((string)$user['id']);
    }

    public function syncStocks(): array {
        // Sample adapter doesn't need syncing — data is already in local DB
        // But we can refresh prices based on "metrics"
        $localDb = Database::getInstance();
        $startTime = date('Y-m-d H:i:s');

        try {
            $stocks = $localDb->query("
                SELECT * FROM stocks WHERE adapter_name = 'sample' AND is_active = 1
            ")->fetchAll();

            $priceStmt = $localDb->prepare("
                INSERT INTO stock_prices (stock_id, price, ac_ratio, submit_count, ac_count)
                VALUES (?, ?, ?, ?, ?)
            ");

            $updateStmt = $localDb->prepare("
                UPDATE stocks SET
                    prev_price = current_price,
                    current_price = ?,
                    price_change_pct = CASE
                        WHEN current_price > 0 THEN ROUND((? - current_price) / current_price * 100, 2)
                        ELSE 0
                    END,
                    market_cap = ROUND(? * circulating_supply, 2),
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");

            $count = 0;
            foreach ($stocks as $stock) {
                $metadata = json_decode($stock['metadata'] ?? '{}', true) ?: [];
                $acCount = (int)($metadata['ac_count'] ?? 50);
                $submitCount = (int)($metadata['submit_count'] ?? 100);

                // Simulate price fluctuation (±5%)
                $oldPrice = (float)$stock['current_price'];
                $fluctuation = 1.0 + ((rand(-50, 50)) / 1000); // ±5%
                $newPrice = round($oldPrice * $fluctuation, 2);
                $newPrice = max($newPrice, 1.0);

                $acRatio = $submitCount > 0 ? $acCount / $submitCount : 0;

                $priceStmt->execute([$stock['id'], $newPrice, round($acRatio, 4), $submitCount, $acCount]);
                $updateStmt->execute([$newPrice, $newPrice, $newPrice, $stock['id']]);
                $count++;
            }

            // Update rarity
            $this->updateRarity();

            // Log sync
            $logStmt = $localDb->prepare("
                INSERT INTO sync_logs (adapter_name, status, items_synced, started_at, finished_at)
                VALUES ('sample', 'success', ?, ?, ?)
            ");
            $logStmt->execute([$count, $startTime, date('Y-m-d H:i:s')]);

            return [
                'adapter' => 'sample',
                'status' => 'success',
                'items_synced' => $count,
            ];

        } catch (Exception $e) {
            $logStmt = $localDb->prepare("
                INSERT INTO sync_logs (adapter_name, status, items_synced, error_message, started_at, finished_at)
                VALUES ('sample', 'failed', 0, ?, ?, ?)
            ");
            $logStmt->execute([$e->getMessage(), $startTime, date('Y-m-d H:i:s')]);

            return [
                'adapter' => 'sample',
                'status' => 'failed',
                'error' => $e->getMessage(),
                'items_synced' => 0,
            ];
        }
    }

    public function syncUser(string $localUsername): bool {
        // Sample adapter: users are already local
        return true;
    }

    public function testConnection(): bool {
        return true; // Always connected (local data)
    }

    public function getConfigFields(): array {
        return []; // No config needed for sample data
    }

    private function updateRarity(): void {
        $localDb = Database::getInstance();
        $stocks = $localDb->query("
            SELECT id, current_price FROM stocks
            WHERE adapter_name = 'sample' AND is_active = 1
            ORDER BY current_price DESC
        ")->fetchAll();

        $total = count($stocks);
        if ($total === 0) return;

        $updateStmt = $localDb->prepare("UPDATE stocks SET rarity = ? WHERE id = ?");

        foreach ($stocks as $rank => $stock) {
            $percentile = (($rank + 1) / $total) * 100;

            if ($percentile <= RARITY_LEGENDARY_PCT) {
                $rarity = 'legendary';
            } elseif ($percentile <= RARITY_EPIC_PCT) {
                $rarity = 'epic';
            } elseif ($percentile <= RARITY_RARE_PCT) {
                $rarity = 'rare';
            } else {
                $rarity = 'common';
            }

            $updateStmt->execute([$rarity, $stock['id']]);
        }
    }
}
