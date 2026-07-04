<?php
require_once __DIR__ . '/bootstrap.php';
Session::requireAuth();
header('Content-Type: application/json; charset=utf-8');

if (!platform_configured('hustoj')) {
    echo json_encode(['success' => false, 'message' => 'OJ 尚未配置。']);
    exit;
}

$code = trim($_POST['code'] ?? '');
if (empty($code)) {
    echo json_encode(['success' => false, 'message' => '请输入验证码。']);
    exit;
}

$db = Database::getInstance();
$localUserId = Session::userId();

$stmt = $db->prepare("
    SELECT * FROM bind_verifications
    WHERE user_id = ? AND verified = 0 AND expires_at > datetime('now')
    ORDER BY created_at DESC LIMIT 1
");
$stmt->execute([$localUserId]);
$verification = $stmt->fetch();

if (!$verification) {
    echo json_encode(['success' => false, 'message' => '没有有效的验证请求，请重新发送验证码。']);
    exit;
}

if (md5($code) !== $verification['code_md5']) {
    echo json_encode(['success' => false, 'message' => '验证码错误，请重新输入。']);
    exit;
}

$db->prepare("UPDATE bind_verifications SET verified = 1 WHERE id = ?")
    ->execute([$verification['id']]);

try {
    $activeAdapter = platform_configured('hustoj') ? 'hustoj' : (platform_configured('hydroj') ? 'hydroj' : null);
    $adapter = $activeAdapter ? AdapterManager::get($activeAdapter) : null;
    $userData = $adapter ? $adapter->fetchUserData($verification['oj_user_id']) : null;

    if (!$userData) {
        echo json_encode(['success' => false, 'message' => '无法获取 OJ 用户数据。']);
        exit;
    }

    $stmt = $db->prepare("
        INSERT INTO user_hustoj_bindings (user_id, oj_user_id, oj_username, total_ac, last_synced_at)
        VALUES (?, ?, ?, ?, datetime('now'))
    ");
    $stmt->execute([$localUserId, $verification['oj_user_id'], $userData['username'], $userData['total_ac']]);

    $tokens = $userData['total_ac'] * TOKENS_PER_AC;
    if ($tokens > 0) {
        TokenSystem::add($localUserId, $tokens, 'reward');
    }

    $db->prepare("DELETE FROM bind_verifications WHERE id = ?")->execute([$verification['id']]);

    echo json_encode([
        'success' => true,
        'message' => "绑定成功！OJ 用户: {$userData['username']}，{$userData['total_ac']} AC → 获得 {$tokens} 代币！",
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '绑定失败：' . $e->getMessage()]);
}
exit;
