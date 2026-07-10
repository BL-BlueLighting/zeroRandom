<?php
/**
 * zero Random - Gacha Engine
 *
 * Random pull mechanics for obtaining stocks via gacha draws.
 * Supports single, 10-pull, and 100-pull with guaranteed drops.
 *
 * Rarity is determined by submission count (heat-based):
 *   Lowest submissions = Legendary (bottom 5%)
 *   5-15% = Epic
 *   15-40% = Rare
 *   Top 60% = Common
 *
 * Pulls:
 *   Single: GACHA_SINGLE_COST tokens
 *   10-pull: GACHA_MULTI_COST tokens (guaranteed Rare+ on 10th)
 *   100-pull: GACHA_HUNDRED_COST tokens (guaranteed Epic; every 3rd 100-pull = Legendary)
 */

class GachaEngine {

    /** Default rarity weights (can be overridden by admin) */
    const DEFAULT_RARITY_WEIGHTS = [
        'common'    => 60,
        'rare'      => 25,
        'epic'      => 10,
        'legendary' => 5,
    ];

    const RARITY_WEIGHTS_10PULL_GUARANTEE = [
        'rare'      => 60,
        'epic'      => 28,
        'legendary' => 12,
    ];

    const RARITY_WEIGHTS_100PULL_GUARANTEE = [
        'epic'      => 70,
        'legendary' => 30,
    ];

    /** Rarity color mapping */
    const RARITY_COLORS = [
        'common'    => '#9ca3af',
        'rare'      => '#4da6ff',
        'epic'      => '#a855f7',
        'legendary' => '#f59e0b',
    ];

    /** Rarity display names */
    const RARITY_NAMES = [
        'common'    => '普通',
        'rare'      => '稀有',
        'epic'      => '史诗',
        'legendary' => '传说',
    ];

    // ─── Kaleidoscope Rarity System ───

    /** Kaleidoscope rarity weights */
    const KS_RARITY_WEIGHTS = [
        'deepseek' => 2.5,  // 传说级
        'gemini'   => 5,    // 史诗级
        'gpt'      => 10,   // 稀有级
        'claude'   => 15,   // 普通级
        'MiMo'     => 10,
        'doubao seed' => 40,
        'gemma'    => 2.5,
        'GLM'      => 0.1,
        'LongCat'  => 5,
        'Qwen'     => 4,
        'Hunyuan'  => 4,
        'MiniMax'  => 1.9,
    ];

    const KS_RARITY_COLORS = [
        'deepseek' => '#f59e0b',
        'gemini'   => '#a855f7',
        'gpt'      => '#4da6ff',
        'claude'   => '#9ca3af',
        'MiMo'     => '#2ecc71',
        'doubao seed' => '#1a1a1a',
        'gemma'    => '#00bcd4',
        'GLM'      => '#ffffff',
        'LongCat'  => 'linear-gradient(135deg, #fff, #2ecc71)',
        'Qwen'     => 'linear-gradient(135deg, #a855f7, #fff)',
        'Hunyuan'  => 'linear-gradient(135deg, #2ecc71, #fff)',
        'MiniMax'  => 'linear-gradient(135deg, #e74c3c, #fff)',
    ];

    const KS_RARITY_NAMES = [
        'deepseek' => 'DeepSeek',
        'gemini'   => 'Gemini',
        'gpt'      => 'GPT',
        'claude'   => 'Claude',
        'MiMo'     => 'MiMo',
        'doubao seed' => 'Seed',
        'gemma'    => 'Gemma',
        'GLM'      => 'GLM',
        'LongCat'  => 'LongCat',
        'Qwen'     => 'Qwen',
        'Hunyuan'  => 'Hunyuan',
        'MiniMax'  => 'MiniMax',
    ];

    const KS_RARITY_ORDER = ['deepseek', 'gemini', 'gpt', 'claude', 'MiMo', 'doubao seed', 'gemma', 'GLM', 'LongCat', 'Qwen', 'Hunyuan', 'MiniMax'];

    /** Kaleidoscope price change logic */
    public static function ksPriceChange(string $rarity): array {
        $prob = (float)(self::KS_RARITY_WEIGHTS[$rarity] ?? 10);
        if ($prob >= 15) {
            $upChance = max(1, 50 - $prob);
            $upAmount = max(0.5, 15 - $prob);
        } else {
            $upChance = max(1, 100 - $prob);
            $upAmount = max(0.5, 35 - $prob);
        }
        if ($upAmount <= 0) $upAmount = mt_rand(15, 60) / 10;
        $up = mt_rand(1, 100) <= $upChance;
        return ['up' => $up, 'pct' => round($upAmount, 1)];
    }

    public static function rarityNames(): array { return is_kaleidoscope() ? self::KS_RARITY_NAMES : self::RARITY_NAMES; }
    public static function rarityColors(): array { return is_kaleidoscope() ? self::KS_RARITY_COLORS : self::RARITY_COLORS; }
    public static function rarityOrder(): array { return is_kaleidoscope() ? self::KS_RARITY_ORDER : self::RARITY_ORDER; }

    /** Rarity order for admin display: 普通 > 稀有 > 史诗 > 传说 */
    const RARITY_ORDER = ['common', 'rare', 'epic', 'legendary'];

    /**
     * Get current rarity weights (possibly overridden by admin).
     */
    public static function getRarityWeights(): array {
        if (is_kaleidoscope()) return self::KS_RARITY_WEIGHTS;
        $db = Database::getInstance();
        try {
            $stmt = $db->prepare("SELECT rarity, weight FROM gacha_config WHERE date = ?");
            $stmt->execute([date('Y-m-d')]);
            $configs = $stmt->fetchAll();
            if (!empty($configs)) {
                $weights = [];
                foreach ($configs as $c) {
                    $weights[$c['rarity']] = (int)$c['weight'];
                }
                foreach (self::rarityOrder() as $r) {
                    if (!isset($weights[$r])) $weights[$r] = self::DEFAULT_RARITY_WEIGHTS[$r] ?? 10;
                }
                return $weights;
            }
        } catch (Exception $e) {}
        return self::DEFAULT_RARITY_WEIGHTS;
    }

    /**
     * Set rarity weights for today (admin override).
     */
    public static function setRarityWeights(array $weights): void {
        $db = Database::getInstance();
        $date = date('Y-m-d');
        $stmt = $db->prepare("
            INSERT INTO gacha_config (date, rarity, weight)
            VALUES (?, ?, ?)
            ON CONFLICT(date, rarity) DO UPDATE SET weight = excluded.weight
        ");
        foreach ($weights as $rarity => $weight) {
            $stmt->execute([$date, $rarity, (int)$weight]);
        }
    }

    /**
     * Randomize rarity weights for today (admin action).
     */
    public static function randomizeWeights(): array {
        // Generate random weights that still follow the rarity hierarchy
        // 普通 > 稀有 > 史诗 > 传说 (common highest, legendary lowest)
        $l = rand(1, 5);       // legendary: 1-5
        $e = rand(5, 12);      // epic: 5-12
        $r = rand(15, 30);     // rare: 15-30
        $c = 100 - $l - $e - $r; // common: rest

        $weights = [
            'legendary' => $l,
            'epic' => $e,
            'rare' => $r,
            'common' => $c,
        ];
        self::setRarityWeights($weights);
        return $weights;
    }

    /**
     * Perform a gacha pull.
     */
    public static function pull(int $userId, string $type = 'single'): array {
        switch ($type) {
            case 'multi':   $count = GACHA_MULTI_COUNT;   $cost = GACHA_MULTI_COST;   break;
            case 'hundred': $count = GACHA_HUNDRED_COUNT; $cost = GACHA_HUNDRED_COST; break;
            default:        $count = 1;                   $cost = GACHA_SINGLE_COST;  break;
        }

        // Check balance
        if (!TokenSystem::canAfford($userId, $cost)) {
            return [
                'success' => false,
                'message' => '代币不足！需要 ' . $cost . ' 枚代币。',
                'results' => [],
            ];
        }

        // Check 100-pull pity counter
        $pity100Count = 0;
        $guaranteedLegendary = false;
        if ($type === 'hundred') {
            $db = Database::getInstance();
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM " . ks_table("gacha_logs") . "
                WHERE user_id = ? AND pull_type = 'hundred'
            ");
            $stmt->execute([$userId]);
            $pity100Count = (int)$stmt->fetchColumn();
            // Every 3rd 100-pull guarantees legendary
            if (($pity100Count + 1) % GACHA_LEGENDARY_PITY_100 === 0) {
                $guaranteedLegendary = true;
            }
        }

        // Deduct tokens
        if (!TokenSystem::spend($userId, $cost, 'gacha_pull')) {
            return [
                'success' => false,
                'message' => '代币扣除失败，请重试。',
                'results' => [],
            ];
        }

        // Execute pulls
        $results = [];
        $db = Database::getInstance();

        for ($i = 0; $i < $count; $i++) {
            $rarity = null;
            $forceLegendary = $guaranteedLegendary && ($i === $count - 1);

            if ($forceLegendary) {
                $rarity = 'legendary';
            } elseif ($type === 'hundred' && $i === $count - 1) {
                // 100-pull: guarantee epic on last pull
                $weights = self::RARITY_WEIGHTS_100PULL_GUARANTEE;
                $rarity = self::rollFromWeights($weights);
            } elseif ($type === 'multi' && $i === $count - 1) {
                // 10-pull: guarantee rare+ on last pull
                $weights = self::RARITY_WEIGHTS_10PULL_GUARANTEE;
                $rarity = self::rollFromWeights($weights);
            } else {
                $weights = self::getRarityWeights();
                $rarity = self::rollFromWeights($weights);
            }

            $stock = self::selectStockByRarity($rarity);
            if (!$stock) $stock = self::selectStockByRarity('common');
            if (!$stock) continue;

            $stockId = (int)$stock['id'];
            self::creditHolding($userId, $stockId);

            // Log the pull
            $logStmt = $db->prepare("
                INSERT INTO " . ks_table("gacha_logs") . " (user_id, stock_id, rarity, pull_type, cost)
                VALUES (?, ?, ?, ?, ?)
            ");
            $perItemCost = round($cost / $count, 2);
            $logStmt->execute([$userId, $stockId, $rarity, $type, $perItemCost]);

            // Log as transaction
            $txStmt = $db->prepare("
                INSERT INTO " . ks_table("transactions") . " (user_id, stock_id, type, quantity, price, total_amount, notes)
                VALUES (?, ?, 'gacha_pull', 1, ?, ?, ?)
            ");
            $txStmt->execute([$userId, $stockId, (float)$stock['current_price'], -$perItemCost, "扭蛋抽卡获得 {$stock['name']}"]);

            $results[] = [
                'stock_id' => $stockId,
                'symbol' => $stock['symbol'],
                'name' => $stock['name'],
                'rarity' => $rarity,
                'rarity_name' => self::rarityNames()[$rarity],
                'rarity_color' => self::rarityColors()[$rarity],
                'price' => (float)$stock['current_price'],
                'category' => $stock['category'],
            ];
        }

        // Calculate pity info
        $pityMessage = '';
        if ($type === 'hundred') {
            $newPityCount = $pity100Count + 1;
            $untilNextLegendary = GACHA_LEGENDARY_PITY_100 - ($newPityCount % GACHA_LEGENDARY_PITY_100);
            if ($untilNextLegendary === GACHA_LEGENDARY_PITY_100) $untilNextLegendary = 0;
            if ($guaranteedLegendary) {
                $pityMessage = "保底触发！获得传说卡片！";
            } elseif ($untilNextLegendary > 0) {
                $pityMessage = "距离传说保底还需 {$untilNextLegendary} 次百连抽";
            }
        }

        return [
            'success' => true,
            'message' => $pityMessage ?: (
                $type === 'hundred' ? "百连抽完成！获得 {$count} 张卡片。" :
                ($type === 'multi' ? "十连抽完成！获得 {$count} 张卡片。" : "抽卡完成！")
            ),
            'cost' => $cost,
            'results' => $results,
            'balance_remaining' => TokenSystem::getBalance($userId),
            'pity_message' => $pityMessage,
            'guaranteed_legendary' => $guaranteedLegendary,
            'hundred_pull_count' => $type === 'hundred' ? ($pity100Count + 1) : null,
        ];
    }

    /**
     * Roll rarity from a set of weights.
     */
    public static function rollFromWeights(array $weights): string {
        $total = array_sum($weights);
        if ($total <= 0) return 'common';
        $roll = mt_rand(1, $total);
        $cumulative = 0;
        foreach ($weights as $rarity => $weight) {
            $cumulative += $weight;
            if ($roll <= $cumulative) return $rarity;
        }
        return 'common';
    }

    /**
     * Select a stock by rarity tier.
     * Heat-based: picks from stocks in that rarity tier based on submit count.
     */
    public static function selectStockByRarity(string $rarity): ?array {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT * FROM stocks
            WHERE rarity = ? AND is_active = 1
            ORDER BY RANDOM()
            LIMIT 1
        ");
        $stmt->execute([$rarity]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Credit a stock to user's holdings.
     */
    public static function creditHolding(int $userId, int $stockId): void {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM " . ks_table("holdings") . " WHERE user_id = ? AND stock_id = ?");
        $stmt->execute([$userId, $stockId]);
        $holding = $stmt->fetch();

        if ($holding) {
            $newQty = (int)$holding['quantity'] + 1;
            $stmt = $db->prepare("UPDATE " . ks_table("holdings") . " SET quantity = ? WHERE id = ?");
            $stmt->execute([$newQty, $holding['id']]);
        } else {
            $stmt = $db->prepare("INSERT INTO " . ks_table("holdings") . " (user_id, stock_id, quantity, avg_cost) VALUES (?, ?, 1, 0)");
            $stmt->execute([$userId, $stockId]);
        }
    }

    /**
     * Recalculate rarity for all stocks based on submission count (heat-based).
     * Lowest submitters = legendary, highest = common.
     * Run this after syncing.
     */
    public static function recalculateRarityByHeat(): void {
        $db = Database::getInstance();

        // Get all stocks ordered by submit_count from metadata
        $stocks = $db->query("
            SELECT id, metadata FROM stocks WHERE is_active = 1
        ")->fetchAll();

        // Extract submit counts
        $items = [];
        foreach ($stocks as $s) {
            $meta = json_decode($s['metadata'] ?? '{}', true) ?: [];
            $submitCount = (int)($meta['submit_count'] ?? 0);
            $items[] = ['id' => $s['id'], 'submit_count' => $submitCount];
        }

        // Sort by submit_count ASC (lowest first = most legendary)
        usort($items, fn($a, $b) => $a['submit_count'] <=> $b['submit_count']);

        $total = count($items);
        if ($total === 0) return;

        $updateStmt = $db->prepare("UPDATE stocks SET rarity = ? WHERE id = ?");

        foreach ($items as $rank => $item) {
            $percentile = (($rank + 1) / $total) * 100;

            if ($percentile <= RARITY_LEGENDARY_PCT) {
                $rarity = 'legendary';  // Lowest submissions
            } elseif ($percentile <= RARITY_EPIC_PCT) {
                $rarity = 'epic';
            } elseif ($percentile <= RARITY_RARE_PCT) {
                $rarity = 'rare';
            } else {
                $rarity = 'common';     // Highest submissions
            }

            $updateStmt->execute([$rarity, $item['id']]);
        }
    }

    /**
     * Get pull history.
     */
    public static function getPullHistory(int $userId, int $limit = 30): array {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT g.*, s.symbol, s.name as stock_name, s.category
            FROM " . ks_table("gacha_logs") . " g
            JOIN stocks s ON g.stock_id = s.id
            WHERE g.user_id = ?
            ORDER BY g.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }

    /**
     * Get pull statistics.
     */
    public static function getPullStats(int $userId): array {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT
                COUNT(*) as total_pulls,
                SUM(CASE WHEN rarity = 'legendary' THEN 1 ELSE 0 END) as legendary_count,
                SUM(CASE WHEN rarity = 'epic' THEN 1 ELSE 0 END) as epic_count,
                SUM(CASE WHEN rarity = 'rare' THEN 1 ELSE 0 END) as rare_count,
                SUM(CASE WHEN rarity = 'common' THEN 1 ELSE 0 END) as common_count,
                SUM(CASE WHEN pull_type = 'multi' THEN 1 ELSE 0 END) as multi_count,
                SUM(CASE WHEN pull_type = 'hundred' THEN 1 ELSE 0 END) as hundred_count,
                SUM(cost) as total_spent
            FROM " . ks_table("gacha_logs") . "
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $stats = $stmt->fetch();

        if (!$stats || $stats['total_pulls'] == 0) {
            return [
                'total_pulls' => 0, 'legendary_count' => 0, 'epic_count' => 0,
                'rare_count' => 0, 'common_count' => 0, 'multi_count' => 0,
                'hundred_count' => 0, 'total_spent' => 0,
            ];
        }

        $total = max((int)$stats['total_pulls'], 1);
        return [
            'total_pulls' => (int)$stats['total_pulls'],
            'legendary_count' => (int)$stats['legendary_count'],
            'epic_count' => (int)$stats['epic_count'],
            'rare_count' => (int)$stats['rare_count'],
            'common_count' => (int)$stats['common_count'],
            'multi_count' => (int)$stats['multi_count'],
            'hundred_count' => (int)$stats['hundred_count'],
            'total_spent' => (float)$stats['total_spent'],
            'legendary_pct' => round($stats['legendary_count'] / $total * 100, 1),
            'epic_pct' => round($stats['epic_count'] / $total * 100, 1),
            'rare_pct' => round($stats['rare_count'] / $total * 100, 1),
            'common_pct' => round($stats['common_count'] / $total * 100, 1),
        ];
    }

    /**
     * Get collection of unique stocks.
     */
    public static function getCollection(int $userId): array {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT DISTINCT s.*, h.quantity
            FROM " . ks_table("holdings") . " h
            JOIN stocks s ON h.stock_id = s.id
            WHERE h.user_id = ? AND h.quantity > 0
            ORDER BY s.rarity DESC, s.current_price DESC
        ");
        $stmt->execute([$userId]);
        $stocks = $stmt->fetchAll();
        foreach ($stocks as &$s) {
            $s['metadata'] = json_decode($s['metadata'] ?? '{}', true) ?: [];
        }
        return $stocks;
    }

    /**
     * Pull from a specific card pool (with pity system).
     */
    public static function pullFromPool(int $userId, string $type, int $poolId): array {
        switch ($type) {
            case 'multi':   $count = GACHA_MULTI_COUNT;   $cost = GACHA_MULTI_COST;   break;
            case 'hundred': $count = GACHA_HUNDRED_COUNT; $cost = GACHA_HUNDRED_COST; break;
            default:        $count = 1;                   $cost = GACHA_SINGLE_COST;  break;
        }
        if (!TokenSystem::canAfford($userId, $cost)) {
            return ['success' => false, 'message' => '代币不足！需要 ' . $cost . ' 枚代币。', 'results' => []];
        }
        if (!TokenSystem::spend($userId, $cost, 'gacha_pull')) {
            return ['success' => false, 'message' => '代币扣除失败。', 'results' => []];
        }

        // Check pity counter for 100-pull
        $db = Database::getInstance();
        $guaranteedLegendary = false;
        $pity100Count = 0;
        if ($type === 'hundred') {
            $pity100Count = (int)$db->query("SELECT COUNT(*) FROM " . ks_table("gacha_logs") . " WHERE user_id = {$userId} AND pull_type = 'hundred'")->fetchColumn();
            if (($pity100Count + 1) % GACHA_LEGENDARY_PITY_100 === 0) {
                $guaranteedLegendary = true;
            }
        }

        $results = [];
        $poolStockIds = PoolEngine::getPoolStockIds($poolId);
        // Check if pool is limited
        $pool = PoolEngine::getPool($poolId);
        $isLimited = $pool && !empty($pool['is_limited']);

        for ($i = 0; $i < $count; $i++) {
            // Determine target rarity with pity
            $rarity = null;
            $forceLegendary = $guaranteedLegendary && ($i === $count - 1);

            if ($forceLegendary) {
                $rarity = 'legendary';
            } elseif ($type === 'hundred' && $i === $count - 1) {
                // 100-pull: guarantee epic on last pull
                $weights = self::RARITY_WEIGHTS_100PULL_GUARANTEE;
                $rarity = self::rollFromWeights($weights);
            } elseif ($type === 'multi' && $i === $count - 1) {
                // 10-pull: guarantee rare+ on last pull
                $weights = self::RARITY_WEIGHTS_10PULL_GUARANTEE;
                $rarity = self::rollFromWeights($weights);
            } else {
                $weights = self::getRarityWeights();
                $rarity = self::rollFromWeights($weights);
            }

            // Limited pool: only 35% chance to actually draw
            if ($isLimited && !$forceLegendary) {
                if (mt_rand(1, 100) > 5) {
                    $rarityName = self::rarityNames()[$rarity] ?? $rarity;
                    $results[] = [
                        'id' => 0, 'symbol' => '💨', 'name' => "Punlucky 没有抽中 [{$rarityName}]",
                        'rarity' => $rarity, 'rarity_name' => '未中', 'rarity_color' => '#666',
                        'price' => 0, 'punlucky' => true,
                    ];
                    continue;
                }
            }

            // Get a stock of the target rarity from the pool
            $stock = self::selectStockByRarityFromPool($rarity, $poolStockIds);
            if (!$stock) {
                // Fallback: any stock from pool
                $stock = PoolEngine::getRandomStockFromPool($poolId);
            }
            if (!$stock) continue;

            $stockId = (int)$stock['id'];
            self::creditHolding($userId, $stockId);
            $perItemCost = round($cost / $count, 2);
            $db->prepare("INSERT INTO " . ks_table("gacha_logs") . " (user_id, stock_id, rarity, pull_type, cost) VALUES (?, ?, ?, ?, ?)")
                ->execute([$userId, $stockId, $rarity, $type, $perItemCost]);
            $results[] = [
                'id' => $stockId,
                'symbol' => $stock['symbol'] ?? '?',
                'name' => $stock['name'] ?? '?',
                'rarity' => $rarity,
                'rarity_name' => self::rarityNames()[$rarity] ?? 'Common',
                'rarity_color' => self::rarityColors()[$rarity] ?? '#aaa',
                'price' => (float)($stock['current_price'] ?? 0),
            ];
        }

        $newBalance = TokenSystem::getBalance($userId);
        $pityMessage = null;
        if ($guaranteedLegendary) {
            $pityMessage = '🌟 百连抽保底触发！获得传说卡牌！';
        }

        return [
            'success' => !empty($results),
            'message' => !empty($results) ? "抽卡成功！获得 " . count($results) . " 张卡牌。" : '未获得任何卡牌',
            'results' => $results,
            'balance_remaining' => $newBalance,
            'pity_message' => $pityMessage,
        ];
    }

    /**
     * Select a random stock of a specific rarity from a pool.
     */
    private static function selectStockByRarityFromPool(string $rarity, array $poolStockIds): ?array {
        if (empty($poolStockIds)) return null;
        $ids = implode(',', array_map('intval', $poolStockIds));
        $db = Database::getInstance();
        // Try exact rarity first
        $stmt = $db->query("SELECT * FROM stocks WHERE id IN ({$ids}) AND rarity = '{$rarity}' AND is_active = 1");
        $candidates = $stmt->fetchAll();
        if (!empty($candidates)) {
            return $candidates[array_rand($candidates)];
        }
        // Fallback: try lower rarities
        $order = ['legendary', 'epic', 'rare', 'common'];
        foreach ($order as $r) {
            if ($r === $rarity) continue;
            $stmt = $db->query("SELECT * FROM stocks WHERE id IN ({$ids}) AND rarity = '{$r}' AND is_active = 1");
            $candidates = $stmt->fetchAll();
            if (!empty($candidates)) return $candidates[array_rand($candidates)];
        }
        return null;
    }
}
