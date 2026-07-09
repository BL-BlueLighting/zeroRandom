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
require_once __DIR__ . '/core/AutoJob.php';
AutoJob::run();
Session::requireAuth();
$user = Session::user();
if (!$user || !$user['is_admin']) { header('Location: ' . url('/')); exit; }

$message = '';
$error = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $name = trim($_POST['pool_name'] ?? '');
        if ($name) { PoolEngine::createPool($name); $message = "卡池「{$name}」已创建。"; }
    }
    if ($action === 'delete') {
        $pid = (int)($_POST['pool_id'] ?? 0);
        if ($pid) { PoolEngine::deletePool($pid); $message = '卡池已删除。'; }
    }
    if ($action === 'split') {
        $pid = (int)($_POST['pool_id'] ?? 0);
        $newName = trim($_POST['new_pool_name'] ?? '');
        $splitAt = (int)($_POST['split_at'] ?? 0);
        if ($pid && $newName) {
            try { PoolEngine::splitPool($pid, $newName, $splitAt); $message = "已分割为新卡池「{$newName}」。"; }
            catch (Exception $e) { $error = $e->getMessage(); }
        }
    }
    if ($action === 'set_limited') {
        $pid = (int)($_POST['pool_id'] ?? 0);
        $expiresAt = $_POST['expires_at'] ?? '';
        if ($pid && $expiresAt) {
            PoolEngine::setLimited($pid, $expiresAt);
            $message = '✅ 已设为绝版卡池，到期后卡牌自动变为绝版。';
        }
    }
    if ($action === 'unset_limited') {
        $pid = (int)($_POST['pool_id'] ?? 0);
        if ($pid) {
            PoolEngine::unsetLimited($pid);
            $message = '✅ 已移除绝版状态。';
        }
    }
}

$pools = PoolEngine::getAllPools();
require_once __DIR__ . '/templates/pool_manager.php';
