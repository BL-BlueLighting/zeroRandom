<?php $pageTitle = ($isKs ? '天界' : '') . '假题目管理'; include __DIR__ . '/layout/header.php';
$ksRarities = $isKs ? GachaEngine::KS_RARITY_ORDER : ['common','rare','epic','legendary'];
$ksNames = $isKs ? GachaEngine::KS_RARITY_NAMES : ['common'=>'普通','rare'=>'稀有','epic'=>'史诗','legendary'=>'传说'];
?>
<style>
.fake-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 12px; }
.fake-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 16px; position: relative; min-height: 180px; }
.fake-card.existing { border-color: var(--border-light); }
.fake-card.editing { border-color: var(--accent); }
.fake-card .cbody { margin-top: 4px; }
.fake-card .cbody .cline { display: flex; align-items: center; gap: 6px; margin: 3px 0; font-size: 13px; }
.fake-card .cbody .cline .clabel { color: var(--text-muted); font-size: 11px; white-space: nowrap; }
.fake-card input, .fake-card select { width: 100%; padding: 5px 7px; border-radius: var(--radius); background: var(--bg-secondary); border: 1px solid var(--border); color: var(--text-primary); font-size: 13px; }
.fake-card label { font-size: 11px; color: var(--text-muted); margin-top: 3px; display: block; }
.card-del { position: absolute; top: 6px; right: 8px; cursor: pointer; color: var(--red); font-size: 18px; }
.btn-mini { font-size: 11px; padding: 2px 8px; border-radius: 4px; cursor: pointer; border: 1px solid var(--border); background: var(--bg-secondary); color: var(--text-secondary); }
.btn-mini:hover { border-color: var(--accent); color: var(--accent); }
</style>
<div class="page-admin" style="max-width:1200px">
    <div class="page-header">
        <h1>🪪 <?= $isKs ? '天界' : '' ?>假题目管理</h1>
        <div style="display:flex;gap:8px;align-items:center">
            <button class="btn btn-primary" onclick="addCard()">＋ 添加新题目</button>
            <button class="btn btn-accent" onclick="document.getElementById('batchForm').submit()">💾 批量提交</button>
            <a href="<?= url('/admin.php') ?>" class="btn btn-outline">← 后台</a>
        </div>
    </div>
    <?php if ($message): ?><div class="flash-message flash-success"><?= $message ?></div><?php endif; ?>

    <form method="POST" id="batchForm">
        <div class="fake-grid" id="cardGrid">
            <?php foreach ($fakeStocks as $idx => $s): ?>
            <div class="fake-card existing" id="card-<?= $idx ?>">
                <span class="card-del" onclick="deleteExisting(<?= $s['id'] ?>, this.parentElement)">×</span>
                <div class="cbody">
                    <div class="cline"><span class="clabel">题号</span><strong><?= htmlspecialchars($s['symbol']) ?></strong></div>
                    <div class="cline"><span class="clabel">名称</span><?= htmlspecialchars($s['name']) ?></div>
                    <div class="cline"><span class="clabel">稀有度</span><span class="rarity-badge small <?= $s['rarity'] ?>"><?= $ksNames[$s['rarity']] ?? $s['rarity'] ?></span></div>
                    <div class="cline"><span class="clabel">价格</span>🪙 <?= $s['current_price'] ?></div>
                    <div class="cline"><span class="clabel">分类</span><?= htmlspecialchars($s['category'] ?: '未分类') ?></div>
                </div>
                <div style="margin-top:8px">
                    <button type="button" class="btn-mini" onclick="editExisting(<?= $idx ?>)">✏️ 修改</button>
                </div>
                <!-- Hidden edit form (shown on click) -->
                <div class="edit-form" style="display:none;margin-top:6px">
                    <label>题号</label><input name="existing_id[]" value="<?= $s['id'] ?>" type="hidden">
                    <input name="existing_symbol[]" value="<?= htmlspecialchars($s['symbol']) ?>" placeholder="题号">
                    <label>名称</label><input name="existing_name[]" value="<?= htmlspecialchars($s['name']) ?>" placeholder="名称">
                    <label>稀有度</label><select name="existing_rarity[]">
                        <?php foreach ($ksRarities as $r): ?>
                        <option value="<?= $r ?>" <?= $s['rarity'] === $r ? 'selected' : '' ?>><?= $ksNames[$r] ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label>价格</label><input name="existing_price[]" value="<?= $s['current_price'] ?>" type="number" step="0.01" min="0.01">
                    <label>分类</label><input name="existing_category[]" value="<?= htmlspecialchars($s['category'] ?: '未分类') ?>" placeholder="分类">
                    <button type="button" class="btn-mini" style="margin-top:4px;color:var(--red)" onclick="this.parentElement.style.display='none'">取消</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </form>
</div>

<template id="cardTemplate">
    <div class="fake-card editing" style="border-color:var(--accent)">
        <span class="card-del" onclick="this.parentElement.remove()">×</span>
        <label>题号</label><input name="new_symbol[]" required placeholder="如 FAKE001">
        <label>名称</label><input name="new_name[]" required placeholder="题目名称">
        <div style="display:flex;gap:8px">
            <div style="flex:1"><label>稀有度</label><select name="new_rarity[]">
                <?php foreach ($ksRarities as $r): ?>
                <option value="<?= $r ?>"><?= $ksNames[$r] ?></option>
                <?php endforeach; ?>
            </select></div>
            <div style="flex:1"><label>价格</label><input name="new_price[]" value="10" min="0.01" step="0.01" type="number"></div>
        </div>
        <label>分类</label><input name="new_category[]" value="未分类" placeholder="分类">
    </div>
</template>

<script>
function addCard() {
    const tmpl = document.getElementById('cardTemplate');
    const clone = tmpl.content.cloneNode(true);
    document.getElementById('cardGrid').appendChild(clone);
}
function editExisting(idx) {
    const card = document.getElementById('card-' + idx);
    card.querySelector('.edit-form').style.display = 'block';
    card.querySelector('.cbody').style.opacity = '0.5';
}
async function deleteExisting(id, el) {
    if (!confirm('删除该假题目？此操作不可恢复！')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    try {
        const resp = await fetch('', {method:'POST', body:fd});
        if (resp.ok) el.remove();
        else alert('删除失败');
    } catch(e) { alert('删除失败'); }
}
</script>
<?php include __DIR__ . '/layout/footer.php'; ?>
