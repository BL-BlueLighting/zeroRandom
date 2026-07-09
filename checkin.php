<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/init_check.php';
require_once __DIR__ . '/core/Session.php';
require_once __DIR__ . '/core/TokenSystem.php';
require_once __DIR__ . '/core/CheckinEngine.php';
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

$userId = Session::userId();
$stats = CheckinEngine::getCheckinStats($userId);
$canCheckin = CheckinEngine::canCheckin($userId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = CheckinEngine::checkin($userId);
    Session::flash($result['success'] ? 'success' : 'error', $result['message']);
    header('Location: ' . url('/checkin.php'));
    exit;
}

require_once __DIR__ . '/templates/checkin.php';
