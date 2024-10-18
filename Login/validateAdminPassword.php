<?php
// 引入 users.php 文件
$users = include 'users.php'; // 確保這裡的路徑正確

// 檢查密碼驗證請求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_password = $_POST['password'];

    // 驗證輸入的密碼是否與 admin 密碼匹配
    if (password_verify($input_password, $users['admin']['password'])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
}
?>
