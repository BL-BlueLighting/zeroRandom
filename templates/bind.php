<?php
/**
 * zero Random - HustOJ Account Binding
 *
 * Supports two verification methods:
 *   1. 站内信验证 (recommended) — send 6-digit code via HUSTOJ mail system
 *   2. 密码验证 — traditional password check against HUSTOJ users table
 */
$pageTitle = '绑定OJ账号';

Session::requireAuth();
$userId = Session::userId();
$db = Database::getInstance();

// Check HustOJ is configured
if (!platform_configured('hustoj')) {
    Session::flash('error', 'HustOJ 尚未配置，请联系管理员。');
    header('Location: /');
    exit;
}

// Check current binding
$stmt = $db->prepare("SELECT * FROM user_hustoj_bindings WHERE user_id = ?");
$stmt->execute([$userId]);
$binding = $stmt->fetch();

$message = null;
$pendingOjUserId = null;

// Handle traditional password-based binding (POST form, non-AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'bind_password') {
        $ojUserId = trim($_POST['oj_user_id'] ?? '');
        $ojPassword = $_POST['oj_password'] ?? '';

        if (empty($ojUserId)) {
            $message = ['error', '请输入OJ用户ID。'];
        } elseif (empty($ojPassword)) {
            $message = ['error', '请输入OJ密码以验证身份。'];
        } else {
            $adapter = AdapterManager::get('hustoj');
            if (!$adapter || !$adapter->testConnection()) {
                $message = ['error', '无法连接到HustOJ数据库，请检查配置。'];
            } else {
                // First check if this OJ user is already bound
                $stmt = $db->prepare("SELECT user_id FROM user_hustoj_bindings WHERE oj_user_id = ? AND user_id != ?");
                $stmt->execute([$ojUserId, $userId]);
                if ($stmt->fetch()) {
                    $message = ['error', '该 OJ 账号已被其他用户绑定。'];
                } elseif (!$adapter->verifyPassword($ojUserId, $ojPassword)) {
                    $message = ['error', 'OJ密码验证失败！请检查你的用户ID和密码是否正确。'];
                } else {
                    $ojUser = $adapter->fetchUserData($ojUserId);
                    if (!$ojUser) {
                        $message = ['error', '未在OJ中找到该用户ID。'];
                    } else {
                        $stmt = $db->prepare("
                            INSERT INTO user_hustoj_bindings (user_id, oj_user_id, oj_username, total_ac, last_synced_at)
                            VALUES (?, ?, ?, ?, datetime('now'))
                            ON CONFLICT(user_id) DO UPDATE SET
                                oj_user_id = excluded.oj_user_id,
                                oj_username = excluded.oj_username,
                                total_ac = excluded.total_ac,
                                last_synced_at = datetime('now')
                        ");
                        $stmt->execute([$userId, $ojUserId, $ojUser['username'], $ojUser['total_ac']]);

                        $tokens = $ojUser['total_ac'] * TOKENS_PER_AC;
                        TokenSystem::add($userId, $tokens, 'reward');

                        $message = ['success', "绑定成功！OJ用户: {$ojUser['username']}，{$ojUser['total_ac']} AC → 获得 {$tokens} 代币！"];
                        $binding = $db->query("SELECT * FROM user_hustoj_bindings WHERE user_id = {$userId}")->fetch();
                    }
                }
            }
        }
    }

    if ($action === 'sync') {
        if (!$binding) {
            $message = ['error', '请先绑定OJ账号。'];
        } else {
            $lastSync = strtotime($binding['last_synced_at'] ?? '2000-01-01');
            $cooldown = SYNC_COOLDOWN_MINUTES * 60;
            if (time() - $lastSync < $cooldown) {
                $remaining = ceil(($cooldown - (time() - $lastSync)) / 60);
                $message = ['error', "同步冷却中，请在 {$remaining} 分钟后重试。"];
            } else {
                $adapter = AdapterManager::get('hustoj');
                if ($adapter && $adapter->testConnection()) {
                    $ojUser = $adapter->fetchUserData($binding['oj_user_id']);
                    if ($ojUser) {
                        $newAc = $ojUser['total_ac'];
                        $oldAc = (int)$binding['total_ac'];
                        $diffAc = $newAc - $oldAc;

                        if ($diffAc > 0) {
                            $tokens = $diffAc * TOKENS_PER_AC;
                            TokenSystem::add($userId, $tokens, 'reward');
                            $message = ['success', "同步成功！新增 {$diffAc} AC → 获得 {$tokens} 代币！"];
                        } else {
                            $message = ['success', "同步成功！AC数量无变化。"];
                        }

                        $db->prepare("UPDATE user_hustoj_bindings SET total_ac = ?, last_synced_at = datetime('now') WHERE user_id = ?")
                            ->execute([$newAc, $userId]);
                        $binding = $db->query("SELECT * FROM user_hustoj_bindings WHERE user_id = {$userId}")->fetch();
                    } else {
                        $message = ['error', 'OJ用户数据获取失败。'];
                    }
                } else {
                    $message = ['error', '无法连接到HustOJ。'];
                }
            }
        }
    }

    if ($action === 'unbind') {
        $db->prepare("DELETE FROM user_hustoj_bindings WHERE user_id = ?")->execute([$userId]);
        $binding = null;
        $message = ['success', '已解绑OJ账号。'];
    }
}

include __DIR__ . '/layout/header.php';
?>

<div class="page-auth">
    <div class="auth-card" style="max-width:520px">
        <h1 class="auth-title">🔗 绑定OJ账号</h1>
        <p class="auth-subtitle">绑定HustOJ账号后，每次AC都会自动转为代币（1 AC = <?= TOKENS_PER_AC ?> 代币）</p>

        <?php if (isset($message)): ?>
        <div class="flash-message flash-<?= $message[0] ?>"><?= $message[1] ?></div>
        <?php endif; ?>

        <?php if ($binding): ?>
        <!-- ─── Already Bound ─── -->
        <div class="binding-info">
            <div class="detail-stat">
                <div class="ds-label">已绑定OJ用户</div>
                <div class="ds-value">👤 <?= htmlspecialchars($binding['oj_username']) ?></div>
            </div>
            <div class="detail-stat">
                <div class="ds-label">OJ用户ID</div>
                <div class="ds-value">#<?= htmlspecialchars($binding['oj_user_id']) ?></div>
            </div>
            <div class="detail-stat">
                <div class="ds-label">累计AC</div>
                <div class="ds-value">✅ <?= $binding['total_ac'] ?></div>
            </div>
            <div class="detail-stat">
                <div class="ds-label">上次同步</div>
                <div class="ds-value">🕐 <?= $binding['last_synced_at'] ?? '从未' ?></div>
            </div>
        </div>

        <div style="display:flex;gap:8px;margin-top:16px">
            <form method="POST" style="flex:1">
                <input type="hidden" name="action" value="sync">
                <button class="btn btn-primary btn-block">🔄 同步AC</button>
            </form>
            <form method="POST" style="flex:1">
                <input type="hidden" name="action" value="unbind">
                <button class="btn btn-danger btn-block" onclick="return confirm('确认解绑？')">解除绑定</button>
            </form>
        </div>

        <?php else: ?>
        <!-- Password method (hidden by default) -->
        <div id="passwordMethod">
            <form method="POST" class="auth-form">
                <input type="hidden" name="action" value="bind_password">
                <div class="form-group">
                    <label for="oj_user_id_pw">OJ用户ID</label>
                    <input type="text" id="oj_user_id_pw" name="oj_user_id" required
                           placeholder="输入你在OJ上的用户ID" class="form-input">
                </div>
                <div class="form-group">
                    <label for="oj_password">OJ密码</label>
                    <input type="password" id="oj_password" name="oj_password" required
                           placeholder="输入OJ密码以验证身份" class="form-input">
                </div>
                <button type="submit" class="btn btn-primary btn-block btn-lg">🔗 绑定</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// ─── Password Method Toggle ───

function togglePasswordMethod(e) {
    e?.preventDefault();
    const step1 = document.getElementById('step1');
    const step2 = document.getElementById('step2');
    const pw = document.getElementById('passwordMethod');

    // If password method is visible, switch back
    if (pw.style.display !== 'none') {
        pw.style.display = 'none';
        step1.style.display = 'block';
        return;
    }

    step1.style.display = 'none';
    step2.style.display = 'none';
    pw.style.display = 'block';
}

// ─── Helpers ───

function showResult(id, type, msg) {
    const el = document.getElementById(id);
    el.className = 'flash-message flash-' + type;
    el.textContent = msg;
    el.style.display = 'block';
}

function hideResult(id) {
    const el = document.getElementById(id);
    el.style.display = 'none';
    el.textContent = '';
}
</script>

<?php include __DIR__ . '/layout/footer.php'; ?>
