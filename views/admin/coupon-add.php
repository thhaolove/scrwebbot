<?php if (!defined('IN_SITE')) {
    die('The Request Not Found');
}
$body = [
    'title' => __('Thêm mã giảm giá').' | '.$CMSNT->site('title'),
    'desc'   => $CMSNT->site('description'),
    'keyword' => $CMSNT->site('keywords')
];
$body['header'] = '';
$body['footer'] = '';
require_once(__DIR__.'/../../models/is_admin.php');
require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
if(checkPermission($getUser['admin'], 'edit_coupon') != true){
    $role_name = getRoleName('edit_coupon');
    die('<script type="text/javascript">if(!alert("' . sprintf(__('Bạn không có quyền %s'), $role_name) . '")){window.history.back();}</script>');
}

if (isset($_POST['submit'])) {
    // Kiểm tra CSRF token
    checkCSRF();
    
    if ($CMSNT->site('status_demo') != 0) {
        die('<script type="text/javascript">if(!alert("' . __('Không được dùng chức năng này vì đây là trang web demo.') . '")){window.history.back().location.reload();}</script>');
    }
    
    $code = trim(strip_tags($_POST['code']));
    $type = validate_string($_POST['type'], 20);
    $value = isset($_POST['value']) ? (float)$_POST['value'] : 0;
    $min_order_amount = isset($_POST['min_order_amount']) ? (float)$_POST['min_order_amount'] : 0;
    $max_discount_amount = isset($_POST['max_discount_amount']) ? (float)$_POST['max_discount_amount'] : 0;
    $usage_limit = isset($_POST['usage_limit']) ? validate_int($_POST['usage_limit'], 0) : 0;
    $user_limit = isset($_POST['user_limit']) ? validate_int($_POST['user_limit'], 0) : 0;
    $start_date = !empty($_POST['start_date']) ? validate_string($_POST['start_date'], 20) : null;
    $end_date = !empty($_POST['end_date']) ? validate_string($_POST['end_date'], 20) : null;
    $status = isset($_POST['status']) ? 1 : 0;
    $description = trim($_POST['description'] ?? '');
    
    // Lấy danh sách product_ids và plan_ids
    $product_ids = [];
    if(!empty($_POST['product_ids']) && is_array($_POST['product_ids'])) {
        foreach($_POST['product_ids'] as $pid) {
            $pid_int = validate_int($pid, 1);
            if($pid_int !== false) {
                $product_ids[] = $pid_int;
            }
        }
    }
    
    $plan_ids = [];
    if(!empty($_POST['plan_ids']) && is_array($_POST['plan_ids'])) {
        foreach($_POST['plan_ids'] as $pid) {
            $pid_int = validate_int($pid, 1);
            if($pid_int !== false) {
                $plan_ids[] = $pid_int;
            }
        }
    }

    if(empty($code)){
        die('<script type="text/javascript">if(!alert("' . __('Vui lòng nhập mã giảm giá.') . '")){window.history.back();}</script>');
    }
    
    if($type === false || !in_array($type, ['percentage', 'fixed'])){
        die('<script type="text/javascript">if(!alert("' . __('Loại mã giảm giá không hợp lệ.') . '")){window.history.back();}</script>');
    }
    
    if($value <= 0){
        die('<script type="text/javascript">if(!alert("' . __('Giá trị mã giảm giá phải lớn hơn 0.') . '")){window.history.back();}</script>');
    }
    
    if($type == 'percentage' && $value > 100){
        die('<script type="text/javascript">if(!alert("' . __('Giá trị phần trăm không được vượt quá 100%.') . '")){window.history.back();}</script>');
    }
    
    // Kiểm tra mã đã tồn tại chưa
    if ($CMSNT->get_row_safe("SELECT * FROM `coupons` WHERE `code` = ?", [$code])) {
        die('<script type="text/javascript">if(!alert("' . __('Mã giảm giá này đã tồn tại trong hệ thống.') . '")){window.history.back();}</script>');
    }
    
    // Validate dates
    if($start_date && !preg_match('/^\d{4}-\d{2}-\d{2}(\s\d{2}:\d{2}:\d{2})?$/', $start_date)) {
        die('<script type="text/javascript">if(!alert("' . __('Ngày bắt đầu không hợp lệ.') . '")){window.history.back();}</script>');
    }
    
    if($end_date && !preg_match('/^\d{4}-\d{2}-\d{2}(\s\d{2}:\d{2}:\d{2})?$/', $end_date)) {
        die('<script type="text/javascript">if(!alert("' . __('Ngày kết thúc không hợp lệ.') . '")){window.history.back();}</script>');
    }
    
    if($start_date && $end_date && strtotime($start_date) > strtotime($end_date)) {
        die('<script type="text/javascript">if(!alert("' . __('Ngày bắt đầu phải nhỏ hơn ngày kết thúc.') . '")){window.history.back();}</script>');
    }
    
    // Format dates
    if($start_date && !strpos($start_date, ' ')) {
        $start_date .= ' 00:00:00';
    }
    if($end_date && !strpos($end_date, ' ')) {
        $end_date .= ' 23:59:59';
    }

    $isInsert = $CMSNT->insert("coupons", [
        'code'              => strtoupper($code),
        'type'              => $type,
        'value'             => $value,
        'min_order_amount'  => $min_order_amount,
        'max_discount_amount' => $max_discount_amount,
        'usage_limit'       => $usage_limit,
        'used_count'        => 0,
        'user_limit'        => $user_limit,
        'product_ids'       => !empty($product_ids) ? json_encode($product_ids) : null,
        'plan_ids'          => !empty($plan_ids) ? json_encode($plan_ids) : null,
        'start_date'        => $start_date,
        'end_date'          => $end_date,
        'status'            => $status,
        'description'       => $description,
        'created_at'        => gettime(),
        'updated_at'        => gettime()
    ]);
    
    if ($isInsert) {
        $CMSNT->insert("logs", [
            'user_id'       => $getUser['id'],
            'ip'            => myip(),
            'device'        => getUserAgent(),
            'createdate'    => gettime(),
            'action'        => "Add Coupon (".$code.")."
        ]);
        die('<script type="text/javascript">if(!alert("' . __('Thêm mã giảm giá thành công!') . '")){location.href = "'.base_url_admin('coupons').'";}  </script>');
    } else {
        die('<script type="text/javascript">if(!alert("' . __('Thêm mã giảm giá thất bại!') . '")){window.history.back();}</script>');
    }
}

// Lấy danh sách sản phẩm và gói để chọn
$products_list = $CMSNT->get_list_safe("SELECT `id`, `name` FROM `products` WHERE `status` = 1 ORDER BY `name` ASC", []);
$plans_list = $CMSNT->get_list_safe("SELECT pp.`id`, pp.`name`, pp.`product_id`, p.`name` as product_name FROM `product_plans` pp LEFT JOIN `products` p ON pp.`product_id` = p.`id` WHERE pp.`status` = 1 ORDER BY p.`name` ASC, pp.`name` ASC", []);
?>

<div class="main-content app-content">
    <div class="container-fluid">
        <!-- Tiêu đề trang -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <div>
                <h1 class="page-name fw-semibold fs-18 mb-0">
                    <i class="fa-solid fa-plus-circle me-1"></i><?=__('Thêm mã giảm giá mới');?>
                </h1>
            </div>
            <div class="ms-md-1 ms-0">
                <a href="<?=base_url_admin('coupons');?>" class="btn btn-secondary btn-sm">
                    <i class="fa-solid fa-arrow-left me-1"></i><?=__('Quay lại');?>
                </a>
            </div>
        </div>

        <!-- Form thêm mã giảm giá -->
        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-body">
                        <form action="" method="POST">
                            <?php echo csrfField(); ?>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><?=__('Mã giảm giá:');?> <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="code" id="coupon-code"
                                            placeholder="<?=__('VD: SALE50, WELCOME2024');?>" required 
                                            style="text-transform: uppercase;">
                                        <small class="text-muted"><?=__('Mã sẽ được chuyển thành chữ in hoa');?></small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><?=__('Loại mã giảm giá:');?> <span class="text-danger">*</span></label>
                                        <select class="form-select" name="type" id="coupon-type" required onchange="toggleCouponType()">
                                            <option value="percentage"><?=__('Phần trăm (%)');?></option>
                                            <option value="fixed"><?=__('Số tiền cố định');?></option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label"><?=__('Giá trị:');?> <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" name="value" id="coupon-value"
                                                value="0" step="0.01" min="0" required>
                                            <span class="input-group-text" id="coupon-value-unit">%</span>
                                        </div>
                                        <small class="text-muted" id="coupon-value-hint"><?=__('Nhập số phần trăm giảm giá (0-100)');?></small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label"><?=__('Đơn hàng tối thiểu:');?></label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" name="min_order_amount"
                                                value="0" step="0.01" min="0">
                                            <span class="input-group-text"><?=getCurrencyNameDefault();?></span>
                                        </div>
                                        <small class="text-muted"><?=__('Đơn hàng phải đạt giá trị này mới được áp dụng mã');?></small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label"><?=__('Giảm tối đa:');?></label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" name="max_discount_amount"
                                                value="0" step="0.01" min="0">
                                            <span class="input-group-text"><?=getCurrencyNameDefault();?></span>
                                        </div>
                                        <small class="text-muted"><?=__('Áp dụng cho mã giảm giá phần trăm');?></small>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label"><?=__('Giới hạn sử dụng:');?></label>
                                        <input type="number" class="form-control" name="usage_limit"
                                            value="0" min="0">
                                        <small class="text-muted"><?=__('0 = không giới hạn');?></small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label"><?=__('Giới hạn mỗi user:');?></label>
                                        <input type="number" class="form-control" name="user_limit"
                                            value="0" min="0">
                                        <small class="text-muted"><?=__('0 = không giới hạn');?></small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label"><?=__('Trạng thái:');?></label>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="status" 
                                                id="couponStatus" checked>
                                            <label class="form-check-label" for="couponStatus">
                                                <?=__('Kích hoạt mã giảm giá');?>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><?=__('Ngày bắt đầu:');?></label>
                                        <input type="datetime-local" class="form-control" name="start_date">
                                        <small class="text-muted"><?=__('Để trống = bắt đầu ngay');?></small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><?=__('Ngày kết thúc:');?></label>
                                        <input type="datetime-local" class="form-control" name="end_date">
                                        <small class="text-muted"><?=__('Để trống = không giới hạn');?></small>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <label class="form-label"><?=__('Mô tả:');?></label>
                                        <textarea class="form-control" name="description" rows="3"
                                            placeholder="<?=__('Nhập mô tả về mã giảm giá');?>"></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><?=__('Áp dụng cho sản phẩm:');?></label>
                                        <select class="form-select" name="product_ids[]" id="product_ids" multiple size="8" onchange="loadPlansByProducts()">
                                            <option value=""><?=__('-- Tất cả sản phẩm --');?></option>
                                            <?php foreach($products_list as $product): ?>
                                            <option value="<?=$product['id'];?>">
                                                <?=htmlspecialchars(html_entity_decode($product['name'], ENT_QUOTES, 'UTF-8'));?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted"><?=__('Giữ Ctrl/Cmd để chọn nhiều. Chọn "-- Tất cả sản phẩm --" = áp dụng cho tất cả sản phẩm và tất cả gói');?></small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><?=__('Áp dụng cho gói:');?></label>
                                        <select class="form-select" name="plan_ids[]" id="plan_ids" multiple size="8">
                                            <option value=""><?=__('-- Tất cả gói --');?></option>
                                            <?php foreach($plans_list as $plan): ?>
                                            <option value="<?=$plan['id'];?>" data-product-id="<?=$plan['product_id'];?>" class="plan-option">
                                                <?=htmlspecialchars(html_entity_decode($plan['product_name'], ENT_QUOTES, 'UTF-8'));?> - <?=htmlspecialchars(html_entity_decode($plan['name'], ENT_QUOTES, 'UTF-8'));?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted"><?=__('Gói sẽ được lọc theo sản phẩm đã chọn. Chọn "-- Tất cả gói --" = áp dụng cho tất cả gói của sản phẩm đã chọn');?></small>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end mt-3">
                                <a href="<?=base_url_admin('coupons');?>" class="btn btn-secondary me-2">
                                    <i class="fa-solid fa-times me-1"></i><?=__('Hủy');?>
                                </a>
                                <button type="submit" name="submit" class="btn btn-primary">
                                    <i class="fa-solid fa-save me-1"></i><?=__('Thêm mã giảm giá');?>
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
require_once(__DIR__.'/footer.php');
?>

<script>
// Tự động chuyển mã thành chữ in hoa
document.getElementById('coupon-code').addEventListener('input', function() {
    this.value = this.value.toUpperCase();
});

// Toggle hiển thị theo loại mã giảm giá
function toggleCouponType() {
    var type = document.getElementById('coupon-type').value;
    var valueUnit = document.getElementById('coupon-value-unit');
    var valueHint = document.getElementById('coupon-value-hint');
    var valueInput = document.getElementById('coupon-value');
    
    if(type == 'percentage') {
        valueUnit.textContent = '%';
        valueHint.textContent = '<?=__("Nhập số phần trăm giảm giá (0-100)");?>';
        valueInput.setAttribute('max', '100');
    } else {
        valueUnit.textContent = '<?=getCurrencyNameDefault();?>';
        valueHint.textContent = '<?=__("Nhập số tiền giảm giá cố định");?>';
        valueInput.removeAttribute('max');
    }
}

// Load plans theo sản phẩm đã chọn
function loadPlansByProducts() {
    var productSelect = document.getElementById('product_ids');
    var planSelect = document.getElementById('plan_ids');
    var selectedProducts = Array.from(productSelect.selectedOptions).map(opt => opt.value).filter(v => v !== '');
    
    // Lấy tất cả options của plans
    var allPlanOptions = planSelect.querySelectorAll('.plan-option');
    
    // Nếu chọn "Tất cả sản phẩm" (không có sản phẩm nào được chọn hoặc có option rỗng được chọn)
    var hasAllProducts = Array.from(productSelect.selectedOptions).some(opt => opt.value === '');
    
    if(hasAllProducts || selectedProducts.length === 0) {
        // Hiển thị tất cả gói
        allPlanOptions.forEach(function(option) {
            option.style.display = '';
        });
    } else {
        // Chỉ hiển thị gói của sản phẩm đã chọn
        allPlanOptions.forEach(function(option) {
            var planProductId = option.getAttribute('data-product-id');
            if(selectedProducts.includes(planProductId)) {
                option.style.display = '';
            } else {
                option.style.display = 'none';
                // Bỏ chọn nếu đã chọn trước đó
                option.selected = false;
            }
        });
    }
    
    // Đảm bảo option "-- Tất cả gói --" luôn hiển thị
    var allPlansOption = planSelect.querySelector('option[value=""]');
    if(allPlansOption) {
        allPlansOption.style.display = '';
    }
}

// Khởi tạo khi trang load
document.addEventListener('DOMContentLoaded', function() {
    loadPlansByProducts();
});
</script>

