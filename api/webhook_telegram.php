<?php
define("IN_SITE", true);
require_once(__DIR__.'/../libs/db.php');
require_once(__DIR__.'/../libs/lang.php');
require_once(__DIR__.'/../libs/helper.php');
require_once(__DIR__.'/../config.php');

$CMSNT = new DB();

// Cấu hình bot
$BOT_TOKEN = $CMSNT->site('telegram_token'); // Thay bằng token bot thực của bạn
$BOT_USERNAME = $CMSNT->site('telegram_bot_username'); // Thay bằng username bot của bạn
$WEBHOOK_SECRET = $CMSNT->site('telegram_webhook_secret'); // Secret key để xác minh webhook

// Sử dụng hàm sendMessTelegram có sẵn trong hệ thống




/**
 * Xác minh webhook từ Telegram
 */
function verifyTelegramWebhook() {
    global $WEBHOOK_SECRET;
    
    // Kiểm tra secret token từ header
    $secret_header = validate_string($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '', 2000);
    if (empty($WEBHOOK_SECRET) || $secret_header !== $WEBHOOK_SECRET) {
        http_response_code(403);
        die('Unauthorized: Invalid secret token');
    }
    
    // Kiểm tra định dạng dữ liệu
    $input = file_get_contents('php://input');
    if (empty($input)) {
        http_response_code(400);
        die('Bad Request: Empty payload');
    }
    
    $update = json_decode($input, true);
    if (!$update || !isset($update['update_id'])) {
        http_response_code(400);
        die('Bad Request: Invalid JSON payload');
    }
    
    return $update;
}

/**
 * Xử lý webhook từ Telegram
 */
function handleTelegramWebhook() {
    global $CMSNT, $BOT_TOKEN;
    
    // Xác minh bảo mật webhook
    $update = verifyTelegramWebhook();
    
    
    if (!isset($update['message'])) {
        return;
    }
    
    $message = $update['message'];
    $chat_id = $message['chat']['id'];
    $text = $message['text'] ?? '';
    $username = $message['from']['username'] ?? 'Unknown';
    $first_name = $message['from']['first_name'] ?? '';
    $last_name = $message['from']['last_name'] ?? '';
    $full_name = trim($first_name . ' ' . $last_name);
    
    // Xử lý lệnh /start
    if ($text === '/start') {
        $welcome_message = "🤖 **".__('Chào mừng bạn đến với')." " . $CMSNT->site('title') . "!**\n\n";
        $welcome_message .= "📱 ".__('Để liên kết tài khoản của bạn, vui lòng').":\n";
        $welcome_message .= "1️⃣ ".__('Đăng nhập vào website')."\n";
        $welcome_message .= "2️⃣ ".__('Vào trang Profile → Bảo mật')."\n";
        $welcome_message .= "3️⃣ ".__('Nhấn')." '".__('Liên kết Telegram')."'\n";
        $welcome_message .= "4️⃣ ".__('Sao chép mã liên kết và gửi cho tôi')."\n\n";
        $welcome_message .= "📝 **".__('Cú pháp').":** `/link ".__('MÃ_LIÊN_KẾT_CỦA_BẠN')."`";
        
        sendMessTelegram($welcome_message, $BOT_TOKEN, $chat_id);
        return;
    }
    
    // Xử lý lệnh /link
    if (strpos($text, '/link') === 0) {
        $parts = explode(' ', $text, 2);
        
        if (count($parts) < 2) {
            $error_message = "❌ **".__('Lỗi cú pháp')."!**\n\n";
            $error_message .= "📝 **".__('Cú pháp đúng').":** `/link ".__('MÃ_LIÊN_KẾT')."`\n\n";
            $error_message .= "💡 **".__('Hướng dẫn').":**\n";
            $error_message .= "1. ".__('Đăng nhập website')."\n";
            $error_message .= "2. ".__('Vào Profile → Bảo mật')."\n";
            $error_message .= "3. ".__('Nhấn')." '".__('Liên kết Telegram')."'\n";
            $error_message .= "4. ".__('Sao chép mã và gửi lại đây');
            
            sendMessTelegram($error_message, $BOT_TOKEN, $chat_id);
            return;
        }
        
        $user_token = trim($parts[1]);
        
        // Gọi API để liên kết tài khoản
        $link_result = linkTelegramAccount($user_token, $chat_id, $username, $full_name);
        
        if ($link_result['status'] === 'success') {
            $success_message = "✅ **".__('Liên kết thành công')."!**\n\n";
            $success_message .= "👤 **".__('Tài khoản').":** " . $link_result['username'] . "\n";
            $success_message .= "🔗 **Telegram:** @" . $username . "\n\n";
            $success_message .= "🎉 ".__('Bạn sẽ nhận được thông báo quan trọng từ hệ thống qua Telegram này')."!";
            
            sendMessTelegram($success_message, $BOT_TOKEN, $chat_id);
        } else {
            $error_message = "❌ **".__('Liên kết thất bại')."!**\n\n";
            $error_message .= "📋 **".__('Lý do').":** " . $link_result['msg'];
            
            sendMessTelegram($error_message, $BOT_TOKEN, $chat_id);
        }
        return;
    }
    
    // Xử lý tin nhắn khác
    $help_message = "🤖 **".__('Tôi không hiểu lệnh này')."!**\n\n";
    $help_message .= "📋 **".__('Các lệnh có sẵn').":**\n";
    $help_message .= "• `/start` - ".__('Bắt đầu')."\n";
    $help_message .= "• `/link ".__('MÃ_LIÊN_KẾT')."` - ".__('Liên kết tài khoản')."\n\n";
    $help_message .= "❓ ".__('Cần hỗ trợ? Liên hệ').": " . $CMSNT->site('hotline');
    
    sendMessTelegram($help_message, $BOT_TOKEN, $chat_id);
}

/**
 * Liên kết tài khoản Telegram
 */
function linkTelegramAccount($api_key, $chat_id, $telegram_username, $full_name) {
    // Gọi API endpoint của chính website
    $url = base_url('ajaxs/client/auth.php');
    $data = [
        'action' => 'linkTelegramBot',
        'api_key' => $api_key,
        'chat_id' => $chat_id,
        'telegram_username' => $telegram_username,
        'full_name' => $full_name
    ];
    
    // Chuẩn bị masked payload (ẩn api_key) cho nội bộ
    $masked = $data;
    if(isset($masked['api_key'])){
        $key = (string)$masked['api_key'];
        $masked['api_key'] = (strlen($key) > 6)
            ? substr($key, 0, 3).'***'.substr($key, -2)
            : '***';
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'TelegramBot/1.0');
    // Thêm header nội bộ để chỉ cho phép request từ server
    $internal_secret = hash_hmac('sha256', (string)$chat_id, $_ENV['SECRET_KEY'] ?? '');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-Internal-Request: 1',
        'X-Internal-Secret: '.$internal_secret
    ]);
    
    $response = curl_exec($ch);
    $curl_errno = curl_errno($ch);
    $curl_error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_info = curl_getinfo($ch);
    curl_close($ch);
    
    // no file logging

    if ($http_code !== 200) {
        return ['status' => 'error', 'msg' => __('Không thể kết nối đến server')];
    }
    
    $result = json_decode($response, true);
    if (!$result) {
        return ['status' => 'error', 'msg' => __('Server trả về dữ liệu không hợp lệ')];
    }
    
    return $result;
}


// Xử lý webhook nếu được gọi qua HTTP
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handleTelegramWebhook();
}
 
 