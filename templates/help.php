<?php
/**
 * OIManka - Help Center
 */
$pageTitle = '帮助';
include __DIR__ . '/layout/header.php';
?>

<style>
.help-container { max-width: 800px; margin: 0 auto; }
.help-toc { position: sticky; top: 72px; background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 16px 20px; margin-bottom: 24px; }
.help-toc-title { font-size: 13px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
.help-toc a { display: inline-block; font-size: 13px; color: var(--text-secondary); margin-right: 16px; margin-bottom: 4px; padding: 2px 0; transition: color var(--transition); }
.help-toc a:hover { color: var(--accent); }
.help-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 28px 32px; margin-bottom: 16px; transition: border-color var(--transition); }
.help-card:hover { border-color: var(--border-light); }
.help-card h3 { font-size: 18px; margin-bottom: 12px; display: flex; align-items: center; gap: 10px; }
.help-card p { font-size: 14px; line-height: 1.8; color: var(--text-secondary); }
.help-card p b { color: var(--text-primary); }
.help-card code { font-family: monospace; font-size: 13px; background: var(--bg-secondary); padding: 2px 8px; border-radius: 4px; color: var(--accent-gold); }
.rarity-row { display: flex; flex-wrap: wrap; gap: 8px; margin: 12px 0; padding: 12px; background: var(--bg-secondary); border-radius: var(--radius); }
.rarity-item { display: flex; align-items: center; gap: 8px; font-size: 13px; padding: 4px 10px; border-radius: 4px; }
.rarity-bar { width: 120px; height: 6px; background: #2a2a3a; border-radius: 3px; overflow: hidden; }
.rarity-fill { height: 100%; border-radius: 3px; }
@media (max-width: 600px) { .help-card { padding: 20px 16px; } }
</style>

<div class="help-container">
    <div class="page-header">
        <h1>❔ 帮助中心</h1>
        <p class="text-muted"><i>zero</i>Random 平台完整使用指南</p>
    </div>

    <!-- Table of Contents -->
    <div class="help-toc">
        <div class="help-toc-title">📋 目录</div>
        <a href="#gacha">🎲 抽卡</a>
        <a href="#stocks">📈 炒股</a>
        <a href="#portfolio">📦 持仓</a>
        <a href="#market">🔄 市场</a>
        <a href="#special">⭐ 特殊卡池</a>
        <a href="#other">📌 其他说明</a>
    </div>

    <!-- 1. Gacha -->
    <div class="help-card" id="gacha">
        <h3><span class="rarity-badge legendary" style="font-size:14px;padding:2px 10px">🎲</span> 1. 抽卡</h3>
        <p>
            抽卡使用系统内置的伪随机系统，通常状态下会有<b>常驻卡池</b>存在，你可以在这里面抽取卡牌。<br><br>
            每张卡牌的概率不固定，如果你运气好，可能一发就是<span class="rarity-badge small legendary">传说</span>级卡牌，也可能必须等保底。<br>
            概率虽然不一定，但是有一定的等级顺序，从<span class="rarity-badge small common">普通</span>到<span class="rarity-badge small legendary">传说</span>，一阶一阶递进，<span class="rarity-badge small common">普通</span>的概率非常之高。<br><br>
            系统会偶尔对该概率进行微调。
        </p>
        <div class="rarity-row">
            <div class="rarity-item"><span class="rarity-badge small legendary">传说</span><div class="rarity-bar"><div class="rarity-fill" style="width:5%;background:var(--rarity-legendary)"></div></div></div>
            <div class="rarity-item"><span class="rarity-badge small epic">史诗</span><div class="rarity-bar"><div class="rarity-fill" style="width:15%;background:var(--rarity-epic)"></div></div></div>
            <div class="rarity-item"><span class="rarity-badge small rare">稀有</span><div class="rarity-bar"><div class="rarity-fill" style="width:40%;background:var(--rarity-rare)"></div></div></div>
            <div class="rarity-item"><span class="rarity-badge small common">普通</span><div class="rarity-bar"><div class="rarity-fill" style="width:40%;background:var(--rarity-common)"></div></div></div>
        </div>
        <p>
            💡 <b>十连抽</b>、<b>百连抽</b>均有保底机制：十连至少出<span class="rarity-badge small rare">稀有</span>，百连至少出<span class="rarity-badge small epic">史诗</span>。<br>
            推荐攒够三次百连抽，将<span class="rarity-badge small legendary">传说</span>卡牌摆到展示台上享受被动收益。
        </p>
    </div>

    <!-- 2. Stocks -->
    <div class="help-card" id="stocks">
        <h3><span class="rarity-badge rare" style="font-size:14px;padding:2px 10px">📈</span> 2. 炒股</h3>
        <p>
            本系统包含 <b>1070+</b> 道 OJ 题目，每一道题就是一支股票。<br>
            你抽卡得到的就是一只只股票，它们是互相绑定的。<br><br>
            当有人在 OJ 上 AC 题目时，该题的 AC/提交 比率会发生变化，从而影响股价。<br>
            每日刷新股价时：如果有真实 AC 变化则按实际数据调整；<b>如果没有真实变化</b>，则按稀有度概率随机波动：
        </p>
        <div class="rarity-row">
            <div class="rarity-item"><span class="rarity-badge small legendary">传说</span><span class="text-green">📈 100% 上涨</span></div>
            <div class="rarity-item"><span class="rarity-badge small epic">史诗</span><span class="text-green">📈 50%</span>  /  <span class="text-red">📉 50%</span></div>
            <div class="rarity-item"><span class="rarity-badge small rare">稀有</span><span class="text-green">📈 25%</span>  /  <span class="text-red">📉 75%</span></div>
            <div class="rarity-item"><span class="rarity-badge small common">普通</span><span class="text-green">📈 10%</span>  /  <span class="text-red">📉 90%</span></div>
        </div>
        <p>
            💡 <span class="rarity-badge small legendary">传说</span>卡牌 <b>100% 正向增长</b>，是最稳健的投资标的。<br>
            稀有度越高的卡牌，上涨概率越大，下跌概率越小。
        </p>
    </div>

    <!-- 3. Portfolio -->
    <div class="help-card" id="portfolio">
        <h3><span class="rarity-badge epic" style="font-size:14px;padding:2px 10px">📦</span> 3. 持仓</h3>
        <p>
            在顶栏中你名字的左边进入 <b>持仓</b> 页面。<br>
            你可以看到你所拥有的全部卡牌，并将其<b>放置到展示台</b>上。<br>
            当该股票价格发生波动时，你的盈利额也会同步变化。<br><br>
            当你的总盈利 > 0 时，可以点击 <b>「💰 提现盈利」</b> 将利润提取为代币。<br>
            提现后持仓均价重置为当前价，未来再涨可继续提现。
        </p>
    </div>

    <!-- 4. Market -->
    <div class="help-card" id="market">
        <h3><span class="rarity-badge common" style="font-size:14px;padding:2px 10px">🔄</span> 4. 市场</h3>
        <p>
            在 <b>持仓</b> 界面中，每行股票右侧有出售表单，可以设置数量和单价，将卡牌挂到市场上出售（默认按市场价）。<br><br>
            在 <b>市场</b> 界面可以浏览所有在售卡牌，<b>购买</b>想要的卡牌，也可以<b>取消</b>自己的挂单。<br>
            挂单被购买后，代币直接进入你的账户。
        </p>
    </div>

    <!-- 5. Special Pools -->
    <div class="help-card" id="special">
        <h3><span class="rarity-badge legendary" style="font-size:14px;padding:2px 10px">⭐</span> 5. 特殊卡池</h3>
        <p>
            在某些特殊日子（如愚人节、情人节、中秋节、元宵节等节日），我们会开放<b>特殊卡池</b>。<br><br>
            这些卡池里的卡牌在节日过后将<b>不再出现</b>，因此被归类为 <span class="rarity-badge small limited">绝版</span> 卡牌。<br>
            <span class="rarity-badge small limited">绝版</span>卡牌通常在市场上获得更高的价格与更大的增幅：<br>
            ✅ 与<span class="rarity-badge small legendary">传说</span>一样 <b>100% 正向盈率</b><br>
            ✅ 增幅大于 <b>20%</b>（高于<span class="rarity-badge small common">普通</span>的 2~17%）<br><br>
            ⚠️ <span class="rarity-badge small limited">绝版</span>卡牌在节日结束后才会变为<span class="rarity-badge small limited">绝版</span>状态，节日结束前仍属于<span class="rarity-badge small rare">稀有</span>。
        </p>
    </div>

    <!-- 6. Other -->
    <div class="help-card" id="other">
        <h3><span class="rarity-badge epic" style="font-size:14px;padding:2px 10px">📌</span> 6. 其他说明</h3>
        <p>
            还没想好要写啥。
        </p>
    </div>
</div>

<?php include __DIR__ . '/layout/footer.php'; ?>
