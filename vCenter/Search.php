<?php
// 設定 JSON 目錄路徑
$jsonDirectory = '/var/www/html/vCenter/list/';

// 獲取搜尋條件
$searchCriteria = [
    'vcenter' => isset($_GET['vcenter']) ? $_GET['vcenter'] : null,
    'esxi_ip' => isset($_GET['esxi_ip']) ? $_GET['esxi_ip'] : null,
    'vm_name' => isset($_GET['vm_name']) ? $_GET['vm_name'] : null,
    'category' => isset($_GET['category']) ? $_GET['category'] : null,
    'ip_address' => isset($_GET['ip_address']) ? $_GET['ip_address'] : null,
    'mac_address' => isset($_GET['mac_address']) ? $_GET['mac_address'] : null
];

// 遍歷目錄下的所有 JSON 檔案
$files = glob($jsonDirectory . '*.json');
$results = [];

foreach ($files as $file) {
    // 解析 JSON 檔案，過濾掉非 JSON 的內容
    $content = file_get_contents($file);
    
    // 只保留從 "[" 開始的部分，過濾掉開頭的非 JSON 內容
    $jsonStart = strpos($content, '[');
    if ($jsonStart !== false) {
        $jsonContent = substr($content, $jsonStart);
        $jsonData = json_decode($jsonContent, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            foreach ($jsonData as $vm) {
                $match = true;

                // 檢查每個搜尋條件
                foreach ($searchCriteria as $key => $value) {
                    if (!empty($value)) {
                        // 處理 ip_address 和 mac_address 位於 network_info 內部的情況
                        if ($key == 'ip_address' || $key == 'mac_address') {
                            $networkMatch = false;
                            foreach ($vm['network_info'] as $network) {
                                if ($network[$key] === $value) {
                                    $networkMatch = true;
                                    break;
                                }
                            }
                            if (!$networkMatch) {
                                $match = false;
                                break;
                            }
                        } else {
                            // 一般條件檢查
                            if ($vm[$key] !== $value) {
                                $match = false;
                                break;
                            }
                        }
                    }
                }

                // 如果符合條件，加入到結果中
                if ($match) {
                    $results[] = $vm;
                }
            }
        } else {
            error_log("JSON decode error: " . json_last_error_msg());
        }
    } else {
        error_log("No valid JSON found in file: " . $file);
    }
}

// 返回搜尋結果
header('Content-Type: application/json');
echo json_encode($results, JSON_PRETTY_PRINT);
?>
