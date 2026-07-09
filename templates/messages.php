<?php $pageTitle = '站内信'; include __DIR__ . '/layout/header.php'; ?>
<div class="page-market" style="max-width:700px;margin:0 auto">
    <div class="page-header">
        <h1>📬 站内信</h1>
        <div style="display:flex;gap:8px">
            <a href="?filter=all" class="btn btn-sm <?= $filter === 'all' ? 'btn-primary' : 'btn-outline' ?>">全部</a>
            <a href="?filter=unread" class="btn btn-sm <?= $filter === 'unread' ? 'btn-primary' : 'btn-outline' ?>">未读</a>
            <a href="?filter=read" class="btn btn-sm <?= $filter === 'read' ? 'btn-primary' : 'btn-outline' ?>">已读</a>
        </div>
    </div>

    <?php if (empty($messages)): ?>
    <div class="empty-state"><p>📭 暂无消息</p></div>
    <?php else: ?>
    <div class="tx-list">
        <?php foreach ($messages as $m): ?>
        <div class="tx-item" style="<?= !$m['is_read'] ? 'border-left:3px solid var(--accent);background:var(--bg-hover)' : '' ?>">
            <div class="tx-body" style="flex:1">
                <div class="tx-title" style="display:flex;justify-content:space-between">
                    <span><?= !$m['is_read'] ? '📌 ' : '' ?><?= htmlspecialchars($m['title']) ?></span>
                    <span class="tx-time"><?= $m['created_at'] ?></span>
                </div>
                <div class="tx-meta" style="margin-top:6px;line-height:1.6;color:var(--text-primary)"><?= nl2br(htmlspecialchars($m['content'])) ?></div>
            </div>
            <?php if (!$m['is_read']): ?>
            <form method="POST" style="margin-left:8px">
                <input type="hidden" name="msg_id" value="<?= $m['id'] ?>">
                <button class="btn btn-xs btn-outline">标为已读</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php
        $start = max(1, $page - 2);
        $end = min($totalPages, $page + 2);
        if ($start > 1): ?><span class="page-link" style="background:transparent;border:none">…</span><?php endif;
        for ($p = $start; $p <= $end; $p++):
        ?>
        <a href="?filter=<?= $filter ?>&page=<?= $p ?>" class="page-link <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
        <?php endfor;
        if ($end < $totalPages): ?><span class="page-link" style="background:transparent;border:none">…</span><?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/layout/footer.php'; ?>
