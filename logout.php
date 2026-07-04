<?php
/**
 * OIManka - Logout
 */
require_once __DIR__ . '/bootstrap.php';

Session::logout();
Session::flash('success', '已安全退出。');
header('Location: ' . url('/index.php'));
exit;
