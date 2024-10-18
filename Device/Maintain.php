<?php
// 檢查請求是否為 POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 取得 POST 請求中的原始輸入資料
    $postData = file_get_contents('php://input');
    $json_data = json_decode($postData, true);

    if (json_last_error() === JSON_ERROR_NONE) {
        $file_path = 'MaintainInfo.php';
        $device_file_path = 'DeviceInfo.php';

        // 讀取現有的 MaintainInfo
        $existing_data = file_exists($file_path) ? json_decode(file_get_contents($file_path), true) : [];
        $existing_data = $existing_data ?: []; // 檢查空數據

        // 檢查是否已經存在相同的 machineSerial 和 event_id
        foreach ($existing_data as $record) {
            if ($record['machineSerial'] === $json_data['machineSerial'] && $record['event_id'] === $json_data['event_id']) {
                echo json_encode(['status' => 'error', 'message' => 'Duplicate entry found.']);
                exit;
            }
        }

        // 添加新記錄到 MaintainInfo.php
        $existing_data[] = $json_data;
        file_put_contents($file_path, json_encode($existing_data, JSON_PRETTY_PRINT));

        // 更新 DeviceInfo.php 中的設備狀態
        if (file_exists($device_file_path)) {
            $device_data = include($device_file_path);
            foreach ($device_data as &$device) {
                if ($device['machineSerial'] === $json_data['machineSerial']) {
                    $device['status'] = 'hardware-error'; // 設置異常狀態為黃色燈號
                }
            }
            // 寫回更新後的 DeviceInfo.php
            file_put_contents($device_file_path, "<?php\n return " . var_export($device_data, true) . ";\n");
        }

        echo json_encode(['status' => 'success', 'message' => 'Data successfully saved and DeviceInfo updated']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON data']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>
