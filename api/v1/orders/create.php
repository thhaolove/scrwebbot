<?php
/**
 * API Endpoint: Tạo đơn hàng
 * POST /api/v1/orders/create
 * 
 * Headers:
 *   X-API-Key: your_api_key
 *   X-API-Secret: your_api_secret
 *   Content-Type: application/json
 * 
 * Body (JSON):
 * {
 *   "items": [
 *     {
 *       "plan_id": 18,           // (Required) ID gói sản phẩm
 *       "quantity": 2,           // (Optional) Số lượng, mặc định = 1
 *       "fields": {              // (Optional) Các field bổ sung theo yêu cầu của plan
 *         "email_dang_nhap": "customer@example.com",
 *         "mat_khau": "password123"
 *       }
 *     }
 *   ],
 *   "coupon_code": "SALE10"      // (Optional) Mã giảm giá
 * }
 * 
 * Note: Không cần truyền product_id, hệ thống tự động lấy từ plan_id
 * 
 * @package SHOPKEY API
 * @version 1.0.1
 */

define("IN_SITE", true);

$start_time = microtime(true);

require_once(__DIR__ . '/../../../libs/db.php');
require_once(__DIR__ . '/../../../libs/lang.php');
require_once(__DIR__ . '/../../../libs/helper.php');
require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../../../libs/services/ApiKeyService.php');
require_once(__DIR__ . '/../../../libs/services/OrderService.php');

// Chỉ chấp nhận POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiKeyService::errorResponse('METHOD_NOT_ALLOWED', __('Chỉ chấp nhận phương thức POST'), 405);
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
if (!$ApiService->hasPermission('orders.create') && !$ApiService->hasPermission('all')) {
    $ApiService->logRequest($api_key, 'orders/create', 'failed', 'Permission denied');
    ApiKeyService::errorResponse('PERMISSION_DENIED', __('Bạn không có quyền tạo đơn hàng'), 403);
}

// Parse JSON body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    $ApiService->logRequest($api_key, 'orders/create', 'failed', 'Invalid JSON');
    ApiKeyService::errorResponse('INVALID_JSON', __('Dữ liệu JSON không hợp lệ'), 400);
}

// Validate items
if (empty($data['items']) || !is_array($data['items'])) {
    $ApiService->logRequest($api_key, 'orders/create', 'failed', 'Items required');
    ApiKeyService::errorResponse('ITEMS_REQUIRED', __('Vui lòng cung cấp danh sách sản phẩm'), 400);
}

// Xác định user đặt hàng
$OrderService = new OrderService();
$user_id = isset($data['user_id']) ? (int)$data['user_id'] : $ApiService->getUserId();

// Nếu đặt cho user khác, cần kiểm tra quyền đặc biệt
if (isset($data['user_id']) && $data['user_id'] != $ApiService->getUserId()) {
    // Chỉ admin hoặc có quyền 'all' mới được đặt cho user khác
    if (!$ApiService->hasPermission('all') && !$ApiService->hasPermission('orders.create_for_others')) {
        $ApiService->logRequest($api_key, 'orders/create', 'failed', 'Cannot create order for other user');
        ApiKeyService::errorResponse('PERMISSION_DENIED', __('Bạn không có quyền đặt hàng cho người dùng khác'), 403);
    }
}

// Xác thực user
if (!$OrderService->authenticateByUserId($user_id)) {
    $ApiService->logRequest($api_key, 'orders/create', 'failed', $OrderService->getFirstError());
    ApiKeyService::errorResponse('USER_ERROR', $OrderService->getFirstError(), 400);
}

// Validate giỏ hàng
if (!$OrderService->validateCart($data['items'])) {
    $ApiService->logRequest($api_key, 'orders/create', 'failed', $OrderService->getFirstError());
    ApiKeyService::errorResponse('CART_ERROR', $OrderService->getFirstError(), 400);
}

// Áp dụng coupon nếu có (soft validation - nếu lỗi thì bỏ qua coupon, không fail đơn hàng)
$coupon_code = $data['coupon_code'] ?? '';
$coupon_warning = null;
if (!empty($coupon_code)) {
    if (!$OrderService->applyCoupon($coupon_code)) {
        // Ghi log warning nhưng không fail
        $coupon_warning = $OrderService->getFirstError();
        $OrderService->clearErrors(); // Xóa lỗi để tiếp tục
        $coupon_code = ''; // Reset coupon code
    }
}

// Kiểm tra số dư
if (!$OrderService->checkBalance()) {
    $ApiService->logRequest($api_key, 'orders/create', 'failed', 'Insufficient balance');
    ApiKeyService::errorResponse(
        ApiKeyService::ERROR_INSUFFICIENT_BALANCE, 
        $OrderService->getFirstError(), 
        400,
    );
}

// Thực hiện thanh toán
$result = $OrderService->processCheckout('api', $api_key);

if ($result === false) {
    $ApiService->logRequest($api_key, 'orders/create', 'failed', $OrderService->getFirstError());
    ApiKeyService::errorResponse('CHECKOUT_ERROR', $OrderService->getFirstError(), 500);
}

// Tính thời gian xử lý
$execution_time = round(microtime(true) - $start_time, 4);

// Log thành công
$log_request_data = [
    'user_id' => $user_id,
    'items_count' => count($data['items']),
    'coupon_code' => $coupon_code ?: null
];

$log_response_data = [
    'orders_count' => count($result['orders']),
    'total_amount' => $result['total_amount']
];

$ApiService->logRequest($api_key, 'orders/create', 'success', 'Order created successfully', $log_request_data, $log_response_data);

// Response
$response_data = [
    'orders' => array_map(function($order) {
        return [
            'order_id' => $order['order_id'],
            'trans_id' => $order['trans_id'],
            'product_id' => $order['product_id'],
            'product_name' => $order['product_name'],
            'plan_id' => $order['plan_id'],
            'plan_name' => $order['plan_name'],
            'quantity' => $order['quantity'],
            'unit_price' => $order['unit_price'],
            'total' => $order['total'],
            'discount' => $order['discount'],
            'final_amount' => $order['final_amount'],
            'status' => $order['status']
        ];
    }, $result['orders']),
    'summary' => [
        'orders_count' => count($result['orders']),
        'original_total' => $result['original_total'],
        'discount_amount' => $result['discount_amount'],
        'coupon_code' => $result['coupon_code'] ?: null,
        'total_amount' => $result['total_amount'],
        'new_balance' => $result['new_balance']
    ],
    'execution_time' => $execution_time
];

// Thêm warning nếu coupon không hợp lệ
if ($coupon_warning) {
    $response_data['warnings'] = [
        [
            'code' => 'COUPON_SKIPPED',
            'message' => $coupon_warning
        ]
    ];
}

$message = __('Đơn hàng đã được tạo thành công');
if ($coupon_warning) {
    $message .= ' ' . __('(Mã giảm giá không được áp dụng)');
}

ApiKeyService::jsonResponse(true, $response_data, $message, 201);

