<?php

/**
 * API Endpoint (ADMIN): Cập nhật trạng thái đơn hàng
 * 
 * POST/GET /api/v1/admin/orders/update-status.php
 * 
 * Parameters:
 *   - api_key: (required) API key từ bảng users (admin)
 *   - trans_id: (required) Mã đơn hàng cần cập nhật
 *   - status: (required) Trạng thái mới (processing, completed, cancelled)
 *   - delivery_content: (optional) Nội dung giao hàng (khi status = completed)
 *   - note: (optional) Ghi chú nội bộ
 * 
 * Ví dụ:
 *   POST: ?api_key=xxx&trans_id=ORD123&status=completed&delivery_content=Account:pass123
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
$trans_id = $_REQUEST['trans_id'] ?? '';
$new_status = $_REQUEST['status'] ?? '';
$delivery_content = $_REQUEST['delivery_content'] ?? '';
$note = $_REQUEST['note'] ?? null;

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

// Kiểm tra quyền edit_orders_product
if (checkPermission($user['admin'], 'edit_orders_product') != true) {
    checkBlockIP('API', 15);
    errorResponse('PERMISSION_DENIED', __('Bạn không có quyền cập nhật đơn hàng'), 403);
}

// ==================== VALIDATE INPUT ====================

// Kiểm tra trans_id
if (empty($trans_id)) {
    errorResponse('TRANS_ID_REQUIRED', __('Vui lòng cung cấp mã đơn hàng (trans_id)'), 400);
}

// Kiểm tra status
if (empty($new_status)) {
    errorResponse('STATUS_REQUIRED', __('Vui lòng cung cấp trạng thái mới (status)'), 400);
}

// Validate status value
$allowed_statuses = ['processing', 'completed', 'cancelled'];
if (!in_array($new_status, $allowed_statuses)) {
    errorResponse('INVALID_STATUS', __('Trạng thái không hợp lệ. Chỉ chấp nhận: processing, completed, cancelled'), 400);
}

// ==================== TÌM ĐƠN HÀNG ====================

// Tìm đơn hàng theo trans_id
$order = $CMSNT->get_row_safe(
    "SELECT * FROM `product_orders` WHERE `trans_id` = ?",
    [$trans_id]
);

if (!$order) {
    errorResponse('ORDER_NOT_FOUND', __('Không tìm thấy đơn hàng'), 404);
}

$old_status = $order['status'];

// Không cho phép cập nhật đơn hàng đã hoàn thành hoặc đã hủy
if (in_array($old_status, ['completed', 'cancelled', 'cancelled_no_refund'])) {
    errorResponse('ORDER_ALREADY_FINALIZED', __('Không thể cập nhật đơn hàng đã hoàn thành hoặc đã hủy'), 400);
}

// ==================== CẬP NHẬT ĐƠN HÀNG ====================

// Chuẩn bị dữ liệu cập nhật
$update_data = [
    'status' => $new_status,
    'updated_at' => gettime()
];

// Nếu hoàn thành, thêm delivery_content và completed_at
if ($new_status === 'completed') {
    if (!empty($delivery_content)) {
        $update_data['delivery_content'] = strip_tags($delivery_content);
    }
    $update_data['completed_at'] = gettime();
}

// Thêm ghi chú nếu có
if ($note !== null && $note !== '') {
    $update_data['note'] = strip_tags($note);
}

// Thực hiện cập nhật
$update_result = $CMSNT->update('product_orders', $update_data, "`id` = ?", [$order['id']]);

if (!$update_result) {
    errorResponse('UPDATE_FAILED', __('Cập nhật đơn hàng thất bại'), 500);
}

// Log hành động
$CMSNT->insert("logs", [
    'user_id'       => $user['id'],
    'ip'            => myip(),
    'device'        => 'API Admin Update Order Status',
    'createdate'    => gettime(),
    'action'        => 'API Admin Update Order Status: ' . $trans_id . ' (' . $old_status . ' -> ' . $new_status . ')'
]);

// ==================== RESPONSE ====================

jsonResponse(true, [
    'trans_id' => $trans_id,
    'old_status' => $old_status,
    'new_status' => $new_status,
    'updated_at' => gettime()
], __('Cập nhật trạng thái đơn hàng thành công'));
