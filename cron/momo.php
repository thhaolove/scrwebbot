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
    require_once(__DIR__.'/../libs/database/users.php');
    $CMSNT = new DB();
    $user = new users();


    // Nếu có đặt key cron job thì kiểm tra key hợp lệ
    if(!empty($CMSNT->site('key_cron_job'))){
        if(empty($_GET['key']) || $_GET['key'] != $CMSNT->site('key_cron_job')){
            die(__('Key không hợp lệ'));
        }
    }

    curl_get2(base_url('cron/cron.php?key='.$CMSNT->site('key_cron_job')));

    if (time() > $CMSNT->site('check_time_cron_momo')) {
        if (time() - $CMSNT->site('check_time_cron_momo') < 5) {
            die('[ÉT O ÉT ] Thao tác quá nhanh, vui lòng đợi');
        }
    }
    $CMSNT->update("settings", ['value' => time()], " `name` = 'check_time_cron_momo' ");

    if($CMSNT->site('momo_status') == 1){
        if($CMSNT->site('momo_token') == ''){
            die('Vui lòng cấu hình Token MOMO');
        }
        $result = curl_get2("https://api.web2m.com/historyapimomo1h/".trim($CMSNT->site('momo_token')));
        $result = json_decode($result, true);
        foreach ($result['momoMsg']['tranList'] as $data) {
            if($data['status'] != 2){
                continue;
            }
            $partnerId      = $data['partnerId'];               // SỐ ĐIỆN THOẠI CHUYỂN
            $description    = $data['comment'];                 // NỘI DUNG CHUYỂN TIỀN
            $tid            = $data['tranId'];                  // MÃ GIAO DỊCH
            $partnerName    = $data['partnerName'];             // TÊN CHỦ VÍ
            $amount         = $data['amount'];                  // SỐ TIỀN CHUYỂN
            $user_id        = parse_order_id($description, $CMSNT->site('prefix_autobank'));         // TÁCH NỘI DUNG CHUYỂN TIỀN
            if($getUser = $CMSNT->get_row_safe(" SELECT * FROM `users` WHERE `id` = ? ", [$user_id])){
                if($CMSNT->num_rows_safe(" SELECT * FROM `payment_momo` WHERE `tid` = ? ", [$tid]) == 0){
                    $received = checkPromotion($amount);
                    $insertSv2 = $CMSNT->insert("payment_momo", array(
                        'tid'               => $tid,
                        'method'            => 'MOMO',
                        'user_id'           => $getUser['id'],
                        'description'       => $description,
                        'amount'            => $amount,
                        'received'          => $received,
                        'create_gettime'    => gettime(),
                        'create_time'       => time()
                    ));
                    if ($insertSv2){
                        $isCong = $user->AddCredits($getUser['id'], $received, "Nạp tiền tự động qua ví MOMO (#$tid - $description - $amount)", $tid);
                        if($isCong){
                            // CỘNG HOA HỒNG
                            if($CMSNT->site('affiliate_status') == 1 && $getUser['ref_id'] != 0){
                                $ck = $CMSNT->site('affiliate_ck');
                                if(getRowRealtime('users', $getUser['ref_id'], 'ref_ck') != 0){
                                    $ck = getRowRealtime('users', $getUser['ref_id'], 'ref_ck');
                                }
                                $price = $received * $ck / 100;
                                $user->AddCommission($getUser['ref_id'], $getUser['id'], $price, __('Hoa hồng thành viên'.' '.$getUser['username']));
                            }
                            // XỬ LÝ TIỀN NỢ NẾU CÓ
                            debit_processing($getUser['id']);
                            // TẠO LOG GIAO DỊCH GẦN ĐÂY
                            $CMSNT->insert('deposit_log',[
                                'user_id'       => $getUser['id'],
                                'method'        => 'MOMO',
                                'amount'        => $amount,
                                'received'      => $received,
                                'create_time'   => time(),
                                'is_virtual'    => 0
                            ]);
                            /** SEND NOTI CHO ADMIN */
                            $my_text = $CMSNT->site('noti_recharge');
                            $my_text = str_replace('{domain}', $_SERVER['SERVER_NAME'], $my_text);
                            $my_text = str_replace('{username}', $getUser['username'], $my_text);
                            $my_text = str_replace('{method}', 'MOMO', $my_text);
                            $my_text = str_replace('{amount}', format_currency($amount), $my_text);
                            $my_text = str_replace('{price}', format_currency($received), $my_text);
                            $my_text = str_replace('{time}', gettime(), $my_text);
                            sendMessAdmin($my_text);
                            echo '[<b style="color:green">-</b>] Xử lý thành công 1 hoá đơn.'.PHP_EOL;
                        }
                    }
                }
            }
        }
    }
    curl_get(base_url('cron/cron.php'));
