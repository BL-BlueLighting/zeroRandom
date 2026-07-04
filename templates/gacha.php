<?php
/**
 * zero Random - Gacha Pull Page
 */
$pageTitle = '抽卡';

include __DIR__ . '/layout/header.php';
?>

<div class="page-gacha">
    <div class="page-header">
        <h1>🎲 抽卡</h1>
        <?php if (Session::isLoggedIn()):
            $userId = Session::userId();
            $balance = TokenSystem::getBalance($userId);
            $stats = GachaEngine::getPullStats($userId);
            // 100-pull pity counter
            $hundredPulls = (int)$stats['hundred_count'];
            $pityNext = GACHA_LEGENDARY_PITY_100 - (($hundredPulls % GACHA_LEGENDARY_PITY_100));
            if ($pityNext === GACHA_LEGENDARY_PITY_100) $pityNext = 0;
        ?>
        <span class="token-display large">🪙 <?= number_format($balance, 1) ?></span>
        <?php endif; ?>
    </div>

    <?php if (!Session::isLoggedIn()): ?>
    <div class="empty-state">
        <p>请 <a href="<?= url('/login.php') ?>">登录</a> 后进行抽卡。</p>
    </div>
    <?php else: ?>

    <!-- Pity Info -->
    <div class="pity-info">
        <?php if (isset($pityNext) && $pityNext > 0): ?>
        <span>📊 百连抽保底进度: <?= $hundredPulls % GACHA_LEGENDARY_PITY_100 ?>/<?= GACHA_LEGENDARY_PITY_100 ?>
            （再 <?= $pityNext ?> 次百连抽保底传说）</span>
        <?php elseif (isset($pityNext)): ?>
        <span>🌟 下次百连抽必定出传说！</span>
        <?php endif; ?>
    </div>

    <!-- Pull Area -->
    <div class="gacha-machine">
        <div class="gacha-display" id="gachaDisplay">
            <div class="gacha-placeholder">
                <div class="gacha-icon">🎰</div>
                <p>点击下方按钮开始抽卡</p>
            </div>
        </div>

    <?php $pools = PoolEngine::getAllPools(); if (!empty($pools)): ?>
    <div style="display:flex;gap:8px;align-items:center;justify-content:center;margin-bottom:16px">
        <label class="text-muted" style="font-size:13px">卡池：</label>
        <select id="poolSelect" class="form-input" style="width:auto;min-width:150px;padding:4px 10px">
            <?php foreach ($pools as $p): ?>
            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?> (<?= count(PoolEngine::getPoolStockIds($p['id'])) ?>题)</option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>

        <div class="gacha-actions">
            <button class="btn btn-primary" onclick="doPull('single')" id="btnSingle">
                🎲 单抽 <br><small>🪙 <?= GACHA_SINGLE_COST ?></small>
            </button>
            <button class="btn btn-accent" onclick="doPull('multi')" id="btnMulti">
                🎰 十连抽 <br><small>🪙 <?= GACHA_MULTI_COST ?> · 保底稀有</small>
            </button>
            <button class="btn btn-gold" onclick="doPull('hundred')" id="btnHundred">
                💫 百连抽 <br><small>🪙 <?= GACHA_HUNDRED_COST ?> · 保底史诗</small>
            </button>
            <button class="btn btn-danger" onclick="doAllIn()" id="btnAllIn" style="background:linear-gradient(135deg,#dc2626,#ef4444);color:#fff;border:none">
                🔴 梭哈 <br><small>最多 500 万 · 30 分钟冷却</small>
            </button>
        </div>

        <div id="pullResult" class="pull-result"></div>
    </div>

    <!-- Probability Info -->
    <div class="gacha-info">
        <h3>📊 今日概率</h3>
        <?php $weights = GachaEngine::getRarityWeights(); ?>
        <div class="prob-grid">
            <?php foreach (GachaEngine::RARITY_ORDER as $rarity): ?>
            <div class="prob-item">
                <span class="rarity-badge <?= $rarity ?>"><?= GachaEngine::RARITY_NAMES[$rarity] ?></span>
                <div class="prob-bar">
                    <div class="prob-fill <?= $rarity ?>" style="width: <?= $weights[$rarity] ?? 10 ?>%"></div>
                </div>
                <span class="prob-pct"><?= $weights[$rarity] ?? 10 ?>%</span>
            </div>
            <?php endforeach; ?>
        </div>
        <p class="text-muted" style="margin-top:8px;font-size:13px">
            💡 稀有度按题目热度决定：提交数越低越稀有（传说 ← 史诗 ← 稀有 ← 普通）
        </p>
    </div>

    <!-- Pull History -->
    <div class="gacha-history">
        <h3>📋 抽卡记录</h3>
        <?php
        $history = GachaEngine::getPullHistory(Session::userId(), 30);
        if (!empty($history)):
        ?>
        <div class="history-list">
            <?php foreach ($history as $h): ?>
            <div class="history-item">
                <span class="rarity-badge small <?= $h['rarity'] ?>"><?= GachaEngine::RARITY_NAMES[$h['rarity']] ?? $h['rarity'] ?></span>
                <span class="hi-name"><?= htmlspecialchars($h['stock_name']) ?></span>
                <span class="hi-symbol"><?= htmlspecialchars($h['symbol']) ?></span>
                <span class="hi-type"><?= $h['pull_type'] === 'hundred' ? '💫百连' : ($h['pull_type'] === 'multi' ? '🎰十连' : '🎲单抽') ?></span>
                <span class="hi-time"><?= $h['created_at'] ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="text-muted">暂无抽卡记录。快来抽一发吧！</p>
        <?php endif; ?>
    </div>

    <!-- Stats -->
    <?php if ($stats['total_pulls'] > 0): ?>
    <div class="gacha-stats">
        <h3>📈 我的统计</h3>
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-label">总抽数</div><div class="stat-value"><?= $stats['total_pulls'] ?></div></div>
            <div class="stat-card"><div class="stat-label">百连抽</div><div class="stat-value">💫 <?= $stats['hundred_count'] ?></div></div>
            <div class="stat-card"><div class="stat-label">传说</div><div class="stat-value" style="color:var(--rarity-legendary)"><?= $stats['legendary_count'] ?></div></div>
            <div class="stat-card"><div class="stat-label">史诗</div><div class="stat-value" style="color:var(--rarity-epic)"><?= $stats['epic_count'] ?></div></div>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
async function doPull(type) {
    const buttons = {
        single: document.getElementById('btnSingle'),
        multi: document.getElementById('btnMulti'),
        hundred: document.getElementById('btnHundred'),
    };
    const display = document.getElementById('gachaDisplay');

    Object.values(buttons).forEach(b => { if (b) b.disabled = true; });

    display.innerHTML = '<div class="gacha-anim"><div class="gacha-spin">🎰</div><p>抽卡中...</p></div>';

    try {
        const resp = await fetch('<?= url('/gacha_pull.php') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: type, pool_id: (document.getElementById('poolSelect')?.value || 0) })
        });
        const data = await resp.json();

        if (!data.success) {
            display.innerHTML = '<div class="gacha-error"><p>❌ ' + data.message + '</p></div>';
        } else {
            let html = '<div class="gacha-results' + (type === 'hundred' ? ' hundred' : '') + '">';
            data.results.forEach((r, i) => {
                const revealDelay = type === 'hundred' ? '0s' : (i * 0.08) + 's';
                html += `
                <div class="gacha-card" style="animation-delay: ${revealDelay}; border-color: ${r.rarity_color}">
                    <div class="gc-rarity" style="color: ${r.rarity_color}">${r.rarity_name}</div>
                    <div class="gc-symbol">${r.symbol}</div>
                    <div class="gc-name">${r.name}</div>
                    <div class="gc-price">🪙 ${parseFloat(r.price).toFixed(2)}</div>
                </div>`;
            });
            html += '</div>';
            if (data.pity_message) {
                html += '<p class="pity-msg">' + data.pity_message + '</p>';
            }
            display.innerHTML = html;

            // Update balance
            const tokenEl = document.querySelector('.token-display.large');
            if (tokenEl) tokenEl.textContent = '🪙 ' + data.balance_remaining.toFixed(1);

            // On 100-pull with guaranteed legendary, show special effect
            if (data.guaranteed_legendary) {
                display.style.boxShadow = '0 0 40px var(--rarity-legendary)';
                setTimeout(() => { display.style.boxShadow = ''; }, 2000);
            }
        }
    } catch (err) {
        display.innerHTML = '<div class="gacha-error"><p>❌ 网络错误，请重试。</p></div>';
    }

    Object.values(buttons).forEach(b => { if (b) b.disabled = false; });
}

// All-in: 3-click confirmation
let allInClicks = 0;
let allInTimer = null;
function doAllIn() {
    allInClicks++;
    const btn = document.getElementById('btnAllIn');
    if (allInTimer) clearTimeout(allInTimer);

    if (allInClicks === 1) {
        btn.textContent = '⚠️ 再次点击确认梭哈';
        btn.style.background = 'linear-gradient(135deg,#f59e0b,#dc2626)';
    } else if (allInClicks === 2) {
        btn.textContent = '🔴 最后一次确认！梭哈所有代币！';
        btn.style.background = 'linear-gradient(135deg,#dc2626,#991111)';
    } else {
        allInClicks = 0;
        btn.disabled = true;
        btn.textContent = '⏳ 梭哈中...';
        doPullAllIn();
        return;
    }
    allInTimer = setTimeout(() => { allInClicks = 0; btn.textContent = '🔴 梭哈 <br><small>最多 500 万 · 30 分钟冷却</small>'; btn.style.background = 'linear-gradient(135deg,#dc2626,#ef4444)'; }, 3000);
}

async function doPullAllIn() {
    const display = document.getElementById('gachaDisplay');
    display.innerHTML = '<div class="gacha-anim"><div class="gacha-spin">🎰</div><p>梭哈中...</p></div>';
    try {
        const resp = await fetch('<?= url('/gacha_allin.php') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ pool_id: (document.getElementById('poolSelect')?.value || 0) })
        });
        const data = await resp.json();
        if (!data.success) { display.innerHTML = '<div class="gacha-error"><p>❌ ' + data.message + '</p></div>'; }
        else {
            let html = '<div class="gacha-results">';
            data.results.forEach((r, i) => {
                html += '<div class="gacha-card" style="animation-delay:' + (i * 0.02) + 's;border-color:' + r.rarity_color + '">'
                    + '<div class="gc-rarity" style="color:' + r.rarity_color + '">' + r.rarity_name + '</div>'
                    + '<div class="gc-symbol">' + r.symbol + '</div>'
                    + '<div class="gc-name">' + r.name + '</div>'
                    + '<div class="gc-price">🪙 ' + parseFloat(r.price).toFixed(2) + '</div></div>';
            });
            html += '</div>';
            if (data.message) html += '<p class="pity-msg">' + data.message + '</p>';
            display.innerHTML = html;
            const tokenEl = document.querySelector('.token-display.large');
            if (tokenEl) tokenEl.textContent = '🪙 ' + data.balance_remaining.toFixed(1);
        }
    } catch (err) { display.innerHTML = '<div class="gacha-error"><p>❌ 网络错误</p></div>'; }
    document.getElementById('btnAllIn').disabled = false;
    document.getElementById('btnAllIn').textContent = '🔴 梭哈 <br><small>最多 500 万 · 30 分钟冷却</small>';
    document.getElementById('btnAllIn').style.background = 'linear-gradient(135deg,#dc2626,#ef4444)';
}
</script>

<?php include __DIR__ . '/layout/footer.php'; ?>
