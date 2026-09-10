<?php if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

// Kiểm tra ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('<script type="text/javascript">if(!alert("' . __('ID không hợp lệ.') . '")){window.history.back();}</script>');
}

$id = validate_int($_GET['id'], 1);
if ($id === false) {
    die('<script type="text/javascript">if(!alert("' . __('ID không hợp lệ.') . '")){window.history.back();}</script>');
}

$body = [
    'title' => __('Sửa Flash Sale') . ' | ' . $CMSNT->site('title'),
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

$FlashSaleHandler = new FlashSaleHandler();

// Lấy thông tin Flash Sale
$flash_sale = $FlashSaleHandler->getFlashSaleById($id);
if (!$flash_sale) {
    die('<script type="text/javascript">if(!alert("' . __('Flash Sale không tồn tại.') . '")){window.history.back();}</script>');
}

// Lấy các items của Flash Sale
$flash_sale_items = $FlashSaleHandler->getFlashSaleItems($id);
$current_plan_ids = [];
$current_product_ids = [];
$current_flash_prices = [];
foreach ($flash_sale_items as $item) {
    if (!empty($item['plan_id'])) {
        $current_plan_ids[] = $item['plan_id'];
        if (!empty($item['flash_price'])) {
            $current_flash_prices[$item['plan_id']] = $item['flash_price'];
        }
    }
    if (!empty($item['product_id'])) {
        $current_product_ids[] = $item['product_id'];
    }
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

    $isUpdate = $CMSNT->update("flash_sales", [
        'name'              => $name,
        'description'       => $description,
        'discount_type'     => $discount_type,
        'discount_value'    => $discount_value,
        'max_discount_amount' => $max_discount_amount,
        'start_time'        => $start_time,
        'end_time'          => $end_time,
        'quantity_limit'    => $quantity_limit,
        'per_user_limit'    => $per_user_limit,
        'status'            => $status,
        'updated_at'        => gettime()
    ], "`id` = ?", [$id]);

    if ($isUpdate) {
        // Xóa items cũ
        $CMSNT->remove("flash_sale_items", "`flash_sale_id` = ?", [$id]);

        // Thêm các items mới
        if (!empty($plan_ids)) {
            foreach ($plan_ids as $plan_id) {
                $flash_price = isset($flash_prices[$plan_id]) ? $flash_prices[$plan_id] : null;
                $CMSNT->insert("flash_sale_items", [
                    'flash_sale_id' => $id,
                    'plan_id'       => $plan_id,
                    'product_id'    => null,
                    'flash_price'   => $flash_price,
                    'created_at'    => gettime()
                ]);
            }
        } elseif (!empty($product_ids)) {
            foreach ($product_ids as $product_id) {
                $CMSNT->insert("flash_sale_items", [
                    'flash_sale_id' => $id,
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
            'action'        => "Update Flash Sale #" . $id . " (" . $name . ")."
        ]);
        die('<script type="text/javascript">if(!alert("' . __('Cập nhật Flash Sale thành công!') . '")){location.href = "' . base_url_admin('flash-sales') . '";}</script>');
    } else {
        die('<script type="text/javascript">if(!alert("' . __('Cập nhật Flash Sale thất bại!') . '")){window.history.back();}</script>');
    }
}

// Format datetime for input
$start_time_value = date('Y-m-d\TH:i', strtotime($flash_sale['start_time']));
$end_time_value = date('Y-m-d\TH:i', strtotime($flash_sale['end_time']));

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
                    <i class="fa-solid fa-bolt text-warning me-1"></i><?= __('Sửa Flash Sale'); ?>: <?= htmlspecialchars($flash_sale['name']); ?>
                </h1>
            </div>
            <div class="ms-md-1 ms-0">
                <a href="<?= base_url_admin('flash-sales'); ?>" class="btn btn-secondary btn-sm">
                    <i class="fa-solid fa-arrow-left me-1"></i><?= __('Quay lại'); ?>
                </a>
            </div>
        </div>

        <!-- Thống kê -->
        <div class="row mb-3">
            <div class="col-md-4">
                <div class="card custom-card">
                    <div class="card-body text-center">
                        <h4 class="text-primary mb-0"><?= $flash_sale['quantity_sold']; ?></h4>
                        <small class="text-muted"><?= __('Đã bán'); ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card custom-card">
                    <div class="card-body text-center">
                        <h4 class="text-success mb-0">
                            <?= $flash_sale['quantity_limit'] > 0 ? ($flash_sale['quantity_limit'] - $flash_sale['quantity_sold']) : '∞'; ?>
                        </h4>
                        <small class="text-muted"><?= __('Còn lại'); ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card custom-card">
                    <div class="card-body text-center">
                        <?php
                        $current_time = time();
                        $start_time = strtotime($flash_sale['start_time']);
                        $end_time = strtotime($flash_sale['end_time']);

                        if ($flash_sale['status'] != 1) {
                            echo '<span class="badge bg-secondary">' . __('Đã tắt') . '</span>';
                        } elseif ($start_time > $current_time) {
                            echo '<span class="badge bg-info">' . __('Sắp diễn ra') . '</span>';
                        } elseif ($end_time <= $current_time) {
                            echo '<span class="badge bg-danger">' . __('Đã kết thúc') . '</span>';
                        } else {
                            echo '<span class="badge bg-success">' . __('Đang diễn ra') . '</span>';
                        }
                        ?>
                        <br><small class="text-muted"><?= __('Trạng thái'); ?></small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Email Notification Button -->
        <div class="card custom-card mb-3">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="mb-1"><i class="ri-mail-send-line me-1 text-primary"></i><?= __('Gửi Email thông báo'); ?></h6>
                    <small class="text-muted"><?= __('Gửi email cho những user đã yêu thích sản phẩm trong Flash Sale này'); ?></small>
                </div>
                <button type="button" class="btn btn-primary" id="btnSendFlashSaleEmail" onclick="sendFlashSaleEmail()">
                    <i class="ri-mail-send-line me-1"></i><?= __('Gửi Email'); ?>
                </button>
            </div>
        </div>

        <!-- Form sửa Flash Sale -->
        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fa-solid fa-edit me-1"></i><?= __('Thông tin Flash Sale'); ?>
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
                                            value="<?= htmlspecialchars($flash_sale['name']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Loại giảm giá:'); ?> <span class="text-danger">*</span></label>
                                        <select class="form-select" name="discount_type" id="discount-type" required onchange="toggleDiscountType()">
                                            <option value="percentage" <?= $flash_sale['discount_type'] == 'percentage' ? 'selected' : ''; ?>><?= __('Phần trăm (%)'); ?></option>
                                            <option value="fixed" <?= $flash_sale['discount_type'] == 'fixed' ? 'selected' : ''; ?>><?= __('Số tiền cố định'); ?></option>
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
                                                value="<?= $flash_sale['discount_value']; ?>" step="0.01" min="0" required>
                                            <span class="input-group-text" id="discount-value-unit"><?= $flash_sale['discount_type'] == 'percentage' ? '%' : getCurrencyNameDefault(); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Giảm tối đa:'); ?></label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" name="max_discount_amount"
                                                value="<?= $flash_sale['max_discount_amount']; ?>" step="0.01" min="0">
                                            <span class="input-group-text"><?= getCurrencyNameDefault(); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Trạng thái:'); ?></label>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="status"
                                                id="flashSaleStatus" <?= $flash_sale['status'] == 1 ? 'checked' : ''; ?>>
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
                                        <input type="datetime-local" class="form-control" name="start_time"
                                            value="<?= $start_time_value; ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Thời gian kết thúc:'); ?> <span class="text-danger">*</span></label>
                                        <input type="datetime-local" class="form-control" name="end_time"
                                            value="<?= $end_time_value; ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Giới hạn số lượng bán:'); ?></label>
                                        <input type="number" class="form-control" name="quantity_limit"
                                            value="<?= $flash_sale['quantity_limit']; ?>" min="0">
                                        <small class="text-muted"><?= __('0 = không giới hạn'); ?></small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Giới hạn mỗi user:'); ?></label>
                                        <input type="number" class="form-control" name="per_user_limit"
                                            value="<?= $flash_sale['per_user_limit']; ?>" min="0">
                                        <small class="text-muted"><?= __('0 = không giới hạn'); ?></small>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('Mô tả:'); ?></label>
                                        <textarea class="form-control" name="description" rows="3"><?= htmlspecialchars($flash_sale['description'] ?? ''); ?></textarea>
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
                                                <option value="<?= $product['id']; ?>" <?= in_array($product['id'], $current_product_ids) ? 'selected' : ''; ?>>
                                                    <?= htmlspecialchars(html_entity_decode($product['name'], ENT_QUOTES, 'UTF-8')); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
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
                                                    data-flash-price="<?= isset($current_flash_prices[$plan['id']]) ? $current_flash_prices[$plan['id']] : ''; ?>"
                                                    data-name="<?= htmlspecialchars($plan['product_name'] . ' - ' . $plan['name']); ?>"
                                                    class="plan-option"
                                                    <?= in_array($plan['id'], $current_plan_ids) ? 'selected' : ''; ?>>
                                                    <?= htmlspecialchars(html_entity_decode($plan['product_name'], ENT_QUOTES, 'UTF-8')); ?> - <?= htmlspecialchars(html_entity_decode($plan['name'], ENT_QUOTES, 'UTF-8')); ?>
                                                    (<?= format_currency($plan['price']); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row" id="flash-price-container" style="display: none;">
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <label class="form-label"><i class="fa-solid fa-tag text-danger me-1"></i><?= __('Giá Flash Sale cố định (tuỳ chọn):'); ?></label>
                                        <div id="flash-price-inputs"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end mt-3">
                                <a href="<?= base_url_admin('flash-sales'); ?>" class="btn btn-secondary me-2">
                                    <i class="fa-solid fa-times me-1"></i><?= __('Hủy'); ?>
                                </a>
                                <button type="submit" name="submit" class="btn btn-primary">
                                    <i class="fa-solid fa-save me-1"></i><?= __('Cập nhật Flash Sale'); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Preview Users -->
<div class="modal fade" id="flashSaleEmailModal" tabindex="-1" aria-labelledby="flashSaleEmailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="flashSaleEmailModalLabel">
                    <i class="ri-mail-send-line me-2"></i><?= __('Gửi Email thông báo Flash Sale'); ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="emailPreviewLoading" class="text-center py-4">
                    <i class="fa fa-spinner fa-spin fa-2x text-primary"></i>
                    <p class="mt-2 text-muted"><?= __('Đang tải danh sách...'); ?></p>
                </div>
                <div id="emailPreviewContent" style="display: none;">
                    <div class="alert alert-info-transparent mb-3">
                        <div class="d-flex align-items-center">
                            <i class="ri-information-line fs-4 me-2"></i>
                            <div>
                                <strong id="previewTotalUsers">0</strong> <?= __('user đủ điều kiện nhận email'); ?>
                                <br><small class="text-muted"><?= __('Những user đã yêu thích sản phẩm trong Flash Sale này'); ?></small>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 40px;" class="text-center">#</th>
                                    <th><?= __('Username'); ?></th>
                                    <th><?= __('Email'); ?></th>
                                    <th><?= __('Sản phẩm yêu thích'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="previewUsersTable">
                            </tbody>
                        </table>
                    </div>
                </div>
                <div id="emailPreviewError" class="text-center py-4" style="display: none;">
                    <i class="ri-error-warning-line fs-1 text-danger"></i>
                    <p class="mt-2 text-danger" id="emailPreviewErrorMsg"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="ri-close-line me-1"></i><?= __('Đóng'); ?>
                </button>
                <button type="button" class="btn btn-primary" id="btnConfirmSendEmail" onclick="confirmSendEmail()" disabled>
                    <i class="ri-send-plane-line me-1"></i><?= __('Gửi Email ngay'); ?>
                </button>
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

        if (type == 'percentage') {
            valueUnit.textContent = '%';
        } else {
            valueUnit.textContent = '<?= getCurrencyNameDefault(); ?>';
        }
    }

    // Load plans theo sản phẩm đã chọn
    function loadPlansByProducts() {
        var productSelect = document.getElementById('product_ids');
        var planSelect = document.getElementById('plan_ids');
        var selectedProducts = Array.from(productSelect.selectedOptions).map(opt => opt.value);

        var allPlanOptions = planSelect.querySelectorAll('.plan-option');

        if (selectedProducts.length === 0) {
            allPlanOptions.forEach(function(option) {
                option.style.display = '';
            });
        } else {
            allPlanOptions.forEach(function(option) {
                var planProductId = option.getAttribute('data-product-id');
                if (selectedProducts.includes(planProductId)) {
                    option.style.display = '';
                } else {
                    option.style.display = 'none';
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
            var flashPrice = option.getAttribute('data-flash-price');

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
                        value="${flashPrice || ''}"
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
        updateFlashPriceInputs();
    });

    // Send Flash Sale Email - Show Preview Modal
    function sendFlashSaleEmail() {
        var btn = document.getElementById('btnSendFlashSaleEmail');
        var originalContent = btn.innerHTML;

        btn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i><?= __('Đang tải...'); ?>';
        btn.disabled = true;

        // Show modal first
        var modal = new bootstrap.Modal(document.getElementById('flashSaleEmailModal'));
        modal.show();

        // Reset modal states
        $('#emailPreviewLoading').show();
        $('#emailPreviewContent').hide();
        $('#emailPreviewError').hide();
        $('#btnConfirmSendEmail').prop('disabled', true);

        // Fetch preview data
        $.ajax({
            url: '<?= base_url('ajaxs/admin/flash-sale-email.php'); ?>',
            method: 'POST',
            dataType: 'JSON',
            data: {
                action: 'preview',
                flash_sale_id: <?= $id; ?>,
                csrf_token: '<?= generateCSRFToken(); ?>'
            },
            success: function(response) {
                $('#emailPreviewLoading').hide();

                if (response.success) {
                    // Populate table
                    var tbody = $('#previewUsersTable');
                    tbody.empty();

                    response.users.forEach(function(user, index) {
                        var row = '<tr>' +
                            '<td>' + (index + 1) + '</td>' +
                            '<td><strong>' + escapeHtml(user.username) + '</strong></td>' +
                            '<td><small>' + escapeHtml(user.email) + '</small></td>' +
                            '<td><small class="text-muted">' + escapeHtml(user.products) + '</small></td>' +
                            '</tr>';
                        tbody.append(row);
                    });

                    $('#previewTotalUsers').text(response.total_users);
                    $('#emailPreviewContent').show();
                    $('#btnConfirmSendEmail').prop('disabled', false);
                } else {
                    $('#emailPreviewErrorMsg').text(response.message);
                    $('#emailPreviewError').show();
                }
            },
            error: function(xhr, status, error) {
                $('#emailPreviewLoading').hide();
                $('#emailPreviewErrorMsg').text('<?= __('Không thể kết nối đến server'); ?>');
                $('#emailPreviewError').show();
            },
            complete: function() {
                btn.innerHTML = originalContent;
                btn.disabled = false;
            }
        });
    }

    // Confirm and Send Email
    function confirmSendEmail() {
        var btn = document.getElementById('btnConfirmSendEmail');
        var originalContent = btn.innerHTML;

        btn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i><?= __('Đang gửi...'); ?>';
        btn.disabled = true;

        $.ajax({
            url: '<?= base_url('ajaxs/admin/flash-sale-email.php'); ?>',
            method: 'POST',
            dataType: 'JSON',
            data: {
                action: 'send',
                flash_sale_id: <?= $id; ?>,
                csrf_token: '<?= generateCSRFToken(); ?>'
            },
            success: function(response) {
                // Close modal
                bootstrap.Modal.getInstance(document.getElementById('flashSaleEmailModal')).hide();

                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '<?= __('Thành công'); ?>',
                        html: response.message + (response.failed > 0 ? '<br><small class="text-warning"><?= __('Thất bại'); ?>: ' + response.failed + '</small>' : ''),
                        timer: 3000,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '<?= __('Có lỗi xảy ra'); ?>',
                        text: response.message
                    });
                }
            },
            error: function(xhr, status, error) {
                Swal.fire({
                    icon: 'error',
                    title: '<?= __('Lỗi kết nối'); ?>',
                    text: '<?= __('Không thể kết nối đến server'); ?>'
                });
            },
            complete: function() {
                btn.innerHTML = originalContent;
                btn.disabled = false;
            }
        });
    }

    // Helper function to escape HTML
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
</script>