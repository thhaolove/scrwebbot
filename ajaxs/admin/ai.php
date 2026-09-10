<?php

define("IN_SITE", true);
require_once(__DIR__ . "/../../libs/db.php");
require_once(__DIR__ . "/../../libs/lang.php");
require_once(__DIR__ . "/../../libs/helper.php");
require_once(__DIR__ . "/../../config.php");
require_once(__DIR__ . '/../../models/is_admin.php');

if ($CMSNT->site('status_demo') == 1) {
    $data = json_encode([
        'status'    => 'error',
        'msg'       => __('Chức năng này không khả dụng trong chế độ demo')
    ]);
    die($data);
}

// Kiểm tra CSRF token cho tất cả request
checkCSRFAjax();

// Function để loại bỏ markdown code blocks và cấu trúc HTML không cần thiết
function cleanAIContent($content)
{
    // Loại bỏ ```html ở đầu
    $content = preg_replace('/^```html\s*/i', '', $content);
    // Loại bỏ ``` ở đầu 
    $content = preg_replace('/^```\s*/i', '', $content);
    // Loại bỏ ``` ở cuối
    $content = preg_replace('/\s*```$/i', '', $content);

    // Loại bỏ cấu trúc HTML cơ bản (cho script footer)
    $content = preg_replace('/^\s*<!DOCTYPE[^>]*>/i', '', $content);
    $content = preg_replace('/^\s*<html[^>]*>/i', '', $content);
    $content = preg_replace('/^\s*<head[^>]*>/i', '', $content);
    $content = preg_replace('/^\s*<\/head>/i', '', $content);
    $content = preg_replace('/^\s*<body[^>]*>/i', '', $content);
    $content = preg_replace('/\s*<\/body>\s*$/i', '', $content);
    $content = preg_replace('/\s*<\/html>\s*$/i', '', $content);

    // Loại bỏ meta tags không cần thiết
    $content = preg_replace('/<meta[^>]*>/i', '', $content);

    // Loại bỏ các tiêu đề HTML thường gặp ở đầu
    $content = preg_replace('/^<h[1-6][^>]*>.*?<\/h[1-6]>\s*/i', '', $content);

    // Loại bỏ các thẻ title
    $content = preg_replace('/<title[^>]*>.*?<\/title>/i', '', $content);

    // Trim khoảng trắng và xuống dòng thừa
    $content = trim($content);
    $content = preg_replace('/^\s*\n+/', '', $content);
    $content = preg_replace('/\n+\s*$/', '', $content);

    return $content;
}

if ($CMSNT->site('status_demo') != 0) {
    $data = json_encode([
        'status'    => 'error',
        'msg'       => __('Chức năng này không khả dụng trong chế độ demo')
    ]);
    die($data);
}
if (!isset($_POST['action'])) {
    $data = json_encode([
        'status'    => 'error',
        'msg'       => 'The Request Not Found'
    ]);
    die($data);
}

// Xử lý tạo HTML detail cho cấp bậc
if ($_POST['action'] == 'generateHTMLdetail') {
    if (!isset($_POST['description']) || empty(trim($_POST['description']))) {
        $data = json_encode([
            'success' => false,
            'message' => __('Vui lòng nhập mô tả về cấp bậc')
        ]);
        die($data);
    }

    $description = trim($_POST['description']);

    // Tạo prompt cho AI
    $prompt = "Tạo HTML cho ưu đãi: '$description'

Sử dụng template này và tùy chỉnh cho phù hợp:
<div class='benefit-item d-flex align-items-center mb-3 p-2 rounded-3' style='background: rgba(108, 117, 125, 0.08);'> 
  <div class='benefit-icon me-3' style='width: 32px; height: 32px; background: linear-gradient(135deg, #fd6e14, #ff5107); border-radius: 8px; display: flex; align-items: center; justify-content: center;'>
    <i class='ri-checkbox-circle-fill text-white' style='font-size: 14px;'></i>
   </div>
   <div>
     <div class='fw-medium' style='font-size: 13px;'>$description</div>
     <small class='text-muted'>Mô tả chi tiết liên quan</small>
   </div>
</div>

Yêu cầu:
1. Thay đổi màu gradient phù hợp với loại ưu đãi
2. Chọn icon RemixIcon phù hợp (ri-customer-service-line cho hỗ trợ, ri-percent-line cho giảm giá, ri-vip-crown-line cho VIP...)
3. Viết mô tả chi tiết hấp dẫn trong thẻ small
4. Giữ nguyên cấu trúc HTML và các class

CHỈ TRA VỀ HTML THUẦN TÚY, KHÔNG GIẢI THÍCH, KHÔNG SỬ DỤNG MARKDOWN CODE BLOCKS.";

    // Gọi hàm tạo nội dung AI
    $result = generateAIContent($prompt);
    $response = json_decode($result, true);

    if ($response['success']) {
        // Loại bỏ markdown code blocks
        $content = cleanAIContent($response['description']);

        $data = json_encode([
            'success' => true,
            'content' => $content
        ]);
    } else {
        $data = json_encode([
            'success' => false,
            'message' => $response['message']
        ]);
    }

    die($data);
}

// Xử lý tạo nội dung thông báo Telegram
if ($_POST['action'] == 'generateTelegramNotification') {
    if (!isset($_POST['type']) || empty(trim($_POST['type']))) {
        $data = json_encode([
            'success' => false,
            'message' => __('Vui lòng chọn loại thông báo')
        ]);
        die($data);
    }

    $type = trim(check_string($_POST['type']));

    // Định nghĩa các loại thông báo và prompt tương ứng
    $notifications = [
        'noti_api_out_of_money' => [
            'title' => 'Thông báo API hết tiền cho Admin',
            'variables' => '{domain}, {username}, {supplier_name}, {product_name}, {product_id}, {plan_id}, {pay}, {amount}, {ip}, {time}, {http_code}',
            'prompt' => 'Tạo nội dung thông báo Telegram cảnh báo cho Admin khi nhà cung cấp API hết tiền (out of money). Nội dung cần thể hiện tính khẩn cấp và hướng dẫn admin nạp tiền vào tài khoản API. Sử dụng emoji cảnh báo phù hợp.'
        ],
        'noti_api_connection_error' => [
            'title' => 'Thông báo lỗi kết nối API cho Admin',
            'variables' => '{domain}, {username}, {supplier_name}, {product_name}, {product_id}, {plan_id}, {pay}, {amount}, {ip}, {time}, {http_code}',
            'prompt' => 'Tạo nội dung thông báo Telegram cảnh báo cho Admin khi có lỗi kết nối đến API nhà cung cấp (connection error, timeout, server error). Nội dung cần thể hiện mã HTTP lỗi và đề xuất kiểm tra trạng thái nhà cung cấp. Sử dụng emoji cảnh báo phù hợp.'
        ],
        'noti_order_success_admin' => [
            'title' => 'Thông báo đơn hàng mới cho Admin',
            'variables' => '{domain}, {username}, {order_count}, {total_amount}, {discount_amount}, {coupon_code}, {order_ids}, {order_details}, {new_balance}, {ip}, {time}',
            'prompt' => 'Tạo nội dung thông báo Telegram chuyên nghiệp cho Admin khi có đơn hàng mới. Nội dung cần ngắn gọn, thể hiện thông tin quan trọng về đơn hàng, số tiền và khách hàng. Sử dụng emoji phù hợp.'
        ],
        'noti_pending_order_admin' => [
            'title' => 'Thông báo đơn hàng ORDER cần xử lý cho Admin',
            'variables' => '{domain}, {username}, {order_count}, {total_amount}, {order_ids}, {order_details}, {ip}, {time}',
            'prompt' => 'Tạo nội dung thông báo Telegram cảnh báo cho Admin khi có đơn hàng ORDER mới cần xử lý thủ công (đặt hàng, không giao ngay). Nội dung cần thể hiện tính khẩn cấp, nhắc nhở admin xử lý đơn hàng. Sử dụng emoji cảnh báo phù hợp như ⏳⚠️📦.'
        ],
        'noti_order_success_user' => [
            'title' => 'Thông báo mua hàng thành công cho User',
            'variables' => '{domain}, {username}, {order_count}, {total_amount}, {discount_amount}, {coupon_code}, {order_ids}, {new_balance}, {ip}, {time}',
            'prompt' => 'Tạo nội dung thông báo Telegram thân thiện cho User khi mua hàng thành công. Nội dung cần vui vẻ, chuyên nghiệp, cảm ơn khách hàng và hiển thị thông tin đơn hàng. Sử dụng emoji phù hợp.'
        ],
        'noti_recharge' => [
            'title' => 'Thông báo nạp tiền cho Admin',
            'variables' => '{domain}, {username}, {trans_id}, {method}, {amount}, {price}, {time}',
            'prompt' => 'Tạo nội dung thông báo Telegram cho Admin khi có giao dịch nạp tiền mới. Cần thể hiện rõ thông tin về số tiền và phương thức thanh toán.'
        ],
        'noti_action' => [
            'title' => 'Thông báo hành động cho Admin',
            'variables' => '{domain}, {username}, {action}, {ip}, {time}',
            'prompt' => 'Tạo nội dung thông báo Telegram cho Admin về các hành động quan trọng của người dùng trên hệ thống. Nội dung cần rõ ràng về hành động được thực hiện.'
        ],
        'noti_affiliate_withdraw' => [
            'title' => 'Thông báo rút hoa hồng cho Admin',
            'variables' => '{domain}, {username}, {bank}, {account_number}, {account_name}, {amount}, {ip}, {time}',
            'prompt' => 'Tạo nội dung thông báo Telegram cho Admin khi có yêu cầu rút hoa hồng. Cần thể hiện đầy đủ thông tin ngân hàng và số tiền rút.'
        ],
        'telegram_noti_login_user' => [
            'title' => 'Thông báo đăng nhập cho User',
            'variables' => '{domain}, {username}, {ip}, {device}, {time}',
            'prompt' => 'Tạo nội dung thông báo Telegram thân thiện cho người dùng khi đăng nhập thành công. Nội dung cần tạo cảm giác an toàn và chào đón.'
        ],

        'support_tickets_telegram_message' => [
            'title' => 'Thông báo khi có ticket mới cho Admin',
            'variables' => '{domain}, {username}, {subject}, {content}, {status}, {category}, {ip}, {time}, {device}',
            'prompt' => 'Tạo nội dung thông báo Telegram thân thiện cho Admin khi có ticket mới. Nội dung cần tạo cảm giác phải chuyên nghiệp.'
        ],
        'noti_user_admin_reply_ticket' => [
            'title' => 'Thông báo khi Admin reply ticket cho User',
            'variables' => '{username}, {subject}, {message}, {time}',
            'prompt' => 'Tạo nội dung thông báo Telegram cho người dùng khi Admin reply ticket của họ. Tin nhắn message phải giống như Admin đang trả lời họ trên Telegram và phải vào đúng trọng tâm.'
        ],
        'support_tickets_telegram_message_reply' => [
            'title' => 'Thông báo khi User reply ticket cho Admin',
            'variables' => '{username}, {subject}, {message}, {category}, {ip}, {time}, {device}',
            'prompt' => 'Tạo nội dung thông báo Telegram cho Admin khi User reply ticket của họ. Tin nhắn phải vào đúng trọng tâm và message phải giống như User đang trả lời họ trên Telegram.'
        ],
        'noti_new_review' => [
            'title' => 'Thông báo đánh giá sản phẩm mới cho Admin',
            'variables' => '{domain}, {username}, {product_name}, {rating}, {stars}, {title}, {content}, {time}',
            'prompt' => 'Tạo nội dung thông báo Telegram cho Admin khi có đánh giá sản phẩm mới cần duyệt. Thông báo cần thể hiện tên sản phẩm, người đánh giá, số sao và nội dung đánh giá. Sử dụng emoji phù hợp như ⭐ cho rating, 📝 cho đánh giá.'
        ]
    ];

    if (!isset($notifications[$type])) {
        $data = json_encode([
            'success' => false,
            'message' => __('Loại thông báo không hợp lệ')
        ]);
        die($data);
    }

    $config = $notifications[$type];


    // Tạo prompt cho AI
    $prompt = $config['prompt'] . "

Yêu cầu:
1. Sử dụng các biến có sẵn: " . $config['variables'] . "
2. Nội dung phải bằng tiếng Việt
3. Sử dụng emoji phù hợp để tạo điểm nhấn
4. Định dạng rõ ràng, dễ đọc
5. Độ dài phù hợp cho thông báo Telegram
6. Thể hiện tính chuyên nghiệp của hệ thống
7. Định dạng phải là Markdown phù hợp với quy định của Telegram

CHỈ TRA VỀ NỘI DUNG THÔNG BÁO THUẦN TÚY, KHÔNG GIẢI THÍCH.";

    // Gọi hàm tạo nội dung AI
    $result = generateAIContent($prompt);
    $response = json_decode($result, true);

    if ($response['success']) {
        // Loại bỏ markdown code blocks
        $content = cleanAIContent($response['description']);

        $data = json_encode([
            'success' => true,
            'content' => $content
        ]);
    } else {
        $data = json_encode([
            'success' => false,
            'message' => $response['message']
        ]);
    }

    die($data);
}

// Xử lý tạo nội dung thông báo Email
if ($_POST['action'] == 'generateEmailNotification') {
    if (!isset($_POST['type']) || empty(trim($_POST['type']))) {
        $data = json_encode([
            'success' => false,
            'message' => __('Vui lòng chọn loại thông báo')
        ]);
        die($data);
    }

    $type = trim(check_string($_POST['type']));

    // Định nghĩa các loại thông báo email và prompt tương ứng
    $notifications = [
        'email_temp_content_order_success' => [
            'title' => 'Thông báo mua hàng thành công',
            'variables' => '{domain}, {title}, {username}, {email}, {order_count}, {order_details}, {total_amount}, {discount_amount}, {coupon_code}, {summary}, {order_link}, {ip}, {device}, {time}',
            'prompt' => 'Tạo nội dung body email thông báo mua hàng thành công cho khách hàng. KHÔNG BAO GỒM TIÊU ĐỀ, chỉ tạo nội dung email. Email cần thể hiện sự chuyên nghiệp, cảm ơn khách hàng đã mua hàng, hiển thị chi tiết đơn hàng và hướng dẫn xem đơn hàng. Sử dụng {order_details} hoặc {summary} để hiển thị thông tin đơn hàng dạng bảng HTML.'
        ],
        'email_temp_content_warning_ticket' => [
            'title' => 'Thông báo Admin khi User tạo ticket',
            'variables' => '{domain}, {title}, {username}, {ip}, {device}, {time}, {subject}, {category}, {order_id}, {content}',
            'prompt' => 'Tạo nội dung body email chuyên nghiệp để thông báo cho Admin khi có ticket mới được tạo. KHÔNG BAO GỒM TIÊU ĐỀ, chỉ tạo nội dung email. Email cần thể hiện tính cấp bách và đầy đủ thông tin về ticket.'
        ],
        'email_temp_content_reply_ticket' => [
            'title' => 'Thông báo Admin khi User trả lời ticket',
            'variables' => '{domain}, {title}, {username}, {ip}, {device}, {time}, {subject}, {category}, {order_id}, {content}',
            'prompt' => 'Tạo nội dung body email thông báo cho Admin khi có phản hồi mới từ User trong ticket. KHÔNG BAO GỒM TIÊU ĐỀ, chỉ tạo nội dung email. Email cần ngắn gọn nhưng đầy đủ thông tin cần thiết.'
        ],
        'email_temp_content_warning_login' => [
            'title' => 'Thông báo đăng nhập',
            'variables' => '{domain}, {title}, {username}, {ip}, {device}, {time}',
            'prompt' => 'Tạo nội dung body email thông báo bảo mật khi có đăng nhập mới. KHÔNG BAO GỒM TIÊU ĐỀ, chỉ tạo nội dung email. Email cần tạo cảm giác an toàn và hướng dẫn người dùng kiểm tra tài khoản.'
        ],
        'email_temp_content_otp_mail' => [
            'title' => 'Gửi OTP xác minh đăng nhập',
            'variables' => '{domain}, {title}, {username}, {otp}, {ip}, {device}, {time}',
            'prompt' => 'Tạo nội dung body email gửi mã OTP xác minh đăng nhập. KHÔNG BAO GỒM TIÊU ĐỀ, chỉ tạo nội dung email. Email cần rõ ràng về cách sử dụng OTP và thời gian hiệu lực.'
        ],
        'email_temp_content_forgot_password' => [
            'title' => 'Khôi phục mật khẩu',
            'variables' => '{domain}, {title}, {username}, {link}, {ip}, {device}, {time}',
            'prompt' => 'Tạo nội dung body email khôi phục mật khẩu. KHÔNG BAO GỒM TIÊU ĐỀ, chỉ tạo nội dung email. Email cần hướng dẫn rõ ràng cách thức đặt lại mật khẩu và lưu ý về bảo mật.'
        ],
        'email_temp_content_order_expiry' => [
            'title' => 'Thông báo đơn hàng hết hạn',
            'variables' => '{domain}, {title}, {username}, {product_name}, {plan_name}, {trans_id}, {expiry_date}, {days_remaining}, {expiry_message}, {time}',
            'prompt' => 'Tạo nội dung body email thông báo đơn hàng sắp hết hạn hoặc đã hết hạn. KHÔNG BAO GỒM TIÊU ĐỀ, chỉ tạo nội dung email. Email cần thể hiện thông tin đơn hàng, ngày hết hạn, và nhắc nhở khách hàng gia hạn. Sử dụng biến {expiry_message} để hiển thị thông báo tự động (sắp hết hạn/đã hết hạn). Tone văn phong thân thiện nhưng chuyên nghiệp.'
        ],
        'email_temp_content_flash_sale_favorite' => [
            'title' => 'Flash Sale - Sản phẩm yêu thích',
            'variables' => '{domain}, {title}, {username}, {flash_sale_name}, {product_name}, {discount_info}, {start_time}, {end_time}, {product_link}, {time}',
            'prompt' => 'Tạo nội dung body email thông báo Flash Sale cho sản phẩm mà user đã yêu thích. KHÔNG BAO GỒM TIÊU ĐỀ, chỉ tạo nội dung email. Email cần tạo cảm giác khẩn cấp và hấp dẫn, thể hiện rõ thông tin khuyến mãi, tên sản phẩm, mức giảm giá và thời gian diễn ra. Sử dụng emoji phù hợp như 🔥⚡💥 để tạo điểm nhấn. Tone văn phong năng động, kích thích mua hàng.'
        ],
        'email_temp_content_order_completed' => [
            'title' => 'Thông báo đơn hàng hoàn thành',
            'variables' => '{domain}, {title}, {username}, {email}, {trans_id}, {product_name}, {plan_name}, {quantity}, {total_amount}, {delivery_content}, {order_link}, {time}',
            'prompt' => 'Tạo nội dung body email thông báo đơn hàng đã hoàn thành cho khách hàng. KHÔNG BAO GỒM TIÊU ĐỀ, chỉ tạo nội dung email. Email cần chuyên nghiệp, thông báo đơn hàng đã được xử lý thành công, hiển thị thông tin đơn hàng (mã đơn, sản phẩm, gói, số lượng, tổng tiền), hiển thị nội dung giao hàng/tài khoản {delivery_content} trong khung đẹp mắt, và hướng dẫn khách hàng xem chi tiết đơn hàng. Sử dụng emoji phù hợp như ✅🎉📦.'
        ],
        'email_temp_content_ticket_created_user' => [
            'title' => 'Thông báo tạo ticket cho User',
            'variables' => '{domain}, {title}, {username}, {ticket_id}, {subject}, {category}, {order_id}, {content}, {time}, {ip}, {device}',
            'prompt' => 'Tạo nội dung body email thông báo xác nhận tạo ticket thành công cho User. KHÔNG BAO GỒM TIÊU ĐỀ, chỉ tạo nội dung email. Email cần thân thiện, xác nhận ticket đã được tiếp nhận, hiển thị mã ticket, tiêu đề và danh mục. Thông báo thời gian phản hồi dự kiến và cảm ơn khách hàng đã liên hệ.'
        ]
    ];

    if (!isset($notifications[$type])) {
        $data = json_encode([
            'success' => false,
            'message' => __('Loại thông báo không hợp lệ')
        ]);
        die($data);
    }

    $config = $notifications[$type];

    // Lấy thông tin website
    $site_info = "
    
Thông tin website để tham khảo:
- Hotline: " . $CMSNT->site('hotline') . "
- Email: " . $CMSNT->site('email') . "
- Địa chỉ: " . $CMSNT->site('address') . "
- Fanpage: " . $CMSNT->site('fanpage') . "
    
Có thể sử dụng các thông tin này khi phù hợp.";

    // Tạo prompt cho AI
    $prompt = $config['prompt'] . $site_info . "

Yêu cầu:
1. Sử dụng các biến có sẵn: " . $config['variables'] . "
2. Nội dung phải bằng tiếng Việt
3. Định dạng HTML đẹp mắt theo phong cách Material Design, chuyên nghiệp
4. BẮT BUỘC: Sử dụng INLINE CSS (style attribute trực tiếp trên tag), KHÔNG dùng thẻ <style> vì email clients sẽ loại bỏ
5. Ví dụ đúng: <p style=\"color: #333; font-size: 16px;\">Nội dung</p>
6. Sử dụng cấu trúc email phù hợp với từng loại thông báo
7. Tone văn phong phù hợp: trang trọng cho thông báo bảo mật, thân thiện cho OTP
8. Bao gồm lời chào và lời kết thích hợp
9. TUYỆT ĐỐI KHÔNG BAO GỒM TIÊU ĐỀ HAY HEADING, CHỈ TẠO NỘI DUNG BODY

CHỈ TRA VỀ NỘI DUNG EMAIL THUẦN TÚY, KHÔNG TIÊU ĐỀ, KHÔNG GIẢI THÍCH, KHÔNG SỬ DỤNG MARKDOWN CODE BLOCKS.";

    // Gọi hàm tạo nội dung AI
    $result = generateAIContent($prompt);
    $response = json_decode($result, true);

    if ($response['success']) {
        // Loại bỏ markdown code blocks
        $content = cleanAIContent($response['description']);

        $data = json_encode([
            'success' => true,
            'content' => $content
        ]);
    } else {
        $data = json_encode([
            'success' => false,
            'message' => $response['message']
        ]);
    }

    die($data);
}

// Xử lý tạo nội dung cho System Pages
if ($_POST['action'] == 'generateSystemPageContent') {
    if (!isset($_POST['type']) || empty(trim($_POST['type']))) {
        $data = json_encode([
            'success' => false,
            'message' => __('Vui lòng chọn loại trang')
        ]);
        die($data);
    }

    $type = trim(check_string($_POST['type']));

    // Định nghĩa các loại trang hệ thống và prompt tương ứng
    $pages = [
        'page_contact' => [
            'title' => 'Nội dung trang liên hệ',
            'prompt' => 'Tạo nội dung trang liên hệ chuyên nghiệp cho website dịch vụ SHOPKEY. Nội dung cần bao gồm thông tin liên hệ, phương thức hỗ trợ khách hàng, giờ làm việc và lời nhắn thân thiện. Sử dụng HTML để định dạng đẹp mắt.'
        ],
        'page_policy' => [
            'title' => 'Nội dung trang chính sách',
            'prompt' => 'Tạo nội dung trang chính sách dịch vụ cho website SHOPKEY. Bao gồm các điều khoản sử dụng, quyền và nghĩa vụ của khách hàng, chính sách hoàn tiền, bảo mật thông tin. Nội dung phải rõ ràng, dễ hiểu và tuân thủ pháp luật.'
        ],
        'page_privacy' => [
            'title' => 'Nội dung trang quyền riêng tư',
            'prompt' => 'Tạo nội dung trang chính sách quyền riêng tư cho website SHOPKEY. Giải thích cách thu thập, sử dụng và bảo vệ thông tin cá nhân của khách hàng. Bao gồm cookie policy, chia sẻ thông tin với bên thứ ba và quyền của người dùng.'
        ],
        'page_faq' => [
            'title' => 'Nội dung trang FAQ',
            'prompt' => 'Tạo nội dung trang câu hỏi thường gặp (FAQ) cho website SHOPKEY. Bao gồm các câu hỏi về cách sử dụng dịch vụ, thanh toán, thời gian xử lý đơn hàng, chính sách hỗ trợ và những vấn đề khách hàng quan tâm nhất.'
        ],
        'policy_register' => [
            'title' => 'Nội dung chính sách đăng ký',
            'prompt' => 'Tạo nội dung chính sách đăng ký tài khoản cho website SHOPKEY. Bao gồm các điều khoản và điều kiện khi đăng ký, quyền và trách nhiệm của người dùng, cam kết bảo mật thông tin cá nhân, quy định về độ tuổi sử dụng dịch vụ, và các lưu ý quan trọng khi tạo tài khoản. Nội dung cần ngắn gọn, rõ ràng để người dùng dễ hiểu trước khi đồng ý đăng ký.'
        ]
    ];

    if (!isset($pages[$type])) {
        $data = json_encode([
            'success' => false,
            'message' => __('Loại trang không hợp lệ')
        ]);
        die($data);
    }

    $config = $pages[$type];

    // Lấy thông tin website
    $site_info = "
    
Thông tin website cần sử dụng:
- Hotline: " . $CMSNT->site('hotline') . "
- Email: " . $CMSNT->site('email') . "
- Địa chỉ: " . $CMSNT->site('address') . "
- Fanpage: " . $CMSNT->site('fanpage') . "
    
Hãy sử dụng các thông tin này trong nội dung khi cần thiết.";

    // Tạo prompt cho AI
    $prompt = $config['prompt'] . $site_info . "

Yêu cầu:
1. Nội dung phải bằng tiếng Việt
2. Sử dụng HTML để định dạng đẹp mắt với các thẻ như <h2>, <h3>, <p>, <ul>, <li>
3. Nội dung chuyên nghiệp, phù hợp với dịch vụ SHOPKEY
4. Cấu trúc rõ ràng, dễ đọc và hiểu
5. Độ dài phù hợp (khoảng 300-500 từ)
6. Tone văn phong thân thiện nhưng chuyên nghiệp
7. KHÔNG BAO GỒM TIÊU ĐỀ CHÍNH, chỉ tạo nội dung body

CHỈ TRA VỀ NỘI DUNG TRANG THUẦN TÚY, KHÔNG TIÊU ĐỀ, KHÔNG GIẢI THÍCH, KHÔNG SỬ DỤNG MARKDOWN CODE BLOCKS.";

    // Gọi hàm tạo nội dung AI
    $result = generateAIContent($prompt);
    $response = json_decode($result, true);

    if ($response['success']) {
        // Loại bỏ markdown code blocks
        $content = cleanAIContent($response['description']);

        $data = json_encode([
            'success' => true,
            'content' => $content
        ]);
    } else {
        $data = json_encode([
            'success' => false,
            'message' => $response['message']
        ]);
    }

    die($data);
}

// Xử lý tạo Script/HTML Footer
if ($_POST['action'] == 'generateFooterScript') {
    if (!isset($_POST['description']) || empty(trim($_POST['description']))) {
        $data = json_encode([
            'success' => false,
            'message' => __('Vui lòng nhập mô tả về script cần tạo')
        ]);
        die($data);
    }

    $description = trim($_POST['description']);

    // Lấy thông tin website
    $site_info = "
    
Thông tin website để tham khảo:
- Hotline: " . $CMSNT->site('hotline') . "
- Email: " . $CMSNT->site('email') . "
- Địa chỉ: " . $CMSNT->site('address') . "
- Fanpage: " . $CMSNT->site('fanpage') . "
    
Có thể sử dụng các thông tin này khi phù hợp.";

    // Tạo prompt cho AI
    $prompt = "Tạo script/HTML cho yêu cầu: '$description'" . $site_info . "

Yêu cầu:
1. CHỈ TẠO SCRIPT/HTML FRAGMENT, KHÔNG TẠO CẢ TRANG WEB (không có <!DOCTYPE>, <html>, <head>, <body>)
2. Nếu cần CSS, đặt trong thẻ <style> 
3. Nếu cần JavaScript, đặt trong thẻ <script>
4. Code phải tối ưu và không ảnh hưởng đến hiệu suất website
5. Sử dụng jQuery nếu cần (đã có sẵn trên website)
6. Code phải responsive và tương thích đa trình duyệt
7. Bao gồm comment giải thích ngắn gọn
8. Nếu là hiệu ứng, tạo đơn giản và đẹp mắt
9. Code phải an toàn, không chứa mã độc

VÍ DỤ ĐỊNH DẠNG ĐÚNG:
<style>
/* CSS cho hiệu ứng */
</style>

<script>
// JavaScript cho hiệu ứng
</script>

HOẶC CHỈ:
<script>
// JavaScript đơn giản
</script>

Lưu ý: Đây là code sẽ được chèn vào footer trang web, chỉ tạo phần script cần thiết.

CHỈ TRA VỀ SCRIPT/HTML FRAGMENT THUẦN TÚY, KHÔNG GIẢI THÍCH THÊM, KHÔNG SỬ DỤNG MARKDOWN CODE BLOCKS.";

    // Gọi hàm tạo nội dung AI
    $result = generateAIContent($prompt);
    $response = json_decode($result, true);

    if ($response['success']) {
        // Loại bỏ markdown code blocks
        $content = cleanAIContent($response['description']);

        $data = json_encode([
            'success' => true,
            'content' => $content
        ]);
    } else {
        $data = json_encode([
            'success' => false,
            'message' => $response['message']
        ]);
    }

    die($data);
}

// Tạo nội dung cho trang Manual Payment (thanh toán thủ công)
if ($_POST['action'] == 'generateManualPaymentContent') {

    if (!isset($_POST['gateway_title']) || empty(trim($_POST['gateway_title']))) {
        die(json_encode([
            'success' => false,
            'message' => __('Vui lòng nhập tên cổng thanh toán')
        ]));
    }
    // Mô tả tùy chọn từ client, nếu có
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $gatewayTitle = isset($_POST['gateway_title']) ? trim($_POST['gateway_title']) : '';

    // Lấy thông tin website để AI cá nhân hóa nội dung
    $site_info = "
    Thông tin website:
    - Hotline: " . $CMSNT->site('hotline') . "
    - Email: " . $CMSNT->site('email') . "
    - Fanpage: " . $CMSNT->site('fanpage') . "
    ";

    // Prompt hướng dẫn AI sinh HTML hướng dẫn nạp thủ công
    $prompt = "Hãy tạo nội dung HTML chi tiết cho trang hướng dẫn nạp tiền thủ công (Manual Payment) cho website SHOPKEY.\n" .
        ($gatewayTitle !== '' ? ("Tên cổng thanh toán: " . $gatewayTitle . "\n") : "") .
        ($description !== '' ? ("Mô tả bổ sung của admin: " . $description . "\n") : "") .
        $site_info . "\n\n" .
        "Các biến placeholder và ý nghĩa (PHẢI giữ nguyên dấu ngoặc nhọn, KHÔNG được đổi thành HTML entities):\n" .
        "- {username} => Username của khách hàng.\n" .
        "- {id} => ID của khách hàng.\n" .
        "- {hotline} => Hotline đã nhập trong cài đặt.\n" .
        "- {email} => Email đã nhập trong cài đặt.\n" .
        "- {fanpage} => Fanpage đã nhập trong cài đặt.\n\n" .
        "Yêu cầu:\n" .
        "1) Nội dung bằng tiếng Việt, rõ ràng, bố cục đẹp, dễ đọc.\n" .
        "2) Sử dụng CỤ THỂ các placeholder: {username}, {id}, {hotline}, {email}, {fanpage} trong nội dung.\n" .
        "   - BẮT BUỘC chèn {username} và {id} trong ví dụ 'Nội dung chuyển khoản'.\n" .
        "   - KHÔNG bao giờ thay đổi format {placeholder} thành &lbrace;...&rbrace; hay \u007B...\u007D.\n" .
        "3) Cấu trúc gợi ý: phần giới thiệu ngắn (nêu rõ đây là cổng '" . ($gatewayTitle !== '' ? $gatewayTitle : 'Manual Payment') . "'), danh sách các bước nạp tiền (ol/li), khối cảnh báo/lưu ý, ví dụ nội dung chuyển khoản, phần hỗ trợ liên hệ.\n" .
        "4) Dùng HTML thuần, có thể dùng class Bootstrap phổ biến như alert, badge, list-group để trình bày.\n" .
        "5) Không tạo toàn bộ trang, chỉ body fragment cần chèn vào editor.\n" .
        "6) KHÔNG dùng markdown, KHÔNG thêm <!DOCTYPE>, <html>, <head>, <body>.\n" .
        "7) Ở cuối nội dung, thêm tiêu đề 'Hỗ Trợ Liên Hệ' và danh sách liệt kê lại đúng 5 placeholder như trên (hiển thị nguyên văn {username}, {id}, {hotline}, {email}, {fanpage}).\n\n" .
        "CHỈ TRẢ VỀ HTML THUẦN TÚY, KHÔNG GIẢI THÍCH, KHÔNG SỬ DỤNG MARKDOWN CODE BLOCKS.";

    // Gọi AI
    $result = generateAIContent($prompt);
    $response = json_decode($result, true);

    if ($response && isset($response['success']) && $response['success']) {
        $content = cleanAIContent($response['description']);
        $data = json_encode([
            'success' => true,
            'content' => $content
        ]);
    } else {
        $data = json_encode([
            'success' => false,
            'message' => isset($response['message']) ? $response['message'] : __('Không thể tạo nội dung, vui lòng thử lại')
        ]);
    }

    die($data);
}

// Lấy template email mặc định
if ($_POST['action'] == 'getDefaultEmailTemplate') {
    if (!isset($_POST['type']) || empty(trim($_POST['type']))) {
        die(json_encode(['success' => false, 'message' => __('Loại template không hợp lệ')]));
    }

    $type = trim(check_string($_POST['type']));
    $siteName = $CMSNT->site('title') ?: 'Website';
    $themeColor = $CMSNT->site('theme_color') ?: '#667eea';
    $themeColor1 = $CMSNT->site('theme_color1') ?: '#764ba2';

    // Base template wrapper
    $wrapTemplate = function ($headerTitle, $headerIcon, $bodyContent, $footerText = '') use ($siteName, $themeColor, $themeColor1) {
        $year = date('Y');
        return '
<div style="font-family: Arial, Helvetica, sans-serif; max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
    <!-- Header -->
    <div style="background: linear-gradient(135deg, ' . $themeColor . ' 0%, ' . $themeColor1 . ' 100%); padding: 30px; text-align: center;">
        <div style="font-size: 40px; margin-bottom: 10px;">' . $headerIcon . '</div>
        <h2 style="color: #ffffff; font-size: 22px; font-weight: 600; margin: 0;">' . $headerTitle . '</h2>
    </div>
    
    <!-- Body -->
    <div style="padding: 30px;">
        ' . $bodyContent . '
    </div>
    
    <!-- Footer -->
    <div style="background: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #eee;">
        ' . ($footerText ? '<p style="color: #666; font-size: 13px; margin: 0 0 10px 0;">' . $footerText . '</p>' : '') . '
        <p style="color: #888; font-size: 12px; margin: 0;">© ' . $year . ' ' . $siteName . '. All rights reserved.</p>
    </div>
</div>';
    };

    // Default templates
    $templates = [
        'email_temp_content_order_success' => $wrapTemplate(
            'Đơn hàng thành công!',
            '🎉',
            '
            <p style="color: #333; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                Xin chào <strong>{username}</strong>,
            </p>
            <p style="color: #555; font-size: 15px; line-height: 1.6; margin: 0 0 20px 0;">
                Cảm ơn bạn đã mua hàng! Đơn hàng của bạn đã được xử lý thành công.
            </p>
            
            <!-- Order Info Card -->
            <div style="background: linear-gradient(135deg, ' . $themeColor . '15 0%, ' . $themeColor1 . '15 100%); border-radius: 8px; padding: 20px; margin: 20px 0; border-left: 4px solid ' . $themeColor . ';">
                <p style="color: #333; font-size: 14px; margin: 0 0 10px 0;"><strong>📦 Số lượng đơn:</strong> {order_count}</p>
                <p style="color: #333; font-size: 14px; margin: 0;"><strong>💰 Tổng thanh toán:</strong> <span style="color: #e53935; font-weight: 600;">{total_amount}</span></p>
            </div>
            
            <!-- Order Details -->
            <div style="margin: 20px 0;">
                {order_details}
            </div>
            
            <!-- CTA Button -->
            <div style="text-align: center; margin: 30px 0;">
                <a href="{order_link}" style="display: inline-block; padding: 14px 32px; background: linear-gradient(135deg, ' . $themeColor . ' 0%, ' . $themeColor1 . ' 100%); color: #ffffff; text-decoration: none; border-radius: 25px; font-weight: 600; font-size: 14px;">Xem đơn hàng</a>
            </div>
            
            <p style="color: #888; font-size: 13px; margin: 20px 0 0 0; padding-top: 20px; border-top: 1px solid #eee;">
                <strong>Thời gian:</strong> {time}<br>
                <strong>IP:</strong> {ip}
            </p>
            ',
            'Cảm ơn bạn đã tin tưởng sử dụng dịch vụ của chúng tôi!'
        ),

        'email_temp_content_warning_login' => $wrapTemplate(
            'Đăng nhập mới được phát hiện',
            '🔐',
            '
            <p style="color: #333; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                Xin chào <strong>{username}</strong>,
            </p>
            <p style="color: #555; font-size: 15px; line-height: 1.6; margin: 0 0 20px 0;">
                Chúng tôi phát hiện một đăng nhập mới vào tài khoản của bạn.
            </p>
            
            <!-- Login Info -->
            <div style="background: #fff3cd; border-radius: 8px; padding: 20px; margin: 20px 0; border-left: 4px solid #ffc107;">
                <p style="color: #856404; font-size: 14px; margin: 0 0 10px 0;"><strong>🕐 Thời gian:</strong> {time}</p>
                <p style="color: #856404; font-size: 14px; margin: 0 0 10px 0;"><strong>🌐 Địa chỉ IP:</strong> {ip}</p>
                <p style="color: #856404; font-size: 14px; margin: 0;"><strong>📱 Thiết bị:</strong> {device}</p>
            </div>
            
            <p style="color: #555; font-size: 15px; line-height: 1.6; margin: 20px 0;">
                Nếu đây là bạn, bạn có thể bỏ qua email này. Nếu không phải, hãy đổi mật khẩu ngay lập tức để bảo vệ tài khoản.
            </p>
            ',
            'Email này được gửi tự động để bảo vệ tài khoản của bạn.'
        ),

        'email_temp_content_otp_mail' => $wrapTemplate(
            'Mã xác thực OTP',
            '🔑',
            '
            <p style="color: #333; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                Xin chào <strong>{username}</strong>,
            </p>
            <p style="color: #555; font-size: 15px; line-height: 1.6; margin: 0 0 20px 0;">
                Đây là mã OTP để xác thực đăng nhập của bạn:
            </p>
            
            <!-- OTP Code -->
            <div style="text-align: center; margin: 30px 0;">
                <div style="display: inline-block; background: linear-gradient(135deg, ' . $themeColor . ' 0%, ' . $themeColor1 . ' 100%); padding: 20px 40px; border-radius: 10px;">
                    <span style="font-size: 32px; font-weight: 700; color: #ffffff; letter-spacing: 8px;">{otp}</span>
                </div>
            </div>
            
            <div style="background: #e8f4fd; border-radius: 8px; padding: 15px; margin: 20px 0; text-align: center;">
                <p style="color: #1565c0; font-size: 14px; margin: 0;">⏱️ Mã có hiệu lực trong <strong>5 phút</strong></p>
            </div>
            
            <p style="color: #888; font-size: 13px; margin: 20px 0 0 0; padding-top: 20px; border-top: 1px solid #eee;">
                <strong>IP:</strong> {ip} • <strong>Thiết bị:</strong> {device}
            </p>
            ',
            'Nếu bạn không yêu cầu mã này, vui lòng bỏ qua email.'
        ),

        'email_temp_content_forgot_password' => $wrapTemplate(
            'Khôi phục mật khẩu',
            '🔓',
            '
            <p style="color: #333; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                Xin chào <strong>{username}</strong>,
            </p>
            <p style="color: #555; font-size: 15px; line-height: 1.6; margin: 0 0 20px 0;">
                Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn. Click vào nút bên dưới để tiếp tục:
            </p>
            
            <!-- CTA Button -->
            <div style="text-align: center; margin: 30px 0;">
                <a href="{link}" style="display: inline-block; padding: 16px 40px; background: linear-gradient(135deg, ' . $themeColor . ' 0%, ' . $themeColor1 . ' 100%); color: #ffffff; text-decoration: none; border-radius: 25px; font-weight: 600; font-size: 15px;">Đặt lại mật khẩu</a>
            </div>
            
            <div style="background: #fff3e0; border-radius: 8px; padding: 15px; margin: 20px 0;">
                <p style="color: #e65100; font-size: 13px; margin: 0;">⚠️ Link sẽ hết hạn sau <strong>30 phút</strong>. Nếu bạn không yêu cầu, hãy bỏ qua email này.</p>
            </div>
            
            <p style="color: #888; font-size: 13px; margin: 20px 0 0 0; padding-top: 20px; border-top: 1px solid #eee;">
                <strong>Thời gian:</strong> {time}<br>
                <strong>IP:</strong> {ip} • <strong>Thiết bị:</strong> {device}
            </p>
            ',
            ''
        ),

        'email_temp_content_order_expiry' => $wrapTemplate(
            '{expiry_message}',
            '⏰',
            '
            <p style="color: #333; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                Xin chào <strong>{username}</strong>,
            </p>
            
            <!-- Product Info -->
            <div style="background: #fce4ec; border-radius: 8px; padding: 20px; margin: 20px 0; border-left: 4px solid #e91e63;">
                <p style="color: #c2185b; font-size: 15px; margin: 0 0 10px 0;"><strong>📦 Sản phẩm:</strong> {product_name}</p>
                <p style="color: #c2185b; font-size: 14px; margin: 0 0 10px 0;"><strong>📋 Gói:</strong> {plan_name}</p>
                <p style="color: #c2185b; font-size: 14px; margin: 0 0 10px 0;"><strong>🔖 Mã đơn:</strong> {trans_id}</p>
                <p style="color: #c2185b; font-size: 14px; margin: 0;"><strong>📅 Ngày hết hạn:</strong> {expiry_date}</p>
            </div>
            
            <p style="color: #555; font-size: 15px; line-height: 1.6; margin: 20px 0;">
                Để tiếp tục sử dụng dịch vụ mà không bị gián đoạn, vui lòng gia hạn đơn hàng của bạn.
            </p>
            
            <!-- CTA Button -->
            <div style="text-align: center; margin: 30px 0;">
                <a href="{domain}" style="display: inline-block; padding: 14px 32px; background: linear-gradient(135deg, #e91e63 0%, #9c27b0 100%); color: #ffffff; text-decoration: none; border-radius: 25px; font-weight: 600; font-size: 14px;">Gia hạn ngay</a>
            </div>
            ',
            'Cảm ơn bạn đã sử dụng dịch vụ của chúng tôi!'
        ),

        'email_temp_content_flash_sale_favorite' => $wrapTemplate(
            '🔥 Flash Sale - Sản phẩm yêu thích!',
            '⚡',
            '
            <p style="color: #333; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                Xin chào <strong>{username}</strong>,
            </p>
            <p style="color: #555; font-size: 15px; line-height: 1.6; margin: 0 0 20px 0;">
                Tin vui! Sản phẩm bạn yêu thích đang có <strong style="color: #e53935;">FLASH SALE</strong>! 🎉
            </p>
            
            <!-- Flash Sale Info -->
            <div style="background: linear-gradient(135deg, #ff5722 0%, #e91e63 100%); border-radius: 10px; padding: 25px; margin: 20px 0; text-align: center;">
                <p style="color: #fff; font-size: 18px; font-weight: 600; margin: 0 0 10px 0;">{flash_sale_name}</p>
                <p style="color: #fff; font-size: 20px; font-weight: 700; margin: 0 0 15px 0;">{product_name}</p>
                <div style="background: rgba(255,255,255,0.2); border-radius: 8px; padding: 15px; display: inline-block;">
                    <span style="color: #fff; font-size: 24px; font-weight: 700;">{discount_info}</span>
                </div>
            </div>
            
            <!-- Time Info -->
            <div style="background: #fff8e1; border-radius: 8px; padding: 15px; margin: 20px 0; text-align: center;">
                <p style="color: #f57c00; font-size: 14px; margin: 0;">
                    ⏰ <strong>Bắt đầu:</strong> {start_time} | <strong>Kết thúc:</strong> {end_time}
                </p>
            </div>
            
            <!-- CTA Button -->
            <div style="text-align: center; margin: 30px 0;">
                <a href="{product_link}" style="display: inline-block; padding: 16px 40px; background: linear-gradient(135deg, #ff5722 0%, #e91e63 100%); color: #ffffff; text-decoration: none; border-radius: 25px; font-weight: 700; font-size: 16px; text-transform: uppercase;">Mua ngay 🛒</a>
            </div>
            ',
            'Số lượng có hạn, nhanh tay kẻo lỡ!'
        ),

        'email_temp_content_order_completed' => $wrapTemplate(
            '✅ Đơn hàng đã hoàn thành!',
            '🎉',
            '
            <p style="color: #333; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                Xin chào <strong>{username}</strong>,
            </p>
            <p style="color: #555; font-size: 15px; line-height: 1.6; margin: 0 0 20px 0;">
                Đơn hàng <strong>#{trans_id}</strong> của bạn đã được xử lý thành công! 🎉
            </p>
            
            <!-- Order Info Card -->
            <div style="background: linear-gradient(135deg, ' . $themeColor . '15 0%, ' . $themeColor1 . '15 100%); border-radius: 8px; padding: 20px; margin: 20px 0; border-left: 4px solid ' . $themeColor . ';">
                <p style="color: #333; font-size: 14px; margin: 0 0 10px 0;"><strong>📦 Sản phẩm:</strong> {product_name}</p>
                <p style="color: #333; font-size: 14px; margin: 0 0 10px 0;"><strong>📋 Gói:</strong> {plan_name}</p>
                <p style="color: #333; font-size: 14px; margin: 0 0 10px 0;"><strong>🔢 Số lượng:</strong> {quantity}</p>
                <p style="color: #333; font-size: 14px; margin: 0;"><strong>💰 Tổng tiền:</strong> <span style="color: #e53935; font-weight: 600;">{total_amount}</span></p>
            </div>
            
            <!-- Delivery Content -->
            <div style="background: #e8f5e9; border-radius: 8px; padding: 20px; margin: 20px 0; border-left: 4px solid #4caf50;">
                <p style="color: #2e7d32; font-size: 14px; font-weight: 600; margin: 0 0 10px 0;">📄 Thông tin tài khoản:</p>
                <div style="background: #fff; border-radius: 6px; padding: 15px; font-family: monospace; font-size: 13px; color: #333; white-space: pre-wrap; word-break: break-all;">{delivery_content}</div>
            </div>
            
            <!-- CTA Button -->
            <div style="text-align: center; margin: 30px 0;">
                <a href="{order_link}" style="display: inline-block; padding: 14px 32px; background: linear-gradient(135deg, ' . $themeColor . ' 0%, ' . $themeColor1 . ' 100%); color: #ffffff; text-decoration: none; border-radius: 25px; font-weight: 600; font-size: 14px;">Xem chi tiết đơn hàng</a>
            </div>
            
            <p style="color: #888; font-size: 13px; margin: 20px 0 0 0; padding-top: 20px; border-top: 1px solid #eee;">
                <strong>Thời gian:</strong> {time}
            </p>
            ',
            'Cảm ơn bạn đã sử dụng dịch vụ của chúng tôi!'
        ),

        'email_temp_content_warning_ticket' => $wrapTemplate(
            'Ticket mới từ khách hàng',
            '🎫',
            '
            <p style="color: #333; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                <strong>Admin</strong>, có ticket mới cần xử lý!
            </p>
            
            <!-- Ticket Info -->
            <div style="background: #e3f2fd; border-radius: 8px; padding: 20px; margin: 20px 0; border-left: 4px solid #2196f3;">
                <p style="color: #1565c0; font-size: 14px; margin: 0 0 10px 0;"><strong>👤 Khách hàng:</strong> {username}</p>
                <p style="color: #1565c0; font-size: 14px; margin: 0 0 10px 0;"><strong>📋 Tiêu đề:</strong> {subject}</p>
                <p style="color: #1565c0; font-size: 14px; margin: 0 0 10px 0;"><strong>📁 Danh mục:</strong> {category}</p>
                <p style="color: #1565c0; font-size: 14px; margin: 0;"><strong>🔖 Mã đơn hàng:</strong> {order_id}</p>
            </div>
            
            <div style="background: #f5f5f5; border-radius: 8px; padding: 15px; margin: 20px 0;">
                <p style="color: #333; font-size: 14px; margin: 0 0 10px 0;"><strong>Nội dung:</strong></p>
                <p style="color: #555; font-size: 14px; margin: 0; white-space: pre-wrap;">{content}</p>
            </div>
            
            <p style="color: #888; font-size: 13px; margin: 20px 0 0 0;">
                <strong>Thời gian:</strong> {time} • <strong>IP:</strong> {ip}
            </p>
            ',
            ''
        ),

        'email_temp_content_reply_ticket' => $wrapTemplate(
            'Phản hồi mới trên Ticket',
            '💬',
            '
            <p style="color: #333; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                <strong>Admin</strong>, có phản hồi mới trên ticket!
            </p>
            
            <!-- Ticket Info -->
            <div style="background: #e8f5e9; border-radius: 8px; padding: 20px; margin: 20px 0; border-left: 4px solid #4caf50;">
                <p style="color: #2e7d32; font-size: 14px; margin: 0 0 10px 0;"><strong>👤 Khách hàng:</strong> {username}</p>
                <p style="color: #2e7d32; font-size: 14px; margin: 0;"><strong>📋 Tiêu đề:</strong> {subject}</p>
            </div>
            
            <div style="background: #f5f5f5; border-radius: 8px; padding: 15px; margin: 20px 0;">
                <p style="color: #333; font-size: 14px; margin: 0 0 10px 0;"><strong>Nội dung phản hồi:</strong></p>
                <p style="color: #555; font-size: 14px; margin: 0; white-space: pre-wrap;">{content}</p>
            </div>
            
            <p style="color: #888; font-size: 13px; margin: 20px 0 0 0;">
                <strong>Thời gian:</strong> {time} • <strong>IP:</strong> {ip}
            </p>
            ',
            ''
        ),

        'email_temp_content_ticket_created_user' => $wrapTemplate(
            'Ticket của bạn đã được tiếp nhận',
            '🎫',
            '
            <p style="color: #333; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                Xin chào <strong>{username}</strong>,
            </p>
            <p style="color: #555; font-size: 15px; line-height: 1.6; margin: 0 0 20px 0;">
                Cảm ơn bạn đã liên hệ với chúng tôi. Yêu cầu hỗ trợ của bạn đã được tiếp nhận và đang được xử lý.
            </p>
            
            <!-- Ticket Info Card -->
            <div style="background: linear-gradient(135deg, ' . $themeColor . '15 0%, ' . $themeColor1 . '15 100%); border-radius: 8px; padding: 20px; margin: 20px 0; border-left: 4px solid ' . $themeColor . ';">
                <p style="color: #333; font-size: 14px; margin: 0 0 10px 0;"><strong>🎫 Mã ticket:</strong> #{ticket_id}</p>
                <p style="color: #333; font-size: 14px; margin: 0 0 10px 0;"><strong>📝 Tiêu đề:</strong> {subject}</p>
                <p style="color: #333; font-size: 14px; margin: 0 0 10px 0;"><strong>📁 Danh mục:</strong> {category}</p>
                <p style="color: #333; font-size: 14px; margin: 0;"><strong>📦 Mã đơn hàng:</strong> {order_id}</p>
            </div>
            
            <div style="background: #e8f4fd; border-radius: 8px; padding: 15px; margin: 20px 0; text-align: center;">
                <p style="color: #1565c0; font-size: 14px; margin: 0;">⏰ Chúng tôi sẽ phản hồi trong vòng <strong>24 giờ làm việc</strong></p>
            </div>
            
            <p style="color: #888; font-size: 13px; margin: 20px 0 0 0; padding-top: 20px; border-top: 1px solid #eee;">
                <strong>Thời gian:</strong> {time}
            </p>
            ',
            'Cảm ơn bạn đã tin tưởng sử dụng dịch vụ của chúng tôi!'
        )
    ];

    if (!isset($templates[$type])) {
        die(json_encode(['success' => false, 'message' => __('Template không tồn tại')]));
    }

    die(json_encode([
        'success' => true,
        'content' => $templates[$type]
    ]));
}

// Lấy template email mặc định - Mẫu 2 (Phong cách Clean Tech)
if ($_POST['action'] == 'getDefaultEmailTemplate2') {
    if (!isset($_POST['type']) || empty(trim($_POST['type']))) {
        die(json_encode(['success' => false, 'message' => __('Loại template không hợp lệ')]));
    }

    $type = trim(check_string($_POST['type']));
    $siteName = $CMSNT->site('title') ?: 'Website';
    $themeColor = $CMSNT->site('theme_color') ?: '#667eea';

    // Base template wrapper - Mẫu 2: Clean Professional Tech Style
    $wrapTemplate2 = function ($headerTitle, $headerIcon, $bodyContent, $footerText = '') use ($siteName, $themeColor) {
        $year = date('Y');
        return '
<div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">
    <!-- Header -->
    <div style="background: #f8fafc; padding: 24px 32px; border-bottom: 1px solid #e5e7eb;">
        <table cellpadding="0" cellspacing="0" border="0" width="100%">
            <tr>
                <td style="vertical-align: middle;">
                    <div style="display: inline-block; width: 44px; height: 44px; background: ' . $themeColor . '; border-radius: 10px; text-align: center; line-height: 44px; margin-right: 16px; vertical-align: middle;">
                        <span style="font-size: 20px; color: #fff;">' . $headerIcon . '</span>
                    </div>
                    <span style="color: #1e293b; font-size: 20px; font-weight: 600; vertical-align: middle;">' . $headerTitle . '</span>
                </td>
            </tr>
        </table>
    </div>
    
    <!-- Body -->
    <div style="padding: 32px;">
        ' . $bodyContent . '
    </div>
    
    <!-- Footer -->
    <div style="background: #f8fafc; padding: 20px 32px; border-top: 1px solid #e5e7eb;">
        ' . ($footerText ? '<p style="color: #64748b; font-size: 13px; margin: 0 0 8px 0; text-align: center;">' . $footerText . '</p>' : '') . '
        <p style="color: #94a3b8; font-size: 12px; margin: 0; text-align: center;">© ' . $year . ' ' . $siteName . ' • All rights reserved</p>
    </div>
</div>';
    };

    // Default templates - Mẫu 2: Clean Tech Style
    $templates = [
        'email_temp_content_order_success' => $wrapTemplate2(
            'Đơn hàng thành công',
            '✓',
            '
            <p style="color: #1e293b; font-size: 15px; line-height: 1.7; margin: 0 0 20px 0;">
                Xin chào <strong>{username}</strong>,
            </p>
            <p style="color: #475569; font-size: 14px; line-height: 1.6; margin: 0 0 24px 0;">
                Đơn hàng của bạn đã được xử lý thành công. Dưới đây là thông tin chi tiết:
            </p>
            
            <!-- Order Summary -->
            <div style="background: #f1f5f9; border-radius: 8px; padding: 20px; margin: 0 0 24px 0;">
                <table cellpadding="0" cellspacing="0" border="0" width="100%" style="font-size: 14px;">
                    <tr>
                        <td style="color: #64748b; padding: 8px 0;">Số lượng đơn:</td>
                        <td style="color: #1e293b; font-weight: 600; text-align: right; padding: 8px 0;">{order_count}</td>
                    </tr>
                    <tr>
                        <td style="color: #64748b; padding: 8px 0; border-top: 1px solid #e2e8f0;">Tổng thanh toán:</td>
                        <td style="color: ' . $themeColor . '; font-weight: 700; font-size: 16px; text-align: right; padding: 8px 0; border-top: 1px solid #e2e8f0;">{total_amount}</td>
                    </tr>
                </table>
            </div>
            
            <!-- Order Details -->
            <div style="margin: 0 0 24px 0;">
                {order_details}
            </div>
            
            <!-- CTA Button -->
            <div style="text-align: center; margin: 28px 0;">
                <a href="{order_link}" style="display: inline-block; padding: 12px 28px; background: ' . $themeColor . '; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 14px;">Xem đơn hàng →</a>
            </div>
            
            <div style="border-top: 1px solid #e5e7eb; padding-top: 16px; margin-top: 24px;">
                <p style="color: #94a3b8; font-size: 12px; margin: 0;">
                    Thời gian: {time} • IP: {ip}
                </p>
            </div>
            ',
            'Cảm ơn bạn đã tin tưởng sử dụng dịch vụ!'
        ),

        'email_temp_content_warning_login' => $wrapTemplate2(
            'Thông báo đăng nhập',
            '�',
            '
            <p style="color: #1e293b; font-size: 15px; line-height: 1.7; margin: 0 0 20px 0;">
                Xin chào <strong>{username}</strong>,
            </p>
            <p style="color: #475569; font-size: 14px; line-height: 1.6; margin: 0 0 24px 0;">
                Tài khoản của bạn vừa được đăng nhập từ một thiết bị mới.
            </p>
            
            <!-- Login Info -->
            <div style="background: #fffbeb; border: 1px solid #fcd34d; border-radius: 8px; padding: 20px; margin: 0 0 24px 0;">
                <table cellpadding="0" cellspacing="0" border="0" width="100%" style="font-size: 14px;">
                    <tr>
                        <td style="color: #92400e; padding: 6px 0; width: 100px;">Thời gian:</td>
                        <td style="color: #78350f; font-weight: 500;">{time}</td>
                    </tr>
                    <tr>
                        <td style="color: #92400e; padding: 6px 0;">Địa chỉ IP:</td>
                        <td style="color: #78350f; font-weight: 500; font-family: monospace;">{ip}</td>
                    </tr>
                    <tr>
                        <td style="color: #92400e; padding: 6px 0;">Thiết bị:</td>
                        <td style="color: #78350f; font-weight: 500;">{device}</td>
                    </tr>
                </table>
            </div>
            
            <p style="color: #475569; font-size: 14px; line-height: 1.6; margin: 0;">
                Nếu đây là bạn, bạn có thể bỏ qua email này. Nếu không phải, vui lòng đổi mật khẩu ngay.
            </p>
            ',
            ''
        ),

        'email_temp_content_otp_mail' => $wrapTemplate2(
            'Mã xác thực OTP',
            '�',
            '
            <p style="color: #1e293b; font-size: 15px; line-height: 1.7; margin: 0 0 20px 0;">
                Xin chào <strong>{username}</strong>,
            </p>
            <p style="color: #475569; font-size: 14px; line-height: 1.6; margin: 0 0 24px 0;">
                Sử dụng mã OTP bên dưới để hoàn tất xác thực đăng nhập:
            </p>
            
            <!-- OTP Code -->
            <div style="text-align: center; margin: 32px 0;">
                <div style="display: inline-block; background: #f1f5f9; border: 2px dashed ' . $themeColor . '; padding: 20px 40px; border-radius: 8px;">
                    <span style="font-size: 32px; font-weight: 700; color: ' . $themeColor . '; letter-spacing: 8px; font-family: \'Courier New\', monospace;">{otp}</span>
                </div>
            </div>
            
            <div style="background: #ecfdf5; border: 1px solid #6ee7b7; border-radius: 6px; padding: 12px 16px; margin: 0 0 24px 0; text-align: center;">
                <p style="color: #047857; font-size: 13px; margin: 0;">⏱ Mã có hiệu lực trong <strong>5 phút</strong></p>
            </div>
            
            <div style="border-top: 1px solid #e5e7eb; padding-top: 16px;">
                <p style="color: #94a3b8; font-size: 12px; margin: 0; text-align: center;">
                    IP: {ip} • Thiết bị: {device}
                </p>
            </div>
            ',
            'Không chia sẻ mã này với bất kỳ ai.'
        ),

        'email_temp_content_forgot_password' => $wrapTemplate2(
            'Khôi phục mật khẩu',
            '🔓',
            '
            <p style="color: #1e293b; font-size: 15px; line-height: 1.7; margin: 0 0 20px 0;">
                Xin chào <strong>{username}</strong>,
            </p>
            <p style="color: #475569; font-size: 14px; line-height: 1.6; margin: 0 0 24px 0;">
                Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn. Nhấn vào nút bên dưới để tiếp tục:
            </p>
            
            <!-- CTA Button -->
            <div style="text-align: center; margin: 32px 0;">
                <a href="{link}" style="display: inline-block; padding: 14px 32px; background: ' . $themeColor . '; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 15px;">Đặt lại mật khẩu</a>
            </div>
            
            <div style="background: #fef2f2; border: 1px solid #fca5a5; border-radius: 6px; padding: 12px 16px; margin: 0 0 24px 0;">
                <p style="color: #991b1b; font-size: 13px; margin: 0;">⚠️ Link sẽ hết hạn sau <strong>30 phút</strong>. Nếu bạn không yêu cầu, hãy bỏ qua email này.</p>
            </div>
            
            <div style="border-top: 1px solid #e5e7eb; padding-top: 16px;">
                <p style="color: #94a3b8; font-size: 12px; margin: 0;">
                    Thời gian: {time}<br>IP: {ip} • Thiết bị: {device}
                </p>
            </div>
            ',
            ''
        ),

        'email_temp_content_order_expiry' => $wrapTemplate2(
            '{expiry_message}',
            '⏰',
            '
            <p style="color: #1e293b; font-size: 15px; line-height: 1.7; margin: 0 0 20px 0;">
                Xin chào <strong>{username}</strong>,
            </p>
            
            <!-- Product Info -->
            <div style="background: #fff1f2; border: 1px solid #fda4af; border-left: 4px solid #e11d48; border-radius: 8px; padding: 20px; margin: 0 0 24px 0;">
                <table cellpadding="0" cellspacing="0" border="0" width="100%" style="font-size: 14px;">
                    <tr>
                        <td style="color: #9f1239; padding: 6px 0; width: 120px;">Sản phẩm:</td>
                        <td style="color: #881337; font-weight: 600;">{product_name}</td>
                    </tr>
                    <tr>
                        <td style="color: #9f1239; padding: 6px 0;">Gói:</td>
                        <td style="color: #881337; font-weight: 500;">{plan_name}</td>
                    </tr>
                    <tr>
                        <td style="color: #9f1239; padding: 6px 0;">Mã đơn hàng:</td>
                        <td style="color: #881337; font-weight: 500; font-family: monospace;">{trans_id}</td>
                    </tr>
                    <tr>
                        <td style="color: #9f1239; padding: 6px 0;">Ngày hết hạn:</td>
                        <td style="color: #e11d48; font-weight: 700;">{expiry_date}</td>
                    </tr>
                </table>
            </div>
            
            <p style="color: #475569; font-size: 14px; line-height: 1.6; margin: 0 0 24px 0;">
                Để tiếp tục sử dụng dịch vụ không gián đoạn, vui lòng gia hạn đơn hàng của bạn.
            </p>
            
            <!-- CTA Button -->
            <div style="text-align: center; margin: 28px 0;">
                <a href="{domain}" style="display: inline-block; padding: 12px 28px; background: #e11d48; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 14px;">Gia hạn ngay →</a>
            </div>
            ',
            ''
        ),

        'email_temp_content_flash_sale_favorite' => $wrapTemplate2(
            'Flash Sale - Sản phẩm yêu thích',
            '⚡',
            '
            <p style="color: #1e293b; font-size: 15px; line-height: 1.7; margin: 0 0 20px 0;">
                Xin chào <strong>{username}</strong>,
            </p>
            <p style="color: #475569; font-size: 14px; line-height: 1.6; margin: 0 0 24px 0;">
                Tin vui! Sản phẩm bạn yêu thích đang có chương trình <strong style="color: #dc2626;">FLASH SALE</strong>! 🎉
            </p>
            
            <!-- Flash Sale Card -->
            <div style="background: linear-gradient(135deg, #dc2626 0%, #ea580c 100%); border-radius: 8px; padding: 24px; margin: 0 0 24px 0; text-align: center;">
                <p style="color: rgba(255,255,255,0.9); font-size: 12px; text-transform: uppercase; letter-spacing: 1px; margin: 0 0 8px 0;">{flash_sale_name}</p>
                <h3 style="color: #ffffff; font-size: 20px; font-weight: 700; margin: 0 0 12px 0;">{product_name}</h3>
                <div style="background: rgba(255,255,255,0.2); display: inline-block; padding: 10px 24px; border-radius: 6px;">
                    <span style="color: #ffffff; font-size: 22px; font-weight: 800;">{discount_info}</span>
                </div>
            </div>
            
            <!-- Time Info -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px; margin: 0 0 24px 0;">
                <table cellpadding="0" cellspacing="0" border="0" width="100%" style="font-size: 13px;">
                    <tr>
                        <td style="color: #64748b; text-align: center; padding: 4px;">🕐 Bắt đầu: <strong style="color: #1e293b;">{start_time}</strong></td>
                    </tr>
                    <tr>
                        <td style="color: #64748b; text-align: center; padding: 4px;">🏁 Kết thúc: <strong style="color: #1e293b;">{end_time}</strong></td>
                    </tr>
                </table>
            </div>
            
            <!-- CTA Button -->
            <div style="text-align: center; margin: 28px 0;">
                <a href="{product_link}" style="display: inline-block; padding: 14px 36px; background: linear-gradient(135deg, #dc2626 0%, #ea580c 100%); color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 700; font-size: 15px;">Mua ngay →</a>
            </div>
            ',
            'Số lượng có hạn, nhanh tay kẻo lỡ!'
        ),

        'email_temp_content_order_completed' => $wrapTemplate2(
            '✅ Đơn hàng hoàn thành',
            '📦',
            '
            <p style="color: #1e293b; font-size: 15px; line-height: 1.7; margin: 0 0 20px 0;">
                Xin chào <strong>{username}</strong>,
            </p>
            <p style="color: #475569; font-size: 14px; line-height: 1.6; margin: 0 0 24px 0;">
                Đơn hàng <strong>#{trans_id}</strong> đã được xử lý thành công!
            </p>
            
            <!-- Order Info -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #10b981; border-radius: 8px; padding: 20px; margin: 0 0 20px 0;">
                <table cellpadding="0" cellspacing="0" border="0" width="100%" style="font-size: 14px;">
                    <tr>
                        <td style="color: #64748b; padding: 6px 0;">📦 Sản phẩm:</td>
                        <td style="color: #1e293b; font-weight: 600;">{product_name}</td>
                    </tr>
                    <tr>
                        <td style="color: #64748b; padding: 6px 0;">📋 Gói:</td>
                        <td style="color: #1e293b;">{plan_name}</td>
                    </tr>
                    <tr>
                        <td style="color: #64748b; padding: 6px 0;">🔢 Số lượng:</td>
                        <td style="color: #1e293b;">{quantity}</td>
                    </tr>
                    <tr>
                        <td style="color: #64748b; padding: 6px 0;">💰 Tổng tiền:</td>
                        <td style="color: #dc2626; font-weight: 700;">{total_amount}</td>
                    </tr>
                </table>
            </div>
            
            <!-- Delivery Content -->
            <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 20px; margin: 0 0 24px 0;">
                <p style="color: #059669; font-size: 13px; font-weight: 600; margin: 0 0 10px 0;">📄 Thông tin tài khoản:</p>
                <div style="background: #fff; border-radius: 6px; padding: 15px; font-family: monospace; font-size: 13px; color: #1e293b; white-space: pre-wrap; word-break: break-all;">{delivery_content}</div>
            </div>
            
            <!-- CTA Button -->
            <div style="text-align: center; margin: 28px 0;">
                <a href="{order_link}" style="display: inline-block; padding: 12px 28px; background: #10b981; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 14px;">Xem chi tiết →</a>
            </div>
            ',
            ''
        ),

        'email_temp_content_warning_ticket' => $wrapTemplate2(
            'Ticket mới từ khách hàng',
            '🎫',
            '
            <p style="color: #1e293b; font-size: 15px; line-height: 1.7; margin: 0 0 20px 0;">
                <strong>Admin</strong>, có ticket mới cần xử lý!
            </p>
            
            <!-- Ticket Info -->
            <div style="background: #eff6ff; border: 1px solid #93c5fd; border-left: 4px solid #3b82f6; border-radius: 8px; padding: 20px; margin: 0 0 20px 0;">
                <table cellpadding="0" cellspacing="0" border="0" width="100%" style="font-size: 14px;">
                    <tr>
                        <td style="color: #1e40af; padding: 6px 0; width: 110px;">Khách hàng:</td>
                        <td style="color: #1e3a8a; font-weight: 600;">{username}</td>
                    </tr>
                    <tr>
                        <td style="color: #1e40af; padding: 6px 0;">Tiêu đề:</td>
                        <td style="color: #1e3a8a; font-weight: 600;">{subject}</td>
                    </tr>
                    <tr>
                        <td style="color: #1e40af; padding: 6px 0;">Danh mục:</td>
                        <td style="color: #1e3a8a; font-weight: 500;">{category}</td>
                    </tr>
                    <tr>
                        <td style="color: #1e40af; padding: 6px 0;">Mã đơn hàng:</td>
                        <td style="color: #1e3a8a; font-weight: 500; font-family: monospace;">{order_id}</td>
                    </tr>
                </table>
            </div>
            
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px; margin: 0 0 20px 0;">
                <p style="color: #64748b; font-size: 12px; text-transform: uppercase; margin: 0 0 8px 0;">Nội dung:</p>
                <p style="color: #334155; font-size: 14px; margin: 0; white-space: pre-wrap; line-height: 1.6;">{content}</p>
            </div>
            
            <div style="border-top: 1px solid #e5e7eb; padding-top: 16px;">
                <p style="color: #94a3b8; font-size: 12px; margin: 0;">
                    Thời gian: {time} • IP: {ip}
                </p>
            </div>
            ',
            ''
        ),

        'email_temp_content_reply_ticket' => $wrapTemplate2(
            'Phản hồi mới trên Ticket',
            '💬',
            '
            <p style="color: #1e293b; font-size: 15px; line-height: 1.7; margin: 0 0 20px 0;">
                <strong>Admin</strong>, có phản hồi mới trên ticket!
            </p>
            
            <!-- Ticket Info -->
            <div style="background: #ecfdf5; border: 1px solid #6ee7b7; border-left: 4px solid #10b981; border-radius: 8px; padding: 20px; margin: 0 0 20px 0;">
                <table cellpadding="0" cellspacing="0" border="0" width="100%" style="font-size: 14px;">
                    <tr>
                        <td style="color: #047857; padding: 6px 0; width: 110px;">Khách hàng:</td>
                        <td style="color: #065f46; font-weight: 600;">{username}</td>
                    </tr>
                    <tr>
                        <td style="color: #047857; padding: 6px 0;">Tiêu đề:</td>
                        <td style="color: #065f46; font-weight: 600;">{subject}</td>
                    </tr>
                </table>
            </div>
            
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px; margin: 0 0 20px 0;">
                <p style="color: #64748b; font-size: 12px; text-transform: uppercase; margin: 0 0 8px 0;">Nội dung phản hồi:</p>
                <p style="color: #334155; font-size: 14px; margin: 0; white-space: pre-wrap; line-height: 1.6;">{content}</p>
            </div>
            
            <div style="border-top: 1px solid #e5e7eb; padding-top: 16px;">
                <p style="color: #94a3b8; font-size: 12px; margin: 0;">
                    Thời gian: {time} • IP: {ip}
                </p>
            </div>
            ',
            ''
        ),

        'email_temp_content_ticket_created_user' => $wrapTemplate2(
            'Ticket đã được tiếp nhận',
            '🎫',
            '
            <p style="color: #1e293b; font-size: 15px; line-height: 1.7; margin: 0 0 20px 0;">
                Xin chào <strong>{username}</strong>,
            </p>
            <p style="color: #475569; font-size: 14px; line-height: 1.6; margin: 0 0 20px 0;">
                Yêu cầu hỗ trợ của bạn đã được tiếp nhận. Chúng tôi sẽ phản hồi sớm nhất có thể.
            </p>
            
            <!-- Ticket Info -->
            <div style="background: #eff6ff; border: 1px solid #93c5fd; border-left: 4px solid #3b82f6; border-radius: 8px; padding: 20px; margin: 0 0 20px 0;">
                <table cellpadding="0" cellspacing="0" border="0" width="100%" style="font-size: 14px;">
                    <tr>
                        <td style="color: #1e40af; padding: 6px 0; width: 110px;">Mã ticket:</td>
                        <td style="color: #1e3a8a; font-weight: 600;">#{ticket_id}</td>
                    </tr>
                    <tr>
                        <td style="color: #1e40af; padding: 6px 0;">Tiêu đề:</td>
                        <td style="color: #1e3a8a; font-weight: 600;">{subject}</td>
                    </tr>
                    <tr>
                        <td style="color: #1e40af; padding: 6px 0;">Danh mục:</td>
                        <td style="color: #1e3a8a; font-weight: 500;">{category}</td>
                    </tr>
                    <tr>
                        <td style="color: #1e40af; padding: 6px 0;">Mã đơn hàng:</td>
                        <td style="color: #1e3a8a; font-weight: 500; font-family: monospace;">{order_id}</td>
                    </tr>
                </table>
            </div>
            
            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 16px; margin: 0 0 20px 0; text-align: center;">
                <p style="color: #166534; font-size: 14px; margin: 0;">⏰ Thời gian phản hồi dự kiến: <strong>24 giờ làm việc</strong></p>
            </div>
            
            <div style="border-top: 1px solid #e5e7eb; padding-top: 16px;">
                <p style="color: #94a3b8; font-size: 12px; margin: 0;">
                    Thời gian: {time}
                </p>
            </div>
            ',
            'Cảm ơn bạn đã tin tưởng sử dụng dịch vụ!'
        )
    ];

    if (!isset($templates[$type])) {
        die(json_encode(['success' => false, 'message' => __('Template không tồn tại')]));
    }

    die(json_encode([
        'success' => true,
        'content' => $templates[$type]
    ]));
}

// Lấy template email mặc định - Mẫu 3 (Phong cách Minimal Elegant)
if ($_POST['action'] == 'getDefaultEmailTemplate3') {
    if (!isset($_POST['type']) || empty(trim($_POST['type']))) {
        die(json_encode(['success' => false, 'message' => __('Loại template không hợp lệ')]));
    }

    $type = trim(check_string($_POST['type']));
    $siteName = $CMSNT->site('title') ?: 'Website';
    $themeColor = $CMSNT->site('theme_color') ?: '#667eea';
    $themeColor1 = $CMSNT->site('theme_color1') ?: '#764ba2';

    // Base template wrapper - Mẫu 3: Minimal Elegant Style
    $wrapTemplate3 = function ($headerTitle, $headerIcon, $bodyContent, $footerText = '') use ($siteName, $themeColor, $themeColor1) {
        $year = date('Y');
        return '
<div style="font-family: \'Georgia\', \'Times New Roman\', serif; max-width: 600px; margin: 0 auto; background: #fefefe;">
    <!-- Top Accent Bar -->
    <div style="height: 6px; background: linear-gradient(90deg, ' . $themeColor . ' 0%, ' . $themeColor1 . ' 100%);"></div>
    
    <!-- Header -->
    <div style="padding: 40px 40px 30px 40px; text-align: center; border-bottom: 1px solid #f0f0f0;">
        <div style="font-size: 48px; margin-bottom: 16px;">' . $headerIcon . '</div>
        <h1 style="color: #2c3e50; font-size: 26px; font-weight: 400; margin: 0; letter-spacing: -0.5px;">' . $headerTitle . '</h1>
    </div>
    
    <!-- Body -->
    <div style="padding: 40px;">
        ' . $bodyContent . '
    </div>
    
    <!-- Footer -->
    <div style="padding: 30px 40px; background: #fafafa; border-top: 1px solid #f0f0f0; text-align: center;">
        ' . ($footerText ? '<p style="color: #7f8c8d; font-size: 14px; margin: 0 0 12px 0; font-style: italic;">' . $footerText . '</p>' : '') . '
        <p style="color: #bdc3c7; font-size: 12px; margin: 0;">© ' . $year . ' ' . $siteName . '</p>
    </div>
</div>';
    };

    // Default templates - Mẫu 3: Minimal Elegant Style
    $templates = [
        'email_temp_content_order_success' => $wrapTemplate3(
            'Cảm ơn bạn đã đặt hàng',
            '🛍️',
            '
            <p style="color: #2c3e50; font-size: 17px; line-height: 1.8; margin: 0 0 24px 0;">
                Xin chào <span style="color: ' . $themeColor . ';">{username}</span>,
            </p>
            <p style="color: #5d6d7e; font-size: 16px; line-height: 1.7; margin: 0 0 30px 0;">
                Đơn hàng của bạn đã được ghi nhận thành công. Chúng tôi rất trân trọng sự tin tưởng của bạn.
            </p>
            
            <!-- Order Summary -->
            <div style="background: #f8f9fa; border-radius: 12px; padding: 28px; margin: 0 0 30px 0;">
                <div style="display: flex; justify-content: space-between; padding-bottom: 16px; border-bottom: 1px dashed #e0e0e0; margin-bottom: 16px;">
                    <span style="color: #7f8c8d; font-size: 14px;">Số lượng đơn</span>
                    <span style="color: #2c3e50; font-size: 16px; font-weight: 600;">{order_count}</span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #7f8c8d; font-size: 14px;">Tổng thanh toán</span>
                    <span style="color: ' . $themeColor . '; font-size: 20px; font-weight: 700;">{total_amount}</span>
                </div>
            </div>
            
            <!-- Order Details -->
            <div style="margin: 0 0 30px 0;">
                {order_details}
            </div>
            
            <!-- CTA Button -->
            <div style="text-align: center; margin: 36px 0;">
                <a href="{order_link}" style="display: inline-block; padding: 16px 48px; background: linear-gradient(135deg, ' . $themeColor . ' 0%, ' . $themeColor1 . ' 100%); color: #ffffff; text-decoration: none; border-radius: 50px; font-family: Arial, sans-serif; font-weight: 600; font-size: 14px; letter-spacing: 0.5px;">Xem chi tiết đơn hàng</a>
            </div>
            
            <p style="color: #bdc3c7; font-size: 13px; margin: 30px 0 0 0; padding-top: 20px; border-top: 1px solid #f0f0f0; text-align: center;">
                {time} · {ip}
            </p>
            ',
            'Trân trọng cảm ơn!'
        ),

        'email_temp_content_warning_login' => $wrapTemplate3(
            'Phát hiện đăng nhập mới',
            '🔔',
            '
            <p style="color: #2c3e50; font-size: 17px; line-height: 1.8; margin: 0 0 24px 0;">
                Xin chào <span style="color: ' . $themeColor . ';">{username}</span>,
            </p>
            <p style="color: #5d6d7e; font-size: 16px; line-height: 1.7; margin: 0 0 30px 0;">
                Chúng tôi ghi nhận một lượt đăng nhập vào tài khoản của bạn từ thiết bị mới.
            </p>
            
            <!-- Login Details -->
            <div style="background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); border-radius: 12px; padding: 28px; margin: 0 0 30px 0;">
                <div style="margin-bottom: 16px;">
                    <div style="color: #b7791f; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;">Thời gian</div>
                    <div style="color: #744210; font-size: 15px; font-weight: 500;">{time}</div>
                </div>
                <div style="margin-bottom: 16px;">
                    <div style="color: #b7791f; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;">Địa chỉ IP</div>
                    <div style="color: #744210; font-size: 15px; font-weight: 500; font-family: monospace;">{ip}</div>
                </div>
                <div>
                    <div style="color: #b7791f; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;">Thiết bị</div>
                    <div style="color: #744210; font-size: 15px; font-weight: 500;">{device}</div>
                </div>
            </div>
            
            <p style="color: #5d6d7e; font-size: 15px; line-height: 1.7; margin: 0; text-align: center;">
                Nếu không phải bạn, vui lòng <strong>đổi mật khẩu ngay</strong>.
            </p>
            ',
            ''
        ),

        'email_temp_content_otp_mail' => $wrapTemplate3(
            'Mã xác thực của bạn',
            '🔐',
            '
            <p style="color: #2c3e50; font-size: 17px; line-height: 1.8; margin: 0 0 24px 0;">
                Xin chào <span style="color: ' . $themeColor . ';">{username}</span>,
            </p>
            <p style="color: #5d6d7e; font-size: 16px; line-height: 1.7; margin: 0 0 30px 0; text-align: center;">
                Vui lòng sử dụng mã OTP dưới đây để xác thực:
            </p>
            
            <!-- OTP Display -->
            <div style="text-align: center; margin: 40px 0;">
                <div style="display: inline-block; background: linear-gradient(135deg, ' . $themeColor . ' 0%, ' . $themeColor1 . ' 100%); padding: 24px 48px; border-radius: 16px; box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);">
                    <span style="font-size: 40px; font-weight: 700; color: #ffffff; letter-spacing: 12px; font-family: \'Courier New\', monospace;">{otp}</span>
                </div>
            </div>
            
            <div style="text-align: center; margin: 30px 0;">
                <span style="display: inline-block; background: #e8f5e9; color: #2e7d32; padding: 12px 24px; border-radius: 50px; font-size: 14px; font-family: Arial, sans-serif;">
                    ⏱ Hiệu lực trong 5 phút
                </span>
            </div>
            
            <p style="color: #bdc3c7; font-size: 13px; margin: 30px 0 0 0; text-align: center;">
                IP: {ip} · Thiết bị: {device}
            </p>
            ',
            'Vui lòng không chia sẻ mã này.'
        ),

        'email_temp_content_forgot_password' => $wrapTemplate3(
            'Yêu cầu đặt lại mật khẩu',
            '🔑',
            '
            <p style="color: #2c3e50; font-size: 17px; line-height: 1.8; margin: 0 0 24px 0;">
                Xin chào <span style="color: ' . $themeColor . ';">{username}</span>,
            </p>
            <p style="color: #5d6d7e; font-size: 16px; line-height: 1.7; margin: 0 0 30px 0;">
                Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn. Nhấn vào nút bên dưới để tiếp tục:
            </p>
            
            <!-- CTA Button -->
            <div style="text-align: center; margin: 40px 0;">
                <a href="{link}" style="display: inline-block; padding: 18px 56px; background: linear-gradient(135deg, ' . $themeColor . ' 0%, ' . $themeColor1 . ' 100%); color: #ffffff; text-decoration: none; border-radius: 50px; font-family: Arial, sans-serif; font-weight: 600; font-size: 15px; box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);">Đặt lại mật khẩu</a>
            </div>
            
            <div style="background: #fff3e0; border-radius: 12px; padding: 20px; margin: 30px 0; text-align: center;">
                <p style="color: #e65100; font-size: 14px; margin: 0; font-family: Arial, sans-serif;">
                    ⏳ Liên kết có hiệu lực trong <strong>30 phút</strong>
                </p>
            </div>
            
            <p style="color: #bdc3c7; font-size: 13px; margin: 0; text-align: center;">
                Thời gian: {time}<br>IP: {ip} · Thiết bị: {device}
            </p>
            ',
            'Bỏ qua email này nếu bạn không yêu cầu.'
        ),

        'email_temp_content_order_expiry' => $wrapTemplate3(
            '{expiry_message}',
            '⏰',
            '
            <p style="color: #2c3e50; font-size: 17px; line-height: 1.8; margin: 0 0 24px 0;">
                Xin chào <span style="color: ' . $themeColor . ';">{username}</span>,
            </p>
            
            <!-- Product Info -->
            <div style="background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); border-radius: 16px; padding: 28px; margin: 0 0 30px 0;">
                <div style="margin-bottom: 20px;">
                    <div style="color: #b7791f; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px;">Sản phẩm</div>
                    <div style="color: #744210; font-size: 18px; font-weight: 600;">{product_name}</div>
                </div>
                <div style="display: flex; gap: 24px;">
                    <div style="flex: 1;">
                        <div style="color: #b7791f; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px;">Gói</div>
                        <div style="color: #744210; font-size: 14px;">{plan_name}</div>
                    </div>
                    <div style="flex: 1;">
                        <div style="color: #b7791f; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px;">Mã đơn</div>
                        <div style="color: #744210; font-size: 14px; font-family: monospace;">{trans_id}</div>
                    </div>
                </div>
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px dashed rgba(180, 121, 31, 0.3);">
                    <div style="color: #b7791f; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px;">Ngày hết hạn</div>
                    <div style="color: #c0392b; font-size: 18px; font-weight: 700;">{expiry_date}</div>
                </div>
            </div>
            
            <p style="color: #5d6d7e; font-size: 16px; line-height: 1.7; margin: 0 0 30px 0; text-align: center;">
                Gia hạn ngay để tiếp tục sử dụng dịch vụ.
            </p>
            
            <!-- CTA Button -->
            <div style="text-align: center; margin: 36px 0;">
                <a href="{domain}" style="display: inline-block; padding: 16px 48px; background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); color: #ffffff; text-decoration: none; border-radius: 50px; font-family: Arial, sans-serif; font-weight: 600; font-size: 14px;">Gia hạn ngay</a>
            </div>
            ',
            ''
        ),

        'email_temp_content_flash_sale_favorite' => $wrapTemplate3(
            '⚡ Flash Sale đang diễn ra!',
            '🎁',
            '
            <p style="color: #2c3e50; font-size: 17px; line-height: 1.8; margin: 0 0 24px 0;">
                Xin chào <span style="color: ' . $themeColor . ';">{username}</span>,
            </p>
            <p style="color: #5d6d7e; font-size: 16px; line-height: 1.7; margin: 0 0 30px 0;">
                Sản phẩm bạn yêu thích đang có <strong style="color: #e74c3c;">ưu đãi đặc biệt</strong>!
            </p>
            
            <!-- Flash Sale Card -->
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 20px; padding: 32px; margin: 0 0 30px 0; text-align: center;">
                <p style="color: rgba(255,255,255,0.8); font-size: 13px; text-transform: uppercase; letter-spacing: 2px; margin: 0 0 12px 0;">{flash_sale_name}</p>
                <h3 style="color: #ffffff; font-size: 24px; font-weight: 700; margin: 0 0 20px 0;">{product_name}</h3>
                <div style="background: rgba(255,255,255,0.2); display: inline-block; padding: 16px 32px; border-radius: 12px;">
                    <span style="color: #ffffff; font-size: 28px; font-weight: 800;">{discount_info}</span>
                </div>
            </div>
            
            <!-- Time Info -->
            <div style="background: #f8f9fa; border-radius: 12px; padding: 20px; margin: 0 0 30px 0; text-align: center;">
                <p style="color: #7f8c8d; font-size: 14px; margin: 0;">
                    🕐 Từ <strong style="color: #2c3e50;">{start_time}</strong> đến <strong style="color: #2c3e50;">{end_time}</strong>
                </p>
            </div>
            
            <!-- CTA Button -->
            <div style="text-align: center; margin: 36px 0;">
                <a href="{product_link}" style="display: inline-block; padding: 18px 56px; background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); color: #ffffff; text-decoration: none; border-radius: 50px; font-family: Arial, sans-serif; font-weight: 700; font-size: 16px; text-transform: uppercase; letter-spacing: 1px;">Mua ngay</a>
            </div>
            ',
            'Số lượng có hạn!'
        ),

        'email_temp_content_order_completed' => $wrapTemplate3(
            '🎉 Đơn hàng đã hoàn thành!',
            '✅',
            '
            <p style="color: #2c3e50; font-size: 17px; line-height: 1.8; margin: 0 0 24px 0;">
                Xin chào <span style="color: ' . $themeColor . ';">{username}</span>,
            </p>
            <p style="color: #5d6d7e; font-size: 16px; line-height: 1.7; margin: 0 0 30px 0;">
                Đơn hàng <strong style="color: #27ae60;">#{trans_id}</strong> của bạn đã được xử lý thành công!
            </p>
            
            <!-- Order Card -->
            <div style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); border-radius: 16px; padding: 28px; margin: 0 0 24px 0;">
                <div style="margin-bottom: 16px;">
                    <div style="color: #2980b9; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;">Sản phẩm</div>
                    <div style="color: #1a5276; font-size: 16px; font-weight: 600;">{product_name}</div>
                </div>
                <div style="display: flex; gap: 24px; margin-bottom: 16px;">
                    <div style="flex: 1;">
                        <div style="color: #2980b9; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;">Gói</div>
                        <div style="color: #1a5276; font-size: 14px;">{plan_name}</div>
                    </div>
                    <div style="flex: 1;">
                        <div style="color: #2980b9; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;">Số lượng</div>
                        <div style="color: #1a5276; font-size: 14px;">{quantity}</div>
                    </div>
                </div>
                <div style="padding-top: 16px; border-top: 1px dashed rgba(41, 128, 185, 0.3);">
                    <div style="color: #2980b9; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;">Tổng tiền</div>
                    <div style="color: #c0392b; font-size: 18px; font-weight: 700;">{total_amount}</div>
                </div>
            </div>
            
            <!-- Delivery Content -->
            <div style="background: #f8f9fa; border-radius: 12px; padding: 24px; margin: 0 0 30px 0;">
                <p style="color: #27ae60; font-size: 14px; font-weight: 600; margin: 0 0 12px 0;">📄 Thông tin tài khoản:</p>
                <div style="background: #fff; border-radius: 8px; padding: 16px; font-family: monospace; font-size: 13px; color: #2c3e50; white-space: pre-wrap; word-break: break-all;">{delivery_content}</div>
            </div>
            
            <!-- CTA Button -->
            <div style="text-align: center; margin: 36px 0;">
                <a href="{order_link}" style="display: inline-block; padding: 16px 48px; background: linear-gradient(135deg, #27ae60 0%, #1e8449 100%); color: #ffffff; text-decoration: none; border-radius: 50px; font-family: Arial, sans-serif; font-weight: 600; font-size: 14px;">Xem chi tiết đơn hàng</a>
            </div>
            ',
            ''
        ),

        'email_temp_content_warning_ticket' => $wrapTemplate3(
            'Ticket hỗ trợ mới',
            '📩',
            '
            <p style="color: #2c3e50; font-size: 17px; line-height: 1.8; margin: 0 0 24px 0;">
                <strong>Admin</strong>, có yêu cầu hỗ trợ mới!
            </p>
            
            <!-- Ticket Info -->
            <div style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); border-radius: 16px; padding: 28px; margin: 0 0 24px 0;">
                <div style="margin-bottom: 16px;">
                    <div style="color: #2980b9; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;">Khách hàng</div>
                    <div style="color: #1a5276; font-size: 16px; font-weight: 600;">{username}</div>
                </div>
                <div style="margin-bottom: 16px;">
                    <div style="color: #2980b9; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;">Tiêu đề</div>
                    <div style="color: #1a5276; font-size: 15px; font-weight: 500;">{subject}</div>
                </div>
                <div style="display: flex; gap: 24px;">
                    <div style="flex: 1;">
                        <div style="color: #2980b9; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;">Danh mục</div>
                        <div style="color: #1a5276; font-size: 14px;">{category}</div>
                    </div>
                    <div style="flex: 1;">
                        <div style="color: #2980b9; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;">Mã đơn hàng</div>
                        <div style="color: #1a5276; font-size: 14px; font-family: monospace;">{order_id}</div>
                    </div>
                </div>
            </div>
            
            <div style="background: #f8f9fa; border-radius: 12px; padding: 20px; margin: 0 0 24px 0;">
                <p style="color: #7f8c8d; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; margin: 0 0 12px 0;">Nội dung</p>
                <p style="color: #2c3e50; font-size: 15px; margin: 0; line-height: 1.7; white-space: pre-wrap;">{content}</p>
            </div>
            
            <p style="color: #bdc3c7; font-size: 13px; margin: 0; text-align: center;">
                {time} · {ip}
            </p>
            ',
            ''
        ),

        'email_temp_content_reply_ticket' => $wrapTemplate3(
            'Phản hồi ticket mới',
            '💬',
            '
            <p style="color: #2c3e50; font-size: 17px; line-height: 1.8; margin: 0 0 24px 0;">
                <strong>Admin</strong>, có phản hồi mới trên ticket!
            </p>
            
            <!-- Ticket Info -->
            <div style="background: linear-gradient(135deg, #d4fc79 0%, #96e6a1 100%); border-radius: 16px; padding: 28px; margin: 0 0 24px 0;">
                <div style="margin-bottom: 16px;">
                    <div style="color: #27ae60; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;">Khách hàng</div>
                    <div style="color: #1e8449; font-size: 16px; font-weight: 600;">{username}</div>
                </div>
                <div>
                    <div style="color: #27ae60; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;">Tiêu đề ticket</div>
                    <div style="color: #1e8449; font-size: 15px; font-weight: 500;">{subject}</div>
                </div>
            </div>
            
            <div style="background: #f8f9fa; border-radius: 12px; padding: 20px; margin: 0 0 24px 0;">
                <p style="color: #7f8c8d; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; margin: 0 0 12px 0;">Nội dung phản hồi</p>
                <p style="color: #2c3e50; font-size: 15px; margin: 0; line-height: 1.7; white-space: pre-wrap;">{content}</p>
            </div>
            
            <p style="color: #bdc3c7; font-size: 13px; margin: 0; text-align: center;">
                {time} · {ip}
            </p>
            ',
            ''
        ),

        'email_temp_content_ticket_created_user' => $wrapTemplate3(
            'Yêu cầu đã tiếp nhận',
            '🎫',
            '
            <p style="color: #2c3e50; font-size: 17px; line-height: 1.8; margin: 0 0 24px 0;">
                Xin chào <strong>{username}</strong>,
            </p>
            <p style="color: #5d6d7e; font-size: 15px; line-height: 1.7; margin: 0 0 24px 0;">
                Cảm ơn bạn đã liên hệ. Ticket của bạn đã được tiếp nhận thành công.
            </p>
            
            <!-- Ticket Info -->
            <div style="background: linear-gradient(135deg, #e0f4ff 0%, #c7ecee 100%); border-radius: 16px; padding: 28px; margin: 0 0 24px 0;">
                <div style="margin-bottom: 16px;">
                    <div style="color: #2980b9; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;">Mã Ticket</div>
                    <div style="color: #1a5276; font-size: 18px; font-weight: 600;">#{ticket_id}</div>
                </div>
                <div style="margin-bottom: 16px;">
                    <div style="color: #2980b9; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;">Tiêu đề</div>
                    <div style="color: #1a5276; font-size: 15px; font-weight: 500;">{subject}</div>
                </div>
                <div style="display: flex; gap: 24px;">
                    <div style="flex: 1;">
                        <div style="color: #2980b9; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;">Danh mục</div>
                        <div style="color: #1a5276; font-size: 14px;">{category}</div>
                    </div>
                    <div style="flex: 1;">
                        <div style="color: #2980b9; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;">Mã đơn</div>
                        <div style="color: #1a5276; font-size: 14px; font-family: monospace;">{order_id}</div>
                    </div>
                </div>
            </div>
            
            <div style="background: #f8f9fa; border-radius: 12px; padding: 20px; margin: 0 0 24px 0; text-align: center;">
                <p style="color: #27ae60; font-size: 14px; margin: 0;">✅ Chúng tôi sẽ phản hồi trong <strong>24 giờ làm việc</strong></p>
            </div>
            
            <p style="color: #bdc3c7; font-size: 13px; margin: 0; text-align: center;">
                {time}
            </p>
            ',
            'Trân trọng cảm ơn!'
        )
    ];

    if (!isset($templates[$type])) {
        die(json_encode(['success' => false, 'message' => __('Template không tồn tại')]));
    }

    die(json_encode([
        'success' => true,
        'content' => $templates[$type]
    ]));
}

// Lấy template email mặc định - Mẫu 4 (Phong cách Galaxy)
if ($_POST['action'] == 'getDefaultEmailTemplate4') {
    if (!isset($_POST['type']) || empty(trim($_POST['type']))) {
        die(json_encode(['success' => false, 'message' => __('Loại template không hợp lệ')]));
    }

    $type = trim(check_string($_POST['type']));
    $siteName = $CMSNT->site('title') ?: 'Website';

    // Base template wrapper - Mẫu 4: Galaxy Space Theme
    $wrapTemplate4 = function ($headerTitle, $headerIcon, $bodyContent, $footerText = '') use ($siteName) {
        $year = date('Y');
        return '
<div style="font-family: Segoe UI, Tahoma, Geneva, Verdana, sans-serif; max-width: 600px; margin: 0 auto; background: linear-gradient(180deg, #0f0c29 0%, #302b63 50%, #24243e 100%); border-radius: 16px; overflow: hidden;">
    <!-- Header -->
    <div style="padding: 40px 30px 30px 30px; text-align: center;">
        <div style="display: inline-block; width: 80px; height: 80px; background: linear-gradient(135deg, #667eea 0%, #f857a6 50%, #764ba2 100%); border-radius: 50%; line-height: 80px; margin-bottom: 20px; box-shadow: 0 0 40px rgba(248, 87, 166, 0.4);">
            <span style="font-size: 36px;">' . $headerIcon . '</span>
        </div>
        <h1 style="color: #ffffff; font-size: 24px; font-weight: 300; margin: 0; text-shadow: 0 0 20px rgba(248, 87, 166, 0.5);">' . $headerTitle . '</h1>
    </div>
    
    <!-- Body -->
    <div style="padding: 30px 35px 40px 35px;">
        ' . $bodyContent . '
    </div>
    
    <!-- Footer -->
    <div style="padding: 25px 30px; background: rgba(0,0,0,0.3); text-align: center; border-top: 1px solid rgba(255,255,255,0.1);">
        ' . ($footerText ? '<p style="color: rgba(255,255,255,0.6); font-size: 13px; margin: 0 0 10px 0;">' . $footerText . '</p>' : '') . '
        <p style="color: rgba(255,255,255,0.3); font-size: 11px; margin: 0;">✨ © ' . $year . ' ' . $siteName . '</p>
    </div>
</div>';
    };

    // Default templates - Mẫu 4: Galaxy Style
    $templates = [
        'email_temp_content_order_success' => $wrapTemplate4(
            '🎉 Đơn hàng thành công!',
            '🚀',
            '
            <p style="color: #e0e0ff; font-size: 16px; line-height: 1.8; margin: 0 0 24px 0;">
                Xin chào <span style="color: #f857a6; font-weight: 600;">{username}</span>,
            </p>
            <p style="color: rgba(255,255,255,0.7); font-size: 15px; line-height: 1.7; margin: 0 0 28px 0;">
                Đơn hàng của bạn đã được xử lý thành công! Cảm ơn bạn đã đồng hành cùng chúng tôi.
            </p>
            
            <!-- Order Summary -->
            <div style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 24px; margin: 0 0 28px 0; backdrop-filter: blur(10px);">
                <div style="display: flex; justify-content: space-between; padding-bottom: 16px; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 16px;">
                    <span style="color: rgba(255,255,255,0.5); font-size: 14px;">📦 Số lượng đơn</span>
                    <span style="color: #ffffff; font-size: 18px; font-weight: 600;">{order_count}</span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: rgba(255,255,255,0.5); font-size: 14px;">💎 Tổng thanh toán</span>
                    <span style="background: linear-gradient(90deg, #667eea, #f857a6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-size: 22px; font-weight: 700;">{total_amount}</span>
                </div>
            </div>
            
            <!-- Order Details -->
            <div style="margin: 0 0 28px 0; color: rgba(255,255,255,0.7);">
                {order_details}
            </div>
            
            <!-- CTA Button -->
            <div style="text-align: center; margin: 32px 0;">
                <a href="{order_link}" style="display: inline-block; padding: 16px 40px; background: linear-gradient(135deg, #667eea 0%, #f857a6 100%); color: #ffffff; text-decoration: none; border-radius: 50px; font-weight: 600; font-size: 14px; box-shadow: 0 8px 30px rgba(248, 87, 166, 0.4); text-transform: uppercase; letter-spacing: 1px;">Xem đơn hàng →</a>
            </div>
            
            <p style="color: rgba(255,255,255,0.3); font-size: 12px; margin: 28px 0 0 0; text-align: center;">
                ⏰ {time} · 🌐 {ip}
            </p>
            ',
            '✨ Cảm ơn bạn đã tin tưởng!'
        ),

        'email_temp_content_warning_login' => $wrapTemplate4(
            '🔐 Đăng nhập mới',
            '👁️',
            '
            <p style="color: #e0e0ff; font-size: 16px; line-height: 1.8; margin: 0 0 24px 0;">
                Xin chào <span style="color: #f857a6; font-weight: 600;">{username}</span>,
            </p>
            <p style="color: rgba(255,255,255,0.7); font-size: 15px; line-height: 1.7; margin: 0 0 28px 0;">
                Phát hiện đăng nhập mới vào tài khoản của bạn.
            </p>
            
            <!-- Login Details -->
            <div style="background: linear-gradient(135deg, rgba(255, 193, 7, 0.15) 0%, rgba(255, 87, 34, 0.15) 100%); border: 1px solid rgba(255, 193, 7, 0.3); border-radius: 16px; padding: 24px; margin: 0 0 28px 0;">
                <div style="margin-bottom: 18px;">
                    <div style="color: #ffc107; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px;">⏰ Thời gian</div>
                    <div style="color: #fff; font-size: 15px;">{time}</div>
                </div>
                <div style="margin-bottom: 18px;">
                    <div style="color: #ffc107; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px;">🌐 Địa chỉ IP</div>
                    <div style="color: #fff; font-size: 15px; font-family: monospace;">{ip}</div>
                </div>
                <div>
                    <div style="color: #ffc107; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px;">📱 Thiết bị</div>
                    <div style="color: #fff; font-size: 15px;">{device}</div>
                </div>
            </div>
            
            <p style="color: rgba(255,255,255,0.6); font-size: 14px; text-align: center; margin: 0;">
                ⚠️ Nếu không phải bạn, hãy đổi mật khẩu ngay!
            </p>
            ',
            ''
        ),

        'email_temp_content_otp_mail' => $wrapTemplate4(
            '🔑 Mã xác thực OTP',
            '🛡️',
            '
            <p style="color: #e0e0ff; font-size: 16px; line-height: 1.8; margin: 0 0 24px 0;">
                Xin chào <span style="color: #f857a6; font-weight: 600;">{username}</span>,
            </p>
            <p style="color: rgba(255,255,255,0.7); font-size: 15px; line-height: 1.7; margin: 0 0 32px 0; text-align: center;">
                Đây là mã OTP để xác thực đăng nhập:
            </p>
            
            <!-- OTP Display -->
            <div style="text-align: center; margin: 40px 0;">
                <div style="display: inline-block; background: linear-gradient(135deg, rgba(102, 126, 234, 0.3) 0%, rgba(248, 87, 166, 0.3) 100%); border: 2px solid rgba(248, 87, 166, 0.5); padding: 28px 50px; border-radius: 20px; box-shadow: 0 0 40px rgba(248, 87, 166, 0.2);">
                    <span style="font-size: 42px; font-weight: 700; color: #ffffff; letter-spacing: 14px; text-shadow: 0 0 20px rgba(248, 87, 166, 0.5); font-family: \'Courier New\', monospace;">{otp}</span>
                </div>
            </div>
            
            <div style="text-align: center; margin: 28px 0;">
                <span style="display: inline-block; background: rgba(76, 175, 80, 0.2); border: 1px solid rgba(76, 175, 80, 0.5); color: #81c784; padding: 12px 24px; border-radius: 50px; font-size: 13px;">
                    ⏱ Có hiệu lực trong 5 phút
                </span>
            </div>
            
            <p style="color: rgba(255,255,255,0.3); font-size: 12px; margin: 28px 0 0 0; text-align: center;">
                IP: {ip} · Thiết bị: {device}
            </p>
            ',
            '🔒 Không chia sẻ mã này với ai.'
        ),

        'email_temp_content_forgot_password' => $wrapTemplate4(
            '🔓 Đặt lại mật khẩu',
            '🔐',
            '
            <p style="color: #e0e0ff; font-size: 16px; line-height: 1.8; margin: 0 0 24px 0;">
                Xin chào <span style="color: #f857a6; font-weight: 600;">{username}</span>,
            </p>
            <p style="color: rgba(255,255,255,0.7); font-size: 15px; line-height: 1.7; margin: 0 0 32px 0;">
                Chúng tôi nhận được yêu cầu đặt lại mật khẩu. Nhấn nút bên dưới để tiếp tục:
            </p>
            
            <!-- CTA Button -->
            <div style="text-align: center; margin: 40px 0;">
                <a href="{link}" style="display: inline-block; padding: 18px 50px; background: linear-gradient(135deg, #667eea 0%, #f857a6 100%); color: #ffffff; text-decoration: none; border-radius: 50px; font-weight: 700; font-size: 15px; box-shadow: 0 8px 30px rgba(248, 87, 166, 0.4); text-transform: uppercase; letter-spacing: 1px;">Đặt lại mật khẩu</a>
            </div>
            
            <div style="background: rgba(255, 152, 0, 0.15); border: 1px solid rgba(255, 152, 0, 0.3); border-radius: 12px; padding: 16px; margin: 28px 0; text-align: center;">
                <p style="color: #ffb74d; font-size: 13px; margin: 0;">
                    ⏳ Link có hiệu lực trong <strong>30 phút</strong>
                </p>
            </div>
            
            <p style="color: rgba(255,255,255,0.3); font-size: 12px; margin: 0; text-align: center;">
                ⏰ {time}<br>🌐 {ip} · 📱 {device}
            </p>
            ',
            'Bỏ qua nếu bạn không yêu cầu.'
        ),

        'email_temp_content_order_expiry' => $wrapTemplate4(
            '{expiry_message}',
            '⏰',
            '
            <p style="color: #e0e0ff; font-size: 16px; line-height: 1.8; margin: 0 0 24px 0;">
                Xin chào <span style="color: #f857a6; font-weight: 600;">{username}</span>,
            </p>
            
            <!-- Product Info -->
            <div style="background: linear-gradient(135deg, rgba(233, 30, 99, 0.15) 0%, rgba(156, 39, 176, 0.15) 100%); border: 1px solid rgba(233, 30, 99, 0.3); border-radius: 16px; padding: 24px; margin: 0 0 28px 0;">
                <div style="margin-bottom: 18px;">
                    <div style="color: #f06292; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px;">📦 Sản phẩm</div>
                    <div style="color: #fff; font-size: 17px; font-weight: 600;">{product_name}</div>
                </div>
                <div style="display: flex; gap: 20px; margin-bottom: 18px;">
                    <div style="flex: 1;">
                        <div style="color: #f06292; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px;">📋 Gói</div>
                        <div style="color: rgba(255,255,255,0.8); font-size: 14px;">{plan_name}</div>
                    </div>
                    <div style="flex: 1;">
                        <div style="color: #f06292; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px;">🔖 Mã đơn</div>
                        <div style="color: rgba(255,255,255,0.8); font-size: 14px; font-family: monospace;">{trans_id}</div>
                    </div>
                </div>
                <div style="padding-top: 18px; border-top: 1px solid rgba(240, 98, 146, 0.3);">
                    <div style="color: #f06292; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px;">📅 Ngày hết hạn</div>
                    <div style="color: #ff5252; font-size: 18px; font-weight: 700;">{expiry_date}</div>
                </div>
            </div>
            
            <p style="color: rgba(255,255,255,0.6); font-size: 14px; text-align: center; margin: 0 0 28px 0;">
                Gia hạn ngay để tiếp tục sử dụng dịch vụ!
            </p>
            
            <!-- CTA Button -->
            <div style="text-align: center; margin: 32px 0;">
                <a href="{domain}" style="display: inline-block; padding: 16px 40px; background: linear-gradient(135deg, #e91e63 0%, #9c27b0 100%); color: #ffffff; text-decoration: none; border-radius: 50px; font-weight: 600; font-size: 14px; box-shadow: 0 8px 30px rgba(233, 30, 99, 0.4);">Gia hạn ngay →</a>
            </div>
            ',
            ''
        ),

        'email_temp_content_flash_sale_favorite' => $wrapTemplate4(
            '⚡ FLASH SALE!',
            '🔥',
            '
            <p style="color: #e0e0ff; font-size: 16px; line-height: 1.8; margin: 0 0 24px 0;">
                Xin chào <span style="color: #f857a6; font-weight: 600;">{username}</span>,
            </p>
            <p style="color: rgba(255,255,255,0.7); font-size: 15px; line-height: 1.7; margin: 0 0 28px 0;">
                Sản phẩm yêu thích đang có <strong style="color: #ff5252;">SALE SỐC</strong>! 💥
            </p>
            
            <!-- Flash Sale Card -->
            <div style="background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%); border-radius: 20px; padding: 32px; margin: 0 0 28px 0; text-align: center; box-shadow: 0 10px 40px rgba(255, 65, 108, 0.4);">
                <p style="color: rgba(255,255,255,0.8); font-size: 12px; text-transform: uppercase; letter-spacing: 3px; margin: 0 0 12px 0;">{flash_sale_name}</p>
                <h3 style="color: #ffffff; font-size: 22px; font-weight: 700; margin: 0 0 20px 0; text-shadow: 0 2px 10px rgba(0,0,0,0.3);">{product_name}</h3>
                <div style="background: rgba(255,255,255,0.2); display: inline-block; padding: 16px 32px; border-radius: 12px;">
                    <span style="color: #ffffff; font-size: 28px; font-weight: 800;">{discount_info}</span>
                </div>
            </div>
            
            <!-- Time Info -->
            <div style="background: rgba(255,255,255,0.08); border-radius: 12px; padding: 18px; margin: 0 0 28px 0; text-align: center;">
                <p style="color: rgba(255,255,255,0.6); font-size: 13px; margin: 0;">
                    🕐 Từ <strong style="color: #fff;">{start_time}</strong> đến <strong style="color: #fff;">{end_time}</strong>
                </p>
            </div>
            
            <!-- CTA Button -->
            <div style="text-align: center; margin: 32px 0;">
                <a href="{product_link}" style="display: inline-block; padding: 18px 50px; background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%); color: #ffffff; text-decoration: none; border-radius: 50px; font-weight: 700; font-size: 16px; box-shadow: 0 8px 30px rgba(255, 65, 108, 0.5); text-transform: uppercase; letter-spacing: 2px;">🛒 MUA NGAY</a>
            </div>
            ',
            '⏰ Số lượng có hạn!'
        ),

        'email_temp_content_order_completed' => $wrapTemplate4(
            '✅ Đơn hàng hoàn thành!',
            '🎉',
            '
            <p style="color: #e0e0ff; font-size: 16px; line-height: 1.8; margin: 0 0 24px 0;">
                Xin chào <span style="color: #f857a6; font-weight: 600;">{username}</span>,
            </p>
            <p style="color: rgba(255,255,255,0.7); font-size: 15px; line-height: 1.7; margin: 0 0 28px 0;">
                Đơn hàng <strong style="color: #4ade80;">#{trans_id}</strong> đã được xử lý thành công! 🎉
            </p>
            
            <!-- Order Card -->
            <div style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(52, 211, 153, 0.15) 100%); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 16px; padding: 24px; margin: 0 0 24px 0;">
                <div style="margin-bottom: 16px;">
                    <div style="color: #4ade80; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">📦 Sản phẩm</div>
                    <div style="color: #fff; font-size: 16px; font-weight: 600; margin-top: 4px;">{product_name}</div>
                </div>
                <div style="display: flex; gap: 20px; margin-bottom: 16px;">
                    <div style="flex: 1;">
                        <div style="color: #4ade80; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">📋 Gói</div>
                        <div style="color: rgba(255,255,255,0.8); font-size: 14px; margin-top: 4px;">{plan_name}</div>
                    </div>
                    <div style="flex: 1;">
                        <div style="color: #4ade80; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">🔢 Số lượng</div>
                        <div style="color: rgba(255,255,255,0.8); font-size: 14px; margin-top: 4px;">{quantity}</div>
                    </div>
                </div>
                <div style="padding-top: 16px; border-top: 1px solid rgba(16, 185, 129, 0.3);">
                    <div style="color: #4ade80; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">💰 Tổng tiền</div>
                    <div style="color: #ff5252; font-size: 18px; font-weight: 700; margin-top: 4px;">{total_amount}</div>
                </div>
            </div>
            
            <!-- Delivery Content -->
            <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 16px; padding: 24px; margin: 0 0 28px 0;">
                <p style="color: #4ade80; font-size: 13px; font-weight: 600; margin: 0 0 12px 0;">📄 Thông tin tài khoản:</p>
                <div style="background: rgba(0,0,0,0.3); border-radius: 8px; padding: 16px; font-family: monospace; font-size: 13px; color: #e0e0ff; white-space: pre-wrap; word-break: break-all;">{delivery_content}</div>
            </div>
            
            <!-- CTA Button -->
            <div style="text-align: center; margin: 32px 0;">
                <a href="{order_link}" style="display: inline-block; padding: 16px 40px; background: linear-gradient(135deg, #10b981 0%, #34d399 100%); color: #ffffff; text-decoration: none; border-radius: 50px; font-weight: 600; font-size: 14px; box-shadow: 0 8px 30px rgba(16, 185, 129, 0.4);">Xem chi tiết đơn hàng →</a>
            </div>
            ',
            ''
        ),

        'email_temp_content_warning_ticket' => $wrapTemplate4(
            '🎫 Ticket mới',
            '📩',
            '
            <p style="color: #e0e0ff; font-size: 16px; line-height: 1.8; margin: 0 0 24px 0;">
                <strong style="color: #f857a6;">Admin</strong>, có ticket mới cần xử lý!
            </p>
            
            <!-- Ticket Info -->
            <div style="background: linear-gradient(135deg, rgba(33, 150, 243, 0.15) 0%, rgba(0, 188, 212, 0.15) 100%); border: 1px solid rgba(33, 150, 243, 0.3); border-radius: 16px; padding: 24px; margin: 0 0 24px 0;">
                <div style="margin-bottom: 16px;">
                    <div style="color: #64b5f6; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">👤 Khách hàng</div>
                    <div style="color: #fff; font-size: 16px; font-weight: 600; margin-top: 4px;">{username}</div>
                </div>
                <div style="margin-bottom: 16px;">
                    <div style="color: #64b5f6; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">📋 Tiêu đề</div>
                    <div style="color: #fff; font-size: 15px; margin-top: 4px;">{subject}</div>
                </div>
                <div style="display: flex; gap: 20px;">
                    <div style="flex: 1;">
                        <div style="color: #64b5f6; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">📁 Danh mục</div>
                        <div style="color: rgba(255,255,255,0.8); font-size: 14px; margin-top: 4px;">{category}</div>
                    </div>
                    <div style="flex: 1;">
                        <div style="color: #64b5f6; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">🔖 Mã đơn</div>
                        <div style="color: rgba(255,255,255,0.8); font-size: 14px; margin-top: 4px; font-family: monospace;">{order_id}</div>
                    </div>
                </div>
            </div>
            
            <div style="background: rgba(255,255,255,0.05); border-radius: 12px; padding: 18px; margin: 0 0 24px 0;">
                <p style="color: rgba(255,255,255,0.5); font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin: 0 0 10px 0;">💬 Nội dung</p>
                <p style="color: rgba(255,255,255,0.8); font-size: 14px; margin: 0; line-height: 1.7; white-space: pre-wrap;">{content}</p>
            </div>
            
            <p style="color: rgba(255,255,255,0.3); font-size: 12px; margin: 0; text-align: center;">
                ⏰ {time} · 🌐 {ip}
            </p>
            ',
            ''
        ),

        'email_temp_content_reply_ticket' => $wrapTemplate4(
            '💬 Phản hồi ticket',
            '✨',
            '
            <p style="color: #e0e0ff; font-size: 16px; line-height: 1.8; margin: 0 0 24px 0;">
                <strong style="color: #f857a6;">Admin</strong>, có phản hồi mới!
            </p>
            
            <!-- Ticket Info -->
            <div style="background: linear-gradient(135deg, rgba(76, 175, 80, 0.15) 0%, rgba(0, 150, 136, 0.15) 100%); border: 1px solid rgba(76, 175, 80, 0.3); border-radius: 16px; padding: 24px; margin: 0 0 24px 0;">
                <div style="margin-bottom: 16px;">
                    <div style="color: #81c784; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">👤 Khách hàng</div>
                    <div style="color: #fff; font-size: 16px; font-weight: 600; margin-top: 4px;">{username}</div>
                </div>
                <div>
                    <div style="color: #81c784; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">📋 Tiêu đề ticket</div>
                    <div style="color: #fff; font-size: 15px; margin-top: 4px;">{subject}</div>
                </div>
            </div>
            
            <div style="background: rgba(255,255,255,0.05); border-radius: 12px; padding: 18px; margin: 0 0 24px 0;">
                <p style="color: rgba(255,255,255,0.5); font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin: 0 0 10px 0;">💬 Nội dung phản hồi</p>
                <p style="color: rgba(255,255,255,0.8); font-size: 14px; margin: 0; line-height: 1.7; white-space: pre-wrap;">{content}</p>
            </div>
            
            <p style="color: rgba(255,255,255,0.3); font-size: 12px; margin: 0; text-align: center;">
                ⏰ {time} · 🌐 {ip}
            </p>
            ',
            ''
        ),

        'email_temp_content_ticket_created_user' => $wrapTemplate4(
            '🎫 Ticket đã tiếp nhận',
            '✨',
            '
            <p style="color: #e0e0ff; font-size: 16px; line-height: 1.8; margin: 0 0 24px 0;">
                Xin chào <strong style="color: #f857a6;">{username}</strong>,
            </p>
            <p style="color: rgba(255,255,255,0.8); font-size: 15px; line-height: 1.7; margin: 0 0 24px 0;">
                Yêu cầu hỗ trợ của bạn đã được tiếp nhận.
            </p>
            
            <!-- Ticket Info -->
            <div style="background: linear-gradient(135deg, rgba(33, 150, 243, 0.15) 0%, rgba(0, 188, 212, 0.15) 100%); border: 1px solid rgba(33, 150, 243, 0.3); border-radius: 16px; padding: 24px; margin: 0 0 24px 0;">
                <div style="margin-bottom: 16px;">
                    <div style="color: #64b5f6; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">🎫 Mã Ticket</div>
                    <div style="color: #fff; font-size: 18px; font-weight: 600; margin-top: 4px;">#{ticket_id}</div>
                </div>
                <div style="margin-bottom: 16px;">
                    <div style="color: #64b5f6; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">📝 Tiêu đề</div>
                    <div style="color: #fff; font-size: 15px; margin-top: 4px;">{subject}</div>
                </div>
                <div style="display: flex; gap: 20px;">
                    <div style="flex: 1;">
                        <div style="color: #64b5f6; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">📁 Danh mục</div>
                        <div style="color: rgba(255,255,255,0.8); font-size: 14px; margin-top: 4px;">{category}</div>
                    </div>
                    <div style="flex: 1;">
                        <div style="color: #64b5f6; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">📦 Mã đơn</div>
                        <div style="color: rgba(255,255,255,0.8); font-size: 14px; margin-top: 4px; font-family: monospace;">{order_id}</div>
                    </div>
                </div>
            </div>
            
            <div style="background: rgba(76, 175, 80, 0.15); border: 1px solid rgba(76, 175, 80, 0.3); border-radius: 12px; padding: 16px; margin: 0 0 24px 0; text-align: center;">
                <p style="color: #81c784; font-size: 14px; margin: 0;">⏰ Phản hồi trong <strong>24 giờ</strong> làm việc</p>
            </div>
            
            <p style="color: rgba(255,255,255,0.3); font-size: 12px; margin: 0; text-align: center;">
                ⏰ {time}
            </p>
            ',
            'Cảm ơn bạn đã sử dụng dịch vụ!'
        )
    ];

    if (!isset($templates[$type])) {
        die(json_encode(['success' => false, 'message' => __('Template không tồn tại')]));
    }

    die(json_encode([
        'success' => true,
        'content' => $templates[$type]
    ]));
}


// Lấy template Telegram mặc định
if ($_POST['action'] == 'getDefaultTelegramTemplate') {
    if (!isset($_POST['type']) || empty(trim($_POST['type']))) {
        die(json_encode(['success' => false, 'message' => __('Loại template không hợp lệ')]));
    }

    $type = trim(check_string($_POST['type']));

    // Default Telegram templates with Markdown format
    $templates = [
        'noti_order_success_admin' => '🛒 *ĐƠN HÀNG MỚI*

👤 *Khách hàng:* {username}
📦 *Số đơn:* {order_count}
💰 *Tổng tiền:* {total_amount}
🎁 *Giảm giá:* {discount_amount}
🏷️ *Mã giảm:* {coupon_code}
💳 *Số dư còn:* {new_balance}

📋 *Mã đơn:* {order_ids}

🌐 {domain}
🕐 {time} | 📍 {ip}',

        'noti_order_success_user' => '✅ *MUA HÀNG THÀNH CÔNG!*

Xin chào *{username}*! 🎉

Cảm ơn bạn đã mua hàng tại {domain}

📦 *Số đơn:* {order_count}
💰 *Tổng tiền:* {total_amount}
💳 *Số dư còn:* {new_balance}
📋 *Mã đơn:* {order_ids}

🕐 {time}

Cảm ơn bạn đã tin tưởng! 💖',

        'noti_api_out_of_money' => '🚨 *CẢNH BÁO: API HẾT TIỀN!*

⚠️ Nhà cung cấp *{supplier_name}* đã hết tiền!

👤 *Khách hàng:* {username}
📦 *Sản phẩm:* {product_name}
🆔 *Product ID:* {product_id}
📋 *Plan ID:* {plan_id}
💰 *Số tiền:* {pay}
📊 *Số lượng:* {amount}
🔴 *HTTP Code:* {http_code}

⏰ Vui lòng nạp tiền API ngay!

🕐 {time} | 📍 {ip}',

        'noti_api_connection_error' => '🔴 *LỖI KẾT NỐI API!*

⚠️ Không thể kết nối đến *{supplier_name}*

👤 *Khách hàng:* {username}
📦 *Sản phẩm:* {product_name}
🆔 *Product ID:* {product_id}
📋 *Plan ID:* {plan_id}
💰 *Số tiền:* {pay}
🔴 *HTTP Code:* {http_code}

🔧 Vui lòng kiểm tra trạng thái nhà cung cấp!

🕐 {time} | 📍 {ip}',

        'noti_recharge' => '💳 *NẠP TIỀN THÀNH CÔNG*

👤 *Khách hàng:* {username}
🆔 *Mã giao dịch:* {trans_id}
💰 *Số tiền:* {amount}
💵 *Thực nhận:* {price}
🏦 *Phương thức:* {method}

🕐 {time}
🌐 {domain}',

        'noti_action' => '📝 *HÀNH ĐỘNG HỆ THỐNG*

👤 *Người dùng:* {username}
🔧 *Hành động:* {action}

🌐 {domain}
🕐 {time} | 📍 {ip}',

        'noti_affiliate_withdraw' => '💸 *YÊU CẦU RÚT HOA HỒNG*

👤 *Người dùng:* {username}
💰 *Số tiền:* {amount}

🏦 *Thông tin ngân hàng:*
• Ngân hàng: {bank}
• Số TK: {account_number}
• Chủ TK: {account_name}

🌐 {domain}
🕐 {time} | 📍 {ip}',

        'support_tickets_telegram_message' => '🎫 *TICKET MỚI*

👤 *Khách hàng:* {username}
📋 *Tiêu đề:* {subject}
📁 *Danh mục:* {category}

💬 *Nội dung:*
{content}

🌐 {domain}
🕐 {time} | 📍 {ip}
📱 {device}',

        'support_tickets_telegram_message_reply' => '💬 *PHẢN HỒI TICKET*

👤 *Khách hàng:* {username}
📋 *Tiêu đề:* {subject}
📁 *Danh mục:* {category}

💬 *Nội dung:*
{message}

🕐 {time} | 📍 {ip}
📱 {device}',

        'telegram_noti_login_user' => '🔐 *ĐĂNG NHẬP MỚI*

Xin chào *{username}*!

Tài khoản của bạn vừa đăng nhập thành công.

📍 *IP:* {ip}
📱 *Thiết bị:* {device}
🕐 *Thời gian:* {time}

🌐 {domain}

Nếu không phải bạn, hãy đổi mật khẩu ngay! ⚠️',

        'noti_user_admin_reply_ticket' => '📩 *ADMIN ĐÃ TRẢ LỜI TICKET*

Xin chào *{username}*!

Ticket *"{subject}"* đã có phản hồi mới:

💬 {message}

🕐 {time}',

        'noti_new_review' => '⭐ *ĐÁNH GIÁ SẢN PHẨM MỚI*

👤 *Người đánh giá:* {username}
📦 *Sản phẩm:* {product_name}
⭐ *Đánh giá:* {stars} ({rating}/5)

📝 *Tiêu đề:* {title}
💬 *Nội dung:* {content}

🌐 {domain}
🕐 {time}',

        'noti_pending_order_admin' => '⏳ *ĐƠN HÀNG ORDER MỚI*

👤 *Khách hàng:* {username}
📦 *Số đơn ORDER:* {order_count}
💰 *Tổng tiền:* {total_amount}
📋 *Mã đơn:* {order_ids}

📝 *Chi tiết:*
{order_details}

⚠️ Vui lòng xử lý đơn hàng!
🌐 {domain}
🕐 {time} | 📍 {ip}'
    ];

    if (!isset($templates[$type])) {
        die(json_encode(['success' => false, 'message' => __('Template không tồn tại')]));
    }

    die(json_encode([
        'success' => true,
        'content' => $templates[$type]
    ]));
}
