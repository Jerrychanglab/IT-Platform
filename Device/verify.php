<?php
// 取得 POST 資料
$user = $_POST['user'];
$esxi_ip = trim($_POST['esxi_ip']); // 使用 trim 移除前後空格
$esxi_password = $_POST['esxi_password'];
$device_serial = $_POST['device_serial']; // 從 POST 中獲取設備序號
$os_type = $_POST['os']; 

// 初始化變數
$ipmi_ip = '';
$retrieved_serial_number = '';

// 根據不同的 OS 選擇對應的命令
if ($os_type === 'ESXi') {
    // 構建 ESXi 命令
    $command1 = "sshpass -p \"$esxi_password\" ssh -o StrictHostKeyChecking=no ${user}@${esxi_ip} \"ipmitool lan print | grep -w 'IP Address' | grep -v 'Source' | awk '{print \\\$4}'\"";
    $command2 = "sshpass -p \"$esxi_password\" ssh -o StrictHostKeyChecking=no ${user}@${esxi_ip} \"esxcli hardware platform get | grep 'Serial Number:' | grep -v 'Enclosure' | awk '{print \\\$3}'\"";

    // 執行 command1 以取得管理 IP
    $ipmi_ip = exec($command1, $output1, $return_var1);

    // 檢查命令是否成功
    if ($return_var1 !== 0) {
        echo json_encode(['status' => 'error', 'message' => 'SSH 驗證失敗: 無法取得 IPMI IP']);
        exit;
    }

    // 執行 command2 以取得機器序號
    $retrieved_serial_number = exec($command2, $output2, $return_var2);

    // 檢查第二個命令是否成功
    if ($return_var2 !== 0) {
        echo json_encode(['status' => 'error', 'message' => 'SSH 驗證失敗: 無法取得機器序號']);
        exit;
    }

    // 比對序號
    if ($retrieved_serial_number !== $device_serial) {
        echo json_encode(['status' => 'error', 'message' => '序號比對失敗: 設備序號不符']);
        exit;
    }

} elseif ($os_type === 'NetApp') {
    // 構建 NetApp 命令
    $command1 = "sshpass -p \"$esxi_password\" ssh -o StrictHostKeyChecking=no ${user}@${esxi_ip} \"network interface show -role cluster-mgmt -fields address\"";

    // 執行 command1 並在 PHP 中過濾以取得管理 IP
    exec($command1, $ipmi_output, $return_var1);

    // 檢查命令是否成功
    if ($return_var1 !== 0) {
        echo json_encode(['status' => 'error', 'message' => 'SSH 驗證失敗: 無法取得管理 IP']);
        exit;
    }

    // 使用正則表達式來提取符合 IP 格式的值
    foreach ($ipmi_output as $line) {
        if (preg_match('/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b/', $line, $matches)) {
            $ipmi_ip = $matches[0]; // 提取第一個符合的 IP
            break;
        }
    }
}

// 返回成功結果
$response = ['status' => 'success', 'ipmi_ip' => $ipmi_ip];
if ($os_type === 'ESXi') {
    $response['machine_serial'] = $retrieved_serial_number;
}
echo json_encode($response);
?>
