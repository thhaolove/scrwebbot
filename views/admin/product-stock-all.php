<?php if (!defined('IN_SITE')) {
    die('The Request Not Found');
}
$body = [
    'title' => __('Quản lý kho hàng') . ' | ' . $CMSNT->site('title'),
    'desc'   => $CMSNT->site('description'),
    'keyword' => $CMSNT->site('keywords')
];
$body['header'] = '<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />';
$body['footer'] = '';
require_once(__DIR__ . '/../../models/is_admin.php');
require_once(__DIR__ . '/header.php');
require_once(__DIR__ . '/sidebar.php');
if (checkPermission($getUser['admin'], 'view_product_stock') != true) {
    $role_name = getRoleName('view_product_stock');
    die('<script type="text/javascript">if(!alert("' . sprintf(__('Bạn không có quyền %s'), $role_name) . '")){window.history.back();}</script>');
}

// Phân trang an toàn
$limit = isset($_GET['limit']) ? (validate_int($_GET['limit'], 1, 1000) ?: 20) : 20;
$page = isset($_GET['page']) ? (validate_int($_GET['page'], 1, 1000000) ?: 1) : 1;
$from = ($page - 1) * $limit;

// Biến giữ giá trị hiển thị lại
$product_filter = 0;
$plan_filter = 0;
$status_filter = -1;
$search = '';

// WHERE an toàn với prepared statements
$where_conditions = ["pp.`is_instant` = 1"];
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

// Lọc theo gói
if (!empty($_GET['plan_id'])) {
    $plan_filter_input = validate_int($_GET['plan_id'], 1);
    if ($plan_filter_input !== false) {
        $plan_filter = $plan_filter_input;
        $where_conditions[] = 'ps.`plan_id` = ?';
        $where_params[] = $plan_filter;
    } else {
        $plan_filter = isset($_GET['plan_id']) ? intval($_GET['plan_id']) : 0;
    }
}

// Lọc theo trạng thái
if (isset($_GET['status']) && $_GET['status'] !== '') {
    $status_filter_input = validate_int($_GET['status'], 0, 1);
    if ($status_filter_input !== false) {
        $status_filter = $status_filter_input;
        $where_conditions[] = 'ps.`status` = ?';
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
        $where_conditions[] = 'ps.`stock_value` LIKE ?';
        $where_params[] = '%' . $search . '%';
    } else {
        $search = isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '';
    }
}

$where_sql = implode(' AND ', $where_conditions);

// Đếm tổng số lượng
$total_query = "SELECT COUNT(*) as total FROM `product_stock` ps 
    INNER JOIN `product_plans` pp ON ps.`plan_id` = pp.`id` 
    WHERE " . $where_sql;
$total_result = $CMSNT->get_row_safe($total_query, $where_params);
$total = $total_result ? (int)$total_result['total'] : 0;
$total_pages = ceil($total / $limit);

// Lấy danh sách kho hàng với thông tin gói và sản phẩm
$stock_list = $CMSNT->get_list_safe("
    SELECT ps.*, pp.`name` as plan_name, pp.`product_id`, p.`name` as product_name 
    FROM `product_stock` ps 
    INNER JOIN `product_plans` pp ON ps.`plan_id` = pp.`id` 
    LEFT JOIN `products` p ON pp.`product_id` = p.`id`
    WHERE " . $where_sql . " 
    ORDER BY ps.`id` DESC 
    LIMIT ? OFFSET ?
", array_merge($where_params, [$limit, $from]));

// Đếm số lượng theo trạng thái
$total_available = $CMSNT->get_row_safe("
    SELECT COUNT(*) as total 
    FROM `product_stock` ps 
    INNER JOIN `product_plans` pp ON ps.`plan_id` = pp.`id` 
    WHERE pp.`is_instant` = 1 AND ps.`status` = 1
", []);
$total_sold = $CMSNT->get_row_safe("
    SELECT COUNT(*) as total 
    FROM `product_stock` ps 
    INNER JOIN `product_plans` pp ON ps.`plan_id` = pp.`id` 
    WHERE pp.`is_instant` = 1 AND ps.`status` = 0
", []);
$total_available_count = $total_available ? (int)$total_available['total'] : 0;
$total_sold_count = $total_sold ? (int)$total_sold['total'] : 0;

// Lấy danh sách sản phẩm và gói để filter
$products_list = $CMSNT->get_list_safe("SELECT DISTINCT p.`id`, p.`name` FROM `products` p INNER JOIN `product_plans` pp ON p.`id` = pp.`product_id` WHERE pp.`is_instant` = 1 ORDER BY p.`name` ASC", []);
$plans_list = [];
if ($product_filter > 0) {
    $plans_list = $CMSNT->get_list_safe("SELECT `id`, `name` FROM `product_plans` WHERE `product_id` = ? AND `is_instant` = 1 ORDER BY `name` ASC", [$product_filter]);
}

// Lấy tất cả gói instant để dropdown chuyển gói nhanh
$all_instant_plans = $CMSNT->get_list_safe("
    SELECT pp.`id`, pp.`name`, p.`name` as product_name 
    FROM `product_plans` pp 
    INNER JOIN `products` p ON pp.`product_id` = p.`id` 
    WHERE pp.`is_instant` = 1 
    ORDER BY p.`name` ASC, pp.`name` ASC
", []);
?>

<div class="main-content app-content">
    <div class="container-fluid">
        <!-- Tiêu đề trang -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <div class="d-flex align-items-center gap-3">
                <h1 class="page-name fw-semibold fs-18 mb-0">
                    <i class="fa-solid fa-warehouse me-1"></i><?= __('Quản lý kho hàng'); ?>
                </h1>
                <?php if (checkPermission($getUser['admin'], 'edit_product_stock')): ?>
                    <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#cleanupProductStockModal">
                        <i class="fa-solid fa-trash me-1"></i><?= __('Dọn dẹp'); ?>
                    </button>
                <?php endif; ?>
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
                                <p class="mb-0 text-muted"><?= __('Tổng số lượng'); ?></p>
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
                                <p class="mb-0 text-muted"><?= __('Còn hàng'); ?></p>
                                <h4 class="mb-0 fw-semibold text-info"><?= number_format($total_available_count); ?></h4>
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
                                <p class="mb-0 text-muted"><?= __('Đã bán'); ?></p>
                                <h4 class="mb-0 fw-semibold text-danger"><?= number_format($total_sold_count); ?></h4>
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
                    <input type="hidden" name="action" value="product-stock-all">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label"><?= __('Sản phẩm'); ?></label>
                            <select class="form-select" name="product_id" id="filter_product_id" onchange="loadPlans(this.value)">
                                <option value=""><?= __('Tất cả sản phẩm'); ?></option>
                                <?php foreach ($products_list as $prod): ?>
                                    <option value="<?= $prod['id']; ?>" <?= $product_filter == $prod['id'] ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars(html_entity_decode($prod['name'], ENT_QUOTES, 'UTF-8')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><?= __('Gói sản phẩm'); ?></label>
                            <select class="form-select" name="plan_id" id="filter_plan_id">
                                <option value=""><?= __('Tất cả gói'); ?></option>
                                <?php foreach ($plans_list as $plan): ?>
                                    <option value="<?= $plan['id']; ?>" <?= $plan_filter == $plan['id'] ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars(html_entity_decode($plan['name'], ENT_QUOTES, 'UTF-8')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label"><?= __('Tìm kiếm'); ?></label>
                            <input type="text" class="form-control" name="search"
                                value="<?= htmlspecialchars($search); ?>"
                                placeholder="<?= __('Giá trị kho hàng...'); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label"><?= __('Trạng thái'); ?></label>
                            <select class="form-select" name="status">
                                <option value="" <?= $status_filter == -1 ? 'selected' : ''; ?>><?= __('Tất cả'); ?></option>
                                <option value="1" <?= $status_filter == 1 ? 'selected' : ''; ?>><?= __('Còn hàng'); ?></option>
                                <option value="0" <?= $status_filter == 0 ? 'selected' : ''; ?>><?= __('Đã bán'); ?></option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label"><?= __('Số lượng/trang'); ?></label>
                            <select class="form-select" name="limit">
                                <option value="20" <?= $limit == 20 ? 'selected' : ''; ?>>20</option>
                                <option value="50" <?= $limit == 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?= $limit == 100 ? 'selected' : ''; ?>>100</option>
                            </select>
                        </div>
                        <div class="col-md-12 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fa-solid fa-filter me-1"></i><?= __('Lọc'); ?>
                            </button>
                            <a href="<?= base_url_admin('product-stock-all') ?>" class="btn btn-secondary">
                                <i class="fa-solid fa-times me-1"></i><?= __('Bỏ lọc'); ?>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Danh sách kho hàng -->
        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-body p-0">
                        <?php if (count($stock_list) > 0): ?>
                            <!-- Thanh công cụ hàng loạt -->
                            <div id="bulkActionsToolbar" class="card-footer bg-light border-bottom d-none">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="text-muted">
                                            <span id="selectedCount">0</span> <?= __('kho hàng đã chọn'); ?>
                                        </span>
                                    </div>
                                    <div class="btn-list">
                                        <button type="button" id="btnBulkUpdateStatus" class="btn btn-sm btn-primary d-none" onclick="showBulkUpdateStatusModal()">
                                            <i class="fa-solid fa-toggle-on me-1"></i><?= __('Cập nhật trạng thái'); ?>
                                        </button>
                                        <button type="button" id="btnBulkUpdatePlan" class="btn btn-sm btn-info d-none" onclick="showBulkUpdatePlanModal()">
                                            <i class="fa-solid fa-exchange-alt me-1"></i><?= __('Chuyển gói'); ?>
                                        </button>
                                        <button type="button" id="btnBulkDelete" class="btn btn-sm btn-danger d-none" onclick="bulkDeleteStocks()">
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
                                                <input type="checkbox" id="selectAll" class="form-check-input" onchange="toggleSelectAll(this)" style="transform: scale(1.3); cursor: pointer;">
                                            </th>
                                            <th><?= __('ID'); ?></th>
                                            <th><?= __('Gói sản phẩm'); ?></th>
                                            <th><?= __('Giá trị kho hàng'); ?></th>
                                            <th class="text-center"><?= __('Trạng thái'); ?></th>
                                            <th><?= __('Ngày tạo'); ?></th>
                                            <th><?= __('Ngày cập nhật'); ?></th>
                                            <th><?= __('Thao tác'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($stock_list as $stock): ?>
                                            <tr>
                                                <td class="text-center">
                                                    <input type="checkbox" class="form-check-input stock-checkbox" value="<?= $stock['id']; ?>" onchange="updateBulkButtons()" style="transform: scale(1.3); cursor: pointer;">
                                                </td>
                                                <td>
                                                    <code><?= $stock['id']; ?></code>
                                                </td>
                                                <td>
                                                    <a href="<?= base_url_admin('product-plans&product_id=' . $stock['product_id']); ?>" class="text-primary">
                                                        <?= htmlspecialchars(html_entity_decode($stock['plan_name'], ENT_QUOTES, 'UTF-8')); ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <textarea class="form-control form-control-sm" rows="2" readonly style="resize: none; background-color: #f8f9fa;"><?= htmlspecialchars($stock['stock_value']); ?></textarea>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($stock['status'] == 1): ?>
                                                        <span class="badge bg-success">
                                                            <i class="fa-solid fa-check-circle me-1"></i><?= __('Còn hàng'); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">
                                                            <i class="fa-solid fa-times-circle me-1"></i><?= __('Đã bán'); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= date('d/m/Y H:i:s', strtotime($stock['created_at'])); ?></td>
                                                <td><?= date('d/m/Y H:i:s', strtotime($stock['updated_at'])); ?></td>
                                                <td>
                                                    <div class="btn-list">
                                                        <a href="<?= base_url_admin('product-stock&plan_id=' . $stock['plan_id']); ?>" class="btn btn-sm btn-info">
                                                            <i class="fa-solid fa-eye me-1"></i><?= __('Xem'); ?>
                                                        </a>
                                                        <?php if ($stock['status'] == 1): ?>
                                                            <button onclick="editStock(<?= $stock['id']; ?>, <?= $stock['plan_id']; ?>)" class="btn btn-sm btn-warning">
                                                                <i class="fa-solid fa-edit me-1"></i><?= __('Sửa'); ?>
                                                            </button>
                                                        <?php endif; ?>
                                                        <button onclick="deleteStock(<?= $stock['id']; ?>)" class="btn btn-sm btn-danger">
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
                            $pagination_url = base_url_admin('product-stock-all');
                            $pagination_url .= '&limit=' . $limit;
                            if (!empty($search)) $pagination_url .= '&search=' . urlencode($search);
                            if ($product_filter > 0) $pagination_url .= '&product_id=' . $product_filter;
                            if ($plan_filter > 0) $pagination_url .= '&plan_id=' . $plan_filter;
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
                                <i class="fa-solid fa-exclamation-circle me-2"></i><?= __('Chưa có kho hàng nào.'); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal sửa kho hàng -->
<div class="modal fade" id="stockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="stockModalTitle">
                    <i class="fa-solid fa-edit me-2"></i><?= __('Chỉnh sửa kho hàng'); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="stockForm">
                    <input type="hidden" id="stock_id" name="stock_id">
                    <input type="hidden" id="stock_plan_id" name="plan_id">

                    <div class="mb-3">
                        <label class="form-label"><?= __('Giá trị kho hàng:'); ?> <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="stock_value" name="stock_value" rows="5"
                            placeholder="<?= __('VD: Tài khoản | mã kích hoạt | serial...'); ?>" required></textarea>
                        <small class="text-muted"><?= __('Nhập giá trị kho hàng'); ?></small>
                    </div>

                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="stock_status_input" name="status" value="1" checked>
                            <label class="form-check-label" for="stock_status_input">
                                <?= __('Trạng thái: Còn hàng'); ?>
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fa-solid fa-times me-1"></i><?= __('Hủy'); ?>
                </button>
                <button type="button" class="btn btn-primary" onclick="saveStock()">
                    <i class="fa-solid fa-save me-1"></i><?= __('Lưu'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<?php
require_once(__DIR__ . '/footer.php');
?>

<script>
    // Modal instance
    var stockModal;
    document.addEventListener('DOMContentLoaded', function() {
        stockModal = new bootstrap.Modal(document.getElementById('stockModal'));
    });

    // Load danh sách gói khi chọn sản phẩm
    function loadPlans(productId) {
        var planSelect = document.getElementById('filter_plan_id');
        if (!planSelect) {
            return;
        }

        planSelect.innerHTML = '<option value=""><?= __("Đang tải..."); ?></option>';
        planSelect.disabled = true;

        if (!productId || productId == '') {
            planSelect.innerHTML = '<option value=""><?= __("Tất cả gói"); ?></option>';
            planSelect.disabled = false;
            return;
        }

        $.ajax({
            url: "<?= BASE_URL("ajaxs/admin/view.php"); ?>",
            method: "POST",
            dataType: "JSON",
            data: {
                action: 'getProductPlans',
                product_id: productId
            },
            success: function(result) {
                planSelect.innerHTML = '<option value=""><?= __("Tất cả gói"); ?></option>';

                if (result.status == 'success' && result.data && result.data.length > 0) {
                    result.data.forEach(function(plan) {
                        var option = document.createElement('option');
                        option.value = plan.id;
                        option.textContent = plan.name;
                        planSelect.appendChild(option);
                    });
                } else {
                    var option = document.createElement('option');
                    option.value = '';
                    option.textContent = '<?= __("Không có gói nào"); ?>';
                    planSelect.appendChild(option);
                }
                planSelect.disabled = false;
            },
            error: function(xhr, status, error) {
                console.error('Lỗi khi tải danh sách gói:', error);
                planSelect.innerHTML = '<option value=""><?= __("Lỗi khi tải danh sách gói"); ?></option>';
                planSelect.disabled = false;
            }
        });
    }

    // Load plans khi trang load nếu đã có product_id được chọn
    $(document).ready(function() {
        var productId = document.getElementById('filter_product_id')?.value;
        if (productId && productId != '') {
            loadPlans(productId);
        }
    });

    // Sửa kho hàng
    function editStock(stockId, planId) {
        $.ajax({
            url: "<?= BASE_URL("ajaxs/admin/view.php"); ?>",
            method: "POST",
            dataType: "JSON",
            data: {
                action: 'getProductStock',
                id: stockId
            },
            success: function(result) {
                if (result.status == 'success') {
                    document.getElementById('stock_id').value = result.data.id;
                    document.getElementById('stock_plan_id').value = planId;
                    document.getElementById('stock_value').value = result.data.stock_value;
                    document.getElementById('stock_status_input').checked = result.data.status == 1;
                    stockModal.show();
                } else {
                    showMessage(result.msg, 'error');
                }
            },
            error: function() {
                showMessage('<?= __("Đã xảy ra lỗi"); ?>', 'error');
            }
        });
    }

    // Lưu kho hàng (chỉ dùng cho sửa)
    function saveStock() {
        var formData = {
            action: 'updateProductStock',
            id: $('#stock_id').val(),
            plan_id: $('#stock_plan_id').val(),
            stock_value: $('#stock_value').val().trim(),
            status: $('#stock_status_input').is(':checked') ? 1 : 0
        };

        if (!formData.stock_value) {
            showMessage('<?= __("Vui lòng nhập giá trị kho hàng"); ?>', 'error');
            return;
        }

        $.ajax({
            url: "<?= BASE_URL("ajaxs/admin/update.php"); ?>",
            method: "POST",
            dataType: "JSON",
            data: formData,
            success: function(result) {
                if (result.status == 'success') {
                    showMessage(result.msg, 'success');
                    stockModal.hide();
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

    // Xóa kho hàng
    function deleteStock(stockId) {
        Swal.fire({
            title: "<?= __('Cảnh báo'); ?>",
            text: "<?= __('Bạn có chắc chắn muốn xóa kho hàng này không?'); ?>",
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
                        action: 'removeProductStock',
                        id: stockId
                    },
                    success: function(result) {
                        if (result.status == 'success') {
                            showMessage(result.msg, 'success');
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
        });
    }

    // Chọn tất cả / Bỏ chọn tất cả
    function toggleSelectAll(checkbox) {
        $('.stock-checkbox').prop('checked', checkbox.checked);
        updateBulkButtons();
    }

    // Cập nhật hiển thị nút bulk action
    function updateBulkButtons() {
        var selectedCount = $('.stock-checkbox:checked').length;
        $('#selectedCount').text(selectedCount);

        if (selectedCount > 0) {
            $('#bulkActionsToolbar').removeClass('d-none');
            $('#btnBulkUpdateStatus').removeClass('d-none');
            $('#btnBulkUpdatePlan').removeClass('d-none');
            $('#btnBulkDelete').removeClass('d-none');
        } else {
            $('#bulkActionsToolbar').addClass('d-none');
            $('#btnBulkUpdateStatus').addClass('d-none');
            $('#btnBulkUpdatePlan').addClass('d-none');
            $('#btnBulkDelete').addClass('d-none');
        }

        // Cập nhật trạng thái checkbox "Chọn tất cả"
        var totalCheckboxes = $('.stock-checkbox').length;
        $('#selectAll').prop('checked', selectedCount === totalCheckboxes && totalCheckboxes > 0);
    }

    // Lấy danh sách ID đã chọn
    function getSelectedStockIds() {
        var selectedIds = [];
        $('.stock-checkbox:checked').each(function() {
            selectedIds.push(parseInt($(this).val()));
        });
        return selectedIds;
    }

    // Xóa các kho hàng đã chọn
    function bulkDeleteStocks() {
        var selectedIds = getSelectedStockIds();

        if (selectedIds.length === 0) {
            showMessage('<?= __("Vui lòng chọn ít nhất một kho hàng để xóa"); ?>', 'error');
            return;
        }

        Swal.fire({
            title: "<?= __('Cảnh báo'); ?>",
            text: "<?= __('Bạn có chắc chắn muốn xóa'); ?> " + selectedIds.length + " <?= __('kho hàng đã chọn không? Hành động này không thể hoàn tác.'); ?>",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: "<?= __('Đồng ý'); ?>",
            cancelButtonText: "<?= __('Hủy'); ?>",
            customClass: {
                confirmButton: 'btn btn-danger me-2',
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
                        action: 'removeProductStocks',
                        ids: JSON.stringify(selectedIds)
                    },
                    beforeSend: function() {
                        $('#btnBulkDelete').prop('disabled', true);
                        $('#btnBulkDelete').html('<i class="fa-solid fa-spinner fa-spin me-1"></i><?= __("Đang xóa..."); ?>');
                    },
                    success: function(result) {
                        $('#btnBulkDelete').prop('disabled', false);
                        $('#btnBulkDelete').html('<i class="fa-solid fa-trash me-1"></i><?= __("Xóa đã chọn"); ?>');

                        if (result.status == 'success') {
                            showMessage(result.msg, 'success');
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            showMessage(result.msg, 'error');
                        }
                    },
                    error: function() {
                        $('#btnBulkDelete').prop('disabled', false);
                        $('#btnBulkDelete').html('<i class="fa-solid fa-trash me-1"></i><?= __("Xóa đã chọn"); ?>');
                        showMessage('<?= __("Đã xảy ra lỗi"); ?>', 'error');
                    }
                });
            }
        });
    }

    // ===== BULK UPDATE FUNCTIONS =====

    // Hiển thị modal cập nhật trạng thái hàng loạt
    function showBulkUpdateStatusModal() {
        var selectedIds = getSelectedStockIds();
        if (selectedIds.length === 0) {
            showMessage('<?= __("Vui lòng chọn ít nhất một kho hàng"); ?>', 'error');
            return;
        }

        Swal.fire({
            title: "<?= __('Cập nhật trạng thái hàng loạt'); ?>",
            html: `
                <p class="text-muted mb-3"><?= __('Chọn trạng thái mới cho'); ?> <strong>${selectedIds.length}</strong> <?= __('kho hàng đã chọn'); ?></p>
                <select id="bulkStatusSelect" class="form-select">
                    <option value="1"><?= __('Còn hàng'); ?></option>
                    <option value="0"><?= __('Đã bán'); ?></option>
                </select>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: "<?= __('Cập nhật'); ?>",
            cancelButtonText: "<?= __('Hủy'); ?>",
            customClass: {
                confirmButton: 'btn btn-primary me-2',
                cancelButton: 'btn btn-secondary'
            },
            buttonsStyling: false,
            preConfirm: () => {
                return document.getElementById('bulkStatusSelect').value;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                bulkUpdateStatus(selectedIds, result.value);
            }
        });
    }

    // Xử lý AJAX cập nhật trạng thái hàng loạt
    function bulkUpdateStatus(ids, status) {
        $.ajax({
            url: "<?= BASE_URL('ajaxs/admin/update.php'); ?>",
            method: "POST",
            dataType: "JSON",
            data: {
                action: 'bulkUpdateProductStockStatus',
                ids: JSON.stringify(ids),
                status: status
            },
            beforeSend: function() {
                Swal.fire({
                    title: "<?= __('Đang xử lý...'); ?>",
                    text: "<?= __('Vui lòng chờ trong giây lát'); ?>",
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
            },
            success: function(result) {
                if (result.status == 'success') {
                    showMessage(result.msg, 'success');
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

    // Hiển thị modal chuyển gói hàng loạt
    function showBulkUpdatePlanModal() {
        var selectedIds = getSelectedStockIds();
        if (selectedIds.length === 0) {
            showMessage('<?= __("Vui lòng chọn ít nhất một kho hàng"); ?>', 'error');
            return;
        }

        // Cập nhật số lượng đã chọn trong modal
        $('#bulkPlanSelectedCount').text(selectedIds.length);

        // Lưu danh sách IDs vào hidden input
        $('#bulkPlanStockIds').val(JSON.stringify(selectedIds));

        // Reset Select2
        $('#bulkPlanSelect').val(null).trigger('change');

        // Mở modal
        var modal = new bootstrap.Modal(document.getElementById('bulkUpdatePlanModal'));
        modal.show();
    }

    // Submit form chuyển gói hàng loạt
    function submitBulkUpdatePlan() {
        var ids = JSON.parse($('#bulkPlanStockIds').val());
        var planId = $('#bulkPlanSelect').val();

        if (!planId) {
            showMessage('<?= __("Vui lòng chọn gói sản phẩm"); ?>', 'error');
            return;
        }

        bulkUpdatePlan(ids, planId);
    }

    // Xử lý AJAX chuyển gói hàng loạt
    function bulkUpdatePlan(ids, planId) {
        $.ajax({
            url: "<?= BASE_URL('ajaxs/admin/update.php'); ?>",
            method: "POST",
            dataType: "JSON",
            data: {
                action: 'bulkUpdateProductStockPlan',
                ids: JSON.stringify(ids),
                plan_id: planId
            },
            beforeSend: function() {
                $('#btnSubmitBulkPlan').prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-1"></i><?= __("Đang xử lý..."); ?>');
            },
            success: function(result) {
                $('#btnSubmitBulkPlan').prop('disabled', false).html('<i class="fa-solid fa-check me-1"></i><?= __("Chuyển gói"); ?>');

                if (result.status == 'success') {
                    // Đóng modal
                    bootstrap.Modal.getInstance(document.getElementById('bulkUpdatePlanModal')).hide();
                    showMessage(result.msg, 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showMessage(result.msg, 'error');
                }
            },
            error: function() {
                $('#btnSubmitBulkPlan').prop('disabled', false).html('<i class="fa-solid fa-check me-1"></i><?= __("Chuyển gói"); ?>');
                showMessage('<?= __("Đã xảy ra lỗi"); ?>', 'error');
            }
        });
    }

    // Khởi tạo Select2 khi modal mở
    $(document).ready(function() {
        $('#bulkUpdatePlanModal').on('shown.bs.modal', function() {
            $('#bulkPlanSelect').select2({
                dropdownParent: $('#bulkUpdatePlanModal'),
                placeholder: '<?= __("Tìm kiếm gói sản phẩm..."); ?>',
                allowClear: true,
                width: '100%'
            });
        });

        // Destroy Select2 khi modal đóng để tránh lỗi
        $('#bulkUpdatePlanModal').on('hidden.bs.modal', function() {
            if ($('#bulkPlanSelect').hasClass('select2-hidden-accessible')) {
                $('#bulkPlanSelect').select2('destroy');
            }
        });
    });
</script>

<!-- Modal chuyển gói hàng loạt -->
<div class="modal fade" id="bulkUpdatePlanModal" tabindex="-1" aria-labelledby="bulkUpdatePlanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulkUpdatePlanModalLabel">
                    <i class="fa-solid fa-exchange-alt text-info me-2"></i><?= __('Chuyển gói hàng loạt'); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="bulkPlanStockIds" value="">

                <div class="alert alert-info border-0 mb-3">
                    <i class="fa-solid fa-info-circle me-2"></i>
                    <?= __('Chọn gói mới cho'); ?> <strong id="bulkPlanSelectedCount">0</strong> <?= __('kho hàng đã chọn'); ?>
                </div>

                <div class="mb-3">
                    <label for="bulkPlanSelect" class="form-label fw-medium"><?= __('Gói sản phẩm'); ?> <span class="text-danger">*</span></label>
                    <select id="bulkPlanSelect" class="form-select" style="width: 100%;">
                        <option value=""><?= __('-- Chọn gói sản phẩm --'); ?></option>
                        <?php foreach ($all_instant_plans as $plan): ?>
                            <option value="<?= $plan['id']; ?>">
                                <?= htmlspecialchars(html_entity_decode($plan['product_name'] . ' - ' . $plan['name'], ENT_QUOTES, 'UTF-8')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fa-solid fa-times me-1"></i><?= __('Hủy'); ?>
                </button>
                <button type="button" id="btnSubmitBulkPlan" class="btn btn-info" onclick="submitBulkUpdatePlan()">
                    <i class="fa-solid fa-check me-1"></i><?= __('Chuyển gói'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal dọn dẹp kho hàng đã bán -->
<div class="modal fade" id="cleanupProductStockModal" tabindex="-1" aria-labelledby="cleanupProductStockModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cleanupProductStockModalLabel">
                    <i class="fa-solid fa-trash text-danger me-2"></i><?= __('Dọn dẹp kho hàng đã bán'); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning border-0">
                    <i class="fa-solid fa-exclamation-triangle me-2"></i>
                    <?= __('Lưu ý: Thao tác này sẽ xóa vĩnh viễn các kho hàng đã bán. Kho hàng còn bán sẽ được giữ nguyên.'); ?>
                </div>
                <div class="mb-3">
                    <label for="cleanupDaysProductStock" class="form-label fw-medium"><?= __('Xóa kho hàng đã bán cũ hơn'); ?></label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="cleanupDaysProductStock" value="30" min="1" max="365" placeholder="30">
                        <span class="input-group-text"><?= __('ngày'); ?></span>
                    </div>
                    <div class="form-text">
                        <i class="fa-solid fa-info-circle me-1"></i>
                        <?= __('Chỉ xóa các kho hàng có trạng thái "Đã bán" cũ hơn số ngày chỉ định.'); ?>
                    </div>
                </div>
                <div id="cleanupPreviewProductStock" class="d-none">
                    <div class="alert alert-info-transparent border mb-0">
                        <i class="fa-solid fa-file-list me-2"></i>
                        <span id="cleanupPreviewTextProductStock"></span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                    <i class="fa-solid fa-times me-1"></i><?= __('Hủy'); ?>
                </button>
                <button type="button" class="btn btn-danger" id="confirmCleanupBtnProductStock">
                    <i class="fa-solid fa-trash me-1"></i><?= __('Xóa kho hàng'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        var cleanupStockModal = document.getElementById('cleanupProductStockModal');
        var $cleanupDays = $('#cleanupDaysProductStock');
        var $cleanupPreview = $('#cleanupPreviewProductStock');
        var $cleanupPreviewText = $('#cleanupPreviewTextProductStock');
        var $confirmBtn = $('#confirmCleanupBtnProductStock');
        var previewTimeout = null;

        function updateStockPreview() {
            var days = parseInt($cleanupDays.val()) || 0;
            if (days < 1) {
                $cleanupPreview.addClass('d-none');
                return;
            }
            $.ajax({
                url: '<?= BASE_URL('ajaxs/admin/view.php'); ?>',
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'previewCleanupProductStock',
                    days: days
                },
                success: function(resp) {
                    if (resp.status === 'success') {
                        $cleanupPreviewText.text('<?= __('Sẽ xóa'); ?> ' + resp.count + ' <?= __('kho hàng đã bán'); ?>');
                        $cleanupPreview.removeClass('d-none');
                    } else {
                        $cleanupPreview.addClass('d-none');
                    }
                }
            });
        }

        $cleanupDays.on('input', function() {
            clearTimeout(previewTimeout);
            previewTimeout = setTimeout(updateStockPreview, 500);
        });

        if (cleanupStockModal) {
            cleanupStockModal.addEventListener('shown.bs.modal', function() {
                $cleanupDays.val(30).focus();
                updateStockPreview();
            });
            cleanupStockModal.addEventListener('hidden.bs.modal', function() {
                $cleanupPreview.addClass('d-none');
                $confirmBtn.prop('disabled', false).html('<i class="fa-solid fa-trash me-1"></i><?= __('Xóa kho hàng'); ?>');
            });
        }

        $confirmBtn.on('click', function() {
            var days = parseInt($cleanupDays.val()) || 0;
            if (days < 1) {
                Swal.fire({
                    icon: 'warning',
                    title: '<?= __('Cảnh báo'); ?>',
                    text: '<?= __('Vui lòng nhập số ngày hợp lệ'); ?>'
                });
                return;
            }
            Swal.fire({
                icon: 'warning',
                title: '<?= __('Xác nhận xóa'); ?>',
                text: '<?= __('Bạn có chắc chắn muốn xóa tất cả kho hàng đã bán cũ hơn'); ?> ' + days + ' <?= __('ngày?'); ?>',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<?= __('Xóa'); ?>',
                cancelButtonText: '<?= __('Hủy'); ?>'
            }).then(function(result) {
                if (result.isConfirmed) {
                    $confirmBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span><?= __('Đang xóa...'); ?>');
                    $.ajax({
                        url: '<?= BASE_URL('ajaxs/admin/remove.php'); ?>',
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'cleanupProductStock',
                            days: days
                        },
                        success: function(resp) {
                            if (resp.status === 'success') {
                                Swal.fire({
                                    icon: 'success',
                                    title: '<?= __('Thành công'); ?>',
                                    text: resp.msg,
                                    timer: 2000,
                                    showConfirmButton: false
                                }).then(function() {
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: '<?= __('Lỗi'); ?>',
                                    text: resp.msg
                                });
                                $confirmBtn.prop('disabled', false).html('<i class="fa-solid fa-trash me-1"></i><?= __('Xóa kho hàng'); ?>');
                            }
                        },
                        error: function() {
                            Swal.fire({
                                icon: 'error',
                                title: '<?= __('Lỗi'); ?>',
                                text: '<?= __('Không thể kết nối đến server'); ?>'
                            });
                            $confirmBtn.prop('disabled', false).html('<i class="fa-solid fa-trash me-1"></i><?= __('Xóa kho hàng'); ?>');
                        }
                    });
                }
            });
        });
    });
</script>

<!-- Select2 JS Library -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    // Khởi tạo Select2 cho filter dropdown
    $(document).ready(function() {
        // Select2 cho sản phẩm
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

        // Select2 cho gói sản phẩm
        $('#filter_plan_id').select2({
            placeholder: '<?= __("Tìm kiếm gói..."); ?>',
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

        // Bắt sự kiện change của Select2 sản phẩm để load gói
        $('#filter_product_id').on('select2:select select2:clear', function(e) {
            loadPlans($(this).val());
        });
    });

    // Override loadPlans để refresh Select2 sau khi load
    var originalLoadPlans = loadPlans;
    loadPlans = function(productId) {
        var planSelect = document.getElementById('filter_plan_id');
        if (!planSelect) {
            return;
        }

        // Hủy Select2 trước khi cập nhật
        if ($('#filter_plan_id').hasClass('select2-hidden-accessible')) {
            $('#filter_plan_id').select2('destroy');
        }

        planSelect.innerHTML = '<option value=""><?= __("\u0110ang tải..."); ?></option>';
        planSelect.disabled = true;

        if (!productId || productId == '') {
            planSelect.innerHTML = '<option value=""><?= __("Tất cả gói"); ?></option>';
            planSelect.disabled = false;
            // Khởi tạo lại Select2
            initPlanSelect2();
            return;
        }

        $.ajax({
            url: "<?= BASE_URL("ajaxs/admin/view.php"); ?>",
            method: "POST",
            dataType: "JSON",
            data: {
                action: 'getProductPlans',
                product_id: productId
            },
            success: function(result) {
                planSelect.innerHTML = '<option value=""><?= __("Tất cả gói"); ?></option>';

                if (result.status == 'success' && result.data && result.data.length > 0) {
                    result.data.forEach(function(plan) {
                        var option = document.createElement('option');
                        option.value = plan.id;
                        option.textContent = plan.name;
                        planSelect.appendChild(option);
                    });
                } else {
                    var option = document.createElement('option');
                    option.value = '';
                    option.textContent = '<?= __("Không có gói nào"); ?>';
                    planSelect.appendChild(option);
                }
                planSelect.disabled = false;
                // Khởi tạo lại Select2
                initPlanSelect2();
            },
            error: function(xhr, status, error) {
                console.error('Lỗi khi tải danh sách gói:', error);
                planSelect.innerHTML = '<option value=""><?= __("Lỗi khi tải danh sách gói"); ?></option>';
                planSelect.disabled = false;
                initPlanSelect2();
            }
        });
    };

    // Hàm khởi tạo Select2 cho plan dropdown
    function initPlanSelect2() {
        $('#filter_plan_id').select2({
            placeholder: '<?= __("Tìm kiếm gói..."); ?>',
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
    }
</script>