<?php
/**
 * OIManka - Stock Market
 */
$pageTitle = '股票';

$category = $_GET['category'] ?? null;
$sort = $_GET['sort'] ?? 'market_cap';
$order = $_GET['order'] ?? 'DESC';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

try {
    $categories = StockEngine::getCategories();
    $stocks = StockEngine::getStocks([
        'category' => $category,
        'sort' => $sort,
        'order' => $order,
        'limit' => $perPage,
        'offset' => ($page - 1) * $perPage,
    ]);
    $totalStocks = StockEngine::getStockCount($category);
    $totalPages = ceil($totalStocks / $perPage);
    $summary = StockEngine::getMarketSummary();
} catch (Exception $e) {
    $categories = [];
    $stocks = [];
    $totalStocks = 0;
    $totalPages = 0;
    $summary = null;
}

include __DIR__ . '/layout/header.php';
?>

<div class="page-market">
    <div class="page-header">
        <h1>📈 股票</h1>
        <?php if (Session::isLoggedIn()): ?>
        <a href="<?= url('/portfolio.php') ?>" class="btn btn-outline">📦 我的持仓</a>
        <?php endif; ?>
    </div>

    <!-- Market Summary Bar -->
    <?php if ($summary): ?>
    <div class="market-summary-bar">
        <div class="ms-item">
            <span class="ms-label">总市值</span>
            <span class="ms-value">🪙 <?= nf($summary['total_market_cap'], 0) ?></span>
        </div>
        <div class="ms-item">
            <span class="ms-label">上市数量</span>
            <span class="ms-value"><?= $summary['total_stocks'] ?></span>
        </div>
        <div class="ms-item">
            <span class="ms-label">上涨</span>
            <span class="ms-value text-green"><?= $summary['gainers'] ?></span>
        </div>
        <div class="ms-item">
            <span class="ms-label">下跌</span>
            <span class="ms-value text-red"><?= $summary['losers'] ?></span>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="market-filters">
        <div class="filter-categories">
            <a href="?<?= http_build_query(array_merge($_GET, ['category' => null, 'page' => 1])) ?>"
               class="filter-tag <?= $category === null ? 'active' : '' ?>">全部</a>
            <?php foreach ($categories as $cat): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['category' => $cat, 'page' => 1])) ?>"
               class="filter-tag <?= $category === $cat ? 'active' : '' ?>">
                <?= htmlspecialchars($cat) ?>
            </a>
            <?php endforeach; ?>
        </div>

        <div class="filter-sorts">
            <span class="sort-label">排序:</span>
            <?php
            $sorts = [
                'market_cap' => '市值',
                'current_price' => '价格',
                'price_change_pct' => '涨跌幅',
                'volume_24h' => '成交量',
            ];
            foreach ($sorts as $key => $label):
                $nextOrder = ($sort === $key && $order === 'DESC') ? 'ASC' : 'DESC';
                $arrow = $sort === $key ? ($order === 'DESC' ? '▼' : '▲') : '';
            ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['sort' => $key, 'order' => $nextOrder, 'page' => 1])) ?>"
               class="sort-link <?= $sort === $key ? 'active' : '' ?>">
                <?= $label ?><?= $arrow ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Stock Table -->
    <div class="stock-table-wrapper">
        <table class="stock-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>股票代码</th>
                    <th>名称</th>
                    <th>分类</th>
                    <th>稀有度</th>
                    <th>价格</th>
                    <th>涨跌</th>
                    <th>市值</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stocks as $i => $s): ?>
                <tr class="stock-row" data-href="<?= url('/market_detail.php') ?>?id=<?= $s['id'] ?>">
                    <td class="td-rank"><?= ($page - 1) * $perPage + $i + 1 ?></td>
                    <td>
                        <span class="stock-symbol-table <?= $s['rarity'] ?>">
                            <?= htmlspecialchars($s['symbol']) ?>
                        </span>
                    </td>
                    <td>
                        <a href="<?= url('/market_detail.php') ?>?id=<?= $s['id'] ?>" class="stock-name-link">
                            <?= htmlspecialchars($s['name']) ?>
                        </a>
                    </td>
                    <td>
                        <span class="category-badge"><?= htmlspecialchars($s['category']) ?></span>
                    </td>
                    <td>
                        <span class="rarity-badge <?= !empty($s['limited_edition']) ? 'limited' : $s['rarity'] ?>">
                            <?= !empty($s['limited_edition']) ? '绝版' : (GachaEngine::RARITY_NAMES[$s['rarity']] ?? $s['rarity']) ?>
                        </span>
                    </td>
                    <td class="td-price">🪙 <?= nf($s['current_price'], 2) ?></td>
                    <td class="td-change <?= $s['price_change_pct'] >= 0 ? 'text-green' : 'text-red' ?>">
                        <?= $s['price_change_pct'] >= 0 ? '+' : '' ?><?= $s['price_change_pct'] ?>%
                    </td>
                    <td class="td-cap"><?= nf($s['market_cap'], 0) ?></td>
                    <td class="td-actions">
                        <?php if (Session::isLoggedIn()): ?>
                        <button class="btn btn-xs btn-primary btn-buy"
                                onclick="event.stopPropagation(); quickBuy(<?= $s['id'] ?>, '<?= htmlspecialchars($s['name'], ENT_QUOTES) ?>')">
                            买入
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($stocks)): ?>
                <tr><td colspan="9" class="empty-state">暂无股票数据</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="page-link">上一页</a>
        <?php endif; ?>

        <?php
        $startPage = max(1, $page - 2);
        $endPage = min($totalPages, $page + 2);
        for ($p = $startPage; $p <= $endPage; $p++):
        ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"
           class="page-link <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="page-link">下一页</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
function quickBuy(stockId, stockName) {
    const qty = prompt('买入 ' + stockName + '\n请输入购买数量:', '1');
    if (!qty || isNaN(qty) || parseInt(qty) <= 0) return;

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?= url('/market_buy.php') ?>';
    form.innerHTML = '<input name="stock_id" value="' + stockId + '">'
                   + '<input name="quantity" value="' + parseInt(qty) + '">';
    document.body.appendChild(form);
    form.submit();
}
</script>

<?php include __DIR__ . '/layout/footer.php'; ?>
