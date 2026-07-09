<?php
/**
 * OIManka - Transaction History
 */
$pageTitle = is_kaleidoscope() ? '天界交易记录' : '交易记录';

$userId = Session::userId();
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$transactions = TokenSystem::getTransactionHistory($userId, $perPage, ($page - 1) * $perPage);
$totalTx = TokenSystem::getTransactionCount($userId);
$totalPages = ceil($totalTx / $perPage);

include __DIR__ . '/layout/header.php';
?>

<div class="page-transactions">
    <div class="page-header">
        <h1>📋 交易记录</h1>
    </div>

    <!-- Transaction type legend -->
    <div class="tx-legend">
        <?php
        $types = [
            'buy' => ['📈', '买入'],
            'sell' => ['📉', '卖出'],
            'gacha_pull' => ['🎲', '抽卡'],
            'reward' => ['🎁', '奖励'],
            'fee' => ['💸', '手续费'],
        ];
        foreach ($types as $type => [$icon, $label]):
        ?>
        <span class="tx-legend-item"><?= $icon ?> <?= $label ?></span>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($transactions)): ?>
    <div class="tx-list">
        <?php foreach ($transactions as $tx): ?>
        <div class="tx-item">
            <div class="tx-icon">
                <?php
                $icons = [
                    'buy' => '📈', 'sell' => '📉', 'gacha_pull' => '🎲',
                    'reward' => '🎁', 'fee' => '💸', 'transfer_out' => '📤',
                ];
                echo $icons[$tx['type']] ?? '💱';
                ?>
            </div>
            <div class="tx-body">
                <div class="tx-title">
                    <?php if ($tx['stock_name']): ?>
                        <a href="<?= url('/market_detail.php') ?>?id=<?= $tx['stock_id'] ?>">
                            <?= htmlspecialchars($tx['stock_name']) ?>
                        </a>
                    <?php else: ?>
                        <?= htmlspecialchars($tx['notes'] ?? $tx['type']) ?>
                    <?php endif; ?>
                </div>
                <div class="tx-meta">
                    <?php if ($tx['quantity']): ?>
                    <span>×<?= $tx['quantity'] ?></span>
                    <?php endif; ?>
                    <?php if ($tx['price']): ?>
                    <span>@🪙 <?= nf($tx['price'], 2) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="tx-amount <?= $tx['total_amount'] >= 0 ? 'text-green' : 'text-red' ?>">
                <?= $tx['total_amount'] >= 0 ? '+' : '' ?><?= nf($tx['total_amount'], 2) ?>
                <?php if ($tx['fee'] > 0): ?>
                <small class="text-muted">(手续费: <?= $tx['fee'] ?>)</small>
                <?php endif; ?>
            </div>
            <div class="tx-time"><?= $tx['created_at'] ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
        <a href="?page=<?= $page - 1 ?>" class="page-link">上一页</a>
        <?php endif; ?>
        <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
        <a href="?page=<?= $p ?>" class="page-link <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
        <a href="?page=<?= $page + 1 ?>" class="page-link">下一页</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="empty-state">暂无交易记录</div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/layout/footer.php'; ?>
