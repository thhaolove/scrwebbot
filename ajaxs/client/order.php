<?php

/**
 * Client Order Handler
 * Sử dụng OrderService để xử lý đơn hàng
 * 
 * @package SHOPKEY
 * @author CMSNT
 */

define("IN_SITE", true);
require_once(__DIR__ . "/../../libs/db.php");
require_once(__DIR__ . "/../../libs/lang.php");
require_once(__DIR__ . "/../../libs/helper.php");
require_once(__DIR__ . "/../../config.php");
require_once(__DIR__ . '/../../libs/services/OrderService.php');

if ($CMSNT->site('status') != 1) {
    die(json_encode([
        'status' => 'error',
        'msg' => __('Hệ thống đang bảo trì!')
    ]));
}

if (!isset($_POST['action'])) {
    die(json_encode([
        'status' => 'error',
        'msg' => __('The Request Not Found')
    ]));
}

// Kiểm tra CSRF token cho tất cả request
checkCSRFAjax();


/**
 * Thanh toán giỏ hàng bằng số dư
 * Sử dụng OrderService để xử lý chung với API
 */
if ($_POST['action'] == 'checkoutCart') {
    if ($CMSNT->site('status_demo') != 0) {
        die(json_encode(['status' => 'error', 'msg' => __('Chức năng này không thể sử dụng trên website demo')]));
    }
    // Khởi tạo OrderService
    $OrderService = new OrderService();

    // Xác thực user qua token
    $token = isset($_POST['token']) ? $_POST['token'] : '';
    if (!$OrderService->authenticateByToken($token)) {
        checkBlockIP('SCAN_TOKEN', 1);
        die(json_encode([
            'status' => 'error',
            'msg' => $OrderService->getFirstError()
        ]));
    }

    // Lấy danh sách sản phẩm từ giỏ hàng
    $cart_items = isset($_POST['cart_items']) ? $_POST['cart_items'] : '';
    if (empty($cart_items)) {
        die(json_encode(['status' => 'error', 'msg' => __('Giỏ hàng trống')]));
    }

    $cart_data = json_decode($cart_items, true);
    if (!is_array($cart_data) || count($cart_data) == 0) {
        die(json_encode(['status' => 'error', 'msg' => __('Giỏ hàng không hợp lệ')]));
    }

    // Validate giỏ hàng
    if (!$OrderService->validateCart($cart_data)) {
        die(json_encode([
            'status' => 'error',
            'msg' => $OrderService->getFirstError()
        ]));
    }

    // Áp dụng mã giảm giá nếu có
    $coupon_code = isset($_POST['coupon_code']) ? trim(strtoupper($_POST['coupon_code'])) : '';
    if (!$OrderService->applyCoupon($coupon_code)) {
        die(json_encode([
            'status' => 'error',
            'msg' => $OrderService->getFirstError()
        ]));
    }

    // Kiểm tra số dư
    if (!$OrderService->checkBalance()) {
        $user = $OrderService->getUser();
        die(json_encode([
            'status' => 'error',
            'msg' => $OrderService->getFirstError(),
            'balance' => $user['money'],
            'total' => $OrderService->getFinalPayment()
        ]));
    }

    // Thực hiện thanh toán
    $result = $OrderService->processCheckout('web');

    if ($result === false) {
        die(json_encode([
            'status' => 'error',
            'msg' => $OrderService->getFirstError()
        ]));
    }

    // Link: nếu chỉ có 1 đơn hàng thì đến trang chi tiết, nhiều hơn 1 thì đến danh sách
    $order_count = count($result['orders']);
    if ($order_count == 1 && !empty($result['orders'][0]['trans_id'])) {
        $order_detail_url = base_url('product-order/' . $result['orders'][0]['trans_id']);
    } else {
        $order_detail_url = base_url('product-orders');
    }

    die(json_encode([
        'status' => 'success',
        'msg' => sprintf(__('Thanh toán thành công! %d đơn hàng đã được tạo.'), count($result['orders'])),
        'orders' => $result['orders'],
        'original_total' => $result['original_total'],
        'discount_amount' => $result['discount_amount'],
        'coupon_code' => $result['coupon_code'],
        'total_amount' => $result['total_amount'],
        'new_balance' => $result['new_balance'],
        'order_detail_url' => $order_detail_url,
        'redirect' => base_url('product-orders')
    ]));
}








/**
 * Validate mã giảm giá cho giỏ hàng
 */
if ($_POST['action'] == 'validateCartCoupon') {
    require_once(__DIR__ . '/../../libs/database/coupon.php');

    // Xác thực user
    $token = isset($_POST['token']) ? validate_string($_POST['token'], 255) : false;
    if (!$token) {
        die(json_encode([
            'status' => 'error',
            'msg' => __('Vui lòng đăng nhập để sử dụng mã giảm giá')
        ]));
    }

    $getUser = $CMSNT->get_row_safe("SELECT * FROM `users` WHERE `token` = ? AND `banned` = 0", [$token]);
    if (!$getUser) {
        checkBlockIP('SCAN_TOKEN', 1);
        die(json_encode([
            'status' => 'error',
            'msg' => __('Phiên đăng nhập không hợp lệ')
        ]));
    }

    // Lấy mã giảm giá
    $coupon_code = isset($_POST['coupon_code']) ? trim(strtoupper($_POST['coupon_code'])) : '';
    if (empty($coupon_code)) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng nhập mã giảm giá')]));
    }

    // Lấy thông tin giỏ hàng
    $cart_items_json = isset($_POST['cart_items']) ? $_POST['cart_items'] : '';
    if (empty($cart_items_json)) {
        die(json_encode(['status' => 'error', 'msg' => __('Giỏ hàng không hợp lệ')]));
    }

    $cart_items = json_decode($cart_items_json, true);
    if (!is_array($cart_items) || count($cart_items) == 0) {
        die(json_encode(['status' => 'error', 'msg' => __('Giỏ hàng trống')]));
    }

    // Lấy thông tin coupon
    $CouponHandler = new CouponHandler();
    $coupon = $CouponHandler->getCouponByCode($coupon_code);

    if (!$coupon) {
        die(json_encode(['status' => 'error', 'msg' => __('Mã giảm giá không tồn tại hoặc đã bị vô hiệu hóa')]));
    }

    // Kiểm tra thời gian hiệu lực
    $current_time = time();
    if (!empty($coupon['start_date']) && strtotime($coupon['start_date']) > $current_time) {
        die(json_encode(['status' => 'error', 'msg' => __('Mã giảm giá chưa đến thời gian sử dụng')]));
    }
    if (!empty($coupon['end_date']) && strtotime($coupon['end_date']) < $current_time) {
        die(json_encode(['status' => 'error', 'msg' => __('Mã giảm giá đã hết hạn')]));
    }

    // Kiểm tra giới hạn sử dụng
    if ($coupon['usage_limit'] > 0 && $coupon['used_count'] >= $coupon['usage_limit']) {
        die(json_encode(['status' => 'error', 'msg' => __('Mã giảm giá đã hết lượt sử dụng')]));
    }

    // Kiểm tra giới hạn mỗi user
    if ($coupon['user_limit'] > 0) {
        $user_usage = $CMSNT->get_row_safe(
            "SELECT COUNT(*) as count FROM `coupon_usages` WHERE `coupon_id` = ? AND `user_id` = ?",
            [$coupon['id'], $getUser['id']]
        );
        if ($user_usage && $user_usage['count'] >= $coupon['user_limit']) {
            die(json_encode(['status' => 'error', 'msg' => __('Bạn đã sử dụng hết lượt cho mã giảm giá này')]));
        }
    }

    // Lấy danh sách product_ids và plan_ids của coupon
    $coupon_product_ids = !empty($coupon['product_ids']) ? json_decode($coupon['product_ids'], true) : null;
    $coupon_plan_ids = !empty($coupon['plan_ids']) ? json_decode($coupon['plan_ids'], true) : null;

    // Tính tổng tiền và tổng tiền đủ điều kiện (lấy giá từ database, không tin client)
    $cart_total = 0;
    $eligible_total = 0;
    $eligible_items = [];

    foreach ($cart_items as $index => $item) {
        $plan_id = isset($item['plan_id']) ? intval($item['plan_id']) : 0;
        $quantity = isset($item['quantity']) ? intval($item['quantity']) : 1;

        if ($plan_id <= 0 || $quantity <= 0) continue;

        // Lấy thông tin plan từ database để có giá chính xác
        $plan = $CMSNT->get_row_safe(
            "SELECT pp.*, p.id as product_id FROM `product_plans` pp 
             LEFT JOIN `products` p ON pp.product_id = p.id 
             WHERE pp.id = ? AND pp.status = 1 AND p.status = 1",
            [$plan_id]
        );

        if (!$plan) continue;

        $product_id = $plan['product_id'];

        // Tính giá từ database (không tin client)
        $unit_price = ($plan['sale_price'] > 0 && $plan['sale_price'] < $plan['price'])
            ? $plan['sale_price']
            : $plan['price'];
        $item_total = $unit_price * $quantity;
        $cart_total += $item_total;

        // Kiểm tra item này có đủ điều kiện không
        $is_eligible = true;

        if ($coupon_product_ids !== null && !in_array($product_id, $coupon_product_ids)) {
            $is_eligible = false;
        }

        if ($coupon_plan_ids !== null && !in_array($plan_id, $coupon_plan_ids)) {
            $is_eligible = false;
        }

        if ($is_eligible) {
            $eligible_total += $item_total;
            $eligible_items[] = [
                'index' => $index,
                'product_id' => $product_id,
                'plan_id' => $plan_id,
                'item_total' => $item_total
            ];
        }
    }

    // Kiểm tra có sản phẩm đủ điều kiện không
    if ($eligible_total <= 0) {
        die(json_encode([
            'status' => 'error',
            'msg' => __('Không có sản phẩm nào trong giỏ hàng đủ điều kiện áp dụng mã giảm giá này')
        ]));
    }

    // Kiểm tra giá trị đơn hàng tối thiểu
    if ($coupon['min_order_amount'] > 0 && $eligible_total < $coupon['min_order_amount']) {
        die(json_encode([
            'status' => 'error',
            'msg' => sprintf(__('Đơn hàng cần đạt tối thiểu %s để áp dụng mã giảm giá này'), format_currency($coupon['min_order_amount']))
        ]));
    }

    // Tính discount
    $discount_amount = $CouponHandler->calculateDiscount($coupon, $eligible_total);
    $final_amount = max(0, $cart_total - $discount_amount);

    // Tính discount cho từng item đủ điều kiện
    $item_discounts = [];
    foreach ($eligible_items as $ei) {
        $item_discount = 0;
        if ($eligible_total > 0) {
            $item_discount = round(($ei['item_total'] / $eligible_total) * $discount_amount);
        }
        $item_discounts[] = [
            'index' => $ei['index'],
            'product_id' => $ei['product_id'],
            'plan_id' => $ei['plan_id'],
            'discount' => $item_discount
        ];
    }

    die(json_encode([
        'status' => 'success',
        'msg' => sprintf(__('Áp dụng mã giảm giá thành công! Giảm %s'), format_currency($discount_amount)),
        'coupon' => [
            'code' => $coupon['code'],
            'type' => $coupon['type'],
            'value' => $coupon['value']
        ],
        'discount_amount' => $discount_amount,
        'original_total' => $cart_total,
        'eligible_total' => $eligible_total,
        'final_amount' => $final_amount,
        'item_discounts' => $item_discounts
    ]));
}

die(json_encode([
    'status' => 'error',
    'msg' => __('Request does not exist')
]));
