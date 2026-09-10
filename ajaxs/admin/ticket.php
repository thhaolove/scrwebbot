<?php

define("IN_SITE", true);
require_once(__DIR__ . "/../../libs/db.php");
require_once(__DIR__ . "/../../libs/lang.php");
require_once(__DIR__ . "/../../libs/helper.php");
require_once(__DIR__ . "/../../config.php");
require_once(__DIR__ . '/../../models/is_admin.php');
require_once(__DIR__."/../../libs/sendEmail.php");

if (!isset($_POST['action'])) {
    $data = json_encode([
        'status'    => 'error',
        'msg'       => 'The Request Not Found'
    ]);
    die($data);
}

// Kiểm tra CSRF token cho tất cả request
checkCSRFAjax();

$action = check_string($_POST['action']);
$_POST['action'] = $action;

$demoRestrictedActions = [
    'changeStatusTicket',
    'deleteTicket',
    'replyTicket',
    'saveAdminNote',
    'createQuickReply',
    'updateQuickReply',
    'deleteQuickReply',
    'bulkDeleteTickets',
    'bulkChangeStatus',
    'createTicketForUser',
    'recallAdminMessage'
];

if ($CMSNT->site('status_demo') != 0 && in_array($action, $demoRestrictedActions, true)) {
    die(json_encode(['status' => 'error', 'msg' => __('Chức năng này không thể sử dụng trên website demo')]));
}

// Lấy toàn bộ hội thoại cho admin
if($_POST['action'] == 'get_ticket_conversation_admin') {
    if(checkPermission($getUser['admin'], 'view_ticket') != true){
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }

    $ticket_id = intval(check_string($_POST['ticket_id']));

    if(!$ticket = $CMSNT->get_row("
        SELECT t.*, u.username, u.email, u.money, u.id AS user_id, u.update_date, u.time_session
        FROM `support_tickets` t
        LEFT JOIN `users` u ON t.user_id = u.id
        WHERE t.id = '$ticket_id'
    ")){
        die(json_encode(['status' => 'error', 'msg' => __('Ticket không tồn tại')]));
    }

    $statusHtml = display_status_support_tickets($ticket['status']);
    $categoryText = $config_category_support_tickets[$ticket['category']] ?? $ticket['category'];

    $isUserOnline = !empty($ticket['time_session']) && ($ticket['time_session'] >= time() - 300);

    $ticketData = [
        'id'             => (int)$ticket['id'],
        'subject'        => $ticket['subject'],
        'status'         => $ticket['status'],
        'status_text'    => strip_tags($statusHtml),
        'status_html'    => $statusHtml,
        'category'       => $ticket['category'],
        'category_text'  => $categoryText,
        'created_at'     => date('d/m/Y H:i', strtotime($ticket['created_at'])),
        'updated_at'     => date('d/m/Y H:i', strtotime($ticket['updated_at'])),
        'username'       => $ticket['username'],
        'email'          => $ticket['email'],
        'user_id'        => (int)$ticket['user_id'],
        'avatar'         => getGravatarUrl($ticket['email']),
        'user_edit_link' => base_url_admin('user-edit&id='.$ticket['user_id']),
        'tickets_link'   => base_url_admin('tickets&username='.$ticket['username']),
        'is_online'      => $isUserOnline ? 1 : 0,
        'admin_note'     => (string)($ticket['admin_note'] ?? '')
    ];

    $user_orders = $CMSNT->get_row("SELECT COUNT(id) as total, SUM(final_amount) as spent FROM product_orders WHERE user_id = '".$ticket['user_id']."'");
    $user_tickets = $CMSNT->get_row("SELECT COUNT(id) as total FROM support_tickets WHERE user_id = '".$ticket['user_id']."'");
    $customerData = [
        'balance'     => format_cash($ticket['money']),
        'spent'       => format_cash($user_orders['spent'] ?? 0),
        'orders'      => (int)($user_orders['total'] ?? 0),
        'tickets'     => (int)($user_tickets['total'] ?? 0),
        'last_active' => !empty($ticket['update_date']) ? timeAgo($ticket['time_session']) : null,
        'online'      => $isUserOnline ? 1 : 0,
        'edit_link'   => base_url_admin('user-edit&id='.$ticket['user_id'])
    ];

    $orderData = ['exists' => false];
    if (!empty($ticket['order_id'])) {
        if ($order_info = $CMSNT->get_row("SELECT po.*, p.name as product_name FROM `product_orders` po LEFT JOIN `products` p ON po.product_id = p.id WHERE po.`id` = '".$ticket['order_id']."'")) {
            $quantity_ordered = intval($order_info['quantity']);
            $pay_total        = floatval($order_info['final_amount']);
            $status_order = $order_info['status'];
            
            // Status labels cho product orders
            $product_order_status_labels = [
                'pending' => __('Chờ xử lý'),
                'processing' => __('Đang xử lý'),
                'completed' => __('Hoàn thành'),
                'cancelled' => __('Đã hủy'),
                'cancelled_no_refund' => __('Hủy không hoàn tiền')
            ];
            $status_text = isset($product_order_status_labels[$status_order]) ? $product_order_status_labels[$status_order] : ucfirst($status_order);

            $orderData = [
                'exists'       => true,
                'trans_id'     => $order_info['trans_id'],
                'status'       => $status_order,
                'status_text'  => $status_text,
                'status_html'  => display_product_order_status($status_order),
                'service_name' => $order_info['product_name'] ?? '',
                'link'         => '',
                'quantity'     => number_format($quantity_ordered),
                'completed'    => number_format($quantity_ordered),
                'remains'      => number_format(0),
                'pay'          => format_cash($pay_total),
                'profit'       => null,
                'note'         => $order_info['note'] ?? '',
                'note_time'    => !empty($order_info['created_at']) ? date('d/m/Y H:i', strtotime($order_info['created_at'])) : '',
                'edit_link'    => base_url_admin('product-order-edit&id='.$order_info['id'])
            ];
        }
    }

    $messages = $CMSNT->get_list("
        SELECT sm.*,
               u.username AS user_username,
               u.email    AS user_email,
               a.username AS admin_username,
               a.email    AS admin_email
        FROM `support_messages` sm 
        LEFT JOIN `users` u ON (sm.sender_id = u.id AND sm.sender_type = 'user') 
        LEFT JOIN `users` a ON (sm.sender_id = a.id AND sm.sender_type = 'admin')
        WHERE sm.ticket_id = '$ticket_id'
        ORDER BY sm.created_at ASC
        LIMIT 500
    ");

    $formatted_messages = [];
    foreach($messages as $msg){
        $isAdmin = $msg['sender_type'] == 'admin';
        $username = $isAdmin
            ? (($msg['admin_username'] ?? '') ?: __('Admin'))
            : (($msg['user_username'] ?? '') ?: ($ticket['username'] ?? __('User')));
        $email = $isAdmin
            ? (($msg['admin_email'] ?? '') ?: '')
            : (($msg['user_email'] ?? '') ?: ($ticket['email'] ?? ''));
        $avatarEmail = $email ?: ($isAdmin ? ($getUser['email'] ?? '') : ($ticket['email'] ?? ''));
        $avatar = getGravatarUrl($avatarEmail);
        $created = $msg['created_at'];

        $formatted_messages[] = [
            'id'             => (int)$msg['id'],
            'sender_type'    => $msg['sender_type'],
            'message'        => nl2br(htmlspecialchars($msg['message'])),
            'username'       => $username,
            'email'          => $email,
            'avatar'         => $avatar,
            'created_at'     => $created,
            'formatted_time' => date('H:i d/m/Y', strtotime($created)),
            'time'           => date('H:i', strtotime($created)),
            'date'           => date('d/m/Y', strtotime($created)),
            'time_ago'       => timeAgo(strtotime($created)),
            'user_online'    => $isAdmin ? null : ($isUserOnline ? 1 : 0),
            'can_recall'     => $isAdmin && intval($msg['sender_id']) === intval($getUser['id'])
        ];
    }

    die(json_encode([
        'status'   => 'success',
        'ticket'   => $ticketData,
        'customer' => $customerData,
        'order'    => $orderData,
        'messages' => $formatted_messages
    ]));
}


// Thay đổi trạng thái ticket
if($_POST['action'] == 'changeStatusTicket') {
    // Kiểm tra quyền
    if(checkPermission($getUser['admin'], 'edit_ticket') != true){
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }

    $id = intval(check_string($_POST['id']));
    $status = check_string($_POST['status']);
    
    if(!in_array($status, array_keys($config_status_support_tickets))){
        die(json_encode(['status' => 'error', 'msg' => __('Trạng thái không hợp lệ')]));
    }
    
    // Kiểm tra ticket có tồn tại không
    if(!$ticket = $CMSNT->get_row("SELECT * FROM `support_tickets` WHERE `id` = '$id'")){
        die(json_encode(['status' => 'error', 'msg' => __('Ticket không tồn tại')]));
    }
    
    // Cập nhật trạng thái
    $isUpdate = $CMSNT->update('support_tickets', [
        'status' => $status
    ], "`id` = '$id'");
    
    if($isUpdate){
        // Ghi log hành động

        $CMSNT->insert("logs", [
            'user_id'       => $getUser['id'],
            'ip'            => myip(),
            'device'        => getUserAgent(),
            'createdate'    => gettime(),
            'action'        => sprintf(__('Thay đổi trạng thái ticket #%d thành %s'), $id, $config_status_support_tickets[$status])
        ]);
        
        die(json_encode(['status' => 'success', 'msg' => __('Cập nhật trạng thái thành công')]));
    } else {
        die(json_encode(['status' => 'error', 'msg' => __('Có lỗi xảy ra, vui lòng thử lại')]));
    }
}

// Xóa ticket
if($_POST['action'] == 'deleteTicket') {
    // Kiểm tra quyền
    if(checkPermission($getUser['admin'], 'edit_ticket') != true){
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }

    $id = intval(check_string($_POST['id']));
    
    // Kiểm tra ticket có tồn tại không
    if(!$ticket = $CMSNT->get_row("SELECT * FROM `support_tickets` WHERE `id` = '$id'")){
        die(json_encode(['status' => 'error', 'msg' => __('Ticket không tồn tại')]));
    }
    
    // Xóa tất cả tin nhắn của ticket này trước
    $CMSNT->remove('support_messages', "`ticket_id` = '$id'");
    
    // Xóa ticket
    $isDelete = $CMSNT->remove('support_tickets', "`id` = '$id'");
    
    if($isDelete){
        // Ghi log hành động
        $CMSNT->insert("logs", [
            'user_id'       => $getUser['id'],
            'ip'            => myip(),
            'device'        => getUserAgent(),
            'createdate'    => gettime(),
            'action'        => __('Xóa ticket')." #$id ({$ticket['subject']})"
        ]);

        die(json_encode(['status' => 'success', 'msg' => __('Xóa ticket thành công')]));
    } else {
        die(json_encode(['status' => 'error', 'msg' => __('Có lỗi xảy ra, vui lòng thử lại')]));
    }
}

// Trả lời ticket (cho admin)
if($_POST['action'] == 'replyTicket') {
    // Kiểm tra quyền
    if(checkPermission($getUser['admin'], 'edit_ticket') != true){
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }

    $ticket_id = intval(check_string($_POST['ticket_id']));
    $message = trim(check_string($_POST['message']));
    
    // Validate
    if(empty($message)){
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng nhập nội dung tin nhắn')]));
    }
    
    if(strlen($message) > 5000){
        die(json_encode(['status' => 'error', 'msg' => __('Nội dung tin nhắn quá dài (tối đa 5000 ký tự)')]));
    }
    
    // Kiểm tra ticket có tồn tại không
    if(!$ticket = $CMSNT->get_row("
        SELECT t.*, u.username AS user_username, u.email AS user_email, u.time_session
        FROM `support_tickets` t
        LEFT JOIN `users` u ON t.user_id = u.id
        WHERE t.id = '$ticket_id'
    ")){
        die(json_encode(['status' => 'error', 'msg' => __('Ticket không tồn tại')]));
    }
    $isUserOnline = !empty($ticket['time_session']) && ($ticket['time_session'] >= time() - 300);
    if(empty($ticket['username'])) {
        $ticket['username'] = $ticket['user_username'];
    }
    if(empty($ticket['email'])) {
        $ticket['email'] = $ticket['user_email'];
    }
    
    // Kiểm tra ticket có bị đóng không
    if($ticket['status'] == 'closed'){
        die(json_encode(['status' => 'error', 'msg' => __('Không thể trả lời ticket đã đóng')]));
    }
    
    // Thêm tin nhắn mới
    $isInsert = $CMSNT->insert('support_messages', [
        'ticket_id' => $ticket_id,
        'sender_id' => $getUser['id'],
        'sender_type' => 'admin',
        'message' => $message
    ]);
    
    if($isInsert){
        // Cập nhật trạng thái ticket thành "answered"
        $CMSNT->update('support_tickets', [
            'status' => 'answered',
            'updated_at'    => gettime()
        ], "`id` = '$ticket_id'");
        
        // Ghi log hành động

        $CMSNT->insert("logs", [
            'user_id'       => $getUser['id'],
            'ip'            => myip(),
            'device'        => getUserAgent(),
            'createdate'    => gettime(),
            'action'        => __('Trả lời ticket')." #$ticket_id"
        ]);
        
        // GỬI THÔNG BÁO CHO USER KHI ADMIN REPLY TICKET
        $user = $CMSNT->get_row("SELECT * FROM `users` WHERE `id` = '{$ticket['user_id']}'");
        if($user['telegram_chat_id'] != '' && $CMSNT->site('noti_user_admin_reply_ticket') != '' && $user['telegram_notification'] == 1){
            $content = $CMSNT->site('noti_user_admin_reply_ticket');
            $content = str_replace('{username}', $user['username'], $content);
            $content = str_replace('{subject}', $ticket['subject'], $content);
            $content = str_replace('{message}', $message, $content);
            $content = str_replace('{time}', gettime(), $content);
            sendMessTelegram($content, '', $user['telegram_chat_id']);
        }

        $newMessage = $CMSNT->get_row("
            SELECT sm.*, a.username AS admin_username, a.email AS admin_email
            FROM `support_messages` sm
            LEFT JOIN `users` a ON (sm.sender_id = a.id AND sm.sender_type = 'admin')
            WHERE sm.id = '$isInsert'
        ");

        $adminEmail = $newMessage['admin_email'] ?? $getUser['email'];
        $adminName  = $newMessage['admin_username'] ?? $getUser['username'];
        $createdAt  = $newMessage['created_at'] ?? gettime();

        $messagePayload = [
            'id'             => (int)$newMessage['id'],
            'sender_type'    => 'admin',
            'message'        => nl2br(htmlspecialchars($newMessage['message'])),
            'username'       => $adminName,
            'email'          => $adminEmail,
            'avatar'         => getGravatarUrl($adminEmail),
            'formatted_time' => date('H:i d/m/Y', strtotime($createdAt)),
            'time'           => date('H:i', strtotime($createdAt)),
            'date'           => date('d/m/Y', strtotime($createdAt)),
            'time_ago'       => timeAgo(strtotime($createdAt)),
            'user_online'    => null,
            'can_recall'     => true
        ];

        die(json_encode([
            'status'  => 'success', 
            'msg'     => __('Gửi tin nhắn thành công'),
            'message' => $messagePayload
        ]));
    } else {
        die(json_encode(['status' => 'error', 'msg' => __('Có lỗi xảy ra, vui lòng thử lại')]));
    }
}

// Lưu admin note cho ticket
if($_POST['action'] == 'saveAdminNote') {
    if(checkPermission($getUser['admin'], 'edit_ticket') != true){
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }

    $ticket_id = intval(check_string($_POST['ticket_id']));
    if(!$ticket = $CMSNT->get_row("SELECT * FROM `support_tickets` WHERE `id` = '$ticket_id'")){
        die(json_encode(['status' => 'error', 'msg' => __('Ticket không tồn tại')]));
    }

    $admin_note = isset($_POST['admin_note']) ? trim($_POST['admin_note']) : '';
    if(mb_strlen($admin_note) > 2000){
        die(json_encode(['status' => 'error', 'msg' => __('Ghi chú quá dài (tối đa 2000 ký tự)')]));
    }

    $updateData = [
        'admin_note' => $admin_note
    ];

    $isUpdate = $CMSNT->update('support_tickets', $updateData, "`id` = '$ticket_id'");

    if($isUpdate !== false){
        die(json_encode(['status' => 'success', 'msg' => __('Lưu ghi chú thành công')]));
    } else {
        die(json_encode(['status' => 'error', 'msg' => __('Không thể lưu ghi chú, vui lòng thử lại')]));
    }
}

// Danh sách quick replies
if($_POST['action'] == 'listQuickReplies') {
    if(checkPermission($getUser['admin'], 'edit_ticket') != true){
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }

    $ownerId = intval($getUser['id']);
    $replies = $CMSNT->get_list("SELECT id, command, content FROM `support_quick_replies` WHERE `user_id` = '{$ownerId}' ORDER BY command ASC");
    $data = [];
    foreach ($replies as $row) {
        $data[] = [
            'id'      => (int)$row['id'],
            'command' => $row['command'],
            'content' => $row['content']
        ];
    }
    die(json_encode(['status' => 'success', 'data' => $data]));
}

// Tạo quick reply
if($_POST['action'] == 'createQuickReply') {
    if(checkPermission($getUser['admin'], 'edit_ticket') != true){
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }

    $ownerId = intval($getUser['id']);
    $command = strtolower(trim(validate_alphanumeric($_POST['command'])));
    $command = ltrim($command, '/');
    $content = validate_string(trim($_POST['content']), 5000);

    if(empty($command) || empty($content)){
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng nhập đầy đủ lệnh và nội dung.')])); 
    }
    if($command === false){
        die(json_encode(['status' => 'error', 'msg' => __('Lệnh chỉ được phép chứa chữ, số, dấu gạch dưới hoặc gạch ngang.')])); 
    }
    if($content === false){
        die(json_encode(['status' => 'error', 'msg' => __('Nội dung quá dài (tối đa 5000 ký tự)')])); 
    }

    if($CMSNT->get_row("SELECT id FROM `support_quick_replies` WHERE `user_id` = '{$ownerId}' AND LOWER(`command`) = '".addslashes(strtolower($command))."'")){
        die(json_encode(['status' => 'error', 'msg' => __('Lệnh đã tồn tại. Vui lòng chọn lệnh khác.')])); 
    }

    $insertId = $CMSNT->insert('support_quick_replies', [
        'user_id'     => $ownerId,
        'command'     => $command,
        'content'     => $content,
        'created_by'  => $getUser['id'],
        'updated_by'  => $getUser['id'],
        'created_at'  => gettime(),
        'updated_at'  => gettime()
    ]);

    if($insertId){
        die(json_encode([
            'status' => 'success',
            'msg'    => __('Thêm câu trả lời nhanh thành công.'),
            'data'   => [
                'id'      => (int)$insertId,
                'command' => $command,
                'content' => $content
            ]
        ]));
    }
    die(json_encode(['status' => 'error', 'msg' => __('Không thể thêm câu trả lời nhanh, vui lòng thử lại.')])); 
}

// Cập nhật quick reply
if($_POST['action'] == 'updateQuickReply') {
    if(checkPermission($getUser['admin'], 'edit_ticket') != true){
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }

    $ownerId = intval($getUser['id']);
    $id = intval(check_string($_POST['id']));
    if(!$reply = $CMSNT->get_row("SELECT * FROM `support_quick_replies` WHERE `id` = '$id' AND `user_id` = '{$ownerId}'")){
        die(json_encode(['status' => 'error', 'msg' => __('Câu trả lời nhanh không tồn tại')]));
    }

    $command = strtolower(trim(check_string($_POST['command'])));
    $command = ltrim($command, '/');
    $content = trim($_POST['content']);

    if(empty($command) || empty($content)){
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng nhập đầy đủ lệnh và nội dung.')])); 
    }

    if(!preg_match('/^[a-zA-Z0-9_\-]+$/', $command)){
        die(json_encode(['status' => 'error', 'msg' => __('Lệnh chỉ được phép chứa chữ, số, dấu gạch dưới hoặc gạch ngang.')])); 
    }

    if($CMSNT->get_row("SELECT id FROM `support_quick_replies` WHERE `user_id` = '{$ownerId}' AND LOWER(`command`) = '".addslashes(strtolower($command))."' AND `id` != '$id'")){
        die(json_encode(['status' => 'error', 'msg' => __('Lệnh đã tồn tại. Vui lòng chọn lệnh khác.')])); 
    }

    $isUpdate = $CMSNT->update('support_quick_replies', [
        'command'    => $command,
        'content'    => $content,
        'updated_by' => $getUser['id'],
        'updated_at' => gettime()
    ], "`id` = '$id'");

    if($isUpdate !== false){
        die(json_encode([
            'status' => 'success',
            'msg'    => __('Cập nhật câu trả lời nhanh thành công.'),
            'data'   => [
                'id'      => (int)$id,
                'command' => $command,
                'content' => $content
            ]
        ]));
    }

    die(json_encode(['status' => 'error', 'msg' => __('Không thể cập nhật câu trả lời nhanh, vui lòng thử lại.')])); 
}

// Xóa quick reply
if($_POST['action'] == 'deleteQuickReply') {
    if(checkPermission($getUser['admin'], 'edit_ticket') != true){
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }

    $ownerId = intval($getUser['id']);
    $id = intval(check_string($_POST['id']));
    if(!$reply = $CMSNT->get_row("SELECT * FROM `support_quick_replies` WHERE `id` = '$id' AND `user_id` = '{$ownerId}'")){
        die(json_encode(['status' => 'error', 'msg' => __('Câu trả lời nhanh không tồn tại')]));
    }

    if($CMSNT->remove('support_quick_replies', "`id` = '$id'")){
        die(json_encode(['status' => 'success', 'msg' => __('Đã xóa câu trả lời nhanh.')])); 
    }
    die(json_encode(['status' => 'error', 'msg' => __('Không thể xóa câu trả lời nhanh, vui lòng thử lại.')])); 
}

// Danh sách ticket realtime cho admin
if($_POST['action'] == 'getSupportTicketsOverview') {
    if(checkPermission($getUser['admin'], 'view_ticket') != true){
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }

    global $config_category_support_tickets;

    $selected_id = isset($_POST['selected_id']) ? intval(check_string($_POST['selected_id'])) : 0;

    $where = " t.id > 0 ";

    $maxTickets = 50; // Số lượng ticket tối đa hiển thị

    $listTickets = $CMSNT->get_list("
        SELECT t.*, u.username, u.email, u.time_session,
               (SELECT COUNT(*) FROM support_messages WHERE ticket_id = t.id) as message_count,
               (SELECT COUNT(*) FROM support_messages WHERE ticket_id = t.id AND sender_type = 'admin') as admin_replies,
               (SELECT created_at FROM support_messages WHERE ticket_id = t.id ORDER BY created_at DESC LIMIT 1) as last_message_time,
               (SELECT message FROM support_messages WHERE ticket_id = t.id ORDER BY created_at DESC LIMIT 1) as last_message_preview
        FROM support_tickets t
        LEFT JOIN users u ON t.user_id = u.id
        WHERE $where
        ORDER BY t.updated_at DESC, t.id DESC
        LIMIT {$maxTickets}
    ");

    $categoryBadgeClasses = [
        'deposit'            => 'bg-info-transparent',
        'account'            => 'bg-warning-transparent',
        'order'              => 'bg-primary-transparent',
        'other'              => 'bg-secondary-transparent'
    ];

$groupedTickets = [
    'unread'   => [],
    'answered' => [],
    'closed'   => []
];

foreach ($listTickets as $ticket) {
    if ($ticket['status'] == 'answered') {
        $groupedTickets['answered'][] = $ticket;
    } elseif ($ticket['status'] == 'closed') {
        $groupedTickets['closed'][] = $ticket;
    } else {
        $groupedTickets['unread'][] = $ticket;
    }
}

if(!$selected_id && !empty($listTickets)) {
        $selected_id = intval($listTickets[0]['id']);
    }

    $totalTickets = $CMSNT->num_rows("
        SELECT t.id
        FROM support_tickets t
        LEFT JOIN users u ON t.user_id = u.id
        WHERE $where
    ");

$groupConfigs = [
    'unread' => [
        'title' => __('Tin nhắn mới'),
        'emptyIcon' => 'ri-inbox-line',
        'iconClass' => 'ri-customer-service-2-line',
        'itemClass' => '',
        'emptyText' => __('Không có dữ liệu')
    ],
    'answered' => [
        'title' => __('Đã trả lời'),
        'emptyIcon' => 'ri-chat-check-line',
        'iconClass' => 'ri-chat-check-line',
        'itemClass' => '',
        'emptyText' => __('Không có dữ liệu')
    ],
    'closed' => [
        'title' => __('Đóng'),
        'emptyIcon' => 'ri-lock-line',
        'iconClass' => 'ri-check-double-line',
        'itemClass' => 'chat-inactive',
        'emptyText' => __('Không có dữ liệu')
    ]
];

$groupsData = [];
foreach ($groupConfigs as $groupKey => $config) {
    $ticketsGroup = $groupedTickets[$groupKey];
    $ticketsData = [];
    foreach ($ticketsGroup as $ticket) {
        $isOnline = !empty($ticket['time_session']) && ($ticket['time_session'] >= time() - 300);
        $statusClass = $isOnline ? 'online' : 'offline';
        $lastTime = $ticket['last_message_time'] ? $ticket['last_message_time'] : $ticket['updated_at'];
        $preview = $ticket['last_message_preview'] ? strip_tags($ticket['last_message_preview']) : strip_tags($ticket['content']);
        $preview = trim($preview);
        if(strlen($preview) > 80){
            $preview = mb_substr($preview, 0, 80).'...';
        }
        $link = base_url_admin('messages&id='.$ticket['id']);
        $showBadge = ($ticket['admin_replies'] ?? 0) > 0 && $groupKey === 'unread';
        $statusIcon = $config['iconClass'];
        $messageClass = $groupKey === 'unread' && ($ticket['admin_replies'] ?? 0) > 0 ? 'chat-msg-typing' : '';
        $categoryKey = $ticket['category'] ?? 'other';
        $categoryText = $config_category_support_tickets[$categoryKey] ?? ucfirst($categoryKey);
        $categoryBadgeClass = $categoryBadgeClasses[$categoryKey] ?? 'bg-secondary text-white';

        $ticketsData[] = [
            'id'            => intval($ticket['id']),
            'subject'       => $ticket['subject'],
            'username'      => $ticket['username'],
            'email'         => $ticket['email'],
            'status'        => $ticket['status'],
            'status_class'  => $statusClass,
            'is_closed'     => $groupKey === 'closed' ? 1 : 0,
            'last_time'     => date('d/m H:i', strtotime($lastTime)),
            'preview'       => $preview,
            'admin_replies' => intval($ticket['admin_replies']),
            'show_badge'    => $showBadge ? 1 : 0,
            'message_class' => $messageClass,
            'status_icon'   => $statusIcon,
            'link'          => $link,
            'avatar'        => getGravatarUrl($ticket['email']),
            'is_online'     => $isOnline ? 1 : 0,
            'category_text' => $categoryText,
            'category_badge_class' => $categoryBadgeClass
        ];
    }
    $groupsData[] = [
        'key'        => $groupKey,
        'title'      => $config['title'],
        'emptyIcon'  => $config['emptyIcon'],
        'emptyText'  => $config['emptyText'],
        'itemClass'  => $config['itemClass'],
        'tickets'    => $ticketsData
    ];
}

$stats = [
    'total'    => (int)$CMSNT->get_row("SELECT COUNT(*) as total FROM support_tickets")['total'],
    'open'     => (int)$CMSNT->get_row("SELECT COUNT(*) as total FROM support_tickets WHERE status = 'open'")['total'],
    'pending'  => (int)$CMSNT->get_row("SELECT COUNT(*) as total FROM support_tickets WHERE status = 'pending'")['total'],
    'answered' => (int)$CMSNT->get_row("SELECT COUNT(*) as total FROM support_tickets WHERE status = 'answered'")['total'],
    'closed'   => (int)$CMSNT->get_row("SELECT COUNT(*) as total FROM support_tickets WHERE status = 'closed'")['total']
];

die(json_encode([
    'status'        => 'success',
    'groups'        => $groupsData,
    'selected_id'   => $selected_id,
    'stats'         => $stats,
    'total_tickets' => (int)$stats['total']
]));
}

// Load tin nhắn mới cho admin
if($_POST['action'] == 'get_new_messages_admin') {
    $ticket_id = intval(check_string($_POST['ticket_id']));
    $last_message_id = intval(check_string($_POST['last_message_id']));
    
    // Kiểm tra ticket có tồn tại không
    if(!$ticket = $CMSNT->get_row("
        SELECT t.*, u.username AS user_username, u.email AS user_email, u.time_session
        FROM `support_tickets` t
        LEFT JOIN `users` u ON t.user_id = u.id
        WHERE t.id = '$ticket_id'
    ")){
        die(json_encode(['status' => 'error', 'msg' => __('Ticket không tồn tại')]));
    }
    $isUserOnline = !empty($ticket['time_session']) && ($ticket['time_session'] >= time() - 300);
    if(empty($ticket['username'])) {
        $ticket['username'] = $ticket['user_username'];
    }
    if(empty($ticket['email'])) {
        $ticket['email'] = $ticket['user_email'];
    }
    
    // Lấy tin nhắn mới
    $messages = $CMSNT->get_list("
        SELECT sm.*,
               u.username AS user_username,
               u.email    AS user_email,
               a.username AS admin_username,
               a.email    AS admin_email
        FROM `support_messages` sm 
        LEFT JOIN `users` u ON (sm.sender_id = u.id AND sm.sender_type = 'user') 
        LEFT JOIN `users` a ON (sm.sender_id = a.id AND sm.sender_type = 'admin')
        WHERE sm.ticket_id = '$ticket_id' AND sm.id > '$last_message_id' 
        ORDER BY sm.created_at ASC
        LIMIT 100
    ");
    
    if(!empty($messages)){
        $formatted_messages = [];
        foreach($messages as $msg){
            $isAdmin  = $msg['sender_type'] == 'admin';
            $username = $isAdmin
                ? (($msg['admin_username'] ?? '') ?: __('Admin'))
                : (($msg['user_username'] ?? '') ?: ($ticket['username'] ?? __('User')));
            $email    = $isAdmin
                ? (($msg['admin_email'] ?? '') ?: '')
                : (($msg['user_email'] ?? '') ?: ($ticket['email'] ?? ''));
            $avatarEmail = $email ?: ($isAdmin ? ($getUser['email'] ?? '') : ($ticket['email'] ?? ''));
            $avatar   = getGravatarUrl($avatarEmail);
            $created  = $msg['created_at'];
            $formatted_messages[] = [
                'id'             => (int)$msg['id'],
                'sender_type'    => $msg['sender_type'],
                'message'        => nl2br(htmlspecialchars($msg['message'])),
                'username'       => $username,
                'email'          => $email,
                'avatar'         => $avatar,
                'created_at'     => $created,
                'formatted_time' => date('H:i d/m/Y', strtotime($created)),
                'time'           => date('H:i', strtotime($created)),
                'date'           => date('d/m/Y', strtotime($created)),
                'time_ago'       => timeAgo(strtotime($created)),
                'user_online'    => $isAdmin ? null : ($isUserOnline ? 1 : 0),
                'can_recall'     => $isAdmin && intval($msg['sender_id']) === intval($getUser['id'])
            ];
        }
        
        die(json_encode([
            'status' => 'success', 
            'messages' => $formatted_messages
        ]));
    } else {
        die(json_encode(['status' => 'success', 'messages' => []]));
    }
}

// Xóa nhiều ticket cùng lúc
if($_POST['action'] == 'bulkDeleteTickets') {
    // Kiểm tra quyền
    if(checkPermission($getUser['admin'], 'edit_ticket') != true){
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }

    if(empty($_POST['ids']) || !is_array($_POST['ids'])){
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng chọn ít nhất một ticket để xóa')]));
    }

    $ids = array_map('intval', $_POST['ids']); // Chuyển đổi sang kiểu int để bảo mật
    $validIds = [];
    $deletedSubjects = [];

    // Kiểm tra từng ticket có tồn tại không
    foreach($ids as $id){
        $id = check_string($id);
        if($ticket = $CMSNT->get_row("SELECT * FROM `support_tickets` WHERE `id` = '$id'")){
            $validIds[] = $id;
            $deletedSubjects[] = "#{$id} ({$ticket['subject']})";
        }
    }

    if(empty($validIds)){
        die(json_encode(['status' => 'error', 'msg' => __('Không tìm thấy ticket nào để xóa')]));
    }

    $deletedCount = 0;
    foreach($validIds as $id){
        // Xóa tất cả tin nhắn của ticket này trước
        $CMSNT->remove('support_messages', "`ticket_id` = '$id'");
        
        // Xóa ticket
        if($CMSNT->remove('support_tickets', "`id` = '$id'")){
            $deletedCount++;
        }
    }

    if($deletedCount > 0){
        // Ghi log hành động

        $CMSNT->insert("logs", [
            'user_id'       => $getUser['id'],
            'ip'            => myip(),
            'device'        => getUserAgent(),
            'createdate'    => gettime(),
            'action'        => sprintf(__('Xóa %d ticket hàng loạt: %s'), $deletedCount, implode(', ', array_slice($deletedSubjects, 0, 5)) . (count($deletedSubjects) > 5 ? '...' : ''))
        ]);

        die(json_encode(['status' => 'success', 'msg' => sprintf(__('Đã xóa thành công %d ticket'), $deletedCount)]));
    } else {
        die(json_encode(['status' => 'error', 'msg' => __('Không thể xóa ticket nào')]));
    }
}

// Thay đổi trạng thái nhiều ticket cùng lúc
if($_POST['action'] == 'bulkChangeStatus') {
    // Kiểm tra quyền
    if(checkPermission($getUser['admin'], 'edit_ticket') != true){
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }

    if(empty($_POST['ids']) || !is_array($_POST['ids'])){
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng chọn ít nhất một ticket')]));
    }

    $ids = array_map('intval', $_POST['ids']); // Chuyển đổi sang kiểu int để bảo mật
    $status = check_string($_POST['status']);
    
    if(!in_array($status, array_keys($config_status_support_tickets))){
        die(json_encode(['status' => 'error', 'msg' => __('Trạng thái không hợp lệ')]));
    }

    $validIds = [];
    $updatedSubjects = [];

    // Kiểm tra từng ticket có tồn tại không
    foreach($ids as $id){
        if($ticket = $CMSNT->get_row("SELECT * FROM `support_tickets` WHERE `id` = '$id'")){
            $validIds[] = $id;
            $updatedSubjects[] = "#{$id} ({$ticket['subject']})";
        }
    }

    if(empty($validIds)){
        die(json_encode(['status' => 'error', 'msg' => __('Không tìm thấy ticket nào để cập nhật')]));
    }

    $updatedCount = 0;
    foreach($validIds as $id){
        // Cập nhật trạng thái
        if($CMSNT->update('support_tickets', [
            'status' => $status
        ], "`id` = '$id'")){
            $updatedCount++;
        }
    }

    if($updatedCount > 0){
        // Ghi log hành động

        $CMSNT->insert("logs", [
            'user_id'       => $getUser['id'],
            'ip'            => myip(),
            'device'        => getUserAgent(),
            'createdate'    => gettime(),
            'action'        => sprintf(__('Thay đổi trạng thái %d ticket hàng loạt thành %s: %s'), $updatedCount, $config_status_support_tickets[$status], implode(', ', array_slice($updatedSubjects, 0, 5)) . (count($updatedSubjects) > 5 ? '...' : ''))
        ]);

        die(json_encode(['status' => 'success', 'msg' => sprintf(__('Đã cập nhật trạng thái thành công cho %d ticket'), $updatedCount)]));
    } else {
        die(json_encode(['status' => 'error', 'msg' => __('Không thể cập nhật trạng thái cho ticket nào')]));
    }
}

// Tạo ticket cho user (từ admin)
if($_POST['action'] == 'createTicketForUser') {
    // Kiểm tra quyền
    if(checkPermission($getUser['admin'], 'edit_ticket') != true){
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }

    $user_id = intval(check_string($_POST['user_id']));
    $subject = trim(check_string($_POST['subject']));
    $category = check_string($_POST['category']);
    $content = trim(check_string($_POST['content']));
    $order_id = isset($_POST['order_id']) && !empty($_POST['order_id']) ? check_string($_POST['order_id']) : NULL;
    
    // Validate
    if(empty($subject)){
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng nhập tiêu đề ticket')]));
    }
    
    if(empty($category)){
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng chọn chủ đề hỗ trợ')]));
    }
    
    if(empty($content)){
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng nhập nội dung ticket')]));
    }
    
    if(strlen($subject) > 200){
        die(json_encode(['status' => 'error', 'msg' => __('Tiêu đề quá dài (tối đa 200 ký tự)')]));
    }
    
    if(strlen($content) > 5000){
        die(json_encode(['status' => 'error', 'msg' => __('Nội dung quá dài (tối đa 5000 ký tự)')]));
    }
    
    // Kiểm tra user có tồn tại không
    if(!$user = $CMSNT->get_row("SELECT * FROM `users` WHERE `id` = '$user_id'")){
        die(json_encode(['status' => 'error', 'msg' => __('User không tồn tại')]));
    }
    
    // Kiểm tra mã đơn hàng nếu có
    if(!empty($order_id) && $category == 'order'){
        $isOrder = $CMSNT->get_row("SELECT * FROM `product_orders` WHERE `trans_id` = '".$order_id."' AND `user_id` = '".$user_id."' ");
        if(!$isOrder){
            die(json_encode(['status' => 'error', 'msg' => __('Mã đơn hàng không tồn tại hoặc không thuộc về user này')]));
        }
        $order_id = $isOrder['id'];
    }
    
    // Tạo ticket mới
    $isInsert = $CMSNT->insert('support_tickets', [
        'user_id'           => $user_id,
        'order_id'          => $order_id,
        'category'          => $category,
        'subject'           => $subject,
        'content'           => $content,
        'status'            => 'open',
        'created_at'        => gettime()
    ]);
    
    if($isInsert){
        // Ghi log hành động
        $CMSNT->insert("logs", [
            'user_id'       => $getUser['id'],
            'ip'            => myip(),
            'device'        => getUserAgent(),
            'createdate'    => gettime(),
            'action'        => sprintf(__('Tạo ticket cho user %s: %s'), $user['username'], $subject)
        ]);
        
        // GỬI THÔNG BÁO CHO USER KHI ADMIN REPLY TICKET
        if($user['telegram_chat_id'] != '' && $CMSNT->site('noti_user_admin_reply_ticket') != '' && $user['telegram_notification'] == 1){
            $content = $CMSNT->site('noti_user_admin_reply_ticket');
            $content = str_replace('{username}', $user['username'], $content);
            $content = str_replace('{subject}', $ticket['subject'], $content);
            $content = str_replace('{message}', $message, $content);
            $content = str_replace('{time}', gettime(), $content);
            sendMessTelegram($content, '', $user['telegram_chat_id']);
        }

        die(json_encode([
            'status' => 'success', 
            'msg' => __('Tạo ticket thành công cho user: ') . $user['username'],
            'redirect_url' => base_url_admin('ticket-detail')
        ]));
    } else {
        die(json_encode(['status' => 'error', 'msg' => __('Có lỗi xảy ra, vui lòng thử lại')]));
    }
}

// Thu hồi tin nhắn admin
if($_POST['action'] == 'recallAdminMessage') {
    if(checkPermission($getUser['admin'], 'edit_ticket') != true){
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }

    $message_id = intval(check_string($_POST['message_id']));
    $ticket_id = intval(check_string($_POST['ticket_id']));

    if(!$message = $CMSNT->get_row("SELECT * FROM `support_messages` WHERE `id` = '$message_id' AND `ticket_id` = '$ticket_id'")){
        die(json_encode(['status' => 'error', 'msg' => __('Tin nhắn không tồn tại hoặc đã được thu hồi.')]));
    }

    if($message['sender_type'] !== 'admin' || intval($message['sender_id']) !== intval($getUser['id'])){
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không thể thu hồi tin nhắn này.')]));
    }

    if($CMSNT->remove('support_messages', "`id` = '$message_id'")){
        $CMSNT->update('support_tickets', [
            'updated_at' => gettime()
        ], "`id` = '$ticket_id'");

        die(json_encode(['status' => 'success', 'msg' => __('Thu hồi tin nhắn thành công')]));
    }

    die(json_encode(['status' => 'error', 'msg' => __('Không thể thu hồi tin nhắn. Vui lòng thử lại.')]));
}

// Dịch nội dung tin nhắn của user
if($_POST['action'] == 'translateMessage') {
    if(checkPermission($getUser['admin'], 'view_ticket') != true){
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }

    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    $targetLang = isset($_POST['target_lang']) ? trim($_POST['target_lang']) : '';

    if($message === ''){
        die(json_encode(['status' => 'error', 'msg' => __('Tin nhắn trống, không thể dịch')]));
    }

    if(mb_strlen($message) > 5000){
        die(json_encode(['status' => 'error', 'msg' => __('Tin nhắn cần dịch không hợp lệ')]));
    }

    if(empty($targetLang)){
        die(json_encode(['status' => 'error', 'msg' => __('Chọn ngôn ngữ dịch')]));
    }

    if(!preg_match('/^[a-zA-Z\-]{2,10}$/', $targetLang)){
        die(json_encode(['status' => 'error', 'msg' => __('Tin nhắn cần dịch không hợp lệ')]));
    }

    $apiUrl = 'https://api.cmsnt.co/translation-api.php';
    $query = http_build_query([
        'license_key' => $CMSNT->site('license_key'),
        'q'           => $message,
        'target'      => $targetLang
    ]);

    $response = @file_get_contents($apiUrl.'?'.$query);
    if(!$response){
        die(json_encode(['status' => 'error', 'msg' => __('Không thể dịch tin nhắn này')]));
    }

    $data = json_decode($response, true);
    if(isset($data['data']['translations'][0]['translatedText'])){
        $translatedText = $data['data']['translations'][0]['translatedText'];
        die(json_encode([
            'status'          => 'success',
            'msg'             => __('Dịch thành công'),
            'translated_text' => $translatedText,
            'target_lang'     => $targetLang
        ]));
    }

    die(json_encode(['status' => 'error', 'msg' => __('Không thể dịch tin nhắn này')]));
}

die(json_encode(['status' => 'error', 'msg' => __('Hành động không hợp lệ')]));
 