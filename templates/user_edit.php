<?php $pageTitle = '管理用户 - ' . htmlspecialchars($targetUser['username']); include __DIR__ . '/layout/header.php'; ?>
<div class="page-admin" style="max-width:900px">
    <div class="page-header">
        <h1>👤 <?= htmlspecialchars($targetUser['username']) ?> — 管理</h1>
        <div style="display:flex;gap:8px">
            <a href="<?= url('/profile.php') ?>?id=<?= $targetId ?>" class="btn btn-outline" target="_blank">普通用户界面</a>
            <a href="<?= url('/user_manager.php') ?>" class="btn btn-outline">← 用户列表</a>
        </div>
    </div>

    <?php if ($message): ?><div class="flash-message flash-success"><?= $message ?></div><?php endif; ?>
    <?php if ($error): ?><div class="flash-message flash-error"><?= $error ?></div><?php endif; ?>

    <!-- User Info -->
    <div class="detail-stats-grid" style="margin-bottom:16px">
        <div class="detail-stat"><div class="ds-label">ID</div><div class="ds-value">#<?= $targetUser['id'] ?></div></div>
        <div class="detail-stat"><div class="ds-label">代币余额</div><div class="ds-value">🪙 <?= nf($targetUser['token_balance'], 1) ?></div></div>
        <div class="detail-stat"><div class="ds-label">累计获得</div><div class="ds-value">🪙 <?= nf($targetUser['total_earned'], 1) ?></div></div>
        <div class="detail-stat"><div class="ds-label">累计消费</div><div class="ds-value">🪙 <?= nf($targetUser['total_spent'], 1) ?></div></div>
        <div class="detail-stat"><div class="ds-label">持仓</div><div class="ds-value"><?= $hc ?> 项</div></div>
        <div class="detail-stat"><div class="ds-label">抽卡/交易</div><div class="ds-value">🎲<?= $gc ?> / 📋<?= $tc ?></div></div>
        <div class="detail-stat"><div class="ds-label">注册时间</div><div class="ds-value" style="font-size:14px"><?= $targetUser['created_at'] ?></div></div>
        <div class="detail-stat"><div class="ds-label">管理员</div><div class="ds-value"><?= $targetUser['is_admin'] ? '✅ 是' : '❌ 否' ?></div></div>
    </div>

    <!-- Edit Form -->
    <section class="admin-section">
        <h2>✏️ 编辑用户信息</h2>
        <form method="POST" class="admin-form">
            <input type="hidden" name="action" value="update_user">
            <div class="form-row">
                <div class="form-group">
                    <label>用户名</label>
                    <input type="text" name="username" value="<?= htmlspecialchars($targetUser['username']) ?>" class="form-input">
                </div>
                <div class="form-group">
                    <label>代币余额</label>
                    <input type="number" name="token_balance" value="<?= $targetUser['token_balance'] ?>" step="0.01" class="form-input" style="width:160px">
                </div>
                <div class="form-group">
                    <label>管理员</label>
                    <select name="is_admin" class="form-input" style="width:100px">
                        <option value="1" <?= $targetUser['is_admin'] ? 'selected' : '' ?>>是</option>
                        <option value="0" <?= !$targetUser['is_admin'] ? 'selected' : '' ?>>否</option>
                    </select>
                </div>
            </div>
            <button class="btn btn-primary">💾 保存修改</button>
        </form>
    </section>

    <!-- Reset Password -->
    <section class="admin-section">
        <h2>🔑 重置密码</h2>
        <form method="POST" class="admin-form">
            <input type="hidden" name="action" value="reset_password">
            <div class="form-row">
                <input type="text" name="new_password" class="form-input" placeholder="输入新密码（至少6位）" style="max-width:300px" required>
                <button class="btn btn-accent">重置密码</button>
            </div>
        </form>
    </section>

    <!-- Holdings List -->
    <section class="admin-section">
        <h2>📦 持仓明细 (<?= $hc ?> 项)</h2>
        <?php if (!empty($holdRows)): ?>
        <div class="stock-table-wrapper">
            <table class="stock-table"><thead><tr>
                <th>代码</th><th>名称</th><th>稀有度</th><th>数量</th><th>均价</th><th>现价</th><th>市值</th><th>盈亏</th>
            </tr></thead><tbody>
            <?php foreach ($holdRows as $h): ?>
            <tr>
                <td><span class="stock-symbol-table <?= GachaEngine::rarityClass($h[\'rarity\']) ?>"><?= htmlspecialchars($h['symbol']) ?></span></td>
                <td><a href="<?= url('/market_detail.php') ?>?id=<?= $h['stock_id'] ?>"><?= htmlspecialchars($h['stock_name']) ?></a></td>
                <td><span class="rarity-badge <?= !empty($h['limited_edition']) ? 'limited' : GachaEngine::rarityClass($h[\'rarity\']) ?>"><?= !empty($h['limited_edition']) ? '绝版' : (GachaEngine::rarityNames()[GachaEngine::rarityClass($h[\'rarity\'])] ?? GachaEngine::rarityClass($h[\'rarity\'])) ?></span></td>
                <td><?= $h['quantity'] ?></td>
                <td>🪙 <?= nf($h['avg_cost'], 2) ?></td>
                <td>🪙 <?= nf($h['current_price'], 2) ?></td>
                <td>🪙 <?= nf($h['market_value'], 2) ?></td>
                <td class="<?= $h['profit_loss'] >= 0 ? 'text-green' : 'text-red' ?>"><?= $h['profit_loss'] >= 0 ? '+' : '' ?><?= nf($h['profit_loss'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody></table>
        </div>
        <?php if ($holdTotalPages > 1): ?>
        <div class="pagination">
            <?php
            $start = max(1, $holdPage - 2);
            $end = min($holdTotalPages, $holdPage + 2);
            if ($start > 1): ?><span class="page-link" style="background:transparent;border:none">…</span><?php endif;
            for ($p = $start; $p <= $end; $p++):
            ?>
            <a href="?id=<?= $targetId ?>&hold_page=<?= $p ?>" class="page-link <?= $p === $holdPage ? 'active' : '' ?>"><?= $p ?></a>
            <?php endfor;
            if ($end < $holdTotalPages): ?><span class="page-link" style="background:transparent;border:none">…</span><?php endif; ?>
        </div>
        <?php endif; ?>
        <?php else: ?>
        <p class="text-muted">无持仓</p>
        <?php endif; ?>
    </section>

    <!-- Clear Holdings -->
    <section class="admin-section">
        <h2>🗑️ 清空持仓</h2>
        <p class="text-muted">删除该用户的所有持仓记录和卡牌放置（不可恢复）</p>
        <form method="POST" onsubmit="return confirm('确定清空该用户的全部持仓？此操作不可恢复！')">
            <input type="hidden" name="action" value="clear_holdings">
            <button class="btn btn-danger">🗑️ 清空持仓</button>
        </form>
    </section>

    <!-- Delete User -->
    <section class="admin-section">
        <h2>⚠️ 删除用户</h2>
        <p class="text-muted">完全删除该用户及其所有关联数据（不可恢复）</p>
        <form method="POST" onsubmit="return confirm('确定删除该用户？所有数据将被永久删除！')">
            <input type="hidden" name="action" value="delete_user">
            <button class="btn btn-danger">⚠️ 删除用户</button>
        </form>
    </section>
</div>
<?php include __DIR__ . '/layout/footer.php'; ?>
