<?php

define("IN_SITE", true);
require_once(__DIR__ . "/../../config.php");
require_once(__DIR__ . "/../../libs/db.php");
require_once(__DIR__ . "/../../libs/lang.php");
require_once(__DIR__ . "/../../libs/helper.php");
require_once(__DIR__ . '/../../libs/database/users.php');
require_once(__DIR__ . '/../../models/is_admin.php');


if (!isset($_POST['action'])) {
    $data = json_encode([
        'status'    => 'error',
        'msg'       => 'The Request Not Found'
    ]);
    die($data);
}
if ($CMSNT->site('status_demo') != 0) {
    die(json_encode(['status' => 'error', 'msg' => __('Chức năng này không thể sử dụng trên website demo')]));
}

// Xử lý duyệt sản phẩm CTV
if ($_POST['action'] == 'approveCtvProduct') {
    if (checkPermission($getUser['admin'], 'edit_product_ctv') != true) {
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }

    $product_id = intval(check_string($_POST['product_id']));
    if (!$product = $CMSNT->get_row(" SELECT * FROM `products` WHERE `id` = $product_id AND `pending` = 1 ")) {
        die(json_encode(['status' => 'error', 'msg' => __('Sản phẩm không tồn tại hoặc đã được duyệt')]));
    }

    // Kiểm tra xem sản phẩm có thuộc về CTV không
    $ctv = $CMSNT->get_row(" SELECT * FROM `users` WHERE `id` = '" . $product['user_id'] . "' AND `ctv` = 1 ");
    if (!$ctv) {
        die(json_encode(['status' => 'error', 'msg' => __('Sản phẩm không thuộc về CTV')]));
    }

    // Duyệt sản phẩm: set pending = 0, status = 1
    $isUpdate = $CMSNT->update("products", [
        'pending' => 0,
        'status' => 1,
        'update_gettime' => gettime()
    ], " `id` = $product_id ");

    if ($isUpdate) {
        // Log hành động
        $CMSNT->insert("logs", [
            'user_id' => $getUser['id'],
            'ip' => myip(),
            'device' => getUserAgent(),
            'createdate' => gettime(),
            'action' => __('Admin duyệt sản phẩm CTV') . ': ' . $product['name'] . ' (ID: ' . $product_id . ')'
        ]);

        die(json_encode(['status' => 'success', 'msg' => __('Duyệt sản phẩm thành công!')]));
    } else {
        die(json_encode(['status' => 'error', 'msg' => __('Có lỗi xảy ra khi duyệt sản phẩm')]));
    }
}

// Xử lý duyệt nhiều sản phẩm CTV cùng lúc
if ($_POST['action'] == 'bulkApproveCtvProducts') {
    if (checkPermission($getUser['admin'], 'edit_product_ctv') != true) {
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }

    if (empty($_POST['product_ids'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng chọn ít nhất một sản phẩm')]));
    }

    $product_ids_json = check_string($_POST['product_ids']);
    $product_ids = json_decode($product_ids_json, true);

    if (!is_array($product_ids) || empty($product_ids)) {
        die(json_encode(['status' => 'error', 'msg' => __('Dữ liệu không hợp lệ')]));
    }

    $product_ids = array_map('intval', $product_ids);
    $approved_count = 0;
    $error_count = 0;
    $product_names = [];

    foreach ($product_ids as $product_id) {
        if ($product_id <= 0) continue;

        // Kiểm tra sản phẩm có tồn tại và đang chờ duyệt không
        if (!$product = $CMSNT->get_row(" SELECT * FROM `products` WHERE `id` = $product_id AND `pending` = 1 ")) {
            $error_count++;
            continue;
        }

        // Kiểm tra xem sản phẩm có thuộc về CTV không
        $ctv = $CMSNT->get_row(" SELECT * FROM `users` WHERE `id` = '" . $product['user_id'] . "' AND `ctv` = 1 ");
        if (!$ctv) {
            $error_count++;
            continue;
        }

        // Duyệt sản phẩm: set pending = 0, status = 1
        $isUpdate = $CMSNT->update("products", [
            'pending' => 0,
            'status' => 1,
            'update_gettime' => gettime()
        ], " `id` = $product_id ");

        if ($isUpdate) {
            $product_names[] = $product['name'];
            $approved_count++;
        } else {
            $error_count++;
        }
    }

    if ($approved_count > 0) {
        // Log hành động
        $CMSNT->insert("logs", [
            'user_id' => $getUser['id'],
            'ip' => myip(),
            'device' => getUserAgent(),
            'createdate' => gettime(),
            'action' => __('Admin duyệt hàng loạt sản phẩm CTV') . ': ' . $approved_count . ' sản phẩm (' .
                implode(', ', array_slice($product_names, 0, 5)) .
                (count($product_names) > 5 ? '...' : '') . ')'
        ]);

        $success_msg = __('Đã duyệt thành công') . ' ' . $approved_count . ' ' . __('sản phẩm');
        if ($error_count > 0) {
            $success_msg .= ' (' . $error_count . ' ' . __('sản phẩm không thể duyệt') . ')';
        }

        die(json_encode(['status' => 'success', 'msg' => $success_msg]));
    } else {
        die(json_encode(['status' => 'error', 'msg' => __('Không có sản phẩm nào được duyệt')]));
    }
}



if ($_POST['action'] == 'cap_nhat_san_pham_nhanh') {
    if (checkPermission($getUser['admin'], 'edit_product') != true) {
        die(json_encode(['status' => 'error', 'msg' => 'Bạn không có quyền sử dụng tính năng này']));
    }
    $id = intval(check_string($_POST['id']));
    if (!$product = $CMSNT->get_row(" SELECT * FROM `products` WHERE `id` = $id ")) {
        die(json_encode(['status' => 'error', 'msg' => 'Sản phẩm không tồn tại trong hệ thống']));
    }
    // Cập nhật chuyên mục nhanh
    if (!empty($_POST['category_id'])) {
        $isUpdate = $CMSNT->update("products", [
            'category_id'    => check_string($_POST['category_id'])
        ], " `id` = $id ");
    }
    // Cập nhật trạng thái nhanh
    if (isset($_POST['status']) && $_POST['status'] != '' && $_POST['status'] != 'keep') {
        $status = check_string($_POST['status']);
        if ($status == 'ON') {
            $status = 1;
        } else {
            $status = 0;
        }
        $isUpdate = $CMSNT->update("products", [
            'status'    => $status
        ], " `id` = $id ");
    }
    // Cập nhật cho phép kết nối API nhanh
    if (isset($_POST['allow_api']) && $_POST['allow_api'] !== '') {
        $allow_api = intval(check_string($_POST['allow_api']));
        $isUpdate = $CMSNT->update("products", [
            'allow_api'    => $allow_api
        ], " `id` = $id ");
    }
    // Cập nhật discount nhanh
    if (!empty($_POST['discount'])) {
        $discount = check_string($_POST['discount']);
        $isUpdate = $CMSNT->update("products", [
            'discount'    => $discount
        ], " `id` = $id ");
    }
    // Cập nhật mô tả ngắn nhanh
    if (!empty($_POST['short_desc'])) {
        $short_desc = check_string($_POST['short_desc']);
        $isUpdate = $CMSNT->update("products", [
            'short_desc'    => $short_desc
        ], " `id` = $id ");
    }
    // Cập nhật giá bán lẻ
    if (!empty($_POST['price'])) {
        $price = check_string($_POST['price']);
        $isUpdate = $CMSNT->update("products", [
            'price'    => $price
        ], " `id` = $id ");
    }
    // Cập nhật giá bán lẻ theo phần trăm
    if (!empty($_POST['pricePercent'])) {
        if (!$row = $CMSNT->get_row("SELECT * FROM `products` WHERE `id` = '$id' ")) {
            die(json_encode(['status' => 'error', 'msg' => __('Sản phẩm không tồn tại trong hệ thống')]));
        }
        $pricePercent = floatval(check_string($_POST['pricePercent']));
        $priceAction = isset($_POST['priceAction']) ? check_string($_POST['priceAction']) : 'increase';
        $cost = floatval($row['cost']); // Giá vốn

        if ($cost > 0) {
            if ($priceAction == 'increase') {
                $newPrice = $cost + ($cost * $pricePercent / 100);
            } else {
                $newPrice = $cost - ($cost * $pricePercent / 100);
                if ($newPrice < 0) {
                    $newPrice = 0;
                }
            }

            $isUpdate = $CMSNT->update("products", [
                'price' => $newPrice
            ], " `id` = $id ");
        }
    }
    // Cập nhật số lượng đã bán - đặt số lượng cụ thể
    if (isset($_POST['sold_set']) && $_POST['sold_set'] !== '') {
        $sold_set = intval(check_string($_POST['sold_set']));
        if ($sold_set >= 0) {
            $isUpdate = $CMSNT->update("products", [
                'sold' => $sold_set
            ], " `id` = $id ");
        }
    }
    // Cập nhật số lượng đã bán - điều chỉnh (cộng/trừ)
    if (isset($_POST['soldAdjust']) && $_POST['soldAdjust'] !== '') {
        if (!$row = $CMSNT->get_row("SELECT * FROM `products` WHERE `id` = '$id' ")) {
            die(json_encode(['status' => 'error', 'msg' => __('Sản phẩm không tồn tại trong hệ thống')]));
        }
        $soldAdjust = intval(check_string($_POST['soldAdjust']));
        $soldAction = isset($_POST['soldAction']) ? check_string($_POST['soldAction']) : 'add';
        $currentSold = intval($row['sold']); // Số lượng đã bán hiện tại

        if ($soldAction == 'add') {
            $newSold = $currentSold + $soldAdjust;
        } else {
            $newSold = $currentSold - $soldAdjust;
            if ($newSold < 0) {
                $newSold = 0;
            }
        }

        $isUpdate = $CMSNT->update("products", [
            'sold' => $newSold
        ], " `id` = $id ");
    }
    if (isset($isUpdate)) {

        $CMSNT->insert("logs", [
            'user_id'       => $getUser['id'],
            'ip'            => myip(),
            'device'        => getUserAgent(),
            'createdate'    => gettime(),
            'action'        => "Cập nhật nhanh sản phẩm (ID $id)"
        ]);
        die(json_encode(['status' => 'success', 'msg' => __('Cập nhật thành công!')]));
    }
    die(json_encode(['status' => 'error', 'msg' => __('Không có sản phẩm nào được thay đổi')]));
}

if ($_POST['action'] == 'cap_nhat_chuyen_muc_nhanh') {
    if (checkPermission($getUser['admin'], 'edit_product') != true) {
        die(json_encode(['status' => 'error', 'msg' => 'Bạn không có quyền sử dụng tính năng này']));
    }
    $id = intval(check_string($_POST['id']));
    if (!$category = $CMSNT->get_row(" SELECT * FROM `categories` WHERE `id` = $id ")) {
        die(json_encode(['status' => 'error', 'msg' => 'Chuyên mục không tồn tại trong hệ thống']));
    }

    $updateData = [];

    // Cập nhật chuyên mục cha nhanh
    if (!empty($_POST['category_id']) && $_POST['category_id'] != 'keep') {
        $updateData['parent_id'] = check_string($_POST['category_id']);
    }

    // Cập nhật trạng thái nhanh
    if (isset($_POST['status']) && $_POST['status'] != '' && $_POST['status'] != 'keep') {
        $status = check_string($_POST['status']);
        if ($status == 'ON') {
            $status = 1;
        } else {
            $status = 0;
        }
        $updateData['status'] = $status;
    }

    if (!empty($updateData)) {
        $isUpdate = $CMSNT->update("categories", $updateData, " `id` = $id ");
        if ($isUpdate) {

            $CMSNT->insert("logs", [
                'user_id'       => $getUser['id'],
                'ip'            => myip(),
                'device'        => getUserAgent(),
                'createdate'    => gettime(),
                'action'        => "Cập nhật nhanh chuyên mục (ID $id)"
            ]);
            die(json_encode(['status' => 'success', 'msg' => __('Cập nhật thành công!')]));
        }
    }
    die(json_encode(['status' => 'error', 'msg' => __('Không có gì được thay đổi')]));
}


if ($_POST['action'] == 'reset_total_money_users') {
    if (checkPermission($getUser['admin'], 'edit_user') != true) {
        die(json_encode([
            'status'    => 'error',
            'msg'       => __('Bạn không có quyền sử dụng tính năng này')
        ]));
    }
    $isUpdate = $CMSNT->update('users', [
        'total_money'  => 0
    ], " `total_money` > 0 ");
    if (isset($isUpdate)) {

        $CMSNT->insert("logs", [
            'user_id'       => $getUser['id'],
            'ip'            => myip(),
            'device'        => getUserAgent(),
            'createdate'    => gettime(),
            'action'        => 'Reset tổng nạp toàn bộ thành viên'
        ]);
        die(json_encode(['status' => 'success', 'msg' => 'Reset tổng nạp toàn bộ user thành công!']));
    }
    die(json_encode(['status' => 'error', 'msg' => __('Reset thất bại')]));
}
if ($_POST['action'] == 'update_status_user') {
    if (checkPermission($getUser['admin'], 'edit_user') != true) {
        die(json_encode(['status' => 'error', 'msg' => 'Bạn không có quyền sử dụng tính năng này']));
    }
    if (!$user = $CMSNT->get_row(" SELECT * FROM `users` WHERE `id` = '" . check_string($_POST['id']) . "' ")) {
        die(json_encode(['status' => 'error', 'msg' => 'Thành viên không tồn tại trong hệ thống']));
    }
    $isUpdate = $CMSNT->update("users", [
        'banned'    => !empty($_POST['status']) ? check_string($_POST['status']) : 0
    ], " `id` = '" . check_string($_POST['id']) . "' ");
    if ($isUpdate) {

        $CMSNT->insert("logs", [
            'user_id'       => $getUser['id'],
            'ip'            => myip(),
            'device'        => getUserAgent(),
            'createdate'    => gettime(),
            'action'        => 'Update Status User (' . $user['username'] . ' - ' . $user['id'] . ')'
        ]);
        die(json_encode(['status' => 'success', 'msg' => __('Cập nhật thành công!')]));
    }
    die(json_encode(['status' => 'error', 'msg' => __('Cập nhật thất bại')]));
}


if ($_POST['action'] == 'update_product_code_product_stock') {
    if (checkPermission($getUser['admin'], 'edit_stock_product') != true) {
        die(json_encode(['status' => 'error', 'msg' => 'Bạn không có quyền sử dụng tính năng này']));
    }
    if (!$row = $CMSNT->get_row(" SELECT * FROM `product_stock` WHERE `id` = '" . check_string($_POST['id']) . "' ")) {
        die(json_encode(['status' => 'error', 'msg' => 'Tài khoản không tồn tại trong hệ thống']));
    }
    $isUpdate = $CMSNT->update("product_stock", [
        'product_code'    => !empty($_POST['product_code']) ? check_string($_POST['product_code']) : $row['product_code']
    ], " `id` = '" . check_string($_POST['id']) . "' ");
    if ($isUpdate) {

        $CMSNT->insert("logs", [
            'user_id'       => $getUser['id'],
            'ip'            => myip(),
            'device'        => getUserAgent(),
            'createdate'    => gettime(),
            'action'        => 'Cập nhật mã kho hàng cho tài khoản (' . $row['uid'] . ')'
        ]);
        die(json_encode(['status' => 'success', 'msg' => 'Cập nhật kho hàng tài khoản ' . $row['uid'] . ' thành công!']));
    }
    die(json_encode(['status' => 'error', 'msg' => __('Cập nhật kho hàng tài khoản ' . $row['uid'] . ' thất bại')]));
}

if ($_POST['action'] == 'refundOrder') {
    if (checkPermission($getUser['admin'], 'refund_orders_product') != true) {
        die(json_encode(['status' => 'error', 'msg' => 'Bạn không có quyền sử dụng tính năng này']));
    }
    if (empty($_POST['id'])) {
        die(json_encode(['status' => 'error', 'msg' => 'ID đơn hàng không tồn tại']));
    }
    $id = check_string($_POST['id']);
    if (!$product_order = $CMSNT->get_row(" SELECT * FROM `product_order` WHERE `id` = '$id' ")) {
        die(json_encode(['status' => 'error', 'msg' => 'Đơn hàng không tồn tại trong hệ thống']));
    }
    if ($product_order['refund'] == 1) {
        die(json_encode(['status' => 'error', 'msg' => __('Đơn hàng này đã được hoàn tiền rồi')]));
    }
    if ($product_order['amount'] == 0 || $product_order['pay'] == 0) {
        die(json_encode(['status' => 'error', 'msg' => __('Đơn hàng này đã được hoàn tiền rồi')]));
    }
    if (empty($_POST['reason'])) {
        die(json_encode(['status' => 'error', 'msg' => 'Vui lòng nhập lý do hoàn tiền']));
    }
    $reason = check_string($_POST['reason']);
    // Bắt đầu transaction tổng thể để đảm bảo tính nhất quán
    $CMSNT->query("START TRANSACTION");

    try {
        // Lấy kiểu hoàn tiền (full/partial)
        $refundType = check_string($_POST['refundType']);
        $User = new users();
        $success = false;

        if ($refundType == 'partial') {
            $reason = 'Hoàn tiền một phần đơn hàng #' . $product_order['trans_id'] . ' (' . $reason . ')';
            // Lấy số lượng cần hoàn (nếu có)
            $partialQuantity = isset($_POST['partialQuantity']) ? intval($_POST['partialQuantity']) : 0;
            if ($partialQuantity > $product_order['amount']) {
                throw new Exception(__('Số lượng tài khoản cần hoàn vượt quá số lượng tài khoản của đơn hàng này.'));
            }
            if ($partialQuantity <= 0) {
                throw new Exception(__('Vui lòng nhập số lượng tài khoản cần hoàn.'));
            }
            $rate = $product_order['pay'] / $product_order['amount'];
            // Tổng số tiền Refund
            $amountRefund = $partialQuantity * $rate;

            $leftover = $product_order['amount'] - $partialQuantity;
            $commission_fee = 0;

            // 1. Thu hồi hoa hồng TRƯỚC (nếu có)
            $user = $CMSNT->get_row(" SELECT * FROM `users` WHERE `id` = '" . $product_order['buyer'] . "' ");
            if ($user['ref_id'] != 0) {
                $ck = $CMSNT->site('affiliate_ck');
                if (getRowRealtime('users', $user['ref_id'], 'ref_ck') != 0) {
                    $ck = getRowRealtime('users', $user['ref_id'], 'ref_ck');
                }
                $commission_fee = $amountRefund * $ck / 100;
                $removeCommission = $User->RemoveCommission($user['ref_id'], $commission_fee, __('[Admin] Thu hồi đơn hoàn tiền' . ' ' . $user['username']));
                if (!$removeCommission) {
                    throw new Exception(__('Không thể thu hồi hoa hồng'));
                }
            }

            // 2. Thu hồi tiền người bán TRƯỚC (nếu được cấu hình)
            if ($CMSNT->site('cong_tien_nguoi_ban') == 1) {
                if (getRowRealtime('users', $product_order['seller'], 'money') < $amountRefund) {
                    throw new Exception(__('Số dư khả dụng của Seller không đủ để hoàn lại cho người mua'));
                }
                $removeSeller = $User->RemoveCredits($product_order['seller'], $amountRefund, __('[Admin] Thu hồi hoàn tiền một phần đơn hàng') . ' #' . $product_order['trans_id'], 'TAKE_REFUND_partial_ORDER_' . $product_order['trans_id'] . '_' . time());
                if (!$removeSeller) {
                    throw new Exception(__('Không thể thu hồi tiền người bán'));
                }
            }

            // 3. Hoàn tiền cho người mua SAU
            $isRefund = $User->RefundCredits($product_order['buyer'], $amountRefund, "[Admin] $reason", 'REFUND_partial_ORDER_' . $product_order['trans_id'] . '_' . time());
            if (!$isRefund) {
                throw new Exception(__('Không thể hoàn tiền cho người mua'));
            }

            // 4. Cập nhật đơn hàng
            $updateOrder = $CMSNT->update('product_order', [
                'pay'    => $product_order['pay'] - $amountRefund,
                'cost'   => ($product_order['cost'] / $product_order['amount']) * $leftover,
                'money'  => ($product_order['money'] / $product_order['amount']) * $leftover,
                'amount' => $leftover
            ], " `id` = '$id' ");
            if (!$updateOrder) {
                throw new Exception(__('Không thể cập nhật đơn hàng'));
            }

            // 5. Ghi log
            $insertLog = $CMSNT->insert("logs", [
                'user_id'       => $getUser['id'],
                'ip'            => myip(),
                'device'        => getUserAgent(),
                'createdate'    => gettime(),
                'action'        => __('[Admin] Hoàn tiền một phần đơn hàng') . ' (' . $product_order['trans_id'] . ')'
            ]);
            if (!$insertLog) {
                throw new Exception(__('Không thể ghi log'));
            }
            /** NOTE ACTION */
            $my_text = $CMSNT->site('noti_refund_orders');
            $my_text = str_replace('{domain}', $_SERVER['SERVER_NAME'], $my_text);
            $my_text = str_replace('{username}', $getUser['username'], $my_text);
            $my_text = str_replace('{action}', __('Hoàn tiền một phần đơn hàng') . ' (' . $product_order['trans_id'] . ') Lý do: ' . $reason, $my_text);
            $my_text = str_replace('{ip}', myip(), $my_text);
            $my_text = str_replace('{time}', gettime(), $my_text);
            sendMessAdmin($my_text);

            $success = true;
        } else {
            // HOÀN TIỀN TOÀN BỘ
            $reason = 'Hoàn tiền đơn hàng #' . $product_order['trans_id'] . ' (' . $reason . ')';


            $commission_fee = 0;

            // 1. Thu hồi hoa hồng TRƯỚC (nếu có)
            $user = $CMSNT->get_row(" SELECT * FROM `users` WHERE `id` = '" . $product_order['buyer'] . "' ");
            if ($user['ref_id'] != 0) {
                $ck = $CMSNT->site('affiliate_ck');
                if (getRowRealtime('users', $user['ref_id'], 'ref_ck') != 0) {
                    $ck = getRowRealtime('users', $user['ref_id'], 'ref_ck');
                }
                $commission_fee = $product_order['pay'] * $ck / 100;
                $removeCommission = $User->RemoveCommission($user['ref_id'], $commission_fee, __('[Admin] Thu hồi đơn hoàn tiền' . ' ' . $user['username']));
                if (!$removeCommission) {
                    throw new Exception(__('Không thể thu hồi hoa hồng'));
                }
            }

            // 2. Thu hồi tiền người bán TRƯỚC (nếu được cấu hình)
            if ($CMSNT->site('cong_tien_nguoi_ban') == 1) {
                // Kiểm tra số dư người bán trước khi hoàn tiền
                if (getRowRealtime('users', $product_order['seller'], 'money') < $product_order['pay']) {
                    throw new Exception(__('Số dư khả dụng của Seller không đủ để hoàn lại cho người mua'));
                }
                $removeSeller = $User->RemoveCredits($product_order['seller'], $product_order['pay'], __('[Admin] Thu hồi hoàn tiền đơn hàng') . ' #' . $product_order['trans_id'], 'TAKE_REFUND_ORDER_' . $product_order['trans_id']);
                if (!$removeSeller) {
                    throw new Exception(__('Không thể thu hồi tiền người bán'));
                }
            }

            // 3. Hoàn tiền cho người mua SAU
            $isRefund = $User->RefundCredits($product_order['buyer'], $product_order['pay'], "[CTV] $reason", 'REFUND_ORDER_' . $product_order['trans_id']);
            if (!$isRefund) {
                throw new Exception(__('Không thể hoàn tiền cho người mua'));
            }

            // 4. Cập nhật đơn hàng
            $updateOrder = $CMSNT->update('product_order', [
                'refund'    => 1,
                'trash'     => 1,
                'pay'       => 0,
                'cost'      => 0,
                'amount'    => 0,
                'money'     => 0
            ], " `id` = '$id' ");
            if (!$updateOrder) {
                throw new Exception(__('Không thể cập nhật đơn hàng'));
            }

            // 5. Ghi log
            $insertLog = $CMSNT->insert("logs", [
                'user_id'       => $getUser['id'],
                'ip'            => myip(),
                'device'        => getUserAgent(),
                'createdate'    => gettime(),
                'action'        => __('[Admin] Hoàn tiền đơn hàng') . ' (' . $product_order['trans_id'] . ')'
            ]);
            if (!$insertLog) {
                throw new Exception(__('Không thể ghi log'));
            }
            /** NOTE ACTION */
            $my_text = $CMSNT->site('noti_refund_orders');
            $my_text = str_replace('{domain}', $_SERVER['SERVER_NAME'], $my_text);
            $my_text = str_replace('{username}', $getUser['username'], $my_text);
            $my_text = str_replace('{action}', __('Hoàn tiền đơn hàng') . ' (' . $product_order['trans_id'] . ') Lý do: ' . $reason, $my_text);
            $my_text = str_replace('{ip}', myip(), $my_text);
            $my_text = str_replace('{time}', gettime(), $my_text);
            sendMessAdmin($my_text);

            $success = true;
        }

        if ($success) {
            // Commit transaction nếu mọi thứ thành công
            $CMSNT->query("COMMIT");
            die(json_encode(['status' => 'success', 'msg' => __('Hoàn tiền đơn hàng thành công!')]));
        }
    } catch (Exception $e) {
        // Rollback transaction nếu có lỗi
        $CMSNT->query("ROLLBACK");
        die(json_encode(['status' => 'error', 'msg' => $e->getMessage()]));
    }
}

if ($_POST['action'] == 'update_stt_table_product') {
    if (checkPermission($getUser['admin'], 'edit_product') != true) {
        die(json_encode([
            'status'    => 'error',
            'msg'       => 'Bạn không có quyền sử dụng tính năng này'
        ]));
    }
    $isUpdate = $CMSNT->update("products", [
        'stt'       => !empty($_POST['stt']) ? check_string($_POST['stt']) : 0
    ], " `id` = '" . check_string($_POST['id']) . "' ");
    if ($isUpdate) {
        $CMSNT->insert("logs", [
            'user_id'       => $getUser['id'],
            'ip'            => myip(),
            'device'        => getUserAgent(),
            'createdate'    => gettime(),
            'action'        => 'Cập nhật ưu tiên sản phẩm (ID ' . check_string($_POST['id']) . ')'
        ]);
        die(json_encode(['status' => 'success', 'msg' => 'Cập nhật ưu tiên sản phẩm ID ' . check_string($_POST['id']) . ' thành công!']));
    }
    die(json_encode(['status' => 'error', 'msg' => __('Cập nhật thất bại')]));
}

if ($_POST['action'] == 'update_category_category') {
    if (checkPermission($getUser['admin'], 'edit_product') != true) {
        die(json_encode(['status' => 'error', 'msg' => 'Bạn không có quyền sử dụng tính năng này']));
    }
    $isUpdate = $CMSNT->update("categories", [
        'parent_id'    => !empty($_POST['category_id']) ? check_string($_POST['category_id']) : 0
    ], " `id` = '" . check_string($_POST['id']) . "' ");
    if ($isUpdate) {
        $CMSNT->insert("logs", [
            'user_id'       => $getUser['id'],
            'ip'            => myip(),
            'device'        => getUserAgent(),
            'createdate'    => gettime(),
            'action'        => 'Cập nhật chuyên mục cha cho chuyên mục (ID ' . check_string($_POST['id']) . ')'
        ]);
        die(json_encode(['status' => 'success', 'msg' => __('Cập nhật chuyên mục cha thành công!')]));
    }
    die(json_encode(['status' => 'error', 'msg' => __('Cập nhật thất bại')]));
}

if ($_POST['action'] == 'update_category_product') {
    if (checkPermission($getUser['admin'], 'edit_product') != true) {
        die(json_encode(['status' => 'error', 'msg' => 'Bạn không có quyền sử dụng tính năng này']));
    }
    $isUpdate = $CMSNT->update("products", [
        'category_id'    => !empty($_POST['category_id']) ? check_string($_POST['category_id']) : 0
    ], " `id` = '" . check_string($_POST['id']) . "' ");
    if ($isUpdate) {
        $CMSNT->insert("logs", [
            'user_id'       => $getUser['id'],
            'ip'            => myip(),
            'device'        => getUserAgent(),
            'createdate'    => gettime(),
            'action'        => 'Cập nhật chuyên mục cho sản phẩm (ID ' . check_string($_POST['id']) . ')'
        ]);
        die(json_encode(['status' => 'success', 'msg' => __('Cập nhật chuyên mục thành công!')]));
    }
    die(json_encode(['status' => 'error', 'msg' => __('Cập nhật thất bại')]));
}
if ($_POST['action'] == 'updateTableProductAPI') {
    if (checkPermission($getUser['admin'], 'manager_suppliers') != true) {
        die(json_encode(['status' => 'error', 'msg' => 'Bạn không có quyền sử dụng tính năng này']));
    }
    $isUpdate = $CMSNT->update("suppliers", [
        'status'    => !empty($_POST['status']) ? check_string($_POST['status']) : 0
    ], " `id` = '" . check_string($_POST['id']) . "' ");
    if ($isUpdate) {

        $CMSNT->insert("logs", [
            'user_id'       => $getUser['id'],
            'ip'            => myip(),
            'device'        => getUserAgent(),
            'createdate'    => gettime(),
            'action'        => 'Update Supplier (ID ' . check_string($_POST['id']) . ')'
        ]);
        die(json_encode(['status' => 'success', 'msg' => __('Cập nhật thành công!')]));
    }
    die(json_encode(['status' => 'error', 'msg' => __('Cập nhật thất bại')]));
}

if ($_POST['action'] == 'updateTableCategory') {
    if (checkPermission($getUser['admin'], 'edit_product') != true) {
        die(json_encode([
            'status'    => 'error',
            'msg'       => 'Bạn không có quyền sử dụng tính năng này'
        ]));
    }
    $isUpdate = $CMSNT->update("categories", [
        'status'    => !empty($_POST['status']) ? check_string($_POST['status']) : 0
    ], " `id` = '" . check_string($_POST['id']) . "' ");
    if ($isUpdate) {
        $CMSNT->insert("logs", [
            'user_id'       => $getUser['id'],
            'ip'            => myip(),
            'device'        => getUserAgent(),
            'createdate'    => gettime(),
            'action'        => 'Update Table Category (ID ' . check_string($_POST['id']) . ')'
        ]);
        die(json_encode(['status' => 'success', 'msg' => __('Cập nhật thành công!')]));
    }
    die(json_encode(['status' => 'error', 'msg' => __('Cập nhật thất bại')]));
}



if ($_POST['action'] == 'update_status_category') {
    if (checkPermission($getUser['admin'], 'edit_product') != true) {
        die(json_encode(['status' => 'error', 'msg' => 'Bạn không có quyền sử dụng tính năng này']));
    }
    $isUpdate = $CMSNT->update("categories", [
        'status'    => !empty($_POST['status']) ? check_string($_POST['status']) : 0
    ], " `id` = '" . check_string($_POST['id']) . "' ");
    if ($isUpdate) {

        $CMSNT->insert("logs", [
            'user_id'       => $getUser['id'],
            'ip'            => myip(),
            'device'        => getUserAgent(),
            'createdate'    => gettime(),
            'action'        => 'Update Status Category (ID ' . check_string($_POST['id']) . ')'
        ]);
        die(json_encode(['status' => 'success', 'msg' => __('Cập nhật thành công!')]));
    }
    die(json_encode(['status' => 'error', 'msg' => __('Cập nhật thất bại')]));
}


if ($_POST['action'] == 'update_status_product') {
    if (checkPermission($getUser['admin'], 'edit_product') != true) {
        die(json_encode(['status' => 'error', 'msg' => 'Bạn không có quyền sử dụng tính năng này']));
    }
    $isUpdate = $CMSNT->update("products", [
        'status'    => !empty($_POST['status']) ? check_string($_POST['status']) : 0
    ], " `id` = '" . check_string($_POST['id']) . "' ");
    if ($isUpdate) {
        $CMSNT->insert("logs", [
            'user_id'       => $getUser['id'],
            'ip'            => myip(),
            'device'        => getUserAgent(),
            'createdate'    => gettime(),
            'action'        => 'Update Status Product (ID ' . check_string($_POST['id']) . ')'
        ]);
        die(json_encode(['status' => 'success', 'msg' => __('Cập nhật thành công!')]));
    }
    die(json_encode(['status' => 'error', 'msg' => __('Cập nhật thất bại')]));
}



if ($_POST['action'] == 'cancel_email_campaigns') {
    if (checkPermission($getUser['admin'], 'edit_email_campaigns') != true) {
        die(json_encode(['status' => 'error', 'msg' => 'Bạn không có quyền sử dụng tính năng này']));
    }
    $isUpdate = $CMSNT->update("email_campaigns", [
        'status'  => 2
    ], " `id` = '" . check_string($_POST['id']) . "' ");
    if ($isUpdate) {
        die(json_encode(['status' => 'success', 'msg' => __('Cập nhật thành công!')]));
    }
    die(json_encode(['status' => 'error', 'msg' => __('Cập nhật thất bại')]));
}

if ($_POST['action'] == 'setDefaultLanguage') {
    if (checkPermission($getUser['admin'], 'edit_lang') != true) {
        die(json_encode(['status' => 'error', 'msg' => 'Bạn không có quyền sử dụng tính năng này']));
    }
    if (empty($_POST['id'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Data does not exist')]));
    }
    $id = check_string($_POST['id']);
    $row = $CMSNT->get_row("SELECT * FROM `languages` WHERE `id` = '$id' ");
    if (!$row) {
        $data = json_encode([
            'status'    => 'error',
            'msg'       => __('Data does not exist')
        ]);
        die($data);
    }
    $CMSNT->update("languages", [
        'lang_default' => 0
    ], " `id` > 0 ");
    $isUpdate = $CMSNT->update("languages", [
        'lang_default' => 1
    ], " `id` = '$id' ");
    if ($isUpdate) {
        $CMSNT->insert("logs", [
            'user_id'       => $getUser['id'],
            'ip'            => myip(),
            'device'        => getUserAgent(),
            'createdate'    => gettime(),
            'action'        => 'Set default language (' . $row['lang'] . ' ID ' . $row['id'] . ')'
        ]);
        $data = json_encode([
            'status'    => 'success',
            'msg'       => __('Language status change successful')
        ]);
        die($data);
    }
}

if ($_POST['action'] == 'changeTranslate') {
    if (checkPermission($getUser['admin'], 'edit_lang') != true) {
        die(json_encode(['status' => 'error', 'msg' => 'Bạn không có quyền sử dụng tính năng này']));
    }
    $isUpdate = $CMSNT->update("translate", [
        'value'  => check_string($_POST['value'])
    ], " `id` = '" . check_string($_POST['id']) . "' ");
    if ($isUpdate) {
        die(json_encode(['status' => 'success', 'msg' => __('Update successful!')]));
    }
    die(json_encode(['status' => 'error', 'msg' => __('Update failed!')]));
}

if ($_POST['action'] == 'setDefaultCurrency') {
    if (checkPermission($getUser['admin'], 'edit_currency') != true) {
        die(json_encode(['status' => 'error', 'msg' => 'Bạn không có quyền sử dụng tính năng này']));
    }
    $id = check_string($_POST['id']);
    $row = $CMSNT->get_row("SELECT * FROM `currencies` WHERE `id` = '$id' ");
    if (!$row) {
        $data = json_encode([
            'status'    => 'error',
            'msg'       => 'ID tiền tệ không tồn tại trong hệ thống'
        ]);
        die($data);
    }
    $CMSNT->update("currencies", [
        'default_currency' => 0
    ], " `id` > 0 ");
    $isUpdate = $CMSNT->update("currencies", [
        'default_currency' => 1
    ], " `id` = '$id' ");
    if ($isUpdate) {
        $CMSNT->insert("logs", [
            'user_id'       => $getUser['id'],
            'ip'            => myip(),
            'device'        => getUserAgent(),
            'createdate'    => gettime(),
            'action'        => 'Set mặc định tiền tệ (' . $row['name'] . ' ID ' . $row['id'] . ')'
        ]);
        $data = json_encode([
            'status'    => 'success',
            'msg'       => 'Thay đổi trạng thái tiền tệ thành công'
        ]);
        die($data);
    } else {
        die(json_encode(['status' => 'error', 'msg' => 'Cập nhật thất bại']));
    }
}

if ($_POST['action'] == 'logoutALL') {
    if (checkPermission($getUser['admin'], 'edit_user') != true) {
        die(json_encode(['status' => 'error', 'msg' => 'Bạn không có quyền sử dụng tính năng này']));
    }
    foreach ($CMSNT->get_list(" SELECT * FROM `users` WHERE `id` > 0 ") as $row) {
        $CMSNT->update('users', [
            'token'     => generateUltraSecureToken(32)
        ], " `id` = '" . $row['id'] . "' ");
    }
    $CMSNT->insert("logs", [
        'user_id'       => $getUser['id'],
        'ip'            => myip(),
        'device'        => getUserAgent(),
        'createdate'    => gettime(),
        'action'        => __('Log out all members on the system')
    ]);
    $data = json_encode([
        'status'    => 'success',
        'msg'       => __('Đăng xuất tất cả tài khoản thành công!')
    ]);
    die($data);
}

if ($_POST['action'] == 'changeAPIKey') {
    if (checkPermission($getUser['admin'], 'edit_user') != true) {
        die(json_encode(['status' => 'error', 'msg' => 'Bạn không có quyền sử dụng tính năng này']));
    }
    foreach ($CMSNT->get_list(" SELECT * FROM `users`  ") as $row) {
        $CMSNT->update('users', [
            'api_key'     => generateUltraSecureToken(16)
        ], " `id` = '" . $row['id'] . "' ");
    }
    $CMSNT->insert("logs", [
        'user_id'       => $getUser['id'],
        'ip'            => myip(),
        'device'        => getUserAgent(),
        'createdate'    => gettime(),
        'action'        => __('Change API Key for all members')
    ]);

    $data = json_encode([
        'status'    => 'success',
        'msg'       => __('Thay đổi API KEY thành công!')
    ]);
    die($data);
}


// Thêm hàm xử lý cập nhật thứ tự chuyên mục khi kéo thả
if ($_POST['action'] == 'updateCategorySTT') {
    if (isset($_POST['order']) && is_array($_POST['order'])) {
        $order = $_POST['order'];

        foreach ($order as $item) {
            $id = isset($item['id']) ? intval($item['id']) : 0;
            $position = isset($item['position']) ? intval($item['position']) : 0;

            if ($id > 0) {
                $CMSNT->update("categories", [
                    'stt' => $position
                ], " `id` = $id ");
            }
        }

        die(json_encode([
            'status' => 'success',
            'msg' => 'Cập nhật thứ tự thành công!'
        ]));
    }
}

if ($_POST['action'] == 'updateChildCategorySTT') {
    if (isset($_POST['order']) && is_array($_POST['order'])) {
        $order = $_POST['order'];

        foreach ($order as $item) {
            $id = isset($item['id']) ? intval($item['id']) : 0;
            $position = isset($item['position']) ? intval($item['position']) : 0;

            if ($id > 0) {
                // Cập nhật thứ tự cho danh mục con
                $check = $CMSNT->get_row("SELECT * FROM `categories` WHERE `id` = $id");
                if ($check) {
                    $CMSNT->update("categories", [
                        'stt' => $position
                    ], " `id` = $id ");
                }
            }
        }

        die(json_encode([
            'status' => 'success',
            'msg' => 'Cập nhật thứ tự chuyên mục con thành công!'
        ]));
    }
}

// Cập nhật trạng thái nhiều chuyên mục con cùng lúc
if ($_POST['action'] == 'bulk_update_category_status') {
    if (checkPermission($getUser['admin'], 'edit_product') != true) {
        die(json_encode(['status' => 'error', 'msg' => 'Bạn không có quyền sử dụng tính năng này']));
    }

    if (empty($_POST['category_ids']) || !is_array($_POST['category_ids'])) {
        die(json_encode(['status' => 'error', 'msg' => 'Vui lòng chọn ít nhất một chuyên mục']));
    }

    if (!isset($_POST['new_status']) || $_POST['new_status'] === '') {
        die(json_encode(['status' => 'error', 'msg' => 'Vui lòng chọn trạng thái mới']));
    }

    $category_ids = array_map('intval', $_POST['category_ids']);
    $new_status = intval($_POST['new_status']);
    $updated_count = 0;

    foreach ($category_ids as $id) {
        if ($id > 0) {
            // Kiểm tra chuyên mục có tồn tại không
            if ($CMSNT->get_row("SELECT * FROM `categories` WHERE `id` = $id")) {
                $isUpdate = $CMSNT->update("categories", [
                    'status' => $new_status
                ], " `id` = $id ");
                if ($isUpdate) {
                    $updated_count++;
                }
            }
        }
    }

    if ($updated_count > 0) {

        $CMSNT->insert("logs", [
            'user_id'       => $getUser['id'],
            'ip'            => myip(),
            'device'        => getUserAgent(),
            'createdate'    => gettime(),
            'action'        => "Cập nhật trạng thái hàng loạt cho $updated_count chuyên mục"
        ]);
        die(json_encode(['status' => 'success', 'msg' => "Đã cập nhật trạng thái cho $updated_count chuyên mục thành công!"]));
    }

    die(json_encode(['status' => 'error', 'msg' => 'Không có chuyên mục nào được cập nhật']));
}

// Cập nhật parent_id cho nhiều chuyên mục cùng lúc
if ($_POST['action'] == 'bulk_update_parent_category') {
    if (checkPermission($getUser['admin'], 'edit_product') != true) {
        die(json_encode(['status' => 'error', 'msg' => 'Bạn không có quyền sử dụng tính năng này']));
    }

    if (empty($_POST['category_ids']) || !is_array($_POST['category_ids'])) {
        die(json_encode(['status' => 'error', 'msg' => 'Vui lòng chọn ít nhất một chuyên mục']));
    }

    if (!isset($_POST['new_parent_id']) || $_POST['new_parent_id'] === '') {
        die(json_encode(['status' => 'error', 'msg' => 'Vui lòng chọn chuyên mục cha mới']));
    }

    $category_ids = array_map('intval', $_POST['category_ids']);
    $new_parent_id = intval(check_string($_POST['new_parent_id']));
    $updated_count = 0;
    $error_count = 0;
    $category_names = [];

    // Kiểm tra parent_id hợp lệ (nếu không phải 0)
    if ($new_parent_id > 0) {
        if (!$CMSNT->get_row("SELECT * FROM `categories` WHERE `id` = $new_parent_id AND `parent_id` = 0")) {
            die(json_encode(['status' => 'error', 'msg' => 'Chuyên mục cha được chọn không tồn tại hoặc không phải là chuyên mục gốc']));
        }
    }

    foreach ($category_ids as $id) {
        if ($id > 0) {
            // Kiểm tra chuyên mục có tồn tại không
            if (!$category = $CMSNT->get_row("SELECT * FROM `categories` WHERE `id` = $id")) {
                $error_count++;
                continue;
            }

            // Không cho phép đặt chuyên mục làm con của chính nó
            if ($id == $new_parent_id) {
                $error_count++;
                continue;
            }

            // Không cho phép đặt chuyên mục cha làm con của chuyên mục con của nó
            if ($new_parent_id > 0) {
                $checkChild = $CMSNT->get_row("SELECT * FROM `categories` WHERE `id` = $new_parent_id AND `parent_id` = $id");
                if ($checkChild) {
                    $error_count++;
                    continue;
                }
            }

            $isUpdate = $CMSNT->update("categories", [
                'parent_id' => $new_parent_id
            ], " `id` = $id ");

            if ($isUpdate) {
                $category_names[] = $category['name'];
                $updated_count++;
            } else {
                $error_count++;
            }
        }
    }

    if ($updated_count > 0) {
        $parent_name = $new_parent_id == 0 ? 'Chuyên mục gốc' : getRowRealtime('categories', $new_parent_id, 'name');


        $CMSNT->insert("logs", [
            'user_id'       => $getUser['id'],
            'ip'            => myip(),
            'device'        => getUserAgent(),
            'createdate'    => gettime(),
            'action'        => "Cập nhật parent_id hàng loạt cho $updated_count chuyên mục về '$parent_name'"
        ]);

        $success_msg = "Đã cập nhật parent cho $updated_count chuyên mục về '$parent_name' thành công!";
        if ($error_count > 0) {
            $success_msg .= " ($error_count chuyên mục không thể cập nhật)";
        }

        die(json_encode(['status' => 'success', 'msg' => $success_msg]));
    }

    die(json_encode(['status' => 'error', 'msg' => 'Không có chuyên mục nào được cập nhật']));
}

die(json_encode([
    'status'    => 'error',
    'msg'       => __('Invalid data')
]));
