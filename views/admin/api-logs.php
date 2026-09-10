<?php if (!defined('IN_SITE')) {
    die('The Request Not Found');
}
$body = [
    'title' => __('API Logs') . ' | ' . $CMSNT->site('title'),
    'desc'   => $CMSNT->site('description'),
    'keyword' => $CMSNT->site('keywords')
];
$body['header'] = '';
$body['footer'] = '';

require_once(__DIR__ . '/../../models/is_admin.php');
require_once(__DIR__ . '/header.php');
require_once(__DIR__ . '/sidebar.php');

if (checkPermission($getUser['admin'], 'view_api_logs') != true) {
    die('<script type="text/javascript">if(!alert("' . __('Bạn không có quyền sử dụng tính năng này') . '")){window.history.back();}</script>');
}

// Xử lý phân trang
$limit = isset($_GET['limit']) ? validate_int($_GET['limit'], 10, 500) : 50;
$limit = $limit ?: 50;

$page = isset($_GET['page']) ? validate_int($_GET['page'], 1, 99999) : 1;
$page = $page ?: 1;

$from = ($page - 1) * $limit;

// Build WHERE clause
$where_conditions = ["`id` > 0"];
$where_params = [];

$api_key_filter = '';
$status_filter = '';
$endpoint_filter = '';

// Filter theo API Key
if (!empty($_GET['api_key'])) {
    $api_key_filter = validate_string($_GET['api_key'], 32);
    if ($api_key_filter !== false) {
        $where_conditions[] = '`api_key` = ?';
        $where_params[] = $api_key_filter;
    }
}

// Filter theo status
if (!empty($_GET['status'])) {
    $status_filter = validate_string($_GET['status'], 20);
    if ($status_filter !== false) {
        $where_conditions[] = '`status` = ?';
        $where_params[] = $status_filter;
    }
}

// Filter theo endpoint
if (!empty($_GET['endpoint'])) {
    $endpoint_filter = validate_string($_GET['endpoint'], 100);
    if ($endpoint_filter !== false) {
        $where_conditions[] = '`endpoint` LIKE ?';
        $where_params[] = '%' . $endpoint_filter . '%';
    }
}

$where_clause = implode(' AND ', $where_conditions);

// Query
$sql_list = "SELECT * FROM `api_logs` WHERE {$where_clause} ORDER BY `id` DESC LIMIT ?, ?";
$params_list = array_merge($where_params, [$from, $limit]);
$listDatatable = $CMSNT->get_list_safe($sql_list, $params_list);

$sql_count = "SELECT COUNT(*) as total FROM `api_logs` WHERE {$where_clause}";
$countResult = $CMSNT->get_row_safe($sql_count, $where_params);
$totalDatatable = $countResult ? $countResult['total'] : 0;

$urlDatatable = pagination(
    base_url_admin("api-logs&limit={$limit}&api_key=" . urlencode($api_key_filter) . "&status=" . urlencode($status_filter) . "&endpoint=" . urlencode($endpoint_filter) . "&"),
    $from,
    $totalDatatable,
    $limit
);

// Thống kê
$today = date('Y-m-d 00:00:00');
$statsToday = $CMSNT->num_rows_safe("SELECT id FROM `api_logs` WHERE `created_at` >= ?", [$today]);
$statsSuccess = $CMSNT->num_rows_safe("SELECT id FROM `api_logs` WHERE `created_at` >= ? AND `status` = 'success'", [$today]);
$statsFailed = $CMSNT->num_rows_safe("SELECT id FROM `api_logs` WHERE `created_at` >= ? AND `status` = 'failed'", [$today]);
$statsBlocked = $CMSNT->num_rows_safe("SELECT id FROM `api_logs` WHERE `created_at` >= ? AND `status` = 'blocked'", [$today]);
?>

<div class="main-content app-content">
    <div class="container-fluid">
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">
                <i class="ri-file-list-3-line me-2"></i><?= __('API Logs'); ?>
            </h1>
            <nav>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= base_url_admin('api-keys'); ?>"><?= __('API Keys'); ?></a></li>
                    <li class="breadcrumb-item active"><?= __('Logs'); ?></li>
                </ol>
            </nav>
        </div>

        <!-- Thống kê hôm nay -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary-transparent">
                    <div class="card-body text-center py-3">
                        <h4 class="mb-1"><?= format_cash($statsToday); ?></h4>
                        <small><?= __('Tổng requests hôm nay'); ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success-transparent">
                    <div class="card-body text-center py-3">
                        <h4 class="mb-1 text-success"><?= format_cash($statsSuccess); ?></h4>
                        <small><?= __('Thành công'); ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger-transparent">
                    <div class="card-body text-center py-3">
                        <h4 class="mb-1 text-danger"><?= format_cash($statsFailed); ?></h4>
                        <small><?= __('Thất bại'); ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning-transparent">
                    <div class="card-body text-center py-3">
                        <h4 class="mb-1 text-warning"><?= format_cash($statsBlocked); ?></h4>
                        <small><?= __('Bị chặn'); ?></small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-header justify-content-between">
                        <div class="card-title">
                            <?= __('LỊCH SỬ API REQUESTS'); ?>
                        </div>
                        <a href="<?= base_url_admin('api-keys'); ?>" class="btn btn-sm btn-outline-primary">
                            <i class="ri-arrow-left-line me-1"></i> <?= __('Quay lại'); ?>
                        </a>
                    </div>
                    <div class="card-body">
                        <!-- Form tìm kiếm -->
                        <form action="<?= base_url(); ?>" method="GET" class="mb-4">
                            <input type="hidden" name="module" value="<?= $CMSNT->site('path_admin'); ?>">
                            <input type="hidden" name="action" value="api-logs">

                            <div class="row g-3 mb-3">
                                <div class="col-md-3">
                                    <input class="form-control form-control-sm" value="<?= htmlspecialchars($api_key_filter); ?>" name="api_key" placeholder="<?= __('API Key'); ?>">
                                </div>
                                <div class="col-md-3">
                                    <input class="form-control form-control-sm" value="<?= htmlspecialchars($endpoint_filter); ?>" name="endpoint" placeholder="<?= __('Endpoint'); ?>">
                                </div>
                                <div class="col-md-2">
                                    <select class="form-select form-select-sm" name="status">
                                        <option value=""><?= __('Trạng thái'); ?></option>
                                        <option <?= $status_filter == 'success' ? 'selected' : ''; ?> value="success"><?= __('Thành công'); ?></option>
                                        <option <?= $status_filter == 'failed' ? 'selected' : ''; ?> value="failed"><?= __('Thất bại'); ?></option>
                                        <option <?= $status_filter == 'blocked' ? 'selected' : ''; ?> value="blocked"><?= __('Bị chặn'); ?></option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-sm btn-primary"><i class="fa fa-search"></i></button>
                                        <a class="btn btn-sm btn-outline-danger" href="<?= base_url_admin('api-logs'); ?>"><i class="fa fa-times"></i></a>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <label class="me-2"><?= __('Hiển thị'); ?>:</label>
                                    <select name="limit" onchange="this.form.submit()" class="form-select form-select-sm d-inline-block w-auto">
                                        <?php foreach ([50, 100, 200, 500] as $l): ?>
                                            <option <?= $limit == $l ? 'selected' : ''; ?> value="<?= $l; ?>"><?= $l; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <small class="text-muted"><?= __('Tổng'); ?>: <?= format_cash($totalDatatable); ?> <?= __('kết quả'); ?></small>
                            </div>
                        </form>

                        <!-- Bảng danh sách -->
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered align-middle table-sm">
                                <thead>
                                    <tr>
                                        <th width="180"><?= __('Thời gian'); ?></th>
                                        <th><?= __('User'); ?></th>
                                        <th><?= __('API Key'); ?></th>
                                        <th><?= __('Endpoint'); ?></th>
                                        <th class="text-center"><?= __('Status'); ?></th>
                                        <th><?= __('IP'); ?></th>
                                        <th><?= __('Message'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($listDatatable)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">
                                                <i class="ri-file-list-3-line fs-40 d-block mb-2"></i>
                                                <?= __('Chưa có log nào'); ?>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($listDatatable as $row): ?>
                                            <tr>
                                                <td>
                                                    <small><?= $row['created_at']; ?></small>
                                                </td>
                                                <td>
                                                    <?php if ($row['user_id'] > 0): ?>
                                                        <?php $user = $CMSNT->get_row_safe("SELECT * FROM users WHERE id = ?", [$row['user_id']]); ?>
                                                        <?php if ($user): ?>
                                                            <i class="fa-solid fa-user"></i> <?= $user['username']; ?> [ID
                                                            <?= $row['user_id']; ?>] <a class="text-primary"
                                                                href="<?= base_url_admin('user-edit&id=' . $row['user_id']); ?>"><i
                                                                    class="fa-solid fa-edit"></i></a>
                                                        <?php else: ?>
                                                            <span class="text-warning"><i class="fa-solid fa-user-slash"></i> [ID <?= $row['user_id']; ?>]</span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <code class="small"><?= $row['api_key']; ?></code>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary"><?= strtoupper($row['method']); ?></span>
                                                    <small><?= htmlspecialchars($row['endpoint']); ?></small>
                                                </td>
                                                <td class="text-center">
                                                    <?php
                                                    switch ($row['status']) {
                                                        case 'success':
                                                            $statusClass = 'success';
                                                            break;
                                                        case 'failed':
                                                            $statusClass = 'danger';
                                                            break;
                                                        case 'blocked':
                                                            $statusClass = 'warning';
                                                            break;
                                                        default:
                                                            $statusClass = 'secondary';
                                                    }
                                                    ?>
                                                    <span class="badge bg-<?= $statusClass; ?>"><?= $row['status']; ?></span>
                                                </td>
                                                <td>
                                                    <small><?= $row['ip']; ?></small>
                                                </td>
                                                <td>
                                                    <small class="text-muted"><?= htmlspecialchars(mb_substr($row['message'] ?? '-', 0, 50)); ?></small>
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

<?php require_once(__DIR__ . '/footer.php'); ?>