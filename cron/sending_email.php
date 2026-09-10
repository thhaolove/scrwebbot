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
    require_once(__DIR__.'/../libs/helper.php');
    require_once(__DIR__.'/../libs/lang.php');
    require_once(__DIR__.'/../libs/sendEmail.php');
    $CMSNT = new DB();


    // Nếu có đặt key cron job thì kiểm tra key hợp lệ
    if(!empty($CMSNT->site('key_cron_job'))){
        if(empty($_GET['key']) || $_GET['key'] != $CMSNT->site('key_cron_job')){
            die(__('Key không hợp lệ'));
        }
    }
    
    /* START CHỐNG SPAM */
    if (time() > $CMSNT->site('check_time_cron_sending_email')) {
        if (time() - $CMSNT->site('check_time_cron_sending_email') < 3) {
            die('Thao tác quá nhanh, vui lòng đợi');
        }
    }

    $CMSNT->update("settings", [
        'value' => time()
    ], " `name` = 'check_time_cron_sending_email' ");


    if($CMSNT->site('smtp_status') != 1){
        die('Vui lòng cấu hình SMTP');
    }
    if($CMSNT->site('smtp_email') == '' || $CMSNT->site('smtp_password') == ''){
        die('Vui lòng cấu hình SMTP');
    }
    $arrContextOptions=array(
        "ssl"=>array(
            "verify_peer"=>false,
            "verify_peer_name"=>false,
        ),
    ); 

    $checkAddon = true;
    
    foreach($CMSNT->get_list_safe(" SELECT * FROM `email_campaigns` WHERE `status` = ? ", [0]) as $camp){

        foreach($CMSNT->get_list_safe(" SELECT * FROM `email_sending` WHERE `camp_id` = ? AND `status` = ? ORDER BY id ASC LIMIT ? ", [$camp['id'], 0, 20]) as $row){

            $content = $camp['content'];
            $title = $camp['subject'];
            $content_email = file_get_contents(base_url('libs/mails/notification.php'), false, stream_context_create($arrContextOptions));
            $content_email = str_replace('{title}', $title, $content_email);
            $content_email = str_replace('{content}', $content, $content_email);
            $email = getRowRealtime('users', $row['user_id'], 'email');
            $response = 'Vui lòng kích hoạt Addon này';
            $status = 2;
            
            if($email == ''){
                $response = 'Không tìm thấy Email người nhận';
                $status = 2;
            } else {
                $response = sendCSM($email, $camp['cc'], $title, $content_email, $camp['bcc']);
                
                // Kiểm tra nếu lỗi liên quan đến giới hạn SMTP
                if (strpos($response, 'SMTP đạt giới hạn') !== false || 
                    strpos($response, 'quota') !== false || 
                    strpos($response, 'limit') !== false) {
                    // Giữ nguyên trạng thái = 0 để có thể thử lại sau
                    $status = 0;
                } else if ($response === true) {
                    // Gửi thành công
                    $status = 1;
                } else {
                    // Các lỗi khác
                    $status = 2;
                }
            }

            $CMSNT->update('email_sending', [
                'status'            => $status,
                'update_gettime'    => gettime(),
                'response'          => $response
            ], " `id` = '".$row['id']."' ");
        }



        
        if(!$CMSNT->get_row_safe(" SELECT * FROM `email_sending` WHERE `camp_id` = ? AND `status` = ? ", [$camp['id'], 0])){
            $CMSNT->update('email_campaigns', [
                'status'            => 1,
                'update_gettime'    => gettime()
            ], " `id` = '".$camp['id']."' ");
        }


    }
 


