<?php $pageTitle = '任务'; include __DIR__ . '/layout/header.php'; ?>
<div class="page-market" style="max-width:800px;margin:0 auto">
    <div class="page-header">
        <h1>🎯 任务</h1>
        <form method="POST" style="display:inline">
            <button class="btn btn-outline">🔄 刷新进度</button>
        </form>
    </div>

    <section class="section">
        <h2 class="section-title">📋 每日任务</h2>
        <?php if (empty($dailyQuests)): ?>
        <p class="text-muted">暂无可用的每日任务</p>
        <?php else: ?>
        <div class="tx-list">
        <?php foreach ($dailyQuests as $q): ?>
            <div class="tx-item">
                <span class="tx-icon"><?= $q['completed'] ? '✅' : '📌' ?></span>
                <div class="tx-body">
                    <div class="tx-title"><?= htmlspecialchars($q['name']) ?></div>
                    <div class="tx-meta"><?= htmlspecialchars($q['description']) ?></div>
                </div>
                <div style="text-align:right">
                    <div class="tx-amount" style="font-size:13px">
                        <?= min($q['progress'], $q['condition_value']) ?>/<?= $q['condition_value'] ?>
                    </div>
                    <div style="width:100px;height:4px;background:var(--bg-secondary);border-radius:2px;overflow:hidden;margin-top:2px">
                        <div style="width:<?= min(100, ($q['progress'] / max(1, $q['condition_value'])) * 100) ?>%;height:100%;background:var(--accent);border-radius:2px"></div>
                    </div>
                    <div class="text-muted" style="font-size:11px">🪙 +<?= $q['reward_tokens'] ?></div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>

    <section class="section">
        <h2 class="section-title">🏆 成就</h2>
        <?php if (empty($achievements)): ?>
        <p class="text-muted">暂无可用的成就</p>
        <?php else: ?>
        <div class="tx-list">
        <?php foreach ($achievements as $q): ?>
            <div class="tx-item">
                <span class="tx-icon"><?= $q['completed'] ? '🏆' : '🔒' ?></span>
                <div class="tx-body">
                    <div class="tx-title"><?= htmlspecialchars($q['name']) ?></div>
                    <div class="tx-meta"><?= htmlspecialchars($q['description']) ?></div>
                </div>
                <div style="text-align:right">
                    <div class="tx-amount" style="font-size:13px;<?= $q['completed'] ? 'color:var(--accent-gold)' : '' ?>">
                        <?= $q['completed'] ? '已完成' : (min($q['progress'], $q['condition_value']) . '/' . $q['condition_value']) ?>
                    </div>
                    <?php if (!$q['completed']): ?>
                    <div style="width:100px;height:4px;background:var(--bg-secondary);border-radius:2px;overflow:hidden;margin-top:2px">
                        <div style="width:<?= min(100, ($q['progress'] / max(1, $q['condition_value'])) * 100) ?>%;height:100%;background:var(--accent-gold);border-radius:2px"></div>
                    </div>
                    <?php endif; ?>
                    <div class="text-muted" style="font-size:11px">🪙 +<?= $q['reward_tokens'] ?></div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>
</div>
<?php include __DIR__ . '/layout/footer.php'; ?>
