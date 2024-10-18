<?php
session_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newRole = $_POST['role'] ?? '';

    // 如果當前角色是 user，並且想切換到 admin，則不允許
    if ($_SESSION['role'] === 'user' && $newRole === 'admin') {
        echo json_encode(['success' => false, 'message' => '無法切換到管理者角色，請聯繫管理員！']);
    } elseif ($newRole === 'admin' || $newRole === 'user') {
        // 允許 admin 使用者切換角色
        $_SESSION['pageType'] = $newRole;
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => '無效的角色']);
    }
} else {
    echo json_encode(['success' => false, 'message' => '無效的請求方式']);
}
?>
