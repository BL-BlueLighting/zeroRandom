<?php $pageTitle = '卡牌市场'; include __DIR__ . '/layout/header.php'; ?>
<div class="page-market">
    <div class="page-header">
        <h1>🔄 卡牌市场</h1>
        <?php if (Session::isLoggedIn()): ?>
        <a href="<?= url('/portfolio.php') ?>" class="btn btn-outline">📦 我的持仓</a>
        <?php endif; ?>
    </div>

    <div class="market-summary-bar">
        <div class="ms-item"><span class="ms-label">在售</span><span class="ms-value"><?= $totalListings ?></span></div>
    </div>

    <?php if (empty($listings)): ?>
    <div class="empty-state"><p>📭 市场暂无在售卡牌</p></div>
    <?php else: ?>
    <div class="stock-table-wrapper">
        <table class="stock-table"><thead><tr>
            <th>卡牌</th><th>稀有度</th><th>数量</th><th>单价</th><th>市场价</th><th>卖家</th><th>操作</th>
        </tr></thead><tbody>
        <?php foreach ($listings as $l): ?>
        <tr>
            <td><strong><?= htmlspecialchars($l['symbol']) ?></strong> <?= htmlspecialchars($l['stock_name']) ?></td>
            <td><span class="rarity-badge small <?= !empty($l['limited_edition']) ? 'limited' : $l['rarity'] ?>"><?= !empty($l['limited_edition']) ? '绝版' : (GachaEngine::RARITY_NAMES[$l['rarity']] ?? $l['rarity']) ?></span></td>
            <td><?= $l['quantity'] ?></td>
            <td>🪙 <?= number_format($l['price'], 2) ?></td>
            <td class="text-muted">🪙 <?= number_format($l['current_price'], 2) ?></td>
            <td><?= htmlspecialchars($l['seller_name']) ?></td>
            <td>
                <?php if (Session::isLoggedIn() && $l['seller_id'] != Session::userId()): ?>
                <form method="POST" action="<?= url('/card_market_buy.php') ?>" style="display:inline">
                    <input type="hidden" name="listing_id" value="<?= $l['id'] ?>">
                    <button class="btn btn-xs btn-primary" onclick="return confirm('花费 🪙<?= number_format($l['price'] * $l['quantity'], 2) ?> 购买此卡牌？')">购买</button>
                </form>
                <?php elseif ($l['seller_id'] == Session::userId()): ?>
                <span class="text-muted">自己的</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody></table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <a href="?page=<?= $p ?>" class="page-link <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <?php if (Session::isLoggedIn() && !empty($myListings)): ?>
    <section class="section" style="margin-top:24px">
        <h2 class="section-title">📋 我的挂单</h2>
        <div class="stock-table-wrapper">
            <table class="stock-table"><thead><tr><th>卡牌</th><th>数量</th><th>价格</th><th>状态</th><th>操作</th></tr></thead><tbody>
            <?php foreach ($myListings as $l): ?>
            <tr>
                <td><?= htmlspecialchars($l['symbol']) ?> <?= htmlspecialchars($l['stock_name']) ?></td>
                <td><?= $l['quantity'] ?></td>
                <td>🪙 <?= number_format($l['price'], 2) ?></td>
                <td><?= $l['status'] === 'listed' ? '📌在售' : '✅已售' ?></td>
                <td><?php if ($l['status'] === 'listed'): ?>
                    <form method="POST" action="<?= url('/card_market_cancel.php') ?>" style="display:inline">
                        <input type="hidden" name="listing_id" value="<?= $l['id'] ?>">
                        <button class="btn btn-xs btn-danger">取消</button>
                    </form>
                <?php endif; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody></table>
        </div>
    </section>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/layout/footer.php'; ?>
