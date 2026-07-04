<?php
/**
 * OIManka - Login
 */
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        Session::flash('error', '请输入用户名和密码。');
        header('Location: ' . url('/login.php'));
        exit;
    }

    $db = Database::getInstance();
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        Session::login((int)$user['id'], $user['username']);
        Session::flash('success', '欢迎回来, ' . $user['username'] . '!');
        header('Location: ' . url('/index.php'));
    } else {
        Session::flash('error', '用户名或密码错误。');
        header('Location: ' . url('/login.php'));
    }
    exit;
}

require_once __DIR__ . '/templates/login.php';
