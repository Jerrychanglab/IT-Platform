#!/usr/bin/php
<?php
// 使用相對路徑讀取白名單中的所有 .conf 檔案
$whitelist_dir = __DIR__ . '/Whitelist';
$whitelist_files = scandir($whitelist_dir);
$cond_arr = [];

// 讀取白名單並過濾條件
foreach ($whitelist_files as $file) {
    if (pathinfo($file, PATHINFO_EXTENSION) === 'conf') {
        foreach (file("$whitelist_dir/$file") as $cond) {
            $cond = trim($cond);
            if (substr($cond, 0, 1) != "#" && strlen($cond) >= 3) {
                $cond_arr[] = $cond;
            }
        }
    }
}

// 比對白名單條件函數
function is_match_cond($line)
{
    global $cond_arr;
    foreach ($cond_arr as $cond) {
        if (stripos($line, $cond) !== false) {
            return true; // 找到符合條件的
        }
    }
    return false;
}

// 告警資訊API路徑
include __DIR__ . '/API/get_device_info.php';

// 發送告警模組 - 路徑
include __DIR__ . '/Alert/send_telegram.php';

// 過濾模組 - 路徑
$module_dir = __DIR__ . '/Modules';

// 預加載所有有效模組函數
$valid_module_functions = [];
$module_files = scandir($module_dir);

foreach ($module_files as $module_file) {
    if (pathinfo($module_file, PATHINFO_EXTENSION) === 'php') {
        include_once "$module_dir/$module_file";
        $module_function = str_replace('.php', '', $module_file);
        if (function_exists($module_function)) {
            $valid_module_functions[] = $module_function;
        }
    }
}

// 主循環處理
while ($line = trim(fgets(STDIN))) {
    // 先過濾行，若不符合白名單條件則跳過
    if (!is_match_cond($line)) {
        continue;
    }

    // 記錄符合條件的行至日誌
    file_put_contents("/var/log/analuze_input.log", date("Y-m-d H:i:s") . " - Received line: $line\n", FILE_APPEND);

    // 執行所有有效模組函數
    foreach ($valid_module_functions as $module_function) {
        $module_function($line, $group);
    }
}
?>
