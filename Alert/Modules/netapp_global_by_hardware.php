<?php
// 處理告警函數
function netapp_global_by_hardware($line, $group) {
	// 正則表達式來匹配關鍵格式: 型號、事件類型和 :error
      //if (preg_match('/ \[(.*):(.*):error]: (.*)$/i', $line, $match)) {
      if (preg_match('/ \[(.*):(.*):(error|ALERT)]: (.*)$/i', $line, $match)) {
        // 匹配到訊息，提取資料
        $model = $match[1]; // 型號
        $event_type = $match[2]; // 事件類型
        $failure_details = str_replace(['[', ']', '_'], ['', '', ' '], $match[4]);
        //$failure_details = str_replace(['[', ']'], ['', ''], $match[4]);
        // 設置告警訊息
        $chat_id = $group[2];

        // 設置時間
        $date = new DateTime('now', new DateTimeZone('Asia/Taipei')); // 設置為台北時區
        $local_time = $date->format('Y-m-d H:i:s'); // 格式化為 "Y-m-d H:i:s"

        // Info 資訊
        $info = [
            ["SH", "設備-序號", "型號-定義", "設備-名稱", "管理-VIP", "設備-IP"],
            ["SZ", "設備-序號", "型號-定義", "設備-名稱", "管理-VIP", "設備-IP"],
        ];

        // 初始化區域、序號、管理
        $location = "未知";
        $serial = "未知";
        $management_ip = "未知";

        // 查找對應的區域和序號
        foreach ($info as $entry) {
            if ($entry[3] === $model) { // 匹配到型號
                $location = $entry[0]; // 區域
		$serial = $entry[1]; // 序號
                $type = $entry[2]; // 型號
		$management_ip = $entry[4]; // 管理 IP
		$device_ip = $entry[5]; // 設備IP
                break; // 找到後退出迴圈
            }
        }

        // 構建 Event ID
        $event_type_parts = explode('.', $event_type); // 以 '.' 分割事件類型
	$event_id = strtolower(trim($event_type_parts[0]) . '-' . trim($event_type_parts[1]) . '-events'); // 組合成新的連結格式
	$event_key = str_replace('.', '-', trim($event_type));
        $event_link = "https://docs.netapp.com/us-en/ontap-ems-9111/" . $event_id . ".html#" . $event_key;

        // 構建告警訊息
        $format_message = "【 告警: NetApp 事件 】
時間: ${local_time}
區域: ${location}
型號: ${type}
序號: ${serial}
機器: ${model}
管理: ${management_ip}
訊息: EventID ➡ [${event_type}](${event_link})，${failure_details}\n";

        // 發送到 Telegram
        send_telegram($chat_id, $format_message);

            // 創建一個包含告警信息的 JSON 數據
            $json_data = [
                'timestamp' => $local_time,
                'model' => $model,
                'machineSerial' => $serial,
                'device_ip' => $device_ip,
                'management_ip' => $management_ip,
                'event_id' => $event_type,
                'alert_text' => $failure_details,
                'event_link' => $event_link
            ];

            // 使用 cURL 將數據發送到 maintain.php
            $url = "http://10.31.33.18/Device/Maintain.php"; //需更改你自己的機器IP
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

           // 將告警記錄保存到日誌中
           file_put_contents("/var/log/analuze_disk_alert.log", date("Y-m-d H:i:s") . "\n$format_message\n", FILE_APPEND);
    } else {
        // 沒有匹配到，將訊息記錄到錯誤日誌
        file_put_contents("/var/log/analuze_error.log", "Failed to match line: $line\n", FILE_APPEND);
    }
}
?>
