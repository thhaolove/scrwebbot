<?php if (!defined('IN_SITE')) {
    die('The Request Not Found');
}
$body = [
    'title' => 'Translate',
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
if (isset($_GET['id'])) {
    $id = check_string($_GET['id']);
    $row = $CMSNT->get_row("SELECT * FROM `languages` WHERE `id` = '$id' ");
    if (!$row) {
        redirect(base_url('admin/language-list'));
    }
} else {
    redirect(base_url('admin/language-list'));
}
require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
require_once(__DIR__.'/nav.php');
if(checkPermission($getUser['admin'], 'edit_lang') != true){
    die('<script type="text/javascript">if(!alert("Bạn không có quyền sử dụng tính năng này")){window.history.back();}</script>');
}
?>
<?php
if (isset($_POST['addTranslate'])) {
    if ($CMSNT->site('status_demo') != 0) {
        die('<script type="text/javascript">if(!alert("Không được dùng chức năng này vì đây là trang web demo.")){window.history.back().location.reload();}</script>');
    }
    foreach ($CMSNT->get_list("SELECT * FROM `languages` WHERE `id` != '".$row['id']."' ") as $lang) {
        if ($CMSNT->num_rows("SELECT * FROM `translate` WHERE `name` = '".check_string($_POST['name'])."' AND `lang_id` = '".$lang['id']."'  ") < 1) {
            $CMSNT->insert("translate", [
                'value' => check_string($_POST['name']),
                'name'  => check_string($_POST['name']),
                'lang_id'   => $lang['id']
            ]);
        }
    }
    if ($CMSNT->num_rows("SELECT * FROM `translate` WHERE `name` = '".check_string($_POST['name'])."' AND `lang_id` = '".$row['id']."' ") < 1) {
        $isInsert = $CMSNT->insert("translate", [
            'value' => check_string($_POST['value']),
            'name'  => check_string($_POST['name']),
            'lang_id'   => $row['id']
        ]);
    }
    if (isset($isInsert)) {
        $CMSNT->insert("logs", [
            'user_id'       => $getUser['id'],
            'ip'            => myip(),
            'device'        => getUserAgent(),
            'createdate'    => gettime(),
            'action'        => "Add Translate (".check_string($_POST['value']).")."
        ]);
       die('<script type="text/javascript">window.location="'.base_url_admin('translate-list&id='.$id).'";</script>');
    } else {
        $CMSNT->update("translate", [
            'value' => check_string($_POST['value']),
            'name'  => check_string($_POST['name']),
            'lang_id'   => $row['id']
        ], " `name` = '".check_string($_POST['name'])."' AND `lang_id` = '".$row['id']."'  ");
        die('<script type="text/javascript">window.location="'.base_url_admin('translate-list&id='.$id).'";</script>');
    }
}
if (isset($_POST['updateTranslate'])) {
    if ($row['lang_default'] == 1) {
        die('<script type="text/javascript">if(!alert("'.__('You cannot format because this is the system default language').'")){window.history.back().location.reload();}</script>');
    }
    $isDelete = $CMSNT->remove("translate", " `lang_id` = '".$row['id']."' ");
    if ($isDelete) {
        foreach ($CMSNT->get_list("SELECT * FROM `translate` WHERE `lang_id` = '".$CMSNT->get_row("SELECT * FROM `languages` WHERE `lang_default` = 1 ")['id']."' ") as $tran) {
            $CMSNT->insert("translate", [
                'lang_id'   => $row['id'],
                'value'     => $tran['value'],
                'name'      => $tran['name']
            ]);
        }
        $CMSNT->insert("logs", [
            'user_id'       => $getUser['id'],
            'ip'            => myip(),
            'device'        => getUserAgent(),
            'createdate'    => gettime(),
            'action'        => __('Change language content.')
        ]);
        die('<script type="text/javascript">if(!alert("Cập nhật thành công !")){window.history.back().location.reload();}</script>');
    }
}
?>

 

<div class="main-content app-content">
    <div class="container-fluid">
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">Translates <?=$row['lang'];?></h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="<?=base_url_admin('language-list');?>">Languages</a></li>
                        <li class="breadcrumb-item"><a href="#"><?=$row['lang'];?></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Translates</li>
                    </ol>
                </nav>
            </div>
        </div>
        <div class="row">
            <div class="col-xl-7">
                <div class="card custom-card">
                    <div class="card-header justify-content-between">
                        <div class="card-title">
                            THÊM NỘI DUNG
                        </div>
                    </div>
                    <div class="card-body">
                        <form action="" method="POST">
                            <div class="form-group mb-3">
                                <label>Default:</label>
                                <textarea class="form-control" name="name"
                                    placeholder="Nhập nội dung tiếng việt mặc định" required></textarea>
                            </div>
                            <div class="form-group mb-3">
                                <label><?=$row['lang'];?>:</label>
                                <textarea class="form-control" name="value" placeholder="Nhập nội dung cần dịch"
                                    required></textarea>
                            </div>
                            <div class="d-grid gap-2 mb-4">
                                <button type="submit" name="addTranslate" class="btn btn-primary btn-wave">THÊM
                                    NGAY</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-xl-5">
                <div class="card custom-card">
                    <div class="card-header justify-content-between">
                        <div class="card-title">
                            LƯU Ý
                        </div>
                    </div>
                    <div class="card-body">
                        <p>Hệ thống tự động cập nhật nội dung mới khi nội dung bạn thêm vào bị trùng lặp.</p>
                        <p>Quý khách có thể sử dụng tính năng này để thay đổi nội dung trên website.</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-header justify-content-between">
                        <div class="card-title">
                            TRANSLATES
                        </div>
                        <button type="button" data-bs-toggle="modal" data-bs-target="#exampleModalScrollable2"
                            class="btn btn-sm btn-primary btn-wave waves-light waves-effect waves-light"><i
                                class='bx bx-reset'></i> Tạo lại bản dịch</button>
                    </div>
                    <div class="card-body">
                        <table id="datatable-basic" class="table table-striped table-hover table-bordered"
                            style="width:100%">
                            <thead>
                                <tr>
                                    <th width="3%">#</th>
                                    <th><?=__('Default');?></th>
                                    <th><?=$row['lang'];?></th>
                                    <th width="20%"><?=__('Action');?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i=0; foreach ($CMSNT->get_list("SELECT * FROM `translate` WHERE `lang_id` = '".$row['id']."' ORDER BY id DESC ") as $trans) {?>
                                <tr onchange="updateForm(`<?= $trans['id']; ?>`)">
                                    <td><?=$i++;?></td>
                                    <td>
                                        <textarea class="form-control" disabled><?=$trans['name'];?></textarea>
                                    </td>
                                    <td>
                                        <textarea class="form-control"
                                            id="value<?=$trans['id'];?>"><?=$trans['value'];?></textarea>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-list">
                                            <button type="button" class="btn btn-primary-gradient btn-wave"
                                                onclick="autoTranslate('<?= $trans['id']; ?>', '<?= addslashes($trans['name']); ?>', '<?= $row['code']; ?>', this)">
                                                <i class="ri-translate"></i>
                                                <?= __('Dịch tự động'); ?>
                                            </button>
                                            <button type="button" class="btn btn-danger-gradient btn-wave"
                                                onclick="RemoveRow('<?= $trans['id']; ?>', '<?= addslashes($trans['name']); ?>')">
                                                <i class="ri-delete-bin-line"></i>
                                                <?= __('Delete'); ?>
                                            </button>
                                        </div>

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


<?php
require_once(__DIR__.'/footer.php');
?>


<div class="modal fade" id="exampleModalScrollable2" tabindex="-1" aria-labelledby="exampleModalScrollable2"
    data-bs-keyboard="false" aria-hidden="true">
    <!-- Scrollable modal -->
    <div class="modal-dialog modal-dialog-centered modal-lg dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="staticBackdropLabel2">Cài lại nội dung mặc định</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <p>Hệ thống sẽ cập nhật lại nội dung giống ngôn ngữ
                        <b><?=$CMSNT->get_row("SELECT * FROM `languages` WHERE `lang_default` = 1")['lang'];?></b>, bạn
                        có chắc chắn muốn thực hiện thay đổi này không?
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light " data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="updateTranslate" class="btn btn-primary shadow-primary btn-wave"><i
                            class="fa fa-fw fa-plus me-1"></i>Xác nhận</button>
                </div>
            </form>
        </div>
    </div>
</div>


<script>
/**
 * Hàm gọi API dịch tự động và hiển thị hiệu ứng spinner trên nút khi đang thực hiện.
 * @param {string} id - ID của bản ghi để cập nhật nội dung dịch vào textarea tương ứng.
 * @param {string} defaultText - Văn bản cần dịch (nội dung mặc định).
 * @param {string} targetLang - Mã ngôn ngữ đích (ví dụ: 'en' hoặc 'vi').
 * @param {HTMLElement} btn - Nút được click để hiển thị hiệu ứng spinner.
 */
function autoTranslate(id, defaultText, targetLang, btn) {
    // Kiểm tra nếu targetLang trống
    if (!targetLang || targetLang.trim() === "") {
        alert("Vui lòng cập nhật ISO CODE ngôn ngữ trước khi thực hiện dịch tự động!");
        // Chuyển hướng trang hiện tại
        window.location.href = "<?= base_url_admin("language-edit&id=$id"); ?>";
        return;
    }

    // Lưu lại nội dung ban đầu của nút
    const originalHTML = btn.innerHTML;
    // Vô hiệu hóa nút và thêm spinner (sử dụng lớp của Bootstrap)
    btn.disabled = true;
    btn.innerHTML =
        `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Translating...`;

    // URL của API
    const apiUrl = 'https://api.cmsnt.co/translation-api.php';
    const url = `${apiUrl}?license_key=<?=$CMSNT->site('license_key');?>&q=${encodeURIComponent(defaultText)}&target=${encodeURIComponent(targetLang)}`;

    // Gọi API bằng fetch
    fetch(url)
        .then(response => response.json())
        .then(data => {
            // Nếu có lỗi từ API
            if (data.error) {
                alert("Lỗi: " + data.error);
                btn.disabled = false;
                btn.innerHTML = originalHTML;
                return;
            }
            // Xử lý kết quả trả về
            let translatedText = "";
            if (data.data && data.data.translations && data.data.translations.length > 0) {
                translatedText = data.data.translations[0].translatedText;
                // Cập nhật giá trị cho textarea
                document.getElementById("value" + id).value = translatedText;
                // Kích hoạt sự kiện change thủ công (dùng jQuery)
                $("#value" + id).trigger("change");

            } else {
                alert("Không nhận được kết quả dịch.");
                btn.disabled = false;
                btn.innerHTML = originalHTML;
                return;
            }
            // Cập nhật kết quả dịch vào textarea tương ứng
            document.getElementById("value" + id).value = translatedText;
            // Khôi phục trạng thái nút
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        })
        .catch(error => {
            console.error("Có lỗi xảy ra: ", error);
            alert("Có lỗi xảy ra khi dịch.");
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        });
}
</script>





<script type="text/javascript">
function updateForm(id) {
    $.ajax({
        url: "<?= BASE_URL("ajaxs/admin/update.php"); ?>",
        method: "POST",
        dataType: "JSON",
        data: {
            action: 'changeTranslate',
            id: id,
            value: $("#value" + id).val()
        },
        success: function(result) {
            if (result.status == 'success') {
                //showMessage(result.msg, result.status);
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

function RemoveRow(id, name) {
    cuteAlert({
        type: "question",
        title: "Xác Nhận Xóa Ngôn Ngữ",
        message: "Bạn có chắc chắn muốn xóa ngôn ngữ (" + name + ") không ?",
        confirmText: "Đồng Ý",
        cancelText: "Hủy"
    }).then((e) => {
        if (e) {
            $.ajax({
                url: "<?=BASE_URL("ajaxs/admin/remove.php");?>",
                method: "POST",
                dataType: "JSON",
                data: {
                    action: 'removeTranslate',
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
    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Tất cả"]],
    "pageLength": 50,
    scrollX: true
});
</script>