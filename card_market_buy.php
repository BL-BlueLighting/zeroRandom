<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/init_check.php';
require_once __DIR__ . '/core/Session.php';
require_once __DIR__ . '/core/TokenSystem.php';
require_once __DIR__ . '/core/StockEngine.php';
require_once __DIR__ . '/core/MarketEngine.php';
Session::start();
require_once __DIR__ . '/core/AutoJob.php';
AutoJob::run();
Session::requireAuth();

$listingId = (int)($_POST['listing_id'] ?? 0);
$result = MarketEngine::buyListing(Session::userId(), $listingId);
Session::flash($result['success'] ? 'success' : 'error', $result['message']);
header('Location: ' . url('/card_market.php'));
exit;
