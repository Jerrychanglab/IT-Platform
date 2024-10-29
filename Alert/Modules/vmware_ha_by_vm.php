<?php
// 原始格式: 2024-09-26T08:05:56.192Z ESXi-10-31-34-51 Fdm[2099736] New event: EventEx=com.vmware.vc.ha.VmRestartedByHAEvent vm=/vmfs/volumes/f94a300c-3c561f93/jarry-ha-01.sr-cni.test/jarry-ha-01.sr-cni.test.vmx host=host-1040 tag=host-1040:-255113267:50
function vmware_ha_by_vm($line, $group) {
    if (preg_match("/([0-9T:\.\-]+Z) (ESXi-\d{2}-\d{2}-\d{2}-\d{2}) .* New event: EventEx=.* vm=\/vmfs\/volumes\/[a-z0-9-]+\/([a-zA-Z0-9-\.]+)\//", $line, $match)) {
        // 白名單比對
        if (!is_match_cond($line)) {
            return;
        }
	$chat_id = $group[1];
        $utc_time = $match[1];
        $date = new DateTime($utc_time, new DateTimeZone('UTC'));
        $date->setTimezone(new DateTimeZone('Asia/Taipei'));
        $date_time = $date->format('Y-m-d H:i:s');
        $esxi_ip = $match[2];
        $vm_name = $match[3];

        $format_message = "【 告警: ESXI HA觸發  】
時間: $date_time
區域: NTP
名稱: ${vm_name}
訊息: 虛擬機已在 ${esxi_ip} 上重啟。\n";

        send_telegram($chat_id, $format_message);
        file_put_contents("/var/log/analuze.log", date("Y-m-d H:i:s") . "\n$format_message\n", FILE_APPEND);
    }
}
?>
