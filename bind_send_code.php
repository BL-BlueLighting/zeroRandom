<?php
require_once __DIR__ . '/bootstrap.php';
Session::requireAuth();
header('Content-Type: application/json; charset=utf-8');

if (!platform_configured('hustoj')) {
    echo json_encode(['success' => false, 'message' => 'OJ 尚未配置。']);
    exit;
}

$ojUserId = trim($_POST['oj_user_id'] ?? '');
if (empty($ojUserId)) {
    echo json_encode(['success' => false, 'message' => '请输入OJ用户ID。']);
    exit;
}

try {
    $activeAdapter = platform_configured('hustoj') ? 'hustoj' : (platform_configured('hydroj') ? 'hydroj' : null);
    $adapter = $activeAdapter ? AdapterManager::get($activeAdapter) : null;
    if (!$adapter || !$adapter->testConnection()) {
        echo json_encode(['success' => false, 'message' => '无法连接到 OJ 数据库。']);
        exit;
    }

    $userData = $adapter->fetchUserData($ojUserId);
    if (!$userData) {
        echo json_encode(['success' => false, 'message' => '未在 OJ 中找到该用户 ID。']);
        exit;
    }

    $db = Database::getInstance();
    $localUserId = Session::userId();

    $stmt = $db->prepare("SELECT user_id FROM user_hustoj_bindings WHERE oj_user_id = ? AND user_id != ?");
    $stmt->execute([$ojUserId, $localUserId]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => '该 OJ 账号已被其他用户绑定。']);
        exit;
    }

    $stmt = $db->prepare("SELECT id FROM user_hustoj_bindings WHERE user_id = ?");
    $stmt->execute([$localUserId]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => '您已经绑定了 OJ 账号，请先解绑。']);
        exit;
    }

    $db->prepare("DELETE FROM bind_verifications WHERE user_id = ? AND verified = 0")
        ->execute([$localUserId]);

    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $codeMd5 = md5($code);

    $stmt = $db->prepare("
        INSERT INTO bind_verifications (user_id, oj_user_id, code, code_md5, verified, expires_at, created_at)
        VALUES (?, ?, ?, ?, 0, datetime('now', '+10 minutes'), datetime('now'))
    ");
    $stmt->execute([$localUserId, $ojUserId, $code, $codeMd5]);

    $adapter->sendMail($ojUserId, 'OJ账号绑定验证码',
        "您在 zero Random 进行 OJ 账号绑定的验证码是：{$code}\n\n请在10分钟内完成验证，请勿泄露给他人。");

    echo json_encode([
        'success' => true,
        'message' => '验证码已通过 OJ 站内信发送，请登录 OJ 查看站内信获取验证码。',
        'oj_username' => $userData['username'],
        'oj_user_id' => $ojUserId,
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '操作失败：' . $e->getMessage()]);
}
exit;
