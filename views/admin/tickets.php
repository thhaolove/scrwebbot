<?php if (!defined('IN_SITE')) {
    die('The Request Not Found');
}
$body = [
    'title' => __('Support Tickets') . ' | ' . $CMSNT->site('title'),
    'desc'   => $CMSNT->site('description'),
    'keyword' => $CMSNT->site('keywords')
];
$body['header'] = '
<link rel="stylesheet" href="' . base_url('assets/libs/flatpickr/flatpickr.min.css') . '">
';
$body['footer'] = '
<script src="' . base_url('assets/libs/flatpickr/flatpickr.min.js') . '"></script>
';
require_once(__DIR__ . '/../../models/is_admin.php');
require_once(__DIR__ . '/header.php');
require_once(__DIR__ . '/sidebar.php');
// Kiểm tra quyền xem tickets
if (checkPermission($getUser['admin'], 'view_ticket') != true) {
    die('<script type="text/javascript">if(!alert("' . __('Bạn không có quyền sử dụng tính năng này') . '")){window.history.back();}</script>');
}



if (isset($_POST['SaveSettings'])) {
    // Kiểm tra CSRF token
    checkCSRF();

    if ($CMSNT->site('status_demo') != 0) {
        die('<script type="text/javascript">if(!alert("' . __('This function cannot be used because this is a demo site') . '")){window.history.back().location.reload();}</script>');
    }
    if (checkPermission($getUser['admin'], 'edit_ticket') != true) {
        die('<script type="text/javascript">if(!alert("' . __('Bạn không có quyền sử dụng tính năng này') . '")){window.history.back();}</script>');
    }
    $CMSNT->insert("logs", [
        'user_id'       => $getUser['id'],
        'ip'            => myip(),
        'device'        => getUserAgent(),
        'createdate'    => gettime(),
        'action'        => __('Cấu hình Ticket')
    ]);
    foreach ($_POST as $key => $value) {
        $CMSNT->update("settings", array(
            'value' => $value
        ), " `name` = '$key' ");
    }
    /** NOTE ACTION */
    $my_text = $CMSNT->site('noti_action');
    $my_text = str_replace('{domain}', $_SERVER['SERVER_NAME'], $my_text);
    $my_text = str_replace('{username}', $getUser['username'], $my_text);
    $my_text = str_replace('{action}', __('Cấu hình Ticket'), $my_text);
    $my_text = str_replace('{ip}', myip(), $my_text);
    $my_text = str_replace('{time}', gettime(), $my_text);
    sendMessAdmin($my_text);
    die('<script type="text/javascript">if(!alert("' . __('Lưu thành công!') . '")){window.history.back().location.reload();}</script>');
}



if (isset($_GET['limit'])) {
    $limit = intval(check_string($_GET['limit']));
} else {
    $limit = 10;
}
if (isset($_GET['page'])) {
    $page = check_string(intval($_GET['page']));
} else {
    $page = 1;
}
$from = ($page - 1) * $limit;
$where = " t.id > 0 ";
$created_at = '';
$subject = '';
$shortByDate = '';
$username = '';
$status = '';
$category = '';

// Filters
if (!empty($_GET['status'])) {
    $status = check_string($_GET['status']);
    $where .= ' AND t.status = "' . $status . '" ';
}
if (!empty($_GET['category'])) {
    $category = check_string($_GET['category']);
    $where .= ' AND t.category = "' . $category . '" ';
}
if (!empty($_GET['subject'])) {
    $subject = check_string($_GET['subject']);
    $where .= ' AND t.subject LIKE "%' . $subject . '%" ';
}
if (!empty($_GET['username'])) {
    $username = check_string($_GET['username']);
    $where .= ' AND u.username LIKE "%' . $username . '%" ';
}
if (!empty($_GET['created_at'])) {
    $created_at = check_string($_GET['created_at']);
    $date_range = explode(' to ', str_replace('-', '/', $created_at));
    if (count($date_range) == 2 && $date_range[0] != $date_range[1]) {
        $date_range = [$date_range[0] . ' 00:00:00', $date_range[1] . ' 23:59:59'];
        $where .= " AND t.created_at >= '" . $date_range[0] . "' AND t.created_at <= '" . $date_range[1] . "' ";
    }
}
if (isset($_GET['shortByDate'])) {
    $shortByDate = check_string($_GET['shortByDate']);
    $yesterday = date('Y-m-d', strtotime("-1 day"));
    $currentWeek = date("W");
    $currentMonth = date('m');
    $currentYear = date('Y');
    $currentDate = date("Y-m-d");
    if ($shortByDate == 1) {
        $where .= " AND DATE(t.created_at) = '" . $currentDate . "' ";
    }
    if ($shortByDate == 2) {
        $where .= " AND YEAR(t.created_at) = $currentYear AND WEEK(t.created_at, 1) = $currentWeek ";
    }
    if ($shortByDate == 3) {
        $where .= " AND MONTH(t.created_at) = '$currentMonth' AND YEAR(t.created_at) = '$currentYear' ";
    }
}

$listDatatable = $CMSNT->get_list("
    SELECT t.*, u.username, u.email,
           (SELECT COUNT(*) FROM support_messages WHERE ticket_id = t.id) as message_count,
           (SELECT COUNT(*) FROM support_messages WHERE ticket_id = t.id AND sender_type = 'admin') as admin_replies,
           (SELECT created_at FROM support_messages WHERE ticket_id = t.id ORDER BY created_at DESC LIMIT 1) as last_message_time
    FROM support_tickets t 
    LEFT JOIN users u ON t.user_id = u.id 
    WHERE $where 
    ORDER BY t.id DESC 
    LIMIT $from,$limit
");

$totalDatatable = $CMSNT->num_rows("
    SELECT t.id 
    FROM support_tickets t 
    LEFT JOIN users u ON t.user_id = u.id 
    WHERE $where
");

$urlDatatable = pagination(base_url_admin("tickets&limit=$limit&shortByDate=$shortByDate&subject=$subject&created_at=$created_at&username=$username&status=$status&category=$category&"), $from, $totalDatatable, $limit);

// Lấy thống kê
$stats = [
    'total' => $CMSNT->get_row("SELECT COUNT(*) as total FROM support_tickets")['total'],
    'open' => $CMSNT->get_row("SELECT COUNT(*) as total FROM support_tickets WHERE status = 'open'")['total'],
    'pending' => $CMSNT->get_row("SELECT COUNT(*) as total FROM support_tickets WHERE status = 'pending'")['total'],
    'answered' => $CMSNT->get_row("SELECT COUNT(*) as total FROM support_tickets WHERE status = 'answered'")['total'],
    'closed' => $CMSNT->get_row("SELECT COUNT(*) as total FROM support_tickets WHERE status = 'closed'")['total']
];
?>

<div class="main-content app-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <div class="d-flex align-items-center gap-3">
                <h1 class="page-title fw-semibold fs-18 mb-0">
                    <i class="ri-customer-service-2-line me-2"></i><?= __('Support Tickets'); ?>
                </h1>
                <?php if (checkPermission($getUser['admin'], 'edit_ticket')): ?>
                    <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#cleanupTicketsModal">
                        <i class="ri-delete-bin-line me-1"></i><?= __('Dọn dẹp'); ?>
                    </button>
                <?php endif; ?>
            </div>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="<?= base_url_admin(''); ?>"><?= __('Dashboard'); ?></a></li>
                        <li class="breadcrumb-item active" aria-current="page"><?= __('Support Tickets'); ?></li>
                    </ol>
                </nav>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row">
            <div class="col-xl-12">
                <div class="text-right">
                    <button type="button" id="open-card-config-tickets" class="btn btn-primary label-btn mb-3">
                        <i class="ri-settings-4-line label-btn-icon me-2"></i> <?= __('CẤU HÌNH'); ?>
                    </button>
                </div>
            </div>
            <div class="col-xl-12" id="card-config-tickets" style="display: none;">
                <div class="card custom-card">
                    <div class="card-body">
                        <form action="" method="POST" enctype="multipart/form-data">
                            <?php echo csrfField(); ?>
                            <div class="row">
                                <div class="col-lg-12 col-xl-6">
                                    <div class="row mb-4">
                                        <label class="col-sm-4 col-form-label"
                                            for="example-hf-email"><?= __('Trạng thái'); ?></label>
                                        <div class="col-sm-8">
                                            <select class="form-control" name="support_tickets_status">
                                                <option
                                                    <?= $CMSNT->site('support_tickets_status') == 1 ? 'selected' : ''; ?>
                                                    value="1"><?= __('ON'); ?></option>
                                                <option
                                                    <?= $CMSNT->site('support_tickets_status') == 0 ? 'selected' : ''; ?>
                                                    value="0"><?= __('OFF'); ?></option>
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="row mb-4">
                                        <label class="col-sm-4 col-form-label"
                                            for="example-hf-email"><?= __('Cho phép User tạo ticket tại lịch sử đơn hàng'); ?></label>
                                        <div class="col-sm-8">
                                            <select class="form-control" name="support_tickets_order_history">
                                                <option
                                                    <?= $CMSNT->site('support_tickets_order_history') == 1 ? 'selected' : ''; ?>
                                                    value="1"><?= __('ON'); ?></option>
                                                <option
                                                    <?= $CMSNT->site('support_tickets_order_history') == 0 ? 'selected' : ''; ?>
                                                    value="0"><?= __('OFF'); ?></option>
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-12 col-xl-6">
                                    <div class="row mb-4">
                                        <label class="col-sm-4 col-form-label"
                                            for="example-hf-email"><?= __('Chat ID Telegram nhận thông báo khi có Ticket mới'); ?></label>
                                        <div class="col-sm-8">
                                            <input type="text" class="form-control" name="support_tickets_telegram_chat_id" value="<?= $CMSNT->site('support_tickets_telegram_chat_id'); ?>">
                                        </div>
                                    </div>
                                </div>

                            </div>
                            <div class="d-grid gap-2 mb-4">
                                <button type="submit" name="SaveSettings" class="btn btn-primary btn-block"><i
                                        class="fa fa-fw fa-save me-1"></i>
                                    <?= __('Lưu'); ?></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
                <div class="card custom-card">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between">
                            <div>
                                <span class="avatar avatar-md avatar-rounded bg-primary">
                                    <i class="ti ti-ticket fs-16"></i>
                                </span>
                            </div>
                            <div class="flex-fill ms-3">
                                <div class="d-flex align-items-center justify-content-between flex-wrap">
                                    <div>
                                        <p class="text-muted mb-0"><?= __('Tổng tickets'); ?></p>
                                        <h4 class="fw-semibold mt-1"><?= number_format($stats['total']); ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
                <div class="card custom-card">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between">
                            <div>
                                <span class="avatar avatar-md avatar-rounded bg-success">
                                    <i class="ti ti-circle-check fs-16"></i>
                                </span>
                            </div>
                            <div class="flex-fill ms-3">
                                <div class="d-flex align-items-center justify-content-between flex-wrap">
                                    <div>
                                        <p class="text-muted mb-0"><?= __('Đang mở'); ?></p>
                                        <h4 class="fw-semibold mt-1"><?= number_format($stats['open']); ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
                <div class="card custom-card">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between">
                            <div>
                                <span class="avatar avatar-md avatar-rounded bg-warning">
                                    <i class="ti ti-clock fs-16"></i>
                                </span>
                            </div>
                            <div class="flex-fill ms-3">
                                <div class="d-flex align-items-center justify-content-between flex-wrap">
                                    <div>
                                        <p class="text-muted mb-0"><?= __('Chờ xử lý'); ?></p>
                                        <h4 class="fw-semibold mt-1"><?= number_format($stats['pending']); ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
                <div class="card custom-card">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between">
                            <div>
                                <span class="avatar avatar-md avatar-rounded bg-info">
                                    <i class="ti ti-message-circle fs-16"></i>
                                </span>
                            </div>
                            <div class="flex-fill ms-3">
                                <div class="d-flex align-items-center justify-content-between flex-wrap">
                                    <div>
                                        <p class="text-muted mb-0"><?= __('Đã trả lời'); ?></p>
                                        <h4 class="fw-semibold mt-1"><?= number_format($stats['answered']); ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tickets Table -->
        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-header">
                        <div class="card-title text-uppercase">
                            <?= __('Danh sách yêu cầu hỗ trợ'); ?>
                        </div>
                        <div class="d-flex">
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="collapse"
                                data-bs-target="#filtersCollapse">
                                <i class="ri-filter-line me-1"></i><?= __('Bộ lọc'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="collapse <?= (!empty($status) || !empty($category) || !empty($subject) || !empty($username) || !empty($created_at) || !empty($shortByDate)) ? 'show' : ''; ?>"
                        id="filtersCollapse">
                        <div class="card-body border-bottom">
                            <form action="<?= base_url(); ?>" method="GET">
                                <input type="hidden" name="module" value="<?= $CMSNT->site('path_admin'); ?>">
                                <input type="hidden" name="action" value="tickets">

                                <div class="row g-3">
                                    <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                                        <label class="form-label"><?= __('Người dùng'); ?></label>
                                        <input class="form-control" value="<?= $username; ?>" name="username"
                                            placeholder="<?= __('Tên người dùng'); ?>">
                                    </div>
                                    <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                                        <label class="form-label"><?= __('Tiêu đề'); ?></label>
                                        <input class="form-control" value="<?= $subject; ?>" name="subject"
                                            placeholder="<?= __('Tiêu đề ticket'); ?>">
                                    </div>
                                    <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                                        <label class="form-label"><?= __('Trạng thái'); ?></label>
                                        <select class="form-select" name="status">
                                            <option value=""><?= __('Tất cả'); ?></option>
                                            <option <?= $status == 'open' ? 'selected' : ''; ?> value="open">
                                                <?= __('Đang mở'); ?></option>
                                            <option <?= $status == 'pending' ? 'selected' : ''; ?> value="pending">
                                                <?= __('Chờ xử lý'); ?></option>
                                            <option <?= $status == 'answered' ? 'selected' : ''; ?> value="answered">
                                                <?= __('Đã trả lời'); ?></option>
                                            <option <?= $status == 'closed' ? 'selected' : ''; ?> value="closed">
                                                <?= __('Đã đóng'); ?></option>
                                        </select>
                                    </div>
                                    <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                                        <label class="form-label"><?= __('Danh mục'); ?></label>
                                        <select class="form-select" name="category">
                                            <option value=""><?= __('Tất cả'); ?></option>
                                            <?php foreach ($config_category_support_tickets as $key => $cat): ?>
                                                <option <?= $category == $key ? 'selected' : ''; ?> value="<?= $key; ?>">
                                                    <?= $cat; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                                        <label class="form-label"><?= __('Thời gian'); ?></label>
                                        <input type="text" name="created_at" class="form-control" id="daterange"
                                            value="<?= $created_at; ?>" placeholder="<?= __('Chọn thời gian'); ?>">
                                    </div>
                                    <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                                        <label class="form-label"><?= __('Lọc nhanh'); ?></label>
                                        <select name="shortByDate" class="form-select">
                                            <option value=""><?= __('Tất cả'); ?></option>
                                            <option <?= $shortByDate == 1 ? 'selected' : ''; ?> value="1">
                                                <?= __('Hôm nay'); ?></option>
                                            <option <?= $shortByDate == 2 ? 'selected' : ''; ?> value="2">
                                                <?= __('Tuần này'); ?></option>
                                            <option <?= $shortByDate == 3 ? 'selected' : ''; ?> value="3">
                                                <?= __('Tháng này'); ?></option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <button class="btn btn-primary me-2">
                                            <i class="ri-search-line me-1"></i><?= __('Tìm kiếm'); ?>
                                        </button>
                                        <a class="btn btn-outline-secondary" href="<?= base_url_admin('tickets'); ?>">
                                            <i class="ri-refresh-line me-1"></i><?= __('Đặt lại'); ?>
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card-body">
                        <!-- Table Controls -->
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="d-flex align-items-center">
                                <label class="form-label me-2 mb-0"><?= __('Hiển thị'); ?>:</label>
                                <form method="GET" class="d-inline">
                                    <input type="hidden" name="module" value="<?= $CMSNT->site('path_admin'); ?>">
                                    <input type="hidden" name="action" value="tickets">
                                    <input type="hidden" name="shortByDate" value="<?= $shortByDate; ?>">
                                    <input type="hidden" name="subject" value="<?= $subject; ?>">
                                    <input type="hidden" name="created_at" value="<?= $created_at; ?>">
                                    <input type="hidden" name="username" value="<?= $username; ?>">
                                    <input type="hidden" name="status" value="<?= $status; ?>">
                                    <input type="hidden" name="category" value="<?= $category; ?>">
                                    <select name="limit" onchange="this.form.submit()" class="form-select form-select-sm" style="width: auto;">
                                        <option <?= $limit == 5 ? 'selected' : ''; ?> value="5">5</option>
                                        <option <?= $limit == 10 ? 'selected' : ''; ?> value="10">10</option>
                                        <option <?= $limit == 20 ? 'selected' : ''; ?> value="20">20</option>
                                        <option <?= $limit == 50 ? 'selected' : ''; ?> value="50">50</option>
                                        <option <?= $limit == 100 ? 'selected' : ''; ?> value="100">100</option>
                                    </select>
                                </form>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <!-- Auto Load Control -->
                                <div class="d-flex align-items-center">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="autoLoadTickets">
                                        <label class="form-check-label" for="autoLoadTickets">
                                            <i class="ri-refresh-line me-1"></i><?= __('Auto Load'); ?>
                                        </label>
                                    </div>
                                    <div class="ms-2">
                                        <select id="autoLoadInterval" class="form-select form-select-sm" style="width: auto;">
                                            <option value="15"><?= __('15 giây'); ?></option>
                                            <option value="30"><?= __('30 giây'); ?></option>
                                            <option value="60" selected><?= __('1 phút'); ?></option>
                                            <option value="120"><?= __('2 phút'); ?></option>
                                            <option value="300"><?= __('5 phút'); ?></option>
                                        </select>
                                    </div>
                                    <div class="ms-2">
                                        <span id="autoLoadStatus" class="badge bg-secondary-transparent" style="display: none;">
                                            <i class="ri-time-line me-1"></i><span id="countdown"></span>
                                        </span>
                                    </div>
                                </div>
                                <div class="text-muted">
                                    <?= __('Hiển thị'); ?> <?= count($listDatatable); ?> <?= __('trong tổng số'); ?> <?= number_format($totalDatatable); ?> <?= __('kết quả'); ?>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="d-flex align-items-center">
                                <button type="button" id="btnBulkDelete" class="btn btn-outline-danger me-2" style="display: none;">
                                    <i class="ri-delete-bin-line me-1"></i><?= __('Xóa đã chọn'); ?>
                                    (<span id="selectedCount">0</span>)
                                </button>
                                <button type="button" id="btnBulkStatus" class="btn btn-outline-primary dropdown-toggle me-2"
                                    data-bs-toggle="dropdown" style="display: none;">
                                    <i class="ri-settings-3-line me-1"></i><?= __('Thay đổi trạng thái'); ?>
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" onclick="bulkChangeStatus('open')">
                                            <i class="ri-play-line me-2 text-success"></i><?= __('Mở'); ?>
                                        </a></li>
                                    <li><a class="dropdown-item" onclick="bulkChangeStatus('answered')">
                                            <i class="ri-check-line me-2 text-info"></i><?= __('Đã trả lời'); ?>
                                        </a></li>
                                    <li><a class="dropdown-item" onclick="bulkChangeStatus('closed')">
                                            <i class="ri-close-line me-2 text-warning"></i><?= __('Đóng'); ?>
                                        </a></li>
                                </ul>
                            </div>
                            <div class="text-muted">
                                <?= __('Tổng cộng'); ?>: <?= number_format($totalDatatable); ?> <?= __('tickets'); ?>
                            </div>
                        </div>

                        <!-- Table -->
                        <div class="table-responsive">
                            <table class="table text-nowrap table-striped table-hover table-bordered">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="selectAll">
                                                <label class="form-check-label" for="selectAll"></label>
                                            </div>
                                        </th>
                                        <th style="width: 80px;"><?= __('ID'); ?></th>
                                        <th><?= __('Ticket'); ?></th>
                                        <th><?= __('Chủ đề'); ?></th>
                                        <th><?= __('Người dùng'); ?></th>
                                        <th class="text-center" style="width: 120px;"><?= __('Trạng thái'); ?></th>
                                        <th class="text-center" style="width: 100px;"><?= __('Tin nhắn'); ?></th>
                                        <th class="text-center" style="width: 150px;"><?= __('Cập nhật'); ?></th>
                                        <th class="text-center" style="width: 120px;"><?= __('Thao tác'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($listDatatable)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center py-4">
                                                <div class="empty-state">
                                                    <i class="ri-inbox-line fs-48 text-muted"></i>
                                                    <h5 class="mt-3 text-muted"><?= __('Không có dữ liệu'); ?></h5>
                                                    <p class="text-muted mb-0">
                                                        <?= __('Không tìm thấy ticket nào phù hợp với điều kiện tìm kiếm'); ?>
                                                    </p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($listDatatable as $row): ?>
                                            <tr>
                                                <td>
                                                    <div class="form-check">
                                                        <input class="form-check-input ticket-checkbox" type="checkbox"
                                                            value="<?= $row['id']; ?>" id="ticket_<?= $row['id']; ?>">
                                                        <label class="form-check-label" for="ticket_<?= $row['id']; ?>"></label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <a href="<?= base_url_admin('ticket-detail&id=' . $row['id']); ?>" class="fw-semibold text-primary">#<?= $row['id']; ?></a>
                                                </td>
                                                <td>
                                                    <span class="mb-1 fw-semibold">
                                                        <a href="<?= base_url_admin('ticket-detail&id=' . $row['id']); ?>"
                                                            class="text-dark text-decoration-none">
                                                            <?= htmlspecialchars(substr($row['subject'], 0, 60)); ?><?= strlen($row['subject']) > 60 ? '...' : ''; ?>
                                                        </a>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span
                                                        class="badge bg-<?= $row['category'] == 'order' ? 'warning' : ($row['category'] == 'payment' ? 'info' : 'secondary'); ?>-gradient">
                                                        <i class="ri-price-tag-3-line"></i>
                                                        <?= $config_category_support_tickets[$row['category']] ?? $row['category']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar avatar-sm me-2">
                                                            <img src="<?= getGravatarUrl($row['email']); ?>"
                                                                alt="<?= $row['username']; ?>" class="avatar-img rounded-circle">
                                                        </div>
                                                        <div>
                                                            <div class="fw-semibold">
                                                                <a href="<?= base_url_admin('user-edit&id=' . $row['user_id']); ?>"
                                                                    class="text-primary text-decoration-none"
                                                                    data-bs-toggle="tooltip"
                                                                    title="<?= __('Chỉnh sửa người dùng'); ?>">
                                                                    <?= $row['username']; ?>
                                                                </a>
                                                            </div>
                                                            <small class="text-muted"><?= $row['email']; ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <?= display_status_support_tickets($row['status']); ?>
                                                </td>
                                                <td class="text-center">
                                                    <div class="d-flex flex-column align-items-center">
                                                        <span
                                                            class="badge bg-primary rounded-pill"><?= $row['message_count']; ?></span>
                                                        <small class="text-muted mt-1"><?= __('Admin'); ?>:
                                                            <?= $row['admin_replies']; ?></small>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($row['last_message_time']): ?>
                                                        <div class="text-muted">
                                                            <small><?= date('d/m/Y', strtotime($row['last_message_time'])); ?></small>
                                                            <br><small><?= date('H:i', strtotime($row['last_message_time'])); ?></small>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="text-muted">
                                                            <small><?= date('d/m/Y', strtotime($row['created_at'])); ?></small>
                                                            <br><small><?= date('H:i', strtotime($row['created_at'])); ?></small>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <div class="btn-group">
                                                        <a href="<?= base_url_admin('ticket-detail&id=' . $row['id']); ?>"
                                                            class="btn btn-sm btn-primary-light" data-bs-toggle="tooltip"
                                                            title="<?= __('Xem chi tiết'); ?>">
                                                            <i class="ri-eye-line"></i>
                                                        </a>
                                                        <div class="btn-group">
                                                            <button type="button"
                                                                class="btn btn-sm btn-outline-primary dropdown-toggle dropdown-toggle-split"
                                                                data-bs-toggle="dropdown">
                                                                <span class="visually-hidden">Toggle Dropdown</span>
                                                            </button>
                                                            <ul class="dropdown-menu">
                                                                <?php if ($row['status'] != 'answered'): ?>
                                                                    <li>
                                                                        <button class="dropdown-item"
                                                                            onclick="changeStatus(<?= $row['id']; ?>, 'answered')">
                                                                            <i
                                                                                class="ri-check-line me-2"></i><?= __('Đánh dấu đã trả lời'); ?>
                                                                        </button>
                                                                    </li>
                                                                <?php endif; ?>
                                                                <?php if ($row['status'] != 'closed'): ?>
                                                                    <li>
                                                                        <button class="dropdown-item"
                                                                            onclick="changeStatus(<?= $row['id']; ?>, 'closed')">
                                                                            <i
                                                                                class="ri-close-line me-2"></i><?= __('Đóng ticket'); ?>
                                                                        </button>
                                                                    </li>
                                                                <?php endif; ?>
                                                                <?php if ($row['status'] == 'closed'): ?>
                                                                    <li>
                                                                        <button class="dropdown-item"
                                                                            onclick="changeStatus(<?= $row['id']; ?>, 'open')">
                                                                            <i class="ri-play-line me-2"></i><?= __('Mở lại'); ?>
                                                                        </button>
                                                                    </li>
                                                                <?php endif; ?>
                                                                <li>
                                                                    <hr class="dropdown-divider">
                                                                </li>
                                                                <li>
                                                                    <button class="dropdown-item text-danger"
                                                                        onclick="RemoveRow(<?= $row['id']; ?>)">
                                                                        <i class="ri-delete-bin-line me-2"></i><?= __('Xóa'); ?>
                                                                    </button>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalDatatable > $limit): ?>
                            <div class="d-flex justify-content-center mt-4">
                                <?= $urlDatatable; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once(__DIR__ . '/footer.php');
?>

<script type="text/javascript">
    $(document).ready(function() {
        // Initialize flatpickr for date range
        $("#daterange").flatpickr({
            mode: "range",
            dateFormat: "d/m/Y",
            locale: "vn"
        });

        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Auto Load functionality
        let autoLoadTimer = null;
        let countdownTimer = null;
        let timeLeft = 0;

        // Load saved auto load settings
        const savedAutoLoad = localStorage.getItem('autoLoadTickets');
        const savedInterval = localStorage.getItem('autoLoadInterval');

        // Set saved interval first before starting auto load
        if (savedInterval) {
            $('#autoLoadInterval').val(savedInterval);
        }

        // Then start auto load if it was enabled
        if (savedAutoLoad === 'true') {
            $('#autoLoadTickets').prop('checked', true);
            startAutoLoad();
        }

        // Auto load checkbox change event
        $('#autoLoadTickets').change(function() {
            if ($(this).is(':checked')) {
                localStorage.setItem('autoLoadTickets', 'true');
                startAutoLoad();
            } else {
                localStorage.setItem('autoLoadTickets', 'false');
                stopAutoLoad();
            }
        });

        // Auto load interval change event
        $('#autoLoadInterval').change(function() {
            const interval = $(this).val();
            localStorage.setItem('autoLoadInterval', interval);

            if ($('#autoLoadTickets').is(':checked')) {
                stopAutoLoad();
                startAutoLoad();
            }
        });

        function startAutoLoad() {
            const interval = parseInt($('#autoLoadInterval').val()) * 1000;
            timeLeft = parseInt($('#autoLoadInterval').val());

            $('#autoLoadStatus').show();
            updateCountdown();

            // Start countdown
            countdownTimer = setInterval(function() {
                timeLeft--;
                updateCountdown();

                if (timeLeft <= 0) {
                    timeLeft = parseInt($('#autoLoadInterval').val());
                }
            }, 1000);

            // Start auto reload
            autoLoadTimer = setInterval(function() {
                // Show loading indicator
                showLoadingIndicator();

                // Reload page to get fresh data
                setTimeout(function() {
                    location.reload();
                }, 500);
            }, interval);
        }

        function stopAutoLoad() {
            if (autoLoadTimer) {
                clearInterval(autoLoadTimer);
                autoLoadTimer = null;
            }

            if (countdownTimer) {
                clearInterval(countdownTimer);
                countdownTimer = null;
            }

            $('#autoLoadStatus').hide();
        }

        function updateCountdown() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            const display = minutes > 0 ? `${minutes}:${seconds.toString().padStart(2, '0')}` : `${seconds}s`;
            $('#countdown').text(display);

            // Change badge color based on time left
            if (timeLeft <= 10) {
                $('#autoLoadStatus').removeClass('bg-secondary-transparent bg-warning-transparent').addClass(
                    'bg-danger-transparent');
            } else if (timeLeft <= 30) {
                $('#autoLoadStatus').removeClass('bg-secondary-transparent bg-danger-transparent').addClass(
                    'bg-warning-transparent');
            } else {
                $('#autoLoadStatus').removeClass('bg-warning-transparent bg-danger-transparent').addClass(
                    'bg-secondary-transparent');
            }
        }

        function showLoadingIndicator() {
            // Create loading overlay
            if (!$('#loadingOverlay').length) {
                $('body').append(`
                <div id="loadingOverlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.1); z-index: 9999; display: none;">
                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                        <div class="d-flex align-items-center">
                            <div class="spinner-border spinner-border-sm text-primary me-3" role="status"></div>
                            <span><?= __('Đang tải dữ liệu mới...'); ?></span>
                        </div>
                    </div>
                </div>
            `);
            }

            $('#loadingOverlay').fadeIn(200);
        }



        // Stop auto load when page is about to unload
        $(window).on('beforeunload', function() {
            stopAutoLoad();
        });

        // Keep auto load running even when tab is not visible
        // Only show/hide the visual countdown, but keep the timer running
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                // Hide the countdown badge when tab is not visible (optional)
                // $('#autoLoadStatus').hide();
            } else {
                // Show the countdown badge when tab becomes visible again (optional)  
                if ($('#autoLoadTickets').is(':checked')) {
                    $('#autoLoadStatus').show();
                }
            }
        });
    });

    function changeStatus(id, status) {
        let statusText = '';
        let alertIcon = 'question';
        let alertTitle = '<?= __('Xác nhận thay đổi'); ?>';

        switch (status) {
            case 'answered':
                statusText = '<?= __('đã trả lời'); ?>';
                alertIcon = 'info';
                break;
            case 'closed':
                statusText = '<?= __('đóng'); ?>';
                alertIcon = 'warning';
                break;
            case 'open':
                statusText = '<?= __('mở lại'); ?>';
                alertIcon = 'success';
                break;
        }

        Swal.fire({
            icon: alertIcon,
            title: alertTitle,
            text: "<?= __('Bạn có chắc chắn muốn đánh dấu ticket này là'); ?> " + statusText + "?",
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: "<?= __('Xác nhận'); ?>",
            cancelButtonText: "<?= __('Hủy'); ?>"
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: "<?= BASE_URL('ajaxs/admin/ticket.php'); ?>",
                    type: 'POST',
                    dataType: "JSON",
                    data: {
                        action: 'changeStatusTicket',
                        id: id,
                        status: status
                    },
                    beforeSend: function() {
                        $('button').prop('disabled', true);
                    },
                    success: function(result) {
                        $('button').prop('disabled', false);
                        if (result.status == 'success') {
                            Swal.fire({
                                icon: "success",
                                title: "<?= __('Thành công'); ?>",
                                text: result.msg,
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: "error",
                                title: "<?= __('Lỗi'); ?>",
                                text: result.msg
                            });
                        }
                    },
                    error: function() {
                        $('button').prop('disabled', false);
                        Swal.fire({
                            icon: "error",
                            title: "<?= __('Lỗi'); ?>",
                            text: "<?= __('Có lỗi xảy ra, vui lòng thử lại'); ?>"
                        });
                    }
                });
            }
        })
    }

    function RemoveRow(id) {
        Swal.fire({
            icon: "question",
            title: "<?= __('Xác nhận xóa'); ?>",
            text: "<?= __('Bạn có chắc chắn muốn xóa ticket này không? Thao tác này không thể hoàn tác.'); ?>",
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: "<?= __('Xóa'); ?>",
            cancelButtonText: "<?= __('Hủy'); ?>"
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: "<?= BASE_URL('ajaxs/admin/ticket.php'); ?>",
                    type: 'POST',
                    dataType: "JSON",
                    data: {
                        action: 'deleteTicket',
                        id: id
                    },
                    beforeSend: function() {
                        $('button').prop('disabled', true);
                    },
                    success: function(result) {
                        $('button').prop('disabled', false);
                        if (result.status == 'success') {
                            Swal.fire({
                                icon: "success",
                                title: "<?= __('Thành công'); ?>",
                                text: result.msg,
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: "error",
                                title: "<?= __('Lỗi'); ?>",
                                text: result.msg
                            });
                        }
                    },
                    error: function() {
                        $('button').prop('disabled', false);
                        Swal.fire({
                            icon: "error",
                            title: "<?= __('Lỗi'); ?>",
                            text: "<?= __('Có lỗi xảy ra, vui lòng thử lại'); ?>"
                        });
                    }
                });
            }
        })
    }

    // Xử lý checkbox chọn tất cả và chọn từng item
    $(document).ready(function() {
        // Xử lý checkbox chọn tất cả
        $('#selectAll').change(function() {
            var isChecked = $(this).is(':checked');
            $('.ticket-checkbox').prop('checked', isChecked);
            updateBulkButtons();
        });

        // Xử lý checkbox từng item
        $(document).on('change', '.ticket-checkbox', function() {
            var totalCheckboxes = $('.ticket-checkbox').length;
            var checkedCheckboxes = $('.ticket-checkbox:checked').length;

            // Cập nhật trạng thái checkbox "chọn tất cả"
            $('#selectAll').prop('checked', totalCheckboxes === checkedCheckboxes);

            updateBulkButtons();
        });

        // Xử lý nút xóa hàng loạt
        $('#btnBulkDelete').click(function() {
            var selectedIds = getSelectedTicketIds();
            if (selectedIds.length === 0) {
                Swal.fire({
                    icon: "warning",
                    title: "<?= __('Cảnh báo'); ?>",
                    text: "<?= __('Vui lòng chọn ít nhất một ticket để xóa'); ?>"
                });
                return;
            }

            Swal.fire({
                icon: "question",
                title: "<?= __('Xác nhận xóa'); ?>",
                text: "<?= __('Bạn có chắc chắn muốn xóa'); ?> " + selectedIds.length + " <?= __('ticket đã chọn? Thao tác này không thể hoàn tác.'); ?>",
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: "<?= __('Xóa'); ?>",
                cancelButtonText: "<?= __('Hủy'); ?>"
            }).then((result) => {
                if (result.isConfirmed) {
                    bulkDeleteTickets(selectedIds);
                }
            });
        });
    });

    // Cập nhật hiển thị nút bulk action
    function updateBulkButtons() {
        var selectedCount = $('.ticket-checkbox:checked').length;
        $('#selectedCount').text(selectedCount);

        if (selectedCount > 0) {
            $('#btnBulkDelete, #btnBulkStatus').show();
        } else {
            $('#btnBulkDelete, #btnBulkStatus').hide();
        }
    }

    // Lấy danh sách ID đã chọn
    function getSelectedTicketIds() {
        var selectedIds = [];
        $('.ticket-checkbox:checked').each(function() {
            selectedIds.push($(this).val());
        });
        return selectedIds;
    }

    // Xóa nhiều ticket cùng lúc
    function bulkDeleteTickets(ids) {
        $.ajax({
            url: "<?= BASE_URL('ajaxs/admin/ticket.php'); ?>",
            type: 'POST',
            dataType: "JSON",
            data: {
                action: 'bulkDeleteTickets',
                ids: ids
            },
            beforeSend: function() {
                $('#btnBulkDelete').prop('disabled', true);
                $('#btnBulkDelete').html('<i class="ri-loader-2-line spinner-border spinner-border-sm me-1"></i><?= __('Đang xóa...'); ?>');
            },
            success: function(result) {
                $('#btnBulkDelete').prop('disabled', false);
                $('#btnBulkDelete').html('<i class="ri-delete-bin-line me-1"></i><?= __('Xóa đã chọn'); ?> (<span id="selectedCount">0</span>)');

                if (result.status == 'success') {
                    Swal.fire({
                        icon: "success",
                        title: "<?= __('Thành công'); ?>",
                        text: result.msg,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "<?= __('Lỗi'); ?>",
                        text: result.msg
                    });
                }
            },
            error: function() {
                $('#btnBulkDelete').prop('disabled', false);
                $('#btnBulkDelete').html('<i class="ri-delete-bin-line me-1"></i><?= __('Xóa đã chọn'); ?> (<span id="selectedCount">0</span>)');
                Swal.fire({
                    icon: "error",
                    title: "<?= __('Lỗi'); ?>",
                    text: "<?= __('Có lỗi xảy ra, vui lòng thử lại'); ?>"
                });
            }
        });
    }

    // Thay đổi trạng thái hàng loạt
    function bulkChangeStatus(status) {
        var selectedIds = getSelectedTicketIds();
        if (selectedIds.length === 0) {
            Swal.fire({
                icon: "warning",
                title: "<?= __('Cảnh báo'); ?>",
                text: "<?= __('Vui lòng chọn ít nhất một ticket'); ?>"
            });
            return;
        }

        let statusText = '';
        switch (status) {
            case 'answered':
                statusText = '<?= __('đã trả lời'); ?>';
                break;
            case 'closed':
                statusText = '<?= __('đóng'); ?>';
                break;
            case 'open':
                statusText = '<?= __('mở lại'); ?>';
                break;
        }

        Swal.fire({
            icon: "question",
            title: "<?= __('Xác nhận thay đổi'); ?>",
            text: "<?= __('Bạn có chắc chắn muốn đánh dấu'); ?> " + selectedIds.length + " <?= __('ticket đã chọn là'); ?> " + statusText + "?",
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: "<?= __('Xác nhận'); ?>",
            cancelButtonText: "<?= __('Hủy'); ?>"
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: "<?= BASE_URL('ajaxs/admin/ticket.php'); ?>",
                    type: 'POST',
                    dataType: "JSON",
                    data: {
                        action: 'bulkChangeStatus',
                        ids: selectedIds,
                        status: status
                    },
                    beforeSend: function() {
                        $('#btnBulkStatus').prop('disabled', true);
                    },
                    success: function(result) {
                        $('#btnBulkStatus').prop('disabled', false);
                        if (result.status == 'success') {
                            Swal.fire({
                                icon: "success",
                                title: "<?= __('Thành công'); ?>",
                                text: result.msg,
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: "error",
                                title: "<?= __('Lỗi'); ?>",
                                text: result.msg
                            });
                        }
                    },
                    error: function() {
                        $('#btnBulkStatus').prop('disabled', false);
                        Swal.fire({
                            icon: "error",
                            title: "<?= __('Lỗi'); ?>",
                            text: "<?= __('Có lỗi xảy ra, vui lòng thử lại'); ?>"
                        });
                    }
                });
            }
        });
    }
</script>


<script>
    document.addEventListener('DOMContentLoaded', function() {
        var button = document.getElementById('open-card-config-tickets');
        var card = document.getElementById('card-config-tickets');

        // Thêm sự kiện click cho nút button
        button.addEventListener('click', function() {
            // Kiểm tra nếu card đang hiển thị thì ẩn đi, ngược lại hiển thị
            if (card.style.display === 'none' || card.style.display === '') {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    });
</script>

<!-- Modal dọn dẹp tickets -->
<div class="modal fade" id="cleanupTicketsModal" tabindex="-1" aria-labelledby="cleanupTicketsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cleanupTicketsModalLabel">
                    <i class="ri-delete-bin-line text-danger me-2"></i><?= __('Dọn dẹp Support Tickets'); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning border-0">
                    <i class="ri-error-warning-line me-2"></i>
                    <?= __('Lưu ý: Thao tác này sẽ xóa vĩnh viễn các tickets đã đóng và tất cả tin nhắn liên quan. Không thể hoàn tác.'); ?>
                </div>
                <div class="mb-3">
                    <label for="cleanupDaysTickets" class="form-label fw-medium"><?= __('Xóa tickets đã đóng cũ hơn'); ?></label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="cleanupDaysTickets" value="30" min="1" max="365" placeholder="30">
                        <span class="input-group-text"><?= __('ngày'); ?></span>
                    </div>
                    <div class="form-text">
                        <i class="ri-information-line me-1"></i>
                        <?= __('Ví dụ: Nhập 30 sẽ xóa tất cả tickets đã đóng cũ hơn 30 ngày trở về trước.'); ?>
                    </div>
                </div>
                <div id="cleanupPreviewTickets" class="d-none">
                    <div class="alert alert-info-transparent border mb-0">
                        <i class="ri-file-list-3-line me-2"></i>
                        <span id="cleanupPreviewTextTickets"></span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                    <i class="ri-close-line me-1"></i><?= __('Hủy'); ?>
                </button>
                <button type="button" class="btn btn-danger" id="confirmCleanupBtnTickets">
                    <i class="ri-delete-bin-line me-1"></i><?= __('Xóa tickets'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        var cleanupModal = document.getElementById('cleanupTicketsModal');
        var $cleanupDays = $('#cleanupDaysTickets');
        var $cleanupPreview = $('#cleanupPreviewTickets');
        var $cleanupPreviewText = $('#cleanupPreviewTextTickets');
        var $confirmBtn = $('#confirmCleanupBtnTickets');
        var previewTimeout = null;

        function updatePreview() {
            var days = parseInt($cleanupDays.val()) || 0;
            if (days < 1) {
                $cleanupPreview.addClass('d-none');
                return;
            }
            $.ajax({
                url: '<?= BASE_URL('ajaxs/admin/view.php'); ?>',
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'previewCleanupTickets',
                    days: days
                },
                success: function(resp) {
                    if (resp.status === 'success') {
                        $cleanupPreviewText.text('<?= __('Sẽ xóa'); ?> ' + resp.count + ' <?= __('tickets đã đóng'); ?>');
                        $cleanupPreview.removeClass('d-none');
                    } else {
                        $cleanupPreview.addClass('d-none');
                    }
                }
            });
        }

        $cleanupDays.on('input', function() {
            clearTimeout(previewTimeout);
            previewTimeout = setTimeout(updatePreview, 500);
        });

        if (cleanupModal) {
            cleanupModal.addEventListener('shown.bs.modal', function() {
                $cleanupDays.val(30).focus();
                updatePreview();
            });
            cleanupModal.addEventListener('hidden.bs.modal', function() {
                $cleanupPreview.addClass('d-none');
                $confirmBtn.prop('disabled', false).html('<i class="ri-delete-bin-line me-1"></i><?= __('Xóa tickets'); ?>');
            });
        }

        $confirmBtn.on('click', function() {
            var days = parseInt($cleanupDays.val()) || 0;
            if (days < 1) {
                Swal.fire({
                    icon: 'warning',
                    title: '<?= __('Cảnh báo'); ?>',
                    text: '<?= __('Vui lòng nhập số ngày hợp lệ'); ?>'
                });
                return;
            }
            Swal.fire({
                icon: 'warning',
                title: '<?= __('Xác nhận xóa'); ?>',
                text: '<?= __('Bạn có chắc chắn muốn xóa tất cả tickets đã đóng cũ hơn'); ?> ' + days + ' <?= __('ngày?'); ?>',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<?= __('Xóa'); ?>',
                cancelButtonText: '<?= __('Hủy'); ?>'
            }).then(function(result) {
                if (result.isConfirmed) {
                    $confirmBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span><?= __('Đang xóa...'); ?>');
                    $.ajax({
                        url: '<?= BASE_URL('ajaxs/admin/remove.php'); ?>',
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'cleanupTickets',
                            days: days
                        },
                        success: function(resp) {
                            if (resp.status === 'success') {
                                Swal.fire({
                                    icon: 'success',
                                    title: '<?= __('Thành công'); ?>',
                                    text: resp.msg,
                                    timer: 2000,
                                    showConfirmButton: false
                                }).then(function() {
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: '<?= __('Lỗi'); ?>',
                                    text: resp.msg
                                });
                                $confirmBtn.prop('disabled', false).html('<i class="ri-delete-bin-line me-1"></i><?= __('Xóa tickets'); ?>');
                            }
                        },
                        error: function() {
                            Swal.fire({
                                icon: 'error',
                                title: '<?= __('Lỗi'); ?>',
                                text: '<?= __('Không thể kết nối đến server'); ?>'
                            });
                            $confirmBtn.prop('disabled', false).html('<i class="ri-delete-bin-line me-1"></i><?= __('Xóa tickets'); ?>');
                        }
                    });
                }
            });
        });
    });
</script>