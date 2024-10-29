<?php
// Chat ID
$group[1] = "-XXXXX"; // 請新增自己的群組group
$group[2] = "-XXXXXX"; // 請新增自己的群組group

// Bot代碼
function send_telegram($chat_id, $message, $parse_mode = 'Markdown')
{
    $message = trim($message);
    $bot = "000000000:AAEXfUy_77bXXXXX_jOVn_aWV5XXXXX"; // 請更換自己的bot
    $ch = curl_init("https://api.telegram.org/bot$bot/sendMessage");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'text' => $message,
        'chat_id' => $chat_id,
        'parse_mode' => $parse_mode,  // 使用 Markdown 格式
    ]));

    // 配置代理
    curl_setopt($ch, CURLOPT_PROXY, "http://10.33.55.87:3128"); // 修改自己的PROXY

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        file_put_contents("/var/log/analuze_telegram.log", "Curl error: " . $error_msg . "\n", FILE_APPEND);
    } else {
        file_put_contents("/var/log/analuze_telegram.log", "Telegram response: " . $response . "\n", FILE_APPEND);
    }
    curl_close($ch);
}
?>
