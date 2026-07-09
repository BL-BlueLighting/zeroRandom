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
$user = Session::user();
if (!$user || !$user['is_admin']) { header('Location: ' . url('/')); exit; }

$db = Database::getInstance();
$users = $db->query("SELECT id, username, token_balance, total_earned, total_spent, is_admin, created_at FROM users ORDER BY id ASC")->fetchAll();

require_once __DIR__ . '/templates/user_manager.php';
