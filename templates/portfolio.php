<?php
/**
 * zero Random - Portfolio with Card Placement
 */
$pageTitle = '我的持仓';

$userId = Session::userId();
$db = Database::getInstance();
$stats = TokenSystem::getUserStats($userId);
$sort = $_GET['sort'] ?? 'value';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$summary = TradingEngine::getPortfolioSummary($userId, $sort);
$allHoldings = $summary['holdings'] ?? [];
$totalHoldings = count($allHoldings);
$totalPages = max(1, ceil($totalHoldings / $perPage));
$holdings = array_slice($allHoldings, ($page - 1) * $perPage, $perPage);

// Get placed cards
$stmt = $db->prepare("
    SELECT cp.*, s.symbol, s.name as stock_name, s.current_price, s.price_change_pct, s.rarity, s.limited_edition
    FROM card_placements cp
    JOIN stocks s ON cp.stock_id = s.id
    WHERE cp.user_id = ?
    ORDER BY cp.slot
");
$stmt->execute([$userId]);
$placedCards = $stmt->fetchAll();
$placedSlots = array_column($placedCards, 'slot');

// Calculate daily earnings from placed cards
$dailyEarnings = 0;
foreach ($placedCards as $pc) {
    $changePct = (float)$pc['price_change_pct'];
    $price = (float)$pc['current_price'];
    // Daily earning = abs(change_pct) * price * 0.01 (simulated daily passive income)
    $dailyEarnings += abs($changePct) * $price * 0.01;
}
$dailyEarnings = round($dailyEarnings, 2);

// Handle placement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'place') {
        $stockId = (int)($_POST['stock_id'] ?? 0);
        $slot = (int)($_POST['slot'] ?? 1);
        if ($stockId && $slot >= 1 && $slot <= 6) {
            // Check user has this stock
            $check = $db->prepare("SELECT quantity FROM holdings WHERE user_id = ? AND stock_id = ? AND quantity > 0");
            $check->execute([$userId, $stockId]);
            if ($check->fetch()) {
                try {
                    $db->prepare("
                        INSERT INTO card_placements (user_id, stock_id, slot)
                        VALUES (?, ?, ?)
                        ON CONFLICT(user_id, slot) DO UPDATE SET stock_id = excluded.stock_id
                    ")->execute([$userId, $stockId, $slot]);
                    Session::flash('success', '卡牌已放置到展示位！');
                } catch (Exception $e) {
                    Session::flash('error', '放置失败: ' . $e->getMessage());
                }
            }
        }
    }
    if ($action === 'remove') {
        $slot = (int)($_POST['slot'] ?? 0);
        $db->prepare("DELETE FROM card_placements WHERE user_id = ? AND slot = ?")->execute([$userId, $slot]);
        Session::flash('success', '卡牌已从展示位移除。');
    }
    header('Location: ' . url('/portfolio.php'));
    exit;
}

include __DIR__ . '/layout/header.php';
?>

<div class="page-portfolio">
    <div class="page-header">
        <h1>📦 我的持仓</h1>
        <span class="token-display large">🪙 <?= nf($stats['token_balance'] ?? 0, 1) ?></span>
    </div>

    <!-- Portfolio Stats -->
    <div class="portfolio-stats">
        <div class="pstat-card">
            <div class="pstat-label">总资产</div>
            <div class="pstat-value">🪙 <?= nf($stats['net_worth'] ?? 0, 2) ?></div>
            <div class="pstat-sub">代币 + 持仓市值</div>
        </div>
        <div class="pstat-card">
            <div class="pstat-label">持仓市值</div>
            <div class="pstat-value">🪙 <?= nf($summary['total_value'] ?? 0, 2) ?></div>
        </div>
        <div class="pstat-card">
            <div class="pstat-label">总盈亏</div>
            <div class="pstat-value <?= ($summary['total_pl'] ?? 0) >= 0 ? 'text-green' : 'text-red' ?>">
                <?= ($summary['total_pl'] ?? 0) >= 0 ? '+' : '' ?><?= nf($summary['total_pl'] ?? 0, 2) ?>
            </div>
            <?php $withdrawable = max(0, $summary['total_pl'] ?? 0); if ($withdrawable > 0): ?>
            <div class="pstat-sub">
                <form method="POST" action="<?= url('/portfolio_withdraw.php') ?>" style="display:inline" onsubmit="return confirm('确定提现 <?= nf($withdrawable, 2) ?> 枚代币？提现后持仓均价将重置为当前价。')">
                    <button class="btn btn-xs btn-primary" style="margin-top:4px;font-size:12px;padding:2px 10px">💰 提现盈利</button>
                </form>
            </div>
            <?php endif; ?>
        </div>
        <div class="pstat-card">
            <div class="pstat-label">每日预估收益</div>
            <div class="pstat-value text-green">🪙 +<?= nf($dailyEarnings, 2) ?></div>
            <div class="pstat-sub">来自 <?= count($placedCards) ?> 张放置卡牌</div>
        </div>
    </div>

    <!-- Card Placement Slots -->
    <section class="section">
        <h2 class="section-title">🃏 卡牌放置展示</h2>
        <p class="text-muted" style="margin-bottom:16px">
            💡 将你的持仓卡牌放置到展示位，每次卡牌价格增幅或跌落，你的资产都会有反应！放置的卡牌越多，每日被动收益越可观。最多放置 6 张。
        </p>
        <div class="placement-slots">
            <?php for ($slot = 1; $slot <= 6; $slot++): ?>
            <?php $placed = null; foreach ($placedCards as $pc) { if ((int)$pc['slot'] === $slot) { $placed = $pc; break; } } ?>
            <div class="placement-slot <?= $placed ? 'filled' : 'empty' ?>">
                <?php if ($placed): ?>
                <div class="ps-card">
                    <span class="rarity-badge <?= !empty($placed['limited_edition']) ? 'limited' : $placed['rarity'] ?>"><?= !empty($placed['limited_edition']) ? '绝版' : (GachaEngine::RARITY_NAMES[$placed['rarity']] ?? $placed['rarity']) ?></span>
                    <div class="ps-symbol"><?= htmlspecialchars($placed['symbol']) ?></div>
                    <div class="ps-name"><?= htmlspecialchars($placed['stock_name']) ?></div>
                    <div class="ps-price">🪙 <?= nf($placed['current_price'], 2) ?></div>
                    <div class="ps-change <?= $placed['price_change_pct'] >= 0 ? 'text-green' : 'text-red' ?>">
                        <?= $placed['price_change_pct'] >= 0 ? '+' : '' ?><?= $placed['price_change_pct'] ?>%
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="slot" value="<?= $slot ?>">
                        <button class="btn btn-xs btn-danger">移除</button>
                    </form>
                </div>
                <?php else: ?>
                <div class="ps-empty">
                    <span class="ps-slot-num">#<?= $slot ?></span>
                    <span class="ps-placeholder">空位</span>
                    <small class="text-muted">从下方持仓中选择放置</small>
                </div>
                <?php endif; ?>
            </div>
            <?php endfor; ?>
        </div>
    </section>

    <!-- Holdings Table -->
    <section class="section">
        <h2 class="section-title">💼 持仓明细</h2>
        <div class="filter-sorts" style="margin-bottom:12px">
            <span class="sort-label">排序：</span>
            <a href="?sort=value" class="sort-link <?= $sort === 'value' ? 'active' : '' ?>">市值</a>
            <a href="?sort=rarity" class="sort-link <?= $sort === 'rarity' ? 'active' : '' ?>">稀有度</a>
            <a href="?sort=profit" class="sort-link <?= $sort === 'profit' ? 'active' : '' ?>">盈亏</a>
            <a href="?sort=name" class="sort-link <?= $sort === 'name' ? 'active' : '' ?>">名称</a>
        </div>
        <?php if (!empty($holdings)): ?>
        <div class="stock-table-wrapper">
            <table class="stock-table">
                <thead>
                    <tr>
                        <th>股票代码</th>
                        <th>名称</th>
                        <th>稀有度</th>
                        <th>持仓量</th>
                        <th>均价</th>
                        <th>现价</th>
                        <th>市值</th>
                        <th>盈亏</th>
                        <th>放置</th>
                        <th>出售</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($holdings as $h):
                        $alreadyPlaced = in_array($h['stock_id'], array_column($placedCards, 'stock_id'));
                    ?>
                    <tr>
                        <td>
                            <span class="stock-symbol-table <?= $h['rarity'] ?>"><?= htmlspecialchars($h['symbol']) ?></span>
                        </td>
                        <td><a href="<?= url('/market_detail.php') ?>?id=<?= $h['stock_id'] ?>"><?= htmlspecialchars($h['stock_name']) ?></a></td>
                        <td><span class="rarity-badge <?= !empty($h['limited_edition']) ? 'limited' : $h['rarity'] ?>"><?= !empty($h['limited_edition']) ? '绝版' : (GachaEngine::RARITY_NAMES[$h['rarity']] ?? $h['rarity']) ?></span></td>
                        <td class="td-qty"><?= $h['quantity'] ?></td>
                        <td>🪙 <?= nf($h['avg_cost'], 2) ?></td>
                        <td>🪙 <?= nf($h['current_price'], 2) ?></td>
                        <td>🪙 <?= nf($h['market_value'], 2) ?></td>
                        <td class="<?= $h['profit_loss'] >= 0 ? 'text-green' : 'text-red' ?>">
                            <?= $h['profit_loss'] >= 0 ? '+' : '' ?><?= nf($h['profit_loss'], 2) ?>
                        </td>
                        <td>
                            <?php if (!$alreadyPlaced): ?>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action" value="place">
                                <input type="hidden" name="stock_id" value="<?= $h['stock_id'] ?>">
                                <select name="slot" class="slot-select">
                                    <?php for ($s = 1; $s <= 6; $s++): ?>
                                        <?php if (!in_array($s, $placedSlots)): ?>
                                        <option value="<?= $s ?>">位 #<?= $s ?></option>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </select>
                                <button class="btn btn-xs btn-primary">放置</button>
                            </form>
                            <?php else: ?>
                            <span class="text-muted">已放置</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display:flex;flex-direction:column;gap:4px">
                            <form method="POST" action="<?= url('/card_market_sell.php') ?>" style="display:flex;gap:4px">
                                <input type="hidden" name="stock_id" value="<?= $h['stock_id'] ?>">
                                <input type="number" name="quantity" value="1" min="1" max="<?= $h['quantity'] ?>" style="width:50px;padding:2px 4px;border-radius:4px;background:var(--bg-primary);border:1px solid var(--border);color:var(--text-primary);font-size:12px">
                                <input type="number" name="price" value="<?= $h['current_price'] ?>" min="0.01" step="0.01" style="width:70px;padding:2px 4px;border-radius:4px;background:var(--bg-primary);border:1px solid var(--border);color:var(--text-primary);font-size:12px" title="单价">
                                <button class="btn btn-xs btn-outline" style="padding:2px 6px;font-size:11px">出售</button>
                            </form>
                            <form method="POST" action="<?= url('/market_sell.php') ?>" style="display:flex">
                                <input type="hidden" name="stock_id" value="<?= $h['stock_id'] ?>">
                                <input type="hidden" name="quantity" value="<?= $h['quantity'] ?>">
                                <button class="btn btn-xs btn-danger" style="flex:1;padding:2px 6px;font-size:11px" onclick="return confirm('全部卖出 <?= $h['quantity'] ?> 股？')">全部卖出</button>
                            </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php
            $start = max(1, $page - 2);
            $end = min($totalPages, $page + 2);
            if ($start > 1): ?><span class="page-link" style="background:transparent;border:none">…</span><?php endif;
            for ($p = $start; $p <= $end; $p++):
            ?>
            <a href="?sort=<?= urlencode($sort) ?>&page=<?= $p ?>" class="page-link <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
            <?php endfor;
            if ($end < $totalPages): ?><span class="page-link" style="background:transparent;border:none">…</span><?php endif; ?>
        </div>
        <?php endif; ?>
        <?php else: ?>
        <div class="empty-state">
            <p>📭 暂无持仓。去<a href="<?= url('/market.php') ?>">股市</a>买点股票，或者<a href="<?= url('/gacha.php') ?>">抽卡</a>试试运气！</p>
        </div>
        <?php endif; ?>
    </section>
</div>

<?php include __DIR__ . '/layout/footer.php'; ?>
