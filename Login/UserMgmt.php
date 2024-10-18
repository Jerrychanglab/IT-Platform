<?php
session_start();

// 防止頁面被快取
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

$timeout_duration = 600;
$webip = '10.31.33.18';

// 檢查會話是否過期
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header('Location: Login.php');
    exit;
}

$_SESSION['LAST_ACTIVITY'] = time();

// 檢查用戶是否已經登入
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: Login.php');
    exit;
}

// 定義用戶和群組文件
$users_file = './users.php';
$groups_file = './groups.php';
$users = include($users_file);
$groups = include($groups_file);

// 驗證帳號是否符合規則
function validate_username($username)
{
    return preg_match('/^[a-zA-Z0-9_]{1,20}$/', $username);
}

// 驗證密碼長度
function validate_password($password)
{
    return strlen($password) <= 20;
}

// 處理新增、修改、刪除用戶及群組的請求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $username = $_POST['username'] ?? null;
    $password = $_POST['password'] ?? null;
    $role = $_POST['role'] ?? null;
    $group = $_POST['group'] ?? null;

    // 處理群組
    if ($action === 'add_group') {
        $group_name = $_POST['group_name'];

        // 檢查群組名稱是否符合字元規則（只允許英文、數字、底線，長度限制 1-20 個字元）
        if (!preg_match('/^[a-zA-Z0-9_-]{6,20}$/', $group_name)) {
            echo json_encode(['status' => 'error', 'message' => '群組名稱只能包含英文、數字、底線且最短6，長度不能超過 20 個字元']);
            exit;
        }
        // 檢查群組是否已經存在
        if (!isset($groups[$group_name])) {
            $groups[$group_name] = [];
            file_put_contents($groups_file, "<?php\nreturn " . var_export($groups, true) . ";\n");
            echo json_encode(['status' => 'success', 'message' => '群組新增成功']);
        } else {
            echo json_encode(['status' => 'error', 'message' => '群組已存在']);
        }
    } elseif ($action === 'delete_group') {
        $group_name = $_POST['group_name'];

        // 檢查是否是 admin 群組
        if ($group_name === 'admin') {
            echo json_encode(['status' => 'error', 'message' => 'admin 群組無法刪除']);
            exit;
        }

        // 檢查群組是否有使用者
        $group_in_use = false;
        foreach ($users as $user) {
            if ($user['group'] === $group_name) {
                $group_in_use = true;
                break;
            }
        }

        if ($group_in_use) {
            echo json_encode(['status' => 'error', 'message' => '該群組有人使用，無法刪除']);
            exit;
        }

        // 如果群組無人使用且不是 admin，則允許刪除
        if (isset($groups[$group_name])) {
            unset($groups[$group_name]);
            file_put_contents($groups_file, "<?php\nreturn " . var_export($groups, true) . ";\n");
            echo json_encode(['status' => 'success', 'message' => '群組刪除成功']);
        } else {
            echo json_encode(['status' => 'error', 'message' => '群組不存在']);
        }
    }


    // 處理用戶
    if ($action === 'add_user') {
        if (strtolower($username) === 'admin') {
            echo json_encode(['status' => 'error', 'message' => '用戶名 "admin" 已經存在，無法新增']);
            exit;
        }

        if (!validate_username($username)) {
            echo json_encode(['status' => 'error', 'message' => '帳號只能包含英文、數字和底線，且不超過 20 個字元']);
            exit;
        }

        if (!validate_password($password)) {
            echo json_encode(['status' => 'error', 'message' => '密碼不能超過 20 個字元']);
            exit;
        }

        if (isset($users[$username])) {
            echo json_encode(['status' => 'error', 'message' => '用戶已存在']);
            exit;
        }

        $users[$username] = ['password' => password_hash($password, PASSWORD_DEFAULT), 'role' => $role, 'group' => $group];
        file_put_contents($users_file, "<?php\nreturn " . var_export($users, true) . ";\n");
        echo json_encode(['status' => 'success', 'message' => '新增成功']);
    } elseif ($action === 'edit_user') {
        $username = $_POST['username'];
        $role = $_POST['role'];
        $group = $_POST['group'];

        // 檢查是否是 admin 帳號，並阻止替換群組
        if ($username === 'admin' && isset($users[$username]) && $users[$username]['group'] !== $group) {
            echo json_encode(['status' => 'error', 'message' => 'admin 帳號的群組不能修改']);
            exit;
        }

        // 檢查角色是否符合要求
        if ($username === 'admin' && $role === 'user') {
            echo json_encode(['status' => 'error', 'message' => '無法將 admin 帳號角色修改為 user']);
            exit;
        }

        // 更新密碼和其他資料
        if ($password) {
            $users[$username]['password'] = password_hash($password, PASSWORD_DEFAULT);
        }
        $users[$username]['role'] = $role;
        $users[$username]['group'] = $group;
        file_put_contents($users_file, "<?php\nreturn " . var_export($users, true) . ";\n");
        echo json_encode(['status' => 'success', 'message' => '編輯成功']);
    } elseif ($action === 'delete_user') {
        if ($username === 'admin') {
            echo json_encode(['status' => 'error', 'message' => 'admin 帳號無法刪除']);
            exit;
        }

        unset($users[$username]);
        file_put_contents($users_file, "<?php\nreturn " . var_export($users, true) . ";\n");
        echo json_encode(['status' => 'success', 'message' => '刪除成功']);
    } elseif ($action === 'reset_password_link') {
        if (isset($users[$username])) {
            // 檢查是否已經生成過重設連結
            $existingFiles = glob("./url/{$username}-*.html");

            if (!empty($existingFiles)) {
                // 如果已經存在重設連結，返回該連結
                $existingFileName = basename($existingFiles[0]);
                echo json_encode(['status' => 'success', 'url' => "http://$webip/Login/url/{$existingFileName}"]);
            } else {
                // 生成隨機密碼重設連結
                $randomString = md5(uniqid(rand(), true));
                $fileName = "{$username}-{$randomString}.html";
                $filePath = "./url/{$fileName}";
                $htmlContent = "
        <!DOCTYPE html>
        <html lang='zh-TW'>
        <head>
            <meta charset='UTF-8'>
            <title>重設密碼</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    background-color: #f4f4f4;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    height: 100vh;
                    margin: 0;
                    text-transform: uppercase;
                }
                .container {
                    background-color: #fff;
                    padding: 20px;
                    border-radius: 10px;
                    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
                    width: 300px;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                }
                td {
                    padding: 10px;
                    border: 1px solid #ddd;
                    text-align: left;
                    white-space: nowrap;
                    font-weight: bold;
                }
                input[type='text'], input[type='password'] {
                    width: 100%;
                    padding: 8px;
                    box-sizing: border-box;
                }
                .buttons {
                    display: flex;
                    justify-content: space-between;
                    margin-top: 10px;
                }
                .buttons input[type='submit'], .buttons .toggle-password {
                    width: 48%;
                    padding: 10px;
                    background-color: #4CAF50;
                    color: white;
                    border: none;
                    border-radius: 5px;
                    cursor: pointer;
                    font-size: 16px;
                }
                .buttons input[type='submit']:hover, .buttons .toggle-password:hover {
                    background-color: #45a049;
                }
                .buttons input[type='submit']:disabled {
                    background-color: #ccc;
                    cursor: not-allowed;
                }
            </style>
            <script>
                function togglePasswordVisibility() {
                    var newPasswordInput = document.getElementsByName('new_password')[0];
                    var confirmPasswordInput = document.getElementsByName('confirm_password')[0];
                    if (newPasswordInput.type === 'password') {
                        newPasswordInput.type = 'text';
                        confirmPasswordInput.type = 'text';
                    } else {
                        newPasswordInput.type = 'password';
                        confirmPasswordInput.type = 'password';
                    }
                }

                function checkPasswords() {
                    var newPasswordInput = document.getElementsByName('new_password')[0];
                    var confirmPasswordInput = document.getElementsByName('confirm_password')[0];
                    var submitButton = document.getElementById('submitBtn');

                    if (newPasswordInput.value === confirmPasswordInput.value && newPasswordInput.value !== '') {
                        submitButton.disabled = false;
                    } else {
                        submitButton.disabled = true;
                    }
                }
            </script>
        </head>
        <body>
            <div class='container'>
                <form action='http://{$webip}/Login/PostPwChang.php' method='post'>
                    <input type='hidden' name='generated_file' value='{$filePath}'>
                    <table>
                        <tr>
                            <td>帳號:</td>
                            <td><input type='text' name='user' value='{$username}' readonly></td>
                        </tr>
                        <tr>
                            <td>新密碼:</td>
                            <td><input type='password' name='new_password' onkeyup='checkPasswords()'></td>
                        </tr>
                        <tr>
                            <td>確認密碼:</td>
                            <td><input type='password' name='confirm_password' onkeyup='checkPasswords()'></td>
                        </tr>
                    </table>
                    <div class='buttons'>
                        <button type='button' class='toggle-password' onclick='togglePasswordVisibility()'>顯示/隱藏 密碼</button>
                        <input type='submit' id='submitBtn' value='送出' disabled>
                    </div>
                </form>
            </div>
        </body>
        </html>";
                file_put_contents($filePath, $htmlContent);
                echo json_encode(['status' => 'success', 'url' => "http://$webip/Login/url/{$fileName}"]);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => '用戶不存在']);
        }
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="zh-Hant">

<head>
    <meta charset="UTF-8">
    <title>帳號與群組管理</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .container {
            width: 100%;
            max-width: 700px;
            background-color: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            border-radius: 8px;
        }

        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th,
        td {
            padding: 10px;
            text-align: center;
            border: 1px solid #ddd;
        }

        th {
            background-color: #007B8F;
            color: white;
        }

        td {
            background-color: #f9f9f9;
        }

        button {
            padding: 10px 15px;
            margin: 5px;
            background-color: #007B8F;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 5px;
        }

        button:hover {
            background-color: #005f6b;
        }

        .button-container {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }

        .btn-add,
        .btn-group-manage {
            padding: 10px 15px;
            background-color: #007B8F;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 5px;
        }

        .btn-add:hover,
        .btn-group-manage:hover {
            background-color: #005f6b;
        }

        /* 自定義模態框樣式 */
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.4);
        }

        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: auto;
            max-width: 35%;
            /* 設置最大寬度，確保彈窗不會過大 */
            min-width: 200px;
            /* 設置最小寬度，確保彈窗不會過小 */
            border-radius: 8px;
            text-align: center;
        }

        .modal-url {
            background-color: #f9f9f9;
            border: 1px solid #ccc;
            padding: 10px;
            word-break: break-all;
            font-size: 16px;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }

        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }

        .modal-buttons {
            margin-top: 20px;
        }

        .modal-buttons button {
            padding: 10px 20px;
            margin: 0 10px;
            border-radius: 5px;
            cursor: pointer;
        }

        .modal-buttons .confirm {
            background-color: #007B8F;
            color: white;
        }

        .modal-buttons .cancel {
            background-color: #ccc;
        }

        .modal-buttons .confirm:hover {
            background-color: #005f6b;
        }

        .modal-buttons .cancel:hover {
            background-color: #bbb;
        }

        ul {
            list-style-type: none;
            /* 移除點號 */
            padding-left: 0;
            /* 去掉左側縮進 */
        }

        ul.group-list {
            list-style-type: none;
            /* 移除點號 */
            padding-left: 0;
            /* 去掉左側縮進 */
        }

        .group-list-container {
            max-height: 300px;
            /* Adjust the height */
            max-width: 75%;
            background-color: #EBEBEB;
            overflow-y: scroll;
            padding-left: 0;
            list-style-type: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            margin: 0 auto;
            border-radius: 5px;
        }

        .group-item {
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 80%;
            margin: 5px auto;
        }

        .group-item:hover {
            background-color: #f0f8ff;
            /* 當滑鼠懸停時變為淺藍色 */
            box-shadow: 0 0 8px rgba(0, 0, 0, 0.2);
            /* 懸停時加深陰影效果 */
            border-color: #007B8F;
            /* 邊框顏色改變 */
        }

        .group-item button {
            background-color: #007B8F;
            border-radius: 5px;
            color: white;
            padding: 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .group-item button:hover {
            background-color: #005f6b;
        }

        .group-list-container::-webkit-scrollbar {
            width: 10px;
        }

        .group-list-container::-webkit-scrollbar-thumb {
            background-color: #007B8F;
            border-radius: 10px;
        }

        /*測試*/
        .flex-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            /* 控制每個項目之間的間距 */
            margin-bottom: 15px;
        }

        .form-item {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            flex: 1;
            /* 確保每個項目均勻分佈寬度 */
        }

        .box {
            border: 1px solid #ddd;
            /* 添加邊框 */
            padding: 10px;
            /* 添加內邊距 */
            border-radius: 5px;
            /* 圓角效果 */
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.1);
            /* 添加陰影效果 */
        }

        .box:hover {
            background-color: #f0f8ff;
            /* 當滑鼠懸停時變為淺藍色 */
            box-shadow: 0 0 8px rgba(0, 0, 0, 0.2);
            /* 懸停時加深陰影效果 */
            border-color: #007B8F;
            /* 邊框顏色改變 */
        }

        select {
            padding: 5px;
            font-size: 16px;
            border-radius: 5px;
        }

        select option {
            padding: 10px;
            font-size: 16px;
        }

        #resetLink {
            white-space: nowrap;
            /* 禁止自動換行 */
            overflow-x: auto;
            /* 如果內容超出，橫向顯示滾輪 */
            display: block;
            /* 確保 div 填滿寬度 */
            max-width: 100%;
            /* 限制寬度不超過彈窗寬度 */
        }

        #resetLink:hover {
            background-color: #f0f8ff;
            box-shadow: 0 0 8px rgba(0, 0, 0, 0.2);
            border-color: #007B8F;
        }

        #groupName:hover {
            background-color: #f0f8ff;
            box-shadow: 0 0 8px rgba(0, 0, 0, 0.2);
            border-color: #007B8F;
            border-radius: 5px;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>帳號與群組管理</h1>

        <table id="userTable">
            <tr>
                <th>用戶名</th>
                <th>角色</th>
                <th>群組</th>
                <th>操作</th>
            </tr>
            <?php foreach ($users as $username => $info) : ?>
                <tr id="row-<?php echo htmlspecialchars($username); ?>">
                    <td><?php echo htmlspecialchars($username); ?></td>
                    <td><?php echo htmlspecialchars($info['role']); ?></td>
                    <td><?php echo htmlspecialchars($info['group']); ?></td>
                    <td>
                        <?php if ($username !== 'admin') : ?>
                            <button onclick="openEditModal('<?php echo htmlspecialchars($username); ?>', '<?php echo htmlspecialchars($info['role']); ?>', '<?php echo htmlspecialchars($info['group']); ?>')">編輯</button>
                            <button onclick="openDeleteModal('<?php echo htmlspecialchars($username); ?>')">刪除</button>
                        <?php else : ?>
                            <button disabled style="background-color: #ccc; cursor: not-allowed;">編輯</button>
                            <button disabled style="background-color: #ccc; cursor: not-allowed;">刪除</button>
                        <?php endif; ?>
                        <button onclick="openResetPasswordModal('<?php echo htmlspecialchars($username); ?>')">重設密碼</button>
                    </td>
                </tr>
            <?php endforeach; ?>


        </table>

        <div class="button-container">
            <button class="btn-add" onclick="openAddModal()">新增帳號</button>
            <button class="btn-group-manage" onclick="openGroupManageModal()">管理群組</button>
        </div>
    </div>

    <!-- Add Account Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAddModal()">&times;</span>
            <h2>新增帳號</h2>
            <form id="addForm">
                <!-- 將每個表單項目包在一個框架內 -->
                <div class="form-row flex-container">
                    <div class="form-item box">
                        <label for="addUsername">帳號:</label>
                        <input type="text" id="addUsername" name="username" required>
                    </div>
                    <div class="form-item box">
                        <label for="addRole">角色:</label>
                        <select id="addRole" name="role">
                            <option value="user" selected>user</option>
                            <option value="admin">admin</option>
                        </select>
                    </div>
                    <div class="form-item box">
                        <label for="addGroup">群組:</label>
                        <select id="addGroup" name="group">
                            <?php foreach ($groups as $group_name => $group) : ?>
                                <?php if ($group_name === 'admin') : ?>
                                    <option value="<?php echo htmlspecialchars($group_name); ?>" disabled><?php echo htmlspecialchars($group_name); ?></option>
                                <?php else : ?>
                                    <option value="<?php echo htmlspecialchars($group_name); ?>"><?php echo htmlspecialchars($group_name); ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="modal-buttons">
                    <button type="button" class="confirm" onclick="submitAddForm()">確定</button>
                    <button type="button" class="cancel" onclick="closeAddModal()">取消</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Account Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h2>編輯帳號</h2>
            <form id="editForm">
                <input type="hidden" id="editUsername" name="username">
                <label for="editRole">角色:</label>
                <select id="editRole" name="role">
                    <option value="admin">admin</option>
                    <option value="user">user</option>
                </select>
                <label for="editGroup">群組:</label>
                <select id="editGroup" name="group">
                    <?php foreach ($groups as $group_name => $group) : ?>
                        <?php if ($group_name === 'admin') : ?>
                            <option value="<?php echo htmlspecialchars($group_name); ?>" disabled><?php echo htmlspecialchars($group_name); ?></option>
                        <?php else : ?>
                            <option value="<?php echo htmlspecialchars($group_name); ?>"><?php echo htmlspecialchars($group_name); ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <div class="modal-buttons">
                    <button type="button" class="confirm" onclick="submitEditForm()">確定</button>
                    <button type="button" class="cancel" onclick="closeEditModal()">取消</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeDeleteModal()">&times;</span>
            <h2>確定要刪除該用戶嗎？</h2>
            <div class="modal-buttons">
                <button class="confirm" id="confirmDeleteBtn">確定</button>
                <button class="cancel" onclick="closeDeleteModal()">取消</button>
            </div>
        </div>
    </div>

    <!-- Group Management Modal -->
    <div id="groupManageModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeGroupManageModal()">&times;</span>
            <h2>群組管理</h2>
            <form id="groupForm">
                <label for="groupName">新增群組:</label>
                <input type="text" id="groupName" name="group_name" required>
                <div class="modal-buttons">
                    <button type="button" class="confirm" onclick="submitAddGroup()">新增群組</button>
                    <button type="button" class="cancel" onclick="closeGroupManageModal()">取消</button>
                </div>
            </form>
            <h3>現有群組</h3>
            <div class="group-list-container">
                <?php foreach ($groups as $group_name => $group) : ?>
                    <div class="group-item">
                        <span><?php echo htmlspecialchars($group_name); ?></span>
                        <button onclick="openDeleteGroupModal('<?php echo htmlspecialchars($group_name); ?>')">刪除</button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <!-- Group Delete Confirmation Modal -->
    <div id="deleteGroupModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeDeleteGroupModal()">&times;</span>
            <h2>確定要刪除該群組嗎？</h2>
            <p id="deleteGroupMessage"></p>
            <div class="modal-buttons">
                <button class="confirm" id="confirmDeleteGroupBtn">確定</button>
                <button class="cancel" onclick="closeDeleteGroupModal()">取消</button>
            </div>
        </div>
    </div>

    <!-- Reset Password Link Modal -->
    <div id="resetLinkModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeResetLinkModal()">&times;</span>
            <h2>重設密碼連結</h2>
            <div class="modal-url" id="resetLink"></div>
            <button onclick="closeResetLinkModal()">關閉</button>
        </div>
    </div>


    <script>
        let currentUsernameToDelete = '';
        let currentUsernameToEdit = '';
        let currentGroupToEdit = '';
        let currentGroupToDelete = '';
        // 打開刪除帳號的模態框
        function openDeleteModal(username) {
            currentUsernameToDelete = username;
            document.getElementById('deleteModal').style.display = 'block';
        }

        // 關閉刪除帳號的模態框
        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        // 確認刪除帳號
        document.getElementById('confirmDeleteBtn').onclick = function() {
            closeDeleteModal();
            deleteUser(currentUsernameToDelete);
        };

        // 刪除帳號
        function deleteUser(username) {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'ldap_api.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    if (response.status === 'success') {
                        location.reload(); // 刷新頁面以顯示更改
                    } else {
                        alert(response.message || '操作失敗，請重試！');
                    }
                }
            };
            xhr.send('action=delete_user&username=' + encodeURIComponent(username));
        }

        // 打開新增帳號的模態框
        function openAddModal() {
            document.getElementById('addUsername').value = '';
            document.getElementById('addRole').value = 'user';
            document.getElementById('addModal').style.display = 'block';
        }

        // 關閉新增帳號的模態框
        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }

        // 提交新增帳號表單
        function generateRandomPassword(length) {
            const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()_+';
            let password = '';
            for (let i = 0; i < length; i++) {
                password += characters.charAt(Math.floor(Math.random() * characters.length));
            }
            return password;
        }

        function submitAddForm() {
            const username = document.getElementById('addUsername').value;
            const role = document.getElementById('addRole').value;
            const group = document.getElementById('addGroup').value;

            // 生成隨機密碼
            const password = generateRandomPassword(20);

            // 確認表單數據是否正確讀取
            console.log('Username:', username, 'Role:', role, 'Group:', group, 'Password:', password);

            const usernameRegex = /^[a-zA-Z0-9_-]{2,20}$/;

            if (!usernameRegex.test(username)) {
                alert('用戶名稱無效：必須為2至20個字元，僅允許數字、字母、底線(_) 和連字符(-)');
                return; // 終止函數執行
            }

            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'ldap_api.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

            const data = 'action=add_user&username=' + encodeURIComponent(username) +
                '&password=' + encodeURIComponent(password) + // 傳遞生成的密碼
                '&role=' + encodeURIComponent(role) +
                '&group=' + encodeURIComponent(group);

            console.log('Sending Data:', data); // 確認傳送的資料

            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    console.log('Response:', xhr.responseText); // 顯示伺服器回應
                    const response = JSON.parse(xhr.responseText);
                    if (response.status === 'success') {
                        location.reload(); // 刷新頁面以顯示新增的用戶
                    } else {
                        alert(response.message || '操作失敗，請重試！');
                    }
                }
            };

            xhr.send(data);
        }

        // 打開編輯帳號的模態框
        function openEditModal(username, role, group) {
            currentUsernameToEdit = username;
            document.getElementById('editUsername').value = username;
            document.getElementById('editRole').value = role;
            document.getElementById('editGroup').value = group;
            document.getElementById('editModal').style.display = 'block';
        }

        // 關閉編輯帳號的模態框
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // 提交編輯使用者表單
        function submitEditForm() {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'ldap_api.php', true); // 指定後端處理 API
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');


            const data = 'action=move_user_group&username=' + encodeURIComponent(document.getElementById('editUsername').value) +
                '&new_group=' + encodeURIComponent(document.getElementById('editGroup').value) +
                '&role=' + encodeURIComponent(document.getElementById('editRole').value);

            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    if (response.status === 'success') {
                        location.reload(); // 刷新頁面以顯示更新後的資料
                    } else {
                        alert(response.message || '操作失敗，請重試！');
                    }
                }
            };

            xhr.send(data);
        }

        // 打開群組管理的模態框
        function openGroupManageModal() {
            document.getElementById('groupName').value = '';
            document.getElementById('groupManageModal').style.display = 'block';
        }

        // 關閉群組管理的模態框
        function closeGroupManageModal() {
            document.getElementById('groupManageModal').style.display = 'none';
        }

        // 提交新增群組表單
        function submitAddGroup() {
            const groupName = document.getElementById('groupName').value;

            // 驗證群組名稱：最多10個字元，僅允許數字、字母、底線和連字符
            const regex = /^[a-zA-Z0-9_-]{2,10}$/;

            if (!regex.test(groupName)) {
                alert('群組名稱無效：最少2個字元，最多10個字元，僅允許數字、字母、底線(_) 和連字符(-)');
                return; // 終止函數執行
            }

            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'ldap_api.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            const data = 'action=add_group&group_name=' + encodeURIComponent(groupName);
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    if (response.status === 'success') {
                        location.reload(); // 刷新頁面以顯示新增的群組
                    } else {
                        alert(response.message || '操作失敗，請重試！');
                    }
                }
            };
            xhr.send(data);
        }



        // 打開編輯群組模態框
        function openEditGroupModal(groupName) {
            currentGroupToEdit = groupName;
            const newGroupName = prompt('請輸入新的群組名稱:', groupName);
            if (newGroupName) {
                editGroup(currentGroupToEdit, newGroupName);
            }
        }

        // 編輯群組
        function editGroup(oldGroupName, newGroupName) {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'deleteGroup', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            const data = 'action=edit_group&old_group_name=' + encodeURIComponent(oldGroupName) +
                '&new_group_name=' + encodeURIComponent(newGroupName);
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    if (response.status === 'success') {
                        location.reload(); // 刷新頁面以顯示更改
                    } else {
                        alert(response.message || '操作失敗，請重試！');
                    }
                }
            };
            xhr.send(data);
        }

        // 打開刪除群組的模態框
        function openDeleteGroupModal(groupName) {
            currentGroupToDelete = groupName;
            document.getElementById('deleteGroupMessage').textContent = '此操作群組 "' + groupName + '" ，無法恢復。';
            document.getElementById('deleteGroupModal').style.display = 'block'; // 顯示模態框
        }

        // 關閉刪除群組的模態框
        function closeDeleteGroupModal() {
            document.getElementById('deleteGroupModal').style.display = 'none'; // 隱藏模態框
        }

        // 確認刪除群組的操作
        document.getElementById('confirmDeleteGroupBtn').onclick = function() {
            closeDeleteGroupModal(); // 關閉模態框
            deleteGroup(currentGroupToDelete); // 執行刪除群組的函數
        };

        // 刪除群組的函數
        function deleteGroup(groupName) {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'ldap_api.php', true); // 發送 POST 請求到後端
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded'); // 設置請求頭
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText); // 解析後端回應
                    if (response.status === 'success') {
                        location.reload(); // 刷新頁面以顯示更改
                    } else {
                        alert(response.message || '操作失敗，請重試！');
                    }
                }
            };
            xhr.send('action=delete_group&group_name=' + encodeURIComponent(groupName)); // 發送群組名稱到後端
        }
        // 發起密碼重設視窗
        function openResetPasswordModal(username) {
            generateResetLink(username);
        }

        // 生成重設密碼連結
        function generateResetLink(username) {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    if (response.status === 'success') {
                        openResetLinkModal(response.url);
                    } else {
                        alert(response.message || '操作失敗，請重試！');
                    }
                }
            };
            xhr.send('action=reset_password_link&username=' + encodeURIComponent(username));
        }

        // 打開重設密碼連結彈窗
        function openResetLinkModal(url) {
            document.getElementById("resetLink").textContent = url;
            document.getElementById("resetLinkModal").style.display = "block";
        }

        // 關閉重設密碼連結彈窗
        function closeResetLinkModal() {
            document.getElementById("resetLinkModal").style.display = "none";
        }

        // 每隔 5 秒檢查一次是否有更新
        setInterval(function() {
            // 檢查是否有模態框正在顯示
            const modalOpen = document.querySelector('.modal[style="display: block;"]');

            // 如果有模態框打開則跳過檢查
            if (modalOpen) return;

            // 檢查資料是否更新
            const xhr = new XMLHttpRequest();
            xhr.open('GET', 'UserMgmtDynamicWebUpdateAJAX.php', true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    if (response.updated) {
                        // 如果有資料更新，重新載入頁面或更新部分內容
                        location.reload(); // 或者局部更新資料
                    }
                }
            };
            xhr.send();
        }, 5000);
    </script>
</body>

</html>
