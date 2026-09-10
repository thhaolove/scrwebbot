<?php if (!defined('IN_SITE')) {
    die('The Request Not Found');
}
$body = [
    'title' => __('Quản lý API Keys') . ' | ' . $CMSNT->site('title'),
    'desc'   => $CMSNT->site('description'),
    'keyword' => $CMSNT->site('keywords')
];
$body['header'] = '';
$body['footer'] = '';

require_once(__DIR__ . '/../../models/is_admin.php');
require_once(__DIR__ . '/header.php');
require_once(__DIR__ . '/sidebar.php');
require_once(__DIR__ . '/../../libs/services/ApiKeyService.php');

if (checkPermission($getUser['admin'], 'view_api_keys') != true) {
    die('<script type="text/javascript">if(!alert("' . __('Bạn không có quyền sử dụng tính năng này') . '")){window.history.back();}</script>');
}

// Xử lý phân trang
$limit = isset($_GET['limit']) ? validate_int($_GET['limit'], 5, 100) : 10;
$limit = $limit ?: 10;

$page = isset($_GET['page']) ? validate_int($_GET['page'], 1, 99999) : 1;
$page = $page ?: 1;

$from = ($page - 1) * $limit;

// Build WHERE clause
$where_conditions = ["ak.`id` > 0"];
$where_params = [];

$username = '';
$status = '';

// Filter theo user
if (!empty($_GET['username'])) {
    $username = validate_string($_GET['username'], 100);
    if ($username !== false) {
        $userSearch = $CMSNT->get_row_safe("SELECT `id` FROM `users` WHERE `username` = ?", [$username]);
        if ($userSearch) {
            $where_conditions[] = 'ak.`user_id` = ?';
            $where_params[] = $userSearch['id'];
        } else {
            $where_conditions[] = '1 = 0';
        }
    }
}

// Filter theo status
if (isset($_GET['status']) && $_GET['status'] !== '') {
    $status = validate_int($_GET['status'], 0, 1);
    if ($status !== false) {
        $where_conditions[] = 'ak.`status` = ?';
        $where_params[] = $status;
    }
}

$where_clause = implode(' AND ', $where_conditions);

// Query với prepared statements
$sql_list = "SELECT ak.*, u.username 
             FROM `api_keys` ak
             LEFT JOIN `users` u ON ak.user_id = u.id
             WHERE {$where_clause} 
             ORDER BY ak.`id` DESC 
             LIMIT ?, ?";
$params_list = array_merge($where_params, [$from, $limit]);
$listDatatable = $CMSNT->get_list_safe($sql_list, $params_list);

$sql_count = "SELECT COUNT(*) as total FROM `api_keys` ak WHERE {$where_clause}";
$countResult = $CMSNT->get_row_safe($sql_count, $where_params);
$totalDatatable = $countResult ? $countResult['total'] : 0;

$urlDatatable = pagination(
    base_url_admin("api-keys&limit={$limit}&status={$status}&username=" . urlencode($username) . "&"),
    $from,
    $totalDatatable,
    $limit
);

// Thống kê
$statsTotal = $CMSNT->num_rows_safe("SELECT id FROM `api_keys`", []);
$statsActive = $CMSNT->num_rows_safe("SELECT id FROM `api_keys` WHERE `status` = 1", []);
$statsInactive = $CMSNT->num_rows_safe("SELECT id FROM `api_keys` WHERE `status` = 0", []);

// Đếm request hôm nay
$today = date('Y-m-d 00:00:00');
$statsRequestsToday = $CMSNT->num_rows_safe("SELECT id FROM `api_logs` WHERE `created_at` >= ?", [$today]);
?>

<div class="main-content app-content">
    <div class="container-fluid">
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">
                <i class="ri-key-2-line me-2"></i><?= __('Quản lý API Keys'); ?>
            </h1>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalApiConfig">
                    <i class="ri-settings-3-line me-1"></i><?= __('Cấu hình'); ?>
                </button>
            </div>
        </div>

        <!-- Thống kê -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body d-flex align-items-center">
                        <div class="avatar avatar-lg bg-primary-transparent me-3">
                            <i class="ri-key-line fs-20"></i>
                        </div>
                        <div>
                            <h5 class="mb-0"><?= format_cash($statsTotal); ?></h5>
                            <small class="text-muted"><?= __('Tổng API Keys'); ?></small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body d-flex align-items-center">
                        <div class="avatar avatar-lg bg-success-transparent me-3">
                            <i class="ri-checkbox-circle-line fs-20"></i>
                        </div>
                        <div>
                            <h5 class="mb-0"><?= format_cash($statsActive); ?></h5>
                            <small class="text-muted"><?= __('Đang hoạt động'); ?></small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body d-flex align-items-center">
                        <div class="avatar avatar-lg bg-danger-transparent me-3">
                            <i class="ri-close-circle-line fs-20"></i>
                        </div>
                        <div>
                            <h5 class="mb-0"><?= format_cash($statsInactive); ?></h5>
                            <small class="text-muted"><?= __('Đã vô hiệu'); ?></small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body d-flex align-items-center">
                        <div class="avatar avatar-lg bg-info-transparent me-3">
                            <i class="ri-bar-chart-line fs-20"></i>
                        </div>
                        <div>
                            <h5 class="mb-0"><?= format_cash($statsRequestsToday); ?></h5>
                            <small class="text-muted"><?= __('Requests hôm nay'); ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-header justify-content-between">
                        <div class="card-title">
                            <?= __('DANH SÁCH API KEYS'); ?>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="<?= base_url_admin('api-logs'); ?>" class="btn btn-sm btn-outline-info">
                                <i class="ri-file-list-line me-1"></i> <?= __('API Logs'); ?>
                            </a>
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createApiKeyModal">
                                <i class="ri-add-line me-1"></i> <?= __('Tạo API Key'); ?>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Form tìm kiếm -->
                        <form action="<?= base_url(); ?>" method="GET" class="mb-4">
                            <input type="hidden" name="module" value="<?= $CMSNT->site('path_admin'); ?>">
                            <input type="hidden" name="action" value="api-keys">

                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <input class="form-control form-control-sm" value="<?= htmlspecialchars($username); ?>" name="username" placeholder="<?= __('Username'); ?>">
                                </div>
                                <div class="col-md-3">
                                    <select class="form-select form-select-sm" name="status">
                                        <option value=""><?= __('Trạng thái'); ?></option>
                                        <option <?= $status === 1 ? 'selected' : ''; ?> value="1"><?= __('Đang hoạt động'); ?></option>
                                        <option <?= $status === 0 ? 'selected' : ''; ?> value="0"><?= __('Đã vô hiệu'); ?></option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-sm btn-primary"><i class="fa fa-search"></i></button>
                                        <a class="btn btn-sm btn-outline-danger" href="<?= base_url_admin('api-keys'); ?>"><i class="fa fa-times"></i></a>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <label class="me-2"><?= __('Hiển thị'); ?>:</label>
                                    <select name="limit" onchange="this.form.submit()" class="form-select form-select-sm d-inline-block w-auto">
                                        <?php foreach ([5, 10, 20, 50] as $l): ?>
                                            <option <?= $limit == $l ? 'selected' : ''; ?> value="<?= $l; ?>"><?= $l; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <small class="text-muted"><?= __('Tổng'); ?>: <?= format_cash($totalDatatable); ?> <?= __('kết quả'); ?></small>
                            </div>
                        </form>

                        <!-- Bảng danh sách -->
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered align-middle">
                                <thead>
                                    <tr>
                                        <th><?= __('User'); ?></th>
                                        <th><?= __('API Key'); ?></th>
                                        <th class="text-center"><?= __('Quyền'); ?></th>
                                        <th class="text-center"><?= __('Rate Limit'); ?></th>
                                        <th class="text-center"><?= __('Trạng thái'); ?></th>
                                        <th><?= __('Sử dụng cuối'); ?></th>
                                        <th class="text-center"><?= __('Thao tác'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($listDatatable)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">
                                                <i class="ri-key-line fs-40 d-block mb-2"></i>
                                                <?= __('Chưa có API Key nào'); ?>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($listDatatable as $row):
                                            $permissions = json_decode($row['permissions'], true) ?: [];
                                        ?>
                                            <tr>
                                                <td>
                                                    <?php if ($row['user_id'] > 0): ?>
                                                        <?php $user = $CMSNT->get_row_safe("SELECT * FROM users WHERE id = ?", [$row['user_id']]); ?>
                                                        <?php if ($user): ?>
                                                            <i class="fa-solid fa-user"></i> <?= $user['username']; ?> [ID
                                                            <?= $row['user_id']; ?>] <a class="text-primary"
                                                                href="<?= base_url_admin('user-edit&id=' . $row['user_id']); ?>"><i
                                                                    class="fa-solid fa-edit"></i></a><br>
                                                            <i class="fa-solid fa-wallet"></i> <?= __('Số dư hiện tại:'); ?>
                                                            <strong
                                                                style="color:red;"><?= format_currency($user['money']); ?></strong><br>
                                                            <i class="fa-solid fa-money-bill-trend-up"></i> <?= __('Tổng nạp:'); ?>
                                                            <strong
                                                                style="color:green;"><?= format_currency($user['total_money']); ?></strong>
                                                        <?php else: ?>
                                                            <span class="text-warning"><i class="fa-solid fa-user-slash"></i> [ID <?= $row['user_id']; ?>] <?= __('Đã xóa'); ?></span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted"><?= __('Hệ thống'); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <code class="user-select-all"><?= $row['api_key']; ?></code>
                                                </td>
                                                <td class="text-center">
                                                    <?php if (in_array('all', $permissions)): ?>
                                                        <span class="badge bg-danger"><?= __('Toàn quyền'); ?></span>
                                                    <?php else: ?>
                                                        <span class="badge bg-info"><?= count($permissions); ?> <?= __('quyền'); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <small>
                                                        <?= $row['rate_limit']; ?>/<?= __('phút'); ?>
                                                        <br>
                                                        <?= format_cash($row['daily_limit']); ?>/<?= __('ngày'); ?>
                                                    </small>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($row['status'] == 1): ?>
                                                        <span class="badge bg-success"><?= __('Hoạt động'); ?></span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger"><?= __('Vô hiệu'); ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($row['expires_at'] && strtotime($row['expires_at']) < time()): ?>
                                                        <br><span class="badge bg-warning"><?= __('Hết hạn'); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($row['last_used_at']): ?>
                                                        <small>
                                                            <?= $row['last_used_at']; ?>
                                                            <br>
                                                            <span class="text-muted"><?= $row['last_ip']; ?></span>
                                                        </small>
                                                    <?php else: ?>
                                                        <small class="text-muted">-</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <div class="btn-group">
                                                        <button onclick="viewApiKey(<?= $row['id']; ?>)" class="btn btn-sm btn-outline-info" title="<?= __('Xem chi tiết'); ?>">
                                                            <i class="ri-eye-line"></i>
                                                        </button>
                                                        <button onclick="toggleStatus(<?= $row['id']; ?>, <?= $row['status']; ?>)" class="btn btn-sm btn-outline-<?= $row['status'] == 1 ? 'warning' : 'success'; ?>" title="<?= $row['status'] == 1 ? __('Vô hiệu hóa') : __('Kích hoạt'); ?>">
                                                            <i class="ri-<?= $row['status'] == 1 ? 'pause' : 'play'; ?>-line"></i>
                                                        </button>
                                                        <button onclick="deleteApiKey(<?= $row['id']; ?>)" class="btn btn-sm btn-outline-danger" title="<?= __('Xóa'); ?>">
                                                            <i class="ri-delete-bin-line"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Phân trang -->
                        <?php if ($totalDatatable > $limit): ?>
                            <div class="d-flex justify-content-end mt-3">
                                <?= $urlDatatable; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal tạo API Key -->
<div class="modal fade" id="createApiKeyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __('Tạo API Key mới'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createApiKeyForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><?= __('Username'); ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="username" id="apiKeyUsername" required placeholder="<?= __('Nhập username của user'); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= __('Tên API Key'); ?></label>
                        <input type="text" class="form-control" name="name" placeholder="<?= __('VD: Production Key'); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= __('Quyền'); ?></label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="permissions[]" value="orders.create" checked>
                            <label class="form-check-label"><?= __('Tạo đơn hàng'); ?> (orders.create)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="permissions[]" value="orders.view" checked>
                            <label class="form-check-label"><?= __('Xem đơn hàng'); ?> (orders.view)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="permissions[]" value="products.view" checked>
                            <label class="form-check-label"><?= __('Xem sản phẩm'); ?> (products.view)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="permissions[]" value="balance.view" checked>
                            <label class="form-check-label"><?= __('Xem số dư'); ?> (balance.view)</label>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <label class="form-label"><?= __('Rate Limit'); ?></label>
                            <input type="number" class="form-control" name="rate_limit" value="60" min="1" max="1000">
                            <small class="text-muted"><?= __('Request/phút'); ?></small>
                        </div>
                        <div class="col-6">
                            <label class="form-label"><?= __('Daily Limit'); ?></label>
                            <input type="number" class="form-control" name="daily_limit" value="10000" min="100" max="1000000">
                            <small class="text-muted"><?= __('Request/ngày'); ?></small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('Hủy'); ?></button>
                    <button type="submit" class="btn btn-primary"><?= __('Tạo API Key'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal hiển thị API Key mới -->
<div class="modal fade" id="showApiKeyModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="ri-checkbox-circle-line me-2"></i><?= __('API Key đã được tạo'); ?></h5>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="ri-error-warning-line me-2"></i>
                    <strong><?= __('Quan trọng!'); ?></strong> <?= __('API Secret chỉ được hiển thị một lần duy nhất. Hãy lưu lại ngay.'); ?>
                </div>
                <div class="mb-3">
                    <label class="form-label"><?= __('API Key'); ?></label>
                    <input type="text" class="form-control user-select-all" id="newApiKey" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label"><?= __('API Secret'); ?></label>
                    <input type="text" class="form-control user-select-all text-danger" id="newApiSecret" readonly>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal" onclick="location.reload()"><?= __('Đã lưu, đóng'); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Cấu hình API -->
<div class="modal fade" id="modalApiConfig" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="ri-settings-3-line me-2"></i><?= __('Cấu hình API cho người dùng'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Bật/Tắt API cho user -->
                <div class="d-flex align-items-center justify-content-between p-3 border rounded mb-3">
                    <div>
                        <h6 class="mb-1"><?= __('Cho phép người dùng tạo API Key'); ?></h6>
                        <small class="text-muted"><?= __('Bật/Tắt cho phép người dùng tự tạo và quản lý API Key của họ'); ?></small>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="apiUserEnabledSwitch"
                            <?php echo ($CMSNT->site('api_user_enabled') == 1) ? 'checked' : ''; ?>
                            style="width: 50px; height: 25px; cursor: pointer;">
                    </div>
                </div>

                <!-- Số API Key tối đa -->
                <div class="mb-3">
                    <label class="form-label"><?= __('Số API Key tối đa mỗi user'); ?></label>
                    <input type="number" class="form-control" id="apiMaxKeysPerUser"
                        value="<?= htmlspecialchars($CMSNT->site('api_max_keys_per_user') ?: 5); ?>"
                        min="1" max="100">
                    <small class="text-muted"><?= __('Giới hạn số lượng API Key mỗi người dùng có thể tạo'); ?></small>
                </div>

                <!-- Rate Limit -->
                <div class="row">
                    <div class="col-6">
                        <div class="mb-3">
                            <label class="form-label"><?= __('Rate Limit / phút'); ?></label>
                            <input type="number" class="form-control" id="apiUserRateLimitMinute"
                                value="<?= htmlspecialchars($CMSNT->site('api_user_rate_limit_minute') ?: 60); ?>"
                                min="1" max="1000">
                            <small class="text-muted"><?= __('Requests/phút mặc định'); ?></small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="mb-3">
                            <label class="form-label"><?= __('Rate Limit / ngày'); ?></label>
                            <input type="number" class="form-control" id="apiUserRateLimitDay"
                                value="<?= htmlspecialchars($CMSNT->site('api_user_rate_limit_day') ?: 10000); ?>"
                                min="100" max="1000000">
                            <small class="text-muted"><?= __('Requests/ngày mặc định'); ?></small>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info mb-0">
                    <i class="ri-information-line me-2"></i>
                    <?= __('Rate Limit mặc định áp dụng cho API Key mới do người dùng tạo. Admin có thể tùy chỉnh cho từng key.'); ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('Đóng'); ?></button>
                <button type="button" class="btn btn-primary" onclick="saveApiConfig()"><?= __('Lưu cấu hình'); ?></button>
            </div>
        </div>
    </div>
</div>

<?php require_once(__DIR__ . '/footer.php'); ?>

<script>
    // Tạo API Key
    $('#createApiKeyForm').on('submit', function(e) {
        e.preventDefault();

        const formData = $(this).serializeArray();
        formData.push({
            name: 'action',
            value: 'create_api_key'
        });
        formData.push({
            name: 'csrf_token',
            value: '<?= generate_csrf_token(); ?>'
        });

        $.ajax({
            url: '<?= base_url('ajaxs/admin/update.php'); ?>',
            type: 'POST',
            dataType: 'JSON',
            data: formData,
            success: function(result) {
                if (result.status == 'success') {
                    $('#createApiKeyModal').modal('hide');
                    $('#newApiKey').val(result.api_key);
                    $('#newApiSecret').val(result.api_secret);
                    $('#showApiKeyModal').modal('show');
                } else {
                    showMessage(result.msg, result.status);
                }
            }
        });
    });

    // Toggle trạng thái
    function toggleStatus(id, currentStatus) {
        const action = currentStatus == 1 ? 'disable' : 'enable';
        const msg = currentStatus == 1 ? '<?= __('Bạn có chắc muốn vô hiệu hóa API Key này?'); ?>' : '<?= __('Bạn có chắc muốn kích hoạt API Key này?'); ?>';

        cuteAlert({
            type: "question",
            title: "<?= __('Xác nhận'); ?>",
            message: msg,
            confirmText: "<?= __('Xác nhận'); ?>",
            cancelText: "<?= __('Hủy'); ?>"
        }).then((e) => {
            if (e) {
                $.ajax({
                    url: '<?= base_url('ajaxs/admin/update.php'); ?>',
                    type: 'POST',
                    dataType: 'JSON',
                    data: {
                        action: 'toggle_api_key',
                        id: id,
                        status: currentStatus == 1 ? 0 : 1,
                        csrf_token: '<?= generate_csrf_token(); ?>'
                    },
                    success: function(result) {
                        showMessage(result.msg, result.status);
                        if (result.status == 'success') {
                            setTimeout(() => location.reload(), 1000);
                        }
                    }
                });
            }
        });
    }

    // Xóa API Key
    function deleteApiKey(id) {
        cuteAlert({
            type: "question",
            title: "<?= __('Xác nhận xóa'); ?>",
            message: "<?= __('API Key sẽ bị xóa vĩnh viễn. Tiếp tục?'); ?>",
            confirmText: "<?= __('Xóa'); ?>",
            cancelText: "<?= __('Hủy'); ?>"
        }).then((e) => {
            if (e) {
                $.ajax({
                    url: '<?= base_url('ajaxs/admin/remove.php'); ?>',
                    type: 'POST',
                    dataType: 'JSON',
                    data: {
                        action: 'api_key',
                        id: id,
                        csrf_token: '<?= generate_csrf_token(); ?>'
                    },
                    success: function(result) {
                        showMessage(result.msg, result.status);
                        if (result.status == 'success') {
                            setTimeout(() => location.reload(), 1000);
                        }
                    }
                });
            }
        });
    }

    // Xem chi tiết
    function viewApiKey(id) {
        // Có thể mở modal hiển thị chi tiết hoặc chuyển trang
        window.location.href = '<?= base_url_admin('api-key-detail&id='); ?>' + id;
    }

    // Lưu cấu hình API
    function saveApiConfig() {
        const apiUserEnabled = $('#apiUserEnabledSwitch').is(':checked') ? 1 : 0;
        const apiMaxKeysPerUser = $('#apiMaxKeysPerUser').val();
        const apiUserRateLimitMinute = $('#apiUserRateLimitMinute').val();
        const apiUserRateLimitDay = $('#apiUserRateLimitDay').val();

        $.ajax({
            url: '<?= base_url('ajaxs/admin/update.php'); ?>',
            type: 'POST',
            dataType: 'JSON',
            data: {
                action: 'update_api_config',
                api_user_enabled: apiUserEnabled,
                api_max_keys_per_user: apiMaxKeysPerUser,
                api_user_rate_limit_minute: apiUserRateLimitMinute,
                api_user_rate_limit_day: apiUserRateLimitDay,
                csrf_token: '<?= generate_csrf_token(); ?>'
            },
            success: function(result) {
                showMessage(result.msg, result.status);
                if (result.status == 'success') {
                    setTimeout(() => {
                        $('#modalApiConfig').modal('hide');
                    }, 1000);
                }
            }
        });
    }
</script>