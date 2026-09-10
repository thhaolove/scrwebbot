<?php

/**
 * BASE CRON SUPPLIER - Logic chung cho tất cả suppliers
 * 
 * CÁCH SỬ DỤNG:
 * 1. Tạo file cron mới (VD: newapi.php)
 * 2. Define SUPPLIER_TYPE và include file này
 * 
 * VÍ DỤ FILE CRON MỚI:
 * <?php
 * define('SUPPLIER_TYPE', 'NEWAPI');
 * require_once(__DIR__ . '/_base.php');
 * 
 * @package SHOPKEY
 * @author CMSNT
 * @version 1.0.0
 */

// Kiểm tra SUPPLIER_TYPE đã được define chưa
if (!defined('SUPPLIER_TYPE')) {
    die('SUPPLIER_TYPE chưa được định nghĩa');
}

define("IN_SITE", true);
require_once(__DIR__ . '/../../libs/db.php');
require_once(__DIR__ . '/../../libs/helper.php');
require_once(__DIR__ . '/../../libs/lang.php');
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/../../libs/suppliers/SupplierApiFactory.php');
require_once(__DIR__ . '/../../libs/services/ProductDeletionService.php');

$CMSNT = new DB();
$SUPPLIER_TYPE = strtoupper(SUPPLIER_TYPE);
$SETTING_KEY = 'time_cron_suppliers_' . strtolower(SUPPLIER_TYPE);

// Kiểm tra key cron job
if (!empty($CMSNT->site('key_cron_job'))) {
    if (empty($_GET['key']) || $_GET['key'] != $CMSNT->site('key_cron_job')) {
        die(__('Key không hợp lệ'));
    }
}

// CHỐNG SPAM - Chỉ cho phép chạy 5 giây/lần
$time_cron = $CMSNT->site($SETTING_KEY);
if (time() - $time_cron < 5) {
    die('Thao tác quá nhanh, vui lòng thử lại sau!');
}
$CMSNT->update("settings", [
    'value' => time()
], " `name` = ? ", [$SETTING_KEY]);

// Kiểm tra API type có được hỗ trợ không
if (!SupplierApiFactory::isSupported($SUPPLIER_TYPE)) {
    die("API type '{$SUPPLIER_TYPE}' không được hỗ trợ");
}

// =====================================================
// HELPER FUNCTIONS
// =====================================================

/**
 * Download ảnh từ URL remote và lưu vào thư mục local
 */
function downloadRemoteImage($imageUrl, $prefix = 'sync', $proxy = '')
{
    if (empty($imageUrl) || !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
        return null;
    }

    $uploadDir = __DIR__ . '/../../assets/storage/images/';
    $relativeDir = 'assets/storage/images/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $urlParts = parse_url($imageUrl);
    $pathInfo = pathinfo($urlParts['path'] ?? '');
    $ext = strtolower($pathInfo['extension'] ?? 'jpg');

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
    if (!in_array($ext, $allowedExtensions)) {
        $ext = 'jpg';
    }

    $rand = substr(md5(uniqid(mt_rand(), true)), 0, 8);
    $fileName = $prefix . '_' . $rand . '.' . $ext;
    $fullPath = $uploadDir . $fileName;
    $relativePath = $relativeDir . $fileName;

    $ch = curl_init($imageUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');

    if (!empty($proxy)) {
        curl_setopt($ch, CURLOPT_PROXY, $proxy);
    }

    $imageData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || empty($imageData)) {
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->buffer($imageData);
    $validMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];

    if (!in_array($mimeType, $validMimes)) {
        return null;
    }

    if (file_put_contents($fullPath, $imageData) !== false) {
        return $relativePath;
    }

    return null;
}

/**
 * Đồng bộ product_plans trực tiếp theo TÊN (mode cơ bản)
 */
function syncProductPlan($CMSNT, $supplier, $api)
{
    $api_name = $supplier['check_string_api'] == 'OFF' ? $api['name'] : validate_string($api['name'], 500, 1);
    $api_desc = $supplier['check_string_api'] == 'OFF'
        ? ($api['short_desc'] ?? ($api['description'] ?? ''))
        : validate_string($api['short_desc'] ?? ($api['description'] ?? ''), 5000);
    $api_price = validate_float($api['price'], 0);
    $api_stock = isset($api['amount']) ? intval($api['amount']) : (isset($api['stock']) ? intval($api['stock']) : 0);
    $is_instant = isset($api['is_instant']) ? ($api['is_instant'] ? 1 : 0) : 1;
    $duration_type = $api['duration_type'] ?? 'lifetime';
    $duration_value = isset($api['duration_value']) ? (int)$api['duration_value'] : null;

    // Gói thủ công (đặt hàng) luôn có stock = 999
    if ($is_instant == 0) {
        $api_stock = 999;
    }

    if ($api_name === false || $api_price === false) return;

    // Quy đổi rate tiền tệ
    if (isset($supplier['rate']) && $supplier['rate'] != 1 && $supplier['rate'] > 0) {
        $api_price = $api_price * $supplier['rate'];
    }

    // Tính giá bán
    $ck = $api_price * $supplier['discount'] / 100;
    $price = $api_price;
    if ($supplier['update_price'] == 'ON') {
        if ($supplier['roundMoney'] == 'ON') {
            $price = roundMoney($api_price + $ck);
        } else {
            $price = $api_price + $ck;
        }
    }

    // Ưu tiên tìm theo api_id + supplier_id để tránh xóa/tạo lại khi tên thay đổi
    $api_id = $api['id'] ?? null;
    $existing = null;

    if (!empty($api_id)) {
        $existing = $CMSNT->get_row_safe(
            " SELECT * FROM `product_plans` WHERE `api_id` = ? AND `supplier_id` = ? LIMIT 1 ",
            [$api_id, $supplier['id']]
        );
    }

    // Fallback: tìm theo name
    if (!$existing) {
        $existing = $CMSNT->get_row_safe(" SELECT * FROM `product_plans` WHERE `name` = ? LIMIT 1 ", [$api_name]);
    }

    if (!$existing) {
        $planId = $CMSNT->insert('product_plans', [
            'product_id'    => 0,
            'supplier_id'   => $supplier['id'],
            'api_id'        => $api['id'] ?? null,
            'api_stock'     => $api_stock,
            'name'          => $api_name,
            'duration_type' => $duration_type,
            'duration_value' => $duration_value,
            'cost_price'    => $api_price,
            'price'         => $price,
            'sale_price'    => 0,
            'description'   => $api_desc,
            'is_instant'    => $is_instant,
            'image'         => null,
            'status'        => isset($supplier['isAutoShow']) && $supplier['isAutoShow'] == 1 ? 1 : 0,
            'sort_order'    => 0,
            'created_at'    => gettime(),
            'updated_at'    => gettime(),
            'api_sync_time' => gettime()
        ]);

        if ($CMSNT->site('debug_api_suppliers') == 1) {
            $duration_text = $duration_type == 'lifetime' ? 'lifetime' : $duration_value . ' ' . $duration_type;
            echo '<b style="color:orange;">CREATE</b> - ' . $api_name . ' (stock: ' . $api_stock . ' | price: ' . number_format($price) . ' | duration: ' . $duration_text . ')<br>';
        }

        // Sync fields cho plan mới
        if (!empty($api['fields']) && $planId) {
            syncProductFields($CMSNT, $planId, $api['fields']);
        }
    } else {
        // Nếu gói đã tồn tại nhưng không thuộc supplier này (hoặc tạo thủ công), bỏ qua không can thiệp
        if ($existing['supplier_id'] != $supplier['id']) {
            if ($CMSNT->site('debug_api_suppliers') == 1) {
                $ownerText = empty($existing['supplier_id']) ? 'tạo thủ công' : 'thuộc supplier #' . $existing['supplier_id'];
                echo '<b style="color:gray;">SKIP</b> - ' . $api_name . ' (' . $ownerText . ', bỏ qua)<br>';
            }
            return;
        }

        $plan_name = $supplier['update_name'] == 'OFF' ? $existing['name'] : $api_name;
        $plan_desc = $supplier['update_name'] == 'OFF' ? $existing['description'] : $api_desc;
        $plan_price = $supplier['update_price'] == 'OFF' ? $existing['price'] : $price;

        $updateData = [
            'api_id'        => $api['id'] ?? null,
            'api_stock'     => $api_stock,
            'name'          => $plan_name,
            'cost_price'    => $api_price,
            'price'         => $plan_price,
            'description'   => $plan_desc,
            'updated_at'    => gettime(),
            'api_sync_time' => gettime()
        ];

        // Chỉ update duration nếu API có trả về (giữ nguyên giá trị cũ nếu không có)
        if (isset($api['duration_type'])) {
            $updateData['duration_type'] = $api['duration_type'];
        }
        if (isset($api['duration_value'])) {
            $updateData['duration_value'] = (int)$api['duration_value'];
        }

        $CMSNT->update('product_plans', $updateData, " `id` = ? ", [$existing['id']]);

        if ($CMSNT->site('debug_api_suppliers') == 1) {
            $duration_text = $duration_type == 'lifetime' ? 'lifetime' : $duration_value . ' ' . $duration_type;
            echo '<b style="color:green;">UPDATE</b> - ' . $plan_name . ' (stock: ' . $api_stock . ' | price: ' . number_format($plan_price) . ' | duration: ' . $duration_text . ')<br>';
        }

        // Sync fields cho plan đã có
        if (!empty($api['fields'])) {
            syncProductFields($CMSNT, $existing['id'], $api['fields']);
        }
    }
}

/**
 * Đồng bộ product_fields cho một plan
 * Được gọi sau khi tạo hoặc cập nhật product_plan
 * 
 * @param DB $CMSNT Database instance
 * @param int $planId ID của plan đã tạo/cập nhật
 * @param array $fields Mảng fields từ API [['key'=>'email', 'label'=>'Email', 'type'=>'email', 'required'=>true, 'placeholder'=>''], ...]
 */
function syncProductFields($CMSNT, $planId, $fields)
{
    if (empty($fields) || !is_array($fields)) {
        return;
    }

    // Lấy danh sách field keys hiện tại của plan
    $existingFields = $CMSNT->get_list_safe(
        "SELECT `id`, `field_key` FROM `product_fields` WHERE `plan_id` = ?",
        [$planId]
    );
    $existingKeys = array_column($existingFields, 'field_key', 'id');

    $syncedKeys = [];
    $sortOrder = 0;

    foreach ($fields as $field) {
        $fieldKey = $field['key'] ?? ($field['field_key'] ?? '');
        $label = $field['label'] ?? $fieldKey;
        $type = $field['type'] ?? 'text';
        $isRequired = isset($field['required']) ? ($field['required'] ? 1 : 0) : (isset($field['is_required']) ? ($field['is_required'] ? 1 : 0) : 1);
        $placeholder = $field['placeholder'] ?? '';

        if (empty($fieldKey)) {
            continue;
        }

        // Validate type
        $validTypes = ['text', 'email', 'password', 'textarea'];
        if (!in_array($type, $validTypes)) {
            $type = 'text';
        }

        $syncedKeys[] = $fieldKey;

        // Kiểm tra field đã tồn tại chưa
        $existingFieldId = array_search($fieldKey, $existingKeys);

        if ($existingFieldId === false) {
            // Tạo mới
            $CMSNT->insert('product_fields', [
                'plan_id' => $planId,
                'field_key' => $fieldKey,
                'label' => $label,
                'type' => $type,
                'is_required' => $isRequired,
                'placeholder' => $placeholder,
                'sort_order' => $sortOrder
            ]);

            if ($CMSNT->site('debug_api_suppliers') == 1) {
                echo '<span style="color:cyan;">  + Field:</span> ' . $fieldKey . ' (' . $type . ($isRequired ? ', required' : '') . ')<br>';
            }
        } else {
            // Cập nhật
            $CMSNT->update('product_fields', [
                'label' => $label,
                'type' => $type,
                'is_required' => $isRequired,
                'placeholder' => $placeholder,
                'sort_order' => $sortOrder
            ], " `id` = ? ", [$existingFieldId]);
        }

        $sortOrder++;
    }

    // Xóa các fields không còn trong API (nếu có syncedKeys)
    if (!empty($syncedKeys)) {
        $deletionService = new ProductDeletionService($CMSNT);
        foreach ($existingKeys as $fieldId => $fieldKey) {
            if (!in_array($fieldKey, $syncedKeys)) {
                $result = $deletionService->deleteField($fieldId, false); // false = không dùng transaction riêng

                if ($CMSNT->site('debug_api_suppliers') == 1) {
                    if ($result) {
                        echo '<span style="color:red;">  - Field:</span> ' . $fieldKey . ' (removed)<br>';
                    } else {
                        echo '<span style="color:orange;">  ⚠ Field:</span> ' . $fieldKey . ' (xóa thất bại: ' . $deletionService->getFirstError() . ')<br>';
                    }
                }
            }
        }
    }
}

/**
 * Đồng bộ chuyên mục (cho mode full sync)
 */
function syncCategory($CMSNT, $supplier, $cat)
{
    $name = $supplier['check_string_api'] == 'OFF' ? $cat['name'] : validate_string($cat['name'], 255, 1);
    $slug = $cat['slug'] ?? create_slug($name);
    $description = $cat['description'] ?? '';
    $parent_id = $cat['parent_id'] ?? 0;
    $remoteImage = $cat['image'] ?? null;
    $stt = $cat['sort_order'] ?? 0;
    $proxy = $supplier['proxy'] ?? '';

    if (empty($name)) return null;

    $existing = $CMSNT->get_row_safe(
        " SELECT * FROM `categories` WHERE `name` = ? LIMIT 1 ",
        [$name]
    );

    // Download ảnh (chỉ khi sync_image != 'OFF')
    $localIcon = null;
    if ($supplier['sync_image'] != 'OFF' && !empty($remoteImage)) {
        if ($existing && !empty($existing['icon']) && !filter_var($existing['icon'], FILTER_VALIDATE_URL)) {
            $localIcon = $existing['icon'];
        } else {
            $downloadedPath = downloadRemoteImage($remoteImage, 'category', $proxy);
            if ($downloadedPath) {
                $localIcon = $downloadedPath;
                if ($CMSNT->site('debug_api_suppliers') == 1) {
                    echo '<span style="color:blue;">📷</span> ' . basename($downloadedPath) . '<br>';
                }
            }
        }
    }

    if (!$existing) {
        $catId = $CMSNT->insert('categories', [
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'parent_id' => $parent_id,
            'icon' => $localIcon,
            'supplier_id' => $supplier['id'],
            'stt' => $stt,
            'status' => isset($supplier['isAutoShow']) && $supplier['isAutoShow'] == 1 ? 'show' : 'hide',
            'created_at' => gettime(),
            'updated_at' => gettime()
        ]);

        if ($CMSNT->site('debug_api_suppliers') == 1) {
            echo '<span style="color:orange;">+ Category:</span> ' . $name . '<br>';
        }

        return $catId;
    } else {
        // Nếu chuyên mục đã tồn tại nhưng không thuộc supplier này (hoặc tạo thủ công), bỏ qua không can thiệp
        if ($existing['supplier_id'] != $supplier['id']) {
            if ($CMSNT->site('debug_api_suppliers') == 1) {
                $ownerText = empty($existing['supplier_id']) ? 'tạo thủ công' : 'thuộc supplier #' . $existing['supplier_id'];
                echo '<span style="color:gray;">⊘ Category:</span> ' . $name . ' (' . $ownerText . ', bỏ qua)<br>';
            }
            return $existing['id'];
        }

        // Luôn update updated_at để cleanup logic hoạt động đúng
        $updateData = [
            'supplier_id' => $supplier['id'],
            'updated_at' => gettime()
        ];

        // Chỉ cập nhật name/description nếu update_name = ON
        if ($supplier['update_name'] == 'ON') {
            $updateData['name'] = $name;
            $updateData['description'] = $description;
            $updateData['stt'] = $stt;
        }

        if ($localIcon) {
            $updateData['icon'] = $localIcon;
        }
        $CMSNT->update('categories', $updateData, " `id` = ? ", [$existing['id']]);

        if ($CMSNT->site('debug_api_suppliers') == 1) {
            echo '<span style="color:green;">~ Category:</span> ' . $name . '<br>';
        }

        return $existing['id'];
    }
}

/**
 * Đồng bộ sản phẩm (cho mode full sync)
 * @param array $localCatIds Mảng category IDs hoặc single category ID
 */
function syncProduct($CMSNT, $supplier, $prod, $localCatIds)
{
    $name = $supplier['check_string_api'] == 'OFF' ? $prod['name'] : validate_string($prod['name'], 500, 1);
    $slug = $prod['slug'] ?? create_slug($name);
    $description = $prod['description'] ?? '';
    $remoteImage = $prod['image'] ?? null;
    $sort_order = $prod['sort_order'] ?? 0;
    $proxy = $supplier['proxy'] ?? '';

    // Xử lý category_ids: chuyển thành mảng nếu cần
    if (!is_array($localCatIds)) {
        $localCatIds = $localCatIds > 0 ? [$localCatIds] : [];
    }
    $categoryIdsStr = !empty($localCatIds) ? implode(',', $localCatIds) : '';

    if (empty($name)) return null;

    // Tìm kiếm sản phẩm theo NAME hoặc SLUG
    $existing = $CMSNT->get_row_safe(
        " SELECT * FROM `products` WHERE `name` = ? OR `slug` = ? LIMIT 1 ",
        [$name, $slug]
    );

    // Download ảnh (chỉ khi sync_image != 'OFF')
    $localImage = null;
    if ($supplier['sync_image'] != 'OFF' && !empty($remoteImage)) {
        if ($existing && !empty($existing['image']) && !filter_var($existing['image'], FILTER_VALIDATE_URL)) {
            $localImage = $existing['image'];
        } else {
            $downloadedPath = downloadRemoteImage($remoteImage, 'product', $proxy);
            if ($downloadedPath) {
                $localImage = $downloadedPath;
                if ($CMSNT->site('debug_api_suppliers') == 1) {
                    echo '<span style="color:blue;">📷</span> ' . basename($downloadedPath) . '<br>';
                }
            }
        }
    }

    if (!$existing) {
        // Kiểm tra slug có trùng không (đề phòng)
        $slugExists = $CMSNT->get_row_safe(" SELECT `id` FROM `products` WHERE `slug` = ? LIMIT 1 ", [$slug]);
        if ($slugExists) {
            // Tạo slug unique bằng cách thêm suffix
            $originalSlug = $slug;
            $counter = 1;
            while ($slugExists) {
                $slug = $originalSlug . '-' . $counter;
                $slugExists = $CMSNT->get_row_safe(" SELECT `id` FROM `products` WHERE `slug` = ? LIMIT 1 ", [$slug]);
                $counter++;
                if ($counter > 100) break; // Giới hạn để tránh vòng lặp vô hạn
            }
        }

        $prodId = $CMSNT->insert('products', [
            'category_ids' => $categoryIdsStr,
            'supplier_id' => $supplier['id'],
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'image' => $localImage,
            'sort_order' => $sort_order,
            'status' => isset($supplier['isAutoShow']) && $supplier['isAutoShow'] == 1 ? 1 : 0,
            'created_at' => gettime(),
            'updated_at' => gettime()
        ]);

        if ($CMSNT->site('debug_api_suppliers') == 1) {
            echo '<span style="color:orange;">+ Product:</span> ' . $name . ' (categories: ' . $categoryIdsStr . ')<br>';
        }

        return $prodId;
    } else {
        // Nếu sản phẩm đã tồn tại nhưng không thuộc supplier này (hoặc tạo thủ công), bỏ qua không can thiệp
        if ($existing['supplier_id'] != $supplier['id']) {
            if ($CMSNT->site('debug_api_suppliers') == 1) {
                $ownerText = empty($existing['supplier_id']) ? 'tạo thủ công' : 'thuộc supplier #' . $existing['supplier_id'];
                echo '<span style="color:gray;">⊘ Product:</span> ' . $name . ' (' . $ownerText . ', bỏ qua)<br>';
            }
            return $existing['id'];
        }

        $updateData = [
            'updated_at' => gettime()
        ];

        if ($supplier['update_name'] == 'ON') {
            $updateData['name'] = $name;
            $updateData['description'] = $description;
        }

        if ($localImage && $localImage !== $existing['image']) {
            $updateData['image'] = $localImage;
        }

        // Cập nhật category_ids nếu có
        if (!empty($categoryIdsStr)) {
            $updateData['category_ids'] = $categoryIdsStr;
        }

        $CMSNT->update('products', $updateData, " `id` = ? ", [$existing['id']]);

        if ($CMSNT->site('debug_api_suppliers') == 1) {
            echo '<span style="color:green;">~ Product:</span> ' . $name . ' (categories: ' . $categoryIdsStr . ')<br>';
        }

        return $existing['id'];
    }
}

/**
 * Đồng bộ gói sản phẩm với product_id (cho mode full sync)
 */
function syncProductPlanWithProduct($CMSNT, $supplier, $plan, $productId, $productName)
{
    $planName = $plan['name'] ?? '';

    $name = $supplier['check_string_api'] == 'OFF' ? $planName : validate_string($planName, 500, 1);
    $api_desc = $supplier['check_string_api'] == 'OFF'
        ? ($plan['description'] ?? '')
        : validate_string($plan['description'] ?? '', 5000);

    $api_price = validate_float($plan['final_price'] ?? ($plan['price'] ?? 0), 0);
    $api_stock = $plan['stock_count'] ?? ($plan['amount'] ?? ($plan['stock'] ?? 0));
    $api_stock = intval($api_stock);
    $is_instant = isset($plan['is_instant']) ? ($plan['is_instant'] ? 1 : 0) : 1;
    $duration_type = $plan['duration_type'] ?? 'lifetime';
    $duration_value = isset($plan['duration_value']) ? (int)$plan['duration_value'] : null;

    // Gói thủ công (đặt hàng) luôn có stock = 999
    if ($is_instant == 0) {
        $api_stock = 999;
    }

    if (empty($name) || $api_price === false) return;

    // Quy đổi rate
    if (isset($supplier['rate']) && $supplier['rate'] != 1 && $supplier['rate'] > 0) {
        $api_price = $api_price * $supplier['rate'];
    }

    // Tính giá bán
    $ck = $api_price * $supplier['discount'] / 100;
    $price = $api_price;
    if ($supplier['update_price'] == 'ON') {
        if ($supplier['roundMoney'] == 'ON') {
            $price = roundMoney($api_price + $ck);
        } else {
            $price = $api_price + $ck;
        }
    }

    // Ưu tiên tìm theo api_id + supplier_id để tránh xóa/tạo lại khi tên thay đổi
    $api_id = $plan['id'] ?? null;
    $existing = null;

    if (!empty($api_id)) {
        $existing = $CMSNT->get_row_safe(
            " SELECT * FROM `product_plans` WHERE `api_id` = ? AND `supplier_id` = ? LIMIT 1 ",
            [$api_id, $supplier['id']]
        );
    }

    // Fallback: tìm theo name hoặc product_id + name LIKE
    if (!$existing) {
        $existing = $CMSNT->get_row_safe(
            " SELECT * FROM `product_plans` WHERE `name` = ? OR (`product_id` = ? AND `name` LIKE ?) LIMIT 1 ",
            [$name, $productId, '%' . $planName . '%']
        );
    }

    if (!$existing) {
        $planId = $CMSNT->insert('product_plans', [
            'product_id'    => $productId,
            'supplier_id'   => $supplier['id'],
            'api_id'        => $plan['id'] ?? null,
            'api_stock'     => $api_stock,
            'name'          => $name,
            'duration_type' => $duration_type,
            'duration_value' => $duration_value,
            'cost_price'    => $api_price,
            'price'         => $price,
            'sale_price'    => 0,
            'description'   => $api_desc,
            'is_instant'    => $is_instant,
            'image'         => null,
            'status'        => isset($supplier['isAutoShow']) && $supplier['isAutoShow'] == 1 ? 1 : 0,
            'sort_order'    => 0,
            'created_at'    => gettime(),
            'updated_at'    => gettime(),
            'api_sync_time' => gettime()
        ]);

        if ($CMSNT->site('debug_api_suppliers') == 1) {
            $duration_text = $duration_type == 'lifetime' ? 'lifetime' : $duration_value . ' ' . $duration_type;
            echo '<span style="color:orange;">+ Plan:</span> ' . $name . ' (stock: ' . $api_stock . ' | price: ' . number_format($price) . ' | duration: ' . $duration_text . ' | instant: ' . ($is_instant ? 'yes' : 'no') . ')<br>';
        }

        // Sync fields cho plan mới
        if (!empty($plan['fields']) && $planId) {
            syncProductFields($CMSNT, $planId, $plan['fields']);
        }
    } else {
        // Nếu gói đã tồn tại nhưng không thuộc supplier này (hoặc tạo thủ công), bỏ qua không can thiệp
        if ($existing['supplier_id'] != $supplier['id']) {
            if ($CMSNT->site('debug_api_suppliers') == 1) {
                $ownerText = empty($existing['supplier_id']) ? 'tạo thủ công' : 'thuộc supplier #' . $existing['supplier_id'];
                echo '<span style="color:gray;">⊘ Plan:</span> ' . $name . ' (' . $ownerText . ', bỏ qua)<br>';
            }
            return;
        }

        $plan_name = $supplier['update_name'] == 'OFF' ? $existing['name'] : $name;
        $plan_desc = $supplier['update_name'] == 'OFF' ? $existing['description'] : $api_desc;
        $plan_price = $supplier['update_price'] == 'OFF' ? $existing['price'] : $price;

        $updateData = [
            'product_id'    => $productId,
            'api_id'        => $plan['id'] ?? null,
            'api_stock'     => $api_stock,
            'name'          => $plan_name,
            'cost_price'    => $api_price,
            'price'         => $plan_price,
            'description'   => $plan_desc,
            'is_instant'    => $is_instant,
            'updated_at'    => gettime(),
            'api_sync_time' => gettime()
        ];

        // Chỉ update duration nếu API có trả về (giữ nguyên giá trị cũ nếu không có)
        if (isset($plan['duration_type'])) {
            $updateData['duration_type'] = $plan['duration_type'];
        }
        if (isset($plan['duration_value'])) {
            $updateData['duration_value'] = (int)$plan['duration_value'];
        }

        $CMSNT->update('product_plans', $updateData, " `id` = ? ", [$existing['id']]);

        if ($CMSNT->site('debug_api_suppliers') == 1) {
            $duration_text = $duration_type == 'lifetime' ? 'lifetime' : $duration_value . ' ' . $duration_type;
            echo '<span style="color:green;">~ Plan:</span> ' . $plan_name . ' (stock: ' . $api_stock . ' | price: ' . number_format($plan_price) . ' | duration: ' . $duration_text . ' | instant: ' . ($is_instant ? 'yes' : 'no') . ')<br>';
        }

        // Sync fields cho plan đã có
        if (!empty($plan['fields'])) {
            syncProductFields($CMSNT, $existing['id'], $plan['fields']);
        }
    }
}

/**
 * Đồng bộ full structure (Categories -> Products -> Plans)
 * @return bool True nếu sync thành công, False nếu API lỗi
 */
function syncFullStructure($CMSNT, $supplier, $api)
{
    // Kiểm tra API có method getFullStructure không
    if (!method_exists($api, 'getFullStructure')) {
        if ($CMSNT->site('debug_api_suppliers') == 1) {
            echo '<b style="color:red;">ERROR</b> - API không hỗ trợ full sync<br>';
        }
        return false;
    }

    $structure = $api->getFullStructure();

    if (!$structure) {
        if ($CMSNT->site('debug_api_suppliers') == 1) {
            echo '<b style="color:red;">ERROR</b> - Không thể lấy cấu trúc từ API<br>';
        }
        return false;
    }

    $categories = $structure['categories'] ?? [];
    $products = $structure['products'] ?? [];

    // Nếu API trả về cấu trúc rỗng, coi như sync không thành công
    if (empty($categories) && empty($products)) {
        if ($CMSNT->site('debug_api_suppliers') == 1) {
            echo '<b style="color:orange;">WARNING</b> - API trả về cấu trúc rỗng, bỏ qua cleanup<br>';
        }
        return false;
    }

    $categoryMap = [];

    // 1. Đồng bộ chuyên mục
    if (!empty($categories)) {
        if ($CMSNT->site('debug_api_suppliers') == 1) {
            echo '<b style="color:cyan;">CATEGORIES</b> - ' . count($categories) . ' chuyên mục<br>';
        }

        foreach ($categories as $cat) {
            $catId = syncCategory($CMSNT, $supplier, $cat);
            if ($catId) {
                $categoryMap[$cat['id']] = $catId;
            }
        }
    }

    // 2. Đồng bộ sản phẩm và gói
    if (!empty($products)) {
        if ($CMSNT->site('debug_api_suppliers') == 1) {
            echo '<b style="color:magenta;">PRODUCTS</b> - ' . count($products) . ' sản phẩm<br>';
        }

        foreach ($products as $prod) {
            // Xử lý multi-category: API trả về categories array
            $localCatIds = [];

            // Ưu tiên categories array từ API
            if (!empty($prod['categories']) && is_array($prod['categories'])) {
                foreach ($prod['categories'] as $apiCat) {
                    $apiCatId = $apiCat['id'] ?? 0;
                    if ($apiCatId && isset($categoryMap[$apiCatId])) {
                        $localCatIds[] = $categoryMap[$apiCatId];
                    }
                }
            }

            // Fallback: single category từ API
            if (empty($localCatIds)) {
                $apiCatId = $prod['category']['id'] ?? ($prod['category_id'] ?? 0);
                if ($apiCatId && isset($categoryMap[$apiCatId])) {
                    $localCatIds[] = $categoryMap[$apiCatId];
                }
            }

            $productId = syncProduct($CMSNT, $supplier, $prod, $localCatIds);

            if ($productId && isset($prod['plans']) && is_array($prod['plans'])) {
                foreach ($prod['plans'] as $plan) {
                    syncProductPlanWithProduct($CMSNT, $supplier, $plan, $productId, $prod['name']);
                }
            }
        }
    }

    return true; // Sync thành công
}

/**
 * Xóa các sản phẩm và gói sản phẩm không còn trong API sau 30 phút
 * Sử dụng ProductDeletionService để cascade xóa fields và images
 */
function cleanupStaleRecords($CMSNT, $supplier)
{
    $staleTime = date('Y-m-d H:i:s', strtotime('-30 minutes'));
    $deletionService = new ProductDeletionService($CMSNT);

    // Xóa product_plans cũ của supplier này (dựa vào api_sync_time)
    $stalePlans = $CMSNT->get_list_safe(
        " SELECT `id`, `name` FROM `product_plans` WHERE `supplier_id` = ? AND `api_sync_time` IS NOT NULL AND `api_sync_time` < ? ",
        [$supplier['id'], $staleTime]
    );

    if (!empty($stalePlans)) {
        // Thu thập plan IDs để bulk delete
        $planIds = array_column($stalePlans, 'id');

        // Sử dụng ProductDeletionService để xóa (cascade xóa fields + images)
        $result = $deletionService->deletePlans($planIds, true);

        if ($CMSNT->site('debug_api_suppliers') == 1) {
            if ($result['success']) {
                foreach ($stalePlans as $plan) {
                    echo '<span style="color:red;">✖ Plan:</span> ' . $plan['name'] . ' (stale > 30min)<br>';
                }
                echo '<b style="color:red;">CLEANUP</b> - Đã xóa ' . $result['deleted_plans'] . ' gói cũ';
                if ($result['deleted_fields'] > 0) {
                    echo ' + ' . $result['deleted_fields'] . ' trường';
                }
                echo '<br>';
            } else {
                echo '<b style="color:red;">ERROR</b> - Không thể xóa gói cũ: ' . $deletionService->getFirstError() . '<br>';
            }
        }
    }

    // Xóa products cũ của supplier này (dựa vào updated_at)
    // CHÚ Ý: Bỏ qua product nếu có plan thủ công (api_sync_time = NULL) để bảo vệ dữ liệu admin tạo
    $staleProducts = $CMSNT->get_list_safe(
        " SELECT `id`, `name` FROM `products` WHERE `supplier_id` = ? AND `updated_at` IS NOT NULL AND `updated_at` < ? ",
        [$supplier['id'], $staleTime]
    );

    if (!empty($staleProducts)) {
        $safeProductIds = [];
        $skippedProducts = [];

        foreach ($staleProducts as $product) {
            // Kiểm tra xem product có plan thủ công (api_sync_time = NULL) không
            $manualPlanCount = $CMSNT->num_rows_safe(
                "SELECT id FROM `product_plans` WHERE `product_id` = ? AND (`api_sync_time` IS NULL OR `supplier_id` IS NULL OR `supplier_id` = 0)",
                [$product['id']]
            );

            if ($manualPlanCount > 0) {
                // Có plan thủ công, BỎ QUA xóa product này
                $skippedProducts[] = $product;
            } else {
                // Không có plan thủ công, an toàn để xóa
                $safeProductIds[] = $product['id'];
            }
        }

        // Log các product bị bỏ qua (có plan thủ công)
        if ($CMSNT->site('debug_api_suppliers') == 1 && !empty($skippedProducts)) {
            foreach ($skippedProducts as $product) {
                echo '<span style="color:orange;">⊘ Product:</span> ' . $product['name'] . ' (có plan thủ công, bỏ qua)<br>';
            }
        }

        // Xóa các product an toàn (không có plan thủ công)
        if (!empty($safeProductIds)) {
            $result = $deletionService->deleteProducts($safeProductIds, true);

            if ($CMSNT->site('debug_api_suppliers') == 1) {
                if ($result['success']) {
                    foreach ($staleProducts as $product) {
                        if (in_array($product['id'], $safeProductIds)) {
                            echo '<span style="color:red;">✖ Product:</span> ' . $product['name'] . ' (stale > 30min)<br>';
                        }
                    }
                    echo '<b style="color:red;">CLEANUP</b> - Đã xóa ' . $result['deleted_products'] . ' sản phẩm';
                    if ($result['deleted_plans'] > 0) {
                        echo ' + ' . $result['deleted_plans'] . ' gói';
                    }
                    if ($result['deleted_fields'] > 0) {
                        echo ' + ' . $result['deleted_fields'] . ' trường';
                    }
                    echo '<br>';
                } else {
                    echo '<b style="color:red;">ERROR</b> - Không thể xóa sản phẩm cũ: ' . $deletionService->getFirstError() . '<br>';
                }
            }
        }
    }

    // Xóa categories cũ của supplier này (dựa vào updated_at)
    $staleCategories = $CMSNT->get_list_safe(
        " SELECT `id`, `name` FROM `categories` WHERE `supplier_id` = ? AND `updated_at` IS NOT NULL AND `updated_at` < ? ",
        [$supplier['id'], $staleTime]
    );

    if (!empty($staleCategories)) {
        $deletedCatCount = 0;

        foreach ($staleCategories as $cat) {
            // Sử dụng ProductDeletionService để xóa category (deleteProducts = false để chỉ unlink products)
            $result = $deletionService->deleteCategory($cat['id'], true, false, false);

            if ($result['success']) {
                $deletedCatCount++;
                if ($CMSNT->site('debug_api_suppliers') == 1) {
                    echo '<span style="color:red;">✖ Category:</span> ' . $cat['name'] . ' (stale > 30min)<br>';
                }
            }
        }

        if ($CMSNT->site('debug_api_suppliers') == 1 && $deletedCatCount > 0) {
            echo '<b style="color:red;">CLEANUP</b> - Đã xóa ' . $deletedCatCount . ' chuyên mục cũ<br>';
        }
    }
}

/**
 * Đồng bộ tài khoản cho các đơn hàng pending từ API Supplier
 * Dành cho các gói thủ công (is_instant = 0)
 * 
 * @param DB $CMSNT Database instance
 * @param array $supplier Thông tin supplier
 * @param object $api API instance (ShopkeyApi, etc.)
 */
function syncPendingOrders($CMSNT, $supplier, $api)
{
    // Chỉ xử lý nếu API hỗ trợ getOrderDetail
    if (!method_exists($api, 'getOrderDetail')) {
        return;
    }

    // Lấy các đơn hàng pending có api_trans_id từ supplier này
    $pendingOrders = $CMSNT->get_list_safe(
        " SELECT * FROM `product_orders` 
          WHERE `supplier_id` = ? 
          AND `status` = 'pending' 
          AND `api_trans_id` IS NOT NULL 
          AND `api_trans_id` != '' 
          AND `created_at` > DATE_SUB(NOW(), INTERVAL 7 DAY)
          ORDER BY `id` ASC 
          LIMIT 50 ",
        [$supplier['id']]
    );

    if (empty($pendingOrders)) {
        return;
    }

    if ($CMSNT->site('debug_api_suppliers') == 1) {
        echo '<b style="color:cyan;">PENDING ORDERS</b> - Đang xử lý ' . count($pendingOrders) . ' đơn pending<br>';
    }

    foreach ($pendingOrders as $order) {
        $api_trans_id = $order['api_trans_id'];

        // Gọi API lấy trạng thái đơn hàng
        $orderDetail = $api->getOrderDetail($api_trans_id);

        if (!$orderDetail) {
            if ($CMSNT->site('debug_api_suppliers') == 1) {
                echo '<span style="color:gray;">⊘ Order #' . $order['trans_id'] . '</span> - Không lấy được thông tin từ API<br>';
            }
            continue;
        }

        // Kiểm tra trạng thái đã completed chưa
        // API trả về format: { order: { status: ... }, delivery: { items: [...] } }
        $apiStatus = $orderDetail['order']['status'] ?? ($orderDetail['status'] ?? '');

        if ($apiStatus !== 'completed') {
            if ($CMSNT->site('debug_api_suppliers') == 1) {
                echo '<span style="color:yellow;">⏳ Order #' . $order['trans_id'] . '</span> - API status: ' . $apiStatus . ' (chưa hoàn thành)<br>';
            }
            continue;
        }

        // Lấy danh sách tài khoản từ delivery.items
        $deliveryItems = $orderDetail['delivery']['items'] ?? [];

        if (empty($deliveryItems)) {
            if ($CMSNT->site('debug_api_suppliers') == 1) {
                echo '<span style="color:orange;">⚠ Order #' . $order['trans_id'] . '</span> - Đơn completed nhưng không có delivery items<br>';
            }
            continue;
        }

        // Chuyển đổi delivery items thành chuỗi để lưu vào delivery_content
        // (giống như đơn thủ công tạo từ hệ thống)
        $deliveryContentArray = [];
        foreach ($deliveryItems as $account) {
            $account_value = is_array($account) ? ($account['content'] ?? json_encode($account)) : $account;
            $deliveryContentArray[] = check_string($account_value);
        }
        $deliveryContent = implode("\r\n", $deliveryContentArray);

        // Cập nhật trạng thái đơn hàng và lưu tài khoản vào delivery_content
        $CMSNT->update('product_orders', [
            'status' => 'completed',
            'delivery_content' => $deliveryContent,
            'updated_at' => gettime()
        ], " `id` = ? ", [$order['id']]);

        // === Gửi email thông báo đơn hàng hoàn thành ===
        try {
            // Lấy thông tin user để gửi email
            $orderUser = $CMSNT->get_row_safe("SELECT `id`, `username`, `email` FROM `users` WHERE `id` = ?", [$order['user_id']]);

            if ($orderUser && !empty($orderUser['email'])) {
                require_once __DIR__ . '/../../libs/SMTPMailer.php';
                $mailer = new SMTPMailer($CMSNT);

                // Chuẩn bị dữ liệu đơn hàng
                $orderData = [
                    'id' => $order['id'],
                    'trans_id' => $order['trans_id'],
                    'product_name' => $order['product_name'] ?? '',
                    'plan_name' => $order['plan_name'] ?? '',
                    'quantity' => $order['quantity'] ?? 1,
                    'total' => $order['total'] ?? 0,
                    'delivery_content' => $deliveryContent
                ];

                $mailer->queueOrderCompletedEmail($orderData, $orderUser);

                if ($CMSNT->site('debug_api_suppliers') == 1) {
                    echo '<span style="color:blue;">📧 Email queued for ' . $orderUser['email'] . '</span><br>';
                }
            }
        } catch (Exception $e) {
            // Silent fail - không làm gián đoạn sync
            if ($CMSNT->site('debug_api_suppliers') == 1) {
                echo '<span style="color:orange;">⚠️ Email error: ' . $e->getMessage() . '</span><br>';
            }
        }

        if ($CMSNT->site('debug_api_suppliers') == 1) {
            echo '<span style="color:green;">✓ Order #' . $order['trans_id'] . '</span> - Đã nhận ' . count($deliveryItems) . ' tài khoản từ API (lưu vào delivery_content)<br>';
        }
    }
}

// =====================================================
// MAIN SYNC LOGIC
// =====================================================

// Lặp qua tất cả suppliers của type này đang hoạt động
foreach ($CMSNT->get_list_safe(" SELECT * FROM `suppliers` WHERE `status` = ? AND `type` = ? ", [1, $SUPPLIER_TYPE]) as $supplier) {

    // Tạo API instance từ Factory
    $api = SupplierApiFactory::create($supplier);
    if (!$api) {
        if ($CMSNT->site('debug_api_suppliers') == 1) {
            echo '<b style="color:red;">ERROR</b> - Không thể tạo API instance cho supplier #' . $supplier['id'] . '<br>';
        }
        continue;
    }

    // CẬP NHẬT SỐ DƯ API
    $balance = $api->getBalance();
    if ($balance !== null) {
        $CMSNT->update('suppliers', [
            'price' => check_string(format_currency($balance)),
            'update_gettime' => gettime()
        ], " `id` = ? ", [$supplier['id']]);

        if ($CMSNT->site('debug_api_suppliers') == 1) {
            echo '<b style="color:blue;">BALANCE</b> - #' . $supplier['id'] . ' (' . $supplier['domain'] . '): ' . format_currency($balance) . '<br>';
        }
    } else {
        $CMSNT->update('suppliers', [
            'price' => __('Không thể kết nối'),
            'update_gettime' => gettime()
        ], " `id` = ? ", [$supplier['id']]);

        if ($CMSNT->site('debug_api_suppliers') == 1) {
            echo '<b style="color:red;">ERROR</b> - Không thể lấy số dư từ supplier #' . $supplier['id'] . '<br>';
        }
    }

    // KIỂM TRA CHẾ ĐỘ ĐỒNG BỘ
    $isFullSync = isset($supplier['child']) && $supplier['child'] == 1;
    $syncSuccess = false; // Flag để track sync thành công

    if ($isFullSync && $supplier['sync_category'] == 'ON') {
        // MODE: Đồng bộ cấu trúc đầy đủ
        if ($CMSNT->site('debug_api_suppliers') == 1) {
            echo '<b style="color:purple;">MODE</b> - Full sync (child = ON)<br>';
        }

        // syncFullStructure sẽ cập nhật updated_at nếu thành công
        $syncSuccess = syncFullStructure($CMSNT, $supplier, $api);
    } else {
        // MODE: Chỉ đồng bộ product_plans
        if ($CMSNT->site('debug_api_suppliers') == 1) {
            echo '<b style="color:purple;">MODE</b> - Plans only (child = OFF)<br>';
        }

        $products = $api->getProducts();

        if ($products && is_array($products) && count($products) > 0) {
            $syncSuccess = true; // API trả về dữ liệu, sync thành công

            if ($CMSNT->site('debug_api_suppliers') == 1) {
                echo '<b style="color:purple;">SYNC</b> - ' . count($products) . ' gói từ ' . $supplier['domain'] . '<br>';
            }

            foreach ($products as $product) {
                $api_product = [
                    'id' => $product['id'],
                    'name' => $product['name'],
                    'price' => $product['price'],
                    'amount' => $product['stock'] ?? 0,
                    'short_desc' => $product['description'] ?? '',
                    'description' => $product['description'] ?? '',
                    'is_instant' => $product['is_instant'] ?? true,
                    'duration_type' => $product['duration_type'] ?? 'lifetime',
                    'duration_value' => $product['duration_value'] ?? null,
                    'fields' => $product['fields'] ?? []
                ];
                syncProductPlan($CMSNT, $supplier, $api_product);
            }
        } else {
            // API không trả về dữ liệu (sập hoặc lỗi), KHÔNG chạy cleanup
            if ($CMSNT->site('debug_api_suppliers') == 1) {
                echo '<b style="color:orange;">WARNING</b> - API không trả về dữ liệu, bỏ qua cleanup để tránh xóa nhầm<br>';
            }
        }
    }

    // XÓA CÁC BẢN GHI CŨ KHÔNG CÒN TRONG API
    // CHỈ chạy cleanup nếu sync thành công, tránh xóa nhầm khi API sập
    if ($syncSuccess) {
        cleanupStaleRecords($CMSNT, $supplier);
    } else {
        if ($CMSNT->site('debug_api_suppliers') == 1) {
            echo '<b style="color:gray;">SKIP CLEANUP</b> - Sync không thành công, bỏ qua cleanup<br>';
        }
    }

    // ĐỒNG BỘ TÀI KHOẢN CHO ĐƠN HÀNG PENDING (gói thủ công)
    syncPendingOrders($CMSNT, $supplier, $api);
}

if ($CMSNT->site('debug_api_suppliers') == 1) {
    echo '<br><b style="color:green;">DONE</b> - Hoàn thành đồng bộ ' . $SUPPLIER_TYPE . '<br>';
}
