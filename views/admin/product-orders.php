<?php if (!defined('IN_SITE')) {
    die('The Request Not Found');
}
$body = [
    'title' => __('Đơn hàng') . ' | ' . $CMSNT->site('title'),
    'desc'   => $CMSNT->site('description'),
    'keyword' => $CMSNT->site('keywords')
];
$body['header'] = '
<script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.6/clipboard.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<style>
    /* CSS cho hàng được chọn */
    .table tr.selected {
        background-color: rgba(0, 94, 234, 0.08) !important;
        position: relative;
        outline: 1px solid #0d6efd !important;
        outline-offset: -1px;
    }

    .table tr.selected td {
        border-color: rgba(13, 110, 253, 0.2) !important;
        color: rgba(0, 0, 0, 0.7);
    }

    .table tr.selected:hover {
        background-color: rgba(13, 110, 253, 0.12) !important;
    }

    .table tr.selected td:first-child {
        border-left: 2px solid #0d6efd !important;
    }

    [data-theme-mode="dark"] .table tr.selected {
        background-color: rgba(13, 110, 253, 0.15) !important;
        outline: 1px solid #3384ff !important;
        color: rgba(255, 255, 255, 0.7);
    }

    [data-theme-mode="dark"] .table tr.selected td {
        border-color: rgba(13, 110, 253, 0.3) !important;
        color: rgba(255, 255, 255, 0.7);
    }
    
    /* Loading overlay */
    .loading-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 9999;
        justify-content: center;
        align-items: center;
    }
    
    .loading-overlay.active {
        display: flex;
    }
    
    .loading-spinner {
        width: 50px;
        height: 50px;
        border: 5px solid #f3f3f3;
        border-top: 5px solid #3498db;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    /* Hỗ trợ cho các nút bulk action */
    #bulk-action-buttons {
        transition: all 0.3s ease;
    }
    
    #selected-counter {
        font-size: 13px;
        padding: 3px 8px;
        background-color: rgba(13, 110, 253, 0.1);
        border-radius: 4px;
    }
    
    /* CSS cho dropdown menu có submenu */
    .dropdown-menu {
        animation: fadeInDown 0.3s ease-in-out;
        border: none !important;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15) !important;
        min-width: 200px;
    }
    
    .dropdown-item {
        padding: 8px 16px;
        transition: all 0.2s ease;
        border-radius: 4px;
        margin: 2px 4px;
        position: relative;
        display: flex;
        align-items: center;
        justify-content: flex-start;
        text-align: left;
    }
    
    .dropdown-item span {
        flex: 1;
        text-align: left;
        margin-left: 4px;
    }
    
    .dropdown-item:hover {
        background-color: rgba(13, 110, 253, 0.1) !important;
        transform: translateX(2px);
    }
    
    .dropdown-item.has-submenu .fa-chevron-right {
        font-size: 10px;
        color: #666;
        transition: transform 0.2s ease;
        margin-left: auto;
        margin-right: 0;
    }
    
    .dropdown-submenu:hover .fa-chevron-right {
        transform: rotate(90deg);
    }
    
    /* Submenu styles */
    .dropdown-submenu {
        position: relative;
    }
    
    .dropdown-submenu .dropdown-menu {
        position: absolute !important;
        top: 0;
        left: 100%;
        margin-top: 0;
        margin-left: 2px;
        border-radius: 6px;
        display: none;
        animation: fadeInRight 0.2s ease-in-out;
    }
    
    .dropdown-submenu:hover > .dropdown-menu {
        display: block;
    }
    
    .dropdown-submenu .dropdown-item {
        padding: 6px 12px;
        font-size: 13px;
        text-align: left;
        justify-content: flex-start;
    }
    
    .dropdown-submenu .dropdown-item i {
        width: 16px;
        text-align: center;
        margin-right: 8px;
    }
    
    /* Icon spacing cho tất cả dropdown items */
    .dropdown-item i {
        width: 18px;
        text-align: center;
        margin-right: 8px;
    }
    
    @keyframes fadeInDown {
        0% {
            opacity: 0;
            transform: translateY(-10px);
        }
        100% {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes fadeInRight {
        0% {
            opacity: 0;
            transform: translateX(-10px);
        }
        100% {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    /* Dark mode support */
    [data-theme-mode="dark"] .dropdown-menu {
        background-color: #1a1d29 !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
        color: #fff !important;
    }
    
    [data-theme-mode="dark"] .dropdown-item {
        color: #fff !important;
    }
    
    [data-theme-mode="dark"] .dropdown-item:hover {
        background-color: rgba(255, 255, 255, 0.1) !important;
        color: #fff !important;
    }
    
    [data-theme-mode="dark"] .dropdown-item:focus {
        background-color: rgba(255, 255, 255, 0.1) !important;
        color: #fff !important;
    }
    
    [data-theme-mode="dark"] .dropdown-item.has-submenu .fa-chevron-right {
        color: #ccc !important;
    }
    
    [data-theme-mode="dark"] .dropdown-submenu .dropdown-menu {
        background-color: #1a1d29 !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
    }
    
    [data-theme-mode="dark"] .dropdown-submenu .dropdown-item {
        color: #fff !important;
    }
    
    [data-theme-mode="dark"] .dropdown-submenu .dropdown-item:hover {
        background-color: rgba(255, 255, 255, 0.1) !important;
        color: #fff !important;
    }
    
    [data-theme-mode="dark"] .dropdown-divider {
        border-color: rgba(255, 255, 255, 0.1) !important;
    }
    
    [data-theme-mode="dark"] .dropdown-item i {
        color: inherit !important;
    }
    
    [data-theme-mode="dark"] .dropdown-item.text-danger {
        color: #ff6b6b !important;
    }
    
    [data-theme-mode="dark"] .dropdown-item.text-danger:hover {
        color: #ff5252 !important;
        background-color: rgba(255, 107, 107, 0.1) !important;
    }
</style>
';
$body['footer'] = '
<!-- Select2 Cdn -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<!-- Internal Select-2.js -->
<script src="' . base_url('public/theme/') . 'assets/js/select2.js"></script>
';
require_once(__DIR__ . '/../../models/is_admin.php');
require_once(__DIR__ . '/header.php');
require_once(__DIR__ . '/sidebar.php');
if (checkPermission($getUser['admin'], 'view_orders_product') != true) {
    die('<script type="text/javascript">if(!alert("Bạn không có quyền sử dụng tính năng này")){window.history.back();}</script>');
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
$where = " `id` > 0 ";
$buyer = '';
$username = '';
$seller = '';
$seller_username = '';
$create_gettime = '';
$trans_id = '';
$shortByDate  = '';
$supplier_id = '';
$api_transid = '';
$product_id = '';
$uid = '';
$account = '';

if (!empty($_GET['account'])) {
    $account = check_string($_GET['account']);
    $product_sold_rows = $CMSNT->get_list('SELECT * FROM `product_sold` WHERE `account` LIKE "%' . $account . '%" ');
    if (!empty($product_sold_rows)) {
        $trans_ids = array_map(function ($row) {
            return $row['trans_id'];
        }, $product_sold_rows);
        $trans_ids_str = implode('","', $trans_ids);
        $where .= ' AND `trans_id` IN ("' . $trans_ids_str . '") ';
    }
}
if (!empty($_GET['uid'])) {
    $uid = check_string($_GET['uid']);
    $product_sold_rows = $CMSNT->get_list('SELECT * FROM `product_sold` WHERE `uid` = "' . $uid . '" ');
    if (!empty($product_sold_rows)) {
        $trans_ids = array_map(function ($row) {
            return $row['trans_id'];
        }, $product_sold_rows);
        $trans_ids_str = implode('","', $trans_ids);
        $where .= ' AND `trans_id` IN ("' . $trans_ids_str . '") ';
    }
}
if (!empty($_GET['product_id'])) {
    $product_id = check_string($_GET['product_id']);
    $where .= ' AND `product_id` = "' . $product_id . '" ';
}
if (!empty($_GET['api_transid'])) {
    $api_transid = check_string($_GET['api_transid']);
    // Kiểm tra xem có phải nhiều mã đơn hàng không (phân tách bằng dấu phẩy)
    if (strpos($api_transid, ',') !== false) {
        // Tách các mã đơn hàng bằng dấu phẩy và loại bỏ khoảng trắng
        $api_transid = array_map('trim', explode(',', $api_transid));
        $api_transid = array_filter($api_transid); // Loại bỏ phần tử rỗng
        if (!empty($api_transid)) {
            $api_transid_str = implode('","', $api_transid);
            $where .= ' AND `api_transid` IN ("' . $api_transid_str . '") ';
        }
    } else {
        // Tìm kiếm một mã đơn hàng như trước
        $where .= ' AND `api_transid` LIKE "%' . $api_transid . '%" ';
    }
}
if (!empty($_GET['supplier_id'])) {
    $supplier_id = check_string($_GET['supplier_id']);
    $where .= ' AND `supplier_id` = "' . $supplier_id . '" ';
}
if (!empty($_GET['username'])) {
    $username = check_string($_GET['username']);
    if ($idUser = $CMSNT->get_row(" SELECT * FROM `users` WHERE `username` = '$username' ")) {
        $where .= ' AND `buyer` =  "' . $idUser['id'] . '" ';
    } else {
        $where .= ' AND `buyer` =  "" ';
    }
}
if (!empty($_GET['buyer'])) {
    $buyer = check_string($_GET['buyer']);
    $where .= ' AND `buyer` = "' . $buyer . '" ';
}
// Xử lý tìm kiếm theo người bán (seller)
if (!empty($_GET['seller'])) {
    $seller = check_string($_GET['seller']);
    $where .= ' AND `seller` = "' . $seller . '" ';
}
// Xử lý tìm kiếm theo tên người bán
if (!empty($_GET['seller_username'])) {
    $seller_username = check_string($_GET['seller_username']);
    if ($idSeller = $CMSNT->get_row(" SELECT * FROM `users` WHERE `username` = '$seller_username' ")) {
        $where .= ' AND `seller` =  "' . $idSeller['id'] . '" ';
    } else {
        $where .= ' AND `seller` =  "" ';
    }
}
if (!empty($_GET['trans_id'])) {
    $trans_id = check_string($_GET['trans_id']);
    // Kiểm tra xem có phải nhiều mã đơn hàng không (phân tách bằng dấu phẩy)
    if (strpos($trans_id, ',') !== false) {
        // Tách các mã đơn hàng bằng dấu phẩy và loại bỏ khoảng trắng
        $trans_ids = array_map('trim', explode(',', $trans_id));
        $trans_ids = array_filter($trans_ids); // Loại bỏ phần tử rỗng
        if (!empty($trans_ids)) {
            $trans_ids_str = implode('","', $trans_ids);
            $where .= ' AND `trans_id` IN ("' . $trans_ids_str . '") ';
        }
    } else {
        // Tìm kiếm một mã đơn hàng như trước
        $where .= ' AND `trans_id` LIKE "%' . $trans_id . '%" ';
    }
}
if (!empty($_GET['create_gettime'])) {
    $create_gettime = check_string($_GET['create_gettime']);
    $createdate = $create_gettime;
    $create_gettime_1 = str_replace('-', '/', $create_gettime);
    $create_gettime_1 = explode(' to ', $create_gettime_1);

    if ($create_gettime_1[0] != $create_gettime_1[1]) {
        $create_gettime_1 = [$create_gettime_1[0] . ' 00:00:00', $create_gettime_1[1] . ' 23:59:59'];
        $where .= " AND `create_gettime` >= '" . $create_gettime_1[0] . "' AND `create_gettime` <= '" . $create_gettime_1[1] . "' ";
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
        $where .= " AND `create_gettime` LIKE '%" . $currentDate . "%' ";
    }
    if ($shortByDate == 2) {
        $where .= " AND YEAR(create_gettime) = $currentYear AND WEEK(create_gettime, 1) = $currentWeek ";
    }
    if ($shortByDate == 3) {
        $where .= " AND MONTH(create_gettime) = '$currentMonth' AND YEAR(create_gettime) = '$currentYear' ";
    }
    if ($shortByDate == 4) {
        $where .= " AND DATE(create_gettime) = '$yesterday' ";
    }
}

$listDatatable = $CMSNT->get_list(" SELECT * FROM `product_order` WHERE $where ORDER BY `id` DESC LIMIT $from,$limit ");
$totalDatatable = $CMSNT->num_rows(" SELECT * FROM `product_order` WHERE $where ORDER BY id DESC ");

$urlDatatable = pagination(base_url_admin("product-orders&limit=$limit&shortByDate=$shortByDate&buyer=$buyer&trans_id=$trans_id&create_gettime=$create_gettime&username=$username&supplier_id=$supplier_id&api_transid=$api_transid&product_id=$product_id&uid=$uid&account=$account&seller=$seller&seller_username=$seller_username&"), $from, $totalDatatable, $limit);

?>



<div class="main-content app-content">
    <div class="container-fluid">
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0"><i class="fa-solid fa-cart-shopping"></i> Danh sách đơn hàng
            </h1>
        </div>
        <?php if (!$CMSNT->get_row(" SELECT * FROM `automations` WHERE `type` IN ('delete_order', 'delete_order_not_uid', 'delete_order_revenue') ")): ?>
            <div class="alert alert-warning alert-dismissible fade show custom-alert-icon shadow-sm" role="alert">
                <svg class="svg-warning" xmlns="http://www.w3.org/2000/svg" height="1.5rem" viewBox="0 0 24 24"
                    width="1.5rem" fill="#000000">
                    <path d="M0 0h24v24H0z" fill="none" />
                    <path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z" />
                </svg>
                Quý khách nên cài <a class="text-primary"
                    href="https://help.cmsnt.co/huong-dan/cau-hinh-tu-dong-xoa-don-hang-da-ban-trong-shopclone7/"
                    target="_blank">tự động xóa đơn hàng</a> đã bán sau khoảng thời gian nhất định để bảo mật dữ liệu khách
                hàng và giảm tải tài nguyên máy chủ.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"><i
                        class="bi bi-x"></i></button>
            </div>
        <?php endif ?>
        <div class="row">
            <div class="col-xl-12">
                <div class="text-right">
                    <?php if (checkPermission($getUser['admin'], 'delete_orders_product') == true): ?>
                        <button type="button" onclick="openCleanupOrdersModal()" class="btn btn-warning btn-sm mb-3">
                            <i class="ri-delete-bin-line"></i> <?= __('Dọn dẹp đơn hàng'); ?>
                        </button>
                    <?php endif; ?>

                    <button type="button" onclick="top_san_pham_ban_chay()" class="btn btn-danger btn-sm mb-3">
                        <i class="fa-solid fa-chart-line"></i> TOP SẢN PHẨM BÁN CHẠY
                    </button>

                </div>
            </div>
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-body">
                        <form action="<?= base_url(); ?>" class="align-items-center mb-3" name="formSearch" method="GET">
                            <div class="row g-2 mb-3">
                                <input type="hidden" name="module" value="admin">
                                <input type="hidden" name="action" value="product-orders">
                                <input type="hidden" value="<?= $getUser['token']; ?>" id="token">
                                <div class="col-md-3 col-6">
                                    <input class="form-control" value="<?= $buyer; ?>" name="buyer" placeholder="ID User mua hàng">
                                </div>
                                <div class="col-md-3 col-6">
                                    <input class="form-control" value="<?= $username; ?>" name="username"
                                        placeholder="Username mua hàng">
                                </div>
                                <?php if ($CMSNT->site('ctv_status') == 1): ?>
                                    <div class="col-md-3 col-6">
                                        <input class="form-control" value="<?= isset($_GET['seller']) ? check_string($_GET['seller']) : ''; ?>" name="seller" placeholder="ID User bán hàng">
                                    </div>
                                    <div class="col-md-3 col-6">
                                        <input class="form-control" value="<?= isset($_GET['seller_username']) ? check_string($_GET['seller_username']) : ''; ?>" name="seller_username"
                                            placeholder="Username bán hàng">
                                    </div>
                                <?php endif; ?>
                                <div class="col-md-3 col-6">
                                    <input class="form-control" value="<?= $trans_id; ?>" name="trans_id"
                                        placeholder="Mã đơn hàng (có thể nhập nhiều mã, phân tách bằng dấu phẩy)">
                                </div>
                                <div class="col-md-3 col-6">
                                    <input class="form-control" value="<?= $uid; ?>" name="uid" placeholder="UID">
                                </div>
                                <div class="col-md-3 col-6">
                                    <input class="form-control" value="<?= $account; ?>" name="account"
                                        placeholder="Account">
                                </div>
                                <div class="col-md-3 col-6">
                                    <input class="form-control" value="<?= $api_transid; ?>" name="api_transid"
                                        placeholder="Mã đơn hàng API (có thể nhập nhiều mã, phân tách bằng dấu phẩy)">
                                </div>
                                <div class="col-md-3 col-6">
                                    <select class="form-control js-example-basic-single" name="supplier_id">
                                        <option value=""><?= __('-- API Supplier --'); ?></option>
                                        <?php foreach ($CMSNT->get_list("SELECT * FROM `suppliers` ") as $supplier): ?>
                                            <option <?= $supplier_id == $supplier['id'] ? 'selected' : ''; ?>
                                                value="<?= $supplier['id']; ?>"><?= $supplier['domain']; ?></option>
                                        <?php endforeach ?>
                                    </select>
                                </div>
                                <div class="col-md-3 col-6">
                                    <select class="form-control js-example-basic-single" name="product_id">
                                        <option value=""><?= __('-- Sản phẩm '); ?></option>
                                        <?php foreach ($CMSNT->get_list("SELECT * FROM `products` ") as $product): ?>
                                            <option <?= $product_id == $product['id'] ? 'selected' : ''; ?>
                                                value="<?= $product['id']; ?>">
                                                <?= $product['name']; ?>
                                            </option>
                                        <?php endforeach ?>
                                    </select>
                                </div>
                                <div class="col-md-3 col-6">
                                    <input type="text" name="create_gettime" class="form-control" id="daterange"
                                        value="<?= $create_gettime; ?>" placeholder="Chọn thời gian">
                                </div>
                                <div class="col-md-3 col-6">
                                    <button class="btn btn-hero btn-primary"><i class="fa fa-search"></i>
                                        <?= __('Search'); ?>
                                    </button>
                                    <a class="btn btn-hero btn-danger" href="<?= base_url_admin('product-orders'); ?>"><i
                                            class="fa fa-trash"></i>
                                        <?= __('Clear filter'); ?>
                                    </a>
                                </div>
                            </div>
                            <div class="top-filter">
                                <div class="filter-show">
                                    <label class="filter-label">Show :</label>
                                    <select name="limit" onchange="this.form.submit()"
                                        class="form-select filter-select">
                                        <option <?= $limit == 5 ? 'selected' : ''; ?> value="5">5</option>
                                        <option <?= $limit == 10 ? 'selected' : ''; ?> value="10">10</option>
                                        <option <?= $limit == 20 ? 'selected' : ''; ?> value="20">20</option>
                                        <option <?= $limit == 50 ? 'selected' : ''; ?> value="50">50</option>
                                        <option <?= $limit == 100 ? 'selected' : ''; ?> value="100">100</option>
                                        <option <?= $limit == 500 ? 'selected' : ''; ?> value="500">500</option>
                                        <option <?= $limit == 1000 ? 'selected' : ''; ?> value="1000">1000</option>
                                    </select>
                                </div>
                                <div class="filter-short">
                                    <label class="filter-label"><?= __('Short by Date:'); ?></label>
                                    <select name="shortByDate" onchange="this.form.submit()"
                                        class="form-select filter-select">
                                        <option value=""><?= __('Tất cả'); ?></option>
                                        <option <?= $shortByDate == 1 ? 'selected' : ''; ?> value="1"><?= __('Hôm nay'); ?>
                                        </option>
                                        <option <?= $shortByDate == 4 ? 'selected' : ''; ?> value="4"><?= __('Hôm qua'); ?>
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

                        <!-- Nút hành động hàng loạt -->
                        <div class="d-flex mb-3">
                            <div class="btn-list" id="bulk-action-buttons" style="display: none;">
                                <div class="dropdown">
                                    <button type="button"
                                        class="btn btn-outline-primary shadow-primary btn-wave btn-sm dropdown-toggle"
                                        data-bs-toggle="dropdown" aria-expanded="false" id="btn_thao_tac_nhanh">
                                        <i class="fa-solid fa-cog"></i> <?= __('Thao tác nhanh'); ?>
                                    </button>
                                    <ul class="dropdown-menu shadow-lg border-0">
                                        <!-- Sao chép -->
                                        <li class="dropdown-submenu">
                                            <a class="dropdown-item has-submenu" href="javascript:void(0);">
                                                <i class="fa-solid fa-copy text-info me-2"></i>
                                                <span><?= __('Sao chép'); ?></span>
                                                <i class="fa-solid fa-chevron-right ms-auto"></i>
                                            </a>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="javascript:void(0);"
                                                        onclick="copyOrderData('trans_id')">
                                                        <i class="fa-solid fa-hashtag text-primary me-2"></i>
                                                        <?= __('Mã đơn hàng'); ?>
                                                    </a></li>
                                                <li><a class="dropdown-item" href="javascript:void(0);"
                                                        onclick="copyOrderData('api_transid')">
                                                        <i class="fa-solid fa-code text-success me-2"></i>
                                                        <?= __('Mã đơn hàng API'); ?>
                                                    </a></li>
                                                <li><a class="dropdown-item" href="javascript:void(0);"
                                                        onclick="copyOrderData('product_name')">
                                                        <i class="fa-solid fa-box text-warning me-2"></i>
                                                        <?= __('Tên sản phẩm'); ?>
                                                    </a></li>
                                            </ul>
                                        </li>

                                        <li>
                                            <hr class="dropdown-divider">
                                        </li>

                                        <!-- Xuất đơn hàng -->
                                        <li><a class="dropdown-item text-success" href="javascript:void(0);"
                                                onclick="showExportModal()">
                                                <i class="fa-solid fa-download text-success me-2"></i>
                                                <?= __('Xuất đơn hàng'); ?>
                                            </a></li>

                                        <li>
                                            <hr class="dropdown-divider">
                                        </li>

                                        <!-- Xóa đơn hàng -->
                                        <li><a class="dropdown-item text-danger" href="javascript:void(0);"
                                                onclick="deleteSelectedOrders()">
                                                <i class="fa-solid fa-trash text-danger me-2"></i>
                                                <?= __('Xóa đơn hàng'); ?>
                                            </a></li>

                                    </ul>
                                </div>
                                <span id="selected-counter" class="ms-2 align-self-center text-primary"></span>
                            </div>
                            <div class="ms-auto">
                                <button id="select-all-btn" type="button" class="btn btn-sm btn-outline-secondary">
                                    <i class="fa-solid fa-check-double"></i> <?= __('Chọn tất cả'); ?>
                                </button>
                                <button id="deselect-all-btn" type="button" class="btn btn-sm btn-outline-secondary">
                                    <i class="fa-solid fa-xmark"></i> <?= __('Bỏ chọn tất cả'); ?>
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive table-wrapper mb-3">
                            <table class="table text-nowrap table-striped table-hover table-bordered">
                                <thead>
                                    <tr>
                                        <th class="text-center">
                                            <div class="form-check form-check-md d-flex align-items-center">
                                                <input type="checkbox" class="form-check-input" style="width: 20px; height: 20px; cursor: pointer;" name="check_all"
                                                    id="check_all_checkbox_product" value="option1">
                                            </div>
                                        </th>
                                        <th class="text-center"><?= __('Thao tác'); ?></th>
                                        <th class="text-center"><?= __('Bên mua'); ?></th>
                                        <?php if ($CMSNT->site('ctv_status') == 1): ?>
                                            <th class="text-center"><?= __('Bên bán'); ?></th>
                                        <?php endif ?>
                                        <th class="text-center"><?= __('Đơn hàng'); ?></th>
                                        <th class="text-center"><?= __('Thanh toán'); ?></th>
                                        <th class="text-center"><?= __('Sản phẩm'); ?></th>
                                        <th class="text-center"><?= __('Thời gian'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($listDatatable as $order): ?>
                                        <tr>
                                            <td class="text-center">
                                                <div class="form-check form-check-md d-flex align-items-center">
                                                    <input type="checkbox" class="form-check-input checkbox_product" style="width: 20px; height: 20px; cursor: pointer;"
                                                        data-id="<?= $order['id']; ?>"
                                                        data-trans-id="<?= $order['trans_id']; ?>"
                                                        data-api-transid="<?= $order['api_transid'] ? $order['api_transid'] : ''; ?>"
                                                        data-product-name="<?= htmlspecialchars($order['product_name'], ENT_QUOTES); ?>"
                                                        name="checkbox_product" value="<?= $order['id']; ?>" />
                                                </div>
                                            </td>
                                            <td class="text-center">

                                                <button class="btn btn-info btn-sm shadow-info btn-wave" id="btnViewOrder"
                                                    onclick="viewOrder(`<?= $order['trans_id']; ?>`)" data-toggle="tooltip"
                                                    type="button"><i class="fa-solid fa-eye"></i></button>
                                                <button class="btn btn-primary btn-sm shadow-primary btn-wave"
                                                    onclick="downloadOrder(`<?= $order['trans_id']; ?>`)"><i
                                                        class="fa-solid fa-cloud-arrow-down"></i></button>
                                                <button type="button" onclick="deleteOrder(`<?= $order['id']; ?>`)"
                                                    id="btnDeleteOrder<?= $order['id']; ?>"
                                                    class="btn btn-danger btn-sm shadow-danger btn-wave">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                                <button class="btn btn-success btn-sm shadow-success btn-wave refund-button"
                                                    data-id="<?= $order['id']; ?>" data-amount="<?= $order['amount']; ?>"
                                                    data-pay="<?= $order['pay']; ?>" data-transid="<?= $order['trans_id']; ?>"
                                                    <?= $order['amount'] == 0 ? 'disabled' : ''; ?> type="button">
                                                    <i class="fa-solid fa-rotate-left"></i> Hoàn tiền
                                                </button>
                                            </td>
                                            <td>
                                                <?php if ($order['buyer'] > 0): ?>
                                                    <?php $user = $CMSNT->get_row(" SELECT * FROM users WHERE id = " . $order['buyer']); ?>
                                                    <i class="fa-solid fa-user"></i> <?= $user['username']; ?> [ID
                                                    <?= $order['buyer']; ?>] <a class="text-primary"
                                                        href="<?= base_url_admin('user-edit&id=' . $order['buyer']); ?>"><i
                                                            class="fa-solid fa-edit"></i></a><br>
                                                    <i class="fa-solid fa-wallet"></i> <?= __('Số dư hiện tại:'); ?>
                                                    <strong
                                                        style="color:red;"><?= format_currency($user['money']); ?></strong><br>
                                                    <i class="fa-solid fa-money-bill-trend-up"></i> <?= __('Tổng nạp:'); ?>
                                                    <strong
                                                        style="color:green;"><?= format_currency($user['total_money']); ?></strong>
                                                <?php else: ?>
                                                    <span class="text-muted"><?= __('Hệ thống'); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <?php if ($CMSNT->site('ctv_status') == 1): ?>
                                                <td>
                                                    <?php if ($order['seller'] > 0): ?>
                                                        <?php $seller = $CMSNT->get_row(" SELECT * FROM users WHERE id = " . $order['seller']); ?>
                                                        <?php if ($seller): ?>
                                                            <i class="fa-solid fa-user-tie"></i> <?= $seller['username']; ?> [ID
                                                            <?= $order['seller']; ?>] <a class="text-primary"
                                                                href="<?= base_url_admin('user-edit&id=' . $order['seller']); ?>"><i
                                                                    class="fa-solid fa-edit"></i></a><br>
                                                            <i class="fa-solid fa-wallet"></i> <?= __('Số dư hiện tại:'); ?>
                                                            <strong
                                                                style="color:red;"><?= format_currency($seller['money']); ?></strong><br>
                                                            <i class="fa-solid fa-money-bill-trend-up"></i> <?= __('Tổng nạp:'); ?>
                                                            <strong
                                                                style="color:green;"><?= format_currency($seller['total_money']); ?></strong>
                                                        <?php endif ?>
                                                    <?php else: ?>
                                                        <span class="text-muted"><?= __('Hệ thống'); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endif ?>
                                            <td>
                                                Mã đơn hàng: #<strong><?= $order['trans_id']; ?></strong><br>
                                                Mã đơn hàng API (nếu có): #<strong><?= $order['api_transid']; ?></strong><br>
                                                <?php if (checkPermission($getUser['admin'], 'view_suppliers') == true): ?>
                                                    Server API (nếu có):
                                                    <?php if ($order['supplier_id'] != 0): ?>
                                                        <?= getRowRealtime('suppliers', $order['supplier_id'], 'domain'); ?> <a
                                                            class="text-primary"
                                                            href="<?= base_url_admin('product-api-manager&id=' . $order['supplier_id']); ?>"><i
                                                                class="fa-solid fa-edit"></i></a><br>
                                                    <?php endif ?>
                                                <?php endif ?>
                                            </td>
                                            <td>
                                                Số lượng: <strong><?= format_cash($order['amount']); ?></strong><br>
                                                Thanh toán: <strong
                                                    style="color:red;"><?= format_currency($order['pay']); ?></strong><br>
                                                Giá vốn: <strong
                                                    style="color:blue;"><?= format_currency($order['cost']); ?></strong> -
                                                Lãi: <strong
                                                    style="color:green;"><?= format_currency($order['pay'] - $order['cost']); ?></strong><br>
                                            </td>
                                            <td>
                                                <?= $order['product_name']; ?> <a class="text-primary"
                                                    href="<?= base_url_admin('product-edit&id=' . $order['product_id']); ?>"><i
                                                        class="fa-solid fa-edit"></i></a>
                                                <?php if ($order['supplier_id'] != 0): ?>
                                                    <br><small>Tên sản phẩm API:
                                                        <?= getRowRealtime('products', $order['product_id'], 'api_name'); ?></small>
                                                <?php endif ?>
                                            </td>
                                            <td class="text-center">
                                                <strong data-toggle="tooltip" data-placement="bottom"
                                                    title="<?= timeAgo(strtotime($order['create_gettime'])); ?>"><?= $order['create_gettime']; ?></strong>
                                            </td>

                                        </tr>
                                    <?php endforeach ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="<?= $CMSNT->site('ctv_status') == 1 ? '8' : '7' ?>">
                                            <?php
                                            $stats = $CMSNT->get_row(" SELECT 
                                                SUM(`amount`) as total_amount,
                                                SUM(`pay`) as total_revenue,
                                                SUM(`cost`) as total_cost
                                                FROM `product_order` 
                                                WHERE `refund` = 0 AND $where 
                                            ");
                                            $total_amount = $stats['total_amount'] ?? 0;
                                            $total_revenue = $stats['total_revenue'] ?? 0;
                                            $total_cost = $stats['total_cost'] ?? 0;
                                            $total_profit = $total_revenue - $total_cost;
                                            ?>
                                            <div class="text-right">
                                                Tài khoản đã bán:
                                                <strong><?= format_cash($total_amount); ?></strong>
                                                |
                                                Đơn hàng: <strong
                                                    style="color: green;"><?= format_cash($totalDatatable); ?></strong> |
                                                Doanh thu: <strong
                                                    style="color:red;"><?= format_currency($total_revenue); ?></strong>
                                                |
                                                Giá vốn: <strong
                                                    style="color:orange;"><?= format_currency($total_cost); ?></strong>
                                                |
                                                Lợi nhuận: <strong
                                                    style="color:blue;"><?= format_currency($total_profit); ?></strong>
                                            </div>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <div class="row">
                            <div class="col-sm-12 col-md-5">
                                <p class="dataTables_info">Showing <?= $limit; ?> of <?= format_cash($totalDatatable); ?>
                                    Results</p>
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


<?php
require_once(__DIR__ . '/footer.php');
?>

<!-- Modal Export đơn hàng -->
<div class="modal fade" id="exportOrdersModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-download me-2"></i><?= __('Xuất đơn hàng'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-medium"><?= __('Loại file'); ?></label>
                    <select class="form-select" id="exportFileType">
                        <option value="txt">TXT (Tab-separated)</option>
                        <option value="csv">CSV (Comma-separated)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-medium"><?= __('Chọn và sắp xếp cột'); ?></label>
                    <small class="text-muted d-block mb-2">
                        <i class="fa-solid fa-grip-vertical me-1"></i><?= __('Kéo thả để sắp xếp thứ tự cột'); ?>
                    </small>
                    <ul class="list-group" id="exportColumnsList">
                        <li class="list-group-item d-flex align-items-center" data-column="trans_id">
                            <i class="fa-solid fa-grip-vertical me-3 text-muted cursor-move"></i>
                            <input type="checkbox" class="form-check-input me-2 export-col-checkbox" value="trans_id" checked>
                            <span><?= __('Mã đơn hàng'); ?></span>
                        </li>
                        <li class="list-group-item d-flex align-items-center" data-column="api_transid">
                            <i class="fa-solid fa-grip-vertical me-3 text-muted cursor-move"></i>
                            <input type="checkbox" class="form-check-input me-2 export-col-checkbox" value="api_transid">
                            <span><?= __('Mã đơn API'); ?></span>
                        </li>
                        <li class="list-group-item d-flex align-items-center" data-column="username">
                            <i class="fa-solid fa-grip-vertical me-3 text-muted cursor-move"></i>
                            <input type="checkbox" class="form-check-input me-2 export-col-checkbox" value="username" checked>
                            <span><?= __('Username'); ?></span>
                        </li>
                        <li class="list-group-item d-flex align-items-center" data-column="product_name">
                            <i class="fa-solid fa-grip-vertical me-3 text-muted cursor-move"></i>
                            <input type="checkbox" class="form-check-input me-2 export-col-checkbox" value="product_name" checked>
                            <span><?= __('Sản phẩm'); ?></span>
                        </li>
                        <li class="list-group-item d-flex align-items-center" data-column="amount">
                            <i class="fa-solid fa-grip-vertical me-3 text-muted cursor-move"></i>
                            <input type="checkbox" class="form-check-input me-2 export-col-checkbox" value="amount" checked>
                            <span><?= __('Số lượng'); ?></span>
                        </li>
                        <li class="list-group-item d-flex align-items-center" data-column="pay">
                            <i class="fa-solid fa-grip-vertical me-3 text-muted cursor-move"></i>
                            <input type="checkbox" class="form-check-input me-2 export-col-checkbox" value="pay" checked>
                            <span><?= __('Thanh toán'); ?></span>
                        </li>
                        <li class="list-group-item d-flex align-items-center" data-column="cost">
                            <i class="fa-solid fa-grip-vertical me-3 text-muted cursor-move"></i>
                            <input type="checkbox" class="form-check-input me-2 export-col-checkbox" value="cost">
                            <span><?= __('Giá vốn'); ?></span>
                        </li>
                        <li class="list-group-item d-flex align-items-center" data-column="create_gettime">
                            <i class="fa-solid fa-grip-vertical me-3 text-muted cursor-move"></i>
                            <input type="checkbox" class="form-check-input me-2 export-col-checkbox" value="create_gettime" checked>
                            <span><?= __('Ngày tạo'); ?></span>
                        </li>
                        <li class="list-group-item d-flex align-items-center" data-column="delivery_content">
                            <i class="fa-solid fa-grip-vertical me-3 text-muted cursor-move"></i>
                            <input type="checkbox" class="form-check-input me-2 export-col-checkbox" value="delivery_content">
                            <span><?= __('Nội dung giao'); ?></span>
                        </li>
                    </ul>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="toggleAllExportColumns(true)">
                        <i class="fa-solid fa-check-double me-1"></i><?= __('Chọn tất cả'); ?>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAllExportColumns(false)">
                        <i class="fa-solid fa-times me-1"></i><?= __('Bỏ chọn tất cả'); ?>
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('Hủy'); ?></button>
                <button type="button" class="btn btn-success" id="confirmExportBtn" onclick="confirmExportOrders()">
                    <i class="fa-solid fa-download me-1"></i><?= __('Tải về'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    #exportColumnsList .cursor-move {
        cursor: move;
    }

    #exportColumnsList .list-group-item {
        user-select: none;
    }

    #exportColumnsList .list-group-item.sortable-ghost {
        opacity: 0.4;
        background-color: #e3f2fd;
    }
</style>

<!-- Sortable.js for drag-drop column ordering -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<!-- Loading overlay -->
<div class="loading-overlay" id="loading-overlay">
    <div class="loading-spinner"></div>
</div>


<!-- Modal Hoàn tiền -->
<div class="modal fade" id="refundModal" tabindex="-1" aria-labelledby="refundModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <!-- modal-lg để rộng hơn, tùy ý -->
        <div class="modal-content rounded-3 shadow">

            <!-- Tiêu đề Modal (header) -->
            <div class="modal-header text-white">
                <!-- Tiêu đề sẽ được thiết lập động bằng JS -->
                <h5 class="modal-title fw-bold" id="refundModalLabel"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- Phần nội dung (body) -->
            <div class="modal-body py-4">

                <!-- Thông tin hoặc hướng dẫn (tùy chọn) -->
                <div class="alert alert-info d-flex align-items-center" role="alert">
                    <i class="fa-solid fa-circle-info me-2"></i>
                    <div>
                        Vui lòng chọn <strong>Hoàn toàn bộ</strong> hoặc <strong>Hoàn một phần</strong> và nhập số lượng
                        cần hoàn.
                    </div>
                </div>

                <!-- Form chính -->
                <form class="px-2">

                    <!-- Chọn kiểu hoàn tiền -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold d-block mb-2">Chọn cách hoàn tiền <span
                                class="text-danger">*</span></label>
                        <div class="form-check form-check-inline form-check-md">
                            <input class="form-check-input" type="radio" name="refundType" id="refundFull" value="full"
                                checked>
                            <label class="form-check-label" for="refundFull">
                                Hoàn toàn bộ
                            </label>
                        </div>
                        <div class="form-check form-check-inline form-check-md">
                            <input class="form-check-input" type="radio" name="refundType" id="refundPartial"
                                value="partial">
                            <label class="form-check-label" for="refundPartial">
                                Hoàn một phần
                            </label>
                        </div>
                    </div>

                    <!-- Nhập số lượng khi hoàn một phần -->
                    <div id="partialGroup" style="display: none;">
                        <div class="mb-3">
                            <label for="partialQuantity" class="form-label fw-semibold"><?= __('Số lượng cần hoàn'); ?>
                                <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light" id="basic-addon1">
                                    <i class="fa-solid fa-hashtag"></i>
                                </span>
                                <input type="number" class="form-control" id="partialQuantity" name="partialQuantity"
                                    placeholder="Nhập số lượng cần hoàn" min="1" aria-describedby="basic-addon1">
                            </div>
                            <small class="text-muted"><?= __('Không vượt quá tổng số lượng còn lại'); ?></small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="reason" class="form-label fw-semibold"><?= __('Lý do hoàn tiền'); ?> <span
                                class="text-danger">*</span></label>
                        <textarea class="form-control" id="reason" placeholder="Nhập nội dung hoàn tiền"></textarea>
                    </div>

                    <!-- Hiển thị tổng số tiền hoàn -->
                    <div class="mb-3">
                        <label for="refundAmount" class="form-label fw-semibold"><?= __('Tổng số tiền hoàn'); ?></label>
                        <input type="text" class="form-control" id="refundAmount" name="refundAmount" placeholder="0"
                            disabled>
                    </div>

                    <!-- Hiển thị số lượng tài khoản hoàn -->
                    <div class="mb-3">
                        <label for="refundCount"
                            class="form-label fw-semibold"><?= __('Số lượng tài khoản hoàn'); ?></label>
                        <div class="form-floating">
                            <input type="text" class="form-control" id="refundCount" name="refundCount" placeholder="0"
                                disabled>
                            <label for="refundCount">Tài khoản</label>
                        </div>
                    </div>

                </form>
                <div class="alert alert-warning d-flex align-items-center" role="alert">
                    <i class="fa-solid fa-warning me-2"></i>
                    <div>
                        <?= __('Hệ thống sẽ thu hồi hoa hồng nếu đơn hàng có phát sinh hoa hồng cho người giới thiệu.'); ?>
                    </div>
                </div>
            </div>

            <!-- Footer của Modal -->
            <div class="modal-footer border-top-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="fa-solid fa-xmark me-1"></i> Đóng
                </button>
                <button type="button" class="btn btn-primary" id="confirmRefund">
                    <i class="fa-solid fa-check me-1"></i> Xác nhận hoàn tiền
                </button>
            </div>
        </div>
    </div>
</div>


<script>
    $(document).ready(() => {
        // ======== 1. Khai báo biến dùng chung ========
        let orderId, orderAmount, orderPay, transId;

        // Cache các selector thường dùng
        const $refundModal = $('#refundModal');
        const $refundModalLabel = $('#refundModalLabel');
        const $refundFull = $('#refundFull');
        const $refundPartial = $('#refundPartial');
        const $partialGroup = $('#partialGroup');
        const $partialQuantity = $('#partialQuantity');
        const $refundAmount = $('#refundAmount');
        const $refundCount = $('#refundCount');
        const $reason = $('#reason');
        const $confirmRefundBtn = $('#confirmRefund');
        const $tokenInput = $('#token'); // Token bảo mật
        const originalBtnContent = $confirmRefundBtn.html(); // Lưu html gốc của nút

        // ======== 2. Hàm phụ ========
        // (A) Đặt lý do hoàn tiền tương ứng
        const setReason = (type) => {
            if (type === 'partial') {
                $reason.val(`Hoàn tiền một phần đơn hàng #${transId}`);
            } else {
                $reason.val(`Hoàn tiền đơn hàng #${transId}`);
            }
        };

        // (B) Ẩn/Hiện khối nhập số lượng partial
        const togglePartialGroup = (show) => {
            if (show) {
                $partialGroup.show();
            } else {
                $partialGroup.hide();
            }
        };

        // ======== 3. Hàm tính tiền hoàn (AJAX) ========
        const calculateRefund = (refundType, partialQuantity) => {
            $.ajax({
                    url: '<?= base_url('ajaxs/admin/view.php'); ?>',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'tinh_tien_refund',
                        id: orderId,
                        refundType: refundType,
                        partialQuantity: partialQuantity
                    }
                })
                .done((response) => {
                    if (response.status === 'error') {
                        showMessage(response.msg, 'error');
                    } else {
                        $refundAmount.val(response.totalRefund);
                        $refundCount.val(refundType === 'full' ? orderAmount : partialQuantity);
                    }
                })
                .fail((xhr, status, error) => {
                    console.error('Lỗi khi tính toán số tiền hoàn:', error);
                });
        };

        // ======== 4. Sự kiện click nút "Hoàn tiền" ========
        $('.refund-button').on('click', function() {
            // Lấy dữ liệu từ nút
            orderId = $(this).data('id');
            orderAmount = parseFloat($(this).data('amount'));
            orderPay = parseFloat($(this).data('pay'));
            transId = $(this).data('transid');

            // Thiết lập Modal
            $refundModalLabel.html(
                `<i class="fa-solid fa-rotate-left"></i> Hoàn tiền đơn hàng #<b>${transId}</b>`
            );

            // Reset form về trạng thái "Hoàn toàn bộ"
            $refundFull.prop('checked', true);
            $refundPartial.prop('checked', false);
            togglePartialGroup(false);
            $partialQuantity.val('').attr('max', orderAmount);

            $refundAmount.val(orderPay.toFixed(2));
            $refundCount.val(orderAmount);
            setReason('full');

            // Tính toán tiền hoàn kiểu full
            calculateRefund('full', orderAmount);

            // Hiển thị Modal
            $refundModal.modal('show');
        });

        // ======== 5. Chọn "Hoàn toàn bộ" / "Hoàn một phần" ========
        $('input[name="refundType"]').change(() => {
            const isPartial = $refundPartial.is(':checked');
            if (isPartial) {
                togglePartialGroup(true);
                // Reset trường liên quan
                $refundAmount.val('');
                $partialQuantity.val('');
                $refundCount.val('');
                setReason('partial');
            } else {
                togglePartialGroup(false);
                calculateRefund('full', orderAmount);
                setReason('full');
            }
        });

        // ======== 6. Nhập số lượng hoàn một phần ========
        $partialQuantity.on('input', function() {
            let quantity = parseInt($(this).val(), 10) || 0;
            if (quantity > orderAmount) {
                quantity = orderAmount;
                $(this).val(quantity);
            }
            // Hiển thị số lượng
            $refundCount.val(quantity);

            // Tính tiền hoàn
            calculateRefund('partial', quantity);
        });

        // ======== 7. Xác nhận hoàn tiền ========
        $confirmRefundBtn.on('click', () => {
            // 1. Kiểm tra bắt buộc
            const refundType = $('input[name="refundType"]:checked').val();
            const partialQuantity = parseInt($partialQuantity.val()) || 0;
            const currentReason = $reason.val().trim();

            // Nếu chọn "Hoàn một phần" mà chưa nhập hoặc nhập 0 => báo lỗi
            if (refundType === 'partial' && partialQuantity < 1) {
                showMessage('Vui lòng nhập số lượng tài khoản cần hoàn!', 'error');
                return; // Dừng, không thực hiện tiếp
            }

            // Kiểm tra ô "Lý do hoàn tiền" - nếu trống => báo lỗi
            if (!currentReason) {
                showMessage('Vui lòng nhập lý do hoàn tiền!', 'error');
                return;
            }

            // 2. Thông báo confirm
            if (!confirm('Bạn có chắc chắn muốn hoàn tiền đơn hàng này?')) {
                return; // Nếu người dùng bấm "Hủy", cũng dừng luôn
            }

            // 3. Bắt đầu xử lý hoàn tiền
            // Vô hiệu hóa nút & hiển thị loading
            $confirmRefundBtn.prop('disabled', true).html(
                '<i class="fa-solid fa-spinner fa-spin me-1"></i> Đang xử lý...');

            // Gửi AJAX hoàn tiền
            $.ajax({
                    url: '<?= BASE_URL('ajaxs/admin/update.php'); ?>',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        token: $tokenInput.val(),
                        action: 'refundOrder',
                        id: orderId,
                        refundType: refundType,
                        partialQuantity: partialQuantity,
                        reason: currentReason
                    }
                })
                .done((result) => {
                    showMessage(result.msg, result.status);
                    $refundModal.modal('hide');
                    if (result.status === 'success') {
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    }
                })
                .fail(() => {
                    alert('Đã có lỗi xảy ra khi hoàn tiền!');
                })
                .always(() => {
                    // Khôi phục trạng thái nút
                    $confirmRefundBtn.prop('disabled', false).html(originalBtnContent);
                });
        });

    });
</script>






<script>
    // JavaScript xử lý chức năng cập nhật nhanh
    $(function() {
        // Checkbox "check all"
        $('#check_all_checkbox_product').on('click', function() {
            $('.checkbox_product').prop('checked', this.checked);
            updateSelectedRows();
        });

        // Chọn/bỏ chọn hàng khi click vào checkbox
        $(document).on('change', '.checkbox_product', function() {
            updateSelectedRows();
        });

        // Nút chọn tất cả
        $('#select-all-btn').on('click', function() {
            $('.checkbox_product').prop('checked', true);
            updateSelectedRows();
        });

        // Nút bỏ chọn tất cả
        $('#deselect-all-btn').on('click', function() {
            $('.checkbox_product').prop('checked', false);
            updateSelectedRows();
        });

        function updateSelectedRows() {
            // Highlight các hàng được chọn
            $('.checkbox_product').each(function() {
                if ($(this).prop('checked')) {
                    $(this).closest('tr').addClass('selected');
                } else {
                    $(this).closest('tr').removeClass('selected');
                }
            });

            // Cập nhật số lượng đã chọn
            var count = $('.checkbox_product:checked').length;

            // Hiển thị/ẩn các nút hành động hàng loạt
            if (count > 0) {
                $('#bulk-action-buttons').fadeIn(10);
                $('#selected-counter').text(count + ' ' + (count == 1 ? '<?= __('đơn hàng'); ?>' :
                    '<?= __('đơn hàng'); ?>') + ' <?= __('đã chọn'); ?>');
            } else {
                $('#bulk-action-buttons').fadeOut(10);
                $('#selected-counter').text('');
            }
        }

        // Xử lý checkbox chọn tất cả trong bảng
        $('#check_all_checkbox_product').on('change', function() {
            $('.checkbox_product').prop('checked', this.checked);
            updateSelectedRows();
        });
    });

    function post_remove(id) {
        $.ajax({
            url: "<?= BASE_URL("ajaxs/admin/remove.php"); ?>",
            method: "POST",
            dataType: "JSON",
            data: {
                id: id,
                token: $("#token").val(),
                action: 'removeOrder'
            },
            success: function(result) {
                if (result.status == 'success') {
                    showMessage(result.msg, result.status);
                } else {
                    showMessage(result.msg, result.status);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                showMessage('Có lỗi xảy ra khi xóa đơn hàng: ' + error, 'error');
            }
        });
    }

    // Hàm xử lý xóa nhiều đơn hàng một lúc
    function delete_records() {
        // Hiển thị loading
        $('#loading-overlay').addClass('active');

        // Thu thập ID của các đơn hàng được chọn
        var ids = [];
        $('.checkbox_product:checked').each(function() {
            ids.push($(this).val());
        });

        $.ajax({
            url: "<?= BASE_URL("ajaxs/admin/remove.php"); ?>",
            method: "POST",
            dataType: "JSON",
            data: {
                action: 'bulkRemoveOrders',
                ids: JSON.stringify(ids),
                token: $("#token").val()
            },
            success: function(respone) {
                if (respone.status == 'success') {
                    Swal.fire({
                        title: "<?= __('Thành công!'); ?>",
                        text: respone.msg,
                        icon: "success"
                    }).then((result) => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        title: "<?= __('Thất bại!'); ?>",
                        text: respone.msg,
                        icon: "error"
                    });
                }
            },
            error: function() {
                Swal.fire({
                    title: "<?= __('Thất bại!'); ?>",
                    text: "<?= __('Đã xảy ra lỗi khi kết nối đến máy chủ'); ?>",
                    icon: "error"
                });
            },
            complete: function() {
                // Ẩn loading
                $('#loading-overlay').removeClass('active');
            }
        });
    }

    // Hàm sao chép dữ liệu đơn hàng theo loại
    function copyOrderData(dataType) {
        var selectedOrders = $('.checkbox_product:checked');

        if (selectedOrders.length == 0) {
            showMessage('<?= __('Vui lòng chọn ít nhất một đơn hàng'); ?>', 'error');
            return;
        }

        var dataList = [];
        var labelText = '';

        selectedOrders.each(function() {
            var element = $(this);
            var data = '';

            switch (dataType) {
                case 'trans_id':
                    data = element.attr('data-trans-id');
                    labelText = '<?= __('mã đơn hàng'); ?>';
                    break;
                case 'api_transid':
                    data = element.attr('data-api-transid');
                    labelText = '<?= __('mã đơn hàng API'); ?>';
                    break;
                case 'product_name':
                    data = element.attr('data-product-name');
                    labelText = '<?= __('tên sản phẩm'); ?>';
                    break;
                default:
                    data = element.attr('data-id');
                    labelText = '<?= __('ID đơn hàng'); ?>';
            }

            // Chỉ thêm dữ liệu không rỗng
            if (data && data.trim() !== '') {
                dataList.push(data.trim());
            }
        });

        if (dataList.length === 0) {
            showMessage('<?= __('Không có dữ liệu'); ?> ' + labelText + ' <?= __('để sao chép'); ?>', 'warning');
            return;
        }

        // Tạo text để sao chép - mỗi dữ liệu một dòng
        var textToCopy = dataList.join('\n');

        // Sao chép vào clipboard
        if (navigator.clipboard) {
            navigator.clipboard.writeText(textToCopy).then(function() {
                showMessage('<?= __('Đã sao chép'); ?> ' + dataList.length + ' ' + labelText, 'success');
            }).catch(function(err) {
                fallbackCopyTextToClipboard(textToCopy, dataList.length, labelText);
            });
        } else {
            // Fallback cho các trình duyệt cũ
            fallbackCopyTextToClipboard(textToCopy, dataList.length, labelText);
        }
    }

    // Hàm fallback để sao chép text
    function fallbackCopyTextToClipboard(text, count, label) {
        var textArea = document.createElement("textarea");
        textArea.value = text;
        textArea.style.position = "fixed";
        textArea.style.top = "-1000px";
        textArea.style.left = "-1000px";
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();

        try {
            var successful = document.execCommand('copy');
            if (successful) {
                showMessage('<?= __('Đã sao chép'); ?> ' + count + ' ' + label, 'success');
            } else {
                showMessage('<?= __('Không thể sao chép'); ?>', 'error');
            }
        } catch (err) {
            console.error('<?= __('Lỗi fallback sao chép'); ?>: ', err);
            showMessage('<?= __('Không thể sao chép'); ?>', 'error');
        }

        document.body.removeChild(textArea);
    }

    // Hàm xóa đơn hàng đã chọn
    function deleteSelectedOrders() {
        var checkboxes = document.querySelectorAll('input[name="checkbox_product"]:checked');
        if (checkboxes.length === 0) {
            showMessage('<?= __('Vui lòng chọn ít nhất một đơn hàng'); ?>', 'error');
            return;
        }

        Swal.fire({
            title: "<?= __('Xác nhận xóa đơn hàng'); ?>",
            html: `
            <div class="text-start">
                <div class="alert alert-danger mb-3">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong><?= __('Cảnh báo!'); ?></strong><br>
                    <?= __('Bạn sắp xóa'); ?> <strong>${checkboxes.length}</strong> <?= __('đơn hàng'); ?><br>
                    <small><?= __('Hành động này không thể hoàn tác!'); ?></small>
                </div>
                <label for="confirmText" class="form-label">
                    <?= __('Để xác nhận, vui lòng nhập'); ?> <strong class="text-danger">DELETE</strong>
                </label>
                <input type="text" id="confirmText" class="form-control" placeholder="<?= __('Nhập: DELETE'); ?>" autocomplete="off">
                <small class="text-muted mt-1 d-block"><?= __('Nhập chính xác từ "DELETE" để tiếp tục'); ?></small>
            </div>
        `,
            icon: "warning",
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonColor: "#dc3545",
            cancelButtonColor: "#6c757d",
            confirmButtonText: "<?= __('Xóa đơn hàng'); ?>",
            cancelButtonText: "<?= __('Hủy'); ?>",
            preConfirm: () => {
                const confirmText = document.getElementById('confirmText').value.trim();
                if (confirmText !== 'DELETE') {
                    Swal.showValidationMessage('<?= __('Vui lòng nhập chính xác "DELETE" để xác nhận'); ?>');
                    return false;
                }
                return true;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                delete_records();
            }
        });
    }
</script>

<script type="text/javascript">
    new ClipboardJS(".copy");

    function copy() {
        showMessage("<?= __('Đã sao chép vào bộ nhớ tạm'); ?>", 'success');
    }
</script>

<script>
    function downloadOrder(trans_id) {
        Swal.fire({
            title: "<?= __('Xác nhận tải đơn hàng'); ?>",
            text: "<?= __('Hệ thống sẽ tải về đơn hàng khi bạn nhấn đồng ý'); ?>",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#3085d6",
            cancelButtonColor: "#d33",
            confirmButtonText: "<?= __('Đồng ý'); ?>",
            cancelButtonText: "<?= __('Đóng'); ?>",
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: "<?= BASE_URL("ajaxs/admin/view.php"); ?>",
                    method: "POST",
                    dataType: "JSON",
                    data: {
                        action: 'download_order',
                        trans_id: trans_id,
                        token: $("#token").val(),
                    },
                    success: function(result) {
                        if (result.status == 'success') {
                            showMessage(result.msg, result.status);
                            downloadTXT(result.filename, result.accounts);
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
        });
    }

    function downloadTXT(filename, text) {
        var element = document.createElement('a');
        element.setAttribute('href', 'data:text/plain;charset=utf-8,' + encodeURIComponent(text));
        element.setAttribute('download', filename);
        element.style.display = 'none';
        document.body.appendChild(element);
        element.click();
        document.body.removeChild(element);
    }
</script>


<script>
    function deleteOrder(id) {
        const originalContent = $('#btnDeleteOrder' + id)
            .html(); // Save the original button content
        $('#btnDeleteOrder' + id).html(
                '<span><i class="fa fa-spinner fa-spin"></i></span>')
            .prop('disabled', true);
        Swal.fire({
            title: "<?= __('Xác nhận xóa đơn hàng'); ?>",
            text: "<?= __('Hệ thống sẽ xóa đơn hàng khỏi hệ thống khi bạn nhấn đồng ý'); ?>",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#3085d6",
            cancelButtonColor: "#d33",
            confirmButtonText: "<?= __('Đồng ý'); ?>",
            cancelButtonText: "<?= __('Đóng'); ?>",
        }).then((result) => {
            if (result.isConfirmed) {
                post_remove(id);
                setTimeout(function() {
                    location.reload();
                }, 500);
            }
        }).finally(() => {
            $('#btnDeleteOrder' + id).html(originalContent)
                .prop('disabled', false);
        });
    }
</script>


<div class="modal fade" id="viewOrder" tabindex="-1" aria-labelledby="viewOrder" data-bs-keyboard="false"
    aria-hidden="true">
    <!-- Scrollable modal -->
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="viewOrder"><i class="fa-solid fa-eye"></i> CHI TIẾT ĐƠN HÀNG
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <textarea class="form-control" id="coypyBox" readonly rows="10"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="copy()" data-clipboard-target="#coypyBox"
                    class="btn btn-danger shadow-danger btn-wave copy">Sao chép</button>
                <button type="button" class="btn btn-light shadow-light btn-wave" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>
<script type="text/javascript">
    function viewOrder(trans_id) {
        $.ajax({
            url: "<?= base_url('ajaxs/admin/view.php'); ?>",
            method: "POST",
            dataType: "JSON",
            data: {
                action: 'view_order',
                token: '<?= $getUser['token']; ?>',
                trans_id: trans_id
            },
            success: function(result) {
                $('#viewOrder').modal('show');
                $('#coypyBox').val(result.accounts);
            },
            error: function() {
                alert(html(result));
                location.reload();
            }
        });
    }
</script>



<div class="modal fade" id="top_san_pham_ban_chay" tabindex="-1" aria-labelledby="top_san_pham_ban_chay"
    data-bs-keyboard="false" aria-hidden="true">
    <!-- Scrollable modal -->
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="top_san_pham_ban_chay"><i class="fa-solid fa-chart-line"></i> TOP SẢN PHẨM
                    BÁN CHẠY
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="hien_thi_top_san_pham_ban_chay"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light shadow-light btn-wave" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>
<script type="text/javascript">
    function top_san_pham_ban_chay() {
        $('#hien_thi_top_san_pham_ban_chay').html(
            '<h5 class="mb-3 py-4 text-center"><i class="fa fa-spinner fa-spin"></i> Đang phân tích dữ liệu, vui lòng chờ...</h5>'
        );
        $('#top_san_pham_ban_chay').modal('show');
        $.ajax({
            url: "<?= base_url('ajaxs/admin/view.php'); ?>",
            method: "POST",
            data: {
                action: 'top_san_pham_ban_chay',
                token: '<?= $getUser['token']; ?>'
            },
            success: function(result) {
                $('#hien_thi_top_san_pham_ban_chay').html(result);
            },
            error: function() {
                $('#hien_thi_top_san_pham_ban_chay').html(result);
            }
        });
    }
</script>

<script>
    // Xử lý tự động chuyển đổi định dạng mã đơn hàng khi paste
    $(document).ready(function() {
        // Xử lý sự kiện paste cho input trans_id
        $('input[name="trans_id"]').on('paste', function(e) {
            const input = this;

            // Ngăn chặn hành vi paste mặc định
            e.preventDefault();

            // Lấy dữ liệu từ clipboard
            let clipboardData = e.originalEvent.clipboardData || window.clipboardData;
            let pastedData = clipboardData.getData('text').trim();

            // Lấy nội dung hiện tại của input
            let currentValue = $(input).val().trim();

            // Xử lý dữ liệu paste
            let newData = '';

            // Kiểm tra xem có phải dữ liệu nhiều dòng không
            if (pastedData.includes('\n') || pastedData.includes('\r')) {
                // Tách các dòng và loại bỏ khoảng trắng
                let lines = pastedData.split(/[\r\n]+/)
                    .map(line => line.trim())
                    .filter(line => line.length > 0);

                if (lines.length > 1) {
                    // Nối các dòng bằng dấu phẩy
                    newData = lines.join(',');

                    // Hiển thị thông báo cho việc chuyển đổi
                    showMessage('<?= __('Đã chuyển đổi'); ?> ' + lines.length +
                        ' <?= __('mã đơn hàng thành định dạng phân tách bằng dấu phẩy'); ?>', 'success');
                } else {
                    // Nếu chỉ có 1 dòng
                    newData = lines[0] || '';
                }
            } else {
                // Nếu không có xuống dòng
                newData = pastedData;
            }

            // Kết hợp với nội dung hiện có
            let finalValue = '';
            if (currentValue && newData) {
                // Nếu cả hai đều có nội dung
                // Kiểm tra xem currentValue có kết thúc bằng dấu phẩy không
                if (currentValue.endsWith(',')) {
                    finalValue = currentValue + newData;
                } else {
                    finalValue = currentValue + ',' + newData;
                }
            } else if (newData) {
                // Chỉ có dữ liệu mới
                finalValue = newData;
            } else {
                // Không có dữ liệu mới, giữ nguyên
                finalValue = currentValue;
            }

            // Loại bỏ dấu phẩy trùng lặp
            finalValue = finalValue.replace(/,+/g, ',').replace(/^,|,$/g, '');

            // Cập nhật giá trị input
            $(input).val(finalValue);
        });

        // Thêm tooltip hướng dẫn
        $('input[name="trans_id"]').attr('title',
            '<?= __('Có thể paste nhiều mã đơn hàng (mỗi mã một dòng), hệ thống sẽ tự động thêm vào danh sách hiện có'); ?>'
        );

        // Xử lý sự kiện paste cho input api_transid
        $('input[name="api_transid"]').on('paste', function(e) {
            const input = this;

            // Ngăn chặn hành vi paste mặc định
            e.preventDefault();

            // Lấy dữ liệu từ clipboard
            let clipboardData = e.originalEvent.clipboardData || window.clipboardData;
            let pastedData = clipboardData.getData('text').trim();

            // Lấy nội dung hiện tại của input
            let currentValue = $(input).val().trim();

            // Xử lý dữ liệu paste
            let newData = '';

            // Kiểm tra xem có phải dữ liệu nhiều dòng không
            if (pastedData.includes('\n') || pastedData.includes('\r')) {
                // Tách các dòng và loại bỏ khoảng trắng
                let lines = pastedData.split(/[\r\n]+/)
                    .map(line => line.trim())
                    .filter(line => line.length > 0);

                if (lines.length > 1) {
                    // Nối các dòng bằng dấu phẩy
                    newData = lines.join(',');

                    // Hiển thị thông báo cho việc chuyển đổi
                    showMessage('<?= __('Đã chuyển đổi'); ?> ' + lines.length +
                        ' <?= __('mã đơn hàng API thành định dạng phân tách bằng dấu phẩy'); ?>',
                        'success');
                } else {
                    // Nếu chỉ có 1 dòng
                    newData = lines[0] || '';
                }
            } else {
                // Nếu không có xuống dòng
                newData = pastedData;
            }

            // Kết hợp với nội dung hiện có
            let finalValue = '';
            if (currentValue && newData) {
                // Nếu cả hai đều có nội dung
                // Kiểm tra xem currentValue có kết thúc bằng dấu phẩy không
                if (currentValue.endsWith(',')) {
                    finalValue = currentValue + newData;
                } else {
                    finalValue = currentValue + ',' + newData;
                }
            } else if (newData) {
                // Chỉ có dữ liệu mới
                finalValue = newData;
            } else {
                // Không có dữ liệu mới, giữ nguyên
                finalValue = currentValue;
            }

            // Loại bỏ dấu phẩy trùng lặp
            finalValue = finalValue.replace(/,+/g, ',').replace(/^,|,$/g, '');

            // Cập nhật giá trị input
            $(input).val(finalValue);
        });

        // Thêm tooltip hướng dẫn cho api_transid
        $('input[name="api_transid"]').attr('title',
            '<?= __('Có thể paste nhiều mã đơn hàng API (mỗi mã một dòng), hệ thống sẽ tự động thêm vào danh sách hiện có'); ?>'
        );
    });
</script>

<!-- Modal Dọn dẹp đơn hàng -->
<?php if (checkPermission($getUser['admin'], 'delete_orders_product') == true): ?>
    <div class="modal fade" id="cleanupOrdersModal" tabindex="-1" aria-labelledby="cleanupOrdersModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content rounded-3 shadow">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title fw-bold" id="cleanupOrdersModalLabel">
                        <i class="ri-delete-bin-line me-2"></i> <?= __('Dọn dẹp đơn hàng'); ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4">
                    <div class="alert alert-danger d-flex align-items-start" role="alert">
                        <i class="fa-solid fa-triangle-exclamation me-2 mt-1"></i>
                        <div>
                            <strong><?= __('Cảnh báo!'); ?></strong><br>
                            <?= __('Hành động này sẽ xóa các đơn hàng cũ theo điều kiện bạn chọn. Dữ liệu đã xóa không thể khôi phục!'); ?>
                        </div>
                    </div>

                    <form id="cleanupOrdersForm">
                        <!-- Loại dọn dẹp -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold d-block mb-3">
                                <?= __('Chọn loại dọn dẹp'); ?> <span class="text-danger">*</span>
                            </label>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="radio" name="cleanup_type" id="cleanup_type_1" value="delete_order_revenue" checked>
                                <label class="form-check-label" for="cleanup_type_1">
                                    <strong class="text-danger"><?= __('Xóa toàn bộ đơn hàng và tài khoản'); ?></strong>
                                    <br><small class="text-muted"><?= __('Xóa hoàn toàn đơn hàng và tất cả tài khoản đã bán (bao gồm UID, Account...)'); ?></small>
                                </label>
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="radio" name="cleanup_type" id="cleanup_type_2" value="delete_order_only">
                                <label class="form-check-label" for="cleanup_type_2">
                                    <strong class="text-primary"><?= __('Xóa đơn hàng, không xóa tài khoản'); ?></strong>
                                    <br><small class="text-muted"><?= __('Ẩn đơn hàng nhưng giữ nguyên toàn bộ tài khoản đã bán'); ?></small>
                                </label>
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="radio" name="cleanup_type" id="cleanup_type_3" value="delete_order_not_uid">
                                <label class="form-check-label" for="cleanup_type_3">
                                    <strong class="text-success"><?= __('Xóa đơn hàng và tài khoản, giữ lại UID'); ?></strong>
                                    <br><small class="text-muted"><?= __('Ẩn đơn hàng, xóa thông tin tài khoản nhưng giữ lại UID để tra cứu'); ?></small>
                                </label>
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="radio" name="cleanup_type" id="cleanup_type_4" value="delete_order">
                                <label class="form-check-label" for="cleanup_type_4">
                                    <strong class="text-warning"><?= __('Xóa đơn hàng, xóa toàn bộ tài khoản'); ?></strong>
                                    <br><small class="text-muted"><?= __('Ẩn đơn hàng và xóa tất cả tài khoản đã bán (bao gồm UID, Account...)'); ?></small>
                                </label>
                            </div>
                        </div>

                        <!-- Số ngày giữ lại -->
                        <div class="mb-4">
                            <label for="cleanup_days" class="form-label fw-semibold">
                                <?= __('Số ngày giữ lại'); ?> <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fa-solid fa-calendar-days"></i></span>
                                <input type="number" class="form-control" id="cleanup_days" name="days_to_keep"
                                    placeholder="<?= __('Nhập số ngày'); ?>" min="1" value="30" required>
                                <span class="input-group-text"><?= __('ngày'); ?></span>
                            </div>
                            <small class="text-muted">
                                <?= __('Ví dụ: Nhập 30 sẽ xóa tất cả đơn hàng từ 30 ngày trở lên, giữ lại đơn hàng trong 30 ngày gần đây'); ?>
                            </small>
                        </div>

                        <!-- Thống kê số đơn hàng sẽ bị ảnh hưởng -->
                        <div class="alert alert-info" id="cleanup_preview">
                            <i class="fa-solid fa-info-circle me-2"></i>
                            <span id="cleanup_preview_text"><?= __('Nhấn "Xem trước" để xem số đơn hàng sẽ bị ảnh hưởng'); ?></span>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-top-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="fa-solid fa-xmark me-1"></i> <?= __('Đóng'); ?>
                    </button>
                    <button type="button" class="btn btn-info" id="btnPreviewCleanup">
                        <i class="fa-solid fa-eye me-1"></i> <?= __('Xem trước'); ?>
                    </button>
                    <button type="button" class="btn btn-danger" id="btnConfirmCleanup" disabled>
                        <i class="fa-solid fa-trash me-1"></i> <?= __('Xác nhận dọn dẹp'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Hàm mở modal dọn dẹp đơn hàng
        function openCleanupOrdersModal() {
            // Reset form
            $('#cleanupOrdersForm')[0].reset();
            $('#cleanup_type_1').prop('checked', true);
            $('#cleanup_days').val(30);
            $('#cleanup_preview_text').text('<?= __("Nhấn \"Xem trước\" để xem số đơn hàng sẽ bị ảnh hưởng"); ?>');
            $('#btnConfirmCleanup').prop('disabled', true);

            // Hiển thị modal
            $('#cleanupOrdersModal').modal('show');
        }

        // Xem trước số đơn hàng sẽ bị ảnh hưởng
        $('#btnPreviewCleanup').on('click', function() {
            const days = parseInt($('#cleanup_days').val()) || 0;
            const cleanupType = $('input[name="cleanup_type"]:checked').val();

            if (days < 1) {
                showMessage('<?= __("Vui lòng nhập số ngày hợp lệ (tối thiểu 1 ngày)"); ?>', 'error');
                return;
            }

            const $btn = $(this);
            const originalHtml = $btn.html();
            $btn.html('<i class="fa-solid fa-spinner fa-spin me-1"></i> <?= __("Đang tính..."); ?>').prop('disabled', true);

            $.ajax({
                url: '<?= base_url("ajaxs/admin/view.php"); ?>',
                method: 'POST',
                dataType: 'JSON',
                data: {
                    action: 'previewCleanupOrders',
                    token: '<?= $getUser['token']; ?>',
                    days_to_keep: days,
                    cleanup_type: cleanupType
                },
                success: function(response) {
                    if (response.status === 'success') {
                        $('#cleanup_preview_text').html(
                            '<strong>' + response.count + '</strong> <?= __("đơn hàng sẽ bị ảnh hưởng"); ?>' +
                            (response.accounts_count ? ' (<strong>' + response.accounts_count + '</strong> <?= __("tài khoản"); ?>)' : '')
                        );
                        $('#btnConfirmCleanup').prop('disabled', false);
                    } else {
                        showMessage(response.msg, 'error');
                    }
                },
                error: function() {
                    showMessage('<?= __("Có lỗi xảy ra khi tính toán"); ?>', 'error');
                },
                complete: function() {
                    $btn.html(originalHtml).prop('disabled', false);
                }
            });
        });

        // Xác nhận dọn dẹp
        $('#btnConfirmCleanup').on('click', function() {
            const days = parseInt($('#cleanup_days').val()) || 0;
            const cleanupType = $('input[name="cleanup_type"]:checked').val();

            if (days < 1) {
                showMessage('<?= __("Vui lòng nhập số ngày hợp lệ"); ?>', 'error');
                return;
            }

            // Lấy text mô tả loại dọn dẹp
            let cleanupTypeText = '';
            switch (cleanupType) {
                case 'delete_order_revenue':
                    cleanupTypeText = '<?= __("Xóa toàn bộ đơn hàng và tài khoản"); ?>';
                    break;
                case 'delete_order_only':
                    cleanupTypeText = '<?= __("Xóa đơn hàng, không xóa tài khoản"); ?>';
                    break;
                case 'delete_order_not_uid':
                    cleanupTypeText = '<?= __("Xóa đơn hàng và tài khoản, giữ lại UID"); ?>';
                    break;
                case 'delete_order':
                    cleanupTypeText = '<?= __("Xóa đơn hàng, xóa toàn bộ tài khoản"); ?>';
                    break;
            }

            // Đóng modal Bootstrap trước khi hiển thị SweetAlert2 để tránh xung đột
            $('#cleanupOrdersModal').modal('hide');

            // Đợi modal đóng xong rồi mới hiển thị SweetAlert
            setTimeout(function() {
                Swal.fire({
                    title: '<?= __("Xác nhận dọn dẹp đơn hàng"); ?>',
                    html: `
                        <div class="text-start">
                            <div class="alert alert-danger mb-3">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong><?= __("Cảnh báo!"); ?></strong><br>
                                <?= __("Bạn sắp dọn dẹp đơn hàng với cấu hình:"); ?>
                                <ul class="mb-0 mt-2">
                                    <li><strong><?= __("Loại:"); ?></strong> ${cleanupTypeText}</li>
                                    <li><strong><?= __("Số ngày giữ lại:"); ?></strong> ${days} <?= __("ngày"); ?></li>
                                </ul>
                                <hr>
                                <small class="text-danger"><?= __("Hành động này không thể hoàn tác!"); ?></small>
                            </div>
                            <label for="confirmCleanupText" class="form-label">
                                <?= __("Để xác nhận, vui lòng nhập"); ?> <strong class="text-danger">CLEANUP</strong>
                            </label>
                            <input type="text" id="confirmCleanupText" class="form-control" placeholder="<?= __("Nhập: CLEANUP"); ?>" autocomplete="off">
                        </div>
                    `,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: '<?= __("Xác nhận dọn dẹp"); ?>',
                    cancelButtonText: '<?= __("Hủy"); ?>',
                    didOpen: () => {
                        document.getElementById('confirmCleanupText').focus();
                    },
                    preConfirm: () => {
                        const confirmText = document.getElementById('confirmCleanupText').value.trim();
                        if (confirmText !== 'CLEANUP') {
                            Swal.showValidationMessage('<?= __("Vui lòng nhập chính xác \"CLEANUP\" để xác nhận"); ?>');
                            return false;
                        }
                        return true;
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Hiển thị loading
                        Swal.fire({
                            title: '<?= __("Đang xử lý..."); ?>',
                            text: '<?= __("Vui lòng đợi, quá trình này có thể mất vài phút"); ?>',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });

                        $.ajax({
                            url: '<?= base_url("ajaxs/admin/remove.php"); ?>',
                            method: 'POST',
                            dataType: 'JSON',
                            data: {
                                action: 'cleanupOrders',
                                token: '<?= $getUser['token']; ?>',
                                days_to_keep: days,
                                cleanup_type: cleanupType
                            },
                            success: function(response) {
                                if (response.status === 'success') {
                                    Swal.fire({
                                        title: '<?= __("Thành công"); ?>',
                                        text: response.msg,
                                        icon: 'success',
                                        confirmButtonText: '<?= __("OK"); ?>'
                                    }).then(() => {
                                        location.reload();
                                    });
                                } else {
                                    Swal.fire({
                                        title: '<?= __("Lỗi"); ?>',
                                        text: response.msg,
                                        icon: 'error',
                                        confirmButtonText: '<?= __("OK"); ?>'
                                    });
                                }
                            },
                            error: function() {
                                Swal.fire({
                                    title: '<?= __("Lỗi"); ?>',
                                    text: '<?= __("Có lỗi xảy ra khi dọn dẹp đơn hàng"); ?>',
                                    icon: 'error',
                                    confirmButtonText: '<?= __("OK"); ?>'
                                });
                            }
                        });
                    }
                });
            }, 300);
        });

        // Khi thay đổi loại dọn dẹp hoặc số ngày, reset nút xác nhận
        $('input[name="cleanup_type"], #cleanup_days').on('change input', function() {
            $('#btnConfirmCleanup').prop('disabled', true);
            $('#cleanup_preview_text').text('<?= __("Nhấn \"Xem trước\" để xem số đơn hàng sẽ bị ảnh hưởng"); ?>');
        });

        // ======== Export Orders Functions ========
        // Khởi tạo Sortable cho danh sách cột export
        if (typeof Sortable !== 'undefined' && document.getElementById('exportColumnsList')) {
            new Sortable(document.getElementById('exportColumnsList'), {
                animation: 150,
                ghostClass: 'sortable-ghost',
                handle: '.fa-grip-vertical'
            });
        }
    </script>

    <script>
        // Hiển thị modal export
        function showExportModal() {
            var selectedIds = getSelectedOrderIds();
            if (selectedIds.length === 0) {
                showMessage('<?= __("Vui lòng chọn ít nhất một đơn hàng"); ?>', 'error');
                return;
            }
            var modalEl = document.getElementById('exportOrdersModal');
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                var modal = new bootstrap.Modal(modalEl);
                modal.show();
            } else {
                $(modalEl).addClass('show').css('display', 'block');
                $('body').addClass('modal-open').append('<div class="modal-backdrop fade show"></div>');
            }
        }

        // Chọn/bỏ chọn tất cả cột
        function toggleAllExportColumns(checked) {
            $('.export-col-checkbox').prop('checked', checked);
        }

        // Lấy danh sách ID đã chọn cho export
        function getSelectedOrderIds() {
            var selectedIds = [];
            $('.checkbox_product:checked').each(function() {
                selectedIds.push($(this).val());
            });
            return selectedIds;
        }

        // Xác nhận export đơn hàng
        function confirmExportOrders() {
            var selectedIds = getSelectedOrderIds();
            if (selectedIds.length === 0) {
                showMessage('<?= __("Vui lòng chọn ít nhất một đơn hàng"); ?>', 'error');
                return;
            }

            // Lấy loại file
            var fileType = $('#exportFileType').val();

            // Lấy danh sách cột được chọn theo thứ tự
            var columns = [];
            $('#exportColumnsList li').each(function() {
                var $checkbox = $(this).find('.export-col-checkbox');
                if ($checkbox.prop('checked')) {
                    columns.push($checkbox.val());
                }
            });

            if (columns.length === 0) {
                showMessage('<?= __("Vui lòng chọn ít nhất một cột để xuất"); ?>', 'error');
                return;
            }

            // Gọi AJAX để export
            $('#confirmExportBtn').prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-1"></i><?= __("Đang tải..."); ?>');

            $.ajax({
                url: "<?= BASE_URL('ajaxs/admin/view.php'); ?>",
                type: 'POST',
                dataType: 'JSON',
                data: {
                    action: 'exportProductOrders',
                    token: '<?= $getUser['token']; ?>',
                    ids: selectedIds,
                    file_type: fileType,
                    columns: columns
                },
                success: function(result) {
                    $('#confirmExportBtn').prop('disabled', false).html('<i class="fa-solid fa-download me-1"></i><?= __("Tải về"); ?>');

                    if (result.status == 'success') {
                        // Tạo file và download
                        var content = result.data.content;
                        var filename = result.data.filename;
                        var mimeType = fileType === 'csv' ? 'text/csv;charset=utf-8;' : 'text/plain;charset=utf-8;';

                        // Thêm BOM cho UTF-8
                        var bom = '\uFEFF';
                        var blob = new Blob([bom + content], {
                            type: mimeType
                        });
                        var link = document.createElement('a');
                        link.href = URL.createObjectURL(blob);
                        link.download = filename;
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                        URL.revokeObjectURL(link.href);

                        showMessage(result.msg, 'success');
                        bootstrap.Modal.getInstance(document.getElementById('exportOrdersModal')).hide();
                    } else {
                        showMessage(result.msg, 'error');
                    }
                },
                error: function() {
                    $('#confirmExportBtn').prop('disabled', false).html('<i class="fa-solid fa-download me-1"></i><?= __("Tải về"); ?>');
                    showMessage('<?= __("Đã xảy ra lỗi"); ?>', 'error');
                }
            });
        }
    </script>
<?php endif; ?>