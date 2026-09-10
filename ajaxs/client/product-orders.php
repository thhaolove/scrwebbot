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
 * Load đơn hàng (AJAX)
 */
if ($_POST['action'] == 'loadOrders') {
    // Kiểm tra CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Token không hợp lệ')]));
    }

    // Kiểm tra token từ POST
    $user_token = isset($_POST['token']) ? validate_alphanumeric($_POST['token'], 255) : false;
    if ($user_token === false) {
        die(json_encode([
            'status' => 'error',
            'msg' => __('Phiên đăng nhập không hợp lệ')
        ]));
    }

    $getUser = $CMSNT->get_row_safe("SELECT * FROM `users` WHERE `token` = ? AND `banned` = 0", [$user_token]);
    if (!$getUser) {
        die(json_encode([
            'status' => 'error',
            'msg' => __('Phiên đăng nhập không hợp lệ')
        ]));
    }

    $page = isset($_POST['page']) ? max(1, (int)$_POST['page']) : 1;
    $per_page = 10;
    $offset = ($page - 1) * $per_page;

    $status_filter = isset($_POST['status']) ? validate_string($_POST['status'], 30) : '';
    $search_query = isset($_POST['q']) ? trim(validate_string($_POST['q'], 100)) : '';

    $status_where = '';
    $params = [$getUser['id']];

    if (!empty($status_filter) && in_array($status_filter, ['pending', 'processing', 'completed', 'cancelled', 'cancelled_no_refund'])) {
        $status_where .= " AND po.`status` = ?";
        $params[] = $status_filter;
    }

    if (!empty($search_query)) {
        $status_where .= " AND (po.`trans_id` LIKE ? OR po.`product_name` LIKE ? OR po.`plan_name` LIKE ? OR p.`name` LIKE ?)";
        $params[] = '%' . $search_query . '%';
        $params[] = '%' . $search_query . '%';
        $params[] = '%' . $search_query . '%';
        $params[] = '%' . $search_query . '%';
    }

    // Đếm tổng
    $total_orders = $CMSNT->get_row_safe("
        SELECT COUNT(*) as total 
        FROM `product_orders` po 
        LEFT JOIN `products` p ON po.`product_id` = p.`id`
        WHERE po.`user_id` = ? {$status_where}
    ", $params)['total'];

    $total_pages = ceil($total_orders / $per_page);
    $has_more = $page < $total_pages;

    // Lấy orders
    $params_list = $params;
    $params_list[] = $per_page;
    $params_list[] = $offset;

    $orders = $CMSNT->get_list_safe("
        SELECT po.*, 
               COALESCE(NULLIF(po.`product_name`, ''), p.`name`, CONCAT('Sản phẩm #', po.`product_id`)) as display_product_name, 
               COALESCE(NULLIF(po.`plan_name`, ''), pp.`name`, CONCAT('Gói #', po.`plan_id`)) as display_plan_name,
               p.`image` as product_image,
               p.`slug` as product_slug,
               pp.`is_instant` as plan_is_instant
        FROM `product_orders` po 
        LEFT JOIN `products` p ON po.`product_id` = p.`id` 
        LEFT JOIN `product_plans` pp ON po.`plan_id` = pp.`id`
        WHERE po.`user_id` = ? {$status_where}
        ORDER BY po.`created_at` DESC
        LIMIT ? OFFSET ?
    ", $params_list);

    // Build HTML rows
    $html = '';
    $mobile_html = '';

    foreach ($orders as $order) {
        $has_sale = $order['sale_price'] > 0 && $order['sale_price'] < $order['total_price'];
        $has_discount = !empty($order['coupon_code']) && isset($order['discount_amount']) && $order['discount_amount'] > 0;
        $final_amount = isset($order['final_amount']) && $order['final_amount'] >= 0
            ? $order['final_amount']
            : ($has_sale ? $order['sale_price'] : $order['total_price']);

        // Status
        $statuses = [
            'pending' => ['label' => __('Chờ xử lý'), 'class' => 'status-pending', 'icon' => 'fa-clock'],
            'processing' => ['label' => __('Đang xử lý'), 'class' => 'status-processing', 'icon' => 'fa-spinner'],
            'completed' => ['label' => __('Hoàn thành'), 'class' => 'status-completed', 'icon' => 'fa-check-circle'],
            'cancelled' => ['label' => __('Đã hủy'), 'class' => 'status-cancelled', 'icon' => 'fa-times-circle'],
            'cancelled_no_refund' => ['label' => __('Đã hủy'), 'class' => 'status-cancelled', 'icon' => 'fa-times-circle']
        ];
        $s = $statuses[$order['status']] ?? ['label' => $order['status'], 'class' => 'status-pending', 'icon' => 'fa-question-circle'];

        // Desktop table row
        $html .= '<tr>';
        $html .= '<td class="td-action text-center" data-label="">';
        $html .= '<a href="' . base_url('product-order/' . $order['trans_id']) . '" class="link-detail">';
        $html .= __('Xem chi tiết');
        $html .= '</a></td>';
        $html .= '<td class="td-product" data-label="' . __('Sản phẩm') . '">';
        $html .= '<div class="td-product-content">';
        $html .= '<span class="product-name">' . htmlspecialchars(html_entity_decode($order['display_plan_name'] ?? $order['plan_name'] ?? '', ENT_QUOTES, 'UTF-8')) . '</span>';
        $html .= '<span class="product-qty">x' . ($order['quantity'] ?? 1) . '</span>';
        $html .= '</div>';
        $html .= '</td>';
        $html .= '<td class="td-price text-right" data-label="' . __('Giá') . '">';
        $html .= format_currency($final_amount);
        $html .= '</td>';
        $html .= '<td class="td-status text-center" data-label="' . __('Trạng thái') . '">';
        $html .= '<span class="order-status ' . $s['class'] . '"><i class="fa-solid ' . $s['icon'] . '"></i> ' . $s['label'] . '</span>';
        $html .= '</td>';
        $html .= '<td class="td-id" data-label="' . __('Mã đơn') . '">';
        $html .= htmlspecialchars($order['trans_id']);
        $html .= '</td>';
        $html .= '<td class="td-time" data-label="' . __('Thời gian') . '">';
        $html .= date('H:i:s d/m/Y', strtotime($order['created_at']));
        $html .= '</td>';
        $html .= '</tr>';

        // Mobile card
        $mobile_html .= '<div class="order-card-mobile">';
        // Row 1: Order ID + Plan Name + Status
        $mobile_html .= '<div class="order-card-row1">';
        $mobile_html .= '<div class="order-card-left">';
        $mobile_html .= '<span class="order-card-id">#' . htmlspecialchars($order['trans_id']) . '</span>';
        $mobile_html .= '<span class="order-card-plan">' . htmlspecialchars(html_entity_decode($order['display_plan_name'] ?? $order['plan_name'] ?? '', ENT_QUOTES, 'UTF-8')) . '</span>';
        $mobile_html .= '</div>';
        $mobile_html .= '<div class="order-card-status">';
        $mobile_html .= '<span class="order-status ' . $s['class'] . '"><i class="fa-solid ' . $s['icon'] . '"></i> ' . $s['label'] . '</span>';
        $mobile_html .= '</div>';
        $mobile_html .= '</div>';
        // Row 2: Date + Total
        $mobile_html .= '<div class="order-card-row2">';
        $mobile_html .= '<span class="order-card-date">' . date('H:i:s d/m/Y', strtotime($order['created_at'])) . '</span>';
        $mobile_html .= '<span class="order-card-total">' . __('Tổng tiền') . ': <strong>' . format_currency($final_amount) . '</strong></span>';
        $mobile_html .= '</div>';
        // Row 3: Product Images + Detail Link
        $mobile_html .= '<div class="order-card-row3">';
        $mobile_html .= '<div class="order-card-products">';
        $display_product_name = $order['display_product_name'] ?? $order['product_name'] ?? '';
        if (!empty($order['product_image'])) {
            $mobile_html .= '<div class="order-card-product-item">';
            $mobile_html .= '<img src="' . BASE_URL($order['product_image']) . '" alt="' . htmlspecialchars($display_product_name) . '">';
            if (($order['quantity'] ?? 1) > 1) {
                $mobile_html .= '<span class="product-qty-badge">x' . $order['quantity'] . '</span>';
            }
            $mobile_html .= '</div>';
        } else {
            $mobile_html .= '<div class="order-card-product-placeholder">';
            $mobile_html .= '<i class="fa-solid fa-box"></i>';
            $mobile_html .= '</div>';
        }
        $mobile_html .= '</div>';
        $mobile_html .= '<a href="' . base_url('product-order/' . $order['trans_id']) . '" class="order-card-detail-link">' . __('Xem chi tiết') . '</a>';
        $mobile_html .= '</div>';
        $mobile_html .= '</div>';
    }

    die(json_encode([
        'status' => 'success',
        'html' => $html,
        'mobile_html' => $mobile_html,
        'has_more' => $has_more,
        'total' => $total_orders
    ]));
}

/**
 * Check trạng thái đơn hàng (AJAX polling)
 * Dùng để tự động reload khi đơn hàng hoàn thành
 */
if ($_POST['action'] == 'checkOrderStatus') {
    // Kiểm tra token từ POST
    $user_token = isset($_POST['token']) ? validate_alphanumeric($_POST['token'], 255) : false;
    if ($user_token === false) {
        die(json_encode([
            'status' => 'error',
            'msg' => __('Phiên đăng nhập không hợp lệ')
        ]));
    }

    $getUser = $CMSNT->get_row_safe("SELECT * FROM `users` WHERE `token` = ? AND `banned` = 0", [$user_token]);
    if (!$getUser) {
        die(json_encode([
            'status' => 'error',
            'msg' => __('Phiên đăng nhập không hợp lệ')
        ]));
    }

    $trans_id = isset($_POST['trans_id']) ? validate_string($_POST['trans_id'], 50) : '';
    if (empty($trans_id)) {
        die(json_encode([
            'status' => 'error',
            'msg' => __('Mã đơn hàng không hợp lệ')
        ]));
    }

    // Lấy trạng thái đơn hàng của user
    $order = $CMSNT->get_row_safe("SELECT `status` FROM `product_orders` WHERE `trans_id` = ? AND `user_id` = ?", [$trans_id, $getUser['id']]);
    if (!$order) {
        die(json_encode([
            'status' => 'error',
            'msg' => __('Đơn hàng không tồn tại')
        ]));
    }

    die(json_encode([
        'status' => 'success',
        'order_status' => $order['status'],
        'should_reload' => ($order['status'] == 'completed' || $order['status'] == 'cancelled' || $order['status'] == 'cancelled_no_refund')
    ]));
}

die(json_encode([
    'status' => 'error',
    'msg' => __('Invalid action')
]));
