<?php
/**
 * zero Random - Home / Dashboard
 */
$pageTitle = '首页';

try {
    $summary = StockEngine::getMarketSummary();
    $topStocks = StockEngine::getStocks(['limit' => 5, 'sort' => 'market_cap', 'order' => 'DESC']);
    $gainers = StockEngine::getStocks(['limit' => 5, 'sort' => 'price_change_pct', 'order' => 'DESC']);
    $losers = StockEngine::getStocks(['limit' => 5, 'sort' => 'price_change_pct', 'order' => 'ASC']);
} catch (Exception $e) {
    $summary = null;
    $topStocks = [];
    $gainers = [];
    $losers = [];
}

include __DIR__ . '/layout/header.php';
?>

<div class="page-home">
    <!-- Hero -->
    <section class="hero">
        <div class="hero-content">
            <h1 class="hero-title">🎰 <i>zero</i>Random</h1>
            <p class="hero-subtitle">
                股票价值与题目难度，AC 数量与 Token 1:10.<br/>
                WELCOME TO zeroRandom!
            </p>
            <div class="hero-actions">
                <a href="./market.php" class="btn btn-primary btn-lg">📈 股市</a>
                <a href="./gacha.php" class="btn btn-accent btn-lg">🎲 抽卡</a>
            </div>
        </div>
    </section>

    <!-- Market Overview -->
    <?php if ($summary): ?>
    <section class="section">
        <h2 class="section-title">📊 概览</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">上市股票</div>
                <div class="stat-value"><?= $summary['total_stocks'] ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">总市值</div>
                <div class="stat-value">🪙 <?= nf($summary['total_market_cap'], 0) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">平均涨跌</div>
                <div class="stat-value <?= $summary['avg_change_pct'] >= 0 ? 'text-green' : 'text-red' ?>">
                    <?= $summary['avg_change_pct'] >= 0 ? '+' : '' ?><?= $summary['avg_change_pct'] ?>%
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-label">上涨 / 下跌</div>
                <div class="stat-value">
                    <span class="text-green"><?= $summary['gainers'] ?>↑</span>
                    <span class="stat-sep">/</span>
                    <span class="text-red"><?= $summary['losers'] ?>↓</span>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Top Gainers & Losers -->
    <div class="two-col">
        <section class="section">
            <h2 class="section-title">🚀 涨幅榜</h2>
            <div class="stock-mini-list">
                <?php foreach ($gainers as $s): ?>
                <a href="./market_detail.php?id=<?= $s['id'] ?>" class="stock-mini-item">
                    <div class="stock-mini-header">
                        <span class="rarity-badge small <?= !empty($s['limited_edition']) ? 'limited' : $s['rarity'] ?>"><?= !empty($s['limited_edition']) ? '绝版' : (GachaEngine::RARITY_NAMES[$s['rarity']] ?? $s['rarity']) ?></span>
                        <span class="stock-symbol <?= $s['rarity'] ?>"><?= htmlspecialchars($s['symbol']) ?></span>
                        <span class="stock-name"><?= htmlspecialchars($s['name']) ?></span>
                    </div>
                    <div class="stock-mini-meta">
                        <span class="stock-price">🪙 <?= nf($s['current_price'], 2) ?></span>
                        <span class="price-change text-green">+<?= $s['price_change_pct'] ?>%</span>
                    </div>
                </a>
                <?php endforeach; ?>
                <?php if (empty($gainers)): ?>
                    <div class="empty-state">暂无数据</div>
                <?php endif; ?>
            </div>
        </section>

        <section class="section">
            <h2 class="section-title">📉 跌幅榜</h2>
            <div class="stock-mini-list">
                <?php foreach ($losers as $s): ?>
                <a href="./market_detail.php?id=<?= $s['id'] ?>" class="stock-mini-item">
                    <div class="stock-mini-header">
                        <span class="rarity-badge small <?= !empty($s['limited_edition']) ? 'limited' : $s['rarity'] ?>"><?= !empty($s['limited_edition']) ? '绝版' : (GachaEngine::RARITY_NAMES[$s['rarity']] ?? $s['rarity']) ?></span>
                        <span class="stock-symbol <?= $s['rarity'] ?>"><?= htmlspecialchars($s['symbol']) ?></span>
                        <span class="stock-name"><?= htmlspecialchars($s['name']) ?></span>
                    </div>
                    <div class="stock-mini-meta">
                        <span class="stock-price">🪙 <?= nf($s['current_price'], 2) ?></span>
                        <span class="price-change text-red"><?= $s['price_change_pct'] ?>%</span>
                    </div>
                </a>
                <?php endforeach; ?>
                <?php if (empty($losers)): ?>
                    <div class="empty-state">暂无数据</div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <!-- Top Market Cap -->
    <section class="section">
        <h2 class="section-title">🏆 市值最高</h2>
        <div class="stock-mini-list">
            <?php foreach ($topStocks as $s): ?>
            <a href="./market_detail.php?id=<?= $s['id'] ?>" class="stock-mini-item">
                <div class="stock-mini-header">
                    <span class="rarity-badge <?= $s['rarity'] ?>"><?= GachaEngine::RARITY_NAMES[$s['rarity']] ?? $s['rarity'] ?></span>
                    <span class="stock-symbol"><?= htmlspecialchars($s['symbol']) ?></span>
                    <span class="stock-name"><?= htmlspecialchars($s['name']) ?></span>
                </div>
                <div class="stock-mini-meta">
                    <span class="stock-cat"><?= htmlspecialchars($s['category']) ?></span>
                    <span class="stock-price">🪙 <?= nf($s['current_price'], 2) ?></span>
                    <span class="stock-cap">市值: <?= nf($s['market_cap'], 0) ?></span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- How it works -->
    <section class="section">
        <h2 class="section-title">💡 玩法说明</h2>
        <div class="info-cards">
            <div class="info-card">
                <div class="info-icon">📈</div>
                <h3>股票市场</h3>
                <p>题目就是股票！价格由 AC/提交 比率和热度驱动，题目热度越低越稀有越值钱。低买高卖，赚取代币差价。</p>
            </div>
            <div class="info-card">
                <div class="info-icon">🎲</div>
                <h3>扭蛋抽卡</h3>
                <p>花费代币抽取随机题目卡片！单抽10币，十连90币（保底稀有），百连850币（保底史诗）。收集传说卡片，炫耀你的收藏！</p>
            </div>
            <div class="info-card">
                <div class="info-icon">💰</div>
                <h3>每日盈利</h3>
                <p>将你的持仓卡牌<strong>放置</strong>到展示位！每次卡牌价格增幅或跌落，你的资产都会有反应。放置的卡牌越多，每日被动收益越可观！</p>
            </div>
        </div>
    </section>
</div>

<?php include __DIR__ . '/layout/footer.php'; ?>
