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
require_once __DIR__ . '/core/QuestEngine.php';
require_once __DIR__ . '/core/MarketEngine.php';
require_once __DIR__ . '/adapters/manager.php';
Session::start();
Session::requireAuth();

$result = null;
$receiver = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target = trim($_POST['target'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $password = $_POST['password'] ?? '';

    if (empty($target) || $amount <= 0 || empty($password)) {
        $result = ['success' => false, 'message' => '请填写所有字段。'];
    } else {
        $db = Database::getInstance();
        $userId = Session::userId();

        // Verify password
        $user = Session::user();
        if (!password_verify($password, $user['password_hash'])) {
            $result = ['success' => false, 'message' => '密码错误！'];
        } else {
            // Find receiver
            $stmt = $db->prepare("SELECT id, username FROM users WHERE username = ? OR id = ?");
            $stmt->execute([$target, is_numeric($target) ? (int)$target : 0]);
            $receiver = $stmt->fetch();

            if (!$receiver || (int)$receiver['id'] === $userId) {
                $result = ['success' => false, 'message' => '接收方不存在或不能转账给自己。'];
            } elseif (!TokenSystem::canAfford($userId, $amount)) {
                $result = ['success' => false, 'message' => '代币不足。'];
            } else {
                $success = TokenSystem::transfer($userId, (int)$receiver['id'], $amount);
                if ($success) {
                    $result = ['success' => true, 'message' => "成功向 {$receiver['username']} 转账 {$amount} 枚代币！"];
                } else {
                    $result = ['success' => false, 'message' => '转账失败，请重试。'];
                }
            }
        }
    }
}

require_once __DIR__ . '/templates/transfer.php';
