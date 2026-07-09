<?php $pageTitle = 'Kaleidoscope 天·界'; include __DIR__ . '/layout/header.php'; ?>
<div class="page-auth">
    <div class="auth-card" style="max-width:500px">
        <h1 class="auth-title">🌌 Kaleidoscope · 天界</h1>
        <p class="auth-subtitle">第二层市场，独立股市，独立代币</p>

        <?php if ($message): ?><div class="flash-message flash-success"><?= $message ?></div><?php endif; ?>
        <?php if ($error): ?><div class="flash-message flash-error"><?= $error ?></div><?php endif; ?>

        <div class="detail-stats-grid" style="margin-bottom:16px">
            <div class="detail-stat"><div class="ds-label">默认代币</div><div class="ds-value">🪙 <?= nf($balance, 1) ?></div></div>
            <div class="detail-stat"><div class="ds-label">Kaleidoscope 代币</div><div class="ds-value" style="color:var(--accent)">🌀 <?= nf($ksBalance, 1) ?></div></div>
        </div>

        <?php if ($isActive): ?>
        <div class="success" style="text-align:center;padding:12px;border-radius:8px;margin-bottom:12px">
            ✅ 已进入 Kaleidoscope，有效期至 <?= $expiry ?>
        </div>
        <form method="POST" style="display:flex;gap:8px">
            <input type="hidden" name="action" value="switch">
            <button class="btn btn-primary btn-block">🌌 进入 Kaleidoscope</button>
        </form>
        <?php else: ?>
            <?php if ($isAdmin || $windowOpen): ?>
            <form method="POST">
                <input type="hidden" name="action" value="enter">
                <?php if ($isAdmin): ?>
                <p class="text-muted" style="margin-bottom:12px">管理员免费进入</p>
                <button class="btn btn-primary btn-block btn-lg">🌌 免费进入 Kaleidoscope</button>
                <?php else: ?>
                <p class="text-muted" style="margin-bottom:12px">支付 <?= nf(KALEIDOSCOPE_ENTRY_FEE) ?> 代币进入，有效期 24 小时</p>
                <button class="btn btn-primary btn-block btn-lg">🌌 支付进入 (<?= nf(KALEIDOSCOPE_ENTRY_FEE) ?>)</button>
                <?php endif; ?>
            </form>
            <?php else: ?>
            <p class="text-muted" style="text-align:center;margin:12px 0">
                ⏰ 入口开放时间：<?= $entryStart ?>:00 ~ <?= $entryEnd ?>:00
            </p>
            <?php endif; ?>

            <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border)">
                <h3 style="font-size:15px;margin-bottom:8px">💰 代币兑换</h3>
                <p class="text-muted" style="font-size:13px;margin-bottom:8px">默认代币 → Kaleidoscope 代币（1 : <?= nf(KALEIDOSCOPE_CONVERT_RATE) ?>）</p>
                <form method="POST" style="display:flex;gap:8px">
                    <input type="hidden" name="action" value="convert">
                    <input type="number" name="convert_amount" min="1" step="1" class="form-input" style="flex:1" placeholder="Kaleidoscope 代币数量" required>
                    <button class="btn btn-accent">兑换</button>
                </form>
                <p class="text-muted" style="font-size:12px;margin-top:4px">消耗默认代币: <span id="convertCost">0</span></p>
            </div>
        <?php endif; ?>
    </div>
</div>
<script>
document.querySelector('[name="convert_amount"]')?.addEventListener('input', function() {
    document.getElementById('convertCost').textContent = nf(parseFloat(this.value || 0) * <?= KALEIDOSCOPE_CONVERT_RATE ?>);
});
</script>
<?php include __DIR__ . '/layout/footer.php'; ?>
