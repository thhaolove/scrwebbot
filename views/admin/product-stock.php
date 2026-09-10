<?php if (!defined('IN_SITE')) {
    die('The Request Not Found');
}
$body = [
    'title' => 'Kho hàng sản phẩm | '.$CMSNT->site('title'),
    'desc'   => $CMSNT->site('description'),
    'keyword' => $CMSNT->site('keywords')
];
$body['header'] = '
<script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.6/clipboard.min.js"></script>
';
$body['footer'] = '

';
require_once(__DIR__.'/../../models/is_admin.php');
if (isset($_GET['code'])) {
    $code = check_string($_GET['code']);
} else {
    redirect(base_url_admin('products'));
}
require_once(__DIR__.'/header.php');
require_once(__DIR__.'/sidebar.php');
if(checkPermission($getUser['admin'], 'edit_stock_product') != true){
    die('<script type="text/javascript">if(!alert("Bạn không có quyền sử dụng tính năng này")){window.history.back();}</script>');
}

if(isset($_GET['limit'])){
    $limit = intval(check_string($_GET['limit']));
}else{
    $limit = 10;
}
if(isset($_GET['page'])){
    $page = check_string(intval($_GET['page']));
}
else{
    $page = 1;
}
$from = ($page - 1) * $limit;
$where = " `product_code` = '$code' ";
$create_gettime = '';
$uid = '';
$shortByDate  = '';
$user_id = '';
$username = '';
$account = '';

if(!empty($_GET['account'])){
    $account = check_string($_GET['account']);
    $where .= ' AND `account` LIKE "%'.$account.'%" ';
}
if(!empty($_GET['uid'])){
    $uid = check_string($_GET['uid']);
    $where .= ' AND `uid` LIKE "%'.$uid.'%" ';
}
if (!empty($_GET['username'])) {
    $username = check_string($_GET['username']);
    if($idUser = $CMSNT->get_row(" SELECT * FROM `users` WHERE `username` = '$username' ")){
        $where .= ' AND `user_id` =  "'.$idUser['id'].'" ';
    }else{
        $where .= ' AND `user_id` =  "" ';
    }
}
if(!empty($_GET['user_id'])){
    $user_id = check_string($_GET['user_id']);
    $where .= ' AND `user_id` = "'.$user_id.'" ';
}
if(!empty($_GET['create_gettime'])){
    $create_gettime = check_string($_GET['create_gettime']);
    $createdate = $create_gettime;
    $create_gettime_1 = str_replace('-', '/', $create_gettime);
    $create_gettime_1 = explode(' to ', $create_gettime_1);

    if($create_gettime_1[0] != $create_gettime_1[1]){
        $create_gettime_1 = [$create_gettime_1[0].' 00:00:00', $create_gettime_1[1].' 23:59:59'];
        $where .= " AND `create_gettime` >= '".$create_gettime_1[0]."' AND `create_gettime` <= '".$create_gettime_1[1]."' ";
    }
}
if(isset($_GET['shortByDate'])){
    $shortByDate = check_string($_GET['shortByDate']);
    $yesterday = date('Y-m-d', strtotime("-1 day"));
    $currentWeek = date("W");
    $currentMonth = date('m');
    $currentYear = date('Y');
    $currentDate = date("Y-m-d");
    if($shortByDate == 1){
        $where .= " AND `create_gettime` LIKE '%".$currentDate."%' ";
    }
    if($shortByDate == 2){
        $where .= " AND YEAR(create_gettime) = $currentYear AND WEEK(create_gettime, 1) = $currentWeek ";
    }
    if($shortByDate == 3){
        $where .= " AND MONTH(create_gettime) = '$currentMonth' AND YEAR(create_gettime) = '$currentYear' ";
    }
}

$listDatatable = $CMSNT->get_list(" SELECT * FROM `product_stock` WHERE $where ORDER BY `id` DESC LIMIT $from,$limit ");
$totalDatatable = $CMSNT->num_rows(" SELECT * FROM `product_stock` WHERE $where ORDER BY id DESC ");
$urlDatatable = pagination(base_url_admin("product-stock&limit=$limit&shortByDate=$shortByDate&code=$code&uid=$uid&create_gettime=$create_gettime&user_id=$user_id&username=$username&account=$account&"), $from, $totalDatatable, $limit);


if(isset($_POST['RemoveAccounts'])){
    if ($CMSNT->site('status_demo') != 0) {
        die('<script type="text/javascript">if(!alert("Không được dùng chức năng này vì đây là trang web demo.")){window.history.back().location.reload();}</script>');
    }
    $value_remove = 0;
    if(empty($_POST['list_account_remove'])){
        die('<script type="text/javascript">if(!alert("Vui lòng nhập tài khoản cần thêm.")){window.history.back().location.reload();}</script>');
    }
    $list_account_remove = trim($_POST['list_account_remove']);
    // Tách dòng an toàn cho Windows/Unix/Mac
    $list = preg_split('/\r\n|\r|\n/', $list_account_remove);
    foreach ($list as $account){
        $account = trim($account);
        if ($account === '') { continue; }
        // Hỗ trợ UID|..., UID:..., hoặc chỉ UID
        if (strpos($account, '|') !== false) {
            $uid_remove = trim(explode('|', $account)[0]);
        } elseif (strpos($account, ':') !== false) {
            $uid_remove = trim(explode(':', $account)[0]);
        } else {
            $uid_remove = trim(explode(' ', $account)[0]);
        }
        // Validate UID
        $uid_remove = validate_string($uid_remove, 5000, 1);
        if ($uid_remove === false) { continue; }
        // Xóa bằng Prepared Statements
        $isRemove = $CMSNT->remove("product_stock", " `uid` = ? ", [$uid_remove]);
        if ($isRemove) {
            $value_remove++;
            $CMSNT->insert("logs", [
                'user_id'       => $getUser['id'],
                'ip'            => myip(),
                'device'        => getUserAgent(),
                'createdate'    => gettime(),
                'action'        => "Xóa tài khoản ($uid_remove) khỏi kho hàng đang bán"
            ]);
        }
    }
    /** NOTE ACTION */
    $my_text = $CMSNT->site('noti_action');
    $my_text = str_replace('{domain}', $_SERVER['SERVER_NAME'], $my_text);
    $my_text = str_replace('{username}', $getUser['username'], $my_text);
    $my_text = str_replace('{action}', "Xóa $value_remove tài khoản khỏi kho hàng", $my_text);
    $my_text = str_replace('{ip}', myip(), $my_text);    
    $my_text = str_replace('{time}', gettime(), $my_text);
    sendMessAdmin($my_text);
    die('<script type="text/javascript">if(!alert("Xóa thành công ['.$value_remove.'] tài khoản.")){window.history.back().location.reload();}</script>');

}

if(isset($_POST['AddAccounts'])){
    if ($CMSNT->site('status_demo') != 0) {
        die('<script type="text/javascript">if(!alert("Không được dùng chức năng này vì đây là trang web demo.")){window.history.back().location.reload();}</script>');
    }
    $value_add = 0;
    $value_update = 0;
    $value_skip_stock = 0; // Đếm số tài khoản bỏ qua vì trùng trong kho
    $value_skip_sold = 0;  // Đếm số tài khoản bỏ qua vì đã bán
    $value_total = 0;      // Tổng số tài khoản được xử lý
    $list = [];
    if($_POST['type'] == 'an'){
        if(empty($_POST['accounts'])){
            die('<script type="text/javascript">if(!alert("Vui lòng nhập tài khoản cần thêm.")){window.history.back().location.reload();}</script>');
        }
        $accounts = $_POST['accounts'];
        array_push($list, $accounts);
    }
    if($_POST['type'] == 'multi'){
        if(empty($_POST['accounts'])){
            die('<script type="text/javascript">if(!alert("Vui lòng nhập tài khoản cần thêm.")){window.history.back().location.reload();}</script>');
        }
        $accounts = $_POST['accounts'];
        $list = explode(PHP_EOL, $accounts);
        // Lọc ra những dòng rỗng
        $list = array_filter($list, function($line) {
            return trim($line) !== ''; // Loại bỏ dòng rỗng và dòng chỉ chứa khoảng trắng
        });
    }
    if($_POST['type'] == 'txt'){
        $file_name = $_FILES["files_txt"]["name"];
        $file_tmp = $_FILES["files_txt"]["tmp_name"];
        $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
        if(strtolower($file_extension) == "txt"){
            $list = [];
            if (($handle = fopen($file_tmp, "r")) !== FALSE) {
                while (($line = fgets($handle)) !== FALSE) {
                    $line = trim($line);
                    if(!empty($line)) {
                        $list[] = $line;
                    }
                }
                fclose($handle);
            }
        } else {
            die('<script type="text/javascript">if(!alert("Vui lòng chọn file có định dạng .txt")){window.history.back().location.reload();}</script>');
        }         
    }
    foreach ($list as $account){
        // Kiểm tra delimiter: ưu tiên | > : > dấu cách
        if (strpos($account, '|') !== false) {
            $uid = explode('|', $account)[0];
        } elseif (strpos($account, ':') !== false) {
            $uid = explode(':', $account)[0];
        } else {
            $uid = explode(' ', $account)[0];
        }
        $value_total++; // Đếm tổng số tài khoản được xử lý
        
        // Kiểm tra lọc trùng UID tài khoản đã bán
        if (isset($_POST['loc_trung_uid_sold']) && $_POST['loc_trung_uid_sold'] == 1){
            if($CMSNT->get_row(" SELECT * FROM `product_sold` WHERE `uid` = '$uid' ")){
                $value_skip_sold++; // Đếm tài khoản bỏ qua vì đã bán
                continue; // Bỏ qua tài khoản này vì đã bán
            }
        }
        
        if (isset($_POST['loc_trung_uid']) && $_POST['loc_trung_uid'] == 1){
            if($CMSNT->get_row(" SELECT * FROM `product_stock` WHERE `uid` = '$uid' ")){
                $isUpdate = $CMSNT->update("product_stock", [
                    'product_code'  => $code,
                    'seller'        => $getUser['id'],
                    'uid'           => $uid,
                    'account'       => $account,
                    'create_gettime'   => gettime()
                ], " `uid` = '$uid' ");
                if ($isUpdate) {
                    $value_update++;
                }
            }else{
                $isAdd = $CMSNT->insert("product_stock", [
                    'product_code'  => $code,
                    'seller'        => $getUser['id'],
                    'uid'           => $uid,
                    'account'       => $account,
                    'create_gettime'   => gettime()
                ]);
                if ($isAdd) {
                    $value_add++;
                }
            }
        }
        else{
            $isAdd = $CMSNT->insert("product_stock", [
                'product_code'  => $code,
                'seller'        => $getUser['id'],
                'uid'           => $uid,
                'account'       => $account,
                'create_gettime'   => gettime()
            ]);
            if ($isAdd) {
                $value_add++;
            }  
        }
    }
    $CMSNT->insert("logs", [
        'user_id'       => $getUser['id'],
        'ip'            => myip(),
        'device'        => getUserAgent(),
        'createdate'    => gettime(),
        'action'        => "Import $value_add tài khoản vào kho hàng $code"
    ]);
    /** NOTE ACTION */
    $my_text = $CMSNT->site('noti_action');
    $my_text = str_replace('{domain}', $_SERVER['SERVER_NAME'], $my_text);
    $my_text = str_replace('{username}', $getUser['username'], $my_text);
    $my_text = str_replace('{action}', "Import $value_add tài khoản vào kho hàng $code", $my_text);
    $my_text = str_replace('{ip}', myip(), $my_text);    
    $my_text = str_replace('{time}', gettime(), $my_text);
    sendMessAdmin($my_text);
    // Tạo thông báo chi tiết và đẹp mắt
    $notification = "═══════════════════════════════════════\n";
    $notification .= "🎉 KẾT QUẢ UPLOAD TÀI KHOẢN 🎉\n";
    $notification .= "═══════════════════════════════════════\n\n";
    $notification .= "📊 THỐNG KÊ CHI TIẾT:\n";
    $notification .= "▫️ Tổng số tài khoản xử lý: " . $value_total . "\n\n";
    $notification .= "✅ Thêm mới thành công: " . $value_add . " tài khoản\n";
    $notification .= "🔄 Cập nhật thành công: " . $value_update . " tài khoản\n";
    
    if ($value_skip_stock > 0) {
        $notification .= "⚠️ Bỏ qua (trùng trong kho): " . $value_skip_stock . " tài khoản\n";
    }
    if ($value_skip_sold > 0) {
        $notification .= "🚫 Bỏ qua (đã bán): " . $value_skip_sold . " tài khoản\n";
    }
    
    $notification .= "\n═══════════════════════════════════════\n";
    $notification .= "💼 Kho hàng: " . $code . "\n";
    $notification .= "⏰ Thời gian: " . date('d/m/Y H:i:s') . "\n";
    $notification .= "═══════════════════════════════════════";
    
    die('<script type="text/javascript">if(!alert("' . str_replace("\n", "\\n", $notification) . '")){window.history.back().location.reload();}</script>');
}

?>


<div class="main-content app-content">
    <div class="container-fluid">
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0"><a type="button"
                    class="btn btn-dark btn-raised-shadow btn-wave btn-sm me-1"
                    href="<?=base_url_admin('products');?>"><i class="fa-solid fa-arrow-left"></i></a> Quản lý kho hàng
                "<b style="color:red;"><?=$code;?></b>"</h1>
        </div>
        <div class="row">
            <div class="col-xl-6">
                <div class="card custom-card">
                    <div class="card-header justify-content-between">
                        <div class="card-title">
                            SẢN PHẨM ĐANG SỬ DỤNG KHO HÀNG NÀY
                        </div>
                    </div>
                    <div class="card-body p-2">
                        <div class="table-responsive mb-2">
                            <table class="table text-nowrap table-striped table-hover table-bordered">
                                <thead>
                                    <tr>
                                        <th class="text-center"><?=__('Thao tác');?></th>
                                        <th class="text-center"><?=__('Trạng thái');?></th>
                                        <th><?=__('Sản phẩm');?></th>
                                        <th class="text-center"><?=__('Chuyên mục');?></th>
                                        <th class="text-center"><?=__('Giá bán');?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($CMSNT->get_list(" SELECT * FROM `products` WHERE `code` = '$code' ") as $product): ?>
                                    <tr onchange="updateFormProduct(`<?=$product['id'];?>`)">
                                        <td>
                                            <a type="button"
                                                href="<?=base_url_admin('product-edit&id='.$product['id']);?>"
                                                class="btn btn-sm btn-info" data-bs-toggle="tooltip"
                                                title="<?=__('Chỉnh sửa');?>">
                                                <i class="fa fa-pencil-alt"></i>
                                            </a>
                                            <a type="button" onclick="removeProduct('<?=$product['id'];?>')"
                                                class="btn btn-sm btn-danger" data-bs-toggle="tooltip"
                                                title="<?=__('Xóa');?>">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                        <td class="text-center">
                                            <div class="form-check form-switch form-check-lg">
                                                <input class="form-check-input" type="checkbox"
                                                    id="status<?=$product['id'];?>" value="1"
                                                    <?=$product['status'] == 1 ? 'checked=""' : '';?>>
                                            </div>
                                        </td>
                                        <td>
                                            <?=$product['name'];?>
                                        </td>
                                        <td class="text-center"><span
                                                class="badge bg-primary"><?=getRowRealtime('categories', $product['category_id'], 'name');?></span>
                                        </td>
                                        <td class="text-right"><span
                                                class="badge bg-danger"><?=format_currency($product['price']);?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-right">
                            <a type="button" target="_blank" href="<?=base_url_admin('product-add&code='.$code);?>"
                                class="btn btn-sm btn-primary btn-wave waves-light waves-effect waves-light"><i
                                    class="ri-add-line fw-semibold align-middle"></i> Tạo sản phẩm sử dụng kho hàng
                                <?=$code;?></a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="card custom-card">
                    <div class="card-header justify-content-between">
                        <div class="card-title">
                            NHẬP TÀI KHOẢN VÀO KHO HÀNG
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-xl-6 d-grid gap-2">
                                <button type="button" data-bs-toggle="modal" data-bs-target="#nhap_tung_tai_khoan"
                                    class="btn btn-outline-danger btn-w-lg btn-wave mb-2"><i
                                        class="fa-solid fa-file"></i> Nhập từng tài
                                    khoản</button>
                            </div>
                            <div class="col-xl-6 d-grid gap-2">
                                <button type="button" data-bs-toggle="modal" data-bs-target="#nhap_nhieu_tai_khoan"
                                    class="btn btn-outline-primary btn-w-lg btn-wave mb-2"><i
                                        class="fa-solid fa-folder"></i> Nhập nhiều tài
                                    khoản</button>
                            </div>
                            <div class="col-xl-6 d-grid gap-2">
                                <button type="button" data-bs-toggle="modal" data-bs-target="#nhap_tai_khoan_bang_txt"
                                    class="btn btn-outline-info btn-w-lg btn-wave mb-2"><i class='bx bxs-file-txt'
                                        style="font-size: 16px;"></i> Nhập bằng tệp
                                    .txt</button>
                            </div>
                            <!-- <div class="col-xl-6 d-grid gap-2">
                                <button type="button" class="btn btn-outline-success btn-w-lg btn-wave mb-2"><i
                                        class="fa-solid fa-file-csv"></i> Nhập bằng tệp
                                    .csv</button>
                            </div> -->
                            <div class="col-xl-6 d-grid gap-2">
                                <button type="button" data-bs-toggle="modal" data-bs-target="#nhap_bang_api"
                                    class="btn btn-outline-dark btn-w-lg btn-wave mb-2"><i class="fa-solid fa-code"></i>
                                    Nhập bằng API</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card custom-card">
                    <div class="card-header justify-content-between">
                        <div class="card-title">
                            XÓA TÀI KHOẢN KHỎI KHO HÀNG
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-xl-6 d-grid gap-2">
                                <button type="button" data-bs-toggle="modal" data-bs-target="#xoa_nhieu_tai_khoan"
                                    class="btn btn-danger btn-w-lg btn-wave mb-2"><i class="fa-solid fa-trash-can"></i>
                                    Xóa nhiều tài khoản</button> 
                            </div>
                            <div class="col-xl-6 d-grid gap-2">
                                <button type="button" data-bs-toggle="modal" data-bs-target="#xoa_toan_bo_tai_khoan"
                                    class="btn btn-danger-gradient btn-wave mb-2"><i class="fa-solid fa-trash"></i>
                                    Xóa toàn bộ tài khoản đang bán</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-header justify-content-between">
                        <div class="card-title">
                            TÀI KHOẢN <strong style="color:green;">LIVE</strong> ĐANG BÁN
                        </div>
                        <div class="btn-list">

                            <button type="button" onclick="viewListLIVE(`<?=$code;?>`)" id="btn_viewListLIVE"
                                class="btn btn-success btn-sm my-1 me-2"><i class="fa-solid fa-copy"></i> TÀI KHOẢN LIVE
                                <span
                                    class="badge ms-2 bg-dark text-white"><?=format_cash($totalDatatable);?></span></button>
                            <button type="button" onclick="viewListDIE(`<?=$code;?>`)" id="btn_viewListDIE"
                                class="btn btn-danger btn-sm my-1 me-2"><i class="fa-solid fa-copy"></i> TÀI KHOẢN DIE
                                <span
                                    class="badge ms-2 bg-dark text-white"><?=format_cash($CMSNT->get_row(" SELECT COUNT(id) FROM `product_die` WHERE `product_code` = '$code' ")['COUNT(id)']);?></span>

                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <form action="" class="align-items-center mb-3" name="formSearch" method="GET">
                            <div class="row row-cols-lg-auto g-3 mb-3">
                                <input type="hidden" name="module" value="admin">
                                <input type="hidden" name="action" value="product-stock">
                                <input type="hidden" name="code" value="<?=$code;?>">
                                <div class="col-lg col-md-4 col-6">
                                    <input class="form-control form-control-sm" value="<?=$uid;?>" name="uid"
                                        placeholder="UID">
                                </div>
                                <div class="col-lg col-md-4 col-6">
                                    <input class="form-control form-control-sm" value="<?=$account;?>" name="account"
                                        placeholder="Tài khoản">
                                </div>
                                <div class="col-lg col-md-4 col-6">
                                    <input class="form-control form-control-sm" value="<?=$user_id;?>" name="user_id"
                                        placeholder="ID Seller">
                                </div>
                                <div class="col-lg col-md-4 col-6">
                                    <input class="form-control form-control-sm" value="<?=$username;?>" name="username"
                                        placeholder="Username Seller">
                                </div>
                                <div class="col-lg col-md-4 col-6">
                                    <input type="text" name="create_gettime" class="form-control form-control-sm"
                                        id="daterange" value="<?=$create_gettime;?>" placeholder="Chọn thời gian">
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-hero btn-sm btn-primary"><i class="fa fa-search"></i>
                                        <?=__('Search');?>
                                    </button>
                                    <a class="btn btn-hero btn-sm btn-danger"
                                        href="<?=base_url_admin('product-stock&code='.$code);?>"><i
                                            class="fa fa-trash"></i>
                                        <?=__('Clear filter');?>
                                    </a>
                                </div>
                            </div>
                            <div class="top-filter">
                                <div class="filter-show">
                                    <label class="filter-label">Show :</label>
                                    <select name="limit" onchange="this.form.submit()"
                                        class="form-select filter-select">
                                        <option <?=$limit == 5 ? 'selected' : '';?> value="5">5</option>
                                        <option <?=$limit == 10 ? 'selected' : '';?> value="10">10</option>
                                        <option <?=$limit == 20 ? 'selected' : '';?> value="20">20</option>
                                        <option <?=$limit == 50 ? 'selected' : '';?> value="50">50</option>
                                        <option <?=$limit == 100 ? 'selected' : '';?> value="100">100</option>
                                        <option <?=$limit == 500 ? 'selected' : '';?> value="500">500</option>
                                        <option <?=$limit == 1000 ? 'selected' : '';?> value="1000">1.000</option>
                                        <option <?=$limit == 2000 ? 'selected' : '';?> value="2000">2.000</option>
                                        <option <?=$limit == 5000 ? 'selected' : '';?> value="5000">5.000</option>
                                        <option <?=$limit == 10000 ? 'selected' : '';?> value="10000">10.000</option>
                                        <option <?=$limit == 20000 ? 'selected' : '';?> value="20000">20.000</option>
                                        <option <?=$limit == 50000 ? 'selected' : '';?> value="50000">50.000</option>
                                        <option <?=$limit == 100000 ? 'selected' : '';?> value="100000">100.000</option>
                                    </select>
                                </div>
                                <div class="filter-short">
                                    <label class="filter-label"><?=__('Short by Date:');?></label>
                                    <select name="shortByDate" onchange="this.form.submit()"
                                        class="form-select filter-select">
                                        <option value=""><?=__('Tất cả');?></option>
                                        <option <?=$shortByDate == 1 ? 'selected' : '';?> value="1"><?=__('Hôm nay');?>
                                        </option>
                                        <option <?=$shortByDate == 2 ? 'selected' : '';?> value="2"><?=__('Tuần này');?>
                                        </option>
                                        <option <?=$shortByDate == 3 ? 'selected' : '';?> value="3">
                                            <?=__('Tháng này');?>
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </form>
                        <div class="table-responsive table-wrapper mb-3">
                            <table class="table text-nowrap table-striped table-hover table-bordered">
                                <thead>
                                    <tr>
                                        <th class="text-center">
                                            <div class="form-check form-check-md d-flex align-items-center">
                                                <input type="checkbox" class="form-check-input" name="check_all"
                                                    id="check_all_checkbox_product_stock" value="option1">
                                            </div>
                                        </th>
                                        <th class="text-center">UID</th>
                                        <th class="text-center">Tài khoản</th>
                                        <th class="text-center">Seller</th>
                                        <th class="text-center">Thời gian</th>
                                        <th class="text-center">Check live gần nhất</th>
                                        <th class="text-center">Type</th>
                                        <th class="text-center">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($listDatatable as $row): ?>
                                    <tr>
                                        <td class="text-center">
                                            <div class="form-check form-check-md d-flex align-items-center">
                                                <input type="checkbox" class="form-check-input checkbox_product_stock"
                                                    data-id="<?=$row['id'];?>" data-checkbox="<?=$row['account'];?>"
                                                    name="checkbox_product_stock" value="<?=$row['id'];?>" />
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <strong><?=$row['uid'];?></strong>
                                        </td>
                                        <td class="text-center">
                                            <textarea rows="1" readonly class="form-control"><?=$row['account'];?></textarea>
                                        </td>
                                        <td class="text-center"><a class="text-primary"
                                                href="<?=base_url_admin('user-edit&id='.$row['seller']);?>"><?=getRowRealtime("users", $row['seller'], "username");?>
                                                [ID <?=$row['seller'];?>]</a>
                                        </td>
                                        <td class="text-center">
                                            <small data-toggle="tooltip" data-placement="bottom"
                                                title="<?=timeAgo(strtotime($row['create_gettime']));?>"><?=$row['create_gettime'];?></small>
                                        </td>
                                        <td class="text-center"><span
                                                class="badge rounded-pill bg-dark text-white" data-toggle="tooltip" data-placement="bottom"
                                                title="<?=date("H:i:s d-m-Y", $row['time_check_live']);?>"><?=timeAgo($row['time_check_live']);?></span>
                                        </td> 
                                        <td class="text-center"><b><?=$row['type'];?></b></td>
                                        <td class="text-center">
                                            <a type="button" onclick="removeAccount('<?=$row['id'];?>')"
                                                class="btn btn-sm btn-danger" data-bs-toggle="tooltip"
                                                title="<?=__('Xóa');?>">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach?>
                                </tbody>
                                <tfoot>
                                    <td colspan="9">
                                        <div class="btn-list">
                                            <button type="button" onclick="exportDataTXT()" id="exportDataTXT"
                                                class="btn btn-outline-primary shadow-primary btn-wave btn-sm"><i
                                                    class="fa-solid fa-file-export"></i> XUẤT TỆP .TXT</button>
                                            <button type="button" onclick="exportDataClipboard()"
                                                id="exportDataClipboard"
                                                class="btn btn-outline-success shadow-success btn-wave btn-sm"><i
                                                    class="fa-solid fa-copy"></i> COPY</button>
                                            <button type="button" onclick="exportUIDClipboard()" id="exportUIDClipboard"
                                                class="btn btn-outline-info shadow-info btn-wave btn-sm"><i
                                                    class="fa-regular fa-copy"></i> COPY UID</button>
                                            <button type="button" onclick="confirmDeleteAccount()"
                                                id="confirmDeleteAccount"
                                                class="btn btn-outline-danger shadow-danger btn-wave btn-sm"><i
                                                    class="fa-solid fa-trash"></i> DELETE</button>
                                        </div>
                                    </td>
                                </tfoot>
                            </table>
                        </div>
                        <div class="row">
                            <div class="col-sm-12 col-md-5">
                                <p class="dataTables_info">Showing <?=format_cash($limit);?> of
                                    <?=format_cash($totalDatatable);?>
                                    Results</p>
                            </div>
                            <div class="col-sm-12 col-md-7 mb-3">
                                <?=$totalDatatable > $limit ? $urlDatatable : '';?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<?php
require_once(__DIR__.'/footer.php');
?>

<div class="modal fade" id="nhap_tung_tai_khoan" tabindex="-1" aria-labelledby="h6_nhap_tung_tai_khoan"
    data-bs-keyboard="false" aria-hidden="true">
    <!-- Scrollable modal -->
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form action="" method="POST">
                <div class="modal-header">
                    <h6 class="modal-title" id="h6_nhap_tung_tai_khoan">NHẬP TỪNG TÀI KHOẢN VÀO KHO HÀNG
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label for="text-area" class="form-label">Tài khoản cần thêm:</label>
                        <textarea class="form-control" name="accounts" placeholder="Định dạng UID|PASS|..." rows="2"
                            required></textarea>
                        <input type="hidden" name="type" value="an" readonly>
                    </div>
                    <div class="form-check form-check-md d-flex align-items-center">
                        <input class="form-check-input" type="checkbox" value="1" id="dsg9898w" name="loc_trung_uid">
                        <label class="form-check-label" for="dsg9898w">
                            Lọc trùng UID tài khoản đang bán
                        </label>
                    </div>
                    <div class="form-check form-check-md d-flex align-items-center">
                        <input class="form-check-input" type="checkbox" value="1" id="dsg9898w_sold" name="loc_trung_uid_sold">
                        <label class="form-check-label" for="dsg9898w_sold">
                            Lọc trùng UID tài khoản đã bán
                        </label>
                    </div>
                    <small>Tắt lọc trùng UID sẽ giúp tăng tốc độ tải sản phẩm lên.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="AddAccounts" class="btn btn-primary btn-sm"><i
                            class="fa fa-fw fa-plus me-1"></i> Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="nhap_nhieu_tai_khoan" tabindex="-1" aria-labelledby="h6_nhap_nhieu_tai_khoan"
    data-bs-keyboard="false" aria-hidden="true">
    <!-- Scrollable modal -->
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form action="" method="POST">
                <div class="modal-header">
                    <h6 class="modal-title" id="h6_nhap_nhieu_tai_khoan">NHẬP NHIỀU TÀI KHOẢN VÀO KHO HÀNG
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label for="text-area" class="form-label">Tài khoản cần thêm: (1 dòng 1 tài khoản)</label>
                        <textarea class="form-control" name="accounts" id="accounts" placeholder="UID|PASS|...
UID|PASS|...
UID|PASS|...
UID|PASS|..." rows="5" required></textarea>
                        <small>Nhấn Submit để thêm <strong style="color: red;" id="countAdd">0</strong> tài
                            khoản</small>
                        <input type="hidden" name="type" value="multi" readonly>
                    </div>
                    <div class="form-check form-check-md d-flex align-items-center">
                        <input class="form-check-input" type="checkbox" value="1" id="a9895w22" name="loc_trung_uid">
                        <label class="form-check-label" for="a9895w22">
                            Lọc trùng UID tài khoản đang bán
                        </label>
                    </div>
                    <div class="form-check form-check-md d-flex align-items-center">
                        <input class="form-check-input" type="checkbox" value="1" id="a9895w22_sold" name="loc_trung_uid_sold">
                        <label class="form-check-label" for="a9895w22_sold">
                            Lọc trùng UID tài khoản đã bán
                        </label>
                    </div>
                    <small>Tắt lọc trùng UID sẽ giúp tăng tốc độ tải sản phẩm lên.</small>

                    <script>
                    document.addEventListener("DOMContentLoaded", function() {
                        var textarea = document.getElementById('accounts');
                        var countAdd = document.getElementById("countAdd");

                        if (textarea && countAdd) {
                            textarea.addEventListener("input", function() {
                                var lines = textarea.value.split('\n');
                                var nonEmptyLinesCount = lines.filter(function(line) {
                                    return line.trim().length >
                                        0; // Lọc ra những dòng không rỗng
                                }).length;
                                countAdd.innerText =
                                    nonEmptyLinesCount; // Cập nhật số dòng không rỗng vào countAdd
                            });
                        }
                    });
                    </script>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="AddAccounts" class="btn btn-primary btn-sm"><i
                            class="fa fa-fw fa-plus me-1"></i> Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="nhap_tai_khoan_bang_txt" tabindex="-1" aria-labelledby="h6_nhap_tai_khoan_bang_txt"
    data-bs-keyboard="false" aria-hidden="true">
    <!-- Scrollable modal -->
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h6 class="modal-title" id="h6_nhap_tai_khoan_bang_txt">NHẬP TÀI KHOẢN BẰNG TỆP .TXT
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-2">
                        <label for="formFile" class="form-label">Tải lên tệp tài khoản .txt</label>
                        <input class="form-control" type="file" name="files_txt" required>
                        <input type="hidden" name="type" value="txt" readonly>
                    </div>
                    <div class="form-check form-check-md d-flex align-items-center mb-2">
                        <input class="form-check-input" type="checkbox" value="1" id="txt_loc_trung_uid" name="loc_trung_uid">
                        <label class="form-check-label" for="txt_loc_trung_uid">
                            Lọc trùng UID tài khoản đang bán
                        </label>
                    </div>
                    <div class="form-check form-check-md d-flex align-items-center mb-2">
                        <input class="form-check-input" type="checkbox" value="1" id="txt_loc_trung_uid_sold" name="loc_trung_uid_sold">
                        <label class="form-check-label" for="txt_loc_trung_uid_sold">
                            Lọc trùng UID tài khoản đã bán
                        </label>
                    </div>
                    <ul>
                        <li>Chỉ nhập tệp định dạng .TXT</li>
                        <li>1 dòng 1 tài khoản</li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="AddAccounts" class="btn btn-primary btn-sm"><i
                            class="fa fa-fw fa-plus me-1"></i> Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="xoa_toan_bo_tai_khoan" tabindex="-1" aria-labelledby="h6_xoa_toan_bo_tai_khoan"
    data-bs-keyboard="false" aria-hidden="true">
    <!-- Scrollable modal -->
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="" method="POST">
                <div class="modal-header">
                    <h6 class="modal-title" id="h6_xoa_toan_bo_tai_khoan"><i class="fa-solid fa-triangle-exclamation"></i> XÓA TOÀN BỘ TÀI KHOẢN ĐANG BÁN
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Hệ thống sẽ thực hiện XÓA TOÀN BỘ tài khoản đang bán của kho hàng <b><?=$code;?></b> nếu bạn xác nhận vào Input dưới đây.</p>
                    <p>Để xác nhận XÓA TOÀN BỘ tài khoản trong kho hàng <b><?=$code;?></b>, vui lòng nhập vào ô dưới đây nội dung là <b style="color:red;font-size:15px;">toi dong y</b> để tiến hành xóa.</p>
                    <input class="form-control" type="text" id="confirm_empty_list_account" placeholder="Nhập nội dung toi dong y nếu bạn chắc chắn đã hiểu nội dung trên"> 
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Close</button>
                    <button type="button" id="btn_format_list_account" class="btn btn-primary btn-sm"><i
                            class="fa fa-fw fa-trash me-1"></i> Xóa ngay</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
$("#btn_format_list_account").click(function() {
    Swal.fire({
        title: "Bạn có chắc không?",
        text: "Hệ thống sẽ xóa vĩnh viễn toàn bộ dữ liệu tài khoản đang bán của kho hàng <?=$code;?> khi bạn nhấn Đồng Ý",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Đồng ý",
        cancelButtonText: "Đóng"
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: "<?=base_url('ajaxs/admin/remove.php');?>",
                method: "POST",
                dataType: "JSON",
                data: {
                    action: 'empty_list_account_stock',
                    token: '<?=$getUser['token'];?>',
                    confirm_empty_list_account: $('#confirm_empty_list_account').val(),
                    id: '<?=$code;?>'
                },
                success: function(result) {
                    if (result.status == 'success') {
                        showMessage(result.msg, 'success');
                        setTimeout("location.href = '';", 1000);
                    } else {
                        showMessage(result.msg, 'error');
                    }
                },
                error: function() {
                    alert(html(result));
                    location.reload();
                }
            });
        }
    });
});
</script>
<div class="modal fade" id="xoa_nhieu_tai_khoan" tabindex="-1" aria-labelledby="h6_xoa_nhieu_tai_khoan"
    data-bs-keyboard="false" aria-hidden="true">
    <!-- Scrollable modal -->
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="" method="POST">
                <div class="modal-header">
                    <h6 class="modal-title" id="h6_xoa_nhieu_tai_khoan">XÓA NHIỀU TÀI KHOẢN <strong style="color:green;">LIVE</strong> ĐANG BÁN
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="text-area" class="form-label">Tài khoản cần xóa: (1 dòng 1 tài khoản)</label>
                        <textarea class="form-control" name="list_account_remove" id="accounts_remove" placeholder="UID|... HOẶC MỖI UID
UID|... HOẶC MỖI UID
UID|... HOẶC MỖI UID
UID|... HOẶC MỖI UID" rows="5" required></textarea>
<small>Nhấn Submit để xóa <strong style="color: red;" id="countRemove">0</strong> tài
                            khoản</small>
                        <input type="hidden" name="type" value="multi" readonly>
                        <script>
                    document.addEventListener("DOMContentLoaded", function() {
                        var textarea = document.getElementById('accounts_remove');
                        var countAdd = document.getElementById("countRemove");

                        if (textarea && countAdd) {
                            textarea.addEventListener("input", function() {
                                var lines = textarea.value.split('\n');
                                var nonEmptyLinesCount = lines.filter(function(line) {
                                    return line.trim().length >
                                        0; // Lọc ra những dòng không rỗng
                                }).length;
                                countAdd.innerText =
                                    nonEmptyLinesCount; // Cập nhật số dòng không rỗng vào countAdd
                            });
                        }
                    });
                    </script>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="RemoveAccounts" class="btn btn-primary btn-sm"><i
                            class="fa fa-fw fa-trash me-1"></i> Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="nhap_bang_api" tabindex="-1" aria-labelledby="h6_nhap_bang_api" data-bs-keyboard="false"
    aria-hidden="true">
    <!-- Scrollable modal -->
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="h6_nhap_bang_api"><i class="fa-solid fa-code"></i> NHẬP TÀI KHOẢN BẰNG API
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="text-area" class="form-label">API nhập tài khoản vào kho hàng này</label>
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" id="url_api"
                            value="<?=base_url('api/importAccount.php?code='.$code.'&api_key='.$getUser['api_key'].'&account=&filter=1');?>">
                        <button class="btn btn-info copy" data-clipboard-target="#url_api" type="button"
                            onclick="copy()">Copy</button>
                    </div>
                </div>
                <p>API Key của bạn là: <strong class="copy" style="color: blue;" id="api_key"
                        data-clipboard-target="#api_key" onclick="copy()" data-toggle="tooltip" data-placement="bottom"
                        title="Nhấn vào để Copy"><?=$getUser['api_key'];?></strong> <button
                        onclick="changeAPIKey(`<?=$getUser['token'];?>`)" data-toggle="tooltip" data-placement="bottom"
                        title="Thay đổi API KEY khác nếu API KEY cũ của bạn bị lộ ra ngoài"
                        class="btn btn-danger btn-sm"><i class="fa-solid fa-rotate"></i></button></p>
                <p>Trong đó:</p>
                <ul>
                    <li><strong>code</strong>: mã của kho hàng, ví dụ kho hàng hiện tại của bạn là <strong
                            style="color:red;"><?=$code;?></strong></li>
                    <li><strong>api_key</strong>: API Key của tài khoản admin có role <strong>Quản lý kho hàng sản phẩm</strong>
                        <span class="badge bg-primary-transparent">edit_stock_product</span>
                    </li>
                    <li><strong>filter</strong>: 1 để bật lọc trùng UID, 0 để tắt lọc trùng UID.</span>
                    </li>
                    <li><strong>account</strong>: tài khoản cần thêm vào kho hàng, có thể thêm nhiều tài khoản bằng cách nhập mỗi tài khoản trên một dòng (xuống dòng để phân tách).</li>
                </ul>


            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<script>
function updateFormProduct(id) {
    $.ajax({
        url: "<?=BASE_URL("ajaxs/admin/update.php");?>",
        method: "POST",
        dataType: "JSON",
        data: {
            action: 'update_status_product',
            id: id,
            status: $('#status' + id + ':checked').val()
        },
        success: function(result) {
            if (result.status == 'success') {
                showMessage(result.msg, result.status);
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

function removeProduct(id) {
    cuteAlert({
        type: "question",
        title: "Xác Nhận Xóa sản phẩm",
        message: "Bạn có chắc chắn muốn xóa sản phẩm ID " + id + " không ?",
        confirmText: "Đồng Ý",
        cancelText: "Hủy"
    }).then((e) => {
        if (e) {
            $.ajax({
                url: "<?=BASE_URL("ajaxs/admin/remove.php");?>",
                method: "POST",
                dataType: "JSON",
                data: {
                    id: id,
                    action: 'removeProduct'
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
function postRemoveAccount(id) {
    $.ajax({
        url: "<?=BASE_URL('ajaxs/admin/remove.php');?>",
        type: 'POST',
        dataType: "JSON",
        data: {
            action: 'removeAccountStock',
            id: id
        },
        success: function(result) {
            if (result.status == 'success') {
                showMessage(result.msg, 'success');
            } else {
                showMessage(result.msg, 'error');
            }
        }
    });
}

function removeAccount(id) {
    cuteAlert({
        type: "question",
        title: "Xác nhận xóa tài khoản",
        message: "Bạn có chắc chắn muốn xóa tài khoản này không ?",
        confirmText: "Đồng ý",
        cancelText: "Không"
    }).then((e) => {
        if (e) {
            postRemoveAccount(id);
            setTimeout(function() {
                location.reload();
            }, 1000);
        }
    })
}
</script>
<script>
function confirmDeleteAccount() {
    var checkbox = document.getElementsByName('checkbox_product_stock');
    var isAnyCheckboxChecked = false;
    for (var i = 0; i < checkbox.length; i++) {
        if (checkbox[i].checked === true) {
            isAnyCheckboxChecked = true;
            break;
        }
    }
    if (!isAnyCheckboxChecked) {
        showMessage('Vui lòng chọn ít nhất một bản ghi', 'error');
        return;
    }
    var result = confirm('Bạn có đồng ý xóa các bản ghi đã chọn không?');
    if (result) {
        $('#confirmDeleteAccount').html('<span><i class="fa fa-spinner fa-spin"></i> <?=__('Processing...');?></span>')
            .prop('disabled',
                true);

        function postUpdatesSequentially(index) {
            if (index < checkbox.length) {
                if (checkbox[index].checked === true) {
                    postRemoveAccount(checkbox[index].value);
                }
                setTimeout(function() {
                    postUpdatesSequentially(index + 1);
                }, 100);
            } else {
                setTimeout(function() {
                    location.reload();
                }, 1000);
            }
        }
        postUpdatesSequentially(0);
    }
}

$(function() {
    $('#check_all_checkbox_product_stock').on('click', function() {
        $('.checkbox_product_stock').prop('checked', this.checked);
    });
    $('.checkbox_product_stock').on('click', function() {
        $('#check_all_checkbox_product_stock').prop('checked', $('.checkbox_product_stock:checked')
            .length === $('.checkbox_product_stock').length);
    });
});
</script>

<script>
function exportDataTXT() {
    // Lấy tất cả các phần tử input có type là checkbox và được chọn
    var checkboxes = document.querySelectorAll('input[name="checkbox_product_stock"]:checked');

    // Kiểm tra nếu không có checkbox nào được chọn
    if (checkboxes.length === 0) {
        showMessage('Vui lòng chọn ít nhất một bản ghi', 'error');
        return;
    }
    $('#exportDataTXT').html('<span><i class="fa fa-spinner fa-spin"></i> <?=__('Processing...');?></span>')
        .prop('disabled',
            true);
    // Tạo một mảng để lưu trữ giá trị của các checkbox được chọn
    var selectedData = [];

    // Duyệt qua mỗi checkbox được chọn và thêm giá trị vào mảng
    checkboxes.forEach(function(checkbox) {
        var accountValue = checkbox.getAttribute('data-checkbox');
        if (accountValue) {
            // Trim giá trị để loại bỏ khoảng trắng thừa
            accountValue = accountValue.trim();
            // Nếu tài khoản có nhiều dòng, tách ra thành các dòng riêng
            var lines = accountValue.split(/\r?\n/);
            lines.forEach(function(line) {
                line = line.trim();
                // Chỉ thêm dòng không rỗng
                if (line.length > 0) {
                    selectedData.push(line);
                }
            });
        }
    });

    // Lấy số lượng dữ liệu được xuất
    var numberOfData = selectedData.length;

    // Chuyển đổi mảng thành chuỗi với mỗi giá trị trên một dòng
    var dataString = selectedData.join('\n');

    // Tạo timestamp cho tên file
    var now = new Date();
    var year = now.getFullYear();
    var month = String(now.getMonth() + 1).padStart(2, '0');
    var day = String(now.getDate()).padStart(2, '0');
    var hours = String(now.getHours()).padStart(2, '0');
    var minutes = String(now.getMinutes()).padStart(2, '0');
    var seconds = String(now.getSeconds()).padStart(2, '0');
    var timestamp = year + '-' + month + '-' + day + '_' + hours + '-' + minutes + '-' + seconds;

    // Tạo một đối tượng Blob chứa dữ liệu
    var blob = new Blob([dataString], {
        type: 'text/plain'
    });

    // Tạo một đường link để tải xuống tệp tin TXT
    var link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = '<?=$code;?>_' + numberOfData + '_' + timestamp + '.txt';

    // Thêm đường link vào trang và kích hoạt sự kiện click để tải xuống
    document.body.appendChild(link);
    link.click();

    // Xóa đường link sau khi đã tải xuống
    document.body.removeChild(link);
    $('#exportDataTXT').html(
        '<i class="fa-solid fa-file-export"></i> XUẤT TỆP .TXT'
    ).prop('disabled',
        false);


}
</script>

<script>
function exportDataClipboard() {
    // Lấy tất cả các phần tử input có type là checkbox và được chọn
    var checkboxes = document.querySelectorAll('input[name="checkbox_product_stock"]:checked');

    // Kiểm tra nếu không có checkbox nào được chọn
    if (checkboxes.length === 0) {
        showMessage('Vui lòng chọn ít nhất một bản ghi', 'error');
        return;
    }
    $('#exportDataClipboard').html('<span><i class="fa fa-spinner fa-spin"></i> <?=__('Processing...');?></span>')
        .prop('disabled',
            true);
    // Tạo một mảng để lưu trữ giá trị của các checkbox được chọn
    var selectedData = [];

    // Duyệt qua mỗi checkbox được chọn và thêm giá trị vào mảng
    checkboxes.forEach(function(checkbox) {
        // Đảm bảo rằng có một dòng mới sau mỗi giá trị
        selectedData.push(checkbox.getAttribute('data-checkbox').trim());
    });

    // Chuyển đổi mảng thành chuỗi, với mỗi giá trị trên một dòng
    var dataString = selectedData.join('\n');

    // Sao chép chuỗi vào clipboard
    navigator.clipboard.writeText(dataString).then(function() {
        showMessage("Nội dung đã được sao chép vào clipboard!", 'success');
        $('#exportDataClipboard').html(
            '<i class="fa-solid fa-copy"></i> COPY'
        ).prop('disabled',
            false);
    }).catch(function(error) {
        $('#exportDataClipboard').html(
            '<i class="fa-solid fa-copy"></i> COPY'
        ).prop('disabled',
            false);
        alert('Có lỗi xảy ra trong quá trình sao chép: ' + error);
    });
}
</script>

<script>
function exportUIDClipboard() {
    // Lấy tất cả các phần tử input có type là checkbox và được chọn
    var checkboxes = document.querySelectorAll('input[name="checkbox_product_stock"]:checked');

    // Kiểm tra nếu không có checkbox nào được chọn
    if (checkboxes.length === 0) {
        showMessage('Vui lòng chọn ít nhất một bản ghi', 'error');
        return;
    }
    $('#exportUIDClipboard').html('<span><i class="fa fa-spinner fa-spin"></i> <?=__('Processing...');?></span>')
        .prop('disabled',
            true);
    // Tạo một mảng để lưu trữ giá trị của các checkbox được chọn
    var selectedData = [];

    // Duyệt qua mỗi checkbox được chọn và thêm giá trị vào mảng
    checkboxes.forEach(function(checkbox) {
        // Lấy dữ liệu và chia nó dựa trên dấu '|'
        var fullData = checkbox.getAttribute('data-checkbox').trim();
        var splitData = fullData.split('|');
        // Kiểm tra để chắc chắn rằng dữ liệu tồn tại trước khi thêm vào mảng
        if (splitData.length > 0) {
            selectedData.push(splitData[0]); // Chỉ lấy phần trước dấu '|'
        }
    });

    // Chuyển đổi mảng thành chuỗi, với mỗi giá trị trên một dòng
    var dataString = selectedData.join('\n');

    // Sao chép chuỗi vào clipboard
    navigator.clipboard.writeText(dataString).then(function() {
        showMessage("Nội dung đã được sao chép vào clipboard!", 'success');
        $('#exportUIDClipboard').html(
            '<i class="fa-regular fa-copy"></i> COPY UID'
        ).prop('disabled',
            false);
    }).catch(function(error) {
        alert('Có lỗi xảy ra trong quá trình sao chép: ' + error);
    });
}
</script>

<script>
function changeAPIKey(token) {
    cuteAlert({
        type: "question",
        title: "Bạn có chắc không?",
        message: "Hệ thống sẽ thay đổi API KEY nếu bạn nhấn Đồng Ý",
        confirmText: "Đồng ý",
        cancelText: "Không"
    }).then((e) => {
        if (e) {
            $.ajax({
                url: "<?=BASE_URL("ajaxs/client/auth.php");?>",
                method: "POST",
                dataType: "JSON",
                data: {
                    token: '<?=$getUser['token'];?>',
                    action: 'changeAPIKey'
                },
                success: function(result) {
                    if (result.status == 'success') {
                        showMessage(result.msg, 'success');
                        document.getElementById("url_api").value =
                            '<?=base_url();?>api/importAccount.php?code=<?=$code;?>&api_key=' +
                            result.api_key + '&account=';
                        document.getElementById("api_key").innerHTML = result.api_key;
                    } else {
                        Swal.fire({
                            title: "<?=__('Thất bại!');?>",
                            text: result.msg,
                            icon: "error"
                        });
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
<script type="text/javascript">
new ClipboardJS(".copy");

function copy() {
    showMessage("<?=__('Đã sao chép vào bộ nhớ tạm');?>", 'success');
}
</script>

<div class="modal fade" id="viewListDIE" tabindex="-1" aria-labelledby="viewListDIE" data-bs-keyboard="false"
    aria-hidden="true">
    <!-- Scrollable modal -->
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="viewListDIE">DANH SÁCH TÀI KHOẢN <strong style="color:red;">DIE</strong> CỦA
                    KHO HÀNG <strong style="color:red;"><?=$code;?></strong>
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <textarea class="form-control mb-2" id="coypyBox_viewListDIE" readonly rows="10"></textarea>
                <button type="button" id="btn_format_list_die" class="btn btn-danger btn-sm"><i class="fa-solid fa-trash"></i> Xóa toàn bộ</button>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="copy()" data-clipboard-target="#coypyBox_viewListDIE"
                    class="btn btn-info shadow-info btn-wave copy">Copy</button>
                <button type="button" class="btn btn-light shadow-light btn-wave" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>
<script type="text/javascript">
function viewListDIE(code) {
    var originalButtonContent = $('#btn_viewListDIE').html();
    $('#btn_viewListDIE').html('<span><i class="fa fa-spinner fa-spin"></i> <?=__('Processing...');?></span>')
        .prop('disabled',
            true);
    $.ajax({
        url: "<?=base_url('ajaxs/admin/view.php');?>",
        method: "POST",
        dataType: "JSON",
        data: {
            action: 'view_product_die',
            token: '<?=$getUser['token'];?>',
            code: code
        },
        success: function(result) {
            $('#viewListDIE').modal('show');
            $('#coypyBox_viewListDIE').val(result.accounts);
            $('#btn_viewListDIE').html(originalButtonContent).prop('disabled', false);
        },
        error: function() {
            alert(html(result));
            location.reload();
        }
    });
}
</script>

<div class="modal fade" id="viewListLIVE" tabindex="-1" aria-labelledby="viewListLIVE" data-bs-keyboard="false"
    aria-hidden="true">
    <!-- Scrollable modal -->
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="viewListLIVE">DANH SÁCH TÀI KHOẢN <strong style="color:green;">LIVE</strong>
                    CỦA KHO HÀNG <strong style="color:red;"><?=$code;?></strong>
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <textarea class="form-control" id="coypyBox_viewListLIVE" readonly rows="10"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="copy()" data-clipboard-target="#coypyBox_viewListLIVE"
                    class="btn btn-info shadow-info btn-wave copy">Copy</button>
                <button type="button" class="btn btn-light shadow-light btn-wave" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>
<script type="text/javascript">
function viewListLIVE(code) {
    var originalButtonContent = $('#btn_viewListLIVE').html();
    $('#btn_viewListLIVE').html('<span><i class="fa fa-spinner fa-spin"></i> <?=__('Processing...');?></span>')
        .prop('disabled',
            true);
    $.ajax({
        url: "<?=base_url('ajaxs/admin/view.php');?>",
        method: "POST",
        dataType: "JSON",
        data: {
            action: 'view_product_live',
            token: '<?=$getUser['token'];?>',
            code: code
        },
        success: function(result) {
            $('#viewListLIVE').modal('show');
            $('#coypyBox_viewListLIVE').val(result.accounts);
            $('#btn_viewListLIVE').html(originalButtonContent).prop('disabled', false);
        },
        error: function() {
            alert(html(result));
            location.reload();
        }
    });
}
</script>

<script>
$("#btn_format_list_die").click(function() {
    Swal.fire({
        title: "Bạn có chắc không?",
        text: "Hệ thống sẽ xóa vĩnh viễn toàn bộ dữ liệu tài khoản DIE của kho hàng <?=$code;?> khi bạn nhấn Đồng Ý",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Đồng ý",
        cancelButtonText: "Đóng"
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: "<?=base_url('ajaxs/admin/remove.php');?>",
                method: "POST",
                dataType: "JSON",
                data: {
                    action: 'empty_list_die',
                    token: '<?=$getUser['token'];?>',
                    id: '<?=$code;?>'
                },
                success: function(result) {
                    if (result.status == 'success') {
                        showMessage(result.msg, 'success');
                        setTimeout("location.href = '';", 1000);
                    } else {
                        showMessage(result.msg, 'error');
                    }
                },
                error: function() {
                    alert(html(result));
                    location.reload();
                }
            });
        }
    });
});
</script>