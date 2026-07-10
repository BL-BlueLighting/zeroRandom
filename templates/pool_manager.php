<?php $pageTitle = '卡池管理'; include __DIR__ . '/layout/header.php'; ?>
<div class="page-admin" style="max-width:1000px">
    <div class="page-header">
        <h1>🎯 卡池管理</h1>
        <a href="<?= url('/admin.php') ?>" class="btn btn-outline">← 返回后台</a>
    </div>

    <?php if ($message): ?><div class="flash-message flash-success"><?= $message ?></div><?php endif; ?>
    <?php if ($error): ?><div class="flash-message flash-error"><?= $error ?></div><?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px">
    <?php foreach ($pools as $pool):
        $stockIds = PoolEngine::getPoolStockIds($pool['id']);
    ?>
        <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:20px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
                <h2 style="font-size:18px;margin:0"><?= htmlspecialchars($pool['name']) ?></h2>
                <div style="display:flex;gap:6px">
                    <a href="<?= url('/pool_edit.php') ?>?id=<?= $pool['id'] ?>" class="btn btn-sm btn-primary">编辑题目</a>
                    <form method="POST" style="display:inline" onsubmit="return confirm('删除卡池「<?= htmlspecialchars($pool['name']) ?>」？')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="pool_id" value="<?= $pool['id'] ?>">
                        <button class="btn btn-sm btn-danger">删除</button>
                    </form>
                </div>
            </div>
            <div class="text-muted" style="margin-bottom:8px;font-size:13px"><?= count($stockIds) ?> 支题目</div>
            <div style="display:flex;flex-wrap:wrap;gap:3px;max-height:200px;overflow-y:auto">
                <?php
                $stocks = PoolEngine::getPoolStocks($pool['id']);
                foreach (array_slice($stocks, 0, 80) as $s):
                    $rarity = GachaEngine::rarityClass($s[\'rarity\']) ?? 'common';
                ?>
                <span class="rarity-badge small <?= $rarity ?>" style="font-size:10px;padding:1px 5px"><?= htmlspecialchars($s['symbol']) ?></span>
                <?php endforeach; ?>
                <?php if (count($stocks) > 80): ?><span class="text-muted" style="font-size:11px">…还有 <?= count($stocks) - 80 ?> 题</span><?php endif; ?>
            </div>
            <!-- Limited Edition Toggle -->
            <div style="margin-top:8px;padding-top:8px;border-top:1px solid var(--border);display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <?php if ($pool['is_limited']): ?>
                <span class="rarity-badge limited" style="font-size:11px;padding:2px 8px">⭐ 绝版卡池</span>
                <span class="text-muted" style="font-size:12px">到期: <?= htmlspecialchars($pool['expires_at'] ?? '未设置') ?></span>
                <form method="POST" style="display:inline">
                    <input type="hidden" name="action" value="unset_limited">
                    <input type="hidden" name="pool_id" value="<?= $pool['id'] ?>">
                    <button class="btn btn-xs btn-outline">取消绝版</button>
                </form>
                <?php else: ?>
                <span class="text-muted" style="font-size:12px">普通卡池</span>
                <form method="POST" style="display:inline-flex;gap:4px;align-items:center">
                    <input type="hidden" name="action" value="set_limited">
                    <input type="hidden" name="pool_id" value="<?= $pool['id'] ?>">
                    <input type="date" name="expires_at" class="form-input" style="width:140px;padding:2px 6px;font-size:12px" required>
                    <button class="btn btn-xs btn-primary">设为绝版</button>
                </form>
                <?php endif; ?>
            </div>

            <form method="POST" style="display:flex;gap:6px;margin-top:8px;align-items:center;padding-top:8px;border-top:1px solid var(--border)">
                <input type="hidden" name="action" value="split">
                <input type="hidden" name="pool_id" value="<?= $pool['id'] ?>">
                <input type="text" name="new_pool_name" placeholder="新卡池名称" class="form-input" style="flex:1;padding:4px 8px;font-size:13px">
                <input type="number" name="split_at" placeholder="分割位置(题号)" style="width:110px;padding:4px 8px;font-size:13px;border-radius:var(--radius);background:var(--bg-primary);border:1px solid var(--border);color:var(--text-primary)">
                <button class="btn btn-sm btn-outline" title="前N支股票分到新卡池">分割</button>
            </form>
        </div>
    <?php endforeach; ?>
    </div>

    <div class="admin-section">
        <h2>创建新卡池</h2>
        <form method="POST" style="display:flex;gap:8px;margin-top:12px">
            <input type="hidden" name="action" value="create">
            <input type="text" name="pool_name" placeholder="卡池名称" class="form-input" style="flex:1">
            <button class="btn btn-primary">创建</button>
        </form>
    </div>
</div>
<?php include __DIR__ . '/layout/footer.php'; ?>
