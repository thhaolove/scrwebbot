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
    if (time() > $CMSNT->site('check_time_cron_cron')) {
        if (time() - $CMSNT->site('check_time_cron_cron') < 3) {
            die('Thao tác quá nhanh, vui lòng thử lại sau!');
        }
    }
    $CMSNT->update("settings", [
        'value' => time()
    ], " `name` = 'check_time_cron_cron' ");


    // Task chỉ xử lý mỗi 24 giờ
    if (time() > $CMSNT->site('task_24h')) {
        if (time() - $CMSNT->site('task_24h') > 86400) {
            $CMSNT->update("settings", [
                'value' => time()
            ], " `name` = 'task_24h' ");

            // Dọn dẹp failed_attempts
            $isRemove = $CMSNT->remove('failed_attempts', " `create_gettime` <= NOW() - INTERVAL 1 DAY ");
            if($isRemove){
                $CMSNT->insert("logs", [
                    'user_id'     => 0, // 0 = log hệ thống
                    'action'      => "Hệ thống thực hiện dọn dẹp failed_attempts sau mỗi 24 giờ",
                    'createdate'  => gettime(),
                    'ip'          => myip(),
                    'device'      => getUserAgent()
                ]);
            }

            // Xóa file CMSNT.CO thừa
            if(is_dir(__DIR__.'/../CMSNT.CO')){
                deleteFolder(__DIR__.'/../CMSNT.CO');
                
                $CMSNT->insert("logs", [
                    'user_id'     => 0, // 0 = log hệ thống
                    'action'      => "Hệ thống thực hiện xóa file rác",
                    'createdate'  => gettime(),
                    'ip'          => myip(),
                    'device'      => getUserAgent()
                ]);
            }
        }
    }


    if($CMSNT->site('status_tao_gd_ao') == 1){
        /** NẠP TIỀN ẢO */
        $int_rand = rand(0, $CMSNT->site('toc_do_gd_nap_ao'));
        if($int_rand == $CMSNT->site('toc_do_gd_nap_ao')){
            $array_amount = explode(PHP_EOL, $CMSNT->site('menh_gia_nap_ao_ngau_nhien'));
            $array_method = explode(PHP_EOL, $CMSNT->site('method_nap_ao'));
            $amount = $array_amount[rand(0, count($array_amount)-1)];
            $amount = $amount != 0 ? $amount : 10000;
            $method = $array_method[rand(0, count($array_method)-1)];
            $CMSNT->insert("deposit_log", [
                'user_id'           => $CMSNT->get_row_safe("SELECT * FROM `users` ORDER BY RAND() LIMIT 1", [])['id'],
                'method'            => $method,
                'amount'            => (float)$amount,
                'received'          => (float)$amount,
                'create_time'       => time(),
                'is_virtual'        => 1
            ]);
        }
        /** MUA HÀNG ẢO */
        $int_rand = rand(0, $CMSNT->site('toc_do_gd_mua_ao'));
        if($int_rand == $CMSNT->site('toc_do_gd_mua_ao')){
            $amount = rand($CMSNT->site('sl_mua_toi_thieu_gd_ao'), $CMSNT->site('sl_mua_toi_da_gd_ao'));
            $trans_id = random("QWERTYUPASDFGHJKZXCVBNM123456789", 4);
            foreach($CMSNT->get_list_safe("SELECT * FROM `products` WHERE `status` = ? ORDER BY RAND() ", [1]) as $product){
                if($CMSNT->site('tao_gd_ao_sp_het_hang') == 1){
                    $stock = $product['supplier_id'] != 0 ? $product['api_stock'] : getStock($product['code']);
                    if($stock == 0){
                        continue;
                    }
                }
                // TẠO LOG GIAO DỊCH GẦN ĐÂY
                $CMSNT->insert('order_log',[
                    'buyer'         => $CMSNT->get_row_safe("SELECT * FROM `users` ORDER BY RAND() LIMIT 1", [])['id'],
                    'product_name'  => $product['name'],
                    'pay'           => $amount * $product['price'],
                    'amount'        => $amount,
                    'create_time'   => time(),
                    'is_virtual'    => 1
                ]);
                break;  
            }
        }
    }

    $CMSNT->remove('deposit_log', " ".time()." - `create_time` >= 604800 ");
    $CMSNT->remove('order_log', " ".time()." - `create_time` >= 604800 ");



    // Thêm các URL của trang web của bạn vào mảng này
    $urls = array();
    $urls[] = base_url('tool/check-live-facebook');
    $urls[] = base_url('tool/get-2fa');
    $urls[] = base_url('tool/icon-facebook');
    $urls[] = base_url('tool/random-face');

    foreach($CMSNT->get_list_safe(" SELECT * FROM categories WHERE `status` = ? ", [1]) as $category){
        $urls[] = base_url('category/'.$category['slug']);
    }
    foreach($CMSNT->get_list_safe(" SELECT * FROM products WHERE `status` = ? ", [1]) as $product){
        $urls[] = base_url('product/'.$product['slug']);
    }
    foreach($CMSNT->get_list_safe(" SELECT * FROM posts WHERE `status` = ? ", [1]) as $blog){
        $urls[] = base_url('blog/'.$blog['slug']);
    }
    // Tạo tệp XML mới
    $xml = new DOMDocument('1.0', 'UTF-8');
    $xml->formatOutput = true;
    // Tạo phần tử gốc <urlset> cho sitemap
    $urlset = $xml->createElement('urlset');
    $urlset->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
    // Thêm các URL vào phần tử gốc <urlset>
    foreach ($urls as $url) {
        $urlElement = $xml->createElement('url');
        $locElement = $xml->createElement('loc', htmlspecialchars($url));
        $urlElement->appendChild($locElement);
        $urlset->appendChild($urlElement);
    }
    $xml->appendChild($urlset);
    // Lưu sitemap vào một tệp
    $xml->save('../sitemap.xml');