<?php
/**
 * OIManka - Login
 */
$pageTitle = '登录';
include __DIR__ . '/layout/header.php';


?>

<div class="page-auth">
    <div class="auth-card">
        <h1 class="auth-title">🔐 登录 <?= APP_NAME ?></h1>
        <p class="auth-subtitle">登录你的账户，参与股票交易和扭蛋抽卡</p>

        <form method="POST" action="<?= url('/login.php') ?>" class="auth-form">
            <div class="form-group">
                <label for="username">用户名</label>
                <input type="text" id="username" name="username" required autofocus
                       placeholder="输入用户名" class="form-input">
            </div>
            <div class="form-group">
                <label for="password">密码</label>
                <input type="password" id="password" name="password" required
                       placeholder="输入密码" class="form-input">
            </div>
            <button type="submit" class="btn btn-primary btn-block btn-lg">登录</button>
        </form>

        <div class="auth-footer">
            还没有账户？<a href="<?= url('/register.php') ?>">立即注册</a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/layout/footer.php'; ?>
