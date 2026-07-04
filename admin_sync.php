<?php
require_once __DIR__ . '/bootstrap.php';
Session::requireAuth();
$user = Session::user();
if ($user['username'] !== 'admin') {
    Session::flash('error', '仅管理员可执行同步操作。');
    header('Location: ' . url('/index.php'));
    exit;
}
$adapterName = $_GET['adapter'] ?? DEFAULT_ADAPTER;
$adapter = AdapterManager::get($adapterName);
if (!$adapter) {
    Session::flash('error', '适配器不存在: ' . $adapterName);
    header('Location: ' . url('/index.php'));
    exit;
}
$result = $adapter->syncStocks();
Session::flash('success', "同步完成: 更新了 {$result['items_synced']} 支股票");
header('Location: ' . url('/market.php'));
exit;
