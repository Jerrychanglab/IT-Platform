<?php
session_start();

$vcenterPhpFile = 'vCenterInfo.php';
$secret_key = "jarry_chang";
$filepath = "/var/www/html/vCenter/list";
$mainLockFile = "$filepath/VmSan.lock";

// 檢查是否已存在全域鎖文件
if (file_exists($mainLockFile)) {
    echo "VmSan.php 已在執行中。退出。\n";
    exit;
}

// 創建文件
file_put_contents($mainLockFile, "Locked at " . date('Y-m-d H:i:s'));

function decryptPassword($encryptedPassword, $secretKey) {
    $data = base64_decode($encryptedPassword);
    if (strlen($data) <= 16) {
        echo "密碼格式異常。\n";
        return false;
    }

    $iv = substr($data, 0, 16);
    $encryptedText = substr($data, 16);
    $decryptedPassword = openssl_decrypt($encryptedText, 'aes-256-cbc', $secretKey, 0, $iv);

    if ($decryptedPassword === false) {
        echo "解密失敗。\n";
    }

    return $decryptedPassword;
}

// 清除第一次執行的 .lock 和 .tmp 文件
$files = glob("$filepath/*.{lock,tmp}", GLOB_BRACE);
foreach ($files as $file) {
    if (is_file($file) && $file !== $mainLockFile) { // 排除主鎖文件
        unlink($file);
        echo "移除lock and tmp：$file\n";
    }
}

// 主循環，每分鐘執行一次
while (true) {
    $vcenterData = file_exists($vcenterPhpFile) ? include($vcenterPhpFile) : [];
    $processes = [];

    foreach ($vcenterData as $vcenter) {
        $vCenterIP = $vcenter['vcenter'];
        $user = $vcenter['user'];
        $encryptedPassword = $vcenter['passwd'];
        $lockFile = "$filepath/$vCenterIP.lock";

        if (file_exists($lockFile)) {
            echo "vCenter IP $vCenterIP，任務執行中。\n";
            continue;
        }

        file_put_contents($lockFile, "Locked at " . date('Y-m-d H:i:s'));

        $password = decryptPassword($encryptedPassword, $secret_key);
        if ($password === false) {
            unlink($lockFile);
            continue;
        }

        $ImagesCluster = "vm_info";
        $ImagesClusterName = "vm_info_" . $vCenterIP;
        $temp_filepath = "$filepath/$vCenterIP.tmp";
        $final_filepath = "$filepath/$vCenterIP.json";
        $dockerCommand = "docker run -e vCenterIP={$vCenterIP} -e UserName={$user} -e Password={$password} --name {$ImagesClusterName} --rm {$ImagesCluster} /workspace/vm_info.ps1 > {$temp_filepath} && mv {$temp_filepath} {$final_filepath}";

        $processes[] = popen("$dockerCommand && rm $lockFile", 'r');
    }

    foreach ($processes as $process) {
        pclose($process);
    }

    echo "所有命令執行完成。\n";
    sleep(60);
}

// 執行完畢後，刪除全域文件
unlink($mainLockFile);
