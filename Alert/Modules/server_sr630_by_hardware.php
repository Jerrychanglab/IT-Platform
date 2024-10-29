<?php
// 處理告警函數
function server_sr630_by_hardware($line, $group) {
    if (preg_match('/([0-9T:\.\-]+Z).*?Alert Text:\s*(.*?)#012.*?BMC Serial Number:\s*([A-Z0-9]+).*?Common Event ID:\s*([A-Z0-9]+)/s', $line, $match)) {
        // 白名單比對
        if (!is_match_cond($line)) {
            return;
        }
        $chat_id = $group[1];
        $date_time = $match[1];
        $alert_text = $match[2];
        $serial = $match[3];
        $event_id = $match[4];
	$utc_time = $match[1];
        $date = new DateTime($utc_time, new DateTimeZone('UTC')); // 將時間設置為 UTC
        $date->setTimezone(new DateTimeZone('Asia/Taipei')); // 將時間轉換為 Asia/Taipei
        $local_time = $date->format('Y-m-d H:i:s'); // 將時間格式化為 "Y-m-d H:i:s"

        // 調用 API 獲取設備信息，只傳入 serial
        $device_info = get_device_info(null, $serial);

// 記錄原始API返回結果
file_put_contents("/var/log/analuze_debug.log", "API response for serial $serial: " . print_r($device_info, true) . "\n", FILE_APPEND);

        // 檢查是否成功獲取設備信息
        if ($device_info) {
            // 從陣列中獲取設備的詳細信息
            $model = isset($device_info['model']) ? $device_info['model'] : 'Null';
            $machineSerial = isset($device_info['machineSerial']) ? $device_info['machineSerial'] : 'Null';
            $management_ip = isset($device_info['management_ip']) ? $device_info['management_ip'] : 'Null';
            $device_ip = isset($device_info['device_ip']) ? $device_info['device_ip'] : 'Null';

	    // 構建Event ID的超連結
	    $event_link = "https://pubs.lenovo.com/sr630/zh-TW/" . $event_id;
	    // 格式化告警訊息
            $format_message = "【 告警: 伺服器 硬體告警 】
時間: ${local_time}
區域: NTP
型號: ${model}
序號: ${machineSerial}
機器: ${device_ip}
管理: ${management_ip}
訊息: EventID ➡ [${event_id}](${event_link})，${alert_text}\n";

	    send_telegram($chat_id, $format_message);

            // 創建一個包含告警信息的 JSON 數據
            $json_data = [
                'timestamp' => $local_time,
                'model' => $model,
                'machineSerial' => $machineSerial,
                'device_ip' => $device_ip,
                'management_ip' => $management_ip,
                'event_id' => $event_id,
		'alert_text' => $alert_text,
		'event_link' => $event_link
            ];

            // 使用 cURL 將數據發送到 maintain.php
            $url = "http://10.31.33.18/Device/Maintain.php";
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json_data));

            // 執行請求
            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                $error_msg = curl_error($ch);
                file_put_contents("/var/log/analuze_error.log", "Curl error: " . $error_msg . "\n", FILE_APPEND);
            } else {
                file_put_contents("/var/log/analuze_debug.log", "Successfully sent data to maintain.php: " . $response . "\n", FILE_APPEND);
            }

            // 關閉 cURL
	    curl_close($ch);

	    file_put_contents("/var/log/analuze.log", date("Y-m-d H:i:s") . "\n$format_message\n", FILE_APPEND);
        } else {
            // 處理設備信息未找到的情況，記錄更多詳細資訊以便調試
            file_put_contents("/var/log/analuze_error.log", "Device info not found for serial: $serial. Line: $line\n", FILE_APPEND);
        }
    } else {
        // 如果正則匹配失敗，記錄錯誤
        file_put_contents("/var/log/analuze_error.log", "Failed to parse line: $line\n", FILE_APPEND);
    }
}
?>
