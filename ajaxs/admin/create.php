<?php

define("IN_SITE", true);
require_once(__DIR__."/../../config.php");
require_once(__DIR__."/../../libs/db.php");
require_once(__DIR__."/../../libs/lang.php");
require_once(__DIR__."/../../libs/helper.php");
require_once(__DIR__.'/../../models/is_admin.php');

if ($CMSNT->site('status_demo') != 0) {
    $data = json_encode([
        'status'    => 'error',
        'msg'       => __('This function cannot be used because this is a demo site')
    ]);
    die($data);
}
if(!isset($_POST['action'])){
    $data = json_encode([
        'status'    => 'error',
        'msg'       => 'The Request Not Found'
    ]);
    die($data);   
}
 
if ($_POST['action'] == 'generated_description_by_ai') {
    // Kiểm tra quyền sử dụng tính năng
    if (checkPermission($getUser['admin'], 'edit_product') != true) {
        die(json_encode(['success' => false, 'message' => 'Bạn không có quyền sử dụng tính năng này']));
    }

    // Lấy dữ liệu từ POST
    $keyword    = isset($_POST['keyword']) ? check_string($_POST['keyword']) : '';
    $short_desc = isset($_POST['short_desc']) ? check_string($_POST['short_desc']) : '';

    // Kiểm tra tham số (loại bỏ biến $license vì không được định nghĩa)
    if (empty($keyword) || empty($short_desc)) {
        echo json_encode([
            'success' => false,
            'message' => 'Thiếu dữ liệu tên sản phẩm và mô tả ngắn'
        ]);
        exit;
    }

    if($CMSNT->site('chatgpt_api_key') == ''){
        echo json_encode([
            'success' => false,
            'message' => 'Vui lòng cấu hình Api Key ChatGPT tại Admin -> Cài Đặt -> Kết nối'
        ]);
        exit;
    }
    // Tạo prompt cho ChatGPT sử dụng cả keyword và short_desc
    $prompt = "Hãy tạo một bài giới thiệu chi tiết về loại tài khoản hoặc sản phẩm có tên \"$keyword\". " .
              "Mô tả ngắn: \"$short_desc\". " .
              "Nội dung cần bao gồm:\n" .
              "- Các tính năng và ưu điểm nổi bật,\n" .
              "- Lợi ích khi sử dụng tài khoản/sản phẩm \"$keyword\",\n" .
              "- Một số hướng dẫn hoặc thông tin bổ ích dành cho người dùng.\n\n" .
              "Yêu cầu:\n" .
              "- Sử dụng các thẻ như <h1>, <h2>, <p>, <ul>, <li> phù hợp trong CKEDITOR để định dạng nội dung giống như bài viết chuẩn SEO.\n" .
              "    - Font chữ (font-family) và màu sắc (color) cho tiêu đề và đoạn văn,\n" .
              "    - Khoảng cách padding và margin hợp lý,\n" .
              "    - Hiệu ứng nhẹ cho tiêu đề, ví dụ như underline hoặc shadow.\n\n";

    // Thiết lập các thông số cho API ChatGPT
    $api_key     = $CMSNT->site('chatgpt_api_key');    
    $model       = $CMSNT->site('chatgpt_model'); // Hoặc "gpt-4" nếu bạn có quyền truy cập
    $max_tokens  = 1000;
    $temperature = 0.7;

    $url     = 'https://api.openai.com/v1/chat/completions';
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ];
    $data = [
        'model' => $model,
        'messages' => [
            ['role' => 'user', 'content' => $prompt]
        ],
        'max_tokens'   => $max_tokens,
        'temperature'  => $temperature
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    // Tắt xác minh SSL nếu môi trường của bạn cần
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo json_encode([
            'success' => false,
            'message' => "Curl Error: " . curl_error($ch)
        ]);
        exit;
    }

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($http_code != 200) {
        echo json_encode([
            'success' => false,
            'message' => "HTTP Error: $http_code => $response"
        ]);
        exit;
    }
    curl_close($ch);

    $response_data = json_decode($response, true);
    if (!$response_data) {
        echo json_encode([
            'success' => false,
            'message' => "AI đang gián đoạn, đang cố gắng thử lại sau: " . $response
        ]);
        exit;
    }

    // Lấy kết quả từ API nếu có
    if (isset($response_data['choices'][0]['message']['content'])) {
        $generatedContent = $response_data['choices'][0]['message']['content'];
    } else {
        $generatedContent = 'No response generated';
    }

    // Trả về kết quả dưới dạng JSON
    echo json_encode([
        'success'     => true,
        'description' => $generatedContent
    ]);
    exit;
}


die(json_encode([
    'status'    => 'error',
    'msg'       => __('Invalid data')
]));