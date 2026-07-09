<?php $pageTitle = '用户管理'; include __DIR__ . '/layout/header.php'; ?>
<div class="page-admin" style="max-width:1200px">
    <div class="page-header">
        <h1>👥 用户管理</h1>
        <a href="<?= url('/admin.php') ?>" class="btn btn-outline">← 返回后台</a>
    </div>
    <div class="stock-table-wrapper">
        <table class="stock-table"><thead><tr>
            <th>ID</th><th>用户名</th><th>代币余额</th><th>累计获得</th><th>累计消费</th><th>管理</th>
        </tr></thead><tbody>
        <?php foreach ($users as $u): ?>
        <tr>
            <td>#<?= $u['id'] ?></td>
            <td><strong><?= htmlspecialchars($u['username']) ?></strong><?= $u['is_admin'] ? ' <span class="rarity-badge small legendary">管理员</span>' : '' ?></td>
            <td>🪙 <?= nf($u['token_balance'], 1) ?></td>
            <td>🪙 <?= nf($u['total_earned'], 1) ?></td>
            <td>🪙 <?= nf($u['total_spent'], 1) ?></td>
            <td>
                <a href="<?= url('/user_edit.php') ?>?id=<?= $u['id'] ?>" class="btn btn-xs btn-primary">管理</a>
                <a href="<?= url('/profile.php') ?>?id=<?= $u['id'] ?>" class="btn btn-xs btn-outline">查看主页</a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody></table>
    </div>
</div>
<?php include __DIR__ . '/layout/footer.php'; ?>
