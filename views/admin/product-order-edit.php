<?php if (!defined('IN_SITE')) {
    die('The Request Not Found');
}
$body = [
    'title' => __('Chi tiết đơn hàng sản phẩm') . ' | ' . $CMSNT->site('title'),
    'desc'   => $CMSNT->site('description'),
    'keyword' => $CMSNT->site('keywords')
];
$body['header'] = '
<style>
/* Tăng độ đậm cho tất cả border transparent */
.card.border-primary-transparent {
    border: 1px solid rgba(var(--primary-rgb), 0.4) !important;
}
.card.border-info-transparent {
    border: 1px solid rgba(var(--info-rgb), 0.4) !important;
}
.card.border-success-transparent {
    border: 1px solid rgba(var(--success-rgb), 0.4) !important;
}
.card.border-warning-transparent {
    border: 1px solid rgba(var(--warning-rgb), 0.4) !important;
}
</style>
';
$body['footer'] = '';
require_once(__DIR__ . '/../../models/is_admin.php');
require_once(__DIR__ . '/header.php');
require_once(__DIR__ . '/sidebar.php');
if (checkPermission($getUser['admin'], 'view_order_product') != true) {
    $role_name = getRoleName('view_order_product');
    die('<script type="text/javascript">if(!alert("' . sprintf(__('Bạn không có quyền %s'), $role_name) . '")){window.history.back();}</script>');
}

$id = isset($_GET['id']) ? validate_int($_GET['id'], 1) : 0;
if (!$id) {
    die('<script type="text/javascript">if(!alert("' . __('ID không hợp lệ') . '")){window.history.back();}</script>');
}

$order = $CMSNT->get_row_safe("
    SELECT po.*, 
           p.`name` as product_name, 
           p.`image` as product_image,
           pp.`name` as plan_name,
           pp.`duration_type`,
           pp.`duration_value`,
           pp.`is_instant` as plan_is_instant,
           u.`username` as user_username,
           u.`money` as user_money,
           u.`email` as user_email,
           cu.`username` as commission_username
    FROM `product_orders` po 
    LEFT JOIN `products` p ON po.`product_id` = p.`id` 
    LEFT JOIN `product_plans` pp ON po.`plan_id` = pp.`id`
    LEFT JOIN `users` u ON po.`user_id` = u.`id`
    LEFT JOIN `users` cu ON po.`commission_user_id` = cu.`id`
    WHERE po.`id` = ?
", [$id]);

if (!$order) {
    die('<script type="text/javascript">if(!alert("' . __('Đơn hàng không tồn tại') . '")){window.history.back();}</script>');
}

// Lấy thông tin kho hàng nếu đơn hàng có stock_id (gói giao ngay)
// Chỉ hiển thị các tài khoản có order_id trùng với id của đơn hàng này
$stock_list = [];
if (isset($order['plan_is_instant']) && (int)$order['plan_is_instant'] == 1) {
    // Chỉ lấy các stock items có order_id trùng với id của đơn hàng này
    $stock_list = $CMSNT->get_list_safe("
        SELECT `id`, `plan_id`, `stock_value`, `status`, `created_at`, `updated_at`, `order_id`
        FROM `product_stock`
        WHERE `order_id` = ?
        ORDER BY `id` ASC
    ", [$id]);
}

// Giữ biến stock_info để backward compatibility (chỉ lấy stock đầu tiên)
$stock_info = !empty($stock_list) ? $stock_list[0] : null;

// Parse fields_data nếu có
$fields_data = [];
if (!empty($order['fields_data'])) {
    $fields_data = json_decode($order['fields_data'], true);
    if (!is_array($fields_data)) {
        $fields_data = [];
    }
}

// Lấy danh sách fields của plan để hiển thị
$plan_fields = [];
if ($order['plan_id'] > 0) {
    $plan_fields = $CMSNT->get_list_safe("
        SELECT * FROM `product_fields` 
        WHERE `plan_id` = ? 
        ORDER BY `sort_order` ASC
    ", [$order['plan_id']]);
}

// Lấy thông tin Flash Sale nếu có
$flash_sale_purchase = $CMSNT->get_row_safe("
    SELECT fsp.*, fs.name as flash_sale_name, fs.discount_type, fs.discount_value, fs.start_time, fs.end_time
    FROM `flash_sale_purchases` fsp 
    LEFT JOIN `flash_sales` fs ON fsp.flash_sale_id = fs.id 
    WHERE fsp.order_id = ?
", [$id]);
$has_flash_sale = !empty($flash_sale_purchase);

if (isset($_POST['submit'])) {
    // Kiểm tra CSRF token
    checkCSRF();

    if (checkPermission($getUser['admin'], 'edit_orders_product') != true) {
        $role_name = getRoleName('edit_orders_product');
        die('<script type="text/javascript">if(!alert("' . sprintf(__('Bạn không có quyền %s'), $role_name) . '")){window.history.back();}</script>');
    }
    if ($CMSNT->site('status_demo') != 0) {
        die('<script type="text/javascript">if(!alert("' . __('Không được dùng chức năng này vì đây là trang web demo.') . '")){window.history.back().location.reload();}</script>');
    }

    // Kiểm tra nếu đơn hàng đã bị khóa (đã hủy)
    $is_locked = ($order['status'] == 'cancelled' || $order['status'] == 'cancelled_no_refund');
    if ($is_locked) {
        // Nếu đơn hàng đã bị khóa, chỉ cho phép cập nhật ghi chú và lý do hủy (nếu có)
        // Không cho phép thay đổi status
        $status = $order['status'];
    } else {
        $status = validate_string($_POST['status'], 20);

        if ($status === false || !in_array($status, ['pending', 'processing', 'completed', 'cancelled', 'cancelled_no_refund'])) {
            die('<script type="text/javascript">if(!alert("' . __('Trạng thái không hợp lệ') . '")){window.history.back();}</script>');
        }
    }

    $note = isset($_POST['note']) ? trim($_POST['note']) : '';
    $delivery_content = isset($_POST['delivery_content']) ? validate_string($_POST['delivery_content'], 10000000000) : '';

    // Xử lý ngày hết hạn tùy chỉnh
    $custom_expiry_date = null;
    if (!empty($_POST['custom_expiry_date'])) {
        $custom_expiry_date = date('Y-m-d H:i:s', strtotime($_POST['custom_expiry_date']));
    }

    // Kiểm tra nếu hủy đơn hàng
    $is_cancelling = !$is_locked && ($status == 'cancelled' || $status == 'cancelled_no_refund')
        && $order['status'] != 'cancelled' && $order['status'] != 'cancelled_no_refund';

    if ($is_cancelling) {
        $User = new users();

        // Chỉ hoàn tiền khi chọn "Đã hủy" (cancelled), không hoàn tiền khi chọn "Hủy không hoàn tiền" (cancelled_no_refund)
        if ($status == 'cancelled' && $order['total_price'] > 0) {
            // Kiểm tra quyền hoàn tiền
            if (checkPermission($getUser['admin'], 'refund_orders_product') != true) {
                $role_name = getRoleName('refund_orders_product');
                die('<script type="text/javascript">if(!alert("' . sprintf(__('Bạn không có quyền %s'), $role_name) . '")){window.history.back();}</script>');
            }
            $refund_amount = $order['sale_price'] > 0 && $order['sale_price'] < $order['total_price'] ? $order['sale_price'] : $order['total_price'];
            $isRefund = $User->RefundCredits(
                $order['user_id'],
                $refund_amount,
                '[Admin] ' . sprintf(__("Hoàn tiền đơn hàng sản phẩm #%s (Hủy đơn hàng)"), $order['trans_id']),
                'PRODUCT_ORDER_' . $order['trans_id']
            );

            if (!$isRefund) {
                die('<script type="text/javascript">if(!alert("' . __('Hoàn tiền thất bại! Vui lòng kiểm tra lại số dư tài khoản.') . '")){window.history.back();}</script>');
            }
        }

        // Thu hồi hoa hồng nếu có (áp dụng cho cả 2 trường hợp: cancelled và cancelled_no_refund)
        if (isset($order['commission_amount']) && $order['commission_amount'] > 0 && isset($order['commission_user_id']) && $order['commission_user_id'] > 0) {
            // Sử dụng AffiliateHandler để thu hồi hoa hồng từ ref_price
            require_once(__DIR__ . '/../../libs/database/affiliate.php');
            $AffiliateHandler = new AffiliateHandler();

            $cancel_type = $status == 'cancelled' ? __('Hủy đơn hàng') : __('Hủy không hoàn tiền');
            $reason = '[Admin] ' . sprintf(__("Thu hồi hoa hồng đơn hàng sản phẩm #%s (%s)"), $order['trans_id'], $cancel_type);

            $isDeduct = $AffiliateHandler->removeCommission(
                $order['commission_user_id'],
                $order['commission_amount'],
                $reason,
                'refund' // type: refund khi thu hồi do hủy đơn
            );

            // Ghi chú: Nếu số dư hoa hồng không đủ, vẫn cho phép hủy đơn hàng
            // nhưng ghi log cảnh báo
            if (!$isDeduct) {
                $CMSNT->insert("logs", [
                    'user_id'       => 0,
                    'ip'            => myip(),
                    'device'        => getUserAgent(),
                    'createdate'    => gettime(),
                    'action'        => "[Cảnh báo] Không thể thu hồi hoa hồng " . format_currency($order['commission_amount']) . " từ User ID " . $order['commission_user_id'] . " cho đơn hàng " . $order['trans_id'] . " (Có thể do số dư hoa hồng không đủ)"
                ]);
            }
        }
    }

    $update_data = [
        'status' => $status,
        'note' => $note,
        'delivery_content' => $delivery_content,
        'custom_expiry_date' => $custom_expiry_date,
        'updated_at' => gettime()
    ];

    // Lưu lý do hủy cho cả hai trường hợp: hủy có hoàn tiền và hủy không hoàn tiền
    if (($status == 'cancelled' || $status == 'cancelled_no_refund') && !empty($_POST['reason'])) {
        $update_data['reason'] = trim($_POST['reason']);
    }

    // Lưu trạng thái cũ để kiểm tra thay đổi
    $old_status = $order['status'];

    $isUpdate = $CMSNT->update("product_orders", $update_data, " `id` = ?", [$id]);

    if ($isUpdate) {
        // Cập nhật số lượng đã bán khi thay đổi status sang completed
        if ($status === 'completed' && $old_status !== 'completed') {
            // Tăng số sold
            $CMSNT->cong('products', 'sold', $order['quantity'], "`id` = ?", [$order['product_id']]);

            // Set completed_at nếu chưa có (chỉ set lần đầu)
            if (empty($order['completed_at'])) {
                $CMSNT->update('product_orders', ['completed_at' => gettime()], "`id` = ?", [$id]);
            }

            // === Gửi email thông báo đơn hàng hoàn thành ===
            try {
                // Lấy thông tin user để gửi email
                $orderUser = $CMSNT->get_row_safe("SELECT `id`, `username`, `email` FROM `users` WHERE `id` = ?", [$order['user_id']]);

                if ($orderUser && !empty($orderUser['email'])) {
                    require_once __DIR__ . '/../../libs/SMTPMailer.php';
                    $mailer = new SMTPMailer($CMSNT);

                    // Lấy lại order mới nhất để có delivery_content
                    $updatedOrder = $CMSNT->get_row_safe("SELECT `delivery_content` FROM `product_orders` WHERE `id` = ?", [$id]);

                    // Chuẩn bị dữ liệu đơn hàng
                    $orderData = [
                        'id' => $order['id'],
                        'trans_id' => $order['trans_id'],
                        'product_name' => $order['product_name'] ?? '',
                        'plan_name' => $order['plan_name'] ?? '',
                        'quantity' => $order['quantity'] ?? 1,
                        'total' => $order['total'] ?? $order['total_price'] ?? 0,
                        'delivery_content' => $delivery_content ?? $updatedOrder['delivery_content'] ?? ''
                    ];

                    $mailer->queueOrderCompletedEmail($orderData, $orderUser);
                }
            } catch (Exception $e) {
                // Silent fail - không làm gián đoạn quá trình cập nhật
                error_log('Order completed email error: ' . $e->getMessage());
            }
        }
        // Giảm số sold khi chuyển từ completed sang trạng thái khác (hủy, etc.)
        elseif ($old_status === 'completed' && $status !== 'completed') {
            // Giảm số sold (đảm bảo không âm)
            $product_data = $CMSNT->get_row_safe("SELECT `sold` FROM `products` WHERE `id` = ?", [$order['product_id']]);
            if ($product_data) {
                $new_sold = max(0, (int)$product_data['sold'] - (int)$order['quantity']);
                $CMSNT->update('products', ['sold' => $new_sold], "`id` = ?", [$order['product_id']]);
            }
        }

        $CMSNT->insert("logs", [
            'user_id'       => $getUser['id'],
            'ip'            => myip(),
            'device'        => getUserAgent(),
            'createdate'    => gettime(),
            'action'        => "Update Product Order ID " . $id . " (" . $order['trans_id'] . ")."
        ]);
        /** NOTE ACTION */
        $my_text = $CMSNT->site('noti_action');
        $my_text = str_replace('{domain}', $_SERVER['SERVER_NAME'], $my_text);
        $my_text = str_replace('{username}', $getUser['username'], $my_text);
        $my_text = str_replace('{action}', "Update Product Order ID " . $id . " (" . $order['trans_id'] . ").", $my_text);
        $my_text = str_replace('{ip}', myip(), $my_text);
        $my_text = str_replace('{time}', gettime(), $my_text);
        sendMessAdmin($my_text);
        die('<script type="text/javascript">if(!alert("' . __('Cập nhật đơn hàng thành công!') . '")){location.href = "' . base_url_admin('product-order-edit&id=' . $id) . '";}  </script>');
    } else {
        die('<script type="text/javascript">if(!alert("' . __('Không có thay đổi nào!') . '")){window.history.back();}</script>');
    }
}
?>

<div class="main-content app-content">
    <div class="container-fluid">
        <!-- Tiêu đề trang -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <div>
                <h1 class="page-name fw-semibold fs-18 mb-0">
                    <i class="fa-solid fa-receipt me-1"></i><?= __('Chi tiết đơn hàng'); ?> <strong class="text-danger">#<?= $order['trans_id']; ?></strong>
                </h1>
            </div>
            <div class="ms-md-1 ms-0">
                <a href="<?= base_url_admin('product-orders'); ?>" class="btn btn-secondary btn-sm">
                    <i class="fa-solid fa-arrow-left me-1"></i><?= __('Quay lại'); ?>
                </a>
            </div>
        </div>

        <!-- Form chỉnh sửa đơn hàng -->
        <form action="" method="POST">
            <?php echo csrfField(); ?>
            <div class="row">
                <div class="col-xl-8">
                    <div class="card custom-card">
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <?= display_product_order_status($order['status'], true); ?>

                                    <?php if (isset($order['plan_is_instant']) && (int)$order['plan_is_instant'] == 1): ?>
                                        <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">
                                            <i class="fa-solid fa-bolt me-1"></i><?= __('Gói giao ngay'); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">
                                            <i class="fa-solid fa-shopping-cart me-1"></i><?= __('Gói đặt hàng'); ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if (!empty($order['duration_type']) && $order['duration_type'] != 'lifetime'): ?>
                                        <?php
                                        $duration_labels = [
                                            'day' => __('ngày'),
                                            'month' => __('tháng'),
                                            'year' => __('năm')
                                        ];
                                        $duration_icons = [
                                            'day' => 'fa-calendar-day',
                                            'month' => 'fa-calendar-alt',
                                            'year' => 'fa-calendar'
                                        ];
                                        $icon = $duration_icons[$order['duration_type']] ?? 'fa-clock';
                                        $label = $duration_labels[$order['duration_type']] ?? '';
                                        ?>
                                        <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">
                                            <i class="fa-solid <?= $icon; ?> me-1"></i>
                                            <?= $order['duration_value']; ?> <?= $label; ?>
                                        </span>
                                    <?php elseif (!empty($order['duration_type']) && $order['duration_type'] == 'lifetime'): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">
                                            <i class="fa-solid fa-infinity me-1"></i><?= __('Vĩnh viễn'); ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if (isset($order['quantity']) && $order['quantity'] > 0): ?>
                                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">
                                            <i class="fa-solid fa-boxes me-1"></i><?= __('Số lượng:'); ?> <?= number_format($order['quantity']); ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if (isset($order['order_source']) && $order['order_source'] === 'api'): ?>
                                        <span class="badge border" style="background-color: rgba(124, 58, 237, 0.1); color: #7c3aed; border-color: rgba(124, 58, 237, 0.3) !important;">
                                            <i class="fa-solid fa-code me-1"></i><?= __('Đặt qua API'); ?>
                                        </span>
                                    <?php elseif (isset($order['order_source']) && $order['order_source'] === 'web'): ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">
                                            <i class="fa-solid fa-globe me-1"></i><?= __('Đặt qua Web'); ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if ($has_flash_sale): ?>
                                        <span class="badge border" style="background-color: rgba(239, 68, 68, 0.1); color: #ef4444; border-color: rgba(239, 68, 68, 0.3) !important;">
                                            <i class="fa-solid fa-bolt me-1"></i><?= __('Flash Sale'); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Thông tin đơn hàng -->
                            <div class="card border-primary-transparent mb-3">
                                <div class="card-body">
                                    <h6 class="card-title mb-3">
                                        <i class="fa-solid fa-receipt me-2 text-primary"></i>
                                        <?= __('Thông tin đơn hàng'); ?>
                                    </h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="d-flex align-items-center">
                                                <i class="fa-solid fa-hashtag text-muted me-2" style="width: 20px;"></i>
                                                <div class="flex-grow-1">
                                                    <small class="text-muted d-block"><?= __('Mã đơn hàng'); ?></small>
                                                    <strong class="text-primary">#<?= $order['trans_id']; ?></strong>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex align-items-center">
                                                <i class="fa-solid fa-calendar text-muted me-2" style="width: 20px;"></i>
                                                <div class="flex-grow-1">
                                                    <small class="text-muted d-block"><?= __('Ngày tạo'); ?></small>
                                                    <strong><?= date('d/m/Y H:i:s', strtotime($order['created_at'])); ?></strong>
                                                </div>
                                            </div>
                                        </div>

                                        <?php
                                        // Tính thời gian hết hạn nếu đơn hàng đã hoàn thành và có thời hạn
                                        $expiry_date = null;
                                        $is_expired = false;
                                        $days_remaining = 0;
                                        $is_custom_expiry = false;

                                        // Ưu tiên sử dụng custom_expiry_date nếu có
                                        if (!empty($order['custom_expiry_date'])) {
                                            $expiry_date = strtotime($order['custom_expiry_date']);
                                            $is_custom_expiry = true;
                                            $is_expired = time() > $expiry_date;
                                            $days_remaining = ceil(($expiry_date - time()) / 86400);
                                        } elseif ($order['status'] == 'completed' && !empty($order['duration_type']) && $order['duration_type'] != 'lifetime') {
                                            // Lấy thời điểm hoàn thành (ưu tiên completed_at, fallback về updated_at)
                                            $completed_time = !empty($order['completed_at'])
                                                ? strtotime($order['completed_at'])
                                                : strtotime($order['updated_at']);

                                            // Tính thời gian hết hạn dựa trên duration_type và duration_value
                                            switch ($order['duration_type']) {
                                                case 'day':
                                                    $expiry_date = strtotime('+' . $order['duration_value'] . ' days', $completed_time);
                                                    break;
                                                case 'month':
                                                    $expiry_date = strtotime('+' . $order['duration_value'] . ' months', $completed_time);
                                                    break;
                                                case 'year':
                                                    $expiry_date = strtotime('+' . $order['duration_value'] . ' years', $completed_time);
                                                    break;
                                            }

                                            if ($expiry_date) {
                                                $is_expired = time() > $expiry_date;
                                                $days_remaining = ceil(($expiry_date - time()) / 86400);
                                            }
                                        }
                                        ?>

                                        <?php if ($expiry_date): ?>
                                            <div class="col-md-6">
                                                <div class="d-flex align-items-center">
                                                    <i class="fa-solid fa-clock text-muted me-2" style="width: 20px;"></i>
                                                    <div class="flex-grow-1">
                                                        <small class="text-muted d-block"><?= __('Ngày hết hạn'); ?></small>
                                                        <strong class="<?= $is_expired ? 'text-danger' : 'text-success'; ?>">
                                                            <?= date('d/m/Y H:i:s', $expiry_date); ?>
                                                        </strong>
                                                        <?php if ($is_custom_expiry): ?>
                                                            <span class="badge bg-info-transparent ms-2">
                                                                <i class="fa-solid fa-edit me-1"></i><?= __('Tùy chỉnh'); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                        <?php if ($is_expired): ?>
                                                            <span class="badge bg-danger-transparent ms-2">
                                                                <i class="fa-solid fa-exclamation-circle me-1"></i><?= __('Đã hết hạn'); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="d-flex align-items-center">
                                                    <i class="fa-solid fa-hourglass-half text-muted me-2" style="width: 20px;"></i>
                                                    <div class="flex-grow-1">
                                                        <small class="text-muted d-block"><?= __('Thời gian còn lại'); ?></small>
                                                        <?php if ($is_expired): ?>
                                                            <strong class="text-danger"><?= __('Đã hết hạn'); ?></strong>
                                                        <?php else: ?>
                                                            <strong class="<?= $days_remaining <= 7 ? 'text-warning' : 'text-success'; ?>">
                                                                <?php if ($days_remaining > 0): ?>
                                                                    <?= $days_remaining; ?> <?= __('ngày'); ?>
                                                                <?php else: ?>
                                                                    <?= __('< 1 ngày'); ?>
                                                                <?php endif; ?>
                                                            </strong>
                                                            <?php if ($days_remaining <= 7 && $days_remaining > 0): ?>
                                                                <span class="badge bg-warning-transparent ms-2">
                                                                    <i class="fa-solid fa-exclamation-triangle me-1"></i><?= __('Sắp hết hạn'); ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php elseif ($order['status'] == 'completed' && !empty($order['duration_type']) && $order['duration_type'] == 'lifetime'): ?>
                                            <div class="col-md-12">
                                                <div class="d-flex align-items-center">
                                                    <i class="fa-solid fa-infinity text-muted me-2" style="width: 20px;"></i>
                                                    <div class="flex-grow-1">
                                                        <small class="text-muted d-block"><?= __('Thời hạn sử dụng'); ?></small>
                                                        <strong class="text-success">
                                                            <i class="fa-solid fa-infinity me-1"></i><?= __('Vĩnh viễn'); ?>
                                                        </strong>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Thông tin khách hàng -->
                            <div class="card border-info-transparent mb-3">
                                <div class="card-body">
                                    <h6 class="card-title mb-3">
                                        <i class="fa-solid fa-user me-2 text-info"></i>
                                        <?= __('Thông tin khách hàng'); ?>
                                    </h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="d-flex align-items-center">
                                                <i class="fa-solid fa-user-circle text-muted me-2" style="width: 20px;"></i>
                                                <div class="flex-grow-1">
                                                    <small class="text-muted d-block"><?= __('Username'); ?></small>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <strong><?= htmlspecialchars($order['user_username']); ?></strong>
                                                        <a href="<?= base_url_admin('user-edit&id=' . $order['user_id']); ?>"
                                                            class="btn btn-sm btn-outline-primary"
                                                            title="<?= __('Xem chi tiết'); ?>">
                                                            <i class="fa-solid fa-external-link"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex align-items-center">
                                                <i class="fa-solid fa-envelope text-muted me-2" style="width: 20px;"></i>
                                                <div class="flex-grow-1">
                                                    <small class="text-muted d-block"><?= __('Email'); ?></small>
                                                    <strong><?= htmlspecialchars($order['user_email']); ?></strong>
                                                </div>
                                            </div>
                                        </div>
                                        <?php if (!empty($order['buyer_ip'])): ?>
                                            <div class="col-md-6">
                                                <div class="d-flex align-items-center">
                                                    <i class="fa-solid fa-network-wired text-muted me-2" style="width: 20px;"></i>
                                                    <div class="flex-grow-1">
                                                        <small class="text-muted d-block"><?= __('IP khi mua hàng'); ?></small>
                                                        <strong class="font-monospace"><?= htmlspecialchars($order['buyer_ip']); ?></strong>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($order['buyer_useragent'])): ?>
                                            <div class="col-md-6">
                                                <div class="d-flex align-items-start">
                                                    <i class="fa-solid fa-desktop text-muted me-2 mt-1" style="width: 20px;"></i>
                                                    <div class="flex-grow-1">
                                                        <small class="text-muted d-block"><?= __('Trình duyệt khi mua hàng'); ?></small>
                                                        <strong class="text-break" style="font-size: 0.875rem; word-break: break-word;"><?= htmlspecialchars($order['buyer_useragent']); ?></strong>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Thông tin sản phẩm -->
                            <div class="card border-success-transparent mb-3">
                                <div class="card-body">
                                    <h6 class="card-title mb-3">
                                        <i class="fa-solid fa-box me-2 text-success"></i>
                                        <?= __('Thông tin sản phẩm'); ?>
                                    </h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="d-flex align-items-start">
                                                <i class="fa-solid fa-cube text-muted me-2 mt-1" style="width: 20px;"></i>
                                                <div class="flex-grow-1">
                                                    <small class="text-muted d-block"><?= __('Sản phẩm'); ?></small>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <strong><?= htmlspecialchars(html_entity_decode($order['product_name'], ENT_QUOTES, 'UTF-8')); ?></strong>
                                                        <a href="<?= base_url_admin('product-edit&id=' . $order['product_id']); ?>"
                                                            class="btn btn-sm btn-outline-success"
                                                            title="<?= __('Xem chi tiết'); ?>">
                                                            <i class="fa-solid fa-external-link"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex align-items-start">
                                                <i class="fa-solid fa-gift text-muted me-2 mt-1" style="width: 20px;"></i>
                                                <div class="flex-grow-1">
                                                    <small class="text-muted d-block"><?= __('Gói'); ?></small>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <strong><?= htmlspecialchars(html_entity_decode($order['plan_name'], ENT_QUOTES, 'UTF-8')); ?></strong>
                                                        <a href="<?= base_url_admin('product-plans&product_id=' . $order['product_id']); ?>"
                                                            class="btn btn-sm btn-outline-warning"
                                                            title="<?= __('Chỉnh sửa gói'); ?>">
                                                            <i class="fa-solid fa-edit"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Thông tin giá -->
                            <div class="card border-warning-transparent mb-3">
                                <div class="card-body">
                                    <h6 class="card-title mb-3">
                                        <i class="fa-solid fa-money-bill-wave me-2 text-warning"></i>
                                        <?= __('Thông tin thanh toán'); ?>
                                    </h6>

                                    <?php
                                    // Kiểm tra xem đơn hàng có áp dụng mã giảm giá không
                                    $has_coupon = !empty($order['coupon_code']) && isset($order['discount_amount']) && $order['discount_amount'] > 0;
                                    $has_sale_price = $order['sale_price'] > 0 && $order['sale_price'] < $order['total_price'];

                                    // Sử dụng final_amount từ database (nếu có), nếu không thì tính toán
                                    $final_amount = isset($order['final_amount']) && $order['final_amount'] >= 0
                                        ? $order['final_amount']
                                        : ($has_sale_price ? $order['sale_price'] : $order['total_price']);

                                    if ($has_coupon):
                                        // Lấy thông tin chi tiết mã giảm giá
                                        $coupon_info = $CMSNT->get_row_safe("SELECT * FROM `coupons` WHERE `code` = ?", [$order['coupon_code']]);
                                    ?>

                                        <!-- Hiển thị chi tiết khi có mã giảm giá -->
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <div class="d-flex align-items-center">
                                                    <i class="fa-solid fa-coins text-muted me-2" style="width: 20px;"></i>
                                                    <div class="flex-grow-1">
                                                        <small class="text-muted d-block"><?= __('Giá gốc'); ?></small>
                                                        <strong class="text-danger fs-5"><?= format_currency($order['total_price']); ?></strong>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php if ($has_sale_price): ?>
                                                <div class="col-md-6">
                                                    <div class="d-flex align-items-center">
                                                        <i class="fa-solid fa-tag text-muted me-2" style="width: 20px;"></i>
                                                        <div class="flex-grow-1">
                                                            <small class="text-muted d-block"><?= __('Giá sau giảm sản phẩm'); ?></small>
                                                            <strong class="text-success fs-5"><?= format_currency($order['sale_price']); ?></strong>
                                                            <small class="text-muted text-decoration-line-through d-block"><?= format_currency($order['total_price']); ?></small>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="row g-3 mt-2 pt-3 border-top">
                                            <div class="col-12">
                                                <div class="alert alert-info mb-0">
                                                    <div class="d-flex align-items-center mb-2">
                                                        <i class="fa-solid fa-ticket text-info me-2"></i>
                                                        <strong><?= __('Mã giảm giá đã áp dụng'); ?></strong>
                                                    </div>
                                                    <div class="row g-2">
                                                        <div class="col-md-6">
                                                            <small class="text-muted d-block"><?= __('Mã giảm giá'); ?></small>
                                                            <strong class="text-primary"><?= htmlspecialchars($order['coupon_code']); ?></strong>
                                                            <?php if ($coupon_info): ?>
                                                                <a href="<?= base_url_admin('coupon-edit&id=' . $coupon_info['id']); ?>"
                                                                    class="btn btn-xs btn-outline-info ms-2"
                                                                    title="<?= __('Xem chi tiết mã giảm giá'); ?>">
                                                                    <i class="fa-solid fa-external-link"></i>
                                                                </a>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <small class="text-muted d-block"><?= __('Số tiền đã giảm'); ?></small>
                                                            <strong class="text-success">-<?= format_currency($order['discount_amount']); ?></strong>
                                                            <?php if ($has_sale_price): ?>
                                                                <small class="text-muted d-block"><?= __('(Tính từ giá sau giảm sản phẩm)'); ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                        <?php if ($coupon_info): ?>
                                                            <div class="col-md-6">
                                                                <small class="text-muted d-block"><?= __('Loại mã'); ?></small>
                                                                <span class="badge bg-<?= $coupon_info['type'] == 'percentage' ? 'info' : 'warning'; ?>">
                                                                    <?= $coupon_info['type'] == 'percentage' ? __('Phần trăm') : __('Số tiền cố định'); ?>
                                                                </span>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <small class="text-muted d-block"><?= __('Giá trị mã'); ?></small>
                                                                <strong>
                                                                    <?php if ($coupon_info['type'] == 'percentage'): ?>
                                                                        <?= $coupon_info['value']; ?>%
                                                                    <?php else: ?>
                                                                        <?= format_currency($coupon_info['value']); ?>
                                                                    <?php endif; ?>
                                                                </strong>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="d-flex align-items-center justify-content-between p-3 bg-primary-transparent rounded border border-primary border-opacity-25">
                                                    <span class="fw-semibold"><?= __('Thành tiền cuối cùng'); ?>:</span>
                                                    <strong class="text-primary fs-4"><?= format_currency($final_amount); ?></strong>
                                                </div>
                                            </div>

                                            <?php if ($has_flash_sale): ?>
                                                <div class="col-12">
                                                    <div class="alert mb-0" style="background-color: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.3);">
                                                        <div class="d-flex align-items-center mb-2">
                                                            <i class="fa-solid fa-bolt me-2" style="color: #ef4444;"></i>
                                                            <strong style="color: #ef4444;"><?= __('Thông tin Flash Sale'); ?></strong>
                                                        </div>
                                                        <div class="row g-2">
                                                            <div class="col-md-6">
                                                                <small class="text-muted d-block"><?= __('Tên Flash Sale'); ?></small>
                                                                <strong><?= htmlspecialchars($flash_sale_purchase['flash_sale_name'] ?? 'N/A'); ?></strong>
                                                                <a href="<?= base_url_admin('flash-sale-edit&id=' . $flash_sale_purchase['flash_sale_id']); ?>"
                                                                    class="btn btn-xs btn-outline-danger ms-2"
                                                                    title="<?= __('Xem chi tiết Flash Sale'); ?>">
                                                                    <i class="fa-solid fa-external-link"></i>
                                                                </a>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <small class="text-muted d-block"><?= __('Loại giảm giá'); ?></small>
                                                                <span class="badge bg-<?= $flash_sale_purchase['discount_type'] == 'percentage' ? 'info' : 'warning'; ?>">
                                                                    <?= $flash_sale_purchase['discount_type'] == 'percentage' ? __('Phần trăm') : __('Số tiền cố định'); ?>
                                                                </span>
                                                                <strong class="ms-2">
                                                                    <?php if ($flash_sale_purchase['discount_type'] == 'percentage'): ?>
                                                                        <?= $flash_sale_purchase['discount_value']; ?>%
                                                                    <?php else: ?>
                                                                        <?= format_currency($flash_sale_purchase['discount_value']); ?>
                                                                    <?php endif; ?>
                                                                </strong>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <small class="text-muted d-block"><?= __('Số lượng mua'); ?></small>
                                                                <strong><?= $flash_sale_purchase['quantity']; ?></strong>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <small class="text-muted d-block"><?= __('Thời gian áp dụng'); ?></small>
                                                                <strong><?= date('d/m/Y H:i', strtotime($flash_sale_purchase['created_at'])); ?></strong>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                    <?php else: ?>
                                        <!-- Hiển thị đơn giản khi không có mã giảm giá -->
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <div class="d-flex align-items-center">
                                                    <i class="fa-solid fa-coins text-muted me-2" style="width: 20px;"></i>
                                                    <div class="flex-grow-1">
                                                        <small class="text-muted d-block"><?= __('Tổng tiền'); ?></small>
                                                        <strong class="text-danger fs-5"><?= format_currency($order['total_price']); ?></strong>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="d-flex align-items-center">
                                                    <i class="fa-solid fa-tag text-muted me-2" style="width: 20px;"></i>
                                                    <div class="flex-grow-1">
                                                        <small class="text-muted d-block"><?= __('Giá khuyến mãi'); ?></small>
                                                        <?php if ($has_sale_price): ?>
                                                            <strong class="text-success fs-5"><?= format_currency($order['sale_price']); ?></strong>
                                                            <small class="text-muted text-decoration-line-through ms-2"><?= format_currency($order['total_price']); ?></small>
                                                        <?php else: ?>
                                                            <strong class="text-danger fs-5"><?= format_currency($order['total_price']); ?></strong>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row g-3 mt-2 pt-3 border-top">
                                            <div class="col-12">
                                                <div class="d-flex align-items-center justify-content-between p-3 bg-success-transparent rounded border border-success border-opacity-25">
                                                    <span class="fw-semibold"><?= __('Số tiền khách hàng thanh toán'); ?>:</span>
                                                    <strong class="text-success fs-4"><?= format_currency($final_amount); ?></strong>
                                                </div>
                                            </div>

                                            <?php
                                            // Hiển thị thông tin hoa hồng nếu có
                                            $has_commission = isset($order['commission_amount']) && $order['commission_amount'] > 0 && isset($order['commission_user_id']) && $order['commission_user_id'] > 0;
                                            ?>
                                            <?php if ($has_commission): ?>
                                                <div class="col-12">
                                                    <div class="alert alert-success mb-0">
                                                        <div class="d-flex align-items-center mb-2">
                                                            <i class="fa-solid fa-hand-holding-dollar text-success me-2"></i>
                                                            <strong><?= __('Thông tin hoa hồng'); ?></strong>
                                                        </div>
                                                        <div class="row g-2">
                                                            <div class="col-md-6">
                                                                <small class="text-muted d-block"><?= __('Người nhận hoa hồng'); ?></small>
                                                                <strong class="text-success">
                                                                    <?= htmlspecialchars($order['commission_username'] ?? 'User #' . $order['commission_user_id']); ?>
                                                                </strong>
                                                                <a href="<?= base_url_admin('user-edit&id=' . $order['commission_user_id']); ?>"
                                                                    class="btn btn-xs btn-outline-success ms-2"
                                                                    title="<?= __('Xem chi tiết user'); ?>">
                                                                    <i class="fa-solid fa-external-link"></i>
                                                                </a>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <small class="text-muted d-block"><?= __('Số tiền hoa hồng'); ?></small>
                                                                <strong class="text-success"><?= format_currency($order['commission_amount']); ?></strong>
                                                            </div>
                                                        </div>
                                                        <small class="text-muted d-block mt-2">
                                                            <i class="fa-solid fa-info-circle me-1"></i>
                                                            <?= __('Khi hủy đơn hàng, hệ thống sẽ tự động thu hồi hoa hồng từ người nhận.'); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($has_flash_sale): ?>
                                                <div class="col-12">
                                                    <div class="alert mb-0" style="background-color: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.3);">
                                                        <div class="d-flex align-items-center mb-2">
                                                            <i class="fa-solid fa-bolt me-2" style="color: #ef4444;"></i>
                                                            <strong style="color: #ef4444;"><?= __('Thông tin Flash Sale'); ?></strong>
                                                        </div>
                                                        <div class="row g-2">
                                                            <div class="col-md-6">
                                                                <small class="text-muted d-block"><?= __('Tên Flash Sale'); ?></small>
                                                                <strong><?= htmlspecialchars($flash_sale_purchase['flash_sale_name'] ?? 'N/A'); ?></strong>
                                                                <a href="<?= base_url_admin('flash-sale-edit&id=' . $flash_sale_purchase['flash_sale_id']); ?>"
                                                                    class="btn btn-xs btn-outline-danger ms-2"
                                                                    title="<?= __('Xem chi tiết Flash Sale'); ?>">
                                                                    <i class="fa-solid fa-external-link"></i>
                                                                </a>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <small class="text-muted d-block"><?= __('Loại giảm giá'); ?></small>
                                                                <span class="badge bg-<?= $flash_sale_purchase['discount_type'] == 'percentage' ? 'info' : 'warning'; ?>">
                                                                    <?= $flash_sale_purchase['discount_type'] == 'percentage' ? __('Phần trăm') : __('Số tiền cố định'); ?>
                                                                </span>
                                                                <strong class="ms-2">
                                                                    <?php if ($flash_sale_purchase['discount_type'] == 'percentage'): ?>
                                                                        <?= $flash_sale_purchase['discount_value']; ?>%
                                                                    <?php else: ?>
                                                                        <?= format_currency($flash_sale_purchase['discount_value']); ?>
                                                                    <?php endif; ?>
                                                                </strong>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <small class="text-muted d-block"><?= __('Số lượng mua'); ?></small>
                                                                <strong><?= $flash_sale_purchase['quantity']; ?></strong>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <small class="text-muted d-block"><?= __('Thời gian áp dụng'); ?></small>
                                                                <strong><?= date('d/m/Y H:i', strtotime($flash_sale_purchase['created_at'])); ?></strong>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Nội dung giao hàng -->
                            <?php if (!empty($order['delivery_content'])):
                                // Split delivery_content thành từng dòng
                                $delivery_lines = array_values(array_filter(
                                    array_map('trim', preg_split('/\r\n|\r|\n/', $order['delivery_content'])),
                                    function ($line) {
                                        return $line !== '';
                                    }
                                ));
                            ?>
                                <div class="card border-info mb-3">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center justify-content-between mb-3">
                                            <h6 class="card-title mb-0">
                                                <i class="fa-solid fa-truck-fast me-2 text-info"></i>
                                                <?= __('Nội dung giao hàng thủ công'); ?>
                                                <?php if (count($delivery_lines) > 1): ?>
                                                    <span class="badge bg-info-transparent ms-2">
                                                        <i class="fa-solid fa-boxes me-1"></i><?= count($delivery_lines); ?> <?= __('tài khoản'); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </h6>
                                            <button type="button" class="btn btn-sm btn-primary" onclick="copyDeliveryContent()" title="<?= __('Sao chép tất cả'); ?>">
                                                <i class="fa-solid fa-copy me-1"></i><?= __('Sao chép'); ?>
                                            </button>
                                        </div>
                                        <div class="bg-light border rounded p-3 font-monospace" id="delivery_content_display"
                                            style="white-space: pre-wrap; word-break: break-word; min-height: 80px; font-size: 14px; line-height: 1.6;"><?= htmlspecialchars($order['delivery_content']); ?></div>
                                        <small class="text-muted d-block mt-2">
                                            <i class="fa-solid fa-info-circle me-1"></i>
                                            <?= __('Nội dung này đã được giao cho khách hàng khi đơn hàng hoàn thành. Nếu đơn giao ngay, nội dung này sẽ ưu tiên hiển thị thay thế cho nội dung từ kho hàng giao ngay.'); ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Thông tin tài khoản từ kho hàng (nếu là gói giao ngay) -->
                            <?php if (!empty($stock_list)): ?>
                                <div class="card border-success mb-3">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center justify-content-between mb-3">
                                            <h6 class="card-title mb-0">
                                                <i class="fa-solid fa-box-open me-2 text-success"></i>
                                                <?= __('Nội dung giao hàng được lấy tự động từ kho hàng'); ?>
                                                <span class="badge bg-success-transparent ms-2">
                                                    <i class="fa-solid fa-bolt me-1"></i><?= __('Gói giao ngay'); ?>
                                                </span>
                                                <?php if (count($stock_list) > 1): ?>
                                                    <span class="badge bg-info-transparent ms-2">
                                                        <i class="fa-solid fa-boxes me-1"></i><?= __('Số lượng:'); ?> <?= count($stock_list); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </h6>
                                            <a href="<?= base_url_admin('product-stock&plan_id=' . $order['plan_id']); ?>"
                                                class="btn btn-sm btn-outline-success"
                                                title="<?= __('Xem kho hàng'); ?>">
                                                <i class="fa-solid fa-external-link me-1"></i><?= __('Xem kho hàng'); ?>
                                            </a>
                                        </div>

                                        <div class="mb-3">
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fa-solid fa-key text-primary me-2"></i>
                                                <small class="text-muted fw-semibold"><?= __('Thông tin tài khoản'); ?></small>
                                            </div>
                                            <div class="position-relative">
                                                <div class="bg-light border rounded p-3 font-monospace"
                                                    id="stock_value_display"
                                                    style="min-height: 100px; white-space: pre-wrap; word-break: break-word; font-size: 14px; line-height: 1.6;"><?php
                                                                                                                                                                    // Gộp tất cả tài khoản, mỗi dòng một tài khoản
                                                                                                                                                                    $all_stock_values = [];
                                                                                                                                                                    foreach ($stock_list as $stock_item) {
                                                                                                                                                                        $all_stock_values[] = trim($stock_item['stock_value']);
                                                                                                                                                                    }
                                                                                                                                                                    echo htmlspecialchars(implode("\n", $all_stock_values));
                                                                                                                                                                    ?></div>
                                                <button type="button"
                                                    class="btn btn-sm btn-primary position-absolute top-0 end-0 m-2"
                                                    onclick="copyStockValue()"
                                                    title="<?= __('Sao chép thông tin tài khoản'); ?>">
                                                    <i class="fa-solid fa-copy"></i>
                                                </button>
                                            </div>
                                            <small class="text-muted d-block mt-2">
                                                <i class="fa-solid fa-info-circle me-1"></i>
                                                <?= __('Thông tin tài khoản đã được giao cho khách hàng'); ?>
                                            </small>
                                        </div>

                                    </div>
                                </div>
                            <?php elseif (isset($order['plan_is_instant']) && (int)$order['plan_is_instant'] == 1 && empty($stock_list)): ?>
                                <!-- Cảnh báo nếu là gói giao ngay nhưng chưa có stock với order_id trùng -->
                                <div class="mb-4">
                                    <div class="alert alert-warning">
                                        <i class="fa-solid fa-exclamation-triangle me-2"></i>
                                        <strong><?= __('Lưu ý:'); ?></strong>
                                        <?= __('Đây là gói giao ngay nhưng đơn hàng chưa có thông tin tài khoản từ kho hàng (chưa có stock nào được gán order_id cho đơn hàng này).'); ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Thông tin trường tùy chỉnh -->
                            <?php if (!empty($plan_fields) && !empty($fields_data)): ?>
                                <div class="card border-secondary-transparent mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title mb-3">
                                            <i class="fa-solid fa-list-check me-2 text-secondary"></i>
                                            <?= __('Thông tin bổ sung'); ?>
                                        </h6>
                                        <?php foreach ($plan_fields as $field):
                                            $field_value = isset($fields_data[$field['field_key']]) ? $fields_data[$field['field_key']] : '';
                                            if (empty($field_value)) continue;

                                            $field_icons = [
                                                'text' => 'fa-font',
                                                'email' => 'fa-envelope',
                                                'password' => 'fa-lock',
                                                'textarea' => 'fa-align-left'
                                            ];
                                            $field_icon = $field_icons[$field['type']] ?? 'fa-circle';
                                        ?>
                                            <div class="mb-3 pb-3 border-bottom">
                                                <div class="d-flex align-items-start">
                                                    <i class="fa-solid <?= $field_icon; ?> text-muted me-2 mt-1" style="width: 20px;"></i>
                                                    <div class="flex-grow-1">
                                                        <small class="text-muted d-block mb-1"><?= htmlspecialchars(html_entity_decode($field['label'], ENT_QUOTES, 'UTF-8')); ?></small>
                                                        <?php if ($field['type'] == 'textarea'): ?>
                                                            <div class="bg-light border rounded p-2 font-monospace" style="white-space: pre-wrap; word-break: break-word; min-height: 60px;">
                                                                <?= htmlspecialchars($field_value); ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <strong class="d-block"><?= htmlspecialchars($field_value); ?></strong>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="col-xl-4">
                    <!-- Quản lý đơn hàng -->
                    <div class="card custom-card mb-4">
                        <div class="card-body">
                            <?php
                            $is_locked = ($order['status'] == 'cancelled' || $order['status'] == 'cancelled_no_refund');
                            ?>
                            <?php if ($is_locked): ?>
                                <div class="alert alert-warning mb-3">
                                    <i class="fa-solid fa-lock me-2"></i>
                                    <strong><?= __('Lưu ý:'); ?></strong>
                                    <?= __('Đơn hàng đã bị hủy, không thể thay đổi trạng thái đơn hàng.'); ?>
                                </div>
                            <?php endif; ?>
                            <div class="mb-3">
                                <label class="form-label"><?= __('Trạng thái:'); ?> <span class="text-danger">*</span></label>
                                <select class="form-select" name="status" id="order_status" required <?= $is_locked ? 'disabled' : ''; ?>>
                                    <option value="pending" <?= $order['status'] == 'pending' ? 'selected' : ''; ?>><?= __('Chờ xử lý'); ?></option>
                                    <option value="processing" <?= $order['status'] == 'processing' ? 'selected' : ''; ?>><?= __('Đang xử lý'); ?></option>
                                    <option value="completed" <?= $order['status'] == 'completed' ? 'selected' : ''; ?>><?= __('Hoàn thành'); ?></option>
                                    <option value="cancelled" <?= $order['status'] == 'cancelled' ? 'selected' : ''; ?>><?= __('Hủy đơn hàng và hoàn tiền'); ?></option>
                                    <option value="cancelled_no_refund" <?= $order['status'] == 'cancelled_no_refund' ? 'selected' : ''; ?>><?= __('Hủy không hoàn tiền'); ?></option>
                                </select>
                                <?php if ($is_locked): ?>
                                    <input type="hidden" name="status" value="<?= $order['status']; ?>">
                                <?php endif; ?>
                                <small class="text-muted d-block mt-2">
                                    <i class="fa-solid fa-info-circle me-1"></i>
                                    <?= __('Trạng thái thanh toán sẽ được tự động quản lý dựa trên trạng thái đơn hàng.'); ?>
                                </small>
                            </div>

                            <div id="cancel-warning" class="alert alert-warning mt-3" style="display: none;">
                                <i class="fa-solid fa-exclamation-triangle me-2"></i>
                                <strong><?= __('Lưu ý:'); ?></strong>
                                <span id="cancel-warning-text"></span>
                            </div>

                            <div id="cancel-no-refund-warning" class="alert alert-danger mt-3" style="display: none;">
                                <i class="fa-solid fa-exclamation-circle me-2"></i>
                                <strong><?= __('Cảnh báo:'); ?></strong>
                                <?= __('Khi chọn "Hủy không hoàn tiền", hệ thống sẽ không hoàn tiền cho khách hàng. Đơn hàng sẽ được đánh dấu là đã hủy.'); ?>
                            </div>

                            <div class="mt-3" id="reason-field" style="<?= (($order['status'] == 'cancelled' || $order['status'] == 'cancelled_no_refund') && !empty($order['reason'])) ? 'display: block;' : 'display: none;'; ?>">
                                <label class="form-label"><?= __('Lý do hủy:'); ?></label>
                                <textarea class="form-control" name="reason" rows="3" placeholder="<?= __('Nhập lý do hủy...'); ?>" <?= $is_locked ? 'readonly' : ''; ?>><?= htmlspecialchars($order['reason'] ?? ''); ?></textarea>
                            </div>

                            <div class="mt-3">
                                <label class="form-label"><?= __('Ghi chú:'); ?></label>
                                <textarea class="form-control" name="note" rows="3" placeholder="<?= __('Nhập ghi chú...'); ?>"><?= htmlspecialchars($order['note'] ?? ''); ?></textarea>
                            </div>

                            <!-- Ngày hết hạn tùy chỉnh -->
                            <?php if (!empty($order['duration_type']) && $order['duration_type'] != 'lifetime'): ?>
                                <div class="mt-3">
                                    <label class="form-label">
                                        <i class="fa-solid fa-calendar-alt me-1 text-info"></i>
                                        <?= __('Ngày hết hạn tùy chỉnh:'); ?>
                                    </label>
                                    <input type="datetime-local"
                                        class="form-control"
                                        name="custom_expiry_date"
                                        value="<?= !empty($order['custom_expiry_date']) ? date('Y-m-d\TH:i', strtotime($order['custom_expiry_date'])) : ''; ?>">
                                    <small class="text-muted d-block mt-2">
                                        <i class="fa-solid fa-info-circle me-1"></i>
                                        <?= __('Để trống để sử dụng thời hạn tự động từ gói sản phẩm. Đặt ngày cụ thể để ghi đè.'); ?>
                                    </small>
                                    <?php if (!empty($order['custom_expiry_date'])): ?>
                                        <div class="alert alert-info mt-2 py-2">
                                            <small>
                                                <i class="fa-solid fa-check-circle me-1"></i>
                                                <?= __('Đang sử dụng ngày hết hạn tùy chỉnh.'); ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Nội dung giao hàng -->
                            <div class="mt-3">
                                <label class="form-label">
                                    <?= __('Nội dung giao hàng:'); ?>
                                    <small class="text-muted">(<?= __('Nội dung khách hàng nhận được khi đơn hàng hoàn tất'); ?>)</small>
                                </label>
                                <textarea class="form-control" name="delivery_content" rows="5" placeholder="<?= __('Nhập nội dung giao hàng...'); ?>"><?= htmlspecialchars($order['delivery_content'] ?? ''); ?></textarea>
                                <small class="text-muted d-block mt-2">
                                    <i class="fa-solid fa-info-circle me-1"></i>
                                    <?= __('Nội dung này sẽ được hiển thị cho khách hàng khi đơn hàng hoàn thành. Mỗi dòng là 1 tài khoản.'); ?>
                                </small>
                            </div>

                            <div class="d-flex justify-content-end mt-4 pt-3 border-top">
                                <a href="<?= base_url_admin('product-orders'); ?>" class="btn btn-secondary btn-sm me-2">
                                    <i class="fa-solid fa-times me-1"></i><?= __('Hủy'); ?>
                                </a>
                                <button type="submit" name="submit" class="btn btn-primary btn-sm">
                                    <i class="fa-solid fa-save me-1"></i><?= __('Cập nhật'); ?>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Danh sách ticket hỗ trợ liên quan -->
                    <?php
                    // Lấy danh sách ticket hỗ trợ liên quan đến đơn hàng này
                    $order_tickets = $CMSNT->get_list_safe(
                        "SELECT st.*, u.username as user_username 
                         FROM `support_tickets` st 
                         LEFT JOIN `users` u ON st.user_id = u.id 
                         WHERE st.order_id = ? 
                         ORDER BY st.created_at DESC",
                        [$order['id']]
                    );
                    ?>
                    <?php if (!empty($order_tickets)): ?>
                        <div class="card custom-card mb-4">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <h6 class="mb-0">
                                        <i class="fa-solid fa-headset me-2 text-info"></i>
                                        <?= __('Ticket hỗ trợ'); ?>
                                        <span class="badge bg-info-transparent text-info ms-2"><?= count($order_tickets); ?></span>
                                    </h6>
                                    <a href="<?= base_url_admin('tickets'); ?>" class="btn btn-sm btn-outline-info" title="<?= __('Xem tất cả'); ?>">
                                        <i class="fa-solid fa-external-link"></i>
                                    </a>
                                </div>
                                <?php foreach ($order_tickets as $ticket):
                                    // Xác định màu badge theo trạng thái
                                    $status_colors = [
                                        'open' => 'warning',
                                        'pending' => 'info',
                                        'replied' => 'primary',
                                        'resolved' => 'success',
                                        'closed' => 'secondary'
                                    ];
                                    $status_labels = [
                                        'open' => __('Mở'),
                                        'pending' => __('Chờ xử lý'),
                                        'replied' => __('Đã trả lời'),
                                        'resolved' => __('Đã giải quyết'),
                                        'closed' => __('Đóng')
                                    ];
                                    $badge_color = $status_colors[$ticket['status']] ?? 'secondary';
                                    $status_label = $status_labels[$ticket['status']] ?? $ticket['status'];
                                ?>
                                    <a href="<?= base_url_admin('ticket-detail&id=' . $ticket['id']); ?>"
                                        class="d-block p-3 mb-2 rounded border bg-light text-decoration-none hover-shadow"
                                        style="transition: all 0.2s ease;">
                                        <div class="d-flex w-100 justify-content-between align-items-center">
                                            <div class="flex-grow-1 me-2">
                                                <div class="fw-semibold text-dark mb-1" style="font-size: 0.875rem;">
                                                    <span class="text-muted"></span> <?= htmlspecialchars($ticket['subject']); ?>
                                                </div>
                                                <small class="text-muted">
                                                    <i class="fa-regular fa-clock me-1"></i>
                                                    <?= date('d/m/Y H:i', strtotime($ticket['created_at'])); ?>
                                                </small>
                                            </div>
                                            <span class="badge bg-<?= $badge_color; ?>-transparent text-<?= $badge_color; ?>">
                                                <?= $status_label; ?>
                                            </span>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
require_once(__DIR__ . '/footer.php');
?>

<script>
    <?php
    $is_locked = ($order['status'] == 'cancelled');
    ?>
    <?php if (!$is_locked): ?>
        // Hiển thị/ẩn cảnh báo khi chọn hủy đơn hàng
        document.getElementById('order_status').addEventListener('change', function() {
            var status = this.value;
            var cancelWarning = document.getElementById('cancel-warning');
            var cancelNoRefundWarning = document.getElementById('cancel-no-refund-warning');
            var cancelWarningText = document.getElementById('cancel-warning-text');
            var reasonField = document.getElementById('reason-field');
            var refundAmount = '<?= format_currency($order['sale_price'] > 0 && $order['sale_price'] < $order['total_price'] ? $order['sale_price'] : $order['total_price']); ?>';
            var totalPrice = <?= $order['total_price']; ?>;
            var hasCommission = <?= isset($order['commission_amount']) && $order['commission_amount'] > 0 ? 'true' : 'false'; ?>;
            var commissionAmount = '<?= isset($order['commission_amount']) && $order['commission_amount'] > 0 ? format_currency($order['commission_amount']) : '0'; ?>';
            var commissionUser = '<?= isset($order['commission_username']) ? htmlspecialchars($order['commission_username'], ENT_QUOTES) : ''; ?>';

            if (status == 'cancelled') {
                // Hủy có hoàn tiền
                if (totalPrice > 0) {
                    var warningHTML = '<?= __('Khi hủy đơn hàng, hệ thống sẽ tự động hoàn tiền cho khách hàng.'); ?><br>' +
                        '<small class="text-muted"><?= __('Số tiền sẽ hoàn:'); ?> <strong class="text-primary">' + refundAmount + '</strong></small>';

                    // Thêm cảnh báo về thu hồi hoa hồng nếu có
                    if (hasCommission) {
                        warningHTML += '<br><br><strong class="text-warning"><?= __('⚠️ Lưu ý:'); ?></strong> <?= __('Hệ thống sẽ tự động thu hồi hoa hồng'); ?><br>' +
                            '<small class="text-muted"><?= __('Số tiền thu hồi:'); ?> <strong class="text-warning">' + commissionAmount + '</strong> <?= __('từ'); ?> <strong>' + commissionUser + '</strong></small>';
                    }

                    cancelWarningText.innerHTML = warningHTML;
                    cancelWarning.style.display = 'block';
                } else {
                    cancelWarningText.innerHTML = '<?= __('Khi hủy đơn hàng, đơn hàng sẽ được đánh dấu là đã hủy.'); ?>';
                    cancelWarning.style.display = 'block';
                }
                cancelNoRefundWarning.style.display = 'none';
                reasonField.style.display = 'block';
            } else if (status == 'cancelled_no_refund') {
                // Hủy không hoàn tiền
                cancelWarning.style.display = 'none';
                cancelNoRefundWarning.style.display = 'block';
                reasonField.style.display = 'block';
            } else {
                cancelWarning.style.display = 'none';
                cancelNoRefundWarning.style.display = 'none';
                reasonField.style.display = 'none';
            }
        });

        // Trigger on page load
        document.getElementById('order_status').dispatchEvent(new Event('change'));
    <?php else: ?>
        // Đơn hàng đã bị khóa, ẩn cảnh báo
        document.getElementById('cancel-warning').style.display = 'none';
        document.getElementById('cancel-no-refund-warning').style.display = 'none';
    <?php endif; ?>

    // Sao chép thông tin tài khoản từ kho hàng
    function copyStockValue() {
        var stockValueDiv = document.getElementById('stock_value_display');

        if (!stockValueDiv) {
            return;
        }

        var textToCopy = stockValueDiv.innerText || stockValueDiv.textContent || '';

        try {
            // Thử dùng Clipboard API mới
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(textToCopy).then(function() {
                    showMessage('<?= __("Đã sao chép thông tin tài khoản vào bộ nhớ tạm"); ?>', 'success');
                }).catch(function() {
                    // Fallback nếu Clipboard API thất bại
                    fallbackCopyStockValue(textToCopy);
                });
            } else {
                // Fallback cho trình duyệt cũ
                fallbackCopyStockValue(textToCopy);
            }
        } catch (err) {
            fallbackCopyStockValue(textToCopy);
        }
    }

    // Hàm fallback để sao chép
    function fallbackCopyStockValue(text) {
        // Tạo một textarea tạm thời để copy
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.left = '-999999px';
        textarea.style.top = '-999999px';
        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();

        try {
            var successful = document.execCommand('copy');
            document.body.removeChild(textarea);
            if (successful) {
                showMessage('<?= __("Đã sao chép thông tin tài khoản"); ?>', 'success');
            } else {
                showMessage('<?= __("Không thể sao chép. Vui lòng chọn và copy thủ công"); ?>', 'error');
            }
        } catch (err) {
            document.body.removeChild(textarea);
            showMessage('<?= __("Không thể sao chép. Vui lòng chọn và copy thủ công"); ?>', 'error');
        }
    }

    // Sao chép nội dung giao hàng
    function copyDeliveryContent() {
        var contentDiv = document.getElementById('delivery_content_display');

        if (!contentDiv) {
            return;
        }

        var textToCopy = contentDiv.innerText || contentDiv.textContent || '';

        try {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(textToCopy).then(function() {
                    showMessage('<?= __("Đã sao chép nội dung giao hàng"); ?>', 'success');
                }).catch(function() {
                    fallbackCopyText(textToCopy, '<?= __("Đã sao chép nội dung giao hàng"); ?>');
                });
            } else {
                fallbackCopyText(textToCopy, '<?= __("Đã sao chép nội dung giao hàng"); ?>');
            }
        } catch (err) {
            fallbackCopyText(textToCopy, '<?= __("Đã sao chép nội dung giao hàng"); ?>');
        }
    }

    // Hàm fallback chung
    function fallbackCopyText(text, successMsg) {
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.left = '-999999px';
        textarea.style.top = '-999999px';
        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();

        try {
            var successful = document.execCommand('copy');
            document.body.removeChild(textarea);
            if (successful) {
                showMessage(successMsg, 'success');
            } else {
                showMessage('<?= __("Không thể sao chép. Vui lòng chọn và copy thủ công"); ?>', 'error');
            }
        } catch (err) {
            document.body.removeChild(textarea);
            showMessage('<?= __("Không thể sao chép. Vui lòng chọn và copy thủ công"); ?>', 'error');
        }
    }
</script>