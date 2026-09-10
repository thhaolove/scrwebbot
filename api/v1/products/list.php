<?php

/**
 * API Endpoint: Danh sách sản phẩm
 * GET /api/v1/products/list?page=1&limit=10&category_id=1
 * 
 * Headers:
 *   X-API-Key: your_api_key
 *   X-API-Secret: your_api_secret
 * 
 * Parameters:
 *   - page: Trang (default: 1)
 *   - limit: Số lượng/trang (default: 10, max: 100)
 *   - category_id: Lọc theo danh mục
 *   - search: Tìm kiếm theo tên
 *   - sort: Sắp xếp (newest, oldest, price_asc, price_desc, bestseller)
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
    $ApiService->logRequest($api_key, 'products/list', 'failed', 'Permission denied');
    ApiKeyService::errorResponse('PERMISSION_DENIED', __('Bạn không có quyền xem sản phẩm'), 403);
}

global $CMSNT;

// Parse parameters
$page = isset($_REQUEST['page']) ? max(1, (int)$_REQUEST['page']) : 1;
$limit = isset($_REQUEST['limit']) ? min(100, max(1, (int)$_REQUEST['limit'])) : 10;
$category_id = isset($_REQUEST['category_id']) ? validate_int($_REQUEST['category_id'], 1) : null;
$search = isset($_REQUEST['search']) ? validate_string($_REQUEST['search'], 100) : null;
$sort = isset($_REQUEST['sort']) ? validate_string($_REQUEST['sort'], 20) : 'newest';

$offset = ($page - 1) * $limit;

// Build query (dùng alias p. để tránh ambiguous column)
$where_conditions = ['p.`status` = 1'];
$params = [];

// Filter by category (supports multi-category via FIND_IN_SET)
if ($category_id) {
    $where_conditions[] = 'FIND_IN_SET(?, p.`category_ids`)';
    $params[] = $category_id;
}

// Search
if ($search) {
    $where_conditions[] = '(p.`name` LIKE ? OR p.`description` LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Sort (dùng alias p.)
switch ($sort) {
    case 'oldest':
        $order_by = 'ORDER BY p.`id` ASC';
        break;
    case 'price_asc':
    case 'price_desc':
        // Sẽ sort trong PHP sau khi lấy data vì min_price không có trong DB
        $order_by = 'ORDER BY p.`id` DESC';
        break;
    case 'bestseller':
        $order_by = 'ORDER BY p.`sold` DESC';
        break;
    case 'rating':
        $order_by = 'ORDER BY p.`rating` DESC';
        break;
    default:
        $order_by = 'ORDER BY p.`id` DESC';
}

// Count total (cần alias p cho products)
$count_sql = "SELECT COUNT(*) as total FROM `products` p {$where_clause}";
$total_result = $CMSNT->get_row_safe($count_sql, $params);
$total = $total_result ? (int)$total_result['total'] : 0;

// Get products
$params[] = $offset;
$params[] = $limit;

$products_sql = "SELECT p.*
                 FROM `products` p
                 {$where_clause}
                 {$order_by}
                 LIMIT ?, ?";

$products = $CMSNT->get_list_safe($products_sql, $params);

// Format response với plans
$formatted_products = [];
foreach ($products as $product) {
    // Lấy danh sách plans
    $plans = $CMSNT->get_list_safe(
        "SELECT `id`, `name`, `price`, `sale_price`, `is_instant`, `description`, `status`, `duration_type`, `duration_value`
         FROM `product_plans` 
         WHERE `product_id` = ? AND `status` = 1
         ORDER BY `sort_order` ASC, `id` ASC",
        [$product['id']]
    );

    $formatted_plans = [];
    foreach ($plans as $plan) {
        // Đếm stock nếu là giao ngay
        $stock_count = 0;
        if ($plan['is_instant'] == 1) {
            $stock_count = $CMSNT->num_rows_safe(
                "SELECT id FROM `product_stock` WHERE `plan_id` = ? AND `status` = 1",
                [$plan['id']]
            );
        }

        // Lấy fields của plan
        $fields = $CMSNT->get_list_safe(
            "SELECT `field_key`, `label`, `type`, `placeholder`, `is_required`
             FROM `product_fields`
             WHERE `plan_id` = ?
             ORDER BY `sort_order` ASC",
            [$plan['id']]
        );

        $final_price = ($plan['sale_price'] > 0 && $plan['sale_price'] < $plan['price'])
            ? $plan['sale_price']
            : $plan['price'];

        $formatted_plans[] = [
            'id' => (int)$plan['id'],
            'name' => html_entity_decode($plan['name'], ENT_QUOTES, 'UTF-8'),
            'price' => (float)$plan['price'],
            'sale_price' => (float)$plan['sale_price'],
            'final_price' => (float)$final_price,
            'is_instant' => (bool)$plan['is_instant'],
            'duration_type' => $plan['duration_type'] ?? 'lifetime',
            'duration_value' => $plan['duration_value'] ? (int)$plan['duration_value'] : null,
            'stock_count' => $stock_count,
            'in_stock' => !$plan['is_instant'] || $stock_count > 0,
            'description' => $plan['description'] ?? '',
            'fields' => array_map(function ($f) {
                return [
                    'key' => $f['field_key'],
                    'label' => $f['label'],
                    'type' => $f['type'],
                    'placeholder' => $f['placeholder'] ?? '',
                    'required' => (bool)$f['is_required']
                ];
            }, $fields)
        ];
    }

    // Tính min/max price từ plans
    $prices = array_column($formatted_plans, 'final_price');
    $min_price = !empty($prices) ? min($prices) : 0;
    $max_price = !empty($prices) ? max($prices) : 0;

    // Lấy tất cả chuyên mục từ category_ids
    $categories_data = [];
    if (!empty($product['category_ids'])) {
        $cat_ids = array_map('intval', explode(',', $product['category_ids']));
        foreach ($cat_ids as $cid) {
            $cat_info = $CMSNT->get_row_safe("SELECT `id`, `name`, `slug`, `icon` FROM `categories` WHERE `id` = ?", [$cid]);
            if ($cat_info) {
                $categories_data[] = [
                    'id' => (int)$cat_info['id'],
                    'name' => html_entity_decode($cat_info['name'] ?? '', ENT_QUOTES, 'UTF-8'),
                    'slug' => $cat_info['slug'] ?? '',
                    'image' => $cat_info['icon'] ? base_url($cat_info['icon']) : null
                ];
            }
        }
    }

    $formatted_products[] = [
        'id' => (int)$product['id'],
        'name' => html_entity_decode($product['name'], ENT_QUOTES, 'UTF-8'),
        'slug' => $product['slug'],
        'image' => $product['image'] ? base_url($product['image']) : null,
        'description' => $product['description'] ?? null,
        'category' => !empty($categories_data) ? $categories_data[0] : ['id' => 0, 'name' => '', 'slug' => ''],
        'categories' => $categories_data,
        'min_price' => (float)$min_price,
        'max_price' => (float)$max_price,
        'sold' => (int)($product['sold'] ?? 0),
        'rating' => (float)($product['rating'] ?? 0),
        'plans' => $formatted_plans
    ];
}

// Sort theo price trong PHP (vì min_price không có trong DB)
if ($sort === 'price_asc') {
    usort($formatted_products, function ($a, $b) {
        return $a['min_price'] <=> $b['min_price'];
    });
} elseif ($sort === 'price_desc') {
    usort($formatted_products, function ($a, $b) {
        return $b['min_price'] <=> $a['min_price'];
    });
}

// Tính thời gian xử lý
$execution_time = round(microtime(true) - $start_time, 4);

// Log
$ApiService->logRequest($api_key, 'products/list', 'success', null, [
    'page' => $page,
    'limit' => $limit,
    'category_id' => $category_id,
    'search' => $search
]);

// Response
$response_data = [
    'products' => $formatted_products,
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
