<?php if (!defined('IN_SITE')) {
    die('The Request Not Found');
}
$body = [
    'title' => __('Thêm Flash Sale') . ' | ' . $CMSNT->site('title'),
    'desc'   => $CMSNT->site('description'),
    'keyword' => $CMSNT->site('keywords')
];
$body['header'] = '';
$body['footer'] = '';
require_once(__DIR__ . '/../../models/is_admin.php');
require_once(__DIR__ . '/header.php');
require_once(__DIR__ . '/sidebar.php');
require_once(__DIR__ . '/../../libs/database/flashsale.php');

if (checkPermission($getUser['admin'], 'edit_flash_sale') != true) {
    $role_name = getRoleName('edit_flash_sale');
    die('<script type="text/javascript">if(!alert("' . sprintf(__('Bạn không có quyền %s'), $role_name) . '")){window.history.back();}</script>');
}

if (isset($_POST['submit'])) {
    checkCSRF();

    if ($CMSNT->site('status_demo') != 0) {
        die('<script type="text/javascript">if(!alert("' . __('Không được dùng chức năng này vì đây là trang web demo.') . '")){window.history.back().location.reload();}</script>');
    }

    $name = trim(strip_tags($_POST['name']));
    $description = trim($_POST['description'] ?? '');
    $discount_type = validate_string($_POST['discount_type'], 20);
    $discount_value = isset($_POST['discount_value']) ? (float)$_POST['discount_value'] : 0;
    $max_discount_amount = isset($_POST['max_discount_amount']) ? (float)$_POST['max_discount_amount'] : 0;
    $start_time = !empty($_POST['start_time']) ? validate_string($_POST['start_time'], 30) : null;
    $end_time = !empty($_POST['end_time']) ? validate_string($_POST['end_time'], 30) : null;
    $quantity_limit = isset($_POST['quantity_limit']) ? validate_int($_POST['quantity_limit'], 0) : 0;
    $per_user_limit = isset($_POST['per_user_limit']) ? validate_int($_POST['per_user_limit'], 0) : 0;
    $status = isset($_POST['status']) ? 1 : 0;

    // Lấy danh sách product_ids và plan_ids
    $product_ids = [];
    if (!empty($_POST['product_ids']) && is_array($_POST['product_ids'])) {
        foreach ($_POST['product_ids'] as $pid) {
            $pid_int = validate_int($pid, 1);
            if ($pid_int !== false) {
                $product_ids[] = $pid_int;
            }
        }
    }

    $plan_ids = [];
    if (!empty($_POST['plan_ids']) && is_array($_POST['plan_ids'])) {
        foreach ($_POST['plan_ids'] as $pid) {
            $pid_int = validate_int($pid, 1);
            if ($pid_int !== false) {
                $plan_ids[] = $pid_int;
            }
        }
    }

    // Lấy flash_prices cho từng plan
    $flash_prices = [];
    if (!empty($_POST['flash_prices']) && is_array($_POST['flash_prices'])) {
        foreach ($_POST['flash_prices'] as $plan_id => $price) {
            if (!empty($price) && floatval($price) > 0) {
                $flash_prices[intval($plan_id)] = floatval($price);
            }
        }
    }

    if (empty($name)) {
        die('<script type="text/javascript">if(!alert("' . __('Vui lòng nhập tên chương trình Flash Sale.') . '")){window.history.back();}</script>');
    }

    if ($discount_type === false || !in_array($discount_type, ['percentage', 'fixed'])) {
        die('<script type="text/javascript">if(!alert("' . __('Loại giảm giá không hợp lệ.') . '")){window.history.back();}</script>');
    }

    if ($discount_value <= 0) {
        die('<script type="text/javascript">if(!alert("' . __('Giá trị giảm giá phải lớn hơn 0.') . '")){window.history.back();}</script>');
    }

    if ($discount_type == 'percentage' && $discount_value > 100) {
        die('<script type="text/javascript">if(!alert("' . __('Giá trị phần trăm không được vượt quá 100%.') . '")){window.history.back();}</script>');
    }

    if (empty($start_time) || empty($end_time)) {
        die('<script type="text/javascript">if(!alert("' . __('Vui lòng nhập thời gian bắt đầu và kết thúc.') . '")){window.history.back();}</script>');
    }

    // Format datetime
    $start_time = str_replace('T', ' ', $start_time);
    $end_time = str_replace('T', ' ', $end_time);

    if (!strpos($start_time, ':')) {
        $start_time .= ':00';
    }
    if (!strpos($end_time, ':')) {
        $end_time .= ':00';
    }

    if (strtotime($start_time) >= strtotime($end_time)) {
        die('<script type="text/javascript">if(!alert("' . __('Thời gian bắt đầu phải trước thời gian kết thúc.') . '")){window.history.back();}</script>');
    }

    if (empty($plan_ids) && empty($product_ids)) {
        die('<script type="text/javascript">if(!alert("' . __('Vui lòng chọn ít nhất một sản phẩm hoặc gói.') . '")){window.history.back();}</script>');
    }

    $isInsert = $CMSNT->insert("flash_sales", [
        'name'              => $name,
        'description'       => $description,
        'discount_type'     => $discount_type,
        'discount_value'    => $discount_value,
        'max_discount_amount' => $max_discount_amount,
        'start_time'        => $start_time,
        'end_time'          => $end_time,
        'quantity_limit'    => $quantity_limit,
        'quantity_sold'     => 0,
        'per_user_limit'    => $per_user_limit,
        'status'            => $status,
        'created_at'        => gettime(),
        'updated_at'        => gettime()
    ]);

    if ($isInsert) {
        $flash_sale_id = $isInsert; // insert() đã trả về insert_id

        // Thêm các items
        if (!empty($plan_ids)) {
            foreach ($plan_ids as $plan_id) {
                $flash_price = isset($flash_prices[$plan_id]) ? $flash_prices[$plan_id] : null;
                $CMSNT->insert("flash_sale_items", [
                    'flash_sale_id' => $flash_sale_id,
                    'plan_id'       => $plan_id,
                    'product_id'    => null,
                    'flash_price'   => $flash_price,
                    'created_at'    => gettime()
                ]);
            }
        } elseif (!empty($product_ids)) {
            // Nếu chọn product mà không chọn plan cụ thể
            foreach ($product_ids as $product_id) {
                $CMSNT->insert("flash_sale_items", [
                    'flash_sale_id' => $flash_sale_id,
                    'plan_id'       => null,
                    'product_id'    => $product_id,
                    'flash_price'   => null,
                    'created_at'    => gettime()
                ]);
            }
        }

        $CMSNT->insert("logs", [
            'user_id'       => $getUser['id'],
            'ip'            => myip(),
            'device'        => getUserAgent(),
            'createdate'    => gettime(),
            'action'        => "Add Flash Sale (" . $name . ")."
        ]);

        die('<script type="text/javascript">if(!alert("' . __('Thêm Flash Sale thành công!') . '")){location.href = "' . base_url_admin('flash-sales') . '";}</script>');
    } else {
        die('<script type="text/javascript">if(!alert("' . __('Thêm Flash Sale thất bại!') . '")){window.history.back();}</script>');
    }
}

// Lấy danh sách sản phẩm và gói
$products_list = $CMSNT->get_list_safe("SELECT `id`, `name` FROM `products` WHERE `status` = 1 ORDER BY `name` ASC", []);
$plans_list = $CMSNT->get_list_safe("SELECT pp.`id`, pp.`name`, pp.`price`, pp.`sale_price`, pp.`product_id`, p.`name` as product_name FROM `product_plans` pp LEFT JOIN `products` p ON pp.`product_id` = p.`id` WHERE pp.`status` = 1 ORDER BY p.`name` ASC, pp.`name` ASC", []);
?>

<div class="main-content app-content">
    <div class="container-fluid">
        <!-- Tiêu đề trang -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <div>
                <h1 class="page-name fw-semibold fs-18 mb-0">
                    <i class="fa-solid fa-bolt text-warning me-1"></i><?= __('Thêm Flash Sale mới'); ?>
                </h1>
            </div>
            <div class="ms-md-1 ms-0">
                <a href="<?= base_url_admin('flash-sales'); ?>" class="btn btn-secondary btn-sm">
                    <i class="fa-solid fa-arrow-left me-1"></i><?= __('Quay lại'); ?>
                </a>
            </div>
        </div>

        <!-- Form thêm Flash Sale -->
        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fa-solid fa-info-circle me-1"></i><?= __('Thông tin Flash Sale'); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form action="" method="POST">
                            <?php echo csrfField(); ?>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Tên chương trình:'); ?> <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="name"
                                            placeholder="<?= __('VD: Flash Sale Cuối Tuần'); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Loại giảm giá:'); ?> <span class="text-danger">*</span></label>
                                        <select class="form-select" name="discount_type" id="discount-type" required onchange="toggleDiscountType()">
                                            <option value="percentage"><?= __('Phần trăm (%)'); ?></option>
                                            <option value="fixed"><?= __('Số tiền cố định'); ?></option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Giá trị giảm:'); ?> <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" name="discount_value" id="discount-value"
                                                value="0" step="0.01" min="0" required>
                                            <span class="input-group-text" id="discount-value-unit">%</span>
                                        </div>
                                        <small class="text-muted" id="discount-value-hint"><?= __('Nhập số phần trăm giảm giá (0-100)'); ?></small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Giảm tối đa:'); ?></label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" name="max_discount_amount"
                                                value="0" step="0.01" min="0">
                                            <span class="input-group-text"><?= getCurrencyNameDefault(); ?></span>
                                        </div>
                                        <small class="text-muted"><?= __('Áp dụng cho loại phần trăm. 0 = không giới hạn'); ?></small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Trạng thái:'); ?></label>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="status"
                                                id="flashSaleStatus" checked>
                                            <label class="form-check-label" for="flashSaleStatus">
                                                <?= __('Kích hoạt Flash Sale'); ?>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Thời gian bắt đầu:'); ?> <span class="text-danger">*</span></label>
                                        <input type="datetime-local" class="form-control" name="start_time" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Thời gian kết thúc:'); ?> <span class="text-danger">*</span></label>
                                        <input type="datetime-local" class="form-control" name="end_time" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Giới hạn số lượng bán:'); ?></label>
                                        <input type="number" class="form-control" name="quantity_limit"
                                            value="0" min="0">
                                        <small class="text-muted"><?= __('Tổng số lượng có thể bán. 0 = không giới hạn'); ?></small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Giới hạn mỗi user:'); ?></label>
                                        <input type="number" class="form-control" name="per_user_limit"
                                            value="0" min="0">
                                        <small class="text-muted"><?= __('Số lượng tối đa mỗi user có thể mua. 0 = không giới hạn'); ?></small>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Mô tả:'); ?></label>
                                        <textarea class="form-control" name="description" rows="3"
                                            placeholder="<?= __('Nhập mô tả về chương trình Flash Sale'); ?>"></textarea>
                                    </div>
                                </div>
                            </div>

                            <hr>
                            <h5 class="mb-3"><i class="fa-solid fa-tags me-1"></i><?= __('Chọn sản phẩm/gói áp dụng'); ?></h5>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Sản phẩm:'); ?></label>
                                        <select class="form-select" name="product_ids[]" id="product_ids" multiple size="8" onchange="loadPlansByProducts()">
                                            <?php foreach ($products_list as $product): ?>
                                                <option value="<?= $product['id']; ?>">
                                                    <?= htmlspecialchars(html_entity_decode($product['name'], ENT_QUOTES, 'UTF-8')); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted"><?= __('Giữ Ctrl/Cmd để chọn nhiều sản phẩm'); ?></small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Gói:'); ?> <span class="text-danger">*</span></label>
                                        <select class="form-select" name="plan_ids[]" id="plan_ids" multiple size="8" onchange="updateFlashPriceInputs()">
                                            <?php foreach ($plans_list as $plan): ?>
                                                <option value="<?= $plan['id']; ?>"
                                                    data-product-id="<?= $plan['product_id']; ?>"
                                                    data-price="<?= $plan['price']; ?>"
                                                    data-sale-price="<?= $plan['sale_price']; ?>"
                                                    data-name="<?= htmlspecialchars($plan['product_name'] . ' - ' . $plan['name']); ?>"
                                                    class="plan-option">
                                                    <?= htmlspecialchars(html_entity_decode($plan['product_name'], ENT_QUOTES, 'UTF-8')); ?> - <?= htmlspecialchars(html_entity_decode($plan['name'], ENT_QUOTES, 'UTF-8')); ?>
                                                    (<?= format_currency($plan['price']); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted"><?= __('Chọn gói cụ thể để áp dụng Flash Sale'); ?></small>
                                    </div>
                                </div>
                            </div>

                            <div class="row" id="flash-price-container" style="display: none;">
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <label class="form-label"><i class="fa-solid fa-tag text-danger me-1"></i><?= __('Giá Flash Sale cố định (tuỳ chọn):'); ?></label>
                                        <div class="alert alert-info">
                                            <i class="fa-solid fa-info-circle me-1"></i>
                                            <?= __('Bạn có thể đặt giá Flash Sale cố định cho từng gói. Để trống nếu muốn áp dụng công thức giảm giá.'); ?>
                                        </div>
                                        <div id="flash-price-inputs"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end mt-3">
                                <a href="<?= base_url_admin('flash-sales'); ?>" class="btn btn-secondary me-2">
                                    <i class="fa-solid fa-times me-1"></i><?= __('Hủy'); ?>
                                </a>
                                <button type="submit" name="submit" class="btn btn-primary">
                                    <i class="fa-solid fa-save me-1"></i><?= __('Tạo Flash Sale'); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once(__DIR__ . '/footer.php');
?>

<script>
    // Toggle hiển thị theo loại giảm giá
    function toggleDiscountType() {
        var type = document.getElementById('discount-type').value;
        var valueUnit = document.getElementById('discount-value-unit');
        var valueHint = document.getElementById('discount-value-hint');
        var valueInput = document.getElementById('discount-value');

        if (type == 'percentage') {
            valueUnit.textContent = '%';
            valueHint.textContent = '<?= __("Nhập số phần trăm giảm giá (0-100)"); ?>';
            valueInput.setAttribute('max', '100');
        } else {
            valueUnit.textContent = '<?= getCurrencyNameDefault(); ?>';
            valueHint.textContent = '<?= __("Nhập số tiền giảm giá cố định"); ?>';
            valueInput.removeAttribute('max');
        }
    }

    // Load plans theo sản phẩm đã chọn
    function loadPlansByProducts() {
        var productSelect = document.getElementById('product_ids');
        var planSelect = document.getElementById('plan_ids');
        var selectedProducts = Array.from(productSelect.selectedOptions).map(opt => opt.value);

        var allPlanOptions = planSelect.querySelectorAll('.plan-option');

        if (selectedProducts.length === 0) {
            // Hiển thị tất cả gói
            allPlanOptions.forEach(function(option) {
                option.style.display = '';
            });
        } else {
            // Chỉ hiển thị gói của sản phẩm đã chọn
            allPlanOptions.forEach(function(option) {
                var planProductId = option.getAttribute('data-product-id');
                if (selectedProducts.includes(planProductId)) {
                    option.style.display = '';
                } else {
                    option.style.display = 'none';
                    option.selected = false;
                }
            });
        }

        updateFlashPriceInputs();
    }

    // Cập nhật inputs giá Flash Sale
    function updateFlashPriceInputs() {
        var planSelect = document.getElementById('plan_ids');
        var selectedPlans = Array.from(planSelect.selectedOptions);
        var container = document.getElementById('flash-price-container');
        var inputsContainer = document.getElementById('flash-price-inputs');

        if (selectedPlans.length === 0) {
            container.style.display = 'none';
            inputsContainer.innerHTML = '';
            return;
        }

        container.style.display = '';
        inputsContainer.innerHTML = '';

        selectedPlans.forEach(function(option) {
            var planId = option.value;
            var planName = option.getAttribute('data-name');
            var price = option.getAttribute('data-price');
            var salePrice = option.getAttribute('data-sale-price');

            var currentPrice = (salePrice > 0 && salePrice < price) ? salePrice : price;

            var row = document.createElement('div');
            row.className = 'row mb-2 align-items-center';
            row.innerHTML = `
            <div class="col-md-6">
                <span>${planName}</span>
                <small class="text-muted d-block">Giá hiện tại: <?= getCurrencyNameDefault(); ?> ${parseFloat(currentPrice).toLocaleString()}</small>
            </div>
            <div class="col-md-6">
                <div class="input-group">
                    <input type="number" class="form-control" name="flash_prices[${planId}]"
                        placeholder="<?= __('Để trống = dùng % giảm giá'); ?>" step="0.01" min="0">
                    <span class="input-group-text"><?= getCurrencyNameDefault(); ?></span>
                </div>
            </div>
        `;
            inputsContainer.appendChild(row);
        });
    }

    // Khởi tạo khi trang load
    document.addEventListener('DOMContentLoaded', function() {
        loadPlansByProducts();
    });
</script>