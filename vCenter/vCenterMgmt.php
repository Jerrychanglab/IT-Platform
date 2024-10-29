<?php
session_start();

$vcenterPhpFile = 'vCenterInfo.php';
$secret_key = "jarry_chang";
$listPath = "/var/www/html/vCenter/list";

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

// 讀取 vCenterInfo.php 的資料並解密密碼
$vcenterData = file_exists($vcenterPhpFile) ? include($vcenterPhpFile) : [];
foreach ($vcenterData as &$entry) {
    $entry['passwd'] = decryptPassword($entry['passwd'], $secret_key);
}
unset($entry);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];

    if ($action === 'verify') {
        $vcenterIp = $_POST['vcenterIp'];
        $username = $_POST['username'];
        $password = $_POST['password'];

        // 檢查 vCenter IP 是否已經存在
        foreach ($vcenterData as $entry) {
            if ($entry['vcenter'] === $vcenterIp) {
                echo json_encode(['status' => 'error', 'message' => 'vCenter IP 已經存在']);
                exit;
            }
        }

        $url = "https://{$vcenterIp}/rest/com/vmware/cis/session";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_USERPWD, "{$username}:{$password}");

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $encryptedPassword = encryptPassword($password, $secret_key);
            $newEntry = [
                'vcenter' => $vcenterIp,
                'user' => $username,
                'passwd' => $encryptedPassword
            ];

            $vcenterData[] = $newEntry;
            saveData($vcenterData);

            echo json_encode(['status' => 'success', 'message' => '驗證成功，已加入清單。']);
        } else {
            echo json_encode(['status' => 'error', 'message' => '驗證失敗，請檢查憑證或 vCenter IP 是否正確。']);
        }
    } elseif ($action === 'remove' && isset($_POST['index'])) {
        $index = intval($_POST['index']);
        if (isset($vcenterData[$index])) {
            $vcenterIp = $vcenterData[$index]['vcenter'];
            unset($vcenterData[$index]);
            $vcenterData = array_values($vcenterData);
            saveData($vcenterData);

            // 刪除 JSON、.tmp 和 .lock 文件
            $jsonFile = "$listPath/$vcenterIp.json";
            $tmpFile = "$listPath/$vcenterIp.tmp";
            $lockFile = "$listPath/$vcenterIp.lock";

            if (file_exists($jsonFile)) unlink($jsonFile);
            if (file_exists($tmpFile)) unlink($tmpFile);
            if (file_exists($lockFile)) unlink($lockFile);

            echo json_encode(['status' => 'success', 'message' => "vCenter IP $vcenterIp 的記錄及相關文件已移除。"]);
        } else {
            echo json_encode(['status' => 'error', 'message' => '記錄不存在。']);
        }
    }
    exit;
}

function saveData($data)
{
    global $vcenterPhpFile, $secret_key;
    foreach ($data as &$entry) {
        $decryptedPassword = decryptPassword($entry['passwd'], $secret_key);
        if ($decryptedPassword === false) {
            $entry['passwd'] = encryptPassword($entry['passwd'], $secret_key);
        }
    }
    unset($entry);
    file_put_contents($vcenterPhpFile, "<?php\n return " . var_export($data, true) . ";\n");
}
?>



<!DOCTYPE html>
<html lang="zh-Hant">

<head>
    <meta charset="UTF-8">
    <title>vCenter 管理頁面</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }

        .container {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            width: 600px;
            text-align: center;
        }

        h1 {
            color: #333;
            margin-bottom: 20px;
        }

        .btn {
            padding: 10px 20px;
            background-color: #007B8F;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .btn:hover {
            background-color: #005f6b;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: center;
        }

        th {
            background-color: #007B8F;
            color: white;
        }

        #statusMessage {
            margin-top: 10px;
            font-weight: bold;
        }

        /* 模態框 */
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 400px;
            border-radius: 10px;
            text-align: center;
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

        form {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            margin-bottom: 15px;
            width: 100%;
        }

        label {
            font-weight: bold;
            margin-bottom: 5px;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            border-radius: 5px;
            border: 1px solid #ccc;
            box-sizing: border-box;
        }

        .form-group-inline {
            display: flex;
            justify-content: space-between;
            width: 100%;
        }

        .form-group-inline .form-group {
            width: 48%;
        }

        .modal-content button {
            width: 100%;
            padding: 10px;
            background-color: #007B8F;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 5px;
            margin-top: 10px;
        }

        .modal-content button:hover {
            background-color: #005f6b;
        }

        .cancel-btn {
            background-color: #ccc;
            color: black;
            margin-top: 5px;
        }

        .cancel-btn:hover {
            background-color: #999;
        }
    </style>

</head>

<body>
    <div class="container">
        <h1>vCenter 管理</h1>

        <button class="btn" onclick="showVcenterForm()">新增 vCenter</button>

        <table id="vcenterTable">
            <thead>
                <tr>
                    <th>vCenter IP</th>
                    <th>使用者名稱</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody id="vcenterList">
                <?php foreach ($vcenterData as $index => $vcenter) : ?>
                    <tr data-index="<?php echo $index; ?>">
                        <td><?php echo htmlspecialchars($vcenter['vcenter']); ?></td>
                        <td><?php echo htmlspecialchars($vcenter['user']); ?></td>
                        <td><button class="btn" onclick="removeVcenter(<?php echo $index; ?>)">移除</button></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- 新增 vCenter 模態框 -->
    <div id="vcenterModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>新增 vCenter</h2>
            <form onsubmit="verifyVcenter(event)">
                <div class="form-group">
                    <label for="vcenterIp">vCenter IP 地址:</label>
                    <input type="text" id="vcenterIp" name="vcenterIp" required placeholder="輸入 vCenter IP">
                </div>
                <div class="form-group-inline">
                    <div class="form-group">
                        <label for="username">使用者名稱:</label>
                        <input type="text" id="username" name="username" required placeholder="輸入使用者名稱">
                    </div>
                    <div class="form-group">
                        <label for="password">密碼:</label>
                        <input type="password" id="password" name="password" required placeholder="輸入密碼">
                    </div>
                </div>
                <button type="submit">認證並加入清單</button>
                <button type="button" class="cancel-btn" onclick="closeModal()">取消</button>
            </form>
            <p id="statusMessage"></p>
        </div>
    </div>

    <script>
        function showVcenterForm() {
            document.getElementById('vcenterModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('vcenterModal').style.display = 'none';
        }

        function verifyVcenter(event) {
            event.preventDefault();

            const vcenterIp = document.getElementById('vcenterIp').value;
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;

            const xhr = new XMLHttpRequest();
            xhr.open('POST', '', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    const statusMessage = document.getElementById('statusMessage');

                    if (response.status === 'success') {
                        statusMessage.innerText = '驗證成功，已加入清單。';
                        statusMessage.style.color = 'green';
                        location.reload();
                    } else {
                        statusMessage.innerText = '驗證失敗：' + response.message;
                        statusMessage.style.color = 'red';
                    }
                }
            };

            const data = `action=verify&vcenterIp=${encodeURIComponent(vcenterIp)}&username=${encodeURIComponent(username)}&password=${encodeURIComponent(password)}`;
            xhr.send(data);
        }

        function removeVcenter(index) {
            if (confirm('確定要移除此 vCenter 記錄嗎？')) {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', '', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        location.reload();
                    }
                };
                xhr.send(`action=remove&index=${index}`);
            }
        }
    </script>
</body>

</html>
