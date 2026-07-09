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
$isAdmin = Session::user() && !empty(Session::user()['is_admin']);
$message = null;
$error = null;

// Check if already in kaleidoscope
$expiresAt = $db->prepare("SELECT kaleidoscope_expires_at FROM users WHERE id = ?");
$expiresAt->execute([$userId]);
$expiry = $expiresAt->fetchColumn();
$isActive = $expiry && strtotime($expiry) > time();

// GET handler for quick switch_back
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'switch_back') {
    $_SESSION['layer'] = 'default';
    header('Location: ' . url('/'));
    exit;
}

// Check entry time window
$entryStart = (int)platform_config('system', 'kaleidoscope_entry_start', '16');
$entryEnd = (int)platform_config('system', 'kaleidoscope_entry_end', '17');
$currentHour = (int)date('G');
$windowOpen = ($currentHour >= $entryStart && $currentHour < $entryEnd);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'enter' && ($isAdmin || $windowOpen)) {
        if ($isActive) {
            $error = '已在 Kaleidoscope 中。';
        } elseif ($isAdmin) {
            $db->prepare("UPDATE users SET kaleidoscope_expires_at = datetime('now', '+".KALEIDOSCOPE_DURATION." hours') WHERE id = ?")->execute([$userId]);
            $_SESSION['layer'] = 'kaleidoscope';
            header('Location: ' . url('/'));
            exit;
        } else {
            $fee = KALEIDOSCOPE_ENTRY_FEE;
            if (TokenSystem::canAfford($userId, $fee)) {
                TokenSystem::spend($userId, $fee, 'kaleidoscope_entry');
                $db->prepare("UPDATE users SET kaleidoscope_expires_at = datetime('now', '+".KALEIDOSCOPE_DURATION." hours') WHERE id = ?")->execute([$userId]);
                $_SESSION['layer'] = 'kaleidoscope';
                header('Location: ' . url('/'));
                exit;
            } else {
                $error = '代币不足，需要 ' . nf($fee) . ' 枚。';
            }
        }
    }

    if ($action === 'convert') {
        $amount = (float)($_POST['convert_amount'] ?? 0);
        if ($amount > 0) {
            $cost = $amount * KALEIDOSCOPE_CONVERT_RATE;
            if (TokenSystem::canAfford($userId, $cost)) {
                TokenSystem::spend($userId, $cost, 'kaleidoscope_convert');
                TokenSystem::addKaleidoscope($userId, $amount);
                $message = "成功兑换 {$amount} Kaleidoscope 代币！";
            } else {
                $error = '代币不足。';
            }
        }
    }

    if ($action === 'switch') {
        if ($isActive) {
            $_SESSION['layer'] = 'kaleidoscope';
            header('Location: ' . url('/'));
            exit;
        }
    }

    if ($action === 'switch_back') {
        $_SESSION['layer'] = 'default';
        header('Location: ' . url('/'));
        exit;
    }
}

$balance = TokenSystem::getBalance($userId);
$ksBalance = TokenSystem::getKaleidoscopeBalance($userId);

require_once __DIR__ . '/templates/enter_kaleidoscope.php';
