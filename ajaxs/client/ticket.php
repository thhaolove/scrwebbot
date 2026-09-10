<?php

define("IN_SITE", true);
require_once(__DIR__ . "/../../libs/db.php");
require_once(__DIR__ . "/../../libs/lang.php");
require_once(__DIR__ . "/../../libs/helper.php");
require_once(__DIR__ . "/../../config.php");
require_once(__DIR__ . "/../../libs/sendEmail.php");




if ($CMSNT->site('status_demo') != 0) {
    $data = json_encode([
        'status'    => 'error',
        'msg'       => __('This function cannot be used because this is a demo site')
    ]);
    die($data);
}
if (!isset($_POST['action'])) {
    $data = json_encode([
        'status'    => 'error',
        'msg'       => __('The Request Not Found')
    ]);
    die($data);
}

// Kiểm tra CSRF token cho tất cả request
checkCSRFAjax();

if ($_POST['action'] == 'replyTicket') {
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập')]));
    }
    if (!$getUser = $CMSNT->get_row_safe("SELECT * FROM `users` WHERE `token` = ? AND `banned` = 0 ", [validate_string($_POST['token'], 255)])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập')]));
    }
    if (empty($_POST['ticket_id'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Ticket không tồn tại')]));
    }
    if (empty($_POST['message'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng nhập tin nhắn')]));
    }
    if ($CMSNT->site('support_tickets_status') == 0) {
        die(json_encode(['status' => 'error', 'msg' => __('Chức năng này đã bị tắt bởi quản trị viên')]));
    }
    $ticket_id = validate_int($_POST['ticket_id'], 1);
    $message = validate_string($_POST['message'], 10000, 1);
    if ($ticket_id === false || $message === false) {
        die(json_encode(['status' => 'error', 'msg' => __('Dữ liệu không hợp lệ')]));
    }
    $isTicket = $CMSNT->get_row_safe("SELECT * FROM `support_tickets` WHERE `id` = ? AND `user_id` = ? ", [$ticket_id, $getUser['id']]);
    if (!$isTicket) {
        die(json_encode(['status' => 'error', 'msg' => __('Ticket không tồn tại')]));
    }
    $isInsert = $CMSNT->insert('support_messages', [
        'ticket_id'     => $ticket_id,
        'sender_type'   => 'user',
        'sender_id'     => $getUser['id'],
        'message'       => $message
    ]);
    if ($isInsert) {
        // Cập nhật trạng thái ticket thành "open"
        $CMSNT->update('support_tickets', [
            'status' => 'open',
            'updated_at' => gettime()
        ], "`id` = ?", [$ticket_id]);

        // Nếu trạng thái cũ là đã trả lời hoặc đóng thì báo Admin
        if ($isTicket['status'] != 'open') {

            // THÔNG BÁO VỀ TELEGRAM CHO ADMIN
            if ($CMSNT->site('support_tickets_telegram_message_reply') != '' && $CMSNT->site('support_tickets_telegram_chat_id') != '') {
                $my_text = $CMSNT->site('support_tickets_telegram_message_reply');
                $my_text = str_replace('{username}', $getUser['username'], $my_text);
                $my_text = str_replace('{ip}', myip(), $my_text);
                $my_text = str_replace('{device}', getUserAgent(), $my_text);
                $my_text = str_replace('{time}', gettime(), $my_text);
                $my_text = str_replace('{subject}', $isTicket['subject'], $my_text);
                $my_text = str_replace('{message}', $message, $my_text);
                $my_text = str_replace('{category}', $config_category_support_tickets[$isTicket['category']] ?? $isTicket['category'], $my_text);
                sendMessTelegram($my_text, '', $CMSNT->site('support_tickets_telegram_chat_id'));
            }

            // THÔNG BÁO VỀ MAIL ADMIN KHI USER TRẢ LỜI TICKET
            // if($CMSNT->site('email_temp_subject_reply_ticket') != ''){
            //     // Chuẩn bị dữ liệu thay thế
            //     $replace_data = [
            //         '{domain}'      => check_string($_SERVER['SERVER_NAME']),
            //         '{title}'       => $CMSNT->site('title'),
            //         '{username}'    => $getUser['username'],
            //         '{ip}'          => myip(),
            //         '{device}'      => getUserAgent(),
            //         '{time}'        => gettime(),
            //         '{subject}'     => $isTicket['subject'],
            //         '{category}'    => $config_category_support_tickets[$isTicket['category']] ?? $isTicket['category'],
            //         '{order_id}'    => $isTicket['order_id'] ?: __('Không có'),
            //         '{content}'     => $message
            //     ];
            //     // Template email subject
            //     $email_subject = $CMSNT->site('email_temp_subject_reply_ticket');
            //     $email_subject = str_replace(array_keys($replace_data), array_values($replace_data), $email_subject);
            //     // Template email content
            //     $email_content = $CMSNT->site('email_temp_content_reply_ticket');
            //     $email_content = str_replace(array_keys($replace_data), array_values($replace_data), $email_content);
            //     $bcc = $CMSNT->site('title');
            //     sendCSM($CMSNT->site('email'), $CMSNT->site('email'), $email_subject, $email_content, $bcc);
            // }
        }

        die(json_encode(['status' => 'success', 'msg' => __('Tin nhắn đã được gửi thành công')]));
    } else {
        die(json_encode(['status' => 'error', 'msg' => __('Lỗi khi gửi tin nhắn')]));
    }
}

if ($_POST['action'] == 'createTicket') {
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập')]));
    }
    if (!$getUser = $CMSNT->get_row_safe("SELECT * FROM `users` WHERE `token` = ? AND `banned` = 0 ", [validate_string($_POST['token'], 255)])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập')]));
    }
    if (empty($_POST['subject'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng nhập tiêu đề ticket')]));
    }
    if (empty($_POST['category'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng chọn chủ đề hỗ trợ')]));
    }
    if (empty($_POST['content'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng nhập nội dung ticket')]));
    }
    if ($CMSNT->site('support_tickets_status') == 0) {
        die(json_encode(['status' => 'error', 'msg' => __('Chức năng này đã bị tắt bởi quản trị viên')]));
    }

    // Xác thực Captcha
    $captchaResponse = check_string($_POST['captcha_response'] ?? $_POST['recaptcha'] ?? $_POST['cf-turnstile-response'] ?? '');
    $captchaResult = verifyCaptchaResponse($captchaResponse, myip(), 'add_ticket');
    if (!$captchaResult['success']) {
        die(json_encode(['status' => 'error', 'msg' => $captchaResult['error_message']]));
    }

    $subject = validate_string($_POST['subject'], 255, 1);
    $category = validate_string($_POST['category'], 50, 1);
    $order_id = isset($_POST['order_id']) ? validate_alphanumeric($_POST['order_id'], 100) : NULL;
    $content = validate_string($_POST['content'], 10000, 1);


    if (!empty($order_id) && $category == 'order') {
        $isOrder = $CMSNT->get_row_safe("SELECT * FROM `product_orders` WHERE `trans_id` = ? AND `user_id` = ? ", [$order_id, $getUser['id']]);
        if (!$isOrder) {
            die(json_encode(['status' => 'error', 'msg' => __('Mã đơn hàng không tồn tại')]));
        }
        // Lấy mã đơn hàng nếu có
        $order_id = $isOrder['id'];
        // Nếu mã đơn hàng này đã tạo ticket trước đó rồi thì không cho tạo nữa.
        if ($CMSNT->get_row_safe("SELECT COUNT(id) as c FROM `support_tickets` WHERE `user_id` = ? AND `order_id` = ? ", [$getUser['id'], $order_id])['c'] > 0) {
            die(json_encode(['status' => 'error', 'msg' => __('Mã đơn hàng này đã tạo ticket trước đó rồi, không thể tạo thêm.')]));
        }
    }

    if ($CMSNT->get_row_safe("SELECT COUNT(id) as c FROM `support_tickets` WHERE `user_id` = ? AND `status` = 'open' ", [$getUser['id']])['c'] >= 5) {
        die(json_encode(['status' => 'error', 'msg' => __('Bạn đã có 5 yêu cầu hỗ trợ đang mở, vui lòng đợi xử lý')]));
    }

    $isInsert = $CMSNT->insert('support_tickets', [
        'user_id'           => $getUser['id'],
        'order_id'          => $order_id,
        'category'          => $category,
        'subject'           => $subject,
        'content'           => $content,
        'status'            => 'open',
        'created_at'        => gettime()
    ]);
    if ($isInsert) {

        // Tạo tin nhắn mới cho ticket
        $CMSNT->insert('support_messages', [
            'ticket_id'     => $isInsert,
            'sender_type'   => 'user',
            'sender_id'     => $getUser['id'],
            'message'       => $content
        ]);

        // // THÔNG BÁO VỀ MAIL ADMIN KHI USER TẠO TICKET
        // if($CMSNT->site('email_temp_subject_warning_ticket') != ''){

        //     $replace_data = [
        //         '{domain}'      => check_string($_SERVER['SERVER_NAME']),
        //         '{title}'       => $CMSNT->site('title'),
        //         '{username}'    => $getUser['username'],
        //         '{ip}'          => myip(),
        //         '{device}'      => getUserAgent(),
        //         '{time}'        => gettime(),
        //         '{subject}'     => $subject,
        //         '{category}'    => $config_category_support_tickets[$category] ?? $category,
        //         '{order_id}'    => $order_id,
        //         '{content}'     => $content
        //     ];
        //     // Template email subject
        //     $email_subject = $CMSNT->site('email_temp_subject_warning_ticket');
        //     $email_subject = str_replace(array_keys($replace_data), array_values($replace_data), $email_subject);
        //     // Template email content
        //     $email_content = $CMSNT->site('email_temp_content_warning_ticket');
        //     $email_content = str_replace(array_keys($replace_data), array_values($replace_data), $email_content);
        //     $bcc = $CMSNT->site('title');
        //     sendCSM($CMSNT->site('email'), $CMSNT->site('email'), $email_subject, $email_content, $bcc);
        // }

        // THÔNG BÁO VỀ TELEGRAM CHO ADMIN
        if ($CMSNT->site('support_tickets_telegram_message') != '' && $CMSNT->site('support_tickets_telegram_chat_id') != '') {

            $my_text = $CMSNT->site('support_tickets_telegram_message');
            $my_text = str_replace('{username}', $getUser['username'], $my_text);
            $my_text = str_replace('{ip}', myip(), $my_text);
            $my_text = str_replace('{device}', getUserAgent(), $my_text);
            $my_text = str_replace('{time}', gettime(), $my_text);
            $my_text = str_replace('{subject}', $subject, $my_text);
            $my_text = str_replace('{content}', $content, $my_text);
            $my_text = str_replace('{status}', $config_status_support_tickets[$isTicket['status']] ?? $isTicket['status'], $my_text);
            $my_text = str_replace('{category}', $config_category_support_tickets[$category] ?? $category, $my_text);
            sendMessTelegram($my_text, '', $CMSNT->site('support_tickets_telegram_chat_id'));
        }

        // THÔNG BÁO VỀ MAIL CHO USER KHI TẠO TICKET THÀNH CÔNG
        if (!empty($getUser['email']) && $CMSNT->site('email_temp_subject_ticket_created_user') != '') {
            require_once(__DIR__ . "/../../libs/SMTPMailer.php");
            $mailer = new SMTPMailer($CMSNT);

            $replace_data = [
                '{domain}' => check_string($_SERVER['SERVER_NAME']),
                '{title}' => $CMSNT->site('title'),
                '{username}' => $getUser['username'],
                '{ticket_id}' => $isInsert,
                '{subject}' => $subject,
                '{category}' => $config_category_support_tickets[$category] ?? $category,
                '{order_id}' => $order_id ?: __('Không có'),
                '{content}' => nl2br(htmlspecialchars($content)),
                '{time}' => gettime(),
                '{ip}' => myip(),
                '{device}' => getUserAgent()
            ];

            $email_subject = $CMSNT->site('email_temp_subject_ticket_created_user');
            $email_subject = str_replace(array_keys($replace_data), array_values($replace_data), $email_subject);

            $email_content = $CMSNT->site('email_temp_content_ticket_created_user');
            $email_content = str_replace(array_keys($replace_data), array_values($replace_data), $email_content);

            $mailer->queueEmail(
                $getUser['email'],
                $getUser['username'],
                $email_subject,
                $email_content,
                2,
                ['type' => 'ticket_created_user', 'ticket_id' => $isInsert, 'user_id' => $getUser['id']]
            );
        }

        // Ghi log hành động
        $CMSNT->insert("logs", [
            'user_id'       => $getUser['id'],
            'ip'            => myip(),
            'device'        => getUserAgent(),
            'createdate'    => gettime(),
            'action'        => __('Tạo yêu cầu hỗ trợ')
        ]);

        die(json_encode(['status' => 'success', 'msg' => __('Yêu cầu hỗ trợ đã được tạo thành công')]));
    } else {
        die(json_encode(['status' => 'error', 'msg' => __('Lỗi khi tạo yêu cầu hỗ trợ')]));
    }
}


if ($_POST['action'] == 'getNewMessages') {
    $ticket_id = validate_int($_POST['ticket_id'], 1);
    $last_message_id = validate_int($_POST['last_message_id'], 0);
    $token = validate_string($_POST['token'], 255);
    if ($ticket_id === false || $last_message_id === false || $token === false) {
        die(json_encode(['status' => 'error', 'msg' => __('Token không hợp lệ')]));
    }
    if (empty($token)) {
        die(json_encode(['status' => 'error', 'msg' => __('Token không hợp lệ')]));
    }
    if (!$getUser = $CMSNT->get_row_safe("SELECT * FROM `users` WHERE `token` = ? AND `banned` = 0 ", [$token])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập')]));
    }

    // Validate ticket belongs to user
    $ticket = $CMSNT->get_row_safe("SELECT * FROM `support_tickets` WHERE `id` = ? AND `user_id` = ?", [$ticket_id, $getUser['id']]);
    if (!$ticket) {
        die(json_encode(['status' => 'error', 'msg' => __('Ticket không tồn tại')]));
    }

    // Get new messages after last_message_id
    $where_condition = "sm.ticket_id = ?";
    $params = [$ticket_id];
    if ($last_message_id > 0) {
        $where_condition .= " AND sm.id > ?";
        $params[] = $last_message_id;
    }

    // Lấy danh sách tin nhắn mới từ database
    $messages = $CMSNT->get_list_safe(
        "SELECT sm.*, u.username 
        FROM `support_messages` sm 
        LEFT JOIN `users` u ON (sm.sender_id = u.id AND sm.sender_type = 'user') 
        WHERE $where_condition 
        ORDER BY sm.created_at ASC",
        $params
    );

    /* 
     * Giải thích query:
     * - SELECT sm.*, u.username: Lấy tất cả trường từ bảng support_messages và thêm username từ bảng users
     * - FROM support_messages sm: Bảng chính chứa tin nhắn support
     * - LEFT JOIN users u: Kết nối với bảng users để lấy tên user (chỉ khi sender_type = 'user')
     * - ON (sm.sender_id = u.id AND sm.sender_type = 'user'): Điều kiện join - chỉ join khi tin nhắn từ user
     * - WHERE $where_condition: Điều kiện lọc (ticket_id và message_id > last_message_id)
     * - ORDER BY sm.created_at ASC: Sắp xếp theo thời gian tạo từ cũ đến mới
     */

    if (empty($messages)) {
        die(json_encode(['status' => 'success', 'messages' => []]));
    }

    // Format messages
    $formatted_messages = [];
    foreach ($messages as $msg) {
        $formatted_msg = [
            'id' => $msg['id'],
            'sender_type' => $msg['sender_type'],
            'message' => nl2br(htmlspecialchars($msg['message'])),
            'formatted_time' => date('H:i d/m/Y', strtotime($msg['created_at'])),
            'time_ago' => timeAgo(strtotime($msg['created_at'])),
            'created_at' => $msg['created_at']
        ];

        if ($msg['sender_type'] == 'user') {
            $formatted_msg['username'] = $msg['username'] ?: __('User');
        }

        $formatted_messages[] = $formatted_msg;
    }

    die(json_encode([
        'status' => 'success',
        'messages' => $formatted_messages,
        'count' => count($formatted_messages)
    ]));
}


// Đóng ticket bởi user
if ($_POST['action'] == 'closeTicket') {
    if (empty($_POST['token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập')]));
    }
    if (!$getUser = $CMSNT->get_row_safe("SELECT * FROM `users` WHERE `token` = ? AND `banned` = 0 ", [validate_string($_POST['token'], 255)])) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng đăng nhập')]));
    }
    if (empty($_POST['ticket_id'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Ticket không tồn tại')]));
    }

    $ticket_id = validate_int($_POST['ticket_id'], 1);
    if ($ticket_id === false) {
        die(json_encode(['status' => 'error', 'msg' => __('Dữ liệu không hợp lệ')]));
    }

    // Kiểm tra ticket thuộc về user và chưa đóng
    $isTicket = $CMSNT->get_row_safe("SELECT * FROM `support_tickets` WHERE `id` = ? AND `user_id` = ? ", [$ticket_id, $getUser['id']]);
    if (!$isTicket) {
        die(json_encode(['status' => 'error', 'msg' => __('Ticket không tồn tại')]));
    }
    if ($isTicket['status'] == 'closed') {
        die(json_encode(['status' => 'error', 'msg' => __('Ticket đã được đóng trước đó')]));
    }

    // Cập nhật trạng thái ticket thành "closed"
    $isUpdate = $CMSNT->update('support_tickets', [
        'status' => 'closed',
        'updated_at' => gettime()
    ], "`id` = ?", [$ticket_id]);

    if ($isUpdate) {
        // Ghi log hành động
        $CMSNT->insert("logs", [
            'user_id'       => $getUser['id'],
            'ip'            => myip(),
            'device'        => getUserAgent(),
            'createdate'    => gettime(),
            'action'        => __('Đóng yêu cầu hỗ trợ') . ' #' . $ticket_id
        ]);

        die(json_encode(['status' => 'success', 'msg' => __('Ticket đã được đóng thành công')]));
    } else {
        die(json_encode(['status' => 'error', 'msg' => __('Lỗi khi đóng ticket')]));
    }
}

/**
 * Load Tickets via AJAX (for AJAX table loading)
 */
if ($_POST['action'] == 'loadTickets') {
    // Kiểm tra CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Token không hợp lệ')]));
    }

    // Kiểm tra token từ POST
    $user_token = isset($_POST['token']) ? validate_alphanumeric($_POST['token'], 255) : false;
    if ($user_token === false) {
        die(json_encode([
            'status' => 'error',
            'msg' => __('Phiên đăng nhập không hợp lệ')
        ]));
    }

    $getUser = $CMSNT->get_row_safe("SELECT * FROM `users` WHERE `token` = ? AND `banned` = 0", [$user_token]);
    if (!$getUser) {
        die(json_encode([
            'status' => 'error',
            'msg' => __('Phiên đăng nhập không hợp lệ')
        ]));
    }

    $page = isset($_POST['page']) ? max(1, (int) $_POST['page']) : 1;
    $per_page = 10;
    $offset = ($page - 1) * $per_page;

    $status_filter = isset($_POST['status']) ? validate_string($_POST['status'], 30) : '';
    $subject_filter = isset($_POST['subject']) ? trim(validate_string($_POST['subject'], 100)) : '';
    $category_filter = isset($_POST['category']) ? validate_string($_POST['category'], 50) : '';
    $time_filter = isset($_POST['time']) ? validate_string($_POST['time'], 50) : '';

    $where_conditions = ['`user_id` = ?'];
    $params = [$getUser['id']];

    // Status filter
    if (!empty($status_filter) && in_array($status_filter, ['open', 'pending', 'answered', 'closed'])) {
        $where_conditions[] = '`status` = ?';
        $params[] = $status_filter;
    }

    // Subject search
    if (!empty($subject_filter)) {
        $where_conditions[] = '`subject` LIKE ?';
        $params[] = '%' . $subject_filter . '%';
    }

    // Category filter
    if (!empty($category_filter)) {
        $where_conditions[] = '`category` = ?';
        $params[] = $category_filter;
    }

    // Time filter (date range)
    if (!empty($time_filter)) {
        $create_date_1 = str_replace('-', '/', $time_filter);
        $create_date_1 = explode(' to ', $create_date_1);
        if (count($create_date_1) == 2 && $create_date_1[0] != $create_date_1[1]) {
            if (validate_date($create_date_1[0], 'Y/m/d') && validate_date($create_date_1[1], 'Y/m/d')) {
                $start_date = $create_date_1[0] . ' 00:00:00';
                $end_date = $create_date_1[1] . ' 23:59:59';
                $where_conditions[] = '`created_at` >= ? AND `created_at` <= ?';
                $params[] = $start_date;
                $params[] = $end_date;
            }
        }
    }

    $where_clause = implode(' AND ', $where_conditions);

    // Đếm tổng
    $count_result = $CMSNT->get_row_safe("SELECT COUNT(*) as total FROM `support_tickets` WHERE $where_clause", $params);
    $total_tickets = $count_result ? (int)$count_result['total'] : 0;

    $total_pages = ceil($total_tickets / $per_page);
    $has_more = $page < $total_pages;

    // Lấy tickets
    $params_list = array_merge($params, [$offset, $per_page]);

    $tickets = $CMSNT->get_list_safe("
        SELECT * FROM `support_tickets` 
        WHERE $where_clause 
        ORDER BY `id` DESC 
        LIMIT ?, ?
    ", $params_list);

    // Status icons
    $status_icons = [
        'open' => 'fa-envelope-open',
        'pending' => 'fa-clock',
        'answered' => 'fa-check-circle',
        'closed' => 'fa-lock'
    ];

    // Build HTML rows
    $html = '';
    $mobile_html = '';

    foreach ($tickets as $ticket) {
        // Status info
        $status_class = 'status-' . $ticket['status'];
        $status_icon = isset($status_icons[$ticket['status']]) ? $status_icons[$ticket['status']] : 'fa-question-circle';
        $status_label = isset($config_status_support_tickets[$ticket['status']]) ? $config_status_support_tickets[$ticket['status']] : $ticket['status'];
        $category_label = isset($config_category_support_tickets[$ticket['category']]) ? $config_category_support_tickets[$ticket['category']] : $ticket['category'];

        // Desktop table row
        $html .= '<tr>';
        $html .= '<td style="text-align: center;">';
        $html .= '<a href="' . base_url('ticket-detail/' . $ticket['id']) . '" class="btn-view-ticket">';
        $html .= '<i class="fa-solid fa-eye"></i> ' . __('Xem');
        $html .= '</a></td>';
        $html .= '<td>';
        $html .= '<a href="' . base_url('ticket-detail/' . $ticket['id']) . '" class="ticket-subject-link">';
        $html .= htmlspecialchars(mb_strlen($ticket['subject']) > 35 ? mb_substr($ticket['subject'], 0, 35) . '...' : $ticket['subject']);
        $html .= '</a></td>';
        $html .= '<td>';
        $html .= '<span class="ticket-category"><i class="fa-solid fa-tag"></i> ' . htmlspecialchars($category_label) . '</span>';
        $html .= '</td>';
        $html .= '<td style="text-align: center;">';
        $html .= '<span class="ticket-status ' . $status_class . '"><i class="fa-solid ' . $status_icon . '"></i> ' . $status_label . '</span>';
        $html .= '</td>';
        $html .= '<td><span class="ticket-date">' . date('d/m/Y H:i', strtotime($ticket['created_at'])) . '</span></td>';
        $html .= '<td><span class="ticket-date">' . date('d/m/Y H:i', strtotime($ticket['updated_at'])) . '</span></td>';
        $html .= '</tr>';

        // Mobile card
        $mobile_html .= '<div class="ticket-card-mobile">';
        $mobile_html .= '<div class="ticket-card-row1">';
        $mobile_html .= '<div class="ticket-card-left">';
        $mobile_html .= '<a href="' . base_url('ticket-detail/' . $ticket['id']) . '" class="ticket-card-subject">' . htmlspecialchars(mb_strlen($ticket['subject']) > 40 ? mb_substr($ticket['subject'], 0, 40) . '...' : $ticket['subject']) . '</a>';
        $mobile_html .= '</div>';
        $mobile_html .= '<div class="ticket-card-status">';
        $mobile_html .= '<span class="ticket-status ' . $status_class . '"><i class="fa-solid ' . $status_icon . '"></i> ' . $status_label . '</span>';
        $mobile_html .= '</div>';
        $mobile_html .= '</div>';
        $mobile_html .= '<div class="ticket-card-row2">';
        $mobile_html .= '<span class="ticket-card-category"><i class="fa-solid fa-tag"></i> ' . htmlspecialchars($category_label) . '</span>';
        $mobile_html .= '<span class="ticket-card-date">' . date('d/m/Y H:i', strtotime($ticket['created_at'])) . '</span>';
        $mobile_html .= '</div>';
        $mobile_html .= '<div class="ticket-card-row3">';
        $mobile_html .= '<a href="' . base_url('ticket-detail/' . $ticket['id']) . '" class="ticket-card-detail-link"><i class="fa-solid fa-eye"></i> ' . __('Xem chi tiết') . '</a>';
        $mobile_html .= '</div>';
        $mobile_html .= '</div>';
    }

    die(json_encode([
        'status' => 'success',
        'html' => $html,
        'mobile_html' => $mobile_html,
        'has_more' => $has_more,
        'total' => $total_tickets
    ]));
}

die(json_encode([
    'status'    => 'error',
    'msg'       => __('Invalid data')
]));
