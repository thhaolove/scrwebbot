<?php

define("IN_SITE", true);
require_once(__DIR__ . "/../../libs/db.php");
require_once(__DIR__ . "/../../libs/lang.php");
require_once(__DIR__ . "/../../libs/helper.php");
require_once(__DIR__ . "/../../config.php");

if (!isset($_POST['action'])) {
    die(json_encode([
        'status' => 'error',
        'msg' => __('The Request Not Found')
    ]));
}

/**
 * Lấy giá realtime cho giỏ hàng (bao gồm Flash Sale)
 */
if ($_POST['action'] == 'getCartPrices') {
    // Lấy danh sách items
    $cart_items = isset($_POST['items']) ? $_POST['items'] : '';
    if (empty($cart_items)) {
        die(json_encode(['status' => 'error', 'msg' => __('Giỏ hàng trống')]));
    }

    $items = json_decode($cart_items, true);
    if (!is_array($items) || count($items) == 0) {
        die(json_encode(['status' => 'error', 'msg' => __('Dữ liệu không hợp lệ')]));
    }

    // Giới hạn số lượng items
    if (count($items) > 50) {
        die(json_encode(['status' => 'error', 'msg' => __('Quá nhiều sản phẩm')]));
    }

    // Load FlashSaleHandler
    require_once(__DIR__ . '/../../libs/database/flashsale.php');
    $FlashSaleHandler = new FlashSaleHandler();

    // Lấy thông tin user (nếu đã đăng nhập) để áp dụng discount
    $user_discount = 0;
    $token = isset($_POST['token']) ? validate_string($_POST['token'], 255) : '';
    if (!empty($token)) {
        $getUser = $CMSNT->get_row_safe("SELECT `discount` FROM `users` WHERE `token` = ? AND `banned` = 0", [$token]);
        if ($getUser && isset($getUser['discount']) && $getUser['discount'] > 0) {
            $user_discount = (float)$getUser['discount'];
        }
    }

    // Collect all plan_ids for batch Flash Sale lookup
    $all_plan_ids = [];
    $all_product_ids = [];
    foreach ($items as $item) {
        if (isset($item['plan_id'])) $all_plan_ids[] = (int)$item['plan_id'];
        if (isset($item['product_id'])) $all_product_ids[] = (int)$item['product_id'];
    }
    $all_plan_ids = array_unique($all_plan_ids);
    $all_product_ids = array_unique($all_product_ids);

    // Batch fetch Flash Sales
    $flash_sales = [];
    if (!empty($all_plan_ids)) {
        $flash_sales = $FlashSaleHandler->getActiveFlashSalesForPlans($all_plan_ids, $all_product_ids);
    }

    $result = [];

    foreach ($items as $item) {
        $product_id = isset($item['product_id']) ? validate_int($item['product_id'], 1) : 0;
        $plan_id = isset($item['plan_id']) ? validate_int($item['plan_id'], 1) : 0;

        if (!$product_id || !$plan_id) {
            continue;
        }

        // Lấy thông tin sản phẩm và gói
        $plan_info = $CMSNT->get_row_safe("
            SELECT pp.*, 
                   p.`name` as product_name, 
                   p.`image` as product_image, 
                   p.`slug` as product_slug,
                   p.`status` as product_status
            FROM `product_plans` pp
            LEFT JOIN `products` p ON pp.`product_id` = p.`id`
            WHERE pp.`id` = ? AND pp.`product_id` = ?
        ", [$plan_id, $product_id]);

        if (!$plan_info) {
            // Sản phẩm/gói không tồn tại
            $result[] = [
                'product_id' => $product_id,
                'plan_id' => $plan_id,
                'available' => false,
                'reason' => __('Sản phẩm không tồn tại')
            ];
            continue;
        }

        // Kiểm tra trạng thái
        if ($plan_info['status'] != 1 || $plan_info['product_status'] != 1) {
            $result[] = [
                'product_id' => $product_id,
                'plan_id' => $plan_id,
                'available' => false,
                'reason' => __('Sản phẩm ngừng bán')
            ];
            continue;
        }

        // Tính giá cơ bản (giá gốc trước mọi giảm giá)
        $original_price = (float)$plan_info['price'];
        $price = $original_price;
        $sale_price = (float)$plan_info['sale_price'];

        // Kiểm tra Flash Sale
        $flash_sale = isset($flash_sales[$plan_id]) ? $flash_sales[$plan_id] : null;
        $has_flash_sale = !empty($flash_sale);
        $flash_price = 0;
        $flash_sale_info = null;

        if ($has_flash_sale) {
            $flash_price = $FlashSaleHandler->calculateFlashSalePrice($plan_info, $flash_sale);
            $flash_sale_info = [
                'id' => (int)$flash_sale['id'],
                'name' => $flash_sale['name'],
                'end_time' => $flash_sale['end_time'],
                'end_timestamp' => strtotime($flash_sale['end_time'])
            ];
        }

        // Áp dụng user discount trước (giảm theo %)
        $user_discount_amount = 0;
        if ($user_discount > 0) {
            $user_discount_amount = $price * $user_discount / 100;
            $price = $price - $user_discount_amount;
            // Cập nhật sale_price theo tỷ lệ tương ứng nếu có
            if ($sale_price > 0) {
                $sale_price = $sale_price * (1 - $user_discount / 100);
            }
        }

        // Tính giá cuối cùng: Flash Sale > Sale Price > Price (đã áp dụng user discount)
        if ($has_flash_sale && $flash_price > 0 && $flash_price < $price) {
            // Flash Sale: tính lại dựa trên giá đã giảm user discount
            $flash_price = $FlashSaleHandler->calculateFlashSalePrice(['price' => $price, 'sale_price' => $sale_price], $flash_sale);
            $final_price = $flash_price;
        } elseif ($sale_price > 0 && $sale_price < $price) {
            $final_price = $sale_price;
        } else {
            $final_price = $price;
        }

        // Kiểm tra kho
        $stock_count = 0;
        $is_instant = isset($plan_info['is_instant']) && $plan_info['is_instant'] == 1;
        $supplier_id = isset($plan_info['supplier_id']) ? (int)$plan_info['supplier_id'] : 0;

        if ($supplier_id > 0) {
            // Sản phẩm API: lấy stock từ api_stock và đánh dấu là instant để JS check stock
            $stock_count = isset($plan_info['api_stock']) ? (int)$plan_info['api_stock'] : 0;
            $is_instant = true; // Đánh dấu để JS check stock
        } else if ($is_instant) {
            // Sản phẩm thường giao ngay: đếm từ product_stock
            $stock_count = $CMSNT->num_rows_safe(
                "SELECT id FROM `product_stock` WHERE `plan_id` = ? AND `status` = 1",
                [$plan_id]
            );
        } else {
            // Sản phẩm không giao ngay: không giới hạn số lượng
            $stock_count = 999999;
        }

        $result[] = [
            'product_id' => $product_id,
            'plan_id' => $plan_id,
            'available' => true,
            'product_name' => html_entity_decode($plan_info['product_name'], ENT_QUOTES, 'UTF-8'),
            'plan_name' => html_entity_decode($plan_info['name'], ENT_QUOTES, 'UTF-8'),
            'product_image' => $plan_info['product_image'],
            'product_slug' => $plan_info['product_slug'],
            'original_price' => $original_price, // Giá gốc
            'price' => $price, // Giá sau user discount
            'sale_price' => $sale_price,
            'flash_price' => $flash_price,
            'has_flash_sale' => $has_flash_sale,
            'flash_sale' => $flash_sale_info,
            'final_price' => $final_price,
            'user_discount_percent' => $user_discount, // % giảm giá theo user
            'user_discount_amount' => $user_discount_amount, // Số tiền giảm theo user
            'is_instant' => $is_instant ? 1 : 0,
            'stock_count' => $stock_count
        ];
    }

    die(json_encode([
        'status' => 'success',
        'items' => $result,
        'user_discount_percent' => $user_discount, // Gửi thêm % discount của user
        'timestamp' => time()
    ]));
}

die(json_encode([
    'status' => 'error',
    'msg' => __('Invalid action')
]));
