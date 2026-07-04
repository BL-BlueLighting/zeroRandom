<?php
/**
 * zero Random - Shared Header
 * Dark theme layout with blue accents.
 */
$currentUser = Session::user();
$pageTitle = $pageTitle ?? 'zero Random';
$currentUri = $_SERVER['REQUEST_URI'] ?? '/';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - zero Random</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
<header class="site-header">
    <div class="header-inner">
        <a href="<?= url('/') ?>" class="site-logo">
            <span class="logo-icon">🎰</span>
            <span class="logo-text"><i>zero</i>Random</span>
        </a>
        <nav class="site-nav">
            <a href="<?= url('/') ?>" class="nav-link <?= $currentUri === '/' ? 'active' : '' ?>">首页</a>
            <a href="<?= url('/market.php') ?>" class="nav-link <?= strpos($currentUri, '/market.php') === 0 ? 'active' : '' ?>">股市</a>
            <a href="<?= url('/gacha.php') ?>" class="nav-link <?= strpos($currentUri, '/gacha.php') === 0 ? 'active' : '' ?>">抽卡</a>
            <a href="<?= url('/card_market.php') ?>" class="nav-link <?= strpos($currentUri, '/card_market.php') === 0 ? 'active' : '' ?>">市场</a>
            <a href="<?= url('/quests.php') ?>" class="nav-link <?= strpos($currentUri, '/quests.php') === 0 ? 'active' : '' ?>">任务</a>
            <a href="<?= url('/ranking.php') ?>" class="nav-link <?= strpos($currentUri, '/ranking.php') === 0 ? 'active' : '' ?>">排行</a>
            <a href="<?= url('/help.php') ?>" class="nav-link <?= strpos($currentUri, '/help.php') === 0 ? 'active' : '' ?>">帮助</a>
            <?php if (Session::isLoggedIn()): ?>
            <a href="<?= url('/checkin.php') ?>" class="nav-link <?= strpos($currentUri, '/checkin.php') === 0 ? 'active' : '' ?>">签到</a>
            <?php endif; ?>
            <?php if (platform_configured('hustoj') && Session::isLoggedIn()): ?>
            <a href="<?= url('/bind.php') ?>" class="nav-link <?= strpos($currentUri, '/bind.php') === 0 ? 'active' : '' ?>">绑定OJ</a>
            <?php endif; ?>
            <?php if ($currentUser && ($currentUser['is_admin'] ?? 0) == 1): ?>
            <a href="<?= url('/admin.php') ?>" class="nav-link <?= strpos($currentUri, '/admin.php') === 0 ? 'active' : '' ?>">⚙️ 管理</a>
            <?php endif; ?>
        </nav>
        <div class="header-actions">
            <?php if ($currentUser): ?>
                <div class="user-menu">
                    <a href="<?= url('/portfolio.php') ?>" class="nav-link <?= strpos($currentUri, '/portfolio.php') === 0 ? 'active' : '' ?>">
                        📦 持仓
                    </a>
                    <span class="token-display" title="代币余额">
                        🪙 <?= number_format((float)$currentUser['token_balance'], 1) ?>
                    </span>
                    <a href="<?= url('/profile.php') ?>" class="user-name"><?= htmlspecialchars($currentUser['username']) ?></a>
                    <?php $ojUrl = oj_url(); if ($ojUrl): ?>
                    <a href="<?= $ojUrl ?>" class="btn btn-sm btn-outline" target="_blank" title="返回OJ">↩ OJ</a>
                    <?php endif; ?>
                    <a href="<?= url('/transfer.php') ?>" class="btn btn-sm btn-outline">💸 转账</a>
                    <a href="<?= url('/logout.php') ?>" class="btn btn-sm btn-outline">退出</a>
                </div>
            <?php else: ?>
                <a href="<?= url('/login.php') ?>" class="btn btn-sm btn-primary">登录</a>
                <a href="<?= url('/register.php') ?>" class="btn btn-sm btn-outline">注册</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<?php
// Flash messages
$flashes = Session::getAllFlashes();
if (!empty($flashes)):
?>
<div class="flash-container">
    <?php foreach ($flashes as $type => $msg): ?>
    <div class="flash-message flash-<?= htmlspecialchars($type) ?>">
        <span class="flash-text"><?= htmlspecialchars($msg) ?></span>
        <button class="flash-close" onclick="this.parentElement.remove()">&times;</button>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Notification Banner -->
<?php
try {
    $db = Database::getInstance();
    $note = $db->query("SELECT message FROM notifications WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1")->fetch();
    if ($note):
?>
<div class="notification-banner">
    <span class="note-icon">📢</span>
    <span class="note-text"><?= $note['message'] ?></span>
</div>
<?php endif;
} catch (Exception $e) { /* table might not exist yet */ }
?>

<main class="site-main">
