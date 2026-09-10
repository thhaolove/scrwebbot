<?php if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

$body = [
    'title' => __('Yêu cầu Hỗ trợ') . ' | ' . $CMSNT->site('title'),
    'desc'   => $CMSNT->site('description'),
    'keyword' => $CMSNT->site('keywords')
];
$body['header'] = '
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="' . BASE_URL('mod/css/ticket.css') . '">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
' . renderCaptchaScripts('add_ticket') . '
';
$body['footer'] = '';

if ($CMSNT->site('support_tickets_status') == 0) {
    redirect(base_url());
}

require_once(__DIR__ . '/../../models/is_user.php');
require_once(__DIR__ . '/header.php');
require_once(__DIR__ . '/nav.php');

// Lấy filter từ URL (để hiển thị active trên form)
$limit = isset($_GET['limit']) ? (validate_int($_GET['limit'], 5, 1000) ?: 10) : 10;
$status_filter = isset($_GET['status']) ? validate_string($_GET['status'], 20) : '';
$subject_filter = isset($_GET['subject']) ? validate_string($_GET['subject'], 100, 1) : '';
$category_filter = isset($_GET['category']) ? validate_string($_GET['category'], 50) : '';
$time_filter = isset($_GET['time']) ? validate_string($_GET['time'], 50) : '';

// Thống kê tickets
$stats = [
    'total' => $CMSNT->num_rows_safe("SELECT COUNT(*) FROM `support_tickets` WHERE `user_id` = ?", [$getUser['id']]),
    'open' => $CMSNT->num_rows_safe("SELECT COUNT(*) FROM `support_tickets` WHERE `user_id` = ? AND `status` = 'open'", [$getUser['id']]),
    'pending' => $CMSNT->num_rows_safe("SELECT COUNT(*) FROM `support_tickets` WHERE `user_id` = ? AND `status` = 'pending'", [$getUser['id']]),
    'answered' => $CMSNT->num_rows_safe("SELECT COUNT(*) FROM `support_tickets` WHERE `user_id` = ? AND `status` = 'answered'", [$getUser['id']])
];

// Status icons
$status_icons = [
    'open' => 'fa-envelope-open',
    'pending' => 'fa-clock',
    'answered' => 'fa-check-circle',
    'closed' => 'fa-lock'
];
?>

<section class="py-5 inner-section support-tickets-page">
    <div class="container">
        <div class="row content-reverse">
            <div class="col-lg-3 mt-3 mt-lg-0">
                <?php require_once(__DIR__ . '/sidebar.php'); ?>
            </div>
            <div class="col-lg-9">
                <!-- Stats Cards -->
                <div class="ticket-stats-wrapper">
                    <div class="ticket-stat-card stat-total">
                        <div class="ticket-stat-icon">
                            <i class="fa-solid fa-ticket"></i>
                        </div>
                        <div class="ticket-stat-content">
                            <h3><?= format_cash($stats['total']); ?></h3>
                            <p><?= __('Tổng tickets'); ?></p>
                        </div>
                    </div>
                    <div class="ticket-stat-card stat-open">
                        <div class="ticket-stat-icon">
                            <i class="fa-solid fa-envelope-open"></i>
                        </div>
                        <div class="ticket-stat-content">
                            <h3><?= format_cash($stats['open']); ?></h3>
                            <p><?= __('Đang mở'); ?></p>
                        </div>
                    </div>
                    <div class="ticket-stat-card stat-pending">
                        <div class="ticket-stat-icon">
                            <i class="fa-solid fa-clock"></i>
                        </div>
                        <div class="ticket-stat-content">
                            <h3><?= format_cash($stats['pending']); ?></h3>
                            <p><?= __('Chờ xử lý'); ?></p>
                        </div>
                    </div>
                    <div class="ticket-stat-card stat-answered">
                        <div class="ticket-stat-icon">
                            <i class="fa-solid fa-check-circle"></i>
                        </div>
                        <div class="ticket-stat-content">
                            <h3><?= format_cash($stats['answered']); ?></h3>
                            <p><?= __('Đã trả lời'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Header -->
                <div class="ticket-card-header">
                    <div class="ticket-header-info">
                        <div class="ticket-header-icon">
                            <i class="fa-solid fa-headset"></i>
                        </div>
                        <div>
                            <h1 class="ticket-card-title"><?= __('Yêu cầu hỗ trợ'); ?></h1>
                            <p class="ticket-subtitle"><?= sprintf(__('Tổng cộng %d ticket'), $stats['total']); ?></p>
                        </div>
                    </div>
                    <button type="button" class="btn-create-ticket" id="openTicketModal">
                        <i class="fa-solid fa-plus"></i>
                        <?= __('Tạo yêu cầu mới'); ?>
                    </button>
                </div>

                <!-- Filter Section -->
                <div class="ticket-filter-section">
                    <form id="ticketFilterForm">
                        <div class="ticket-filter-grid">
                            <div class="ticket-filter-group">
                                <label><?= __('Tìm kiếm'); ?></label>
                                <input type="text" class="form-control" name="subject" id="filterSubject" value="<?= htmlspecialchars($subject_filter); ?>" placeholder="<?= __('Nhập tiêu đề ticket...'); ?>">
                            </div>
                            <div class="ticket-filter-group">
                                <label><?= __('Trạng thái'); ?></label>
                                <select name="status" id="filterStatus" class="form-select">
                                    <option value=""><?= __('Tất cả'); ?></option>
                                    <?php foreach ($config_status_support_tickets as $key => $value): ?>
                                        <option value="<?= $key; ?>" <?= $status_filter == $key ? 'selected' : ''; ?>><?= $value; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="ticket-filter-group">
                                <label><?= __('Chủ đề'); ?></label>
                                <select name="category" id="filterCategory" class="form-select">
                                    <option value=""><?= __('Tất cả'); ?></option>
                                    <?php foreach ($config_category_support_tickets as $key => $value): ?>
                                        <option value="<?= $key; ?>" <?= $category_filter == $key ? 'selected' : ''; ?>><?= $value; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="ticket-filter-group">
                                <label><?= __('Thời gian'); ?></label>
                                <input type="text" class="form-control" id="flatpickr-range" name="time" value="<?= htmlspecialchars($time_filter); ?>" placeholder="<?= __('Chọn ngày'); ?>" readonly>
                            </div>
                            <div class="ticket-filter-actions">
                                <button type="submit" class="btn-filter-search">
                                    <i class="fa-solid fa-search"></i>
                                    <?= __('Tìm'); ?>
                                </button>
                                <a href="javascript:void(0)" class="btn-filter-reset" id="resetFilter">
                                    <i class="fa-solid fa-times"></i>
                                    <?= __('Xóa'); ?>
                                </a>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Tickets Table -->
                <div class="ticket-table-wrapper">
                    <!-- Empty State (hidden by default, shown via JS) -->
                    <div class="ticket-empty-state" id="ticketsEmptyState" style="display: none;">
                        <div class="ticket-empty-icon">
                            <i class="fa-solid fa-ticket"></i>
                        </div>
                        <h4><?= __('Chưa có ticket nào'); ?></h4>
                        <p><?= __('Bạn chưa tạo yêu cầu hỗ trợ nào. Hãy tạo ticket mới nếu cần trợ giúp!'); ?></p>
                        <button type="button" class="btn-create-ticket btn-open-ticket-modal">
                            <i class="fa-solid fa-plus"></i>
                            <?= __('Tạo yêu cầu mới'); ?>
                        </button>
                    </div>

                    <!-- Table -->
                    <table class="ticket-table" id="ticketsTable">
                        <thead>
                            <tr>
                                <th style="width: 80px; text-align: center;"><?= __('Thao tác'); ?></th>
                                <th><?= __('Tiêu đề'); ?></th>
                                <th style="width: 120px;"><?= __('Chủ đề'); ?></th>
                                <th style="width: 100px; text-align: center;"><?= __('Trạng thái'); ?></th>
                                <th style="width: 120px;"><?= __('Ngày tạo'); ?></th>
                                <th style="width: 120px;"><?= __('Cập nhật'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="ticketsTableBody">
                            <!-- Content loaded via AJAX -->
                        </tbody>
                        <!-- Loading Skeleton -->
                        <tbody class="tickets-loading-skeleton" id="ticketsLoadingDesktop">
                            <?php for ($i = 0; $i < 10; $i++): ?>
                                <tr class="skeleton-row">
                                    <td class="text-center">
                                        <div class="skeleton-cell skeleton-action"></div>
                                    </td>
                                    <td>
                                        <div class="skeleton-cell skeleton-subject"></div>
                                    </td>
                                    <td>
                                        <div class="skeleton-cell skeleton-category"></div>
                                    </td>
                                    <td class="text-center">
                                        <div class="skeleton-cell skeleton-status"></div>
                                    </td>
                                    <td>
                                        <div class="skeleton-cell skeleton-date"></div>
                                    </td>
                                    <td>
                                        <div class="skeleton-cell skeleton-date"></div>
                                    </td>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>

                    <!-- Load More -->
                    <div class="load-more-wrapper" id="loadMoreWrapper" style="display: none;">
                        <button type="button" class="btn-load-more" id="btnLoadMore">
                            <i class="fa-solid fa-plus"></i>
                            <?= __('Xem thêm'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Modal Tạo Ticket Mới -->
<div class="ticket-modal-overlay" id="addTicketModal">
    <div class="ticket-modal-container">
        <div class="ticket-modal-content">
            <div class="ticket-modal-header">
                <h5 class="ticket-modal-title">
                    <i class="fa-solid fa-plus-circle"></i>
                    <?= __('Tạo yêu cầu hỗ trợ mới'); ?>
                </h5>
                <button type="button" class="ticket-modal-close" id="closeTicketModal">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>
            <div class="ticket-modal-body">
                <form id="addTicketForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="ticket-form-group">
                                <label class="ticket-form-label"><?= __('Tiêu đề'); ?> <span class="required">*</span></label>
                                <input type="text" class="ticket-form-control" id="subject" name="subject" placeholder="<?= __('Nhập tiêu đề ticket'); ?>" required maxlength="200">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="ticket-form-group">
                                <label class="ticket-form-label"><?= __('Chủ đề'); ?></label>
                                <select class="ticket-form-control" id="category" name="category">
                                    <?php foreach ($config_category_support_tickets as $key => $value): ?>
                                        <option value="<?= $key; ?>"><?= $value; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row" id="orderIdField" style="display: none;">
                        <div class="col-md-12">
                            <div class="ticket-form-group">
                                <label class="ticket-form-label"><?= __('Mã đơn hàng'); ?></label>
                                <input type="text" class="ticket-form-control" id="orderID" name="order_id" placeholder="<?= __('Nhập mã đơn hàng (nếu có)'); ?>" list="orderIDList" maxlength="50">
                                <datalist id="orderIDList">
                                    <?php
                                    $recentOrders = $CMSNT->get_list_safe("SELECT * FROM `product_orders` WHERE `user_id` = ? ORDER BY `id` DESC LIMIT 20", [$getUser['id']]);
                                    foreach ($recentOrders as $order):
                                    ?>
                                        <option value="<?= htmlspecialchars($order['trans_id']); ?>"><?= htmlspecialchars($order['trans_id']); ?> | <?= htmlspecialchars($order['plan_name']); ?></option>
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                        </div>
                    </div>
                    <div class="ticket-form-group">
                        <label class="ticket-form-label"><?= __('Nội dung'); ?> <span class="required">*</span></label>
                        <textarea class="ticket-form-control" id="ticketContent" name="content" rows="5" placeholder="<?= __('Mô tả chi tiết vấn đề bạn gặp phải...'); ?>" required maxlength="5000"></textarea>
                    </div>

                    <?php if (isCaptchaEnabledForModule('add_ticket')): ?>
                        <div class="ticket-form-group">
                            <div class="text-center" id="captcha-container">
                                <?= renderCaptchaWidget('captcha-container', 'add_ticket'); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
            <div class="ticket-modal-footer">
                <button type="button" class="btn-modal-cancel" id="cancelTicketModal"><?= __('Đóng'); ?></button>
                <button type="button" class="btn-submit-ticket" id="submitTicket">
                    <span class="btn-spinner d-none">
                        <i class="fa-solid fa-spinner fa-spin me-1"></i><?= __('Đang tạo...'); ?>
                    </span>
                    <span class="btn-text">
                        <i class="fa-solid fa-paper-plane me-1"></i><?= __('Tạo yêu cầu'); ?>
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden Inputs for AJAX -->
<input type="hidden" id="userToken" value="<?= isset($getUser) ? $getUser['token'] : ''; ?>">
<input type="hidden" id="csrfToken" value="<?= generateCSRFToken(); ?>">

<?php require_once(__DIR__ . '/footer.php'); ?>

<!-- Ticket JS Variables -->
<script>
    var TICKET_AJAX_URL = '<?= base_url('ajaxs/client/ticket.php'); ?>';
    var TICKET_USER_TOKEN = '<?= $getUser['token']; ?>';
    var TICKET_CAPTCHA_REQUIRED = <?= isCaptchaEnabled() && isCaptchaEnabledForModule('add_ticket') ? 'true' : 'false'; ?>;
    var TICKET_LANG = {
        success: '<?= __('Thành công'); ?>',
        subject_min_length: '<?= __('Tiêu đề phải có ít nhất 5 ký tự'); ?>',
        content_min_length: '<?= __('Nội dung phải có ít nhất 10 ký tự'); ?>',
        captcha_required: '<?= __('Vui lòng xác nhận Captcha'); ?>',
        server_error: '<?= __('Không thể kết nối đến server'); ?>',
        loading: '<?= __('Đang tải...'); ?>',
        load_more: '<?= __('Xem thêm'); ?>',
        remaining: '<?= __('còn lại'); ?>',
        showing: '<?= __('Hiển thị'); ?>',
        results: '<?= __('kết quả'); ?>'
    };

    // Safe captcha response wrapper
    function getSafeCaptchaResponse() {
        try {
            if (typeof getCaptchaResponse === 'function') {
                return getCaptchaResponse() || '';
            }
            return '';
        } catch (e) {
            return '';
        }
    }
</script>
<script src="<?= BASE_URL('mod/js/ticket.js'); ?>"></script>