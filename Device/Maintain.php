<?php
// 檢查請求是否為 POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postData = file_get_contents('php://input');
    $json_data = json_decode($postData, true);

    if (json_last_error() === JSON_ERROR_NONE) {
        $file_path = 'MaintainInfo.php';
        $device_file_path = 'DeviceInfo.php';

        $retry_limit = 5;
        $retry_delay = 1;
        $write_successful = false;

        for ($attempt = 1; $attempt <= $retry_limit; $attempt++) {
            // 嘗試對 MaintainInfo.php 鎖定並寫入
            $maintain_file = fopen($file_path, 'c+');
            if (flock($maintain_file, LOCK_EX)) {
                $existing_data = json_decode(file_get_contents($file_path), true) ?: [];

                foreach ($existing_data as $record) {
                    if ($record['machineSerial'] === $json_data['machineSerial'] && $record['event_id'] === $json_data['event_id']) {
                        fclose($maintain_file);
                        echo json_encode(['status' => 'error', 'message' => 'Duplicate entry found.']);
                        exit;
                    }
                }

                // 更新 MaintainInfo.php
                $existing_data[] = $json_data;
                ftruncate($maintain_file, 0);
                fwrite($maintain_file, json_encode($existing_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                fflush($maintain_file);
                flock($maintain_file, LOCK_UN);
                fclose($maintain_file);

                // 嘗試對 DeviceInfo.php 鎖定並更新
                $device_file = fopen($device_file_path, 'c+');
                if (flock($device_file, LOCK_EX)) {
                    $device_data = include($device_file_path);
                    if (is_array($device_data)) {
                        foreach ($device_data as &$device) {
                            if ($device['machineSerial'] === $json_data['machineSerial']) {
                                $device['status'] = 'hardware-error';
                            }
                        }
                        ftruncate($device_file, 0);
                        fwrite($device_file, "<?php\n return " . var_export($device_data, true) . ";\n");
                        fflush($device_file);
                        flock($device_file, LOCK_UN);
                        fclose($device_file);
                        $write_successful = true;
                        break; // 成功寫入，跳出重試循環
                    } else {
                        error_log("DeviceInfo.php 資料格式錯誤，無法更新");
                        fclose($device_file);
                    }
                } else {
                    error_log("DeviceInfo.php 鎖定失敗");
                    fclose($device_file);
                }
            } else {
                fclose($maintain_file);
                sleep($retry_delay);
            }
        }

        if (!$write_successful) {
            error_log("Failed to acquire lock for both files after {$retry_limit} attempts.");
            echo json_encode(['status' => 'error', 'message' => 'Could not acquire lock for both files.']);
            exit;
        }

        echo json_encode(['status' => 'success', 'message' => 'Data successfully saved and DeviceInfo updated']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON data']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>
