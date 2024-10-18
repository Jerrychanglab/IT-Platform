<?php
// 引入 DeviceInfo.php，獲取設備數據
$devices = include('DeviceInfo.php');

// 從 URL 中獲取 target_ip (e.g., ?ip=10.31.34.13) 或 target_serial (e.g., ?serial=J302A9NL)
$target_ip = isset($_GET['ip']) ? $_GET['ip'] : null;
$target_serial = isset($_GET['serial']) ? $_GET['serial'] : null;

if (!$target_ip && !$target_serial) {
    // 返回錯誤信息為 JSON
    header('Content-Type: application/json');
    echo json_encode(["error" => "請提供一個 IP 地址或機器序號 (e.g., ?ip=10.31.34.13 或 ?serial=J302A9NL)"]);
    exit;
}

// 初始化變數來保存結果
$found_device = null;

// 遍歷陣列來查找符合的 device_ip 或 machineSerial
foreach ($devices as $device) {
    if (($target_ip && $device['device_ip'] === $target_ip) || ($target_serial && $device['machineSerial'] === $target_serial)) {
        $found_device = $device;
        break; // 找到匹配的設備後停止遍歷
    }
}

// 設置 HTTP 頭信息為 JSON 格式
header('Content-Type: application/json');

// 如果找到匹配的設備，返回設備信息作為 JSON
if ($found_device) {
    echo json_encode($found_device, JSON_PRETTY_PRINT); // 格式化輸出
} else {
    // 如果未找到設備，返回錯誤信息
    echo json_encode(["error" => "No device found with IP: $target_ip or Serial: $target_serial"]);
}
?>
