<?php

define("IN_SITE", true);
require_once(__DIR__ . "/../../config.php");
require_once(__DIR__ . "/../../libs/db.php");
require_once(__DIR__ . "/../../libs/lang.php");
require_once(__DIR__ . "/../../libs/helper.php");
require_once(__DIR__ . '/../../models/is_admin.php');


if (!isset($_POST['action'])) {
    $data = json_encode([
        'status'    => 'error',
        'msg'       => __('The Request Not Found')
    ]);
    die($data);
}


if ($_POST['action'] == 'tinh_tien_refund') {
    if (checkPermission($getUser['admin'], 'refund_orders_product') != true) {
        die(json_encode(['status' => 'error', 'msg' => 'Bạn không có quyền sử dụng tính năng này']));
    }
    if (empty($_POST['id'])) {
        die(json_encode(['status' => 'error', 'msg' => 'ID đơn hàng không tồn tại']));
    }
    $id = check_string($_POST['id']);
    if (!$product_order = $CMSNT->get_row(" SELECT * FROM `product_order` WHERE `id` = '$id' ")) {
        die(json_encode(['status' => 'error', 'msg' => 'Đơn hàng không tồn tại trong hệ thống']));
    }
    // Lấy kiểu hoàn tiền (full/partial)
    $refundType = check_string($_POST['refundType']);
    if ($refundType == 'partial') {
        // Lấy số lượng cần hoàn (nếu có)
        $partialQuantity = isset($_POST['partialQuantity']) ? intval($_POST['partialQuantity']) : 0;
        if ($partialQuantity > $product_order['amount']) {
            die(json_encode(['status' => 'error', 'msg' => __('Số lượng tài khoản cần hoàn vượt quá số lượng tài khoản của đơn hàng này.')]));
        }
        $rate = $product_order['pay'] / $product_order['amount'];
        // Tổng số tiền Refund
        $amountRefund = $partialQuantity * $rate;
        die(json_encode(['status' => 'success', 'totalRefund' => format_currency($amountRefund)]));
    } else {
        die(json_encode(['status' => 'success', 'totalRefund' => format_currency($product_order['pay'])]));
    }
}
if ($_POST['action'] == 'download_product_die') {
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (!$getUser = $CMSNT->get_row("SELECT * FROM `users` WHERE `token` = '" . check_string($_POST['token']) . "' AND `banned` = 0 AND `admin` != 0 ")) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (checkPermission($getUser['admin'], 'edit_stock_product') != true) {
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }
    $accounts = '';
    $current_product_code = "";
    foreach ($CMSNT->get_list(" SELECT * FROM `product_die` ORDER BY product_code ") as $row) {

        if ($row['product_code'] != $current_product_code) {
            if ($current_product_code != "") {
                $accounts .= PHP_EOL;
            }
            $current_product_code = $row['product_code'];
            $accounts .= PHP_EOL . PHP_EOL . PHP_EOL . "============== " . $CMSNT->get_row(" SELECT * FROM `products` WHERE `code` = '$current_product_code' ")['name'] . " | Kho Hàng: " . $current_product_code . " ==============" . PHP_EOL;
        }
        $accounts .= $row['account'] . PHP_EOL;
    }
    $data = json_encode([
        'status'    => 'success',
        'filename'  => 'all_list_die_' . gettime(),
        'accounts'  => $accounts,
        'msg'       => __('Xuất dữ liệu thành công')
    ]);

    $CMSNT->insert("logs", [
        'user_id'       => $getUser['id'],
        'ip'            => myip(),
        'device'        => getUserAgent(),
        'createdate'    => gettime(),
        'action'        => __('Tải toàn bộ tài khoản DIE về máy')
    ]);
    die($data);
}

// Export đơn hàng sản phẩm
if ($_POST['action'] == 'exportProductOrders') {
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (!$getUser = $CMSNT->get_row("SELECT * FROM `users` WHERE `token` = '" . check_string($_POST['token']) . "' AND `banned` = 0 AND `admin` != 0 ")) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (checkPermission($getUser['admin'], 'view_orders_product') != true) {
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }

    // Validate input
    if (empty($_POST['ids']) || !is_array($_POST['ids'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng chọn ít nhất một đơn hàng')]));
    }

    if (empty($_POST['columns']) || !is_array($_POST['columns'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng chọn ít nhất một cột để xuất')]));
    }

    $file_type = isset($_POST['file_type']) && in_array($_POST['file_type'], ['txt', 'csv']) ? $_POST['file_type'] : 'txt';
    $separator = $file_type === 'csv' ? ',' : "\t";

    // Sanitize IDs
    $ids = array_filter(array_map('intval', $_POST['ids']));
    if (empty($ids)) {
        die(json_encode(['status' => 'error', 'msg' => __('ID đơn hàng không hợp lệ')]));
    }

    // Allowed columns mapping - adapted for SHOPCLONE7 product_order table
    $allowed_columns = [
        'trans_id' => ['field' => 'po.trans_id', 'label' => __('Mã đơn hàng')],
        'api_transid' => ['field' => 'po.api_transid', 'label' => __('Mã đơn API')],
        'username' => ['field' => 'u.username', 'label' => __('Username')],
        'product_name' => ['field' => 'po.product_name', 'label' => __('Sản phẩm')],
        'amount' => ['field' => 'po.amount', 'label' => __('Số lượng')],
        'pay' => ['field' => 'po.pay', 'label' => __('Thanh toán')],
        'cost' => ['field' => 'po.cost', 'label' => __('Giá vốn')],
        'create_gettime' => ['field' => 'po.create_gettime', 'label' => __('Ngày tạo')],
        'delivery_content' => ['field' => '', 'label' => __('Nội dung giao')] // Lấy từ product_sold
    ];

    // Filter and validate columns
    $selected_columns = [];
    foreach ($_POST['columns'] as $col) {
        if (isset($allowed_columns[$col])) {
            $selected_columns[$col] = $allowed_columns[$col];
        }
    }

    if (empty($selected_columns)) {
        die(json_encode(['status' => 'error', 'msg' => __('Không có cột hợp lệ để xuất')]));
    }

    // Build SELECT clause (exclude delivery_content as it's from another table)
    $select_fields = [];
    foreach ($selected_columns as $key => $col) {
        if ($key !== 'delivery_content' && !empty($col['field'])) {
            $select_fields[] = $col['field'] . ' AS `' . $key . '`';
        }
    }

    // Always get trans_id for joining with product_sold if delivery_content is selected
    $need_delivery = isset($selected_columns['delivery_content']);
    if ($need_delivery && !isset($selected_columns['trans_id'])) {
        array_unshift($select_fields, 'po.trans_id AS `_trans_id`');
    }

    // Build query with placeholders
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $query = "SELECT " . implode(', ', $select_fields) . "
              FROM `product_order` po
              LEFT JOIN `users` u ON po.buyer = u.id
              WHERE po.id IN ($placeholders)
              ORDER BY po.id DESC";

    $orders = $CMSNT->get_list_safe($query, $ids);

    if (empty($orders)) {
        die(json_encode(['status' => 'error', 'msg' => __('Không tìm thấy đơn hàng')]));
    }

    // Get delivery content from product_sold if needed
    $delivery_contents = [];
    if ($need_delivery) {
        foreach ($orders as $order) {
            $trans_id = $order['trans_id'] ?? $order['_trans_id'] ?? '';
            if ($trans_id) {
                $sold_items = $CMSNT->get_list_safe(
                    "SELECT `uid`, `account` FROM `product_sold` WHERE `trans_id` = ?",
                    [$trans_id]
                );
                $content_parts = [];
                foreach ($sold_items as $item) {
                    $content_parts[] = ($item['uid'] ? $item['uid'] . '|' : '') . $item['account'];
                }
                $delivery_contents[$trans_id] = implode(' || ', $content_parts);
            }
        }
    }

    // Build content
    $lines = [];

    // Header row
    $headers = [];
    foreach ($selected_columns as $col) {
        $label = $col['label'];
        if ($file_type === 'csv') {
            $label = '"' . str_replace('"', '""', $label) . '"';
        }
        $headers[] = $label;
    }
    $lines[] = implode($separator, $headers);

    // Data rows
    foreach ($orders as $order) {
        $row = [];
        foreach ($selected_columns as $key => $col) {
            if ($key === 'delivery_content') {
                $trans_id = $order['trans_id'] ?? $order['_trans_id'] ?? '';
                $value = $delivery_contents[$trans_id] ?? '';
            } else {
                $value = $order[$key] ?? '';
            }

            // Format specific fields
            if (in_array($key, ['pay', 'cost'])) {
                $value = number_format((float)$value, 0, ',', '.');
            }

            if ($file_type === 'csv') {
                $value = '"' . str_replace('"', '""', $value) . '"';
            }
            $row[] = $value;
        }
        $lines[] = implode($separator, $row);
    }

    $content = implode("\n", $lines);
    $filename = 'orders_export_' . date('Y-m-d_His') . '.' . $file_type;

    // Ghi log xuất đơn hàng
    $log_content = sprintf(
        'Xuất %d đơn hàng (IDs: %s) - File: %s - Các cột: %s',
        count($orders),
        implode(', ', array_slice($ids, 0, 10)) . (count($ids) > 10 ? '...' : ''),
        $filename,
        implode(', ', array_keys($selected_columns))
    );
    $CMSNT->insert('logs', [
        'user_id' => $getUser['id'],
        'ip' => myip(),
        'device' => getUserAgent(),
        'createdate' => gettime(),
        'action' => $log_content
    ]);

    die(json_encode([
        'status' => 'success',
        'msg' => sprintf(__('Đã xuất %d đơn hàng'), count($orders)),
        'data' => [
            'content' => $content,
            'filename' => $filename
        ]
    ]));
}


// ======== Export Products ========
if ($_POST['action'] == 'exportProducts') {
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (!$getUser = $CMSNT->get_row("SELECT * FROM `users` WHERE `token` = '" . check_string($_POST['token']) . "' AND `banned` = 0 AND `admin` != 0 ")) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (checkPermission($getUser['admin'], 'view_product') != true) {
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }

    // Validate input
    if (empty($_POST['ids']) || !is_array($_POST['ids'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng chọn ít nhất một sản phẩm')]));
    }

    if (empty($_POST['columns']) || !is_array($_POST['columns'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng chọn ít nhất một cột để xuất')]));
    }

    $file_type = isset($_POST['file_type']) && in_array($_POST['file_type'], ['txt', 'csv']) ? $_POST['file_type'] : 'txt';
    $separator = $file_type === 'csv' ? ',' : "\t";

    // Sanitize IDs
    $ids = array_filter(array_map('intval', $_POST['ids']));
    if (empty($ids)) {
        die(json_encode(['status' => 'error', 'msg' => __('ID sản phẩm không hợp lệ')]));
    }

    // Allowed columns mapping for products table
    $allowed_columns = [
        'name'           => ['field' => 'p.name', 'label' => __('Tên sản phẩm')],
        'code'           => ['field' => 'p.code', 'label' => __('Mã kho hàng')],
        'price'          => ['field' => 'p.price', 'label' => __('Giá bán')],
        'cost'           => ['field' => 'p.cost', 'label' => __('Giá vốn')],
        'category'       => ['field' => 'c.name', 'label' => __('Chuyên mục')],
        'stock_live'     => ['field' => '', 'label' => __('Tồn kho Live')], // Computed
        'sold'           => ['field' => 'p.sold', 'label' => __('Đã bán')],
        'status'         => ['field' => 'p.status', 'label' => __('Trạng thái')],
        'seller'         => ['field' => 'u.username', 'label' => __('Seller')],
        'create_gettime' => ['field' => 'p.create_gettime', 'label' => __('Ngày tạo')],
        'stock_data'     => ['field' => '', 'label' => __('Dữ liệu kho')], // Computed from product_stock - account only, 1 per line
    ];

    // Filter and validate columns
    $selected_columns = [];
    foreach ($_POST['columns'] as $col) {
        if (isset($allowed_columns[$col])) {
            $selected_columns[$col] = $allowed_columns[$col];
        }
    }

    if (empty($selected_columns)) {
        die(json_encode(['status' => 'error', 'msg' => __('Không có cột hợp lệ để xuất')]));
    }

    // Build SELECT clause
    $select_fields = ['p.id', 'p.supplier_id', 'p.code AS _code', 'p.api_stock'];
    foreach ($selected_columns as $key => $col) {
        if ($key === 'stock_live') continue; // Computed later
        if (!empty($col['field'])) {
            $alias = $key;
            if ($key === 'category') $alias = 'category_name';
            if ($key === 'seller') $alias = 'seller_name';
            $select_fields[] = $col['field'] . ' AS `' . $alias . '`';
        }
    }

    // Build query with placeholders
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $query = "SELECT " . implode(', ', $select_fields) . "
              FROM `products` p
              LEFT JOIN `categories` c ON p.category_id = c.id
              LEFT JOIN `users` u ON p.user_id = u.id
              WHERE p.id IN ($placeholders)
              ORDER BY p.id DESC";

    $products = $CMSNT->get_list_safe($query, $ids);

    if (empty($products)) {
        die(json_encode(['status' => 'error', 'msg' => __('Không tìm thấy sản phẩm')]));
    }

    // Build content
    $lines = [];

    // Header row
    $headers = [];
    foreach ($selected_columns as $col) {
        $label = $col['label'];
        if ($file_type === 'csv') {
            $label = '"' . str_replace('"', '""', $label) . '"';
        }
        $headers[] = $label;
    }
    $lines[] = implode($separator, $headers);

    // Check if stock_data column is selected
    $has_stock_data = isset($selected_columns['stock_data']);
    $exported_count = 0;

    // Tính memory limit để kiểm tra khi xuất dữ liệu lớn
    $memory_limit_str = ini_get('memory_limit');
    $memory_limit_bytes = -1;
    if ($memory_limit_str !== '-1' && $memory_limit_str !== '0') {
        $val = (int) $memory_limit_str;
        $unit = strtolower(substr(trim($memory_limit_str), -1));
        switch ($unit) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }
        $memory_limit_bytes = $val;
    }
    // Ngưỡng cảnh báo: 80% memory limit
    $memory_threshold = ($memory_limit_bytes > 0) ? (int)($memory_limit_bytes * 0.8) : 0;

    // Data rows
    foreach ($products as $product) {
        // Kiểm tra bộ nhớ trước mỗi sản phẩm
        if ($memory_threshold > 0 && memory_get_usage() > $memory_threshold) {
            die(json_encode([
                'status' => 'error',
                'msg' => sprintf(
                    __('Dữ liệu quá lớn, bộ nhớ sắp đầy (đã xuất %d dòng). Vui lòng chọn ít sản phẩm hơn hoặc bỏ cột "Dữ liệu kho" để xuất.'),
                    $exported_count
                )
            ]));
        }
        // Nếu có cột stock_data, lấy dữ liệu kho trước
        $stock_accounts = [];
        if ($has_stock_data) {
            $stock_items = $CMSNT->get_list("SELECT `account` FROM `product_stock` WHERE `product_code` = '" . check_string($product['_code']) . "' ORDER BY `id` DESC");
            if ($stock_items) {
                foreach ($stock_items as $item) {
                    if (!empty(trim($item['account']))) {
                        $stock_accounts[] = trim($item['account']);
                    }
                }
            }
            // Bỏ qua sản phẩm không có kho hàng
            if (empty($stock_accounts)) {
                continue;
            }
        }

        // Build base row values (non-stock columns)
        $base_row = [];
        foreach ($selected_columns as $key => $col) {
            if ($key === 'stock_data') {
                $base_row[$key] = ''; // placeholder, will be filled per stock entry
                continue;
            }
            $value = '';
            switch ($key) {
                case 'stock_live':
                    if ($product['supplier_id'] == 0) {
                        $value = getStock($product['_code']);
                    } else {
                        $value = $product['api_stock'] ?? 0;
                    }
                    break;
                case 'category':
                    $value = $product['category_name'] ?? '';
                    break;
                case 'seller':
                    $value = $product['seller_name'] ?? '';
                    break;
                case 'status':
                    $value = $product['status'] == 1 ? __('Hiển thị') : __('Ẩn');
                    break;
                default:
                    $value = $product[$key] ?? '';
                    break;
            }

            // Format specific fields
            if (in_array($key, ['price', 'cost'])) {
                $value = number_format((float)$value, 0, ',', '.');
            }

            if ($file_type === 'csv') {
                $value = '"' . str_replace('"', '""', (string)$value) . '"';
            }
            $base_row[$key] = $value;
        }

        if ($has_stock_data) {
            // Mỗi account 1 dòng
            foreach ($stock_accounts as $account) {
                $row = [];
                foreach ($selected_columns as $key => $col) {
                    if ($key === 'stock_data') {
                        $val = $account;
                        if ($file_type === 'csv') {
                            $val = '"' . str_replace('"', '""', $val) . '"';
                        }
                        $row[] = $val;
                    } else {
                        $row[] = $base_row[$key];
                    }
                }
                $lines[] = implode($separator, $row);
                $exported_count++;
            }
        } else {
            // Không có cột stock_data - xuất 1 dòng bình thường
            $row = array_values($base_row);
            $lines[] = implode($separator, $row);
            $exported_count++;
        }
    }

    $content = implode("\n", $lines);
    $filename = 'products_export_' . date('Y-m-d_His') . '.' . $file_type;

    // Ghi log xuất sản phẩm
    $log_content = sprintf(
        'Xuất %d sản phẩm (IDs: %s) - File: %s - Các cột: %s',
        count($products),
        implode(', ', array_slice($ids, 0, 10)) . (count($ids) > 10 ? '...' : ''),
        $filename,
        implode(', ', array_keys($selected_columns))
    );
    $CMSNT->insert('logs', [
        'user_id' => $getUser['id'],
        'ip' => myip(),
        'device' => getUserAgent(),
        'createdate' => gettime(),
        'action' => $log_content
    ]);

    die(json_encode([
        'status' => 'success',
        'msg' => sprintf(__('Đã xuất %d sản phẩm'), count($products)),
        'data' => [
            'content' => $content,
            'filename' => $filename
        ]
    ]));
}


if ($_POST['action'] == 'view_chart_thong_ke_don_hang') {
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (!$getUser = $CMSNT->get_row("SELECT * FROM `users` WHERE `token` = '" . check_string($_POST['token']) . "' AND `banned` = 0 AND `admin` != 0 ")) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (checkPermission($getUser['admin'], 'view_statistical') != true) {
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }

    $time_range = check_string($_POST['time_range']);
    $labels = [];
    $revenues = [];
    $profits = [];

    if ($time_range == 'today') {
        // Thống kê theo giờ trong ngày hôm nay
        $today = date("Y-m-d");
        for ($hour = 0; $hour < 24; $hour++) {
            $hour_start = sprintf("%02d:00:00", $hour);
            $hour_end = sprintf("%02d:59:59", $hour);
            $query = "SELECT SUM(pay) AS total_pay, SUM(cost) AS total_cost FROM product_order 
                      WHERE `refund` = 0 AND DATE(create_gettime) = '$today' 
                      AND TIME(create_gettime) >= '$hour_start' AND TIME(create_gettime) <= '$hour_end'";
            $result = $CMSNT->get_row($query);

            $labels[] = sprintf("%02d:00", $hour);
            $revenues[] = $result['total_pay'] ?? 0;
            $profits[] = ($result['total_pay'] ?? 0) - ($result['total_cost'] ?? 0);
        }
    } else if ($time_range == 'week') {
        // Thống kê 7 ngày gần đây
        for ($i = 6; $i >= 0; $i--) {
            $date = date("Y-m-d", strtotime("-$i days"));
            $query = "SELECT SUM(pay) AS total_pay, SUM(cost) AS total_cost FROM product_order WHERE `refund` = 0 AND DATE(create_gettime) = '$date'";
            $result = $CMSNT->get_row($query);

            $labels[] = date("d/m", strtotime("-$i days"));
            $revenues[] = $result['total_pay'] ?? 0;
            $profits[] = ($result['total_pay'] ?? 0) - ($result['total_cost'] ?? 0);
        }
    } else if ($time_range == 'month') {
        // Thống kê theo tháng hiện tại
        $month = date('m');
        $year = date('Y');
        $numOfDays = cal_days_in_month(CAL_GREGORIAN, $month, $year);

        for ($day = 1; $day <= $numOfDays; $day++) {
            $date = "$year-$month-$day";
            $query = "SELECT SUM(pay) AS total_pay, SUM(cost) AS total_cost FROM product_order WHERE `refund` = 0 AND DATE(create_gettime) = '$date'";
            $result = $CMSNT->get_row($query);

            $labels[] = "$day/$month";
            $revenues[] = $result['total_pay'] ?? 0;
            $profits[] = ($result['total_pay'] ?? 0) - ($result['total_cost'] ?? 0);
        }
    } else if ($time_range == 'last_month') {
        // Thống kê theo tháng trước
        $lastMonth = date('m', strtotime('-1 month'));
        $lastMonthYear = date('Y', strtotime('-1 month'));
        $numOfDays = cal_days_in_month(CAL_GREGORIAN, $lastMonth, $lastMonthYear);

        for ($day = 1; $day <= $numOfDays; $day++) {
            $date = sprintf("%s-%02d-%02d", $lastMonthYear, $lastMonth, $day);
            $query = "SELECT SUM(pay) AS total_pay, SUM(cost) AS total_cost FROM product_order WHERE `refund` = 0 AND DATE(create_gettime) = '$date'";
            $result = $CMSNT->get_row($query);

            $labels[] = "$day/$lastMonth";
            $revenues[] = $result['total_pay'] ?? 0;
            $profits[] = ($result['total_pay'] ?? 0) - ($result['total_cost'] ?? 0);
        }
    } else if ($time_range == 'year') {
        // Thống kê theo năm hiện tại
        $year = date('Y');

        for ($month = 1; $month <= 12; $month++) {
            $month_name = date('m', mktime(0, 0, 0, $month, 1));
            $query = "SELECT SUM(pay) AS total_pay, SUM(cost) AS total_cost FROM product_order 
                      WHERE `refund` = 0 AND MONTH(create_gettime) = '$month' AND YEAR(create_gettime) = '$year'";
            $result = $CMSNT->get_row($query);

            $labels[] = "Tháng $month_name";
            $revenues[] = $result['total_pay'] ?? 0;
            $profits[] = ($result['total_pay'] ?? 0) - ($result['total_cost'] ?? 0);
        }
    }

    die(json_encode([
        'labels' => $labels,
        'revenues' => $revenues,
        'profits' => $profits
    ]));
}


if ($_POST['action'] == 'view_chart_thong_ke_nap_tien') {
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (!$getUser = $CMSNT->get_row("SELECT * FROM `users` WHERE `token` = '" . check_string($_POST['token']) . "' AND `banned` = 0 AND `admin` != 0 ")) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (checkPermission($getUser['admin'], 'view_statistical') != true) {
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }

    $time_range = check_string($_POST['time_range']);
    $labels = [];
    $amount = [];

    if ($time_range == 'today') {
        // Thống kê theo giờ trong ngày hôm nay
        $today = date("Y-m-d");
        for ($hour = 0; $hour < 24; $hour++) {
            $hour_start = sprintf("%02d:00:00", $hour);
            $hour_end = sprintf("%02d:59:59", $hour);

            $total_topup_bank = $CMSNT->get_row("SELECT SUM(amount) AS total FROM payment_bank WHERE DATE(create_gettime) = '$today' AND TIME(create_gettime) >= '$hour_start' AND TIME(create_gettime) <= '$hour_end'")['total'] ?? 0;
            $total_topup_card = $CMSNT->get_row("SELECT SUM(amount) AS total FROM cards WHERE `status` = 'completed' AND DATE(create_date) = '$today' AND TIME(create_date) >= '$hour_start' AND TIME(create_date) <= '$hour_end'")['total'] ?? 0;
            $total_topup_crypto = $CMSNT->get_row("SELECT SUM(received) AS total FROM payment_crypto WHERE `status` = 'completed' AND DATE(create_gettime) = '$today' AND TIME(create_gettime) >= '$hour_start' AND TIME(create_gettime) <= '$hour_end'")['total'] ?? 0;
            $total_topup_momo = $CMSNT->get_row("SELECT SUM(amount) AS total FROM payment_momo WHERE DATE(create_gettime) = '$today' AND TIME(create_gettime) >= '$hour_start' AND TIME(create_gettime) <= '$hour_end'")['total'] ?? 0;
            $total_topup_paypal = $CMSNT->get_row("SELECT SUM(price) AS total FROM payment_paypal WHERE DATE(create_date) = '$today' AND TIME(create_date) >= '$hour_start' AND TIME(create_date) <= '$hour_end'")['total'] ?? 0;
            $total_topup_pm = $CMSNT->get_row("SELECT SUM(price) AS total FROM payment_pm WHERE `status` = 1 AND DATE(create_date) = '$today' AND TIME(create_date) >= '$hour_start' AND TIME(create_date) <= '$hour_end'")['total'] ?? 0;
            $total_topup_squadco = $CMSNT->get_row("SELECT SUM(amount) AS total FROM payment_squadco WHERE DATE(create_gettime) = '$today' AND TIME(create_gettime) >= '$hour_start' AND TIME(create_gettime) <= '$hour_end'")['total'] ?? 0;
            $total_topup_toyyibpay = $CMSNT->get_row("SELECT SUM(amount) AS total FROM payment_toyyibpay WHERE `status` = 1 AND DATE(create_gettime) = '$today' AND TIME(create_gettime) >= '$hour_start' AND TIME(create_gettime) <= '$hour_end'")['total'] ?? 0;
            $total_topup_xipay = $CMSNT->get_row("SELECT SUM(price) AS total FROM payment_xipay WHERE `status` = 1 AND DATE(created_at) = '$today' AND TIME(created_at) >= '$hour_start' AND TIME(created_at) <= '$hour_end'")['total'] ?? 0;
            $total_topup_korapay = $CMSNT->get_row("SELECT SUM(price) AS total FROM payment_korapay WHERE `status` = 1 AND DATE(created_at) = '$today' AND TIME(created_at) >= '$hour_start' AND TIME(created_at) <= '$hour_end'")['total'] ?? 0;
            $total_topup_tmweasyapi = $CMSNT->get_row("SELECT SUM(price) AS total FROM payment_tmweasyapi WHERE `status` = 1 AND DATE(created_at) = '$today' AND TIME(created_at) >= '$hour_start' AND TIME(created_at) <= '$hour_end'")['total'] ?? 0;
            $total_topup_openpix = $CMSNT->get_row("SELECT SUM(price) AS total FROM payment_openpix WHERE `status` = 1 AND DATE(created_at) = '$today' AND TIME(created_at) >= '$hour_start' AND TIME(created_at) <= '$hour_end'")['total'] ?? 0;
            $total_topup = $total_topup_bank + $total_topup_card + $total_topup_crypto + $total_topup_momo + $total_topup_paypal + $total_topup_pm + $total_topup_squadco + $total_topup_toyyibpay + $total_topup_xipay + $total_topup_korapay + $total_topup_tmweasyapi + $total_topup_openpix;

            $labels[] = sprintf("%02d:00", $hour);
            $amount[] = $total_topup;
        }
    } else if ($time_range == 'week') {
        // Thống kê 7 ngày gần đây
        for ($i = 6; $i >= 0; $i--) {
            $date = date("Y-m-d", strtotime("-$i days"));

            $total_topup_bank = $CMSNT->get_row("SELECT SUM(amount) AS total FROM payment_bank WHERE DATE(create_gettime) = '$date'")['total'] ?? 0;
            $total_topup_card = $CMSNT->get_row("SELECT SUM(amount) AS total FROM cards WHERE `status` = 'completed' AND DATE(create_date) = '$date'")['total'] ?? 0;
            $total_topup_crypto = $CMSNT->get_row("SELECT SUM(received) AS total FROM payment_crypto WHERE `status` = 'completed' AND DATE(create_gettime) = '$date'")['total'] ?? 0;
            $total_topup_momo = $CMSNT->get_row("SELECT SUM(amount) AS total FROM payment_momo WHERE DATE(create_gettime) = '$date'")['total'] ?? 0;
            $total_topup_paypal = $CMSNT->get_row("SELECT SUM(price) AS total FROM payment_paypal WHERE DATE(create_date) = '$date'")['total'] ?? 0;
            $total_topup_pm = $CMSNT->get_row("SELECT SUM(price) AS total FROM payment_pm WHERE `status` = 1 AND DATE(create_date) = '$date'")['total'] ?? 0;
            $total_topup_squadco = $CMSNT->get_row("SELECT SUM(amount) AS total FROM payment_squadco WHERE DATE(create_gettime) = '$date'")['total'] ?? 0;
            $total_topup_toyyibpay = $CMSNT->get_row("SELECT SUM(amount) AS total FROM payment_toyyibpay WHERE `status` = 1 AND DATE(create_gettime) = '$date'")['total'] ?? 0;
            $total_topup_xipay = $CMSNT->get_row("SELECT SUM(price) AS total FROM payment_xipay WHERE `status` = 1 AND DATE(created_at) = '$date'")['total'] ?? 0;
            $total_topup_korapay = $CMSNT->get_row("SELECT SUM(price) AS total FROM payment_korapay WHERE `status` = 1 AND DATE(created_at) = '$date'")['total'] ?? 0;
            $total_topup_tmweasyapi = $CMSNT->get_row("SELECT SUM(price) AS total FROM payment_tmweasyapi WHERE `status` = 1 AND DATE(created_at) = '$date'")['total'] ?? 0;
            $total_topup = $total_topup_bank + $total_topup_card + $total_topup_crypto + $total_topup_momo + $total_topup_paypal + $total_topup_pm + $total_topup_squadco + $total_topup_toyyibpay + $total_topup_xipay + $total_topup_korapay + $total_topup_tmweasyapi;

            $labels[] = date("d/m", strtotime("-$i days"));
            $amount[] = $total_topup;
        }
    } else if ($time_range == 'month') {
        // Thống kê theo tháng hiện tại
        $month = date('m');
        $year = date('Y');
        $numOfDays = cal_days_in_month(CAL_GREGORIAN, $month, $year);

        for ($day = 1; $day <= $numOfDays; $day++) {
            $date = "$year-$month-$day";

            $total_topup_bank = $CMSNT->get_row("SELECT SUM(amount) AS total FROM payment_bank WHERE DATE(create_gettime) = '$date'")['total'] ?? 0;
            $total_topup_card = $CMSNT->get_row("SELECT SUM(amount) AS total FROM cards WHERE `status` = 'completed' AND DATE(create_date) = '$date'")['total'] ?? 0;
            $total_topup_crypto = $CMSNT->get_row("SELECT SUM(received) AS total FROM payment_crypto WHERE `status` = 'completed' AND DATE(create_gettime) = '$date'")['total'] ?? 0;
            $total_topup_momo = $CMSNT->get_row("SELECT SUM(amount) AS total FROM payment_momo WHERE DATE(create_gettime) = '$date'")['total'] ?? 0;
            $total_topup_paypal = $CMSNT->get_row("SELECT SUM(price) AS total FROM payment_paypal WHERE DATE(create_date) = '$date'")['total'] ?? 0;
            $total_topup_pm = $CMSNT->get_row("SELECT SUM(price) AS total FROM payment_pm WHERE `status` = 1 AND DATE(create_date) = '$date'")['total'] ?? 0;
            $total_topup_squadco = $CMSNT->get_row("SELECT SUM(amount) AS total FROM payment_squadco WHERE DATE(create_gettime) = '$date'")['total'] ?? 0;
            $total_topup_toyyibpay = $CMSNT->get_row("SELECT SUM(amount) AS total FROM payment_toyyibpay WHERE `status` = 1 AND DATE(create_gettime) = '$date'")['total'] ?? 0;
            $total_topup_xipay = $CMSNT->get_row("SELECT SUM(price) AS total FROM payment_xipay WHERE `status` = 1 AND DATE(created_at) = '$date'")['total'] ?? 0;
            $total_topup_korapay = $CMSNT->get_row("SELECT SUM(price) AS total FROM payment_korapay WHERE `status` = 1 AND DATE(created_at) = '$date'")['total'] ?? 0;
            $total_topup_tmweasyapi = $CMSNT->get_row("SELECT SUM(price) AS total FROM payment_tmweasyapi WHERE `status` = 1 AND DATE(created_at) = '$date'")['total'] ?? 0;
            $total_topup_openpix = $CMSNT->get_row("SELECT SUM(price) AS total FROM payment_openpix WHERE `status` = 1 AND DATE(created_at) = '$date'")['total'] ?? 0;
            $total_topup = $total_topup_bank + $total_topup_card + $total_topup_crypto + $total_topup_momo + $total_topup_paypal + $total_topup_pm + $total_topup_squadco + $total_topup_toyyibpay + $total_topup_xipay + $total_topup_korapay + $total_topup_tmweasyapi + $total_topup_openpix;

            $labels[] = "$day/$month";
            $amount[] = $total_topup;
        }
    } else if ($time_range == 'last_month') {
        // Thống kê theo tháng trước
        $lastMonth = date('m', strtotime('-1 month'));
        $lastMonthYear = date('Y', strtotime('-1 month'));
        $numOfDays = cal_days_in_month(CAL_GREGORIAN, $lastMonth, $lastMonthYear);

        for ($day = 1; $day <= $numOfDays; $day++) {
            $date = sprintf("%s-%02d-%02d", $lastMonthYear, $lastMonth, $day);

            $total_topup_bank = $CMSNT->get_row("SELECT SUM(amount) AS total FROM payment_bank WHERE DATE(create_gettime) = '$date'")['total'] ?? 0;
            $total_topup_card = $CMSNT->get_row("SELECT SUM(amount) AS total FROM cards WHERE `status` = 'completed' AND DATE(create_date) = '$date'")['total'] ?? 0;
            $total_topup_crypto = $CMSNT->get_row("SELECT SUM(received) AS total FROM payment_crypto WHERE `status` = 'completed' AND DATE(create_gettime) = '$date'")['total'] ?? 0;
            $total_topup_momo = $CMSNT->get_row("SELECT SUM(amount) AS total FROM payment_momo WHERE DATE(create_gettime) = '$date'")['total'] ?? 0;
            $total_topup_paypal = $CMSNT->get_row("SELECT SUM(price) AS total FROM payment_paypal WHERE DATE(create_date) = '$date'")['total'] ?? 0;
            $total_topup_pm = $CMSNT->get_row("SELECT SUM(price) AS total FROM payment_pm WHERE `status` = 1 AND DATE(create_date) = '$date'")['total'] ?? 0;
            $total_topup_squadco = $CMSNT->get_row("SELECT SUM(amount) AS total FROM payment_squadco WHERE DATE(create_gettime) = '$date'")['total'] ?? 0;
            $total_topup_toyyibpay = $CMSNT->get_row("SELECT SUM(amount) AS total FROM payment_toyyibpay WHERE `status` = 1 AND DATE(create_gettime) = '$date'")['total'] ?? 0;
            $total_topup_xipay = $CMSNT->get_row("SELECT SUM(price) AS total FROM payment_xipay WHERE `status` = 1 AND DATE(created_at) = '$date'")['total'] ?? 0;
            $total_topup_korapay = $CMSNT->get_row("SELECT SUM(price) AS total FROM payment_korapay WHERE `status` = 1 AND DATE(created_at) = '$date'")['total'] ?? 0;
            $total_topup_tmweasyapi = $CMSNT->get_row("SELECT SUM(price) AS total FROM payment_tmweasyapi WHERE `status` = 1 AND DATE(created_at) = '$date'")['total'] ?? 0;
            $total_topup_openpix = $CMSNT->get_row("SELECT SUM(price) AS total FROM payment_openpix WHERE `status` = 1 AND DATE(created_at) = '$date'")['total'] ?? 0;
            $total_topup = $total_topup_bank + $total_topup_card + $total_topup_crypto + $total_topup_momo + $total_topup_paypal + $total_topup_pm + $total_topup_squadco + $total_topup_toyyibpay + $total_topup_xipay + $total_topup_korapay + $total_topup_tmweasyapi + $total_topup_openpix;

            $labels[] = "$day/$lastMonth";
            $amount[] = $total_topup;
        }
    } else if ($time_range == 'year') {
        // Thống kê theo năm hiện tại
        $year = date('Y');

        for ($month = 1; $month <= 12; $month++) {
            $month_name = date('m', mktime(0, 0, 0, $month, 1));

            $start_date = "$year-$month-01";
            $last_day = cal_days_in_month(CAL_GREGORIAN, $month, $year);
            $end_date = "$year-$month-$last_day";

            $total_topup_bank = $CMSNT->get_row("SELECT SUM(amount) AS total FROM payment_bank WHERE DATE(create_gettime) BETWEEN '$start_date' AND '$end_date'")['total'] ?? 0;
            $total_topup_card = $CMSNT->get_row("SELECT SUM(amount) AS total FROM cards WHERE `status` = 'completed' AND DATE(create_date) BETWEEN '$start_date' AND '$end_date'")['total'] ?? 0;
            $total_topup_crypto = $CMSNT->get_row("SELECT SUM(received) AS total FROM payment_crypto WHERE `status` = 'completed' AND DATE(create_gettime) BETWEEN '$start_date' AND '$end_date'")['total'] ?? 0;
            $total_topup_momo = $CMSNT->get_row("SELECT SUM(amount) AS total FROM payment_momo WHERE DATE(create_gettime) BETWEEN '$start_date' AND '$end_date'")['total'] ?? 0;
            $total_topup_paypal = $CMSNT->get_row("SELECT SUM(price) AS total FROM payment_paypal WHERE DATE(create_date) BETWEEN '$start_date' AND '$end_date'")['total'] ?? 0;
            $total_topup_pm = $CMSNT->get_row("SELECT SUM(price) AS total FROM payment_pm WHERE `status` = 1 AND DATE(create_date) BETWEEN '$start_date' AND '$end_date'")['total'] ?? 0;
            $total_topup_squadco = $CMSNT->get_row("SELECT SUM(amount) AS total FROM payment_squadco WHERE DATE(create_gettime) BETWEEN '$start_date' AND '$end_date'")['total'] ?? 0;
            $total_topup_toyyibpay = $CMSNT->get_row("SELECT SUM(amount) AS total FROM payment_toyyibpay WHERE `status` = 1 AND DATE(create_gettime) BETWEEN '$start_date' AND '$end_date'")['total'] ?? 0;
            $total_topup_xipay = $CMSNT->get_row("SELECT SUM(price) AS total FROM payment_xipay WHERE `status` = 1 AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'")['total'] ?? 0;
            $total_topup_korapay = $CMSNT->get_row("SELECT SUM(price) AS total FROM payment_korapay WHERE `status` = 1 AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'")['total'] ?? 0;
            $total_topup_tmweasyapi = $CMSNT->get_row("SELECT SUM(price) AS total FROM payment_tmweasyapi WHERE `status` = 1 AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'")['total'] ?? 0;
            $total_topup_openpix = $CMSNT->get_row("SELECT SUM(price) AS total FROM payment_openpix WHERE `status` = 1 AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'")['total'] ?? 0;
            $total_topup = $total_topup_bank + $total_topup_card + $total_topup_crypto + $total_topup_momo + $total_topup_paypal + $total_topup_pm + $total_topup_squadco + $total_topup_toyyibpay + $total_topup_xipay + $total_topup_korapay + $total_topup_tmweasyapi + $total_topup_openpix;

            $labels[] = "Tháng $month_name";
            $amount[] = $total_topup;
        }
    }

    die(json_encode([
        'labels' => $labels,
        'amount' => $amount
    ]));
}

if ($_POST['action'] == 'view_chart_thong_ke_nap_tien_thang') {
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (!$getUser = $CMSNT->get_row("SELECT * FROM `users` WHERE `token` = '" . check_string($_POST['token']) . "' AND `banned` = 0 AND `admin` != 0 ")) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (checkPermission($getUser['admin'], 'view_statistical') != true) {
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }
    $month = date('m');
    $year = date('Y');
    $numOfDays = cal_days_in_month(CAL_GREGORIAN, $month, $year);

    $labels = [];
    $data = [];

    for ($day = 1; $day <= $numOfDays; $day++) {
        $date = "$year-$month-$day";

        $total_topup_bank = $CMSNT->get_row("SELECT SUM(amount) AS total FROM payment_bank WHERE DATE(create_gettime) = '$date'")['total'] ?? 0;
        $total_topup_card = $CMSNT->get_row("SELECT SUM(amount) AS total FROM cards WHERE `status` = 'completed' AND DATE(create_date) = '$date'")['total'] ?? 0;
        $total_topup_crypto = $CMSNT->get_row("SELECT SUM(received) AS total FROM payment_crypto WHERE `status` = 'completed' AND DATE(create_gettime) = '$date'")['total'] ?? 0;
        $total_topup_momo = $CMSNT->get_row("SELECT SUM(amount) AS total FROM payment_momo WHERE DATE(create_gettime) = '$date'")['total'] ?? 0;
        $total_topup_paypal = $CMSNT->get_row("SELECT SUM(price) AS total FROM payment_paypal WHERE DATE(create_date) = '$date'")['total'] ?? 0;
        $total_topup_pm = $CMSNT->get_row("SELECT SUM(price) AS total FROM payment_pm WHERE `status` = 1 AND DATE(create_date) = '$date'")['total'] ?? 0;
        $total_topup_squadco = $CMSNT->get_row("SELECT SUM(amount) AS total FROM payment_squadco WHERE DATE(create_gettime) = '$date'")['total'] ?? 0;
        $total_topup_toyyibpay = $CMSNT->get_row("SELECT SUM(amount) AS total FROM payment_toyyibpay WHERE `status` = 1 AND DATE(create_gettime) = '$date'")['total'] ?? 0;
        $total_topup_xipay = $CMSNT->get_row("SELECT SUM(price) AS total FROM payment_xipay WHERE `status` = 1 AND DATE(created_at) = '$date'")['total'] ?? 0;
        $total_topup_korapay = $CMSNT->get_row("SELECT SUM(price) AS total FROM payment_korapay WHERE `status` = 1 AND DATE(created_at) = '$date'")['total'] ?? 0;
        $total_topup_tmweasyapi = $CMSNT->get_row("SELECT SUM(price) AS total FROM payment_tmweasyapi WHERE `status` = 1 AND DATE(created_at) = '$date'")['total'] ?? 0;
        $total_topup_openpix = $CMSNT->get_row("SELECT SUM(price) AS total FROM payment_openpix WHERE `status` = 1 AND DATE(created_at) = '$date'")['total'] ?? 0;
        $total_topup = $total_topup_bank + $total_topup_card + $total_topup_crypto + $total_topup_momo + $total_topup_paypal + $total_topup_pm + $total_topup_squadco + $total_topup_toyyibpay + $total_topup_xipay + $total_topup_korapay + $total_topup_tmweasyapi + $total_topup_openpix;

        $labels[] = "$day/$month/$year";
        $data[] = $total_topup;
    }

    die(json_encode([
        'labels' => $labels,
        'data' => $data
    ]));
}

if ($_POST['action'] == 'view_chart_thong_ke_don_hang_thang') {
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (!$getUser = $CMSNT->get_row("SELECT * FROM `users` WHERE `token` = '" . check_string($_POST['token']) . "' AND `banned` = 0 AND `admin` != 0 ")) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (checkPermission($getUser['admin'], 'view_statistical') != true) {
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }
    $month = date('m');
    $year = date('Y');
    $numOfDays = cal_days_in_month(CAL_GREGORIAN, $month, $year);

    $labels = [];
    $revenues = [];
    $profits = [];

    for ($day = 1; $day <= $numOfDays; $day++) {
        $date = "$year-$month-$day";
        $query = "SELECT SUM(pay) AS total_pay, SUM(cost) AS total_cost FROM product_order WHERE `refund` = 0 AND DATE(create_gettime) = '$date'";
        $result = $CMSNT->get_row($query);

        $labels[] = "$day/$month/$year";
        $revenues[] = $result['total_pay'] ?? 0;
        $profits[] = ($result['total_pay'] ?? 0) - ($result['total_cost'] ?? 0);
    }

    die(json_encode([
        'labels' => $labels,
        'revenues' => $revenues,
        'profits' => $profits
    ]));
}


if ($_POST['action'] == 'show_thong_ke_dashboard') {
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (!$getUser = $CMSNT->get_row("SELECT * FROM `users` WHERE `token` = '" . check_string($_POST['token']) . "' AND `banned` = 0 AND `admin` != 0 ")) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (checkPermission($getUser['admin'], 'view_statistical') != true) {
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }
    $currentDate = date("Y-m-d");
    $currentYear = date('Y');
    $currentMonth = date('m');

    // Xác định ngày bắt đầu và kết thúc của tuần hiện tại (Thứ Hai đến Chủ Nhật)
    $startOfWeek = date("Y-m-d", strtotime("last Monday", strtotime($currentDate)));
    // Nếu hôm nay là Thứ Hai, không cần lùi lại
    if (date('N', strtotime($currentDate)) == 1) {
        $startOfWeek = $currentDate;
    }
    $endOfWeek = date("Y-m-d", strtotime("next Sunday", strtotime($currentDate)));
    // Nếu hôm nay là Chủ Nhật, không cần tiến lên
    if (date('N', strtotime($currentDate)) == 7) {
        $endOfWeek = $currentDate;
    }

    // Dữ liệu hôm nay
    $query1 = "SELECT 
                COUNT(id) AS total_orders_today, 
                SUM(pay) AS total_pay_today, 
                SUM(cost) AS total_cost_today 
              FROM `product_order` 
              WHERE `refund` = 0 
              AND `create_gettime` LIKE '%$currentDate%'";
    $result1 = $CMSNT->get_row($query1);

    $total_orders_today = $result1['total_orders_today'];
    $total_pay_today = $result1['total_pay_today'];
    $total_cost_today = $result1['total_cost_today'];
    $profit_today = $total_pay_today - $total_cost_today;

    $new_users_today = $CMSNT->get_row("SELECT COUNT(id) AS total_users_today FROM `users` WHERE `create_date` LIKE '%$currentDate%'")['total_users_today'];

    // Dữ liệu tuần này
    $query_week = "SELECT 
                    COUNT(id) AS total_orders_week, 
                    SUM(pay) AS total_pay_week, 
                    SUM(cost) AS total_cost_week 
                  FROM `product_order` 
                  WHERE `refund` = 0 
                  AND DATE(`create_gettime`) BETWEEN '$startOfWeek' AND '$endOfWeek'";
    $result_week = $CMSNT->get_row($query_week);

    $total_orders_week = $result_week['total_orders_week'];
    $total_pay_week = $result_week['total_pay_week'];
    $total_cost_week = $result_week['total_cost_week'];
    $profit_week = $total_pay_week - $total_cost_week;

    $new_users_week = $CMSNT->get_row("SELECT COUNT(id) AS total_users_week FROM `users` WHERE DATE(`create_date`) BETWEEN '$startOfWeek' AND '$endOfWeek'")['total_users_week'];

    // Dữ liệu tháng này
    $query2 = "SELECT 
                COUNT(id) AS total_orders_month, 
                SUM(pay) AS total_pay_month, 
                SUM(cost) AS total_cost_month 
              FROM `product_order` 
              WHERE `refund` = 0 
              AND YEAR(create_gettime) = $currentYear 
              AND MONTH(create_gettime) = $currentMonth";
    $result2 = $CMSNT->get_row($query2);

    $total_orders_month = $result2['total_orders_month'];
    $total_pay_month = $result2['total_pay_month'];
    $total_cost_month = $result2['total_cost_month'];
    $profit_month = $total_pay_month - $total_cost_month;

    $new_users_month = $CMSNT->get_row("SELECT COUNT(id) AS total_users_month FROM `users` WHERE YEAR(create_date) = $currentYear AND MONTH(create_date) = $currentMonth")['total_users_month'];

    // Dữ liệu toàn thời gian
    $query3 = "SELECT 
                COUNT(id) AS total_orders_all, 
                SUM(pay) AS total_pay_all, 
                SUM(cost) AS total_cost_all 
              FROM `product_order` 
              WHERE `refund` = 0";
    $result3 = $CMSNT->get_row($query3);

    $total_orders_all = $result3['total_orders_all'];
    $total_pay_all = $result3['total_pay_all'];
    $total_cost_all = $result3['total_cost_all'];
    $profit_all = $total_pay_all - $total_cost_all;

    $total_users_all = $CMSNT->get_row("SELECT COUNT(id) AS total_users_all FROM `users`")['total_users_all'];

    $data = array(
        "total_orders_today" => format_cash($total_orders_today),
        "total_pay_today" => format_currency($total_pay_today),
        "total_cost_today" => format_currency($total_cost_today),
        "profit_today" => format_currency($profit_today),
        "new_users_today" => format_cash($new_users_today),

        // Thêm dữ liệu tuần này
        "total_orders_week" => format_cash($total_orders_week),
        "total_pay_week" => format_currency($total_pay_week),
        "total_cost_week" => format_currency($total_cost_week),
        "profit_week" => format_currency($profit_week),
        "new_users_week" => format_cash($new_users_week),

        "total_orders_month" => format_cash($total_orders_month),
        "total_pay_month" => format_currency($total_pay_month),
        "total_cost_month" => format_currency($total_cost_month),
        "profit_month" => format_currency($profit_month),
        "new_users_month" => format_cash($new_users_month),
        "total_orders_all" => format_cash($total_orders_all),
        "total_pay_all" => format_currency($total_pay_all),
        "total_cost_all" => format_currency($total_cost_all),
        "profit_all" => format_currency($profit_all),
        "total_users_all" => format_cash($total_users_all)
    );

    die(json_encode($data));
}



if ($_POST['action'] == 'phan_tich_utm_source_users') {
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (!$getUser = $CMSNT->get_row("SELECT * FROM `users` WHERE `token` = '" . check_string($_POST['token']) . "' AND `banned` = 0 AND `admin` != 0 ")) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (checkPermission($getUser['admin'], 'view_user') != true) {
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }
    // Tạo HTML cho tab
    $html = '<ul class="nav nav-tabs mb-5 nav-justified nav-style-1 d-sm-flex d-block" id="myTab" role="tablist">';
    $html .= '<li class="nav-item">';
    $html .= '<a class="nav-link active" id="table-tab" data-toggle="tab" href="#table-content" role="tab" aria-controls="table-content" aria-selected="true">Table</a>';
    $html .= '</li>';
    $html .= '<li class="nav-item">';
    $html .= '<a class="nav-link" id="chart-tab" data-toggle="tab" href="#chart-content" role="tab" aria-controls="chart-content" aria-selected="false">Pie Chart</a>';
    $html .= '</li>';
    $html .= '</ul>';

    // Tạo HTML cho nội dung của tab
    $html .= '<div class="tab-content" id="myTabContent">';
    $html .= '<div class="tab-pane fade show active" id="table-content" role="tabpanel" aria-labelledby="table-tab">';
    $html .= '<div class="table-responsive table-wrapper" style="max-height: 500px;overflow-y: auto;">';
    $html .= '<table class="table text-nowrap table-striped table-hover table-bordered">
            <thead>
                <tr>
                    <th class="text-center">Xếp hạng</th>
                    <th class="text-center">utm_source</th>
                    <th class="text-center">Số thành viên đăng ký</th>
                </tr>
            </thead>
            <tbody>';
    $i = 1;
    $data_labels = [];
    $data_user_counts = [];
    foreach (
        $CMSNT->get_list("SELECT 
    utm_source, 
    COUNT(*) AS total_users
FROM users 
GROUP BY utm_source 
ORDER BY total_users DESC ") as $row
    ) {
        $data_labels[] = $row['utm_source'];
        $data_user_counts[] = $row['total_users'];
        $html .= "<tr>
    <td class='text-center' style='font-size:15px;'>" . $i++ . "</td>
    <td class='text-center'>" . $row['utm_source'] . "</td>
    <td class='text-center'><b>" . format_cash($row['total_users']) . "</b></td>
  </tr>";
    }
    $html .= "</tbody>
        </table>";
    $html .= "</div>";
    $html .= '</div>';

    $html .= '<div class="tab-pane fade" id="chart-content" role="tabpanel" aria-labelledby="chart-tab">';
    $html .= '<canvas id="myChart" width="500" height="300"></canvas>';
    $html .= '</div>';

    $html .= '</div>';

    // Thêm kịch bản JavaScript để chuyển đổi tab
    $html .= '<script>
            $(document).ready(function(){
                $("#table-tab").click(function(){
                    $("#chart-content").removeClass("show active");
                    $("#chart-tab").removeClass("active");
                    $("#table-content").addClass("show active");
                    $("#table-tab").addClass("active");
                });
                $("#chart-tab").click(function(){
                    $("#table-content").removeClass("show active");
                    $("#table-tab").removeClass("active");
                    $("#chart-content").addClass("show active");
                    $("#chart-tab").addClass("active");
                    // Thêm kịch bản JavaScript để vẽ biểu đồ Pie Chart
                    var ctx = document.getElementById("myChart").getContext("2d");
                    var myChart = new Chart(ctx, {
                        type: "pie",
                        data: {
                            labels: ' . json_encode($data_labels) . ',
                            datasets: [{
                                label: "Số lượng người dùng",
                                data: ' . json_encode($data_user_counts) . ',
                                backgroundColor: [
                                    "rgba(255, 99, 132, 0.6)",
                                    "rgba(54, 162, 235, 0.6)",
                                    "rgba(255, 206, 86, 0.6)",
                                    "rgba(75, 192, 192, 0.6)",
                                    "rgba(153, 102, 255, 0.6)",
                                    "rgba(255, 159, 64, 0.6)"
                                ],
                                borderColor: [
                                    "rgba(255, 99, 132, 1)",
                                    "rgba(54, 162, 235, 1)",
                                    "rgba(255, 206, 86, 1)",
                                    "rgba(75, 192, 192, 1)",
                                    "rgba(153, 102, 255, 1)",
                                    "rgba(255, 159, 64, 1)"
                                ],
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            legend: {
                                position: "right",
                                labels: {
                                    fontColor: "black",
                                    fontSize: 12
                                }
                            }
                        }
                    });
                });
            });
        </script>';







    die($html);
}


if ($_POST['action'] == 'view_nap_tien_gan_day') {
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (!$getUser = $CMSNT->get_row("SELECT * FROM `users` WHERE `token` = '" . check_string($_POST['token']) . "' AND `banned` = 0 AND `admin` != 0 ")) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (checkPermission($getUser['admin'], 'view_recent_transactions') != true) {
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }
    $deposits = $CMSNT->get_list("SELECT * FROM `deposit_log` WHERE `is_virtual` = 0 ORDER BY id DESC limit 100");
    $html = '';
    foreach ($deposits as $deposit) {
        $html .= '<li>
        <div class="timeline-time text-end">
            <span class="date">' . timeAgo($deposit['create_time']) . '</span>
        </div>
        <div class="timeline-icon">
            <a href="javascript:void(0);"></a>
        </div>
        <div class="timeline-body">
            <div class="d-flex align-items-top timeline-main-content flex-wrap mt-0">
                <div class="flex-fill">
                    <div class="d-flex align-items-center">
                        <div class="mt-sm-0 mt-2">
                            <p class="mb-0 text-muted"><a class="fw-bold" href="' . base_url_admin('user-edit&id=' . $deposit['user_id']) . '" style="color: green;">' . getRowRealtime('users', $deposit['user_id'], 'username') . '</a>
                                thực hiện nạp <b style="color: blue;">' . format_currency($deposit['amount']) . '</b>
                                bằng <b style="color:red">' . $deposit['method'] . '</b> thực nhận <b style="color:blue;">' . format_currency($deposit['received']) . '</b>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </li>';
    }
    die($html);
}
if ($_POST['action'] == 'view_don_hang_gan_day') {
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (!$getUser = $CMSNT->get_row("SELECT * FROM `users` WHERE `token` = '" . check_string($_POST['token']) . "' AND `banned` = 0 AND `admin` != 0 ")) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (checkPermission($getUser['admin'], 'view_recent_transactions') != true) {
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }
    $orders = $CMSNT->get_list("SELECT * FROM `order_log` WHERE `is_virtual` = 0 ORDER BY id DESC limit 100");
    $html = '';
    foreach ($orders as $order) {
        $html .= '<li>
            <div class="timeline-time text-end">
                <span class="date">' . timeAgo($order['create_time']) . '</span>
            </div>
            <div class="timeline-icon">
                <a href="javascript:void(0);"></a>
            </div>
            <div class="timeline-body">
                <div class="d-flex align-items-top timeline-main-content flex-wrap mt-0">
                    <div class="flex-fill">
                        <div class="d-flex align-items-center">
                            <div class="mt-sm-0 mt-2">
                                <p class="mb-0 text-muted"><a class="fw-bold" href="' . base_url_admin('user-edit&id=' . $order['buyer']) . '" style="color: green;">' . getRowRealtime('users', $order['buyer'], 'username') . '</a>
                                    mua <b style="color: red;">' . format_cash($order['amount']) . '</b>
                                    <b>' . $order['product_name'] . '</b> với giá <b style="color:blue;">' . format_currency($order['pay']) . '</b>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </li>';
    }
    die($html);
}

if ($_POST['action'] == 'top_san_pham_ban_chay') {
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (!$getUser = $CMSNT->get_row("SELECT * FROM `users` WHERE `token` = '" . check_string($_POST['token']) . "' AND `banned` = 0 AND `admin` != 0 ")) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (checkPermission($getUser['admin'], 'view_order_product') != true) {
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }
    // Tạo HTML cho tab
    $html = '<ul class="nav nav-tabs mb-5 nav-justified nav-style-1 d-sm-flex d-block" id="myTab" role="tablist">';
    $html .= '<li class="nav-item">';
    $html .= '<a class="nav-link active" id="table-tab" data-toggle="tab" href="#table-content" role="tab" aria-controls="table-content" aria-selected="true">Table</a>';
    $html .= '</li>';
    $html .= '<li class="nav-item">';
    $html .= '<a class="nav-link" id="chart-tab" data-toggle="tab" href="#chart-content" role="tab" aria-controls="chart-content" aria-selected="false">Pie Chart</a>';
    $html .= '</li>';
    $html .= '</ul>';

    // Tạo HTML cho nội dung của tab
    $html .= '<div class="tab-content" id="myTabContent">';
    $html .= '<div class="tab-pane fade show active" id="table-content" role="tabpanel" aria-labelledby="table-tab">';
    $html .= '<div class="table-responsive table-wrapper" style="max-height: 500px;overflow-y: auto;">';
    $html .= '<table class="table text-nowrap table-striped table-hover table-bordered">
            <thead>
                <tr>
                    <th scope="col">Xếp hạng</th>
                    <th scope="col">Sản phẩm</th>
                    <th scope="col">Đơn hàng đã bán</th>
                    <th scope="col">Tài khoản đã bán</th>
                    <th scope="col">Doanh thu</th>
                    <th scope="col">Lợi nhuận</th>
                </tr>
            </thead>
            <tbody>';
    $i = 1;
    $data_labels = [];
    $data_revenue = [];
    foreach (
        $CMSNT->get_list("SELECT 
    product_id, 
    product_name, 
    COUNT(*) AS total_orders, 
    SUM(amount) AS total_quantity, 
    SUM(pay) AS total_revenue,
    SUM(cost) AS total_cost
FROM product_order 
WHERE refund != 1 
GROUP BY product_id, product_name 
ORDER BY total_quantity DESC, total_orders DESC ") as $row
    ) {
        $data_labels[] = $row['product_name'];
        $data_revenue[] = $row['total_revenue'];
        $profit = $row['total_revenue'] - $row['total_cost']; // Lợi nhuận = Tổng doanh thu - Tổng chi phí
        $html .= "<tr>
    <td class='text-center' style='font-size:15px;'>" . $i++ . "</td>
    <td><a class='text-primary' href='" . base_url_admin('product-edit&id=' . $row['product_id']) . "'>" . $row['product_name'] . "</a></td>
    <td class='text-right'><b>" . format_cash($row['total_orders']) . "</b></td>
    <td class='text-right'><b style='color:blue;'>" . format_cash($row['total_quantity']) . "</b></td>
    <td class='text-right'><b style='color:red;'>" . format_currency($row['total_revenue']) . "</b></td>
    <td class='text-right'><b style='color:green;'>" . format_currency($profit) . "</b></td>
  </tr>";
    }
    $html .= "</tbody>
        </table>";
    $html .= "</div>";
    $html .= '</div>';

    $html .= '<div class="tab-pane fade" id="chart-content" role="tabpanel" aria-labelledby="chart-tab">';
    $html .= '<canvas id="myChart" width="500" height="300"></canvas>';
    $html .= '</div>';

    $html .= '</div>';

    // Thêm kịch bản JavaScript để chuyển đổi tab
    $html .= '<script>
            $(document).ready(function(){
                $("#table-tab").click(function(){
                    $("#chart-content").removeClass("show active");
                    $("#chart-tab").removeClass("active");
                    $("#table-content").addClass("show active");
                    $("#table-tab").addClass("active");
                });
                $("#chart-tab").click(function(){
                    $("#table-content").removeClass("show active");
                    $("#table-tab").removeClass("active");
                    $("#chart-content").addClass("show active");
                    $("#chart-tab").addClass("active");
                    // Thêm kịch bản JavaScript để vẽ biểu đồ Pie Chart
                    var ctx = document.getElementById("myChart").getContext("2d");
                    var myChart = new Chart(ctx, {
                        type: "pie",
                        data: {
                            labels: ' . json_encode($data_labels) . ',
                            datasets: [{
                                label: "Doanh Thu",
                                data: ' . json_encode($data_revenue) . ',
                                backgroundColor: [
                                    "rgba(255, 99, 132, 0.6)",
                                    "rgba(54, 162, 235, 0.6)",
                                    "rgba(255, 206, 86, 0.6)",
                                    "rgba(75, 192, 192, 0.6)",
                                    "rgba(153, 102, 255, 0.6)",
                                    "rgba(255, 159, 64, 0.6)"
                                ],
                                borderColor: [
                                    "rgba(255, 99, 132, 1)",
                                    "rgba(54, 162, 235, 1)",
                                    "rgba(255, 206, 86, 1)",
                                    "rgba(75, 192, 192, 1)",
                                    "rgba(153, 102, 255, 1)",
                                    "rgba(255, 159, 64, 1)"
                                ],
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            legend: {
                                position: "right",
                                labels: {
                                    fontColor: "black",
                                    fontSize: 12
                                }
                            }
                        }
                    });
                });
            });
        </script>';






    die($html);
}

if ($_POST['action'] == 'view_product_sold') {
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (!$getUser = $CMSNT->get_row("SELECT * FROM `users` WHERE `token` = '" . check_string($_POST['token']) . "' AND `banned` = 0 AND `admin` != 0 ")) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (checkPermission($getUser['admin'], 'view_sold_product') != true) {
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }
    $accounts = '';
    foreach ($CMSNT->get_list(" SELECT * FROM `product_sold` ORDER BY id DESC ") as $account) {
        $accounts .= htmlspecialchars_decode($account['account']) . PHP_EOL;
    }
    $data = json_encode([
        'status'    => 'success',
        'accounts'  => $accounts,
        'msg'       => __('Xuất dữ liệu thành công')
    ]);

    $CMSNT->insert("logs", [
        'user_id'       => $getUser['id'],
        'ip'            => myip(),
        'device'        => getUserAgent(),
        'createdate'    => gettime(),
        'action'        => __('Copy toàn bộ danh sách tài khoản đã bán')
    ]);
    die($data);
}

if ($_POST['action'] == 'view_product_live') {
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (!$getUser = $CMSNT->get_row("SELECT * FROM `users` WHERE `token` = '" . check_string($_POST['token']) . "' AND `banned` = 0 AND `admin` != 0 ")) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (checkPermission($getUser['admin'], 'edit_stock_product') != true) {
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }
    if (empty($_POST['code'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Mã kho hàng không hợp lệ')]));
    }
    $code = check_string($_POST['code']);
    if (!$product_die = $CMSNT->get_row("SELECT * FROM `product_stock` WHERE `product_code` = '$code' ")) {
        die(json_encode(['status' => 'error', 'msg' => __('Mã kho hàng không tồn tại trong hệ thống')]));
    }
    $accounts = '';
    foreach ($CMSNT->get_list(" SELECT * FROM `product_stock` WHERE `product_code` = '$code' ORDER BY id DESC ") as $account) {
        $accounts .= htmlspecialchars_decode($account['account']) . PHP_EOL;
    }
    $data = json_encode([
        'status'    => 'success',
        'accounts'  => $accounts,
        'msg'       => __('Xuất dữ liệu thành công')
    ]);

    $CMSNT->insert("logs", [
        'user_id'       => $getUser['id'],
        'ip'            => myip(),
        'device'        => getUserAgent(),
        'createdate'    => gettime(),
        'action'        => __('Xem danh sách tài khoản LIVE của kho hàng') . ' (' . $code . ')'
    ]);
    die($data);
}
if ($_POST['action'] == 'view_product_die') {
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (!$getUser = $CMSNT->get_row("SELECT * FROM `users` WHERE `token` = '" . check_string($_POST['token']) . "' AND `banned` = 0 AND `admin` != 0 ")) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (checkPermission($getUser['admin'], 'edit_stock_product') != true) {
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }
    if (empty($_POST['code'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Mã kho hàng không hợp lệ')]));
    }
    $code = check_string($_POST['code']);
    if (!$product_die = $CMSNT->get_row("SELECT * FROM `product_die` WHERE `product_code` = '$code' ")) {
        die(json_encode(['status' => 'error', 'msg' => __('Mã kho hàng không tồn tại trong hệ thống')]));
    }
    $accounts = '';
    foreach ($CMSNT->get_list(" SELECT * FROM `product_die` WHERE `product_code` = '$code' ORDER BY id DESC ") as $account) {
        $accounts .= htmlspecialchars_decode($account['account']) . PHP_EOL;
    }
    $data = json_encode([
        'status'    => 'success',
        'accounts'  => $accounts,
        'msg'       => __('Xuất dữ liệu thành công')
    ]);

    $CMSNT->insert("logs", [
        'user_id'       => $getUser['id'],
        'ip'            => myip(),
        'device'        => getUserAgent(),
        'createdate'    => gettime(),
        'action'        => __('Xem danh sách tài khoản DIE của kho hàng') . ' (' . $code . ')'
    ]);
    die($data);
}
if ($_POST['action'] == 'view_order') {
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (!$getUser = $CMSNT->get_row("SELECT * FROM `users` WHERE `token` = '" . check_string($_POST['token']) . "' AND `banned` = 0 AND `admin` != 0 ")) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (checkPermission($getUser['admin'], 'view_order_product') != true) {
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }
    if (empty($_POST['trans_id'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Đơn hàng không hợp lệ')]));
    }
    $trans_id = check_string($_POST['trans_id']);
    if (!$order = $CMSNT->get_row("SELECT * FROM `product_order` WHERE `trans_id` = '$trans_id' ")) {
        die(json_encode(['status' => 'error', 'msg' => __('Đơn hàng không tồn tại trong hệ thống')]));
    }
    $accounts = '';
    foreach ($CMSNT->get_list(" SELECT * FROM `product_sold` WHERE `trans_id` = '$trans_id' ORDER BY id DESC ") as $account) {
        $accounts .= htmlspecialchars_decode($account['account']) . PHP_EOL;
    }
    $data = json_encode([
        'status'    => 'success',
        'accounts'  => $accounts,
        'msg'       => __('Lấy thành công chi tiết đơn hàng')
    ]);

    $CMSNT->insert("logs", [
        'user_id'       => $getUser['id'],
        'ip'            => myip(),
        'device'        => getUserAgent(),
        'createdate'    => gettime(),
        'action'        => __('View order') . ' (' . $order['trans_id'] . ')'
    ]);

    die($data);
}
if ($_POST['action'] == 'download_order') {
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (!$getUser = $CMSNT->get_row("SELECT * FROM `users` WHERE `token` = '" . check_string($_POST['token']) . "' AND `banned` = 0 AND `admin` != 0 ")) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (checkPermission($getUser['admin'], 'view_order_product') != true) {
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }
    if (empty($_POST['trans_id'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Đơn hàng không hợp lệ')]));
    }
    $trans_id = check_string($_POST['trans_id']);
    if (!$order = $CMSNT->get_row("SELECT * FROM `product_order` WHERE `trans_id` = '$trans_id' ")) {
        die(json_encode(['status' => 'error', 'msg' => __('Đơn hàng không tồn tại trong hệ thống')]));
    }
    $accounts = '';
    foreach ($CMSNT->get_list(" SELECT * FROM `product_sold` WHERE `trans_id` = '$trans_id' ORDER BY id DESC ") as $account) {
        $accounts .= preg_replace('/\s+/', '', $account['account']) . PHP_EOL;
    }
    $file = $trans_id . ".txt";
    $data = json_encode([
        'status'    => 'success',
        'filename'  => $file,
        'accounts'  => $accounts,
        'msg'       => __('Đang tải xuống đơn hàng...')
    ]);

    $CMSNT->insert("logs", [
        'user_id'       => $getUser['id'],
        'ip'            => myip(),
        'device'        => getUserAgent(),
        'createdate'    => gettime(),
        'action'        => __('Download order') . ' (' . $order['trans_id'] . ')'
    ]);

    /** NOTE ACTION */
    $my_text = $CMSNT->site('noti_action');
    $my_text = str_replace('{domain}', $_SERVER['SERVER_NAME'], $my_text);
    $my_text = str_replace('{username}', $getUser['username'], $my_text);
    $my_text = str_replace('{action}',  __('Download order') . ' (' . $order['trans_id'] . ')', $my_text);
    $my_text = str_replace('{ip}', myip(), $my_text);
    $my_text = str_replace('{time}', gettime(), $my_text);
    sendMessAdmin($my_text);
    die($data);
}

if ($_POST['action'] == 'view_chart_thong_ke_don_hang_api' || $_POST['action'] == 'view_chart_thong_ke_don_hang_supplier') {
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (!$getUser = $CMSNT->get_row("SELECT * FROM `users` WHERE `token` = '" . check_string($_POST['token']) . "' AND `banned` = 0 AND `admin` != 0 ")) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (checkPermission($getUser['admin'], 'view_statistical') != true) {
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }

    $time_range = check_string($_POST['time_range']);
    $labels = [];
    $revenues = [];
    $profits = [];

    // Xác định điều kiện lọc supplier_id
    $supplierCondition = "";
    if ($_POST['action'] == 'view_chart_thong_ke_don_hang_api') {
        // Nếu là thống kê tất cả API
        $supplierCondition = "`supplier_id` != 0";
    } else {
        // Nếu là thống kê theo supplier cụ thể
        $supplier_id = check_string($_POST['supplier_id']);
        // Kiểm tra tồn tại supplier_id
        if (!$CMSNT->get_row("SELECT * FROM `suppliers` WHERE `id` = '$supplier_id'")) {
            die(json_encode(['status' => 'error', 'msg' => __('Nhà cung cấp không tồn tại')]));
        }
        $supplierCondition = "`supplier_id` = '$supplier_id'";
    }

    if ($time_range == 'week') {
        // Thống kê 7 ngày gần đây
        for ($i = 6; $i >= 0; $i--) {
            $date = date("Y-m-d", strtotime("-$i days"));
            $query = "SELECT SUM(pay) AS total_pay, SUM(cost) AS total_cost FROM product_order WHERE `refund` = 0 AND $supplierCondition AND DATE(create_gettime) = '$date'";
            $result = $CMSNT->get_row($query);

            $labels[] = date("d/m", strtotime("-$i days"));
            $revenues[] = $result['total_pay'] ?? 0;
            $profits[] = ($result['total_pay'] ?? 0) - ($result['total_cost'] ?? 0);
        }
    } else if ($time_range == 'month') {
        // Thống kê theo tháng hiện tại
        $month = date('m');
        $year = date('Y');
        $numOfDays = cal_days_in_month(CAL_GREGORIAN, $month, $year);

        for ($day = 1; $day <= $numOfDays; $day++) {
            $date = "$year-$month-$day";
            $query = "SELECT SUM(pay) AS total_pay, SUM(cost) AS total_cost FROM product_order WHERE `refund` = 0 AND $supplierCondition AND DATE(create_gettime) = '$date'";
            $result = $CMSNT->get_row($query);

            $labels[] = "$day/$month";
            $revenues[] = $result['total_pay'] ?? 0;
            $profits[] = ($result['total_pay'] ?? 0) - ($result['total_cost'] ?? 0);
        }
    } else if ($time_range == 'year') {
        // Thống kê theo năm hiện tại
        $year = date('Y');

        for ($month = 1; $month <= 12; $month++) {
            $month_name = date('m', mktime(0, 0, 0, $month, 1));
            $query = "SELECT SUM(pay) AS total_pay, SUM(cost) AS total_cost FROM product_order 
                      WHERE `refund` = 0 AND $supplierCondition AND MONTH(create_gettime) = '$month' AND YEAR(create_gettime) = '$year'";
            $result = $CMSNT->get_row($query);

            $labels[] = "Tháng $month_name";
            $revenues[] = $result['total_pay'] ?? 0;
            $profits[] = ($result['total_pay'] ?? 0) - ($result['total_cost'] ?? 0);
        }
    }

    die(json_encode([
        'labels' => $labels,
        'revenues' => $revenues,
        'profits' => $profits
    ]));
}

// Duy trì API cũ để tương thích với code cũ
if ($_POST['action'] == 'view_chart_thong_ke_don_hang_api_thang') {
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (!$getUser = $CMSNT->get_row("SELECT * FROM `users` WHERE `token` = '" . check_string($_POST['token']) . "' AND `banned` = 0 AND `admin` != 0 ")) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (checkPermission($getUser['admin'], 'view_statistical') != true) {
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }
    $month = date('m');
    $year = date('Y');
    $numOfDays = cal_days_in_month(CAL_GREGORIAN, $month, $year);

    $labels = [];
    $revenues = [];
    $profits = [];

    for ($day = 1; $day <= $numOfDays; $day++) {
        $date = "$year-$month-$day";
        $query = "SELECT SUM(pay) AS total_pay, SUM(cost) AS total_cost FROM product_order WHERE `refund` = 0 AND `supplier_id` != 0 AND DATE(create_gettime) = '$date'";
        $result = $CMSNT->get_row($query);

        $labels[] = "$day/$month/$year";
        $revenues[] = $result['total_pay'] ?? 0;
        $profits[] = ($result['total_pay'] ?? 0) - ($result['total_cost'] ?? 0);
    }

    die(json_encode([
        'labels' => $labels,
        'revenues' => $revenues,
        'profits' => $profits
    ]));
}

if ($_POST['action'] == 'view_chart_doanh_thu_api_suppliers') {
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (!$getUser = $CMSNT->get_row("SELECT * FROM `users` WHERE `token` = '" . check_string($_POST['token']) . "' AND `banned` = 0 AND `admin` != 0 ")) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (checkPermission($getUser['admin'], 'view_statistical') != true) {
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }

    $time_range = check_string($_POST['time_range']);
    $labels = [];
    $suppliers = [];

    // Lấy danh sách tất cả nhà cung cấp API
    $suppliersList = $CMSNT->get_list("SELECT `id`, `domain` FROM `suppliers` WHERE `status` = 1 ORDER BY `id` ASC");

    if ($time_range == 'week') {
        // Thống kê 7 ngày gần đây
        for ($i = 6; $i >= 0; $i--) {
            $date = date("Y-m-d", strtotime("-$i days"));
            $labels[] = date("d/m", strtotime("-$i days"));

            // Lấy dữ liệu cho mỗi nhà cung cấp
            foreach ($suppliersList as $supplier) {
                $supplier_id = $supplier['id'];

                // Tìm supplier trong mảng hoặc tạo mới
                $found = false;
                foreach ($suppliers as &$sup) {
                    if ($sup['id'] == $supplier_id) {
                        $found = true;
                        $query = "SELECT SUM(pay) AS total_pay FROM product_order 
                                  WHERE `refund` = 0 AND `supplier_id` = '$supplier_id' 
                                  AND DATE(create_gettime) = '$date'";
                        $result = $CMSNT->get_row($query);
                        $sup['revenues'][] = $result['total_pay'] ?? 0;
                        break;
                    }
                }

                if (!$found) {
                    $supplierData = [
                        'id' => $supplier_id,
                        'domain' => $supplier['domain'],
                        'name' => preg_replace('/^https?:\/\/(www\.)?/', '', rtrim($supplier['domain'], '/')),
                        'revenues' => []
                    ];

                    // Fill với 0 cho các ngày trước
                    for ($j = 0; $j < 6 - $i; $j++) {
                        $supplierData['revenues'][] = 0;
                    }

                    $query = "SELECT SUM(pay) AS total_pay FROM product_order 
                              WHERE `refund` = 0 AND `supplier_id` = '$supplier_id' 
                              AND DATE(create_gettime) = '$date'";
                    $result = $CMSNT->get_row($query);
                    $supplierData['revenues'][] = $result['total_pay'] ?? 0;

                    $suppliers[] = $supplierData;
                }
            }
        }
    } else if ($time_range == 'month') {
        // Thống kê theo tháng hiện tại
        $month = date('m');
        $year = date('Y');
        $numOfDays = cal_days_in_month(CAL_GREGORIAN, $month, $year);

        for ($day = 1; $day <= $numOfDays; $day++) {
            $date = "$year-$month-$day";
            $labels[] = "$day/$month";

            // Lấy dữ liệu cho mỗi nhà cung cấp
            foreach ($suppliersList as $supplier) {
                $supplier_id = $supplier['id'];

                // Tìm supplier trong mảng hoặc tạo mới
                $found = false;
                foreach ($suppliers as &$sup) {
                    if ($sup['id'] == $supplier_id) {
                        $found = true;
                        $query = "SELECT SUM(pay) AS total_pay FROM product_order 
                                  WHERE `refund` = 0 AND `supplier_id` = '$supplier_id' 
                                  AND DATE(create_gettime) = '$date'";
                        $result = $CMSNT->get_row($query);
                        $sup['revenues'][] = $result['total_pay'] ?? 0;
                        break;
                    }
                }

                if (!$found) {
                    $supplierData = [
                        'id' => $supplier_id,
                        'domain' => $supplier['domain'],
                        'name' => preg_replace('/^https?:\/\/(www\.)?/', '', rtrim($supplier['domain'], '/')),
                        'revenues' => []
                    ];

                    // Fill với 0 cho các ngày trước
                    for ($j = 1; $j < $day; $j++) {
                        $supplierData['revenues'][] = 0;
                    }

                    $query = "SELECT SUM(pay) AS total_pay FROM product_order 
                              WHERE `refund` = 0 AND `supplier_id` = '$supplier_id' 
                              AND DATE(create_gettime) = '$date'";
                    $result = $CMSNT->get_row($query);
                    $supplierData['revenues'][] = $result['total_pay'] ?? 0;

                    $suppliers[] = $supplierData;
                }
            }
        }
    } else if ($time_range == 'year') {
        // Thống kê theo năm hiện tại
        $year = date('Y');

        for ($month = 1; $month <= 12; $month++) {
            $month_name = date('m', mktime(0, 0, 0, $month, 1));
            $labels[] = "Tháng $month_name";

            // Lấy dữ liệu cho mỗi nhà cung cấp
            foreach ($suppliersList as $supplier) {
                $supplier_id = $supplier['id'];

                // Tìm supplier trong mảng hoặc tạo mới
                $found = false;
                foreach ($suppliers as &$sup) {
                    if ($sup['id'] == $supplier_id) {
                        $found = true;
                        $query = "SELECT SUM(pay) AS total_pay FROM product_order 
                                  WHERE `refund` = 0 AND `supplier_id` = '$supplier_id' 
                                  AND MONTH(create_gettime) = '$month' AND YEAR(create_gettime) = '$year'";
                        $result = $CMSNT->get_row($query);
                        $sup['revenues'][] = $result['total_pay'] ?? 0;
                        break;
                    }
                }

                if (!$found) {
                    $supplierData = [
                        'id' => $supplier_id,
                        'domain' => $supplier['domain'],
                        'name' => preg_replace('/^https?:\/\/(www\.)?/', '', rtrim($supplier['domain'], '/')),
                        'revenues' => []
                    ];

                    // Fill với 0 cho các tháng trước
                    for ($j = 1; $j < $month; $j++) {
                        $supplierData['revenues'][] = 0;
                    }

                    $query = "SELECT SUM(pay) AS total_pay FROM product_order 
                              WHERE `refund` = 0 AND `supplier_id` = '$supplier_id' 
                              AND MONTH(create_gettime) = '$month' AND YEAR(create_gettime) = '$year'";
                    $result = $CMSNT->get_row($query);
                    $supplierData['revenues'][] = $result['total_pay'] ?? 0;

                    $suppliers[] = $supplierData;
                }
            }
        }
    }

    // Loại bỏ các nhà cung cấp không có doanh thu
    $filteredSuppliers = [];
    foreach ($suppliers as $supplier) {
        $totalRevenue = array_sum($supplier['revenues']);
        if ($totalRevenue > 0) {
            $filteredSuppliers[] = $supplier;
        }
    }

    die(json_encode([
        'labels' => $labels,
        'suppliers' => $filteredSuppliers
    ]));
}

if ($_POST['action'] == 'exportUsersCSV') {
    if (checkPermission($getUser['admin'], 'view_user') != true) {
        die(json_encode(['status' => 'error', 'msg' => 'Bạn không có quyền sử dụng tính năng này']));
    }

    try {
        // Lấy toàn bộ danh sách users
        $users = $CMSNT->get_list("SELECT `id`, `username`, `fullname`, `email`, `phone`, `create_date` FROM `users` ORDER BY id DESC");

        if (empty($users)) {
            die(json_encode(['status' => 'error', 'msg' => 'Không có dữ liệu để xuất']));
        }
    } catch (Exception $e) {
        die(json_encode(['status' => 'error', 'msg' => 'Lỗi truy vấn database: ' . $e->getMessage()]));
    }

    try {
        // Tạo nội dung CSV
        $csvContent = "ID,Username,Họ tên,Email,Số điện thoại,Ngày đăng ký\n";

        foreach ($users as $user) {
            $fullname = !empty($user['fullname']) ? $user['fullname'] : 'N/A';
            $email = !empty($user['email']) ? $user['email'] : 'N/A';
            $phone = !empty($user['phone']) ? $user['phone'] : 'N/A';

            // Escape các ký tự đặc biệt trong CSV
            $fullname = '"' . str_replace('"', '""', $fullname) . '"';
            $email = '"' . str_replace('"', '""', $email) . '"';
            $phone = '"' . str_replace('"', '""', $phone) . '"';
            $username = '"' . str_replace('"', '""', $user['username']) . '"';

            $csvContent .= $user['id'] . ',' . $username . ',' . $fullname . ',' . $email . ',' . $phone . ',' . $user['create_date'] . "\n";
        }

        // Thêm BOM cho UTF-8 để Excel hiển thị đúng tiếng Việt
        $csvContent = "\xEF\xBB\xBF" . $csvContent;

        $filename = 'users_export_' . date('Y-m-d_H-i-s') . '.csv';
    } catch (Exception $e) {
        die(json_encode(['status' => 'error', 'msg' => 'Lỗi tạo CSV: ' . $e->getMessage()]));
    }

    try {
        // Log hoạt động
        $CMSNT->insert("logs", [
            'user_id'       => $getUser['id'],
            'ip'            => myip(),
            'device'        => getUserAgent(),
            'createdate'    => gettime(),
            'action'        => 'Xuất toàn bộ danh sách thành viên CSV (' . count($users) . ' records)'
        ]);

        die(json_encode([
            'status' => 'success',
            'data' => $csvContent,
            'filename' => $filename,
            'msg' => 'Xuất toàn bộ file CSV thành công (' . count($users) . ' bản ghi)'
        ]));
    } catch (Exception $e) {
        die(json_encode(['status' => 'error', 'msg' => 'Lỗi ghi log hoặc trả response: ' . $e->getMessage()]));
    }
}


// Lấy bảng xếp hạng user theo giá trị đơn hàng trong ngày
if ($_POST['action'] == 'get_daily_leaderboard') {
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (!$getUser = $CMSNT->get_row("SELECT * FROM `users` WHERE `token` = '" . check_string($_POST['token']) . "' AND `banned` = 0 AND `admin` != 0 ")) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (checkPermission($getUser['admin'], 'view_statistical') != true) {
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }

    $currentDate = date("Y-m-d");

    // Lấy top 50 user có tổng giá trị đơn hàng cao nhất trong ngày
    $query = "SELECT 
                u.id,
                u.username,
                u.fullname,
                u.email,
                SUM(po.pay) as total_spent,
                COUNT(po.id) as total_orders
              FROM `users` u
              INNER JOIN `product_order` po ON u.id = po.buyer
              WHERE po.refund = 0
              AND DATE(po.create_gettime) = '$currentDate'
              GROUP BY u.id, u.username, u.fullname, u.email
              ORDER BY total_spent DESC
              LIMIT 50";

    $leaderboard = $CMSNT->get_list($query);

    $data = [];
    $rank = 1;

    foreach ($leaderboard as $user) {
        $data[] = [
            'rank'  => $rank,
            'id'    => $user['id'],
            'username' => $user['username'],
            'fullname' => $user['fullname'] ? $user['fullname'] : $user['username'],
            'email' => $user['email'],
            'total_spent' => format_currency($user['total_spent']),
            'total_orders' => format_cash($user['total_orders'])
        ];
        $rank++;
    }

    die(json_encode([
        'status' => 'success',
        'data' => $data,
        'date' => date('d/m/Y')
    ]));
}

// Lấy top 50 services bán chạy nhất trong ngày
if ($_POST['action'] == 'get_daily_top_services') {
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (!$getUser = $CMSNT->get_row("SELECT * FROM `users` WHERE `token` = '" . check_string($_POST['token']) . "' AND `banned` = 0 AND `admin` != 0 ")) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (checkPermission($getUser['admin'], 'view_statistical') != true) {
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }

    $currentDate = date("Y-m-d");

    // Lấy top 50 products có tổng doanh thu cao nhất trong ngày
    $query = "SELECT 
                p.id as product_id,
                p.name as product_name,
                SUM(po.pay) as total_revenue,
                SUM(po.cost) as total_cost,
                COUNT(po.id) as total_orders,
                AVG(po.pay) as avg_price
              FROM `products` p
              INNER JOIN `product_order` po ON p.id = po.product_id
              WHERE po.refund = 0
              AND DATE(po.create_gettime) = '$currentDate'
              AND p.name IS NOT NULL
              AND p.name != ''
              GROUP BY p.id, p.name
              ORDER BY total_revenue DESC
              LIMIT 50";

    $products = $CMSNT->get_list($query);

    $data = [];
    $rank = 1;

    foreach ($products as $product) {
        $profit = $product['total_revenue'] - $product['total_cost'];
        $data[] = [
            'rank' => $rank,
            'product_id' => $product['product_id'],
            'product_name' => $product['product_name'],
            'total_revenue' => format_currency($product['total_revenue']),
            'total_cost' => format_currency($product['total_cost']),
            'profit' => format_currency($profit),
            'total_orders' => format_cash($product['total_orders']),
            'avg_price' => format_currency($product['avg_price'])
        ];
        $rank++;
    }

    die(json_encode([
        'status' => 'success',
        'data' => $data,
        'date' => date('d/m/Y')
    ]));
}







if ($_POST['action'] == 'get_affiliate_members') {
    if (checkPermission($getUser['admin'], 'view_affiliate') != true) {
        die(json_encode(['status' => 'error', 'msg' => 'Bạn không có quyền sử dụng tính năng này']));
    }
    if (empty($_POST['ref_id'])) {
        die(json_encode(['status' => 'error', 'msg' => 'ID không hợp lệ']));
    }
    $ref_id = check_string($_POST['ref_id']);

    // Lấy thông tin user ref
    if (!$refUser = $CMSNT->get_row(" SELECT * FROM `users` WHERE `id` = '$ref_id' ")) {
        die(json_encode(['status' => 'error', 'msg' => 'Người dùng không tồn tại']));
    }

    // Lấy danh sách thành viên đăng ký qua link affiliate
    $members = $CMSNT->get_list(" 
        SELECT `id`, `username`, `email`, `money`, `total_money`, `create_date` 
        FROM `users` 
        WHERE `ref_id` = '$ref_id' 
        ORDER BY `create_date` DESC 
    ");

    $data = [];
    if (!empty($members)) {
        foreach ($members as $member) {
            $data[] = [
                'id' => $member['id'],
                'username' => $member['username'],
                'email' => $member['email'],
                'money' => $member['money'],
                'money_formatted' => format_currency($member['money']),
                'total_money' => $member['total_money'],
                'total_money_formatted' => format_currency($member['total_money']),
                'create_date' => $member['create_date'],
                'edit_url' => base_url_admin('user-edit&id=' . $member['id'])
            ];
        }
    }

    die(json_encode([
        'status' => 'success',
        'data' => $data,
        'total' => count($members),
        'ref_username' => $refUser['username']
    ]));
}

if ($_POST['action'] == 'load_categories_api_datatable') {
    if (checkPermission($getUser['admin'], 'manager_suppliers') != true) {
        die(json_encode(['status' => 'error', 'msg' => 'Bạn không có quyền sử dụng tính năng này']));
    }
    if (empty($_POST['supplier_id'])) {
        die(json_encode(['status' => 'error', 'msg' => 'ID nhà cung cấp không hợp lệ']));
    }

    $supplier_id = check_string($_POST['supplier_id']);

    // Kiểm tra supplier tồn tại
    if (!$supplier = $CMSNT->get_row("SELECT * FROM `suppliers` WHERE `id` = '$supplier_id' ")) {
        die(json_encode(['status' => 'error', 'msg' => 'Nhà cung cấp không tồn tại']));
    }

    // Lấy danh sách categories
    $categories = $CMSNT->get_list("SELECT * FROM `categories` WHERE `supplier_id` = '$supplier_id' ORDER BY `id` DESC");

    $data = [];
    foreach ($categories as $cate) {
        // Lấy tên chuyên mục cha
        $parent_name = '';
        $parent_url = '';
        if ($cate['parent_id'] > 1) {
            $parent = $CMSNT->get_row("SELECT `name` FROM `categories` WHERE `id` = '" . $cate['parent_id'] . "' ");
            $parent_name = $parent ? $parent['name'] : '';
            $parent_url = base_url_admin('category-edit&id=' . $cate['parent_id']);
        }

        // Đếm số sản phẩm
        $total_products = $CMSNT->num_rows("SELECT * FROM `products` WHERE `category_id` = '" . $cate['id'] . "' ");

        $data[] = [
            'id' => $cate['id'],
            'name' => $cate['name'],
            'parent_id' => $cate['parent_id'],
            'parent_name' => $parent_name,
            'parent_url' => $parent_url,
            'total_products' => $total_products,
            'icon' => base_url($cate['icon']),
            'status' => $cate['status'],
            'edit_url' => base_url_admin('category-edit&id=' . $cate['id'])
        ];
    }

    die(json_encode([
        'status' => 'success',
        'data' => $data
    ]));
}

// Xem trước số đơn hàng sẽ bị ảnh hưởng khi dọn dẹp
if ($_POST['action'] == 'previewCleanupOrders') {
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (!$getUser = $CMSNT->get_row("SELECT * FROM `users` WHERE `token` = '" . check_string($_POST['token']) . "' AND `banned` = 0 AND `admin` != 0 ")) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập để sử dụng tính năng này')]));
    }
    if (checkPermission($getUser['admin'], 'delete_orders_product') != true) {
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }

    $days_to_keep = intval($_POST['days_to_keep']);
    $cleanup_type = check_string($_POST['cleanup_type']);

    if ($days_to_keep < 1) {
        die(json_encode(['status' => 'error', 'msg' => __('Số ngày giữ lại phải lớn hơn 0')]));
    }

    // Tính số giây tương ứng với số ngày
    $schedule = $days_to_keep * 24 * 60 * 60;

    // Đếm số đơn hàng sẽ bị ảnh hưởng
    $orders_count = $CMSNT->num_rows(" SELECT * FROM `product_order` WHERE " . time() . " - UNIX_TIMESTAMP(create_gettime) >= " . $schedule . " ");

    // Chỉ đếm số tài khoản khi loại dọn dẹp có ảnh hưởng đến tài khoản
    $response = [
        'status' => 'success',
        'count' => format_cash($orders_count)
    ];

    // Nếu không phải loại "Xóa đơn hàng, không xóa tài khoản" thì mới hiển thị số tài khoản
    if ($cleanup_type != 'delete_order_only') {
        $accounts_count = $CMSNT->num_rows(" SELECT * FROM `product_sold` WHERE " . time() . " - UNIX_TIMESTAMP(create_gettime) >= " . $schedule . " ");
        $response['accounts_count'] = format_cash($accounts_count);
    }

    die(json_encode($response));
}

die(json_encode([
    'status'    => 'error',
    'msg'       => __('Invalid data')
]));
