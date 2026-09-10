<?php

/**
 * API Endpoint: Danh sách chuyên mục
 * GET /api/v1/categories/list
 * 
 * Headers:
 *   X-API-Key: your_api_key
 *   X-API-Secret: your_api_secret
 * 
 * Parameters:
 *   - parent_id: Lọc theo parent (default: null = tất cả)
 *   - include_children: Bao gồm categories con (default: 0)
 *   - include_products: Bao gồm số lượng sản phẩm (default: 0)
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
if (!$ApiService->hasPermission('products.list') && !$ApiService->hasPermission('all')) {
    $ApiService->logRequest($api_key, 'categories/list', 'failed', 'Permission denied');
    ApiKeyService::errorResponse('PERMISSION_DENIED', __('Bạn không có quyền xem chuyên mục'), 403);
}

global $CMSNT;

// Parse parameters
$parent_id = isset($_REQUEST['parent_id']) ? validate_int($_REQUEST['parent_id'], 0) : null;
$include_children = isset($_REQUEST['include_children']) ? (bool)$_REQUEST['include_children'] : false;
$include_products = isset($_REQUEST['include_products']) ? (bool)$_REQUEST['include_products'] : false;

// Build query
$where_conditions = ["`status` = 'show'"];
$params = [];

if ($parent_id !== null) {
    $where_conditions[] = '`parent_id` = ?';
    $params[] = $parent_id;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get categories
$categories_sql = "SELECT * FROM `categories` {$where_clause} ORDER BY `stt` DESC, `id` ASC";
$categories = $CMSNT->get_list_safe($categories_sql, $params);

// Format response
$formatted_categories = [];

foreach ($categories as $cat) {
    // Xử lý full URL ảnh
    $imageUrl = null;
    if (!empty($cat['icon'])) {
        // Nếu đã là URL đầy đủ thì giữ nguyên, nếu không thì thêm base_url
        if (filter_var($cat['icon'], FILTER_VALIDATE_URL)) {
            $imageUrl = $cat['icon'];
        } else {
            $imageUrl = base_url($cat['icon']);
        }
    }

    $category_data = [
        'id' => (int)$cat['id'],
        'name' => html_entity_decode($cat['name'], ENT_QUOTES, 'UTF-8'),
        'slug' => $cat['slug'],
        'description' => $cat['description'] ?? '',
        'image' => $imageUrl,
        'parent_id' => (int)($cat['parent_id'] ?? 0),
        'sort_order' => (int)($cat['stt'] ?? 0)
    ];

    // Đếm sản phẩm nếu yêu cầu
    if ($include_products) {
        $product_count = $CMSNT->num_rows_safe(
            "SELECT id FROM `products` WHERE FIND_IN_SET(?, `category_ids`) AND `status` = 1",
            [$cat['id']]
        );
        $category_data['product_count'] = $product_count;
    }

    // Lấy categories con nếu yêu cầu
    if ($include_children) {
        $children = $CMSNT->get_list_safe(
            "SELECT * FROM `categories` WHERE `parent_id` = ? AND `status` = 'show' ORDER BY `stt` DESC",
            [$cat['id']]
        );

        $category_data['children'] = array_map(function ($child) use ($include_products, $CMSNT) {
            // Xử lý full URL ảnh cho children
            $childImageUrl = null;
            if (!empty($child['icon'])) {
                if (filter_var($child['icon'], FILTER_VALIDATE_URL)) {
                    $childImageUrl = $child['icon'];
                } else {
                    $childImageUrl = base_url($child['icon']);
                }
            }

            $child_data = [
                'id' => (int)$child['id'],
                'name' => html_entity_decode($child['name'], ENT_QUOTES, 'UTF-8'),
                'slug' => $child['slug'],
                'description' => $child['description'] ?? '',
                'image' => $childImageUrl,
                'parent_id' => (int)$child['parent_id'],
                'sort_order' => (int)($child['stt'] ?? 0)
            ];

            if ($include_products) {
                $child_data['product_count'] = $CMSNT->num_rows_safe(
                    "SELECT id FROM `products` WHERE FIND_IN_SET(?, `category_ids`) AND `status` = 1",
                    [$child['id']]
                );
            }

            return $child_data;
        }, $children);
    }

    $formatted_categories[] = $category_data;
}

// Tính thời gian xử lý
$execution_time = round(microtime(true) - $start_time, 4);

// Log
$ApiService->logRequest($api_key, 'categories/list', 'success', null, [
    'parent_id' => $parent_id,
    'include_children' => $include_children
]);

// Response
$response_data = [
    'categories' => $formatted_categories,
    'total' => count($formatted_categories),
    'execution_time' => $execution_time
];

ApiKeyService::jsonResponse(true, $response_data);
