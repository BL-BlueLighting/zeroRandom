<?php $pageTitle = $isKs ? '天界' : '' . '假题目管理'; include __DIR__ . '/layout/header.php';
$ksRarities = $isKs ? GachaEngine::KS_RARITY_ORDER : ['common','rare','epic','legendary'];
$ksNames = $isKs ? GachaEngine::KS_RARITY_NAMES : ['common'=>'普通','rare'=>'稀有','epic'=>'史诗','legendary'=>'传说'];
?>
<style>
.fake-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 12px; }
.fake-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 16px; position: relative; }
.fake-card .card-del { position: absolute; top: 8px; right: 10px; cursor: pointer; color: var(--red); font-size: 18px; }
.fake-card input, .fake-card select { width: 100%; padding: 6px 8px; margin: 4px 0; border-radius: var(--radius); background: var(--bg-secondary); border: 1px solid var(--border); color: var(--text-primary); font-size: 13px; }
.fake-card label { font-size: 11px; color: var(--text-muted); margin-top: 4px; display: block; }
.fake-card .card-inputs { display: flex; flex-wrap: wrap; gap: 6px; }
.fake-card .card-inputs > div { flex: 1; min-width: 60px; }
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
            <?php foreach ($fakeStocks as $s): ?>
            <div class="fake-card">
                <span class="card-del" onclick="this.parentElement.remove()" title="删除">×</span>
                <label>题号</label><input name="symbol[]" value="<?= htmlspecialchars($s['symbol']) ?>" readonly style="opacity:0.7">
                <label>名称</label><input name="name[]" value="<?= htmlspecialchars($s['name']) ?>" readonly style="opacity:0.7">
                <label>稀有度</label><span class="rarity-badge small <?= $s['rarity'] ?>"><?= $ksNames[$s['rarity']] ?? $s['rarity'] ?></span>
                <label>价格</label><input value="<?= $s['current_price'] ?>" readonly style="opacity:0.5;font-size:12px">
            </div>
            <?php endforeach; ?>
        </div>
    </form>
</div>

<template id="cardTemplate">
    <div class="fake-card" style="border-color:var(--accent)">
        <span class="card-del" onclick="this.parentElement.remove()">×</span>
        <label>题号</label><input name="symbol[]" required placeholder="如 FAKE001">
        <label>名称</label><input name="name[]" required placeholder="题目名称">
        <div class="card-inputs">
            <div><label>稀有度</label><select name="rarity[]">
                <?php foreach ($ksRarities as $r): ?>
                <option value="<?= $r ?>"><?= $ksNames[$r] ?></option>
                <?php endforeach; ?>
            </select></div>
            <div><label>价格</label><input name="price[]" value="10" min="0.01" step="0.01" type="number"></div>
        </div>
        <label>分类</label><input name="category[]" value="未分类" placeholder="分类">
    </div>
</template>

<script>
function addCard() {
    const tmpl = document.getElementById('cardTemplate');
    const clone = tmpl.content.cloneNode(true);
    document.getElementById('cardGrid').appendChild(clone);
}
</script>
<?php include __DIR__ . '/layout/footer.php'; ?>
