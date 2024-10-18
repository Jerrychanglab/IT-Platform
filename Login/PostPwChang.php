<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['user'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $generated_file = $_POST['generated_file'] ?? '';

    // 確保新密碼和確認密碼一致
    if ($new_password !== $confirm_password) {
        $status = "密碼不一致，請返回並重新輸入。";
        display_result($status);
        exit;
    }

    // 檢查用戶名是否為空
    if (empty($username)) {
        $status = "用戶名為空，請檢查表單是否正確提交。";
        display_result($status);
        exit;
    }

    // 連接到 LDAP
    $ldapconn = ldap_connect("ldap://10.31.34.8") or die("無法連接到 LDAP 伺服器。");
    ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldapconn, LDAP_OPT_REFERRALS, 0);

    $ldaprdn = "cn=admin,dc=chungyo,dc=local";  // 管理員帳號
    $ldappass = 'passwd';            // 管理員密碼

    if (ldap_bind($ldapconn, $ldaprdn, $ldappass)) {
        // 檢查用戶是否存在
        $search = ldap_search($ldapconn, "ou=People,dc=chungyo,dc=local", "(uid={$username})");
        $entries = ldap_get_entries($ldapconn, $search);

        if ($entries["count"] === 0) {
            $status = "用戶不存在，請檢查 LDAP 搜索條件和用戶是否存在。";
            display_result($status);
            exit;
        } else {
            // 更新用戶密碼
            $user_dn = "uid=$username,ou=People,dc=chungyo,dc=local";
            $entry = [];
            $entry["userPassword"] = ldap_hash_password($new_password);

            if (ldap_modify($ldapconn, $user_dn, $entry)) {
                // 密碼成功更新到 LDAP，現在更新 users.php
                update_users_file($username, $new_password);

                // 刪除生成的 HTML 文件
                if (!empty($generated_file) && file_exists($generated_file)) {
                    unlink($generated_file); // 移除文件
                }

                $status = "密碼重設成功！";
                display_result($status); // 顯示結果
            } else {
                $status = "密碼重設失敗：" . ldap_error($ldapconn);
                display_result($status);
            }
        }
        ldap_close($ldapconn);
    } else {
        $status = "LDAP 認證失敗：" . ldap_error($ldapconn);
        display_result($status);
    }
} else {
    echo "無效的請求方法。";
}

// 密碼加密函數
function ldap_hash_password($password)
{
    return "{SHA}" . base64_encode(pack("H*", sha1($password)));
}

// 更新 users.php 文件
function update_users_file($username, $new_password)
{
    $users_file = 'users.php';

    if (file_exists($users_file)) {
        $users = include($users_file);
    } else {
        $users = [];
    }

    // 檢查用戶是否存在於 users.php 中
    if (isset($users[$username])) {
        // 將密碼哈希儲存在 users.php 中
        $users[$username]['password'] = password_hash($new_password, PASSWORD_DEFAULT);

        // 將更新後的資料寫回 users.php
        file_put_contents($users_file, "<?php\nreturn " . var_export($users, true) . ";\n");
    }
}

// 顯示結果並添加「返回首頁」按鈕
function display_result($status)
{
    echo "
    <!DOCTYPE html>
    <html lang='zh-TW'>
    <head>
        <meta charset='UTF-8'>
        <title>密碼修改結果</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background-color: #f4f4f4;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                margin: 0;
            }
            .container {
                background-color: #fff;
                padding: 20px;
                border-radius: 10px;
                box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
                width: 300px;
                text-align: center;
            }
            button {
                margin-top: 20px;
                padding: 10px 15px;
                background-color: #007B8F;
                color: white;
                border: none;
                border-radius: 5px;
                cursor: pointer;
            }
            button:hover {
                background-color: #005f6b;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <h3>{$status}</h3>
            <button onclick=\"location.href='/index.php'\">返回首頁</button>
        </div>
    </body>
    </html>";
}
?>
