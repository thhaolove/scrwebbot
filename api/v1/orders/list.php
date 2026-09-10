<?php
/**
 * API Endpoint: Danh sách đơn hàng
 * GET /api/v1/orders/list?page=1&limit=10&status=completed
 * 
 * Headers:
 *   X-API-Key: your_api_key
 *   X-API-Secret: your_api_secret
 * 
 * Parameters:
 *   - page: Trang (default: 1)
 *   - limit: Số lượng/trang (default: 10, max: 100)
 *   - status: Lọc theo trạng thái (pending, completed, cancelled, refunded)
 *   - from_date: Từ ngày (Y-m-d)
 *   - to_date: Đến ngày (Y-m-d)
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
if (!$ApiService->hasPermission('orders.list') && !$ApiService->hasPermission('all')) {
    $ApiService->logRequest($api_key, 'orders/list', 'failed', 'Permission denied');
    ApiKeyService::errorResponse('PERMISSION_DENIED', __('Bạn không có quyền xem đơn hàng'), 403);
}

global $CMSNT;

// Parse parameters
$page = isset($_REQUEST['page']) ? max(1, (int)$_REQUEST['page']) : 1;
$limit = isset($_REQUEST['limit']) ? min(100, max(1, (int)$_REQUEST['limit'])) : 10;
$status = isset($_REQUEST['status']) ? validate_string($_REQUEST['status'], 20) : null;
$from_date = isset($_REQUEST['from_date']) ? validate_string($_REQUEST['from_date'], 10) : null;
$to_date = isset($_REQUEST['to_date']) ? validate_string($_REQUEST['to_date'], 10) : null;

$offset = ($page - 1) * $limit;

// Build query
$where_conditions = [];
$params = [];

// Chỉ xem đơn hàng của chính mình (trừ khi có quyền all)
$user_id = $ApiService->getUserId();
if (!$ApiService->hasPermission('all')) {
    $where_conditions[] = '`user_id` = ?';
    $params[] = $user_id;
}

// Filter by status
if ($status) {
    $allowed_statuses = ['pending', 'completed', 'cancelled', 'refunded'];
    if (in_array($status, $allowed_statuses)) {
        $where_conditions[] = '`status` = ?';
        $params[] = $status;
    }
}

// Filter by date
if ($from_date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date)) {
    $where_conditions[] = 'DATE(`created_at`) >= ?';
    $params[] = $from_date;
}

if ($to_date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)) {
    $where_conditions[] = 'DATE(`created_at`) <= ?';
    $params[] = $to_date;
}

$where_clause = count($where_conditions) > 0 
    ? 'WHERE ' . implode(' AND ', $where_conditions) 
    : '';

// Count total
$count_sql = "SELECT COUNT(*) as total FROM `product_orders` {$where_clause}";
$total_result = $CMSNT->get_row_safe($count_sql, $params);
$total = $total_result ? (int)$total_result['total'] : 0;

// Get orders
$params[] = $offset;
$params[] = $limit;

$orders_sql = "SELECT po.*, p.name as product_name, p.slug as product_slug, pp.name as plan_name
               FROM `product_orders` po
               LEFT JOIN `products` p ON po.product_id = p.id
               LEFT JOIN `product_plans` pp ON po.plan_id = pp.id
               {$where_clause}
               ORDER BY po.id DESC
               LIMIT ?, ?";

$orders = $CMSNT->get_list_safe($orders_sql, $params);

// Format response
$formatted_orders = [];
foreach ($orders as $order) {
    $formatted_orders[] = [
        'id' => (int)$order['id'],
        'trans_id' => $order['trans_id'],
        'product' => [
            'id' => (int)$order['product_id'],
            'name' => html_entity_decode($order['product_name'] ?? '', ENT_QUOTES, 'UTF-8'),
            'slug' => $order['product_slug'] ?? ''
        ],
        'plan' => [
            'id' => (int)$order['plan_id'],
            'name' => html_entity_decode($order['plan_name'] ?? '', ENT_QUOTES, 'UTF-8')
        ],
        'quantity' => (int)$order['quantity'],
        'total_price' => (float)$order['total_price'],
        'final_amount' => (float)$order['final_amount'],
        'status' => $order['status'],
        'payment_status' => $order['payment_status'],
        'created_at' => $order['created_at']
    ];
}

// Tính thời gian xử lý
$execution_time = round(microtime(true) - $start_time, 4);

// Log
$ApiService->logRequest($api_key, 'orders/list', 'success', null, [
    'page' => $page,
    'limit' => $limit,
    'status' => $status
]);

// Response
$response_data = [
    'orders' => $formatted_orders,
    'pagination' => [
        'current_page' => $page,
        'per_page' => $limit,
        'total' => $total,
        'total_pages' => ceil($total / $limit),
        'has_more' => ($page * $limit) < $total
    ],
    'execution_time' => $execution_time
];

ApiKeyService::jsonResponse(true, $response_data);

