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

$page = max(1, (int)($_GET['page'] ?? 1));
$listings = MarketEngine::getListings($page);
$totalListings = MarketEngine::getListingCount();
$totalPages = max(1, ceil($totalListings / 20));

if (Session::isLoggedIn()) {
    $myListings = MarketEngine::getUserListings(Session::userId());
}

require_once __DIR__ . '/templates/card_market.php';
