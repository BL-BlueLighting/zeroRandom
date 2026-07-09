<?php
/**
 * OIManka - Home Page
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/init_check.php';
require_once __DIR__ . '/core/Session.php';
require_once __DIR__ . '/core/StockEngine.php';
require_once __DIR__ . '/core/TokenSystem.php';
require_once __DIR__ . '/core/GachaEngine.php';
require_once __DIR__ . '/core/TradingEngine.php';
require_once __DIR__ . '/adapters/manager.php';

Session::start();
require_once __DIR__ . '/core/AutoJob.php';
AutoJob::run();

require __DIR__ . '/templates/help.php';
