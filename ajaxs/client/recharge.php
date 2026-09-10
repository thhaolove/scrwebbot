<?php

define("IN_SITE", true);
require_once(__DIR__ . "/../../libs/db.php");
require_once(__DIR__ . "/../../libs/lang.php");
require_once(__DIR__ . "/../../libs/helper.php");
require_once(__DIR__ . "/../../config.php");
require_once(__DIR__ . '/../../libs/database/users.php');

if ($CMSNT->site('status') != 1) {
    $data = json_encode([
        'status'    => 'error',
        'msg'       => __('Hệ thống đang bảo trì!')
    ]);
    die($data);
}
if (!isset($_POST['action'])) {
    $data = json_encode([
        'status'    => 'error',
        'msg'       => __('The Request Not Found')
    ]);
    die($data);
}

// Kiểm tra CSRF token cho tất cả request
checkCSRFAjax();

if ($_POST['action'] == 'createInvoice') {
    if ($CMSNT->site('bank_status') != 1) {
        die(json_encode(['status' => 'error', 'msg' => __('Chức năng này đang được bảo trì')]));
    }
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập')]));
    }
    $token = validate_string($_POST['token'], 255);
    if ($token === false) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập')]));
    }
    if (!$getUser = $CMSNT->get_row_safe("SELECT * FROM `users` WHERE `token` = ? AND `banned` = 0", [$token])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập')]));
    }
    // Xác thực Captcha
    $captchaResponse = validate_string($_POST['captcha_response'] ?? $_POST['recaptcha'] ?? $_POST['cf-turnstile-response'] ?? '', 2000);
    if ($captchaResponse === false) $captchaResponse = '';
    $captchaResult = verifyCaptchaResponse($captchaResponse, myip(), 'add_invoice_recharge');
    if (!$captchaResult['success']) {
        die(json_encode(['status' => 'error', 'msg' => $captchaResult['error_message']]));
    }
    if (empty($_POST['bank_id'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Phương thức nạp không tồn tại')]));
    }
    $bankId = validate_int($_POST['bank_id'], 1);
    if ($bankId === false) {
        die(json_encode(['status' => 'error', 'msg' => __('Phương thức nạp không tồn tại')]));
    }
    if (!$bank = $CMSNT->get_row_safe("SELECT * FROM `banks` WHERE `id` = ?", [$bankId])) {
        die(json_encode(['status' => 'error', 'msg' => __('Phương thức nạp không tồn tại')]));
    }
    if (empty($_POST['amount'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng nhập số tiền cần nạp')]));
    }
    $amount = validate_float($_POST['amount'], 0.01);
    if ($amount === false) {
        die(json_encode(['status' => 'error', 'msg' => __('Số tiền không hợp lệ')]));
    }
    if ($amount < $CMSNT->site('bank_min')) {
        die(json_encode(['status' => 'error', 'msg' => __('Số tiền nạp tối thiểu phải là') . ' ' . format_currency($CMSNT->site('bank_min'))]));
    }
    if ($amount > $CMSNT->site('bank_max')) {
        die(json_encode(['status' => 'error', 'msg' => __('Số tiền nạp tối đa là') . ' ' . format_currency($CMSNT->site('bank_max'))]));
    }
    $countSameAmount = $CMSNT->get_row_safe("SELECT COUNT(id) as count FROM `payment_bank_invoice` WHERE `user_id` = ? AND `status` = 'waiting' AND `amount` = ?", [$getUser['id'], $amount]);
    if ($countSameAmount['count'] >= 3) {
        die(json_encode(['status' => 'error', 'msg' => __('Bạn đang có nhiều hóa đơn chưa thanh toán')]));
    }
    $countWaiting = $CMSNT->get_row_safe("SELECT COUNT(id) as count FROM `payment_bank_invoice` WHERE `user_id` = ? AND `status` = 'waiting'", [$getUser['id']]);
    if ($countWaiting['count'] >= 10) {
        die(json_encode(['status' => 'error', 'msg' => __('Bạn đang có nhiều hóa đơn chưa thanh toán')]));
    }

    // Tính số tiền thực nhận với khuyến mãi ngân hàng
    $received = calculateCryptoReceivedAmount($amount, $CMSNT->site('bank_promotions'));

    // Tạo nội dung nạp dựa vào điều kiện
    if ($CMSNT->site('random_content') == 'string') {
        do {
            $trans_id = $CMSNT->site('prefix_autobank') . random('QWERTYUOPASDFGHJKLZXCVBNM', intval($CMSNT->site('bank_random_length')));
        } while ($CMSNT->get_row_safe("SELECT COUNT(id) as count FROM `payment_bank_invoice` WHERE `trans_id` = ?", [$trans_id])['count'] > 0);
    } elseif ($CMSNT->site('random_content') == 'string_number') {
        do {
            $trans_id = $CMSNT->site('prefix_autobank') . random('123456789QWERTYUOPASDFGHJKLZXCVBNM', intval($CMSNT->site('bank_random_length')));
        } while ($CMSNT->get_row_safe("SELECT COUNT(id) as count FROM `payment_bank_invoice` WHERE `trans_id` = ?", [$trans_id])['count'] > 0);
    } else {
        do {
            $trans_id = $CMSNT->site('prefix_autobank') . random('123456789', intval($CMSNT->site('bank_random_length')));
        } while ($CMSNT->get_row_safe("SELECT COUNT(id) as count FROM `payment_bank_invoice` WHERE `trans_id` = ?", [$trans_id])['count'] > 0);
    }
    //

    $isInsert = $CMSNT->insert('payment_bank_invoice', [
        'trans_id'      => $trans_id,
        'user_id'       => $getUser['id'],
        'bank_id'       => $bank['id'],
        'short_name'    => $bank['short_name'],
        'amount'        => $amount,
        'received'      => $received,
        'create_time'   => time()
    ]);
    if ($isInsert) {
        die(json_encode([
            'status'    => 'success',
            'msg'       => __('Tạo hóa đơn thành công'),
            'payment_url'      => base_url('payment/' . $trans_id)
        ]));
    } else {
        die(json_encode(['status' => 'error', 'msg' => __('Tạo hóa đơn thất bại')]));
    }
}


if ($_POST['action'] == 'getInvoice') {
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập')]));
    }
    $token = validate_string($_POST['token'], 255);
    if ($token === false) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập')]));
    }
    if (!$getUser = $CMSNT->get_row_safe("SELECT * FROM `users` WHERE `token` = ? AND `banned` = 0", [$token])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập')]));
    }
    if (empty($_POST['trans_id'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Mã hóa đơn không tồn tại')]));
    }
    $transId = validate_string($_POST['trans_id'], 100);
    if ($transId === false) {
        die(json_encode(['status' => 'error', 'msg' => __('Mã hóa đơn không tồn tại')]));
    }
    if (!$invoice = $CMSNT->get_row_safe("SELECT * FROM `payment_bank_invoice` WHERE `trans_id` = ? AND `user_id` = ?", [$transId, $getUser['id']])) {
        die(json_encode(['status' => 'error', 'msg' => __('Mã hóa đơn không tồn tại')]));
    }
    die(json_encode(['status' => 'success', 'msg' => __('Lấy hóa đơn thành công'), 'invoice' => [
        'trans_id'      => $invoice['trans_id'],
        'status'        => $invoice['status'],
        'amount'        => format_currency($invoice['amount']),
        'received'      => format_currency($invoice['received'])
    ]]));
}

if ($_POST['action'] == 'getReceivedCrypto') {
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập')]));
    }
    $token = validate_string($_POST['token'], 255);
    if ($token === false) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập')]));
    }
    if (!$getUser = $CMSNT->get_row_safe("SELECT * FROM `users` WHERE `token` = ? AND `banned` = 0", [$token])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập')]));
    }
    if (empty($_POST['amount'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng nhập số tiền cần nạp')]));
    }
    $amount = validate_float($_POST['amount'], 0.01);
    if ($amount === false) {
        die(json_encode(['status' => 'error', 'msg' => __('Số tiền không hợp lệ')]));
    }
    $price = floatval($CMSNT->site('crypto_rate'));
    $received = $price * $amount;  // Số tiền thực nhận ban đầu
    $received = checkPromotion($received); // Bao gồm khuyến mãi nạp tiền
    die(json_encode(['status' => 'success', 'msg' => __('Lấy số tiền thực nhận thành công'), 'received' => format_currency($received)]));
}

if ($_POST['action'] == 'getReceivedBank') {
    if (empty($_POST['amount'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng nhập số tiền cần nạp')]));
    }
    $amount = validate_float($_POST['amount'], 0.01);
    if ($amount === false) {
        die(json_encode(['status' => 'error', 'msg' => __('Số tiền không hợp lệ')]));
    }
    // Tính số tiền thực nhận với khuyến mãi ngân hàng
    $received = calculateCryptoReceivedAmount($amount, $CMSNT->site('bank_promotions'));
    die(json_encode(['status' => 'success', 'msg' => __('Lấy số tiền thực nhận thành công'), 'received' => format_currency($received)]));
}

if ($_POST['action'] == 'getReceivedCard') {
    if (empty($_POST['amount'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng nhập số tiền cần nạp')]));
    }
    $amount = validate_float($_POST['amount'], 0.01);
    if ($amount === false) {
        die(json_encode(['status' => 'error', 'msg' => __('Số tiền không hợp lệ')]));
    }
    // Tính số tiền thực nhận với khuyến mãi thẻ cào
    $received = calculateCryptoReceivedAmount($amount, $CMSNT->site('card_promotions'));
    die(json_encode(['status' => 'success', 'msg' => __('Lấy số tiền thực nhận thành công'), 'received' => format_currency($received)]));
}

if ($_POST['action'] == 'RechargeCryptoNew') {
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Please log in')]));
    }
    $token = validate_string($_POST['token'], 255);
    if ($token === false) {
        die(json_encode(['status' => 'error', 'msg' => __('Please log in')]));
    }
    if (!$getUser = $CMSNT->get_row_safe("SELECT * FROM `users` WHERE `token` = ? AND `banned` = 0", [$token])) {
        die(json_encode(['status' => 'error', 'msg' => __('Please log in')]));
    }
    // Xác thực Captcha
    $captchaResponse = validate_string($_POST['captcha_response'] ?? $_POST['recaptcha'] ?? $_POST['cf-turnstile-response'] ?? '', 2000);
    if ($captchaResponse === false) {
        $captchaResponse = '';
    }
    $captchaResult = verifyCaptchaResponse($captchaResponse, myip(), 'add_invoice_recharge');
    if (!$captchaResult['success']) {
        die(json_encode(['status' => 'error', 'msg' => $captchaResult['error_message']]));
    }
    if (empty($_POST['amount'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Please enter amount')]));
    }
    $amount = validate_float($_POST['amount'], 0.01);
    if ($amount === false) {
        die(json_encode(['status' => 'error', 'msg' => __('Deposit amount is not available')]));
    }
    if ($amount < $CMSNT->site('crypto_min')) {
        die(json_encode(['status' => 'error', 'msg' => __('The minimum deposit amount is:') . ' $' . $CMSNT->site('crypto_min')]));
    }
    if ($amount > $CMSNT->site('crypto_max')) {
        die(json_encode(['status' => 'error', 'msg' => __('The maximum deposit amount is:') . ' $' . format_cash($CMSNT->site('crypto_max'))]));
    }
    if ($CMSNT->site('crypto_status') != 1) {
        die(json_encode(['status' => 'error', 'msg' => __('This function is under maintenance')]));
    }
    if ($CMSNT->site('crypto_merchant_id') == '' || $CMSNT->site('crypto_api_key') == '') {
        die(json_encode(['status' => 'error', 'msg' => __('Chức năng này chưa được cấu hình, vui lòng liên hệ Admin')]));
    }
    $countSameAmount = $CMSNT->get_row_safe("SELECT COUNT(*) as count FROM `payment_crypto` WHERE `user_id` = ? AND `status` = 'waiting' AND ROUND(`amount`) = ?", [$getUser['id'], $amount]);
    if ($countSameAmount['count'] >= 3) {
        die(json_encode(['status' => 'error', 'msg' => __('Please do not SPAM')]));
    }
    $countWaiting = $CMSNT->get_row_safe("SELECT COUNT(*) as count FROM `payment_crypto` WHERE `user_id` = ? AND `status` = 'waiting'", [$getUser['id']]);
    if ($countWaiting['count'] >= 10) {
        die(json_encode(['status' => 'error', 'msg' => __('Please do not SPAM')]));
    }
    $request_id = md5(time() . random('qwertyuiopasdfghjklzxcvbnm0123456789', 5));

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://app.fpayment.net/api/AddInvoice",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array(
            'merchant_id'     => $CMSNT->site('crypto_merchant_id'),                          // Không được để lộ thông tin này
            'api_key'         => $CMSNT->site('crypto_api_key'),                 // Không được để lộ thông tin này
            'name'            => 'Recharge ' . (validate_string($_SERVER['HTTP_HOST'] ?? '', 255) ?: 'localhost'),                         // Tên hóa đơn ví dụ như: Nạp tiền vào website abc.xyz
            'description'     => 'Recharge invoice to ' . $getUser['username'],                  // Mô tả hóa đơn ví dụ như: Username hoặc Email của user tạo hóa đơn
            'amount'          => $amount,                                   // Số tiền user muốn nạp vào hệ thống bạn
            'request_id'      => $request_id,                // Mã giao dịch bí mật của hệ thống bạn dùng để so sánh giao dịch của user nào
            'callback_url'    => base_url('api/callback_crypto_new.php'),     // Liên kết dùng nhận kết quả giao dịch
            'success_url'     => base_url('client/recharge-crypto'),      // Liên kết khi người dùng nhấn nút Return to Website khi hóa đơn xử lý thành công
            'cancel_url'      => base_url('client/recharge-crypto')        // Liên kết khi người dùng nhấn nút Return to Website khi hóa đơn hết hạn
        ),
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    $result = json_decode($response, true);
    if (!isset($result['status'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng liên hệ Admin FPAYMENT khắc phục vấn đề này nếu bạn là Admin website.')]));
    }
    // Xử lý kết quả cURL sau đó lưu vào Database để đem ra xử lý khi có callback
    if ($result['status'] == 'success') {
        // Tạo hóa đơn thành công, lưu thông tin hóa đơn vào Database
        $trans_id       = validate_string($result['data']['trans_id'] ?? '', 100) ?: '';
        $amount         = validate_float($result['data']['amount'] ?? 0, 0.01) ?: 0;
        $status         = validate_string($result['data']['status'] ?? '', 50) ?: 'waiting';
        $url_payment    = validate_string($result['data']['url_payment'] ?? '', 500) ?: '';

        // Đoạn code lưu thông tin hóa đơn vào database
        $received = calculateCryptoReceivedAmount($amount, $CMSNT->site('crypto_promotions'));
        $received = $received * $CMSNT->site('crypto_rate');
        $isInsert = $CMSNT->insert('payment_crypto', [
            'trans_id'          => $trans_id,
            'user_id'           => $getUser['id'],
            'request_id'        => $request_id,
            'amount'            => $amount,
            'received'          => $received,
            'create_gettime'    => gettime(),
            'update_gettime'    => gettime(),
            'status'            => $status,
            'url_payment'       => $url_payment,
            'msg'               => NULL
        ]);
        if ($isInsert) {
            $CMSNT->insert("logs", [
                'user_id'       => $getUser['id'],
                'ip'            => myip(),
                'device'        => getUserAgent(),
                'createdate'    => gettime(),
                'action'        => __('Tạo hóa đơn nạp tiền điện tử') . ' #' . $trans_id
            ]);
            // Rate limit
            checkBlockIP('PAYMENT', 5);
            die(json_encode([
                'url'       => $url_payment,
                'status'    => 'success',
                'msg'       => __('Tạo hóa đơn nạp tiền thành công')
            ]));
        } else {
            die(json_encode([
                'status'    => 'error',
                'msg'       => __('Tạo hóa đơn nạp tiền thất bại')
            ]));
        }
    } else {
        die(json_encode(['status' => 'error', 'msg' => __($result['msg'])]));
    }
}

if ($_POST['action'] == 'recharge_card') {
    if ($CMSNT->site('card_status') != 1) {
        die(json_encode(['status' => 'error', 'msg' => __('Chức năng nạp thẻ đang được tắt')]));
    }
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập')]));
    }
    $token = validate_string($_POST['token'], 255);
    if ($token === false) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập')]));
    }
    if (!$getUser = $CMSNT->get_row_safe("SELECT * FROM `users` WHERE `token` = ? AND `banned` = 0", [$token])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập')]));
    }
    if (empty($_POST['telco'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng chọn nhà mạng')]));
    }
    if (empty($_POST['amount'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng chọn mệnh giá cần nạp')]));
    }
    if ($_POST['amount'] <= 0) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng chọn mệnh giá cần nạp')]));
    }
    if (empty($_POST['serial'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng nhập serial thẻ')]));
    }
    if (empty($_POST['pin'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng nhập mã thẻ')]));
    }

    // Lấy giá trị và kiểm tra loại thẻ
    $telco = validate_string($_POST['telco'], 50);
    if ($telco === false) {
        die(json_encode(['status' => 'error', 'msg' => __('Loại thẻ không hợp lệ')]));
    }
    // Lấy danh sách loại thẻ cho phép từ cấu hình
    $list_network_topup_card = $CMSNT->site('list_network_topup_card');
    $cards = explode("\n", $list_network_topup_card);
    $allowed_cards = [];
    foreach ($cards as $card) {
        $card = trim($card);
        if (!$card) {
            continue;
        }
        $arr = explode('|', $card);
        if (count($arr) == 2) {
            $allowed_cards[] = $arr[0];
        }
    }
    // Nếu loại thẻ không nằm trong danh sách cho phép thì dừng xử lý
    if (!in_array($telco, $allowed_cards)) {
        die(json_encode(['status' => 'error', 'msg' => __('Loại thẻ không được hỗ trợ')]));
    }

    $amount = validate_float($_POST['amount'], 0.01);
    if ($amount === false) {
        die(json_encode(['status' => 'error', 'msg' => __('Mệnh giá không hợp lệ')]));
    }
    $serial = validate_string($_POST['serial'], 50);
    if ($serial === false) {
        die(json_encode(['status' => 'error', 'msg' => __('Serial thẻ không hợp lệ')]));
    }
    $pin = validate_string($_POST['pin'], 50);
    if ($pin === false) {
        die(json_encode(['status' => 'error', 'msg' => __('Mã thẻ không hợp lệ')]));
    }

    $checkResult = checkFormatCard($telco, $serial, $pin);
    if ($checkResult['status'] !== true) {
        die(json_encode(['status' => 'error', 'msg' => $checkResult['msg']]));
    }
    $countPending = $CMSNT->get_row_safe("SELECT COUNT(*) as count FROM `cards` WHERE `user_id` = ? AND `status` = 'pending'", [$getUser['id']]);
    if ($countPending['count'] > 5) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng không spam!')]));
    }
    $countError = $CMSNT->get_row_safe("SELECT COUNT(*) as count FROM `cards` WHERE `status` = 'error' AND `user_id` = ? AND `create_date` >= DATE(NOW()) AND `create_date` < DATE(NOW()) + INTERVAL 1 DAY", [$getUser['id']]);
    $countCompleted = $CMSNT->get_row_safe("SELECT COUNT(*) as count FROM `cards` WHERE `status` = 'complted' AND `user_id` = ? AND `create_date` >= DATE(NOW()) AND `create_date` < DATE(NOW()) + INTERVAL 1 DAY", [$getUser['id']]);
    if (($countError['count'] - $countCompleted['count']) >= 5) {
        die(json_encode(['status' => 'error', 'msg' => __('Bạn đã bị chặn sử dụng chức năng nạp thẻ trong 1 ngày')]));
    }
    $trans_id = random('QWERTYUIOPASDFGHJKLZXCVBNM', 6) . time();
    $data = card24h($telco, $amount, $serial, $pin, $trans_id);
    if ($data['status'] == 99) {
        $isInsert = $CMSNT->insert("cards", array(
            'trans_id'  => $trans_id,
            'telco'     => $telco,
            'amount'    => $amount,
            'serial'    => $serial,
            'pin'       => $pin,
            'price'     => 0,
            'user_id'   => $getUser['id'],
            'status'    => 'pending',
            'reason'    => '',
            'create_date'    => gettime(),
            'update_date'    => gettime()
        ));
        if ($isInsert) {
            // Cập nhật thời gian request chống spam
            $CMSNT->update("users", [
                'time_request' => time()
            ], " `id` = '" . $getUser['id'] . "' ");
            $CMSNT->insert("logs", [
                'user_id'       => $getUser['id'],
                'ip'            => myip(),
                'device'        => getUserAgent(),
                'createdate'    => gettime(),
                'action'        => "Thực hiện nạp thẻ Serial: $serial - Pin: $pin"
            ]);
            // Rate limit
            checkBlockIP('PAYMENT', 5);
            die(json_encode(['status' => 'success', 'msg' => __('Đẩy thẻ lên thành công, vui lòng chờ xử lý thẻ trong giây lát!')]));
        } else {
            die(json_encode(['status' => 'error', 'msg' => __('Nạp thẻ thất bại, vui lòng liên hệ Admin')]));
        }
    } else {
        die(json_encode(['status' => 'error', 'msg' => $data['data']['msg']]));
    }
}




use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Core\ProductionEnvironment;
use PayPalCheckoutSdk\Orders\OrdersGetRequest;
use PayPalHttp\HttpException;

if ($_POST['action'] == 'confirmPaypal' && isset($_POST['order'])) {

    if ($CMSNT->site('paypal_status') != 1) {
        die(json_encode(['status' => 'error', 'msg' => __('Chức năng này đang được bảo trì')]));
    }
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập')]));
    }
    $token = validate_string($_POST['token'], 255);
    if ($token === false) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập')]));
    }
    if (!$getUser = $CMSNT->get_row_safe("SELECT * FROM `users` WHERE `token` = ? AND `banned` = 0", [$token])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập')]));
    }
    $clientId = $CMSNT->site('paypal_clientId');
    $clientSecret = $CMSNT->site('paypal_clientSecret');
    $environment = new ProductionEnvironment($clientId, $clientSecret);
    //$environment = new SandboxEnvironment($clientId, $clientSecret);
    $client = new PayPalHttpClient($environment);
    $orderData = $_POST['order'];
    $request = new OrdersGetRequest($orderData['id']);
    try {
        $response = $client->execute($request);
        if ($response->statusCode != 200) {
            die(json_encode(['status' => 'error', 'msg' => __('Đã xảy ra lỗi!')]));
        }
        $order = $response->result;
        if ($order->status != 'COMPLETED') {
            die(json_encode(['status' => 'error', 'msg' => __('Đơn hàng không hợp lệ hoặc chưa thanh toán')]));
        }
        $orderDetail = $order->purchase_units[0];
        if ($CMSNT->num_rows("SELECT * FROM `payment_paypal` WHERE `trans_id` = '" . $order->id . "' ") > 0) {
            die(json_encode(['status' => 'error', 'msg' => __('Giao dịch này đã được xử lý')]));
        }
        $price = $CMSNT->site('paypal_rate') * $orderDetail->amount->value;
        // Tính khuyến mãi nạp tiền PayPal
        $price = calculateCryptoReceivedAmount($price, $CMSNT->site('paypal_promotions'));
        $isInsert = $CMSNT->insert("payment_paypal", [
            'user_id'       => $getUser['id'],
            'trans_id'      => $order->id,
            'amount'        => $orderDetail->amount->value,
            'price'         => $price,
            'create_date'   => gettime(),
            'create_time'   => time()
        ]);
        if ($isInsert) {
            $user = new users();
            $isCong = $user->AddCredits($getUser['id'], $price, __('Nạp tiền tự động qua PayPal') . " - $order->id", 'TOPUP_PAYPAL_' . $order->id);
            if ($isCong) {
                /** SEND NOTI CHO ADMIN */
                $my_text = $CMSNT->site('noti_recharge');
                $my_text = str_replace('{domain}', check_string($_SERVER['SERVER_NAME']), $my_text);
                $my_text = str_replace('{username}', $getUser['username'], $my_text);
                $my_text = str_replace('{method}', 'PayPal', $my_text);
                $my_text = str_replace('{amount}', $orderDetail->amount->value, $my_text);
                $my_text = str_replace('{price}', $price, $my_text);
                $my_text = str_replace('{time}', gettime(), $my_text);
                sendMessAdmin($my_text);
                die(json_encode(['status' => 'success', 'msg' => __('Nạp tiền thành công')]));
            } else {
                die(json_encode(['status' => 'error', 'msg' => __('Hóa đơn này đã được cộng tiền rồi')]));
            }
        }
    } catch (HttpException $e) {
        die(json_encode(['status' => 'error', 'msg' => $e->getMessage()]));
    } catch (Exception $e) {
        die(json_encode(['status' => 'error', 'msg' => $e->getMessage()]));
    }
}

if ($_POST['action'] == 'notication_topup_xipay') {
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    $token = validate_string($_POST['token'], 255);
    if ($token === false) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (!$getUser = $CMSNT->get_row_safe("SELECT * FROM `users` WHERE `token` = ? AND `banned` = 0", [$token])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (!$row = $CMSNT->get_row_safe("SELECT * FROM `payment_xipay` WHERE `notication` = 0 AND `user_id` = ? AND `status` = 1", [$getUser['id']])) {
        die(json_encode(['status' => 'error', 'msg' => __('Không có lịch sử nạp tiền gần đây')]));
    }
    $CMSNT->update('payment_xipay', [
        'notication'    => 1
    ], " `id` = '" . $row['id'] . "' ");
    die(json_encode([
        'status' => 'success',
        'msg' => __('Deposit successful') . ' ' . format_currency($row['price'])
    ]));
}

if ($_POST['action'] == 'getReceivedXipay') {
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập')]));
    }
    $token = validate_string($_POST['token'], 255);
    if ($token === false) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập')]));
    }
    if (!$getUser = $CMSNT->get_row_safe("SELECT * FROM `users` WHERE `token` = ? AND `banned` = 0", [$token])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập')]));
    }
    if (empty($_POST['amount'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng nhập số tiền cần nạp')]));
    }
    $amount = validate_float($_POST['amount'], 0.01);
    if ($amount === false) {
        die(json_encode(['status' => 'error', 'msg' => __('Số tiền không hợp lệ')]));
    }
    $price = floatval($CMSNT->site('gateway_xipay_rate'));
    $received = $price * $amount;  // Số tiền thực nhận ban đầu
    // Tính khuyến mãi nạp tiền XiPay
    $received = calculateCryptoReceivedAmount($received, $CMSNT->site('xipay_promotions'));
    die(json_encode(['status' => 'success', 'msg' => __('Lấy số tiền thực nhận thành công'), 'received' => format_currency($received)]));
}




if ($_POST['action'] == 'RechargeKorapay') {
    if ($CMSNT->site('status_demo') != 0) {
        die(json_encode(['status' => 'error', 'msg' => __('You cannot use this function because this is a demo site')]));
    }
    if ($CMSNT->site('status') != 1 && isSecureCookie('admin_login') != true) {
        die(json_encode(['status' => 'error', 'msg' => __('The system is maintenance')]));
    }
    if ($CMSNT->site('korapay_status') != 1) {
        die(json_encode(['status' => 'error', 'msg' => __('This function is under maintenance')]));
    }
    if ($CMSNT->site('korapay_secretKey') == '') {
        die(json_encode(['status' => 'error', 'msg' => __('This function has not been configured')]));
    }
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Please log in')]));
    }
    $token = validate_string($_POST['token'], 255);
    if ($token === false) {
        die(json_encode(['status' => 'error', 'msg' => __('Please log in')]));
    }
    if (!$getUser = $CMSNT->get_row_safe("SELECT * FROM `users` WHERE `token` = ? AND `banned` = 0", [$token])) {
        die(json_encode(['status' => 'error', 'msg' => __('Please log in')]));
    }
    if (empty($_POST['amount'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Please enter deposit amount')]));
    }
    $amount = validate_float($_POST['amount'], 0.01);
    if ($amount === false) {
        die(json_encode(['status' => 'error', 'msg' => __('Deposit amount is not available')]));
    }
    if ($amount < $CMSNT->site('korapay_min')) {
        die(json_encode(['status' => 'error', 'msg' => __('Minimum deposit amount is ' . $CMSNT->site('korapay_min') . '')]));
    }
    if ($amount > $CMSNT->site('korapay_max')) {
        die(json_encode(['status' => 'error', 'msg' => __('Maximum deposit amount is ' . $CMSNT->site('korapay_max') . '')]));
    }
    $trans_id = random('QWERTYUIOPASDFGHJKLZXCVBNM', 3) . time();
    $price = $amount * $CMSNT->site('korapay_rate');
    // Tính khuyến mãi nạp tiền Korapay
    $price = calculateCryptoReceivedAmount($price, $CMSNT->site('korapay_promotions'));
    require_once(__DIR__ . "/../../libs/payments/korapay.php");

    // Các tham số cần thiết cho khởi tạo giao dịch
    $params = [
        "amount"      => (int)$amount,       // Đảm bảo là kiểu số
        "currency"    => $CMSNT->site('korapay_currency_code'),
        "reference"   => $trans_id,
        "redirect_url" => base_url('?action=recharge-korapay'), // URL nhận kết quả redirect sau khi thanh toán
        "notification_url" => base_url('api/callback_korapay.php'),
        "narration"        => "Deposit money into " . $getUser['username'],
        "customer"    => [
            "email" => $getUser['email']
        ],
        // Tùy chọn thêm
        // "channels"       => ["bank_transfer", "card"], v.v.
        // "metadata"       => [ "customField" => "anyValue" ],
        // "merchant_bears_cost" => true (hoặc false),
    ];

    // Gọi hàm khởi tạo giao dịch (Initialize Charge)
    $secretKey = $CMSNT->site('korapay_secretKey'); // Thay bằng secret key thực tế
    $response  = korapayInitializeCharge($secretKey, $params);

    // Kiểm tra phản hồi từ API
    if ($response && isset($response['status']) && $response['status'] === true) {
        // Lấy checkout_url từ data được trả về bởi API
        $checkoutUrl = $response['data']['checkout_url'];
        $reference = $response['data']['reference'];

        $isInsert = $CMSNT->insert('payment_korapay', array(
            'user_id'           => $getUser['id'],
            'trans_id'          => $reference,
            'price'             => $price,
            'amount'            => $amount,
            'status'            => 0,
            'created_at'        => gettime(),
            'updated_at'        => gettime(),
            'checkout_url'      => $checkoutUrl
        ));
        if ($isInsert) {
            $CMSNT->update("users", [
                'time_request' => time()
            ], " `id` = '" . $getUser['id'] . "' ");

            $CMSNT->insert("logs", [
                'user_id'       => $getUser['id'],
                'ip'            => myip(),
                'device'        => getUserAgent(),
                'createdate'    => gettime(),
                'action'        => __('Create Korapay top-up invoice #') . " $trans_id"
            ]);
            // Rate limit
            checkBlockIP('PAYMENT', 5);
            die(json_encode(['invoice_url'  => $checkoutUrl, 'status' => 'success', 'msg' => __('Successful!')]));
        }
    } else {
        // Xử lý lỗi nếu khởi tạo không thành công
        die(json_encode(['status' => 'error', 'msg' => $response['message']]));
    }
}
if ($_POST['action'] == 'getReceivedKorapay') {
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Please log in')]));
    }
    $token = validate_string($_POST['token'], 255);
    if ($token === false) {
        die(json_encode(['status' => 'error', 'msg' => __('Please log in')]));
    }
    if (!$getUser = $CMSNT->get_row_safe("SELECT * FROM `users` WHERE `token` = ? AND `banned` = 0", [$token])) {
        die(json_encode(['status' => 'error', 'msg' => __('Please log in')]));
    }
    if (empty($_POST['amount'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Please enter deposit amount')]));
    }
    $amount = validate_float($_POST['amount'], 0.01);
    if ($amount === false) {
        die(json_encode(['status' => 'error', 'msg' => __('Deposit amount is not available')]));
    }
    $price = floatval($CMSNT->site('korapay_rate'));
    $received = $price * $amount;  // Số tiền thực nhận ban đầu
    // Tính khuyến mãi nạp tiền Korapay
    $received = calculateCryptoReceivedAmount($received, $CMSNT->site('korapay_promotions'));
    die(json_encode(['status' => 'success', 'msg' => __('Lấy số tiền thực nhận thành công'), 'received' => format_currency($received)]));
}


if ($_POST['action'] == 'RechargeTmweasyapi') {
    if ($CMSNT->site('status_demo') != 0) {
        die(json_encode(['status' => 'error', 'msg' => __('You cannot use this function because this is a demo site')]));
    }
    if ($CMSNT->site('status') != 1 && isSecureCookie('admin_login') != true) {
        die(json_encode(['status' => 'error', 'msg' => __('The system is maintenance')]));
    }
    if ($CMSNT->site('tmweasyapi_status') != 1) {
        die(json_encode(['status' => 'error', 'msg' => __('This function is under maintenance')]));
    }
    if ($CMSNT->site('tmweasyapi_username') == '' || $CMSNT->site('tmweasyapi_password') == '' || $CMSNT->site('tmweasyapi_con_id') == '') {
        die(json_encode(['status' => 'error', 'msg' => __('This function has not been configured')]));
    }
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Please log in')]));
    }
    $token = validate_string($_POST['token'], 255);
    if ($token === false) {
        die(json_encode(['status' => 'error', 'msg' => __('Please log in')]));
    }
    if (!$getUser = $CMSNT->get_row_safe("SELECT * FROM `users` WHERE `token` = ? AND `banned` = 0", [$token])) {
        die(json_encode(['status' => 'error', 'msg' => __('Please log in')]));
    }
    if (empty($_POST['amount'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Please enter deposit amount')]));
    }
    $amount = validate_float($_POST['amount'], 0.01);
    if ($amount === false) {
        die(json_encode(['status' => 'error', 'msg' => __('Deposit amount is not available')]));
    }
    if ($amount < $CMSNT->site('tmweasyapi_min')) {
        die(json_encode(['status' => 'error', 'msg' => __('Minimum deposit amount is ' . $CMSNT->site('tmweasyapi_min') . '')]));
    }
    if ($amount > $CMSNT->site('tmweasyapi_max')) {
        die(json_encode(['status' => 'error', 'msg' => __('Maximum deposit amount is ' . $CMSNT->site('tmweasyapi_max') . '')]));
    }
    $trans_id = random('QWERTYUIOPASDFGHJKLZXCVBNM', 3) . time();
    $price = $amount * $CMSNT->site('tmweasyapi_rate');
    // Tính khuyến mãi nạp tiền Tmweasyapi Thailand
    $price = calculateCryptoReceivedAmount($price, $CMSNT->site('tmweasyapi_promotions'));
    require_once(__DIR__ . "/../../libs/payments/tmweasyapi.php");


    $paramsCreate = [
        "username" => $CMSNT->site('tmweasyapi_username'),
        "password" => $CMSNT->site('tmweasyapi_password'),
        "con_id"   => $CMSNT->site('tmweasyapi_con_id'),
        "amount"   => $amount,
        "ref1"     => $trans_id,
        "method"   => "create_pay"
    ];

    // Gọi hàm
    $responseCreate = callMaemaneeApi($paramsCreate);
    if ($responseCreate === false) {
        die(json_encode(['status' => 'error', 'msg' => __('Lỗi gọi API create_pay')]));
    }
    // Kiểm tra response
    if (isset($responseCreate['status']) && $responseCreate['status'] == 1) {
        $idPay = $responseCreate['id_pay'];  // Lưu để dùng ở bước tiếp
        $paramsDetail = [
            "username" => $CMSNT->site('tmweasyapi_username'),
            "password" => $CMSNT->site('tmweasyapi_password'),
            "con_id"   => $CMSNT->site('tmweasyapi_con_id'),
            "id_pay"   => $idPay,
            "qr"       => 1,
            "method"   => "detail_pay"
        ];

        $responseDetail = callMaemaneeApi($paramsDetail);
        if ($responseDetail === false) {
            die(json_encode(['status' => 'error', 'msg' => __('Lỗi gọi API detail_pay')]));
        }

        // Kiểm tra kết quả
        if (isset($responseDetail['status']) && $responseDetail['status'] == 1) {
            $ref1     = check_string($responseDetail['ref1']);
            $amount   = check_string($responseDetail['amount']);
            $urlPay   = check_string($responseDetail['urlpay']);
            $timeOut  = check_string($responseDetail['time_out']);
            $isInsert = $CMSNT->insert('payment_tmweasyapi', array(
                'user_id'           => $getUser['id'],
                'trans_id'          => $trans_id,
                'id_pay'            => $idPay,
                'price'             => $price,
                'amount'            => $amount,
                'status'            => 0,
                'created_at'        => gettime(),
                'updated_at'        => gettime(),
                'checkout_url'      => $urlPay
            ));
            if ($isInsert) {
                $CMSNT->update("users", [
                    'time_request' => time()
                ], " `id` = '" . $getUser['id'] . "' ");

                $CMSNT->insert("logs", [
                    'user_id'       => $getUser['id'],
                    'ip'            => myip(),
                    'device'        => getUserAgent(),
                    'createdate'    => gettime(),
                    'action'        => __('Create Tmweasyapi Thailand top-up invoice #') . " $trans_id"
                ]);
                // Rate limit
                checkBlockIP('PAYMENT', 5);
                die(json_encode([
                    'invoice_url'  => $urlPay,
                    'qr'           => $responseDetail['qr_base64_image'],
                    'time_out'     => $timeOut,
                    'amount'       => $amount,
                    'status'       => 'success',
                    'msg'          => __('Successful!')
                ]));
            }

            die(json_encode(['status' => 'error', 'msg' => __('Không thể tạo hóa đơn nạp tiền!')]));
        } else {
            $msgError = isset($responseDetail['msg']) ? $responseDetail['msg'] : "Không rõ lỗi";
            die("Không thể lấy chi tiết thanh toán. Lý do: " . $msgError);
        }
    } else {
        // Thất bại
        $msgError = isset($responseCreate['msg']) ? check_string($responseCreate['msg']) : "Không rõ lỗi";
        die(json_encode(['status' => 'error', 'msg' => $msgError]));
    }
}
if ($_POST['action'] == 'getReceivedTmweasyapi') {
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Please log in')]));
    }
    $token = validate_string($_POST['token'], 255);
    if ($token === false) {
        die(json_encode(['status' => 'error', 'msg' => __('Please log in')]));
    }
    if (!$getUser = $CMSNT->get_row_safe("SELECT * FROM `users` WHERE `token` = ? AND `banned` = 0", [$token])) {
        die(json_encode(['status' => 'error', 'msg' => __('Please log in')]));
    }
    if (empty($_POST['amount'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Please enter deposit amount')]));
    }
    $amount = validate_float($_POST['amount'], 0.01);
    if ($amount === false) {
        die(json_encode(['status' => 'error', 'msg' => __('Deposit amount is not available')]));
    }
    $price = floatval($CMSNT->site('tmweasyapi_rate'));
    $received = $price * $amount;  // Số tiền thực nhận ban đầu
    // Tính khuyến mãi nạp tiền Tmweasyapi Thailand
    $received = calculateCryptoReceivedAmount($received, $CMSNT->site('tmweasyapi_promotions'));
    die(json_encode(['status' => 'success', 'msg' => __('Lấy số tiền thực nhận thành công'), 'received' => format_currency($received)]));
}

if ($_POST['action'] == 'RechargeOpenPix') {
    if ($CMSNT->site('status_demo') != 0) {
        die(json_encode(['status' => 'error', 'msg' => __('You cannot use this function because this is a demo site')]));
    }
    if ($CMSNT->site('status') != 1 && isSecureCookie('admin_login') != true) {
        die(json_encode(['status' => 'error', 'msg' => __('The system is maintenance')]));
    }
    if ($CMSNT->site('openpix_status') != 1) {
        die(json_encode(['status' => 'error', 'msg' => __('This function is under maintenance')]));
    }
    if ($CMSNT->site('openpix_api_key') == '') {
        die(json_encode(['status' => 'error', 'msg' => __('This function has not been configured')]));
    }
    if ($CMSNT->site('openpix_HMAC_key') == '') {
        die(json_encode(['status' => 'error', 'msg' => __('This function has not been configured')]));
    }
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Please log in')]));
    }
    $token = validate_string($_POST['token'], 255);
    if ($token === false) {
        die(json_encode(['status' => 'error', 'msg' => __('Please log in')]));
    }
    if (!$getUser = $CMSNT->get_row_safe("SELECT * FROM `users` WHERE `token` = ? AND `banned` = 0", [$token])) {
        die(json_encode(['status' => 'error', 'msg' => __('Please log in')]));
    }
    if (empty($_POST['amount'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Please enter deposit amount')]));
    }
    $amount = validate_float($_POST['amount'], 0.01);
    if ($amount === false) {
        die(json_encode(['status' => 'error', 'msg' => __('Deposit amount is not available')]));
    }
    if ($amount < $CMSNT->site('openpix_min')) {
        die(json_encode(['status' => 'error', 'msg' => __('Minimum deposit amount is ' . $CMSNT->site('openpix_min') . '')]));
    }
    if ($amount > $CMSNT->site('openpix_max')) {
        die(json_encode(['status' => 'error', 'msg' => __('Maximum deposit amount is ' . $CMSNT->site('openpix_max') . '')]));
    }
    $trans_id = random('QWERTYUIOPASDFGHJKLZXCVBNM', 3) . time();
    $price = $amount * $CMSNT->site('openpix_rate');
    // Tính khuyến mãi nạp tiền OpenPix Brazil
    $price = calculateCryptoReceivedAmount($price, $CMSNT->site('openpix_promotions'));
    // Chuyển đổi số tiền để đảm bảo API OpenPix nhận đúng giá trị
    // API OpenPix coi 1.00 là 1 Real, nên cần chuyển đổi giá trị input
    // Nếu người dùng nhập 100, cần gửi 100 thay vì API hiểu thành 1.00
    $openpix_value = (float)$amount * 100; // Nhân với 100 để đảm bảo giá trị đúng format

    // JSON data to be sent
    $data = array(
        'correlationID' => $trans_id,
        'value' => $openpix_value, // Giá trị đã được xử lý đúng format
        'comment' => 'Topup ' . $getUser['username'],
    );
    // Encode the data to JSON format
    $json = json_encode($data);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_URL => 'https://api.openpix.com.br/api/v1/charge?return_existing=true',
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_POST => 1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_HTTPHEADER => [
            "Authorization: " . $CMSNT->site('openpix_api_key'),
            "content-type: application/json"
        ]
    ]);

    // Execute the cURL request
    $response = curl_exec($ch);
    // Close the cURL handle
    curl_close($ch);
    $response = json_decode($response, true);


    // Kiểm tra phản hồi từ API
    if (($response && isset($response['charge']) && $response['charge']['status'] === 'ACTIVE') || ($response && isset($response['status']) && $response['status'] === 'ACTIVE')) {

        // Lưu thông tin cần thiết
        $transactionID = isset($response['charge']) ? $response['charge']['transactionID'] : $response['pix']['transactionID'];
        $qrCodeImage = isset($response['charge']) ? $response['charge']['qrCodeImage'] : $response['pix']['qrCodeImage'];
        $checkoutUrl = isset($response['charge']) ? $response['charge']['paymentLinkUrl'] : $response['paymentLinkUrl'];
        $reference = isset($response['charge']) ? $response['charge']['correlationID'] : $response['correlationID'];

        $isInsert = $CMSNT->insert('payment_openpix', array(
            'user_id'           => $getUser['id'],
            'trans_id'          => $trans_id,
            'price'             => $price,
            'amount'            => $amount,
            'status'            => 0,
            'created_at'        => gettime(),
            'updated_at'        => gettime(),
            'checkout_url'      => $checkoutUrl
        ));
        if ($isInsert) {
            $CMSNT->update("users", [
                'time_request' => time()
            ], " `id` = '" . $getUser['id'] . "' ");


            $CMSNT->insert("logs", [
                'user_id'       => $getUser['id'],
                'ip'            => myip(),
                'device'        => getUserAgent(),
                'createdate'    => gettime(),
                'action'        => __('Create OpenPix top-up invoice #') . " $trans_id"
            ]);
            // Rate limit
            checkBlockIP('PAYMENT', 5);
            die(json_encode(['invoice_url'  => $checkoutUrl, 'status' => 'success', 'msg' => __('Successful!')]));
        } else {
            die(json_encode(['status' => 'error', 'msg' => __('Failed to create invoice')]));
        }
    } else {
        // Xử lý lỗi nếu khởi tạo không thành công
        if (isset($response['error'])) {
            die(json_encode(['status' => 'error', 'msg' => $response['error']]));
        } else {
            die(json_encode(['status' => 'error', 'msg' => 'Unknown error occurred']));
        }
    }
}
if ($_POST['action'] == 'getReceivedOpenPix') {
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Please log in')]));
    }
    $token = validate_string($_POST['token'], 255);
    if ($token === false) {
        die(json_encode(['status' => 'error', 'msg' => __('Please log in')]));
    }
    if (!$getUser = $CMSNT->get_row_safe("SELECT * FROM `users` WHERE `token` = ? AND `banned` = 0", [$token])) {
        die(json_encode(['status' => 'error', 'msg' => __('Please log in')]));
    }
    if (empty($_POST['amount'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Please enter deposit amount')]));
    }
    $amount = validate_float($_POST['amount'], 0.01);
    if ($amount === false) {
        die(json_encode(['status' => 'error', 'msg' => __('Deposit amount is not available')]));
    }
    $price = floatval($CMSNT->site('openpix_rate'));
    $received = $price * $amount;  // Số tiền thực nhận ban đầu
    // Tính khuyến mãi nạp tiền OpenPix Brazil
    $received = calculateCryptoReceivedAmount($received, $CMSNT->site('openpix_promotions'));
    die(json_encode(['status' => 'success', 'msg' => __('Lấy số tiền thực nhận thành công'), 'received' => format_currency($received)]));
}

if ($_POST['action'] == 'RechargeBakong') {
    if ($CMSNT->site('status_demo') != 0) {
        die(json_encode(['status' => 'error', 'msg' => __('You cannot use this function because this is a demo site')]));
    }
    if ($CMSNT->site('status') != 1 && isSecureCookie('admin_login') != true) {
        die(json_encode(['status' => 'error', 'msg' => __('The system is maintenance')]));
    }
    if ($CMSNT->site('bakong_status') != 1) {
        die(json_encode(['status' => 'error', 'msg' => __('This function is under maintenance')]));
    }
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Please log in')]));
    }
    $token = validate_string($_POST['token'], 255);
    if ($token === false) {
        die(json_encode(['status' => 'error', 'msg' => __('Please log in')]));
    }
    if (!$getUser = $CMSNT->get_row_safe("SELECT * FROM `users` WHERE `token` = ? AND `banned` = 0", [$token])) {
        die(json_encode(['status' => 'error', 'msg' => __('Please log in')]));
    }
    if (empty($_POST['amount'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Please enter deposit amount')]));
    }
    $amount = validate_float($_POST['amount'], 0.01);
    if ($amount === false) {
        die(json_encode(['status' => 'error', 'msg' => __('Deposit amount is not available')]));
    }
    if ($amount < $CMSNT->site('bakong_min')) {
        die(json_encode(['status' => 'error', 'msg' => __('Minimum deposit amount is ' . $CMSNT->site('bakong_min') . '')]));
    }
    if ($amount > $CMSNT->site('bakong_max')) {
        die(json_encode(['status' => 'error', 'msg' => __('Maximum deposit amount is ' . $CMSNT->site('bakong_max') . '')]));
    }
    $trans_id = random('123456789', 4) . time();
    $price = $amount * $CMSNT->site('bakong_rate');
    // Tính khuyến mãi nạp tiền Bakong
    $price = calculateCryptoReceivedAmount($price, $CMSNT->site('bakong_promotions'));

    require_once(__DIR__ . "/../../libs/payments/bakong.php");

    $params = [
        'amount' => $amount,
        'transaction_id' => $trans_id,
        'success_url' => base_url('?action=recharge-bakong'),
        'remark' => 'Topup ' . $getUser['username']
    ];
    $response = createPaymentBakong($params);
    if (isset($response)) {
        $isInsert = $CMSNT->insert('payment_bakong', array(
            'user_id'           => $getUser['id'],
            'trans_id'          => $trans_id,
            'price'             => $price,
            'amount'            => $amount,
            'status'            => 0,
            'created_at'        => gettime(),
            'updated_at'        => gettime(),
            'checkout_url'      => NULL
        ));
        if ($isInsert) {

            $CMSNT->insert("logs", [
                'user_id'       => $getUser['id'],
                'ip'            => myip(),
                'device'        => getUserAgent(),
                'createdate'    => gettime(),
                'action'        => __('Create Bakong Wallet Cambodia top-up invoice #') . " $trans_id"
            ]);
            // Rate limit
            checkBlockIP('PAYMENT', 5);
            die(json_encode([
                'status' => 'success',
                'msg' => __('Successful!'),
                'invoice_url'  => $response
            ]));
        }
        die(json_encode(['status' => 'error', 'msg' => __('Failed to create invoice 1')]));
    } else {
        die(json_encode(['status' => 'error', 'msg' => __('Failed to create invoice')]));
    }
}
if ($_POST['action'] == 'getReceivedBakong') {
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Please log in')]));
    }
    $token = validate_string($_POST['token'], 255);
    if ($token === false) {
        die(json_encode(['status' => 'error', 'msg' => __('Please log in')]));
    }
    if (!$getUser = $CMSNT->get_row_safe("SELECT * FROM `users` WHERE `token` = ? AND `banned` = 0", [$token])) {
        die(json_encode(['status' => 'error', 'msg' => __('Please log in')]));
    }
    if (empty($_POST['amount'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Please enter deposit amount')]));
    }
    if ($_POST['amount'] <= 0) {
        die(json_encode(['status' => 'error', 'msg' => __('Deposit amount is not available')]));
    }
    $amount = floatval($_POST['amount']);
    $price = floatval($CMSNT->site('bakong_rate'));
    $received = $price * $amount;  // Số tiền thực nhận ban đầu
    // Tính khuyến mãi nạp tiền Bakong
    $received = calculateCryptoReceivedAmount($received, $CMSNT->site('bakong_promotions'));
    die(json_encode(['status' => 'success', 'msg' => __('Lấy số tiền thực nhận thành công'), 'received' => format_currency($received)]));
}

// HIỂN THỊ THÔNG BÁO KHI NẠP TIỀN
if ($_POST['action'] == 'notication_topup') {
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    $token = validate_string($_POST['token'], 255);
    if ($token === false) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (!$getUser = $CMSNT->get_row_safe("SELECT * FROM `users` WHERE `token` = ? AND `banned` = 0", [$token])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (!$row = $CMSNT->get_row_safe("SELECT * FROM `payment_bank` WHERE `notication` = 0 AND `user_id` = ?", [$getUser['id']])) {
        die(json_encode(['status' => 'error', 'msg' => __('Không có lịch sử nạp tiền gần đây')]));
    }
    $CMSNT->update('payment_bank', [
        'notication'    => 1
    ], " `id` = '" . $row['id'] . "' ");
    die(json_encode([
        'status' => 'success',
        'msg' => __('Nạp tiền thành công') . ' ' . format_currency($row['received'])
    ]));
}



if ($_POST['action'] == 'notication_topup_korapay') {
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    $token = validate_string($_POST['token'], 255);
    if ($token === false) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (!$getUser = $CMSNT->get_row_safe("SELECT * FROM `users` WHERE `token` = ? AND `banned` = 0", [$token])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    require_once(__DIR__ . "/../../libs/payments/korapay.php");
    require_once(__DIR__ . "/../../libs/database/users.php");
    $user = new users;

    foreach ($CMSNT->get_list_safe("SELECT * FROM `payment_korapay` WHERE `status` = 0 AND `user_id` = ? ORDER BY `id` DESC LIMIT 3", [$getUser['id']]) as $row) {
        $secretKey = $CMSNT->site('korapay_secretKey');
        $reference = $row['trans_id'];
        $verification = korapayVerifyCharge($secretKey, $reference);
        if ($verification || isset($verification['status']) || $verification['status'] !== false) {
            if ($verification['data']['status'] == 'success') {
                $isCong = $user->AddCredits($row['user_id'], $row['price'], __('Recharge Korapay') . ' #' . $reference, 'TOPUP_korapay_' . $reference);
                if ($isCong) {
                    $CMSNT->update('payment_korapay', [
                        'status'            => 1,
                        'notication'        => 1,
                        'updated_at'        => gettime()
                    ], " `id` = '" . $row['id'] . "'  ");
                    // TẠO LOG GIAO DỊCH GẦN ĐÂY
                    $CMSNT->insert('deposit_log', [
                        'user_id'       => $row['user_id'],
                        'method'        => __('Korapay Africa'),
                        'amount'        => $amount,
                        'received'      => $row['price'],
                        'create_time'   => time(),
                        'is_virtual'    => 0
                    ]);
                    /** SEND NOTI CHO ADMIN */
                    $my_text = $CMSNT->site('noti_recharge');
                    $my_text = str_replace('{domain}', (validate_string($_SERVER['SERVER_NAME'] ?? '', 255) ?: ''), $my_text);
                    $my_text = str_replace('{username}', getRowRealtime('users', $row['user_id'], 'username'), $my_text);
                    $my_text = str_replace('{method}', __('Recharge Korapay'), $my_text);
                    $my_text = str_replace('{amount}', $amount, $my_text);
                    $my_text = str_replace('{price}', format_currency($row['price']), $my_text);
                    $my_text = str_replace('{time}', gettime(), $my_text);
                    sendMessAdmin($my_text);
                    die(json_encode([
                        'status' => 'success',
                        'msg' => __('Deposit successful') . ' ' . format_currency($row['price'])
                    ]));
                }
            }
            if ($verification['data']['status'] == 'failed' || $verification['data']['status'] == 'expired') {
                $CMSNT->update('payment_korapay', [
                    'status'            => 2,
                    'updated_at'        => gettime()
                ], " `id` = '" . $row['id'] . "'  ");
            }
        }
    }
}

if ($_POST['action'] == 'notication_topup_bakong') {
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    $token = validate_string($_POST['token'], 255);
    if ($token === false) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (!$getUser = $CMSNT->get_row_safe("SELECT * FROM `users` WHERE `token` = ? AND `banned` = 0", [$token])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    require_once(__DIR__ . "/../../libs/payments/bakong.php");
    require_once(__DIR__ . "/../../libs/database/users.php");
    $user = new users;

    foreach ($CMSNT->get_list_safe("SELECT * FROM `payment_bakong` WHERE `user_id` = ? AND `status` = 0 ORDER BY `id` DESC LIMIT 3", [$getUser['id']]) as $payment_bakong) {
        $response = verifyPaymentBakong($payment_bakong['trans_id'], $payment_bakong['amount']);
        if ($response['status'] == true) {
            // Cộng tiền cho user
            $isCong = $user->AddCredits(
                $payment_bakong['user_id'],
                $payment_bakong['amount'],
                __('Recharge Bakong Wallet Cambodia') . ' #' . $payment_bakong['trans_id'],
                'TOPUP_Bakong_' . $payment_bakong['trans_id']
            );

            if ($isCong) {
                // Cập nhật status = 1 (đã thanh toán)
                $CMSNT->update('payment_bakong', [
                    'status'     => 1,
                    'updated_at' => gettime(),
                    'notication' => 1
                ], " `id` = '" . $payment_bakong['id'] . "' ");

                // Tạo log nạp
                $CMSNT->insert('deposit_log', [
                    'user_id'       => $payment_bakong['user_id'],
                    'method'        => __('Bakong Wallet Cambodia'),
                    'amount'        => $payment_bakong['amount'],
                    'received'      => $payment_bakong['price'],
                    'create_time'   => time(),
                    'is_virtual'    => 0
                ]);

                // Gửi thông báo admin (nếu có hàm sendMessAdmin)
                $my_text = $CMSNT->site('noti_recharge');
                $my_text = str_replace('{domain}', (validate_string($_SERVER['SERVER_NAME'] ?? '', 255) ?: ''), $my_text);
                $my_text = str_replace('{username}', getRowRealtime('users', $payment_bakong['user_id'], 'username'), $my_text);
                $my_text = str_replace('{method}', __('Bakong Wallet Cambodia'), $my_text);
                $my_text = str_replace('{amount}', $payment_bakong['amount'], $my_text);
                $my_text = str_replace('{price}', format_currency($payment_bakong['price']), $my_text);
                $my_text = str_replace('{time}', gettime(), $my_text);
                sendMessAdmin($my_text);

                die(json_encode([
                    'status' => 'success',
                    'msg'    => __('Deposit successful') . ' ' . format_currency($payment_bakong['price'])
                ]));
            }
        }
    }
}
if ($_POST['action'] == 'notication_topup_tmweasyapi') {
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    $token = validate_string($_POST['token'], 255);
    if ($token === false) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (!$getUser = $CMSNT->get_row_safe("SELECT * FROM `users` WHERE `token` = ? AND `banned` = 0", [$token])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    require_once(__DIR__ . "/../../libs/payments/tmweasyapi.php");
    require_once(__DIR__ . "/../../libs/database/users.php");
    require_once(__DIR__ . "/../../libs/database/users.php");
    $user = new users;

    foreach ($CMSNT->get_list_safe("SELECT * FROM `payment_tmweasyapi` WHERE `user_id` = ? AND `status` = 0 ORDER BY `id` DESC LIMIT 3", [$getUser['id']]) as $payment_tmweasyapi) {

        // Gọi confirm
        $paramsConfirm = [
            "username" => $CMSNT->site('tmweasyapi_username'),
            "password" => $CMSNT->site('tmweasyapi_password'),
            "con_id"   => $CMSNT->site('tmweasyapi_con_id'),
            "id_pay"   => $payment_tmweasyapi['id_pay'],
            "ip"       => myip(),
            "method"   => "confirm"
        ];
        $result = callMaemaneeApi($paramsConfirm);

        if ($result === false) {
            die(json_encode([
                'status' => 'error',
                'msg' => __('Không thể kết nối API confirm')
            ]));
        }

        // TH1: Thanh toán thành công
        if (!empty($result['status']) && $result['status'] == 1) {
            $ref1   = $result['ref1']   ?? '';
            $amount = validate_float($result['amount'] ?? 0, 0.01) ?: 0;

            // Cộng tiền cho user
            $isCong = $user->AddCredits(
                $payment_tmweasyapi['user_id'],
                $payment_tmweasyapi['price'],
                __('Recharge Tmweasyapi Thailand') . ' #' . $payment_tmweasyapi['trans_id'],
                'TOPUP_Tmweasyapi_' . $payment_tmweasyapi['trans_id']
            );

            if ($isCong) {
                // Cập nhật status = 1 (đã thanh toán)
                $CMSNT->update('payment_tmweasyapi', [
                    'status'     => 1,
                    'updated_at' => gettime(),
                    'notication' => 1
                ], " `id` = '" . $payment_tmweasyapi['id'] . "' ");

                // Tạo log nạp
                $CMSNT->insert('deposit_log', [
                    'user_id'       => $payment_tmweasyapi['user_id'],
                    'method'        => __('Tmweasyapi Thailand'),
                    'amount'        => $amount,
                    'received'      => $payment_tmweasyapi['price'],
                    'create_time'   => time(),
                    'is_virtual'    => 0
                ]);

                // Gửi thông báo admin (nếu có hàm sendMessAdmin)
                $my_text = $CMSNT->site('noti_recharge');
                $my_text = str_replace('{domain}', (validate_string($_SERVER['SERVER_NAME'] ?? '', 255) ?: ''), $my_text);
                $my_text = str_replace('{username}', getRowRealtime('users', $payment_tmweasyapi['user_id'], 'username'), $my_text);
                $my_text = str_replace('{method}', __('Tmweasyapi Thailand'), $my_text);
                $my_text = str_replace('{amount}', $amount, $my_text);
                $my_text = str_replace('{price}', format_currency($payment_tmweasyapi['price']), $my_text);
                $my_text = str_replace('{time}', gettime(), $my_text);
                sendMessAdmin($my_text);

                die(json_encode([
                    'status' => 'success',
                    'msg'    => __('Deposit successful') . ' ' . format_currency($payment_tmweasyapi['price'])
                ]));
            }
        } else {
            // TH2: Chưa thanh toán hoặc lỗi
            // -> Kiểm tra xem hóa đơn đã quá 24 giờ chưa
            // Giả sử cột created_at là datetime
            $timeCreated = strtotime($payment_tmweasyapi['created_at']);
            $timeNow     = time();
            // Nếu > 24 giờ, gọi API cancel
            if (($timeNow - $timeCreated) >= 86400) {
                $CMSNT->update('payment_tmweasyapi', [
                    'status'     => 2, // bạn có thể đặt = 2 để đánh dấu "đã hủy"
                    'updated_at' => gettime()
                ], " `id` = '" . $payment_tmweasyapi['id'] . "' ");
            }
        }
    }
}


if ($_POST['action'] == 'notication_topup_openpix') {
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    $token = validate_string($_POST['token'], 255);
    if ($token === false) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (!$getUser = $CMSNT->get_row_safe("SELECT * FROM `users` WHERE `token` = ? AND `banned` = 0", [$token])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (!$row = $CMSNT->get_row_safe("SELECT * FROM `payment_openpix` WHERE `notication` = 0 AND `user_id` = ? AND `status` = 1", [$getUser['id']])) {
        die(json_encode(['status' => 'error', 'msg' => __('Không có lịch sử nạp tiền gần đây')]));
    }
    $CMSNT->update('payment_openpix', [
        'notication'    => 1
    ], " `id` = '" . $row['id'] . "' ");
    die(json_encode([
        'status' => 'success',
        'msg' => __('Deposit successful') . ' ' . format_currency($row['price'])
    ]));
}

// Lấy danh sách cổng thanh toán đang bật
if ($_POST['action'] == 'getActivePaymentGateways') {

    $gateways = [];
    // Ngân hàng
    if ($CMSNT->site('bank_status') == 1) {
        $gateways[] = [
            'name' => __('Ngân hàng'),
            'description' => __('Thanh toán qua chuyển khoản ngân hàng'),
            'icon' => 'ri-bank-line',
            'url' => base_url('client/recharge-bank'),
            'color' => 'primary'
        ];
    }

    // Thẻ cào
    if ($CMSNT->site('card_status') == 1) {
        $gateways[] = [
            'name' => __('Nạp thẻ cào'),
            'description' => __('Thanh toán bằng thẻ cào điện thoại'),
            'icon' => 'ri-sim-card-line',
            'url' => base_url('client/recharge-card'),
            'color' => 'success'
        ];
    }

    // Crypto USDT
    if ($CMSNT->site('crypto_status') == 1) {
        $gateways[] = [
            'name' => __('Crypto USDT'),
            'description' => __('Thanh toán bằng tiền điện tử USDT'),
            'icon' => 'ri-coins-line',
            'url' => base_url('client/recharge-crypto'),
            'color' => 'warning'
        ];
    }

    // PayPal
    if ($CMSNT->site('paypal_status') == 1) {
        $gateways[] = [
            'name' => __('PayPal'),
            'description' => __('Thanh toán qua PayPal'),
            'icon' => 'ri-paypal-line',
            'url' => base_url('client/recharge-paypal'),
            'color' => 'info'
        ];
    }

    // XiPay (AliPay & WeChat Pay)
    if ($CMSNT->site('gateway_xipay_status') == 1) {
        $gateways[] = [
            'name' => __('AliPay & WeChat Pay'),
            'description' => __('Thanh toán qua AliPay hoặc WeChat Pay'),
            'icon' => 'ri-alipay-line',
            'url' => base_url('client/recharge-xipay'),
            'color' => 'secondary'
        ];
    }

    // Korapay Africa
    if ($CMSNT->site('korapay_status') == 1) {
        $gateways[] = [
            'name' => __('Korapay Africa'),
            'description' => __('Thanh toán qua Korapay cho khu vực châu Phi'),
            'icon' => 'ri-global-line',
            'url' => base_url('client/recharge-korapay'),
            'color' => 'dark'
        ];
    }

    // PromptPay Thailand
    if ($CMSNT->site('tmweasyapi_status') == 1) {
        $gateways[] = [
            'name' => __('PromptPay Thailand'),
            'description' => __('Thanh toán qua PromptPay Thái Lan'),
            'icon' => 'ri-smartphone-line',
            'url' => base_url('client/recharge-tmweasyapi'),
            'color' => 'danger'
        ];
    }

    // OpenPix Brazil
    if ($CMSNT->site('openpix_status') == 1) {
        $gateways[] = [
            'name' => __('OpenPix Brazil'),
            'description' => __('Thanh toán qua OpenPix Brazil'),
            'icon' => 'ri-money-dollar-circle-line',
            'url' => base_url('client/recharge-openpix'),
            'color' => 'success'
        ];
    }

    // Bakong Wallet Cambodia
    if ($CMSNT->site('bakong_status') == 1) {
        $gateways[] = [
            'name' => __('Bakong Wallet Cambodia'),
            'description' => __('Thanh toán qua Bakong Wallet Cambodia'),
            'icon' => 'ri-wallet-line',
            'url' => base_url('client/recharge-bakong'),
            'color' => 'primary'
        ];
    }

    // Cổng thanh toán thủ công
    foreach ($CMSNT->get_list(" SELECT * FROM `payment_manual` WHERE `display` = 1 ") as $row) {
        $gateways[] = [
            'name' => __($row['title']),
            'description' => __($row['description']),
            'icon' => 'ri-money-dollar-circle-line',
            'url' => base_url('recharge-manual/' . $row['slug']),
            'color' => 'success'
        ];
    }

    die(json_encode(['status' => 'success', 'data' => $gateways]));
}

die(json_encode([
    'status'    => 'error',
    'msg'       => __('Invalid data')
]));
