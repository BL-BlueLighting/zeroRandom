<?php
/**
 * OIManka - Stock Detail
 */
$pageTitle = '股票详情';

$stockId = (int)($_GET['id'] ?? 0);
if ($stockId <= 0) {
    header('Location: ' . url('/market.php'));
    exit;
}

$stock = StockEngine::getStock($stockId);
if (!$stock) {
    http_response_code(404);
    $pageTitle = '未找到';
    include __DIR__ . '/layout/header.php';
    echo '<div class="container"><h1>股票未找到</h1><p>该股票不存在或已下架。</p></div>';
    include __DIR__ . '/layout/footer.php';
    exit;
}

$priceHistory = StockEngine::getPriceHistory($stockId);
$metadata = $stock['metadata'] ?? [];

// User's holding (if logged in)
$userHolding = null;
if (Session::isLoggedIn()) {
    $db = Database::getInstance();
    $stmt = $db->prepare("SELECT * FROM holdings WHERE user_id = ? AND stock_id = ?");
    $stmt->execute([Session::userId(), $stockId]);
    $userHolding = $stmt->fetch();
}

include __DIR__ . '/layout/header.php';
?>

<div class="page-market-detail">
    <a href="<?= url('/market.php') ?>" class="back-link">← 返回股市</a>

    <div class="stock-detail-header">
        <div class="sdh-left">
            <div class="sdh-title-row">
                <span class="rarity-badge large <?= !empty($stock['limited_edition']) ? 'limited' : $stock['rarity'] ?>">
                    <?= !empty($stock['limited_edition']) ? '绝版' : (GachaEngine::RARITY_NAMES[$stock['rarity']] ?? $stock['rarity']) ?>
                </span>
                <h1>
                    <span class="stock-symbol-big"><?= htmlspecialchars($stock['symbol']) ?></span>
                    <?= htmlspecialchars($stock['name']) ?>
                </h1>
            </div>
            <div class="sdh-meta">
                <span class="category-badge"><?= htmlspecialchars($stock['category']) ?></span>
                <span>来源: <?= htmlspecialchars($stock['adapter_name']) ?></span>
            </div>
        </div>
        <div class="sdh-right">
            <div class="sdh-price">
                <span class="price-big">🪙 <?= nf($stock['current_price'], 2) ?></span>
                <span class="price-change-big <?= $stock['price_change_pct'] >= 0 ? 'text-green' : 'text-red' ?>">
                    <?= $stock['price_change_pct'] >= 0 ? '+' : '' ?><?= $stock['price_change_pct'] ?>%
                </span>
            </div>
        </div>
    </div>

    <!-- Price Chart -->
    <div class="chart-container">
        <canvas id="priceChart"></canvas>
    </div>

    <!-- Stats Grid -->
    <div class="detail-stats-grid">
        <div class="detail-stat">
            <div class="ds-label">市值</div>
            <div class="ds-value">🪙 <?= nf($stock['market_cap'], 0) ?></div>
        </div>
        <div class="detail-stat">
            <div class="ds-label">流通量</div>
            <div class="ds-value"><?= nf($stock['circulating_supply']) ?></div>
        </div>
        <div class="detail-stat">
            <div class="ds-label">24h 成交量</div>
            <div class="ds-value"><?= nf($stock['volume_24h']) ?></div>
        </div>
        <div class="detail-stat">
            <div class="ds-label">AC 率</div>
            <div class="ds-value">
                <?= isset($metadata['ac_ratio']) ? round($metadata['ac_ratio'] * 100, 1) . '%' : 'N/A' ?>
            </div>
        </div>
        <div class="detail-stat">
            <div class="ds-label">AC / 提交</div>
            <div class="ds-value">
                <?= ($metadata['ac_count'] ?? '?') ?> / <?= ($metadata['submit_count'] ?? '?') ?>
            </div>
        </div>
        <div class="detail-stat">
            <div class="ds-label">难度</div>
            <div class="ds-value">⭐ <?= $metadata['difficulty'] ?? 'N/A' ?></div>
        </div>
    </div>

    <!-- Trade Form -->
    <?php if (Session::isLoggedIn()): ?>
    <div class="trade-section">
        <h2>⚡ 交易</h2>
        <?php if ($userHolding): ?>
        <div class="holding-info">
            持仓: <strong><?= $userHolding['quantity'] ?></strong> 股 |
            均价: <strong>🪙 <?= nf($userHolding['avg_cost'], 2) ?></strong> |
            市值: <strong>🪙 <?= nf($userHolding['quantity'] * $stock['current_price'], 2) ?></strong>
            <?php $pl = ($stock['current_price'] - $userHolding['avg_cost']) * $userHolding['quantity']; ?>
            | 盈亏: <strong class="<?= $pl >= 0 ? 'text-green' : 'text-red' ?>"><?= $pl >= 0 ? '+' : '' ?><?= nf($pl, 2) ?></strong>
        </div>
        <?php endif; ?>

        <div class="trade-forms">
            <form method="POST" action="<?= url('/market_buy.php') ?>" class="trade-form buy-form">
                <input type="hidden" name="stock_id" value="<?= $stockId ?>">
                <label>买入数量</label>
                <input type="number" name="quantity" min="1" value="1" class="trade-input">
                <?php
                $buyPremium = (!empty($stock['limited_edition']) && $userHolding && $userHolding['quantity'] > 0) ? 1.15 : 1.3;
                $buyPct = $buyPremium === 1.15 ? '+15%' : '+30%';
                ?>
                <div class="trade-preview">
                    预计花费: 🪙 <span class="preview-amount" data-price="<?= round($stock['current_price'] * $buyPremium, 2) ?>">
                        <?= nf($stock['current_price'] * $buyPremium, 2) ?>
                    </span>
                    <span class="text-muted">(市价 <?= nf($stock['current_price'], 2) ?> <?= $buyPct ?>)</span>
                    (含 <?= TRADE_FEE_PCT ?>% 手续费)
                </div>
                <button type="submit" class="btn btn-primary btn-block">📈 买入</button>
            </form>

            <?php if ($userHolding && $userHolding['quantity'] > 0): ?>
            <form method="POST" action="<?= url('/market_sell.php') ?>" class="trade-form sell-form" id="sellForm">
                <input type="hidden" name="stock_id" value="<?= $stockId ?>">
                <label>卖出数量</label>
                <input type="number" name="quantity" id="sellQty" min="1" max="<?= $userHolding['quantity'] ?>" value="1" class="trade-input">
                <div class="trade-preview">
                    预计获得: 🪙 <span class="preview-amount" data-price="<?= $stock['current_price'] ?>">
                        <?= nf($stock['current_price'], 2) ?>
                    </span>
                    (含 <?= TRADE_FEE_PCT ?>% 手续费)
                </div>
                <div style="display:flex;gap:8px">
                    <button type="submit" class="btn btn-danger" style="flex:1">📉 卖出</button>
                    <button type="button" class="btn btn-danger" style="flex:1" onclick="document.getElementById('sellQty').value=<?= $userHolding['quantity'] ?>;document.getElementById('sellForm').submit()">📉 全部卖出</button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="trade-section">
        <p class="text-muted">请 <a href="<?= url('/login.php') ?>">登录</a> 后进行交易。</p>
    </div>
    <?php endif; ?>
</div>

<script>
// Price chart
const priceData = <?= json_encode($priceHistory, JSON_UNESCAPED_UNICODE) ?>;
if (priceData.length > 0) {
    const ctx = document.getElementById('priceChart').getContext('2d');
    const labels = priceData.map(p => p.recorded_at);
    const prices = priceData.map(p => parseFloat(p.price));

    const gradient = ctx.createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, 'rgba(255, 107, 157, 0.3)');
    gradient.addColorStop(1, 'rgba(255, 107, 157, 0.0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: '价格',
                data: prices,
                borderColor: '#ff6b9d',
                backgroundColor: gradient,
                borderWidth: 2,
                fill: true,
                tension: 0.3,
                pointRadius: 0,
                pointHitRadius: 10,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => '🪙 ' + parseFloat(ctx.raw).toFixed(2)
                    }
                }
            },
            scales: {
                x: {
                    ticks: { color: '#666', maxTicksLimit: 10 },
                    grid: { color: '#1a1a3a' }
                },
                y: {
                    ticks: { color: '#666', callback: v => '🪙' + v },
                    grid: { color: '#1a1a3a' }
                }
            }
        }
    });
}
</script>

<?php include __DIR__ . '/layout/footer.php'; ?>
