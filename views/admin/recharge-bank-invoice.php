<?php if (!defined('IN_SITE')) {
    die('The Request Not Found');
}
$body = [
    'title' => __('Hóa đơn nạp tiền'),
    'desc'   => 'CMSNT Panel',
    'keyword' => 'cmsnt, CMSNT, cmsnt.co,'
];
$body['header'] = '
 

 
';
$body['footer'] = '
 
';
require_once(__DIR__.'/../../models/is_admin.php');
if (isset($_GET['id'])) {
    $CMSNT = new DB();
    $id = check_string($_GET['id']);
    $row = $CMSNT->get_row("SELECT * FROM `payment_bank_invoice` WHERE `id` = '$id' ");
    if (!$row) {
        redirect(base_url_admin('recharge-bank'));
    }
} else {
    redirect(base_url_admin('recharge-bank'));
}
require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
require_once(__DIR__.'/nav.php');
if(checkPermission($getUser['admin'], 'edit_recharge_bank_invoice') != true){
    die('<script type="text/javascript">if(!alert("'.__('Bạn không có quyền sử dụng tính năng này').'")){window.history.back();}</script>');
}
?>
<?php
if (isset($_POST['LuuHoaDon'])) {
    // Kiểm tra CSRF token
    checkCSRF();
    
    if ($CMSNT->site('status_demo') != 0) {
        die('<script type="text/javascript">if(!alert("'.__('Chức năng này không thể sử dụng vì đây là trang web demo').'")){window.history.back().location.reload();}</script>');
    }
    if($row['status'] == 'completed'){
        die('<script type="text/javascript">if(!alert("'.__('Hóa đơn đã hoàn thành, không thể cập nhật').'")){window.history.back();}</script>');
    }
    if($_POST['status'] == 'completed'){
        $user = new users();
        $addCredit = $user->AddCredits($row['user_id'], $row['received'], '[Admin] '.__('Thanh toán hoá đơn nạp tiền').' #'.$row['trans_id'], 'bank_invoice_'.$row['trans_id']);
        
        // Ghi log giao dịch nạp tiền
        if($addCredit){
            $CMSNT->insert('deposit_log',[
                'user_id'       => $row['user_id'],
                'method'        => $row['short_name'],
                'amount'        => $row['amount'],
                'received'      => $row['received'],
                'create_time'   => time(),
                'is_virtual'    => 0
            ]);
        }
    }
    $isUpdate = $CMSNT->update("payment_bank_invoice", [
        'status' => check_string($_POST['status']),
        'note' => check_string($_POST['note'])
    ], " `id` = '$id' ");
    if ($isUpdate) {
        $CMSNT->insert("logs", [
            'user_id'       => $getUser['id'],
            'ip'            => myip(),
            'device'        => getUserAgent(),
            'createdate'    => gettime(),
            'action'        => __('Chỉnh sửa hóa đơn')." #".$row['trans_id']."."
        ]);
        /** NOTE ACTION */
        $my_text = $CMSNT->site('noti_action');
        $my_text = str_replace('{domain}', $_SERVER['SERVER_NAME'], $my_text);
        $my_text = str_replace('{username}', $getUser['username'], $my_text);
        $my_text = str_replace('{action}', __('Chỉnh sửa hóa đơn')." #".$row['trans_id'].".", $my_text);
        $my_text = str_replace('{ip}', myip(), $my_text);    
        $my_text = str_replace('{time}', gettime(), $my_text);
        sendMessAdmin($my_text);
        die('<script type="text/javascript">if(!alert("'.__('Lưu thành công!').'")){window.history.back().location.reload();}</script>');
    } else {
        die('<script type="text/javascript">if(!alert("'.__('Lưu thất bại!').'")){window.history.back().location.reload();}</script>');
    }
}
?>
<style>
.timeline {
    position: relative;
    padding-left: 2rem;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 0.75rem;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e5e7eb;
}

.timeline-item {
    position: relative;
    margin-bottom: 1.5rem;
}

.timeline-marker {
    position: absolute;
    left: -2.25rem;
    top: 0.25rem;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #e5e7eb;
    border: 3px solid #fff;
    box-shadow: 0 0 0 1px #e5e7eb;
}

.timeline-item-success .timeline-marker {
    background: #10b981;
    box-shadow: 0 0 0 1px #10b981;
}

.timeline-item-danger .timeline-marker {
    background: #ef4444;
    box-shadow: 0 0 0 1px #ef4444;
}

.timeline-marker-pending {
    background: #f59e0b;
    box-shadow: 0 0 0 1px #f59e0b;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.timeline-title {
    font-size: 0.875rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.timeline-text {
    font-size: 0.75rem;
    margin-bottom: 0;
}
</style>

<div class="main-content app-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0"><?=__('Chi tiết hóa đơn');?> #<?=$row['trans_id'];?></h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="#"><?=__('Nạp tiền');?></a></li>
                        <li class="breadcrumb-item"><a href="<?=base_url_admin('recharge-bank');?>"><?=__('Hóa đơn');?></a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page"><?=__('Chi tiết hóa đơn');?> #<?=$row['trans_id'];?></li>
                    </ol>
                </nav>
            </div>
        </div>

        <!-- CRON Warning -->
        <?php if(time() - $CMSNT->site('check_time_cron_bank') >= 120):?>
            <div class="alert alert-danger alert-dismissible fade show custom-alert-icon shadow-sm" role="alert">
            <svg class="svg-danger" xmlns="http://www.w3.org/2000/svg" height="1.5rem" viewBox="0 0 24 24"
                width="1.5rem" fill="#000000">
                <path d="M0 0h24v24H0z" fill="none" />
                <path
                    d="M15.73 3H8.27L3 8.27v7.46L8.27 21h7.46L21 15.73V8.27L15.73 3zM12 17.3c-.72 0-1.3-.58-1.3-1.3 0-.72.58-1.3 1.3-1.3.72 0 1.3.58 1.3 1.3 0 .72-.58 1.3-1.3 1.3zm1-4.3h-2V7h2v6z" />
            </svg>
            <?=__('Vui lòng thực hiện');?> <b><a target="_blank" class="text-primary" href="https://help.cmsnt.co/huong-dan/huong-dan-xu-ly-khi-website-bao-loi-cron/">CRON JOB</a></b> <?=__('liên kết');?>: <a class="text-primary" href="<?=base_url('cron/bank.php?key='.$CMSNT->site('key_cron_job'));?>"
                target="_blank"><?=base_url('cron/bank.php?key='.$CMSNT->site('key_cron_job'));?></a> <?=__('1 phút 1 lần để hệ thống xử lý nạp tiền tự động.');?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"><i
                    class="bi bi-x"></i></button>
        </div>
        <?php endif?>

        <div class="row">
            <!-- Invoice Overview Card -->
            <div class="col-xl-8">
                <div class="card custom-card overflow-hidden">
                    <div class="card-header border-bottom-0 pb-0">
                        <div class="d-flex align-items-center justify-content-between w-100">
                            <div class="card-title mb-0">
                                <h6 class="fw-semibold mb-0 text-uppercase">
                                    <i class="ri-file-text-line me-2 text-primary"></i><?=__('Thông tin hóa đơn');?>
                                </h6>
                            </div>
                            <div>
                                <?=display_invoice($row['status']);?>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Invoice Header -->
                        <div class="row mb-4">
                            <div class="col-xl-12">
                                <div class="bg-light rounded p-3 border">
                                    <div class="row align-items-center">
                                        <div class="col-xl-6">
                                            <p class="text-muted mb-1 fs-12"><?=__('Mã giao dịch');?></p>
                                            <h5 class="fw-semibold mb-0 text-primary">#<?=$row['trans_id'];?></h5>
                                        </div>
                                        <div class="col-xl-6 text-xl-end">
                                            <p class="text-muted mb-1 fs-12"><?=__('Ngày tạo');?></p>
                                            <h6 class="fw-semibold mb-0"><?=date('d/m/Y H:i', strtotime($row['created_at']));?></h6>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Transaction Details -->
                        <div class="row">
                            <div class="col-xl-6 col-lg-6 col-md-6 col-sm-6">
                                <div class="card border shadow-none mb-3">
                                    <div class="card-header bg-primary-transparent border-bottom-0 pb-2">
                                        <h6 class="card-title text-primary mb-0 text-uppercase">
                                            <i class="ri-user-line me-1"></i><?=__('Thông tin khách hàng');?>
                                        </h6>
                                    </div>
                                    <div class="card-body pt-2">
                                        <div class="mb-3">
                                            <label class="form-label text-muted fs-11 fw-semibold text-uppercase"><?=__('Username');?></label>
                                            <div class="d-flex align-items-center">
                                                <span class="fw-semibold"><?=getRowRealtime("users", $row['user_id'], "username");?></span>
                                                <a href="<?=base_url_admin('user-edit&id='.$row['user_id']);?>" class="badge bg-primary-transparent ms-2">
                                                    <i class="ri-external-link-line fs-10"></i>
                                                </a>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label text-muted fs-11 fw-semibold text-uppercase"><?=__('ID khách hàng');?></label>
                                            <p class="mb-0 fw-semibold">#<?=$row['user_id'];?></p>
                                        </div>
                                        <div class="mb-0">
                                            <label class="form-label text-muted fs-11 fw-semibold text-uppercase"><?=__('Cập nhật cuối');?></label>
                                            <p class="mb-0 text-muted fs-12"><?=date('d/m/Y H:i', strtotime($row['updated_at']));?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-xl-6 col-lg-6 col-md-6 col-sm-6">
                                <div class="card border shadow-none mb-3">
                                    <div class="card-header bg-success-transparent border-bottom-0 pb-2">
                                        <h6 class="card-title text-success mb-0 text-uppercase">
                                            <i class="ri-bank-line me-1"></i><?=__('Thông tin thanh toán');?>
                                        </h6>
                                    </div>
                                    <div class="card-body pt-2">
                                        <div class="mb-3">
                                            <label class="form-label text-muted fs-11 fw-semibold text-uppercase"><?=__('Ngân hàng');?></label>
                                            <div class="d-flex align-items-center">
                                                <span class="fw-semibold"><?=$row['short_name'];?></span>
                                                <a href="<?=base_url_admin('recharge-bank-edit&id='.$row['bank_id']);?>" class="badge bg-success-transparent ms-2">
                                                    <i class="ri-external-link-line fs-10"></i>
                                                </a>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label text-muted fs-11 fw-semibold text-uppercase"><?=__('Số tiền yêu cầu');?></label>
                                            <p class="mb-0 fw-semibold text-dark"><?=format_currency($row['amount']);?></p>
                                        </div>
                                        <div class="mb-0">
                                            <label class="form-label text-muted fs-11 fw-semibold text-uppercase"><?=__('Số tiền thực nhận');?></label>
                                            <p class="mb-0 fw-semibold text-success fs-14"><?=format_currency($row['received']);?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- API Information -->
                        <?php if(!empty($row['api_type']) || !empty($row['api_tid']) || !empty($row['api_desc'])): ?>
                        <div class="mt-4">
                            <div class="card border shadow-none">
                                <div class="card-header bg-info-transparent border-bottom-0 pb-2">
                                    <h6 class="card-title text-info mb-0 text-uppercase">
                                        <i class="ri-code-line me-1"></i><?=__('Thông tin API');?>
                                    </h6>
                                </div>
                                <div class="card-body pt-2">
                                    <div class="table-responsive">
                                        <table class="table table-borderless mb-0">
                                            <tbody>
                                                <?php if(!empty($row['api_type'])): ?>
                                                <tr>
                                                    <td class="ps-0 py-2">
                                                        <span class="text-muted fs-12 fw-semibold text-uppercase"><?=__('API Type');?></span>
                                                    </td>
                                                    <td class="py-2">
                                                        <span class="fw-semibold"><?=$row['api_type'];?></span>
                                                    </td>
                                                </tr>
                                                <?php endif; ?>
                                                <?php if(!empty($row['api_tid'])): ?>
                                                <tr>
                                                    <td class="ps-0 py-2">
                                                        <span class="text-muted fs-12 fw-semibold text-uppercase"><?=__('API TID');?></span>
                                                    </td>
                                                    <td class="py-2">
                                                        <code class="text-primary"><?=$row['api_tid'];?></code>
                                                    </td>
                                                </tr>
                                                <?php endif; ?>
                                                <?php if(!empty($row['api_desc'])): ?>
                                                <tr>
                                                    <td class="ps-0 py-2">
                                                        <span class="text-muted fs-12 fw-semibold text-uppercase"><?=__('API Desc');?></span>
                                                    </td>
                                                    <td class="py-2">
                                                        <code class="fw-semibold"><?=$row['api_desc'];?></code>
                                                    </td>
                                                </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Action Panel -->
            <div class="col-xl-4">
                <div class="card custom-card">
                    <div class="card-header">
                        <div class="card-title text-uppercase">
                            <i class="ri-settings-3-line me-2 text-warning"></i><?=__('Cập nhật hóa đơn');?>
                        </div>
                    </div>
                    <div class="card-body">
                        <form action="" method="POST">
                            <?php echo csrfField(); ?>
                            <!-- Status Update -->
                            <div class="mb-4">
                                <label class="form-label fw-semibold">
                                    <i class="ri-checkbox-circle-line me-1 text-primary"></i><?=__('Trạng thái hóa đơn');?>
                                </label>
                                <select class="form-select" name="status" id="statusSelect">
                                    <option <?=$row['status'] == 'waiting' ? 'selected' : '';?> value="waiting">
                                        <i class="ri-time-line"></i> <?=__('Chờ thanh toán');?>
                                    </option>
                                    <option <?=$row['status'] == 'expired' ? 'selected' : '';?> value="expired">
                                        <i class="ri-close-circle-line"></i> <?=__('Hết hạn');?>
                                    </option>
                                    <option <?=$row['status'] == 'completed' ? 'selected' : '';?> value="completed">
                                        <i class="ri-check-double-line"></i> <?=__('Đã thanh toán');?>
                                    </option>
                                </select>
                                <div class="form-text text-muted fs-11">
                                    <i class="ri-information-line me-1"></i><?=__('Chọn trạng thái phù hợp cho hóa đơn');?>
                                </div>
                                <div id="completedWarning" class="alert alert-warning mt-2 d-none">
                                    <div class="d-flex align-items-start">
                                        <i class="ri-information-line me-2 mt-1"></i>
                                        <div>
                                            <strong><?=__('Lưu ý quan trọng:');?></strong><br>
                                            <?=__('Khi cập nhật trạng thái thành Đã thanh toán, hệ thống sẽ tự động cộng');?> 
                                            <strong class="text-success"><?=format_currency($row['received']);?></strong> 
                                            <?=__('vào tài khoản của user');?> 
                                            <strong><?=getRowRealtime("users", $row['user_id'], "username");?></strong>.
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Notes -->
                            <div class="mb-4">
                                <label class="form-label fw-semibold">
                                    <i class="ri-sticky-note-line me-1 text-info"></i><?=__('Ghi chú quản trị');?>
                                </label>
                                <textarea class="form-control" name="note" rows="4" placeholder="<?=__('Nhập ghi chú cho hóa đơn này (không bắt buộc)');?>"><?=$row['note'];?></textarea>
                                <div class="form-text text-muted fs-11">
                                    <i class="ri-lightbulb-line me-1"></i><?=__('Ghi chú sẽ được lưu trong lịch sử hóa đơn');?>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="d-grid gap-2">
                                <button type="submit" name="LuuHoaDon" class="btn btn-primary">
                                    <i class="ri-save-line me-1"></i><?=__('Cập nhật hóa đơn');?>
                                </button>
                                <a href="<?=base_url_admin('recharge-bank');?>" class="btn btn-light">
                                    <i class="ri-arrow-left-line me-1"></i><?=__('Quay lại danh sách');?>
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Invoice Status Timeline -->
                <div class="card custom-card mt-4">
                    <div class="card-header">
                        <div class="card-title text-uppercase">
                            <i class="ri-time-line me-2 text-success"></i><?=__('Trạng thái hóa đơn');?>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <div class="timeline-item <?=$row['status'] != 'waiting' ? 'timeline-item-success' : '';?>">
                                <div class="timeline-marker"></div>
                                <div class="timeline-content">
                                    <h6 class="timeline-title"><?=__('Hóa đơn được tạo');?></h6>
                                    <p class="timeline-text text-muted fs-12"><?=date('d/m/Y H:i', strtotime($row['created_at']));?></p>
                                </div>
                            </div>
                            <?php if($row['status'] == 'completed'): ?>
                            <div class="timeline-item timeline-item-success">
                                <div class="timeline-marker"></div>
                                <div class="timeline-content">
                                    <h6 class="timeline-title text-success"><?=__('Thanh toán thành công');?></h6>
                                    <p class="timeline-text text-muted fs-12"><?=date('d/m/Y H:i', strtotime($row['updated_at']));?></p>
                                </div>
                            </div>
                            <?php elseif($row['status'] == 'expired'): ?>
                            <div class="timeline-item timeline-item-danger">
                                <div class="timeline-marker"></div>
                                <div class="timeline-content">
                                    <h6 class="timeline-title text-danger"><?=__('Hóa đơn hết hạn');?></h6>
                                    <p class="timeline-text text-muted fs-12"><?=date('d/m/Y H:i', strtotime($row['updated_at']));?></p>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="timeline-item">
                                <div class="timeline-marker timeline-marker-pending"></div>
                                <div class="timeline-content">
                                    <h6 class="timeline-title text-warning"><?=__('Chờ thanh toán');?></h6>
                                    <p class="timeline-text text-muted fs-12"><?=__('Đang chờ xử lý');?></p>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const statusSelect = document.getElementById('statusSelect');
    const completedWarning = document.getElementById('completedWarning');
    const originalStatus = '<?=$row['status'];?>';
    
    function toggleWarning() {
        if (statusSelect.value === 'completed' && originalStatus !== 'completed') {
            completedWarning.classList.remove('d-none');
        } else {
            completedWarning.classList.add('d-none');
        }
    }
    
    // Check initial state
    toggleWarning();
    
    // Listen for changes
    statusSelect.addEventListener('change', toggleWarning);
    
    // Show confirmation dialog when submitting with completed status
    const form = statusSelect.closest('form');
    form.addEventListener('submit', function(e) {
        if (statusSelect.value === 'completed' && originalStatus !== 'completed') {
            const confirmMessage = '<?=__("Xác nhận cộng tiền cho user?");?>\n\n' +
                                   '<?=__("User:");?> <?=getRowRealtime("users", $row['user_id'], "username");?>\n' +
                                   '<?=__("Số tiền:");?> <?=format_currency($row['received']);?>\n\n' +
                                   '<?=__("Bạn có chắc chắn muốn thực hiện thao tác này?");?>';
            
            if (!confirm(confirmMessage)) {
                e.preventDefault();
                return false;
            }
        }
    });
});
</script>

<?php
require_once(__DIR__.'/footer.php');
?>