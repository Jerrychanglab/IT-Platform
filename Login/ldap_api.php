<?php
// 設定回應為 JSON 格式
header('Content-Type: application/json');

// 連接 LDAP 伺服器
$ldapconn = ldap_connect("ldap://10.31.34.8") or die("無法連接到 LDAP 伺服器。");

// 設置 LDAP 選項
ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3); // 使用 LDAP v3
ldap_set_option($ldapconn, LDAP_OPT_REFERRALS, 0);        // 禁用 LDAP 轉介

$ldaprdn = "cn=admin,dc=chungyo,dc=local";  // 管理員帳號
$ldappass = 'passwd';               // 管理員密碼

// 綁定 LDAP 伺服器
if (!ldap_bind($ldapconn, $ldaprdn, $ldappass)) {
    $error = ldap_error($ldapconn); // 顯示具體的 LDAP 錯誤信息
    echo json_encode(['status' => 'error', 'message' => 'LDAP 認證失敗: ' . $error]);
    exit;
}

// 取得動作類型
$action = $_POST['action'] ?? '';

// 新增群組
if ($action === 'add_group') {
    $group_name = $_POST['group_name'] ?? '';

    // 檢查群組名稱是否為空
    if (empty($group_name)) {
        echo json_encode(['status' => 'error', 'message' => '群組名稱不可為空']);
        exit;
    }

    // 檢查群組是否已存在
    $search = ldap_search($ldapconn, "ou=Groups,dc=chungyo,dc=local", "(cn=$group_name)");
    $entries = ldap_get_entries($ldapconn, $search);

    if ($entries["count"] > 0) {
        echo json_encode(['status' => 'error', 'message' => '群組已存在']);
        exit;
    }

    // 新增群組
    $group_dn = "cn=$group_name,ou=Groups,dc=chungyo,dc=local";
    $entry = [];
    $entry["cn"] = $group_name;
    $entry["objectClass"] = ["top", "groupOfUniqueNames"];
    $entry["uniqueMember"] = "cn=admin,dc=chungyo,dc=local"; // 初始成員為 admin

    if (ldap_add($ldapconn, $group_dn, $entry)) {
        // 更新 groups.php 檔案
        updateGroupsFile($group_name);
        echo json_encode(['status' => 'success', 'message' => "群組 $group_name 新增成功"]);
    } else {
        echo json_encode(['status' => 'error', 'message' => "群組新增失敗"]);
    }
}

// 更新 groups.php 檔案
function updateGroupsFile($group_name) {
    $groupsFile = 'groups.php';
    if (file_exists($groupsFile)) {
        $groups = include $groupsFile;
    } else {
        $groups = [];
    }

    // 新增群組
    $groups[$group_name] = [];

    // 寫入 groups.php 檔案
    file_put_contents($groupsFile, "<?php\nreturn " . var_export($groups, true) . ";\n");
}

// 刪除群組
if ($action === 'delete_group') {
    $group_name = $_POST['group_name'] ?? '';

    // 檢查群組是否存在
    $search = ldap_search($ldapconn, "ou=Groups,dc=chungyo,dc=local", "(cn=$group_name)");
    $entries = ldap_get_entries($ldapconn, $search);

    if ($entries["count"] === 0) {
        echo json_encode(['status' => 'error', 'message' => '群組不存在']);
        exit;
    }

    // 檢查群組中是否有其他使用者（排除 admin）
    $group_dn = "cn=$group_name,ou=Groups,dc=chungyo,dc=local";
    $search_members = ldap_search($ldapconn, $group_dn, "(uniqueMember=*)", ["uniqueMember"]);
    $members = ldap_get_entries($ldapconn, $search_members);

    // 過濾掉 admin 使用者
    $non_admin_members = [];
    if ($members["count"] > 0) {
        foreach ($members[0]["uniquemember"] as $index => $member_dn) {
            if ($index !== "count" && $member_dn !== 'cn=admin,dc=chungyo,dc=local') {
                $non_admin_members[] = $member_dn;
            }
        }
    }

    // 如果群組內有非 admin 使用者，則不允許刪除
    if (count($non_admin_members) > 0) {
        echo json_encode(['status' => 'error', 'message' => '群組還有使用者，無法刪除']);
        exit;
    }

    // 刪除群組
    if (ldap_delete($ldapconn, $group_dn)) {
        // 更新 groups.php 檔案
        removeGroupFromFile($group_name);
        echo json_encode(['status' => 'success', 'message' => "群組 $group_name 已成功刪除"]);
    } else {
        echo json_encode(['status' => 'error', 'message' => "刪除群組失敗"]);
    }
}

// 從 groups.php 中移除群組
function removeGroupFromFile($group_name) {
    $groupsFile = 'groups.php';
    if (file_exists($groupsFile)) {
        $groups = include $groupsFile;
        unset($groups[$group_name]);
        file_put_contents($groupsFile, "<?php\nreturn " . var_export($groups, true) . ";\n");
    }
}

// 編輯群組名稱
if ($action === 'edit_group') {
    $old_group_name = $_POST['old_group_name'] ?? '';
    $new_group_name = $_POST['new_group_name'] ?? '';

    // 檢查群組名稱是否提供
    if (empty($old_group_name) || empty($new_group_name)) {
        echo json_encode(['status' => 'error', 'message' => '舊群組和新群組名稱都不可為空']);
        exit;
    }

    // 定義舊群組和新群組的 DN
    $old_group_dn = "cn=$old_group_name,ou=Groups,dc=chungyo,dc=local";
    $new_rdn = "cn=$new_group_name";

    // 檢查群組是否存在
    $search = ldap_search($ldapconn, "ou=Groups,dc=chungyo,dc=local", "(cn=$old_group_name)");
    $entries = ldap_get_entries($ldapconn, $search);

    if ($entries["count"] === 0) {
        echo json_encode(['status' => 'error', 'message' => '舊群組不存在']);
        exit;
    }

    // 修改群組名稱
    if (ldap_rename($ldapconn, $old_group_dn, $new_rdn, null, true)) {
        // 更新 groups.php 檔案
        renameGroupInFile($old_group_name, $new_group_name);
        echo json_encode(['status' => 'success', 'message' => "群組名稱已修改為 $new_group_name"]);
    } else {
        $error = ldap_error($ldapconn);
        echo json_encode(['status' => 'error', 'message' => "修改群組名稱失敗: $error"]);
    }
}

// 更新 groups.php 中的群組名稱
function renameGroupInFile($old_group_name, $new_group_name) {
    $groupsFile = 'groups.php';

    // 檢查檔案是否存在
    if (file_exists($groupsFile)) {
        $groups = include $groupsFile;

        // 修改群組名稱
        if (isset($groups[$old_group_name])) {
            $groups[$new_group_name] = $groups[$old_group_name];
            unset($groups[$old_group_name]);
            file_put_contents($groupsFile, "<?php\nreturn " . var_export($groups, true) . ";\n");
        }
    }
}

// 新增使用者
if ($action === 'add_user') {
    $username = $_POST['username'] ?? '';
    $role = $_POST['role'] ?? '';
    $group = $_POST['group'] ?? '';

    // 檢查使用者名稱和群組是否為空
    if (empty($username) || empty($group)) {
        echo json_encode(['status' => 'error', 'message' => '使用者名稱及群組不可為空']);
        exit;
    }

    // 生成隨機密碼
    $password = generateRandomPassword(12);
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    // 定義使用者的 DN
    $user_dn = "uid=$username,ou=People,dc=chungyo,dc=local";

    // 檢查使用者是否已存在
    $search = ldap_search($ldapconn, "ou=People,dc=chungyo,dc=local", "(uid=$username)");
    $entries = ldap_get_entries($ldapconn, $search);

    if ($entries["count"] > 0) {
        echo json_encode(['status' => 'error', 'message' => '使用者已存在']);
        exit;
    }

    // 建立使用者的條目
    $entry = [];
    $entry["cn"] = $username;
    $entry["sn"] = $username;
    $entry["uid"] = $username;
    $entry["userPassword"] = ldap_hash_password($password);
    $entry["objectClass"] = ["top", "person", "organizationalPerson", "inetOrgPerson"];
    $entry["description"] = $group;

    // 新增使用者到 LDAP
    if (ldap_add($ldapconn, $user_dn, $entry)) {
        // 將使用者加入群組
        $group_dn = "cn=$group,ou=Groups,dc=chungyo,dc=local";
        $mod_entry = ["uniqueMember" => $user_dn];

        if (ldap_mod_add($ldapconn, $group_dn, $mod_entry)) {
            // 更新 users.php 檔案
            if (updateUsersFile($username, $hashedPassword, $role, $group)) {
                echo json_encode(['status' => 'success', 'message' => "使用者 $username 已新增並加入群組 $group，並更新至 users.php", 'password' => $password]);
            } else {
                echo json_encode(['status' => 'error', 'message' => "使用者新增成功，但更新 users.php 失敗", 'password' => $password]);
            }
        } else {
            $error = ldap_error($ldapconn);
            echo json_encode(['status' => 'error', 'message' => "使用者新增成功，但加入群組失敗: $error", 'password' => $password]);
        }
    } else {
        $error = ldap_error($ldapconn);
        echo json_encode(['status' => 'error', 'message' => "使用者新增失敗: $error"]);
    }
}

// 更新 users.php 檔案
function updateUsersFile($username, $passwordHash, $role, $group) {
    $usersFile = 'users.php';

    // 檢查 users.php 是否存在
    if (file_exists($usersFile)) {
        $users = include $usersFile;
    } else {
        $users = [];
    }

    // 更新使用者資訊
    $users[$username] = [
        'password' => $passwordHash,
        'role' => $role,
        'group' => $group,
    ];

    // 寫入更新後的陣列到 users.php 檔案
    $content = "<?php\nreturn " . var_export($users, true) . ";\n";

    // 寫入檔案
    return file_put_contents($usersFile, $content);
}

// 移除使用者
if ($action === 'delete_user') {
    $username = $_POST['username'] ?? '';

    // 檢查使用者名稱是否提供
    if (empty($username)) {
        echo json_encode(['status' => 'error', 'message' => '使用者名稱不可為空']);
        exit;
    }

    // 定義使用者的 DN
    $user_dn = "uid=$username,ou=People,dc=chungyo,dc=local";

    // 檢查使用者是否存在於 LDAP
    $search = ldap_search($ldapconn, "ou=People,dc=chungyo,dc=local", "(uid=$username)");
    $entries = ldap_get_entries($ldapconn, $search);

    if ($entries["count"] === 0) {
        echo json_encode(['status' => 'error', 'message' => '使用者不存在']);
        exit;
    }

    // 查詢該使用者所屬的群組，並從群組中移除
    $group_search = ldap_search($ldapconn, "ou=Groups,dc=chungyo,dc=local", "(uniqueMember=$user_dn)", ["cn"]);
    $groups = ldap_get_entries($ldapconn, $group_search);

    if ($groups["count"] > 0) {
        foreach ($groups as $group) {
            if (isset($group['dn'])) {
                $group_dn = $group['dn'];
                $mod_entry = ["uniqueMember" => $user_dn];
                ldap_mod_del($ldapconn, $group_dn, $mod_entry);  // 從群組中移除使用者
            }
        }
    }

    // 移除使用者資料
    if (ldap_delete($ldapconn, $user_dn)) {
        // 使用者成功從 OpenLDAP 中刪除，更新 users.php
        removeUserFromFile($username);
        echo json_encode(['status' => 'success', 'message' => "使用者 $username 已成功移除"]);
    } else {
        $error = ldap_error($ldapconn);
        echo json_encode(['status' => 'error', 'message' => "無法刪除使用者: $error"]);
    }
}

// 從 users.php 檔案中移除使用者
function removeUserFromFile($username) {
    $usersFile = 'users.php';
    if (file_exists($usersFile)) {
        $users = include $usersFile;
        unset($users[$username]); // 從 users 陣列中移除該使用者
        file_put_contents($usersFile, "<?php\nreturn " . var_export($users, true) . ";\n");
    }
}

// 搬移使用者群組
if ($action === 'move_user_group') {
    $username = $_POST['username'] ?? '';
    $new_group = $_POST['new_group'] ?? '';
    $new_role = $_POST['role'] ?? ''; // 新增角色變數

    // 檢查使用者是否存在
    $search = ldap_search($ldapconn, "ou=People,dc=chungyo,dc=local", "(uid=$username)");
    $entries = ldap_get_entries($ldapconn, $search);

    if ($entries["count"] === 0) {
        echo json_encode(['status' => 'error', 'message' => '使用者不存在']);
        exit;
    }

    // 查詢當前群組並從當前群組中移除使用者
    $user_dn = "uid=$username,ou=People,dc=chungyo,dc=local";
    $group_search = ldap_search($ldapconn, "ou=Groups,dc=chungyo,dc=local", "(uniqueMember=$user_dn)", ["dn", "uniqueMember"]);
    $groups = ldap_get_entries($ldapconn, $group_search);

    if ($groups["count"] > 0) {
        foreach ($groups as $group) {
            if (isset($group['dn'])) {
                // 移除使用者
                ldap_mod_del($ldapconn, $group['dn'], ['uniqueMember' => $user_dn]);
            }
        }
    }

    // 檢查新群組是否存在
    $new_group_search = ldap_search($ldapconn, "ou=Groups,dc=chungyo,dc=local", "(cn=$new_group)");
    $new_group_entries = ldap_get_entries($ldapconn, $new_group_search);

    if ($new_group_entries["count"] === 0) {
        echo json_encode(['status' => 'error', 'message' => '新群組不存在']);
        exit;
    }

    // 將使用者加入新群組
    $new_group_dn = "cn=$new_group,ou=Groups,dc=chungyo,dc=local";
    if (ldap_mod_add($ldapconn, $new_group_dn, ['uniqueMember' => $user_dn])) {
        // 更新 users.php 檔案
        updateUserGroupInFile($username, $new_group, $new_role);
        echo json_encode(['status' => 'success', 'message' => "使用者 $username 已成功移動到新群組 $new_group"]);
    } else {
        $error = ldap_error($ldapconn);
        echo json_encode(['status' => 'error', 'message' => "搬移群組失敗: $error"]);
    }
}

// 更新 users.php 中的使用者群組
function updateUserGroupInFile($username, $new_group, $new_role) {
    $usersFile = 'users.php';
    if (file_exists($usersFile)) {
        $users = include $usersFile;
        if (isset($users[$username])) {
		$users[$username]['group'] = $new_group;
		$users[$username]['role'] = $new_role;
            file_put_contents($usersFile, "<?php\nreturn " . var_export($users, true) . ";\n");
        }
    }
}

// 重設密碼
if ($action === 'reset_password') {
    $username = $_POST['username'] ?? '';
    $new_password = $_POST['new_password'] ?? '';

    // 檢查用戶是否存在
    $search = ldap_search($ldapconn, "ou=People,dc=chungyo,dc=local", "(uid=$username)");
    $entries = ldap_get_entries($ldapconn, $search);

    if ($entries["count"] === 0) {
        echo json_encode(['status' => 'error', 'message' => '用戶不存在']);
        exit;
    }

    // 更新用戶的 LDAP 密碼
    $user_dn = "uid=$username,ou=People,dc=chungyo,dc=local";
    $entry = [];
    $entry["userPassword"] = ldap_hash_password($new_password);

    if (ldap_modify($ldapconn, $user_dn, $entry)) {
        echo json_encode(['status' => 'success', 'message' => '密碼重設成功']);
    } else {
        $error = ldap_error($ldapconn);
        echo json_encode(['status' => 'error', 'message' => "密碼重設失敗: $error"]);
    }
}

// 查詢群組
if ($action === 'query_groups') {
    // 查詢基點與過濾條件
    $base_dn = "ou=Groups,dc=chungyo,dc=local";
    $filter = "(objectClass=groupOfUniqueNames)";
    $attributes = ["uniqueMember"];

    // 執行查詢
    $search = ldap_search($ldapconn, $base_dn, $filter, $attributes);
    $entries = ldap_get_entries($ldapconn, $search);

    // 處理查詢結果
    if ($entries["count"] > 0) {
        $results = [];
        foreach ($entries as $entry) {
            if (isset($entry['dn'])) {
                $group = [];
                $group['dn'] = $entry['dn'];
                // 檢查 uniqueMember 是否存在
                if (isset($entry['uniqueMember'])) {
                    $group['uniqueMembers'] = $entry['uniqueMember'];
                } else {
                    $group['uniqueMembers'] = [];
                }
                $results[] = $group;
            }
        }
        echo json_encode(['status' => 'success', 'groups' => $results]);
    } else {
        echo json_encode(['status' => 'error', 'message' => '沒有找到群組']);
    }
}

// 關閉 LDAP 連線
ldap_close($ldapconn);

// 密碼加密函數
function ldap_hash_password($password) {
    return "{SHA}" . base64_encode(pack("H*", sha1($password)));
}

// 生成隨機密碼的函數
function generateRandomPassword($length = 12) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*';
    $charactersLength = strlen($characters);
    $randomPassword = '';
    for ($i = 0; $i < $length; $i++) {
        $randomPassword .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomPassword;
}
?>
