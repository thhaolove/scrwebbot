<?php if (!defined('IN_SITE')) {
    die('The Request Not Found');
}
$body = [
    'title' => __('Quản lý gói sản phẩm') . ' | ' . $CMSNT->site('title'),
    'desc'   => $CMSNT->site('description'),
    'keyword' => $CMSNT->site('keywords')
];
$body['header'] = '<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>';
$body['header'] .= '<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />';
$body['footer'] = '';
require_once(__DIR__ . '/../../models/is_admin.php');
require_once(__DIR__ . '/header.php');
require_once(__DIR__ . '/sidebar.php');
if (checkPermission($getUser['admin'], 'view_product_plan') != true) {
    $role_name = getRoleName('view_product_plan');
    die('<script type="text/javascript">if(!alert("' . sprintf(__('Bạn không có quyền %s'), $role_name) . '")){window.history.back();}</script>');
}

// Phân trang an toàn
$limit = isset($_GET['limit']) ? (validate_int($_GET['limit'], 1, 1000) ?: 20) : 20;
$page = isset($_GET['page']) ? (validate_int($_GET['page'], 1, 1000000) ?: 1) : 1;
$from = ($page - 1) * $limit;

// Biến giữ giá trị hiển thị lại
$product_filter = 0;
$supplier_filter = 0;
$status_filter = -1;
$search = '';

// WHERE an toàn với prepared statements
$where_conditions = ["1 = 1"];
$where_params = [];

// Lọc theo sản phẩm
if (!empty($_GET['product_id'])) {
    $product_filter_input = validate_int($_GET['product_id'], 1);
    if ($product_filter_input !== false) {
        $product_filter = $product_filter_input;
        $where_conditions[] = 'pp.`product_id` = ?';
        $where_params[] = $product_filter;
    } else {
        $product_filter = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
    }
}


// Lọc theo API Supplier
if (!empty($_GET['supplier_id'])) {
    $supplier_filter_input = validate_int($_GET['supplier_id'], 1);
    if ($supplier_filter_input !== false) {
        $supplier_filter = $supplier_filter_input;
        $where_conditions[] = 'pp.`supplier_id` = ?';
        $where_params[] = $supplier_filter;
    } else {
        $supplier_filter = isset($_GET['supplier_id']) ? intval($_GET['supplier_id']) : 0;
    }
}

// Lọc theo trạng thái
if (isset($_GET['status']) && $_GET['status'] !== '') {
    $status_filter_input = validate_int($_GET['status'], 0, 1);
    if ($status_filter_input !== false) {
        $status_filter = $status_filter_input;
        $where_conditions[] = 'pp.`status` = ?';
        $where_params[] = $status_filter;
    } else {
        $status_filter = isset($_GET['status']) ? intval($_GET['status']) : -1;
    }
}

// Tìm kiếm
if (!empty($_GET['search'])) {
    $search_input = validate_string($_GET['search'], 255, 1);
    if ($search_input !== false) {
        $search = $search_input;
        $where_conditions[] = '(pp.`name` LIKE ? OR p.`name` LIKE ?)';
        $searchPattern = '%' . $search . '%';
        $where_params[] = $searchPattern;
        $where_params[] = $searchPattern;
    } else {
        $search = isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '';
    }
}

$where_sql = implode(' AND ', $where_conditions);

// Đếm tổng số lượng
$total_query = "SELECT COUNT(*) as total FROM `product_plans` pp 
    LEFT JOIN `products` p ON pp.`product_id` = p.`id` 
    WHERE " . $where_sql;
$total_result = $CMSNT->get_row_safe($total_query, $where_params);
$total = $total_result ? (int)$total_result['total'] : 0;
$total_pages = ceil($total / $limit);

// Lấy danh sách gói với thông tin sản phẩm và supplier
$plans_list = $CMSNT->get_list_safe("
    SELECT pp.*, p.`name` as product_name, p.`id` as product_id, 
           s.`domain` as supplier_domain, s.`type` as supplier_type
    FROM `product_plans` pp 
    LEFT JOIN `products` p ON pp.`product_id` = p.`id`
    LEFT JOIN `suppliers` s ON pp.`supplier_id` = s.`id`
    WHERE " . $where_sql . " 
    ORDER BY pp.`sort_order` ASC, pp.`id` DESC 
    LIMIT ? OFFSET ?
", array_merge($where_params, [$limit, $from]));

// Đếm số đơn hàng đã bán cho mỗi gói
$plan_orders_count = [];
if (count($plans_list) > 0) {
    $plan_ids = array_column($plans_list, 'id');
    $placeholders = implode(',', array_fill(0, count($plan_ids), '?'));
    $orders_count_query = "SELECT `plan_id`, COUNT(*) as total_orders, SUM(`quantity`) as total_quantity FROM `product_orders` WHERE `plan_id` IN ($placeholders) GROUP BY `plan_id`";
    $orders_count_result = $CMSNT->get_list_safe($orders_count_query, $plan_ids);

    foreach ($orders_count_result as $row) {
        $plan_orders_count[$row['plan_id']] = [
            'total_orders' => (int)$row['total_orders'],
            'total_quantity' => (int)$row['total_quantity']
        ];
    }
}

// Đếm số lượng theo trạng thái
$total_active = $CMSNT->get_row_safe("SELECT COUNT(*) as total FROM `product_plans` WHERE `status` = 1", []);
$total_inactive = $CMSNT->get_row_safe("SELECT COUNT(*) as total FROM `product_plans` WHERE `status` = 0", []);
$total_active_count = $total_active ? (int)$total_active['total'] : 0;
$total_inactive_count = $total_inactive ? (int)$total_inactive['total'] : 0;

// Lấy danh sách tất cả sản phẩm để filter
$products_list = $CMSNT->get_list_safe("SELECT `id`, `name` FROM `products` ORDER BY `name` ASC", []);

// Lấy danh sách API Suppliers để filter
$suppliers_list = $CMSNT->get_list_safe("SELECT * FROM `suppliers` WHERE `status` = 1 ORDER BY `domain` ASC", []);


?>

<div class="main-content app-content">
    <div class="container-fluid">
        <!-- Tiêu đề trang -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <div>
                <h1 class="page-name fw-semibold fs-18 mb-0">
                    <i class="fa-solid fa-box-open me-1"></i><?= __('Quản lý gói sản phẩm'); ?>
                </h1>
            </div>
            <div class="ms-md-1 ms-0">
                <a href="<?= base_url_admin('products'); ?>" class="btn btn-secondary btn-sm">
                    <i class="fa-solid fa-arrow-left me-1"></i><?= __('Quay lại'); ?>
                </a>
            </div>
        </div>

        <!-- Thống kê -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card custom-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="avatar avatar-md bg-success-transparent rounded-circle">
                                    <i class="fa-solid fa-boxes-stacked fs-20"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="mb-0 text-muted"><?= __('Tổng số gói'); ?></p>
                                <h4 class="mb-0 fw-semibold"><?= number_format($total); ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card custom-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="avatar avatar-md bg-info-transparent rounded-circle">
                                    <i class="fa-solid fa-check-circle fs-20"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="mb-0 text-muted"><?= __('Đang hoạt động'); ?></p>
                                <h4 class="mb-0 fw-semibold text-info"><?= number_format($total_active_count); ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card custom-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="avatar avatar-md bg-danger-transparent rounded-circle">
                                    <i class="fa-solid fa-times-circle fs-20"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="mb-0 text-muted"><?= __('Đã tắt'); ?></p>
                                <h4 class="mb-0 fw-semibold text-danger"><?= number_format($total_inactive_count); ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bộ lọc -->
        <div class="card custom-card mb-4">
            <div class="card-body">
                <form method="GET" action="<?= base_url(); ?>">
                    <input type="hidden" name="module" value="<?= $CMSNT->site('path_admin'); ?>">
                    <input type="hidden" name="action" value="product-plans-all">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label"><?= __('Sản phẩm'); ?></label>
                            <select class="form-select" name="product_id" id="filter_product_id">
                                <option value=""><?= __('Tất cả sản phẩm'); ?></option>
                                <?php foreach ($products_list as $prod): ?>
                                    <option value="<?= $prod['id']; ?>" <?= $product_filter == $prod['id'] ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars(html_entity_decode($prod['name'], ENT_QUOTES, 'UTF-8')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><?= __('Kết nối API'); ?></label>
                            <select class="form-select" name="supplier_id" id="filter_supplier_id">
                                <option value=""><?= __('Tất cả nguồn'); ?></option>
                                <?php foreach ($suppliers_list as $sup): ?>
                                    <option value="<?= $sup['id']; ?>" <?= $supplier_filter == $sup['id'] ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($sup['domain']); ?> (<?= $sup['type']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><?= __('Tìm kiếm'); ?></label>
                            <input type="text" class="form-control" name="search"
                                value="<?= htmlspecialchars($search); ?>"
                                placeholder="<?= __('Tên gói, tên sản phẩm...'); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label"><?= __('Trạng thái'); ?></label>
                            <select class="form-select" name="status">
                                <option value="" <?= $status_filter == -1 ? 'selected' : ''; ?>><?= __('Tất cả'); ?></option>
                                <option value="1" <?= $status_filter == 1 ? 'selected' : ''; ?>><?= __('Đang hoạt động'); ?></option>
                                <option value="0" <?= $status_filter == 0 ? 'selected' : ''; ?>><?= __('Đã tắt'); ?></option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label"><?= __('Số lượng/trang'); ?></label>
                            <select class="form-select" name="limit">
                                <option value="10" <?= $limit == 10 ? 'selected' : ''; ?>>10</option>
                                <option value="20" <?= $limit == 20 ? 'selected' : ''; ?>>20</option>
                                <option value="50" <?= $limit == 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?= $limit == 100 ? 'selected' : ''; ?>>100</option>
                                <option value="200" <?= $limit == 200 ? 'selected' : ''; ?>>200</option>
                                <option value="500" <?= $limit == 500 ? 'selected' : ''; ?>>500</option>
                                <option value="1000" <?= $limit == 1000 ? 'selected' : ''; ?>>1000</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fa-solid fa-filter me-1"></i><?= __('Lọc'); ?>
                            </button>
                            <a href="<?= base_url_admin('product-plans-all') ?>" class="btn btn-secondary">
                                <i class="fa-solid fa-times me-1"></i><?= __('Bỏ lọc'); ?>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Danh sách gói -->
        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-body p-0">
                        <?php if (count($plans_list) > 0): ?>
                            <!-- Thanh công cụ hàng loạt -->
                            <div id="bulkActionsToolbar" class="card-footer bg-light border-bottom d-none">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="text-muted">
                                            <span id="selectedCount">0</span> <?= __('gói đã chọn'); ?>
                                        </span>
                                    </div>
                                    <div class="btn-list">
                                        <button type="button" id="btnBulkQuickUpdate" class="btn btn-sm btn-info d-none" onclick="showBulkQuickUpdateModal()">
                                            <i class="fa-solid fa-bolt me-1"></i><?= __('Cập nhật nhanh'); ?>
                                        </button>
                                        <button type="button" id="btnBulkDelete" class="btn btn-sm btn-danger d-none" onclick="bulkDeletePlans()">
                                            <i class="fa-solid fa-trash me-1"></i><?= __('Xóa đã chọn'); ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover border text-nowrap">
                                    <thead>
                                        <tr>
                                            <th class="text-center" style="width: 50px;">
                                                <input type="checkbox" id="selectAllPlans" class="form-check-input" onchange="toggleSelectAll(this)" style="transform: scale(1.3); cursor: pointer;" title="<?= __('Chọn tất cả'); ?>">
                                            </th>
                                            <th><?= __('Tên gói'); ?></th>
                                            <th><?= __('Nguồn API'); ?></th>
                                            <th><?= __('Thời hạn'); ?></th>
                                            <th><?= __('Giá'); ?></th>
                                            <th class="text-center"><?= __('Loại'); ?></th>
                                            <th class="text-center"><?= __('Trạng thái'); ?></th>
                                            <th class="text-center"><?= __('Số đơn hàng đã bán'); ?></th>
                                            <th><?= __('Thao tác'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($plans_list as $plan): ?>
                                            <tr data-plan-id="<?= $plan['id']; ?>" data-sort-order="<?= $plan['sort_order']; ?>">
                                                <td class="text-center">
                                                    <input type="checkbox" class="form-check-input plan-checkbox" value="<?= $plan['id']; ?>" onchange="updateBulkButtons()" style="transform: scale(1.3); cursor: pointer;">
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php
                                                        $plan_image = isset($plan['image']) && !empty($plan['image']) ? $plan['image'] : '';
                                                        if ($plan_image):
                                                        ?>
                                                            <img src="<?= base_url($plan_image); ?>" alt="" class="me-2" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px; border: 1px solid #dee2e6;">
                                                        <?php endif; ?>
                                                        <div>
                                                            <strong><?= htmlspecialchars(html_entity_decode($plan['name'], ENT_QUOTES, 'UTF-8')); ?></strong>
                                                            <?php if (!empty($plan['product_name'])): ?>
                                                                <a href="<?= base_url_admin('product-edit&id=' . $plan['product_id']); ?>" class="d-block text-decoration-none mt-1">
                                                                    <small class="text-success"><i class="fa-solid fa-box me-1"></i><?= htmlspecialchars(html_entity_decode($plan['product_name'], ENT_QUOTES, 'UTF-8')); ?></small>
                                                                </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if (!empty($plan['supplier_domain'])): ?>
                                                        <span class="badge bg-primary-transparent text-primary border border-primary border-opacity-25">
                                                            <i class="fa-solid fa-plug me-1"></i><?= htmlspecialchars($plan['supplier_domain'] ?? ''); ?>
                                                        </span>
                                                        <span class="badge bg-secondary-transparent text-secondary border border-secondary border-opacity-25 d-block mt-1">
                                                            <?= htmlspecialchars($plan['supplier_type']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $duration_text = '';
                                                    if ($plan['duration_type'] == 'lifetime') {
                                                        $duration_text = '<span class="badge bg-success"><i class="fa-solid fa-infinity"></i> ' . __('Vĩnh viễn') . '</span>';
                                                    } else {
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
                                                        $icon = $duration_icons[$plan['duration_type']] ?? 'fa-clock';
                                                        $label = $duration_labels[$plan['duration_type']] ?? '';
                                                        $duration_text = '<span class="badge bg-info"><i class="fa-solid ' . $icon . '"></i> ' . $plan['duration_value'] . ' ' . $label . '</span>';
                                                    }
                                                    echo $duration_text;
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $sale_price = isset($plan['sale_price']) ? (float)$plan['sale_price'] : 0;

                                                    if ($sale_price > 0 && $sale_price < $plan['price']) {
                                                        echo '<div>';
                                                        echo '<strong class="text-primary">' . format_currency($sale_price) . '</strong>';
                                                        echo '<small class="text-muted text-decoration-line-through d-block">' . format_currency($plan['price']) . '</small>';
                                                        echo '</div>';
                                                    } else {
                                                        echo '<strong class="text-primary">' . format_currency($plan['price']) . '</strong>';
                                                    }
                                                    ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php
                                                    $is_instant = isset($plan['is_instant']) ? (int)$plan['is_instant'] : 0;
                                                    if ($is_instant == 1) {
                                                        echo '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">';
                                                        echo '<i class="fa-solid fa-bolt me-1"></i>' . __('Giao ngay');
                                                        echo '</span>';
                                                    } else {
                                                        echo '<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">';
                                                        echo '<i class="fa-solid fa-shopping-cart me-1"></i>' . __('Đặt hàng');
                                                        echo '</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td class="text-center">
                                                    <div class="form-check form-switch d-flex justify-content-center">
                                                        <input class="form-check-input" type="checkbox"
                                                            id="plan_status<?= $plan['id']; ?>"
                                                            style="transform: scale(1.5);"
                                                            <?= $plan['status'] == 1 ? 'checked' : ''; ?>
                                                            onchange="updatePlanStatus('<?= $plan['id']; ?>', this.checked ? 1 : 0)">
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <?php
                                                    $orders_info = $plan_orders_count[$plan['id']] ?? ['total_orders' => 0, 'total_quantity' => 0];
                                                    $total_orders = $orders_info['total_orders'];
                                                    $total_quantity = $orders_info['total_quantity'];

                                                    if ($total_orders > 0) {
                                                        echo '<div class="d-flex flex-column align-items-center gap-2">';

                                                        // Badge số đơn hàng
                                                        echo '<a href="' . base_url_admin('product-orders&plan_id=' . $plan['id']) . '" class="text-decoration-none" title="' . __('Xem đơn hàng') . '">';
                                                        echo '<span class="badge bg-primary-transparent text-primary border border-primary border-opacity-25 px-3 py-2 d-inline-flex align-items-center gap-2" style="font-size: 13px;">';
                                                        echo '<i class="fa-solid fa-shopping-cart"></i>';
                                                        echo '<span class="fw-semibold">' . number_format($total_orders) . '</span>';
                                                        echo '<span class="text-muted">' . __('đơn') . '</span>';
                                                        echo '</span>';
                                                        echo '</a>';

                                                        // Hiển thị số lượng sản phẩm nếu khác số đơn
                                                        if ($total_quantity > $total_orders) {
                                                            echo '<small class="text-muted d-flex align-items-center gap-1">';
                                                            echo '<i class="fa-solid fa-box"></i>';
                                                            echo '<span>' . number_format($total_quantity) . ' ' . __('key') . '</span>';
                                                            echo '</small>';
                                                        }


                                                        echo '</div>';
                                                    } else {
                                                        echo '<span class="text-muted fst-italic">' . __('Chưa có đơn hàng') . '</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $is_api_plan = !empty($plan['supplier_id']);
                                                    ?>
                                                    <div class="btn-list">
                                                        <?php if (!$is_api_plan && isset($plan['is_instant']) && (int)$plan['is_instant'] == 1): ?>
                                                            <a href="<?= base_url_admin('product-stock&plan_id=' . $plan['id']); ?>" class="btn btn-sm btn-success">
                                                                <i class="fa-solid fa-warehouse me-1"></i><?= __('Kho hàng'); ?>
                                                            </a>
                                                        <?php endif; ?>
                                                        <?php if (!$is_api_plan): ?>
                                                            <button onclick="managePlanFields(<?= $plan['id']; ?>)" class="btn btn-sm btn-warning">
                                                                <i class="fa-solid fa-list-check me-1"></i><?= __('Trường'); ?>
                                                            </button>
                                                        <?php endif; ?>
                                                        <button onclick="editPlan(<?= $plan['id']; ?>)" class="btn btn-sm btn-info">
                                                            <i class="fa-solid fa-edit me-1"></i><?= __('Sửa'); ?>
                                                        </button>
                                                        <button onclick="deletePlan(<?= $plan['id']; ?>)" class="btn btn-sm btn-danger">
                                                            <i class="fa-solid fa-trash me-1"></i><?= __('Xóa'); ?>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Phân trang -->
                            <?php
                            // Tạo URL pagination với các tham số filter
                            $pagination_url = base_url_admin('product-plans-all');
                            $pagination_url .= '&limit=' . $limit;
                            if (!empty($search)) $pagination_url .= '&search=' . urlencode($search);
                            if ($product_filter > 0) $pagination_url .= '&product_id=' . $product_filter;
                            if ($supplier_filter > 0) $pagination_url .= '&supplier_id=' . $supplier_filter;
                            if ($status_filter != -1) $pagination_url .= '&status=' . $status_filter;
                            $pagination_url .= '&';

                            $urlDatatable = pagination($pagination_url, $from, $total, $limit);
                            ?>
                            <?php if ($total > $limit): ?>
                                <div class="card-footer">
                                    <div class="row">
                                        <div class="col-sm-12 col-md-5">
                                            <p class="dataTables_info"><?= __('Showing'); ?> <?= $limit; ?> <?= __('of'); ?> <?= number_format($total); ?> <?= __('Results'); ?></p>
                                        </div>
                                        <div class="col-sm-12 col-md-7">
                                            <?= $urlDatatable; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fa-solid fa-exclamation-circle me-2"></i><?= __('Chưa có gói sản phẩm nào.'); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal sửa gói sản phẩm -->
<div class="modal fade" id="planModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="planModalTitle">
                    <i class="fa-solid fa-edit me-2"></i><?= __('Chỉnh sửa gói sản phẩm'); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="planForm" enctype="multipart/form-data">
                    <input type="hidden" id="plan_id" name="plan_id">
                    <input type="hidden" id="plan_product_id" name="product_id">

                    <div class="mb-3" id="plan_product_group">
                        <label class="form-label"><?= __('Sản phẩm:'); ?> <span class="text-danger">*</span></label>
                        <select class="form-select" id="plan_product_select" name="product_id" required>
                            <option value="0"><?= __('-- Không gắn sản phẩm --'); ?></option>
                            <?php foreach ($products_list as $prod): ?>
                                <option value="<?= $prod['id']; ?>"><?= htmlspecialchars(html_entity_decode($prod['name'], ENT_QUOTES, 'UTF-8')); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted"><?= __('Chọn sản phẩm để gắn gói này hoặc để trống nếu là gói API'); ?></small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?= __('Ảnh icon:'); ?></label>
                        <input type="file" class="form-control" id="plan_image" name="image" accept="image/*">
                        <small class="text-muted d-block"><?= __('Nếu không chọn, sẽ dùng ảnh của sản phẩm'); ?></small>
                        <small class="text-info d-block"><i class="fa-solid fa-circle-info me-1"></i><?= __('Khuyến nghị: 1000x500px (tỷ lệ 2:1), định dạng WEBP hoặc JPG'); ?></small>
                        <div id="plan_image_preview" class="mt-2" style="display: none;">
                            <img id="plan_image_preview_img" src="" alt="" style="max-width: 150px; max-height: 150px; border-radius: 4px; border: 1px solid #dee2e6;">
                            <button type="button" class="btn btn-sm btn-danger mt-2" onclick="removePlanImagePreview()">
                                <i class="fa-solid fa-times me-1"></i><?= __('Xóa ảnh'); ?>
                            </button>
                        </div>
                        <div id="plan_current_image" class="mt-2" style="display: none;">
                            <p class="text-muted mb-1"><?= __('Ảnh hiện tại:'); ?></p>
                            <img id="plan_current_image_img" src="" alt="" style="max-width: 150px; max-height: 150px; border-radius: 4px; border: 1px solid #dee2e6;">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?= __('Tên gói:'); ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="plan_name" name="name"
                            placeholder="<?= __('VD: Gói 1 tháng, Gói Premium...'); ?>" required>
                        <small class="text-muted"><?= __('Tên gói sẽ hiển thị cho khách hàng'); ?></small>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><?= __('Loại thời hạn:'); ?> <span class="text-danger">*</span></label>
                                <select class="form-select" id="plan_duration_type" name="duration_type" required onchange="toggleDurationValue()">
                                    <option value="day"><?= __('Ngày'); ?></option>
                                    <option value="month" selected><?= __('Tháng'); ?></option>
                                    <option value="year"><?= __('Năm'); ?></option>
                                    <option value="lifetime"><?= __('Vĩnh viễn'); ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3" id="duration_value_group">
                                <label class="form-label"><?= __('Số lượng:'); ?> <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="plan_duration_value" name="duration_value"
                                    value="1" min="1" required>
                                <small class="text-muted"><?= __('Số ngày/tháng/năm'); ?></small>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label"><?= __('Giá vốn:'); ?></label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="plan_cost_price" name="cost_price"
                                        value="0" inputmode="numeric" pattern="[0-9,]*">
                                    <span class="input-group-text"><?= getCurrencyNameDefault(); ?></span>
                                </div>
                                <small class="text-muted"><?= __('Giá nhập vào'); ?></small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label"><?= __('Giá bán lẻ:'); ?> <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="plan_price" name="price"
                                        value="0" inputmode="numeric" pattern="[0-9,]*" required>
                                    <span class="input-group-text"><?= getCurrencyNameDefault(); ?></span>
                                </div>
                                <small class="text-muted"><?= __('Giá bán cho khách hàng'); ?></small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label"><?= __('Giá khuyến mãi:'); ?></label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="plan_sale_price" name="sale_price"
                                        value="0" inputmode="numeric" pattern="[0-9,]*">
                                    <span class="input-group-text"><?= getCurrencyNameDefault(); ?></span>
                                </div>
                                <small class="text-muted"><?= __('Giá khuyến mãi (nếu có)'); ?></small>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?= __('Mô tả:'); ?></label>
                        <textarea class="form-control" id="plan_description" name="description" rows="5"
                            placeholder="<?= __('Nhập mô tả chi tiết về gói sản phẩm'); ?>"></textarea>
                        <small class="text-muted"><?= __('Mô tả này sẽ hiển thị cho khách hàng'); ?></small>
                    </div>

                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="plan_is_instant_input" name="is_instant" value="1">
                            <label class="form-check-label" for="plan_is_instant_input">
                                <i class="fa-solid fa-bolt me-1 text-warning"></i><?= __('Sản phẩm giao ngay'); ?>
                            </label>
                        </div>
                        <small class="text-muted"><?= __('Nếu bật, sản phẩm sẽ được giao ngay sau khi thanh toán. Nếu tắt, sẽ tạo đơn hàng để xử lý sau.'); ?></small>
                    </div>

                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="plan_status_input" name="status" value="1" checked>
                            <label class="form-check-label" for="plan_status_input">
                                <?= __('Kích hoạt gói này'); ?>
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fa-solid fa-times me-1"></i><?= __('Hủy'); ?>
                </button>
                <button type="button" class="btn btn-primary" onclick="savePlan()">
                    <i class="fa-solid fa-save me-1"></i><?= __('Lưu'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal quản lý trường tùy chỉnh của gói (gộp cả list và form) -->
<div class="modal fade" id="planFieldsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="planFieldsModalTitle">
                    <i class="fa-solid fa-list-check me-2"></i><?= __('Quản lý trường tùy chỉnh'); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body position-relative" style="min-height: 400px;">
                <!-- Loading state -->
                <div id="plan-fields-loading" class="text-center py-5" style="display: none;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden"><?= __('Đang tải...'); ?></span>
                    </div>
                    <p class="text-muted mt-3"><?= __('Đang tải dữ liệu...'); ?></p>
                </div>

                <!-- List View -->
                <div id="plan-fields-list-view" class="view-content" style="display: block;">
                    <div class="alert alert-info mb-3">
                        <i class="fa-solid fa-info-circle me-2"></i><?= __('Các trường này sẽ hiển thị khi khách hàng đặt hàng gói này. Kéo thả để sắp xếp thứ tự.'); ?>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0" id="planFieldsModalSubtitle"></h6>
                        <button type="button" class="btn btn-sm btn-primary" onclick="showPlanFieldForm()">
                            <i class="fa-solid fa-plus me-1"></i><?= __('Thêm trường'); ?>
                        </button>
                    </div>

                    <div id="plan-fields-content" class="table-responsive" style="display: none;">
                        <table class="table table-striped table-hover border text-nowrap">
                            <thead>
                                <tr>
                                    <th class="text-center"><i class="fa-solid fa-grip-vertical"></i></th>
                                    <th><?= __('Thao tác'); ?></th>
                                    <th><?= __('Nhãn hiển thị'); ?></th>
                                    <th><?= __('Field Key'); ?></th>
                                    <th><?= __('Loại'); ?></th>

                                    <th><?= __('Bắt buộc'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="sortable-plan-fields">
                                <!-- Sẽ được load bằng AJAX -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Empty state -->
                    <div id="plan-fields-empty" class="text-center py-5" style="display: none;">
                        <i class="fa-solid fa-inbox fa-3x text-muted mb-3"></i>
                        <p class="text-muted"><?= __('Chưa có trường nào'); ?></p>
                        <button type="button" class="btn btn-primary" onclick="showPlanFieldForm()">
                            <i class="fa-solid fa-plus me-1"></i><?= __('Thêm trường đầu tiên'); ?>
                        </button>
                    </div>
                </div>

                <!-- Form View -->
                <div id="plan-fields-form-view" class="view-content" style="display: none;">
                    <form id="planFieldForm">
                        <input type="hidden" id="plan_field_id" name="field_id">
                        <input type="hidden" id="plan_field_plan_id" name="plan_id">

                        <div class="mb-3">
                            <label class="form-label"><?= __('Nhãn hiển thị:'); ?> <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="plan_field_label" name="label"
                                placeholder="<?= __('VD: Link tài khoản Facebook'); ?>" required>
                            <small class="text-muted"><?= __('Nhãn này sẽ hiển thị cho khách hàng'); ?></small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><?= __('Field Key:'); ?> <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="plan_field_key" name="field_key"
                                placeholder="<?= __('VD: facebook_link'); ?>" required>
                            <small class="text-muted"><?= __('Tên kỹ thuật (không dấu, chữ thường, gạch dưới)'); ?></small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><?= __('Loại trường:'); ?> <span class="text-danger">*</span></label>
                            <select class="form-select" id="plan_field_type" name="type" required>
                                <option value="text"><?= __('Text - Văn bản ngắn'); ?></option>
                                <option value="email"><?= __('Email - Địa chỉ email'); ?></option>
                                <option value="password"><?= __('Password - Mật khẩu'); ?></option>
                                <option value="textarea"><?= __('Textarea - Văn bản dài'); ?></option>
                            </select>
                        </div>



                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="plan_field_required" name="is_required" value="1" checked>
                                <label class="form-check-label" for="plan_field_required">
                                    <?= __('Bắt buộc nhập'); ?>
                                </label>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="modal-footer" id="planFieldsModalFooter">
                <button type="button" class="btn btn-secondary" onclick="backToListView()" id="backToListBtn" style="display: none;">
                    <i class="fa-solid fa-arrow-left me-1"></i><?= __('Quay lại'); ?>
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="closeModalBtn">
                    <i class="fa-solid fa-times me-1"></i><?= __('Đóng'); ?>
                </button>
                <button type="button" class="btn btn-primary" onclick="savePlanField()" id="savePlanFieldBtn" style="display: none;">
                    <i class="fa-solid fa-save me-1"></i><?= __('Lưu'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Cập nhật nhanh hàng loạt -->
<div class="modal fade" id="bulkQuickUpdateModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <?= __('Cập nhật nhanh'); ?> <span class="badge bg-primary" id="bulkUpdateSelectedCount">0</span> <?= __('sản phẩm đã chọn'); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning mb-4">
                    <i class="fa-solid fa-info-circle me-2"></i>
                    <strong><?= __('Hướng dẫn:'); ?></strong> <?= __('Chỉ nhập vào các trường bạn muốn thay đổi. Để trống nếu muốn giữ nguyên giá trị hiện tại.'); ?>
                </div>

                <form id="bulkQuickUpdateForm">
                    <div class="row">
                        <!-- CỘT TRÁI -->
                        <div class="col-md-6">
                            <!-- Thông tin cơ bản -->
                            <div class="card mb-3">
                                <div class="card-header py-2">
                                    <i class="fa-solid fa-info-circle me-1"></i><?= __('Thông tin cơ bản'); ?>
                                </div>
                                <div class="card-body">
                                    <!-- Sản phẩm -->
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Sản phẩm'); ?></label>
                                        <select class="form-select" id="bulk_product_id" name="product_id">
                                            <option value=""><?= __('Giữ nguyên sản phẩm hiện tại'); ?></option>
                                            <option value="0"><?= __('-- Không gắn sản phẩm --'); ?></option>
                                            <?php
                                            $all_products = $CMSNT->get_list_safe("SELECT `id`, `name` FROM `products` ORDER BY `name` ASC", []);
                                            foreach ($all_products as $prod):
                                            ?>
                                                <option value="<?= $prod['id']; ?>"><?= htmlspecialchars(html_entity_decode($prod['name'], ENT_QUOTES, 'UTF-8')); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <!-- Trạng thái -->
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Trạng thái'); ?></label>
                                        <select class="form-select" id="bulk_status" name="status">
                                            <option value=""><?= __('Giữ nguyên trạng thái hiện tại'); ?></option>
                                            <option value="1"><?= __('Kích hoạt'); ?></option>
                                            <option value="0"><?= __('Tắt'); ?></option>
                                        </select>
                                    </div>

                                    <!-- Loại thời hạn -->
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Loại thời hạn'); ?></label>
                                        <select class="form-select" id="bulk_duration_type" name="duration_type" onchange="toggleBulkDurationValue()">
                                            <option value=""><?= __('Giữ nguyên thời hạn hiện tại'); ?></option>
                                            <option value="day"><?= __('Ngày'); ?></option>
                                            <option value="month"><?= __('Tháng'); ?></option>
                                            <option value="year"><?= __('Năm'); ?></option>
                                            <option value="lifetime"><?= __('Vĩnh viễn'); ?></option>
                                        </select>
                                    </div>

                                    <!-- Số lượng thời hạn -->
                                    <div class="mb-0" id="bulk_duration_value_group" style="display: none;">
                                        <label class="form-label"><?= __('Số lượng thời hạn'); ?></label>
                                        <input type="number" class="form-control" id="bulk_duration_value" name="duration_value" min="1" placeholder="<?= __('Nhập số ngày/tháng/năm'); ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- Quản lý giá -->
                            <div class="card mb-3">
                                <div class="card-header py-2">
                                    <i class="fa-solid fa-tags me-1"></i><?= __('Quản lý giá'); ?>
                                </div>
                                <div class="card-body">
                                    <!-- Giá bán lẻ -->
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Giá bán lẻ'); ?></label>
                                        <div class="input-group">
                                            <input type="text" class="form-control bulk-price-input" id="bulk_price" name="price" placeholder="<?= __('Nhập giá bán lẻ mới'); ?>">
                                            <span class="input-group-text"><?= getCurrencyNameDefault(); ?></span>
                                        </div>
                                        <small class="text-muted"><?= __('Nhập giá cố định mới hoặc để trống nếu muốn giữ nguyên'); ?></small>
                                    </div>

                                    <!-- Điều chỉnh giá theo % -->
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Điều chỉnh giá theo % (dựa trên giá vốn)'); ?></label>
                                        <div class="input-group">
                                            <select class="form-select" id="bulk_price_adjust_type" style="max-width: 120px;">
                                                <option value="increase"><?= __('Tăng'); ?></option>
                                                <option value="decrease"><?= __('Giảm'); ?></option>
                                            </select>
                                            <input type="number" class="form-control" id="bulk_price_adjust_percent" placeholder="<?= __('Nhập %'); ?>" min="0" max="1000" step="0.1">
                                            <span class="input-group-text">%</span>
                                        </div>
                                        <small class="text-muted"><?= __('Ví dụ: Giá vốn 100.000đ, tăng 20% = Giá bán 120.000đ'); ?></small>
                                    </div>

                                    <!-- Giảm giá -->
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Giảm giá (%)'); ?></label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" id="bulk_discount_percent" placeholder="<?= __('Nhập % giảm giá'); ?>" min="0" max="100" step="0.1">
                                            <span class="input-group-text">%</span>
                                        </div>
                                        <small class="text-muted"><?= __('Giá khuyến mãi = Giá bán - (Giá bán × %)'); ?></small>
                                    </div>

                                    <!-- Giá vốn -->
                                    <div class="mb-0">
                                        <label class="form-label"><?= __('Giá vốn'); ?></label>
                                        <div class="input-group">
                                            <input type="text" class="form-control bulk-price-input" id="bulk_cost_price" name="cost_price" placeholder="<?= __('Nhập giá vốn mới'); ?>">
                                            <span class="input-group-text"><?= getCurrencyNameDefault(); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- CỘT PHẢI -->
                        <div class="col-md-6">
                            <!-- Mô tả -->
                            <div class="card mb-3">
                                <div class="card-header py-2">
                                    <i class="fa-solid fa-align-left me-1"></i><?= __('Mô tả'); ?>
                                </div>
                                <div class="card-body">
                                    <div class="mb-0">
                                        <label class="form-label"><?= __('Mô tả ngắn'); ?></label>
                                        <textarea class="form-control" id="bulk_description" name="description" rows="5" placeholder="<?= __('Nhập mô tả ngắn mới cho sản phẩm...'); ?>"></textarea>
                                        <small class="text-muted"><?= __('Mô tả ngắn sẽ hiển thị trên danh sách sản phẩm'); ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <?= __('Đóng'); ?>
                </button>
                <button type="button" class="btn btn-primary" id="btnSubmitBulkQuickUpdate" onclick="submitBulkQuickUpdate()">
                    <i class="fa-solid fa-save me-1"></i><?= __('Cập Nhật'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<?php
require_once(__DIR__ . '/footer.php');
?>

<script>
    // Khởi tạo tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Modal instance
    var planModal;
    var planFieldsModal;
    var bulkQuickUpdateModal;
    var currentPlanId = null;
    var currentView = 'list';

    document.addEventListener('DOMContentLoaded', function() {
        planModal = new bootstrap.Modal(document.getElementById('planModal'));
        planFieldsModal = new bootstrap.Modal(document.getElementById('planFieldsModal'));
        bulkQuickUpdateModal = new bootstrap.Modal(document.getElementById('bulkQuickUpdateModal'));

        // Format price inputs trong bulk update modal
        document.querySelectorAll('.bulk-price-input').forEach(function(input) {
            input.addEventListener('input', function(e) {
                var value = this.value.replace(/[^\d]/g, '');
                this.value = value;
            });

            input.addEventListener('blur', function() {
                var value = this.value.replace(/[^\d]/g, '');
                if (value && parseInt(value) > 0) {
                    this.value = parseInt(value).toLocaleString('vi-VN');
                }
            });

            input.addEventListener('focus', function() {
                var value = this.value.replace(/[^\d]/g, '');
                this.value = value || '';
            });
        });
    });

    // Toggle duration value input
    function toggleDurationValue() {
        var durationType = $('#plan_duration_type').val();
        if (durationType === 'lifetime') {
            $('#duration_value_group').hide();
            $('#plan_duration_value').prop('required', false);
        } else {
            $('#duration_value_group').show();
            $('#plan_duration_value').prop('required', true);
        }
    }

    // Sửa gói
    function editPlan(planId) {
        $.ajax({
            url: "<?= BASE_URL("ajaxs/admin/view.php"); ?>",
            method: "POST",
            dataType: "JSON",
            data: {
                action: 'getProductPlan',
                id: planId
            },
            success: function(result) {
                if (result.status == 'success') {
                    document.getElementById('planModalTitle').innerHTML = '<i class="fa-solid fa-edit me-2"></i><?= __("Chỉnh sửa gói sản phẩm"); ?>';
                    document.getElementById('plan_id').value = result.data.id;
                    document.getElementById('plan_product_id').value = result.data.product_id;
                    $('#plan_product_select').val(result.data.product_id || 0);
                    document.getElementById('plan_name').value = result.data.name;
                    document.getElementById('plan_duration_type').value = result.data.duration_type;
                    document.getElementById('plan_duration_value').value = result.data.duration_value || 1;

                    // Format số cho các input giá
                    var formatNumber = function(num) {
                        return num ? parseInt(num).toLocaleString('vi-VN') : '0';
                    };
                    document.getElementById('plan_cost_price').value = formatNumber(result.data.cost_price || 0);
                    document.getElementById('plan_price').value = formatNumber(result.data.price || 0);
                    document.getElementById('plan_sale_price').value = formatNumber(result.data.sale_price || 0);

                    document.getElementById('plan_status_input').checked = result.data.status == 1;
                    document.getElementById('plan_is_instant_input').checked = (result.data.is_instant == 1 || result.data.is_instant == '1');

                    // Hiển thị ảnh hiện tại nếu có
                    if (result.data.image && result.data.image !== '') {
                        document.getElementById('plan_current_image_img').src = '<?= base_url(); ?>' + result.data.image;
                        document.getElementById('plan_current_image').style.display = 'block';
                    } else {
                        document.getElementById('plan_current_image').style.display = 'none';
                    }
                    document.getElementById('plan_image_preview').style.display = 'none';
                    document.getElementById('plan_image').value = '';

                    // Set CKEditor content - decode HTML entities first
                    var descriptionContent = result.data.description || '';
                    // Decode HTML entities để hiển thị đúng trong CKEditor
                    var tempDiv = document.createElement('div');
                    tempDiv.innerHTML = descriptionContent;
                    var decodedDescription = tempDiv.innerHTML;

                    if (typeof planDescriptionEditor !== 'undefined' && planDescriptionEditor) {
                        planDescriptionEditor.setData(decodedDescription);
                    } else {
                        $('#plan_description').val(decodedDescription);
                    }

                    // Kiểm tra nếu là gói API thì ẩn/hiện các trường phù hợp
                    var isApiPlan = result.data.supplier_id && result.data.supplier_id > 0;
                    if (isApiPlan) {
                        // Gói API: ẩn các trường không cần thiết
                        $('#plan_is_instant_input').closest('.mb-3').hide();
                        $('#plan_product_group').find('small').text('<?= __("Gói API - có thể gắn vào sản phẩm để hiển thị trên website"); ?>');
                    } else {
                        // Gói thường: hiện tất cả
                        $('#plan_is_instant_input').closest('.mb-3').show();
                        $('#plan_product_group').find('small').text('<?= __("Chọn sản phẩm để gắn gói này hoặc để trống nếu là gói API"); ?>');
                    }

                    toggleDurationValue();
                    planModal.show();
                } else {
                    showMessage(result.msg, 'error');
                }
            },
            error: function() {
                showMessage('<?= __("Đã xảy ra lỗi"); ?>', 'error');
            }
        });
    }

    // Lưu gói
    function savePlan() {
        // Lấy dữ liệu từ CKEditor
        var description = '';
        if (typeof planDescriptionEditor !== 'undefined' && planDescriptionEditor) {
            description = planDescriptionEditor.getData();
        } else {
            description = $('#plan_description').val();
        }

        // Lấy giá trị số từ các input đã format
        var getRawNumber = function(inputId) {
            var value = $('#' + inputId).val();
            return value ? parseInt(value.replace(/[^\d]/g, '')) || 0 : 0;
        };

        // Lấy product_id từ select (cho phép thay đổi)
        var selectedProductId = $('#plan_product_select').val() || 0;

        // Kiểm tra có file upload không
        var imageFile = document.getElementById('plan_image').files[0];
        var hasImage = imageFile !== undefined;

        // Tạo FormData nếu có ảnh, nếu không thì dùng object thông thường
        var formData;
        if (hasImage) {
            formData = new FormData();
            formData.append('action', 'updateProductPlan');
            formData.append('id', $('#plan_id').val());
            formData.append('product_id', selectedProductId);
            formData.append('name', $('#plan_name').val());
            formData.append('duration_type', $('#plan_duration_type').val());
            formData.append('duration_value', $('#plan_duration_value').val());
            formData.append('cost_price', getRawNumber('plan_cost_price'));
            formData.append('price', getRawNumber('plan_price'));
            formData.append('sale_price', getRawNumber('plan_sale_price'));
            formData.append('description', description);
            formData.append('is_instant', $('#plan_is_instant_input').is(':checked') ? 1 : 0);
            formData.append('status', $('#plan_status_input').is(':checked') ? 1 : 0);
            formData.append('image', imageFile);
            formData.append('csrf_token', getCSRFToken());
        } else {
            formData = {
                action: 'updateProductPlan',
                id: $('#plan_id').val(),
                product_id: selectedProductId,
                name: $('#plan_name').val(),
                duration_type: $('#plan_duration_type').val(),
                duration_value: $('#plan_duration_value').val(),
                cost_price: getRawNumber('plan_cost_price'),
                price: getRawNumber('plan_price'),
                sale_price: getRawNumber('plan_sale_price'),
                description: description,
                is_instant: $('#plan_is_instant_input').is(':checked') ? 1 : 0,
                status: $('#plan_status_input').is(':checked') ? 1 : 0,
                csrf_token: getCSRFToken()
            };
        }

        var nameValue = hasImage ? formData.get('name') : formData.name;
        var priceValue = hasImage ? formData.get('price') : formData.price;

        if (!nameValue || !priceValue) {
            showMessage('<?= __("Vui lòng điền đầy đủ thông tin bắt buộc"); ?>', 'error');
            return;
        }

        $.ajax({
            url: "<?= BASE_URL("ajaxs/admin/update.php"); ?>",
            method: "POST",
            dataType: "JSON",
            data: formData,
            processData: hasImage ? false : true,
            contentType: hasImage ? false : 'application/x-www-form-urlencoded',
            success: function(result) {
                if (result.status == 'success') {
                    showMessage(result.msg, 'success');
                    planModal.hide();
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showMessage(result.msg, 'error');
                }
            },
            error: function() {
                showMessage('<?= __("Đã xảy ra lỗi"); ?>', 'error');
            }
        });
    }

    // Xem preview ảnh khi chọn file
    document.addEventListener('DOMContentLoaded', function() {
        var planImageInput = document.getElementById('plan_image');
        if (planImageInput) {
            planImageInput.addEventListener('change', function(e) {
                var file = e.target.files[0];
                if (file) {
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        document.getElementById('plan_image_preview_img').src = e.target.result;
                        document.getElementById('plan_image_preview').style.display = 'block';
                        document.getElementById('plan_current_image').style.display = 'none';
                    };
                    reader.readAsDataURL(file);
                } else {
                    document.getElementById('plan_image_preview').style.display = 'none';
                }
            });
        }
    });

    // Xóa preview ảnh
    function removePlanImagePreview() {
        document.getElementById('plan_image').value = '';
        document.getElementById('plan_image_preview').style.display = 'none';
    }

    // Xóa gói
    function deletePlan(planId) {
        // Reset modal state
        $('#confirmSinglePlanDeleteCheckbox').prop('checked', false);
        $('#confirmSinglePlanDeleteButton').prop('disabled', true).data('plan-id', planId);
        $('#singlePlanDeleteInfo').html('<div class="text-center"><i class="fa-solid fa-spinner fa-spin"></i> <?= __("Đang tải..."); ?></div>');
        $('#singlePlanDeleteStockCount').text('...');
        $('#singlePlanDeleteFieldsCount').text('...');
        $('#singlePlanDeleteOrdersCount').text('...');

        // Show modal
        $('#confirmSinglePlanDeleteModal').modal('show');

        // Fetch plan details
        $.ajax({
            url: "<?= BASE_URL('ajaxs/admin/view.php'); ?>",
            type: 'POST',
            dataType: "JSON",
            data: {
                action: 'previewBulkDeletePlans',
                ids: [planId]
            },
            success: function(result) {
                if (result.status == 'success' && result.data.plans.length > 0) {
                    var plan = result.data.plans[0];
                    $('#singlePlanDeleteStockCount').text(plan.stock_count);
                    $('#singlePlanDeleteFieldsCount').text(result.data.total_fields);
                    $('#singlePlanDeleteOrdersCount').text(result.data.total_orders);

                    // Build plan info HTML
                    var html = '<div class="d-flex align-items-center p-2 bg-light rounded">';
                    if (plan.image) {
                        html += '<img src="' + plan.image + '" class="rounded me-3" style="width: 60px; height: 60px; object-fit: cover;">';
                    } else {
                        html += '<div class="bg-secondary-transparent rounded me-3 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;"><i class="fa-solid fa-box-open text-muted fs-4"></i></div>';
                    }
                    html += '<div>';
                    html += '<div class="fw-bold">' + plan.name + '</div>';
                    html += '<small class="text-muted">ID: ' + plan.id;
                    if (plan.product_name) {
                        html += ' • <i class="fa-solid fa-cube me-1"></i>' + plan.product_name;
                    }
                    html += '</small>';
                    html += '</div>';
                    html += '</div>';
                    $('#singlePlanDeleteInfo').html(html);
                } else {
                    $('#singlePlanDeleteInfo').html('<div class="text-danger"><?= __("Không tìm thấy gói"); ?></div>');
                }
            },
            error: function() {
                $('#singlePlanDeleteInfo').html('<div class="text-danger"><?= __("Không thể tải dữ liệu"); ?></div>');
            }
        });

        // Handle checkbox change
        $('#confirmSinglePlanDeleteCheckbox').off('change').on('change', function() {
            $('#confirmSinglePlanDeleteButton').prop('disabled', !$(this).prop('checked'));
        });

        // Handle confirm button click
        $('#confirmSinglePlanDeleteButton').off('click').on('click', function() {
            if (!$('#confirmSinglePlanDeleteCheckbox').prop('checked')) return;

            var planIdToDelete = $(this).data('plan-id');
            var $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-1"></i><?= __("Đang xóa..."); ?>');

            $.ajax({
                url: "<?= BASE_URL('ajaxs/admin/remove.php'); ?>",
                type: 'POST',
                dataType: "JSON",
                data: {
                    action: 'removeProductPlan',
                    id: planIdToDelete,
                    csrf_token: getCSRFToken()
                },
                success: function(result) {
                    $btn.prop('disabled', false).html('<i class="fa-solid fa-trash me-1"></i><?= __("Xóa gói"); ?>');

                    if (result.status == 'success') {
                        $('#confirmSinglePlanDeleteModal').modal('hide');
                        showMessage(result.msg, 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        showMessage(result.msg, 'error');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).html('<i class="fa-solid fa-trash me-1"></i><?= __("Xóa gói"); ?>');
                    showMessage('<?= __("Đã xảy ra lỗi"); ?>', 'error');
                }
            });
        });
    }

    // Cập nhật trạng thái gói
    function updatePlanStatus(id, status) {
        $.ajax({
            url: "<?= BASE_URL("ajaxs/admin/update.php"); ?>",
            method: "POST",
            dataType: "JSON",
            data: {
                action: 'updateProductPlanStatus',
                id: id,
                status: status,
                csrf_token: getCSRFToken()
            },
            success: function(result) {
                if (result.status == 'success') {
                    showMessage(result.msg, result.status);
                } else {
                    showMessage(result.msg, result.status);
                    // Khôi phục lại checkbox nếu lỗi
                    $('#plan_status' + id).prop('checked', !status);
                }
            },
            error: function() {
                showMessage('<?= __("Đã xảy ra lỗi"); ?>', 'error');
                $('#plan_status' + id).prop('checked', !status);
            }
        });
    }

    // ==================== BULK ACTIONS ====================

    // Chọn tất cả / Bỏ chọn tất cả
    function toggleSelectAll(checkbox) {
        $('.plan-checkbox').prop('checked', checkbox.checked);
        updateBulkButtons();
    }

    // Cập nhật hiển thị nút bulk action
    function updateBulkButtons() {
        var selectedCount = $('.plan-checkbox:checked').length;
        $('#selectedCount').text(selectedCount);

        if (selectedCount > 0) {
            $('#bulkActionsToolbar').removeClass('d-none');
            $('#btnBulkDelete, #btnBulkQuickUpdate').removeClass('d-none');
        } else {
            $('#bulkActionsToolbar').addClass('d-none');
            $('#btnBulkDelete, #btnBulkQuickUpdate').addClass('d-none');
        }

        // Cập nhật trạng thái checkbox "Chọn tất cả"
        var totalCheckboxes = $('.plan-checkbox').length;
        $('#selectAllPlans').prop('checked', selectedCount === totalCheckboxes && totalCheckboxes > 0);
    }

    // Lấy danh sách ID đã chọn
    function getSelectedPlanIds() {
        var selectedIds = [];
        $('.plan-checkbox:checked').each(function() {
            selectedIds.push(parseInt($(this).val()));
        });
        return selectedIds;
    }

    // Xóa hàng loạt gói
    function bulkDeletePlans() {
        var selectedIds = getSelectedPlanIds();

        if (selectedIds.length === 0) {
            showMessage('<?= __("Vui lòng chọn ít nhất một gói sản phẩm để xóa"); ?>', 'error');
            return;
        }

        // Reset modal state
        $('#confirmBulkPlanDeleteCheckbox').prop('checked', false);
        $('#confirmBulkPlanDeleteButton').prop('disabled', true);
        $('#bulkPlanDeleteCount').text(selectedIds.length);
        $('#bulkPlanDeleteStockCount').text('...');
        $('#bulkPlanDeleteFieldsCount').text('...');
        $('#bulkPlanDeleteOrdersCount').text('...');
        $('#bulkPlanDeleteList').html('<div class="text-center"><i class="fa-solid fa-spinner fa-spin"></i> <?= __("Đang tải..."); ?></div>');

        // Show modal
        $('#confirmBulkPlanDeleteModal').modal('show');

        // Fetch plans details
        $.ajax({
            url: "<?= BASE_URL('ajaxs/admin/view.php'); ?>",
            type: 'POST',
            dataType: "JSON",
            data: {
                action: 'previewBulkDeletePlans',
                ids: selectedIds
            },
            success: function(result) {
                if (result.status == 'success') {
                    $('#bulkPlanDeleteStockCount').text(result.data.total_stock);
                    $('#bulkPlanDeleteFieldsCount').text(result.data.total_fields);
                    $('#bulkPlanDeleteOrdersCount').text(result.data.total_orders);

                    // Build plans list HTML
                    var html = '<div class="row g-2" style="max-height: 250px; overflow-y: auto;">';
                    result.data.plans.forEach(function(plan) {
                        html += '<div class="col-md-6">';
                        html += '<div class="d-flex align-items-center p-2 bg-light rounded">';
                        if (plan.image) {
                            html += '<img src="' + plan.image + '" class="rounded me-2" style="width: 40px; height: 40px; object-fit: cover;">';
                        } else {
                            html += '<div class="bg-secondary-transparent rounded me-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;"><i class="fa-solid fa-box-open text-muted"></i></div>';
                        }
                        html += '<div class="flex-grow-1 min-width-0">';
                        html += '<div class="fw-bold text-truncate">' + plan.name + '</div>';
                        html += '<small class="text-muted">ID: ' + plan.id + ' • ' + plan.stock_count + ' stock';
                        if (plan.product_name) {
                            html += ' • ' + plan.product_name;
                        }
                        html += '</small>';
                        html += '</div>';
                        html += '</div>';
                        html += '</div>';
                    });
                    html += '</div>';
                    $('#bulkPlanDeleteList').html(html);
                } else {
                    $('#bulkPlanDeleteList').html('<div class="text-danger">' + result.msg + '</div>');
                }
            },
            error: function() {
                $('#bulkPlanDeleteList').html('<div class="text-danger"><?= __("Không thể tải dữ liệu"); ?></div>');
            }
        });

        // Handle checkbox change
        $('#confirmBulkPlanDeleteCheckbox').off('change').on('change', function() {
            $('#confirmBulkPlanDeleteButton').prop('disabled', !$(this).prop('checked'));
        });

        // Handle confirm button click
        $('#confirmBulkPlanDeleteButton').off('click').on('click', function() {
            if (!$('#confirmBulkPlanDeleteCheckbox').prop('checked')) return;

            var $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-1"></i><?= __("Đang xóa..."); ?>');

            $.ajax({
                url: "<?= BASE_URL('ajaxs/admin/remove.php'); ?>",
                type: 'POST',
                dataType: "JSON",
                data: {
                    action: 'bulkDeleteProductPlans',
                    ids: selectedIds,
                    csrf_token: getCSRFToken()
                },
                success: function(result) {
                    $btn.prop('disabled', false).html('<i class="fa-solid fa-trash me-1"></i><?= __("Xóa gói"); ?>');

                    if (result.status == 'success') {
                        $('#confirmBulkPlanDeleteModal').modal('hide');
                        showMessage(result.msg, 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        showMessage(result.msg, 'error');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).html('<i class="fa-solid fa-trash me-1"></i><?= __("Xóa gói"); ?>');
                    showMessage('<?= __("Đã xảy ra lỗi"); ?>', 'error');
                }
            });
        });
    }

    // ==================== BULK QUICK UPDATE ====================

    // Hiển thị modal cập nhật nhanh
    function showBulkQuickUpdateModal() {
        var selectedIds = getSelectedPlanIds();

        if (selectedIds.length === 0) {
            showMessage('<?= __("Vui lòng chọn ít nhất một gói sản phẩm"); ?>', 'error');
            return;
        }

        // Reset form
        $('#bulkQuickUpdateForm')[0].reset();
        $('#bulk_duration_value_group').hide();

        // Hiển thị số lượng
        $('#bulkUpdateSelectedCount').text(selectedIds.length);

        // Show modal
        bulkQuickUpdateModal.show();
    }

    // Toggle hiển thị duration value
    function toggleBulkDurationValue() {
        var durationType = $('#bulk_duration_type').val();
        if (durationType === '' || durationType === 'lifetime') {
            $('#bulk_duration_value_group').hide();
        } else {
            $('#bulk_duration_value_group').show();
        }
    }

    // Submit cập nhật nhanh hàng loạt
    function submitBulkQuickUpdate() {
        var selectedIds = getSelectedPlanIds();

        if (selectedIds.length === 0) {
            showMessage('<?= __("Vui lòng chọn ít nhất một gói sản phẩm"); ?>', 'error');
            return;
        }

        // Lấy giá trị raw từ các input giá
        var getRawPrice = function(inputId) {
            var value = $('#' + inputId).val();
            if (!value || value.trim() === '') return null;
            return parseInt(value.replace(/[^\d]/g, '')) || 0;
        };

        // Chuẩn bị data - chỉ gửi các field có giá trị
        var updateData = {
            action: 'bulkQuickUpdatePlans',
            ids: selectedIds,
            csrf_token: getCSRFToken(),
            fields: {}
        };

        // Sản phẩm
        var productId = $('#bulk_product_id').val();
        if (productId !== '') {
            updateData.fields.product_id = productId;
        }

        // Trạng thái
        var status = $('#bulk_status').val();
        if (status !== '') {
            updateData.fields.status = status;
        }

        // Loại thời hạn
        var durationType = $('#bulk_duration_type').val();
        if (durationType !== '') {
            updateData.fields.duration_type = durationType;
            if (durationType !== 'lifetime') {
                var durationValue = $('#bulk_duration_value').val();
                if (durationValue) {
                    updateData.fields.duration_value = durationValue;
                }
            } else {
                updateData.fields.duration_value = null;
            }
        }

        // Giá bán lẻ (cố định)
        var price = getRawPrice('bulk_price');
        if (price !== null) {
            updateData.fields.price = price;
        }

        // Điều chỉnh giá theo %
        var priceAdjustPercent = $('#bulk_price_adjust_percent').val();
        if (priceAdjustPercent && parseFloat(priceAdjustPercent) > 0) {
            updateData.fields.price_adjust_type = $('#bulk_price_adjust_type').val();
            updateData.fields.price_adjust_percent = parseFloat(priceAdjustPercent);
        }

        // Giảm giá %
        var discountPercent = $('#bulk_discount_percent').val();
        if (discountPercent && parseFloat(discountPercent) > 0) {
            updateData.fields.discount_percent = parseFloat(discountPercent);
        }

        // Giá vốn
        var costPrice = getRawPrice('bulk_cost_price');
        if (costPrice !== null) {
            updateData.fields.cost_price = costPrice;
        }

        // Mô tả
        var description = $('#bulk_description').val();
        if (description && description.trim() !== '') {
            updateData.fields.description = description;
        }

        // Kiểm tra có field nào để cập nhật không
        if (Object.keys(updateData.fields).length === 0) {
            showMessage('<?= __("Vui lòng nhập ít nhất một trường để cập nhật"); ?>', 'error');
            return;
        }

        // Xác nhận
        Swal.fire({
            title: "<?= __('Xác nhận cập nhật'); ?>",
            text: "<?= __('Bạn có chắc chắn muốn cập nhật'); ?> " + selectedIds.length + " <?= __('gói đã chọn?'); ?>",
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: "<?= __('Đồng ý'); ?>",
            cancelButtonText: "<?= __('Hủy'); ?>",
            customClass: {
                confirmButton: 'btn btn-primary me-2',
                cancelButton: 'btn btn-secondary'
            },
            buttonsStyling: false,
            showCloseButton: true
        }).then((result) => {
            if (result.isConfirmed) {
                var $btn = $('#btnSubmitBulkQuickUpdate');
                $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-1"></i><?= __("Đang cập nhật..."); ?>');

                $.ajax({
                    url: "<?= BASE_URL("ajaxs/admin/update.php"); ?>",
                    method: "POST",
                    dataType: "JSON",
                    data: updateData,
                    success: function(result) {
                        $btn.prop('disabled', false).html('<i class="fa-solid fa-save me-1"></i><?= __("Cập Nhật"); ?>');

                        if (result.status == 'success') {
                            showMessage(result.msg, 'success');
                            bulkQuickUpdateModal.hide();
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            showMessage(result.msg, 'error');
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).html('<i class="fa-solid fa-save me-1"></i><?= __("Cập Nhật"); ?>');
                        showMessage('<?= __("Đã xảy ra lỗi"); ?>', 'error');
                    }
                });
            }
        });
    }

    // Format số cho các input giá
    function formatPriceInput(inputId) {
        var input = document.getElementById(inputId);
        if (!input) return;

        input.addEventListener('input', function(e) {
            var value = this.value.replace(/[^\d,]/g, '');
            value = value.replace(/,/g, '');
            this.value = value;
        });

        input.addEventListener('focus', function() {
            var value = this.value.replace(/[^\d]/g, '');
            this.value = value || '0';
        });

        input.addEventListener('blur', function() {
            var value = this.value.replace(/[^\d]/g, '');
            if (value && parseInt(value) > 0) {
                var formatted = parseInt(value).toLocaleString('vi-VN');
                this.value = formatted;
            } else {
                this.value = '0';
            }
        });

        input.addEventListener('paste', function(e) {
            e.preventDefault();
            var paste = (e.clipboardData || window.clipboardData).getData('text');
            var value = paste.replace(/[^\d]/g, '');
            if (value) {
                this.value = value;
                this.dispatchEvent(new Event('input'));
            }
        });
    }

    // Áp dụng format cho các input giá
    document.addEventListener('DOMContentLoaded', function() {
        formatPriceInput('plan_cost_price');
        formatPriceInput('plan_price');
        formatPriceInput('plan_sale_price');
    });

    // ==================== PLAN FIELDS MANAGEMENT ====================

    // Quản lý trường của gói
    function managePlanFields(planId) {
        currentPlanId = planId;
        $('#plan_field_plan_id').val(planId);

        // Lấy tên gói để hiển thị (sau khi xóa cột sản phẩm, tên gói ở cột đầu tiên sau checkbox)
        var planName = $('tr[data-plan-id="' + planId + '"]').find('td:eq(1) strong').text();
        $('#planFieldsModalSubtitle').text(planName || '');

        // Đảm bảo list view được hiển thị
        $('#plan-fields-list-view').show();
        $('#plan-fields-form-view').hide();
        $('#plan-fields-loading').hide();

        // Reset footer buttons
        $('#backToListBtn').hide();
        $('#savePlanFieldBtn').hide();
        $('#closeModalBtn').show();

        // Cập nhật title
        $('#planFieldsModalTitle').html('<i class="fa-solid fa-list-check me-2"></i><?= __("Quản lý trường tùy chỉnh"); ?>');
        currentView = 'list';

        // Mở modal và load dữ liệu
        planFieldsModal.show();
        loadPlanFields(planId);
    }

    // Load danh sách fields của plan
    function loadPlanFields(planId) {
        $('#plan-fields-loading').show();
        $('#plan-fields-content').hide();
        $('#plan-fields-empty').hide();

        $.ajax({
            url: "<?= BASE_URL("ajaxs/admin/view.php"); ?>",
            method: "POST",
            dataType: "JSON",
            data: {
                action: 'getPlanFields',
                plan_id: planId
            },
            success: function(result) {
                $('#plan-fields-loading').hide();

                if (result.status == 'success' && result.data && result.data.length > 0) {
                    renderPlanFields(result.data);
                    initPlanFieldsSortable();
                } else {
                    $('#plan-fields-content').hide();
                    $('#plan-fields-empty').show();
                }
            },
            error: function() {
                $('#plan-fields-loading').hide();
                showMessage('<?= __("Đã xảy ra lỗi khi tải dữ liệu"); ?>', 'error');
            }
        });
    }

    // Render danh sách fields
    function renderPlanFields(fields) {
        if (!fields || fields.length === 0) {
            $('#plan-fields-content').hide();
            $('#plan-fields-empty').show();
            return;
        }

        var html = '';
        var typeConfig = {
            'text': {
                icon: 'fa-font',
                color: 'primary',
                label: '<?= __("Text"); ?>'
            },
            'email': {
                icon: 'fa-envelope',
                color: 'info',
                label: '<?= __("Email"); ?>'
            },
            'password': {
                icon: 'fa-lock',
                color: 'warning',
                label: '<?= __("Password"); ?>'
            },
            'textarea': {
                icon: 'fa-align-left',
                color: 'success',
                label: '<?= __("Textarea"); ?>'
            }
        };

        fields.forEach(function(field, index) {
            var typeInfo = typeConfig[field.type] || {
                icon: 'fa-question',
                color: 'secondary',
                label: field.type
            };
            var label = html_entity_decode(field.label);


            html += '<tr data-field-id="' + field.id + '" data-sort-order="' + field.sort_order + '" class="field-row">';
            html += '<td class="text-center align-middle handle-plan-field" style="cursor: move; width: 50px;">';
            html += '<i class="fa-solid fa-grip-vertical text-muted"></i>';
            html += '</td>';
            html += '<td class="text-center align-middle">';
            html += '<div class="btn-group btn-group-sm" role="group">';
            html += '<button onclick="editPlanField(' + field.id + ')" class="btn btn-outline-info" data-bs-toggle="tooltip" title="<?= __("Sửa"); ?>">';
            html += '<i class="fa-solid fa-edit"></i>';
            html += '</button>';
            html += '<button onclick="deletePlanField(' + field.id + ')" class="btn btn-outline-danger" data-bs-toggle="tooltip" title="<?= __("Xóa"); ?>">';
            html += '<i class="fa-solid fa-trash"></i>';
            html += '</button>';
            html += '</div>';
            html += '</td>';
            html += '<td class="align-middle">';
            html += '<strong class="d-block">' + escapeHtml(label) + '</strong>';
            html += '</td>';
            html += '<td class="align-middle">';
            html += '<code class="bg-light px-2 py-1 rounded">' + escapeHtml(field.field_key) + '</code>';
            html += '</td>';
            html += '<td class="align-middle">';
            html += '<span class="badge bg-' + typeInfo.color + ' bg-opacity-10 text-' + typeInfo.color + ' border border-' + typeInfo.color + ' border-opacity-25">';
            html += '<i class="fa-solid ' + typeInfo.icon + ' me-1"></i>' + typeInfo.label;
            html += '</span>';
            html += '</td>';

            html += '<td class="text-center align-middle">';
            if (field.is_required == 1) {
                html += '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">';
                html += '<i class="fa-solid fa-asterisk me-1"></i><?= __("Bắt buộc"); ?>';
                html += '</span>';
            } else {
                html += '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">';
                html += '<i class="fa-solid fa-circle-check me-1"></i><?= __("Tùy chọn"); ?>';
                html += '</span>';
            }
            html += '</td>';
            html += '</tr>';
        });

        $('#sortable-plan-fields').html(html);
        $('#plan-fields-content').show();
        $('#plan-fields-empty').hide();
        $('#plan-fields-list-view').show();

        // Re-init tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('#planFieldsModal [data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    // Decode HTML entities
    function html_entity_decode(str) {
        if (!str) return '';
        var map = {
            '&amp;': '&',
            '&lt;': '<',
            '&gt;': '>',
            '&quot;': '"',
            '&#039;': "'"
        };
        return str.replace(/&amp;|&lt;|&gt;|&quot;|&#039;/g, function(m) {
            return map[m];
        });
    }

    // Escape HTML
    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text ? text.replace(/[&<>"']/g, function(m) {
            return map[m];
        }) : '';
    }

    // Khởi tạo Sortable cho plan fields
    function initPlanFieldsSortable() {
        var sortableEl = document.getElementById('sortable-plan-fields');
        if (sortableEl && sortableEl.querySelectorAll('tr[data-field-id]').length > 0) {
            new Sortable(sortableEl, {
                handle: '.handle-plan-field',
                animation: 150,
                onEnd: function(evt) {
                    updatePlanFieldsOrder();
                }
            });
        }
    }

    // Chuyển sang list view
    function showListView() {
        currentView = 'list';
        $('#plan-fields-form-view').hide();
        $('#plan-fields-list-view').show();
        $('#backToListBtn').hide();
        $('#savePlanFieldBtn').hide();
        $('#closeModalBtn').show();
        $('#planFieldsModalTitle').html('<i class="fa-solid fa-list-check me-2"></i><?= __("Quản lý trường tùy chỉnh"); ?>');
    }

    // Chuyển sang form view
    function showFormView() {
        currentView = 'form';
        $('#plan-fields-list-view').hide();
        $('#plan-fields-form-view').show();
        $('#backToListBtn').show();
        $('#savePlanFieldBtn').show();
        $('#closeModalBtn').show();
    }

    // Quay lại list view
    function backToListView() {
        showListView();
        loadPlanFields(currentPlanId);
    }

    // Hiển thị form thêm field
    function showPlanFieldForm() {
        $('#planFieldForm')[0].reset();
        $('#plan_field_id').val('');
        $('#plan_field_required').prop('checked', true);
        $('#plan_field_type').val('text');
        $('#savePlanFieldBtn').prop('disabled', false).html('<i class="fa-solid fa-save me-1"></i><?= __("Lưu"); ?>');
        $('#planFieldsModalTitle').html('<i class="fa-solid fa-plus-circle me-2"></i><?= __("Thêm trường tùy chỉnh"); ?>');
        showFormView();
        setTimeout(function() {
            $('#plan_field_label').focus();
        }, 300);
    }

    // Sửa field
    function editPlanField(fieldId) {
        $.ajax({
            url: "<?= BASE_URL("ajaxs/admin/view.php"); ?>",
            method: "POST",
            dataType: "JSON",
            data: {
                action: 'getProductField',
                id: fieldId
            },
            beforeSend: function() {
                $('#savePlanFieldBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span><?= __("Đang tải..."); ?>');
            },
            success: function(result) {
                $('#savePlanFieldBtn').prop('disabled', false).html('<i class="fa-solid fa-save me-1"></i><?= __("Lưu"); ?>');

                if (result.status == 'success') {
                    var field = result.data;
                    $('#planFieldsModalTitle').html('<i class="fa-solid fa-edit me-2"></i><?= __("Chỉnh sửa trường"); ?>');
                    $('#plan_field_id').val(field.id);
                    $('#plan_field_label').val(html_entity_decode(field.label));
                    $('#plan_field_key').val(field.field_key);
                    $('#plan_field_type').val(field.type);

                    $('#plan_field_required').prop('checked', field.is_required == 1);
                    showFormView();
                    setTimeout(function() {
                        $('#plan_field_label').focus();
                    }, 300);
                } else {
                    showMessage(result.msg, 'error');
                }
            },
            error: function() {
                $('#savePlanFieldBtn').prop('disabled', false).html('<i class="fa-solid fa-save me-1"></i><?= __("Lưu"); ?>');
                showMessage('<?= __("Đã xảy ra lỗi"); ?>', 'error');
            }
        });
    }

    // Lưu field
    function savePlanField() {
        var formData = {
            action: $('#plan_field_id').val() ? 'updatePlanField' : 'addPlanField',
            id: $('#plan_field_id').val(),
            plan_id: $('#plan_field_plan_id').val(),
            label: $('#plan_field_label').val().trim(),
            field_key: $('#plan_field_key').val().trim(),
            type: $('#plan_field_type').val(),

            is_required: $('#plan_field_required').is(':checked') ? 1 : 0,
            csrf_token: getCSRFToken()
        };

        if (!formData.label) {
            showMessage('<?= __("Vui lòng nhập nhãn hiển thị"); ?>', 'error');
            $('#plan_field_label').focus();
            return;
        }

        if (!formData.field_key) {
            showMessage('<?= __("Vui lòng nhập Field Key"); ?>', 'error');
            $('#plan_field_key').focus();
            return;
        }

        var $btn = $('#savePlanFieldBtn');
        var originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span><?= __("Đang lưu..."); ?>');

        $.ajax({
            url: "<?= BASE_URL("ajaxs/admin/update.php"); ?>",
            method: "POST",
            dataType: "JSON",
            data: formData,
            success: function(result) {
                $btn.prop('disabled', false).html(originalHtml);

                if (result.status == 'success') {
                    showMessage(result.msg, 'success');
                    showListView();
                    loadPlanFields(currentPlanId);
                } else {
                    showMessage(result.msg, 'error');
                }
            },
            error: function() {
                $btn.prop('disabled', false).html(originalHtml);
                showMessage('<?= __("Đã xảy ra lỗi khi lưu"); ?>', 'error');
            }
        });
    }

    // Xóa field
    function deletePlanField(fieldId) {
        Swal.fire({
            title: "<?= __('Cảnh báo'); ?>",
            text: "<?= __('Bạn có chắc chắn muốn xóa trường này không?'); ?>",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: "<?= __('Đồng ý'); ?>",
            cancelButtonText: "<?= __('Hủy'); ?>",
            customClass: {
                confirmButton: 'btn btn-primary me-2',
                cancelButton: 'btn btn-secondary'
            },
            buttonsStyling: false,
            showCloseButton: true
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: "<?= BASE_URL('ajaxs/admin/remove.php'); ?>",
                    type: 'POST',
                    dataType: "JSON",
                    data: {
                        action: 'removeProductField',
                        id: fieldId,
                        csrf_token: getCSRFToken()
                    },
                    success: function(result) {
                        if (result.status == 'success') {
                            showMessage(result.msg, 'success');
                            loadPlanFields(currentPlanId);
                        } else {
                            showMessage(result.msg, 'error');
                        }
                    },
                    error: function() {
                        showMessage('<?= __("Đã xảy ra lỗi"); ?>', 'error');
                    }
                });
            }
        });
    }

    // Cập nhật thứ tự plan fields
    function updatePlanFieldsOrder() {
        var order = [];
        $('#sortable-plan-fields tr[data-field-id]').each(function(index) {
            var fieldId = $(this).data('field-id');
            if (fieldId) {
                order.push({
                    id: fieldId,
                    sort_order: index
                });
            }
        });

        $.ajax({
            url: "<?= BASE_URL("ajaxs/admin/update.php"); ?>",
            method: "POST",
            dataType: "JSON",
            data: {
                action: 'updatePlanFieldsOrder',
                order: JSON.stringify(order),
                csrf_token: getCSRFToken()
            },
            success: function(result) {
                if (result.status == 'success') {
                    showMessage(result.msg, 'success');
                } else {
                    showMessage(result.msg, 'error');
                }
            },
            error: function() {
                showMessage('<?= __("Đã xảy ra lỗi khi cập nhật thứ tự"); ?>', 'error');
            }
        });
    }

    // Tự động tạo field_key từ label
    $('#plan_field_label').on('input', function() {
        if (!$('#plan_field_id').val()) {
            var label = $(this).val();
            var key = label.toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/đ/g, 'd')
                .replace(/Đ/g, 'D')
                .replace(/ /g, '_')
                .replace(/[^\w_]+/g, '');
            $('#plan_field_key').val(key);
        }
    });

    // Khởi tạo CKEditor cho description
    var planDescriptionEditor;
    if (typeof CKEDITOR !== 'undefined') {
        planDescriptionEditor = CKEDITOR.replace("plan_description", {
            toolbar: [{
                    name: 'styles',
                    items: ['Format', 'Font', 'FontSize']
                },
                {
                    name: 'basicstyles',
                    items: ['Bold', 'Italic', 'Underline', 'Strike']
                },
                {
                    name: 'paragraph',
                    items: ['NumberedList', 'BulletedList', '-', 'JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock']
                },
                {
                    name: 'links',
                    items: ['Link', 'Unlink']
                },
                {
                    name: 'insert',
                    items: ['Image', 'Table', 'HorizontalRule', 'SpecialChar']
                },
                {
                    name: 'colors',
                    items: ['TextColor', 'BGColor']
                },
                {
                    name: 'tools',
                    items: ['Maximize', 'ShowBlocks', 'Source']
                }
            ],
            extraPlugins: 'image',
            language: 'vi',
            height: 300,
            resize_enabled: true,
            allowedContent: true,
            removeDialogTabs: 'image:advanced;image:Link'
        });
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    // Khởi tạo Select2 cho filter dropdown
    $(document).ready(function() {
        $('#filter_product_id').select2({
            placeholder: '<?= __("Tìm kiếm sản phẩm..."); ?>',
            allowClear: true,
            width: '100%',
            language: {
                noResults: function() {
                    return '<?= __("Không tìm thấy kết quả"); ?>';
                },
                searching: function() {
                    return '<?= __("Đang tìm..."); ?>';
                }
            }
        });

        $('#filter_supplier_id').select2({
            placeholder: '<?= __("Tìm kiếm nguồn API..."); ?>',
            allowClear: true,
            width: '100%',
            language: {
                noResults: function() {
                    return '<?= __("Không tìm thấy kết quả"); ?>';
                },
                searching: function() {
                    return '<?= __("Đang tìm..."); ?>';
                }
            }
        });

        // Khởi tạo Select2 cho modal dropdown
        $('#plan_product_select').select2({
            placeholder: '<?= __("Tìm kiếm sản phẩm..."); ?>',
            allowClear: true,
            width: '100%',
            dropdownParent: $('#planModal'),
            language: {
                noResults: function() {
                    return '<?= __("Không tìm thấy kết quả"); ?>';
                },
                searching: function() {
                    return '<?= __("Đang tìm..."); ?>';
                }
            }
        });
    });
</script>

<!-- Modal Xác nhận xóa gói đơn lẻ -->
<div class="modal fade" id="confirmSinglePlanDeleteModal" tabindex="-1" aria-labelledby="confirmSinglePlanDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger-transparent">
                <h5 class="modal-title text-danger" id="confirmSinglePlanDeleteModalLabel">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i><?= __('Xác nhận xóa gói'); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger mb-3">
                    <i class="fa-solid fa-exclamation-circle me-2"></i>
                    <strong><?= __('Cảnh báo:'); ?></strong> <?= __('Hành động này không thể hoàn tác!'); ?>
                </div>

                <!-- Thông tin gói -->
                <div class="mb-3" id="singlePlanDeleteInfo">
                    <div class="text-center"><i class="fa-solid fa-spinner fa-spin"></i> <?= __("Đang tải..."); ?></div>
                </div>

                <!-- Dữ liệu sẽ bị xóa -->
                <div class="card border-danger mb-3">
                    <div class="card-header bg-danger-transparent py-2">
                        <i class="fa-solid fa-trash-can me-2"></i><?= __('Dữ liệu sẽ bị xóa:'); ?>
                    </div>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fa-solid fa-key me-2 text-muted"></i><?= __('Tồn kho (Stock)'); ?></span>
                            <span class="badge bg-danger" id="singlePlanDeleteStockCount">...</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fa-solid fa-list-check me-2 text-muted"></i><?= __('Trường tùy chỉnh'); ?></span>
                            <span class="badge bg-warning" id="singlePlanDeleteFieldsCount">...</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fa-solid fa-receipt me-2 text-muted"></i><?= __('Đơn hàng liên quan'); ?></span>
                            <span class="badge bg-info" id="singlePlanDeleteOrdersCount">...</span>
                        </li>
                    </ul>
                </div>

                <!-- Xác nhận -->
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="confirmSinglePlanDeleteCheckbox">
                    <label class="form-check-label text-danger" for="confirmSinglePlanDeleteCheckbox">
                        <strong><?= __('Tôi hiểu rằng tất cả dữ liệu trên sẽ bị xóa vĩnh viễn'); ?></strong>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fa-solid fa-times me-1"></i><?= __('Hủy'); ?>
                </button>
                <button type="button" class="btn btn-danger" id="confirmSinglePlanDeleteButton" disabled>
                    <i class="fa-solid fa-trash me-1"></i><?= __('Xóa gói'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Xác nhận xóa hàng loạt gói -->
<div class="modal fade" id="confirmBulkPlanDeleteModal" tabindex="-1" aria-labelledby="confirmBulkPlanDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger-transparent">
                <h5 class="modal-title text-danger" id="confirmBulkPlanDeleteModalLabel">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i><?= __('Xác nhận xóa hàng loạt'); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger mb-3">
                    <i class="fa-solid fa-exclamation-circle me-2"></i>
                    <strong><?= __('Cảnh báo:'); ?></strong>
                    <?= __('Bạn sắp xóa'); ?> <strong id="bulkPlanDeleteCount">0</strong> <?= __('gói. Hành động này không thể hoàn tác!'); ?>
                </div>

                <!-- Tổng hợp dữ liệu -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="card border-danger text-center">
                            <div class="card-body py-2">
                                <div class="fs-4 text-danger fw-bold" id="bulkPlanDeleteStockCount">...</div>
                                <small class="text-muted"><i class="fa-solid fa-key me-1"></i><?= __('Tồn kho'); ?></small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-warning text-center">
                            <div class="card-body py-2">
                                <div class="fs-4 text-warning fw-bold" id="bulkPlanDeleteFieldsCount">...</div>
                                <small class="text-muted"><i class="fa-solid fa-list-check me-1"></i><?= __('Trường'); ?></small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-info text-center">
                            <div class="card-body py-2">
                                <div class="fs-4 text-info fw-bold" id="bulkPlanDeleteOrdersCount">...</div>
                                <small class="text-muted"><i class="fa-solid fa-receipt me-1"></i><?= __('Đơn hàng'); ?></small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Danh sách gói -->
                <div class="mb-3">
                    <h6 class="mb-2"><i class="fa-solid fa-list me-2"></i><?= __('Danh sách gói sẽ xóa:'); ?></h6>
                    <div id="bulkPlanDeleteList">
                        <div class="text-center"><i class="fa-solid fa-spinner fa-spin"></i> <?= __("Đang tải..."); ?></div>
                    </div>
                </div>

                <!-- Xác nhận -->
                <div class="form-check bg-danger-transparent p-3 rounded">
                    <input class="form-check-input" type="checkbox" id="confirmBulkPlanDeleteCheckbox">
                    <label class="form-check-label text-danger" for="confirmBulkPlanDeleteCheckbox">
                        <strong><?= __('Tôi hiểu rằng tất cả dữ liệu trên sẽ bị xóa vĩnh viễn và không thể khôi phục'); ?></strong>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fa-solid fa-times me-1"></i><?= __('Hủy'); ?>
                </button>
                <button type="button" class="btn btn-danger" id="confirmBulkPlanDeleteButton" disabled>
                    <i class="fa-solid fa-trash me-1"></i><?= __('Xóa gói'); ?>
                </button>
            </div>
        </div>
    </div>
</div>