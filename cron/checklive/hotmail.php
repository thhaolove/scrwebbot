<?php

    define("IN_SITE", true);
    require_once(__DIR__.'/../../libs/db.php');
    require_once(__DIR__.'/../../config.php');
    require_once(__DIR__.'/../../libs/lang.php');
    require_once(__DIR__.'/../../libs/helper.php');
    $CMSNT = new DB();

    // Nếu có đặt key cron job thì kiểm tra key hợp lệ
    if(!empty($CMSNT->site('key_cron_job'))){
        if(empty($_GET['key']) || $_GET['key'] != $CMSNT->site('key_cron_job')){
            die(__('Key không hợp lệ'));
        }
    }

    /* START CHỐNG SPAM */
    if (time() > $CMSNT->site('time_cron_checklive_hotmail')) {
        if (time() - $CMSNT->site('time_cron_checklive_hotmail') < 1) {
            die('Thao tác quá nhanh, vui lòng thử lại sau!');
        }
    }
    $CMSNT->update("settings", [
        'value' => time()
    ], " `name` = 'time_cron_checklive_hotmail' ");

 
// Khởi tạo mảng UIDs
$uids = [];
// Khởi tạo mảng chứa các thông tin về sản phẩm
$products_info = [];

$where_is_checklive = '';
// Lấy danh sách các sản phẩm có `check_live` là 'Hotmail'
$products_list = $CMSNT->get_list_safe("SELECT * FROM `products` WHERE `check_live` = ?", ['Hotmail']);

if (!empty($products_list)) {
    $product_codes = array_map(function($product) {
        return $product['code'];
    }, $products_list);

    // Chuyển đổi các mã sản phẩm thành chuỗi để sử dụng trong mệnh đề IN
    $product_codes_str = implode("','", array_map('addslashes', $product_codes));
    $where_is_checklive = " AND `product_code` IN ('$product_codes_str')";
}else{
    die('Không có sản phẩm nào bật check live');
}

$limit = intval($CMSNT->site('limit_check_live_clone')) ?? 500;
$products = $CMSNT->get_list_safe("SELECT * FROM `product_stock` WHERE `id` > ? $where_is_checklive ORDER BY `time_check_live` ASC LIMIT ? ", [0, $limit]);

// Lặp qua danh sách sản phẩm để tạo danh sách UIDs
foreach ($products as $product) {
    // Kiểm tra xem UID đã tồn tại trong mảng UIDs chưa, nếu chưa thì thêm vào
    if (!in_array(check_string($product['account']), $uids)) {
        $uids[] = check_string($product['account']);
    }
    // Lưu thông tin sản phẩm vào mảng để sử dụng sau này
    $products_info[check_string($product['account'])] = $product;
}

// Khởi tạo multi-curl handler
$mh = curl_multi_init();
$curl_handles = [];

// Lặp qua danh sách UIDs để tạo các xử lý cURL riêng lẻ cho mỗi UID
foreach ($uids as $uid) {
    $ch = curl_init();
    $url = base_url('api/checklive_hotmail.php?account='.$uid);
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true
    ]);
    curl_multi_add_handle($mh, $ch);
    $curl_handles[$uid] = $ch;
}

// Thực thi các yêu cầu đồng thời
$running = null;
do {
    curl_multi_exec($mh, $running);
} while ($running > 0);


// Lặp qua các xử lý cURL để lấy kết quả và xử lý
foreach ($curl_handles as $uid => $ch) {
    $result = curl_multi_getcontent($ch);
    $info = curl_getinfo($ch);
    if ($info['http_code'] == 200) {
        if (isset($result) && $result == 'DIE') {
            // UID die, di chuyển sang bảng product_die
            // Sử dụng giao dịch để đảm bảo tính nhất quán dữ liệu
            $CMSNT->query("START TRANSACTION");
            try {
                // Chèn vào bảng product_die
                $isInsert = $CMSNT->insert('product_die', [
                    'product_code'      => $products_info[$uid]['product_code'],
                    'seller'            => $products_info[$uid]['seller'],
                    'uid'               => $products_info[$uid]['uid'],
                    'account'           => $products_info[$uid]['account'],
                    'create_gettime'    => $products_info[$uid]['create_gettime'],
                    'type'              => $products_info[$uid]['type']
                ]);
                
                if($isInsert){
                    // Xóa khỏi bảng product_stock
                    $CMSNT->remove('product_stock', " `id` = '".$products_info[$uid]['id']."' ");
                    echo "UID: ".substr($uid, 0, 6)."*******, Result: DIE <br>";
                    $CMSNT->query("COMMIT");
                } else {
                    $CMSNT->query("ROLLBACK");
                    echo "UID: ".substr($uid, 0, 6)."*******, Result: DIE | Lỗi: Không thể chèn dữ liệu <br>";
                }
            } catch (Exception $e) {
                $CMSNT->query("ROLLBACK");
                echo "UID: ".substr($uid, 0, 6)."*******, Result: DIE | Lỗi: ".$e->getMessage()." <br>";
            }
        } else {
            // UID live, cập nhật thời gian kiểm tra live
            $CMSNT->update("product_stock", ['time_check_live' => time()], " `id` = '".$products_info[$uid]['id']."' ");
            echo "UID: ".substr($uid, 0, 6)."*******, Result: LIVE<br>";
        }
    } else {
        $CMSNT->update("product_stock", ['time_check_live' => time()], " `id` = '".$products_info[$uid]['id']."' ");
        $error_message = "UID: ".substr($uid, 0, 6)."*******, Result: ERROR";
        echo $error_message . "<br>";
    }
    // Đóng xử lý cURL
    curl_multi_remove_handle($mh, $ch);
    curl_close($ch);
}

// Đóng multi-curl handler
curl_multi_close($mh);
