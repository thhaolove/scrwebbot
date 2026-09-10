<?php
define("IN_SITE", true);

require_once __DIR__ . "/../../libs/db.php";
require_once __DIR__ . "/../../config.php";
require_once __DIR__ . "/../../libs/lang.php";
require_once __DIR__ . "/../../libs/helper.php";

$CMSNT = new DB();

// Kiểm tra thời gian chạy cron
$currentTime = time();
$lastCronTime = $CMSNT->site("time_cron_checklive_gmail");
if ($lastCronTime >= $currentTime || ($currentTime - $lastCronTime < 1)) {
    exit("Thao tác quá nhanh, vui lòng thử lại sau!");
}

// Cập nhật thời gian chạy cron
$CMSNT->update("settings", ["value" => $currentTime], " `name` = 'time_cron_checklive_gmail' ");

// Lấy danh sách sản phẩm có bật `check_live = 'Gmail'`
$products_list = $CMSNT->get_list("SELECT * FROM `products` WHERE `check_live` = 'Gmail'");

if (empty($products_list)) {
    exit("Không có sản phẩm nào bật check live");
}

// Lấy danh sách mã sản phẩm
$product_codes = array_column($products_list, "code");
$product_codes_str = implode("','", array_map("addslashes", $product_codes));
$where_is_checklive = " AND `product_code` IN ('" . $product_codes_str . "')";

// Lấy danh sách email cần kiểm tra
$timeLimit = $currentTime - $CMSNT->site("time_limit_check_live_gmail");
$products = $CMSNT->get_list("
    SELECT * 
    FROM `product_stock` 
    WHERE `time_check_live` < {$timeLimit} {$where_is_checklive} 
    ORDER BY `time_check_live` ASC 
    LIMIT 1000
");

if (empty($products)) {
    exit("Không có sản phẩm cần kiểm tra live");
}

$uids = [];
$emails = [];
$products_info = [];

// Chuẩn bị dữ liệu cho API
foreach ($products as $product) {
    $email = $product["uid"];
    if (!in_array($email, $uids)) {
        $uids[] = $email;
        $emails[] = ["email" => $email];
    }
    $products_info[$email] = $product;
}

// Gửi yêu cầu kiểm tra đến API
$ch = curl_init();
$apiUrl = $CMSNT->site("api_check_live_gmail");
$apiKey = $CMSNT->site("api_key_check_live_gmail");

curl_setopt_array($ch, [
    CURLOPT_URL => $apiUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode(["api_key" => $apiKey, "emails" => $emails]),
    CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
    CURLOPT_TIMEOUT => 30,
]);

$result = curl_exec($ch);
$info = curl_getinfo($ch);

// Xử lý kết quả từ API
if ($info["http_code"] === 200) {
    $response_data = json_decode($result, true);
    foreach ($response_data["data"] as $email_data) {
        $email = $email_data["email"];
        $status = $email_data["status"];

        if (in_array($status, ["live", "Verify"])) {
            // Cập nhật thời gian kiểm tra
            $CMSNT->update("product_stock", ["time_check_live" => $currentTime], " `id` = '" . $products_info[$email]["id"] . "' ");
            echo "GMAIL: " . $email . ", Result: LIVE | " . $status . "<br>";
        } else {
            // Di chuyển vào bảng `product_die`
            $isInserted = $CMSNT->insert("product_die", [
                "product_code" => $products_info[$email]["product_code"],
                "seller" => $products_info[$email]["seller"],
                "uid" => $products_info[$email]["uid"],
                "account" => $products_info[$email]["account"],
                "create_gettime" => $products_info[$email]["create_gettime"],
                "type" => $products_info[$email]["type"]
            ]);

            if ($isInserted) {
                $CMSNT->remove("product_stock", " `id` = '" . $products_info[$email]["id"] . "' ");
                echo "GMAIL: " . $email . ", Result: DIE | " . $status . "<br>";
            }
        }
    }
} else {
    // Xử lý lỗi khi API trả về mã không phải 200
    foreach ($uids as $email) {
        $CMSNT->update("product_stock", ["time_check_live" => $currentTime], " `id` = '" . $products_info[$email]["id"] . "' ");
        echo "GMAIL: " . substr($email, 0, 6) . "*******, Result: ERROR<br>";
    }
}

// Đóng cURL
curl_close($ch);
