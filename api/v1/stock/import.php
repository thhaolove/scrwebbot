<?php

/**
 * API Endpoint: Import Stock vào kho hàng
 * 
 * POST/GET /api/v1/stock/import.php
 * 
 * Parameters:
 *   - api_key: (required) API key từ bảng users
 *   - plan_id: (required) ID của product plan (phải là gói giao ngay)
 *   - stock_data: (required) Dữ liệu stock, mỗi dòng là 1 item
 * 
 * Ví dụ:
 *   POST: ?api_key=xxx&plan_id=123&stock_data=acc1:pass1%0Aacc2:pass2
 *   GET:  ?api_key=xxx&plan_id=123&stock_data=acc1:pass1%0Aacc2:pass2
 * 
 * @package SHOPKEY API
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

require_once(__DIR__ . '/../../../libs/db.php');
require_once(__DIR__ . '/../../../libs/lang.php');
require_once(__DIR__ . '/../../../libs/helper.php');
require_once(__DIR__ . '/../../../config.php');

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
$plan_id = isset($_REQUEST['plan_id']) ? intval($_REQUEST['plan_id']) : 0;
$stock_data = $_REQUEST['stock_data'] ?? '';

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

// Kiểm tra quyền edit_product_stock
if (checkPermission($user['admin'], 'edit_product_stock') != true) {
    checkBlockIP('API', 15);
    errorResponse('PERMISSION_DENIED', __('Bạn không có quyền quản lý kho hàng'), 403);
}

// ==================== VALIDATE INPUT ====================

// Kiểm tra plan_id
if ($plan_id <= 0) {
    errorResponse('INVALID_PLAN_ID', __('Plan ID không hợp lệ'), 400);
}

// Kiểm tra stock_data
if (empty($stock_data)) {
    errorResponse('STOCK_DATA_REQUIRED', __('Vui lòng cung cấp dữ liệu kho hàng'), 400);
}

// Kiểm tra gói tồn tại và là gói giao ngay
$plan = $CMSNT->get_row_safe(
    "SELECT pp.*, p.name as product_name 
     FROM `product_plans` pp 
     LEFT JOIN `products` p ON pp.product_id = p.id 
     WHERE pp.id = ?",
    [$plan_id]
);

if (!$plan) {
    errorResponse('PLAN_NOT_FOUND', __('Gói sản phẩm không tồn tại'), 404);
}

if (!isset($plan['is_instant']) || (int)$plan['is_instant'] != 1) {
    errorResponse('NOT_INSTANT_PLAN', __('Chỉ gói sản phẩm giao ngay mới có thể quản lý kho hàng'), 400);
}

// ==================== IMPORT STOCK ====================

// Tách dữ liệu theo từng dòng
$lines = explode("\n", $stock_data);
$lines = array_map('trim', $lines);
$lines = array_filter($lines); // Loại bỏ dòng trống

if (empty($lines)) {
    errorResponse('NO_VALID_DATA', __('Không có dữ liệu hợp lệ'), 400);
}

$success_count = 0;
$error_count = 0;

foreach ($lines as $line) {
    if (empty($line)) continue;

    $stock_value = strip_tags($line);
    if (empty($stock_value)) continue;

    $isInsert = $CMSNT->insert("product_stock", [
        'plan_id'       => $plan_id,
        'stock_value'   => $stock_value,
        'status'        => 1, // Mặc định là còn hàng
        'created_at'    => gettime(),
        'updated_at'    => gettime()
    ]);

    if ($isInsert) {
        $success_count++;
    } else {
        $error_count++;
    }
}

// Log hành động
$CMSNT->insert("logs", [
    'user_id'       => $user['id'],
    'ip'            => myip(),
    'device'        => 'API Import Stock',
    'createdate'    => gettime(),
    'action'        => 'API Import Product Stock (' . $success_count . ' items) for Plan ID ' . $plan_id
]);

// Đếm số lượng stock còn lại
$stock_available = $CMSNT->get_row_safe(
    "SELECT COUNT(*) as total FROM `product_stock` WHERE `plan_id` = ? AND `status` = 1",
    [$plan_id]
);

// ==================== RESPONSE ====================

if ($success_count > 0) {
    jsonResponse(true, [
        'plan_id' => $plan_id,
        'plan_name' => html_entity_decode($plan['name'], ENT_QUOTES, 'UTF-8'),
        'product_name' => $plan['product_name'] ?? '',
        'imported_count' => $success_count,
        'failed_count' => $error_count,
        'stock_available' => (int)($stock_available['total'] ?? 0)
    ], __('Nhập kho hàng thành công'), 201);
}

errorResponse('IMPORT_FAILED', __('Nhập kho hàng thất bại'), 500);
