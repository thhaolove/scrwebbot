<?php

define("IN_SITE", true);
require_once(__DIR__ . "/../../config.php");
require_once(__DIR__ . "/../../libs/db.php");
require_once(__DIR__ . "/../../libs/lang.php");
require_once(__DIR__ . "/../../libs/helper.php");
require_once(__DIR__ . "/../../libs/sendEmail.php");
require_once(__DIR__ . '/../../libs/suppliers.php');
require_once(__DIR__ . '/../../libs/database/users.php');

header('Content-Type: application/json; charset=utf-8');


if ($CMSNT->site('status') != 1) {
    http_response_code(503); // Service Unavailable
    $data = json_encode([
        'status'    => 'error',
        'msg'       => __('Hệ thống đang bảo trì!')
    ]);
    die($data);
}
if (!isset($_REQUEST['action'])) {
    http_response_code(400); // Bad Request
    $data = json_encode([
        'status'    => 'error',
        'msg'       => __('The Request Not Found')
    ]);
    die($data);
}
if ($_REQUEST['action'] == 'buyProduct') {
    if ($CMSNT->site('status_demo') != 0) {
        http_response_code(403); // Forbidden
        die(json_encode(['status' => 'error', 'msg' => __('This function cannot be used because this is a demo site')]));
    }
    // Xử lý User khi mua bằng API
    if (!empty($_REQUEST['api_key'])) {
        $api_key = validate_alphanumeric($_REQUEST['api_key']);
        if ($api_key === false) {
            checkBlockIP('API', 5);
            http_response_code(400); // Bad Request
            die(json_encode(['status' => 'error', 'msg' => __('API key không hợp lệ!')]));
        }

        if (!$getUser = $CMSNT->get_row_safe("SELECT * FROM `users` WHERE `api_key` = ? AND `banned` = 0", [$api_key])) {
            // Rate limit
            checkBlockIP('API', 5);
            http_response_code(401); // Unauthorized
            die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập')]));
        }
        // Kiểm tra IP có trong Whitelist hay không
        $client_ip = myip();
        if (!checkIPWhitelist($getUser['ip_whitelist_api'], $client_ip)) {
            checkBlockIP('IP_NOT_WHITELIST_API', 5);
            http_response_code(403); // Forbidden
            die(json_encode([
                'status' => 'error',
                'msg' => __('IP của bạn không nằm trong Whitelist API của User này'),
                'client_ip' => $client_ip
            ]));
        }
    }
    // Xử lý User khi mua tại web
    else if (!empty($_REQUEST['token'])) {
        $token = validate_alphanumeric($_REQUEST['token'], 255);
        if ($token === false) {
            checkBlockIP('API', 5);
            http_response_code(400); // Bad Request
            die(json_encode(['status' => 'error', 'msg' => __('Token không hợp lệ!')]));
        }

        if (!$getUser = $CMSNT->get_row_safe("SELECT * FROM `users` WHERE `token` = ? AND `banned` = 0", [$token])) {
            // Rate limit
            checkBlockIP('API', 5);
            http_response_code(401); // Unauthorized
            die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập')]));
        }
    } else {
        http_response_code(401); // Unauthorized
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập')]));
    }
    //

    if ($getUser['banned'] != 0) {
        http_response_code(403); // Forbidden
        die(json_encode(['status' => 'error', 'msg' => __('Tài khoản của bạn đã bị cấm')]));
    }
    if ($getUser['ctv'] != 0) {
        http_response_code(403); // Forbidden
        die(json_encode(['status' => 'error', 'msg' => __('Tài khoản CTV không được phép mua hàng')]));
    }
    if (time() > $getUser['time_request'] && time() - $getUser['time_request'] < $CMSNT->site('thoi_gian_mua_cach_nhau')) {
        http_response_code(429); // Too Many Requests
        die(json_encode(['status' => 'error', 'msg' => __('Thao tác quá nhanh, vui lòng chờ')]));
    }
    $product_id = validate_int($_REQUEST['id'], 1);
    if ($product_id === false) {
        http_response_code(400); // Bad Request
        die(json_encode(['status' => 'error', 'msg' => __('ID sản phẩm không hợp lệ!')]));
    }

    if (!$product = $CMSNT->get_row_safe("SELECT * FROM `products` WHERE `id` = ? AND `status` = 1", [$product_id])) {
        http_response_code(404); // Not Found
        die(json_encode(['status' => 'error', 'msg' => __('Sản phẩm không tồn tại trong hệ thống')]));
    }

    $amount = validate_int($_REQUEST['amount'], 1);
    if ($amount === false) {
        http_response_code(400); // Bad Request
        die(json_encode(['status' => 'error', 'msg' => __('Số lượng không hợp lệ!')]));
    }
    if ($amount < $product['min']) {
        http_response_code(400); // Bad Request
        die(json_encode(['status' => 'error', 'msg' => __('Số lượng cần mua tối thiểu là') . ' ' . format_cash($product['min'])]));
    }
    if ($amount > $product['max']) {
        http_response_code(400); // Bad Request
        die(json_encode(['status' => 'error', 'msg' => __('Số lượng cần mua tối đa là') . ' ' . format_cash($product['max'])]));
    }
    if (is_numeric($amount) && floor($amount) != $amount) {
        http_response_code(400); // Bad Request
        die(json_encode(['status' => 'error', 'msg' => __('Số lượng mua không hợp lệ')]));
    }
    if ($product['supplier_id'] == 0) {
        // KIỂM TRA STOCK HỆ THỐNG
        $stock_count = $CMSNT->get_row_safe("SELECT COUNT(id) as total FROM `product_stock` WHERE `product_code` = ?", [$product['code']])['total'];
        if ($stock_count < $amount) {
            http_response_code(400); // Bad Request
            die(json_encode(['status' => 'error', 'msg' => __('Số lượng còn lại trong hệ thống không đủ')]));
        }
    } else {
        // KIỂM TRA STOCK API
        // if($product['api_stock'] < $amount){
        //     die(json_encode(['status' => 'error', 'msg' => __('Số lượng còn lại trong hệ thống không đủ')]));
        // }
        if (!$supplier = $CMSNT->get_row_safe("SELECT * FROM `suppliers` WHERE `id` = ? AND `status` = 1", [$product['supplier_id']])) {
            http_response_code(503); // Service Unavailable
            die(json_encode(['status' => 'error', 'msg' => __('Sản phẩm này đang bảo trì, không thể mua hàng vào lúc này')]));
        }
    }
    $trans_id = random('QWERTYUOPASDFGHJKZXCVBNM123456789', 4) . uniqid();
    $price = $product['discount'] == 0 ? $product['price'] : $product['price'] - $product['price'] * $product['discount'] / 100;
    $money = $amount * $price; // giá gốc
    $pay = $money;
    $discount = 0;
    $discount_coupon = 0;
    // xử lý giảm giá bằng chiết khấu
    if ($getUser['discount'] == 0) {
        $discount = $money * getDiscount($amount, $product['id']) / 100;
        // Xử lý giảm giá bằng coupon
        if (!empty($_REQUEST['coupon'])) {
            $coupon = validate_alphanumeric($_REQUEST['coupon'], 50);
            if ($coupon !== false) {
                // Lấy số tiền giảm từ Coupon
                $discount_coupon = checkCoupon($product['id'], $coupon, $getUser['id'], $money);
            }
        }
        $pay = $money - $discount - $discount_coupon;
    } else {
        $discount = $money * $getUser['discount'] / 100;
        $pay = $money - $discount;
    }

    $price_vat      = $CMSNT->site('tax_vat') > 0 ? $pay * $CMSNT->site('tax_vat') / 100 : 0; // Số tiền thuế VAT cần trả thêm
    $pay            = $pay + $price_vat; // Số tiền thanh toán sau khi tính thuế VAT


    if (getRowRealtime('users', $getUser['id'], 'money') < $pay) {
        http_response_code(402); // Payment Required
        die(json_encode(['status' => 'error', 'msg' => __('Số dư không đủ, vui lòng nạp thêm')]));
    }
    $User = new users();
    $isTru = $User->RemoveCredits($getUser['id'], $pay, __('Thanh toán đơn hàng mua tài khoản') . ' <b>' . $product['name'] . '</b> - #' . $trans_id, 'ORDER_' . $trans_id);
    if ($isTru) {
        if (getRowRealtime("users", $getUser['id'], "money") < -500) {
            $User->Banned($getUser['id'], __('Gian lận khi mua tài khoản'));
            http_response_code(403); // Forbidden
            die(json_encode(['status' => 'error', 'msg' => __('Bạn đã bị khoá tài khoản vì gian lận')]));
        }
        $api_trans_id = NULL;
        $isValue = 0;

        // Lấy hàng từ API
        if ($product['supplier_id'] != 0) {
            // LẤY HÀNG TỪ API
            if ($supplier['type'] == 'SHOPCLONE6') {
                $data = buy_API_SHOPCLONE6($supplier['domain'], $supplier['username'], $supplier['password'], $product['api_id'], $amount, $supplier['proxy']);
                $data = json_decode($data, true);
                $http_code = validate_string($data['http_code']);
                if (!isset($data) || $data['status'] == 'error2') {
                    if ($CMSNT->site('auto_refund_order_failed_api') == 1) {
                        $User->RefundCredits($getUser['id'], $pay, "[$http_code][Error] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    }
                    http_response_code(503); // Service Unavailable
                    die(json_encode(['status' => 'error', 'msg' => "[$http_code] " . __('Mất kết nối đến kho hàng')]));
                }
                if ($data['status'] == 'error') {
                    $User->RefundCredits($getUser['id'], $pay, "[Error 3] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);

                    // Kiểm tra nếu có HTTP code là 402 thì gửi thông báo qua Telegram
                    if (isset($data['http_code']) && $data['http_code'] == 402) {
                        /** NOTE ACTION */
                        $my_text = $CMSNT->site('noti_api_out_of_money');
                        $my_text = str_replace('{domain}', $_SERVER['SERVER_NAME'], $my_text);
                        $my_text = str_replace('{username}', $getUser['username'], $my_text);
                        $my_text = str_replace('{supplier_name}', $supplier['domain'], $my_text);
                        $my_text = str_replace('{product_name}', $product['name'], $my_text);
                        $my_text = str_replace('{product_id}', $product['id'], $my_text);
                        $my_text = str_replace('{pay}', format_currency($pay), $my_text);
                        $my_text = str_replace('{amount}', format_cash($amount), $my_text);
                        $my_text = str_replace('{ip}', myip(), $my_text);
                        $my_text = str_replace('{time}', gettime(), $my_text);
                        sendMessAdmin($my_text);
                    }
                    http_response_code(503); // Service Unavailable
                    die(json_encode(['status' => 'error', 'msg' => __($data['msg'])]));
                }
                $api_trans_id = $data['data']['trans_id'];
                foreach ($data['data']['lists'] as $account) {
                    $account = check_string($account['account']);
                    $uid = explode('|', $account)[0];
                    $isInsertAPI = $CMSNT->insert("product_sold", [
                        'type'              => $supplier['domain'],
                        'product_code'      => NULL,
                        'supplier_id'       => $product['supplier_id'],
                        'trans_id'          => $trans_id,
                        'buyer'             => $getUser['id'],
                        'seller'            => $product['user_id'],
                        'uid'               => $uid,
                        'account'           => $account,
                        'create_gettime'    => gettime()
                    ]);
                    if ($isInsertAPI) {
                        $isValue++;
                    }
                }
            }
            //
            if ($supplier['type'] == 'SHOPCLONE7') {
                $data = buy_API_SHOPCLONE7($supplier['domain'], $supplier['coupon'], $supplier['api_key'], $product['api_id'], $amount, $supplier['proxy']);
                $data = json_decode($data, true);
                $http_code = validate_string($data['http_code']);
                if (!isset($data) || $data['status'] == 'error2') {
                    if ($CMSNT->site('auto_refund_order_failed_api') == 1) {
                        $User->RefundCredits($getUser['id'], $pay, "[$http_code][Error] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    }
                    http_response_code(503); // Service Unavailable
                    die(json_encode(['status' => 'error', 'msg' => "[$http_code] " . __('Mất kết nối đến kho hàng')]));
                }
                if ($data['status'] == 'error') {
                    $User->RefundCredits($getUser['id'], $pay, "[Error 1] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);

                    // Kiểm tra nếu có HTTP code là 402 thì gửi thông báo qua Telegram
                    if (isset($data['http_code']) && $data['http_code'] == 402) {
                        /** NOTE ACTION */
                        $my_text = $CMSNT->site('noti_api_out_of_money');
                        $my_text = str_replace('{domain}', $_SERVER['SERVER_NAME'], $my_text);
                        $my_text = str_replace('{username}', $getUser['username'], $my_text);
                        $my_text = str_replace('{supplier_name}', $supplier['domain'], $my_text);
                        $my_text = str_replace('{product_name}', $product['name'], $my_text);
                        $my_text = str_replace('{product_id}', $product['id'], $my_text);
                        $my_text = str_replace('{pay}', format_currency($pay), $my_text);
                        $my_text = str_replace('{amount}', format_cash($amount), $my_text);
                        $my_text = str_replace('{ip}', myip(), $my_text);
                        $my_text = str_replace('{time}', gettime(), $my_text);
                        sendMessAdmin($my_text);
                    }
                    http_response_code(503); // Service Unavailable
                    die(json_encode(['status' => 'error', 'msg' => __($data['msg'])]));
                }
                $api_trans_id = $data['trans_id'];
                foreach ($data['data'] as $account) {
                    $account = check_string($account);
                    $uid = explode('|', $account)[0];
                    $isInsertAPI = $CMSNT->insert("product_sold", [
                        'type'              => $supplier['domain'],
                        'product_code'      => NULL,
                        'supplier_id'       => $product['supplier_id'],
                        'trans_id'          => $trans_id,
                        'buyer'             => $getUser['id'],
                        'seller'            => $product['user_id'],
                        'uid'               => $uid,
                        'account'           => $account,
                        'create_gettime'    => gettime()
                    ]);
                    if ($isInsertAPI) {
                        $isValue++;
                    }
                }
            }
            //
            if ($supplier['type'] == 'API_1') {
                $dataPost = [
                    'api_key' => $supplier['api_key'],
                    'id_product' => $product['api_id'],
                    'quantity' => $amount,
                ];
                $response = buy_API_1($supplier['domain'], $dataPost);
                $data = json_decode($response, true);
                if (!isset($data)) {
                    if ($CMSNT->site('auto_refund_order_failed_api') == 1) {
                        $User->RefundCredits($getUser['id'], $pay, "[Error] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    }
                    http_response_code(503); // Service Unavailable
                    die(json_encode(['status' => 'error', 'msg' => __('Mất kết nối đến kho hàng')]));
                }
                if ($data['status'] == false) {
                    $User->RefundCredits($getUser['id'], $pay, "[Error 1] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    die(json_encode(['status' => 'error', 'msg' => __($data['msg'])]));
                }
                $api_trans_id = $data['order_id'];
                $response = order_API_1($supplier['domain'], $supplier['api_key'], $api_trans_id);
                $result = json_decode($response, true);
                foreach ($result['data'] as $account) {
                    $account = check_string($account['full_info']);
                    $uid = explode('|', $account)[0];
                    $isInsertAPI = $CMSNT->insert("product_sold", [
                        'type'              => $supplier['domain'],
                        'product_code'      => NULL,
                        'supplier_id'       => $product['supplier_id'],
                        'trans_id'          => $trans_id,
                        'buyer'             => $getUser['id'],
                        'seller'            => $product['user_id'],
                        'uid'               => $uid,
                        'account'           => $account,
                        'create_gettime'    => gettime()
                    ]);
                    if ($isInsertAPI) {
                        $isValue++;
                    }
                }
            }
            //
            if ($supplier['type'] == 'API_4') {
                $response = buy_API_4($supplier['domain'], $supplier['token'], $product['api_id'], $amount);
                $result = json_decode($response, true);
                if (!isset($result)) {
                    if ($CMSNT->site('auto_refund_order_failed_api') == 1) {
                        $User->RefundCredits($getUser['id'], $pay, "[Error] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    }
                    http_response_code(503); // Service Unavailable
                    die(json_encode(['status' => 'error', 'msg' => __('Mất kết nối đến kho hàng')]));
                }
                if (!isset($result['data'])) {
                    $User->RefundCredits($getUser['id'], $pay, "[Error 1] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    die(json_encode(['status' => 'error', 'msg' => __($result['message']['messageVNI'])]));
                }
                $api_trans_id = NULL;
                foreach ($result['data'] as $account) {
                    $account = check_string($account);
                    $uid = explode('|', $account)[0];
                    $isInsertAPI = $CMSNT->insert("product_sold", [
                        'type'              => $supplier['domain'],
                        'product_code'      => NULL,
                        'supplier_id'       => $product['supplier_id'],
                        'trans_id'          => $trans_id,
                        'buyer'             => $getUser['id'],
                        'seller'            => $product['user_id'],
                        'uid'               => $uid,
                        'account'           => $account,
                        'create_gettime'    => gettime()
                    ]);
                    if ($isInsertAPI) {
                        $isValue++;
                    }
                }
            }
            //
            if ($supplier['type'] == 'API_6') {
                $response = curl_get2($supplier['domain'] . '/api.php?apikey=' . $supplier['api_key'] . '&action=create-order&service_id=' . $product['api_id'] . '&amount=' . $amount);
                $data = json_decode($response, true);
                if (!isset($data)) {
                    if ($CMSNT->site('auto_refund_order_failed_api') == 1) {
                        $User->RefundCredits($getUser['id'], $pay, "[Error] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    }
                    http_response_code(503); // Service Unavailable
                    die(json_encode(['status' => 'error', 'msg' => __('Mất kết nối đến kho hàng')]));
                }
                if ($data['code'] != 200) {
                    $User->RefundCredits($getUser['id'], $pay, "[Error 1] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    die(json_encode(['status' => 'error', 'msg' => __($data['message'])]));
                }
                $api_trans_id = $data['order_id'];
                while (true) {
                    $response = curl_get2($supplier['domain'] . '/api.php?apikey=' . $supplier['api_key'] . '&action=get-order-detail&order_id=' . $api_trans_id);
                    $data_account = json_decode($response, true);
                    if ($data_account['order']['status'] == 1) {
                        break;
                    }
                }
                if (explode(PHP_EOL, $data_account['order']['data'])) {
                    $lines = explode(PHP_EOL, $data_account['order']['data']);
                } else {
                    // FIX DO API BMTRAU THAY ĐỔI JSON API
                    $lines = $data_account['order']['data'];
                }
                foreach ($lines as $account) {
                    if (empty($account)) {
                        continue;
                    }
                    $account = check_string($account);
                    $uid = explode('|', $account)[0];
                    if (!isset(explode('|', $account)[1])) {
                        continue;
                    }
                    $isInsertAPI = $CMSNT->insert("product_sold", [
                        'type'              => $supplier['domain'],
                        'product_code'      => NULL,
                        'supplier_id'       => $product['supplier_id'],
                        'trans_id'          => $trans_id,
                        'buyer'             => $getUser['id'],
                        'seller'            => $product['user_id'],
                        'uid'               => $uid,
                        'account'           => $account,
                        'create_gettime'    => gettime()
                    ]);
                    if ($isInsertAPI) {
                        $isValue++;
                    }
                }
            }
            //
            if ($supplier['type'] == 'API_9') {
                $dataPost = [
                    'type_id'   => $product['api_id'],
                    'quantity'  => $amount
                ];
                $response = buy_API_9($supplier['domain'], $supplier['api_key'], $dataPost);
                $result = json_decode($response, true);
                if (!isset($result)) {
                    if ($CMSNT->site('auto_refund_order_failed_api') == 1) {
                        $User->RefundCredits($getUser['id'], $pay, "[Error] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    }
                    http_response_code(503); // Service Unavailable
                    die(json_encode(['status' => 'error', 'msg' => __('Mất kết nối đến kho hàng')]));
                }
                if ($result['error'] != 0) {
                    $User->RefundCredits($getUser['id'], $pay, "[Error 1] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    die(json_encode(['status' => 'error', 'msg' => __($result['error'])]));
                }
                $api_trans_id = $result['data']['buy_id'];
                foreach ($result['data']['data'] as $account) {
                    $account = check_string($account);
                    $uid = explode('|', $account)[0];
                    $isInsertAPI = $CMSNT->insert("product_sold", [
                        'type'              => $supplier['domain'],
                        'product_code'      => NULL,
                        'supplier_id'       => $product['supplier_id'],
                        'trans_id'          => $trans_id,
                        'buyer'             => $getUser['id'],
                        'seller'            => $product['user_id'],
                        'uid'               => $uid,
                        'account'           => $account,
                        'create_gettime'    => gettime()
                    ]);
                    if ($isInsertAPI) {
                        $isValue++;
                    }
                }
            }
            //
            if ($supplier['type'] == 'API_14') {
                $response = buy_API_14($supplier['domain'], $supplier['token'], $product['api_id'], $amount);
                $data = json_decode($response, true);
                if (!isset($data)) {
                    if ($CMSNT->site('auto_refund_order_failed_api') == 1) {
                        $User->RefundCredits($getUser['id'], $pay, "[Error] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    }
                    http_response_code(503); // Service Unavailable
                    die(json_encode(['status' => 'error', 'msg' => __('Mất kết nối đến kho hàng')]));
                }
                if ($data['error_code'] == 1) {
                    $User->RefundCredits($getUser['id'], $pay, "[Error 1] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    die(json_encode(['status' => 'error', 'msg' => __($data['message'])]));
                }
                $api_trans_id = $data['order_id'];
                while (true) {
                    $response = getOrder_API_14($supplier['domain'], $supplier['token'], $api_trans_id);
                    $data_account = json_decode($response, true);
                    if (isset($data_account['data'])) {
                        break;
                    }
                }
                $lines = explode(PHP_EOL, $data_account['data']['data']);
                foreach ($lines as $account) {
                    if ($account == '') {
                        continue;
                    }
                    $account = check_string($account);
                    $uid = explode('|', $account)[0];
                    $isInsertAPI = $CMSNT->insert("product_sold", [
                        'type'              => $supplier['domain'],
                        'product_code'      => NULL,
                        'supplier_id'       => $product['supplier_id'],
                        'trans_id'          => $trans_id,
                        'buyer'             => $getUser['id'],
                        'seller'            => $product['user_id'],
                        'uid'               => $uid,
                        'account'           => $account,
                        'create_gettime'    => gettime()
                    ]);
                    if ($isInsertAPI) {
                        $isValue++;
                    }
                }
            }
            //
            if ($supplier['type'] == 'API_17') {
                $data = buy_API_17($supplier['domain'], $supplier['username'], $supplier['password'], $product['api_id'], $amount);
                $data = json_decode($data, true);
                if (!isset($data)) {
                    if ($CMSNT->site('auto_refund_order_failed_api') == 1) {
                        $User->RefundCredits($getUser['id'], $pay, "[Error] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    }
                    http_response_code(503); // Service Unavailable
                    die(json_encode(['status' => 'error', 'msg' => __('Mất kết nối đến kho hàng')]));
                }
                if ($data['status'] == 'error') {
                    $User->RefundCredits($getUser['id'], $pay, "[Error 1] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    die(json_encode(['status' => 'error', 'msg' => __($data['msg'])]));
                }
                $api_trans_id = $data['data']['trans_id'];
                foreach ($data['data']['lists'] as $account) {
                    $account = check_string($account['account']);
                    $uid = explode('|', $account)[0];
                    $isInsertAPI = $CMSNT->insert("product_sold", [
                        'type'              => $supplier['domain'],
                        'product_code'      => NULL,
                        'supplier_id'       => $product['supplier_id'],
                        'trans_id'          => $trans_id,
                        'buyer'             => $getUser['id'],
                        'seller'            => $product['user_id'],
                        'uid'               => $uid,
                        'account'           => $account,
                        'create_gettime'    => gettime()
                    ]);
                    if ($isInsertAPI) {
                        $isValue++;
                    }
                }
            }
            //
            if ($supplier['type'] == 'API_18') {
                $response = buy_API_18($supplier['domain'], $supplier['api_key'], $product['api_id'], $amount);
                $data = json_decode($response, true);
                if (!isset($data)) {
                    if ($CMSNT->site('auto_refund_order_failed_api') == 1) {
                        $User->RefundCredits($getUser['id'], $pay, "[Error] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    }
                    http_response_code(503); // Service Unavailable
                    die(json_encode(['status' => 'error', 'msg' => __('Mất kết nối đến kho hàng')]));
                }
                if (isset($data['error'])) {
                    $User->RefundCredits($getUser['id'], $pay, "[Error 1] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    die(json_encode(['status' => 'error', 'msg' => __('Số lượng còn lại trong hệ thống không đủ')]));
                }
                $api_trans_id = $data['Data']['TransId'];
                foreach ($data['Data']['Emails'] as $account) {
                    $isInsertAPI = $CMSNT->insert("product_sold", [
                        'type'              => $supplier['domain'],
                        'product_code'      => NULL,
                        'supplier_id'       => $product['supplier_id'],
                        'trans_id'          => $trans_id,
                        'buyer'             => $getUser['id'],
                        'seller'            => $product['user_id'],
                        'uid'               => $account['Email'],
                        'account'           => $account['Email'] . '|' . $account['Password'] . '|' . $account['RefreshToken'] . '|' . $account['AccessToken'] . '|' . $account['ClientId'],
                        'create_gettime'    => gettime()
                    ]);
                    if ($isInsertAPI) {
                        $isValue++;
                    }
                }
            }
            //
            if ($supplier['type'] == 'API_19') {
                $response = buy_API_19($supplier['domain'], $supplier['api_key'], $product['api_id'], $amount);
                $data = json_decode($response, true);
                if (!isset($data)) {
                    if ($CMSNT->site('auto_refund_order_failed_api') == 1) {
                        $User->RefundCredits($getUser['id'], $pay, "[Error] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    }
                    http_response_code(503); // Service Unavailable
                    die(json_encode(['status' => 'error', 'msg' => __('Mất kết nối đến kho hàng')]));
                }
                if ($data['error_code'] != 200) {
                    $User->RefundCredits($getUser['id'], $pay, "[Error 1] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    die(json_encode(['status' => 'error', 'msg' => __($data['message'])]));
                }
                $api_trans_id = $data['data']['order_code'];
                foreach ($data['data']['list_data'] as $account) {
                    $isInsertAPI = $CMSNT->insert("product_sold", [
                        'type'              => $supplier['domain'],
                        'product_code'      => NULL,
                        'supplier_id'       => $product['supplier_id'],
                        'trans_id'          => $trans_id,
                        'buyer'             => $getUser['id'],
                        'seller'            => $product['user_id'],
                        'uid'               => explode('|', $account)[0],
                        'account'           => $account,
                        'create_gettime'    => gettime()
                    ]);
                    if ($isInsertAPI) {
                        $isValue++;
                    }
                }
            }
            //
            if ($supplier['type'] == 'API_20') {
                $response = curl_get($supplier['domain'] . 'api/buyProducts?kioskToken=' . $supplier['api_key'] . '&userToken=' . $supplier['token'] . '&quantity=' . $amount);
                $data = json_decode($response, true);
                if (!isset($data)) {
                    if ($CMSNT->site('auto_refund_order_failed_api') == 1) {
                        $User->RefundCredits($getUser['id'], $pay, "[Error] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    }
                    http_response_code(503); // Service Unavailable
                    die(json_encode(['status' => 'error', 'msg' => __('Mất kết nối đến kho hàng')]));
                }
                if ($data['success'] != true) {
                    $User->RefundCredits($getUser['id'], $pay, "[Error 1] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    die(json_encode(['status' => 'error', 'msg' => __($data['description'])]));
                }
                $api_trans_id = $data['order_id'];
                sleep(5);
                $response = curl_get($supplier['domain'] . 'api/getProducts?orderId=' . $api_trans_id . '&userToken=' . $supplier['token']);
                $result = json_decode($response, true);
                if ($result['success'] == true) {
                    if (isset($result['data'])) {
                        foreach ($result['data'] as $account) {
                            $account = check_string($account['product']);
                            $uid = explode('|', $account)[0];
                            $isInsertAPI = $CMSNT->insert("product_sold", [
                                'type'              => $supplier['domain'],
                                'product_code'      => NULL,
                                'supplier_id'       => $product['supplier_id'],
                                'trans_id'          => $trans_id,
                                'buyer'             => $getUser['id'],
                                'seller'            => $product['user_id'],
                                'uid'               => $uid,
                                'account'           => $account,
                                'create_gettime'    => gettime()
                            ]);
                            if ($isInsertAPI) {
                                $isValue++;
                            }
                        }
                    } else {
                        die(json_encode(['status' => 'error', 'msg' => __($data)]));
                    }
                } else {
                    die(json_encode(['status' => 'error', 'msg' => __($data['description'])]));
                }
            }
            //
            if ($supplier['type'] == 'API_21') {
                $response = buy_API_21($supplier['domain'], $supplier['token'], $product['api_id'], $amount);
                $data = json_decode($response, true);
                if (!isset($data)) {
                    if ($CMSNT->site('auto_refund_order_failed_api') == 1) {
                        $User->RefundCredits($getUser['id'], $pay, "[Error] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    }
                    http_response_code(503); // Service Unavailable
                    die(json_encode(['status' => 'error', 'msg' => __('Mất kết nối đến kho hàng')]));
                }
                if ($data['status'] != true) {
                    $User->RefundCredits($getUser['id'], $pay, "[Error 1] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    die(json_encode(['status' => 'error', 'msg' => __($data['message'])]));
                }
                $api_trans_id = NULL;
                foreach ($data['data'] as $account) {
                    $isInsertAPI = $CMSNT->insert("product_sold", [
                        'type'              => $supplier['domain'],
                        'product_code'      => NULL,
                        'supplier_id'       => $product['supplier_id'],
                        'trans_id'          => $trans_id,
                        'buyer'             => $getUser['id'],
                        'seller'            => $product['user_id'],
                        'uid'               => explode('|', $account)[0],
                        'account'           => $account,
                        'create_gettime'    => gettime()
                    ]);
                    if ($isInsertAPI) {
                        $isValue++;
                    }
                }
            }
            //
            if ($supplier['type'] == 'API_22') {
                $response = buy_API_22($supplier['domain'], $supplier['token'], $product['api_id'], $amount);
                $data = json_decode($response, true);
                if (!isset($data)) {
                    if ($CMSNT->site('auto_refund_order_failed_api') == 1) {
                        $User->RefundCredits($getUser['id'], $pay, "[Error] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    }
                    http_response_code(503); // Service Unavailable
                    die(json_encode(['status' => 'error', 'msg' => __('Mất kết nối đến kho hàng')]));
                }
                if ($data['status'] != 'success') {
                    $User->RefundCredits($getUser['id'], $pay, "[Error 1] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    die(json_encode(['status' => 'error', 'msg' => __($data['message'])]));
                }
                $api_trans_id = NULL;
                foreach ($data['data'] as $account) {
                    $isInsertAPI = $CMSNT->insert("product_sold", [
                        'type'              => $supplier['domain'],
                        'product_code'      => NULL,
                        'supplier_id'       => $product['supplier_id'],
                        'trans_id'          => $trans_id,
                        'buyer'             => $getUser['id'],
                        'seller'            => $product['user_id'],
                        'uid'               => $account['email'],
                        'account'           => $account['email'] . '|' . $account['password'] . '|' . $account['refresh_token'] . '|' . $account['client_id'],
                        'create_gettime'    => gettime()
                    ]);
                    if ($isInsertAPI) {
                        $isValue++;
                    }
                }
            }
            //
            if ($supplier['type'] == 'API_23') {
                $response = buy_API_23($supplier['domain'], $supplier['api_key'], $product['api_id'], $amount);
                $data = json_decode($response, true);
                if (!isset($data)) {
                    if ($CMSNT->site('auto_refund_order_failed_api') == 1) {
                        $User->RefundCredits($getUser['id'], $pay, "[Error] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    }
                    http_response_code(503); // Service Unavailable
                    die(json_encode(['status' => 'error', 'msg' => __('Mất kết nối đến kho hàng')]));
                }
                if (isset($data['Code']) && $data['Code'] == 1) {
                    $User->RefundCredits($getUser['id'], $pay, "[Error 1] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    die(json_encode(['status' => 'error', 'msg' => __('Số lượng còn lại trong hệ thống không đủ')]));
                }
                $api_trans_id = $data['PurchaseId'];
                foreach ($data['Accounts'] as $account) {
                    $isInsertAPI = $CMSNT->insert("product_sold", [
                        'type'              => $supplier['domain'],
                        'product_code'      => NULL,
                        'supplier_id'       => $product['supplier_id'],
                        'trans_id'          => $trans_id,
                        'buyer'             => $getUser['id'],
                        'seller'            => $product['user_id'],
                        'uid'               => $account['Email'],
                        'account'           => $account['Email'] . '|' . $account['Password'] . '|' . $account['RefreshToken'] . '|' . $account['ClientId'],
                        'create_gettime'    => gettime()
                    ]);
                    if ($isInsertAPI) {
                        $isValue++;
                    }
                }
            }
            //
            if ($supplier['type'] == 'API_24') {
                $response = buy_API_24($supplier['domain'], $supplier['api_key'], $product['api_id'], $amount);
                $data = json_decode($response, true);
                if (!isset($data)) {
                    if ($CMSNT->site('auto_refund_order_failed_api') == 1) {
                        $User->RefundCredits($getUser['id'], $pay, "[Error] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    }
                    http_response_code(503); // Service Unavailable
                    die(json_encode(['status' => 'error', 'msg' => __('Mất kết nối đến kho hàng')]));
                }
                if (!isset($data['data'])) {
                    $User->RefundCredits($getUser['id'], $pay, "[Error 1] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    die(json_encode(['status' => 'error', 'msg' => __('Số lượng còn lại trong hệ thống không đủ')]));
                }
                foreach ($data['data'] as $account) {
                    $api_trans_id = $account['order_id'];
                    $isInsertAPI = $CMSNT->insert("product_sold", [
                        'type'              => $supplier['domain'],
                        'product_code'      => NULL,
                        'supplier_id'       => $product['supplier_id'],
                        'trans_id'          => $trans_id,
                        'buyer'             => $getUser['id'],
                        'seller'            => $product['user_id'],
                        'uid'               => $account['email'],
                        'account'           => $account['email'] . '|' . $account['password'],
                        'create_gettime'    => gettime()
                    ]);
                    if ($isInsertAPI) {
                        $isValue++;
                    }
                }
            }
            //
            if ($supplier['type'] == 'API_25') {
                $response = buy_API_25($supplier['domain'], $supplier['api_key'], $product['api_id'], $amount);
                $data = json_decode($response, true);
                if (!isset($data)) {
                    if ($CMSNT->site('auto_refund_order_failed_api') == 1) {
                        $User->RefundCredits($getUser['id'], $pay, "[Error] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    }
                    http_response_code(503); // Service Unavailable
                    die(json_encode(['status' => 'error', 'msg' => __('Mất kết nối đến kho hàng')]));
                }
                if (!isset($data['Data']) || $data['Code'] == 1) {
                    $User->RefundCredits($getUser['id'], $pay, "[Error 1] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    die(json_encode(['status' => 'error', 'msg' => __('Số lượng còn lại trong hệ thống không đủ')]));
                }
                $api_trans_id = $data['Data']['PurchaseId'];
                foreach ($data['Data']['Accounts'] as $account) {
                    $isInsertAPI = $CMSNT->insert("product_sold", [
                        'type'              => $supplier['domain'],
                        'product_code'      => NULL,
                        'supplier_id'       => $product['supplier_id'],
                        'trans_id'          => $trans_id,
                        'buyer'             => $getUser['id'],
                        'seller'            => $product['user_id'],
                        'uid'               => $account['Email'],
                        'account'           => $account['Email'] . '|' . $account['Password'] . '|' . $account['RefreshToken'] . '|' . $account['ClientId'],
                        'create_gettime'    => gettime()
                    ]);
                    if ($isInsertAPI) {
                        $isValue++;
                    }
                }
            }
            //
            if ($supplier['type'] == 'API_26') {
                $response = buy_API_26($supplier['domain'], $supplier['api_key'], $supplier['token'], $product['api_id'], $amount);
                $data = json_decode($response, true);
                if (!isset($data)) {
                    if ($CMSNT->site('auto_refund_order_failed_api') == 1) {
                        $User->RefundCredits($getUser['id'], $pay, "[Error] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    }
                    http_response_code(503); // Service Unavailable
                    die(json_encode(['status' => 'error', 'msg' => __('Mất kết nối đến kho hàng')]));
                }
                if (!isset($data['status']) || $data['status'] != 'ok') {
                    $User->RefundCredits($getUser['id'], $pay, "[Error 1] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    die(json_encode(['status' => 'error', 'msg' => __($data['error'])]));
                }
                $api_trans_id = $data['invoice'];
                $response = getOrder_API_26($supplier['domain'], $supplier['api_key'], $supplier['token'], $api_trans_id);
                foreach (explode("\n", $response) as $account) {
                    if ($account == '') {
                        continue;
                    }
                    $uid = explode(" ", $account);
                    $isInsertAPI = $CMSNT->insert("product_sold", [
                        'type'              => $supplier['domain'],
                        'product_code'      => NULL,
                        'supplier_id'       => $product['supplier_id'],
                        'trans_id'          => $trans_id,
                        'buyer'             => $getUser['id'],
                        'seller'            => $product['user_id'],
                        'uid'               => $uid[0],
                        'account'           => str_replace(":", "|", $account),
                        'create_gettime'    => gettime()
                    ]);
                    if ($isInsertAPI) {
                        $isValue++;
                    }
                }
            }
            //

            //
            if ($supplier['type'] == 'API_28') {
                $data = buy_API_28($supplier['domain'], $supplier['token'], $product['api_id'], $amount);
                $data = json_decode($data, true);
                if (!isset($data)) {
                    if ($CMSNT->site('auto_refund_order_failed_api') == 1) {
                        $User->RefundCredits($getUser['id'], $pay, "[Error] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    }
                    http_response_code(503); // Service Unavailable
                    die(json_encode(['status' => 'error', 'msg' => __('Mất kết nối đến kho hàng')]));
                }
                if ($data['status'] != 'success') {
                    $User->RefundCredits($getUser['id'], $pay, "[Error 1] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    die(json_encode(['status' => 'error', 'msg' => __($data['message'])]));
                }
                $api_trans_id = $data['data']['order_code'];
                $accounts = json_decode($data['data']['account'], true);
                if (is_array($accounts)) {
                    foreach ($accounts as $account) {
                        $account = check_string($account);
                        $uid = explode('|', $account)[0];
                        $isInsertAPI = $CMSNT->insert("product_sold", [
                            'type'              => $supplier['domain'],
                            'product_code'      => NULL,
                            'supplier_id'       => $product['supplier_id'],
                            'trans_id'          => $trans_id,
                            'buyer'             => $getUser['id'],
                            'seller'            => $product['user_id'],
                            'uid'               => $uid,
                            'account'           => $account,
                            'create_gettime'    => gettime()
                        ]);
                        if ($isInsertAPI) {
                            $isValue++;
                        }
                    }
                }
            }
            //
            if ($supplier['type'] == 'API_29') {
                $response = buy_API_29($supplier['domain'], $supplier['api_key'], $product['api_id'], $amount);
                $data = json_decode($response, true);
                if (!isset($data)) {
                    if ($CMSNT->site('auto_refund_order_failed_api') == 1) {
                        $User->RefundCredits($getUser['id'], $pay, "[Error] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    }
                    http_response_code(503); // Service Unavailable
                    die(json_encode(['status' => 'error', 'msg' => __('Mất kết nối đến kho hàng')]));
                }
                if (!isset($data['data']) || $data['code'] != 0) {
                    $User->RefundCredits($getUser['id'], $pay, "[Error 1] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    die(json_encode(['status' => 'error', 'msg' => __(check_string($data['message']))]));
                }
                $api_trans_id = NULL;
                foreach ($data['data'] as $account) {
                    $account = check_string($account);
                    $account = str_replace(":", "|", $account);
                    $isInsertAPI = $CMSNT->insert("product_sold", [
                        'type'              => $supplier['domain'],
                        'product_code'      => NULL,
                        'supplier_id'       => $product['supplier_id'],
                        'trans_id'          => $trans_id,
                        'buyer'             => $getUser['id'],
                        'seller'            => $product['user_id'],
                        'uid'               => explode('|', $account)[0],
                        'account'           => $account,
                        'create_gettime'    => gettime()
                    ]);
                    if ($isInsertAPI) {
                        $isValue++;
                    }
                }
            }
            //
            if ($supplier['type'] == 'API_30') {
                $response = buy_API_30($supplier['domain'], $supplier['api_key'], $product['api_id'], $amount);

                // Kiểm tra nếu response là JSON có status error
                $data = json_decode($response, true);
                if (!isset($data)) {
                    if ($CMSNT->site('auto_refund_order_failed_api') == 1) {
                        $User->RefundCredits($getUser['id'], $pay, "[Error] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    }
                    http_response_code(503); // Service Unavailable
                    die(json_encode(['status' => 'error', 'msg' => __('Mất kết nối đến kho hàng')]));
                }
                if (is_array($data) && (isset($data['status']) && $data['status'] == -1)) {
                    $User->RefundCredits($getUser['id'], $pay, "[Error 1] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    die(json_encode(['status' => 'error', 'msg' => __(check_string($data['msg']))]));
                }

                $api_trans_id = NULL;
                // Xử lý response như text, tách từng dòng
                $lines = explode("\n", trim($response));
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;

                    // Thay "----" thành "|"
                    $account = str_replace("----", "|", $line);
                    $account = check_string($account);

                    $isInsertAPI = $CMSNT->insert("product_sold", [
                        'type'              => $supplier['domain'],
                        'product_code'      => NULL,
                        'supplier_id'       => $product['supplier_id'],
                        'trans_id'          => $trans_id,
                        'buyer'             => $getUser['id'],
                        'seller'            => $product['user_id'],
                        'uid'               => explode('|', $account)[0],
                        'account'           => $account,
                        'create_gettime'    => gettime()
                    ]);
                    if ($isInsertAPI) {
                        $isValue++;
                    }
                }
            }
            //
            if ($supplier['type'] == 'API_31') {
                $data = buy_API_31($supplier['domain'], $supplier['coupon'], $supplier['api_key'], $product['api_id'], $amount, $supplier['proxy']);
                $data = json_decode($data, true);
                if (!isset($data)) {
                    if ($CMSNT->site('auto_refund_order_failed_api') == 1) {
                        $User->RefundCredits($getUser['id'], $pay, "[Error] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    }
                    http_response_code(503); // Service Unavailable
                    die(json_encode(['status' => 'error', 'msg' => __('Mất kết nối đến kho hàng')]));
                }
                if ($data['status'] == 'error') {
                    $User->RefundCredits($getUser['id'], $pay, "[Error 1] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    die(json_encode(['status' => 'error', 'msg' => __($data['msg'])]));
                }
                $api_trans_id = $data['trans_id'];
                foreach ($data['data'] as $account) {
                    $account = check_string($account);
                    $uid = explode('|', $account)[0];
                    $isInsertAPI = $CMSNT->insert("product_sold", [
                        'type'              => $supplier['domain'],
                        'product_code'      => NULL,
                        'supplier_id'       => $product['supplier_id'],
                        'trans_id'          => $trans_id,
                        'buyer'             => $getUser['id'],
                        'seller'            => $product['user_id'],
                        'uid'               => $uid,
                        'account'           => $account,
                        'create_gettime'    => gettime()
                    ]);
                    if ($isInsertAPI) {
                        $isValue++;
                    }
                }
            }
            if ($supplier['type'] == 'API_32') {
                $data = buy_API_32($supplier['domain'], $supplier['api_key'], $product['api_id'], $amount, $supplier['proxy']);
                $data = json_decode($data, true);
                if (!isset($data)) {
                    if ($CMSNT->site('auto_refund_order_failed_api') == 1) {
                        $User->RefundCredits($getUser['id'], $pay, "[Error] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    }
                    http_response_code(503); // Service Unavailable
                    die(json_encode(['status' => 'error', 'msg' => __('Mất kết nối đến kho hàng')]));
                }
                if ($data['success'] != true) {
                    $User->RefundCredits($getUser['id'], $pay, "[Error 1] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    die(json_encode(['status' => 'error', 'msg' => __($data['message'])]));
                }
                $api_trans_id = $data['data']['trans_id'];
                foreach ($data['data']['accounts'] as $account) {
                    $account = check_string($account);
                    $uid = explode('|', $account)[0];
                    $isInsertAPI = $CMSNT->insert("product_sold", [
                        'type'              => $supplier['domain'],
                        'product_code'      => NULL,
                        'supplier_id'       => $product['supplier_id'],
                        'trans_id'          => $trans_id,
                        'buyer'             => $getUser['id'],
                        'seller'            => $product['user_id'],
                        'uid'               => $uid,
                        'account'           => $account,
                        'create_gettime'    => gettime()
                    ]);
                    if ($isInsertAPI) {
                        $isValue++;
                    }
                }
            }
            if ($supplier['type'] == 'API_33') {
                // Bước 1: Tạo invoice (mua activation codes)
                $data = buy_API_33($supplier['domain'], $supplier['token'], $product['api_id'], $amount, $supplier['proxy']);
                $data = json_decode($data, true);

                // Xử lý lỗi kết nối
                if (!isset($data)) {
                    if ($CMSNT->site('auto_refund_order_failed_api') == 1) {
                        $User->RefundCredits($getUser['id'], $pay, "[Error] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    }
                    http_response_code(503);
                    die(json_encode(['status' => 'error', 'msg' => __('Mất kết nối đến kho hàng')]));
                }

                // Xử lý lỗi từ API
                if ($data['code'] != '200000') {
                    $User->RefundCredits($getUser['id'], $pay, "[Error 1] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    die(json_encode(['status' => 'error', 'msg' => __($data['message'])]));
                }

                // Lấy invoice code từ response
                $api_trans_id = $data['data']['code'];

                // Chờ API xử lý
                sleep(3);

                // Bước 2: Lấy danh sách activation codes
                $result_accounts = getInvoiceAPI_33($supplier['domain'], $supplier['token'], $api_trans_id, $supplier['proxy']);
                $result_accounts = json_decode($result_accounts, true);

                // Dữ liệu cơ bản cho product_sold (tránh lặp lại)
                $baseProductSoldData = [
                    'type'           => $supplier['domain'],
                    'product_code'   => NULL,
                    'supplier_id'    => $product['supplier_id'],
                    'trans_id'       => $trans_id,
                    'buyer'          => $getUser['id'],
                    'seller'         => $product['user_id'],
                    'create_gettime' => gettime()
                ];

                // Helper function để insert product_sold và tăng $isValue
                $insertProductSold = function ($uid, $account) use ($CMSNT, $baseProductSoldData, &$isValue) {
                    $data = array_merge($baseProductSoldData, [
                        'uid'     => $uid,
                        'account' => $account
                    ]);
                    if ($CMSNT->insert("product_sold", $data)) {
                        $isValue++;
                        return true;
                    }
                    return false;
                };

                // Xử lý response lấy activation codes
                if (isset($result_accounts) && $result_accounts['code'] == '200000') {
                    // Kiểm tra có data không
                    if (!isset($result_accounts['data']['data']) || empty($result_accounts['data']['data'])) {
                        // Không có activation codes -> ghi log lỗi
                        $insertProductSold(time() . rand(1000, 9999), __('[Error 2] Vui lòng liên hệ Admin để nhận Activation Code'));
                    } else {
                        // Lặp qua từng activation code
                        foreach ($result_accounts['data']['data'] as $account_item) {
                            $uid = check_string($account_item['code']);
                            $account_text = 'Activation Code: ' . $uid;

                            // Insert activation code, nếu thất bại thì insert error record
                            if (!$insertProductSold($uid, $account_text)) {
                                $insertProductSold(time() . rand(1000, 9999), __('[Error 1] Vui lòng liên hệ Admin để nhận Activation Code'));
                            }
                        }
                    }
                } else {
                    // Lấy activation codes thất bại -> ghi log
                    $insertProductSold(time() . rand(1000, 9999), __('Vui lòng liên hệ Admin để nhận Activation Code'));
                }
            }
            //
            if ($supplier['type'] == 'API_34') {
                $response = buy_API_34($supplier['domain'], $supplier['api_key'], $product['api_id'], $amount, $supplier['proxy']);
                $data = json_decode($response, true);

                // Xử lý lỗi kết nối
                if (!isset($data)) {
                    if ($CMSNT->site('auto_refund_order_failed_api') == 1) {
                        $User->RefundCredits($getUser['id'], $pay, "[Error] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    }
                    http_response_code(503);
                    die(json_encode(['status' => 'error', 'msg' => __('Mất kết nối đến kho hàng')]));
                }

                // Xử lý lỗi từ API
                if (!isset($data['success']) || $data['success'] != true) {
                    $User->RefundCredits($getUser['id'], $pay, "[Error 1] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    $errorMsg = isset($data['message']) ? $data['message'] : 'Lỗi không xác định từ API';
                    die(json_encode(['status' => 'error', 'msg' => __($errorMsg)]));
                }

                $api_trans_id = NULL;

                // Lặp qua từng account trong accountData
                foreach ($data['data']['accountData'] as $account) {
                    $account = check_string($account);
                    $uid = explode('|', $account)[0];
                    $isInsertAPI = $CMSNT->insert("product_sold", [
                        'type'              => $supplier['domain'],
                        'product_code'      => NULL,
                        'supplier_id'       => $product['supplier_id'],
                        'trans_id'          => $trans_id,
                        'buyer'             => $getUser['id'],
                        'seller'            => $product['user_id'],
                        'uid'               => $uid,
                        'account'           => $account,
                        'create_gettime'    => gettime()
                    ]);
                    if ($isInsertAPI) {
                        $isValue++;
                    }
                }
            }
            //
            if ($supplier['type'] == 'API_35') {
                // api_key lưu email, token lưu token
                $response = buy_API_35($supplier['domain'], $supplier['api_key'], $supplier['token'], $product['api_id'], $amount, $supplier['proxy']);
                $data = json_decode($response, true);

                // Xử lý lỗi kết nối
                if (!isset($data)) {
                    if ($CMSNT->site('auto_refund_order_failed_api') == 1) {
                        $User->RefundCredits($getUser['id'], $pay, "[Error] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    }
                    http_response_code(503);
                    die(json_encode(['status' => 'error', 'msg' => __('Mất kết nối đến kho hàng')]));
                }

                // Xử lý lỗi từ API
                if (!isset($data['success']) || $data['success'] != true) {
                    $User->RefundCredits($getUser['id'], $pay, "[Error 1] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    $errorMsg = isset($data['message']) ? $data['message'] : 'Lỗi không xác định từ API';
                    die(json_encode(['status' => 'error', 'msg' => __($errorMsg)]));
                }

                // Lấy invoice_id làm trans_id
                $api_trans_id = isset($data['data']['invoice_id']) ? $data['data']['invoice_id'] : NULL;

                // Xử lý card_content - có thể chứa nhiều dòng với \r\n
                $card_content = isset($data['data']['card_content']) ? $data['data']['card_content'] : '';
                $lines = preg_split('/\r\n|\r|\n/', trim($card_content));

                foreach ($lines as $account) {
                    $account = trim($account);
                    if (empty($account)) continue;

                    $account = check_string($account);
                    $uid = explode('|', $account)[0];
                    $isInsertAPI = $CMSNT->insert("product_sold", [
                        'type'              => $supplier['domain'],
                        'product_code'      => NULL,
                        'supplier_id'       => $product['supplier_id'],
                        'trans_id'          => $trans_id,
                        'buyer'             => $getUser['id'],
                        'seller'            => $product['user_id'],
                        'uid'               => $uid,
                        'account'           => $account,
                        'create_gettime'    => gettime()
                    ]);
                    if ($isInsertAPI) {
                        $isValue++;
                    }
                }
            }
            //
            // API_36 - humkt.com
            if ($supplier['type'] == 'API_36') {
                // Bước 1: Tạo đơn hàng
                $response = buy_API_36($supplier['domain'], $supplier['token'], $product['api_id'], $amount, $supplier['proxy']);
                $data = json_decode($response, true);

                // Xử lý lỗi kết nối
                if (!isset($data)) {
                    if ($CMSNT->site('auto_refund_order_failed_api') == 1) {
                        $User->RefundCredits($getUser['id'], $pay, "[Error] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    }
                    http_response_code(503);
                    die(json_encode(['status' => 'error', 'msg' => __('Mất kết nối đến kho hàng')]));
                }

                // Xử lý lỗi từ API
                if (!isset($data['code']) || $data['code'] != 1) {
                    $User->RefundCredits($getUser['id'], $pay, "[Error 1] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    $errorMsg = isset($data['message']) ? $data['message'] : 'Lỗi không xác định từ API';
                    die(json_encode(['status' => 'error', 'msg' => __($errorMsg)]));
                }

                // Lấy order ID từ response
                $api_trans_id = isset($data['data']['id']) ? $data['data']['id'] : NULL;

                // Chờ API xử lý
                sleep(2);

                // Bước 2: Lấy chi tiết đơn hàng
                $order_response = getOrder_API_36($supplier['domain'], $supplier['token'], $api_trans_id, $supplier['proxy']);
                $order_data = json_decode($order_response, true);

                if (isset($order_data) && $order_data['code'] == 1 && isset($order_data['data']['items'])) {
                    // Lặp qua từng item
                    foreach ($order_data['data']['items'] as $account) {
                        $account = check_string($account);
                        $uid = explode('|', $account)[0];
                        $isInsertAPI = $CMSNT->insert("product_sold", [
                            'type'              => $supplier['domain'],
                            'product_code'      => NULL,
                            'supplier_id'       => $product['supplier_id'],
                            'trans_id'          => $trans_id,
                            'buyer'             => $getUser['id'],
                            'seller'            => $product['user_id'],
                            'uid'               => $uid,
                            'account'           => $account,
                            'create_gettime'    => gettime()
                        ]);
                        if ($isInsertAPI) {
                            $isValue++;
                        }
                    }
                } else {
                    // Nếu không lấy được order detail, hoàn tiền
                    $User->RefundCredits($getUser['id'], $pay, "[Error 2] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    die(json_encode(['status' => 'error', 'msg' => __('Không thể lấy chi tiết đơn hàng từ API')]));
                }
            }
            //
            // API_37
            if ($supplier['type'] == 'API_37') {
                // Gọi API mua hàng
                $response = buy_API_37($supplier['domain'], $supplier['token'], $product['api_id'], $amount, $supplier['proxy']);
                $data = json_decode($response, true);

                // Xử lý lỗi kết nối
                if (!isset($data)) {
                    if ($CMSNT->site('auto_refund_order_failed_api') == 1) {
                        $User->RefundCredits($getUser['id'], $pay, "[Error] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    }
                    http_response_code(503);
                    die(json_encode(['status' => 'error', 'msg' => __('Mất kết nối đến kho hàng')]));
                }

                // Xử lý lỗi từ API
                if (!isset($data['status']) || $data['status'] != 1) {
                    $User->RefundCredits($getUser['id'], $pay, "[Error 1] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    $errorMsg = isset($data['message']) ? $data['message'] : 'Lỗi không xác định từ API';
                    die(json_encode(['status' => 'error', 'msg' => __($errorMsg)]));
                }

                // Lấy order_id làm trans_id
                $api_trans_id = isset($data['order_id']) ? $data['order_id'] : NULL;

                // Xử lý data - chứa các keys, có thể nhiều dòng
                $keys_data = isset($data['data']) ? $data['data'] : '';
                $lines = preg_split('/\r\n|\r|\n/', trim($keys_data));

                foreach ($lines as $account) {
                    $account = trim($account);
                    if (empty($account)) continue;

                    $account = check_string($account);
                    $uid = explode('|', $account)[0];
                    $isInsertAPI = $CMSNT->insert("product_sold", [
                        'type'              => $supplier['domain'],
                        'product_code'      => NULL,
                        'supplier_id'       => $product['supplier_id'],
                        'trans_id'          => $trans_id,
                        'buyer'             => $getUser['id'],
                        'seller'            => $product['user_id'],
                        'uid'               => $uid,
                        'account'           => $account,
                        'create_gettime'    => gettime()
                    ]);
                    if ($isInsertAPI) {
                        $isValue++;
                    }
                }
            }
            //
            // API_38 - API Shared (Partner API với MD5 Signature)
            if ($supplier['type'] == 'API_38') {
                // API_38 sử dụng api_key làm app_id và token làm app_key
                $app_id = $supplier['api_key'];
                $app_key = $supplier['token'];

                // Gọi API mua hàng (trade)
                $response = buy_API_38($supplier['domain'], $app_id, $app_key, $product['api_id'], $amount, $supplier['proxy']);
                $data = json_decode($response, true);

                // Debug log nếu cần
                if ($CMSNT->site('debug_api_suppliers') == 1) {
                    error_log("API_38 Buy Response: " . $response);
                }

                // Xử lý lỗi kết nối
                if (!isset($data)) {
                    if ($CMSNT->site('auto_refund_order_failed_api') == 1) {
                        $User->RefundCredits($getUser['id'], $pay, "[Error] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    }
                    http_response_code(503);
                    die(json_encode(['status' => 'error', 'msg' => __('Mất kết nối đến kho hàng')]));
                }

                // Xử lý lỗi từ API (code != 200)
                if (!isset($data['code']) || $data['code'] != 200) {
                    $errorMsg = isset($data['msg']) ? $data['msg'] : 'Lỗi không xác định từ API';
                    $User->RefundCredits($getUser['id'], $pay, "[Error 1] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    die(json_encode(['status' => 'error', 'msg' => __($errorMsg)]));
                }

                // Lấy tradeNo từ response
                $tradeNo = isset($data['data']['tradeNo']) ? $data['data']['tradeNo'] : null;
                if (!$tradeNo) {
                    $User->RefundCredits($getUser['id'], $pay, "[Error 2] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    die(json_encode(['status' => 'error', 'msg' => __('Không nhận được mã đơn hàng từ API')]));
                }

                $api_trans_id = $tradeNo;

                // Gọi API query để lấy chi tiết đơn hàng (lấy secret/nội dung giao hàng)
                $orderResponse = getOrder_API_38($supplier['domain'], $app_id, $app_key, $tradeNo, $supplier['proxy']);
                $orderData = json_decode($orderResponse, true);

                // Debug log
                if ($CMSNT->site('debug_api_suppliers') == 1) {
                    error_log("API_38 Order Response: " . $orderResponse);
                }

                if (isset($orderData['code']) && $orderData['code'] == 200 && isset($orderData['data']['secret'])) {
                    // secret chứa nội dung giao hàng (có thể nhiều dòng)
                    $secret = $orderData['data']['secret'];
                    $lines = preg_split('/\r\n|\r|\n/', trim($secret));

                    foreach ($lines as $account) {
                        $account = trim($account);
                        if (empty($account)) continue;

                        $account = check_string($account);
                        $uid = explode('|', $account)[0];
                        $isInsertAPI = $CMSNT->insert("product_sold", [
                            'type'              => $supplier['domain'],
                            'product_code'      => NULL,
                            'supplier_id'       => $product['supplier_id'],
                            'trans_id'          => $trans_id,
                            'buyer'             => $getUser['id'],
                            'seller'            => $product['user_id'],
                            'uid'               => $uid,
                            'account'           => $account,
                            'create_gettime'    => gettime()
                        ]);
                        if ($isInsertAPI) {
                            $isValue++;
                        }
                    }
                } else {
                    // Nếu không lấy được order detail, hoàn tiền
                    $User->RefundCredits($getUser['id'], $pay, "[Error 3] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    die(json_encode(['status' => 'error', 'msg' => __('Không thể lấy chi tiết đơn hàng từ API')]));
                }
            }
            //
            // SHOPKEY - API với header-based authentication (X-API-Key, X-API-Secret)
            if ($supplier['type'] == 'SHOPKEY') {
                // Bước 1: Gọi API tạo đơn hàng
                $response = buy_API_SHOPKEY($supplier['domain'], $supplier['coupon'], $supplier['api_key'], $supplier['token'], $product['api_id'], $amount, $supplier['proxy']);
                $data = json_decode($response, true);
                $http_code = isset($data['http_code']) ? validate_string($data['http_code']) : 0;

                // Xử lý lỗi kết nối
                if (!isset($data)) {
                    if ($CMSNT->site('auto_refund_order_failed_api') == 1) {
                        $User->RefundCredits($getUser['id'], $pay, "[$http_code][Error] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    }
                    http_response_code(503);
                    die(json_encode(['status' => 'error', 'msg' => "[$http_code] " . __('Mất kết nối đến kho hàng')]));
                }

                // Xử lý lỗi từ API
                if (!isset($data['success']) || $data['success'] != true) {
                    $errorMsg = isset($data['message']) ? $data['message'] : (isset($data['msg']) ? $data['msg'] : 'Lỗi không xác định từ API');
                    $User->RefundCredits($getUser['id'], $pay, "[Error 1] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);

                    // Kiểm tra nếu có HTTP code là 402 thì gửi thông báo qua Telegram
                    if (isset($data['http_code']) && $data['http_code'] == 402) {
                        $my_text = $CMSNT->site('noti_api_out_of_money');
                        $my_text = str_replace('{domain}', $_SERVER['SERVER_NAME'], $my_text);
                        $my_text = str_replace('{username}', $getUser['username'], $my_text);
                        $my_text = str_replace('{supplier_name}', $supplier['domain'], $my_text);
                        $my_text = str_replace('{product_name}', $product['name'], $my_text);
                        $my_text = str_replace('{product_id}', $product['id'], $my_text);
                        $my_text = str_replace('{pay}', format_currency($pay), $my_text);
                        $my_text = str_replace('{amount}', format_cash($amount), $my_text);
                        $my_text = str_replace('{ip}', myip(), $my_text);
                        $my_text = str_replace('{time}', gettime(), $my_text);
                        sendMessAdmin($my_text);
                    }
                    http_response_code(503);
                    die(json_encode(['status' => 'error', 'msg' => __($errorMsg)]));
                }

                // Lấy trans_id từ orders[0]
                $shopkey_trans_id = null;
                if (isset($data['data']['orders']) && is_array($data['data']['orders']) && count($data['data']['orders']) > 0) {
                    $shopkey_trans_id = $data['data']['orders'][0]['trans_id'];
                }

                if (empty($shopkey_trans_id)) {
                    $User->RefundCredits($getUser['id'], $pay, "[Error 2] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    die(json_encode(['status' => 'error', 'msg' => __('Không nhận được mã đơn hàng từ API')]));
                }

                $api_trans_id = $shopkey_trans_id;

                // Bước 2: Gọi API lấy chi tiết đơn hàng để lấy delivery items
                $order_response = getOrder_API_SHOPKEY($supplier['domain'], $supplier['api_key'], $supplier['token'], $shopkey_trans_id, $supplier['proxy']);
                $order_data = json_decode($order_response, true);

                // Xử lý lấy delivery items
                if (isset($order_data['success']) && $order_data['success'] == true && isset($order_data['data']['delivery']['items'])) {
                    foreach ($order_data['data']['delivery']['items'] as $account) {
                        $account = check_string($account);
                        $uid = explode('|', $account)[0];

                        $isInsertAPI = $CMSNT->insert("product_sold", [
                            'type'              => $supplier['domain'],
                            'product_code'      => NULL,
                            'supplier_id'       => $product['supplier_id'],
                            'trans_id'          => $trans_id,
                            'buyer'             => $getUser['id'],
                            'seller'            => $product['user_id'],
                            'uid'               => $uid,
                            'account'           => $account,
                            'create_gettime'    => gettime()
                        ]);
                        if ($isInsertAPI) {
                            $isValue++;
                        }
                    }
                } else {
                    // Nếu không lấy được delivery items, hoàn tiền
                    $User->RefundCredits($getUser['id'], $pay, "[Error 3] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                    die(json_encode(['status' => 'error', 'msg' => __('Không thể lấy chi tiết đơn hàng từ API')]));
                }
            }
            //

        }
        // Lấy hàng từ Kho
        else {

            // LẤY HÀNG TỪ KHO
            $order_by = 'ORDER BY id ASC';
            if ($product['order_by'] == 1) {
                // Check live gần nhất
                $order_by = 'ORDER BY time_check_live DESC';
            } else if ($product['order_by'] == 2) {
                // Import lâu nhất
                $order_by = 'ORDER BY id ASC';
            } else if ($product['order_by'] == 3) {
                // Import gần nhất
                $order_by = 'ORDER BY id DESC';
            } else if ($product['order_by'] == 4) {
                // Ngẫu nhiên
                $order_by = 'ORDER BY RAND()';
            }

            // Bắt đầu transaction
            $CMSNT->query("START TRANSACTION");
            $success = true;
            $inserted_accounts = [];

            // Sử dụng FOR UPDATE để lock rows, tránh race condition khi nhiều người mua cùng lúc
            $for_update = ($CMSNT->site('isForUpdateBuy') == 1) ? ' FOR UPDATE' : '';

            try {
                foreach ($CMSNT->get_list_safe("SELECT * FROM `product_stock` WHERE `product_code` = ? $order_by LIMIT ?" . $for_update, [$product['code'], $amount]) as $product_stock) {
                    $isInsertSold = $CMSNT->insert('product_sold', [
                        'type'              => $product_stock['type'],
                        'product_code'      => $product_stock['product_code'],
                        'supplier_id'       => $product['supplier_id'],
                        'trans_id'          => $trans_id,
                        'buyer'             => $getUser['id'],
                        'seller'            => $product_stock['seller'],
                        'uid'               => $product_stock['uid'],
                        'account'           => $product_stock['account'],
                        'create_gettime'    => gettime(),
                        'time_check_live'   => $product_stock['time_check_live']
                    ]);

                    if ($isInsertSold) {
                        $isValue++;
                        $inserted_accounts[] = $product_stock['id'];
                        $isRemoved = $CMSNT->remove('product_stock', " `id` = ?", [$product_stock['id']]);
                        if (!$isRemoved) {
                            throw new Exception('Lỗi khi xóa tài khoản khỏi kho');
                        }
                    } else {
                        throw new Exception('Lỗi khi insert tài khoản');
                    }
                }

                // Kiểm tra số lượng xuất có đủ không TRƯỚC KHI COMMIT
                if ($isValue < $amount) {
                    throw new Exception('Số lượng còn lại trong hệ thống không đủ');
                }

                // Nếu tất cả đều thành công, commit transaction
                $CMSNT->query("COMMIT");
            } catch (Exception $e) {
                // Nếu có lỗi, rollback transaction
                $CMSNT->query("ROLLBACK");

                // Hoàn tiền cho người dùng
                $User->RefundCredits($getUser['id'], $pay, __('[Error 4] Hoàn tiền đơn hàng mua tài khoản do lỗi hệ thống') . ' #' . $trans_id, 'REFUND_' . $trans_id);
                die(json_encode(['status' => 'error', 'msg' => $e->getMessage()]));
            }
        }


        if ($isValue > 0) {
            // TIỀN HOA HỒNG MẶC ĐỊNH LÀ 0
            $commission_fee = 0;
            // TÍNH TIỀN HOA HỒNG
            if ($CMSNT->site('affiliate_status') == 1 && $getUser['ref_id'] != 0) {
                $ck = $CMSNT->site('affiliate_ck');
                if (getRowRealtime('users', $getUser['ref_id'], 'ref_ck') != 0) {
                    $ck = getRowRealtime('users', $getUser['ref_id'], 'ref_ck');
                }
                $commission_fee = $pay * $ck / 100;
            }

            /* TẠO ĐƠN HÀNG */
            $isInsertOrder = $CMSNT->insert('product_order', [
                'trans_id'          => $trans_id,
                'api_transid'       => $api_trans_id,
                'supplier_id'       => $product['supplier_id'],
                'product_id'        => $product['id'],
                'product_name'      => $product['name'],
                'buyer'             => $getUser['id'],
                'seller'            => $product['user_id'],
                'amount'            => $amount,
                'money'             => $money,
                'pay'               => $pay,
                'cost'              => $product['cost'] * $amount,
                //'commission_fee'    => $commission_fee,
                'create_gettime'    => gettime(),
                'update_gettime'    => gettime(),
                'trash'             => 0,
                'status_view_order' => $getUser['status_view_order'],
                'ip'                => myip(),
                'device'            => getUserAgent()
            ]);
            if ($isInsertOrder) {
                if ($CMSNT->site('cong_tien_nguoi_ban') == 1) {
                    $User->AddCredits($product['user_id'], $pay, __('Doanh thu đơn hàng mua tài khoản') . ' <b>' . $product['name'] . '</b> - #' . $trans_id, 'DOANH_THU_' . $trans_id);
                }
                // CỘNG HOA HỒNG
                if ($CMSNT->site('affiliate_status') == 1 && $getUser['ref_id'] != 0) {
                    $User->AddCommission($getUser['ref_id'], $getUser['id'], $commission_fee, __('Hoa hồng thành viên' . ' ' . $getUser['username']));
                }
                /* SỬ DỤNG MÃ GIẢM GIÁ */
                if (isset($discount_coupon) && $discount_coupon > 0 && isset($coupon)) {
                    $isAddCoupon = $CMSNT->cong("coupons", "used", 1, " `code` = ? ", [$coupon]);
                    if ($isAddCoupon) {
                        $coupon_data = $CMSNT->get_row_safe("SELECT * FROM `coupons` WHERE `code` = ?", [$coupon]);
                        if ($coupon_data) {
                            $CMSNT->insert("coupon_used", [
                                'coupon_id'     => $coupon_data['id'],
                                'user_id'       => $getUser['id'],
                                'trans_id'      => $trans_id,
                                'create_gettime'    => gettime()
                            ]);
                        }
                    }
                }
                /* CỘNG ĐÃ BÁN */
                $CMSNT->cong('products', 'sold', $amount, " `id` = ? ", [$product['id']]);
                $accounts = [];
                $file_txt_email = '';
                foreach ($CMSNT->get_list_safe("SELECT * FROM `product_sold` WHERE `trans_id` = ?", [$trans_id]) as $account_sold) {
                    $accounts[] = preg_replace("/\r/", "", $account_sold['account']);
                    $file_txt_email .= PHP_EOL . htmlspecialchars_decode($account_sold['account']);
                }

                // CẬP NHẬT USER
                $CMSNT->update('users', [
                    'time_request'  => time()
                ], " `id` = ?", [$getUser['id']]);

                // TẠO LOG GIAO DỊCH GẦN ĐÂY
                $CMSNT->insert('order_log', [
                    'buyer'         => $getUser['id'],
                    'product_name'  => $product['name'],
                    'pay'           => $pay,
                    'amount'        => $amount,
                    'create_time'   => time(),
                    'is_virtual'    => 0
                ]);
                if ($CMSNT->site('email_temp_subject_buy_order') != '') {
                    $content = $CMSNT->site('email_temp_content_buy_order');
                    $content = str_replace('{domain}', $_SERVER['SERVER_NAME'], $content);
                    $content = str_replace('{title}', $CMSNT->site('title'), $content);
                    $content = str_replace('{username}', $getUser['username'], $content);
                    $content = str_replace('{ip}', myip(), $content);
                    $content = str_replace('{device}', getUserAgent(), $content);
                    $content = str_replace('{time}', gettime(), $content);
                    $content = str_replace('{product}', $product['name'], $content);
                    $content = str_replace('{amount}', format_cash($amount), $content);
                    $content = str_replace('{trans_id}', $trans_id, $content);
                    $content = str_replace('{pay}', format_currency($pay), $content);
                    ////////////////////////////////////////////////////////////////////
                    $subject = $CMSNT->site('email_temp_subject_buy_order');
                    $subject = str_replace('{domain}', $_SERVER['SERVER_NAME'], $subject);
                    $subject = str_replace('{title}', $CMSNT->site('title'), $subject);
                    $subject = str_replace('{username}', $getUser['username'], $subject);
                    $subject = str_replace('{ip}', myip(), $subject);
                    $subject = str_replace('{device}', getUserAgent(), $subject);
                    $subject = str_replace('{time}', gettime(), $subject);
                    $subject = str_replace('{product}', $product['name'], $subject);
                    $subject = str_replace('{amount}', format_cash($amount), $subject);
                    $subject = str_replace('{trans_id}', $trans_id, $subject);
                    $subject = str_replace('{pay}', format_currency($pay), $subject);
                    $bcc = $CMSNT->site('title');

                    // Tạo tên file tạm thời với hash md5 để tránh dự đoán
                    $file_txt_name = md5($trans_id . time() . rand(1000, 9999)) . ".txt";
                    $tmp_dir = __DIR__ . "/../../tmp/";

                    // Đảm bảo thư mục tmp tồn tại với quyền đầy đủ
                    if (!file_exists($tmp_dir)) {
                        @mkdir($tmp_dir, 0777, true);
                    }

                    $tmp_file = $tmp_dir . $file_txt_name;
                    $email_sent = false;

                    try {
                        // Ghi nội dung vào file
                        if (@file_put_contents($tmp_file, $file_txt_email) !== false) {
                            // Gửi email với file đính kèm
                            sendCSM($getUser['email'], $getUser['username'], $subject, $content, $bcc, $tmp_file);
                            $email_sent = true;
                        } else {
                            // Không thể ghi file, gửi email không có đính kèm
                            error_log("Không thể ghi file tạm: " . $tmp_file . ". Gửi email không có đính kèm.");
                            // Tạo nội dung email mới bao gồm cả dữ liệu tài khoản
                            $content_with_accounts = $content . "<br><br><strong>Dữ liệu tài khoản:</strong><br>" . nl2br(htmlspecialchars($file_txt_email));
                            // Gửi email không có đính kèm
                            sendCSM($getUser['email'], $getUser['username'], $subject, $content_with_accounts, $bcc, '');
                            $email_sent = true;
                        }
                    } catch (Exception $e) {
                        // Ghi log lỗi
                        error_log("Lỗi gửi email: " . $e->getMessage());
                    } finally {
                        // Dọn dẹp: luôn xóa file sau khi sử dụng
                        if (file_exists($tmp_file)) {
                            @unlink($tmp_file);
                        }
                    }
                }
                if ($CMSNT->site('noti_buy_product') != '') {
                    /** SEND NOTI CHO ADMIN */
                    $my_text = $CMSNT->site('noti_buy_product');
                    $my_text = str_replace('{domain}', $_SERVER['SERVER_NAME'], $my_text);
                    $my_text = str_replace('{username}', $getUser['username'], $my_text);
                    $my_text = str_replace('{product}', $product['name'], $my_text);
                    $my_text = str_replace('{amount}', format_cash($amount), $my_text);
                    $my_text = str_replace('{trans_id}', $trans_id, $my_text);
                    $my_text = str_replace('{pay}', format_currency($pay), $my_text);
                    $my_text = str_replace('{ip}', myip(), $my_text);
                    $my_text = str_replace('{time}', gettime(), $my_text);
                    sendMessAdmin($my_text);
                }
                $isTaphoammo = isset($_REQUEST['is_taphoammo']) ? true : false;
                if ($isTaphoammo) {
                    $formatted_accounts = array_map(function ($account) {
                        return ["product" => $account];
                    }, $accounts);
                    die(json_encode($formatted_accounts, JSON_PRETTY_PRINT));
                } else {
                    die(json_encode([
                        'status'    => 'success',
                        'msg'       => __('Tạo đơn hàng thành công!'),
                        'trans_id'  => $trans_id,
                        'data'      => $accounts
                    ]));
                }
            }
        } else {
            if ($product['supplier_id'] != 0) {
                if ($CMSNT->site('auto_refund_order_failed_api') == 1) {
                    $User->RefundCredits($getUser['id'], $pay, "[Error 2] " . __('Hoàn tiền đơn hàng mua tài khoản') . " <b>" . $product['name'] . "</b> - #" . $trans_id, 'REFUND_' . $trans_id);
                }
                die(json_encode(['status' => 'error', 'msg' => __('Mất kết nối đến kho hàng')]));
            } else {
                $User->RefundCredits($getUser['id'], $pay, __('[Error 2] Hoàn tiền đơn hàng mua tài khoản') . ' #' . $trans_id, 'REFUND_' . $trans_id);
                die(json_encode(['status' => 'error', 'msg' => __('Số lượng còn lại trong hệ thống không đủ')]));
            }
        }
        die(json_encode(['status' => 'error', 'msg' => 'ERROR 1 - ' . __('System error')]));
    } else {
        die(json_encode(['status' => 'error', 'msg' => 'ERROR 2 - ' . __('Vui lòng thử lại')]));
    }
}


if ($_REQUEST['action'] == 'total_payment') {
    $product_id = validate_int($_REQUEST['id'], 1);
    if ($product_id === false) {
        die(json_encode(['status' => 'error', 'msg' => __('ID sản phẩm không hợp lệ!')]));
    }

    $amount = validate_int($_REQUEST['amount'], 1);
    if ($amount === false) {
        die(json_encode(['status' => 'error', 'msg' => __('Số lượng không hợp lệ!')]));
    }

    if (!$product = $CMSNT->get_row_safe("SELECT * FROM `products` WHERE `id` = ? AND `status` = 1", [$product_id])) {
        die(json_encode(['status' => 'error', 'msg' => __('Sản phẩm không khả dụng')]));
    }
    $discount = 0; // số tiền được giảm
    $discount_coupon = 0;
    $price = $product['discount'] == 0 ? $product['price'] : $product['price'] - $product['price'] * $product['discount'] / 100;
    $money = $amount * $price; // giá gốc
    $pay = $money; // số tiền cần thanh toán nếu không có khuyến mãi
    if (!empty($_REQUEST['token'])) {
        $token = validate_alphanumeric($_REQUEST['token'], 255);
        if ($token !== false && $getUser = $CMSNT->get_row_safe("SELECT * FROM `users` WHERE `token` = ? AND `banned` = 0", [$token])) {
            if ($getUser['discount'] == 0) {
                // Giảm giá bằng điều kiện nếu user ko dc ck
                $discount = $money * getDiscount($amount, $product['id']) / 100;
                // Xử lý giảm giá bằng coupon
                if (!empty($_REQUEST['coupon'])) {
                    $coupon = validate_alphanumeric($_REQUEST['coupon'], 50);
                    if ($coupon !== false) {
                        // Lấy số tiền giảm từ Coupon
                        $discount_coupon = checkCoupon($product['id'], $coupon, $getUser['id'], $money);
                    }
                }
                // Số tiền thanh toán sau khi trừ discount
                $pay = $money - $discount - $discount_coupon;
            } else {
                $discount = $money * $getUser['discount'] / 100;
                $pay = $money - $discount;
            }
        }
    } else {
        $discount = $money * getDiscount($amount, $product['id']) / 100;
    }

    $price          = $pay; // Số tiền thanh toán ban đầu chưa bao gồm VAT
    $price_vat      = $CMSNT->site('tax_vat') > 0 ? $pay * $CMSNT->site('tax_vat') / 100 : 0; // Số tiền thuế VAT cần trả thêm
    $pay            = $price + $price_vat; // Số tiền thanh toán sau khi tính thuế VAT

    die(json_encode([
        'status'    => 'success',
        'money'     => format_currency($money),
        'discount'      => format_currency($money - $price),
        'discount_number'  => $money - $price,
        'pay'           => format_currency($pay),               // Số tiền thanh toán sau khi tính thuế VAT
        'price'         => format_currency($price),             // Số tiền chưa tính thuế
        'price_vat'     => format_currency($price_vat),         // Số tiền thuế VAT
        'tax_vat'       => floatval($CMSNT->site('tax_vat'))    // Thuế VAT (%)

    ]));
}

die(json_encode([
    'status'    => 'error',
    'msg'       => __('Request does not exist')
]));
