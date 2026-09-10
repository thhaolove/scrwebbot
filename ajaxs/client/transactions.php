<?php

/**
 * Transactions AJAX Handler
 * Handles AJAX requests for loading transactions with pagination and filtering
 */

define("IN_SITE", true);
require_once(__DIR__ . "/../../libs/db.php");
require_once(__DIR__ . "/../../libs/lang.php");
require_once(__DIR__ . "/../../libs/helper.php");
require_once(__DIR__ . "/../../config.php");

if (!isset($_POST['action'])) {
    die(json_encode(['status' => 'error', 'message' => 'The Request Not Found']));
}

// Kiểm tra CSRF token
checkCSRFAjax();

// loadTransactions action
if ($_POST['action'] == 'loadTransactions') {

    // User token validation
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'message' => 'Vui lòng đăng nhập']));
    }

    $userToken = validate_string($_POST['token'], 255);
    $getUser = $CMSNT->get_row_safe("SELECT * FROM `users` WHERE `token` = ? AND `banned` = 0", [$userToken]);

    if (!$getUser) {
        die(json_encode(['status' => 'error', 'message' => 'Vui lòng đăng nhập']));
    }

    // Pagination parameters
    $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
    $per_page = 10;
    $offset = ($page - 1) * $per_page;

    // Filter parameters
    $content_filter = isset($_POST['content']) ? validate_string($_POST['content'], 255, 1) : '';
    $shortByDate = isset($_POST['shortByDate']) ? validate_int($_POST['shortByDate'], 1, 3) : '';
    $time_filter = isset($_POST['time']) ? validate_string($_POST['time'], 50) : '';
    $type_filter = isset($_POST['type']) ? validate_string($_POST['type'], 10) : '';

    // Build WHERE clause
    $where_conditions = ["`user_id` = ?"];
    $params = [$getUser['id']];

    // Content filter
    if (!empty($content_filter)) {
        $where_conditions[] = '`noidung` LIKE ?';
        $params[] = '%' . $content_filter . '%';
    }

    // Type filter (plus = cộng tiền, minus = trừ tiền) - same as admin transactions
    if (!empty($type_filter)) {
        if ($type_filter == 'plus') {
            $where_conditions[] = '(`sotiensau` - `sotientruoc`) > 0';
        } elseif ($type_filter == 'minus') {
            $where_conditions[] = '(`sotiensau` - `sotientruoc`) < 0';
        }
    }

    // Short by date filter
    if (!empty($shortByDate)) {
        $currentDate = date("Y-m-d");
        $currentWeek = date("W");
        $currentMonth = date('m');
        $currentYear = date('Y');

        if ($shortByDate == 1) {
            $where_conditions[] = '`thoigian` LIKE ?';
            $params[] = '%' . $currentDate . '%';
        }
        if ($shortByDate == 2) {
            $where_conditions[] = 'YEAR(thoigian) = ? AND WEEK(thoigian, 1) = ?';
            $params[] = $currentYear;
            $params[] = $currentWeek;
        }
        if ($shortByDate == 3) {
            $where_conditions[] = 'MONTH(thoigian) = ? AND YEAR(thoigian) = ?';
            $params[] = $currentMonth;
            $params[] = $currentYear;
        }
    }

    // Time range filter
    if (!empty($time_filter)) {
        $create_date_1 = str_replace('-', '/', $time_filter);
        $create_date_1 = explode(' to ', $create_date_1);
        if (count($create_date_1) == 2 && $create_date_1[0] != $create_date_1[1]) {
            if (validate_date($create_date_1[0], 'Y/m/d') && validate_date($create_date_1[1], 'Y/m/d')) {
                $start_date = $create_date_1[0] . ' 00:00:00';
                $end_date = $create_date_1[1] . ' 23:59:59';
                $where_conditions[] = '`thoigian` >= ? AND `thoigian` <= ?';
                $params[] = $start_date;
                $params[] = $end_date;
            }
        }
    }

    $where_clause = implode(' AND ', $where_conditions);

    // Count total
    $count_result = $CMSNT->get_row_safe("SELECT COUNT(*) as total FROM `dongtien` WHERE $where_clause", $params);
    $total = $count_result ? (int)$count_result['total'] : 0;

    $total_pages = ceil($total / $per_page);
    $has_more = $page < $total_pages;

    // Get transactions
    $params_list = array_merge($params, [$offset, $per_page]);
    $transactions = $CMSNT->get_list_safe(
        "SELECT * FROM `dongtien` WHERE $where_clause ORDER BY `id` DESC LIMIT ?, ?",
        $params_list
    );

    // Generate HTML
    $html = '';
    foreach ($transactions as $row) {
        $change = $row['sotiensau'] - $row['sotientruoc'];
        $changeColor = $change > 0 ? 'green' : ($change < 0 ? 'red' : 'inherit');
        $changePrefix = $change > 0 ? '+' : ($change < 0 ? '-' : '');

        $html .= '<tr>';
        $html .= '<td><span class="data-date">' . date('d/m/Y H:i', strtotime($row['thoigian'])) . '</span></td>';
        $html .= '<td class="text-right"><b>' . format_currency($row['sotientruoc']) . '</b></td>';
        $html .= '<td class="text-right"><b style="color:' . $changeColor . ';">' . $changePrefix . format_currency($row['sotienthaydoi']) . '</b></td>';
        $html .= '<td class="text-right"><b style="color: var(--data-info);">' . format_currency($row['sotiensau']) . '</b></td>';
        $html .= '<td><small>' . htmlspecialchars($row['noidung']) . '</small></td>';
        $html .= '</tr>';
    }

    die(json_encode([
        'status' => 'success',
        'html' => $html,
        'has_more' => $has_more,
        'total' => $total
    ]));
}
