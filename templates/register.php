<?php
/**
 * OIManka - Register
 */
$pageTitle = '注册';
include __DIR__ . '/layout/header.php';
?>

<div class="page-auth">
    <div class="auth-card">
        <h1 class="auth-title">✨ 注册 <?= APP_NAME ?></h1>
        <p class="auth-subtitle">创建账户即可获得 <?= STARTER_TOKENS ?> 枚起始代币！</p>

        <form method="POST" action="<?= url('/register.php') ?>" class="auth-form">
            <div class="form-group">
                <label for="username">用户名</label>
                <input type="text" id="username" name="username" required autofocus
                       placeholder="选择你的用户名" class="form-input" minlength="2" maxlength="32">
            </div>
            <div class="form-group">
                <label for="password">密码</label>
                <input type="password" id="password" name="password" required
                       placeholder="至少6个字符" class="form-input" minlength="6">
            </div>
            <div class="form-group">
                <label for="confirm_password">确认密码</label>
                <input type="password" id="confirm_password" name="confirm_password" required
                       placeholder="再次输入密码" class="form-input" minlength="6">
            </div>
            <button type="submit" class="btn btn-primary btn-block btn-lg">
                🎁 注册并获取 <?= STARTER_TOKENS ?> 代币
            </button>
        </form>

        <div class="auth-footer">
            已有账户？<a href="<?= url('/login.php') ?>">立即登录</a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/layout/footer.php'; ?>
