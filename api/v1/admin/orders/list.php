<?php

/**
 * API Endpoint (ADMIN): Lấy danh sách đơn hàng
 * 
 * POST/GET /api/v1/admin/orders/list.php
 * 
 * Parameters:
 *   - api_key: (required) API key từ bảng users (admin)
 *   - status: (optional) Lọc theo trạng thái (pending, processing, completed, cancelled)
 *   - page: (optional) Trang (default: 1)
 *   - limit: (optional) Số lượng/trang (default: 10, max: 100)
 *   - from_date: (optional) Từ ngày (Y-m-d)
 *   - to_date: (optional) Đến ngày (Y-m-d)
 * 
 * Ví dụ:
 *   GET: ?api_key=xxx&status=pending&page=1&limit=20
 * 
 * @package SHOPKEY API (Admin)
 * @version 1.0.0
 */

define("IN_SITE", true);

// Headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once(__DIR__ . '/../../../../libs/db.php');
require_once(__DIR__ . '/../../../../libs/lang.php');
require_once(__DIR__ . '/../../../../libs/helper.php');
require_once(__DIR__ . '/../../../../config.php');

/**
 * JSON Response helper
 */
function jsonResponse($success, $data = null, $message = '', $code = 200)
{
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Error Response helper
 */
function errorResponse($error_code, $message, $http_code = 400)
{
    http_response_code($http_code);
    echo json_encode([
        'success' => false,
        'error' => $error_code,
        'message' => $message
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Lấy parameters từ GET hoặc POST
$api_key = $_REQUEST['api_key'] ?? '';
$status = isset($_REQUEST['status']) ? trim($_REQUEST['status']) : null;
$page = isset($_REQUEST['page']) ? max(1, (int)$_REQUEST['page']) : 1;
$limit = isset($_REQUEST['limit']) ? min(100, max(1, (int)$_REQUEST['limit'])) : 10;
$from_date = isset($_REQUEST['from_date']) ? trim($_REQUEST['from_date']) : null;
$to_date = isset($_REQUEST['to_date']) ? trim($_REQUEST['to_date']) : null;

// ==================== XÁC THỰC ====================

// Kiểm tra api_key
if (empty($api_key)) {
    errorResponse('API_KEY_REQUIRED', __('Vui lòng cung cấp api_key'), 401);
}

// Tìm user theo api_key
$user = $CMSNT->get_row_safe(
    "SELECT * FROM `users` WHERE `api_key` = ? AND `banned` = 0",
    [$api_key]
);

if (!$user) {
    checkBlockIP('API', 15);
    errorResponse('INVALID_API_KEY', __('API key không hợp lệ hoặc tài khoản đã bị khóa'), 401);
}

// ==================== PHÂN QUYỀN ====================

// Kiểm tra quyền edit_orders_product (quyền quản lý đơn hàng)
if (checkPermission($user['admin'], 'edit_orders_product') != true) {
    checkBlockIP('API', 15);
    errorResponse('PERMISSION_DENIED', __('Bạn không có quyền xem danh sách đơn hàng'), 403);
}

// ==================== BUILD QUERY ====================

$offset = ($page - 1) * $limit;

$where_conditions = [];
$params = [];

// Filter by status
if ($status) {
    $allowed_statuses = ['pending', 'processing', 'completed', 'cancelled', 'refunded'];
    if (in_array($status, $allowed_statuses)) {
        $where_conditions[] = 'po.`status` = ?';
        $params[] = $status;
    }
}

// Filter by date
if ($from_date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date)) {
    $where_conditions[] = 'DATE(po.`created_at`) >= ?';
    $params[] = $from_date;
}

if ($to_date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)) {
    $where_conditions[] = 'DATE(po.`created_at`) <= ?';
    $params[] = $to_date;
}

$where_clause = count($where_conditions) > 0
    ? 'WHERE ' . implode(' AND ', $where_conditions)
    : '';

// ==================== COUNT TOTAL ====================

$count_sql = "SELECT COUNT(*) as total FROM `product_orders` po {$where_clause}";
$total_result = $CMSNT->get_row_safe($count_sql, $params);
$total = $total_result ? (int)$total_result['total'] : 0;

// ==================== GET ORDERS ====================

$query_params = array_merge($params, [$limit, $offset]);

$orders_sql = "SELECT po.*, p.name as product_name, p.slug as product_slug, pp.name as plan_name, u.username
               FROM `product_orders` po
               LEFT JOIN `products` p ON po.product_id = p.id
               LEFT JOIN `product_plans` pp ON po.plan_id = pp.id
               LEFT JOIN `users` u ON po.user_id = u.id
               {$where_clause}
               ORDER BY po.id DESC
               LIMIT ? OFFSET ?";

$orders = $CMSNT->get_list_safe($orders_sql, $query_params);

// ==================== FORMAT RESPONSE ====================

$formatted_orders = [];
foreach ($orders as $order) {
    $formatted_orders[] = [
        'id' => (int)$order['id'],
        'trans_id' => $order['trans_id'],
        'user' => [
            'id' => (int)$order['user_id'],
            'username' => $order['username'] ?? ''
        ],
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
        'payment_status' => $order['payment_status'] ?? null,
        'delivery_content' => $order['delivery_content'] ?? null,
        'note' => $order['note'] ?? null,
        'created_at' => $order['created_at'],
        'completed_at' => $order['completed_at'] ?? null
    ];
}

// Log hành động
$CMSNT->insert("logs", [
    'user_id'       => $user['id'],
    'ip'            => myip(),
    'device'        => 'API Admin List Orders',
    'createdate'    => gettime(),
    'action'        => 'API Admin List Orders (page: ' . $page . ', limit: ' . $limit . ', status: ' . ($status ?: 'all') . ')'
]);

// ==================== RESPONSE ====================

jsonResponse(true, [
    'orders' => $formatted_orders,
    'pagination' => [
        'current_page' => $page,
        'per_page' => $limit,
        'total' => $total,
        'total_pages' => ceil($total / $limit),
        'has_more' => ($page * $limit) < $total
    ]
], __('Lấy danh sách đơn hàng thành công'));
