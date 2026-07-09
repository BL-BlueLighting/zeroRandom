<?php include __DIR__ . '/layout/header.php'; ?>
<div class="page-market" style="max-width:700px;margin:0 auto">
    <div class="page-header">
        <h1>👤 <?= htmlspecialchars($profileUser['username']) ?> 的主页</h1>
    </div>

    <div class="detail-stats-grid">
        <div class="detail-stat"><div class="ds-label">净资产</div><div class="ds-value">🪙 <?= nf($stats['net_worth'] ?? 0, 1) ?></div></div>
        <div class="detail-stat"><div class="ds-label">代币余额</div><div class="ds-value">🪙 <?= nf($stats['token_balance'] ?? 0, 1) ?></div></div>
        <div class="detail-stat"><div class="ds-label">持仓市值</div><div class="ds-value">🪙 <?= nf($summary['total_value'] ?? 0, 1) ?></div></div>
        <div class="detail-stat"><div class="ds-label">累计签到</div><div class="ds-value">📅 <?= $checkinStats['total'] ?>天</div></div>
        <div class="detail-stat"><div class="ds-label">抽卡次数</div><div class="ds-value">🎲 <?= $gachaStats['total_pulls'] ?? 0 ?></div></div>
        <div class="detail-stat"><div class="ds-label">持有股票</div><div class="ds-value">📈 <?= $summary['total_stocks'] ?? 0 ?></div></div>
    </div>

    <!-- Best Cards -->
    <section class="section">
        <h2 class="section-title">⭐ 优秀卡片</h2>
        <?php
        $cards = $bestCards->fetchAll();
        if (!empty($cards)): ?>
        <div class="stock-mini-list">
        <?php foreach ($cards as $c): ?>
            <a href="<?= url('/market_detail.php') ?>?id=<?= $c['stock_id'] ?>" class="stock-mini-item">
                <div class="stock-mini-header">
                    <span class="rarity-badge small <?= $c['rarity'] ?>"><?= GachaEngine::RARITY_NAMES[$c['rarity']] ?? $c['rarity'] ?></span>
                    <span class="stock-symbol <?= $c['rarity'] ?>"><?= htmlspecialchars($c['symbol']) ?></span>
                    <span class="stock-name"><?= htmlspecialchars($c['stock_name']) ?></span>
                </div>
                <div class="stock-mini-meta">
                    <span class="stock-price">🪙 <?= nf($c['market_value'], 2) ?></span>
                    <span>x<?= $c['quantity'] ?></span>
                </div>
            </a>
        <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="text-muted">暂无持仓</p>
        <?php endif; ?>
    </section>

    <!-- Achievements -->
    <section class="section">
        <h2 class="section-title">🏆 成就</h2>
        <?php
        $achs = $achievements->fetchAll();
        if (!empty($achs)): ?>
        <div class="tx-list">
        <?php foreach ($achs as $a): ?>
            <div class="tx-item">
                <span class="tx-icon">🏆</span>
                <div class="tx-body">
                    <div class="tx-title"><?= htmlspecialchars($a['name']) ?></div>
                    <div class="tx-meta"><?= htmlspecialchars($a['description'] ?? '') ?></div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="text-muted">暂未获得成就</p>
        <?php endif; ?>
    </section>

    <!-- Contact Info -->
    <?php
    $contactQQ = platform_config('system', 'contact_qq', '');
    $contactQQQR = platform_config('system', 'contact_qq_qr', '');
    $contactWXQR = platform_config('system', 'contact_wx_qr', '');
    if ($contactQQ || $contactQQQR || $contactWXQR):
    ?>
    <section class="section">
        <h2 class="section-title">💬 加入我们</h2>
        <div style="display:flex;gap:20px;flex-wrap:wrap">
            <?php if ($contactQQ): ?>
            <div class="detail-stat" style="flex:1;min-width:180px">
                <div class="ds-label">🐧 QQ 群</div>
                <div class="ds-value" style="font-size:20px"><?= htmlspecialchars($contactQQ) ?></div>
                <?php if ($contactQQQR): ?>
                <img src="<?= url('/' . $contactQQQR) ?>" alt="QQ群二维码" style="max-width:180px;margin-top:8px;border-radius:8px">
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php if ($contactWXQR): ?>
            <div class="detail-stat" style="flex:1;min-width:180px">
                <div class="ds-label">💚 微信</div>
                <img src="<?= url('/' . $contactWXQR) ?>" alt="微信二维码" style="max-width:180px;margin-top:8px;border-radius:8px">
            </div>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/layout/footer.php'; ?>
