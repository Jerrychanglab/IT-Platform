<?php
session_start();

// 防止页面被缓存
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1
header("Pragma: no-cache"); // HTTP 1.0
header("Expires: 0"); // Proxies

// 3600 秒登出
$timeout_duration = 3600;

// 檢查是否有上次活動時間記錄
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
    // 如果距離上次活動超過30分鐘，則清空會話並重定向到登入頁面
    session_unset();
    session_destroy();
    header('Location: /Login/Login.php');
    exit;
}

// 更新上次活動時間
$_SESSION['LAST_ACTIVITY'] = time();

// 檢查用戶是否已經登入
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: /Login/Login.php');
    exit;
}

// 引入 DeviceInfo.php，取得設備資料
$servers = include('DeviceInfo.php');

// 引入 MaintainInfo.php
$maintainInfo = json_decode(file_get_contents('MaintainInfo.php'), true);

// 提取所有唯一的類型
$categories = array_unique(array_column($servers, 'category'));

// 定義加密和解密密鑰
$secret_key = "jarry_chang";

// 加密和解密函數
function encryptPassword($password, $secretKey)
{
    $iv = openssl_random_pseudo_bytes(16);
    $encryptedText = openssl_encrypt($password, 'aes-256-cbc', $secretKey, 0, $iv);
    return base64_encode($iv . $encryptedText);
}

function decryptPassword($encryptedPassword, $secretKey)
{
    $data = base64_decode($encryptedPassword);
    $iv = substr($data, 0, 16);
    $encryptedText = substr($data, 16);
    return openssl_decrypt($encryptedText, 'aes-256-cbc', $secretKey, 0, $iv);
}

// 處理 POST 請求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'restore') {
            // 還原設備資料的邏輯
            $serial = $_POST['serial'];
            foreach ($servers as &$server) {
                if ($server['machineSerial'] === $serial) {
                    $server['os'] = '';
                    $server['device_ip'] = '';
                    $server['management_ip'] = '';
                    $server['user'] = '';
                    $server['password'] = '';
                    if ($server['status'] === 'normal' || $server['status'] === 'unreachable') {
                        $server['status'] = '';
                    }
                    break;
                }
            }
            file_put_contents('DeviceInfo.php', "<?php\n return " . var_export($servers, true) . ";\n");
            echo json_encode(['status' => 'success', 'servers' => $servers]);
            exit;
        } elseif ($_POST['action'] === 'updateStatus') {
            // 更新狀態的邏輯
            $serial = $_POST['machineSerial'];
            $newStatus = $_POST['status'];
            foreach ($servers as &$server) {
                if ($server['machineSerial'] === $serial) {
                    $server['status'] = $newStatus;
                    break;
                }
            }
            file_put_contents('DeviceInfo.php', "<?php\n return " . var_export($servers, true) . ";\n");
            echo json_encode(['status' => 'success', 'message' => 'Status updated']);
            exit;
        } elseif ($_POST['action'] === 'updateMaintainStatus') {
            // 更新 MaintainInfo 狀態邏輯
            $serial = $_POST['machineSerial'];
            $eventId = $_POST['event_id'];
            $newStatus = $_POST['status'];
            $updatedBy = $_POST['updated_by'] ?? '';

            $found = false;

            if ($newStatus === '完成') {
                $maintainInfo = array_filter($maintainInfo, function ($info) use ($serial, $eventId) {
                    return !($info['machineSerial'] === $serial && $info['event_id'] === $eventId);
                });

                foreach ($servers as &$server) {
                    if ($server['machineSerial'] === $serial) {
                        $server['status'] = 'normal';
                        $found = true;
                        break;
                    }
                }
                file_put_contents('DeviceInfo.php', "<?php\n return " . var_export($servers, true) . ";\n");
            } else {
                foreach ($maintainInfo as &$info) {
                    if ($info['machineSerial'] === $serial && $info['event_id'] === $eventId) {
                        $info['status'] = $newStatus;
                        $info['updated_by'] = $updatedBy;
                        $found = true;
                        break;
                    }
                }
            }

            file_put_contents('MaintainInfo.php', json_encode(array_values($maintainInfo), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            if ($found) {
                echo json_encode(['status' => 'success', 'message' => '維護狀態已更新']);
            } else {
                echo json_encode(['status' => 'error', 'message' => '找不到對應的設備資料']);
            }
            exit;
        } elseif ($_POST['action'] === 'saveTag') {
            // 新增或修改標籤邏輯
            $serial = $_POST['serial'];
            $key = $_POST['key'];
            $value = $_POST['value'];

            // 讀取目前的設備資訊
            $servers = include('DeviceInfo.php');

            // 找到對應的設備，新增或修改標籤
            foreach ($servers as &$server) {
                if ($server['machineSerial'] === $serial) {
                    if (!isset($server['tags'])) {
                        $server['tags'] = [];
                    }
                    $server['tags'][$key] = $value; // 新增或修改標籤
                    break;
                }
            }

            // 將更新後的設備信息寫回 DeviceInfo.php
            file_put_contents('DeviceInfo.php', "<?php\n return " . var_export($servers, true) . ";\n");

            echo json_encode(['status' => 'success']);
            exit;
        } elseif ($_POST['action'] === 'removeTag') {
            // 移除標籤邏輯
            $serial = $_POST['serial'];
            $key = $_POST['key'];

            // 讀取目前的設備資訊
            $servers = include('DeviceInfo.php');

            // 找到對應的設備，移除指定標籤
            foreach ($servers as &$server) {
                if ($server['machineSerial'] === $serial) {
                    if (isset($server['tags'][$key])) {
                        unset($server['tags'][$key]); // 刪除標籤
                    }
                    break;
                }
            }

            // 將更新後的設備信息寫回 DeviceInfo.php
            file_put_contents('DeviceInfo.php', "<?php\n return " . var_export($servers, true) . ";\n");

            echo json_encode(['status' => 'success']);
            exit;
        }
    }

    // 處理設備更新的邏輯
    $os = $_POST['os'];
    $serial = $_POST['serial'];
    $device_ip = $_POST['device_ip'];
    $management_ip = $_POST['management_ip'];
    $user = $_POST['user'];
    $password = $_POST['password'];

    // 使用加密函數加密密碼
    $encrypted_password = encryptPassword($password, $secret_key);

    // 更新對應設備的數據
    foreach ($servers as &$server) {
        if ($server['machineSerial'] === $serial) {
            $server['os'] = $os;
            $server['device_ip'] = $device_ip;
            $server['management_ip'] = $management_ip;
            $server['user'] = $user;
            $server['password'] = $encrypted_password;
            break;
        }
    }

    // 將更新後的設備信息寫回 DeviceInfo.php
    file_put_contents('DeviceInfo.php', "<?php\n return " . var_export($servers, true) . ";\n");

    echo json_encode(['status' => 'success', 'servers' => $servers]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="zh-Hant">

<head>
    <meta charset="UTF-8">
    <title>設備管理清單</title>
    <style>
        /* 現有的 CSS 內容保持不變 */
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .container {
            width: auto;
            min-width: 850px;
            background-color: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            border-radius: 8px;
            margin: 0 auto;
        }

        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 20px;
        }

        .button-container {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .all-devices-btn {
            background-color: #6c757d;
            color: white;
            /* 設置白色文字 */
            font-weight: bold;
            font-size: 16px;
        }

        .all-devices-btn:hover {
            background-color: #4F4F4F;
            /* 當滑鼠懸停時設置深紅色背景 */
            font-weight: bold;
        }

        button {
            padding: 10px 15px;
            background-color: #007B8F;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 5px;
        }

        button:hover {
            background-color: #005f6b;
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

        /* 模態框樣式 */
        .modal {
            display: none;
            position: fixed;
            align-items: center;
            justify-content: center;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.4);
            overflow-y: auto;
        }

        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: auto;
            max-width: 35%;
            min-width: 200px;
            border-radius: 8px;
            text-align: center;
            max-height: 90vh;
            overflow-y: auto;
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

        /* 表單與輸入框樣式 */
        label {
            display: block;
            margin-top: 10px;
            text-align: left;
        }

        input[type="text"],
        input[type="password"],
        select {
            width: 90%;
            padding: 10px;
            margin-top: 5px;
            box-sizing: border-box;
            border-radius: 5px;
            border: 1px solid #ccc;
        }

        .section {
            padding: 10px 10px;
        }

        .item {
            width: 32%;
            display: inline-block;
            vertical-align: top;
        }

        .row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .row:hover {
            background-color: #f0f8ff;
            box-shadow: 0 0 8px rgba(0, 0, 0, 0.2);
            border-color: #007B8F;
            margin-bottom: 15px;
            border-radius: 4px;
        }

        .section:last-child {
            border-bottom: none;
        }

        input[readonly] {
            border: 1px solid #ccc;
            background-color: #f9f9f9;
            color: #666;
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

        .status-circle {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: inline-block;
            box-shadow: 0 0 6px rgba(0, 0, 0, 0.15);
            /* 添加柔和的陰影效果 */
            border: 2px solid #FFFFFF;
            /* 白色邊框，與背景區分開來 */
        }

        .normal {
            background-color: #006000;
        }

        .hardware-error {
            background-color: #EAC100;
        }

        .unreachable {
            background-color: #CE0000;
        }

        /* 半綠半黃 */
        .green-yellow {
            background: linear-gradient(to right, #006000 50%, #EAC100 50%);
        }

        /* 半灰半黃 */
        .gray-yellow {
            background: linear-gradient(to right, #5B5B5B 50%, #EAC100 50%);
        }

        .manage-modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: auto;
            max-width: 90%;
            /* 調整寬度，使內容更好展示 */
            min-width: 200px;
            border-radius: 8px;
            text-align: center;
            max-height: 80vh;
            /* 限制高度，避免內容溢出視窗 */
            overflow-y: auto;
            /* 添加垂直滾動條，防止內容被截斷 */
        }

        /* 滾動條樣式（可選） */
        .manage-modal-content::-webkit-scrollbar {
            width: 10px;
        }

        .manage-modal-content::-webkit-scrollbar-thumb {
            background-color: #007B8F;
            border-radius: 10px;
        }

        .alert-content {
            max-width: 300px;
            /* Adjust the max-width as needed */
            overflow-x: auto;
            white-space: nowrap;
            text-align: left;
            padding-right: 10px;
        }

        .alert-content::-webkit-scrollbar {
            height: 6px;
            /* Adjust the height of the scrollbar */
        }

        .alert-content::-webkit-scrollbar-thumb {
            background-color: #007B8F;
            /* Set the color of the scrollbar */
            border-radius: 3px;
            /* Round the corners of the scrollbar */
        }

        .alert-content::-webkit-scrollbar-track {
            background-color: #f1f1f1;
            /* Optional: set background color for the scrollbar track */
        }

        .modal-buttons .restore {
            font-weight: bold;
            background-color: #FF5151;
            /* Orange color for Restore button */
            color: white;
        }

        .modal-buttons .restore:hover {
            background-color: #FF0000;
            /* Darker orange on hover */
        }

        /* 儲存標籤與取消按鈕排列在同一行 */
        /* 儲存標籤與取消按鈕排列在同一行 */
        .modal-buttons {
            display: flex;
            justify-content: center;
            gap: 10px;
            /* 控制按鈕之間的間距 */
            margin-top: 20px;
        }

        /* Key 和 Value 放在同一行 */
        #tagForm {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }

        #tagForm .input-row {
            display: flex;
            justify-content: center;
            gap: 10px;
            align-items: center;
            width: 100%;
        }

        /* Key 和 Value 的輸入框調整 */
        #tagForm input[type="text"] {
            width: 150px;
            padding: 8px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }

        #tagForm label {
            font-weight: bold;
        }

        /* 調整現有標籤的顯示 */

        #existingTags {
            display: flex;
            flex-direction: column;
            align-items: center;
            /* 將內容居中 */
            justify-content: center;
        }

        #existingTags div {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 5px;
            background-color: #F9F9F9;
            border-radius: 4px;
            text-align: left;
            font-weight: bold;
            gap: 10px;
            /* 增加間距 */
        }


        /* 修改與刪除按鈕的間距 */
        #existingTags button {
            margin-left: 10px;
            background-color: #3A632E;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 4px;
            padding: 5px 10px;
        }

        #existingTags button:hover {
            background-color: #2a4b1f;
        }

        #globalSearch {
            width: 90%;
            padding: 10px;
            margin: 20px auto;
            display: block;
            border-radius: 5px;
            border: 1px solid #ccc;
            font-size: 16px;
        }

        /* 標籤欄位文字的樣式 */
        .tag-column {
            font-size: 12px;
            /* 設定字體大小，依需求調整 */
            color: #007B8F;
            /* 可選擇調整字體顏色 */
            text-align: left;
            /* 如果希望字體左對齊 */
            white-space: nowrap;
            /* 避免自動換行，若需要 */
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>設備清單管理</h1>

        <!-- 動態生成類型按鈕 -->
        <div class="button-container" id="categoryButtons">
            <button class="all-devices-btn" onclick="filterByType('ALL')">ALL</button>
            <?php foreach ($categories as $category) : ?>
                <button class="all-devices-btn" onclick="filterByType('<?php echo $category; ?>')"><?php echo $category; ?></button>
            <?php endforeach; ?>
        </div>

        <!-- 新增搜尋框 -->
        <div>
            <input type="text" id="globalSearch" placeholder="全域搜尋..." onkeyup="globalFilterTags()">
        </div>
        <br>
        <!-- 設備列表 -->
        <table id="userTable">
            <tr>
                <th>狀態</th>
                <th>設備類型</th>
                <th>設備型號</th>
                <th>設備序號</th>
                <th>OS</th>
                <th>設備IP</th>
                <th>管理IP</th>
                <th>標籤</th>
                <th>操作</th>
            </tr>
        </table>
    </div>

    <!-- 編輯設備模態框 -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h2>編輯設備</h2>
            <form id="editForm">
                <input type="hidden" id="originalSerial" name="originalSerial">

                <!-- 設備型號 + 設備序號 + 管理IP -->
                <div class="row section">
                    <div class="item">
                        <label for="editModel">設備型號:</label>
                        <input type="text" id="editModel" name="model" readonly>
                    </div>
                    <div class="item">
                        <label for="editSerial">設備序號:</label>
                        <input type="text" id="editSerial" name="serial" readonly>
                    </div>
                    <div class="item">
                        <label for="editManagementIp">管理IP:</label>
                        <input type="text" id="editManagementIp" name="management_ip" readonly placeholder="驗證後自動回填">
                    </div>
                </div>

                <!-- OS + 設備IP + 驗證密碼 -->
                <div class="row section">
                    <div class="item">
                        <label for="editOS">OS:</label>
                        <select id="editOS" name="os" required>
                            <option value="ESXi">ESXi</option>
                            <option value="Linux">Linux</option>
                            <option value="NetApp">NetApp</option>
                        </select>
                    </div>
                    <div class="item">
                        <label for="editDeviceIp">設備IP:</label>
                        <input type="text" id="editDeviceIp" name="device_ip" required placeholder="請填寫IP">
                    </div>
                    <div class="item">
                        <label for="editUser">帳號:</label>
                        <input type="text" id="editUser" name="user" required placeholder="請輸入帳號">
                    </div>
                    <div class="item">
                        <label for="editPassword">密碼:</label>
                        <input type="password" id="editPassword" name="password" required placeholder="請輸入密碼">
                    </div>
                </div>

                <!-- 驗證並更新 + 取消 -->
                <div class="modal-buttons">
                    <button type="button" class="confirm" onclick="verifyAndSave()">驗證並更新</button>
                    <button type="button" class="restore" onclick="restoreServer()">還原</button>
                    <button type="button" class="cancel" onclick="closeEditModal()">取消</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 標籤模態框 -->
    <div id="tagModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeTagModal()">&times;</span>
            <h2>管理標籤</h2>
            <form id="tagForm">
                <input type="hidden" id="tagSerial" name="serial">
                <div class="input-row">
                    <label for="tagKey">Key:</label>
                    <input type="text" id="tagKey" name="key" required>
                    <label for="tagValue">Value:</label>
                    <input type="text" id="tagValue" name="value" required>
                </div>
                <div class="modal-buttons">
                    <button type="button" class="confirm" onclick="saveTag()">保存標籤</button>
                    <button type="button" class="cancel" onclick="closeTagModal()">取消</button>
                </div>
            </form>

            <!-- 顯示當前標籤 -->
            <br>
            <div id="existingTags">
                <!-- 現有標籤將會在這裡動態生成 -->
            </div>
        </div>
    </div>

    <!-- 顯示管理資訊模態框 -->
    <div id="manageModal" class="modal">
        <div class="manage-modal-content">
            <span class="close" onclick="closeManageModal()">&times;</span>
            <h2>設備異常信息</h2>
            <div id="manageContent"></div>
        </div>
    </div>

    <script>
        let servers = <?php echo json_encode($servers); ?>;
        let maintainInfo = <?php echo json_encode($maintainInfo); ?>;
        let currentCategory = localStorage.getItem('currentCategory') || 'ALL';

        document.addEventListener('DOMContentLoaded', function() {
            filterByType('ALL'); // 預設顯示所有設備資訊
        });

        function filterByType(type) {
            currentCategory = type; // 記錄當前選擇的類型
            localStorage.setItem('currentCategory', type);
            const table = document.getElementById('userTable');
            table.innerHTML = `
                <tr>
                    <th>狀態</th>
                    <th>設備類型</th>
                    <th>設備型號</th>
                    <th>設備序號</th>
                    <th>OS</th>
                    <th>設備IP</th>
		    <th>管理IP</th>
		    <th>標籤</th>
		    <th>操作</th>
                </tr>
`;


            // 清空搜尋框，避免干擾類別篩選
            document.getElementById('globalSearch').value = '';
            // 先根據燈號狀態進行排序
            servers
                .filter(server => type === 'ALL' || server.category === type)
                .sort((a, b) => {
                    const priority = {
                        'hardware-error': 1, //黃燈
                        'unreachable': 2, //灰燈
                        '': 3, //無狀態
                        'normal': 4 // 綠燈
                    };
                    return priority[a.status] - priority[b.status]; // 比較 a 和 b 的狀態優先級
                })
                .forEach(server => {
                    const tags = server.tags ?
                        Object.entries(server.tags).map(([key, value]) => `${key}: ${value}`).join('<br>') :
                        '-';

                    const row = document.createElement('tr');
                    row.innerHTML = `
            <td><div class="status-circle ${server.status}"></div></td>
            <td>${server.category}</td>
            <td>${server.model}</td>
            <td>${server.machineSerial}</td>
            <td>${server.os || '-'}</td>
            <td>${server.device_ip || '-'}</td>
            <td>${server.management_ip || '-'}</td>
            <td class="tag-column">${tags}</td>
            <td>
                <button onclick="openEditModal('${server.os}', '${server.machineSerial}', '${server.device_ip}', '${server.management_ip}', '${server.model}')">驗證</button>
                <button onclick="openTagModal('${server.machineSerial}')">標籤</button>
                <button onclick="openManageModal('${server.machineSerial}')">事件</button>
            </td>
        `;
                    table.appendChild(row);
                });


        }

        // 標籤
        function openTagModal(machineSerial) {
            document.getElementById('tagSerial').value = machineSerial;
            document.getElementById('tagKey').value = '';
            document.getElementById('tagValue').value = '';
            document.getElementById('tagModal').style.display = 'block';

            const existingTagsDiv = document.getElementById('existingTags');
            existingTagsDiv.innerHTML = ''; // 清空之前的標籤

            // 獲取當前設備的標籤
            const server = servers.find(server => server.machineSerial === machineSerial);
            if (server && server.tags) {
                Object.keys(server.tags).forEach(key => {
                    const tagDiv = document.createElement('div');
                    tagDiv.innerHTML = `
                        <div>
                            <strong>${key}:</strong> ${server.tags[key]}
                            <button onclick="editTag('${key}', '${server.tags[key]}')">修改</button>
                            <button onclick="removeTag('${machineSerial}', '${key}')">刪除</button>
                        </div>
                    `;
                    existingTagsDiv.appendChild(tagDiv);
                });
            }
        }

        function editTag(key, value) {
            // 將選中的標籤內容填充到輸入框中
            document.getElementById('tagKey').value = key;
            document.getElementById('tagValue').value = value;
        }

        function saveTag() {
            const serial = document.getElementById('tagSerial').value;
            const key = document.getElementById('tagKey').value;
            const value = document.getElementById('tagValue').value;

            if (!key || !value) {
                alert('請輸入完整的 key 和 value');
                return;
            }

            const xhr = new XMLHttpRequest();
            xhr.open('POST', '', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    if (response.status === 'success') {
                        alert('標籤保存成功');
                        closeTagModal();
                        reloadServersData(); // 重新載入設備數據
                    } else {
                        alert('標籤保存失敗');
                    }
                }
            };
            const data = `action=saveTag&serial=${encodeURIComponent(serial)}&key=${encodeURIComponent(key)}&value=${encodeURIComponent(value)}`;
            xhr.send(data);
        }

        function removeTag(serial, key) {
            if (!confirm('確定要刪除此標籤嗎？')) {
                return;
            }

            const xhr = new XMLHttpRequest();
            xhr.open('POST', '', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    if (response.status === 'success') {
                        alert('標籤已刪除');
                        closeTagModal();
                        reloadServersData(); // 重新載入設備數據
                    } else {
                        alert('標籤刪除失敗');
                    }
                }
            };

            const data = `action=removeTag&serial=${encodeURIComponent(serial)}&key=${encodeURIComponent(key)}`;
            xhr.send(data);
        }

        // 全域搜尋和篩選功能
        function globalFilterTags() {
            const searchValue = document.getElementById('globalSearch').value.toLowerCase(); // 取得搜尋框的值
            const rows = document.querySelectorAll('#userTable tr'); // 取得設備表格的所有行

            rows.forEach((row, index) => {
                if (index === 0) return; // 跳過表頭

                const cells = row.querySelectorAll('td');
                let rowText = '';

                cells.forEach(cell => {
                    rowText += cell.textContent.toLowerCase(); // 將該行所有欄位的文字加到一起進行檢查
                });

                if (rowText.includes(searchValue)) {
                    row.style.display = ''; // 如果符合搜尋條件，顯示該行
                } else {
                    row.style.display = 'none'; // 不符合搜尋條件，隱藏該行
                }
            });
        }


        function openEditModal(os, machineSerial, deviceIp, managementIp, model) {
            document.getElementById('editOS').value = os;
            document.getElementById('editSerial').value = machineSerial;
            document.getElementById('editModel').value = model;

            if (deviceIp && deviceIp !== 'undefined') {
                document.getElementById('editDeviceIp').value = deviceIp;
                document.getElementById('editDeviceIp').placeholder = '';
            } else {
                document.getElementById('editDeviceIp').value = '';
                document.getElementById('editDeviceIp').placeholder = '請填寫IP';
            }

            if (managementIp && managementIp !== 'undefined') {
                document.getElementById('editManagementIp').value = managementIp;
                document.getElementById('editManagementIp').placeholder = '';
            } else {
                document.getElementById('editManagementIp').value = '';
                document.getElementById('editManagementIp').placeholder = '驗證後自動回填';
            }
            document.getElementById('editPassword').value = '';
            document.getElementById('originalSerial').value = machineSerial;
            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function restoreServer() {
            const serial = document.getElementById('originalSerial').value; // Get the serial from the edit modal

            if (confirm('確定要還原此設備嗎？這將會清除所有資料，只保留設備類型、設備型號和設備序號。')) {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', '', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        const response = JSON.parse(xhr.responseText);
                        if (response.status === 'success') {
                            servers = response.servers;
                            alert('設備已還原成功');
                            closeEditModal(); // Close the modal after restoring
                            filterByType('Server'); // Reload the table
                        } else {
                            alert('還原失敗');
                        }
                    }
                };
                const data = `action=restore&serial=${encodeURIComponent(serial)}`;
                xhr.send(data);
            }
        }

        function verifyAndSave() {
            const user = document.getElementById('editUser').value; // 取得帳號
            const esxi_ip = document.getElementById('editDeviceIp').value;
            const esxi_password = document.getElementById('editPassword').value;
            const machineSerial = document.getElementById('editSerial').value;
            const os = document.getElementById('editOS').value;

            if (!user || !esxi_ip || !esxi_password || !os) {
                alert('請輸入設備IP、帳號和驗證密碼');
                return;
            }

            const server = servers.find(server => server.machineSerial === machineSerial);

            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'verify.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    if (response.status === 'success') {
                        document.getElementById('editManagementIp').value = response.ipmi_ip;

                        // 更新資料即使燈號是黃燈
                        if (server && server.status === 'hardware-error') {
                            alert('驗證完成，但黃燈狀態保留不變');
                            saveData(user, esxi_password); // 保存資料，不變更狀態
                        } else {
                            updateDeviceStatus(machineSerial, 'normal'); // 改為綠燈
                            saveData(user, esxi_password); // 保存資料
                        }

                        closeEditModal();
                    } else {
                        alert(response.message);
                    }
                }
            };
            const data = `user=${encodeURIComponent(user)}&esxi_ip=${encodeURIComponent(esxi_ip)}&esxi_password=${encodeURIComponent(esxi_password)}&device_serial=${encodeURIComponent(machineSerial)}&os=${encodeURIComponent(os)}`;
            xhr.send(data);
        }

        // 管理按鈕
        function openManageModal(machineSerial) {
            const modal = document.getElementById('manageModal');
            const manageContent = document.getElementById('manageContent');

            // 發送 AJAX 請求以獲取最新的 MaintainInfo.php 資料
            const xhr = new XMLHttpRequest();
            xhr.open('GET', 'MaintainInfo.php', true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    // 解析最新的維護信息
                    maintainInfo = JSON.parse(xhr.responseText);

                    // 清空內容，避免殘留舊資料
                    manageContent.innerHTML = '';

                    // 過濾出對應的維護資訊
                    const maintenance = maintainInfo.filter(item => item.machineSerial === machineSerial);

                    if (maintenance.length > 0) {
                        let content = '<table><tr><th>時間</th><th>型號</th><th>序號</th><th>設備IP</th><th>管理IP</th><th>事件ID</th><th>告警內容</th><th>狀態</th><th>更新者</th></tr>';
                        maintenance.forEach(item => {
                            const eventIdLink = item.event_link ?
                                `<a href="${item.event_link}" target="_blank">${item.event_id}</a>` :
                                item.event_id;
                            content += `<tr>
                                <td>${item.timestamp}</td>
                                <td>${item.model}</td>
                                <td>${item.machineSerial}</td>
                                <td>${item.device_ip}</td>
                                <td>${item.management_ip}</td>
                                <td>${eventIdLink}</td>
                                <td><div class="alert-content">${item.alert_text}</div></td>
                                <td>
                                    <select name="status" onchange="updateStatus('${item.machineSerial}', '${item.event_id}', this.value)">
                                        <option value="未處理" ${item.status === '未處理' ? 'selected' : ''}>未處理</option>
                                        <option value="處理中" ${item.status === '處理中' ? 'selected' : ''}>處理中</option>
                                        <option value="完成" ${item.status === '完成' ? 'selected' : ''}>完成</option>
                                    </select>
                                </td>
                                <td>${item.updated_by || 'N/A'}</td>
                            </tr>`;
                        });
                        content += '</table>';
                        manageContent.innerHTML = content;
                    } else {
                        manageContent.innerHTML = '無異常信息。';
                    }

                    modal.style.display = 'block';
                }
            };
            xhr.send();
        }

        function reloadServersData() {
            const xhr = new XMLHttpRequest();
            xhr.open('GET', 'fetchDeviceInfo.php', true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    try {
                        // 直接解析 JSON 格式
                        servers = JSON.parse(xhr.responseText);
                        //console.log('自動更新的資料:', JSON.stringify(servers, null, 2)); // 格式化輸出物件資料
                        // 更新頁面顯示
                        filterByType(currentCategory);
                    } catch (e) {
                        console.error('解析 JSON 資料時發生錯誤:', e);
                    }
                }
            };
            xhr.send();
        }

        setInterval(reloadServersData, 10000);

        function updateStatus(machineSerial, eventId, newStatus) {
            const updatedBy = "<?php echo $_SESSION['username']; ?>";

            if (newStatus === '完成' && !confirm('確定要將狀態設置為「完成」並移除維護記錄？')) {
                return;
            }

            const xhr = new XMLHttpRequest();
            xhr.open('POST', '', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    if (response.status === 'success') {
                        if (newStatus === '完成') {
                            updateDeviceStatus(machineSerial, 'normal'); // 更新為綠燈
                            maintainInfo = maintainInfo.filter(item => item.machineSerial !== machineSerial || item.event_id !== eventId);
                            closeManageModal();
                            reloadServersData(); // 更新 DeviceInfo.php 的資料
                        } else {
                            maintainInfo.forEach(item => {
                                if (item.machineSerial === machineSerial && item.event_id === eventId) {
                                    item.status = newStatus;
                                    item.updated_by = updatedBy;
                                }
                            });
                            openManageModal(machineSerial);
                        }
                    } else {
                        console.log(response.message);
                    }
                }
            };
            const data = `action=updateMaintainStatus&machineSerial=${encodeURIComponent(machineSerial)}&event_id=${encodeURIComponent(eventId)}&status=${encodeURIComponent(newStatus)}&updated_by=${encodeURIComponent(updatedBy)}`;
            xhr.send(data);
        }

        function updateDeviceStatus(machineSerial, eventId, newStatus) {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    // 確認 MaintainInfo 內是否還有該設備的維護記錄
                    const maintenanceRecords = maintainInfo.filter(item => item.machineSerial === machineSerial);
                    const hasIncomplete = maintenanceRecords.some(item => item.status !== '完成');

                    // 若存在維護記錄且有未完成的事件，設為黃燈，否則為綠燈
                    const finalStatus = maintenanceRecords.length > 0 && hasIncomplete ? 'hardware-error' : 'normal';

                    // 更新設備狀態為黃燈或綠燈
                    const updateStatusXhr = new XMLHttpRequest();
                    updateStatusXhr.open('POST', '', true);
                    updateStatusXhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    updateStatusXhr.onreadystatechange = function() {
                        if (updateStatusXhr.readyState === 4 && updateStatusXhr.status === 200) {
                            reloadServersData(); // 重新加載 DeviceInfo.php
                        }
                    };
                    const updateData = `action=updateStatus&machineSerial=${encodeURIComponent(machineSerial)}&status=${encodeURIComponent(finalStatus)}`;
                    updateStatusXhr.send(updateData);
                }
            };

            const data = `action=updateMaintainStatus&machineSerial=${encodeURIComponent(machineSerial)}&eventId=${encodeURIComponent(eventId)}&status=${encodeURIComponent(newStatus)}`;
            xhr.send(data);
        }


        function closeManageModal() {
            document.getElementById('manageModal').style.display = 'none';
        }


        function closeTagModal() {
            document.getElementById('tagModal').style.display = 'none';
        }

        function saveData(user, password) {
            const os = document.getElementById('editOS').value;
            const model = document.getElementById('editModel').value;
            const machineSerial = document.getElementById('editSerial').value;
            const deviceIp = document.getElementById('editDeviceIp').value;
            const managementIp = document.getElementById('editManagementIp').value;

            if (!deviceIp || !managementIp) {
                alert('設備IP和管理IP不能為空');
                return;
            }

            const xhr = new XMLHttpRequest();
            xhr.open('POST', '', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    servers = JSON.parse(xhr.responseText).servers;
                    alert('設備信息更新成功');
                    closeEditModal();
                    filterByType('Server');
                }
            };

            const data = `os=${encodeURIComponent(os)}&model=${encodeURIComponent(model)}&serial=${encodeURIComponent(machineSerial)}&device_ip=${encodeURIComponent(deviceIp)}&management_ip=${encodeURIComponent(managementIp)}&user=${encodeURIComponent(user)}&password=${encodeURIComponent(password)}`;
            xhr.send(data);
        }
    </script>
</body>

</html>
