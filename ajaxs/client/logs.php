<?php

/**
 * Logs AJAX Handler
 * Handles AJAX requests for loading activity logs with pagination and filtering
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

// loadLogs action
if ($_POST['action'] == 'loadLogs') {

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
    $content_filter = isset($_POST['content']) ? validate_string($_POST['content'], 255, 2) : '';
    $ip_filter = isset($_POST['ip']) ? validate_ip($_POST['ip']) : '';
    $shortByDate = isset($_POST['shortByDate']) ? validate_int($_POST['shortByDate'], 1, 3) : '';
    $time_filter = isset($_POST['time']) ? validate_string($_POST['time'], 50) : '';

    // Build WHERE clause
    $where_conditions = ["`user_id` = ?"];
    $params = [$getUser['id']];

    // Content filter
    if (!empty($content_filter)) {
        $where_conditions[] = '`action` LIKE ?';
        $params[] = '%' . $content_filter . '%';
    }

    // IP filter
    if (!empty($ip_filter)) {
        $where_conditions[] = '`ip` LIKE ?';
        $params[] = '%' . $ip_filter . '%';
    }

    // Short by date filter
    if (!empty($shortByDate)) {
        $currentDate = date("Y-m-d");
        $currentWeek = date("W");
        $currentMonth = date('m');
        $currentYear = date('Y');

        if ($shortByDate == 1) {
            $where_conditions[] = '`createdate` LIKE ?';
            $params[] = '%' . $currentDate . '%';
        }
        if ($shortByDate == 2) {
            $where_conditions[] = 'YEAR(createdate) = ? AND WEEK(createdate, 1) = ?';
            $params[] = $currentYear;
            $params[] = $currentWeek;
        }
        if ($shortByDate == 3) {
            $where_conditions[] = 'MONTH(createdate) = ? AND YEAR(createdate) = ?';
            $params[] = $currentMonth;
            $params[] = $currentYear;
        }
    }

    // Time range filter
    if (!empty($time_filter)) {
        $create_date_1 = str_replace('-', '/', $time_filter);
        $create_date_1 = explode(' to ', $create_date_1);
        if (count($create_date_1) == 2 && $create_date_1[0] != $create_date_1[1]) {
            if (validate_date($create_date_1[0], 'Y/m/d') !== false && validate_date($create_date_1[1], 'Y/m/d') !== false) {
                $start_date = $create_date_1[0] . ' 00:00:00';
                $end_date = $create_date_1[1] . ' 23:59:59';
                $where_conditions[] = '`createdate` >= ? AND `createdate` <= ?';
                $params[] = $start_date;
                $params[] = $end_date;
            }
        }
    }

    $where_clause = implode(' AND ', $where_conditions);

    // Count total
    $count_result = $CMSNT->get_row_safe("SELECT COUNT(*) as total FROM `logs` WHERE $where_clause", $params);
    $total = $count_result ? (int)$count_result['total'] : 0;

    $total_pages = ceil($total / $per_page);
    $has_more = $page < $total_pages;

    // Get logs
    $params_list = array_merge($params, [$offset, $per_page]);
    $logs = $CMSNT->get_list_safe(
        "SELECT * FROM `logs` WHERE $where_clause ORDER BY `id` DESC LIMIT ?, ?",
        $params_list
    );

    // Generate HTML
    $html = '';
    foreach ($logs as $row) {
        $maskedIp = '***.***.' . substr($row['ip'], -6);

        $html .= '<tr>';
        $html .= '<td><span class="data-date">' . date('d/m/Y H:i', strtotime($row['createdate'])) . '</span></td>';
        $html .= '<td>' . htmlspecialchars($row['action']) . '</td>';
        $html .= '<td><span class="data-category"><i class="fa-solid fa-globe"></i> ' . $maskedIp . '</span></td>';
        $html .= '</tr>';
    }

    die(json_encode([
        'status' => 'success',
        'html' => $html,
        'has_more' => $has_more,
        'total' => $total
    ]));
}
