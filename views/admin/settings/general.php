<?php if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

// Xử lý lưu cài đặt chung
if (isset($_POST['SaveSettings'])) {
    // Kiểm tra quyền
    if (checkPermission($getUser['admin'], 'edit_general') != true) {
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
        'action'        => __('Thay đổi cài đặt chung')
    ]);

    foreach ($_POST as $key => $value) {
        $CMSNT->update("settings", array(
            'value' => $value
        ), " `name` = '$key' ");
    }

    $my_text = $CMSNT->site('noti_action');
    $my_text = str_replace('{domain}', $_SERVER['SERVER_NAME'], $my_text);
    $my_text = str_replace('{username}', $getUser['username'], $my_text);
    $my_text = str_replace('{action}', __('Thay đổi cài đặt chung'), $my_text);
    $my_text = str_replace('{ip}', myip(), $my_text);
    $my_text = str_replace('{time}', gettime(), $my_text);
    sendMessAdmin($my_text);

    admin_msg_success("Lưu thành công!", "", 1000);
}
?>
<div class="tab-pane text-muted show active" id="cai-dat-chung" role="tabpanel">
    <h4><?= __('Cài đặt chung'); ?></h4>
    <form action="" method="POST">
        <?= csrfField(); ?>
        <div class="row push mb-3">
            <div class="col-md-6">
                <table class="table table-bordered table-striped table-hover">
                    <tbody>
                        <!-- <tr>
                            <td><?= __('Allowed Domains'); ?></td>
                            <td>
                                <input type="text" name="domains"
                                    value="<?= $CMSNT->site('domains'); ?>"
                                    class="form-control">
                                <small>Không thay đổi nếu không hiểu rõ, phí khôi
                                    phục 100.000đ 1 lần</small>
                            </td>
                        </tr> -->
                        <tr>
                            <td><?= __('Title'); ?></td>
                            <td>
                                <input type="text" name="title"
                                    value="<?= $CMSNT->site('title'); ?>"
                                    class="form-control">
                            </td>
                        </tr>
                        <tr>
                            <td><?= __('Description'); ?></td>
                            <td>
                                <textarea name="description"
                                    class="form-control"><?= $CMSNT->site('description'); ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <td><?= __('Keywords'); ?></td>
                            <td>
                                <textarea name="keywords"
                                    class="form-control"><?= $CMSNT->site('keywords'); ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <td><?= __('Author'); ?></td>
                            <td>
                                <input type="text" name="author"
                                    value="<?= $CMSNT->site('author'); ?>"
                                    class="form-control">
                            </td>
                        </tr>
                        <tr>
                            <td><?= __('Timezone'); ?></td>
                            <td>
                                <input type="text" name="timezone"
                                    value="<?= $CMSNT->site('timezone'); ?>"
                                    class="form-control">
                            </td>
                        </tr>
                        <tr>
                            <td><?= __('Email'); ?></td>
                            <td>
                                <div class="input-group mb-1">
                                    <span
                                        class="input-group-text"><?= $CMSNT->site('icon_email'); ?></span>
                                    <input type="text" name="icon_email"
                                        value='<?= $CMSNT->site('icon_email'); ?>'
                                        class="form-control">
                                </div>
                                <input type="text" name="email"
                                    value="<?= $CMSNT->site('email'); ?>"
                                    class="form-control">
                            </td>
                        </tr>
                        <tr>
                            <td><?= __('Hotline'); ?></td>
                            <td>
                                <div class="input-group mb-1">
                                    <span
                                        class="input-group-text"><?= $CMSNT->site('icon_hotline'); ?></span>
                                    <input type="text" name="icon_hotline"
                                        value='<?= $CMSNT->site('icon_hotline'); ?>'
                                        class="form-control">
                                </div>
                                <input type="text" name="hotline"
                                    value="<?= $CMSNT->site('hotline'); ?>"
                                    class="form-control">
                            </td>
                        </tr>

                        <tr>
                            <td><?= __('Địa chỉ'); ?></td>
                            <td>
                                <div class="input-group mb-1">
                                    <span
                                        class="input-group-text"><?= $CMSNT->site('icon_address'); ?></span>
                                    <input type="text" name="icon_address"
                                        value='<?= $CMSNT->site('icon_address'); ?>'
                                        class="form-control">
                                </div>
                                <input type="text" name="address"
                                    value="<?= $CMSNT->site('address'); ?>"
                                    class="form-control">
                            </td>
                        </tr>
                        <tr>
                            <td><?= __('Fanpage'); ?></td>
                            <td>
                                <input type="text" name="fanpage"
                                    value="<?= $CMSNT->site('fanpage'); ?>"
                                    class="form-control">
                            </td>
                        </tr>
                        <tr>
                            <td><?= __('Copyright Footer Left'); ?></td>
                            <td>
                                <textarea name="copyright_footer_left"
                                    class="form-control"><?= $CMSNT->site('copyright_footer_left'); ?></textarea>
                            </td>
                        </tr>


                    </tbody>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-bordered table-striped table-hover">
                    <tbody>
                        <tr>
                            <td><?= __('Trạng thái website'); ?></td>
                            <td>
                                <select class="form-control" name="status">
                                    <option
                                        <?= $CMSNT->site('status') == 1 ? 'selected' : ''; ?>
                                        value="1">ON
                                    </option>
                                    <option
                                        <?= $CMSNT->site('status') == 0 ? 'selected' : ''; ?>
                                        value="0">
                                        OFF
                                    </option>
                                </select>
                                <div class="form-text">
                                    <?= __('Chọn OFF nếu bạn muốn bật chế độ bảo trì. <br>Lưu ý: đang trong chế độ bảo trì vui lòng không đăng xuất tài khoản Admin.'); ?>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td><?= __('Cập nhật phiên bản tự động'); ?></td>
                            <td>
                                <select class="form-control" name="status_update">
                                    <option
                                        <?= $CMSNT->site('status_update') == 1 ? 'selected' : ''; ?>
                                        value="1">ON
                                    </option>
                                    <option
                                        <?= $CMSNT->site('status_update') == 0 ? 'selected' : ''; ?>
                                        value="0">
                                        OFF
                                    </option>
                                </select>
                                <div class="form-text">
                                    <?= __('Hệ thống sẽ tự động cập nhật khi có phiên bản mới nếu bạn chọn ON.'); ?>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td><?= __('ON/OFF Debug'); ?></td>
                            <td>
                                <select class="form-control" name="debug_mode">
                                    <option
                                        <?= $CMSNT->site('debug_mode') == 1 ? 'selected' : ''; ?>
                                        value="1">ON
                                    </option>
                                    <option
                                        <?= $CMSNT->site('debug_mode') == 0 ? 'selected' : ''; ?>
                                        value="0">
                                        OFF
                                    </option>
                                </select>
                                <div class="form-text">
                                    <?= __('Không bật ON khi chưa được CMSNT yêu cầu'); ?>.
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td><?= __('ON/OFF Debug Auto Bank'); ?></td>
                            <td>
                                <select class="form-control" name="debug_auto_bank">
                                    <option
                                        <?= $CMSNT->site('debug_auto_bank') == 1 ? 'selected' : ''; ?>
                                        value="1">ON
                                    </option>
                                    <option
                                        <?= $CMSNT->site('debug_auto_bank') == 0 ? 'selected' : ''; ?>
                                        value="0">
                                        OFF
                                    </option>
                                </select>
                                <div class="form-text">
                                    <?= __('Không bật ON khi chưa được CMSNT yêu cầu'); ?>.
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td><?= __('ON/OFF Debug API Suppliers'); ?></td>
                            <td>
                                <select class="form-control" name="debug_api_suppliers">
                                    <option
                                        <?= $CMSNT->site('debug_api_suppliers') == 1 ? 'selected' : ''; ?>
                                        value="1">ON
                                    </option>
                                    <option
                                        <?= $CMSNT->site('debug_api_suppliers') == 0 ? 'selected' : ''; ?>
                                        value="0">
                                        OFF
                                    </option>
                                </select>
                                <div class="form-text">
                                    <?= __('Debug API đồng bộ nguồn hàng'); ?>.
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>Font Family</td>
                            <td>
                                <textarea name="font_family" rows="4"
                                    class="form-control" placeholder='font-family: "Be Vietnam Pro", sans-serif;
font-weight: 400;
font-style: normal;'><?= $CMSNT->site('font_family'); ?></textarea>
                                <div class="form-text"><a class="text-primary"
                                        href="https://help.cmsnt.co/huong-dan/shopkey-huong-dan-thay-doi-font-chu-cho-website/"
                                        target="_blank"><?= __('Hướng dẫn sử dụng'); ?></a>
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <td><?= __('ON/OFF Tài liệu API'); ?></td>
                            <td>
                                <select class="form-control" name="api_status">
                                    <option
                                        <?= $CMSNT->site('api_status') == 1 ? 'selected' : ''; ?>
                                        value="1">ON
                                    </option>
                                    <option
                                        <?= $CMSNT->site('api_status') == 0 ? 'selected' : ''; ?>
                                        value="0">
                                        OFF
                                    </option>
                                </select>
                                <div class="form-text">
                                    <?= __('Hệ thống sẽ ẩn menu tài liệu API nếu bạn chọn OFF'); ?>
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <td><?= __('Hiển thị hình đại diện'); ?>
                            </td>
                            <td>
                                <select class="form-control" name="type_avatar">
                                    <option
                                        <?= $CMSNT->site('type_avatar') == 'gravatar' ? 'selected' : ''; ?>
                                        value="gravatar"><?= __('Gravatar'); ?>
                                    </option>
                                    <option
                                        <?= $CMSNT->site('type_avatar') == 'ui-avatars' ? 'selected' : ''; ?>
                                        value="ui-avatars">
                                        <?= __('Theo chữ cái đầu (UI Avatars)'); ?>
                                    </option>
                                    <option
                                        <?= $CMSNT->site('type_avatar') == 'default' ? 'selected' : ''; ?>
                                        value="default">
                                        <?= __('Mặc định (sử dụng ảnh trong Giao diện / Avatar)'); ?>
                                    </option>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <td><?= __('Thông báo liên kết Telegram'); ?>
                            </td>
                            <td>
                                <select class="form-control" name="is_show_telegram_reminder">
                                    <option
                                        <?= $CMSNT->site('is_show_telegram_reminder') == '0' ? 'selected' : ''; ?>
                                        value="0"><?= __('OFF'); ?>
                                    </option>
                                    <option
                                        <?= $CMSNT->site('is_show_telegram_reminder') == '1' ? 'selected' : ''; ?>
                                        value="1">
                                        <?= __('ON'); ?>
                                    </option>
                                </select>
                                <div class="form-text">
                                    <?= __('Hiển thị thông báo nhắc nhở User liên kết Telegram'); ?>
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <td><?= __('Hiển thị Slider'); ?>
                            </td>
                            <td>
                                <select class="form-control" name="is_show_slider">
                                    <option
                                        <?= $CMSNT->site('is_show_slider') == '0' ? 'selected' : ''; ?>
                                        value="0"><?= __('OFF'); ?>
                                    </option>
                                    <option
                                        <?= $CMSNT->site('is_show_slider') == '1' ? 'selected' : ''; ?>
                                        value="1">
                                        <?= __('ON'); ?>
                                    </option>
                                </select>
                                <div class="form-text">
                                    <?= __('Bật/Tắt hiển thị Slider trên trang chủ'); ?>
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <td><?= __('Hiển thị Banner'); ?>
                            </td>
                            <td>
                                <select class="form-control" name="is_show_banner">
                                    <option
                                        <?= $CMSNT->site('is_show_banner') == '0' ? 'selected' : ''; ?>
                                        value="0"><?= __('OFF'); ?>
                                    </option>
                                    <option
                                        <?= $CMSNT->site('is_show_banner') == '1' ? 'selected' : ''; ?>
                                        value="1">
                                        <?= __('ON'); ?>
                                    </option>
                                </select>
                                <div class="form-text">
                                    <?= __('Bật/Tắt hiển thị Banner trên trang chủ'); ?>
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <td><?= __('Hiển thị Sản phẩm đã xem'); ?>
                            </td>
                            <td>
                                <select class="form-control" name="is_show_recently_viewed">
                                    <option
                                        <?= $CMSNT->site('is_show_recently_viewed') == '0' ? 'selected' : ''; ?>
                                        value="0"><?= __('OFF'); ?>
                                    </option>
                                    <option
                                        <?= $CMSNT->site('is_show_recently_viewed') == '1' ? 'selected' : ''; ?>
                                        value="1">
                                        <?= __('ON'); ?>
                                    </option>
                                </select>
                                <div class="form-text">
                                    <?= __('Bật/Tắt hiển thị widget "Sản phẩm đã xem gần đây" trên trang chủ'); ?>
                                </div>
                            </td>
                        </tr>

                    </tbody>
                </table>
            </div>



            <div class="col-md-12">
                <div class="card border border-success-transparent">
                    <div class="card-header bg-success-transparent">
                        <div class="d-flex align-items-center">
                            <div>
                                <h6
                                    class="card-title mb-0 text-uppercase text-dark fw-semibold">
                                    <?= __('Tùy chỉnh Script/HTML'); ?>
                                </h6>
                                <small
                                    class="text-muted"><?= __('Cấu hình các script và HTML tùy chỉnh cho website'); ?></small>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- Header Script cho trang khách -->
                            <div class="col-lg-12 mb-4">
                                <div
                                    class="card border border-primary-transparent h-100">
                                    <div class="card-header bg-primary-transparent">
                                        <div class="d-flex align-items-center">
                                            <div>
                                                <h6
                                                    class="card-title mb-0 fw-semibold text-dark">
                                                    <?= __('Script/HTML Header trang khách'); ?>
                                                </h6>
                                                <small class="text-muted">
                                                    <?= __('Hiển thị trong thẻ &lt;head&gt; của trang khách'); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <textarea rows="8" name="javascript_header"
                                            id="javascript_header"
                                            class="form-control"
                                            placeholder="<?= __('Nhập script/HTML header trang khách...'); ?>"><?= $CMSNT->site('javascript_header'); ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer Script cho trang khách -->
                            <div class="col-lg-12 mb-4">
                                <div
                                    class="card border border-warning-transparent h-100">
                                    <div
                                        class="card-header bg-warning-transparent d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6
                                                class="card-title mb-0 fw-semibold text-dark">
                                                <?= __('Script/HTML Footer trang khách'); ?>
                                            </h6>
                                            <small class="text-muted">
                                                <?= __('Hiển thị cuối trang khách trước thẻ &lt;/body&gt;'); ?>
                                            </small>
                                        </div>
                                        <button type="button"
                                            class="btn btn-sm btn-dark btn-wave text-white"
                                            onclick="openFooterScriptAIModal()"
                                            title="Tạo script bằng AI">
                                            <i
                                                class="ri-magic-line me-1"></i><?= __('Generated by AI'); ?>
                                        </button>
                                    </div>
                                    <div class="card-body">
                                        <textarea rows="8" name="javascript_footer"
                                            id="javascript_footer"
                                            class="form-control"
                                            placeholder="<?= __('Nhập script/HTML footer trang khách...'); ?>"><?= $CMSNT->site('javascript_footer'); ?></textarea>
                                    </div>
                                </div>
                            </div>



                            <!-- Footer Script cho trang admin -->
                            <div class="col-lg-12 mb-4">
                                <div
                                    class="card border border-danger-transparent h-100">
                                    <div class="card-header bg-danger-transparent">
                                        <div class="d-flex align-items-center">
                                            <div>
                                                <h6
                                                    class="card-title mb-0 fw-semibold text-dark">
                                                    <?= __('Script/HTML Footer trang quản trị'); ?>
                                                </h6>
                                                <small class="text-muted">
                                                    <?= __('Hiển thị cuối trang admin trước thẻ &lt;/body&gt;'); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <textarea rows="8"
                                            name="script_footer_admin"
                                            id="script_footer_admin"
                                            class="form-control"
                                            placeholder="<?= __('Nhập script/HTML footer trang admin...'); ?>"><?= $CMSNT->site('script_footer_admin'); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Thông tin hỗ trợ -->
                        <div class="row">
                            <div class="col-12">
                                <div
                                    class="alert alert-light border border-light-subtle">
                                    <div class="d-flex align-items-start">
                                        <i
                                            class="ri-lightbulb-line text-warning me-2 fs-16 mt-1"></i>
                                        <div>
                                            <h6 class="alert-heading mb-2">
                                                <i
                                                    class="ri-information-line me-1"></i>
                                                <?= __('Lưu ý quan trọng'); ?>
                                            </h6>
                                            <ul class="mb-0 small">
                                                <li><?= __('Header Script: Dành cho Google Analytics, Facebook Pixel, Meta tags...'); ?>
                                                </li>
                                                <li><?= __('Footer Script: Dành cho chat plugin, tracking, popup...'); ?>
                                                </li>
                                                <li><?= __('Footer Card: Script hiển thị trong phần footer card của trang khách'); ?>
                                                </li>
                                                <li><?= __('Admin Footer: Script chỉ hiển thị trong trang quản trị'); ?>
                                                </li>
                                                <li class="text-danger">
                                                    <?= __('Cẩn thận với script có thể ảnh hưởng đến bảo mật website'); ?>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <button type="submit" name="SaveSettings"
            class="btn btn-primary w-100 mb-3">
            <i class="fa fa-fw fa-save me-1"></i> <?= __('Save'); ?>
        </button>
    </form>
</div>

<!-- Modal AI Generate Footer Script -->
<div class="modal fade" id="footerScriptAIModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg"
            style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 20px;">
            <!-- Header với gradient -->
            <div class="modal-header border-0 text-white pb-2">
                <div class="d-flex align-items-center">
                    <div class="me-3 p-2 rounded-circle" style="background: rgba(255,255,255,0.2);">
                        <i class="ri-robot-line" style="font-size: 24px;"></i>
                    </div>
                    <div>
                        <h5 class="mb-0 fw-bold text-white"><?= __('AI Script Generator'); ?></h5>
                        <small class="opacity-75"><?= __('Tạo script/HTML thông minh'); ?></small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <!-- Body với background trắng -->
            <div class="modal-body bg-white m-3 rounded-4 shadow-sm">
                <div class="text-center mb-3">
                    <div class="d-inline-flex align-items-center justify-content-center mb-2"
                        style="width: 60px; height: 60px; background: linear-gradient(45deg, #667eea, #764ba2); border-radius: 50%;">
                        <i class="ri-magic-line text-white" style="font-size: 24px;"></i>
                    </div>
                    <p class="text-muted mb-0"><?= __('Mô tả script/hiệu ứng bạn muốn tạo'); ?></p>
                </div>

                <textarea class="form-control border-2" id="footerScriptDescription" rows="3"
                    placeholder="<?= __('VD: Tạo hiệu ứng tuyết rơi, chat popup, back to top button, loading animation...'); ?>"
                    style="border-color: #667eea; border-radius: 12px; resize: none;"></textarea>

                <div class="mt-2">
                    <small class="text-muted">
                        <i class="ri-lightbulb-line me-1"></i>
                        <?= __('Gợi ý: Hãy mô tả rõ ràng hiệu ứng, màu sắc, vị trí hiển thị để AI tạo script chính xác nhất'); ?>
                    </small>
                </div>
            </div>

            <!-- Footer với nút gradient -->
            <div class="modal-footer border-0 pt-0 px-4 pb-4">
                <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal" style="border-radius: 25px;">
                    <i class="ri-close-line me-1"></i><?= __('Hủy'); ?>
                </button>
                <button type="button" class="btn text-white fw-bold px-4" onclick="generateFooterScript()"
                    id="generateFooterBtn"
                    style="background: linear-gradient(45deg, #667eea, #764ba2); border: none; border-radius: 25px; min-width: 140px;">
                    <i class="ri-magic-line me-1"></i><?= __('Tạo ngay'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    // Khởi tạo CodeMirror cho các textarea script/HTML
    setTimeout(function() {
        // CodeMirror configuration
        var codeMirrorConfig = {
            lineNumbers: true,
            mode: "htmlmixed",
            theme: "monokai",
            matchBrackets: true,
            autoCloseTags: true,
            lineWrapping: true,
            foldGutter: true,
            gutters: ["CodeMirror-linenumbers", "CodeMirror-foldgutter"],
            extraKeys: {
                "Ctrl-Space": "autocomplete",
                "F11": function(cm) {
                    cm.setOption("fullScreen", !cm.getOption("fullScreen"));
                },
                "Esc": function(cm) {
                    if (cm.getOption("fullScreen")) cm.setOption("fullScreen", false);
                }
            }
        };

        // Tạo biến global để lưu trữ CodeMirror instances
        window.codeMirrorInstances = {};

        // Khởi tạo cho tất cả các textarea script
        ['javascript_header', 'javascript_footer', 'script_footer_admin'].forEach(function(id) {
            if (document.getElementById(id)) {
                window.codeMirrorInstances[id] = CodeMirror.fromTextArea(document.getElementById(id),
                    codeMirrorConfig);
            }
        });
    }, 100);

    // Xử lý modal AI cho Footer Script
    window.openFooterScriptAIModal = function() {
        document.getElementById('footerScriptDescription').value = '';
        var modal = new bootstrap.Modal(document.getElementById('footerScriptAIModal'));
        modal.show();
    };

    // Xử lý tạo script footer bằng AI
    window.generateFooterScript = function() {
        const description = document.getElementById('footerScriptDescription').value.trim();
        const generateBtn = document.getElementById('generateFooterBtn');

        if (description === '') {
            Swal.fire({
                icon: 'warning',
                title: '<?= __('Thiếu thông tin'); ?>',
                text: '<?= __('Vui lòng nhập mô tả về script cần tạo'); ?>'
            });
            return;
        }

        // Disable button và show loading
        generateBtn.disabled = true;
        generateBtn.innerHTML =
            '<i class="me-1 spinner-border spinner-border-sm"></i><?= __('Đang tạo...'); ?>';

        $.ajax({
            url: '<?= base_url('ajaxs/admin/ai.php'); ?>',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'generateFooterScript',
                description: description
            },
            success: function(response) {
                console.log('AI Response:', response);
                if (response.success) {
                    if (window.codeMirrorInstances && window.codeMirrorInstances['javascript_footer']) {
                        const codeMirrorInstance = window.codeMirrorInstances['javascript_footer'];
                        const currentContent = codeMirrorInstance.getValue();
                        const newContent = currentContent + (currentContent ? '\n\n' : '') + response.content;
                        codeMirrorInstance.setValue(newContent);
                        codeMirrorInstance.focus();
                        codeMirrorInstance.setCursor(codeMirrorInstance.lineCount(), 0);
                    } else {
                        const footerTextarea = document.getElementById('javascript_footer');
                        if (footerTextarea) {
                            const currentContent = footerTextarea.value;
                            const newContent = currentContent + (currentContent ? '\n\n' : '') + response.content;
                            footerTextarea.value = newContent;
                            footerTextarea.dispatchEvent(new Event('change'));
                        }
                    }

                    var modal = bootstrap.Modal.getInstance(document.getElementById('footerScriptAIModal'));
                    if (modal) {
                        modal.hide();
                    }

                    Swal.fire({
                        icon: 'success',
                        title: '<?= __('Thành công'); ?>',
                        text: '<?= __('Đã tạo script footer bằng AI'); ?>',
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '<?= __('Có lỗi xảy ra'); ?>',
                        text: response.message || '<?= __('Không thể tạo script'); ?>'
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: '<?= __('Lỗi kết nối'); ?>',
                    text: '<?= __('Không thể kết nối đến server AI'); ?>'
                });
            },
            complete: function() {
                generateBtn.disabled = false;
                generateBtn.innerHTML = '<i class="ri-magic-line me-1"></i><?= __('Tạo ngay'); ?>';
            }
        });
    };
</script>