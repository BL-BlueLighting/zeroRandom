<?php
/**
 * OIManka - Trading Engine
 *
 * Handles buy and sell orders for the stock market.
 * Includes fee calculation, price impact, and transaction logging.
 */

class TradingEngine {

    /**
     * Buy stocks.
     *
     * @param int $userId The buyer
     * @param int $stockId The stock to buy
     * @param int $quantity Number of shares
     * @return array Result with success/error message
     */
    public static function buy(int $userId, int $stockId, int $quantity): array {
        if ($quantity <= 0) {
            return ['success' => false, 'message' => '购买数量必须大于0。'];
        }

        $stock = StockEngine::getStock($stockId);
        if (!$stock || !$stock['is_active']) {
            return ['success' => false, 'message' => '该股票不存在或已下架。'];
        }
        if (!empty($stock['limited_edition'])) {
            // Must already hold this limited card to buy more from stock page
            $holdDb = Database::getInstance();
            $holdCheck = $holdDb->prepare("SELECT id, quantity FROM holdings WHERE user_id = ? AND stock_id = ?");
            $holdCheck->execute([$userId, $stockId]);
            $existingHold = $holdCheck->fetch();
            if (!$existingHold || (int)$existingHold['quantity'] <= 0) {
                return ['success' => false, 'message' => '未持有此绝版卡牌，请通过卡牌市场购买。'];
            }
            // 15% premium for existing holders
            $pricePerShare = round((float)$stock['current_price'] * 1.15, 2);
        } else {
            // Direct buy price: 30% premium over market price
            $pricePerShare = round((float)$stock['current_price'] * 1.3, 2);
        }
        $subtotal = $pricePerShare * $quantity;
        $fee = round($subtotal * (TRADE_FEE_PCT / 100), 2);
        $totalCost = $subtotal + $fee;

        // Check balance
        if (!TokenSystem::canAfford($userId, $totalCost)) {
            return [
                'success' => false,
                'message' => sprintf(
                    '代币不足！需要 %.2f 枚代币（含 %.2f 手续费），当前余额不足。',
                    $totalCost, $fee
                ),
            ];
        }

        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            // Deduct tokens (direct SQL, no nested transaction)
            $balStmt = $db->prepare("SELECT token_balance FROM users WHERE id = ?");
            $balStmt->execute([$userId]);
            $bal = (float)$balStmt->fetchColumn();
            if ($bal < $totalCost) throw new RuntimeException('代币不足');
            $db->prepare("UPDATE users SET token_balance = token_balance - ?, total_spent = total_spent + ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                ->execute([$totalCost, $totalCost, $userId]);

            // Update holdings
            $stmt = $db->prepare("SELECT * FROM holdings WHERE user_id = ? AND stock_id = ?");
            $stmt->execute([$userId, $stockId]);
            $holding = $stmt->fetch();

            if ($holding) {
                // Update average cost
                $oldQty = (int)$holding['quantity'];
                $oldCost = (float)$holding['avg_cost'];
                $newQty = $oldQty + $quantity;
                $newAvgCost = ($oldCost * $oldQty + $subtotal) / $newQty;

                $stmt = $db->prepare("UPDATE holdings SET quantity = ?, avg_cost = ? WHERE id = ?");
                $stmt->execute([$newQty, round($newAvgCost, 4), $holding['id']]);
            } else {
                $stmt = $db->prepare("INSERT INTO holdings (user_id, stock_id, quantity, avg_cost) VALUES (?, ?, ?, ?)");
                $stmt->execute([$userId, $stockId, $quantity, $pricePerShare]);
            }

            // Update stock circulating supply and volume
            $db->prepare("UPDATE stocks SET circulating_supply = circulating_supply + ?, volume_24h = volume_24h + ? WHERE id = ?")
                ->execute([$quantity, $quantity, $stockId]);

            // Log the trade + fee as separate transaction entries
            $logStmt = $db->prepare("
                INSERT INTO transactions (user_id, stock_id, type, quantity, price, total_amount, fee, notes)
                VALUES (?, ?, 'buy', ?, ?, ?, ?, ?)
            ");
            $logStmt->execute([
                $userId, $stockId, $quantity,
                $pricePerShare, -$subtotal, $fee,
                "买入 {$stock['name']} ×{$quantity} @{$pricePerShare}"
            ]);

            // Log fee separately
            if ($fee > 0) {
                $feeStmt = $db->prepare("
                    INSERT INTO transactions (user_id, stock_id, type, total_amount, fee, notes)
                    VALUES (?, ?, 'fee', ?, ?, ?)
                ");
                $feeStmt->execute([$userId, $stockId, -$fee, $fee, "交易手续费"]);
            }

            $db->commit();

            // Apply price impact (price goes up after buy)
            StockEngine::applyTradeImpact($stockId, $quantity, 'buy');

            $newBalance = TokenSystem::getBalance($userId);

            return [
                'success' => true,
                'message' => sprintf(
                    '成功买入 %s ×%d 股 @%.2f！共花费 %.2f 代币（含 %.2f 手续费）。',
                    $stock['name'], $quantity, $pricePerShare, $totalCost, $fee
                ),
                'quantity' => $quantity,
                'price' => $pricePerShare,
                'fee' => $fee,
                'total' => $totalCost,
                'balance_remaining' => $newBalance,
            ];

        } catch (Exception $e) {
            $db->rollBack();
            return ['success' => false, 'message' => '交易失败: ' . $e->getMessage()];
        }
    }

    /**
     * Sell stocks.
     *
     * @param int $userId The seller
     * @param int $stockId The stock to sell
     * @param int $quantity Number of shares
     * @return array Result with success/error message
     */
    public static function sell(int $userId, int $stockId, int $quantity): array {
        if ($quantity <= 0) {
            return ['success' => false, 'message' => '卖出数量必须大于0。'];
        }

        $stock = StockEngine::getStock($stockId);
        if (!$stock || !$stock['is_active']) {
            return ['success' => false, 'message' => '该股票不存在或已下架。'];
        }
        if (!empty($stock['limited_edition'])) {
            return ['success' => false, 'message' => '绝版卡牌仅可在卡牌市场交易，无法直接买卖。'];
        }

        $db = Database::getInstance();

        // Check holdings
        $stmt = $db->prepare("SELECT * FROM holdings WHERE user_id = ? AND stock_id = ?");
        $stmt->execute([$userId, $stockId]);
        $holding = $stmt->fetch();

        if (!$holding || (int)$holding['quantity'] < $quantity) {
            return ['success' => false, 'message' => '持仓不足！您当前持有 ' . ($holding['quantity'] ?? 0) . ' 股。'];
        }

        $pricePerShare = (float)$stock['current_price'];
        $subtotal = $pricePerShare * $quantity;
        $fee = round($subtotal * (TRADE_FEE_PCT / 100), 2);
        $totalReceived = $subtotal - $fee;

        $db->beginTransaction();

        try {
            // Update holdings
            $currentQty = (int)$holding['quantity'];
            $remainingQty = $currentQty - $quantity;

            if ($remainingQty <= 0) {
                $db->prepare("DELETE FROM holdings WHERE id = ?")->execute([$holding['id']]);
            } else {
                // Keep average cost the same
                $db->prepare("UPDATE holdings SET quantity = ? WHERE id = ?")
                    ->execute([$remainingQty, $holding['id']]);
            }

            // Credit tokens to user (direct SQL, no nested transaction)
            $db->prepare("UPDATE users SET token_balance = token_balance + ?, total_earned = total_earned + ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                ->execute([$totalReceived, $totalReceived, $userId]);

            // Update stock circulating supply and volume
            $db->prepare("UPDATE stocks SET circulating_supply = MAX(0, circulating_supply - ?), volume_24h = volume_24h + ? WHERE id = ?")
                ->execute([$quantity, $quantity, $stockId]);

            // Log the trade
            $logStmt = $db->prepare("
                INSERT INTO transactions (user_id, stock_id, type, quantity, price, total_amount, fee, notes)
                VALUES (?, ?, 'sell', ?, ?, ?, ?, ?)
            ");
            $logStmt->execute([
                $userId, $stockId, $quantity,
                $pricePerShare, $totalReceived, $fee,
                "卖出 {$stock['name']} ×{$quantity} @{$pricePerShare}"
            ]);

            if ($fee > 0) {
                $feeStmt = $db->prepare("
                    INSERT INTO transactions (user_id, stock_id, type, total_amount, fee, notes)
                    VALUES (?, ?, 'fee', ?, ?, ?)
                ");
                $feeStmt->execute([$userId, $stockId, -$fee, $fee, "卖出交易手续费"]);
            }

            $db->commit();

            // Apply price impact (price goes down after sell)
            StockEngine::applyTradeImpact($stockId, $quantity, 'sell');

            $newBalance = TokenSystem::getBalance($userId);

            // Calculate profit/loss
            $avgCost = (float)$holding['avg_cost'];
            $pl = ($pricePerShare - $avgCost) * $quantity;
            $plPct = $avgCost > 0 ? round(($pricePerShare - $avgCost) / $avgCost * 100, 2) : 0;

            return [
                'success' => true,
                'message' => sprintf(
                    '成功卖出 %s ×%d 股 @%.2f！获得 %.2f 代币（含 %.2f 手续费）。盈亏: %+.2f (%+.2f%%)',
                    $stock['name'], $quantity, $pricePerShare, $totalReceived, $fee, $pl, $plPct
                ),
                'quantity' => $quantity,
                'price' => $pricePerShare,
                'fee' => $fee,
                'total' => $totalReceived,
                'profit_loss' => round($pl, 2),
                'profit_loss_pct' => $plPct,
                'balance_remaining' => $newBalance,
            ];

        } catch (Exception $e) {
            $db->rollBack();
            return ['success' => false, 'message' => '交易失败: ' . $e->getMessage()];
        }
    }

    /**
     * Get user's portfolio (holdings with current market data).
     */
    public static function getPortfolio(int $userId, string $sort = 'value'): array {
        $db = Database::getInstance();
        $orderClause = 'market_value DESC';
        if ($sort === 'rarity') {
            // limited > legendary > epic > rare > common
            $orderClause = "
                CASE WHEN s.limited_edition = 1 THEN 0
                     WHEN s.rarity = 'legendary' THEN 1
                     WHEN s.rarity = 'epic' THEN 2
                     WHEN s.rarity = 'rare' THEN 3
                     WHEN s.rarity = 'common' THEN 4
                     ELSE 5 END ASC, market_value DESC
            ";
        } elseif ($sort === 'profit') {
            $orderClause = 'profit_loss DESC';
        } elseif ($sort === 'name') {
            $orderClause = 's.name ASC';
        }
        $stmt = $db->prepare("
            SELECT
                h.*,
                s.symbol,
                s.name as stock_name,
                s.category,
                s.rarity,
                s.limited_edition,
                s.current_price,
                s.price_change_pct,
                s.adapter_name,
                (h.quantity * s.current_price) as market_value,
                ((s.current_price - h.avg_cost) * h.quantity) as profit_loss,
                CASE WHEN h.avg_cost > 0
                    THEN ROUND(((s.current_price - h.avg_cost) / h.avg_cost) * 100, 2)
                    ELSE 0
                END as profit_loss_pct
            FROM holdings h
            JOIN stocks s ON h.stock_id = s.id
            WHERE h.user_id = ? AND h.quantity > 0
            ORDER BY {$orderClause}
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /**
     * Withdraw all unrealized profits into tokens.
     *
     * Calculates the total positive profit across all holdings
     * (current_price - avg_cost) * quantity, adds it to the user's
     * token balance, and resets avg_cost to current_price.
     *
     * @return array ['success', 'amount', 'message']
     */
    public static function withdrawProfits(int $userId): array {
        $portfolio = self::getPortfolio($userId);
        $totalProfit = 0;
        $updated = [];

        foreach ($portfolio as $item) {
            $qty = (int)$item['quantity'];
            $avgCost = (float)$item['avg_cost'];
            $currentPrice = (float)$item['current_price'];
            $profit = round(($currentPrice - $avgCost) * $qty, 2);

            if ($profit > 0.01) {
                $totalProfit += $profit;
                $updated[] = [
                    'stock_id' => $item['stock_id'],
                    'profit' => $profit,
                    'new_avg_cost' => $currentPrice,
                ];
            }
        }

        if ($totalProfit <= 0) {
            return ['success' => false, 'amount' => 0, 'message' => '当前没有可提现的盈利。'];
        }

        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            // Add profits to token balance (direct SQL, no nested transaction)
            $stmt = $db->prepare("UPDATE users SET token_balance = token_balance + ?, total_earned = total_earned + ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$totalProfit, $totalProfit, $userId]);

            // Log transaction
            $stmt = $db->prepare("INSERT INTO transactions (user_id, type, total_amount, notes) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId, 'withdraw', $totalProfit, "提现盈利 {$totalProfit} 枚代币"]);

            // Reset avg_cost to current_price for profitable holdings
            $updateStmt = $db->prepare("UPDATE holdings SET avg_cost = ? WHERE user_id = ? AND stock_id = ?");
            foreach ($updated as $u) {
                $updateStmt->execute([$u['new_avg_cost'], $userId, $u['stock_id']]);
            }

            $db->commit();

            return [
                'success' => true,
                'amount' => $totalProfit,
                'message' => "成功提现 {$totalProfit} 枚代币！",
            ];
        } catch (Exception $e) {
            $db->rollBack();
            return ['success' => false, 'amount' => 0, 'message' => '提现失败: ' . $e->getMessage()];
        }
    }

    /**
     * Get portfolio summary for a user.
     */
    public static function getPortfolioSummary(int $userId, string $sort = 'value'): array {
        $portfolio = self::getPortfolio($userId, $sort);

        $totalValue = 0;
        $totalCost = 0;
        $totalPL = 0;

        foreach ($portfolio as $item) {
            $totalValue += (float)$item['market_value'];
            $totalCost += (float)$item['avg_cost'] * (int)$item['quantity'];
            $totalPL += (float)$item['profit_loss'];
        }

        $totalPLPct = $totalCost > 0 ? round(($totalPL / $totalCost) * 100, 2) : 0;

        return [
            'total_stocks' => count($portfolio),
            'total_value' => round($totalValue, 2),
            'total_cost' => round($totalCost, 2),
            'total_pl' => round($totalPL, 2),
            'total_pl_pct' => $totalPLPct,
            'holdings' => $portfolio,
        ];
    }
}
