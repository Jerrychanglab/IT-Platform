<?php
function get_device_info($ip = null, $serial = null) {
    if (!$ip && !$serial) {
        return "請提供 IP 或序號來查詢設備信息。";
    }

    // 構建 URL，根據提供的 IP 或序號進行查詢
    if ($ip) {
        $url = "http://10.31.33.18/Device/Search.php?ip=" . urlencode($ip);
    } elseif ($serial) {
        $url = "http://10.31.33.18/Device/Search.php?serial=" . urlencode($serial);
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        file_put_contents("/var/log/analuze_error.log", "Curl error: " . $error_msg . "\n", FILE_APPEND);
        return null;
    }

    curl_close($ch);

    // 檢查是否有返回結果
    if (!$response) {
        file_put_contents("/var/log/analuze_error.log", "Empty response from the server\n", FILE_APPEND);
        return null;
    }

    // 假設返回的是 JSON 格式，將其解碼成陣列
    $device_info = json_decode($response, true);

    // 如果解碼失敗，記錄錯誤
    if (json_last_error() !== JSON_ERROR_NONE) {
        file_put_contents("/var/log/analuze_error.log", "JSON decode error: " . json_last_error_msg() . "\n", FILE_APPEND);
        return null;
    }

    return $device_info; // 返回解析後的設備信息
}
?>
