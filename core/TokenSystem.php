<?php
/**
 * OIManka - Token System
 *
 * Manages user token balances: earn, spend, transfer, and query.
 * All token operations are logged as transactions.
 */

class TokenSystem {

    /**
     * Get user's current token balance.
     */
    public static function getBalance(int $userId): float {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT token_balance FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return (float)($stmt->fetchColumn() ?: 0);
    }

    /**
     * Add tokens to a user's balance.
     */
    public static function add(int $userId, float $amount, string $reason = 'reward'): bool {
        if ($amount <= 0) return false;

        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare("UPDATE users SET token_balance = token_balance + ?, total_earned = total_earned + ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$amount, $amount, $userId]);

            // Log transaction
            $logStmt = $db->prepare("INSERT INTO transactions (user_id, type, total_amount, notes) VALUES (?, ?, ?, ?)");
            $logStmt->execute([$userId, $reason, $amount, "获得 {$amount} 代币"]);

            $db->commit();
            return true;
        } catch (Exception $e) {
            $db->rollBack();
            return false;
        }
    }

    /**
     * Deduct tokens from a user's balance.
     * Returns false if insufficient balance.
     */
    public static function spend(int $userId, float $amount, string $reason = 'purchase'): bool {
        if ($amount <= 0) return false;

        $balance = self::getBalance($userId);
        if ($balance < $amount) return false;

        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare("UPDATE users SET token_balance = token_balance - ?, total_spent = total_spent + ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$amount, $amount, $userId]);

            // Log transaction
            $logStmt = $db->prepare("INSERT INTO transactions (user_id, type, total_amount, notes) VALUES (?, ?, ?, ?)");
            $logStmt->execute([$userId, $reason, -$amount, "消费 {$amount} 代币"]);

            $db->commit();
            return true;
        } catch (Exception $e) {
            $db->rollBack();
            return false;
        }
    }

    /**
     * Transfer tokens between two users.
     */
    public static function transfer(int $fromUserId, int $toUserId, float $amount): bool {
        if ($amount <= 0) return false;
        if ($fromUserId === $toUserId) return false;

        $balance = self::getBalance($fromUserId);
        if ($balance < $amount) return false;

        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            // Deduct from sender
            $stmt = $db->prepare("UPDATE users SET token_balance = token_balance - ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$amount, $fromUserId]);

            // Add to receiver
            $stmt = $db->prepare("UPDATE users SET token_balance = token_balance + ?, total_earned = total_earned + ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$amount, $amount, $toUserId]);

            // Log both sides
            $logStmt = $db->prepare("INSERT INTO transactions (user_id, type, total_amount, notes) VALUES (?, ?, ?, ?)");
            $logStmt->execute([$fromUserId, 'transfer_out', -$amount, "转账给用户 #{$toUserId}"]);
            $logStmt->execute([$toUserId, 'reward', $amount, "收到用户 #{$fromUserId} 转账"]);

            $db->commit();
            return true;
        } catch (Exception $e) {
            $db->rollBack();
            return false;
        }
    }

    /**
     * Check if user can afford an amount.
     */
    public static function canAfford(int $userId, float $amount): bool {
        return self::getBalance($userId) >= $amount;
    }

    /**
     * Get user's transaction history.
     */
    public static function getTransactionHistory(int $userId, int $limit = 50, int $offset = 0): array {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT t.*, s.symbol, s.name as stock_name
            FROM transactions t
            LEFT JOIN stocks s ON t.stock_id = s.id
            WHERE t.user_id = ?
            ORDER BY t.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$userId, $limit, $offset]);
        return $stmt->fetchAll();
    }

    /**
     * Get total transaction count for a user.
     */
    public static function getTransactionCount(int $userId): int {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT COUNT(*) FROM transactions WHERE user_id = ?");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Get user statistics.
     */
    public static function getUserStats(int $userId): array {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT
                token_balance,
                total_earned,
                total_spent,
                (SELECT COUNT(*) FROM holdings WHERE user_id = ? AND quantity > 0) as unique_stocks,
                (SELECT COALESCE(SUM(h.quantity * s.current_price), 0)
                 FROM holdings h JOIN stocks s ON h.stock_id = s.id
                 WHERE h.user_id = ? AND h.quantity > 0) as portfolio_value,
                (SELECT COUNT(*) FROM gacha_logs WHERE user_id = ?) as total_pulls
            FROM users WHERE id = ?
        ");
        $stmt->execute([$userId, $userId, $userId, $userId]);
        $stats = $stmt->fetch();

        if (!$stats) return [];

        $stats['token_balance'] = (float)$stats['token_balance'];
        $stats['total_earned'] = (float)$stats['total_earned'];
        $stats['total_spent'] = (float)$stats['total_spent'];
        $stats['portfolio_value'] = (float)$stats['portfolio_value'];
        $stats['net_worth'] = $stats['token_balance'] + $stats['portfolio_value'];

        return $stats;
    }

    /**
     * Get leaderboard by net worth.
     */
    public static function getLeaderboard(int $limit = 50): array {
        $db = Database::getInstance();
        return $db->query("
            SELECT
                u.id,
                u.username,
                u.token_balance,
                u.total_earned,
                COALESCE(SUM(h.quantity * s.current_price), 0) as portfolio_value,
                u.token_balance + COALESCE(SUM(h.quantity * s.current_price), 0) as net_worth,
                COUNT(DISTINCT h.stock_id) as unique_stocks,
                (SELECT COUNT(*) FROM gacha_logs WHERE user_id = u.id) as total_pulls
            FROM users u
            LEFT JOIN holdings h ON h.user_id = u.id AND h.quantity > 0
            LEFT JOIN stocks s ON h.stock_id = s.id
            GROUP BY u.id
            ORDER BY net_worth DESC
            LIMIT {$limit}
        ")->fetchAll();
    }

    // ─── Kaleidoscope Balance ───

    public static function getKaleidoscopeBalance(int $userId): float {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT kaleidoscope_balance FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return (float)($stmt->fetchColumn() ?: 0);
    }

    public static function addKaleidoscope(int $userId, float $amount): bool {
        if ($amount <= 0) return false;
        $db = Database::getInstance();
        $db->prepare("UPDATE users SET kaleidoscope_balance = kaleidoscope_balance + ? WHERE id = ?")->execute([$amount, $userId]);
        return true;
    }

    public static function spendKaleidoscope(int $userId, float $amount): bool {
        if ($amount <= 0) return false;
        $bal = self::getKaleidoscopeBalance($userId);
        if ($bal < $amount) return false;
        $db = Database::getInstance();
        $db->prepare("UPDATE users SET kaleidoscope_balance = kaleidoscope_balance - ? WHERE id = ?")->execute([$amount, $userId]);
        return true;
    }

    public static function canAffordKaleidoscope(int $userId, float $amount): bool {
        return self::getKaleidoscopeBalance($userId) >= $amount;
    }
}
