<?php
// 原始格式: 2024-09-26 08:06:37 - Received line: 2024-09-26T08:05:43.085Z 10.31.34.50 vcenter-server vSphere HA detected a possible host failure of host 10.31.34.50 in cluster HA-test in datacenter SR
function vmware_ha_by_esxi($line, $group) {
    if (preg_match("/([0-9T:\.\-]+Z) .*vSphere HA detected a possible host failure of host ([0-9.]+) in cluster ([a-zA-Z0-9-]+) in datacenter ([a-zA-Z0-9-]+)/", $line, $match)) {
        // 白名單比對
        if (!is_match_cond($line)) {
            return;
        }
        $chat_id = $group[1];
        $utc_time = $match[1];
        $date = new DateTime($utc_time, new DateTimeZone('UTC'));
        $date->setTimezone(new DateTimeZone('Asia/Taipei'));
        $date_time = $date->format('Y-m-d H:i:s');
        $ip = $match[2];
        $cluster_name = $match[3];
        $datacenter_name = $match[4];
        // 調用 API 獲取設備信息
        $device_info = get_device_info($ip);

        // 從陣列中獲取設備的詳細信息
        $model = isset($device_info['model']) ? $device_info['model'] : 'Null';
        $machineSerial = isset($device_info['machineSerial']) ? $device_info['machineSerial'] : 'Null';
        $management_ip = isset($device_info['management_ip']) ? $device_info['management_ip'] : 'Null';

        $format_message = "【 告警: ESXI HA觸發  】
時間: ${date_time}
區域: NTP
型號: ${model}
序號: ${machineSerial}
機器: ${ip}
管理: ${management_ip}
訊息: 在資料中心 ${datacenter_name} 中的集群 ${cluster_name} 觸發了 HA。\n";

        send_telegram($chat_id, $format_message);
        file_put_contents("/var/log/analuze.log", date("Y-m-d H:i:s") . "\n$format_message\n", FILE_APPEND);
    }
}
?>
