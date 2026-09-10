<?php if (!defined('IN_SITE')) {
    die('The Request Not Found');
}
$body = [
    'title' => 'Settings',
    'desc'   => 'CMSNT Panel',
    'keyword' => 'cmsnt, CMSNT, cmsnt.co,'
];
$body['header'] = '
<!-- ckeditor -->
<script src="' . BASE_URL('public/ckeditor/ckeditor.js') . '"></script>
<!-- Thêm CSS của CodeMirror -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/codemirror.min.css">

<!-- Thêm JavaScript của CodeMirror -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/codemirror.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/theme/monokai.min.css">
<!-- Mode HTML mixed (hỗ trợ HTML, CSS và JS) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/htmlmixed/htmlmixed.min.js"></script>
<!-- Mode cho CSS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/css/css.min.js"></script>
<!-- Mode cho JavaScript -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/javascript/javascript.min.js"></script>
<!-- Mode cho XML (cần cho HTML) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/xml/xml.min.js"></script>

';
$body['footer'] = '
 
';
require_once(__DIR__ . '/../../models/is_admin.php');
require_once(__DIR__ . '/header.php');
require_once(__DIR__ . '/sidebar.php');
require_once(__DIR__ . '/nav.php');
if (checkPermission($getUser['admin'], 'edit_setting') != true) {
    die('<script type="text/javascript">if(!alert("Bạn không có quyền sử dụng tính năng này")){window.history.back();}</script>');
}
if (isset($_POST['SaveSettings'])) {
    if ($CMSNT->site('status_demo') != 0) {
        die('<script type="text/javascript">if(!alert("' . __('This function cannot be used because this is a demo site') . '")){window.history.back().location.reload();}</script>');
    }
    $CMSNT->insert("logs", [
        'user_id'       => $getUser['id'],
        'ip'            => myip(),
        'device'        => getUserAgent(),
        'createdate'    => gettime(),
        'action'        => __('Thay đổi thông tin trong trang cài đặt')
    ]);



    foreach ($_POST as $key => $value) {

        if ($key == 'captcha_modules') {
            $captcha_modules_value = implode(',', $value);
            $CMSNT->update("settings", array(
                'value' => $captcha_modules_value
            ), " `name` = 'captcha_modules' ");
            continue;
        }

        $CMSNT->update("settings", array(
            'value' => $value
        ), " `name` = '$key' ");
    }
    /** NOTE ACTION */
    $my_text = $CMSNT->site('noti_action');
    $my_text = str_replace('{domain}', $_SERVER['SERVER_NAME'], $my_text);
    $my_text = str_replace('{username}', $getUser['username'], $my_text);
    $my_text = str_replace('{action}', __('Thay đổi thông tin trong trang cài đặt'), $my_text);
    $my_text = str_replace('{ip}', myip(), $my_text);
    $my_text = str_replace('{time}', gettime(), $my_text);
    sendMessAdmin($my_text);

    admin_msg_success("Lưu thành công!", "", 1000);
}
?>


<style>
    /* Ẩn pseudo-element :before của card title */
    .card.custom-card .card-header .card-title:before {
        display: none !important;
    }

    /* Hoặc có thể dùng cách này */
    .card.custom-card .card-header .card-title:before {
        content: none !important;
    }
</style>


<div class="main-content app-content">
    <div class="container-fluid">
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0"><i class="fa-solid fa-gear"></i> Cài đặt</h1>
        </div>
        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-xl-2">
                                <nav class="nav nav-tabs flex-column nav-style-5 mb-3" role="tablist">
                                    <a class="nav-link active" data-bs-toggle="tab" role="tab" aria-current="page"
                                        href="#cai-dat-chung" aria-selected="false"><i
                                            class="bx bx-cog me-2 align-middle d-inline-block"></i>Cài đặt chung</a>
                                    <a class="nav-link" data-bs-toggle="tab" role="tab" aria-current="page"
                                        href="#ket-noi" aria-selected="false"><i
                                            class="bx bx-plug me-2 align-middle d-inline-block"></i>Kết nối</a>
                                    <a class="nav-link" data-bs-toggle="tab" role="tab" aria-current="page"
                                        href="#telegram-template" aria-selected="true"><i
                                            class="fa-brands fa-telegram me-2 align-middle d-inline-block"></i>Telegram
                                        Template</a>
                                    <a class="nav-link" data-bs-toggle="tab" role="tab" aria-current="page"
                                        href="#mail-template" aria-selected="true"><i
                                            class="fa-solid fa-envelope me-2 align-middle d-inline-block"></i>Mail
                                        Template</a>
                                    <a class="nav-link" data-bs-toggle="tab" role="tab" aria-current="page"
                                        href="#security" aria-selected="false"><i
                                            class="fa-solid fa-shield-halved me-2 align-middle d-inline-block"></i>Bảo mật</a>
                                    <a class="nav-link" data-bs-toggle="tab" role="tab" aria-current="page"
                                        href="#hien-thi-san-pham" aria-selected="false"><i
                                            class="bx bx-desktop me-2 align-middle d-inline-block"></i>Hiển thị sản
                                        phẩm</a>
                                    <a class="nav-link" data-bs-toggle="tab" role="tab" aria-current="page"
                                        href="#giao-dich-gan-day" aria-selected="false"><i
                                            class="fa-solid fa-clock-rotate-left me-2 align-middle d-inline-block"></i>Giao
                                        dịch gần đây</a>
                                    <a class="nav-link" data-bs-toggle="tab" role="tab" aria-current="page"
                                        href="#widget" aria-selected="false"><i
                                            class="fa-brands fa-themeco me-2 align-middle d-inline-block"></i>Widget</a>
                                    <a class="nav-link" data-bs-toggle="tab" role="tab" aria-current="page"
                                        href="#addons" aria-selected="false">
                                        <i class="fa-solid fa-puzzle-piece me-2 align-middle d-inline-block"></i>Addons
                                    </a>
                                    <a class="nav-link" data-bs-toggle="tab" role="tab" aria-current="page"
                                        href="#cron-jobs" aria-selected="false">
                                        <i class="fas fa-clock me-2 align-middle d-inline-block"></i>Cron Jobs
                                    </a>

                                </nav>
                            </div>
                            <div class="col-xl-10">
                                <div class="tab-content">
                                    <div class="tab-pane text-muted show active" id="cai-dat-chung" role="tabpanel">
                                        <h4><?= __('Cài đặt chung'); ?></h4>
                                        <form action="" method="POST">
                                            <div class="row push mb-3">
                                                <div class="col-md-6">
                                                    <table class="table table-bordered table-striped table-hover">
                                                        <tbody>
                                                            <tr>
                                                                <td><?= __('Allowed Domains'); ?></td>
                                                                <td>
                                                                    <input type="text" name="domains"
                                                                        value="<?= $CMSNT->site('domains'); ?>"
                                                                        class="form-control">
                                                                    <small>Không thay đổi nếu không hiểu rõ, phí khôi
                                                                        phục 100.000đ 1 lần</small>
                                                                </td>
                                                            </tr>
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
                                                                        <span class="input-group-text"><?= $CMSNT->site('icon_email'); ?></span>
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
                                                                        <span class="input-group-text"><?= $CMSNT->site('icon_hotline'); ?></span>
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
                                                                        <span class="input-group-text"><?= $CMSNT->site('icon_address'); ?></span>
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
                                                                <td>ON/OFF menu Công cụ</td>
                                                                <td>
                                                                    <select class="form-control"
                                                                        name="status_menu_tools">
                                                                        <option
                                                                            <?= $CMSNT->site('status_menu_tools') == 1 ? 'selected' : ''; ?>
                                                                            value="1">ON
                                                                        </option>
                                                                        <option
                                                                            <?= $CMSNT->site('status_menu_tools') == 0 ? 'selected' : ''; ?>
                                                                            value="0">
                                                                            OFF
                                                                        </option>
                                                                    </select>
                                                                    <img src="<?= base_url('mod/img/demo-menu-tools.webp'); ?>"
                                                                        width="500px">
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td><?= __('Màu chủ đạo'); ?></td>
                                                                <td>
                                                                    <input type="color"
                                                                        class="form-control form-control-color border-0"
                                                                        id="exampleColorInput" name="theme_color"
                                                                        value="<?= $CMSNT->site('theme_color'); ?>"
                                                                        title="Choose your color">
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td><?= __('Màu phụ'); ?></td>
                                                                <td>
                                                                    <input type="color"
                                                                        class="form-control form-control-color border-0"
                                                                        id="exampleColorInput" name="theme_color1"
                                                                        value="<?= $CMSNT->site('theme_color1'); ?>"
                                                                        title="Choose your color">

                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>Font Family</td>
                                                                <td>
                                                                    <input type="text" name="font_family"
                                                                        value="<?= $CMSNT->site('font_family'); ?>"
                                                                        class="form-control">
                                                                    <small><a class="text-primary"
                                                                            href="https://help.cmsnt.co/huong-dan/huong-dan-thay-doi-font-chu-cho-website/"
                                                                            target="_blank">Hướng dẫn</a></small>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <div class="col-md-6">
                                                    <table class="table table-bordered table-striped table-hover">
                                                        <tbody>
                                                            <tr>
                                                                <td>Trạng thái website</td>
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
                                                                    <div class="form-text">Chọn OFF nếu bạn muốn bật chế độ bảo
                                                                        trì.</div>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>Cập nhật phiên bản tự động</td>
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
                                                                    <div class="form-text">Hệ thống sẽ tự động cập nhật khi có phiên bản
                                                                        mới nếu bạn chọn ON.</div>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>ON/OFF Debug Auto Bank</td>
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
                                                                    <div class="form-text">Không bật ON khi chưa được CMSNT yêu
                                                                        cầu.</div>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>ON/OFF Debug API Suppliers</td>
                                                                <td>
                                                                    <select class="form-control"
                                                                        name="debug_api_suppliers">
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
                                                                    <div class="form-text">OFF để ẩn hiển thị tên sản phẩm trong link
                                                                        cron tránh người khác biết bạn đấu API.</div>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>ON/OFF Tài liệu API</td>
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
                                                                    <div class="form-text">Hệ thống sẽ ẩn menu tài liệu API nếu bạn chọn
                                                                        OFF</div>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>ON/OFF menu Blogs</td>
                                                                <td>
                                                                    <select class="form-control" name="blog_status">
                                                                        <option
                                                                            <?= $CMSNT->site('blog_status') == 1 ? 'selected' : ''; ?>
                                                                            value="1">ON
                                                                        </option>
                                                                        <option
                                                                            <?= $CMSNT->site('blog_status') == 0 ? 'selected' : ''; ?>
                                                                            value="0">
                                                                            OFF
                                                                        </option>
                                                                    </select>
                                                                    <div class="form-text">Hệ thống sẽ ẩn menu Blogs nếu bạn chọn
                                                                        OFF, các bài viết vẫn hiển thị trên google tìm
                                                                        kiếm.</div>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>ON/OFF Cộng tiền cho người bán</td>
                                                                <td>
                                                                    <select class="form-control"
                                                                        name="cong_tien_nguoi_ban">
                                                                        <option
                                                                            <?= $CMSNT->site('cong_tien_nguoi_ban') == 1 ? 'selected' : ''; ?>
                                                                            value="1">ON
                                                                        </option>
                                                                        <option
                                                                            <?= $CMSNT->site('cong_tien_nguoi_ban') == 0 ? 'selected' : ''; ?>
                                                                            value="0">
                                                                            OFF
                                                                        </option>
                                                                    </select>
                                                                    <div class="form-text">Nếu bạn ON chức năng này, admin nào tạo ra
                                                                        sản phẩm sẽ được cộng tiền khi có khách mua hàng
                                                                        sản phẩm đó.</div>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>Thời gian mua hàng cách nhau</td>
                                                                <td>
                                                                    <div class="input-group">
                                                                        <input name="thoi_gian_mua_cach_nhau"
                                                                            type="text" class="form-control"
                                                                            value="<?= $CMSNT->site('thoi_gian_mua_cach_nhau'); ?>"
                                                                            required>
                                                                        <span class="input-group-text">
                                                                            Giây
                                                                        </span>
                                                                    </div>
                                                                    <div class="form-text">Nếu bạn không muốn giới hạn thời gian mua
                                                                        hàng liên tục thì nhập số 0</div>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>Thuế VAT nếu có</td>
                                                                <td>
                                                                    <div class="input-group">
                                                                        <input name="tax_vat" type="text"
                                                                            class="form-control"
                                                                            value="<?= $CMSNT->site('tax_vat'); ?>"
                                                                            required>
                                                                        <span class="input-group-text">
                                                                            %
                                                                        </span>
                                                                    </div>
                                                                    <div class="form-text">Nếu bạn muốn thu thuế VAT thì nhập vào đây, nếu không có thì để trống.<br>
                                                                        Ví dụ bạn nhập 10% thì hệ thống sẽ tự động thêm 10% vào tổng số tiền thanh toán sau khi trừ khuyến mãi.</div>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>ON/OFF bắt buộc nhập thông tin để xuất VAT</td>
                                                                <td>
                                                                    <select class="form-control"
                                                                        name="popup_vat">
                                                                        <option
                                                                            <?= $CMSNT->site('popup_vat') == 0 ? 'selected' : ''; ?>
                                                                            value="0">
                                                                            OFF
                                                                        </option>
                                                                        <option
                                                                            <?= $CMSNT->site('popup_vat') == 1 ? 'selected' : ''; ?>
                                                                            value="1">ON
                                                                        </option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>ON/OFF Tự động hoàn tiền cho User khi mua hàng báo lỗi "<?= __('Mất kết nối đến kho hàng'); ?>"</td>
                                                                <td>
                                                                    <select class="form-control"
                                                                        name="auto_refund_order_failed_api">
                                                                        <option
                                                                            <?= $CMSNT->site('auto_refund_order_failed_api') == 0 ? 'selected' : ''; ?>
                                                                            value="0">
                                                                            OFF
                                                                        </option>
                                                                        <option
                                                                            <?= $CMSNT->site('auto_refund_order_failed_api') == 1 ? 'selected' : ''; ?>
                                                                            value="1">ON
                                                                        </option>
                                                                    </select>
                                                                    <div class="form-text">Nếu bạn ON chức năng này, những đơn hàng mà bạn đấu API, nếu báo lỗi "<strong><?= __('Mất kết nối đến kho hàng'); ?></strong>" thì hệ thống sẽ tự động hoàn tiền cho khách hàng.<br>
                                                                        <strong style="color: red;">Lưu ý:</strong> nếu ON đồng nghĩa với việc bạn chấp nhận rủi ro có thể xảy ra nếu API bạn kết nối có lỗi nghiêm trọng. Nếu OFF bạn sẽ cần hoàn lại tiền thủ công cho đơn hàng gặp lỗi "<?= __('Mất kết nối đến kho hàng'); ?>".
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>Limit Check live</td>
                                                                <td>
                                                                    <div class="input-group">
                                                                        <input name="limit_check_live_clone" type="number"
                                                                            class="form-control"
                                                                            value="<?= $CMSNT->site('limit_check_live_clone'); ?>"
                                                                            required>
                                                                        <span class="input-group-text">
                                                                            tài khoản
                                                                        </span>
                                                                    </div>
                                                                    <div class="form-text">Số lượng tài khoản check live mỗi lần cron chạy.</div>
                                                                </td>
                                                            </tr>
                                                            <tr></tr>
                                                            <td>ON/OFF Lock rows khi mua hàng</td>
                                                            <td>
                                                                <select class="form-control" name="isForUpdateBuy">
                                                                    <option value="0" <?= $CMSNT->site('isForUpdateBuy') == 0 ? 'selected' : ''; ?>>OFF</option>
                                                                    <option value="1" <?= $CMSNT->site('isForUpdateBuy') == 1 ? 'selected' : ''; ?>>ON</option>
                                                                </select>
                                                                <div class="form-text">Nếu bạn ON chức năng này, hệ thống sẽ lock rows khi khách hàng mua hàng để tránh trường hợp nhiều người mua cùng lúc gây lỗi, tuy nhiên nó sẽ tiêu tốn nhiều tài nguyên để xử lý.</div>
                                                            </td>
                                                            </tr>
                                                            <tr>
                                                                <td><?= __('ON/OFF Menu Thông tin xuất hóa đơn VAT'); ?>
                                                                </td>
                                                                <td>
                                                                    <select class="form-control"
                                                                        name="sidebar_vat_invoice_status">
                                                                        <option
                                                                            <?= $CMSNT->site('sidebar_vat_invoice_status') == 1 ? 'selected' : ''; ?>
                                                                            value="1"><?= __('Hiển thị'); ?>
                                                                        </option>
                                                                        <option
                                                                            <?= $CMSNT->site('sidebar_vat_invoice_status') == 0 ? 'selected' : ''; ?>
                                                                            value="0">
                                                                            <?= __('Ẩn'); ?>
                                                                        </option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <div class="col-md-12">
                                                    <table class="table table-bordered table-striped table-hover">
                                                        <tbody>
                                                            <tr>
                                                                <td><?= __('Thông báo phía trên góc trái màn hình'); ?>
                                                                </td>
                                                                <td>
                                                                    <textarea class="form-control"
                                                                        name="notice_top_left"><?= $CMSNT->site('notice_top_left'); ?></textarea>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td><?= __('Thông báo ngoài trang chủ'); ?></td>
                                                                <td>
                                                                    <textarea id="notice_home"
                                                                        name="notice_home"><?= $CMSNT->site('notice_home'); ?></textarea>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td><?= __('Thông báo trang lịch sử đơn hàng'); ?></td>
                                                                <td>
                                                                    <textarea id="notice_orders"
                                                                        name="notice_orders"><?= $CMSNT->site('notice_orders'); ?></textarea>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>ON/OFF Thông báo nổi</td>
                                                                <td>
                                                                    <select class="form-control" name="popup_status">
                                                                        <option
                                                                            <?= $CMSNT->site('popup_status') == 1 ? 'selected' : ''; ?>
                                                                            value="1">ON
                                                                        </option>
                                                                        <option
                                                                            <?= $CMSNT->site('popup_status') == 0 ? 'selected' : ''; ?>
                                                                            value="0">
                                                                            OFF
                                                                        </option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td><?= __('Thông báo nổi ngoài trang chủ'); ?></td>
                                                                <td>
                                                                    <textarea id="popup_noti"
                                                                        name="popup_noti"><?= $CMSNT->site('popup_noti'); ?></textarea>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>ON/OFF Yêu cầu đọc chính sách trước khi đăng ký</td>
                                                                <td>
                                                                    <select class="form-control" name="isConfirmPolicyRegister">
                                                                        <option
                                                                            <?= $CMSNT->site('isConfirmPolicyRegister') == 1 ? 'selected' : ''; ?>
                                                                            value="1">ON
                                                                        </option>
                                                                        <option
                                                                            <?= $CMSNT->site('isConfirmPolicyRegister') == 0 ? 'selected' : ''; ?>
                                                                            value="0">
                                                                            OFF
                                                                        </option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td><?= __('Nội dung chính sách khi đăng ký'); ?></td>
                                                                <td>
                                                                    <textarea id="policy_register"
                                                                        name="policy_register"><?= $CMSNT->site('policy_register'); ?></textarea>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td><?= __('Nội dung trang liên hệ'); ?></td>
                                                                <td>
                                                                    <textarea id="page_contact"
                                                                        name="page_contact"><?= $CMSNT->site('page_contact'); ?></textarea>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td><?= __('Nội dung trang chính sách'); ?></td>
                                                                <td>
                                                                    <textarea id="page_policy"
                                                                        name="page_policy"><?= $CMSNT->site('page_policy'); ?></textarea>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td><?= __('Nội dung trang FAQ'); ?></td>
                                                                <td>
                                                                    <textarea id="page_faq"
                                                                        name="page_faq"><?= $CMSNT->site('page_faq'); ?></textarea>
                                                                </td>
                                                            </tr>

                                                            <tr>
                                                                <td><?= __('Script/HTML Header trang khách'); ?></td>
                                                                <td>
                                                                    <textarea rows="5" name="javascript_header"
                                                                        id="javascript_header"
                                                                        class="form-control"><?= $CMSNT->site('javascript_header'); ?></textarea>
                                                                    <script>
                                                                        var editor = CodeMirror.fromTextArea(document
                                                                            .getElementById("javascript_header"), {
                                                                                lineNumbers: true, // Hiển thị số dòng
                                                                                mode: "htmlmixed", // Ngôn ngữ lập trình
                                                                                theme: "monokai", // Giao diện (có thể chọn các giao diện khác)
                                                                                matchBrackets: true // Hỗ trợ đánh dấu các cặp ngoặc
                                                                            });
                                                                    </script>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td><?= __('Script/HTML Footer trang khách'); ?></td>
                                                                <td>
                                                                    <textarea rows="5" name="javascript_footer"
                                                                        id="javascript_footer"
                                                                        class="form-control"><?= $CMSNT->site('javascript_footer'); ?></textarea>
                                                                    <script>
                                                                        var editor = CodeMirror.fromTextArea(document
                                                                            .getElementById("javascript_footer"), {
                                                                                lineNumbers: true, // Hiển thị số dòng
                                                                                mode: "htmlmixed", // Ngôn ngữ lập trình
                                                                                theme: "monokai", // Giao diện (có thể chọn các giao diện khác)
                                                                                matchBrackets: true // Hỗ trợ đánh dấu các cặp ngoặc
                                                                            });
                                                                    </script>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td><?= __('Script/HTML Footer Card trang khách'); ?></td>
                                                                <td>
                                                                    <textarea rows="5" name="footer_card"
                                                                        id="footer_card"
                                                                        class="form-control"><?= $CMSNT->site('footer_card'); ?></textarea>
                                                                    <script>
                                                                        var editor = CodeMirror.fromTextArea(document
                                                                            .getElementById("footer_card"), {
                                                                                lineNumbers: true, // Hiển thị số dòng
                                                                                mode: "htmlmixed", // Ngôn ngữ lập trình
                                                                                theme: "monokai", // Giao diện (có thể chọn các giao diện khác)
                                                                                matchBrackets: true // Hỗ trợ đánh dấu các cặp ngoặc
                                                                            });
                                                                    </script>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td><?= __('Script/HTML Footer trang quản trị'); ?></td>
                                                                <td>
                                                                    <textarea rows="5" name="script_footer_admin"
                                                                        id="script_footer_admin"
                                                                        class="form-control"><?= $CMSNT->site('script_footer_admin'); ?></textarea>
                                                                    <script>
                                                                        var editor = CodeMirror.fromTextArea(document
                                                                            .getElementById("script_footer_admin"), {
                                                                                lineNumbers: true, // Hiển thị số dòng
                                                                                mode: "htmlmixed", // Ngôn ngữ lập trình
                                                                                theme: "monokai", // Giao diện (có thể chọn các giao diện khác)
                                                                                matchBrackets: true // Hỗ trợ đánh dấu các cặp ngoặc
                                                                            });
                                                                    </script>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            <button type="submit" name="SaveSettings"
                                                class="btn btn-primary w-100 mb-3">
                                                <i class="fa fa-fw fa-save me-1"></i> <?= __('Save'); ?>
                                            </button>
                                        </form>
                                    </div>
                                    <div class="tab-pane text-muted" id="ket-noi" role="tabpanel">
                                        <h4><?= __('Kết nối'); ?></h4>
                                        <form action="" method="POST">
                                            <div class="row push mb-3">
                                                <div class="col-md-6">
                                                    <table class="table table-bordered table-striped table-hover mb-3">
                                                        <thead class="table-dark text-center">
                                                            <tr>
                                                                <th colspan="2">
                                                                    <img src="<?= BASE_URL('assets/img/icon-smtp.png'); ?>"
                                                                        width="20px" class="me-1">
                                                                    <?= __('SMTP'); ?>
                                                                </th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <!-- Bật/Tắt SMTP -->
                                                            <tr>
                                                                <td>
                                                                    <i class="fa fa-toggle-on text-success"></i>
                                                                    <?= __('SMTP Mail'); ?>
                                                                </td>
                                                                <td>
                                                                    <select class="form-control" name="smtp_status">
                                                                        <option value="1"
                                                                            <?= $CMSNT->site('smtp_status') == 1 ? 'selected' : ''; ?>>
                                                                            ON
                                                                        </option>
                                                                        <option value="0"
                                                                            <?= $CMSNT->site('smtp_status') == 0 ? 'selected' : ''; ?>>
                                                                            OFF
                                                                        </option>
                                                                    </select>
                                                                </td>
                                                            </tr>

                                                            <!-- SMTP Host -->
                                                            <tr>
                                                                <td>
                                                                    <i class="fas fa-server text-primary"></i>
                                                                    <?= __('SMTP Host'); ?>
                                                                </td>
                                                                <td>
                                                                    <input type="text" name="smtp_host"
                                                                        class="form-control"
                                                                        placeholder="<?= __('VD: smtp.gmail.com'); ?>"
                                                                        value="<?= $CMSNT->site('smtp_host'); ?>">
                                                                </td>
                                                            </tr>

                                                            <!-- SMTP Encryption -->
                                                            <tr>
                                                                <td>
                                                                    <i class="fas fa-shield-alt text-warning"></i>
                                                                    <?= __('SMTP Encryption'); ?>
                                                                </td>
                                                                <td>
                                                                    <input type="text" name="smtp_encryption"
                                                                        class="form-control"
                                                                        placeholder="<?= __('VD: ssl/tls'); ?>"
                                                                        value="<?= $CMSNT->site('smtp_encryption'); ?>">
                                                                </td>
                                                            </tr>

                                                            <!-- SMTP Port -->
                                                            <tr>
                                                                <td>
                                                                    <i class="fas fa-network-wired text-info"></i>
                                                                    <?= __('SMTP Port'); ?>
                                                                </td>
                                                                <td>
                                                                    <input type="text" name="smtp_port"
                                                                        class="form-control"
                                                                        placeholder="<?= __('VD: 465, 587'); ?>"
                                                                        value="<?= $CMSNT->site('smtp_port'); ?>">
                                                                </td>
                                                            </tr>

                                                            <!-- SMTP Email -->
                                                            <tr>
                                                                <td>
                                                                    <i class="fa fa-envelope text-danger"></i>
                                                                    <?= __('SMTP Email'); ?>
                                                                </td>
                                                                <td>
                                                                    <input type="text" name="smtp_email"
                                                                        class="form-control"
                                                                        placeholder="<?= __('VD: yourmail@gmail.com'); ?>"
                                                                        value="<?= $CMSNT->site('smtp_email'); ?>">
                                                                </td>
                                                            </tr>

                                                            <!-- SMTP Password -->
                                                            <tr>
                                                                <td>
                                                                    <i class="fas fa-key text-secondary"></i>
                                                                    <?= __('SMTP Password'); ?>
                                                                </td>
                                                                <td>
                                                                    <input type="text" name="smtp_password"
                                                                        class="form-control"
                                                                        placeholder="<?= __('Nhập mật khẩu SMTP...'); ?>"
                                                                        value="<?= $CMSNT->site('smtp_password'); ?>">
                                                                    <small class="text-muted">

                                                                        <?= __('Hướng dẫn tích hợp SMTP Gmail miễn phí'); ?>
                                                                        tại <a
                                                                            href="https://help.cmsnt.co/huong-dan/huong-dan-cau-hinh-smtp-vao-website-shopclone7/"
                                                                            target="_blank"
                                                                            class="text-primary">đây</a>, hoặc sử dụng
                                                                        Email theo tên miền tại <a
                                                                            href="https://ntlink.co/TMtoW"
                                                                            target="_blank"
                                                                            class="text-primary">đây</a>.

                                                                    </small>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>

                                                    <table class="mb-3 table table-bordered table-striped table-hover">
                                                        <thead class="table-dark">
                                                            <tr>
                                                                <th colspan="2" class="text-center">
                                                                    <img src="<?= BASE_URL('assets/img/icon-Google-Analytics.png'); ?>"
                                                                        width="20px"> <?= __('Google Analytics'); ?>
                                                                </th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr>
                                                                <td><?= __('Trạng thái'); ?></td>
                                                                <td>
                                                                    <select class="form-control"
                                                                        name="google_analytics_status">
                                                                        <option
                                                                            <?= $CMSNT->site('google_analytics_status') == 1 ? 'selected' : ''; ?>
                                                                            value="1">ON
                                                                        </option>
                                                                        <option
                                                                            <?= $CMSNT->site('google_analytics_status') == 0 ? 'selected' : ''; ?>
                                                                            value="0">
                                                                            OFF
                                                                        </option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td><?= __('ID'); ?></td>
                                                                <td>
                                                                    <input type="text" name="google_analytics_id"
                                                                        placeholder="VD: G-XXXXXXXX"
                                                                        value="<?= $CMSNT->site('google_analytics_id'); ?>"
                                                                        class="form-control">
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                    <table class="mb-3 table table-bordered table-striped table-hover">
                                                        <thead class="table-dark">
                                                            <tr>
                                                                <th colspan="2" class="text-center">
                                                                    <img src="<?= BASE_URL('mod/img/icon-Google-Ads.webp'); ?>"
                                                                        width="20px"> <?= __('Google Ads'); ?>
                                                                </th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr>
                                                                <td><?= __('Trạng thái'); ?></td>
                                                                <td>
                                                                    <select class="form-control"
                                                                        name="google_ads_status">
                                                                        <option
                                                                            <?= $CMSNT->site('google_ads_status') == 1 ? 'selected' : ''; ?>
                                                                            value="1">ON
                                                                        </option>
                                                                        <option
                                                                            <?= $CMSNT->site('google_ads_status') == 0 ? 'selected' : ''; ?>
                                                                            value="0">
                                                                            OFF
                                                                        </option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td><?= __('ID'); ?></td>
                                                                <td>
                                                                    <input type="text" name="google_ads_id"
                                                                        placeholder="VD: AW-XXXXXXXX"
                                                                        value="<?= $CMSNT->site('google_ads_id'); ?>"
                                                                        class="form-control">
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                    <table class="mb-3 table table-bordered table-striped table-hover">
                                                        <thead class="table-dark">
                                                            <tr>
                                                                <th colspan="2" class="text-center">
                                                                    <img src="https://i.imgur.com/5iOyCNW.png"
                                                                        width="20px"> <?= __('ChatGPT'); ?>
                                                                </th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr>
                                                                <td><?= __('API Key'); ?></td>
                                                                <td>
                                                                    <input type="text" name="chatgpt_api_key"
                                                                        placeholder="VD: sk-XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX"
                                                                        value="<?= $CMSNT->site('chatgpt_api_key'); ?>"
                                                                        class="form-control">
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td><?= __('Model'); ?></td>
                                                                <td>
                                                                    <select class="form-control" name="chatgpt_model">
                                                                        <option value="gpt-3.5-turbo"
                                                                            <?= $CMSNT->site('chatgpt_model') == 'gpt-3.5-turbo' ? 'selected' : ''; ?>>
                                                                            gpt-3.5-turbo</option>
                                                                        <option value="gpt-3.5-turbo-16k"
                                                                            <?= $CMSNT->site('chatgpt_model') == 'gpt-3.5-turbo-16k' ? 'selected' : ''; ?>>
                                                                            gpt-3.5-turbo-16k</option>
                                                                        <option value="gpt-4"
                                                                            <?= $CMSNT->site('chatgpt_model') == 'gpt-4' ? 'selected' : ''; ?>>
                                                                            gpt-4</option>
                                                                        <option value="gpt-4-32k"
                                                                            <?= $CMSNT->site('chatgpt_model') == 'gpt-4-32k' ? 'selected' : ''; ?>>
                                                                            gpt-4-32k</option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>

                                                </div>
                                                <div class="col-md-6">
                                                    <table class="mb-3 table table-bordered table-striped table-hover">
                                                        <thead class="table-dark">
                                                            <tr>
                                                                <th colspan="2" class="text-center">
                                                                    <img src="<?= BASE_URL('assets/img/icon-bot-telegram.avif'); ?>"
                                                                        width="25px"> <?= __('Bot Thông báo Telegram'); ?>
                                                                </th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr>
                                                                <td><?= __('Trạng thái BOT Telegram'); ?></td>
                                                                <td>
                                                                    <select class="form-control" name="telegram_status">
                                                                        <option
                                                                            <?= $CMSNT->site('telegram_status') == 1 ? 'selected' : ''; ?>
                                                                            value="1">ON
                                                                        </option>
                                                                        <option
                                                                            <?= $CMSNT->site('telegram_status') == 0 ? 'selected' : ''; ?>
                                                                            value="0">
                                                                            OFF
                                                                        </option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td><?= __('Telegram Token'); ?></td>
                                                                <td>
                                                                    <input type="text" name="telegram_token"
                                                                        value="<?= $CMSNT->site('telegram_token'); ?>"
                                                                        class="form-control">
                                                                    <small><a class="text-primary"
                                                                            href="https://help.cmsnt.co/huong-dan/huong-dan-tich-hop-bot-telegram-vao-shopclone7/"
                                                                            target="_blank"><?= __('Xem hướng dẫn'); ?></a></small>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td><?= __('Telegram Chat ID'); ?></td>
                                                                <td>
                                                                    <input type="text" name="telegram_chat_id"
                                                                        value="<?= $CMSNT->site('telegram_chat_id'); ?>"
                                                                        class="form-control">
                                                                    <small><a class="text-primary"
                                                                            href="https://help.cmsnt.co/huong-dan/huong-dan-tich-hop-bot-telegram-vao-shopclone7/"
                                                                            target="_blank"><?= __('Xem hướng dẫn'); ?></a></small>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td><?= __('Telegram URL'); ?></td>
                                                                <td>
                                                                    <select name="telegram_url" class="form-control">
                                                                        <option value="https://bypass-telegram.cmsnt.workers.dev/" <?= $CMSNT->site('telegram_url') == 'https://bypass-telegram.cmsnt.workers.dev/' ? 'selected' : ''; ?>>https://bypass-telegram.cmsnt.workers.dev/</option>
                                                                        <option value="https://api.telegram.org/" <?= $CMSNT->site('telegram_url') == 'https://api.telegram.org/' ? 'selected' : ''; ?>>https://api.telegram.org/</option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td><?= __('Telegram Proxy'); ?></td>
                                                                <td>
                                                                    <div class="input-group">
                                                                        <input type="text" name="telegram_proxy" id="telegram_proxy_input"
                                                                            value="<?= $CMSNT->site('telegram_proxy'); ?>"
                                                                            class="form-control"
                                                                            placeholder="ip:port:username:password hoặc ip:port">
                                                                        <button class="btn btn-success" type="button" id="btnGetFreeProxy" onclick="getFreeProxy()">
                                                                            <span id="btnText">
                                                                                <i class="fas fa-download me-1"></i>Lấy Proxy Free
                                                                            </span>
                                                                            <span id="btnLoading" class="d-none">
                                                                                <i class="fas fa-spinner fa-spin me-1"></i>Đang lấy...
                                                                            </span>
                                                                        </button>
                                                                    </div>
                                                                    <small class="text-muted">
                                                                        <i class="fas fa-info-circle me-1 text-info"></i>
                                                                        <strong>Lưu ý:</strong> Nếu máy chủ tại Việt Nam bị chặn kết nối Telegram, bạn có thể sử dụng Proxy để đảm bảo Bot Telegram hoạt động ổn định.
                                                                    </small>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td><?= __('Telegram Proxy Type'); ?></td>
                                                                <td>
                                                                    <select name="telegram_proxy_type" id="telegram_proxy_type_select" class="form-control">
                                                                        <option value="SOCKS5" <?= $CMSNT->site('telegram_proxy_type') == 'SOCKS5' ? 'selected' : ''; ?>>SOCKS5</option>
                                                                        <option value="HTTP" <?= $CMSNT->site('telegram_proxy_type') == 'HTTP' ? 'selected' : ''; ?>>HTTP</option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>


                                                    <table class="mb-3 table table-bordered table-striped table-hover">
                                                        <thead class="table-dark">
                                                            <tr>
                                                                <th colspan="2" class="text-center">
                                                                    <img src="<?= BASE_URL('mod/img/icon-gmail.webp'); ?>"
                                                                        width="20px"> <?= __('Check live GMAIL'); ?>
                                                                </th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr>
                                                                <td><?= __('API Link'); ?></td>
                                                                <td>
                                                                    <input type="text" name="api_check_live_gmail"
                                                                        value="<?= $CMSNT->site('api_check_live_gmail'); ?>"
                                                                        class="form-control">
                                                                    <small>Chỉ áp dụng cho API nội bộ</small>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td><?= __('API Key'); ?></td>
                                                                <td>
                                                                    <input type="text" name="api_key_check_live_gmail"
                                                                        value="<?= $CMSNT->site('api_key_check_live_gmail'); ?>"
                                                                        class="form-control">
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>Thời gian check live cách nhau</td>
                                                                <td>
                                                                    <div class="input-group">
                                                                        <input name="time_limit_check_live_gmail"
                                                                            type="text" class="form-control"
                                                                            value="<?= $CMSNT->site('time_limit_check_live_gmail'); ?>"
                                                                            required>
                                                                        <span class="input-group-text">
                                                                            Giây
                                                                        </span>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                    <table class="table table-bordered table-striped table-hover">
                                                        <thead class="table-dark">
                                                            <tr>
                                                                <th colspan="2" class="text-center">
                                                                    <img src="<?= BASE_URL('mod/img/icon-Instagram.webp'); ?>"
                                                                        width="20px">
                                                                    <?= __('Check live Instagram'); ?><br>

                                                                </th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr>
                                                                <td><?= __('API Link'); ?></td>
                                                                <td>
                                                                    <input type="text" name="api_check_live_instagram"
                                                                        value="<?= $CMSNT->site('api_check_live_instagram'); ?>"
                                                                        class="form-control">
                                                                    <small>Chỉ áp dụng cho API nội bộ</small>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td><?= __('API Key'); ?></td>
                                                                <td>
                                                                    <input type="text"
                                                                        name="api_key_check_live_instagram"
                                                                        value="<?= $CMSNT->site('api_key_check_live_instagram'); ?>"
                                                                        class="form-control">
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>Thời gian check live cách nhau</td>
                                                                <td>
                                                                    <div class="input-group">
                                                                        <input name="time_limit_check_live_instagram"
                                                                            type="text" class="form-control"
                                                                            value="<?= $CMSNT->site('time_limit_check_live_instagram'); ?>"
                                                                            required>
                                                                        <span class="input-group-text">
                                                                            Giây
                                                                        </span>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <div class="col-md-6">
                                                    <table class="table table-bordered table-striped table-hover">
                                                        <tbody>

                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            <button type="submit" name="SaveSettings"
                                                class="btn btn-primary w-100 mb-3">
                                                <i class="fa fa-fw fa-save me-1"></i> <?= __('Save'); ?>
                                            </button>
                                        </form>
                                    </div>
                                    <div class="tab-pane text-muted" id="telegram-template" role="tabpanel">
                                        <h4>Nội dung thông báo Telegram</h4>
                                        <form action="" method="POST">
                                            <div class="row push mb-3">
                                                <div class="col-md-12">
                                                    <table class="mb-3 table table-bordered table-striped table-hover">
                                                        <thead class="table-dark">
                                                            <tr>
                                                                <th colspan="2" class="text-center">
                                                                    Để mặc định nếu bạn không có nhu cầu tùy chỉnh<br>
                                                                    <small>Xóa toàn bộ nội dung trong ô nếu không muốn
                                                                        bật thông báo</small>
                                                                </th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr>
                                                                <td><?= __('Thông báo mua hàng'); ?></td>
                                                                <td>
                                                                    <textarea class="form-control mb-2" rows="3"
                                                                        name="noti_buy_product"><?= $CMSNT->site('noti_buy_product'); ?></textarea>
                                                                    <ul>
                                                                        <li><b>{domain}</b> => Tên website của quý
                                                                            khách.</li>
                                                                        <li><b>{username}</b> => Tên thành viên.</li>
                                                                        <li><b>{trans_id}</b> => Mã đơn hàng.</li>
                                                                        <li><b>{product}</b> => Sản phẩm mua.</li>
                                                                        <li><b>{amount}</b> => Số lượng mua.</li>
                                                                        <li><b>{pay}</b> => Số tiền thanh toán.</li>
                                                                        <li><b>{ip}</b> => Địa chỉ IP mua hàng.
                                                                        </li>
                                                                        <li><b>{time}</b> => Thời gian mua hàng</li>
                                                                    </ul>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td><?= __('Thông báo nạp tiền'); ?></td>
                                                                <td>
                                                                    <textarea class="form-control mb-2" rows="3"
                                                                        name="noti_recharge"><?= $CMSNT->site('noti_recharge'); ?></textarea>
                                                                    <ul>
                                                                        <li><b>{domain}</b> => Tên website của quý
                                                                            khách.</li>
                                                                        <li><b>{username}</b> => Tên khách hàng nạp.
                                                                        </li>
                                                                        <li><b>{method}</b> => Phương thức nạp.</li>
                                                                        <li><b>{amount}</b> => Số tiền nạp.</li>
                                                                        <li><b>{price}</b> => Thực nhận.</li>
                                                                        <li><b>{time}</b> => Thời gian.</li>
                                                                    </ul>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td><?= __('Thông báo hành động'); ?></td>
                                                                <td>
                                                                    <textarea class="form-control mb-2" rows="3"
                                                                        name="noti_action"><?= $CMSNT->site('noti_action'); ?></textarea>
                                                                    <ul>
                                                                        <li><b>{domain}</b> => Tên website của quý
                                                                            khách.</li>
                                                                        <li><b>{username}</b> => Tên thành viên.</li>
                                                                        <li><b>{action}</b> => Hành động của thành viên.
                                                                        </li>
                                                                        <li><b>{ip}</b> => Địa chỉ IP của thành viên.
                                                                        </li>
                                                                        <li><b>{time}</b> => Thời gian.</li>
                                                                    </ul>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td><?= __('Thông báo hoàn tiền đơn hàng'); ?></td>
                                                                <td>
                                                                    <textarea class="form-control mb-2" rows="3"
                                                                        name="noti_refund_orders"><?= $CMSNT->site('noti_refund_orders'); ?></textarea>
                                                                    <ul>
                                                                        <li><b>{domain}</b> => Tên website của quý
                                                                            khách.</li>
                                                                        <li><b>{username}</b> => Tên thành viên.</li>
                                                                        <li><b>{action}</b> => Hành động của thành viên.
                                                                        </li>
                                                                        <li><b>{ip}</b> => Địa chỉ IP của thành viên.
                                                                        </li>
                                                                        <li><b>{time}</b> => Thời gian.</li>
                                                                    </ul>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td><?= __('Thông báo rút số dư hoa hồng'); ?></td>
                                                                <td>
                                                                    <textarea class="form-control mb-2" rows="3"
                                                                        name="noti_affiliate_withdraw"><?= $CMSNT->site('noti_affiliate_withdraw'); ?></textarea>
                                                                    <ul>
                                                                        <li><b>{domain}</b> => Tên website của quý
                                                                            khách.</li>
                                                                        <li><b>{username}</b> => Tên thành viên rút.
                                                                        </li>
                                                                        <li><b>{bank}</b> => Tên ngân hàng nhận tiền.
                                                                        </li>
                                                                        <li><b>{account_number}</b> => Số tài khoản nhận
                                                                            tiền.</li>
                                                                        <li><b>{account_name}</b> => Tên chủ tài khoản.
                                                                        </li>
                                                                        <li><b>{amount}</b> => Số dư cần rút.</li>
                                                                        <li><b>{ip}</b> => Địa chỉ IP của thành viên.
                                                                        </li>
                                                                        <li><b>{time}</b> => Thời gian.</li>
                                                                    </ul>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td><?= __('Thông báo khi API hết số dư'); ?></td>
                                                                <td>
                                                                    <textarea class="form-control mb-2" rows="3"
                                                                        name="noti_api_out_of_money"><?= $CMSNT->site('noti_api_out_of_money'); ?></textarea>
                                                                    <ul>
                                                                        <li><b>{domain}</b> => Tên website của quý
                                                                            khách.</li>
                                                                        <li><b>{username}</b> => Tên thành viên mua hàng.
                                                                        </li>
                                                                        <li><b>{product_name}</b> => Tên sản phẩm cần mua.
                                                                        </li>
                                                                        <li><b>{supplier_name}</b> => Tên nhà cung cấp.</li>
                                                                        <li><b>{product_id}</b> => ID sản phẩm cần mua.</li>
                                                                        <li><b>{pay}</b> => Số tiền đơn hàng.
                                                                        </li>
                                                                        <li><b>{amount}</b> => Số lượng cần mua.</li>
                                                                        <li><b>{ip}</b> => Địa chỉ IP của thành viên mua hàng.</li>
                                                                        <li><b>{time}</b> => Thời gian.</li>
                                                                    </ul>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            <button type="submit" name="SaveSettings"
                                                class="btn btn-primary w-100 mb-3">
                                                <i class="fa fa-fw fa-save me-1"></i> <?= __('Save'); ?>
                                            </button>
                                        </form>
                                    </div>
                                    <div class="tab-pane text-muted" id="mail-template" role="tabpanel">
                                        <h4>Nội dung thông báo Mail</h4>
                                        <form action="" method="POST">
                                            <div class="row push mb-3">
                                                <div class="col-md-12">
                                                    <table class="mb-3 table table-bordered table-striped table-hover">
                                                        <thead class="table-dark">
                                                            <tr>
                                                                <th colspan="2" class="text-center">
                                                                    Để mặc định nếu bạn không có nhu cầu tùy chỉnh<br>
                                                                    <small>Xóa toàn bộ nội dung trong ô Subject nếu
                                                                        không muốn bật thông báo</small>
                                                                </th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr>
                                                                <td>Thông tin đơn hàng</td>
                                                                <td>
                                                                    <div class="input-group mb-2">
                                                                        <span class="input-group-text"
                                                                            id="basic-addon1">Subject</span>
                                                                        <input class="form-control"
                                                                            name="email_temp_subject_buy_order"
                                                                            value="<?= $CMSNT->site('email_temp_subject_buy_order'); ?>">
                                                                    </div>
                                                                    <textarea class="form-control mb-2"
                                                                        id="email_temp_content_buy_order" rows="6"
                                                                        name="email_temp_content_buy_order"><?= $CMSNT->site('email_temp_content_buy_order'); ?></textarea>
                                                                    <div
                                                                        class="accordion accordion-customicon1 accordion-primary">
                                                                        <div class="accordion-item">
                                                                            <h2 class="accordion-header">
                                                                                <button
                                                                                    class="accordion-button collapsed"
                                                                                    type="button"
                                                                                    data-bs-toggle="collapse"
                                                                                    data-bs-target="#email_temp_content_buy_order"
                                                                                    aria-expanded="false"
                                                                                    aria-controls="email_temp_content_buy_order">
                                                                                    Văn bản thay thế
                                                                                </button>
                                                                            </h2>
                                                                            <div id="email_temp_content_buy_order"
                                                                                class="accordion-collapse collapse">
                                                                                <div class="accordion-body">
                                                                                    <ul>
                                                                                        <li><b>{domain}</b> => Link
                                                                                            Website.</li>
                                                                                        <li><b>{title}</b> => Tên
                                                                                            website.</li>
                                                                                        <li><b>{username}</b> => Tên
                                                                                            khách hàng.</li>
                                                                                        <li><b>{ip}</b> => Địa chỉ IP.
                                                                                        </li>
                                                                                        <li><b>{device}</b> => Thiết bị.
                                                                                        </li>
                                                                                        <li><b>{time}</b> => Thời gian.
                                                                                        </li>
                                                                                        <li><b>{product}</b> => Tên sản
                                                                                            phẩm.</li>
                                                                                        <li><b>{amount}</b> => Số lượng
                                                                                            đã mua.</li>
                                                                                        <li><b>{trans_id}</b> => Mã đơn
                                                                                            hàng.</li>
                                                                                        <li><b>{pay}</b> => Số tiền đã
                                                                                            thanh toán.</li>
                                                                                    </ul>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>Thông báo đăng nhập</td>
                                                                <td>
                                                                    <div class="input-group mb-2">
                                                                        <span class="input-group-text"
                                                                            id="basic-addon1">Subject</span>
                                                                        <input class="form-control"
                                                                            name="email_temp_subject_warning_login"
                                                                            value="<?= $CMSNT->site('email_temp_subject_warning_login'); ?>">
                                                                    </div>
                                                                    <textarea class="form-control mb-2"
                                                                        id="email_temp_content_warning_login" rows="6"
                                                                        name="email_temp_content_warning_login"><?= $CMSNT->site('email_temp_content_warning_login'); ?></textarea>
                                                                    <div
                                                                        class="accordion accordion-customicon1 accordion-primary">
                                                                        <div class="accordion-item">
                                                                            <h2 class="accordion-header">
                                                                                <button
                                                                                    class="accordion-button collapsed"
                                                                                    type="button"
                                                                                    data-bs-toggle="collapse"
                                                                                    data-bs-target="#email_temp_content_warning_login"
                                                                                    aria-expanded="false"
                                                                                    aria-controls="email_temp_content_warning_login">
                                                                                    Văn bản thay thế
                                                                                </button>
                                                                            </h2>
                                                                            <div id="email_temp_content_warning_login"
                                                                                class="accordion-collapse collapse">
                                                                                <div class="accordion-body">
                                                                                    <ul>
                                                                                        <li><b>{domain}</b> => Link
                                                                                            Website.</li>
                                                                                        <li><b>{title}</b> => Tên
                                                                                            website.</li>
                                                                                        <li><b>{username}</b> => Tên
                                                                                            khách hàng.</li>
                                                                                        <li><b>{ip}</b> => Địa chỉ IP.
                                                                                        </li>
                                                                                        <li><b>{device}</b> => Thiết bị.
                                                                                        </li>
                                                                                        <li><b>{time}</b> => Thời gian.
                                                                                        </li>
                                                                                    </ul>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>Gửi OTP xác minh đăng nhập</td>
                                                                <td>
                                                                    <div class="input-group mb-2">
                                                                        <span class="input-group-text"
                                                                            id="basic-addon1">Subject</span>
                                                                        <input class="form-control"
                                                                            name="email_temp_subject_otp_mail"
                                                                            value="<?= $CMSNT->site('email_temp_subject_otp_mail'); ?>">
                                                                    </div>
                                                                    <textarea class="form-control mb-2" rows="6"
                                                                        id="email_temp_content_otp_mail"
                                                                        name="email_temp_content_otp_mail"><?= $CMSNT->site('email_temp_content_otp_mail'); ?></textarea>
                                                                    <div
                                                                        class="accordion accordion-customicon1 accordion-primary">
                                                                        <div class="accordion-item">
                                                                            <h2 class="accordion-header">
                                                                                <button
                                                                                    class="accordion-button collapsed"
                                                                                    type="button"
                                                                                    data-bs-toggle="collapse"
                                                                                    data-bs-target="#email_temp_content_otp_mail"
                                                                                    aria-expanded="false"
                                                                                    aria-controls="email_temp_content_otp_mail">
                                                                                    Văn bản thay thế
                                                                                </button>
                                                                            </h2>
                                                                            <div id="email_temp_content_otp_mail"
                                                                                class="accordion-collapse collapse">
                                                                                <div class="accordion-body">
                                                                                    <ul>
                                                                                        <li><b>{domain}</b> => Link
                                                                                            Website.</li>
                                                                                        <li><b>{title}</b> => Tên
                                                                                            website.</li>
                                                                                        <li><b>{username}</b> => Tên
                                                                                            khách hàng.</li>
                                                                                        <li><b>{otp}</b> => Mã OTP.
                                                                                        </li>
                                                                                        <li><b>{ip}</b> => Địa chỉ IP.
                                                                                        </li>
                                                                                        <li><b>{device}</b> => Thiết bị.
                                                                                        </li>
                                                                                        <li><b>{time}</b> => Thời gian.
                                                                                        </li>
                                                                                    </ul>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>Khôi phục mật khẩu</td>
                                                                <td>
                                                                    <div class="input-group mb-2">
                                                                        <span class="input-group-text"
                                                                            id="basic-addon1">Subject</span>
                                                                        <input class="form-control"
                                                                            name="email_temp_subject_forgot_password"
                                                                            value="<?= $CMSNT->site('email_temp_subject_forgot_password'); ?>">
                                                                    </div>
                                                                    <textarea class="form-control mb-2" rows="6"
                                                                        id="email_temp_content_forgot_password"
                                                                        name="email_temp_content_forgot_password"><?= $CMSNT->site('email_temp_content_forgot_password'); ?></textarea>
                                                                    <div
                                                                        class="accordion accordion-customicon1 accordion-primary">
                                                                        <div class="accordion-item">
                                                                            <h2 class="accordion-header">
                                                                                <button
                                                                                    class="accordion-button collapsed"
                                                                                    type="button"
                                                                                    data-bs-toggle="collapse"
                                                                                    data-bs-target="#email_temp_content_forgot_password"
                                                                                    aria-expanded="false"
                                                                                    aria-controls="email_temp_content_forgot_password">
                                                                                    Văn bản thay thế
                                                                                </button>
                                                                            </h2>
                                                                            <div id="email_temp_content_forgot_password"
                                                                                class="accordion-collapse collapse">
                                                                                <div class="accordion-body">
                                                                                    <ul>
                                                                                        <li><b>{domain}</b> => Link
                                                                                            Website.</li>
                                                                                        <li><b>{title}</b> => Tên
                                                                                            website.</li>
                                                                                        <li><b>{username}</b> => Tên
                                                                                            khách hàng.</li>
                                                                                        <li><b>{link}</b> => Link xác
                                                                                            minh.
                                                                                        </li>
                                                                                        <li><b>{ip}</b> => Địa chỉ IP.
                                                                                        </li>
                                                                                        <li><b>{device}</b> => Thiết bị.
                                                                                        </li>
                                                                                        <li><b>{time}</b> => Thời gian.
                                                                                        </li>
                                                                                    </ul>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            <button type="submit" name="SaveSettings"
                                                class="btn btn-primary w-100 mb-3">
                                                <i class="fa fa-fw fa-save me-1"></i> <?= __('Save'); ?>
                                            </button>
                                        </form>
                                    </div>

                                    <div class="tab-pane text-muted" id="security" role="tabpanel">
                                        <div class="d-flex align-items-center mb-4">
                                            <div class="flex-shrink-0">
                                                <div class="avatar avatar-md bg-primary-transparent rounded-circle">
                                                    <i class="ri-shield-check-line fs-18 text-primary"></i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <h4 class="mb-1"><?= __('Cài đặt bảo mật hệ thống'); ?></h4>
                                                <p class="text-muted mb-0">
                                                    <?= __('Cấu hình các tính năng bảo mật để bảo vệ hệ thống khỏi các cuộc tấn công'); ?>
                                                </p>
                                            </div>
                                        </div>
                                        <form action="" method="POST">
                                            <div class="row">
                                                <!-- Card 1: Bảo vệ chống Brute Force -->
                                                <div class="col-xl-6">
                                                    <div class="card border border-danger-subtle h-100">
                                                        <div class="card-header bg-danger-subtle">
                                                            <div class="d-flex align-items-center">
                                                                <div class="flex-shrink-0">
                                                                    <i
                                                                        class="ri-shield-cross-line fs-18 text-danger"></i>
                                                                </div>
                                                                <div class="flex-grow-1 ms-2">
                                                                    <h6 class="card-title mb-0 fw-semibold text-danger">
                                                                        <?= __('Bảo vệ chống Brute Force'); ?>
                                                                    </h6>
                                                                    <small class="text-muted">
                                                                        <?= __('Ngăn chặn tấn công scan tài khoản'); ?>
                                                                    </small>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="card-body">
                                                            <!-- Block IP Login -->
                                                            <div class="mb-3">
                                                                <label class="form-label fw-medium">
                                                                    <i class="ri-lock-line me-1 text-primary"></i>
                                                                    <?= __('Khóa IP nếu đăng nhập sai mật khẩu quá nhiều lần trong 15 phút'); ?>
                                                                </label>
                                                                <div class="input-group">
                                                                    <input type="number" class="form-control"
                                                                        value="<?= $CMSNT->site('limit_block_ip_login'); ?>"
                                                                        name="limit_block_ip_login" min="1">
                                                                    <span
                                                                        class="input-group-text"><?= __('lần'); ?></span>
                                                                </div>
                                                                <div class="form-text">
                                                                    <i class="ri-information-line me-1"></i>
                                                                    <?= __('Khuyến nghị: ≤ 5 lần để bảo mật tốt nhất'); ?>
                                                                </div>
                                                            </div>

                                                            <!-- Block Client Account -->
                                                            <div class="mb-3">
                                                                <label class="form-label fw-medium">
                                                                    <i
                                                                        class="ri-user-forbid-line me-1 text-warning"></i>
                                                                    <?= __('Khóa tài khoản nếu đăng nhập sai mật khẩu quá nhiều lần'); ?>
                                                                </label>
                                                                <div class="input-group">
                                                                    <input type="number" class="form-control"
                                                                        value="<?= $CMSNT->site('limit_block_client_login'); ?>"
                                                                        name="limit_block_client_login" min="1"
                                                                        max="50">
                                                                    <span
                                                                        class="input-group-text"><?= __('lần'); ?></span>
                                                                </div>
                                                                <div class="form-text">
                                                                    <i class="ri-information-line me-1"></i>
                                                                    <?= __('Khuyến nghị: ≤ 10 lần để cân bằng bảo mật và trải nghiệm'); ?>
                                                                </div>
                                                            </div>

                                                            <!-- Block API -->
                                                            <div class="mb-3">
                                                                <label class="form-label fw-medium">
                                                                    <i class="ri-key-line me-1 text-info"></i>
                                                                    <?= __('Khóa IP nếu API KEY sai quá nhiều lần trong 15 phút'); ?>
                                                                </label>
                                                                <div class="input-group">
                                                                    <input type="number" class="form-control"
                                                                        value="<?= $CMSNT->site('limit_block_ip_api'); ?>"
                                                                        name="limit_block_ip_api" min="1">
                                                                    <span
                                                                        class="input-group-text"><?= __('lần'); ?></span>
                                                                </div>
                                                                <div class="form-text">
                                                                    <i class="ri-information-line me-1"></i>
                                                                    <?= __('Khuyến nghị: ≤ 20 lần để bảo vệ API'); ?>
                                                                </div>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label fw-medium">
                                                                    <i
                                                                        class=" ri-shield-keyhole-fill me-1 text-info"></i>
                                                                    <?= __('Khóa IP nếu nhập sai 2FA quá nhiều lần trong 15 phút'); ?>
                                                                </label>
                                                                <div class="input-group">
                                                                    <input type="number" class="form-control"
                                                                        value="<?= $CMSNT->site('limit_block_ip_2fa'); ?>"
                                                                        name="limit_block_ip_2fa" min="1">
                                                                    <span
                                                                        class="input-group-text"><?= __('lần'); ?></span>
                                                                </div>
                                                                <div class="form-text">
                                                                    <i class="ri-information-line me-1"></i>
                                                                    <?= __('Khuyến nghị: ≤ 10 lần'); ?>
                                                                </div>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label fw-medium">
                                                                    <i class="ri-shield-flash-line me-1 text-info"></i>
                                                                    <?= __('Khóa IP nếu nhập sai OTP quá nhiều lần trong 15 phút'); ?>
                                                                </label>
                                                                <div class="input-group">
                                                                    <input type="number" class="form-control"
                                                                        value="<?= $CMSNT->site('limit_block_ip_otp'); ?>"
                                                                        name="limit_block_ip_otp" min="1">
                                                                    <span
                                                                        class="input-group-text"><?= __('lần'); ?></span>
                                                                </div>
                                                                <div class="form-text">
                                                                    <i class="ri-information-line me-1"></i>
                                                                    <?= __('Khuyến nghị: ≤ 10 lần'); ?>
                                                                </div>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label fw-medium">
                                                                    <i class="ri-shield-user-line me-1 text-info"></i>
                                                                    <?= __('Khóa IP nếu tạo hóa đơn nạp tiền quá nhiều lần trong 15 phút'); ?>
                                                                </label>
                                                                <div class="input-group">
                                                                    <input type="number" class="form-control"
                                                                        value="<?= $CMSNT->site('limit_block_ip_payment'); ?>"
                                                                        name="limit_block_ip_payment" min="1">
                                                                    <span
                                                                        class="input-group-text"><?= __('lần'); ?></span>
                                                                </div>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label fw-medium">
                                                                    <i class="ri-shield-user-line me-1 text-info"></i>
                                                                    <?= __('Khóa IP nếu yêu cầu khôi phục mật khẩu quá nhiều lần trong 15 phút'); ?>
                                                                </label>
                                                                <div class="input-group">
                                                                    <input type="number" class="form-control"
                                                                        value="<?= $CMSNT->site('limit_block_ip_reset_password'); ?>"
                                                                        name="limit_block_ip_reset_password" min="1">
                                                                    <span
                                                                        class="input-group-text"><?= __('lần'); ?></span>
                                                                </div>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label fw-medium">
                                                                    <i class="ri-shield-user-line me-1 text-info"></i>
                                                                    <?= __('Khóa IP nếu load tất cả sản phẩm quá nhiều lần trong 15 phút (Spam Load Ajax Products)'); ?>
                                                                </label>
                                                                <div class="input-group">
                                                                    <input type="number" class="form-control"
                                                                        value="<?= $CMSNT->site('limit_block_ip_load_products'); ?>"
                                                                        name="limit_block_ip_load_products" min="1">
                                                                    <span
                                                                        class="input-group-text"><?= __('lần'); ?></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Card 2: Kiểm soát truy cập -->
                                                <div class="col-xl-6">
                                                    <div class="card border border-primary-subtle h-100">
                                                        <div class="card-header bg-primary-subtle">
                                                            <div class="d-flex align-items-center">
                                                                <div class="flex-shrink-0">
                                                                    <i
                                                                        class="ri-shield-check-line fs-18 text-primary"></i>
                                                                </div>
                                                                <div class="flex-grow-1 ms-2">
                                                                    <h6
                                                                        class="card-title mb-0 fw-semibold text-primary">
                                                                        <?= __('Kiểm soát truy cập'); ?>
                                                                    </h6>
                                                                    <small class="text-muted">
                                                                        <?= __('Giới hạn số thiết bị và IP đăng nhập'); ?>
                                                                    </small>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="card-body">
                                                            <!-- Admin Access Attempts -->
                                                            <div class="mb-4">
                                                                <label class="form-label fw-medium">
                                                                    <i class="ri-admin-line me-1 text-danger"></i>
                                                                    <?= __('Khóa IP truy cập trái phép Admin Panel trong 15 phút'); ?>
                                                                </label>
                                                                <div class="input-group">
                                                                    <input type="number" class="form-control"
                                                                        value="<?= $CMSNT->site('limit_block_ip_admin_access'); ?>"
                                                                        name="limit_block_ip_admin_access" min="1"
                                                                        max="20">
                                                                    <span
                                                                        class="input-group-text"><?= __('lần'); ?></span>
                                                                </div>
                                                                <div class="form-text">
                                                                    <i class="ri-information-line me-1"></i>
                                                                    <?= __('Số lần truy cập sai URL admin trước khi block IP'); ?>
                                                                </div>
                                                            </div>

                                                            <!-- Single IP Admin -->
                                                            <div class="mb-4">
                                                                <label class="form-label fw-medium">
                                                                    <i class="ri-global-line me-1 text-success"></i>
                                                                    <?= __('Chỉ cho phép Admin đăng nhập từ 1 IP'); ?>
                                                                </label>
                                                                <select class="form-select"
                                                                    name="status_only_ip_login_admin" required>
                                                                    <option value="0"
                                                                        <?= $CMSNT->site('status_only_ip_login_admin') == 0 ? 'selected' : ''; ?>>
                                                                        <i class="ri-close-line"></i> <?= __('Tắt'); ?>
                                                                        (<?= __('Cho phép nhiều IP'); ?>)
                                                                    </option>
                                                                    <option value="1"
                                                                        <?= $CMSNT->site('status_only_ip_login_admin') == 1 ? 'selected' : ''; ?>>
                                                                        <i class="ri-check-line"></i> <?= __('Bật'); ?>
                                                                        (<?= __('Chỉ 1 IP duy nhất'); ?>)
                                                                    </option>
                                                                </select>
                                                                <div class="form-text">
                                                                    <i class="ri-information-line me-1"></i>
                                                                    <?= __('Tự động đăng xuất phiên đăng nhập Admin cũ nếu có lần đăng nhập mới'); ?>
                                                                </div>
                                                            </div>

                                                            <!-- Single Device Admin -->
                                                            <div class="mb-4">
                                                                <label class="form-label fw-medium">
                                                                    <i class="ri-computer-line me-1 text-warning"></i>
                                                                    <?= __('Chỉ cho phép Admin đăng nhập từ 1 thiết bị'); ?>
                                                                </label>
                                                                <select class="form-select"
                                                                    name="status_only_device_admin" required>
                                                                    <option value="0"
                                                                        <?= $CMSNT->site('status_only_device_admin') == 0 ? 'selected' : ''; ?>>
                                                                        <?= __('Tắt'); ?>
                                                                        (<?= __('Cho phép nhiều thiết bị'); ?>)
                                                                    </option>
                                                                    <option value="1"
                                                                        <?= $CMSNT->site('status_only_device_admin') == 1 ? 'selected' : ''; ?>>
                                                                        <?= __('Bật'); ?>
                                                                        (<?= __('Chỉ 1 thiết bị duy nhất'); ?>)
                                                                    </option>
                                                                </select>
                                                                <div class="form-text">
                                                                    <i class="ri-information-line me-1"></i>
                                                                    <?= __('Tự động đăng xuất phiên đăng nhập Admin cũ nếu có lần đăng nhập mới'); ?>
                                                                </div>
                                                            </div>

                                                            <!-- Single Device Client -->
                                                            <div class="mb-0">
                                                                <label class="form-label fw-medium">
                                                                    <i class="ri-smartphone-line me-1 text-info"></i>
                                                                    <?= __('Chỉ cho phép Client đăng nhập từ 1 thiết bị'); ?>
                                                                </label>
                                                                <select class="form-select"
                                                                    name="status_only_device_client" required>
                                                                    <option value="0"
                                                                        <?= $CMSNT->site('status_only_device_client') == 0 ? 'selected' : ''; ?>>
                                                                        <?= __('Tắt'); ?>
                                                                        (<?= __('Cho phép nhiều thiết bị'); ?>)
                                                                    </option>
                                                                    <option value="1"
                                                                        <?= $CMSNT->site('status_only_device_client') == 1 ? 'selected' : ''; ?>>
                                                                        <?= __('Bật'); ?>
                                                                        (<?= __('Chỉ 1 thiết bị duy nhất'); ?>)
                                                                    </option>
                                                                </select>
                                                                <div class="form-text">
                                                                    <i class="ri-information-line me-1"></i>
                                                                    <?= __('Tự động đăng xuất phiên đăng nhập Client cũ nếu có lần đăng nhập mới'); ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <!-- Card 3: Admin Panel URL Security -->
                                                <div class="row mt-4">
                                                    <div class="col-12">
                                                        <div class="card border border-warning-subtle">
                                                            <div class="card-header bg-warning-subtle">
                                                                <div class="d-flex align-items-center">
                                                                    <div class="flex-shrink-0">
                                                                        <i
                                                                            class="ri-shield-check-line fs-18 text-warning"></i>
                                                                    </div>
                                                                    <div class="flex-grow-1 ms-2">
                                                                        <h6
                                                                            class="card-title mb-0 fw-semibold text-warning">
                                                                            <?= __('Bảo mật khác'); ?>
                                                                        </h6>
                                                                        <small class="text-muted">
                                                                            <?= __('Một số cấu hình bảo mật khác'); ?>
                                                                        </small>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="card-body">
                                                                <div class="row">

                                                                    <div class="col-md-6">
                                                                        <div class="mb-3">
                                                                            <label class="form-label fw-medium">
                                                                                <i
                                                                                    class="ri-admin-line me-1 text-primary"></i>
                                                                                <?= __('Số lượng tài khoản có thể đăng ký tối đa của 1 IP'); ?>
                                                                            </label>
                                                                            <input name="max_register_ip" type="text"
                                                                                class="form-control"
                                                                                value="<?= $CMSNT->site('max_register_ip'); ?>"
                                                                                required>
                                                                            <div class="form-text">
                                                                                <i class="ri-information-line me-1"></i>
                                                                                <?= __('1 IP chỉ được phép đăng ký tối đa'); ?>
                                                                                <strong><?= $CMSNT->site('max_register_ip'); ?></strong>
                                                                                <?= __('tài khoản'); ?>.
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <div class="mb-3">
                                                                            <label class="form-label fw-medium">
                                                                                <i
                                                                                    class="ri-time-line me-1 text-primary"></i>
                                                                                <?= __('Thời gian lưu đăng nhập'); ?>
                                                                            </label>
                                                                            <div class="input-group">
                                                                                <input name="session_login"
                                                                                    type="number" class="form-control"
                                                                                    value="<?= $CMSNT->site('session_login'); ?>"
                                                                                    placeholder="<?= __('Nhập thời gian...'); ?>"
                                                                                    required>
                                                                                <span class="input-group-text">
                                                                                    <i class="ri-time-line me-1"></i>
                                                                                    <?= __('giây'); ?>
                                                                                </span>
                                                                            </div>
                                                                            <div class="form-text">
                                                                                <i class="ri-information-line me-1"></i>
                                                                                <?= __('VD: 86400 = 24 giờ, 3600 = 1 giờ'); ?>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <div class="mb-3">
                                                                            <label class="form-label fw-medium">
                                                                                <i
                                                                                    class="ri-key-line me-1 text-warning"></i>
                                                                                <?= __('Mã bí mật Cron Job'); ?>
                                                                            </label>
                                                                            <div class="input-group">
                                                                                <input name="key_cron_job" type="text"
                                                                                    class="form-control"
                                                                                    value="<?= $CMSNT->site('key_cron_job'); ?>"
                                                                                    required readonly>
                                                                                <button type="button"
                                                                                    class="btn btn-primary"
                                                                                    onclick="generateKeyCronJob()">
                                                                                    <i
                                                                                        class="ri-refresh-line me-1"></i><?= __('Tạo mới'); ?>
                                                                                </button>
                                                                            </div>
                                                                            <div class="form-text">
                                                                                <i class="fas fa-info-circle me-1"></i>
                                                                                <?= __('Mã bí mật Cron Job sẽ được sử dụng để xác thực yêu cầu từ Cron Job, tránh spam cron job từ bên ngoài.'); ?>
                                                                            </div>
                                                                        </div>
                                                                        <script>
                                                                            /**
                                                                             * Hàm tạo random key 16 ký tự cho Cron Job
                                                                             * Sử dụng các ký tự alphanumeric (a-z, A-Z, 0-9) để đảm bảo tính bảo mật
                                                                             */
                                                                            function generateKeyCronJob() {
                                                                                // Xác nhận từ user trước khi thay đổi
                                                                                cuteAlert({
                                                                                    type: "question",
                                                                                    title: "<?= __('Xác nhận thay đổi'); ?>",
                                                                                    message: "<?= __('Bạn có chắc chắn muốn tạo mã bí mật Cron Job mới?'); ?>",
                                                                                    confirmText: "<?= __('Đồng ý'); ?>",
                                                                                    cancelText: "<?= __('Hủy'); ?>"
                                                                                }).then((confirmed) => {
                                                                                    if (confirmed) {
                                                                                        // Tạo random key 16 ký tự
                                                                                        const characters =
                                                                                            'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
                                                                                        let randomKey = '';

                                                                                        // Tạo 16 ký tự random
                                                                                        for (let i = 0; i < 16; i++) {
                                                                                            randomKey += characters
                                                                                                .charAt(Math.floor(Math
                                                                                                    .random() *
                                                                                                    characters
                                                                                                    .length));
                                                                                        }

                                                                                        // Cập nhật giá trị vào input field
                                                                                        const keyInput = document
                                                                                            .querySelector(
                                                                                                'input[name="key_cron_job"]'
                                                                                            );
                                                                                        if (keyInput) {
                                                                                            keyInput.value = randomKey;

                                                                                            // Hiệu ứng highlight để user biết đã thay đổi
                                                                                            keyInput.style
                                                                                                .backgroundColor =
                                                                                                '#fff3cd';
                                                                                            keyInput.style.borderColor =
                                                                                                '#ffc107';

                                                                                            // Reset highlight sau 2 giây
                                                                                            setTimeout(() => {
                                                                                                keyInput.style
                                                                                                    .backgroundColor =
                                                                                                    '';
                                                                                                keyInput.style
                                                                                                    .borderColor =
                                                                                                    '';
                                                                                            }, 2000);

                                                                                            // Hiển thị thông báo thành công
                                                                                            cuteAlert({
                                                                                                type: "success",
                                                                                                title: "<?= __('Thành công!'); ?>",
                                                                                                message: "<?= __('Đã tạo mã bí mật Cron Job mới. Vui lòng lưu cài đặt để áp dụng thay đổi.'); ?>",
                                                                                                confirmText: "<?= __('Đóng'); ?>"
                                                                                            });
                                                                                        } else {
                                                                                            // Lỗi không tìm thấy input field
                                                                                            cuteAlert({
                                                                                                type: "error",
                                                                                                title: "<?= __('Lỗi!'); ?>",
                                                                                                message: "<?= __('Không tìm thấy trường nhập mã bí mật.'); ?>",
                                                                                                confirmText: "<?= __('Đóng'); ?>"
                                                                                            });
                                                                                        }
                                                                                    }
                                                                                });
                                                                            }
                                                                        </script>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-xl-6">
                                                        <div class="card border border-info-subtle h-100">
                                                            <div class="card-header bg-info-subtle">
                                                                <div class="d-flex align-items-center">
                                                                    <div class="flex-shrink-0">
                                                                        <i class="fas fa-shield-alt fs-18 text-info"></i>
                                                                    </div>
                                                                    <div class="flex-grow-1 ms-2">
                                                                        <h6 class="card-title mb-0 fw-semibold text-info">
                                                                            <?= __('Captcha'); ?>
                                                                        </h6>
                                                                        <small class="text-muted">
                                                                            <?= __('Cấu hình Captcha ngăn chặn SPAM'); ?>
                                                                        </small>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="card-body">
                                                                <div class="mb-4">
                                                                    <label class="form-label fw-medium">
                                                                        <i class="fas fa-power-off me-1 text-info"></i>
                                                                        <?= __('Trạng thái'); ?>
                                                                    </label>
                                                                    <select class="form-select" name="captcha_status"
                                                                        required>
                                                                        <option value="0"
                                                                            <?= $CMSNT->site('captcha_status') == 0 ? 'selected' : ''; ?>>
                                                                            <?= __('Tắt'); ?>
                                                                        </option>
                                                                        <option value="1"
                                                                            <?= $CMSNT->site('captcha_status') == 1 ? 'selected' : ''; ?>>
                                                                            <?= __('Bật'); ?>
                                                                        </option>
                                                                    </select>
                                                                </div>
                                                                <div class="mb-4">
                                                                    <label class="form-label fw-medium">
                                                                        <i class="fas fa-puzzle-piece me-1 text-info"></i>
                                                                        <?= __('Loại Captcha'); ?>
                                                                    </label>
                                                                    <select class="form-select" name="captcha_type"
                                                                        required>
                                                                        <option value="reCAPTCHA"
                                                                            <?= $CMSNT->site('captcha_type') == 'reCAPTCHA' ? 'selected' : ''; ?>>
                                                                            <?= __('reCAPTCHA (Google)'); ?>
                                                                        </option>
                                                                        <option value="Cloudflare"
                                                                            <?= $CMSNT->site('captcha_type') == 'Cloudflare' ? 'selected' : ''; ?>>
                                                                            <?= __('Cloudflare Captcha'); ?>
                                                                        </option>
                                                                    </select>
                                                                    <div class="form-text">
                                                                        <i class="fas fa-info-circle me-1"></i>
                                                                        <?= __('Chọn loại Captcha sau đó cấu hình Site Key, Secret Key để sử dụng.'); ?>
                                                                    </div>
                                                                    <!-- Link hướng dẫn động theo loại Captcha -->
                                                                    <div id="captcha-help-link" class="alert alert-success-transparent border-0 mt-3">
                                                                        <div class="d-flex align-items-center">
                                                                            <div class="me-2">
                                                                                <i class="fas fa-book-open fs-16 text-success"></i>
                                                                            </div>
                                                                            <div>
                                                                                <strong><?= __('Cần trợ giúp?'); ?></strong><br>
                                                                                <a id="help-link" href="https://help.cmsnt.co/huong-dan/smmpanel2-huong-dan-cau-hinh-recaptcha/" target="_blank" class="text-success fw-medium">
                                                                                    <i class="fas fa-external-link-alt me-1"></i>
                                                                                    <span id="help-text"><?= __('Xem hướng dẫn chi tiết cấu hình reCAPTCHA'); ?></span>
                                                                                </a>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="mb-4">
                                                                    <label class="form-label fw-medium">
                                                                        <i class="fas fa-key me-1 text-info"></i>
                                                                        <?= __('Site Key'); ?>
                                                                    </label>
                                                                    <input class="form-control" type="text"
                                                                        name="captcha_site_key"
                                                                        value="<?= $CMSNT->site('captcha_site_key'); ?>"
                                                                        placeholder="<?= __('Nhập Site Key...'); ?>">
                                                                </div>
                                                                <div class="mb-4">
                                                                    <label class="form-label fw-medium">
                                                                        <i class="fas fa-lock me-1 text-info"></i>
                                                                        <?= __('Secret Key'); ?>
                                                                    </label>
                                                                    <input class="form-control" type="text"
                                                                        name="captcha_secret_key"
                                                                        value="<?= $CMSNT->site('captcha_secret_key'); ?>"
                                                                        placeholder="<?= __('Nhập Secret Key...'); ?>">
                                                                </div>
                                                                <div class="mb-0">
                                                                    <label class="form-label fw-medium">
                                                                        <i class="fas fa-list-check me-1 text-info"></i>
                                                                        <?= __('Module áp dụng Captcha'); ?>
                                                                    </label>
                                                                    <select class="form-control"
                                                                        name="captcha_modules[]"
                                                                        id="captcha-modules" multiple>
                                                                        <?php
                                                                        $selectedModules = explode(',', $CMSNT->site('captcha_modules') ?? '');
                                                                        $modules = [
                                                                            'login'             => __('Đăng nhập'),
                                                                            'register'          => __('Đăng ký'),
                                                                            'forgot_password'   => __('Quên mật khẩu'),
                                                                            'verify_2fa'        => __('Xác minh 2FA'),
                                                                            'verify_otp'        => __('Xác minh OTP')
                                                                        ];
                                                                        foreach ($modules as $value => $label):
                                                                            $selected = in_array($value, $selectedModules) ? 'selected' : '';
                                                                        ?>
                                                                            <option value="<?= $value; ?>" <?= $selected; ?>><?= $label; ?></option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                    <div class="form-text">
                                                                        <i class="fas fa-info-circle me-1"></i>
                                                                        <?= __('Nhưng module mà bạn chọn sẽ áp dụng tính năng xác thực Captcha khi submit.'); ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Save Button -->
                                                <div class="row mt-4">
                                                    <div class="col-12">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div class="text-muted">
                                                                <i class="ri-information-line me-1"></i>
                                                                <?= __('Lưu ý: Những thay đổi này sẽ ảnh hưởng đến bảo mật toàn hệ thống'); ?>
                                                            </div>
                                                            <button type="submit" name="SaveSettings"
                                                                class="btn btn-danger btn-label">
                                                                <i
                                                                    class="ri-save-line label-icon align-middle fs-16 me-2"></i>
                                                                <?= __('Lưu cài đặt bảo mật'); ?>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </form>
                                    </div>

                                    <div class="tab-pane text-muted" id="hien-thi-san-pham" role="tabpanel">
                                        <h4><?= __('Tùy chỉnh giao diện hiển thị sản phẩm'); ?></h4>
                                        <form action="" method="POST">
                                            <div class="row push mb-3">
                                                <div class="col-md-6">
                                                    <table class="mb-3 table table-bordered table-striped table-hover">
                                                        <tbody>
                                                            <tr>
                                                                <td><?= __('Menu chuyên mục thanh bên'); ?></td>
                                                                <td>
                                                                    <select class="form-control"
                                                                        name="menu_category_right">
                                                                        <option
                                                                            <?= $CMSNT->site('menu_category_right') == 1 ? 'selected' : ''; ?>
                                                                            value="1"><?= __('Hiển thị bên phải'); ?>
                                                                        </option>
                                                                        <option
                                                                            <?= $CMSNT->site('menu_category_right') == 2 ? 'selected' : ''; ?>
                                                                            value="2"><?= __('Hiển thị bên trái'); ?>
                                                                        </option>
                                                                        <option
                                                                            <?= $CMSNT->site('menu_category_right') == 0 ? 'selected' : ''; ?>
                                                                            value="0">
                                                                            <?= __('Ẩn'); ?>
                                                                        </option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td><?= __('Ảnh sản phẩm'); ?></td>
                                                                <td>
                                                                    <select class="form-control"
                                                                        name="product_photo_display">
                                                                        <option
                                                                            <?= $CMSNT->site('product_photo_display') == 1 ? 'selected' : ''; ?>
                                                                            value="1"><?= __('Hiển thị'); ?>
                                                                        </option>
                                                                        <option
                                                                            <?= $CMSNT->site('product_photo_display') == 0 ? 'selected' : ''; ?>
                                                                            value="0">
                                                                            <?= __('Ẩn'); ?>
                                                                        </option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td><?= __('Đánh giá và reviews'); ?></td>
                                                                <td>
                                                                    <select class="form-control" disabled
                                                                        name="product_rating_display">
                                                                        <option
                                                                            <?= $CMSNT->site('product_rating_display') == 1 ? 'selected' : ''; ?>
                                                                            value="1"><?= __('Hiển thị'); ?>
                                                                        </option>
                                                                        <option
                                                                            <?= $CMSNT->site('product_rating_display') == 0 ? 'selected' : ''; ?>
                                                                            value="0">
                                                                            <?= __('Ẩn'); ?>
                                                                        </option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td><?= __('Hiển thị số lượng đã bán'); ?></td>
                                                                <td>
                                                                    <select class="form-control"
                                                                        name="product_sold_display">
                                                                        <option
                                                                            <?= $CMSNT->site('product_sold_display') == 1 ? 'selected' : ''; ?>
                                                                            value="1"><?= __('Hiển thị'); ?>
                                                                        </option>
                                                                        <option
                                                                            <?= $CMSNT->site('product_sold_display') == 0 ? 'selected' : ''; ?>
                                                                            value="0">
                                                                            <?= __('Ẩn'); ?>
                                                                        </option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td><?= __('Ẩn sản phẩm khỏi trang chủ khi hết hàng'); ?>
                                                                </td>
                                                                <td>
                                                                    <select class="form-control"
                                                                        name="product_hide_outstock">
                                                                        <option
                                                                            <?= $CMSNT->site('product_hide_outstock') == 1 ? 'selected' : ''; ?>
                                                                            value="1">ON
                                                                        </option>
                                                                        <option
                                                                            <?= $CMSNT->site('product_hide_outstock') == 0 ? 'selected' : ''; ?>
                                                                            value="0">
                                                                            OFF
                                                                        </option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td><?= __('ON/OFF Hiển thị cột UID trong đơn hàng'); ?>
                                                                </td>
                                                                <td>
                                                                    <select class="form-control" name="is_uid_visible">
                                                                        <option
                                                                            <?= $CMSNT->site('is_uid_visible') == 1 ? 'selected' : ''; ?>
                                                                            value="1">ON
                                                                        </option>
                                                                        <option
                                                                            <?= $CMSNT->site('is_uid_visible') == 0 ? 'selected' : ''; ?>
                                                                            value="0">
                                                                            OFF
                                                                        </option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <div class="col-md-6">
                                                    <table class="mb-3 table table-bordered table-striped table-hover">
                                                        <tbody>
                                                            <tr>
                                                                <td><?= __('Loại hiển thị'); ?></td>
                                                                <td>
                                                                    <select class="form-control"
                                                                        name="type_show_product">
                                                                        <option
                                                                            <?= $CMSNT->site('type_show_product') == 'BOX' ? 'selected' : ''; ?>
                                                                            value="BOX">BOX (1 dòng 2 sản phẩm, không hiển thị ảnh)
                                                                        </option>
                                                                        <option
                                                                            <?= $CMSNT->site('type_show_product') == 'LIST' ? 'selected' : ''; ?>
                                                                            value="LIST">
                                                                            LIST (1 dòng 1 sản phẩm, không hiển thị ảnh)
                                                                        </option>
                                                                        <option
                                                                            <?= $CMSNT->site('type_show_product') == 'BOX_4' ? 'selected' : ''; ?>
                                                                            value="BOX_4">BOX 4 (hiển thị ảnh sản phẩm bên trên 1 dòng 1 sản phẩm)
                                                                        </option>
                                                                        <option
                                                                            <?= $CMSNT->site('type_show_product') == 'BOX_5' ? 'selected' : ''; ?>
                                                                            value="BOX_5">BOX 5 (hiển thị ảnh sản phẩm bên trái, 1 dòng 2 sản phẩm)
                                                                        </option>
                                                                        <option
                                                                            <?= $CMSNT->site('type_show_product') == 'BOX_6' ? 'selected' : ''; ?>
                                                                            value="BOX_6">BOX 6 (hiển thị ảnh sản phẩm bên trái, 1 dòng 1 sản phẩm)
                                                                        </option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td><?= __('Số sản phẩm hiển thị tối đa của 1 chuyên mục tại trang chủ'); ?>
                                                                </td>
                                                                <td>
                                                                    <input type="number" class="form-control"
                                                                        value="<?= $CMSNT->site('max_show_product_home'); ?>"
                                                                        name="max_show_product_home">
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>Sắp xếp sản phẩm</td>
                                                                <td>
                                                                    <select class="form-control"
                                                                        name="order_by_product_home">
                                                                        <option
                                                                            <?= $CMSNT->site('order_by_product_home') == 1 ? 'selected' : ''; ?>
                                                                            value="1">Theo số ưu tiên (stt)
                                                                        </option>
                                                                        <option
                                                                            <?= $CMSNT->site('order_by_product_home') == 2 ? 'selected' : ''; ?>
                                                                            value="2"> Giá thấp đến cao
                                                                        </option>
                                                                        <option
                                                                            <?= $CMSNT->site('order_by_product_home') == 3 ? 'selected' : ''; ?>
                                                                            value="3"> Giá cao đến thấp
                                                                        </option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td><?= __('ON/OFF cột số dư bên phải'); ?></td>
                                                                <td>
                                                                    <select class="form-control"
                                                                        name="cot_so_du_ben_phai">
                                                                        <option
                                                                            <?= $CMSNT->site('cot_so_du_ben_phai') == 1 ? 'selected' : ''; ?>
                                                                            value="1"><?= __('Hiển thị'); ?>
                                                                        </option>
                                                                        <option
                                                                            <?= $CMSNT->site('cot_so_du_ben_phai') == 0 ? 'selected' : ''; ?>
                                                                            value="0">
                                                                            <?= __('Ẩn'); ?>
                                                                        </option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td><?= __('ON/OFF Nút chuyên mục ở Trang chủ'); ?></td>
                                                                <td>
                                                                    <select class="form-control"
                                                                        name="show_btn_category_home">
                                                                        <option
                                                                            <?= $CMSNT->site('show_btn_category_home') == 1 ? 'selected' : ''; ?>
                                                                            value="1"><?= __('Hiển thị'); ?>
                                                                        </option>
                                                                        <option
                                                                            <?= $CMSNT->site('show_btn_category_home') == 0 ? 'selected' : ''; ?>
                                                                            value="0">
                                                                            <?= __('Ẩn'); ?>
                                                                        </option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td><?= __('ON/OFF Đăng nhập mới được phép xem sản phẩm'); ?>
                                                                </td>
                                                                <td>
                                                                    <select class="form-control"
                                                                        name="isLoginRequiredToViewProduct">
                                                                        <option
                                                                            <?= $CMSNT->site('isLoginRequiredToViewProduct') == 1 ? 'selected' : ''; ?>
                                                                            value="1"><?= __('ON'); ?>
                                                                        </option>
                                                                        <option
                                                                            <?= $CMSNT->site('isLoginRequiredToViewProduct') == 0 ? 'selected' : ''; ?>
                                                                            value="0">
                                                                            <?= __('OFF'); ?>
                                                                        </option>
                                                                    </select>
                                                                </td>
                                                            </tr>

                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            <button type="submit" name="SaveSettings"
                                                class="btn btn-primary w-100 mb-3">
                                                <i class="fa fa-fw fa-save me-1"></i> <?= __('Save'); ?>
                                            </button>
                                        </form>
                                    </div>
                                    <div class="tab-pane text-muted" id="giao-dich-gan-day" role="tabpanel">
                                        <h4><?= __('Tùy chỉnh giao dịch gần đây'); ?></h4>
                                        <form action="" method="POST">
                                            <div class="row push mb-3">
                                                <div class="col-md-6">
                                                    <table class="mb-3 table table-bordered table-striped table-hover">
                                                        <tbody>
                                                            <tr>
                                                                <td><?= __('ON/OFF giao dịch gần đây'); ?></td>
                                                                <td>
                                                                    <select class="form-control"
                                                                        name="status_giao_dich_gan_day">
                                                                        <option
                                                                            <?= $CMSNT->site('status_giao_dich_gan_day') == 1 ? 'selected' : ''; ?>
                                                                            value="1">ON
                                                                        </option>
                                                                        <option
                                                                            <?= $CMSNT->site('status_giao_dich_gan_day') == 0 ? 'selected' : ''; ?>
                                                                            value="0">
                                                                            OFF
                                                                        </option>
                                                                    </select>
                                                                    <img src="<?= base_url('mod/img/demo-gd-gan-day.webp'); ?>"
                                                                        width="500px">
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td colspan="2">
                                                                    <center class="mb-2">Nội dung giao dịch mua hàng gần
                                                                        đây
                                                                    </center>
                                                                    <textarea class="form-control mb-2"
                                                                        id="content_gd_mua_gan_day" rows="2"
                                                                        name="content_gd_mua_gan_day"><?= $CMSNT->site('content_gd_mua_gan_day'); ?></textarea>
                                                                    <div
                                                                        class="accordion accordion-customicon1 accordion-primary">
                                                                        <div class="accordion-item">
                                                                            <h2 class="accordion-header">
                                                                                <button
                                                                                    class="accordion-button collapsed"
                                                                                    type="button"
                                                                                    data-bs-toggle="collapse"
                                                                                    data-bs-target="#content_gd_mua_gan_day"
                                                                                    aria-expanded="false"
                                                                                    aria-controls="content_gd_mua_gan_day">
                                                                                    Văn bản thay thế
                                                                                </button>
                                                                            </h2>
                                                                            <div id="content_gd_mua_gan_day"
                                                                                class="accordion-collapse collapse">
                                                                                <div class="accordion-body">
                                                                                    <ul>
                                                                                        <li><b>{username}</b> => Tên
                                                                                            user mua hàng.</li>
                                                                                        <li><b>{amount}</b> => Số lượng
                                                                                            mua.</li>
                                                                                        <li><b>{product_Name}</b> => Tên
                                                                                            sản phẩm.</li>
                                                                                        <li><b>{price}</b> => Giá bán.
                                                                                    </ul>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td colspan="2">
                                                                    <center class="mb-2">Nội dung giao dịch nạp tiền gần
                                                                        đây
                                                                    </center>
                                                                    <textarea class="form-control mb-2"
                                                                        id="content_gd_nap_tien_gan_day" rows="2"
                                                                        name="content_gd_nap_tien_gan_day"><?= $CMSNT->site('content_gd_nap_tien_gan_day'); ?></textarea>
                                                                    <div
                                                                        class="accordion accordion-customicon1 accordion-primary">
                                                                        <div class="accordion-item">
                                                                            <h2 class="accordion-header">
                                                                                <button
                                                                                    class="accordion-button collapsed"
                                                                                    type="button"
                                                                                    data-bs-toggle="collapse"
                                                                                    data-bs-target="#content_gd_nap_tien_gan_day"
                                                                                    aria-expanded="false"
                                                                                    aria-controls="content_gd_nap_tien_gan_day">
                                                                                    Văn bản thay thế
                                                                                </button>
                                                                            </h2>
                                                                            <div id="content_gd_nap_tien_gan_day"
                                                                                class="accordion-collapse collapse">
                                                                                <div class="accordion-body">
                                                                                    <ul>
                                                                                        <li><b>{username}</b> => Tên
                                                                                            user nạp tiền.</li>
                                                                                        <li><b>{amount}</b> => Số tiền
                                                                                            nạp.</li>
                                                                                        <li><b>{method}</b> => Phương
                                                                                            thức nạp.</li>
                                                                                        <li><b>{received}</b> => Thực
                                                                                            nhận.
                                                                                    </ul>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                            </tr>

                                                        </tbody>
                                                    </table>
                                                </div>
                                                <div class="col-md-6">
                                                    <table class="mb-3 table table-bordered table-striped table-hover">
                                                        <tbody>
                                                            <tr>
                                                                <td>ON/OFF tạo giao dịch ảo</td>
                                                                <td>
                                                                    <select class="form-control"
                                                                        name="status_tao_gd_ao">
                                                                        <option
                                                                            <?= $CMSNT->site('status_tao_gd_ao') == 1 ? 'selected' : ''; ?>
                                                                            value="1">ON
                                                                        </option>
                                                                        <option
                                                                            <?= $CMSNT->site('status_tao_gd_ao') == 0 ? 'selected' : ''; ?>
                                                                            value="0">
                                                                            OFF
                                                                        </option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>Số lượng mua ảo tối thiểu
                                                                </td>
                                                                <td>
                                                                    <input type="number" class="form-control"
                                                                        value="<?= $CMSNT->site('sl_mua_toi_thieu_gd_ao'); ?>"
                                                                        name="sl_mua_toi_thieu_gd_ao">
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>Số lượng mua ảo tối đa
                                                                </td>
                                                                <td>
                                                                    <input type="number" class="form-control"
                                                                        value="<?= $CMSNT->site('sl_mua_toi_da_gd_ao'); ?>"
                                                                        name="sl_mua_toi_da_gd_ao">
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>ON/OFF tạo giao dịch ảo sản phẩm hết hàng</td>
                                                                <td>
                                                                    <select class="form-control"
                                                                        name="tao_gd_ao_sp_het_hang">
                                                                        <option
                                                                            <?= $CMSNT->site('tao_gd_ao_sp_het_hang') == 1 ? 'selected' : ''; ?>
                                                                            value="1">ON
                                                                        </option>
                                                                        <option
                                                                            <?= $CMSNT->site('tao_gd_ao_sp_het_hang') == 0 ? 'selected' : ''; ?>
                                                                            value="0">
                                                                            OFF
                                                                        </option>
                                                                    </select>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>Tốc độ giao dịch mua ảo
                                                                </td>
                                                                <td>
                                                                    <input type="number" class="form-control"
                                                                        value="<?= $CMSNT->site('toc_do_gd_mua_ao'); ?>"
                                                                        name="toc_do_gd_mua_ao">
                                                                    <small>Tốc độ càng thấp, thời gian tạo giao dịch ảo
                                                                        càng nhanh.</small>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>Mệnh giá nạp ảo ngẫu nhiên
                                                                </td>
                                                                <td>
                                                                    <textarea class="form-control" rows="3"
                                                                        name="menh_gia_nap_ao_ngau_nhien"><?= $CMSNT->site('menh_gia_nap_ao_ngau_nhien'); ?></textarea>
                                                                    <small>1 dòng 1 dữ liệu.</small>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>Phương thức nạp ảo
                                                                </td>
                                                                <td>
                                                                    <textarea class="form-control" rows="3"
                                                                        name="method_nap_ao"><?= $CMSNT->site('method_nap_ao'); ?></textarea>
                                                                    <small>1 dòng 1 dữ liệu.</small>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>Tốc độ giao dịch nạp ảo
                                                                </td>
                                                                <td>
                                                                    <input type="number" class="form-control"
                                                                        value="<?= $CMSNT->site('toc_do_gd_nap_ao'); ?>"
                                                                        name="toc_do_gd_nap_ao">
                                                                    <small>Tốc độ càng thấp, thời gian tạo giao dịch ảo
                                                                        càng nhanh.</small>
                                                                </td>
                                                            </tr>

                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            <button type="submit" name="SaveSettings"
                                                class="btn btn-primary w-100 mb-3">
                                                <i class="fa fa-fw fa-save me-1"></i> <?= __('Save'); ?>
                                            </button>
                                        </form>
                                    </div>
                                    <div class="tab-pane text-muted" id="widget" role="tabpanel">
                                        <h4><?= __('Tùy chỉnh Widget'); ?></h4>
                                        <form action="" method="POST">
                                            <div class="row push mb-3">
                                                <div class="col-md-6">
                                                    <table class="mb-3 table table-bordered table-striped table-hover">
                                                        <tbody>
                                                            <tr>
                                                                <td colspan="2">
                                                                    <select class="form-control mb-1"
                                                                        name="widget_zalo1_status">
                                                                        <option
                                                                            <?= $CMSNT->site('widget_zalo1_status') == 1 ? 'selected' : ''; ?>
                                                                            value="1">ON
                                                                        </option>
                                                                        <option
                                                                            <?= $CMSNT->site('widget_zalo1_status') == 0 ? 'selected' : ''; ?>
                                                                            value="0">
                                                                            OFF
                                                                        </option>
                                                                    </select>
                                                                    <img src="<?= base_url('mod/img/demo-widget-zalo1.png'); ?>"
                                                                        width="500px">
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>Số điện thoại
                                                                </td>
                                                                <td>
                                                                    <input type="text" class="form-control"
                                                                        value="<?= $CMSNT->site('widget_zalo1_sdt'); ?>"
                                                                        name="widget_zalo1_sdt">
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                    <table class="mb-3 table table-bordered table-striped table-hover">
                                                        <tbody>
                                                            <tr>
                                                                <td colspan="2">
                                                                    <select class="form-control mb-1"
                                                                        name="widget_fbzalo2_status">
                                                                        <option
                                                                            <?= $CMSNT->site('widget_fbzalo2_status') == 1 ? 'selected' : ''; ?>
                                                                            value="1">ON
                                                                        </option>
                                                                        <option
                                                                            <?= $CMSNT->site('widget_fbzalo2_status') == 0 ? 'selected' : ''; ?>
                                                                            value="0">
                                                                            OFF
                                                                        </option>
                                                                    </select>
                                                                    <img src="<?= base_url('mod/img/demo-widget-fbzalo2.png'); ?>"
                                                                        width="200px">
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>Link Zalo
                                                                </td>
                                                                <td>
                                                                    <input type="text" class="form-control"
                                                                        value="<?= $CMSNT->site('widget_fbzalo2_zalo'); ?>"
                                                                        name="widget_fbzalo2_zalo">
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>Link Facebook
                                                                </td>
                                                                <td>
                                                                    <input type="text" class="form-control"
                                                                        value="<?= $CMSNT->site('widget_fbzalo2_fb'); ?>"
                                                                        name="widget_fbzalo2_fb">
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <div class="col-md-6">
                                                    <table class="mb-3 table table-bordered table-striped table-hover">
                                                        <tbody>
                                                            <tr>
                                                                <td colspan="2">
                                                                    <select class="form-control mb-1"
                                                                        name="widget_phone1_status">
                                                                        <option
                                                                            <?= $CMSNT->site('widget_phone1_status') == 1 ? 'selected' : ''; ?>
                                                                            value="1">ON
                                                                        </option>
                                                                        <option
                                                                            <?= $CMSNT->site('widget_phone1_status') == 0 ? 'selected' : ''; ?>
                                                                            value="0">
                                                                            OFF
                                                                        </option>
                                                                    </select>
                                                                    <img src="<?= base_url('mod/img/demo-widget-phone1.png'); ?>"
                                                                        width="500px">
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>Số điện thoại
                                                                </td>
                                                                <td>
                                                                    <input type="text" class="form-control"
                                                                        value="<?= $CMSNT->site('widget_phone1_sdt'); ?>"
                                                                        name="widget_phone1_sdt">
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            <button type="submit" name="SaveSettings"
                                                class="btn btn-primary w-100 mb-3">
                                                <i class="fa fa-fw fa-save me-1"></i> <?= __('Save'); ?>
                                            </button>
                                        </form>
                                    </div>
                                    <div class="tab-pane text-muted" id="addons" role="tabpanel">
                                        <h4 class="mb-4"><i class="fa-solid fa-puzzle-piece text-info me-2"></i>Cửa hàng Addon</h4>


                                        <!-- BẮT ĐẦU ACCORDION (danh sách Addon) -->
                                        <div class="accordion" id="accordionAddons">

                                            <!-- ADDON CTV PANEL -->
                                            <div class="accordion-item border-0 mb-3">
                                                <h2 class="accordion-header" id="headingCtvPanel">
                                                    <button
                                                        class="accordion-button collapsed bg-gradient-primary-hover shadow-sm"
                                                        type="button" data-bs-toggle="collapse"
                                                        data-bs-target="#collapseCtvPanel" aria-expanded="false"
                                                        aria-controls="collapseCtvPanel">
                                                        <div class="d-flex align-items-center w-100">
                                                            <div class="me-3 bg-success text-white rounded-circle p-2 d-flex align-items-center justify-content-center"
                                                                style="width: 36px; height: 36px;">
                                                                <i class="fas fa-users fs-5"></i>
                                                            </div>
                                                            <div class="flex-grow-1">
                                                                <h5 class="mb-0 fw-semibold text-dark">CTV Panel - Hệ Thống Cộng Tác Viên</h5>
                                                                <small class="text-muted">Quản lý cộng tác viên, sản phẩm và rút tiền hoa hồng</small>
                                                            </div>
                                                        </div>
                                                    </button>
                                                </h2>
                                                <div id="collapseCtvPanel" class="accordion-collapse collapse border-top"
                                                    aria-labelledby="headingCtvPanel" data-bs-parent="#accordionAddons">
                                                    <div class="accordion-body pt-4">
                                                        <!-- NỘI DUNG CHI TIẾT -->
                                                        <div class="row g-4">

                                                            <!-- CỘT DEMO -->
                                                            <div class="col-lg-6">
                                                                <div class="card border-0 shadow-hover">
                                                                    <div
                                                                        class="card-header bg-transparent border-bottom py-3">
                                                                        <h6 class="mb-0 fw-semibold">
                                                                            <i
                                                                                class="fas fa-play-circle text-primary me-2"></i>
                                                                            DEMO CTV Panel
                                                                        </h6>
                                                                    </div>
                                                                    <div class="card-body">
                                                                        <!-- Phần demo GIF -->
                                                                        <div class="position-relative overflow-hidden rounded-3"
                                                                            style="padding-top: 100%">
                                                                            <img src="https://i.postimg.cc/FKHgKQ6F/A-nh-ma-n-hi-nh-2025-09-11-lu-c-18-46-55.png"
                                                                                class="position-absolute top-0 start-0 w-100 h-100 object-fit-cover"
                                                                                alt="Demo CTV Panel" loading="lazy">
                                                                        </div>

                                                                        <!-- Phần tính năng nổi bật -->
                                                                        <div class="mt-4">
                                                                            <h6 class="fw-semibold mb-3">Tính năng nổi
                                                                                bật</h6>
                                                                            <ul class="list-unstyled mb-0">
                                                                                <li class="d-flex mb-2">
                                                                                    <i
                                                                                        class="fas fa-check-circle text-success me-2 mt-1"></i>
                                                                                    <span>Quản lý cộng tác viên chuyên nghiệp.</span>
                                                                                </li>
                                                                                <li class="d-flex mb-2">
                                                                                    <i
                                                                                        class="fas fa-check-circle text-success me-2 mt-1"></i>
                                                                                    <span>CTV có thể thêm sản phẩm và quản lý đơn hàng.</span>
                                                                                </li>
                                                                                <li class="d-flex mb-2">
                                                                                    <i
                                                                                        class="fas fa-check-circle text-success me-2 mt-1"></i>
                                                                                    <span>Hệ thống rút tiền hoa hồng tự động.</span>
                                                                                </li>
                                                                                <li class="d-flex mb-2">
                                                                                    <i
                                                                                        class="fas fa-check-circle text-success me-2 mt-1"></i>
                                                                                    <span>Thống kê doanh thu và báo cáo chi tiết.</span>
                                                                                </li>
                                                                                <li class="d-flex mb-2">
                                                                                    <i
                                                                                        class="fas fa-check-circle text-success me-2 mt-1"></i>
                                                                                    <span>Giao diện thân thiện, dễ sử dụng.</span>
                                                                                </li>
                                                                            </ul>
                                                                        </div>

                                                                        <!-- Phần giá bán Addon -->
                                                                        <div class="mt-4 pt-3 border-top">
                                                                            <div
                                                                                class="d-flex align-items-center justify-content-between">
                                                                                <div>
                                                                                    <h6 class="fw-semibold mb-1">Giá bán
                                                                                        Addon</h6>
                                                                                    <small class="text-muted">Bản quyền
                                                                                        vĩnh viễn, hỗ trợ trọn
                                                                                        đời</small>
                                                                                </div>
                                                                                <div class="text-end">
                                                                                    <div
                                                                                        class="fs-5 fw-bold text-primary">
                                                                                        1.500.000đ
                                                                                    </div>
                                                                                    <small
                                                                                        class="text-danger text-decoration-line-through">
                                                                                        2.000.000đ
                                                                                    </small>
                                                                                </div>
                                                                            </div>
                                                                            <a href="https://client.cmsnt.co/store/license-source-code/addon-ctv-panel-shopclone-v7"
                                                                                target="_blank"
                                                                                class="btn btn-success w-100 mt-3">
                                                                                <i
                                                                                    class="fas fa-shopping-cart me-2"></i>
                                                                                Mua Ngay
                                                                            </a>
                                                                            <div class="text-center mt-2">
                                                                                <small class="text-muted">
                                                                                    <i
                                                                                        class="fas fa-shield-alt me-1"></i>
                                                                                    Thanh toán an toàn và tự động 24/7
                                                                                </small>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <!-- CỘT MÔ TẢ CHI TIẾT -->
                                                            <div class="col-lg-6">
                                                                <div class="card border-0 shadow-hover">
                                                                    <div
                                                                        class="card-header bg-transparent border-bottom py-3">
                                                                        <h6 class="mb-0 fw-semibold">
                                                                            <i
                                                                                class="fas fa-info-circle text-info me-2"></i>
                                                                            Mô Tả Chi Tiết
                                                                        </h6>
                                                                    </div>
                                                                    <div class="card-body">
                                                                        <p class="mb-4">CTV Panel là hệ thống quản lý cộng tác viên chuyên nghiệp, cho phép bạn mở rộng kinh doanh thông qua mạng lưới cộng tác viên. CTV có thể thêm sản phẩm, quản lý đơn hàng và rút tiền hoa hồng một cách dễ dàng.</p>

                                                                        <h6 class="fw-semibold mb-3">Chức năng chính:</h6>
                                                                        <ul class="list-unstyled mb-4">
                                                                            <li class="d-flex mb-2">
                                                                                <i class="fas fa-user-plus text-primary me-2 mt-1"></i>
                                                                                <span><strong>Quản lý CTV:</strong> Thêm, sửa, xóa cộng tác viên</span>
                                                                            </li>
                                                                            <li class="d-flex mb-2">
                                                                                <i class="fas fa-box text-warning me-2 mt-1"></i>
                                                                                <span><strong>Quản lý sản phẩm:</strong> CTV có thể thêm và quản lý sản phẩm</span>
                                                                            </li>
                                                                            <li class="d-flex mb-2">
                                                                                <i class="fas fa-shopping-cart text-info me-2 mt-1"></i>
                                                                                <span><strong>Quản lý đơn hàng:</strong> Theo dõi và xử lý đơn hàng của CTV</span>
                                                                            </li>
                                                                            <li class="d-flex mb-2">
                                                                                <i class="fas fa-money-bill-wave text-success me-2 mt-1"></i>
                                                                                <span><strong>Rút tiền hoa hồng:</strong> Hệ thống rút tiền tự động</span>
                                                                            </li>
                                                                            <li class="d-flex mb-2">
                                                                                <i class="fas fa-chart-bar text-danger me-2 mt-1"></i>
                                                                                <span><strong>Thống kê:</strong> Báo cáo doanh thu chi tiết</span>
                                                                            </li>
                                                                        </ul>

                                                                        <div class="alert alert-info">
                                                                            <i class="fas fa-lightbulb me-2"></i>
                                                                            <strong>Lưu ý:</strong> Addon này yêu cầu bật tính năng "Cộng tiền người bán" trong cài đặt hệ thống để hoạt động.
                                                                        </div>

                                                                        <div class="alert alert-warning">
                                                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                                                            <strong>Yêu cầu:</strong> Cần có license ShopClone v7 để sử dụng addon này.
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- ADDON 1 -->



                                            <!-- ADDON XI-PAY -->
                                            <div class="accordion-item border-0 mb-3">
                                                <h2 class="accordion-header" id="headingXipay">
                                                    <button
                                                        class="accordion-button collapsed bg-gradient-primary-hover shadow-sm"
                                                        type="button" data-bs-toggle="collapse"
                                                        data-bs-target="#collapseXipay" aria-expanded="false"
                                                        aria-controls="collapseXipay">
                                                        <div class="d-flex align-items-center w-100">
                                                            <div class="me-3 bg-primary text-white rounded-circle p-2 d-flex align-items-center justify-content-center"
                                                                style="width: 36px; height: 36px;">
                                                                <i class="fas fa-money-check-alt fs-5"></i>
                                                            </div>
                                                            <div class="flex-grow-1">
                                                                <h5 class="mb-0 fw-semibold text-dark">Tích Hợp Thanh
                                                                    Toán Qua XiPay (China)</h5>
                                                                <small class="text-muted">Hỗ trợ thanh toán qua AliPay &
                                                                    WeChatPay</small>
                                                            </div>
                                                        </div>
                                                    </button>
                                                </h2>
                                                <div id="collapseXipay" class="accordion-collapse collapse border-top"
                                                    aria-labelledby="headingXipay" data-bs-parent="#accordionAddons">
                                                    <div class="accordion-body pt-4">
                                                        <!-- NỘI DUNG CHI TIẾT -->
                                                        <div class="row g-4">
                                                            <!-- Cột mô tả addon -->
                                                            <div class="col-lg-6">
                                                                <div class="card border-0 shadow-hover">
                                                                    <div
                                                                        class="card-header bg-transparent border-bottom py-3">
                                                                        <h6 class="mb-0 fw-semibold">
                                                                            <i
                                                                                class="fas fa-info-circle text-info me-2"></i>
                                                                            Mô Tả Addon
                                                                        </h6>
                                                                    </div>
                                                                    <div class="card-body">
                                                                        <p>Addon này cho phép bạn tích hợp cổng thanh
                                                                            toán XiPay trực tiếp vào website của mình.
                                                                            Với tích hợp này, khách hàng của bạn có thể
                                                                            thanh toán nhanh chóng qua AliPay hoặc
                                                                            WeChatPay một cách an toàn và tiện lợi.</p>
                                                                        <ul class="list-unstyled">
                                                                            <li class="d-flex mb-2">
                                                                                <i
                                                                                    class="fas fa-check-circle text-success me-2 mt-1"></i>
                                                                                <span>Thanh toán nhanh chóng qua
                                                                                    XiPay.</span>
                                                                            </li>
                                                                            <li class="d-flex mb-2">
                                                                                <i
                                                                                    class="fas fa-check-circle text-success me-2 mt-1"></i>
                                                                                <span>Hỗ trợ cả AliPay và
                                                                                    WeChatPay.</span>
                                                                            </li>
                                                                            <li class="d-flex mb-2">
                                                                                <i
                                                                                    class="fas fa-check-circle text-success me-2 mt-1"></i>
                                                                                <span>Dễ dàng tích hợp vào website hiện
                                                                                    tại.</span>
                                                                            </li>
                                                                        </ul>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <!-- Cột demo và giá bán -->
                                                            <div class="col-lg-6">
                                                                <div class="card border-0 shadow-hover">
                                                                    <div
                                                                        class="card-header bg-transparent border-bottom py-3">
                                                                        <h6 class="mb-0 fw-semibold">
                                                                            <i
                                                                                class="fas fa-play-circle text-primary me-2"></i>
                                                                            Demo & Giá Bán
                                                                        </h6>
                                                                    </div>
                                                                    <div class="card-body">
                                                                        <!-- Phần demo ảnh -->
                                                                        <div class="position-relative overflow-hidden rounded-3"
                                                                            style="padding-top: 56.25%;">
                                                                            <img src="https://i.imgur.com/tEqnBN5.png"
                                                                                class="position-absolute top-0 start-0 w-100 h-100 object-fit-cover"
                                                                                alt="Demo Addon XiPay">
                                                                        </div>

                                                                        <!-- Phần giá bán -->
                                                                        <div class="mt-4 pt-3 border-top">
                                                                            <div
                                                                                class="d-flex align-items-center justify-content-between">
                                                                                <div>
                                                                                    <h6 class="fw-semibold mb-1">Giá bán
                                                                                        Addon</h6>
                                                                                    <small class="text-muted">Bản quyền
                                                                                        vĩnh viễn, hỗ trợ cấu hình API
                                                                                        lần đầu, các lần cấu hình API hộ
                                                                                        tiếp theo sẽ tính phí 300.000đ /
                                                                                        lần.</small>
                                                                                </div>
                                                                                <div class="text-end">
                                                                                    <div
                                                                                        class="fs-5 fw-bold text-primary">
                                                                                        1.200.000đ</div>
                                                                                    <small
                                                                                        class="text-danger text-decoration-line-through">1.500.000đ</small>
                                                                                </div>
                                                                            </div>
                                                                            <!-- Nút mua hàng tích hợp thanh toán qua XiPay -->
                                                                            <a href="https://client.cmsnt.co/cart.php?a=add&pid=77"
                                                                                target="_blank"
                                                                                class="btn btn-primary w-100 mt-3">
                                                                                <i
                                                                                    class="fas fa-shopping-cart me-2"></i>
                                                                                Mua Ngay
                                                                            </a>
                                                                            <div class="text-center mt-2">
                                                                                <small class="text-muted">
                                                                                    <i
                                                                                        class="fas fa-shield-alt me-1"></i>
                                                                                    Thanh toán an toàn và tự động 24/7
                                                                                </small>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="accordion-item border-0 mb-3">
                                                <h2 class="accordion-header" id="headingKorapay">
                                                    <button
                                                        class="accordion-button collapsed bg-gradient-primary-hover shadow-sm"
                                                        type="button" data-bs-toggle="collapse"
                                                        data-bs-target="#collapseKorapay" aria-expanded="false"
                                                        aria-controls="collapseKorapay">
                                                        <div class="d-flex align-items-center w-100">
                                                            <div class="me-3 bg-primary text-white rounded-circle p-2 d-flex align-items-center justify-content-center"
                                                                style="width: 36px; height: 36px;">
                                                                <i class="fas fa-credit-card fs-5"></i>
                                                            </div>
                                                            <div class="flex-grow-1">
                                                                <h5 class="mb-0 fw-semibold text-dark">Tích Hợp Thanh
                                                                    Toán Qua Korapay (Africa)</h5>
                                                                <small class="text-muted">Hỗ trợ đa kênh thanh toán tại
                                                                    Africa như:
                                                                    Ngân hàng, thẻ tín dụng, ví điện tử & Mobile
                                                                    Money</small>
                                                            </div>
                                                        </div>
                                                    </button>
                                                </h2>
                                                <div id="collapseKorapay" class="accordion-collapse collapse border-top"
                                                    aria-labelledby="headingKorapay" data-bs-parent="#accordionAddons">
                                                    <div class="accordion-body pt-4">
                                                        <!-- NỘI DUNG CHI TIẾT -->
                                                        <div class="row g-4">
                                                            <!-- Cột mô tả addon -->
                                                            <div class="col-lg-6">
                                                                <div class="card border-0 shadow-hover">
                                                                    <div
                                                                        class="card-header bg-transparent border-bottom py-3">
                                                                        <h6 class="mb-0 fw-semibold">
                                                                            <i
                                                                                class="fas fa-info-circle text-info me-2"></i>
                                                                            Mô Tả Addon
                                                                        </h6>
                                                                    </div>
                                                                    <div class="card-body">
                                                                        <p>Addon này cho phép bạn tích hợp cổng thanh
                                                                            toán Korapay trực tiếp vào website của mình.
                                                                            Khách hàng của bạn có thể thanh toán qua
                                                                            nhiều kênh như chuyển khoản ngân hàng, thẻ
                                                                            tín dụng, ví điện tử và Mobile Money. Giao
                                                                            dịch được xử lý an toàn và nhanh chóng, mang
                                                                            lại trải nghiệm thanh toán tiện lợi.</p>
                                                                        <ul class="list-unstyled">
                                                                            <li class="d-flex mb-2">
                                                                                <i
                                                                                    class="fas fa-check-circle text-success me-2 mt-1"></i>
                                                                                <span>Thanh toán đa kênh linh
                                                                                    hoạt.</span>
                                                                            </li>
                                                                            <li class="d-flex mb-2">
                                                                                <i
                                                                                    class="fas fa-check-circle text-success me-2 mt-1"></i>
                                                                                <span>Giao dịch được xử lý an toàn và
                                                                                    nhanh chóng.</span>
                                                                            </li>
                                                                            <li class="d-flex mb-2">
                                                                                <i
                                                                                    class="fas fa-check-circle text-success me-2 mt-1"></i>
                                                                                <span>Dễ dàng tích hợp và tùy chỉnh theo
                                                                                    yêu cầu.</span>
                                                                            </li>
                                                                        </ul>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <!-- Cột demo và giá bán -->
                                                            <div class="col-lg-6">
                                                                <div class="card border-0 shadow-hover">
                                                                    <div
                                                                        class="card-header bg-transparent border-bottom py-3">
                                                                        <h6 class="mb-0 fw-semibold">
                                                                            <i
                                                                                class="fas fa-play-circle text-primary me-2"></i>
                                                                            Demo & Giá Bán
                                                                        </h6>
                                                                    </div>
                                                                    <div class="card-body">
                                                                        <!-- Phần demo ảnh -->
                                                                        <div class="position-relative overflow-hidden rounded-3"
                                                                            style="padding-top: 56.25%;">
                                                                            <img src="https://i.imgur.com/O9QQRc5.png"
                                                                                class="position-absolute top-0 start-0 w-100 h-100 object-fit-cover"
                                                                                alt="Demo Addon Korapay">
                                                                        </div>

                                                                        <!-- Phần giá bán -->
                                                                        <div class="mt-4 pt-3 border-top">
                                                                            <div
                                                                                class="d-flex align-items-center justify-content-between">
                                                                                <div>
                                                                                    <h6 class="fw-semibold mb-1">Giá bán
                                                                                        Addon</h6>
                                                                                    <small class="text-muted">Bản quyền
                                                                                        vĩnh viễn. Hỗ trợ cấu hình API
                                                                                        miễn phí cho lần đầu, các lần hỗ
                                                                                        trợ sau tính phí 300.000đ 1
                                                                                        lần.</small>
                                                                                </div>
                                                                                <div class="text-end">
                                                                                    <div
                                                                                        class="fs-5 fw-bold text-primary">
                                                                                        1.200.000đ</div>
                                                                                    <small
                                                                                        class="text-danger text-decoration-line-through">1.500.000đ</small>
                                                                                </div>
                                                                            </div>
                                                                            <!-- Nút mua hàng tích hợp thanh toán qua Korapay -->
                                                                            <a href="https://client.cmsnt.co/cart.php?a=add&pid=79"
                                                                                target="_blank"
                                                                                class="btn btn-primary w-100 mt-3">
                                                                                <i
                                                                                    class="fas fa-shopping-cart me-2"></i>
                                                                                Mua Ngay
                                                                            </a>
                                                                            <div class="text-center mt-2">
                                                                                <small class="text-muted">
                                                                                    <i
                                                                                        class="fas fa-shield-alt me-1"></i>
                                                                                    Thanh toán an toàn và tự động 24/7
                                                                                </small>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="accordion-item border-0 mb-3">
                                                <h2 class="accordion-header" id="headingTmweasyapi">
                                                    <button
                                                        class="accordion-button collapsed bg-gradient-primary-hover shadow-sm"
                                                        type="button" data-bs-toggle="collapse"
                                                        data-bs-target="#collapseTmweasyapi" aria-expanded="false"
                                                        aria-controls="collapseTmweasyapi">
                                                        <div class="d-flex align-items-center w-100">
                                                            <div class="me-3 bg-primary text-white rounded-circle p-2 d-flex align-items-center justify-content-center"
                                                                style="width: 36px; height: 36px;">
                                                                <i class="fas fa-globe-asia fs-5"></i>
                                                            </div>
                                                            <div class="flex-grow-1">
                                                                <h5 class="mb-0 fw-semibold text-dark">
                                                                    Tích Hợp Thanh Toán Qua Tmweasyapi (Thailand)
                                                                </h5>
                                                                <small class="text-muted">
                                                                    Hỗ trợ đa kênh thanh toán tại Thái Lan: Bank,
                                                                    PromptPay, e-Wallet, TrueMoney Wallet
                                                                </small>
                                                            </div>
                                                        </div>
                                                    </button>
                                                </h2>
                                                <div id="collapseTmweasyapi"
                                                    class="accordion-collapse collapse border-top"
                                                    aria-labelledby="headingTmweasyapi"
                                                    data-bs-parent="#accordionAddons">
                                                    <div class="accordion-body pt-4">
                                                        <!-- NỘI DUNG CHI TIẾT -->
                                                        <div class="row g-4">
                                                            <!-- Cột mô tả addon -->
                                                            <div class="col-lg-6">
                                                                <div class="card border-0 shadow-hover">
                                                                    <div
                                                                        class="card-header bg-transparent border-bottom py-3">
                                                                        <h6 class="mb-0 fw-semibold">
                                                                            <i
                                                                                class="fas fa-info-circle text-info me-2"></i>
                                                                            Mô Tả Addon
                                                                        </h6>
                                                                    </div>
                                                                    <div class="card-body">
                                                                        <p>
                                                                            Addon này cho phép bạn tích hợp cổng thanh
                                                                            toán
                                                                            <strong>Tmweasyapi (Thailand)</strong>
                                                                            trực tiếp vào website của mình. Khách hàng
                                                                            của bạn
                                                                            có thể thanh toán qua nhiều kênh như ngân
                                                                            hàng nội địa Thái Lan,
                                                                            PromptPay QR code, TrueMoney, và ví điện tử
                                                                            khác.
                                                                            Giao dịch được xử lý an toàn và nhanh chóng,
                                                                            mang lại trải nghiệm thanh toán tiện lợi.
                                                                        </p>
                                                                        <ul class="list-unstyled">
                                                                            <li class="d-flex mb-2">
                                                                                <i
                                                                                    class="fas fa-check-circle text-success me-2 mt-1"></i>
                                                                                <span>Hỗ trợ thanh toán qua PromptPay,
                                                                                    TrueMoney Wallet, mobile
                                                                                    banking.</span>
                                                                            </li>
                                                                            <li class="d-flex mb-2">
                                                                                <i
                                                                                    class="fas fa-check-circle text-success me-2 mt-1"></i>
                                                                                <span>Giao dịch bảo mật, xác nhận qua
                                                                                    API tự động.</span>
                                                                            </li>
                                                                            <li class="d-flex mb-2">
                                                                                <i
                                                                                    class="fas fa-check-circle text-success me-2 mt-1"></i>
                                                                                <span>Dễ dàng triển khai, quản lý các
                                                                                    giao dịch.</span>
                                                                            </li>
                                                                        </ul>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <!-- Cột demo và giá bán -->
                                                            <div class="col-lg-6">
                                                                <div class="card border-0 shadow-hover">
                                                                    <div
                                                                        class="card-header bg-transparent border-bottom py-3">
                                                                        <h6 class="mb-0 fw-semibold">
                                                                            <i
                                                                                class="fas fa-play-circle text-primary me-2"></i>
                                                                            Demo & Giá Bán
                                                                        </h6>
                                                                    </div>
                                                                    <div class="card-body">
                                                                        <!-- Phần demo ảnh -->
                                                                        <div class="position-relative overflow-hidden rounded-3"
                                                                            style="padding-top: 56.25%;">
                                                                            <img src="https://i.imgur.com/8rnKeuE.png"
                                                                                class="position-absolute top-0 start-0 w-100 h-100 object-fit-cover"
                                                                                alt="Demo Addon Tmweasyapi">
                                                                        </div>
                                                                        <!-- Phần giá bán -->
                                                                        <div class="mt-4 pt-3 border-top">
                                                                            <div
                                                                                class="d-flex align-items-center justify-content-between">
                                                                                <div>
                                                                                    <h6 class="fw-semibold mb-1">Giá bán
                                                                                        Addon</h6>
                                                                                    <small class="text-muted">
                                                                                        Bản quyền vĩnh viễn. Cấu hình
                                                                                        API hộ miễn phí lần đầu, lần thứ
                                                                                        2 sẽ tính phí 300.000đ / lần cấu
                                                                                        hình hộ.
                                                                                    </small>
                                                                                </div>
                                                                                <div class="text-end">
                                                                                    <div
                                                                                        class="fs-5 fw-bold text-primary">
                                                                                        1.200.000đ</div>
                                                                                    <small
                                                                                        class="text-danger text-decoration-line-through">1.500.000đ</small>
                                                                                </div>
                                                                            </div>
                                                                            <!-- Nút mua hàng -->
                                                                            <a href="https://client.cmsnt.co/cart.php?a=add&pid=80"
                                                                                target="_blank"
                                                                                class="btn btn-primary w-100 mt-3">
                                                                                <i
                                                                                    class="fas fa-shopping-cart me-2"></i>
                                                                                Mua Ngay
                                                                            </a>
                                                                            <div class="text-center mt-2">
                                                                                <small class="text-muted">
                                                                                    <i
                                                                                        class="fas fa-shield-alt me-1"></i>
                                                                                    Thanh toán an toàn và tự động 24/7
                                                                                </small>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div><!-- /.row -->
                                                    </div><!-- /.accordion-body -->
                                                </div><!-- /.accordion-collapse -->
                                            </div><!-- /.accordion-item -->

                                            <div class="accordion-item border-0 mb-3">
                                                <h2 class="accordion-header" id="headingOpenPix">
                                                    <button
                                                        class="accordion-button collapsed bg-gradient-primary-hover shadow-sm"
                                                        type="button" data-bs-toggle="collapse"
                                                        data-bs-target="#collapseOpenPix" aria-expanded="false"
                                                        aria-controls="collapseOpenPix">
                                                        <div class="d-flex align-items-center w-100">
                                                            <div class="me-3 bg-primary text-white rounded-circle p-2 d-flex align-items-center justify-content-center"
                                                                style="width: 36px; height: 36px;">
                                                                <i class="fas fa-globe fs-5"></i>
                                                            </div>
                                                            <div class="flex-grow-1">
                                                                <h5 class="mb-0 fw-semibold text-dark">
                                                                    Tích Hợp Thanh Toán Qua OpenPix (Brazil)
                                                                </h5>
                                                                <small class="text-muted">
                                                                    Hỗ trợ thanh toán nhanh chóng và an toàn qua OpenPix
                                                                </small>
                                                            </div>
                                                        </div>
                                                    </button>
                                                </h2>
                                                <div id="collapseOpenPix" class="accordion-collapse collapse border-top"
                                                    aria-labelledby="headingOpenPix" data-bs-parent="#accordionAddons">
                                                    <div class="accordion-body pt-4">
                                                        <!-- NỘI DUNG CHI TIẾT -->
                                                        <div class="row g-4">
                                                            <!-- Cột mô tả addon -->
                                                            <div class="col-lg-6">
                                                                <div class="card border-0 shadow-hover">
                                                                    <div
                                                                        class="card-header bg-transparent border-bottom py-3">
                                                                        <h6 class="mb-0 fw-semibold">
                                                                            <i
                                                                                class="fas fa-info-circle text-info me-2"></i>
                                                                            Mô Tả Addon
                                                                        </h6>
                                                                    </div>
                                                                    <div class="card-body">
                                                                        <p>
                                                                            Addon này cho phép bạn tích hợp cổng thanh
                                                                            toán
                                                                            <strong>OpenPix</strong>
                                                                            trực tiếp vào website của mình. Khách hàng
                                                                            của bạn
                                                                            có thể thanh toán một cách nhanh chóng và
                                                                            an toàn.
                                                                            Giao dịch được xử lý an toàn và nhanh chóng,
                                                                            mang lại trải nghiệm thanh toán tiện lợi.
                                                                        </p>
                                                                        <ul class="list-unstyled">
                                                                            <li class="d-flex mb-2">
                                                                                <i
                                                                                    class="fas fa-check-circle text-success me-2 mt-1"></i>
                                                                                <span>Hỗ trợ thanh toán nhanh chóng qua
                                                                                    OpenPix.</span>
                                                                            </li>
                                                                            <li class="d-flex mb-2">
                                                                                <i
                                                                                    class="fas fa-check-circle text-success me-2 mt-1"></i>
                                                                                <span>Giao dịch bảo mật, xác nhận qua
                                                                                    API tự động.</span>
                                                                            </li>
                                                                            <li class="d-flex mb-2">
                                                                                <i
                                                                                    class="fas fa-check-circle text-success me-2 mt-1"></i>
                                                                                <span>Dễ dàng triển khai, quản lý các
                                                                                    giao dịch.</span>
                                                                            </li>
                                                                        </ul>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <!-- Cột demo và giá bán -->
                                                            <div class="col-lg-6">
                                                                <div class="card border-0 shadow-hover">
                                                                    <div
                                                                        class="card-header bg-transparent border-bottom py-3">
                                                                        <h6 class="mb-0 fw-semibold">
                                                                            <i
                                                                                class="fas fa-play-circle text-primary me-2"></i>
                                                                            Demo & Giá Bán
                                                                        </h6>
                                                                    </div>
                                                                    <div class="card-body">
                                                                        <!-- Phần demo ảnh -->
                                                                        <div class="position-relative overflow-hidden rounded-3"
                                                                            style="padding-top: 56.25%;">
                                                                            <img src="https://i.imgur.com/YBkHmXi.png"
                                                                                class="position-absolute top-0 start-0 w-100 h-100 object-fit-cover"
                                                                                alt="Demo Addon OpenPix">
                                                                        </div>
                                                                        <!-- Phần giá bán -->
                                                                        <div class="mt-4 pt-3 border-top">
                                                                            <div
                                                                                class="d-flex align-items-center justify-content-between">
                                                                                <div>
                                                                                    <h6 class="fw-semibold mb-1">Giá bán
                                                                                        Addon</h6>
                                                                                    <small class="text-muted">
                                                                                        Bản quyền vĩnh viễn. Cấu hình
                                                                                        API hộ miễn phí lần đầu, lần thứ
                                                                                        2 sẽ tính phí 300.000đ / lần cấu
                                                                                        hình hộ.
                                                                                    </small>
                                                                                </div>
                                                                                <div class="text-end">
                                                                                    <div
                                                                                        class="fs-5 fw-bold text-primary">
                                                                                        1.200.000đ</div>
                                                                                    <small
                                                                                        class="text-danger text-decoration-line-through">1.500.000đ</small>
                                                                                </div>
                                                                            </div>
                                                                            <!-- Nút mua hàng -->
                                                                            <a href="https://client.cmsnt.co/cart.php?a=add&pid=81"
                                                                                target="_blank"
                                                                                class="btn btn-primary w-100 mt-3">
                                                                                <i
                                                                                    class="fas fa-shopping-cart me-2"></i>
                                                                                Mua Ngay
                                                                            </a>
                                                                            <div class="text-center mt-2">
                                                                                <small class="text-muted">
                                                                                    <i
                                                                                        class="fas fa-shield-alt me-1"></i>
                                                                                    Thanh toán an toàn và tự động 24/7
                                                                                </small>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div><!-- /.row -->
                                                    </div><!-- /.accordion-body -->
                                                </div><!-- /.accordion-collapse -->
                                            </div><!-- /.accordion-item -->

                                            <div class="accordion-item border-0 mb-3">
                                                <h2 class="accordion-header" id="headingBakong">
                                                    <button
                                                        class="accordion-button collapsed bg-gradient-primary-hover shadow-sm"
                                                        type="button" data-bs-toggle="collapse"
                                                        data-bs-target="#collapseBakong" aria-expanded="false"
                                                        aria-controls="collapseBakong">
                                                        <div class="d-flex align-items-center w-100">
                                                            <div class="me-3 bg-primary text-white rounded-circle p-2 d-flex align-items-center justify-content-center"
                                                                style="width: 36px; height: 36px;">
                                                                <i class="fas fa-wallet fs-5"></i>
                                                            </div>
                                                            <div class="flex-grow-1">
                                                                <h5 class="mb-0 fw-semibold text-dark">
                                                                    Tích Hợp Thanh Toán Qua Bakong Wallet (Cambodia)
                                                                </h5>
                                                                <small class="text-muted">
                                                                    Hỗ trợ thanh toán nhanh chóng và an toàn qua Bakong Wallet
                                                                </small>
                                                            </div>
                                                        </div>
                                                    </button>
                                                </h2>
                                                <div id="collapseBakong" class="accordion-collapse collapse border-top"
                                                    aria-labelledby="headingBakong" data-bs-parent="#accordionAddons">
                                                    <div class="accordion-body pt-4">
                                                        <!-- NỘI DUNG CHI TIẾT -->
                                                        <div class="row g-4">
                                                            <!-- Cột mô tả addon -->
                                                            <div class="col-lg-6">
                                                                <div class="card border-0 shadow-hover">
                                                                    <div
                                                                        class="card-header bg-transparent border-bottom py-3">
                                                                        <h6 class="mb-0 fw-semibold">
                                                                            <i
                                                                                class="fas fa-info-circle text-info me-2"></i>
                                                                            Mô Tả Addon
                                                                        </h6>
                                                                    </div>
                                                                    <div class="card-body">
                                                                        <p>
                                                                            Addon này cho phép bạn tích hợp cổng thanh
                                                                            toán
                                                                            <strong>Bakong Wallet</strong>
                                                                            trực tiếp vào website của mình. Khách hàng
                                                                            của bạn
                                                                            có thể thanh toán một cách nhanh chóng và
                                                                            an toàn.
                                                                            Giao dịch được xử lý an toàn và nhanh chóng,
                                                                            mang lại trải nghiệm thanh toán tiện lợi.
                                                                        </p>
                                                                        <ul class="list-unstyled">
                                                                            <li class="d-flex mb-2">
                                                                                <i
                                                                                    class="fas fa-check-circle text-success me-2 mt-1"></i>
                                                                                <span>Hỗ trợ thanh toán nhanh chóng qua
                                                                                    Bakong Wallet.</span>
                                                                            </li>
                                                                            <li class="d-flex mb-2">
                                                                                <i
                                                                                    class="fas fa-check-circle text-success me-2 mt-1"></i>
                                                                                <span>Giao dịch bảo mật, xác nhận qua
                                                                                    API tự động.</span>
                                                                            </li>
                                                                            <li class="d-flex mb-2">
                                                                                <i
                                                                                    class="fas fa-check-circle text-success me-2 mt-1"></i>
                                                                                <span>Dễ dàng triển khai, quản lý các
                                                                                    giao dịch.</span>
                                                                            </li>
                                                                        </ul>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <!-- Cột demo và giá bán -->
                                                            <div class="col-lg-6">
                                                                <div class="card border-0 shadow-hover">
                                                                    <div
                                                                        class="card-header bg-transparent border-bottom py-3">
                                                                        <h6 class="mb-0 fw-semibold">
                                                                            <i
                                                                                class="fas fa-play-circle text-primary me-2"></i>
                                                                            Demo & Giá Bán
                                                                        </h6>
                                                                    </div>
                                                                    <div class="card-body">
                                                                        <!-- Phần demo ảnh -->
                                                                        <div class="position-relative overflow-hidden rounded-3"
                                                                            style="padding-top: 56.25%;">
                                                                            <img src="https://i.imgur.com/lyY2Lzp.png"
                                                                                class="position-absolute top-0 start-0 w-100 h-100 object-fit-cover"
                                                                                alt="Demo Addon Bakong">
                                                                        </div>
                                                                        <!-- Phần giá bán -->
                                                                        <div class="mt-4 pt-3 border-top">
                                                                            <div
                                                                                class="d-flex align-items-center justify-content-between">
                                                                                <div>
                                                                                    <h6 class="fw-semibold mb-1">Giá bán
                                                                                        Addon</h6>
                                                                                    <small class="text-muted">
                                                                                        Bản quyền vĩnh viễn. Cấu hình
                                                                                        API hộ miễn phí lần đầu, lần thứ
                                                                                        2 sẽ tính phí 300.000đ / lần cấu
                                                                                        hình hộ.
                                                                                    </small>
                                                                                </div>
                                                                                <div class="text-end">
                                                                                    <div
                                                                                        class="fs-5 fw-bold text-primary">
                                                                                        1.000.000đ</div>
                                                                                    <small
                                                                                        class="text-danger text-decoration-line-through">1.500.000đ</small>
                                                                                </div>
                                                                            </div>
                                                                            <!-- Nút mua hàng -->
                                                                            <a href="https://client.cmsnt.co/cart.php?a=add&pid=82"
                                                                                target="_blank"
                                                                                class="btn btn-primary w-100 mt-3">
                                                                                <i
                                                                                    class="fas fa-shopping-cart me-2"></i>
                                                                                Mua Ngay
                                                                            </a>
                                                                            <div class="text-center mt-2">
                                                                                <small class="text-muted">
                                                                                    <i
                                                                                        class="fas fa-shield-alt me-1"></i>
                                                                                    Thanh toán an toàn và tự động 24/7
                                                                                </small>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div><!-- /.row -->
                                                    </div><!-- /.accordion-body -->
                                                </div><!-- /.accordion-collapse -->
                                            </div><!-- /.accordion-item -->

                                            <!-- ADDON POCKETFI -->
                                            <div class="accordion-item border-0 mb-3">
                                                <h2 class="accordion-header" id="headingPocketfi">
                                                    <button
                                                        class="accordion-button collapsed bg-gradient-primary-hover shadow-sm"
                                                        type="button" data-bs-toggle="collapse"
                                                        data-bs-target="#collapsePocketfi" aria-expanded="false"
                                                        aria-controls="collapsePocketfi">
                                                        <div class="d-flex align-items-center w-100">
                                                            <div class="me-3 bg-success text-white rounded-circle p-2 d-flex align-items-center justify-content-center"
                                                                style="width: 36px; height: 36px;">
                                                                <i class="fas fa-wallet fs-5"></i>
                                                            </div>
                                                            <div class="flex-grow-1">
                                                                <h5 class="mb-0 fw-semibold text-dark">
                                                                    Tích Hợp Thanh Toán Qua PocketFi (Nigeria)
                                                                </h5>
                                                                <small class="text-muted">
                                                                    Hỗ trợ thanh toán nhanh chóng và an toàn qua PocketFi tại Nigeria
                                                                </small>
                                                            </div>
                                                        </div>
                                                    </button>
                                                </h2>
                                                <div id="collapsePocketfi" class="accordion-collapse collapse border-top"
                                                    aria-labelledby="headingPocketfi" data-bs-parent="#accordionAddons">
                                                    <div class="accordion-body pt-4">
                                                        <!-- NỘI DUNG CHI TIẾT -->
                                                        <div class="row g-4">
                                                            <!-- Cột mô tả addon -->
                                                            <div class="col-lg-6">
                                                                <div class="card border-0 shadow-hover">
                                                                    <div
                                                                        class="card-header bg-transparent border-bottom py-3">
                                                                        <h6 class="mb-0 fw-semibold">
                                                                            <i
                                                                                class="fas fa-info-circle text-info me-2"></i>
                                                                            Mô Tả Addon
                                                                        </h6>
                                                                    </div>
                                                                    <div class="card-body">
                                                                        <p>
                                                                            Addon này cho phép bạn tích hợp cổng thanh
                                                                            toán
                                                                            <strong>PocketFi (Nigeria)</strong>
                                                                            trực tiếp vào website của mình. Khách hàng
                                                                            của bạn
                                                                            có thể thanh toán một cách nhanh chóng và
                                                                            an toàn thông qua nhiều kênh thanh toán phổ biến tại Nigeria.
                                                                            Giao dịch được xử lý an toàn và nhanh chóng,
                                                                            mang lại trải nghiệm thanh toán tiện lợi.
                                                                        </p>
                                                                        <ul class="list-unstyled">
                                                                            <li class="d-flex mb-2">
                                                                                <i
                                                                                    class="fas fa-check-circle text-success me-2 mt-1"></i>
                                                                                <span>Hỗ trợ thanh toán nhanh chóng qua
                                                                                    PocketFi.</span>
                                                                            </li>
                                                                            <li class="d-flex mb-2">
                                                                                <i
                                                                                    class="fas fa-check-circle text-success me-2 mt-1"></i>
                                                                                <span>Giao dịch bảo mật, xác nhận qua
                                                                                    API tự động.</span>
                                                                            </li>
                                                                            <li class="d-flex mb-2">
                                                                                <i
                                                                                    class="fas fa-check-circle text-success me-2 mt-1"></i>
                                                                                <span>Dễ dàng triển khai, quản lý các
                                                                                    giao dịch.</span>
                                                                            </li>
                                                                        </ul>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <!-- Cột demo và giá bán -->
                                                            <div class="col-lg-6">
                                                                <div class="card border-0 shadow-hover">
                                                                    <div
                                                                        class="card-header bg-transparent border-bottom py-3">
                                                                        <h6 class="mb-0 fw-semibold">
                                                                            <i
                                                                                class="fas fa-play-circle text-primary me-2"></i>
                                                                            Demo & Giá Bán
                                                                        </h6>
                                                                    </div>
                                                                    <div class="card-body">
                                                                        <!-- Phần demo ảnh -->
                                                                        <div class="position-relative overflow-hidden rounded-3"
                                                                            style="padding-top: 56.25%;">
                                                                            <img src="https://i.postimg.cc/mDf9Fyt4/A-nh-ma-n-hi-nh-2025-12-23-lu-c-22-36-12.png"
                                                                                class="position-absolute top-0 start-0 w-100 h-100 object-fit-contain bg-light p-4"
                                                                                alt="Demo Addon PocketFi">
                                                                        </div>
                                                                        <!-- Phần giá bán -->
                                                                        <div class="mt-4 pt-3 border-top">
                                                                            <div
                                                                                class="d-flex align-items-center justify-content-between">
                                                                                <div>
                                                                                    <h6 class="fw-semibold mb-1">Giá bán
                                                                                        Addon</h6>
                                                                                    <small class="text-muted">
                                                                                        Bản quyền vĩnh viễn. Cấu hình
                                                                                        API hộ miễn phí lần đầu, lần thứ
                                                                                        2 sẽ tính phí 300.000đ / lần cấu
                                                                                        hình hộ.
                                                                                    </small>
                                                                                </div>
                                                                                <div class="text-end">
                                                                                    <div
                                                                                        class="fs-5 fw-bold text-primary">
                                                                                        1.200.000đ</div>
                                                                                    <small
                                                                                        class="text-danger text-decoration-line-through">1.500.000đ</small>
                                                                                </div>
                                                                            </div>
                                                                            <!-- Nút mua hàng -->
                                                                            <a href="https://client.cmsnt.co/cart.php?a=add&pid=96"
                                                                                target="_blank"
                                                                                class="btn btn-success w-100 mt-3">
                                                                                <i
                                                                                    class="fas fa-shopping-cart me-2"></i>
                                                                                Mua Ngay
                                                                            </a>
                                                                            <div class="text-center mt-2">
                                                                                <small class="text-muted">
                                                                                    <i
                                                                                        class="fas fa-shield-alt me-1"></i>
                                                                                    Thanh toán an toàn và tự động 24/7
                                                                                </small>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div><!-- /.row -->
                                                    </div><!-- /.accordion-body -->
                                                </div><!-- /.accordion-collapse -->
                                            </div><!-- /.accordion-item -->

                                            <!-- ADDON PAYMENTPOINT -->
                                            <div class="accordion-item border-0 mb-3">
                                                <h2 class="accordion-header" id="headingPaymentpoint">
                                                    <button
                                                        class="accordion-button collapsed bg-gradient-primary-hover shadow-sm"
                                                        type="button" data-bs-toggle="collapse"
                                                        data-bs-target="#collapsePaymentpoint" aria-expanded="false"
                                                        aria-controls="collapsePaymentpoint">
                                                        <div class="d-flex align-items-center w-100">
                                                            <div class="me-3 bg-primary text-white rounded-circle p-2 d-flex align-items-center justify-content-center"
                                                                style="width: 36px; height: 36px;">
                                                                <i class="fas fa-credit-card fs-5"></i>
                                                            </div>
                                                            <div class="flex-grow-1">
                                                                <h5 class="mb-0 fw-semibold text-dark">
                                                                    Tích Hợp Thanh Toán Qua PaymentPoint (Nigeria)
                                                                </h5>
                                                                <small class="text-muted">
                                                                    Hỗ trợ thanh toán qua tài khoản ảo Virtual Account tại Nigeria
                                                                </small>
                                                            </div>
                                                        </div>
                                                    </button>
                                                </h2>
                                                <div id="collapsePaymentpoint" class="accordion-collapse collapse border-top"
                                                    aria-labelledby="headingPaymentpoint" data-bs-parent="#accordionAddons">
                                                    <div class="accordion-body pt-4">
                                                        <!-- NỘI DUNG CHI TIẾT -->
                                                        <div class="row g-4">
                                                            <!-- Cột mô tả addon -->
                                                            <div class="col-lg-6">
                                                                <div class="card border-0 shadow-hover">
                                                                    <div
                                                                        class="card-header bg-transparent border-bottom py-3">
                                                                        <h6 class="mb-0 fw-semibold">
                                                                            <i
                                                                                class="fas fa-info-circle text-info me-2"></i>
                                                                            Mô Tả Addon
                                                                        </h6>
                                                                    </div>
                                                                    <div class="card-body">
                                                                        <p>
                                                                            Addon này cho phép bạn tích hợp cổng thanh
                                                                            toán
                                                                            <strong>PaymentPoint (Nigeria)</strong>
                                                                            trực tiếp vào website của mình. Khách hàng
                                                                            của bạn
                                                                            có thể tạo tài khoản ảo (Virtual Account) và
                                                                            chuyển khoản qua các ngân hàng như PalmPay, OPay.
                                                                            Giao dịch được xử lý an toàn và nhanh chóng qua webhook.
                                                                        </p>
                                                                        <ul class="list-unstyled">
                                                                            <li class="d-flex mb-2">
                                                                                <i
                                                                                    class="fas fa-check-circle text-success me-2 mt-1"></i>
                                                                                <span>Tạo tài khoản ảo Virtual Account tự động.</span>
                                                                            </li>
                                                                            <li class="d-flex mb-2">
                                                                                <i
                                                                                    class="fas fa-check-circle text-success me-2 mt-1"></i>
                                                                                <span>Hỗ trợ nhiều ngân hàng: PalmPay, OPay.</span>
                                                                            </li>
                                                                            <li class="d-flex mb-2">
                                                                                <i
                                                                                    class="fas fa-check-circle text-success me-2 mt-1"></i>
                                                                                <span>Xác nhận giao dịch tự động qua webhook.</span>
                                                                            </li>
                                                                        </ul>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <!-- Cột demo và giá bán -->
                                                            <div class="col-lg-6">
                                                                <div class="card border-0 shadow-hover">
                                                                    <div
                                                                        class="card-header bg-transparent border-bottom py-3">
                                                                        <h6 class="mb-0 fw-semibold">
                                                                            <i
                                                                                class="fas fa-play-circle text-primary me-2"></i>
                                                                            Demo & Giá Bán
                                                                        </h6>
                                                                    </div>
                                                                    <div class="card-body">
                                                                        <!-- Phần demo ảnh -->
                                                                        <div class="position-relative overflow-hidden rounded-3"
                                                                            style="padding-top: 56.25%;">
                                                                            <img src="https://i.postimg.cc/jqNRS5pX/A-nh-ma-n-hi-nh-2026-01-02-lu-c-12-41-39.png"
                                                                                class="position-absolute top-0 start-0 w-100 h-100 object-fit-contain bg-light p-4"
                                                                                alt="Demo Addon PaymentPoint">
                                                                        </div>
                                                                        <!-- Phần giá bán -->
                                                                        <div class="mt-4 pt-3 border-top">
                                                                            <div
                                                                                class="d-flex align-items-center justify-content-between">
                                                                                <div>
                                                                                    <h6 class="fw-semibold mb-1">Giá bán
                                                                                        Addon</h6>
                                                                                    <small class="text-muted">
                                                                                        Bản quyền vĩnh viễn. Cấu hình
                                                                                        API hộ miễn phí lần đầu, lần thứ
                                                                                        2 sẽ tính phí 300.000đ / lần cấu
                                                                                        hình hộ.
                                                                                    </small>
                                                                                </div>
                                                                                <div class="text-end">
                                                                                    <div
                                                                                        class="fs-5 fw-bold text-primary">
                                                                                        1.200.000đ</div>
                                                                                    <small
                                                                                        class="text-danger text-decoration-line-through">1.500.000đ</small>
                                                                                </div>
                                                                            </div>
                                                                            <!-- Nút mua hàng -->
                                                                            <a href="https://client.cmsnt.co/cart.php?a=add&pid=97"
                                                                                target="_blank"
                                                                                class="btn btn-success w-100 mt-3">
                                                                                <i
                                                                                    class="fas fa-shopping-cart me-2"></i>
                                                                                Mua Ngay
                                                                            </a>
                                                                            <div class="text-center mt-2">
                                                                                <small class="text-muted">
                                                                                    <i
                                                                                        class="fas fa-shield-alt me-1"></i>
                                                                                    Thanh toán an toàn và tự động 24/7
                                                                                </small>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div><!-- /.row -->
                                                    </div><!-- /.accordion-body -->
                                                </div><!-- /.accordion-collapse -->
                                            </div><!-- /.accordion-item -->


                                            <!-- CÓ THỂ THÊM NHIỀU ACCORDION-ITEM NỮA CHO CÁC ADDON KHÁC -->
                                            <!-- Addon Sắp Ra Mắt -->
                                            <div class="accordion-item border-0 mb-3">
                                                <h2 class="accordion-header" id="headingTwo">
                                                    <button
                                                        class="accordion-button collapsed bg-gradient-primary-hover shadow-sm"
                                                        type="button" data-bs-toggle="collapse"
                                                        data-bs-target="#collapseTwo" aria-expanded="false"
                                                        aria-controls="collapseTwo">
                                                        <div class="d-flex align-items-center w-100">
                                                            <div class="me-3 bg-secondary text-white rounded-circle p-2 d-flex align-items-center justify-content-center"
                                                                style="width: 36px; height: 36px;">
                                                                <i class="fas fa-hourglass-half fs-5"></i>
                                                            </div>
                                                            <div class="flex-grow-1">
                                                                <h5 class="mb-0 fw-semibold text-dark">Addon Sắp Ra Mắt
                                                                </h5>
                                                                <small class="text-muted">Tính năng mới đang trong giai
                                                                    đoạn phát triển</small>
                                                            </div>
                                                        </div>
                                                    </button>
                                                </h2>
                                                <div id="collapseTwo" class="accordion-collapse collapse border-top"
                                                    aria-labelledby="headingTwo" data-bs-parent="#accordionAddons">
                                                    <div class="accordion-body pt-4">
                                                        <div class="row g-4">
                                                            <!-- Cột thông tin -->
                                                            <div class="col-lg-6">
                                                                <div class="card border-0 shadow-hover">
                                                                    <div
                                                                        class="card-header bg-transparent border-bottom py-3">
                                                                        <h6 class="mb-0 fw-semibold">
                                                                            <i
                                                                                class="fas fa-info-circle text-info me-2"></i>
                                                                            Thông Tin Sắp Ra Mắt
                                                                        </h6>
                                                                    </div>
                                                                    <div class="card-body">
                                                                        <div class="text-center py-5">
                                                                            <i
                                                                                class="fas fa-tools fa-3x text-secondary mb-3"></i>
                                                                            <h5 class="fw-semibold mb-3">Tính Năng Đang
                                                                                Phát Triển</h5>
                                                                            <p class="text-muted mb-4">
                                                                                Chúng tôi đang nỗ lực hoàn thiện tính
                                                                                năng mới để mang đến trải nghiệm tốt
                                                                                nhất cho bạn.
                                                                            </p>
                                                                            <div class="progress mb-4"
                                                                                style="height: 8px;">
                                                                                <div class="progress-bar progress-bar-striped progress-bar-animated"
                                                                                    role="progressbar"
                                                                                    style="width: 75%"
                                                                                    aria-valuenow="75" aria-valuemin="0"
                                                                                    aria-valuemax="100"></div>
                                                                            </div>
                                                                            <small class="text-muted">Tiến độ phát
                                                                                triển: 75%</small>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <!-- Cột thông báo -->
                                                            <div class="col-lg-6">
                                                                <div class="card border-0 shadow-hover">
                                                                    <div
                                                                        class="card-header bg-transparent border-bottom py-3">
                                                                        <h6 class="mb-0 fw-semibold">
                                                                            <i
                                                                                class="fas fa-bell text-warning me-2"></i>
                                                                            Nhận Thông Báo Sớm Nhất
                                                                        </h6>
                                                                    </div>
                                                                    <div class="card-body">
                                                                        <form class="needs-validation" novalidate>
                                                                            <div class="mb-3">
                                                                                <label class="form-label fw-semibold">
                                                                                    <i
                                                                                        class="fas fa-envelope me-1 text-primary"></i>
                                                                                    Email của bạn
                                                                                </label>
                                                                                <input type="email"
                                                                                    class="form-control shadow-sm"
                                                                                    placeholder="Nhập email để nhận thông báo"
                                                                                    required>
                                                                                <div class="invalid-feedback">
                                                                                    Vui lòng nhập email hợp lệ
                                                                                </div>
                                                                            </div>

                                                                            <div class="mb-3">
                                                                                <label class="form-label fw-semibold">
                                                                                    <i
                                                                                        class="fas fa-mobile-alt me-1 text-success"></i>
                                                                                    Số điện thoại (tuỳ chọn)
                                                                                </label>
                                                                                <input type="tel"
                                                                                    class="form-control shadow-sm"
                                                                                    placeholder="Nhập số điện thoại">
                                                                            </div>

                                                                            <button type="submit"
                                                                                class="btn btn-primary w-100 shadow-sm">
                                                                                <i class="fas fa-bell me-2"></i>
                                                                                Đăng Ký Nhận Thông Báo
                                                                            </button>
                                                                        </form>

                                                                        <div class="alert alert-info mt-3 mb-0">
                                                                            <i class="fas fa-info-circle me-2"></i>
                                                                            Chúng tôi sẽ thông báo ngay khi tính năng
                                                                            này ra mắt
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>


                                        </div><!-- End accordion -->
                                    </div>

                                    <div class="tab-pane text-muted" id="cron-jobs" role="tabpanel">
                                        <h4><?= __('Cron Jobs'); ?></h4>
                                        <div class="alert alert-info border-0 mb-4">
                                            <div class="d-flex align-items-center">
                                                <div class="me-2">
                                                    <i class="ri-information-line fs-16"></i>
                                                </div>
                                                <div>
                                                    <strong><?= __('Hướng dẫn:'); ?></strong> <?= __('Thiết lập các Cron Jobs sau trên hosting/server để hệ thống hoạt động tự động. Nhấn nút Copy để sao chép link.'); ?>
                                                    <br><small><?= __('Tham khảo hướng dẫn chi tiết tại:'); ?> <a href="https://help.cmsnt.co/huong-dan/huong-dan-xu-ly-khi-website-bao-loi-cron/" target="_blank" class="text-primary"><?= __('CMSNT Help'); ?></a></small>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-12">
                                                <div class="card custom-card">
                                                    <div class="card-body">
                                                        <div class="table-responsive">
                                                            <table class="table table-bordered">
                                                                <thead>
                                                                    <tr>
                                                                        <th width="25%"><?= __('Tên Cron Job'); ?></th>
                                                                        <th width="35%"><?= __('Đường dẫn'); ?></th>
                                                                        <th width="15%" class="text-center"><?= __('Thời gian khuyến nghị'); ?></th>
                                                                        <th width="15%"><?= __('Lần chạy cuối'); ?></th>
                                                                        <th width="10%" class="text-center"><?= __('Thao tác'); ?></th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php
                                                                    // Danh sách tất cả các setting cho cron jobs cần kiểm tra
                                                                    $cronSettings = [
                                                                        'check_time_cron_cron',
                                                                        'check_time_cron_bank',
                                                                        'check_time_cron_momo',
                                                                        'check_time_cron_thesieure',
                                                                        'check_time_cron_task',
                                                                        'check_time_cron_sending_email',
                                                                        'time_cron_checklive_gmail',
                                                                        'time_cron_checklive_hotmail',
                                                                        'time_cron_checklive_clone',
                                                                        'time_cron_checklive_via',
                                                                        'time_cron_checklive_instagram',
                                                                        'check_time_cron_gmail',
                                                                        'check_time_cron_hotmail',
                                                                        'check_time_cron_clone',
                                                                        'check_time_cron_via',
                                                                        'check_time_cron_instagram'
                                                                    ];

                                                                    // Kiểm tra và tạo các setting nếu chưa tồn tại
                                                                    foreach ($cronSettings as $setting) {
                                                                        if ($CMSNT->num_rows_safe(" SELECT * FROM `settings` WHERE `name` = ? ", [$setting]) == 0) {
                                                                            $CMSNT->insert("settings", [
                                                                                'name'  => $setting,
                                                                                'value' => '0'
                                                                            ]);
                                                                        }
                                                                    }

                                                                    // Nhóm cron jobs theo loại
                                                                    $cronGroups = [
                                                                        'general' => [
                                                                            'title' => __('Cron Job Chung'),
                                                                            'jobs' => [
                                                                                [
                                                                                    'name' => __('Cron Job Chính'),
                                                                                    'description' => __('Bắt buộc CRON để hệ thống xử lý tự động'),
                                                                                    'path' => 'cron/cron.php',
                                                                                    'recommended_time' => __('5 phút'),
                                                                                    'setting_name' => 'check_time_cron_cron'
                                                                                ],
                                                                                [
                                                                                    'name' => __('Cron Job Bank'),
                                                                                    'description' => __('Xử lý nạp tiền tự động qua ngân hàng'),
                                                                                    'path' => 'cron/bank.php',
                                                                                    'recommended_time' => __('1 phút'),
                                                                                    'setting_name' => 'check_time_cron_bank'
                                                                                ],
                                                                                [
                                                                                    'name' => __('Cron Job MOMO'),
                                                                                    'description' => __('Xử lý nạp tiền tự động qua MOMO'),
                                                                                    'path' => 'cron/momo.php',
                                                                                    'recommended_time' => __('1 phút'),
                                                                                    'setting_name' => 'check_time_cron_momo'
                                                                                ],
                                                                                [
                                                                                    'name' => __('Cron Job Thesieure'),
                                                                                    'description' => __('Xử lý nạp tiền tự động qua Thesieure'),
                                                                                    'path' => 'cron/thesieure.php',
                                                                                    'recommended_time' => __('1 phút'),
                                                                                    'setting_name' => 'check_time_cron_thesieure'
                                                                                ],
                                                                                [
                                                                                    'name' => __('Task Automation'),
                                                                                    'description' => __('Xử lý các tác vụ tự động'),
                                                                                    'path' => 'cron/task.php',
                                                                                    'recommended_time' => __('5 phút'),
                                                                                    'setting_name' => 'check_time_cron_task'
                                                                                ],
                                                                                [
                                                                                    'name' => __('Gửi Email'),
                                                                                    'description' => __('Xử lý gửi email hàng loạt'),
                                                                                    'path' => 'cron/sending_email.php',
                                                                                    'recommended_time' => __('1 phút'),
                                                                                    'setting_name' => 'check_time_cron_sending_email'
                                                                                ]
                                                                            ]
                                                                        ],
                                                                        'checklive' => [
                                                                            'title' => __('Cron Job Check Live'),
                                                                            'jobs' => [
                                                                                [
                                                                                    'name' => __('Check live Gmail'),
                                                                                    'description' => __('Kiểm tra tài khoản Gmail còn sống hay đã chết'),
                                                                                    'path' => 'cron/checklive/gmail.php',
                                                                                    'recommended_time' => __('1 phút'),
                                                                                    'setting_name' => 'time_cron_checklive_gmail'
                                                                                ],
                                                                                [
                                                                                    'name' => __('Check live Hotmail'),
                                                                                    'description' => __('Kiểm tra tài khoản Hotmail còn sống hay đã chết'),
                                                                                    'path' => 'cron/checklive/hotmail.php',
                                                                                    'recommended_time' => __('1 phút'),
                                                                                    'setting_name' => 'time_cron_checklive_hotmail'
                                                                                ],
                                                                                [
                                                                                    'name' => __('Check live Clone'),
                                                                                    'description' => __('Kiểm tra tài khoản Clone còn sống hay đã chết'),
                                                                                    'path' => 'cron/checklive/clone.php',
                                                                                    'recommended_time' => __('1 phút'),
                                                                                    'setting_name' => 'time_cron_checklive_clone'
                                                                                ],
                                                                                [
                                                                                    'name' => __('Check live Via'),
                                                                                    'description' => __('Kiểm tra tài khoản Via còn sống hay đã chết'),
                                                                                    'path' => 'cron/checklive/via.php',
                                                                                    'recommended_time' => __('1 phút'),
                                                                                    'setting_name' => 'time_cron_checklive_via'
                                                                                ],
                                                                                [
                                                                                    'name' => __('Check live Instagram'),
                                                                                    'description' => __('Kiểm tra tài khoản Instagram còn sống hay đã chết'),
                                                                                    'path' => 'cron/checklive/instagram.php',
                                                                                    'recommended_time' => __('1 phút'),
                                                                                    'setting_name' => 'time_cron_checklive_instagram'
                                                                                ]
                                                                            ]
                                                                        ],
                                                                        'suppliers' => [
                                                                            'title' => __('Cron Job Suppliers API'),
                                                                            'jobs' => []
                                                                        ]
                                                                    ];

                                                                    // Lấy danh sách tất cả các file cron suppliers
                                                                    $supplierFiles = glob(__DIR__ . '/../../cron/suppliers/*.php');
                                                                    foreach ($supplierFiles as $file) {
                                                                        $fileName = basename($file);
                                                                        if ($fileName == 'index.html') continue;

                                                                        $supplierKey = str_replace('.php', '', $fileName);
                                                                        $settingName = 'time_cron_suppliers_' . $supplierKey;


                                                                        // Lấy tên từ database nếu có
                                                                        $supplierName = strtoupper($supplierKey);

                                                                        $cronGroups['suppliers']['jobs'][] = [
                                                                            'name' => strtoupper($supplierKey),
                                                                            'description' => $supplierName,
                                                                            'path' => 'cron/suppliers/' . $fileName,
                                                                            'recommended_time' => __('1-5 phút'),
                                                                            'setting_name' => $settingName
                                                                        ];
                                                                    }

                                                                    $globalIndex = 0;
                                                                    foreach ($cronGroups as $groupKey => $group):
                                                                        if (empty($group['jobs'])) continue;
                                                                    ?>
                                                                        <!-- Header nhóm -->
                                                                        <tr>
                                                                            <td colspan="5" class="bg-primary text-white">
                                                                                <strong><i class="ri-folder-line"></i> <?= $group['title']; ?></strong>
                                                                            </td>
                                                                        </tr>
                                                                        <?php
                                                                        foreach ($group['jobs'] as $job):
                                                                            $cronUrl = base_url($job['path'] . '?key=' . $CMSNT->site('key_cron_job'));
                                                                            $lastRun = $CMSNT->site($job['setting_name']);
                                                                            $lastRunFormatted = $lastRun && $lastRun > 0 ? timeAgo($lastRun) : __('Chưa chạy');

                                                                            // Kiểm tra cron job chạy trong 5 phút gần nhất (300 giây)
                                                                            $isActive = $lastRun && $lastRun > 0 && (time() - $lastRun) <= 300;
                                                                            $bgColor = $isActive ? 'rgba(25, 135, 84, 0.12)' : 'rgba(220, 53, 69, 0.12)';
                                                                        ?>
                                                                            <tr>
                                                                                <td style="background-color: <?= $bgColor; ?>">
                                                                                    <div>
                                                                                        <strong class="text-primary"><?= $job['name']; ?></strong>
                                                                                        <br><small class="text-muted"><?= $job['description']; ?></small>
                                                                                    </div>
                                                                                </td>
                                                                                <td style="background-color: <?= $bgColor; ?>">
                                                                                    <div class="input-group">
                                                                                        <input type="text" class="form-control form-control-sm"
                                                                                            id="cronUrl<?= $globalIndex; ?>"
                                                                                            value="<?= $cronUrl; ?>"
                                                                                            readonly
                                                                                            style="font-size: 12px;">
                                                                                        <button class="btn btn-outline-secondary btn-sm"
                                                                                            type="button"
                                                                                            onclick="copyToClipboard('cronUrl<?= $globalIndex; ?>')"
                                                                                            title="<?= __('Copy đường dẫn'); ?>">
                                                                                            <i class="ri-file-copy-line"></i>
                                                                                        </button>
                                                                                    </div>
                                                                                </td>
                                                                                <td class="text-center" style="background-color: <?= $bgColor; ?>">
                                                                                    <span class="badge bg-danger"><?= $job['recommended_time']; ?></span>
                                                                                </td>
                                                                                <td style="background-color: <?= $bgColor; ?>">
                                                                                    <small class="<?= ($lastRun && $lastRun > 0) ? 'text-success' : 'text-warning'; ?>">
                                                                                        <?= $lastRunFormatted; ?>
                                                                                    </small>
                                                                                </td>
                                                                                <td class="text-center" style="background-color: <?= $bgColor; ?>">
                                                                                    <a href="<?= $cronUrl; ?>" target="_blank"
                                                                                        class="btn btn-sm btn-primary"
                                                                                        title="<?= __('Chạy thử'); ?>">
                                                                                        <i class="ri-play-line"></i>
                                                                                    </a>
                                                                                </td>
                                                                            </tr>
                                                                    <?php
                                                                            $globalIndex++;
                                                                        endforeach;
                                                                    endforeach;
                                                                    ?>
                                                                </tbody>
                                                            </table>
                                                        </div>

                                                        <div class="mt-4">
                                                            <div class="alert alert-warning border-0">
                                                                <div class="d-flex">
                                                                    <div class="me-2">
                                                                        <i class="ri-alert-line fs-16"></i>
                                                                    </div>
                                                                    <div>
                                                                        <strong><?= __('Lưu ý quan trọng:'); ?></strong>
                                                                        <ul class="mb-0 mt-2">
                                                                            <li><?= __('Cài đặt Cron Job dưới đây để hệ thống tự động xử lý công việc'); ?></li>
                                                                            <li><?= __('Khuyến nghị cấu hình trên máy chủ dùng wget hoặc curl để gọi URL'); ?></li>
                                                                            <li><?= __('Ví dụ: wget -q -O /dev/null'); ?> <code><?= base_url('cron/cron.php?key=' . $CMSNT->site('key_cron_job')); ?></code> <?= __('hoặc curl'); ?> <code><?= base_url('cron/cron.php?key=' . $CMSNT->site('key_cron_job')); ?></code></li>
                                                                            <li><?= __('Thời gian "Lần chạy cuối" sẽ cập nhật sau mỗi lần cron job được thực thi'); ?></li>
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

                                    <script>
                                        // SỬ DỤNG localStorage để nhớ item đang mở
                                        document.addEventListener('DOMContentLoaded', function() {
                                            // Đọc localStorage để xem có item nào cần mở
                                            const activeAddon = localStorage.getItem('activeAddon');
                                            if (activeAddon) {
                                                const collapseElement = document.querySelector(activeAddon);
                                                if (collapseElement) {
                                                    // Tạo instance Bootstrap Collapse, toggle: false để không auto
                                                    const collapseInstance = new bootstrap.Collapse(
                                                        collapseElement, {
                                                            toggle: false
                                                        });
                                                    collapseInstance.show();
                                                }
                                            }

                                            // Bắt sự kiện khi item mở
                                            const allCollapses = document.querySelectorAll('.accordion-collapse');
                                            allCollapses.forEach(function(collapseEl) {
                                                collapseEl.addEventListener('shown.bs.collapse', function(
                                                    e) {
                                                    localStorage.setItem('activeAddon', '#' + e
                                                        .target.id);
                                                });
                                                // Tuỳ chọn: nếu muốn xoá khi đóng => 'hidden.bs.collapse'
                                                // collapseEl.addEventListener('hidden.bs.collapse', function(e) {
                                                //   localStorage.removeItem('activeAddon');
                                                // });
                                            });
                                        });
                                    </script>


                                </div>
                            </div>
                        </div>
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
    CKEDITOR.replace("popup_noti");
    CKEDITOR.replace("page_faq");
    CKEDITOR.replace("page_policy");
    CKEDITOR.replace("page_contact");
    CKEDITOR.replace("notice_home");
    CKEDITOR.replace("notice_orders");
    CKEDITOR.replace("policy_register");
    CKEDITOR.replace("email_temp_content_warning_login");
    CKEDITOR.replace("email_temp_content_forgot_password");
    CKEDITOR.replace("email_temp_content_otp_mail");
    CKEDITOR.replace("email_temp_content_buy_order");
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Get the active tab from Local Storage
        var activeTab = localStorage.getItem('activeTab');
        if (activeTab) {
            // Show the saved tab
            $('.nav-tabs a[href="#' + activeTab + '"]').tab('show');
        }

        // Save the selected tab to Local Storage
        $('.nav-tabs a').on('shown.bs.tab', function(e) {
            var selectedTab = $(e.target).attr('href').substr(1);
            localStorage.setItem('activeTab', selectedTab);
        });
    });

    // Hàm lấy proxy free từ API CMSNT
    function getFreeProxy() {
        const btn = $('#btnGetFreeProxy');
        const btnText = $('#btnText');
        const btnLoading = $('#btnLoading');
        const proxyInput = $('#telegram_proxy_input');
        const proxyTypeSelect = $('#telegram_proxy_type_select');

        // Hiển thị loading
        btn.prop('disabled', true);
        btnText.addClass('d-none');
        btnLoading.removeClass('d-none');

        // Gọi API
        $.ajax({
            url: 'https://api.cmsnt.co/free-proxy-socks5.php',
            type: 'GET',
            dataType: 'JSON',
            success: function(data) {
                try {
                    if (!Array.isArray(data) || data.length === 0) {
                        throw new Error('Không có proxy nào khả dụng');
                    }

                    // Lọc proxy có ping thấp (< 2000ms) để có chất lượng tốt hơn
                    const goodProxies = data.filter(proxy => proxy.ping < 2000);
                    const proxyList = goodProxies.length > 0 ? goodProxies : data;

                    // Chọn random 1 proxy
                    const randomIndex = Math.floor(Math.random() * proxyList.length);
                    const selectedProxy = proxyList[randomIndex];

                    // Điền vào input với format ip:port
                    const proxyString = `${selectedProxy.ip}:${selectedProxy.port}`;
                    proxyInput.val(proxyString);

                    // Tự động chọn SOCKS5
                    proxyTypeSelect.val('SOCKS5');

                    // Hiển thị thông báo thành công
                    showProxyNotification('success', `
                    <strong>Lấy proxy thành công!</strong><br>
                    <small>
                        <i class="fas fa-server me-1"></i>${selectedProxy.ip}:${selectedProxy.port} 
                        <i class="fas fa-flag ms-2 me-1"></i>${selectedProxy.country} 
                        <i class="fas fa-signal ms-2 me-1"></i>${selectedProxy.ping}ms
                    </small>
                `);

                } catch (error) {
                    showProxyNotification('error', 'Có lỗi xảy ra khi lấy proxy. Vui lòng thử lại!');
                }
            },
            error: function(xhr, status, error) {
                showProxyNotification('error', 'Không thể kết nối đến API. Vui lòng kiểm tra kết nối mạng và thử lại!');
            },
            complete: function() {
                // Khôi phục trạng thái nút
                btn.prop('disabled', false);
                btnText.removeClass('d-none');
                btnLoading.addClass('d-none');
            }
        });
    }


    // Hàm hiển thị thông báo
    function showProxyNotification(type, message) {
        // Xóa thông báo cũ nếu có
        const oldAlert = document.querySelector('.proxy-alert');
        if (oldAlert) {
            oldAlert.remove();
        }

        // Tạo thông báo mới
        let alertClass, iconClass;

        switch (type) {
            case 'success':
                alertClass = 'alert-success';
                iconClass = 'fa-check-circle';
                break;
            case 'warning':
                alertClass = 'alert-warning';
                iconClass = 'fa-exclamation-triangle';
                break;
            case 'error':
            default:
                alertClass = 'alert-danger';
                iconClass = 'fa-times-circle';
                break;
        }

        const alertDiv = document.createElement('div');
        alertDiv.className = `alert ${alertClass} alert-dismissible fade show proxy-alert mt-2 mb-0`;
        alertDiv.innerHTML = `
        <i class="fas ${iconClass} me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

        // Thêm vào sau input group
        const inputGroup = document.querySelector('#telegram_proxy_input').closest('.input-group');
        inputGroup.parentNode.insertBefore(alertDiv, inputGroup.nextSibling.nextSibling);

        // Tự động ẩn sau 8 giây (tăng thời gian hiển thị)
        setTimeout(() => {
            if (alertDiv && alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 8000);
    }

    // Hàm xử lý ẩn/hiện input proxy dựa trên telegram_url
    function toggleProxyInputs() {
        const telegramUrlSelect = document.querySelector('select[name="telegram_url"]');
        const proxyRow = document.querySelector('input[name="telegram_proxy"]').closest('tr');
        const proxyTypeRow = document.querySelector('select[name="telegram_proxy_type"]').closest('tr');
        const proxyInput = document.querySelector('input[name="telegram_proxy"]');

        if (telegramUrlSelect && proxyRow && proxyTypeRow && proxyInput) {
            const selectedUrl = telegramUrlSelect.value;

            if (selectedUrl === 'https://bypass-telegram.cmsnt.workers.dev/') {
                // Ẩn input proxy và xóa dữ liệu
                proxyRow.style.display = 'none';
                proxyTypeRow.style.display = 'none';
                proxyInput.value = '';
            } else {
                // Hiển thị input proxy
                proxyRow.style.display = '';
                proxyTypeRow.style.display = '';
            }
        }
    }

    // Khởi tạo khi trang load
    document.addEventListener('DOMContentLoaded', function() {
        // Thực hiện kiểm tra ban đầu
        toggleProxyInputs();

        // Theo dõi thay đổi của select telegram_url
        const telegramUrlSelect = document.querySelector('select[name="telegram_url"]');
        if (telegramUrlSelect) {
            telegramUrlSelect.addEventListener('change', toggleProxyInputs);
        }
    });
</script>


<script>
    $(document).ready(function() {
        // Function để cập nhật link hướng dẫn Captcha
        function updateCaptchaHelpLink() {
            const captchaType = $('select[name="captcha_type"]').val();
            const helpLink = $('#help-link');
            const helpText = $('#help-text');

            if (captchaType === 'reCAPTCHA') {
                helpLink.attr('href', 'https://help.cmsnt.co/huong-dan/huong-dan-cau-hinh-recaptcha-trong-shopclone7/');
                helpText.text('<?= __('Xem hướng dẫn chi tiết cấu hình reCAPTCHA'); ?>');
            } else if (captchaType === 'Cloudflare') {
                helpLink.attr('href', 'https://help.cmsnt.co/huong-dan/shopclone7-huong-dan-cau-hinh-captcha-su-dung-cloudflare/');
                helpText.text('<?= __('Xem hướng dẫn chi tiết cấu hình Cloudflare Captcha'); ?>');
            }
        }

        // Cập nhật link khi trang load
        updateCaptchaHelpLink();

        // Cập nhật link khi thay đổi loại Captcha
        $('select[name="captcha_type"]').on('change', function() {
            updateCaptchaHelpLink();
        });

        // Khởi tạo Choices.js cho select module captcha
        const captchaModulesChoice = new Choices('#captcha-modules', {
            removeItemButton: true,
            searchEnabled: false,
            noChoicesText: '<?= __('Không có lựa chọn nào'); ?>',
            itemSelectText: '<?= __('Nhấn để chọn'); ?>',
            // Preserve selected values from database
            silent: false
        });
    });
</script>