<?php if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

// Kiểm tra tính năng API cho user có được bật không
if ($CMSNT->site('api_user_enabled') != 1) {
    redirect(base_url());
}

$body = [
    'title' => __('Quản lý API Keys') . ' | ' . $CMSNT->site('title'),
    'desc'   => $CMSNT->site('description'),
    'keyword' => $CMSNT->site('keywords')
];
$body['header'] = '
<link rel="stylesheet" href="' . BASE_URL('public/client/') . 'css/profile.css">
<link rel="stylesheet" href="' . BASE_URL('mod/css/') . 'api.css?v=1">
';
$body['footer'] = '<script src="' . BASE_URL('mod/js/') . 'api.js?v=1"></script>';

require_once(__DIR__ . '/../../models/is_user.php');
require_once(__DIR__ . '/header.php');
require_once(__DIR__ . '/nav.php');

// Lấy danh sách API keys của user
$api_keys = $CMSNT->get_list_safe(
    "SELECT * FROM `api_keys` WHERE `user_id` = ? ORDER BY `id` DESC",
    [$getUser['id']]
);

$max_api_keys = (int)($CMSNT->site('api_max_keys_per_user') ?: 3);
$can_create_more = count($api_keys) < $max_api_keys;

// Đếm số key hoạt động/tắt
$active_keys = 0;
$inactive_keys = 0;
foreach ($api_keys as $key) {
    if ($key['status'] == 1) {
        $active_keys++;
    } else {
        $inactive_keys++;
    }
}
?>

<section class="py-5 inner-section profile-part">
    <div class="container">
        <div class="row content-reverse">
            <div class="col-lg-3">
                <?php require_once(__DIR__ . '/sidebar.php'); ?>
            </div>
            <div class="col-lg-9">
                <!-- Header -->
                <div class="api-keys-header">
                    <div class="api-keys-header-content">
                        <h1 class="api-keys-title">
                            <i class="fa-solid fa-key"></i>
                            <?= __('Quản lý API Keys'); ?>
                        </h1>
                        <p class="api-keys-desc"><?= __('Tạo và quản lý API Keys để tích hợp mua hàng tự động'); ?></p>
                    </div>
                </div>

                <!-- Stats -->
                <div class="api-stats-grid">
                    <div class="api-stat-card">
                        <div class="api-stat-icon purple">
                            <i class="fa-solid fa-key"></i>
                        </div>
                        <div class="api-stat-info">
                            <h3><?= count($api_keys); ?>/<?= $max_api_keys; ?></h3>
                            <p><?= __('Tổng API Keys'); ?></p>
                        </div>
                    </div>
                    <div class="api-stat-card">
                        <div class="api-stat-icon green">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                        <div class="api-stat-info">
                            <h3><?= $active_keys; ?></h3>
                            <p><?= __('Đang hoạt động'); ?></p>
                        </div>
                    </div>
                    <div class="api-stat-card">
                        <div class="api-stat-icon orange">
                            <i class="fa-solid fa-circle-pause"></i>
                        </div>
                        <div class="api-stat-info">
                            <h3><?= $inactive_keys; ?></h3>
                            <p><?= __('Đã tắt'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Hidden inputs -->
                <input type="hidden" id="api_token" value="<?= $getUser['token']; ?>">
                <input type="hidden" id="api_csrf_token" value="<?= generate_csrf_token(); ?>">

                <!-- Create Button or Limit Warning -->
                <?php if ($can_create_more): ?>
                    <button class="api-create-btn" onclick="openCreateApiKeyModal()">
                        <i class="fa-solid fa-plus"></i>
                        <?= __('Tạo API Key mới'); ?>
                    </button>
                <?php else: ?>
                    <div class="api-limit-warning">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <p><?= sprintf(__('Bạn đã đạt giới hạn %d API key. Xóa API key cũ để tạo mới.'), $max_api_keys); ?></p>
                    </div>
                <?php endif; ?>

                <!-- API Keys List -->
                <?php if (empty($api_keys)): ?>
                    <div class="api-empty-state">
                        <div class="api-empty-state-icon">
                            <i class="fa-solid fa-key"></i>
                        </div>
                        <h4><?= __('Chưa có API Key nào'); ?></h4>
                        <p><?= __('Tạo API Key đầu tiên để bắt đầu tích hợp hệ thống của bạn'); ?></p>
                    </div>
                <?php else: ?>
                    <?php foreach ($api_keys as $key): ?>
                        <div class="api-key-card" id="api-key-card-<?= $key['id']; ?>">
                            <div class="api-key-card-header">
                                <div class="api-key-card-title">
                                    <div class="api-key-card-icon">
                                        <i class="fa-solid fa-key"></i>
                                    </div>
                                    <div>
                                        <h4 class="api-key-card-name"><?= htmlspecialchars($key['key_name'] ?: 'API Key #' . $key['id']); ?></h4>
                                        <span class="api-key-card-date">
                                            <i class="fa-regular fa-calendar"></i>
                                            <?= __('Tạo lúc'); ?>: <?= date('d/m/Y H:i', strtotime($key['created_at'])); ?>
                                        </span>
                                    </div>
                                </div>
                                <span class="api-key-status-badge <?= $key['status'] == 1 ? 'active' : 'inactive'; ?>">
                                    <i class="fa-solid <?= $key['status'] == 1 ? 'fa-circle-check' : 'fa-circle-xmark'; ?>"></i>
                                    <?= $key['status'] == 1 ? __('Hoạt động') : __('Đã tắt'); ?>
                                </span>
                            </div>

                            <div class="api-key-card-body">
                                <!-- API Key Field -->
                                <div class="api-key-field">
                                    <div class="api-key-field-icon">
                                        <i class="fa-solid fa-fingerprint"></i>
                                    </div>
                                    <div class="api-key-field-content">
                                        <div class="api-key-field-label"><?= __('API Key'); ?></div>
                                        <div class="api-key-field-value"><?= htmlspecialchars($key['api_key']); ?></div>
                                    </div>
                                    <button class="api-key-copy-btn" onclick="copyToClipboard('<?= htmlspecialchars($key['api_key'], ENT_QUOTES); ?>')" title="<?= __('Sao chép'); ?>">
                                        <i class="fa-solid fa-copy"></i>
                                    </button>
                                </div>

                                <!-- IP Whitelist Field -->
                                <div class="api-key-field">
                                    <div class="api-key-field-icon">
                                        <i class="fa-solid fa-shield-halved"></i>
                                    </div>
                                    <div class="api-key-field-content">
                                        <div class="api-key-field-label"><?= __('IP Whitelist'); ?></div>
                                        <div class="api-key-field-value <?= empty($key['ip_whitelist']) ? 'empty' : ''; ?>">
                                            <?= !empty($key['ip_whitelist']) ? htmlspecialchars($key['ip_whitelist']) : __('Không giới hạn (cho phép tất cả IP)'); ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Rate Limit Info -->
                                <div class="api-key-rate-limit">
                                    <div class="api-key-rate-limit-item">
                                        <i class="fa-solid fa-gauge-high"></i>
                                        <span><?= sprintf(__('%d request/phút'), $key['rate_limit_per_minute']); ?></span>
                                    </div>
                                    <div class="api-key-rate-limit-item">
                                        <i class="fa-solid fa-calendar-day"></i>
                                        <span><?= sprintf(__('%d request/ngày'), $key['rate_limit_per_day']); ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="api-key-card-actions">
                                <button class="api-key-action-btn edit" onclick="openEditApiKeyModal(<?= $key['id']; ?>, '<?= htmlspecialchars($key['key_name'], ENT_QUOTES); ?>', '<?= htmlspecialchars($key['ip_whitelist'] ?? '', ENT_QUOTES); ?>')">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                    <?= __('Chỉnh sửa'); ?>
                                </button>
                                <button class="api-key-action-btn toggle" onclick="toggleApiKey(<?= $key['id']; ?>, '<?= base_url('ajaxs/client/api.php'); ?>', apiTranslations)">
                                    <i class="fa-solid fa-power-off"></i>
                                    <?= $key['status'] == 1 ? __('Tắt') : __('Bật'); ?>
                                </button>
                                <button class="api-key-action-btn regenerate" onclick="regenerateApiSecret(<?= $key['id']; ?>, '<?= base_url('ajaxs/client/api.php'); ?>', apiTranslations)">
                                    <i class="fa-solid fa-rotate"></i>
                                    <?= __('Tạo lại Secret'); ?>
                                </button>
                                <button class="api-key-action-btn delete" onclick="deleteApiKey(<?= $key['id']; ?>, '<?= base_url('ajaxs/client/api.php'); ?>', apiTranslations)">
                                    <i class="fa-solid fa-trash-can"></i>
                                    <?= __('Xóa'); ?>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Quick Links -->
                <div class="api-alert info" style="margin-top: 24px;">
                    <i class="fa-solid fa-circle-info api-alert-icon"></i>
                    <div>
                        <strong><?= __('Tài liệu hướng dẫn'); ?></strong>
                        <?= __('Xem hướng dẫn chi tiết về cách sử dụng API tại'); ?>
                        <a href="<?= base_url('document-api'); ?>" style="color: inherit; text-decoration: underline; font-weight: 600;">
                            <?= __('Tài liệu API'); ?> <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Modal Tạo API Key -->
<div class="api-modal" id="createApiKeyModal">
    <div class="api-modal-backdrop" onclick="closeCreateApiKeyModal()"></div>
    <div class="api-modal-dialog">
        <div class="api-modal-header">
            <h5><i class="fa-solid fa-plus"></i> <?= __('Tạo API Key mới'); ?></h5>
            <button type="button" class="api-modal-close" onclick="closeCreateApiKeyModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="api-modal-body">
            <div class="api-form-group">
                <label class="api-form-label"><?= __('Tên API Key'); ?></label>
                <input type="text" class="api-form-input" id="create_key_name" placeholder="<?= __('VD: My App API, Website Integration...'); ?>" maxlength="100">
                <p class="api-form-hint"><?= __('Đặt tên để dễ phân biệt. Để trống sẽ tự động đặt tên.'); ?></p>
            </div>
            <div class="api-form-group">
                <label class="api-form-label"><?= __('IP Whitelist'); ?> <span class="optional">(<?= __('tùy chọn'); ?>)</span></label>
                <input type="text" class="api-form-input" id="create_ip_whitelist" placeholder="<?= __('VD: 192.168.1.1, 10.0.0.1'); ?>">
                <p class="api-form-hint"><?= __('Chỉ cho phép các IP này gọi API. Phân cách bằng dấu phẩy. Để trống để cho phép tất cả IP.'); ?></p>
            </div>

            <div id="createApiKeyResult" style="display:none;"></div>
        </div>
        <div class="api-modal-footer">
            <button type="button" class="api-modal-btn secondary" onclick="closeCreateApiKeyModal()"><?= __('Đóng'); ?></button>
            <button type="button" class="api-modal-btn primary" id="btnCreateApiKey" onclick="createApiKey('<?= base_url('ajaxs/client/api.php'); ?>', apiTranslations)">
                <i class="fa-solid fa-plus"></i> <?= __('Tạo API Key'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Modal Sửa API Key -->
<div class="api-modal" id="editApiKeyModal">
    <div class="api-modal-backdrop" onclick="closeEditApiKeyModal()"></div>
    <div class="api-modal-dialog">
        <div class="api-modal-header">
            <h5><i class="fa-solid fa-pen-to-square"></i> <?= __('Chỉnh sửa API Key'); ?></h5>
            <button type="button" class="api-modal-close" onclick="closeEditApiKeyModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="api-modal-body">
            <input type="hidden" id="edit_key_id">
            <div class="api-form-group">
                <label class="api-form-label"><?= __('Tên API Key'); ?></label>
                <input type="text" class="api-form-input" id="edit_key_name" maxlength="100">
            </div>
            <div class="api-form-group">
                <label class="api-form-label"><?= __('IP Whitelist'); ?></label>
                <input type="text" class="api-form-input" id="edit_ip_whitelist" placeholder="<?= __('VD: 192.168.1.1, 10.0.0.1'); ?>">
                <p class="api-form-hint"><?= __('Phân cách bằng dấu phẩy. Để trống để cho phép tất cả IP.'); ?></p>
            </div>
        </div>
        <div class="api-modal-footer">
            <button type="button" class="api-modal-btn secondary" onclick="closeEditApiKeyModal()"><?= __('Đóng'); ?></button>
            <button type="button" class="api-modal-btn primary" id="btnUpdateApiKey" onclick="updateApiKey('<?= base_url('ajaxs/client/api.php'); ?>', apiTranslations)">
                <i class="fa-solid fa-save"></i> <?= __('Lưu thay đổi'); ?>
            </button>
        </div>
    </div>
</div>

<script>
    // Translations for JS
    var apiTranslations = {
        processing: '<?= __('Đang xử lý...'); ?>',
        saveSecret: '<?= __('Lưu lại API Secret ngay!'); ?>',
        secretOnce: '<?= __('Secret Key này chỉ hiển thị một lần!'); ?>',
        copySecret: '<?= __('Sao chép Secret'); ?>',
        created: '<?= __('Đã tạo'); ?>',
        createBtn: '<?= __('Tạo API Key'); ?>',
        error: '<?= __('Lỗi'); ?>',
        success: '<?= __('Thành công'); ?>',
        requestError: '<?= __('Không thể xử lý yêu cầu'); ?>',
        save: '<?= __('Lưu thay đổi'); ?>',
        confirm: '<?= __('Xác nhận'); ?>',
        toggleConfirm: '<?= __('Bạn có chắc chắn muốn thay đổi trạng thái API Key này?'); ?>',
        confirmBtn: '<?= __('Xác nhận'); ?>',
        cancel: '<?= __('Hủy'); ?>',
        warning: '<?= __('Cảnh báo'); ?>',
        regenerateConfirm: '<?= __('Bạn có chắc chắn muốn tạo lại API Secret?'); ?>',
        oldSecretInvalid: '<?= __('Secret cũ sẽ không còn hoạt động!'); ?>',
        regenerateBtn: '<?= __('Tạo lại'); ?>',
        newSecret: '<?= __('API Secret mới'); ?>',
        saveNow: '<?= __('Lưu lại ngay! Secret chỉ hiển thị một lần.'); ?>',
        saved: '<?= __('Đã lưu'); ?>',
        deleteConfirmTitle: '<?= __('Xác nhận xóa'); ?>',
        deleteConfirm: '<?= __('Bạn có chắc chắn muốn xóa API Key này? Hành động này không thể hoàn tác.'); ?>',
        deleteBtn: '<?= __('Xóa'); ?>'
    };
</script>

<?php require_once(__DIR__ . '/footer.php'); ?>