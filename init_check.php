<?php
/**
 * OIManka - Database initialization check.
 * Include this AFTER config.php and Database.php in entry point files.
 */
require_once __DIR__ . '/helpers.php';

// Load user's number format preference into session
if (isset($_SESSION['user_id']) && !isset($_SESSION['number_style'])) {
    try {
        $__db = Database::getInstance();
        $__stmt = $__db->prepare("SELECT number_style FROM users WHERE id = ?");
        $__stmt->execute([$_SESSION['user_id']]);
        $__ns = $__stmt->fetchColumn();
        $_SESSION['number_style'] = $__ns ?: 'wan';
    } catch (Exception $e) { $_SESSION['number_style'] = 'wan'; }
}

$__dataDir = dirname(defined('DB_PATH') ? DB_PATH : __DIR__ . '/data/oimanka.db');
if (!is_writable(dirname($__dataDir)) && !is_writable($__dataDir)) {
    http_response_code(500);
    ?><!DOCTYPE html>
<html lang="zh-CN">
<head><meta charset="UTF-8"><title>错误 - <?= APP_NAME ?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Noto Sans SC",sans-serif;background:#0a0a1a;color:#e0e0e0;min-height:100vh;display:flex;align-items:center;justify-content:center}
.card{background:#12122a;border-radius:12px;padding:40px;max-width:500px;border:1px solid #2a2a4a;text-align:center}
h1{color:#f87171;font-size:22px;margin-bottom:12px}
p{color:#a0a0c0;font-size:14px;line-height:1.6}
.error{background:#3a1010;border:1px solid #f87171;color:#fca5a5;padding:12px;border-radius:8px}
</style>
</head>
<body>
<div class="card"><h1>⚠️ 系统错误</h1>
<div class="error">! 程序 data 目录不可写，请联系管理人员或刷新页面 !</div>
<p class="text-muted" style="margin-top:16px;font-size:12px"><?= htmlspecialchars($__dataDir) ?></p>
</div></body></html><?php
    exit;
}

// Skip check on setup.php
$__script = basename($_SERVER['SCRIPT_NAME'] ?? '');
if ($__script !== 'setup.php' && $__script !== 'install.php') {
    try {
        if (!Database::isInitialized()) {
            header('Location: setup.php');
            exit;
        }
    } catch (Exception $e) {
        header('Location: setup.php');
        exit;
    }
}
unset($__dataDir, $__script);
