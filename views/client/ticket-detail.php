<?php if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

require_once(__DIR__ . '/../../models/is_user.php');

if ($CMSNT->site('support_tickets_status') == 0) {
    redirect(base_url());
}

// Lấy ID ticket từ URL (validate)
$ticket_id = validate_int($_GET['id'], 1) ?: 0;

if (!$ticket_id) {
    redirect(base_url('client/support-tickets'));
}

// Kiểm tra ticket có thuộc về user hiện tại không (prepared statement)
$ticket = $CMSNT->get_row_safe("SELECT * FROM `support_tickets` WHERE `id` = ? AND `user_id` = ?", [$ticket_id, $getUser['id']]);

if (!$ticket) {
    redirect(base_url('client/support-tickets'));
}

$body = [
    'title' => __('Chi tiết Ticket') . ' #' . $ticket_id . ' | ' . $CMSNT->site('title'),
    'desc'   => $CMSNT->site('description'),
    'keyword' => $CMSNT->site('keywords')
];

$body['header'] = '
<link rel="stylesheet" href="' . BASE_URL('mod/css/ticket.css') . '">
';
$body['footer'] = '';

require_once(__DIR__ . '/header.php');
require_once(__DIR__ . '/nav.php');

// Lấy danh sách tin nhắn (prepared statement)
$messages = $CMSNT->get_list_safe("SELECT sm.*, u.username FROM `support_messages` sm LEFT JOIN `users` u ON (sm.sender_id = u.id AND sm.sender_type = 'user') WHERE sm.ticket_id = ? ORDER BY sm.created_at ASC", [$ticket_id]);

// Config trạng thái
$status_config = [
    'open' => ['name' => __('Đang mở'), 'icon' => 'fa-envelope-open'],
    'pending' => ['name' => __('Chờ xử lý'), 'icon' => 'fa-clock'],
    'answered' => ['name' => __('Đã trả lời'), 'icon' => 'fa-check-circle'],
    'closed' => ['name' => __('Đã đóng'), 'icon' => 'fa-lock']
];

$current_status = isset($status_config[$ticket['status']]) ? $status_config[$ticket['status']] : ['name' => $ticket['status'], 'icon' => 'fa-question-circle'];
?>

<section class="py-5 inner-section ticket-detail-page">
    <div class="container">
        <div class="row content-reverse">
            <div class="col-lg-3">
                <?php require_once(__DIR__ . '/sidebar.php'); ?>
            </div>
            <div class="col-lg-9">
                <div class="row">
                    <!-- Chat Area -->
                    <div class="col-lg-8 mb-4">
                        <div class="ticket-chat-wrapper">
                            <!-- Header -->
                            <div class="ticket-chat-header">
                                <h5>
                                    <i class="fa-solid fa-comments"></i>
                                    <?= __('Cuộc hội thoại'); ?>
                                </h5>
                                <a href="<?= base_url('client/support-tickets'); ?>" class="btn-back-tickets">
                                    <i class="fa-solid fa-arrow-left"></i>
                                    <?= __('Quay lại'); ?>
                                </a>
                            </div>

                            <!-- Messages -->
                            <div class="ticket-chat-messages" id="chatMessages">
                                <!-- Các tin nhắn -->
                                <?php if (!empty($messages)): ?>
                                    <?php foreach ($messages as $msg): ?>
                                        <?php if ($msg['sender_type'] == 'user'): ?>
                                            <!-- Tin nhắn từ user -->
                                            <div class="chat-message is-user" data-message-id="<?= $msg['id']; ?>">
                                                <div class="chat-avatar">
                                                    <img src="<?= getGravatarUrl($getUser['email']); ?>" alt="<?= htmlspecialchars($msg['username'] ?: 'User'); ?>">
                                                </div>
                                                <div class="chat-bubble">
                                                    <div class="chat-bubble-meta">
                                                        <span class="chat-sender"><?= htmlspecialchars($msg['username'] ?: __('User')); ?></span>
                                                        <span class="chat-time"><?= date('H:i d/m/Y', strtotime($msg['created_at'])); ?></span>
                                                    </div>
                                                    <div class="chat-bubble-content">
                                                        <p><?= nl2br(htmlspecialchars($msg['message'])); ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <!-- Tin nhắn từ admin -->
                                            <div class="chat-message is-admin" data-message-id="<?= $msg['id']; ?>">
                                                <div class="chat-avatar-admin">
                                                    <i class="fa-solid fa-headset"></i>
                                                </div>
                                                <div class="chat-bubble">
                                                    <div class="chat-bubble-meta">
                                                        <span class="chat-sender"><?= __('Admin Support'); ?></span>
                                                        <span class="chat-time"><?= date('H:i d/m/Y', strtotime($msg['created_at'])); ?></span>
                                                    </div>
                                                    <div class="chat-bubble-content">
                                                        <p><?= nl2br(htmlspecialchars($msg['message'])); ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <?php if (empty($messages)): ?>
                                    <!-- Trạng thái chờ phản hồi -->
                                    <div class="chat-waiting-state">
                                        <div class="waiting-animation">
                                            <i class="fa-solid fa-headset"></i>
                                        </div>
                                        <h5><?= __('Đang chờ phản hồi'); ?></h5>
                                        <p><?= __('Đội ngũ hỗ trợ sẽ phản hồi trong thời gian sớm nhất'); ?></p>
                                        <div class="waiting-hint">
                                            <i class="fa-solid fa-clock"></i>
                                            <?= __('Thời gian phản hồi: 2-24 giờ'); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Input Area -->
                            <div class="ticket-chat-footer">
                                <form class="chat-input-form" id="replyForm">
                                    <input type="hidden" name="ticket_id" value="<?= $ticket['id']; ?>">
                                    <div class="chat-input-wrapper">
                                        <textarea id="replyMessage" name="message" placeholder="<?= __('Nhập tin nhắn của bạn...'); ?>" rows="1" required></textarea>
                                    </div>
                                    <button type="submit" class="btn-send-message" id="btnSendMessage">
                                        <i class="fa-solid fa-paper-plane"></i>
                                    </button>
                                </form>
                                <div class="chat-shortcut-hint">
                                    <i class="fa-solid fa-keyboard"></i>
                                    <?= __('Phím tắt'); ?>: <kbd>Ctrl</kbd> + <kbd>Enter</kbd> <?= __('để gửi'); ?>
                                </div>
                                <?php if ($ticket['status'] != 'closed'): ?>
                                    <div class="ticket-close-hint">
                                        <i class="fa-solid fa-circle-check"></i>
                                        <?= __('Nếu vấn đề đã được giải quyết, vui lòng đóng ticket để hỗ trợ chúng tôi phục vụ bạn tốt hơn.'); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Ticket Info -->
                    <div class="col-lg-4">
                        <div class="ticket-sidebar-card">
                            <!-- Header with Status -->
                            <div class="ticket-sidebar-header">
                                <div class="ticket-sidebar-id">#<?= $ticket['id']; ?></div>
                                <span class="ticket-status-pill status-<?= $ticket['status']; ?>">
                                    <i class="fa-solid <?= $current_status['icon']; ?>"></i>
                                    <?= $current_status['name']; ?>
                                </span>
                            </div>

                            <!-- Ticket Subject -->
                            <div class="ticket-sidebar-subject">
                                <div class="subject-title"><?= htmlspecialchars($ticket['subject']); ?></div>
                                <div class="subject-category">
                                    <i class="fa-solid fa-folder"></i>
                                    <?= isset($config_category_support_tickets[$ticket['category']]) ? $config_category_support_tickets[$ticket['category']] : $ticket['category']; ?>
                                </div>
                            </div>

                            <!-- Info List -->
                            <div class="ticket-sidebar-info">
                                <?php if ($ticket['order_id']): ?>
                                    <?php $order_info = $CMSNT->get_row_safe("SELECT * FROM `product_orders` WHERE `id` = ? AND `user_id` = ?", [$ticket['order_id'], $getUser['id']]); ?>
                                    <?php if ($order_info): ?>
                                        <div class="sidebar-info-item">
                                            <div class="info-icon"><i class="fa-solid fa-shopping-bag"></i></div>
                                            <div class="info-content">
                                                <span class="info-label"><?= __('Đơn hàng liên quan'); ?></span>
                                                <span class="info-value">#<?= htmlspecialchars($order_info['trans_id']); ?></span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <div class="sidebar-info-item">
                                    <div class="info-icon"><i class="fa-solid fa-calendar-plus"></i></div>
                                    <div class="info-content">
                                        <span class="info-label"><?= __('Ngày tạo'); ?></span>
                                        <span class="info-value"><?= date('d/m/Y H:i', strtotime($ticket['created_at'])); ?></span>
                                    </div>
                                </div>

                                <?php if ($ticket['updated_at'] != $ticket['created_at']): ?>
                                    <div class="sidebar-info-item">
                                        <div class="info-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
                                        <div class="info-content">
                                            <span class="info-label"><?= __('Cập nhật lần cuối'); ?></span>
                                            <span class="info-value"><?= date('d/m/Y H:i', strtotime($ticket['updated_at'])); ?></span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Quick Actions -->
                            <div class="ticket-sidebar-actions">
                                <a href="<?= base_url('client/support-tickets'); ?>" class="sidebar-action-btn">
                                    <i class="fa-solid fa-list"></i>
                                    <?= __('Danh sách ticket'); ?>
                                </a>
                                <?php if ($ticket['status'] != 'closed'): ?>
                                    <button type="button" class="sidebar-action-btn btn-close-ticket" id="btnCloseTicket">
                                        <i class="fa-solid fa-lock"></i>
                                        <?= __('Đóng ticket'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Âm thanh thông báo -->
<audio id="notification-sound" class="d-none">
    <source src="<?= base_url('assets/audio/facebook-messenger.mp3'); ?>" type="audio/mpeg">
</audio>

<?php require_once(__DIR__ . '/footer.php'); ?>

<!-- Ticket JS Variables -->
<script>
    var TICKET_AJAX_URL = '<?= base_url('ajaxs/client/ticket.php'); ?>';
    var TICKET_USER_TOKEN = '<?= $getUser['token']; ?>';
    var TICKET_ID = <?= $ticket['id']; ?>;
    var TICKET_USERNAME = '<?= htmlspecialchars($getUser['username']); ?>';
    var TICKET_USER_AVATAR = '<?= getGravatarUrl($getUser['email']); ?>';
    var CSRF_TOKEN = '<?= generate_csrf_token(); ?>';
    var TICKET_LANG = {
        admin_support: '<?= __('Admin Support'); ?>',
        message_required: '<?= __('Vui lòng nhập tin nhắn'); ?>',
        server_error: '<?= __('Không thể kết nối đến server'); ?>',
        confirm_close_ticket: '<?= __('Bạn có chắc chắn muốn đóng ticket này không?'); ?>',
        close_ticket_success: '<?= __('Ticket đã được đóng thành công'); ?>'
    };
</script>
<script src="<?= BASE_URL('mod/js/ticket.js'); ?>"></script>
<script>
    // Close ticket functionality
    $(document).ready(function() {
        $('#btnCloseTicket').on('click', function() {
            var $btn = $(this);
            var originalHtml = $btn.html();

            Swal.fire({
                title: '<?= __('Đóng ticket'); ?>',
                text: TICKET_LANG.confirm_close_ticket,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '<?= __('Đóng ticket'); ?>',
                cancelButtonText: '<?= __('Hủy'); ?>'
            }).then(function(result) {
                if (result.isConfirmed) {
                    $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i>');

                    $.ajax({
                        url: TICKET_AJAX_URL,
                        type: 'POST',
                        data: {
                            action: 'closeTicket',
                            ticket_id: TICKET_ID,
                            token: TICKET_USER_TOKEN,
                            csrf_token: CSRF_TOKEN
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                Swal.fire({
                                    icon: 'success',
                                    title: '<?= __('Thành công'); ?>',
                                    text: response.msg || TICKET_LANG.close_ticket_success,
                                    timer: 1500,
                                    showConfirmButton: false
                                }).then(function() {
                                    window.location.href = '<?= base_url('client/support-tickets'); ?>';
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: '<?= __('Lỗi'); ?>',
                                    text: response.msg || TICKET_LANG.server_error
                                });
                                $btn.prop('disabled', false).html(originalHtml);
                            }
                        },
                        error: function() {
                            Swal.fire({
                                icon: 'error',
                                title: '<?= __('Lỗi'); ?>',
                                text: TICKET_LANG.server_error
                            });
                            $btn.prop('disabled', false).html(originalHtml);
                        }
                    });
                }
            });
        });
    });
</script>