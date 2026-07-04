<?php $pageTitle = '转账'; include __DIR__ . '/layout/header.php'; ?>
<div class="page-auth">
    <div class="auth-card" style="max-width:450px">
        <h1 class="auth-title">💸 转账</h1>
        <p class="auth-subtitle">向其他用户转账代币</p>

        <?php if ($result): ?>
        <div class="flash-message flash-<?= $result['success'] ? 'success' : 'error' ?>"><?= $result['message'] ?></div>
        <?php if ($result['success']): ?>
        <div style="text-align:center;margin-top:16px">
            <a href="<?= url('/transfer.php') ?>" class="btn btn-primary">继续转账</a>
            <a href="<?= url('/') ?>" class="btn btn-outline">返回首页</a>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <?php if (!$result || !$result['success']): ?>
        <form method="POST" class="auth-form" id="transferForm">
            <div class="form-group">
                <label>接收方（用户名或用户ID）</label>
                <input type="text" name="target" required class="form-input" placeholder="输入用户名或ID" value="<?= htmlspecialchars($_POST['target'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>转账金额</label>
                <input type="number" name="amount" id="amountInput" required min="1" step="1" class="form-input" placeholder="输入代币数量" value="<?= (int)($_POST['amount'] ?? 0) > 0 ? (int)$_POST['amount'] : '' ?>">
            </div>
            <div class="form-group">
                <label>确认密码</label>
                <input type="password" name="password" required class="form-input" placeholder="输入你的账户密码">
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg" id="transferBtn">💸 转账</button>

            <div id="bigAmountWarning" style="display:none;margin-top:8px">
                <div class="flash-message flash-error" id="bigAmountMsg">⚠️ 转账金额较大，请多次确认</div>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<script>
const BIG_AMOUNT = 1000000;
let confirmClicks = 0;
let confirmTimer = null;

document.getElementById('amountInput')?.addEventListener('input', function() {
    const val = parseFloat(this.value) || 0;
    const warning = document.getElementById('bigAmountWarning');
    if (val >= BIG_AMOUNT) {
        warning.style.display = 'block';
    } else {
        warning.style.display = 'none';
        confirmClicks = 0;
    }
});

document.getElementById('transferBtn')?.addEventListener('click', function(e) {
    const val = parseFloat(document.getElementById('amountInput').value) || 0;
    if (val < BIG_AMOUNT) return; // normal submit

    e.preventDefault();
    confirmClicks++;
    const msg = document.getElementById('bigAmountMsg');
    const btn = document.getElementById('transferBtn');

    if (confirmTimer) clearTimeout(confirmTimer);

    if (confirmClicks === 1) {
        msg.textContent = '⚠️ 转账金额大于 100 万，再次点击确认';
        btn.textContent = '⚠️ 再次确认转账';
    } else if (confirmClicks === 2) {
        msg.textContent = '🔴 最后一次确认！点击后将转账 ' + val.toFixed(0) + ' 枚代币';
        btn.textContent = '🔴 最终确认转账';
    } else {
        confirmClicks = 0;
        document.getElementById('transferForm').submit();
        return;
    }

    confirmTimer = setTimeout(() => {
        confirmClicks = 0;
        msg.textContent = '⚠️ 转账金额较大，请多次确认';
        btn.textContent = '💸 转账';
    }, 5000);
});
</script>
<?php include __DIR__ . '/layout/footer.php'; ?>
