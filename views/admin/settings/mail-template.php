<?php if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

// Xử lý lưu template Mail
if (isset($_POST['SaveSettings'])) {
    // Kiểm tra quyền
    if (checkPermission($getUser['admin'], 'edit_mail_template') != true) {
        die('<script type="text/javascript">if(!alert("' . __('Bạn không có quyền sử dụng tính năng này') . '")){window.history.back();}</script>');
    }
    // Kiểm tra CSRF token
    checkCSRF();

    if ($CMSNT->site('status_demo') != 0) {
        die('<script type="text/javascript">if(!alert("' . __('This function cannot be used because this is a demo site') . '")){window.history.back().location.reload();}</script>');
    }
    $CMSNT->insert("logs", [
        'user_id'       => $getUser['id'],
        'ip'            => myip(),
        'device'        => getUserAgent(),
        'createdate'    => gettime(),
        'action'        => __('Thay đổi template Mail')
    ]);

    foreach ($_POST as $key => $value) {
        $CMSNT->update("settings", array(
            'value' => $value
        ), " `name` = '$key' ");
    }

    $my_text = $CMSNT->site('noti_action');
    $my_text = str_replace('{domain}', $_SERVER['SERVER_NAME'], $my_text);
    $my_text = str_replace('{username}', $getUser['username'], $my_text);
    $my_text = str_replace('{action}', __('Thay đổi template Mail'), $my_text);
    $my_text = str_replace('{ip}', myip(), $my_text);
    $my_text = str_replace('{time}', gettime(), $my_text);
    sendMessAdmin($my_text);

    admin_msg_success("Lưu thành công!", "", 1000);
}

// Define email templates configuration
$email_templates = [
    [
        'id' => 'order_success',
        'icon' => 'ri-shopping-cart-2-line',
        'color' => 'success',
        'title' => __('Thông báo mua hàng thành công'),
        'subject_key' => 'email_temp_subject_order_success',
        'content_key' => 'email_temp_content_order_success',
        'variables' => [
            '{domain}' => 'Link Website',
            '{title}' => 'Tên website',
            '{username}' => 'Tên khách hàng',
            '{email}' => 'Email khách hàng',
            '{order_count}' => 'Số lượng đơn hàng',
            '{order_details}' => 'Chi tiết đơn hàng (bảng HTML)',
            '{total_amount}' => 'Tổng tiền thanh toán',
            '{discount_amount}' => 'Số tiền giảm giá',
            '{coupon_code}' => 'Mã giảm giá đã dùng',
            '{summary}' => 'Tóm tắt đơn hàng (HTML)',
            '{order_link}' => 'Link xem đơn hàng',
            '{ip}' => 'Địa chỉ IP',
            '{device}' => 'Thiết bị',
            '{time}' => 'Thời gian'
        ]
    ],
    [
        'id' => 'warning_login',
        'icon' => 'ri-shield-check-line',
        'color' => 'warning',
        'title' => __('Thông báo đăng nhập'),
        'subject_key' => 'email_temp_subject_warning_login',
        'content_key' => 'email_temp_content_warning_login',
        'variables' => [
            '{domain}' => 'Link Website',
            '{title}' => 'Tên website',
            '{username}' => 'Tên khách hàng',
            '{ip}' => 'Địa chỉ IP',
            '{device}' => 'Thiết bị',
            '{time}' => 'Thời gian'
        ]
    ],
    [
        'id' => 'otp_mail',
        'icon' => 'ri-key-2-line',
        'color' => 'info',
        'title' => __('Gửi OTP xác minh đăng nhập'),
        'subject_key' => 'email_temp_subject_otp_mail',
        'content_key' => 'email_temp_content_otp_mail',
        'variables' => [
            '{domain}' => 'Link Website',
            '{title}' => 'Tên website',
            '{username}' => 'Tên khách hàng',
            '{otp}' => 'Mã OTP',
            '{ip}' => 'Địa chỉ IP',
            '{device}' => 'Thiết bị',
            '{time}' => 'Thời gian'
        ]
    ],
    [
        'id' => 'forgot_password',
        'icon' => 'ri-lock-unlock-line',
        'color' => 'danger',
        'title' => __('Khôi phục mật khẩu'),
        'subject_key' => 'email_temp_subject_forgot_password',
        'content_key' => 'email_temp_content_forgot_password',
        'variables' => [
            '{domain}' => 'Link Website',
            '{title}' => 'Tên website',
            '{username}' => 'Tên khách hàng',
            '{link}' => 'Link xác minh',
            '{ip}' => 'Địa chỉ IP',
            '{device}' => 'Thiết bị',
            '{time}' => 'Thời gian'
        ]
    ],
    [
        'id' => 'order_expiry',
        'icon' => 'ri-timer-line',
        'color' => 'secondary',
        'title' => __('Thông báo đơn hàng hết hạn'),
        'subject_key' => 'email_temp_subject_order_expiring',
        'subject_key_2' => 'email_temp_subject_order_expired',
        'content_key' => 'email_temp_content_order_expiry',
        'has_two_subjects' => true,
        'variables' => [
            '{domain}' => 'Link Website',
            '{title}' => 'Tên website',
            '{username}' => 'Tên khách hàng',
            '{product_name}' => 'Tên sản phẩm',
            '{plan_name}' => 'Tên gói',
            '{trans_id}' => 'Mã đơn hàng',
            '{expiry_date}' => 'Ngày hết hạn',
            '{days_remaining}' => 'Số ngày còn lại',
            '{expiry_message}' => 'Thông báo tự động (sắp hết hạn/đã hết hạn)',
            '{time}' => 'Thời gian hiện tại'
        ]
    ],
    [
        'id' => 'flash_sale_favorite',
        'icon' => 'ri-flashlight-line',
        'color' => 'danger',
        'title' => __('Flash Sale - Sản phẩm yêu thích'),
        'subject_key' => 'email_temp_subject_flash_sale_favorite',
        'content_key' => 'email_temp_content_flash_sale_favorite',
        'variables' => [
            '{domain}' => 'Link Website',
            '{title}' => 'Tên website',
            '{username}' => 'Tên khách hàng',
            '{flash_sale_name}' => 'Tên chương trình Flash Sale',
            '{product_name}' => 'Tên sản phẩm',
            '{discount_info}' => 'Thông tin giảm giá',
            '{start_time}' => 'Thời gian bắt đầu',
            '{end_time}' => 'Thời gian kết thúc',
            '{product_link}' => 'Link đến sản phẩm',
            '{time}' => 'Thời gian hiện tại'
        ]
    ],
    [
        'id' => 'order_completed',
        'icon' => 'ri-check-double-line',
        'color' => 'success',
        'title' => __('Thông báo đơn hàng hoàn thành'),
        'subject_key' => 'email_temp_subject_order_completed',
        'content_key' => 'email_temp_content_order_completed',
        'variables' => [
            '{domain}' => 'Link Website',
            '{title}' => 'Tên website',
            '{username}' => 'Tên khách hàng',
            '{email}' => 'Email khách hàng',
            '{trans_id}' => 'Mã đơn hàng',
            '{product_name}' => 'Tên sản phẩm',
            '{plan_name}' => 'Tên gói',
            '{quantity}' => 'Số lượng',
            '{total_amount}' => 'Tổng tiền thanh toán',
            '{delivery_content}' => 'Nội dung giao hàng (tài khoản)',
            '{order_link}' => 'Link xem đơn hàng',
            '{time}' => 'Thời gian'
        ]
    ],
    [
        'id' => 'ticket_created_user',
        'icon' => 'ri-customer-service-2-line',
        'color' => 'primary',
        'title' => __('Thông báo tạo ticket cho User'),
        'subject_key' => 'email_temp_subject_ticket_created_user',
        'content_key' => 'email_temp_content_ticket_created_user',
        'variables' => [
            '{domain}' => 'Link Website',
            '{title}' => 'Tên website',
            '{username}' => 'Tên khách hàng',
            '{ticket_id}' => 'Mã ticket',
            '{subject}' => 'Tiêu đề ticket',
            '{category}' => 'Danh mục hỗ trợ',
            '{order_id}' => 'Mã đơn hàng liên quan',
            '{content}' => 'Nội dung ticket',
            '{time}' => 'Thời gian tạo',
            '{ip}' => 'Địa chỉ IP',
            '{device}' => 'Thiết bị'
        ]
    ]
];
?>

<!-- GrapesJS Core CSS -->
<link href="https://unpkg.com/grapesjs@0.21.10/dist/css/grapes.min.css" rel="stylesheet">

<style>
    .email-template-accordion .accordion-item {
        border: 1px solid rgba(0, 0, 0, .125);
        margin-bottom: 0.75rem;
        border-radius: 0.5rem !important;
        overflow: hidden;
    }

    .email-template-accordion .accordion-button {
        font-weight: 600;
        padding: 1rem 1.25rem;
        font-size: 0.95rem;
    }

    .email-template-accordion .accordion-button:not(.collapsed) {
        box-shadow: none;
    }

    .email-template-accordion .accordion-button .template-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 0.75rem;
        font-size: 1rem;
    }

    .email-template-accordion .accordion-button .template-icon.bg-success-transparent {
        background: rgba(25, 135, 84, 0.15);
        color: #198754;
    }

    .email-template-accordion .accordion-button .template-icon.bg-warning-transparent {
        background: rgba(255, 193, 7, 0.15);
        color: #ffc107;
    }

    .email-template-accordion .accordion-button .template-icon.bg-info-transparent {
        background: rgba(13, 202, 240, 0.15);
        color: #0dcaf0;
    }

    .email-template-accordion .accordion-button .template-icon.bg-danger-transparent {
        background: rgba(220, 53, 69, 0.15);
        color: #dc3545;
    }

    .email-template-accordion .accordion-button .template-icon.bg-secondary-transparent {
        background: rgba(108, 117, 125, 0.15);
        color: #6c757d;
    }

    .email-template-accordion .accordion-button .template-icon.bg-primary-transparent {
        background: rgba(13, 110, 253, 0.15);
        color: #0d6efd;
    }

    .email-template-accordion .accordion-body {
        padding: 1.25rem;
        background: #fafbfc;
    }

    .variables-list {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 0.5rem;
        margin-top: 0.5rem;
    }

    .variables-list .var-item {
        display: flex;
        align-items: center;
        padding: 0.35rem 0.5rem;
        background: #fff;
        border: 1px solid #e9ecef;
        border-radius: 4px;
        font-size: 0.85rem;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .variables-list .var-item:hover {
        background: #e7f1ff;
        border-color: #0d6efd;
    }

    .variables-list .var-item code {
        background: #e7f1ff;
        color: #0d6efd;
        padding: 0.15rem 0.4rem;
        border-radius: 3px;
        font-size: 0.8rem;
        margin-right: 0.5rem;
    }

    /* GrapesJS Modal */
    .gjs-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.85);
        z-index: 9999;
        display: none;
    }

    .gjs-modal-overlay.active {
        display: flex;
        flex-direction: column;
    }

    .gjs-modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.75rem 1.25rem;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: #fff;
    }

    .gjs-modal-header h5 {
        margin: 0;
        font-weight: 600;
        font-size: 1rem;
    }

    .gjs-modal-header .btn-group .btn {
        padding: 0.4rem 0.75rem;
        font-size: 0.85rem;
    }

    .gjs-modal-body {
        flex: 1;
        display: flex;
        overflow: hidden;
        background: #444;
    }

    .gjs-editor-container {
        flex: 1;
        height: 100%;
    }

    /* Content preview */
    .content-preview {
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
        padding: 1rem;
        min-height: 150px;
        max-height: 300px;
        overflow: auto;
        margin-bottom: 0.5rem;
    }

    .content-preview:empty::before {
        content: 'Chưa có nội dung. Click "Mở Editor" để tạo template.';
        color: #6c757d;
        font-style: italic;
    }

    /* GrapesJS Customization */
    .gjs-one-bg {
        background-color: #2d3748 !important;
    }

    .gjs-two-color {
        color: #fff !important;
    }

    .gjs-three-bg {
        background-color: #4a5568 !important;
    }

    .gjs-four-color,
    .gjs-four-color-h:hover {
        color: #667eea !important;
    }

    /* Dark Mode Styles */
    [data-theme-mode="dark"] .email-template-accordion .accordion-item {
        border-color: rgba(255, 255, 255, 0.1);
    }

    [data-theme-mode="dark"] .email-template-accordion .accordion-body {
        background: rgba(255, 255, 255, 0.03);
    }

    [data-theme-mode="dark"] .variables-list .var-item {
        background: rgba(255, 255, 255, 0.05);
        border-color: rgba(255, 255, 255, 0.1);
        color: #c5c5c5;
    }

    [data-theme-mode="dark"] .variables-list .var-item:hover {
        background: rgba(13, 110, 253, 0.2);
    }

    [data-theme-mode="dark"] .variables-list .var-item code {
        background: rgba(13, 110, 253, 0.2);
        color: #6ea8fe;
    }

    [data-theme-mode="dark"] .card.border-0.bg-white {
        background: rgba(255, 255, 255, 0.05) !important;
    }

    [data-theme-mode="dark"] .card-header.bg-light {
        background: rgba(255, 255, 255, 0.08) !important;
        border-color: rgba(255, 255, 255, 0.1) !important;
    }

    [data-theme-mode="dark"] .content-preview {
        background: rgba(255, 255, 255, 0.05);
        border-color: rgba(255, 255, 255, 0.1);
        color: #c5c5c5;
    }

    [data-theme-mode="dark"] .content-preview:empty::before {
        color: #888;
    }
</style>

<div class="tab-pane text-muted show active" id="mail-template" role="tabpanel">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><?= __('Nội dung thông báo Mail'); ?></h4>
        <span class="badge bg-light text-muted"><?= count($email_templates); ?> templates</span>
    </div>

    <div class="alert alert-info-transparent mb-3">
        <i class="ri-information-line me-1"></i>
        <?= __('Để trống Subject = tắt gửi email.'); ?>
    </div>

    <form action="" method="POST" id="mailTemplateForm">
        <?= csrfField(); ?>

        <div class="accordion email-template-accordion" id="emailTemplatesAccordion">
            <?php foreach ($email_templates as $index => $template): ?>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#collapse_<?= $template['id']; ?>"
                            aria-expanded="false"
                            aria-controls="collapse_<?= $template['id']; ?>">
                            <span class="template-icon bg-<?= $template['color']; ?>-transparent">
                                <i class="<?= $template['icon']; ?>"></i>
                            </span>
                            <?= $template['title']; ?>
                        </button>
                    </h2>
                    <div id="collapse_<?= $template['id']; ?>" class="accordion-collapse collapse" data-bs-parent="#emailTemplatesAccordion">
                        <div class="accordion-body">
                            <!-- Subject -->
                            <?php if (!empty($template['has_two_subjects'])): ?>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">
                                            <i class="ri-text me-1"></i><?= __('Subject (Sắp hết hạn)'); ?>
                                        </label>
                                        <input class="form-control"
                                            name="<?= $template['subject_key']; ?>"
                                            placeholder="<?= __('Nhập tiêu đề email...'); ?>"
                                            value="<?= $CMSNT->site($template['subject_key']) ?: 'Đơn hàng của bạn sắp hết hạn - {product_name}'; ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">
                                            <i class="ri-text me-1"></i><?= __('Subject (Đã hết hạn)'); ?>
                                        </label>
                                        <input class="form-control"
                                            name="<?= $template['subject_key_2']; ?>"
                                            placeholder="<?= __('Nhập tiêu đề email...'); ?>"
                                            value="<?= $CMSNT->site($template['subject_key_2']) ?: 'Đơn hàng của bạn đã hết hạn - {product_name}'; ?>">
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">
                                        <i class="ri-text me-1"></i><?= __('Subject'); ?>
                                    </label>
                                    <input class="form-control"
                                        name="<?= $template['subject_key']; ?>"
                                        placeholder="<?= __('Nhập tiêu đề email...'); ?>"
                                        value="<?= $CMSNT->site($template['subject_key']); ?>">
                                </div>
                            <?php endif; ?>

                            <!-- Content -->
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-label fw-semibold mb-0">
                                        <i class="ri-file-text-line me-1"></i><?= __('Nội dung Email'); ?>
                                    </label>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-secondary btn-default-template"
                                            data-type="<?= $template['content_key']; ?>"
                                            data-target="<?= $template['content_key']; ?>"
                                            title="<?= __('Sử dụng mẫu mặc định phong cách 1'); ?>">
                                            <i class="ri-file-copy-line me-1"></i><?= __('Mẫu 1'); ?>
                                        </button>
                                        <button type="button" class="btn btn-outline-info btn-default-template-2"
                                            data-type="<?= $template['content_key']; ?>"
                                            data-target="<?= $template['content_key']; ?>"
                                            title="<?= __('Sử dụng mẫu mặc định phong cách 2'); ?>">
                                            <i class="ri-layout-3-line me-1"></i><?= __('Mẫu 2'); ?>
                                        </button>
                                        <button type="button" class="btn btn-outline-success btn-default-template-3"
                                            data-type="<?= $template['content_key']; ?>"
                                            data-target="<?= $template['content_key']; ?>"
                                            title="<?= __('Sử dụng mẫu mặc định phong cách 3'); ?>">
                                            <i class="ri-palette-line me-1"></i><?= __('Mẫu 3'); ?>
                                        </button>
                                        <button type="button" class="btn btn-outline-purple btn-default-template-4"
                                            data-type="<?= $template['content_key']; ?>"
                                            data-target="<?= $template['content_key']; ?>"
                                            title="<?= __('Sử dụng mẫu mặc định phong cách Galaxy'); ?>"
                                            style="border-color: #8b5cf6; color: #8b5cf6;">
                                            <i class="ri-planet-line me-1"></i><?= __('Mẫu 4'); ?>
                                        </button>
                                        <button type="button" class="btn btn-dark btn-ai-email"
                                            data-type="<?= $template['content_key']; ?>"
                                            data-target="<?= $template['content_key']; ?>"
                                            title="<?= __('Tạo nội dung bằng AI'); ?>">
                                            <i class="ri-magic-line me-1"></i><?= __('AI'); ?>
                                        </button>
                                        <button type="button" class="btn btn-primary btn-open-editor"
                                            data-target="<?= $template['content_key']; ?>"
                                            data-title="<?= htmlspecialchars($template['title']); ?>">
                                            <i class="ri-drag-drop-line me-1"></i><?= __('Mở Editor'); ?>
                                        </button>
                                    </div>
                                </div>
                                <?php
                                $content_value = $CMSNT->site($template['content_key']);
                                if (empty($content_value) && !empty($template['default_content'])) {
                                    $content_value = $template['default_content'];
                                }
                                ?>
                                <!-- Content Preview -->
                                <div class="content-preview" id="preview_<?= $template['content_key']; ?>">
                                    <?= $content_value; ?>
                                </div>
                                <!-- Hidden input to store HTML -->
                                <input type="hidden"
                                    id="<?= $template['content_key']; ?>"
                                    name="<?= $template['content_key']; ?>"
                                    value="<?= htmlspecialchars($content_value); ?>">
                            </div>

                            <!-- Variables Reference -->
                            <div class="card border-0 bg-white">
                                <div class="card-header bg-light py-2 px-3">
                                    <h6 class="mb-0 fs-12 text-muted">
                                        <i class="ri-code-s-slash-line me-1"></i><?= __('Văn bản thay thế'); ?>
                                        <span class="text-primary ms-2" style="font-weight: normal;">(Click để copy)</span>
                                    </h6>
                                </div>
                                <div class="card-body py-2 px-3">
                                    <div class="variables-list">
                                        <?php foreach ($template['variables'] as $var => $desc): ?>
                                            <div class="var-item" data-var="<?= $var; ?>" title="Click để copy">
                                                <code><?= $var; ?></code>
                                                <span class="text-muted"><?= $desc; ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <button type="submit" name="SaveSettings" class="btn btn-primary w-100 mt-3 mb-3">
            <i class="fa fa-fw fa-save me-1"></i> <?= __('Save'); ?>
        </button>
    </form>
</div>

<!-- GrapesJS Modal -->
<div class="gjs-modal-overlay" id="gjsModal">
    <div class="gjs-modal-header">
        <h5><i class="ri-mail-line me-2"></i><span id="gjsModalTitle">Email Template Editor</span></h5>
        <div class="btn-group">
            <button type="button" class="btn btn-light" id="gjsPreviewBtn">
                <i class="ri-eye-line me-1"></i><?= __('Preview'); ?>
            </button>
            <button type="button" class="btn btn-success" id="gjsSaveBtn">
                <i class="ri-check-line me-1"></i><?= __('Lưu'); ?>
            </button>
            <button type="button" class="btn btn-secondary" id="gjsCloseBtn">
                <i class="ri-close-line me-1"></i><?= __('Đóng'); ?>
            </button>
        </div>
    </div>
    <div class="gjs-modal-body">
        <div id="gjsEditor" class="gjs-editor-container"></div>
    </div>
</div>

<!-- GrapesJS Core JS -->
<script src="https://unpkg.com/grapesjs@0.21.10/dist/grapes.min.js"></script>
<script src="https://unpkg.com/grapesjs-preset-newsletter@1.0.2/dist/index.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        let gjsEditor = null;
        let currentTargetId = null;
        const modal = document.getElementById('gjsModal');

        // Initialize GrapesJS
        function initGrapesJS(content) {
            if (gjsEditor) {
                gjsEditor.destroy();
            }

            gjsEditor = grapesjs.init({
                container: '#gjsEditor',
                height: '100%',
                width: 'auto',
                plugins: ['grapesjs-preset-newsletter'],
                pluginsOpts: {
                    'grapesjs-preset-newsletter': {
                        modalTitleImport: 'Import HTML',
                        modalTitleExport: 'Export HTML',
                    }
                },
                storageManager: false,
                assetManager: {
                    embedAsBase64: true,
                    upload: false,
                },
                panels: {
                    defaults: []
                },
                deviceManager: {
                    devices: [{
                            name: 'Desktop',
                            width: ''
                        },
                        {
                            name: 'Mobile',
                            width: '320px',
                            widthMedia: '480px'
                        }
                    ]
                },
            });

            // Add custom blocks for email variables
            const blockManager = gjsEditor.BlockManager;

            // Add text block with styling
            blockManager.add('text-basic', {
                label: 'Text',
                category: 'Basic',
                content: '<p style="margin: 0; padding: 10px;">Nhập nội dung văn bản tại đây...</p>',
                attributes: {
                    class: 'gjs-fonts gjs-f-text'
                }
            });

            // Add heading block
            blockManager.add('heading', {
                label: 'Heading',
                category: 'Basic',
                content: '<h2 style="margin: 0; padding: 10px; color: #333;">Tiêu đề</h2>',
                attributes: {
                    class: 'gjs-fonts gjs-f-h1p'
                }
            });

            // Add button block
            blockManager.add('button', {
                label: 'Button',
                category: 'Basic',
                content: `<a href="#" style="display: inline-block; padding: 12px 24px; background: #0d6efd; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600;">Click Here</a>`,
                attributes: {
                    class: 'gjs-fonts gjs-f-button'
                }
            });

            // Add divider
            blockManager.add('divider', {
                label: 'Divider',
                category: 'Basic',
                content: '<hr style="border: none; border-top: 1px solid #ddd; margin: 20px 0;">',
            });

            // Add image placeholder
            blockManager.add('image', {
                label: 'Image',
                category: 'Basic',
                content: {
                    type: 'image',
                    style: {
                        width: '100%',
                        'max-width': '300px'
                    }
                },
                attributes: {
                    class: 'gjs-fonts gjs-f-image'
                }
            });

            // Load content
            if (content) {
                gjsEditor.setComponents(content);
            }

            // Open blocks panel by default
            gjsEditor.runCommand('open-blocks');
        }

        // Open editor
        document.querySelectorAll('.btn-open-editor').forEach(btn => {
            btn.addEventListener('click', function() {
                currentTargetId = this.dataset.target;
                const title = this.dataset.title;
                const content = document.getElementById(currentTargetId).value;

                document.getElementById('gjsModalTitle').textContent = title;
                modal.classList.add('active');

                setTimeout(() => {
                    initGrapesJS(content);
                }, 100);
            });
        });

        // Save button
        document.getElementById('gjsSaveBtn').addEventListener('click', function() {
            if (gjsEditor && currentTargetId) {
                // Get HTML with inline CSS for email compatibility
                // Email clients like Gmail strip <style> tags, so we need inline styles
                const inlinedHtml = gjsEditor.runCommand('gjs-get-inlined-html');

                // Fallback: if inlined command not available, combine manually
                let fullHtml = inlinedHtml;
                if (!fullHtml) {
                    const html = gjsEditor.getHtml();
                    const css = gjsEditor.getCss();
                    fullHtml = css ? `<style>${css}</style>${html}` : html;
                }

                // Update hidden input
                document.getElementById(currentTargetId).value = fullHtml;

                // Update preview
                document.getElementById('preview_' + currentTargetId).innerHTML = fullHtml;

                // Show success message
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: '<?= __('Đã lưu'); ?>',
                        text: '<?= __('Nội dung đã được cập nhật. Nhấn Save để lưu vào database.'); ?>',
                        timer: 2000,
                        showConfirmButton: false
                    });
                }

                closeModal();
            }
        });

        // Preview button
        document.getElementById('gjsPreviewBtn').addEventListener('click', function() {
            if (gjsEditor) {
                gjsEditor.runCommand('preview');
            }
        });

        // Close button
        document.getElementById('gjsCloseBtn').addEventListener('click', closeModal);

        function closeModal() {
            modal.classList.remove('active');
            if (gjsEditor) {
                gjsEditor.destroy();
                gjsEditor = null;
            }
            currentTargetId = null;
        }

        // ESC to close
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.classList.contains('active')) {
                closeModal();
            }
        });

        // Copy variable on click
        document.querySelectorAll('.var-item').forEach(item => {
            item.addEventListener('click', function() {
                const varText = this.dataset.var;
                navigator.clipboard.writeText(varText).then(() => {
                    // Visual feedback
                    const originalBg = this.style.background;
                    this.style.background = '#d4edda';
                    this.style.borderColor = '#28a745';
                    setTimeout(() => {
                        this.style.background = originalBg;
                        this.style.borderColor = '';
                    }, 500);

                    showMessage('<?= __('Đã copy'); ?>: ' + varText, 'success');
                });
            });
        });

        // AI Email button handler
        $('.btn-ai-email').click(function() {
            var btn = $(this);
            var type = btn.data('type');
            var targetId = btn.data('target');

            var originalContent = btn.html();
            btn.html('<i class="fa fa-spinner fa-spin"></i>').prop('disabled', true);

            $.ajax({
                url: '<?= base_url('ajaxs/admin/ai.php'); ?>',
                method: 'POST',
                dataType: 'JSON',
                data: {
                    action: 'generateEmailNotification',
                    type: type
                },
                success: function(response) {
                    if (response.success) {
                        // Update hidden input
                        $('#' + targetId).val(response.content);
                        // Update preview
                        $('#preview_' + targetId).html(response.content);

                        Swal.fire({
                            icon: 'success',
                            title: '<?= __('Thành công'); ?>',
                            text: '<?= __('Đã tạo nội dung thông báo Email bằng AI'); ?>',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: '<?= __('Có lỗi xảy ra'); ?>',
                            text: response.message || '<?= __('Không thể tạo nội dung'); ?>'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: '<?= __('Lỗi kết nối'); ?>',
                        text: '<?= __('Không thể kết nối đến server AI'); ?>'
                    });
                },
                complete: function() {
                    btn.html(originalContent).prop('disabled', false);
                }
            });
        });

        // Default Template 1 button handler
        $('.btn-default-template').click(function() {
            var btn = $(this);
            var type = btn.data('type');
            var targetId = btn.data('target');

            var originalContent = btn.html();
            btn.html('<i class="fa fa-spinner fa-spin"></i>').prop('disabled', true);

            $.ajax({
                url: '<?= base_url('ajaxs/admin/ai.php'); ?>',
                method: 'POST',
                dataType: 'JSON',
                data: {
                    action: 'getDefaultEmailTemplate',
                    type: type
                },
                success: function(response) {
                    if (response.success) {
                        $('#' + targetId).val(response.content);
                        $('#preview_' + targetId).html(response.content);

                        showMessage('<?= __('Đã áp dụng mẫu 1'); ?>', 'success');
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: '<?= __('Có lỗi xảy ra'); ?>',
                            text: response.message || '<?= __('Không thể tải mẫu'); ?>'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: '<?= __('Lỗi kết nối'); ?>',
                        text: '<?= __('Không thể kết nối đến server'); ?>'
                    });
                },
                complete: function() {
                    btn.html(originalContent).prop('disabled', false);
                }
            });
        });

        // Default Template 2 button handler (Phong cách khác)
        $('.btn-default-template-2').click(function() {
            var btn = $(this);
            var type = btn.data('type');
            var targetId = btn.data('target');

            var originalContent = btn.html();
            btn.html('<i class="fa fa-spinner fa-spin"></i>').prop('disabled', true);

            $.ajax({
                url: '<?= base_url('ajaxs/admin/ai.php'); ?>',
                method: 'POST',
                dataType: 'JSON',
                data: {
                    action: 'getDefaultEmailTemplate2',
                    type: type
                },
                success: function(response) {
                    if (response.success) {
                        $('#' + targetId).val(response.content);
                        $('#preview_' + targetId).html(response.content);

                        showMessage('<?= __('Đã áp dụng mẫu 2'); ?>', 'success');
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: '<?= __('Có lỗi xảy ra'); ?>',
                            text: response.message || '<?= __('Không thể tải mẫu'); ?>'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: '<?= __('Lỗi kết nối'); ?>',
                        text: '<?= __('Không thể kết nối đến server'); ?>'
                    });
                },
                complete: function() {
                    btn.html(originalContent).prop('disabled', false);
                }
            });
        });

        // Default Template 3 button handler (Phong cách Gradient)
        $('.btn-default-template-3').click(function() {
            var btn = $(this);
            var type = btn.data('type');
            var targetId = btn.data('target');

            var originalContent = btn.html();
            btn.html('<i class="fa fa-spinner fa-spin"></i>').prop('disabled', true);

            $.ajax({
                url: '<?= base_url('ajaxs/admin/ai.php'); ?>',
                method: 'POST',
                dataType: 'JSON',
                data: {
                    action: 'getDefaultEmailTemplate3',
                    type: type
                },
                success: function(response) {
                    if (response.success) {
                        $('#' + targetId).val(response.content);
                        $('#preview_' + targetId).html(response.content);

                        showMessage('<?= __('Đã áp dụng mẫu 3'); ?>', 'success');
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: '<?= __('Có lỗi xảy ra'); ?>',
                            text: response.message || '<?= __('Không thể tải mẫu'); ?>'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: '<?= __('Lỗi kết nối'); ?>',
                        text: '<?= __('Không thể kết nối đến server'); ?>'
                    });
                },
                complete: function() {
                    btn.html(originalContent).prop('disabled', false);
                }
            });
        });

        // Default Template 4 button handler (Phong cách Galaxy)
        $('.btn-default-template-4').click(function() {
            var btn = $(this);
            var type = btn.data('type');
            var targetId = btn.data('target');

            var originalContent = btn.html();
            btn.html('<i class="fa fa-spinner fa-spin"></i>').prop('disabled', true);

            $.ajax({
                url: '<?= base_url('ajaxs/admin/ai.php'); ?>',
                method: 'POST',
                dataType: 'JSON',
                data: {
                    action: 'getDefaultEmailTemplate4',
                    type: type
                },
                success: function(response) {
                    if (response.success) {
                        $('#' + targetId).val(response.content);
                        $('#preview_' + targetId).html(response.content);

                        showMessage('<?= __('Đã áp dụng mẫu 4 - Galaxy'); ?>', 'success');
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: '<?= __('Có lỗi xảy ra'); ?>',
                            text: response.message || '<?= __('Không thể tải mẫu'); ?>'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: '<?= __('Lỗi kết nối'); ?>',
                        text: '<?= __('Không thể kết nối đến server'); ?>'
                    });
                },
                complete: function() {
                    btn.html(originalContent).prop('disabled', false);
                }
            });
        });
    });
</script>