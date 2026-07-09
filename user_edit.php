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
$admin = Session::user();
if (!$admin || !$admin['is_admin']) { header('Location: ' . url('/')); exit; }

$targetId = (int)($_GET['id'] ?? 0);
if ($targetId <= 0) { header('Location: ' . url('/user_manager.php')); exit; }

$db = Database::getInstance();

// Fetch target user
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$targetId]);
$targetUser = $stmt->fetch();
if (!$targetUser) { echo '用户不存在'; exit; }

$message = null;
$error = null;

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_user') {
        $fields = ['username', 'token_balance', 'is_admin'];
        $sets = [];
        $params = [];
        foreach ($fields as $f) {
            if (isset($_POST[$f])) {
                if ($f === 'is_admin') {
                    $sets[] = "is_admin = ?";
                    $params[] = (int)(bool)$_POST[$f];
                } else {
                    $sets[] = "{$f} = ?";
                    $params[] = $_POST[$f];
                }
            }
        }
        if (!empty($sets)) {
            $params[] = $targetId;
            $db->prepare("UPDATE users SET " . implode(', ', $sets) . ", updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute($params);
            $message = '✅ 用户信息已更新。';
            // Refresh
            $stmt->execute([$targetId]);
            $targetUser = $stmt->fetch();
        }
    }

    if ($action === 'clear_holdings') {
        $db->prepare("DELETE FROM card_placements WHERE user_id = ?")->execute([$targetId]);
        $db->prepare("DELETE FROM holdings WHERE user_id = ?")->execute([$targetId]);
        $message = '✅ 持仓已清空。';
    }

    if ($action === 'reset_password') {
        $newPw = trim($_POST['new_password'] ?? '');
        if (strlen($newPw) >= 6) {
            $hash = password_hash($newPw, PASSWORD_DEFAULT);
            $db->prepare("UPDATE users SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$hash, $targetId]);
            $message = '✅ 密码已重置。';
        } else { $error = '密码至少6位。'; }
    }

    if ($action === 'delete_user') {
        $db->prepare("DELETE FROM card_placements WHERE user_id = ?")->execute([$targetId]);
        $db->prepare("DELETE FROM holdings WHERE user_id = ?")->execute([$targetId]);
        $db->prepare("DELETE FROM transactions WHERE user_id = ?")->execute([$targetId]);
        $db->prepare("DELETE FROM gacha_logs WHERE user_id = ?")->execute([$targetId]);
        $db->prepare("DELETE FROM daily_checkins WHERE user_id = ?")->execute([$targetId]);
        $db->prepare("DELETE FROM user_quests WHERE user_id = ?")->execute([$targetId]);
        $db->prepare("DELETE FROM user_hustoj_bindings WHERE user_id = ?")->execute([$targetId]);
        $db->prepare("DELETE FROM claimed_rewards WHERE user_id = ?")->execute([$targetId]);
        $db->prepare("DELETE FROM card_market_listings WHERE seller_id = ? OR buyer_id = ?")->execute([$targetId, $targetId]);
        $db->prepare("DELETE FROM users WHERE id = ?")->execute([$targetId]);
        header('Location: ' . url('/user_manager.php'));
        exit;
    }
}

// Stats
$holdingCount = $db->prepare("SELECT COUNT(*) FROM holdings WHERE user_id = ? AND quantity > 0");
$holdingCount->execute([$targetId]);
$hc = (int)$holdingCount->fetchColumn();

$gachaCount = $db->prepare("SELECT COUNT(*) FROM gacha_logs WHERE user_id = ?");
$gachaCount->execute([$targetId]);
$gc = (int)$gachaCount->fetchColumn();

$txCount = $db->prepare("SELECT COUNT(*) FROM transactions WHERE user_id = ?");
$txCount->execute([$targetId]);
$tc = (int)$txCount->fetchColumn();

// Holdings with pagination
$holdPage = max(1, (int)($_GET['hold_page'] ?? 1));
$holdPerPage = 20;
$holdings = $db->prepare("
    SELECT h.*, s.symbol, s.name as stock_name, s.rarity, s.limited_edition, s.current_price,
           (h.quantity * s.current_price) as market_value,
           ((s.current_price - h.avg_cost) * h.quantity) as profit_loss
    FROM holdings h JOIN stocks s ON h.stock_id = s.id
    WHERE h.user_id = ? AND h.quantity > 0
    ORDER BY market_value DESC
    LIMIT ? OFFSET ?
");
$holdings->execute([$targetId, $holdPerPage, ($holdPage - 1) * $holdPerPage]);
$holdRows = $holdings->fetchAll();
$holdTotalPages = max(1, ceil($hc / $holdPerPage));

require_once __DIR__ . '/templates/user_edit.php';
