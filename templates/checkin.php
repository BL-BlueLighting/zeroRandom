<?php $pageTitle = '每日签到'; include __DIR__ . '/layout/header.php';
$isKs = is_kaleidoscope();
$reward = $isKs ? 1 : CheckinEngine::DAILY_REWARD;
$unit = $isKs ? 'SKYT' : '枚代币';
?>
<div class="page-auth">
    <div class="auth-card" style="max-width:450px;text-align:center">
        <h1 class="auth-title">📅 <?= $isKs ? '天界' : '' ?>签到</h1>
        <p class="auth-subtitle">每天签到领取 <?= $reward ?> <?= $unit ?></p>
        <div style="font-size:64px;margin:24px 0"><?= $canCheckin ? '📮' : '✅' ?></div>
        <div class="stats-grid" style="margin-bottom:20px">
            <div class="stat-card"><div class="stat-label">累计签到</div><div class="stat-value"><?= $stats['total'] ?> 天</div></div>
            <div class="stat-card"><div class="stat-label">连续签到</div><div class="stat-value">🔥 <?= $stats['streak'] ?> 天</div></div>
        </div>
        <?php if ($canCheckin): ?>
        <form method="POST">
            <button class="btn btn-primary btn-lg btn-block">🎯 领取 <?= $reward ?> <?= $unit ?></button>
        </form>
        <?php else: ?>
        <p class="text-muted">✅ 今日已签到，明天再来！</p>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/layout/footer.php'; ?>
