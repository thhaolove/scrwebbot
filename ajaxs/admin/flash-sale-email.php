<?php

/**
 * AJAX Handler: Send Flash Sale Email to Favorited Users
 */

define("IN_SITE", true);
require_once(__DIR__ . "/../../libs/db.php");
require_once(__DIR__ . "/../../libs/lang.php");
require_once(__DIR__ . "/../../libs/helper.php");
require_once(__DIR__ . "/../../config.php");
require_once(__DIR__ . '/../../models/is_admin.php');

header('Content-Type: application/json');

// Kiểm tra CSRF token cho tất cả request
checkCSRFAjax();

// Check permission
if (checkPermission($getUser['admin'], 'edit_flash_sale') != true) {
    echo json_encode(['success' => false, 'message' => __('Bạn không có quyền sử dụng tính năng này')]);
    exit;
}
if ($CMSNT->site('status_demo') != 0) {
    die(json_encode(['status' => 'error', 'msg' => __('Chức năng này không thể sử dụng trên website demo')]));
}
// Get action type (preview or send)
$action = isset($_POST['action']) ? $_POST['action'] : 'send';

// Validate flash_sale_id
$flash_sale_id = isset($_POST['flash_sale_id']) ? (int)$_POST['flash_sale_id'] : 0;
if ($flash_sale_id <= 0) {
    echo json_encode(['success' => false, 'message' => __('ID Flash Sale không hợp lệ')]);
    exit;
}

require_once(__DIR__ . '/../../libs/database/flashsale.php');

$FlashSaleHandler = new FlashSaleHandler();

// Get Flash Sale info
$flash_sale = $FlashSaleHandler->getFlashSaleById($flash_sale_id);
if (!$flash_sale) {
    echo json_encode(['success' => false, 'message' => __('Flash Sale không tồn tại')]);
    exit;
}

// Get Flash Sale items
$flash_sale_items = $FlashSaleHandler->getFlashSaleItems($flash_sale_id);
if (empty($flash_sale_items)) {
    echo json_encode(['success' => false, 'message' => __('Flash Sale không có sản phẩm nào')]);
    exit;
}

// Get product IDs from items
$flash_sale_product_ids = [];
$plan_ids = [];

foreach ($flash_sale_items as $item) {
    if (!empty($item['plan_id'])) {
        $plan_ids[] = (int)$item['plan_id'];
    }
    if (!empty($item['product_id'])) {
        $flash_sale_product_ids[] = (int)$item['product_id'];
    }
}

// Get product_ids from plans
if (!empty($plan_ids)) {
    $plan_ids_str = implode(',', $plan_ids);
    $plans_with_products = $CMSNT->get_list_safe(
        "SELECT DISTINCT `product_id` FROM `product_plans` WHERE `id` IN ($plan_ids_str) AND `product_id` IS NOT NULL",
        []
    );
    foreach ($plans_with_products as $p) {
        if (!empty($p['product_id'])) {
            $flash_sale_product_ids[] = (int)$p['product_id'];
        }
    }
}

$flash_sale_product_ids = array_unique($flash_sale_product_ids);

if (empty($flash_sale_product_ids)) {
    echo json_encode(['success' => false, 'message' => __('Không tìm thấy sản phẩm trong Flash Sale')]);
    exit;
}

// Find users who have favorited these products
$product_ids_str = implode(',', array_map('intval', $flash_sale_product_ids));
$favorites = $CMSNT->get_list_safe(
    "SELECT pf.`user_id`, pf.`product_id`, u.`email`, u.`username`, p.`name` as product_name, p.`slug` as product_slug
     FROM `product_favorites` pf
     INNER JOIN `users` u ON pf.`user_id` = u.`id`
     INNER JOIN `products` p ON pf.`product_id` = p.`id`
     WHERE pf.`product_id` IN ($product_ids_str)
     AND u.`email` IS NOT NULL AND u.`email` != ''",
    []
);

if (empty($favorites)) {
    echo json_encode(['success' => false, 'message' => __('Không có user nào yêu thích các sản phẩm trong Flash Sale này')]);
    exit;
}

// Group by user
$users_to_notify = [];
foreach ($favorites as $fav) {
    $user_id = $fav['user_id'];
    if (!isset($users_to_notify[$user_id])) {
        $users_to_notify[$user_id] = [
            'user_id' => $user_id,
            'email' => $fav['email'],
            'username' => $fav['username'],
            'products' => []
        ];
    }
    $users_to_notify[$user_id]['products'][] = [
        'name' => $fav['product_name'],
        'slug' => $fav['product_slug']
    ];
}

// ========== PREVIEW MODE ==========
if ($action === 'preview') {
    $users_list = [];
    foreach ($users_to_notify as $user_data) {
        $product_names = array_column($user_data['products'], 'name');
        $users_list[] = [
            'user_id' => $user_data['user_id'],
            'username' => $user_data['username'],
            'email' => $user_data['email'],
            'products' => implode(', ', $product_names),
            'product_count' => count($product_names)
        ];
    }

    echo json_encode([
        'success' => true,
        'total_users' => count($users_list),
        'flash_sale_name' => $flash_sale['name'],
        'users' => $users_list
    ]);
    exit;
}

// ========== SEND MODE ==========
// Check SMTP enabled
if ($CMSNT->site('smtp_status') != 1) {
    echo json_encode(['success' => false, 'message' => __('SMTP chưa được bật. Vui lòng bật SMTP trong cài đặt.')]);
    exit;
}

require_once(__DIR__ . '/../../libs/SMTPMailer.php');

// Initialize mailer
$mailer = new SMTPMailer($CMSNT);

// Get email template
$template = $mailer->getTemplate(SMTPMailer::TEMPLATE_FLASH_SALE_FAVORITE);
if (empty($template['subject'])) {
    echo json_encode(['success' => false, 'message' => __('Chưa cấu hình template email Flash Sale. Vui lòng vào Settings > Mail Template để cấu hình.')]);
    exit;
}

// Prepare discount info
$discount_info = ($flash_sale['discount_type'] == 'percentage')
    ? __('Giảm') . ' ' . $flash_sale['discount_value'] . '%'
    : __('Giảm') . ' ' . format_currency($flash_sale['discount_value']);

// Queue emails for each user
$queued_count = 0;
$failed_count = 0;

foreach ($users_to_notify as $user_data) {
    // For multiple products, list them all
    $product_names = array_column($user_data['products'], 'name');
    $product_name_str = implode(', ', $product_names);

    // Use first product link
    $first_product_slug = $user_data['products'][0]['slug'] ?? '';
    $product_link = base_url('product/' . $first_product_slug);

    $variables = [
        '{username}' => $user_data['username'],
        '{flash_sale_name}' => $flash_sale['name'],
        '{product_name}' => $product_name_str,
        '{discount_info}' => $discount_info,
        '{start_time}' => date('d/m/Y H:i', strtotime($flash_sale['start_time'])),
        '{end_time}' => date('d/m/Y H:i', strtotime($flash_sale['end_time'])),
        '{product_link}' => $product_link,
        '{domain}' => $_SERVER['SERVER_NAME'] ?? '',
        '{title}' => $CMSNT->site('title'),
        '{time}' => gettime()
    ];

    $subject = $mailer->parseTemplate($template['subject'], $variables);
    $body = $mailer->parseTemplate($template['content'], $variables);

    // Queue email with priority 2
    $queueResult = $mailer->queueEmail(
        $user_data['email'],
        $user_data['username'],
        $subject,
        $body,
        2,
        [
            'type' => 'flash_sale_favorite',
            'flash_sale_id' => $flash_sale_id,
            'products' => $product_name_str
        ]
    );

    if ($queueResult) {
        $queued_count++;
    } else {
        $failed_count++;
    }
}

// Log action
$CMSNT->insert("logs", [
    'user_id'       => $getUser['id'],
    'ip'            => myip(),
    'device'        => getUserAgent(),
    'createdate'    => gettime(),
    'action'        => "Flash Sale Email: Queued $queued_count emails for Flash Sale #{$flash_sale_id} ({$flash_sale['name']})"
]);

if ($queued_count > 0) {
    echo json_encode([
        'success' => true,
        'message' => sprintf(__('Đã thêm %d email vào hàng đợi'), $queued_count),
        'queued' => $queued_count,
        'failed' => $failed_count
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => __('Không thể thêm email vào hàng đợi. Vui lòng kiểm tra cấu hình SMTP.'),
        'queued' => $queued_count,
        'failed' => $failed_count
    ]);
}
