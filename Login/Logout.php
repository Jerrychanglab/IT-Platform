<?php
session_start();
session_unset();
session_destroy();
header('Content-Type: application/json'); // 確保回應為 JSON 格式
echo json_encode(['success' => true]);
exit;
?>
