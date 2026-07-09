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

// Mark as read or send message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_read') {
        $msgId = (int)($_POST['msg_id'] ?? 0);
        if ($msgId) {
            $db->prepare("UPDATE user_messages SET is_read = 1 WHERE id = ? AND to_user = ?")->execute([$msgId, $userId]);
        }
        header('Location: ' . url('/messages.php'));
        exit;
    }
    if ($action === 'send') {
        $toUser = trim($_POST['to_user'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        if ($toUser && $title && $content) {
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR id = ?");
            $stmt->execute([$toUser, is_numeric($toUser) ? (int)$toUser : 0]);
            $target = $stmt->fetch();
            if ($target && (int)$target['id'] !== $userId) {
                $db->prepare("INSERT INTO user_messages (to_user, from_user, title, content) VALUES (?, ?, ?, ?)")
                    ->execute([(int)$target['id'], Session::user()['username'] ?? 'unknown', $title, $content]);
                Session::flash('success', '消息已发送！');
            } else {
                Session::flash('error', '接收方不存在或不能给自己发信。');
            }
        } else {
            Session::flash('error', '请填写完整。');
        }
        header('Location: ' . url('/messages.php'));
        exit;
    }
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
