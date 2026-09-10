<?php

define("IN_SITE", true);
require_once(__DIR__ . "/../libs/db.php");
require_once(__DIR__ . "/../libs/lang.php");
require_once(__DIR__ . "/../libs/session.php");
require_once(__DIR__ . "/../libs/helper.php");
require_once(__DIR__ . "/../config.php");
require_once(__DIR__ . "/../libs/sendEmail.php");
require_once(__DIR__ . "/../libs/database/users.php");

use PragmaRX\Google2FAQRCode\Google2FA;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$CMSNT = new DB();



// Máy chủ phải được bật NTP synchronized mới có thể nhận callback từ google

$client = new Google_Client();
$client->setClientId($CMSNT->site('google_login_client_id')); // Client ID của bạn
$client->setClientSecret($CMSNT->site('google_login_client_secret')); // Client Secret của bạn
$client->setRedirectUri(base_url('api/callback_google_login.php')); // URL callback
$client->addScope("email");
$client->addScope("profile");

// Xử lý callback sau khi Google redirect
if (isset($_GET['code'])) {
    checkBlockIP('LOGIN', 5);
    $errorResponse = function ($message) {
        die(json_encode(['status' => 'error', 'msg' => $message]));
    };

    if (empty($_GET['state']) || empty($_SESSION['google_oauth_state']) || empty($_SESSION['google_oauth_state_expires'])) {
        unset($_SESSION['google_oauth_state'], $_SESSION['google_oauth_state_expires'], $_SESSION['google_oauth_intent']);
        $errorResponse(__('Phiên đăng nhập Google không hợp lệ, vui lòng thử lại.'));
    }

    if (!hash_equals($_SESSION['google_oauth_state'], $_GET['state'])) {
        unset($_SESSION['google_oauth_state'], $_SESSION['google_oauth_state_expires'], $_SESSION['google_oauth_intent']);
        $errorResponse(__('Phiên đăng nhập Google không hợp lệ, vui lòng thử lại.'));
    }

    if (time() > $_SESSION['google_oauth_state_expires']) {
        unset($_SESSION['google_oauth_state'], $_SESSION['google_oauth_state_expires'], $_SESSION['google_oauth_intent']);
        $errorResponse(__('Phiên đăng nhập Google đã hết hạn, vui lòng thử lại.'));
    }

    $oauthIntent = $_SESSION['google_oauth_intent'] ?? null;
    unset($_SESSION['google_oauth_state'], $_SESSION['google_oauth_state_expires'], $_SESSION['google_oauth_intent']);

    //
    $columnGoogleId = $CMSNT->get_row("SHOW COLUMNS FROM `users` LIKE 'google_id'");
    if (!$columnGoogleId) {
        $CMSNT->query("ALTER TABLE `users` ADD COLUMN `google_id` VARCHAR(191) NULL DEFAULT NULL");
    }
    $columnGoogleLinkedAt = $CMSNT->get_row("SHOW COLUMNS FROM `users` LIKE 'google_linked_at'");
    if (!$columnGoogleLinkedAt) {
        $CMSNT->query("ALTER TABLE `users` ADD COLUMN `google_linked_at` DATETIME NULL DEFAULT NULL");
    }
    $indexGoogleId = $CMSNT->get_row("SHOW INDEX FROM `users` WHERE `Key_name` = 'uniq_google_id'");
    if (!$indexGoogleId) {
        $CMSNT->query("ALTER TABLE `users` ADD UNIQUE KEY `uniq_google_id` (`google_id`)");
    }
    //

    // Lấy token từ Google
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    if (isset($token['error'])) {
        $errorResponse(__('Đăng nhập thất bại, vui lòng thử lại sau!'));
    }

    // Xác minh id_token để đảm bảo token hợp lệ
    $payload = $client->verifyIdToken($token['id_token']);
    if (!$payload) {
        $errorResponse(__('Token Google không hợp lệ'));
    }

    // Lấy thông tin từ payload
    $email = $payload['email'];
    $name = $payload['name'];
    $google_id = $payload['sub']; // ID duy nhất từ Google

    $normalizedEmail = validate_email($email);
    if ($normalizedEmail === false) {
        $errorResponse(__('Email Google không hợp lệ'));
    }

    $getUser = $CMSNT->get_row_safe("SELECT * FROM `users` WHERE `google_id` = ? ", [$google_id]);

    if (!$getUser) {
        $getUser = $CMSNT->get_row_safe("SELECT * FROM `users` WHERE `email` = ? ", [$normalizedEmail]);
        if ($getUser && !empty($getUser['google_id']) && $getUser['google_id'] !== $google_id) {
            $errorResponse(__('Email này đã được liên kết với tài khoản Google khác.'));
        }
    }

    if ($getUser) {
        // Nếu email đã tồn tại -> Đăng nhập
        if ($getUser['banned'] == 1) {
            $errorResponse(__('Tài khoản của bạn đã bị khoá truy cập'));
        }
        if ($getUser['status_otp_mail'] == 1) {
            $otp_mail = random('QWERTYUOPASDFGHJKZXCVBNM0126456789', 6);
            $token_otp_mail = md5(uniqid()) . md5(random('QWERTYUOPASDFGHJKZXCVBNM0126456789', 12));
            $CMSNT->update('users', [
                'token_otp_mail'    => $token_otp_mail,
                'otp_mail'          => $otp_mail,
                'limit_otp_mail'    => 0
            ], " `id` = ?", [$getUser['id']]);
            $CMSNT->insert("logs", [
                'user_id'       => $getUser['id'],
                'ip'            => myip(),
                'device'        => getUserAgent(),
                'createdate'    => gettime(),
                'action'        => '[Warning] ' . __('Đăng nhập thành công - đang tiến hành đến bước xác minh OTP Mail')
            ]);
            // THÔNG BÁO VỀ MAIL KHI LOGIN
            if ($CMSNT->site('email_temp_subject_otp_mail') != '') {
                $replacements = [
                    '{domain}' => validate_string($_SERVER['SERVER_NAME'] ?? '', 255) ?: 'localhost',
                    '{title}' => $CMSNT->site('title'),
                    '{username}' => $getUser['username'],
                    '{otp}' => $otp_mail,
                    '{ip}' => myip(),
                    '{device}' => getUserAgent(),
                    '{time}' => gettime()
                ];

                $content = str_replace(array_keys($replacements), array_values($replacements), $CMSNT->site('email_temp_content_otp_mail'));
                $subject = str_replace(array_keys($replacements), array_values($replacements), $CMSNT->site('email_temp_subject_otp_mail'));

                sendCSM($getUser['email'], $getUser['username'], $subject, $content, $CMSNT->site('title'));
            }
            redirect(base_url('?action=verify_otp&token=' . $token_otp_mail));
        }
        if ($getUser['status_2fa'] == 1) {
            $token_2fa = md5(random('qwertyuiopasdfghjklzxcvbnm0123456789', 55)) . md5(uniqid());
            $CMSNT->update('users', [
                'token_2fa' => $token_2fa,
                'limit_2fa' => 0
            ], " `id` = ?", [$getUser['id']]);
            $CMSNT->insert("logs", [
                'user_id'       => $getUser['id'],
                'ip'            => myip(),
                'device'        => getUserAgent(),
                'createdate'    => gettime(),
                'action'        => '[Warning] ' . __('Đăng nhập thành công - đang tiến hành đến bước xác minh 2FA')
            ]);
            redirect(base_url('?action=verify_2fa&token=' . $token_2fa));
        }
        $CMSNT->insert("logs", [
            'user_id'       => $getUser['id'],
            'ip'            => myip(),
            'device'        => getUserAgent(),
            'createdate'    => gettime(),
            'action'        => '[Warning] ' . __('Thực hiện đăng nhập vào website bằng Google')
        ]);
        $remember_token = generateRememberToken($getUser['remember_token'], $getUser['ip']);
        $updateData = [
            'remember_token'    => $remember_token,
            'token_2fa'         => NULL,
            'limit_2fa'         => 0,
            'token_otp_mail'    => NULL,
            'limit_otp_mail'    => 0,
            'otp_mail'          => NULL,
            'ip'                => myip(),
            'time_request'      => time(),
            'time_session'      => time(),
            'device'            => getUserAgent()
        ];
        if (empty($getUser['google_id'])) {
            $updateData['google_id'] = $google_id;
            $updateData['google_linked_at'] = gettime();
            $getUser['google_id'] = $google_id;
        }
        $CMSNT->update("users", $updateData, " `id` = ? ", [$getUser['id']]);
        // THÔNG BÁO VỀ MAIL KHI LOGIN
        if ($getUser['status_noti_login_to_mail'] == 1 && $CMSNT->site('email_temp_subject_warning_login') != '') {
            $content = $CMSNT->site('email_temp_content_warning_login');
            $content = str_replace('{domain}', $_SERVER['SERVER_NAME'], $content);
            $content = str_replace('{title}', $CMSNT->site('title'), $content);
            $content = str_replace('{username}', $getUser['username'], $content);
            $content = str_replace('{ip}', myip(), $content);
            $content = str_replace('{device}', getUserAgent(), $content);
            $content = str_replace('{time}', gettime(), $content);
            ////////////////////////////////////////////////////////////////////
            $subject = $CMSNT->site('email_temp_subject_warning_login');
            $subject = str_replace('{domain}', $_SERVER['SERVER_NAME'], $subject);
            $subject = str_replace('{title}', $CMSNT->site('title'), $subject);
            $subject = str_replace('{username}', $getUser['username'], $subject);
            $subject = str_replace('{ip}', myip(), $subject);
            $subject = str_replace('{device}', getUserAgent(), $subject);
            $subject = str_replace('{time}', gettime(), $subject);
            $bcc = $CMSNT->site('title');
            sendCSM($getUser['email'], $getUser['username'], $subject, $content, $bcc);
        }

        // Tạo phiên đăng nhập
        createSession($getUser['id'], $getUser['token']);

        // Lấy redirect URL từ session nếu có
        $redirect_url = base_url('client/home');
        if (!empty($_SESSION['login_redirect_url'])) {
            $redirect_url = $_SESSION['login_redirect_url'];
            unset($_SESSION['login_redirect_url']);
        }
        redirect($redirect_url);
    } else {
        // Nếu email chưa tồn tại -> Đăng ký tài khoản mới
        $google2fa = new Google2FA();
        $new_token = generateUserToken($normalizedEmail);

        // ✅ Xử lý ref_id an toàn - kiểm tra ref_code hoặc ID (legacy)
        $ref_id = 0;
        if (isset($_COOKIE['aff'])) {
            $aff_value = check_string($_COOKIE['aff']);

            // Kiểm tra nếu là ref_code (không phải số)
            if (!is_numeric($aff_value)) {
                $ref_code = validate_string($aff_value, 10);
                if ($ref_code !== false) {
                    // Lấy user_id từ ref_code
                    $ref_user = $CMSNT->get_row_safe("SELECT `id` FROM `users` WHERE `ref_code` = ?", [$ref_code]);
                    if ($ref_user) {
                        $ref_id = (int)$ref_user['id'];
                    }
                }
            } else {
                // Legacy: Hỗ trợ cookie cũ với ID
                $ref_id = validate_int($aff_value, 1) ?: 0;
            }
        }

        $utm_source = isset($_COOKIE['utm_source']) ? validate_string($_COOKIE['utm_source'], 50) : 'web';
        $utm_source = ($utm_source !== false) ? $utm_source : 'web';

        $isCreate = $CMSNT->insert("users", [
            'ref_id'        => $ref_id,
            'ref_code'      => generateRefCode($CMSNT), // ✅ Tạo ref_code unique
            'utm_source'    => $utm_source,
            'token'         => $new_token,
            'username'      => $normalizedEmail,
            'email'         => $normalizedEmail,
            'password'      => generateApiKey(),
            'ip'            => myip(),
            'device'        => getUserAgent(),
            'create_date'   => gettime(),
            'update_date'   => gettime(),
            'time_session'  => time(),
            'api_key'       => generateApiKey(),
            'SecretKey_2fa' => $google2fa->generateSecretKey(),
            'status_noti_login_to_mail' => 1,
            'google_id'         => $google_id,
            'google_linked_at'  => gettime()
        ]);

        if ($isCreate) {
            $CMSNT->insert("logs", [
                'user_id'       => $isCreate,
                'ip'            => myip(),
                'device'        => getUserAgent(),
                'createdate'    => gettime(),
                'action'        => __('Đăng ký tài khoản mới')
            ]);
            // Tạo phiên đăng nhập
            createSession($isCreate, $new_token);

            // Lấy redirect URL từ session nếu có, mặc định về client/order cho user mới
            $redirect_url = base_url('client/order');
            if (!empty($_SESSION['login_redirect_url'])) {
                $redirect_url = $_SESSION['login_redirect_url'];
                unset($_SESSION['login_redirect_url']);
            }
            redirect($redirect_url);
        } else {
            $errorResponse(__('Không thể tạo tài khoản mới'));
        }
    }
}
