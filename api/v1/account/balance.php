<?php
/**
 * API Endpoint: Kiểm tra số dư tài khoản
 * GET /api/v1/account/balance
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

// Kiểm tra quyền
if (!$ApiService->hasPermission('account.balance') && !$ApiService->hasPermission('all')) {
    $ApiService->logRequest($api_key, 'account/balance', 'failed', 'Permission denied');
    ApiKeyService::errorResponse('PERMISSION_DENIED', __('Bạn không có quyền xem số dư'), 403);
}

$user = $ApiService->getUserData();

// Tính thời gian xử lý
$execution_time = round(microtime(true) - $start_time, 4);

// Log
$ApiService->logRequest($api_key, 'account/balance', 'success');

// Response
$response_data = [
    'user' => [
        'id' => (int)$user['id'],
        'username' => $user['username'],
        'email' => $user['email']
    ],
    'balance' => [
        'current' => (float)$user['money'],
        'total_deposited' => (float)$user['total_money'],
        'currency' => currencyDefault() ?: 'VND',
        'formatted' => format_currency($user['money'])
    ],
    'execution_time' => $execution_time
];

ApiKeyService::jsonResponse(true, $response_data);

