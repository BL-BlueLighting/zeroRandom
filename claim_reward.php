<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/init_check.php';
require_once __DIR__ . '/core/Session.php';
require_once __DIR__ . '/core/TokenSystem.php';
require_once __DIR__ . '/core/StockEngine.php';
require_once __DIR__ . '/core/GachaEngine.php';
require_once __DIR__ . '/core/TradingEngine.php';
require_once __DIR__ . '/core/PoolEngine.php';
require_once __DIR__ . '/core/MarketEngine.php';
require_once __DIR__ . '/adapters/manager.php';
Session::start();
Session::requireAuth();
header('Content-Type: application/json; charset=utf-8');

$notificationId = (int)($_POST['notification_id'] ?? 0);
$userId = Session::userId();
$db = Database::getInstance();

// Check notification exists and has rewards
$stmt = $db->prepare("SELECT * FROM notifications WHERE id = ? AND is_active = 1");
$stmt->execute([$notificationId]);
$note = $stmt->fetch();

if (!$note) {
    echo json_encode(['success' => false, 'message' => '公告不存在。']);
    exit;
}

if ((float)$note['reward_tokens'] <= 0 && (int)$note['reward_stock_id'] <= 0) {
    echo json_encode(['success' => false, 'message' => '此公告没有奖励。']);
    exit;
}

// Check already claimed
$stmt = $db->prepare("SELECT id FROM claimed_rewards WHERE user_id = ? AND notification_id = ?");
$stmt->execute([$userId, $notificationId]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => '你已经领取过此奖励了。']);
    exit;
}

$db->beginTransaction();
try {
    $tokenReward = min((float)$note['reward_tokens'], 100000);
    $stockId = (int)$note['reward_stock_id'];
    $stockQty = (int)$note['reward_stock_quantity'];

    // Award tokens
    if ($tokenReward > 0) {
        $db->prepare("UPDATE users SET token_balance = token_balance + ?, total_earned = total_earned + ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
            ->execute([$tokenReward, $tokenReward, $userId]);
        $db->prepare("INSERT INTO transactions (user_id, type, total_amount, notes) VALUES (?, 'reward', ?, ?)")
            ->execute([$userId, $tokenReward, "公告奖励 {$tokenReward} 代币"]);
    }

    // Award stock (value check ~1000T)
    if ($stockId > 0 && $stockQty > 0) {
        $stock = StockEngine::getStock($stockId);
        if ($stock) {
            $stockValue = (float)$stock['current_price'] * $stockQty;
            if ($stockValue <= 1000) {
                $hold = $db->prepare("SELECT * FROM holdings WHERE user_id = ? AND stock_id = ?");
                $hold->execute([$userId, $stockId]);
                $h = $hold->fetch();
                if ($h) {
                    $newQty = $h['quantity'] + $stockQty;
                    $newCost = ($h['avg_cost'] * $h['quantity'] + $stock['current_price'] * $stockQty) / $newQty;
                    $db->prepare("UPDATE holdings SET quantity = ?, avg_cost = ? WHERE id = ?")->execute([$newQty, round($newCost, 4), $h['id']]);
                } else {
                    $db->prepare("INSERT INTO holdings (user_id, stock_id, quantity, avg_cost) VALUES (?, ?, ?, ?)")
                        ->execute([$userId, $stockId, $stockQty, $stock['current_price']]);
                }
            }
        }
    }

    // Mark claimed
    $db->prepare("INSERT INTO claimed_rewards (user_id, notification_id) VALUES (?, ?)")
        ->execute([$userId, $notificationId]);

    $db->commit();

    $msg = '🎉 领取成功！';
    if ($tokenReward > 0) $msg .= " 获得 {$tokenReward} 代币。";
    if ($stockId > 0) $msg .= " 获得 {$stockQty} 股股票。";

    echo json_encode(['success' => true, 'message' => $msg]);
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => '领取失败: ' . $e->getMessage()]);
}
exit;
