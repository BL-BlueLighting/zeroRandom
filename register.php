<?php
/**
 * OIManka - Register
 */
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (empty($username) || empty($password)) {
        Session::flash('error', '请填写所有必填字段。');
        header('Location: ' . url('/register.php'));
        exit;
    }
    if ($password !== $confirm) {
        Session::flash('error', '两次输入的密码不一致。');
        header('Location: ' . url('/register.php'));
        exit;
    }
    if (strlen($password) < 6) {
        Session::flash('error', '密码长度至少为6个字符。');
        header('Location: ' . url('/register.php'));
        exit;
    }

    try {
        $db = Database::getInstance();
        $userCount = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $isAdmin = ($userCount === 0) ? 1 : 0;

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (username, password_hash, token_balance, is_admin) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $hash, STARTER_TOKENS, $isAdmin]);

        $userId = (int)$db->lastInsertId();
        Session::login($userId, $username);

        if ($isAdmin) {
            Session::flash('success', "🎉 注册成功！您是本站第一位用户，已被设为管理员。已赠送 " . STARTER_TOKENS . " 枚代币。");
        } else {
            Session::flash('success', "注册成功！已赠送您 " . STARTER_TOKENS . " 枚代币。");
        }
        header('Location: ' . url('/index.php'));
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'UNIQUE') !== false) {
            Session::flash('error', '该用户名已被注册，请换一个。');
        } else {
            Session::flash('error', '注册失败: ' . $e->getMessage());
        }
        header('Location: ' . url('/register.php'));
    }
    exit;
}

require_once __DIR__ . '/templates/register.php';
