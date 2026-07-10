<?php $pageTitle = '编辑卡池 - ' . htmlspecialchars($pool['name']); include __DIR__ . '/layout/header.php'; ?>
<div class="page-admin" style="max-width:1200px">
    <div class="page-header">
        <h1>🎯 <?= htmlspecialchars($pool['name']) ?> — 选择题目</h1>
        <div style="display:flex;gap:8px">
            <span class="token-display" id="selectedCount">已选 0 题</span>
            <a href="<?= url('/pool_manager.php') ?>" class="btn btn-outline">← 返回</a>
        </div>
    </div>

    <?php if ($message): ?><div class="flash-message flash-success"><?= $message ?></div><?php endif; ?>

    <form method="POST" id="poolForm">
        <!-- Selection Toolbar -->
        <div class="admin-section" style="margin-bottom:16px">
            <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
                <span class="text-muted" style="font-size:13px">选择工具：</span>
                <button type="button" class="btn btn-sm btn-outline" onclick="selectAll(true)">全选</button>
                <button type="button" class="btn btn-sm btn-outline" onclick="selectAll(false)">取消全选</button>
                <button type="button" class="btn btn-sm btn-outline" onclick="selectHalf(true)">前半</button>
                <button type="button" class="btn btn-sm btn-outline" onclick="selectHalf(false)">后半</button>
                <button type="button" class="btn btn-sm btn-outline" onclick="invertSelection()">反选</button>
                <span style="width:1px;height:24px;background:var(--border);margin:0 4px"></span>
                <span class="text-muted" style="font-size:13px">按分类选择：</span>
                <?php foreach ($categories as $cat => $stocks): ?>
                <button type="button" class="btn btn-sm btn-outline" onclick="selectCategory('<?= htmlspecialchars($cat, ENT_QUOTES) ?>')">
                    <?= htmlspecialchars($cat) ?> (<?= count($stocks) ?>)
                </button>
                <?php endforeach; ?>
                <span style="width:1px;height:24px;background:var(--border);margin:0 4px"></span>
                <button type="button" class="btn btn-sm btn-outline" onclick="selectEveryNth(3, 0)">第1列</button>
                <button type="button" class="btn btn-sm btn-outline" onclick="selectEveryNth(3, 1)">第2列</button>
                <button type="button" class="btn btn-sm btn-outline" onclick="selectEveryNth(3, 2)">第3列</button>
            </div>
        </div>

        <!-- Stock Grid -->
        <div class="admin-section" style="max-height:70vh;overflow-y:auto">
            <?php foreach ($categories as $cat => $stocks): ?>
            <div style="margin-bottom:16px">
                <h3 style="font-size:15px;margin-bottom:8px;padding-bottom:4px;border-bottom:1px solid var(--border)">
                    <?= htmlspecialchars($cat) ?>
                    <span class="text-muted" style="font-size:12px">(<?= count($stocks) ?>题)</span>
                </h3>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:4px">
                    <?php foreach ($stocks as $s): ?>
                    <label style="display:flex;align-items:center;gap:4px;padding:3px 6px;border-radius:4px;background:var(--bg-secondary);border:1px solid var(--border);cursor:pointer;font-size:12px;transition:all .15s"
                           data-stock='<?= json_encode(['id' => $s['id'], 'category' => $cat], JSON_HEX_APOS) ?>'
                           onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor=this.querySelector('input').checked ? 'var(--accent)' : 'var(--border)'">
                        <input type="checkbox" name="stock_ids[]" value="<?= $s['id'] ?>"
                               <?= in_array($s['id'], $poolStockIds) ? 'checked' : '' ?>
                               onchange="updateCount(); this.parentElement.style.borderColor=this.checked?'var(--accent)':'var(--border)'"
                               style="accent-color:var(--accent)">
                        <span class="rarity-badge small <?= !empty($s['limited_edition']) ? 'limited' : ($s['rarity'] ?: 'common') ?>" style="font-size:9px;padding:0 4px"><?= !empty($s['limited_edition']) ? '绝版' : (GachaEngine::rarityNames()[$s['rarity']] ?? $s['rarity']) ?></span>
                        <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:60px" title="<?= htmlspecialchars($s['name']) ?>"><?= htmlspecialchars($s['symbol']) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div style="position:sticky;bottom:0;padding:12px 0;background:var(--bg-primary);display:flex;gap:12px;align-items:center;border-top:1px solid var(--border);margin-top:12px">
            <button class="btn btn-primary btn-lg" style="flex:1">💾 保存 (已选 <span id="saveCount">0</span> 题)</button>
        </div>
    </form>
</div>

<script>
let stocks = <?= json_encode(array_map(function($s) {
    return ['id' => $s['id'], 'symbol' => $s['symbol'], 'category' => $s['category'] ?: '未分类'];
}, $allStocks)) ?>;

function getCheckboxes() {
    return document.querySelectorAll('input[name="stock_ids[]"]');
}

function updateCount() {
    const checked = document.querySelectorAll('input[name="stock_ids[]"]:checked').length;
    document.getElementById('selectedCount').textContent = '已选 ' + checked + ' 题';
    document.getElementById('saveCount').textContent = checked;
}
updateCount();

function selectAll(select) {
    getCheckboxes().forEach(cb => { cb.checked = select; cb.dispatchEvent(new Event('change')); });
    updateCount();
}

function selectHalf(first) {
    const cbs = Array.from(getCheckboxes());
    const mid = Math.floor(cbs.length / 2);
    cbs.forEach((cb, i) => { cb.checked = first ? i < mid : i >= mid; cb.dispatchEvent(new Event('change')); });
    updateCount();
}

function invertSelection() {
    getCheckboxes().forEach(cb => { cb.checked = !cb.checked; cb.dispatchEvent(new Event('change')); });
    updateCount();
}

function selectCategory(category) {
    getCheckboxes().forEach(cb => {
        const label = cb.closest('label');
        if (label) {
            try {
                const data = JSON.parse(label.getAttribute('data-stock')?.replace(/'/g, "'") || '{}');
                if (data.category === category) cb.checked = true;
            } catch(e) {}
        }
        cb.dispatchEvent(new Event('change'));
    });
    updateCount();
}

function selectEveryNth(n, offset) {
    getCheckboxes().forEach((cb, i) => {
        const col = i % n;
        cb.checked = col === offset;
        cb.dispatchEvent(new Event('change'));
    });
    updateCount();
}
</script>
<?php include __DIR__ . '/layout/footer.php'; ?>
