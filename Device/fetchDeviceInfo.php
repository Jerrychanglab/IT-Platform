<?php
header('Content-Type: application/json');
// 加入 DeviceInfo.php 的內容
$data = include('DeviceInfo.php');
echo json_encode($data);
exit; // 或者使用 die();
?>
