<?php if (!defined('IN_SITE')) {
    die('The Request Not Found');
}
$body = [
    'title' => __('Quản lý Flash Sale') . ' | ' . $CMSNT->site('title'),
    'desc'   => $CMSNT->site('description'),
    'keyword' => $CMSNT->site('keywords')
];
$body['header'] = '';
$body['footer'] = '';
require_once(__DIR__ . '/../../models/is_admin.php');
require_once(__DIR__ . '/header.php');
require_once(__DIR__ . '/sidebar.php');
require_once(__DIR__ . '/../../libs/database/flashsale.php');

if (checkPermission($getUser['admin'], 'view_flash_sale') != true) {
    $role_name = getRoleName('view_flash_sale');
    die('<script type="text/javascript">if(!alert("' . sprintf(__('Bạn không có quyền %s'), $role_name) . '")){window.history.back();}</script>');
}

$FlashSaleHandler = new FlashSaleHandler();

// Phân trang an toàn
$limit = isset($_GET['limit']) ? (validate_int($_GET['limit'], 1, 1000) ?: 20) : 20;
$page = isset($_GET['page']) ? (validate_int($_GET['page'], 1, 1000000) ?: 1) : 1;
$from = ($page - 1) * $limit;

// Biến giữ giá trị hiển thị lại
$search = '';
$status_filter = '';
$date_from = '';
$date_to = '';

// WHERE an toàn với prepared statements
$where_conditions = ["1 = 1"];
$where_params = [];

// Lọc theo trạng thái
if (isset($_GET['status']) && $_GET['status'] !== '') {
    $status_input = validate_string($_GET['status'], 20);
    if ($status_input !== false && in_array($status_input, ['active', 'upcoming', 'ended'])) {
        $status_filter = $status_input;
        $current_time = gettime();
        if ($status_input == 'active') {
            $where_conditions[] = '`status` = 1 AND `start_time` <= ? AND `end_time` > ?';
            $where_params[] = $current_time;
            $where_params[] = $current_time;
        } elseif ($status_input == 'upcoming') {
            $where_conditions[] = '`status` = 1 AND `start_time` > ?';
            $where_params[] = $current_time;
        } else {
            $where_conditions[] = '(`status` = 0 OR `end_time` <= ?)';
            $where_params[] = $current_time;
        }
    }
}

// Lọc theo thời gian từ
if (!empty($_GET['date_from'])) {
    $date_from_input = validate_string($_GET['date_from'], 20);
    if ($date_from_input !== false && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from_input)) {
        $date_from = $date_from_input;
        $where_conditions[] = 'DATE(`created_at`) >= ?';
        $where_params[] = $date_from;
    }
}

// Lọc theo thời gian đến
if (!empty($_GET['date_to'])) {
    $date_to_input = validate_string($_GET['date_to'], 20);
    if ($date_to_input !== false && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to_input)) {
        $date_to = $date_to_input;
        $where_conditions[] = 'DATE(`created_at`) <= ?';
        $where_params[] = $date_to;
    }
}

// Tìm kiếm (LIKE)
if (!empty($_GET['search'])) {
    $search_input = validate_string($_GET['search'], 255, 1);
    if ($search_input !== false) {
        $search = $search_input;
        $where_conditions[] = '(`name` LIKE ? OR `description` LIKE ?)';
        $searchPattern = '%' . $search . '%';
        $where_params[] = $searchPattern;
        $where_params[] = $searchPattern;
    }
}

$where_clause = implode(' AND ', $where_conditions);

// Tổng số Flash Sale
$countSql = "SELECT COUNT(*) AS total_count FROM `flash_sales` WHERE $where_clause";
$totalRow = $CMSNT->get_row_safe($countSql, $where_params);
$total = (int)($totalRow['total_count'] ?? 0);
$total_pages = ceil($total / $limit);

// Lấy danh sách Flash Sale
$listSql = "SELECT * FROM `flash_sales` WHERE $where_clause ORDER BY `id` DESC LIMIT ?, ?";
$listParams = array_merge($where_params, [$from, $limit]);
$flash_sales = $CMSNT->get_list_safe($listSql, $listParams);
?>

<div class="main-content app-content">
    <div class="container-fluid">
        <!-- Tiêu đề trang -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-name fw-semibold fs-18 mb-0">
                <i class="fa-solid fa-bolt text-warning me-1"></i><?= __('Quản lý Flash Sale'); ?>
            </h1>
            <div class="ms-md-1 ms-0">
                <a href="<?= base_url_admin('flash-sale-add'); ?>" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-plus me-1"></i><?= __('Thêm Flash Sale'); ?>
                </a>
            </div>
        </div>

        <!-- Bộ lọc -->
        <div class="row mb-3">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-body">
                        <form method="GET" action="<?= base_url(); ?>" class="row g-3">
                            <input type="hidden" name="module" value="<?= $CMSNT->site('path_admin'); ?>">
                            <input type="hidden" name="action" value="flash-sales">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label"><?= __('Tìm kiếm'); ?></label>
                                    <input type="text" class="form-control" name="search"
                                        value="<?= htmlspecialchars($search); ?>"
                                        placeholder="<?= __('Tên chương trình...'); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label"><?= __('Trạng thái'); ?></label>
                                    <select class="form-select" name="status">
                                        <option value=""><?= __('Tất cả'); ?></option>
                                        <option value="active" <?= $status_filter == 'active' ? 'selected' : ''; ?>><?= __('Đang diễn ra'); ?></option>
                                        <option value="upcoming" <?= $status_filter == 'upcoming' ? 'selected' : ''; ?>><?= __('Sắp diễn ra'); ?></option>
                                        <option value="ended" <?= $status_filter == 'ended' ? 'selected' : ''; ?>><?= __('Đã kết thúc'); ?></option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label"><?= __('Từ ngày'); ?></label>
                                    <input type="date" class="form-control" name="date_from"
                                        value="<?= htmlspecialchars($date_from); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label"><?= __('Đến ngày'); ?></label>
                                    <input type="date" class="form-control" name="date_to"
                                        value="<?= htmlspecialchars($date_to); ?>">
                                </div>
                                <div class="col-md-1">
                                    <label class="form-label"><?= __('Số lượng'); ?></label>
                                    <select class="form-select" name="limit">
                                        <option value="10" <?= $limit == 10 ? 'selected' : ''; ?>>10</option>
                                        <option value="20" <?= $limit == 20 ? 'selected' : ''; ?>>20</option>
                                        <option value="50" <?= $limit == 50 ? 'selected' : ''; ?>>50</option>
                                        <option value="100" <?= $limit == 100 ? 'selected' : ''; ?>>100</option>
                                    </select>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="fa-solid fa-filter me-1"></i><?= __('Lọc'); ?>
                                    </button>
                                    <a href="<?= base_url_admin('flash-sales'); ?>" class="btn btn-secondary">
                                        <i class="fa-solid fa-times me-1"></i><?= __('Bỏ lọc'); ?>
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Danh sách Flash Sale -->
        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-body p-0">
                        <?php if (count($flash_sales) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover border text-nowrap">
                                    <thead>
                                        <tr>
                                            <th><?= __('Tên chương trình'); ?></th>
                                            <th><?= __('Giảm giá'); ?></th>
                                            <th><?= __('Thời gian'); ?></th>
                                            <th class="text-center"><?= __('Đã bán/Giới hạn'); ?></th>
                                            <th class="text-center"><?= __('Giới hạn/user'); ?></th>
                                            <th class="text-center"><?= __('Trạng thái'); ?></th>
                                            <th><?= __('Ngày tạo'); ?></th>
                                            <th><?= __('Thao tác'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($flash_sales as $flash_sale):
                                            // Kiểm tra trạng thái
                                            $current_time = time();
                                            $start_time = strtotime($flash_sale['start_time']);
                                            $end_time = strtotime($flash_sale['end_time']);

                                            $status_text = '';
                                            $status_class = '';

                                            if ($flash_sale['status'] != 1) {
                                                $status_text = __('Đã tắt');
                                                $status_class = 'bg-secondary';
                                            } elseif ($start_time > $current_time) {
                                                $status_text = __('Sắp diễn ra');
                                                $status_class = 'bg-info';
                                            } elseif ($end_time <= $current_time) {
                                                $status_text = __('Đã kết thúc');
                                                $status_class = 'bg-danger';
                                            } elseif ($flash_sale['quantity_limit'] > 0 && $flash_sale['quantity_sold'] >= $flash_sale['quantity_limit']) {
                                                $status_text = __('Hết hàng');
                                                $status_class = 'bg-warning';
                                            } else {
                                                $status_text = __('Đang diễn ra');
                                                $status_class = 'bg-success';
                                            }
                                        ?>
                                            <tr id="flash-sale-<?= $flash_sale['id']; ?>">
                                                <td>
                                                    <div class="d-flex flex-column">
                                                        <strong class="text-primary">
                                                            <i class="fa-solid fa-bolt text-warning me-1"></i>
                                                            <?= htmlspecialchars($flash_sale['name']); ?>
                                                        </strong>
                                                        <?php if (!empty($flash_sale['description'])): ?>
                                                            <small class="text-muted"><?= htmlspecialchars(mb_substr($flash_sale['description'] ?? '', 0, 50)); ?><?= mb_strlen($flash_sale['description'] ?? '') > 50 ? '...' : ''; ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($flash_sale['discount_type'] == 'percentage'): ?>
                                                        <span class="badge bg-info">
                                                            <i class="fa-solid fa-percent me-1"></i><?= number_format($flash_sale['discount_value'], 0); ?>%
                                                        </span>
                                                        <?php if ($flash_sale['max_discount_amount'] > 0): ?>
                                                            <br><small class="text-muted"><?= __('Tối đa'); ?> <?= format_currency($flash_sale['max_discount_amount']); ?></small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">
                                                            <i class="fa-solid fa-money-bill me-1"></i><?= format_currency($flash_sale['discount_value']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex flex-column">
                                                        <small>
                                                            <i class="fa-solid fa-play text-success me-1"></i>
                                                            <?= date('d/m/Y H:i', $start_time); ?>
                                                        </small>
                                                        <small>
                                                            <i class="fa-solid fa-stop text-danger me-1"></i>
                                                            <?= date('d/m/Y H:i', $end_time); ?>
                                                        </small>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-primary-transparent">
                                                        <?= $flash_sale['quantity_sold']; ?>
                                                        <?php if ($flash_sale['quantity_limit'] > 0): ?>
                                                            / <?= $flash_sale['quantity_limit']; ?>
                                                        <?php else: ?>
                                                            / ∞
                                                        <?php endif; ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($flash_sale['per_user_limit'] > 0): ?>
                                                        <span class="badge bg-secondary-transparent"><?= $flash_sale['per_user_limit']; ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">∞</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge <?= $status_class; ?>"><?= $status_text; ?></span>
                                                    <div class="form-check form-switch d-flex justify-content-center mt-1">
                                                        <input class="form-check-input" type="checkbox"
                                                            id="status<?= $flash_sale['id']; ?>"
                                                            <?= $flash_sale['status'] == 1 ? 'checked' : ''; ?>
                                                            onchange="updateStatus('<?= $flash_sale['id']; ?>', this.checked ? 1 : 0)"
                                                            style="transform: scale(1.2);">
                                                    </div>
                                                </td>
                                                <td>
                                                    <small>
                                                        <i class="fa-regular fa-clock me-1"></i><?= date('d/m/Y H:i', strtotime($flash_sale['created_at'])); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="btn-list">
                                                        <a href="<?= base_url_admin('flash-sale-edit&id=' . $flash_sale['id']); ?>"
                                                            class="btn btn-sm btn-info">
                                                            <i class="fa-solid fa-edit me-1"></i><?= __('Sửa'); ?>
                                                        </a>
                                                        <button onclick="removeFlashSale('<?= $flash_sale['id']; ?>')"
                                                            class="btn btn-sm btn-danger">
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
                            $pagination_url = base_url_admin('flash-sales');
                            $pagination_url .= '&limit=' . $limit;
                            if (!empty($search)) $pagination_url .= '&search=' . urlencode($search);
                            if ($status_filter) $pagination_url .= '&status=' . $status_filter;
                            if ($date_from) $pagination_url .= '&date_from=' . urlencode($date_from);
                            if ($date_to) $pagination_url .= '&date_to=' . urlencode($date_to);
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
                            <div class="alert alert-warning m-3">
                                <i class="fa-solid fa-exclamation-circle me-2"></i><?= __('Chưa có Flash Sale nào.'); ?>
                                <a href="<?= base_url_admin('flash-sale-add'); ?>" class="alert-link ms-2"><?= __('Tạo ngay'); ?></a>
                            </div>
                        <?php endif; ?>
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
    // Cập nhật trạng thái Flash Sale
    function updateStatus(id, status) {
        $.ajax({
            url: "<?= BASE_URL("ajaxs/admin/update.php"); ?>",
            method: "POST",
            dataType: "JSON",
            data: {
                action: 'updateFlashSaleStatus',
                id: id,
                status: status
            },
            success: function(result) {
                if (result.status == 'success') {
                    showMessage(result.msg, result.status);
                    location.reload();
                } else {
                    showMessage(result.msg, result.status);
                    $('#status' + id).prop('checked', !status);
                }
            },
            error: function() {
                showMessage('<?= __("Đã xảy ra lỗi"); ?>', 'error');
                $('#status' + id).prop('checked', !status);
            }
        });
    }

    // Xóa Flash Sale
    function removeFlashSale(id) {
        Swal.fire({
            title: "<?= __('Cảnh báo'); ?>",
            text: "<?= __('Bạn có chắc chắn muốn xóa Flash Sale này không? Các sản phẩm liên quan sẽ tự động được gỡ.'); ?>",
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
                        action: 'removeFlashSale',
                        id: id
                    },
                    success: function(result) {
                        if (result.status == 'success') {
                            showMessage(result.msg, 'success');
                            $('#flash-sale-' + id).fadeOut(300, function() {
                                $(this).remove();
                            });
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
</script>