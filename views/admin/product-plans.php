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

// Lấy product_id từ URL
$product_id = isset($_GET['product_id']) ? validate_int($_GET['product_id'], 1) : 0;
if (!$product_id) {
    die('<script type="text/javascript">if(!alert("' . __('Product ID không hợp lệ') . '")){window.history.back();}</script>');
}

// Lấy thông tin sản phẩm
$product = $CMSNT->get_row_safe("SELECT * FROM `products` WHERE `id` = ?", [$product_id]);
if (!$product) {
    die('<script type="text/javascript">if(!alert("' . __('Sản phẩm không tồn tại') . '")){window.history.back();}</script>');
}

// Lấy danh sách tất cả sản phẩm để hiển thị trong modal Select2
$all_products = $CMSNT->get_list_safe("SELECT `id`, `name` FROM `products` ORDER BY `name` ASC", []);

// Lấy danh sách gói (JOIN với suppliers để lấy thông tin nguồn API)
$product_plans = $CMSNT->get_list_safe("
    SELECT pp.*, s.`domain` as supplier_domain, s.`type` as supplier_type 
    FROM `product_plans` pp 
    LEFT JOIN `suppliers` s ON pp.`supplier_id` = s.`id` 
    WHERE pp.`product_id` = ? 
    ORDER BY pp.`sort_order` ASC, pp.`id` ASC
", [$product_id]);

// Đếm số đơn hàng đã bán cho mỗi gói (tất cả đơn hàng, không phân biệt trạng thái)
$plan_orders_count = [];
if (count($product_plans) > 0) {
    $plan_ids = array_column($product_plans, 'id');
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
?>

<div class="main-content app-content">
    <div class="container-fluid">
        <!-- Tiêu đề trang -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <div>
                <h1 class="page-name fw-semibold fs-18 mb-0">
                    <i class="fa-solid fa-box-open me-1"></i><?= __('Quản lý gói sản phẩm'); ?> <strong class="text-danger"><?= htmlspecialchars(html_entity_decode($product['name'], ENT_QUOTES, 'UTF-8')); ?></strong>
                </h1>
            </div>
            <div class="ms-md-1 ms-0">
                <button type="button" class="btn btn-primary btn-sm me-2" onclick="showAddPlanModal()">
                    <i class="fa-solid fa-plus me-1"></i><?= __('Thêm gói'); ?>
                </button>
                <a href="<?= base_url_admin('products'); ?>" class="btn btn-secondary btn-sm">
                    <i class="fa-solid fa-arrow-left me-1"></i><?= __('Quay lại'); ?>
                </a>
            </div>
        </div>

        <!-- Danh sách gói -->
        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-body p-0">
                        <?php if (count($product_plans) > 0): ?>
                            <!-- Thanh công cụ hàng loạt -->
                            <div id="bulkActionsToolbar" class="card-footer bg-light border-bottom d-none">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="text-muted">
                                            <span id="selectedCount">0</span> <?= __('gói đã chọn'); ?>
                                        </span>
                                    </div>
                                    <div class="btn-list">
                                        <button type="button" id="btnBulkStatusOn" class="btn btn-sm btn-success d-none" onclick="bulkUpdatePlanStatus(1)">
                                            <i class="fa-solid fa-toggle-on me-1"></i><?= __('Bật đã chọn'); ?>
                                        </button>
                                        <button type="button" id="btnBulkStatusOff" class="btn btn-sm btn-warning d-none" onclick="bulkUpdatePlanStatus(0)">
                                            <i class="fa-solid fa-toggle-off me-1"></i><?= __('Tắt đã chọn'); ?>
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
                                                <input type="checkbox" id="selectAllPlans" class="form-check-input" onchange="toggleSelectAll(this)" title="<?= __('Chọn tất cả'); ?>" style="transform: scale(1.3); cursor: pointer;">
                                            </th>
                                            <th class="text-center" style="width: 50px;"><i class="fa-solid fa-grip-vertical"></i></th>
                                            <th><?= __('Tên gói'); ?></th>
                                            <th><?= __('Nguồn API'); ?></th>
                                            <th><?= __('Thời hạn'); ?></th>
                                            <th><?= __('Giá'); ?></th>
                                            <th class="text-center"><?= __('Loại'); ?></th>
                                            <th class="text-center"><?= __('Trạng thái'); ?></th>
                                            <th class="text-center"><?= __('Số đơn hàng đã bán'); ?></th>
                                            <th><?= __('Trường'); ?></th>
                                            <th><?= __('Thao tác'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="sortable-plans">
                                        <?php foreach ($product_plans as $plan): ?>
                                            <tr data-plan-id="<?= $plan['id']; ?>" data-sort-order="<?= $plan['sort_order']; ?>">
                                                <td class="text-center">
                                                    <input type="checkbox" class="form-check-input plan-checkbox" value="<?= $plan['id']; ?>" onchange="updateBulkButtons()" style="transform: scale(1.3); cursor: pointer;">
                                                </td>
                                                <td class="text-center handle-plan" style="cursor: move;">
                                                    <i class="fa-solid fa-grip-vertical"></i>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php
                                                        $plan_image = isset($plan['image']) && !empty($plan['image']) ? $plan['image'] : (isset($product['image']) && !empty($product['image']) ? $product['image'] : '');
                                                        if ($plan_image):
                                                        ?>
                                                            <img src="<?= base_url($plan_image); ?>" alt="" class="me-2" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px; border: 1px solid #dee2e6;">
                                                        <?php endif; ?>
                                                        <strong><?= htmlspecialchars(html_entity_decode($plan['name'], ENT_QUOTES, 'UTF-8')); ?></strong>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if (!empty($plan['supplier_domain'])): ?>
                                                        <span class="badge bg-primary-transparent text-primary border border-primary border-opacity-25">
                                                            <i class="fa-solid fa-plug me-1"></i><?= htmlspecialchars($plan['supplier_domain'] ?? ''); ?>
                                                        </span>
                                                        <small class="d-block text-muted"><?= $plan['supplier_type']; ?></small>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
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
                                                    $plan_fields = $CMSNT->get_list("SELECT `field_key`, `type` FROM `product_fields` WHERE `plan_id` = '" . $plan['id'] . "' ORDER BY `sort_order` ASC");
                                                    if ($plan_fields && count($plan_fields) > 0) {
                                                        $typeConfig = [
                                                            'text' => ['color' => 'primary', 'icon' => 'fa-font'],
                                                            'email' => ['color' => 'info', 'icon' => 'fa-envelope'],
                                                            'password' => ['color' => 'warning', 'icon' => 'fa-lock'],
                                                            'textarea' => ['color' => 'success', 'icon' => 'fa-align-left']
                                                        ];
                                                        foreach ($plan_fields as $field) {
                                                            $config = $typeConfig[$field['type']] ?? ['color' => 'secondary', 'icon' => 'fa-question'];
                                                            echo '<span class="badge bg-' . $config['color'] . '-transparent me-1 mb-1"><i class="fa-solid ' . $config['icon'] . ' me-1"></i>' . htmlspecialchars($field['field_key']) . '</span>';
                                                        }
                                                    } else {
                                                        echo '<span class="text-muted">-</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $is_api_plan = !empty($plan['supplier_id']);
                                                    ?>
                                                    <div class="btn-list">
                                                        <?php if (!$is_api_plan && isset($plan['is_instant']) && (int)$plan['is_instant'] == 1): ?>
                                                            <button onclick="managePlanStock(<?= $plan['id']; ?>)" class="btn btn-sm btn-success">
                                                                <i class="fa-solid fa-warehouse me-1"></i><?= __('Quản lý kho hàng'); ?>
                                                            </button>
                                                        <?php endif; ?>
                                                        <?php if (!$is_api_plan): ?>
                                                            <button onclick="managePlanFields(<?= $plan['id']; ?>)" class="btn btn-sm btn-warning">
                                                                <i class="fa-solid fa-list-check me-1"></i><?= __('Quản lý trường'); ?>
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
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fa-solid fa-exclamation-circle me-2"></i><?= __('Chưa có gói sản phẩm nào. Nhấn "Thêm gói" để tạo.'); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal thêm/sửa gói sản phẩm -->
<div class="modal fade" id="planModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="planModalTitle">
                    <i class="fa-solid fa-box me-2"></i><?= __('Thêm gói sản phẩm'); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="planForm" enctype="multipart/form-data">
                    <input type="hidden" id="plan_id" name="plan_id">
                    <input type="hidden" id="plan_product_id" name="product_id" value="<?= $product['id']; ?>">

                    <!-- Select Product with Select2 - only shown when adding new plan -->
                    <div class="mb-3" id="plan_product_group" style="display: none;">
                        <label class="form-label"><?= __('Sản phẩm:'); ?> <span class="text-danger">*</span></label>
                        <select class="form-select" id="plan_product_select" name="product_id_select" style="width: 100%;">
                            <?php foreach ($all_products as $prod): ?>
                                <option value="<?= $prod['id']; ?>" <?= $prod['id'] == $product['id'] ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars(html_entity_decode($prod['name'], ENT_QUOTES, 'UTF-8')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted"><?= __('Chọn sản phẩm muốn thêm gói'); ?></small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?= __('Ảnh icon:'); ?></label>

                        <!-- Image Preview -->
                        <div id="plan-image-preview-container" class="mb-2" style="display: none;">
                            <img id="plan_image_preview_img" src="" alt="<?= __('Ảnh gói sản phẩm'); ?>" class="img-thumbnail" style="max-width: 200px; max-height: 200px;">
                            <button type="button" class="btn btn-sm btn-danger ms-2" onclick="clearPlanImage()">
                                <i class="fa-solid fa-times"></i>
                            </button>
                        </div>

                        <!-- Hidden input for library image path -->
                        <input type="hidden" name="image_path" id="plan-image-path" value="">

                        <div class="d-flex gap-2 align-items-start">
                            <!-- Upload file option -->
                            <div class="flex-grow-1">
                                <input type="file" class="form-control" name="image" id="plan_image"
                                    accept="image/png,image/jpeg,image/jpg,image/gif,image/svg,image/webp"
                                    onchange="previewPlanImage(this)">
                            </div>

                            <!-- Browse Library button -->
                            <button type="button" class="btn btn-outline-primary" onclick="openPlanFileManager()">
                                <i class="fa-solid fa-folder-open me-1"></i><?= __('Thư viện'); ?>
                            </button>
                        </div>

                        <small class="text-muted d-block mt-1"><?= __('Upload ảnh mới hoặc chọn từ thư viện. Nếu không chọn, sẽ dùng ảnh của sản phẩm.'); ?></small>
                        <small class="text-info d-block"><i class="fa-solid fa-circle-info me-1"></i><?= __('Khuyến nghị: 1000x500px (tỷ lệ 2:1), định dạng WEBP hoặc JPG'); ?></small>
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

<?php
require_once(__DIR__ . '/footer.php');
?>

<!-- Fix CKEditor dialog z-index khi nằm trong Bootstrap Modal -->
<style>
    /* Container phải cao nhất để tạo stacking context */
    .cke_dialog_container {
        z-index: 20000 !important;
        position: fixed !important;
    }

    /* Background cover ngay dưới container */
    .cke_dialog_background_cover {
        z-index: 19999 !important;
    }

    /* Dialog element */
    .cke_dialog {
        z-index: 20001 !important;
    }
</style>

<script>
    // Fix CKEditor dialog focus trong Bootstrap Modal
    (function() {
        // 1. Capturing phase listener - chặn focus theft TRƯỚC khi Bootstrap can thiệp
        window.addEventListener('focusin', function(e) {
            if (e.target.closest('.cke_dialog')) {
                e.stopImmediatePropagation();
            }
        }, true); // true = capturing phase

        // 2. Sau khi DOM ready
        $(document).ready(function() {
            // Disable Bootstrap focus enforcement
            if ($.fn.modal && $.fn.modal.Constructor && $.fn.modal.Constructor.prototype) {
                $.fn.modal.Constructor.prototype._enforceFocus = function() {};
            }
            // Unbind focusin event
            $(document).off('focusin.bs.modal');

            // 3. Khi modal mở, xóa tabindex để không trap focus
            $('#planModal').on('shown.bs.modal', function() {
                $(this).removeAttr('tabindex');
            });
        });
    })();
</script>


<script>
    // Khởi tạo tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Modal instance
    var planModal;
    document.addEventListener('DOMContentLoaded', function() {
        planModal = new bootstrap.Modal(document.getElementById('planModal'));

        // Khởi tạo Sortable cho bảng plans
        var sortablePlansEl = document.getElementById('sortable-plans');
        if (sortablePlansEl) {
            new Sortable(sortablePlansEl, {
                handle: '.handle-plan',
                animation: 150,
                filter: '.plan-checkbox',
                preventOnFilter: false,
                onEnd: function(evt) {
                    updatePlansOrder();
                }
            });
        }
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

    // Hiển thị modal thêm gói
    function showAddPlanModal() {
        document.getElementById('planModalTitle').innerHTML = '<i class="fa-solid fa-plus-circle me-2"></i><?= __("Thêm gói sản phẩm"); ?>';
        document.getElementById('planForm').reset();
        document.getElementById('plan_id').value = '';
        document.getElementById('plan_status_input').checked = true;
        document.getElementById('plan_is_instant_input').checked = false;
        document.getElementById('plan_cost_price').value = 0;
        document.getElementById('plan_price').value = 0;
        document.getElementById('plan_sale_price').value = 0;
        $('#plan_duration_type').val('month');
        toggleDurationValue();

        // Show product dropdown và set default product
        document.getElementById('plan_product_group').style.display = 'block';
        $('#plan_product_select').val(<?= $product['id']; ?>);
        document.getElementById('plan_product_id').value = <?= $product['id']; ?>;

        // Reset image fields
        document.getElementById('plan-image-path').value = '';
        document.getElementById('plan_image').value = '';
        document.getElementById('plan-image-preview-container').style.display = 'none';

        // Reset CKEditor
        if (typeof planDescriptionEditor !== 'undefined' && planDescriptionEditor) {
            planDescriptionEditor.setData('');
        }

        planModal.show();
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
                        var imgSrc = result.data.image.startsWith('http') ? result.data.image : '<?= base_url(); ?>' + result.data.image;
                        document.getElementById('plan_image_preview_img').src = imgSrc;
                        document.getElementById('plan-image-preview-container').style.display = 'block';
                        // Set image_path if it's from library
                        if (result.data.image.includes('library/')) {
                            document.getElementById('plan-image-path').value = imgSrc;
                        }
                    } else {
                        document.getElementById('plan-image-preview-container').style.display = 'none';
                    }
                    document.getElementById('plan_image').value = '';

                    // Set CKEditor content - decode HTML entities first
                    var descriptionContent = result.data.description || '';
                    // Decode HTML entities để hiển thị đúng trong CKEditor
                    var tempDiv = document.createElement('div');
                    tempDiv.innerHTML = descriptionContent;
                    var decodedDescription = tempDiv.innerHTML;

                    if (typeof planDescriptionEditor !== 'undefined' && planDescriptionEditor) {
                        planDescriptionEditor.setData(decodedDescription);
                    }

                    toggleDurationValue();

                    // Ẩn product dropdown khi sửa (không cho đổi sản phẩm)
                    document.getElementById('plan_product_group').style.display = 'none';

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

        // Kiểm tra có file upload hoặc library image không
        var imageFile = document.getElementById('plan_image').files[0];
        var imagePath = document.getElementById('plan-image-path').value;
        var hasImage = imageFile !== undefined;
        var hasLibraryImage = imagePath !== '';

        // Tạo FormData nếu có ảnh upload, nếu không thì dùng object thông thường
        var formData;
        if (hasImage) {
            formData = new FormData();
            formData.append('action', $('#plan_id').val() ? 'updateProductPlan' : 'addProductPlan');
            formData.append('id', $('#plan_id').val() || '');
            formData.append('product_id', $('#plan_product_id').val());
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
        } else {
            formData = {
                action: $('#plan_id').val() ? 'updateProductPlan' : 'addProductPlan',
                id: $('#plan_id').val(),
                product_id: $('#plan_product_id').val(),
                name: $('#plan_name').val(),
                duration_type: $('#plan_duration_type').val(),
                duration_value: $('#plan_duration_value').val(),
                cost_price: getRawNumber('plan_cost_price'),
                price: getRawNumber('plan_price'),
                sale_price: getRawNumber('plan_sale_price'),
                description: description,
                is_instant: $('#plan_is_instant_input').is(':checked') ? 1 : 0,
                status: $('#plan_status_input').is(':checked') ? 1 : 0,
                image_path: imagePath // Include library image path
            };
        }

        var nameValue = hasImage ? formData.get('name') : formData.name;
        var priceValue = hasImage ? formData.get('price') : formData.price;

        if (!nameValue || !priceValue) {
            showMessage('<?= __("Vui lòng điền đầy đủ thông tin bắt buộc"); ?>', 'error');
            return;
        }

        // Chọn URL dựa trên action
        var actionValue = hasImage ? formData.get('action') : formData.action;
        var ajaxUrl = actionValue == 'addProductPlan' ? "<?= BASE_URL("ajaxs/admin/create.php"); ?>" : "<?= BASE_URL("ajaxs/admin/update.php"); ?>";

        $.ajax({
            url: ajaxUrl,
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

    // ========== elFinder Integration for Plan Modal ==========
    var planFileManagerWindow = null;

    // Open file manager in popup window
    function openPlanFileManager() {
        var url = '<?= BASE_URL("admin/elfinder?callback=planElfinderCallback"); ?>';
        var width = 900;
        var height = 600;
        var left = (screen.width - width) / 2;
        var top = (screen.height - height) / 2;

        planFileManagerWindow = window.open(url, 'planElfinderFileManager',
            'width=' + width + ',height=' + height + ',left=' + left + ',top=' + top +
            ',menubar=no,toolbar=no,location=no,status=no,scrollbars=yes,resizable=yes');

        if (planFileManagerWindow) {
            planFileManagerWindow.focus();
        }
    }

    // Callback when file is selected from elFinder for plan
    function planElfinderCallback(fileUrl) {
        document.getElementById('plan-image-path').value = fileUrl;
        document.getElementById('plan_image_preview_img').src = fileUrl;
        document.getElementById('plan-image-preview-container').style.display = 'block';

        // Clear file input since we're using library image
        document.getElementById('plan_image').value = '';
    }

    // Preview uploaded image for plan
    function previewPlanImage(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('plan_image_preview_img').src = e.target.result;
                document.getElementById('plan-image-preview-container').style.display = 'block';
                // Clear library path since we're uploading new image
                document.getElementById('plan-image-path').value = '';
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    // Clear selected/uploaded plan image
    function clearPlanImage() {
        document.getElementById('plan-image-path').value = '';
        document.getElementById('plan_image').value = '';
        document.getElementById('plan_image_preview_img').src = '';
        document.getElementById('plan-image-preview-container').style.display = 'none';
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
                status: status
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

    // Cập nhật thứ tự plans
    function updatePlansOrder() {
        var order = [];
        $('#sortable-plans tr').each(function(index) {
            var planId = $(this).data('plan-id');
            if (planId) {
                order.push({
                    id: planId,
                    sort_order: index
                });
            }
        });

        $.ajax({
            url: "<?= BASE_URL("ajaxs/admin/update.php"); ?>",
            method: "POST",
            dataType: "JSON",
            data: {
                action: 'updateProductPlansOrder',
                order: JSON.stringify(order)
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

    // ==================== PLAN FIELDS MANAGEMENT ====================

    var planFieldsModal;
    var currentPlanId = null;
    var currentView = 'list'; // 'list' hoặc 'form'

    document.addEventListener('DOMContentLoaded', function() {
        planFieldsModal = new bootstrap.Modal(document.getElementById('planFieldsModal'));
    });

    // Quản lý fields của gói
    function managePlanFields(planId) {
        currentPlanId = planId;
        $('#plan_field_plan_id').val(planId);

        // Lấy tên gói để hiển thị
        var planName = $('#sortable-plans tr[data-plan-id="' + planId + '"]').find('td:eq(1) strong').text();
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

    // Chuyển sang list view
    function showListView() {
        currentView = 'list';

        // Ẩn form view và hiển thị list view ngay
        $('#plan-fields-form-view').hide();
        $('#plan-fields-list-view').show();

        // Cập nhật footer
        $('#backToListBtn').hide();
        $('#savePlanFieldBtn').hide();
        $('#closeModalBtn').show();

        // Cập nhật title
        $('#planFieldsModalTitle').html('<i class="fa-solid fa-list-check me-2"></i><?= __("Quản lý trường tùy chỉnh"); ?>');
    }

    // Chuyển sang form view
    function showFormView() {
        currentView = 'form';

        // Ẩn list view và hiển thị form view ngay
        $('#plan-fields-list-view').hide();
        $('#plan-fields-form-view').show();

        // Cập nhật footer
        $('#backToListBtn').show();
        $('#savePlanFieldBtn').show();
        $('#closeModalBtn').show();
    }

    // Quay lại list view
    function backToListView() {
        showListView();
    }

    // Load danh sách fields của gói
    function loadPlanFields(planId) {
        // Ẩn loading ngay lập tức
        $('#plan-fields-loading').hide();

        $.ajax({
            url: "<?= BASE_URL("ajaxs/admin/view.php"); ?>",
            method: "POST",
            dataType: "JSON",
            data: {
                action: 'getPlanFields',
                plan_id: planId
            },
            success: function(result) {
                if (result.status == 'success') {
                    renderPlanFields(result.data);
                    initPlanFieldsSortable();
                } else {
                    showMessage(result.msg, 'error');
                    showPlanFieldsEmpty();
                }
            },
            error: function() {
                showMessage('<?= __("Đã xảy ra lỗi khi tải dữ liệu"); ?>', 'error');
                showPlanFieldsEmpty();
            }
        });
    }

    // Hiển thị empty state
    function showPlanFieldsEmpty() {
        $('#plan-fields-empty').show();
        $('#plan-fields-content').hide();
        // Đảm bảo list view vẫn hiển thị
        $('#plan-fields-list-view').show();
    }

    // Render danh sách fields với UI đẹp
    function renderPlanFields(fields) {
        if (!fields || fields.length === 0) {
            showPlanFieldsEmpty();
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
        // Đảm bảo list view hiển thị
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

    // Hiển thị form thêm field
    function showPlanFieldForm() {
        // Reset form
        $('#planFieldForm')[0].reset();
        $('#plan_field_id').val('');
        $('#plan_field_required').prop('checked', true);
        $('#plan_field_type').val('text');
        $('#savePlanFieldBtn').prop('disabled', false).html('<i class="fa-solid fa-save me-1"></i><?= __("Lưu"); ?>');

        // Cập nhật title
        $('#planFieldsModalTitle').html('<i class="fa-solid fa-plus-circle me-2"></i><?= __("Thêm trường tùy chỉnh"); ?>');

        // Chuyển sang form view
        showFormView();

        // Focus vào input đầu tiên
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

                    // Chuyển sang form view
                    showFormView();

                    // Focus vào input đầu tiên
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

            is_required: $('#plan_field_required').is(':checked') ? 1 : 0
        };

        // Validation
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

        // Disable button và hiển thị loading
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

                    // Quay lại list view và reload dữ liệu
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
                        id: fieldId
                    },
                    success: function(result) {
                        if (result.status == 'success') {
                            showMessage(result.msg, 'success');

                            // Reload dữ liệu trong modal
                            loadPlanFields(currentPlanId);

                            // Reload trang để cập nhật số lượng fields trong bảng chính
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
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
                order: JSON.stringify(order)
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

    // Format số cho các input giá
    function formatPriceInput(inputId) {
        var input = document.getElementById(inputId);
        if (!input) return;

        // Chỉ format khi blur, không format khi đang nhập để tránh conflict
        input.addEventListener('input', function(e) {
            // Cho phép nhập số và dấu phẩy, nhưng tự động loại bỏ các ký tự không hợp lệ
            var value = this.value.replace(/[^\d,]/g, '');
            // Loại bỏ các dấu phẩy thừa, chỉ giữ lại số
            value = value.replace(/,/g, '');
            this.value = value;
        });

        input.addEventListener('focus', function() {
            // Khi focus, hiển thị số thuần (không có dấu phẩy) để dễ nhập
            var value = this.value.replace(/[^\d]/g, '');
            this.value = value || '0';
        });

        input.addEventListener('blur', function() {
            // Khi blur, format lại với dấu phẩy
            var value = this.value.replace(/[^\d]/g, '');
            if (value && parseInt(value) > 0) {
                var formatted = parseInt(value).toLocaleString('vi-VN');
                this.value = formatted;
            } else {
                this.value = '0';
            }
        });

        // Xử lý paste
        input.addEventListener('paste', function(e) {
            e.preventDefault();
            var paste = (e.clipboardData || window.clipboardData).getData('text');
            var value = paste.replace(/[^\d]/g, '');
            if (value) {
                this.value = value;
                // Trigger input event để format
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

    // Quản lý kho hàng cho gói giao ngay
    function managePlanStock(planId) {
        window.location.href = '<?= BASE_URL_admin("product-stock"); ?>&plan_id=' + planId;
    }

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
            $('#btnBulkDelete, #btnBulkStatusOn, #btnBulkStatusOff').removeClass('d-none');
        } else {
            $('#bulkActionsToolbar').addClass('d-none');
            $('#btnBulkDelete, #btnBulkStatusOn, #btnBulkStatusOff').addClass('d-none');
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
            showMessage('<?= __("Vui lòng chọn ít nhất một gói sản phẩm"); ?>', 'error');
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

    // Cập nhật trạng thái hàng loạt
    function bulkUpdatePlanStatus(status) {
        var selectedIds = getSelectedPlanIds();

        if (selectedIds.length === 0) {
            showMessage('<?= __("Vui lòng chọn ít nhất một gói sản phẩm"); ?>', 'error');
            return;
        }

        var statusText = status == 1 ? '<?= __("bật"); ?>' : '<?= __("tắt"); ?>';

        Swal.fire({
            title: "<?= __('Xác nhận'); ?>",
            text: "<?= __('Bạn có chắc chắn muốn'); ?> " + statusText + " <?= __('cho'); ?> " + selectedIds.length + " <?= __('gói đã chọn không?'); ?>",
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
                $.ajax({
                    url: "<?= BASE_URL("ajaxs/admin/update.php"); ?>",
                    method: "POST",
                    dataType: "JSON",
                    data: {
                        action: 'bulkUpdateProductPlanStatus',
                        ids: selectedIds,
                        status: status
                    },
                    beforeSend: function() {
                        if (status == 1) {
                            $('#btnBulkStatusOn').prop('disabled', true);
                            $('#btnBulkStatusOn').html('<i class="fa-solid fa-spinner fa-spin me-1"></i><?= __("Đang xử lý..."); ?>');
                        } else {
                            $('#btnBulkStatusOff').prop('disabled', true);
                            $('#btnBulkStatusOff').html('<i class="fa-solid fa-spinner fa-spin me-1"></i><?= __("Đang xử lý..."); ?>');
                        }
                    },
                    success: function(result) {
                        if (status == 1) {
                            $('#btnBulkStatusOn').prop('disabled', false);
                            $('#btnBulkStatusOn').html('<i class="fa-solid fa-toggle-on me-1"></i><?= __("Bật đã chọn"); ?>');
                        } else {
                            $('#btnBulkStatusOff').prop('disabled', false);
                            $('#btnBulkStatusOff').html('<i class="fa-solid fa-toggle-off me-1"></i><?= __("Tắt đã chọn"); ?>');
                        }

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
                        if (status == 1) {
                            $('#btnBulkStatusOn').prop('disabled', false);
                            $('#btnBulkStatusOn').html('<i class="fa-solid fa-toggle-on me-1"></i><?= __("Bật đã chọn"); ?>');
                        } else {
                            $('#btnBulkStatusOff').prop('disabled', false);
                            $('#btnBulkStatusOff').html('<i class="fa-solid fa-toggle-off me-1"></i><?= __("Tắt đã chọn"); ?>');
                        }
                        showMessage('<?= __("Đã xảy ra lỗi"); ?>', 'error');
                    }
                });
            }
        });
    }
</script>

<!-- Select2 JS Library -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    // Khởi tạo Select2 cho modal dropdown
    $(document).ready(function() {
        // Select2 sẽ được khởi tạo khi modal mở để đảm bảo dropdownParent hoạt động
        $('#planModal').on('shown.bs.modal', function() {
            if (!$('#plan_product_select').hasClass('select2-hidden-accessible')) {
                $('#plan_product_select').select2({
                    placeholder: '<?= __("Tìm kiếm sản phẩm..."); ?>',
                    allowClear: false,
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
            }
        });

        // Khi chọn sản phẩm khác, cập nhật hidden input
        $(document).on('change', '#plan_product_select', function() {
            $('#plan_product_id').val($(this).val());
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