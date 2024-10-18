<?php
// 解密密碼
$encryptedFile = 'encrypted_pw.txt';
$decryptionKey = 'verifypasswd';
$pw = trim(shell_exec("openssl enc -aes-256-cbc -d -salt -pbkdf2 -in $encryptedFile -pass pass:$decryptionKey"));

// 發送告警的函數（外部定義，避免多次宣告）
function sendAlert($model, $machineSerial, $device_ip, $management_ip, $alert_text, $event_id) {
    date_default_timezone_set('Asia/Taipei');
    $json_data = [
        'timestamp' => date('Y-m-d H:i:s'),
        'model' => $model,
        'machineSerial' => $machineSerial,
        'device_ip' => $device_ip,
        'management_ip' => $management_ip,
        'event_id' => $event_id,
        'alert_text' => $alert_text,
        'event_link' => 'https://vgjira.atlassian.net/wiki/spaces/SRCNI/pages/' . $event_id
    ];

    $maintain_url = "http://10.31.33.18/Device/Maintain.php";
    $ch = curl_init($maintain_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json_data));
    curl_exec($ch);
    curl_close($ch);
}

// 定義掃描函數
function performScan($server, $pw) {
    if (empty($server['device_ip'])) {
        exit(0);
    }

    $esxi_ip = $server['device_ip'];
    exec("timeout 5 fping -t 500 $esxi_ip 2>&1", $ping_output, $ping_status);
    $ping_result = implode("\n", $ping_output);

    if ($ping_status === 124 || strpos($ping_result, 'is unreachable') !== false) {
        $event_text = "設備-IP:($esxi_ip)，Ping異常，請檢查設備狀態。";
        $event_id = "278954512";
        sendAlert($server['model'], $server['machineSerial'], $server['device_ip'], $server['management_ip'], $event_text, $event_id);
        exit(0);
    }

    $connection = ssh2_connect($esxi_ip, 22);
    if (!$connection) {
        echo "無法連接到 $esxi_ip\n";
        exit(0);
    }

    if (ssh2_auth_password($connection, 'root', $pw)) {
        $stream_ip = ssh2_exec($connection, "ipmitool lan print | grep -w 'IP Address' | grep -v 'Source' | awk '{print \$4}'");
        stream_set_blocking($stream_ip, true);
        $management_ip = trim(stream_get_contents($stream_ip));

        $stream_serial = ssh2_exec($connection, "esxcli hardware platform get | grep 'Serial Number:' | grep -v 'Enclosure' | awk '{print \$3}'");
        stream_set_blocking($stream_serial, true);
        $serial_number = trim(stream_get_contents($stream_serial));

        if ($management_ip !== $server['management_ip'] || $serial_number !== $server['machineSerial']) {
            $event_text = "伺服器-管理IP:($management_ip)，或序號不匹配。";
            $event_id = "277119236";
            sendAlert($server['model'], $server['machineSerial'], $server['device_ip'], $server['management_ip'], $event_text, $event_id);
        }
    } else {
        echo "認證失敗到 $esxi_ip\n";
    }

    exit(0);
}

// 無限迴圈
while (true) {
    $servers = include('/var/www/html/Device/DeviceInfo.php');
    $max_processes = 10;
    $current_processes = 0;

    foreach ($servers as $server) {
        if (!isset($server['device_ip'])) continue;

        $pid = pcntl_fork();
        if ($pid == -1) {
            die("無法創建子進程");
        } elseif ($pid) {
            $current_processes++;
            while ($current_processes >= $max_processes) {
                $waited_pid = pcntl_waitpid(-1, $status, WNOHANG);
                if ($waited_pid > 0) {
                    $current_processes--;
                } else {
                    usleep(500000);
                }
            }
        } else {
            performScan($server, $pw);
            exit(0);
        }
    }

    while ($current_processes > 0) {
        $waited_pid = pcntl_wait($status);
        if ($waited_pid > 0) {
            $current_processes--;
        } elseif ($waited_pid == -1) {
            break;
        }
    }

    sleep(60);
}
?>
