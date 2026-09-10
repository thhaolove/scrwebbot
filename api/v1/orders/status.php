<?php
/**
 * API Endpoint: Kiểm tra trạng thái đơn hàng
 * GET /api/v1/orders/status?order_id=123
 * GET /api/v1/orders/status?trans_id=ABC123
 * 
 * Headers:
 *   X-API-Key: your_api_key
 *   X-API-Secret: your_api_secret
 * 
 * @package SHOPKEY API
 * @version 1.0.0
 */

define("IN_SITE", true);

$start_time = microtime(true);

require_once(__DIR__ . '/../../../libs/db.php');
require_once(__DIR__ . '/../../../libs/lang.php');
require_once(__DIR__ . '/../../../libs/helper.php');
require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../../../libs/services/ApiKeyService.php');
require_once(__DIR__ . '/../../../libs/services/OrderService.php');

// Chấp nhận GET và POST
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
    ApiKeyService::errorResponse('METHOD_NOT_ALLOWED', __('Chỉ chấp nhận phương thức GET hoặc POST'), 405);
}

// Lấy headers
$api_key = $_SERVER['HTTP_X_API_KEY'] ?? '';
$api_secret = $_SERVER['HTTP_X_API_SECRET'] ?? '';

// Xác thực API Key
$ApiService = new ApiKeyService();

if (!$ApiService->authenticate($api_key, $api_secret)) {
    $error = $ApiService->getErrors()[0] ?? ['code' => 'AUTH_FAILED', 'message' => __('Xác thực thất bại')];
    ApiKeyService::errorResponse($error['code'], $error['message'], 401);
}

// Kiểm tra quyền
if (!$ApiService->hasPermission('orders.status') && !$ApiService->hasPermission('all')) {
    $ApiService->logRequest($api_key, 'orders/status', 'failed', 'Permission denied');
    ApiKeyService::errorResponse('PERMISSION_DENIED', __('Bạn không có quyền xem đơn hàng'), 403);
}

// Lấy parameters
$order_id = isset($_REQUEST['order_id']) ? validate_int($_REQUEST['order_id'], 1) : null;
$trans_id = isset($_REQUEST['trans_id']) ? validate_string($_REQUEST['trans_id'], 50) : null;

if (!$order_id && !$trans_id) {
    $ApiService->logRequest($api_key, 'orders/status', 'failed', 'order_id or trans_id required');
    ApiKeyService::errorResponse('PARAMS_REQUIRED', __('Vui lòng cung cấp order_id hoặc trans_id'), 400);
}

global $CMSNT;

// Tìm đơn hàng
$where = "";
$params = [];

if ($order_id) {
    $where = "`id` = ?";
    $params[] = $order_id;
} else {
    $where = "`trans_id` = ?";
    $params[] = $trans_id;
}

// Chỉ xem đơn hàng của chính mình (trừ khi có quyền all)
$user_id = $ApiService->getUserId();
if (!$ApiService->hasPermission('all')) {
    $where .= " AND `user_id` = ?";
    $params[] = $user_id;
}

$order = $CMSNT->get_row_safe("SELECT * FROM `product_orders` WHERE {$where}", $params);

if (!$order) {
    $ApiService->logRequest($api_key, 'orders/status', 'failed', 'Order not found');
    ApiKeyService::errorResponse('ORDER_NOT_FOUND', __('Đơn hàng không tồn tại'), 404);
}

// Lấy thông tin sản phẩm và gói
$product = $CMSNT->get_row_safe("SELECT `id`, `name`, `slug`, `image` FROM `products` WHERE `id` = ?", [$order['product_id']]);
$plan = $CMSNT->get_row_safe("SELECT `id`, `name`, `is_instant` FROM `product_plans` WHERE `id` = ?", [$order['plan_id']]);

// Lấy delivery items
$delivery_items = [];
if ($order['status'] === 'completed') {
    // Ưu tiên lấy từ delivery_content
    if (!empty($order['delivery_content'])) {
        // Split theo \r\n hoặc \n, sau đó trim từng dòng
        $lines = preg_split('/\r\n|\r|\n/', $order['delivery_content']);
        $delivery_items = array_values(array_filter(
            array_map('trim', $lines),
            function($line) { return $line !== ''; }
        ));
    }
    
    // Nếu delivery_content trống, lấy từ product_stock
    if (empty($delivery_items)) {
        $stocks = $CMSNT->get_list_safe(
            "SELECT `stock_value` FROM `product_stock` WHERE `order_id` = ?",
            [$order['id']]
        );
        $delivery_items = array_column($stocks, 'stock_value');
    }
}

// Tính thời gian xử lý
$execution_time = round(microtime(true) - $start_time, 4);

// Log
$ApiService->logRequest($api_key, 'orders/status', 'success', null, ['order_id' => $order['id']]);

// Response
$response_data = [
    'order' => [
        'id' => (int)$order['id'],
        'trans_id' => $order['trans_id'],
        'user_id' => (int)$order['user_id'],
        'product' => $product ? [
            'id' => (int)$product['id'],
            'name' => html_entity_decode($product['name'], ENT_QUOTES, 'UTF-8'),
            'slug' => $product['slug'],
            'image' => $product['image'] ? base_url($product['image']) : null
        ] : null,
        'plan' => $plan ? [
            'id' => (int)$plan['id'],
            'name' => html_entity_decode($plan['name'], ENT_QUOTES, 'UTF-8'),
            'is_instant' => (bool)$plan['is_instant']
        ] : null,
        'quantity' => (int)$order['quantity'],
        'total_price' => (float)$order['total_price'],
        'sale_price' => (float)$order['sale_price'],
        'discount_amount' => (float)$order['discount_amount'],
        'coupon_code' => $order['coupon_code'] ?: null,
        'final_amount' => (float)$order['final_amount'],
        'fields_data' => json_decode($order['fields_data'], true) ?: [],
        'status' => $order['status'],
        'payment_status' => $order['payment_status'],
        'order_source' => $order['order_source'] ?? 'web',
        'created_at' => $order['created_at'],
        'updated_at' => $order['updated_at']
    ],
    'delivery' => [
        'items' => $delivery_items,
        'delivered_count' => count($delivery_items),
        'expected_count' => (int)$order['quantity']
    ],
    'execution_time' => $execution_time
];

ApiKeyService::jsonResponse(true, $response_data);

