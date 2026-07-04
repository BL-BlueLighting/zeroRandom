<?php
class MarketEngine {

    // List a card for sale
    public static function listCard(int $sellerId, int $stockId, int $quantity, float $price): array {
        if ($quantity <= 0) return ['success' => false, 'message' => '数量必须大于0。'];
        if ($price <= 0) return ['success' => false, 'message' => '价格必须大于0。'];

        $db = Database::getInstance();
        $holding = $db->prepare("SELECT * FROM holdings WHERE user_id = ? AND stock_id = ? AND quantity >= ?");
        $holding->execute([$sellerId, $stockId, $quantity]);
        if (!$holding->fetch()) return ['success' => false, 'message' => '持仓不足。'];

        $db->beginTransaction();
        try {
            // Deduct from holdings
            $db->prepare("UPDATE holdings SET quantity = quantity - ? WHERE user_id = ? AND stock_id = ?")
                ->execute([$quantity, $sellerId, $stockId]);
            $db->prepare("DELETE FROM holdings WHERE user_id = ? AND stock_id = ? AND quantity <= 0")
                ->execute([$sellerId, $stockId]);
            // Create listing
            $db->prepare("INSERT INTO card_market_listings (seller_id, stock_id, quantity, price) VALUES (?, ?, ?, ?)")
                ->execute([$sellerId, $stockId, $quantity, $price]);
            $db->commit();
            return ['success' => true, 'message' => '已挂单出售！'];
        } catch (Exception $e) {
            $db->rollBack();
            return ['success' => false, 'message' => '挂单失败: ' . $e->getMessage()];
        }
    }

    // Buy a listing
    public static function buyListing(int $buyerId, int $listingId): array {
        $db = Database::getInstance();
        $listing = $db->prepare("SELECT * FROM card_market_listings WHERE id = ? AND status = 'listed'");
        $listing->execute([$listingId]);
        $l = $listing->fetch();
        if (!$l) return ['success' => false, 'message' => '该挂单已售出或不存在。'];
        if ($l['seller_id'] == $buyerId) return ['success' => false, 'message' => '不能购买自己的挂单。'];

        $totalCost = (float)$l['price'] * (int)$l['quantity'];
        if (!TokenSystem::canAfford($buyerId, $totalCost)) {
            return ['success' => false, 'message' => "代币不足！需要 {$totalCost} 枚。"];
        }

        $db->beginTransaction();
        try {
            // Deduct from buyer (direct SQL)
            $db->prepare("UPDATE users SET token_balance = token_balance - ?, total_spent = total_spent + ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                ->execute([$totalCost, $totalCost, $buyerId]);
            // Add to seller (direct SQL)
            $db->prepare("UPDATE users SET token_balance = token_balance + ?, total_earned = total_earned + ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                ->execute([$totalCost, $totalCost, $l['seller_id']]);
            // Add holdings to buyer
            $hold = $db->prepare("SELECT * FROM holdings WHERE user_id = ? AND stock_id = ?");
            $hold->execute([$buyerId, $l['stock_id']]);
            $h = $hold->fetch();
            if ($h) {
                $newQty = $h['quantity'] + $l['quantity'];
                $newCost = ($h['avg_cost'] * $h['quantity'] + $l['price'] * $l['quantity']) / $newQty;
                $db->prepare("UPDATE holdings SET quantity = ?, avg_cost = ? WHERE id = ?")
                    ->execute([$newQty, $newCost, $h['id']]);
            } else {
                $db->prepare("INSERT INTO holdings (user_id, stock_id, quantity, avg_cost) VALUES (?, ?, ?, ?)")
                    ->execute([$buyerId, $l['stock_id'], $l['quantity'], $l['price']]);
            }
            // Mark listing as sold
            $db->prepare("UPDATE card_market_listings SET status = 'sold', buyer_id = ?, sold_at = datetime('now') WHERE id = ?")
                ->execute([$buyerId, $listingId]);
            $db->commit();
            return ['success' => true, 'message' => "购买成功！花费 {$totalCost} 枚代币。"];
        } catch (Exception $e) {
            $db->rollBack();
            return ['success' => false, 'message' => '购买失败: ' . $e->getMessage()];
        }
    }

    // Cancel a listing
    public static function cancelListing(int $userId, int $listingId): array {
        $db = Database::getInstance();
        $listing = $db->prepare("SELECT * FROM card_market_listings WHERE id = ? AND seller_id = ? AND status = 'listed'");
        $listing->execute([$listingId, $userId]);
        $l = $listing->fetch();
        if (!$l) return ['success' => false, 'message' => '挂单不存在或无权操作。'];

        $db->beginTransaction();
        try {
            $db->prepare("UPDATE card_market_listings SET status = 'cancelled' WHERE id = ?")->execute([$listingId]);
            // Return cards to seller
            $hold = $db->prepare("SELECT * FROM holdings WHERE user_id = ? AND stock_id = ?");
            $hold->execute([$userId, $l['stock_id']]);
            $h = $hold->fetch();
            if ($h) {
                $db->prepare("UPDATE holdings SET quantity = quantity + ? WHERE id = ?")
                    ->execute([$l['quantity'], $h['id']]);
            } else {
                $stock = StockEngine::getStock($l['stock_id']);
                $db->prepare("INSERT INTO holdings (user_id, stock_id, quantity, avg_cost) VALUES (?, ?, ?, ?)")
                    ->execute([$userId, $l['stock_id'], $l['quantity'], $stock['current_price'] ?? 0]);
            }
            $db->commit();
            return ['success' => true, 'message' => '已取消挂单。'];
        } catch (Exception $e) {
            $db->rollBack();
            return ['success' => false, 'message' => '取消失败: ' . $e->getMessage()];
        }
    }

    // Get active listings
    public static function getListings(int $page = 1, int $perPage = 20): array {
        $db = Database::getInstance();
        $offset = ($page - 1) * $perPage;
        $stmt = $db->prepare("
            SELECT m.*, s.symbol, s.name as stock_name, s.rarity, s.limited_edition, s.current_price,
                   u.username as seller_name
            FROM card_market_listings m
            JOIN stocks s ON m.stock_id = s.id
            JOIN users u ON m.seller_id = u.id
            WHERE m.status = 'listed'
            ORDER BY m.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$perPage, $offset]);
        return $stmt->fetchAll();
    }

    public static function getListingCount(): int {
        $db = Database::getInstance();
        return (int)$db->query("SELECT COUNT(*) FROM card_market_listings WHERE status = 'listed'")->fetchColumn();
    }

    public static function getUserListings(int $userId): array {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT m.*, s.symbol, s.name as stock_name, s.rarity, s.limited_edition, s.current_price
            FROM card_market_listings m
            JOIN stocks s ON m.stock_id = s.id
            WHERE m.seller_id = ? AND m.status != 'cancelled'
            ORDER BY m.created_at DESC LIMIT 50
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
}
