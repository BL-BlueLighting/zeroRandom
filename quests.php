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

// Handle refresh
$refreshMessage = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && Session::isLoggedIn()) {
    $userId = Session::userId();
    $db = Database::getInstance();

    // Gather user stats
    $stats = TokenSystem::getUserStats($userId);
    $gachaStats = GachaEngine::getPullStats($userId);
    $checkinStats = null;
    if (class_exists('CheckinEngine')) {
        $checkinStats = CheckinEngine::getCheckinStats($userId);
    }
    $holdingCount = $db->prepare("SELECT COUNT(*) FROM holdings WHERE user_id = ? AND quantity > 0");
    $holdingCount->execute([$userId]);
    $holdingCountVal = (int)$holdingCount->fetchColumn();

    // Get all active quests
    $quests = $db->query("SELECT * FROM quest_config WHERE is_active = 1")->fetchAll();
    $refreshed = 0;

    foreach ($quests as $q) {
        $current = 0;
        switch ($q['condition_type']) {
            case 'token_balance': $current = $stats['token_balance'] ?? 0; break;
            case 'total_earned':  $current = $stats['total_earned'] ?? 0; break;
            case 'total_spent':   $current = $stats['total_spent'] ?? 0; break;
            case 'gacha_single':  $current = $gachaStats['single_count'] ?? 0; break;
            case 'gacha_multi':   $current = $gachaStats['multi_count'] ?? 0; break;
            case 'gacha_hundred': $current = $gachaStats['hundred_count'] ?? 0; break;
            case 'holdings':      $current = $holdingCountVal; break;
            case 'checkin_days':
                $current = $checkinStats ? $checkinStats['total'] : 0;
                break;
        }

        $progress = min($current, (float)$q['condition_value']);

        // Upsert progress
        $db->prepare("
            INSERT INTO user_quests (user_id, quest_id, progress, completed, completed_at)
            VALUES (?, ?, ?, CASE WHEN ? >= ? THEN 1 ELSE 0 END, CASE WHEN ? >= ? THEN datetime('now') ELSE NULL END)
            ON CONFLICT(user_id, quest_id) DO UPDATE SET
                progress = excluded.progress,
                completed = excluded.completed,
                completed_at = CASE WHEN excluded.completed = 1 AND user_quests.completed = 0 THEN excluded.completed_at ELSE user_quests.completed_at END
        ")->execute([$userId, $q['id'], $progress, $progress, $q['condition_value'], $progress, $q['condition_value']]);

        // Award if newly completed
        $uq = $db->prepare("SELECT * FROM user_quests WHERE user_id = ? AND quest_id = ?");
        $uq->execute([$userId, $q['id']]);
        $row = $uq->fetch();
        if ($row && $row['completed'] && $progress >= (float)$q['condition_value'] && $q['reward_tokens'] > 0) {
            // Check if already awarded
            $awarded = $db->prepare("SELECT id FROM transactions WHERE user_id = ? AND type = 'quest_reward' AND notes LIKE ?");
            $awarded->execute([$userId, '%quest#' . $q['id'] . '%']);
            if (!$awarded->fetch()) {
                TokenSystem::add($userId, (float)$q['reward_tokens'], 'quest_reward');
                $db->prepare("INSERT INTO transactions (user_id, type, total_amount, notes) VALUES (?, 'quest_reward', ?, ?)")
                    ->execute([$userId, (float)$q['reward_tokens'], '完成任务「' . $q['name'] . '」quest#' . $q['id']]);
            }
        }
        $refreshed++;
    }
    $refreshMessage = "✅ 已刷新 {$refreshed} 个任务进度。";
    Session::flash('success', $refreshMessage);
    header('Location: ' . url('/quests.php'));
    exit;
}

$dailyQuests = [];
$achievements = [];
if (Session::isLoggedIn()) {
    $dailyQuests = QuestEngine::getUserQuests(Session::userId(), 'daily');
    $achievements = QuestEngine::getUserQuests(Session::userId(), 'achievement');
}

require_once __DIR__ . '/templates/quests.php';
