<?php if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

// Xử lý lưu cài đặt SHOPKEY
if (isset($_POST['SaveSettings'])) {
    // Kiểm tra quyền
    if (checkPermission($getUser['admin'], 'edit_shopkey') != true) {
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
        'action'        => __('Thay đổi cài đặt SHOPKEY')
    ]);

    foreach ($_POST as $key => $value) {
        $CMSNT->update("settings", array(
            'value' => $value
        ), " `name` = '$key' ");
    }

    $my_text = $CMSNT->site('noti_action');
    $my_text = str_replace('{domain}', $_SERVER['SERVER_NAME'], $my_text);
    $my_text = str_replace('{username}', $getUser['username'], $my_text);
    $my_text = str_replace('{action}', __('Thay đổi cài đặt SHOPKEY'), $my_text);
    $my_text = str_replace('{ip}', myip(), $my_text);
    $my_text = str_replace('{time}', gettime(), $my_text);
    sendMessAdmin($my_text);

    admin_msg_success("Lưu thành công!", "", 1000);
}
?>
<div class="tab-pane text-muted show active" id="cai-dat-smmpanel" role="tabpanel">
    <div class="d-flex align-items-center mb-4">
        <div class="flex-shrink-0">
            <div class="avatar avatar-md bg-primary-transparent rounded-circle">
                <i class="ri-settings-3-line fs-18 text-primary"></i>
            </div>
        </div>
        <div class="flex-grow-1 ms-3">
            <h4 class="mb-1"><?= __('Cài đặt SHOPKEY'); ?></h4>
            <p class="text-muted mb-0">
                <?= __('Cấu hình các tính năng và tham số cho hệ thống SHOPKEY'); ?>
            </p>
        </div>
    </div>

    <form action="" method="POST">
        <?= csrfField(); ?>
        <div class="row g-4">
            <!-- Card: Cấu hình thuế và quyền truy cập -->
            <div class="col-xl-6">
                <div class="card border border-primary-subtle h-100">
                    <div class="card-body">
                        <!-- Thuế VAT -->
                        <!-- <div class="mb-4">
                            <label
                                class="form-label fw-medium d-flex align-items-center">
                                <i class="ri-percent-line me-2 text-primary"></i>
                                <?= __('Thuế VAT nếu có'); ?>
                            </label>
                            <div class="input-group">
                                <input name="tax_vat" type="text"
                                    class="form-control"
                                    value="<?= $CMSNT->site('tax_vat'); ?>"
                                    placeholder="<?= __('Nhập % thuế VAT'); ?>"
                                    required>
                                <span
                                    class="input-group-text bg-primary-transparent text-primary fw-semibold">
                                    <i class="ri-percent-line me-1"></i>
                                </span>
                            </div>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                <?= __('Nhập 0 nếu không muốn tính thuế VAT cho đơn hàng'); ?>
                            </div>
                        </div> -->

                        <!-- Quyền xem dịch vụ -->
                        <div class="mb-4">
                            <label
                                class="form-label fw-medium d-flex align-items-center">
                                <i class="ri-lock-line me-2 text-warning"></i>
                                <?= __('Yêu cầu đăng nhập để xem dịch vụ'); ?>
                            </label>
                            <select class="form-select"
                                name="isLoginRequiredToViewProduct">
                                <option value="1"
                                    <?= $CMSNT->site('isLoginRequiredToViewProduct') == 1 ? 'selected' : ''; ?>>
                                    <i class="ri-lock-line"></i>
                                    <?= __('BẬT - Phải đăng nhập mới xem được dịch vụ'); ?>
                                </option>
                                <option value="0"
                                    <?= $CMSNT->site('isLoginRequiredToViewProduct') == 0 ? 'selected' : ''; ?>>
                                    <i class="ri-lock-unlock-line"></i>
                                    <?= __('TẮT - Không cần đăng nhập'); ?>
                                </option>
                            </select>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                <?= __('Bật tính năng này để yêu cầu khách hàng phải đăng nhập mới có thể xem danh sách dịch vụ'); ?>
                            </div>
                        </div>
                        <!-- ON/OFF Hiển thị số lượng đã bán -->
                        <div class="mb-4">
                            <label
                                class="form-label fw-medium d-flex align-items-center">
                                <i class="fa-solid fa-fire-flame-curved me-2 text-success"></i>
                                <?= __('ON/OFF Hiển thị số lượng đã bán'); ?>
                            </label>
                            <select class="form-select" name="isShowSold">
                                <option value="1"
                                    <?= $CMSNT->site('isShowSold') == 1 ? 'selected' : ''; ?>>
                                    <i class="fa-solid fa-fire-flame-curved"></i>
                                    <?= __('BẬT'); ?>
                                </option>
                                <option value="0"
                                    <?= $CMSNT->site('isShowSold') == 0 ? 'selected' : ''; ?>>
                                    <i class="fa-solid fa-fire-flame-curved"></i>
                                    <?= __('TẮT'); ?>
                                </option>
                            </select>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                <?= __('Bật tính năng này để hiển thị số lượng đã bán của sản phẩm'); ?>
                            </div>
                        </div>

                        <!-- ON/OFF Đánh giá sản phẩm -->
                        <div class="mb-4">
                            <label
                                class="form-label fw-medium d-flex align-items-center">
                                <i class="fa-solid fa-star me-2 text-warning"></i>
                                <?= __('ON/OFF Đánh giá sản phẩm'); ?>
                            </label>
                            <select class="form-select" name="status_review_product">
                                <option value="1"
                                    <?= $CMSNT->site('status_review_product') == 1 ? 'selected' : ''; ?>>
                                    <?= __('BẬT - Cho phép khách hàng đánh giá sản phẩm'); ?>
                                </option>
                                <option value="0"
                                    <?= $CMSNT->site('status_review_product') == 0 ? 'selected' : ''; ?>>
                                    <?= __('TẮT - Ẩn tính năng đánh giá'); ?>
                                </option>
                            </select>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                <?= __('Bật tính năng này để cho phép khách hàng đánh giá sản phẩm sau khi mua hàng'); ?>
                            </div>
                        </div>

                        <!-- Chat ID nhận thông báo đơn hàng ORDER -->
                        <div class="mb-4">
                            <label
                                class="form-label fw-medium d-flex align-items-center">
                                <i class="ri-telegram-line me-2 text-info"></i>
                                <?= __('Chat ID nhận thông báo đơn hàng thủ công'); ?>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-info-transparent text-info">
                                    <i class="ri-telegram-line"></i>
                                </span>
                                <input name="pending_order_telegram_chat_id" type="text"
                                    class="form-control"
                                    value="<?= $CMSNT->site('pending_order_telegram_chat_id'); ?>"
                                    placeholder="<?= __('Nhập Chat ID Telegram'); ?>">
                            </div>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                <?= __('Bỏ trống để sử dụng Chat ID trong Cài đặt → Kết nối → Bot thông báo Telegram'); ?>
                            </div>
                        </div>

                    </div>
                </div>
            </div>



            <div class="col-xl-6">
                <div class="card border border-success-subtle h-100">
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold"><i
                                    class="ri-repeat-line me-2 text-success"></i>
                                <?= __('Loại random mã đơn hàng'); ?></label>
                            <select class="form-select"
                                name="random_transid_order_type">
                                <option value="string"
                                    <?= $CMSNT->site('random_transid_order_type') == 'string' ? 'selected' : ''; ?>>
                                    <?= __('Chuỗi ký tự (ABC...)'); ?>
                                </option>
                                <option value="string_number"
                                    <?= $CMSNT->site('random_transid_order_type') == 'string_number' ? 'selected' : ''; ?>>
                                    <?= __('Chuỗi ký tự + số (ABC123...)'); ?>
                                </option>
                                <option value="number"
                                    <?= $CMSNT->site('random_transid_order_type') == 'number' ? 'selected' : ''; ?>>
                                    <?= __('Chỉ số (123456...)'); ?>
                                </option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold"><i
                                    class="ri-text me-2 text-danger"></i>
                                <?= __('Số ký tự Random'); ?></label>
                            <div class="input-group">
                                <span class="input-group-text"><i
                                        class="ri-text"></i></span>
                                <input type="number" class="form-control"
                                    value="<?= $CMSNT->site('random_transid_order_length'); ?>"
                                    name="random_transid_order_length"
                                    placeholder="6" min="6" max="20">
                            </div>
                            <div class="form-text text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                <?= __('Tối thiểu 6 ký tự, tối đa 20 ký tự'); ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold"><i
                                    class="ri-hashtag me-2 text-info"></i>
                                <?= __('Prefix'); ?></label>
                            <div class="input-group">
                                <span class="input-group-text"><i
                                        class="ri-hashtag"></i></span>
                                <input type="text" class="form-control"
                                    value="<?= $CMSNT->site('prefix_transid_order'); ?>"
                                    name="prefix_transid_order"
                                    placeholder="<?= __('Nhập prefix (không bắt buộc)'); ?>">
                            </div>

                            <div class="form-text text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                <?= __('Prefix sẽ được thêm vào đầu mã đơn hàng'); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Save Button -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="d-flex justify-content-end">
                    <button type="submit" name="SaveSettings"
                        class="btn btn-primary btn-lg px-5">
                        <i class="ri-save-line me-2"></i>
                        <?= __('Lưu Cài đặt SHOPKEY'); ?>
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>