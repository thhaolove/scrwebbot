<?php if (!defined('IN_SITE')) {
    die('The Request Not Found');
}
$body = [
    'title' => __('List of languages'),
    'desc'   => 'CMSNT Panel',
    'keyword' => 'cmsnt, CMSNT, cmsnt.co,'
];
$body['header'] = '
<link rel="stylesheet" href="https://cdn.datatables.net/1.12.1/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.3.0/css/responsive.bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.3/css/buttons.bootstrap5.min.css">

';
$body['footer'] = '
<script src="https://cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.12.1/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.3.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.3/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.print.min.js"></script>
';
require_once(__DIR__.'/../../models/is_admin.php');
require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
require_once(__DIR__.'/nav.php');
if(checkPermission($getUser['admin'], 'view_lang') != true){
    die('<script type="text/javascript">if(!alert("Bạn không có quyền sử dụng tính năng này")){window.history.back();}</script>');
}
?>
<?php
if (isset($_POST['AddLang'])) {
    if ($CMSNT->site('status_demo') != 0) {
        die('<script type="text/javascript">if(!alert("'.__('This function cannot be used as this is a demo site.').'")){window.history.back().location.reload();}</script>');
    }
    if(checkPermission($getUser['admin'], 'edit_lang') != true){
        die('<script type="text/javascript">if(!alert("Bạn không có quyền sử dụng tính năng này")){window.history.back();}</script>');
    }
    $icon = '';
    if (check_img('icon') == true) {
        $rand = check_string($_POST['lang']);
        $uploads_dir = "assets/storage/flags/flag_$rand.png";
        $tmp_name = $_FILES['icon']['tmp_name'];
        $addIcon = move_uploaded_file($tmp_name, $uploads_dir);
        if ($addIcon) {
            $icon = "assets/storage/flags/flag_$rand.png";
        } else {
            $icon = '';
        }
    }
    $isInsert = $CMSNT->insert("languages", [
        'icon'  => $icon,
        'lang'  => check_string($_POST['lang']),
        'code'  => check_string($_POST['code']),
        'status'    => check_string($_POST['status'])
    ]);

    if ($isInsert) {
        $CMSNT->insert("logs", [
            'user_id'       => $getUser['id'],
            'ip'            => myip(),
            'device'        => getUserAgent(),
            'createdate'    => gettime(),
            'action'        => __('Thêm ngôn ngữ')." (".$_POST['lang'].")."
        ]);
        /** NOTE ACTION */
        $my_text = $CMSNT->site('noti_action');
        $my_text = str_replace('{domain}', $_SERVER['SERVER_NAME'], $my_text);
        $my_text = str_replace('{username}', $getUser['username'], $my_text);
        $my_text = str_replace('{action}', __('Thêm ngôn ngữ')." (".$_POST['lang'].").", $my_text);
        $my_text = str_replace('{ip}', myip(), $my_text);    
        $my_text = str_replace('{time}', gettime(), $my_text);
        sendMessAdmin($my_text);
        die('<script type="text/javascript">if(!alert("'.__('Successfully added new!').'")){location.href = "'.base_url_admin('language-list').'";}</script>');
    } else {
        die('<script type="text/javascript">if(!alert("'.__('Add new failure!').'")){window.history.back().location.reload();}</script>');
    }
}
if (isset($_POST['SaveSettings'])) {
    if ($CMSNT->site('status_demo') != 0) {
        die('<script type="text/javascript">if(!alert("'.__('This function cannot be used because this is a demo site').'")){window.history.back().location.reload();}</script>');
    }
    if(checkPermission($getUser['admin'], 'edit_lang') != true){
        die('<script type="text/javascript">if(!alert("Bạn không có quyền sử dụng tính năng này")){window.history.back();}</script>');
    }
    $CMSNT->insert("logs", [
        'user_id'       => $getUser['id'],
        'ip'            => myip(),
        'device'        => getUserAgent(),
        'createdate'    => gettime(),
        'action'        => __('Cấu hình ngôn ngữ')
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
    $my_text = str_replace('{action}', __('Cấu hình ngôn ngữ'), $my_text);
    $my_text = str_replace('{ip}', myip(), $my_text);    
    $my_text = str_replace('{time}', gettime(), $my_text);
    sendMessAdmin($my_text);
    die('<script type="text/javascript">if(!alert("'.__('Save successfully!').'")){window.history.back().location.reload();}</script>');
} 
?>


<div class="main-content app-content">
    <div class="container-fluid">
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">Languages</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item active" aria-current="page">Languages</li>
                    </ol>
                </nav>
            </div>
        </div>
        <div class="row">
            <div class="col-xl-6">
                <div class="card custom-card">
                    <div class="card-body">
                        <form action="" method="POST" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-lg-12 col-xl-12">
                                    <div class="row mb-4">
                                        <label class="col-sm-4 col-form-label"
                                            for="example-hf-email"><?=__('Loại');?></label>
                                        <div class="col-sm-8">
                                            <select class="form-control" id="language_type" name="language_type">
                                                <option
                                                    <?=$CMSNT->site('language_type') == 'manual' ? 'selected' : '';?>
                                                    value="manual">Dịch thủ công
                                                </option>
                                                <option
                                                    <?=$CMSNT->site('language_type') == 'gtranslate' ? 'selected' : '';?>
                                                    value="gtranslate">Gtranslate.io
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-12 col-xl-12" id="gtranslate_script" style="display:none;">
                                    <div class="row mb-4">
                                        <label class="col-sm-4 col-form-label" for="example-hf-email">Gtranslate
                                            Script</label>
                                        <div class="col-sm-8">
                                            <textarea class="form-control" rows="5" name="gtranslate_script"><?=$CMSNT->site('gtranslate_script');?></textarea>
                                            <small>Truy cập vào <a href="https://gtranslate.io/website-translator-widget" class="text-primary" target="_blank">gtranslate.io</a> để tạo mã sciprt theo nhu cầu của bạn, hoặc sử dụng sciprt mặc định của chúng tôi cung cấp.</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-grid gap-2 mb-4">
                                <button type="submit" name="SaveSettings" class="btn btn-primary btn-block"><i
                                        class="fa fa-fw fa-save me-1"></i>
                                    <?=__('Save');?></button>
                            </div>
                        </form>
                        <p>Hướng dẫn sử dụng tính năng đa ngôn ngữ: <a target="_blank" class="text-primary" href="https://help.cmsnt.co/danh-muc/huong-dan-su-dung-tinh-nang-da-ngon-ngu/">https://help.cmsnt.co/danh-muc/huong-dan-su-dung-tinh-nang-da-ngon-ngu/</a></p>
                    </div>
                </div>
            </div>
            <div class="col-xl-12" id="table2a">
                <div class="card custom-card">
                    <div class="card-header justify-content-between">
                        <div class="card-title">
                            DANH SÁCH NGÔN NGỮ
                        </div>
                        <button type="button" data-bs-toggle="modal" data-bs-target="#exampleModalScrollable2"
                            class="btn btn-sm btn-primary btn-wave waves-light waves-effect waves-light"><i
                                class="ri-add-line fw-semibold align-middle"></i> Thêm ngôn ngữ mới</button>
                    </div>
                    <div class="card-body">
                        <table id="datatable-basic" class="table text-nowrap table-striped table-hover table-bordered"
                            style="width:100%">
                            <thead>
                                <tr>
                                    <th style="width: 5px;">#</th>
                                    <th><?=__('Language');?></th>
                                    <th><?=__('ISO Code');?></th>
                                    <th><?=__('Default');?></th>
                                    <th class="text-center"><?=__('Status');?></th>
                                    <th width="20%"><?=__('Action');?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($CMSNT->get_list("SELECT * FROM `languages` ORDER BY `id` DESC ") as $row) {?>
                                <tr>
                                    <td><?=$row['id'];?></td>
                                    <td><img width="25px" src="<?=base_url($row['icon']);?>"> <?=$row['lang'];?><br>
                                    <small><a href="<?=base_url($row['code']);?>" data-toggle="tooltip" data-placement="bottom" title="<?=__('Tự động chuyển sang ngôn ngữ này khi user truy cập liên kết này');?>" target="_blank"><?=base_url($row['code']);?></a></small>
                                    </td>
                                    <td><?=$row['code'];?></td>
                                    <td><?=display_mark($row['lang_default']);?></td>
                                    <td class="text-center"><?=display_status_product($row['status']);?></td>
                                    <td class="text-center fs-base">
                                        <a type="button" onclick="setDefault('<?=$row['id'];?>')"
                                            class="btn btn-sm btn-light" data-bs-toggle="tooltip"
                                            title="<?=__('Set Default');?>">
                                            <i class="fa fa-key"></i>
                                        </a>
                                        <a type="button" href="<?=base_url_admin('translate-list&id='.$row['id']);?>"
                                            class="btn btn-sm btn-light" data-bs-toggle="tooltip"
                                            title="<?=__('Translate');?>">
                                            <i class="fa fa-language"></i>
                                        </a>
                                        <a type="button" href="<?=base_url_admin('language-edit&id='.$row['id']);?>"
                                            class="btn btn-sm btn-light" data-bs-toggle="tooltip"
                                            title="<?=__('Edit');?>">
                                            <i class="fa fa-pencil-alt"></i>
                                        </a>
                                        <a type="button" onclick="RemoveRow('<?=$row['id'];?>')"
                                            class="btn btn-sm btn-light" data-bs-toggle="tooltip"
                                            title="<?=__('Delete');?>">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php }?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>





<div class="modal fade" id="exampleModalScrollable2" tabindex="-1" aria-labelledby="exampleModalScrollable2"
    data-bs-keyboard="false" aria-hidden="true">
    <!-- Scrollable modal -->
    <div class="modal-dialog modal-dialog-centered modal-lg dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="staticBackdropLabel2">Thêm ngôn ngữ mới</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="row mb-4">
                        <label class="col-sm-4 col-form-label" for="example-hf-email">Tên ngôn ngữ</label>
                        <div class="col-sm-8">
                            <input type="text" class="form-control" name="lang"
                                placeholder="Nhập tên ngôn ngữ VD: English" required>
                        </div>
                    </div>
                    <div class="row mb-4">
                        <label class="col-sm-4 col-form-label" for="example-hf-email">ISO Code</label>
                        <div class="col-sm-8">
                            <input type="text" class="form-control" name="code"
                            placeholder="VD: vi, en, th, fr, zh"  required>
                            <small>Để sử dụng tính năng dịch tự động, Code phải được thêm vào và phải là ISO 639-1 <a class="text-primary" href="https://www.loc.gov/standards/iso639-2/php/code_list.php" target="_blank">Xem ISO</a> .</small>
                        </div>
                    </div>
                    <div class="row mb-4">
                        <label class="col-sm-4 col-form-label" for="example-hf-email">Flag</label>
                        <div class="col-sm-8">

                            <input class="form-control" type="file" name="icon" required id="example-file-input">
                        </div>
                    </div>
                    <div class="row mb-4">
                        <label class="col-sm-4 col-form-label" for="example-hf-email">Trạng thái</label>
                        <div class="col-sm-8">
                            <select class="form-control" name="status" required>
                                <option value="1"><?=__('Show');?></option>
                                <option value="0"><?=__('Hide');?></option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light " data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="AddLang" class="btn btn-primary shadow-primary btn-wave"><i
                            class="fa fa-fw fa-plus me-1"></i>
                        <?=__('Submit');?></button>
                </div>
            </form>
        </div>
    </div>
</div>



<?php
require_once(__DIR__.'/footer.php');
?>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Lắng nghe sự kiện thay đổi của select
    document.getElementById('language_type').addEventListener('change', function() {
        // Lấy giá trị được chọn
        var selectedValue = this.value;

        // Kiểm tra nếu giá trị là 'gtranslate' thì ẩn div #table, ngược lại hiển thị
        if (selectedValue === 'gtranslate') {
            document.getElementById('table2a').style.display = 'none';
            document.getElementById('gtranslate_script').style.display = 'block';
        } else {
            document.getElementById('table2a').style.display = 'block';
            document.getElementById('gtranslate_script').style.display = 'none';
        }
    });

    // Kích hoạt sự kiện change để ẩn/div #table ban đầu nếu là 'gtranslate'
    var initialSelectedValue = document.getElementById('language_type').value;
    if (initialSelectedValue === 'gtranslate') {
        document.getElementById('table2a').style.display = 'none';
        document.getElementById('gtranslate_script').style.display = 'block';
    }
});
</script>
<script type="text/javascript">
function setDefault(id) {
    $('.setDefault').html('<i class="fa fa-spinner fa-spin"></i> Loading...').prop('disabled',
        true);
    $.ajax({
        url: "<?=BASE_URL("ajaxs/admin/update.php");?>",
        method: "POST",
        dataType: "JSON",
        data: {
            action: 'setDefaultLanguage',
            id: id
        },
        success: function(result) {
            if (result.status == 'success') {
                showMessage(result.msg, result.status);
                location.reload();
            } else {
                showMessage(result.msg, result.status);
            }
        },
        error: function() {
            alert(html(result));
            location.reload();
        }
    });
}

function RemoveRow(id) {
    cuteAlert({
        type: "question",
        title: "<?=__('Confirm Language Remove');?>",
        message: "Bạn có chắc chắn muốn xóa ngôn ngữ ID " + id + " không ?",
        confirmText: "Okey",
        cancelText: "Close"
    }).then((e) => {
        if (e) {
            $.ajax({
                url: "<?=BASE_URL("ajaxs/admin/remove.php");?>",
                method: "POST",
                dataType: "JSON",
                data: {
                    action: 'removeLanguage',
                    id: id
                },
                success: function(result) {
                    if (result.status == 'success') {
                        showMessage(result.msg, result.status);
                        location.reload();
                    } else {
                        showMessage(result.msg, result.status);
                    }
                },
                error: function() {
                    alert(html(result));
                    location.reload();
                }
            });
        }
    })
}
</script>

<script>
$('#datatable-basic').DataTable({
    language: {
        searchPlaceholder: 'Search...',
        sSearch: '',
    },
    "pageLength": 10,
    scrollX: true
});
</script>