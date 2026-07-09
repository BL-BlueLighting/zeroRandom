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

$userId = Session::userId();
$db = Database::getInstance();

// Mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $msgId = (int)($_POST['msg_id'] ?? 0);
    if ($msgId) {
        $db->prepare("UPDATE user_messages SET is_read = 1 WHERE id = ? AND to_user = ?")->execute([$msgId, $userId]);
    }
    header('Location: ' . url('/messages.php'));
    exit;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$filter = $_GET['filter'] ?? 'all';

$where = "to_user = {$userId}";
if ($filter === 'unread') $where .= " AND is_read = 0";
elseif ($filter === 'read') $where .= " AND is_read = 1";

$total = (int)$db->query("SELECT COUNT(*) FROM user_messages WHERE {$where}")->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));
$offset = ($page - 1) * $perPage;

$messages = $db->query("SELECT * FROM user_messages WHERE {$where} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}")->fetchAll();

require_once __DIR__ . '/templates/messages.php';
