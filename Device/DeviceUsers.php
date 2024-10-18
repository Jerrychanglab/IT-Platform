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
    </style>
</head>

<body>
    <div class="container">
        <h1>設備清單管理</h1>

        <!-- 動態生成類型按鈕 -->
        <div class="button-container" id="categoryButtons">
            <?php foreach ($categories as $category) : ?>
                <button class="all-devices-btn" onclick="filterByType('<?php echo $category; ?>')"><?php echo $category; ?></button>
            <?php endforeach; ?>
        </div>

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

        function filterByType(type) {
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
                    <th>操作</th>
                </tr>
            `;
            // 先根據燈號狀態進行排序
            servers
                .filter(server => server.category === type)
                .sort((a, b) => {
                    const priority = {
                        'hardware-error': 1, //黃燈
                        'unreachable': 2, //灰燈
                        '': 3, //無狀態
                        'normal': 4 // 綠燈
                    };
                    return priority[a.status] - priority[b.status];
                })
                .forEach(server => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                <td><div class="status-circle ${server.status}"></div></td>
                <td>${server.category}</td>
                <td>${server.model}</td>
                <td>${server.machineSerial}</td>
                <td>${server.os || '-'}</td>
                <td>${server.device_ip || '-'}</td>
                <td>${server.management_ip || '-'}</td>
                <td>
                    <button onclick="openEditModal('${server.os}', '${server.machineSerial}', '${server.device_ip}', '${server.management_ip}', '${server.model}')">驗證</button>
                    <button onclick="openManageModal('${server.machineSerial}')">事件</button>
                </td>
            `;
                    table.appendChild(row);
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

            if (!user || !esxi_ip || !esxi_password) {
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
            const data = `user=${encodeURIComponent(user)}&esxi_ip=${encodeURIComponent(esxi_ip)}&esxi_password=${encodeURIComponent(esxi_password)}&device_serial=${encodeURIComponent(machineSerial)}`;
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
                        //console.log('自動更新的資料:', servers); // 檢查資料更新是否觸發
                        console.log('自動更新的資料:', JSON.stringify(servers, null, 2)); // 格式化輸出物件資料
                        // 更新頁面顯示
                        filterByType('Server');
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
                    // 確認 MaintainInfo 內是否還有未完成的狀態
                    const hasIncomplete = maintainInfo
                        .filter(item => item.machineSerial === machineSerial)
                        .some(item => item.status !== '完成');

                    // 若所有事件皆完成，設為綠燈；否則保持黃燈
                    const finalStatus = hasIncomplete ? 'hardware-error' : 'normal';

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
