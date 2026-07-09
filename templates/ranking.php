<?php
/**
 * OIManka - Rankings
 */
$pageTitle = '排行榜';

try {
    $leaderboard = TokenSystem::getLeaderboard(50);
} catch (Exception $e) {
    $leaderboard = [];
}

include __DIR__ . '/layout/header.php';
?>

<div class="page-ranking">
    <div class="page-header">
        <h1>🏆 排行榜</h1>
        <p class="text-muted">按总资产排名（代币余额 + 持仓市值）</p>
    </div>

    <?php if (!empty($leaderboard)): ?>
    <div class="stock-table-wrapper">
        <table class="stock-table">
            <thead>
                <tr>
                    <th>排名</th>
                    <th>用户</th>
                    <th>代币余额</th>
                    <th>持仓市值</th>
                    <th>总资产</th>
                    <th>持仓数</th>
                    <th>抽卡次数</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leaderboard as $i => $u):
                    $rank = $i + 1;
                    $medal = $rank === 1 ? '🥇' : ($rank === 2 ? '🥈' : ($rank === 3 ? '🥉' : ''));
                    $isMe = Session::isLoggedIn() && (int)$u['id'] === Session::userId();
                ?>
                <tr class="<?= $isMe ? 'row-highlight' : '' ?>">
                    <td class="td-rank">
                        <?= $medal ? "$medal " : '' ?><?= $rank ?>
                    </td>
                    <td>
                        <a href="<?= url('/profile.php') ?>?id=<?= $u['id'] ?>" style="font-weight:600;color:inherit"><?= htmlspecialchars($u['username']) ?></a>
                        <?= $isMe ? '<span class="badge-me">我</span>' : '' ?>
                    </td>
                    <td>🪙 <?= nf($u['token_balance'], 1) ?></td>
                    <td>🪙 <?= nf($u['portfolio_value'], 2) ?></td>
                    <td class="td-price">
                        <strong>🪙 <?= nf($u['net_worth'], 2) ?></strong>
                    </td>
                    <td><?= $u['unique_stocks'] ?></td>
                    <td><?= $u['total_pulls'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state">暂无排行数据</div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/layout/footer.php'; ?>
