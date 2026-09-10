<?php if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

// Xử lý lưu cài đặt thông báo
if (isset($_POST['SaveSettings'])) {
    // Kiểm tra quyền
    if (checkPermission($getUser['admin'], 'edit_notification') != true) {
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
        'action'        => __('Thay đổi cài đặt thông báo')
    ]);

    foreach ($_POST as $key => $value) {
        $CMSNT->update("settings", array(
            'value' => $value
        ), " `name` = '$key' ");
    }

    $my_text = $CMSNT->site('noti_action');
    $my_text = str_replace('{domain}', $_SERVER['SERVER_NAME'], $my_text);
    $my_text = str_replace('{username}', $getUser['username'], $my_text);
    $my_text = str_replace('{action}', __('Thay đổi cài đặt thông báo'), $my_text);
    $my_text = str_replace('{ip}', myip(), $my_text);
    $my_text = str_replace('{time}', gettime(), $my_text);
    sendMessAdmin($my_text);

    admin_msg_success("Lưu thành công!", "", 1000);
}
?>
<div class="tab-pane text-muted show active" id="notification" role="tabpanel">
    <h4><?= __('Thay đổi nội dung thông báo'); ?></h4>
    <form action="" method="POST">
        <?= csrfField(); ?>
        <div class="row">
            <!-- Thông báo Website -->
            <div class="col-lg-6 mb-4">
                <div class="card border border-primary-transparent">
                    <div class="card-header bg-primary-transparent">
                        <h6 class="card-title mb-0 text-uppercase text-dark">
                            <i class="ri-notification-line me-2"></i>
                            <?= __('Thông báo Website'); ?>
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                <i
                                    class="ri-home-line me-1"></i><?= __('Thông báo ngoài trang chủ'); ?>
                            </label>
                            <textarea class="form-control" id="notice_home" rows="4"
                                name="notice_home"
                                placeholder="<?= __('Nhập thông báo hiển thị trên trang chủ...'); ?>"><?= $CMSNT->site('notice_home'); ?></textarea>
                            <small
                                class="text-muted"><?= __('Hiển thị ở banner hoặc khu vực thông báo trang chủ'); ?></small>
                        </div>
                        <!-- <div class="mb-0">
                            <label class="form-label fw-semibold">
                                <i
                                    class="ri-history-line me-1"></i><?= __('Thông báo trang lịch sử đơn hàng'); ?>
                            </label>
                            <textarea class="form-control" id="notice_orders"
                                rows="4" name="notice_orders"
                                placeholder="<?= __('Nhập thông báo hiển thị trên trang lịch sử đơn hàng...'); ?>"><?= $CMSNT->site('notice_orders'); ?></textarea>
                            <small
                                class="text-muted"><?= __('Hiển thị ở đầu trang lịch sử đơn hàng của khách hàng'); ?></small>
                        </div> -->
                    </div>
                </div>
            </div>

            <!-- Thông báo Popup -->
            <div class="col-lg-6 mb-4">
                <div class="card border border-warning-transparent">
                    <div class="card-header bg-warning-transparent">
                        <h6 class="card-title mb-0 text-uppercase text-dark">
                            <i class="ri-window-line me-2"></i>
                            <?= __('Thông báo Popup'); ?>
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                <i
                                    class="ri-toggle-line me-1"></i><?= __('Trạng thái Popup'); ?>
                            </label>
                            <select class="form-select" name="popup_status">
                                <option
                                    <?= $CMSNT->site('popup_status') == 1 ? 'selected' : ''; ?>
                                    value="1">
                                    <i class="ri-checkbox-circle-line"></i>
                                    <?= __('Bật'); ?> (ON)
                                </option>
                                <option
                                    <?= $CMSNT->site('popup_status') == 0 ? 'selected' : ''; ?>
                                    value="0">
                                    <i class="ri-close-circle-line"></i>
                                    <?= __('Tắt'); ?> (OFF)
                                </option>
                            </select>
                            <small
                                class="text-muted"><?= __('Bật/tắt hiển thị popup thông báo trên website'); ?></small>
                        </div>
                        <div class="mb-0">
                            <label class="form-label fw-semibold">
                                <i
                                    class="ri-window-2-line me-1"></i><?= __('Nội dung Popup'); ?>
                            </label>
                            <textarea class="form-control" id="popup_noti" rows="5"
                                name="popup_noti"
                                placeholder="<?= __('Nhập nội dung hiển thị trong popup...'); ?>"><?= $CMSNT->site('popup_noti'); ?></textarea>
                            <small
                                class="text-muted"><?= __('Nội dung sẽ hiển thị trong popup khi khách truy cập website'); ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Nội dung các trang -->
        <div class="row">
            <div class="col-12">
                <div class="card border border-info-transparent">
                    <div class="card-header bg-info-transparent">
                        <h6 class="card-title mb-0 text-uppercase text-dark">
                            <i class="ri-pages-line me-2"></i>
                            <?= __('Nội dung các trang hệ thống'); ?>
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- Trang liên hệ -->
                            <div class="col-lg-6 mb-3">
                                <div
                                    class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-label fw-semibold mb-0">
                                        <i
                                            class="ri-phone-line me-1"></i><?= __('Nội dung trang liên hệ'); ?>
                                    </label>
                                    <button type="button"
                                        class="btn btn-sm btn-dark btn-wave text-white btn-ai-page"
                                        data-type="page_contact"
                                        data-target="#page_contact"
                                        title="Tạo nội dung bằng AI">
                                        <i
                                            class="ri-magic-line me-1"></i><?= __('Generated by AI'); ?>
                                    </button>
                                </div>
                                <textarea class="form-control" id="page_contact"
                                    rows="6" name="page_contact"
                                    placeholder="<?= __('Nhập nội dung trang liên hệ...'); ?>"><?= $CMSNT->site('page_contact'); ?></textarea>
                                <small
                                    class="text-muted"><?= __('Hiển thị trên trang'); ?>
                                    <a href="<?= BASE_URL('client/contact'); ?>"
                                        target="_blank"><?= __('Liên hệ'); ?></a></small>
                            </div>

                            <!-- Trang chính sách -->
                            <div class="col-lg-6 mb-3">
                                <div
                                    class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-label fw-semibold mb-0">
                                        <i
                                            class="ri-file-shield-line me-1"></i><?= __('Nội dung trang chính sách'); ?>
                                    </label>
                                    <button type="button"
                                        class="btn btn-sm btn-dark btn-wave text-white btn-ai-page"
                                        data-type="page_policy"
                                        data-target="#page_policy"
                                        title="Tạo nội dung bằng AI">
                                        <i
                                            class="ri-magic-line me-1"></i><?= __('Generated by AI'); ?>
                                    </button>
                                </div>
                                <textarea class="form-control" id="page_policy"
                                    rows="6" name="page_policy"
                                    placeholder="<?= __('Nhập nội dung trang chính sách...'); ?>"><?= $CMSNT->site('page_policy'); ?></textarea>
                                <small
                                    class="text-muted"><?= __('Hiển thị trên trang'); ?>
                                    <a href="<?= BASE_URL('client/policy'); ?>"
                                        target="_blank"><?= __('Chính sách'); ?></a></small>
                            </div>

                            <!-- Trang quyền riêng tư -->
                            <div class="col-lg-6 mb-3">
                                <div
                                    class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-label fw-semibold mb-0">
                                        <i
                                            class="ri-shield-user-line me-1"></i><?= __('Nội dung trang quyền riêng tư'); ?>
                                    </label>
                                    <button type="button"
                                        class="btn btn-sm btn-dark btn-wave text-white btn-ai-page"
                                        data-type="page_privacy"
                                        data-target="#page_privacy"
                                        title="Tạo nội dung bằng AI">
                                        <i
                                            class="ri-magic-line me-1"></i><?= __('Generated by AI'); ?>
                                    </button>
                                </div>
                                <textarea class="form-control" id="page_privacy"
                                    rows="6" name="page_privacy"
                                    placeholder="<?= __('Nhập nội dung trang quyền riêng tư...'); ?>"><?= $CMSNT->site('page_privacy'); ?></textarea>
                                <small
                                    class="text-muted"><?= __('Hiển thị trên trang'); ?>
                                    <a href="<?= BASE_URL('client/privacy'); ?>"
                                        target="_blank"><?= __('Quyền riêng tư'); ?></a></small>
                            </div>

                            <!-- Trang FAQ -->
                            <div class="col-lg-6 mb-3">
                                <div
                                    class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-label fw-semibold mb-0">
                                        <i
                                            class="ri-question-answer-line me-1"></i><?= __('Nội dung trang FAQ'); ?>
                                    </label>
                                    <button type="button"
                                        class="btn btn-sm btn-dark btn-wave text-white btn-ai-page"
                                        data-type="page_faq" data-target="#page_faq"
                                        title="Tạo nội dung bằng AI">
                                        <i
                                            class="ri-magic-line me-1"></i><?= __('Generated by AI'); ?>
                                    </button>
                                </div>
                                <textarea class="form-control" id="page_faq"
                                    rows="6" name="page_faq"
                                    placeholder="<?= __('Nhập nội dung trang FAQ...'); ?>"><?= $CMSNT->site('page_faq'); ?></textarea>
                                <small
                                    class="text-muted"><?= __('Hiển thị trên trang'); ?>
                                    <a href="<?= BASE_URL('client/faq'); ?>"
                                        target="_blank"><?= __('Câu hỏi thường gặp'); ?></a></small>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chính sách đăng ký -->
        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="card border border-secondary-transparent">
                    <div class="card-header bg-secondary-transparent">
                        <h6 class="card-title mb-0 text-uppercase text-dark">
                            <i class="ri-user-add-line me-2"></i>
                            <?= __('Chính sách đăng ký'); ?>
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                <i class="ri-toggle-line me-1"></i><?= __('Trạng thái'); ?>
                            </label>
                            <select class="form-select" name="isConfirmPolicyRegister">
                                <option <?= $CMSNT->site('isConfirmPolicyRegister') == 1 ? 'selected' : ''; ?> value="1">
                                    <?= __('Bật'); ?> (ON)
                                </option>
                                <option <?= $CMSNT->site('isConfirmPolicyRegister') == 0 ? 'selected' : ''; ?> value="0">
                                    <?= __('Tắt'); ?> (OFF)
                                </option>
                            </select>
                            <small class="text-muted"><?= __('Bật/tắt yêu cầu xác nhận chính sách khi đăng ký'); ?></small>
                        </div>
                        <div class="mb-0">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label fw-semibold mb-0">
                                    <i class="ri-file-text-line me-1"></i><?= __('Nội dung chính sách'); ?>
                                </label>
                                <button type="button"
                                    class="btn btn-sm btn-dark btn-wave text-white btn-ai-page"
                                    data-type="policy_register" data-target="#policy_register"
                                    title="Tạo nội dung bằng AI">
                                    <i class="ri-magic-line me-1"></i><?= __('Generated by AI'); ?>
                                </button>
                            </div>
                            <textarea class="form-control" id="policy_register"
                                rows="5" name="policy_register"
                                placeholder="<?= __('Nhập nội dung chính sách đăng ký...'); ?>"><?= $CMSNT->site('policy_register'); ?></textarea>
                            <small class="text-muted"><?= __('Hiển thị khi người dùng đăng ký tài khoản mới'); ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card border border-success-transparent">
                    <div class="card-body text-center">
                        <button type="submit" name="SaveSettings"
                            class="btn btn-success btn-wave">
                            <i
                                class="ri-save-line me-2"></i><?= __('Lưu tất cả thay đổi'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    // Cấu hình CKEDITOR đơn giản với các chức năng cần thiết - 1 hàng duy nhất
    var ckeditorConfig = {
        toolbar: [{
                name: 'styles',
                items: ['Format', 'FontSize']
            },
            {
                name: 'basicstyles',
                items: ['Bold', 'Italic', 'Underline']
            },
            {
                name: 'colors',
                items: ['TextColor']
            },
            {
                name: 'paragraph',
                items: ['NumberedList', 'BulletedList', 'JustifyLeft', 'JustifyCenter']
            },
            {
                name: 'links',
                items: ['Link']
            },
            {
                name: 'insert',
                items: ['Image', 'Table']
            },
            {
                name: 'tools',
                items: ['Source']
            }
        ],
        height: 200,
        removePlugins: 'elementspath',
        resize_enabled: true,
        language: 'vi'
    };

    // Khởi tạo CKEDITOR cho các textarea thông báo
    CKEDITOR.replace("popup_noti", ckeditorConfig);
    CKEDITOR.replace("page_faq", ckeditorConfig);
    CKEDITOR.replace("page_policy", ckeditorConfig);
    CKEDITOR.replace("page_privacy", ckeditorConfig);
    CKEDITOR.replace("page_contact", ckeditorConfig);
    CKEDITOR.replace("policy_register", ckeditorConfig);
    CKEDITOR.replace("notice_home", ckeditorConfig);
    CKEDITOR.replace("notice_orders", ckeditorConfig);

    document.addEventListener('DOMContentLoaded', function() {
        // Xử lý nút AI cho System Pages
        $('.btn-ai-page').click(function() {
            var btn = $(this);
            var type = btn.data('type');
            var target = btn.data('target');
            var targetElement = $(target);

            // Hiển thị loading
            var originalContent = btn.html();
            btn.html('<i class="fa fa-spinner fa-spin"></i>').prop('disabled', true);

            $.ajax({
                url: '<?= base_url('ajaxs/admin/ai.php'); ?>',
                method: 'POST',
                dataType: 'JSON',
                data: {
                    action: 'generateSystemPageContent',
                    type: type
                },
                success: function(response) {
                    if (response.success) {
                        // Nếu là CKEDITOR, cập nhật vào editor
                        if (CKEDITOR.instances[targetElement.attr('id')]) {
                            CKEDITOR.instances[targetElement.attr('id')].setData(response
                                .content);
                        } else {
                            // Nếu là textarea thường
                            targetElement.val(response.content);
                        }

                        // Hiển thị thông báo thành công
                        Swal.fire({
                            icon: 'success',
                            title: '<?= __('Thành công'); ?>',
                            text: '<?= __('Đã tạo nội dung trang hệ thống bằng AI'); ?>',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    } else {
                        // Hiển thị lỗi
                        Swal.fire({
                            icon: 'error',
                            title: '<?= __('Có lỗi xảy ra'); ?>',
                            text: response.message ||
                                '<?= __('Không thể tạo nội dung'); ?>'
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
                    // Khôi phục nút
                    btn.html(originalContent).prop('disabled', false);
                }
            });
        });
    });
</script>