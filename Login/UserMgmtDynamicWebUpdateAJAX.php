<?php
session_start();

// 檢查 users.php 和 groups.php 的最後修改時間
$last_update_users = filemtime('users.php');
$last_update_groups = filemtime('groups.php');

// 找出兩個文件中較新的修改時間
$last_update_time = max($last_update_users, $last_update_groups);

// 比較最後檢查時間和最新的修改時間
if (isset($_SESSION['last_check_time']) && $_SESSION['last_check_time'] < $last_update_time) {
    // 如果檢查時間早於最新修改時間，表示有更新
    echo json_encode(['updated' => true]);
} else {
    // 沒有更新
    echo json_encode(['updated' => false]);
}

// 更新 session 中的最後檢查時間
$_SESSION['last_check_time'] = time();
?>
