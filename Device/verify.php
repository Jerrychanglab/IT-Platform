<?php
// 取得 POST 資料
$user = $_POST['user'];
$esxi_ip = trim($_POST['esxi_ip']); // 使用 trim 移除前後空格
$esxi_password = $_POST['esxi_password'];
$device_serial = $_POST['device_serial']; // 從 POST 中獲取設備序號

// 構建 SSH 命令
$command1 = "sshpass -p \"$esxi_password\" ssh -o StrictHostKeyChecking=no ${user}@${esxi_ip} \"ipmitool lan print | grep -w 'IP Address' | grep -v 'Source' | awk '{print \\\$4}'\"";
$command2 = "sshpass -p \"$esxi_password\" ssh -o StrictHostKeyChecking=no ${user}@${esxi_ip} \"esxcli hardware platform get | grep 'Serial Number:' | grep -v 'Enclosure' | awk '{print \\\$3}'\"";

// 執行第一個命令取得管理IP
$ipmi_ip = exec($command1, $output1, $return_var1);

// 檢查第一個命令是否成功
if ($return_var1 !== 0) {
    // 若 SSH 連接失敗，返回錯誤並停止後續操作
    echo json_encode(['status' => 'error', 'message' => 'SSH 驗證失敗: 無法取得 IPMI IP']);
    exit; // 停止執行後續命令
}

// 如果第一個命令成功，執行第二個命令取得機器序號
$retrieved_serial_number = exec($command2, $output2, $return_var2);

// 檢查第二個命令是否成功
if ($return_var2 !== 0) {
    // 若第二個命令失敗，返回錯誤
    echo json_encode(['status' => 'error', 'message' => 'SSH 驗證失敗: 無法取得機器序號']);
    exit; // 停止執行
}

// 比對從命令獲得的機器序號與傳入的設備序號是否相符
if ($retrieved_serial_number !== $device_serial) {
    echo json_encode(['status' => 'error', 'message' => '序號比對失敗: 設備序號不符']);
    exit;
}

// 如果兩個命令都成功且序號比對通過，返回 IPMI IP 和機器序號
echo json_encode(['status' => 'success', 'ipmi_ip' => $ipmi_ip, 'machine_serial' => $retrieved_serial_number]);
?>
