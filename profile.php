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
require_once __DIR__ . '/core/CheckinEngine.php';
require_once __DIR__ . '/adapters/manager.php';
Session::start();
require_once __DIR__ . '/core/AutoJob.php';
AutoJob::run();

$profileUserId = (int)($_GET['id'] ?? Session::userId());
if ($profileUserId <= 0) $profileUserId = Session::userId();
$isOwner = Session::isLoggedIn() && $profileUserId === Session::userId();

// Handle number_style save
if ($isOwner && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_number_style') {
    $style = $_POST['number_style'] ?? 'wan';
    if (in_array($style, ['wan', '4digit', '3digit'])) {
        $db = Database::getInstance();
        $db->prepare("UPDATE users SET number_style = ? WHERE id = ?")->execute([$style, $profileUserId]);
        $_SESSION['number_style'] = $style;
        Session::flash('success', '数字格式已更新。');
    }
    header('Location: ' . url('/profile.php'));
    exit;
}

$db = Database::getInstance();
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$profileUserId]);
$profileUser = $stmt->fetch();
if (!$profileUser) { http_response_code(404); echo '用户不存在'; exit; }

// Stats
$stats = TokenSystem::getUserStats($profileUserId);
$checkinStats = CheckinEngine::getCheckinStats($profileUserId);
$summary = TradingEngine::getPortfolioSummary($profileUserId);

// Best cards (top 3 by value)
$bestCards = $db->prepare("
    SELECT h.*, s.symbol, s.name as stock_name, s.rarity, s.current_price,
           (h.quantity * s.current_price) as market_value
    FROM holdings h JOIN stocks s ON h.stock_id = s.id
    WHERE h.user_id = ? AND h.quantity > 0
    ORDER BY market_value DESC LIMIT 3
");
$bestCards->execute([$profileUserId]);

// Completed achievements
$achievements = $db->prepare("
    SELECT qc.* FROM user_quests uq
    JOIN quest_config qc ON uq.quest_id = qc.id
    WHERE uq.user_id = ? AND uq.completed = 1 AND qc.type = 'achievement'
    ORDER BY uq.completed_at DESC
");
$achievements->execute([$profileUserId]);

$gachaStats = GachaEngine::getPullStats($profileUserId);

$pageTitle = htmlspecialchars($profileUser['username']) . ' 的个人主页';
require_once __DIR__ . '/templates/profile.php';
