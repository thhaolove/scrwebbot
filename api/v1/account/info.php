<?php
/**
 * API Endpoint: Thông tin tài khoản
 * GET /api/v1/account/info
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

$user = $ApiService->getUserData();
$api_key_data = $ApiService->getApiKeyData();

global $CMSNT;

// Đếm số đơn hàng
$orders_count = $CMSNT->get_row_safe(
    "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
     FROM `product_orders` WHERE `user_id` = ?",
    [$user['id']]
);

// Lấy usage stats
$usage_stats = $ApiService->getUsageStats($api_key, 'day');

// Tính thời gian xử lý
$execution_time = round(microtime(true) - $start_time, 4);

// Log
$ApiService->logRequest($api_key, 'account/info', 'success');

// Response
$response_data = [
    'user' => [
        'id' => (int)$user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'phone' => $user['phone'] ?? null,
        'balance' => (float)$user['money'],
        'total_deposited' => (float)$user['total_money'],
        'created_at' => $user['thoigian'] ?? null
    ],
    'api_key' => [
        'name' => $api_key_data['name'],
        'permissions' => json_decode($api_key_data['permissions'], true) ?: [],
        'rate_limit' => (int)$api_key_data['rate_limit'],
        'daily_limit' => (int)$api_key_data['daily_limit'],
        'expires_at' => $api_key_data['expires_at'],
        'created_at' => $api_key_data['created_at']
    ],
    'orders_summary' => [
        'total' => (int)($orders_count['total'] ?? 0),
        'pending' => (int)($orders_count['pending'] ?? 0),
        'completed' => (int)($orders_count['completed'] ?? 0)
    ],
    'api_usage_today' => $usage_stats,
    'execution_time' => $execution_time
];

ApiKeyService::jsonResponse(true, $response_data);

