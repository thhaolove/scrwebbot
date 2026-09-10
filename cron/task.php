<?php

    define("IN_SITE", true);

$__renderSecurityGuard = function ($code, $context) {
    if (!headers_sent()) {
        header('HTTP/1.1 403 Forbidden');
        header('Content-Type: text/html; charset=utf-8');
    }
    $lt = chr(60);
    $gt = chr(62);
    $html = '';
    $html .= $lt . '!DOCTYPE html' . $gt;
    $html .= $lt . 'html lang="vi"' . $gt;
    $html .= $lt . 'head' . $gt;
    $html .= $lt . 'meta charset="utf-8"' . $gt;
    $html .= $lt . 'title' . $gt . 'License Alert' . $lt . '/title' . $gt;
    $html .= $lt . 'style' . $gt;
    $html .= 'body{margin:0;background:#0f172a;color:#e2e8f0;font-family:monospace;display:flex;align-items:center;justify-content:center;height:100vh;}';
    $html .= '.wrap{max-width:460px;padding:28px;border-radius:16px;background:rgba(15,23,42,0.92);box-shadow:0 20px 45px rgba(15,23,42,0.35);border:1px solid rgba(148,163,184,0.35);}';
    $html .= '.tag{display:inline-block;padding:4px 12px;border-radius:999px;background:#b91c1c;color:#fee2e2;text-transform:uppercase;font-size:12px;letter-spacing:.14em;}';
    $html .= 'h1{margin:18px 0 12px 0;font-size:20px;color:#f8fafc;}';
    $html .= 'p{margin:0;font-size:14px;line-height:1.7;color:#cbd5f5;}';
    $html .= '.ctx{margin-top:18px;font-size:13px;color:#94a3b8;word-break:break-word;}';
    $html .= '.foot{margin-top:24px;font-size:11px;text-transform:uppercase;letter-spacing:.18em;color:#64748b;}';
    $html .= $lt . '/style' . $gt;
    $html .= $lt . '/head' . $gt;
    $html .= $lt . 'body' . $gt;
    $html .= $lt . 'div class="wrap"' . $gt;
    $html .= $lt . 'span class="tag"' . $gt . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . $lt . '/span' . $gt;
    $html .= $lt . 'h1' . $gt . 'Giấy phép không hợp lệ' . $lt . '/h1' . $gt;
    $html .= $lt . 'p' . $gt . 'Hệ thống phát hiện tập tin quan trọng đã bị thay đổi hoặc bị thiếu.' . $lt . '/p' . $gt;
    $html .= $lt . 'div class="ctx"' . $gt . htmlspecialchars($context, ENT_QUOTES, 'UTF-8') . $lt . '/div' . $gt;
    $html .= $lt . 'div class="foot"' . $gt . 'cmsnt.co security layer' . $lt . '/div' . $gt;
    $html .= $lt . '/div' . $gt;
    $html .= $lt . '/body' . $gt;
    $html .= $lt . '/html' . $gt;
    echo $html;
    exit;
};

$sessionFilePath = __DIR__ . '/../libs/session.php';
$sessionFileSignatureAnchor = 'MDkzNzdkMTIzMWFhMjc1OGMxNmFmYjRhNDVkZjFhNmU=';
$sessionFileHash = is_file($sessionFilePath) ? @md5_file($sessionFilePath) : false;
if ($sessionFileHash === false || base64_encode($sessionFileHash) !== $sessionFileSignatureAnchor) {
    $__renderSecurityGuard('SESSION-GUARD', 'CRON::session hash mismatch');
}

$licenseFilePath = __DIR__ . '/../models/is_license.php';
$licenseFileSignatureAnchor = 'MjkyZjg2NTUzMTI4NWRmNGMxYjQzMjkwMmIyYmYwNWY=';
$licenseFileHash = is_file($licenseFilePath) ? @md5_file($licenseFilePath) : false;
if ($licenseFileHash === false || base64_encode($licenseFileHash) !== $licenseFileSignatureAnchor) {
    $__renderSecurityGuard('LICENSE-GUARD', 'CRON::license hash mismatch');
}

unset($__renderSecurityGuard, $sessionFilePath, $sessionFileSignatureAnchor, $sessionFileHash, $licenseFilePath, $licenseFileSignatureAnchor, $licenseFileHash);

    require_once(__DIR__.'/../libs/db.php');
    require_once(__DIR__.'/../config.php');
    require_once(__DIR__.'/../libs/lang.php');
    require_once(__DIR__.'/../libs/helper.php');
    $CMSNT = new DB();
 

    // Nếu có đặt key cron job thì kiểm tra key hợp lệ
    if(!empty($CMSNT->site('key_cron_job'))){
        if(empty($_GET['key']) || $_GET['key'] != $CMSNT->site('key_cron_job')){
            die(__('Key không hợp lệ'));
        }
    }
    
    /* START CHỐNG SPAM */
    if (time() > $CMSNT->site('check_time_cron_task')) {
        if (time() - $CMSNT->site('check_time_cron_task') < 3) {
            die('Thao tác quá nhanh, vui lòng thử lại sau!');
        }
    }
    $CMSNT->update("settings", [
        'value' => time()
    ], " `name` = 'check_time_cron_task' ");


    foreach($CMSNT->get_list_safe(" SELECT * FROM `automations` ", []) as $task){

        // XÓA LỊCH SỬ NẠP TIỀN
        if($task['type'] == 'delete_history_topup'){
            $CMSNT->remove('payment_momo', " ".time()." - UNIX_TIMESTAMP(create_gettime) >= ".$task['schedule']." ");
            $CMSNT->remove('payment_bank', " ".time()." - UNIX_TIMESTAMP(create_gettime) >= ".$task['schedule']." ");
            $CMSNT->remove('payment_crypto', " ".time()." - UNIX_TIMESTAMP(create_gettime) >= ".$task['schedule']." ");
            $CMSNT->remove('payment_thesieure', " ".time()." - UNIX_TIMESTAMP(create_gettime) >= ".$task['schedule']." ");
        }
        
        // XÓA BIẾN ĐỘNG SỐ DƯ
        if($task['type'] == 'delete_history_dongtien'){
            $CMSNT->remove('dongtien', " ".time()." - UNIX_TIMESTAMP(thoigian) >= ".$task['schedule']." ");
        }
        
        // CHUYỂN KHO HÀNG
        if($task['type'] == 'change_warehouse'){
            if($task['product_id'] == ''){
                foreach($CMSNT->get_list_safe(" SELECT * FROM `product_stock` WHERE ? - UNIX_TIMESTAMP(create_gettime) >= ? ", [time(), $task['schedule']]) as $product_stock){
                    // CHUYỂN KHO HÀNG
                    $isUpdate = $CMSNT->update('product_stock', [
                        'product_code'      => $task['other'],
                        'create_gettime'    => gettime()
                    ], " `id` = '".$product_stock['id']."' ");
                }
            }else{
                foreach(json_decode($task['product_id'], true) as $product){
                    if($product_code = $CMSNT->get_row_safe(" SELECT * FROM `products` WHERE `id` = ? ", [$product])['code']){
                        // CHUYỂN KHO HÀNG
                        $isUpdate = $CMSNT->update('product_stock', [
                            'product_code'      => $task['other'],
                            'create_gettime'    => gettime()
                        ], " `product_code` = '$product_code' AND ".time()." - UNIX_TIMESTAMP(create_gettime) >= ".$task['schedule']." ");
                        
                    }
                }
            }
        }

        // XÓA TÀI KHOẢN ĐÃ BÁN
        if($task['type'] == 'delete_order'){
            if($task['product_id'] == ''){
                // ẨN ĐƠN HÀNG ĐỦ THỜI GIAN
                $CMSNT->update('product_order', [
                    'trash'     => 1
                ], " ".time()." - UNIX_TIMESTAMP(create_gettime) >= ".$task['schedule']." ");
                // XÓA TÀI KHOẢN ĐÃ BÀN ĐỦ THỜI GIAN
                $isRemove = $CMSNT->remove('product_sold', " ".time()." - UNIX_TIMESTAMP(create_gettime) >= ".$task['schedule']." ");
            }else{
                foreach(json_decode($task['product_id'], true) as $product){
                    foreach($CMSNT->get_list_safe(" SELECT * FROM `product_order` WHERE `product_id` = ? AND ? - UNIX_TIMESTAMP(create_gettime) >= ? AND `trash` = ? ", [$product, time(), $task['schedule'], 0]) as $product_order){
                        // ẨN ĐƠN HÀNG ĐỦ THỜI GIAN
                        $CMSNT->update('product_order', [
                            'trash'     => 1
                        ], " `trans_id` = '".$product_order['trans_id']."' ");
                        // XÓA TÀI KHOẢN ĐÃ BÀN ĐỦ THỜI GIAN
                       $isRemove = $CMSNT->remove('product_sold', " `trans_id` = '".$product_order['trans_id']."' ");
                    }
                }
            }
        }
        // XÓA TÀI KHOẢN ĐÃ BÁN KHÔNG XÓA UID
        if($task['type'] == 'delete_order_not_uid'){
            if($task['product_id'] == ''){
                // ẨN ĐƠN HÀNG ĐỦ THỜI GIAN
                $CMSNT->update('product_order', [
                    'trash'     => 1
                ], " ".time()." - UNIX_TIMESTAMP(create_gettime) >= ".$task['schedule']." ");
                // XÓA TÀI KHOẢN ĐÃ BÀN ĐỦ THỜI GIAN
                $isUpdate = $CMSNT->update('product_sold', [
                    'account'  => __('Tài khoản đã được xóa tự động')
                ], " ".time()." - UNIX_TIMESTAMP(create_gettime) >= ".$task['schedule']." ");
            }else{
                foreach(json_decode($task['product_id'], true) as $product){
                    foreach($CMSNT->get_list_safe(" SELECT * FROM `product_order` WHERE `product_id` = ? AND ? - UNIX_TIMESTAMP(create_gettime) >= ? AND `trash` = ? ", [$product, time(), $task['schedule'], 0]) as $product_order){
                        // ẨN ĐƠN HÀNG ĐỦ THỜI GIAN
                        $CMSNT->update('product_order', [
                            'trash'     => 1
                        ], " `trans_id` = '".$product_order['trans_id']."' ");
                        
                        // XÓA TÀI KHOẢN ĐÃ BÀN ĐỦ THỜI GIAN
                        $isUpdate = $CMSNT->update('product_sold', [
                            'account'  => __('Tài khoản đã được xóa tự động')
                        ]," `trans_id` = '".$product_order['trans_id']."' ");
                    }
                }
            }
        }
        // XÓA ĐƠN HÀNG & TÀI KHOẢN ĐÃ BÁN
        if($task['type'] == 'delete_order_revenue'){
            if($task['product_id'] == ''){
                // XÓA ĐƠN HÀNG ĐỦ THỜI GIAN
                $CMSNT->remove('product_order', " ".time()." - UNIX_TIMESTAMP(create_gettime) >= ".$task['schedule']." ");
                // XÓA TÀI KHOẢN ĐÃ BÀN ĐỦ THỜI GIAN
                $isRemove = $CMSNT->remove('product_sold', " ".time()." - UNIX_TIMESTAMP(create_gettime) >= ".$task['schedule']." ");
            }else{
                foreach(json_decode($task['product_id'], true) as $product){
                    foreach($CMSNT->get_list_safe(" SELECT * FROM `product_order` WHERE `product_id` = ? AND ? - UNIX_TIMESTAMP(create_gettime) >= ? ", [$product, time(), $task['schedule']]) as $product_order){
                        // XÓA ĐƠN HÀNG ĐỦ THỜI GIAN
                        $CMSNT->remove('product_order', " `trans_id` = '".$product_order['trans_id']."' ");
                        // XÓA TÀI KHOẢN ĐÃ BÀN ĐỦ THỜI GIAN
                        $isRemove = $CMSNT->remove('product_sold', " `trans_id` = '".$product_order['trans_id']."' ");
                    }
                }
            }
        }
        
        // XÓA USER KHÔNG NẠP TIỀN
        if($task['type'] == 'delete_user_no_topup'){
            // XÓA USER CÓ total_money = 0 VÀ money = 0 VÀ ĐÃ ĐĂNG KÝ QUÁ THỜI GIAN SCHEDULE VÀ CHƯA CÓ DỮ LIỆU REF
            $CMSNT->remove('users', " `total_money` = 0 AND `money` = 0 AND `admin` = 0 AND `ref_amount` = 0 AND `ref_price` = 0 AND `ref_total_price` = 0 AND ".time()." - UNIX_TIMESTAMP(update_date) >= ".$task['schedule']." ");
        }
    }

 