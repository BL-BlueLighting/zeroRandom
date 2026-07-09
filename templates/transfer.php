<?php $pageTitle = '转账'; include __DIR__ . '/layout/header.php';
$isKs = is_kaleidoscope();
$unit = $isKs ? 'SKYT' : '代币';
$icon = $isKs ? '🌀' : '🪙';
?>
<div class="page-auth">
    <div class="auth-card" style="max-width:450px">
        <h1 class="auth-title">💸 <?= $isKs ? '天界' : '' ?>转账</h1>
        <p class="auth-subtitle">向其他用户转账<?= $unit ?></p>

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
                <label>转账金额 (<?= $unit ?>)</label>
                <input type="number" name="amount" id="amountInput" required min="1" step="1" class="form-input" placeholder="输入<?= $unit ?>数量" value="<?= (int)($_POST['amount'] ?? 0) > 0 ? (int)$_POST['amount'] : '' ?>">
            </div>
            <div class="form-group">
                <label>确认密码</label>
                <input type="password" name="password" required class="form-input" placeholder="输入你的账户密码">
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg" id="transferBtn">💸 <?= $isKs ? '天界' : '' ?>转账</button>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/layout/footer.php'; ?>
