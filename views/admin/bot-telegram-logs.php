<?php if (!defined('IN_SITE')) {
    die('The Request Not Found');
}
$body = [
    'title' => __('Nhật ký Bot Telegram'),
    'desc'   => 'CMSNT Panel',
    'keyword' => 'cmsnt, CMSNT, cmsnt.co,'
];
$body['header'] = '
<script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.6/clipboard.min.js"></script>

';
$body['footer'] = '
 
';
require_once(__DIR__ . '/../../models/is_admin.php');
require_once(__DIR__ . '/header.php');
require_once(__DIR__ . '/sidebar.php');
require_once(__DIR__ . '/nav.php');
if (checkPermission($getUser['admin'], 'view_bot_telegram_logs') != true) {
    die('<script type="text/javascript">if(!alert("' . __('Bạn không có quyền sử dụng tính năng này') . '")){window.history.back();}</script>');
}
if (isset($_GET['limit'])) {
    $limit = intval(check_string($_GET['limit']));
} else {
    $limit = 20;
}
if (isset($_GET['page'])) {
    $page = check_string(intval($_GET['page']));
} else {
    $page = 1;
}
$from = ($page - 1) * $limit;
$where = " `id` > 0 ";
$chat_id = '';
$message = '';
$token = '';
$response = '';
$created_at = '';
$shortByDate  = '';

if (isset($_GET['chat_id']) && $_GET['chat_id'] !== '') {
    $chat_id = check_string($_GET['chat_id']);
    $where .= ' AND `chat_id` LIKE "%' . $chat_id . '%" ';
}
if (!empty($_GET['message'])) {
    $message = check_string($_GET['message']);
    $where .= ' AND `message` LIKE "%' . $message . '%" ';
}
if (!empty($_GET['token'])) {
    $token = check_string($_GET['token']);
    $where .= ' AND `token` LIKE "%' . $token . '%" ';
}
if (!empty($_GET['response'])) {
    $response = check_string($_GET['response']);
    $where .= ' AND `response` LIKE "%' . $response . '%" ';
}
if (!empty($_GET['created_at'])) {
    $create_date = check_string($_GET['created_at']);
    $created_at = $create_date;
    $create_date_1 = str_replace('-', '/', $create_date);
    $create_date_1 = explode(' to ', $create_date_1);
    if ($create_date_1[0] != $create_date_1[1]) {
        $create_date_1 = [$create_date_1[0] . ' 00:00:00', $create_date_1[1] . ' 23:59:59'];
        $where .= " AND `created_at` >= '" . $create_date_1[0] . "' AND `created_at` <= '" . $create_date_1[1] . "' ";
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
        $where .= " AND `created_at` LIKE '%" . $currentDate . "%' ";
    }
    if ($shortByDate == 2) {
        $where .= " AND YEAR(created_at) = $currentYear AND WEEK(created_at, 1) = $currentWeek ";
    }
    if ($shortByDate == 3) {
        $where .= " AND MONTH(created_at) = '$currentMonth' AND YEAR(created_at) = '$currentYear' ";
    }
}

$listDatatable = $CMSNT->get_list(" SELECT * FROM `bot_telegram_logs` WHERE $where ORDER BY `id` DESC LIMIT $from,$limit ");
$totalDatatable = $CMSNT->num_rows(" SELECT * FROM `bot_telegram_logs` WHERE $where ORDER BY id DESC ");
$urlDatatable = pagination(base_url_admin("bot-telegram-logs&limit=$limit&shortByDate=$shortByDate&chat_id=$chat_id&message=$message&token=$token&response=$response&created_at=$created_at&"), $from, $totalDatatable, $limit);

?>

<div class="main-content app-content">
    <div class="container-fluid">
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <div class="d-flex align-items-center gap-3">
                <h1 class="page-title fw-semibold fs-18 mb-0"><i class="fa-solid fa-robot"></i> <?= __('Nhật ký Bot Telegram'); ?></h1>
                <?php if (checkPermission($getUser['admin'], 'edit_bot_telegram_logs')): ?>
                    <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#cleanupBotLogsModal">
                        <i class="ri-delete-bin-line me-1"></i><?= __('Dọn dẹp'); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-header justify-content-between">
                        <div class="card-title">
                            <?= __('NHẬT KÝ BOT TELEGRAM'); ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <form action="<?= base_url(); ?>" class="align-items-center mb-3" name="formSearch" method="GET">
                            <div class="row row-cols-lg-auto g-3 mb-3">
                                <input type="hidden" name="module" value="<?= $CMSNT->site('path_admin'); ?>">
                                <input type="hidden" name="action" value="bot-telegram-logs">
                                <div class="col-lg col-md-4 col-6">
                                    <input class="form-control form-control-sm" value="<?= $chat_id; ?>" name="chat_id"
                                        placeholder="<?= __('Chat ID'); ?>">
                                </div>
                                <div class="col-lg col-md-4 col-6">
                                    <input class="form-control form-control-sm" value="<?= $message; ?>" name="message"
                                        placeholder="<?= __('Tin nhắn'); ?>">
                                </div>
                                <div class="col-lg col-md-4 col-6">
                                    <input class="form-control form-control-sm" value="<?= $token; ?>" name="token"
                                        placeholder="<?= __('Token'); ?>">
                                </div>
                                <div class="col-lg col-md-4 col-6">
                                    <input class="form-control form-control-sm" value="<?= $response; ?>" name="response"
                                        placeholder="<?= __('Phản hồi'); ?>">
                                </div>
                                <div class="col-lg col-md-4 col-6">
                                    <input type="text" name="created_at" class="form-control form-control-sm" id="daterange"
                                        value="<?= $created_at; ?>" placeholder="<?= __('Chọn thời gian'); ?>">
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-hero btn-sm btn-primary"><i class="fa fa-search"></i>
                                        <?= __('Tìm kiếm'); ?>
                                    </button>
                                    <a class="btn btn-hero btn-sm btn-danger" href="<?= base_url_admin('bot-telegram-logs'); ?>"><i
                                            class="fa fa-trash"></i>
                                        <?= __('Xóa bộ lọc'); ?>
                                    </a>
                                </div>
                            </div>
                            <div class="top-filter">
                                <div class="filter-show">
                                    <label class="filter-label"><?= __('Hiển thị'); ?> :</label>
                                    <select name="limit" onchange="this.form.submit()"
                                        class="form-select filter-select">
                                        <option <?= $limit == 5 ? 'selected' : ''; ?> value="5">5</option>
                                        <option <?= $limit == 10 ? 'selected' : ''; ?> value="10">10</option>
                                        <option <?= $limit == 20 ? 'selected' : ''; ?> value="20">20</option>
                                        <option <?= $limit == 50 ? 'selected' : ''; ?> value="50">50</option>
                                        <option <?= $limit == 100 ? 'selected' : ''; ?> value="100">100</option>
                                        <option <?= $limit == 500 ? 'selected' : ''; ?> value="500">500</option>
                                        <option <?= $limit == 1000 ? 'selected' : ''; ?> value="1000">1.000</option>
                                        <option <?= $limit == 5000 ? 'selected' : ''; ?> value="5000">5.000</option>
                                        <option <?= $limit == 10000 ? 'selected' : ''; ?> value="10000">10.000</option>
                                    </select>
                                </div>
                                <div class="filter-short">
                                    <label class="filter-label"><?= __('Sắp xếp theo ngày'); ?> :</label>
                                    <select name="shortByDate" onchange="this.form.submit()"
                                        class="form-select filter-select">
                                        <option value=""><?= __('Tất cả'); ?></option>
                                        <option <?= $shortByDate == 1 ? 'selected' : ''; ?> value="1"><?= __('Hôm nay'); ?>
                                        </option>
                                        <option <?= $shortByDate == 2 ? 'selected' : ''; ?> value="2"><?= __('Tuần này'); ?>
                                        </option>
                                        <option <?= $shortByDate == 3 ? 'selected' : ''; ?> value="3">
                                            <?= __('Tháng này'); ?>
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </form>
                        <div class="table-responsive table-wrapper mb-3">
                            <table class="table text-nowrap table-striped table-hover table-bordered">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th><?= __('Chat ID nhận tin nhắn'); ?></th>
                                        <th><?= __('Tin nhắn'); ?></th>
                                        <th><?= __('Token'); ?></th>
                                        <th><?= __('Phản hồi từ API'); ?></th>
                                        <th><?= __('Thời gian'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $i = 0;
                                    foreach ($listDatatable as $row): ?>
                                        <tr>
                                            <td><?= $row['id']; ?></td>
                                            <td>
                                                <span class="badge bg-primary-transparent"><?= $row['chat_id']; ?></span>
                                            </td>
                                            <td>
                                                <textarea class="form-control" rows="3" readonly style="min-width: 300px; font-size: 12px;"><?= htmlspecialchars($row['message'] ?? ''); ?></textarea>
                                            </td>
                                            <td>
                                                <span class="badge bg-warning-transparent" title="<?= $row['token']; ?>">
                                                    <?= strlen($row['token']) > 20 ? substr($row['token'], 0, 20) . '...' . substr($row['token'], -10) : $row['token']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <textarea class="form-control" rows="3" readonly style="min-width: 300px; font-size: 12px;"><?= htmlspecialchars($row['response']); ?></textarea>
                                            </td>
                                            <td><span class="badge bg-light text-dark" data-toggle="tooltip" data-placement="bottom"
                                                    title="<?= timeAgo(strtotime($row['created_at'])); ?>"><?= $row['created_at']; ?></span></td>
                                        </tr>
                                    <?php endforeach ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="row">
                            <div class="col-sm-12 col-md-5">
                                <p class="dataTables_info"><?= __('Hiển thị'); ?> <?= $limit; ?> <?= __('trên tổng'); ?> <?= format_cash($totalDatatable); ?> <?= __('kết quả'); ?></p>
                            </div>
                            <div class="col-sm-12 col-md-7 mb-3">
                                <?= $totalDatatable > $limit ? $urlDatatable : ''; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal dọn dẹp bot telegram logs -->
<div class="modal fade" id="cleanupBotLogsModal" tabindex="-1" aria-labelledby="cleanupBotLogsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cleanupBotLogsModalLabel">
                    <i class="ri-delete-bin-line text-danger me-2"></i><?= __('Dọn dẹp nhật ký Bot Telegram'); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning border-0">
                    <i class="ri-error-warning-line me-2"></i>
                    <?= __('Lưu ý: Thao tác này sẽ xóa vĩnh viễn các bản ghi và không thể hoàn tác.'); ?>
                </div>
                <div class="mb-3">
                    <label for="cleanupDaysBotLogs" class="form-label fw-medium"><?= __('Xóa nhật ký cũ hơn'); ?></label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="cleanupDaysBotLogs" value="30" min="1" max="365" placeholder="30">
                        <span class="input-group-text"><?= __('ngày'); ?></span>
                    </div>
                    <div class="form-text">
                        <i class="ri-information-line me-1"></i>
                        <?= __('Ví dụ: Nhập 30 sẽ xóa tất cả nhật ký cũ hơn 30 ngày trở về trước.'); ?>
                    </div>
                </div>
                <div id="cleanupPreviewBotLogs" class="d-none">
                    <div class="alert alert-info-transparent border mb-0">
                        <i class="ri-file-list-3-line me-2"></i>
                        <span id="cleanupPreviewTextBotLogs"></span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                    <i class="ri-close-line me-1"></i><?= __('Hủy'); ?>
                </button>
                <button type="button" class="btn btn-danger" id="confirmCleanupBtnBotLogs">
                    <i class="ri-delete-bin-line me-1"></i><?= __('Xóa nhật ký'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        var cleanupModal = document.getElementById('cleanupBotLogsModal');
        var $cleanupDays = $('#cleanupDaysBotLogs');
        var $cleanupPreview = $('#cleanupPreviewBotLogs');
        var $cleanupPreviewText = $('#cleanupPreviewTextBotLogs');
        var $confirmBtn = $('#confirmCleanupBtnBotLogs');
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
                    action: 'previewCleanupBotTelegramLogs',
                    days: days
                },
                success: function(resp) {
                    if (resp.status === 'success') {
                        $cleanupPreviewText.text('<?= __('Sẽ xóa'); ?> ' + resp.count + ' <?= __('bản ghi'); ?>');
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
                $confirmBtn.prop('disabled', false).html('<i class="ri-delete-bin-line me-1"></i><?= __('Xóa nhật ký'); ?>');
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
                text: '<?= __('Bạn có chắc chắn muốn xóa tất cả nhật ký cũ hơn'); ?> ' + days + ' <?= __('ngày?'); ?>',
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
                            action: 'cleanupBotTelegramLogs',
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
                                $confirmBtn.prop('disabled', false).html('<i class="ri-delete-bin-line me-1"></i><?= __('Xóa nhật ký'); ?>');
                            }
                        },
                        error: function() {
                            Swal.fire({
                                icon: 'error',
                                title: '<?= __('Lỗi'); ?>',
                                text: '<?= __('Không thể kết nối đến server'); ?>'
                            });
                            $confirmBtn.prop('disabled', false).html('<i class="ri-delete-bin-line me-1"></i><?= __('Xóa nhật ký'); ?>');
                        }
                    });
                }
            });
        });
    });
</script>

<?php
require_once(__DIR__ . '/footer.php');
?>