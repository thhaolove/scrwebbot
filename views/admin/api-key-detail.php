<?php if (!defined('IN_SITE')) {
    die('The Request Not Found');
}
$body = [
    'title' => __('Chi tiết API Key') . ' | ' . $CMSNT->site('title'),
    'desc'   => $CMSNT->site('description'),
    'keyword' => $CMSNT->site('keywords')
];
$body['header'] = '';
$body['footer'] = '';

require_once(__DIR__ . '/../../models/is_admin.php');

// Validate ID
if (!isset($_GET['id'])) {
    redirect(base_url_admin('api-keys'));
}

$key_id = validate_int($_GET['id'], 1);
if ($key_id === false) {
    redirect(base_url_admin('api-keys'));
}

// Lấy thông tin API Key
$apiKey = $CMSNT->get_row_safe(
    "SELECT ak.*, u.username, u.email as user_email 
     FROM `api_keys` ak 
     LEFT JOIN `users` u ON ak.user_id = u.id 
     WHERE ak.id = ?",
    [$key_id]
);

if (!$apiKey) {
    redirect(base_url_admin('api-keys'));
}

require_once(__DIR__ . '/header.php');
require_once(__DIR__ . '/sidebar.php');
require_once(__DIR__ . '/nav.php');

if (checkPermission($getUser['admin'], 'view_api_keys') != true) {
    die('<script type="text/javascript">if(!alert("' . __('Bạn không có quyền sử dụng tính năng này') . '")){window.history.back();}</script>');
}

// Lấy permissions
$permissions = json_decode($apiKey['permissions'], true) ?: [];

// Thống kê sử dụng (dùng api_key string vì api_key_id có thể NULL)
$today = date('Y-m-d 00:00:00');
$thisMonth = date('Y-m-01 00:00:00');
$apiKeyString = $apiKey['api_key'];

$statsToday = $CMSNT->num_rows_safe(
    "SELECT id FROM `api_logs` WHERE `api_key` = ? AND `created_at` >= ?",
    [$apiKeyString, $today]
);
$statsThisMonth = $CMSNT->num_rows_safe(
    "SELECT id FROM `api_logs` WHERE `api_key` = ? AND `created_at` >= ?",
    [$apiKeyString, $thisMonth]
);
$statsTotal = $CMSNT->num_rows_safe(
    "SELECT id FROM `api_logs` WHERE `api_key` = ?",
    [$apiKeyString]
);
$statsSuccess = $CMSNT->num_rows_safe(
    "SELECT id FROM `api_logs` WHERE `api_key` = ? AND `status` = 'success'",
    [$apiKeyString]
);
$statsFailed = $statsTotal - $statsSuccess;

// Lấy logs gần đây
$recentLogs = $CMSNT->get_list_safe(
    "SELECT * FROM `api_logs` WHERE `api_key` = ? ORDER BY `id` DESC LIMIT 20",
    [$apiKeyString]
);

// Danh sách quyền có thể có
$allPermissions = [
    'orders.create' => __('Tạo đơn hàng'),
    'orders.list' => __('Xem danh sách đơn hàng'),
    'orders.status' => __('Kiểm tra trạng thái đơn'),
    'products.list' => __('Xem danh sách sản phẩm'),
    'account.balance' => __('Xem số dư'),
    'account.info' => __('Xem thông tin tài khoản'),
    'all' => __('Toàn quyền (Full Access)')
];
?>

<div class="main-content app-content">
    <div class="container-fluid">
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">
                <i class="ri-key-2-line me-2"></i><?= __('Chi tiết API Key'); ?>
            </h1>
            <nav>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= base_url_admin('api-keys'); ?>"><?= __('API Keys'); ?></a></li>
                    <li class="breadcrumb-item active"><?= htmlspecialchars($apiKey['key_name'] ?: 'API Key #' . $key_id); ?></li>
                </ol>
            </nav>
        </div>

        <!-- Thông tin cơ bản -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card custom-card">
                    <div class="card-header justify-content-between">
                        <div class="card-title d-flex align-items-center">
                            <span class="badge bg-<?= $apiKey['status'] == 1 ? 'success' : 'danger'; ?> me-2">
                                <?= $apiKey['status'] == 1 ? __('Hoạt động') : __('Vô hiệu'); ?>
                            </span>
                            <?= htmlspecialchars($apiKey['key_name'] ?: 'API Key'); ?>
                        </div>
                        <div class="d-flex gap-2">
                            <button onclick="toggleStatus(<?= $apiKey['id']; ?>, <?= $apiKey['status']; ?>)"
                                class="btn btn-sm btn-<?= $apiKey['status'] == 1 ? 'warning' : 'success'; ?>">
                                <i class="ri-<?= $apiKey['status'] == 1 ? 'pause' : 'play'; ?>-line me-1"></i>
                                <?= $apiKey['status'] == 1 ? __('Vô hiệu hóa') : __('Kích hoạt'); ?>
                            </button>
                            <button onclick="regenerateSecret(<?= $apiKey['id']; ?>)" class="btn btn-sm btn-outline-primary">
                                <i class="ri-refresh-line me-1"></i><?= __('Tạo lại Secret'); ?>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label text-muted"><?= __('User'); ?></label>
                                <div>
                                    <a href="<?= base_url_admin('user-info&id=' . $apiKey['user_id']); ?>" class="fw-semibold">
                                        <i class="ri-user-line me-1"></i><?= htmlspecialchars($apiKey['username']); ?>
                                    </a>
                                    <br>
                                    <small class="text-muted"><?= htmlspecialchars($apiKey['user_email']); ?></small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted"><?= __('Ngày tạo'); ?></label>
                                <div><?= $apiKey['created_at']; ?></div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label text-muted"><?= __('API Key'); ?></label>
                            <div class="input-group">
                                <input type="text" class="form-control font-monospace user-select-all"
                                    value="<?= $apiKey['api_key']; ?>" readonly id="apiKeyInput">
                                <button class="btn btn-outline-secondary" onclick="copyToClipboard('apiKeyInput')">
                                    <i class="ri-file-copy-line"></i>
                                </button>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label text-muted"><?= __('API Secret'); ?></label>
                            <div class="input-group">
                                <input type="password" class="form-control font-monospace"
                                    value="<?= $apiKey['api_secret']; ?>" readonly id="apiSecretInput">
                                <button class="btn btn-outline-secondary" onclick="toggleSecret()">
                                    <i class="ri-eye-line" id="secretEyeIcon"></i>
                                </button>
                                <button class="btn btn-outline-secondary" onclick="copyToClipboard('apiSecretInput')">
                                    <i class="ri-file-copy-line"></i>
                                </button>
                            </div>
                            <small class="text-danger"><?= __('Không chia sẻ API Secret với bất kỳ ai!'); ?></small>
                        </div>

                        <hr>

                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label text-muted"><?= __('Rate Limit'); ?></label>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <div class="input-group input-group-sm">
                                            <input type="number" class="form-control" id="rateLimitMinute"
                                                value="<?= $apiKey['rate_limit_per_minute']; ?>" min="1" max="1000">
                                            <span class="input-group-text">/<?= __('phút'); ?></span>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="input-group input-group-sm">
                                            <input type="number" class="form-control" id="rateLimitDay"
                                                value="<?= $apiKey['rate_limit_per_day']; ?>" min="1" max="100000">
                                            <span class="input-group-text">/<?= __('ngày'); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <button onclick="updateRateLimit(<?= $apiKey['id']; ?>)" class="btn btn-sm btn-primary mt-2">
                                    <i class="ri-save-line me-1"></i><?= __('Lưu Rate Limit'); ?>
                                </button>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted"><?= __('IP Whitelist'); ?></label>
                                <div>
                                    <?php if (!empty($apiKey['ip_whitelist'])): ?>
                                        <code><?= htmlspecialchars($apiKey['ip_whitelist']); ?></code>
                                    <?php else: ?>
                                        <span class="text-muted"><?= __('Tất cả IP'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label text-muted"><?= __('Sử dụng lần cuối'); ?></label>
                                <div>
                                    <?php if ($apiKey['last_used_at']): ?>
                                        <?= $apiKey['last_used_at']; ?>
                                        <br>
                                        <small class="text-muted">IP: <?= $apiKey['last_ip']; ?></small>
                                    <?php else: ?>
                                        <span class="text-muted"><?= __('Chưa sử dụng'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted"><?= __('Ngày hết hạn'); ?></label>
                                <div>
                                    <?php if (!empty($apiKey['expires_at'])): ?>
                                        <?= $apiKey['expires_at']; ?>
                                        <?php if (strtotime($apiKey['expires_at']) < time()): ?>
                                            <span class="badge bg-danger ms-1"><?= __('Đã hết hạn'); ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted"><?= __('Vĩnh viễn'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quyền hạn -->
                <div class="card custom-card">
                    <div class="card-header">
                        <div class="card-title"><?= __('Quyền hạn'); ?></div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($allPermissions as $perm => $label): ?>
                                <div class="col-md-6 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox"
                                            <?= in_array($perm, $permissions) || in_array('all', $permissions) ? 'checked' : ''; ?> disabled>
                                        <label class="form-check-label">
                                            <?= $label; ?>
                                            <br><small class="text-muted"><?= $perm; ?></small>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Logs gần đây -->
                <div class="card custom-card">
                    <div class="card-header justify-content-between">
                        <div class="card-title"><?= __('Requests gần đây'); ?></div>
                        <a href="<?= base_url_admin('api-logs&api_key=' . urlencode($apiKeyString)); ?>" class="btn btn-sm btn-outline-info">
                            <?= __('Xem tất cả'); ?>
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th><?= __('Endpoint'); ?></th>
                                        <th class="text-center"><?= __('Status'); ?></th>
                                        <th><?= __('IP'); ?></th>
                                        <th><?= __('Thời gian'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentLogs)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-3">
                                                <?= __('Chưa có request nào'); ?>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recentLogs as $log): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-<?= $log['method'] == 'GET' ? 'info' : 'success'; ?> me-1">
                                                        <?= $log['method']; ?>
                                                    </span>
                                                    <code><?= htmlspecialchars($log['endpoint']); ?></code>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-<?= $log['status'] == 'success' ? 'success' : 'danger'; ?>">
                                                        <?= $log['status']; ?>
                                                    </span>
                                                </td>
                                                <td><small><?= $log['ip']; ?></small></td>
                                                <td><small><?= $log['created_at']; ?></small></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar thống kê -->
            <div class="col-lg-4">
                <div class="card custom-card">
                    <div class="card-header">
                        <div class="card-title"><?= __('Thống kê sử dụng'); ?></div>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                            <span class="text-muted"><?= __('Hôm nay'); ?></span>
                            <span class="fw-semibold"><?= format_cash($statsToday); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                            <span class="text-muted"><?= __('Tháng này'); ?></span>
                            <span class="fw-semibold"><?= format_cash($statsThisMonth); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                            <span class="text-muted"><?= __('Tổng requests'); ?></span>
                            <span class="fw-semibold"><?= format_cash($statsTotal); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                            <span class="text-muted"><?= __('Thành công'); ?></span>
                            <span class="fw-semibold text-success"><?= format_cash($statsSuccess); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted"><?= __('Thất bại'); ?></span>
                            <span class="fw-semibold text-danger"><?= format_cash($statsFailed); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card custom-card">
                    <div class="card-header">
                        <div class="card-title"><?= __('Thao tác'); ?></div>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="<?= base_url_admin('api-logs&api_key=' . urlencode($apiKeyString)); ?>" class="btn btn-outline-info">
                                <i class="ri-file-list-line me-1"></i><?= __('Xem tất cả logs'); ?>
                            </a>
                            <button onclick="deleteApiKey(<?= $apiKey['id']; ?>)" class="btn btn-outline-danger">
                                <i class="ri-delete-bin-line me-1"></i><?= __('Xóa API Key'); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Test API -->
                <div class="card custom-card">
                    <div class="card-header">
                        <div class="card-title"><?= __('Test API'); ?></div>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small"><?= __('Sử dụng cURL để test API:'); ?></p>
                        <pre class="bg-dark text-light p-3 rounded small" style="white-space: pre-wrap;"><code>curl -X GET "<?= base_url('api/v1/account/balance'); ?>" \
  -H "X-API-Key: <?= $apiKey['api_key']; ?>" \
  -H "X-API-Secret: YOUR_SECRET"</code></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once(__DIR__ . '/footer.php'); ?>

<script>
    // Toggle hiển thị secret
    let secretVisible = false;

    function toggleSecret() {
        const input = document.getElementById('apiSecretInput');
        const icon = document.getElementById('secretEyeIcon');

        if (secretVisible) {
            input.type = 'password';
            icon.className = 'ri-eye-line';
        } else {
            input.type = 'text';
            icon.className = 'ri-eye-off-line';
        }
        secretVisible = !secretVisible;
    }

    // Copy to clipboard
    function copyToClipboard(inputId) {
        const input = document.getElementById(inputId);
        const originalType = input.type;
        input.type = 'text';
        input.select();
        document.execCommand('copy');
        input.type = originalType;

        showMessage('<?= __('Đã sao chép!'); ?>', 'success');
    }

    // Toggle trạng thái
    function toggleStatus(id, currentStatus) {
        const msg = currentStatus == 1 ?
            '<?= __('Bạn có chắc muốn vô hiệu hóa API Key này?'); ?>' :
            '<?= __('Bạn có chắc muốn kích hoạt API Key này?'); ?>';

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

    // Regenerate Secret
    function regenerateSecret(id) {
        cuteAlert({
            type: "question",
            title: "<?= __('Tạo lại API Secret'); ?>",
            message: "<?= __('API Secret hiện tại sẽ không còn hoạt động. Tiếp tục?'); ?>",
            confirmText: "<?= __('Tạo lại'); ?>",
            cancelText: "<?= __('Hủy'); ?>"
        }).then((e) => {
            if (e) {
                $.ajax({
                    url: '<?= base_url('ajaxs/admin/update.php'); ?>',
                    type: 'POST',
                    dataType: 'JSON',
                    data: {
                        action: 'regenerate_api_secret',
                        id: id,
                        csrf_token: '<?= generate_csrf_token(); ?>'
                    },
                    success: function(result) {
                        if (result.status == 'success') {
                            cuteAlert({
                                type: "success",
                                title: "<?= __('API Secret mới'); ?>",
                                message: result.api_secret + "<br><br><small class='text-danger'><?= __('Hãy lưu lại ngay, không thể xem lại!'); ?></small>",
                                confirmText: "<?= __('Đã lưu'); ?>"
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            showMessage(result.msg, result.status);
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
                            setTimeout(() => {
                                location.href = '<?= base_url_admin('api-keys'); ?>';
                            }, 1000);
                        }
                    }
                });
            }
        });
    }

    // Update Rate Limit
    function updateRateLimit(id) {
        const rateMinute = parseInt($('#rateLimitMinute').val()) || 60;
        const rateDay = parseInt($('#rateLimitDay').val()) || 10000;

        if (rateMinute < 1 || rateMinute > 1000) {
            showMessage('<?= __('Rate limit per minute phải từ 1-1000'); ?>', 'error');
            return;
        }
        if (rateDay < 1 || rateDay > 100000) {
            showMessage('<?= __('Rate limit per day phải từ 1-100000'); ?>', 'error');
            return;
        }

        $.ajax({
            url: '<?= base_url('ajaxs/admin/update.php'); ?>',
            type: 'POST',
            dataType: 'JSON',
            data: {
                action: 'update_api_rate_limit',
                id: id,
                rate_limit_per_minute: rateMinute,
                rate_limit_per_day: rateDay,
                csrf_token: '<?= generate_csrf_token(); ?>'
            },
            success: function(result) {
                showMessage(result.msg, result.status);
            }
        });
    }
</script>