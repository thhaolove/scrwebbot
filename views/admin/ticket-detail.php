<?php if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

require_once(__DIR__ . '/../../models/is_admin.php');

// Kiểm tra quyền xem tickets
if (checkPermission($getUser['admin'], 'view_ticket') != true) {
    die('<script type="text/javascript">if(!alert("' . __('Bạn không có quyền sử dụng tính năng này') . '")){window.history.back();}</script>');
}

// Lấy ID ticket từ URL
$ticket_id = intval(check_string($_GET['id']));

if (!$ticket_id) {
    redirect(base_url_admin('tickets'));
}

// Lấy thông tin ticket (admin có thể xem tất cả ticket)
$ticket = $CMSNT->get_row("
    SELECT t.*, u.username, u.email, u.money 
    FROM `support_tickets` t 
    LEFT JOIN `users` u ON t.user_id = u.id 
    WHERE t.id = '$ticket_id'
");

if (!$ticket) {
    redirect(base_url_admin('tickets'));
}

$body = [
    'title' => __('Chi tiết Ticket') . ' #' . $ticket_id . ' | ' . $CMSNT->site('title'),
    'desc'   => $CMSNT->site('description'),
    'keyword' => $CMSNT->site('keywords')
];

$body['header'] = '
<style>
/* Modern Chat Interface */
.chat-container {
    background: #ffffff;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    overflow: hidden;
}

.chat-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 1.5rem;
    border: none;
}

.chat-header h6 {
    color: white;
    font-weight: 600;
    margin: 0;
}

.chat-conversation {
    height: 500px;
    overflow-y: auto;
    background: #f8fafc;
    padding: 1.5rem;
}

.chat-conversation::-webkit-scrollbar {
    width: 6px;
}

.chat-conversation::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.chat-conversation::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

.chat-conversation::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

/* Message Styling */
.chat-list {
    margin-bottom: 1.5rem;
}

.chat-list.left .conversation-list {
    margin-right: 15%;
}

.chat-list.right .conversation-list {
    margin-left: 15%;
}

/* User Messages - Left */
.user-message {
    background: #ffffff;
    color: #374151;
    border-radius: 8px 8px 8px 4px;
    padding: 12px 16px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border: 1px solid #e5e7eb;
}

/* Admin Messages - Right */
.admin-message {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    border-radius: 8px 8px 4px 8px;
    padding: 12px 16px;
    box-shadow: 0 2px 8px rgba(16,185,129,0.3);
}

/* Message Headers */
.message-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}

.message-header h6 {
    font-size: 0.875rem;
    font-weight: 600;
    margin: 0;
}

.message-time {
    font-size: 0.75rem;
    opacity: 0.7;
}

/* Badges */
.admin-badge {
    background: linear-gradient(45deg, #8b5cf6, #a855f7);
    color: white;
    font-size: 0.65rem;
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    font-weight: 500;
}

.user-badge {
    background: linear-gradient(45deg, #6b7280, #4b5563);
    color: white;
    font-size: 0.65rem;
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    font-weight: 500;
}

/* Avatars */
.message-avatar {
    width: 40px;
    height: 40px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.875rem;
}

.admin-avatar {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 6px;
}

/* Reply Form */
.reply-form-container {
    background: white;
    padding: 1.5rem;
    border-top: 1px solid #e5e7eb;
}

.reply-form {
    display: flex;
    gap: 12px;
    align-items: end;
}

.reply-textarea {
    flex: 1;
    border: 2px solid #e5e7eb;
    border-radius: 6px;
    padding: 12px 16px;
    resize: none;
    transition: all 0.2s ease;
    font-size: 0.875rem;
    line-height: 1.5;
    background: white;
    color: #374151;
}

.reply-textarea:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
    outline: none;
}

.reply-btn {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    border-radius: 6px;
    padding: 12px 20px;
    font-weight: 600;
    transition: all 0.2s ease;
    min-width: 80px;
}

.reply-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(102,126,234,0.3);
    color: white;
}

.reply-btn:disabled {
    opacity: 0.6;
    transform: none;
}

/* Keyboard Shortcuts */
.keyboard-shortcuts {
    margin-top: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.75rem;
    color: #6b7280;
}

.kbd-key {
    background: #f3f4f6;
    color: #374151;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 0.7rem;
    font-weight: 600;
    border: 1px solid #d1d5db;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

/* Sidebar Cards */
.info-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    overflow: hidden;
    margin-bottom: 1.5rem;
}

.info-card-header {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
    padding: 1rem 1.5rem;
    font-weight: 600;
}

.info-card-body {
    padding: 1.5rem;
}

/* Status Badges */
.status-badge {
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin: 1rem 0;
}

.stat-card {
    text-align: center;
    padding: 1rem;
    border-radius: 6px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
}

.stat-value {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1e293b;
}

.stat-label {
    font-size: 0.75rem;
    color: #64748b;
    margin-top: 0.25rem;
}

/* Action Buttons */
.action-btn {
    background: white;
    border: 2px solid #e5e7eb;
    color: #374151;
    padding: 0.75rem 1rem;
    border-radius: 6px;
    font-weight: 500;
    transition: all 0.2s ease;
    text-decoration: none;
    display: inline-block;
    width: 100%;
    text-align: center;
    margin-bottom: 0.5rem;
}

.action-btn:hover {
    border-color: #667eea;
    color: #667eea;
    transform: translateY(-1px);
    text-decoration: none;
}

/* Dark Mode Support */
[data-theme-mode="dark"] .chat-container,
[data-bs-theme="dark"] .chat-container,
.dark .chat-container,
body.dark .chat-container {
    background: #1f2937;
}

[data-theme-mode="dark"] .chat-conversation,
[data-bs-theme="dark"] .chat-conversation,
.dark .chat-conversation,
body.dark .chat-conversation {
    background: #111827;
}

[data-theme-mode="dark"] .chat-conversation::-webkit-scrollbar-track,
[data-bs-theme="dark"] .chat-conversation::-webkit-scrollbar-track,
.dark .chat-conversation::-webkit-scrollbar-track,
body.dark .chat-conversation::-webkit-scrollbar-track {
    background: #374151;
}

[data-theme-mode="dark"] .chat-conversation::-webkit-scrollbar-thumb,
[data-bs-theme="dark"] .chat-conversation::-webkit-scrollbar-thumb,
.dark .chat-conversation::-webkit-scrollbar-thumb,
body.dark .chat-conversation::-webkit-scrollbar-thumb {
    background: #6b7280;
}

[data-theme-mode="dark"] .chat-conversation::-webkit-scrollbar-thumb:hover,
[data-bs-theme="dark"] .chat-conversation::-webkit-scrollbar-thumb:hover,
.dark .chat-conversation::-webkit-scrollbar-thumb:hover,
body.dark .chat-conversation::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}

[data-theme-mode="dark"] .user-message,
[data-bs-theme="dark"] .user-message,
.dark .user-message,
body.dark .user-message {
    background: #374151;
    color: #e5e7eb;
    border-color: #4b5563;
}

[data-theme-mode="dark"] .reply-form-container,
[data-bs-theme="dark"] .reply-form-container,
.dark .reply-form-container,
body.dark .reply-form-container {
    background: #1f2937;
    border-top-color: #4b5563;
}

[data-theme-mode="dark"] .reply-textarea,
[data-bs-theme="dark"] .reply-textarea,
.dark .reply-textarea,
body.dark .reply-textarea {
    background: #374151;
    color: #e5e7eb;
    border-color: #4b5563;
}

[data-theme-mode="dark"] .reply-textarea:focus,
[data-bs-theme="dark"] .reply-textarea:focus,
.dark .reply-textarea:focus,
body.dark .reply-textarea:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102,126,234,0.2);
}

[data-theme-mode="dark"] .kbd-key,
[data-bs-theme="dark"] .kbd-key,
.dark .kbd-key,
body.dark .kbd-key {
    background: #4b5563;
    color: #e5e7eb;
    border-color: #6b7280;
}

[data-theme-mode="dark"] .keyboard-shortcuts,
[data-bs-theme="dark"] .keyboard-shortcuts,
.dark .keyboard-shortcuts,
body.dark .keyboard-shortcuts {
    color: #9ca3af;
}

[data-theme-mode="dark"] .info-card,
[data-bs-theme="dark"] .info-card,
.dark .info-card,
body.dark .info-card {
    background: #1f2937;
}

[data-theme-mode="dark"] .info-card-body,
[data-bs-theme="dark"] .info-card-body,
.dark .info-card-body,
body.dark .info-card-body {
    color: #e5e7eb;
}

[data-theme-mode="dark"] .stat-card,
[data-bs-theme="dark"] .stat-card,
.dark .stat-card,
body.dark .stat-card {
    background: #374151;
    border-color: #4b5563;
}

[data-theme-mode="dark"] .stat-value,
[data-bs-theme="dark"] .stat-value,
.dark .stat-value,
body.dark .stat-value {
    color: #e5e7eb;
}

[data-theme-mode="dark"] .stat-label,
[data-bs-theme="dark"] .stat-label,
.dark .stat-label,
body.dark .stat-label {
    color: #9ca3af;
}

[data-theme-mode="dark"] .action-btn,
[data-bs-theme="dark"] .action-btn,
.dark .action-btn,
body.dark .action-btn {
    background: #374151;
    border-color: #4b5563;
    color: #e5e7eb;
}

[data-theme-mode="dark"] .action-btn:hover,
[data-bs-theme="dark"] .action-btn:hover,
.dark .action-btn:hover,
body.dark .action-btn:hover {
    border-color: #667eea;
    color: #667eea;
}

[data-theme-mode="dark"] .message-header h6,
[data-bs-theme="dark"] .message-header h6,
.dark .message-header h6,
body.dark .message-header h6 {
    color: #9ca3af;
}

[data-theme-mode="dark"] .message-time,
[data-bs-theme="dark"] .message-time,
.dark .message-time,
body.dark .message-time {
    color: #6b7280;
}

[data-theme-mode="dark"] .empty-state,
[data-bs-theme="dark"] .empty-state,
.dark .empty-state,
body.dark .empty-state {
    color: #9ca3af;
}

[data-theme-mode="dark"] .empty-state h5,
[data-bs-theme="dark"] .empty-state h5,
.dark .empty-state h5,
body.dark .empty-state h5 {
    color: #d1d5db;
}

/* Responsive */
@media (max-width: 768px) {
    .chat-list.left .conversation-list {
        margin-right: 5%;
    }
    
    .chat-list.right .conversation-list {
        margin-left: 5%;
    }
    
    .reply-form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .reply-btn {
        margin-top: 0.5rem;
    }
}

/* Animation */
.new-message {
    animation: slideInUp 0.4s ease-out;
}

@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Visual Feedback */
#replyMessage {
    transition: all 0.2s ease;
}

#replyMessage.bg-success-subtle {
    border-color: #10b981 !important;
    box-shadow: 0 0 0 3px rgba(16,185,129,0.1) !important;
}

[data-theme-mode="dark"] #replyMessage.bg-success-subtle,
[data-bs-theme="dark"] #replyMessage.bg-success-subtle,
.dark #replyMessage.bg-success-subtle,
body.dark #replyMessage.bg-success-subtle {
    border-color: #10b981 !important;
    box-shadow: 0 0 0 3px rgba(16,185,129,0.2) !important;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: #6b7280;
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}
</style>
';

$body['footer'] = '';

require_once(__DIR__ . '/header.php');
require_once(__DIR__ . '/sidebar.php');

// Lấy danh sách tin nhắn
$messages = $CMSNT->get_list("
    SELECT sm.*, 
           u.username as sender_username, u.email as user_email,
           a.username as admin_username, a.email as admin_email
    FROM `support_messages` sm 
    LEFT JOIN `users` u ON (sm.sender_id = u.id AND sm.sender_type = 'user') 
    LEFT JOIN `users` a ON (sm.sender_id = a.id AND sm.sender_type = 'admin')
    WHERE sm.ticket_id = '$ticket_id' 
    ORDER BY sm.created_at ASC
");

// Lấy thông tin đơn hàng nếu có
$order_info = null;
if ($ticket['order_id']) {
    $order_info = $CMSNT->get_row("SELECT * FROM `product_orders` WHERE `id` = '" . $ticket['order_id'] . "'");
}

?>

<div class="main-content app-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">
                <i class="ri-customer-service-2-line me-2"></i><?= __('Chi tiết Ticket'); ?> #<?= $ticket['id']; ?>
            </h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="<?= base_url_admin(''); ?>"><?= __('Dashboard'); ?></a></li>
                        <li class="breadcrumb-item"><a href="<?= base_url_admin('tickets'); ?>"><?= __('Support Tickets'); ?></a></li>
                        <li class="breadcrumb-item active" aria-current="page"><?= __('Chi tiết'); ?></li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="row">
            <!-- Cột trái: Cuộc hội thoại -->
            <div class="col-xl-7">
                <!-- Chat container -->
                <div class="chat-container mb-5">
                    <div class="chat-header">
                        <h6 class="mb-0">
                            <i class="ri-chat-3-line me-2"></i><?= __('Cuộc hội thoại'); ?>
                        </h6>
                    </div>
                    <div class="chat-conversation" data-simplebar>
                        <ul class="list-unstyled chat-conversation-list" id="chatMessages">
                            <!-- Tin nhắn đầu tiên từ user -->
                            <?php if (!empty($ticket['content'])): ?>
                                <li class="chat-list left">
                                    <div class="conversation-list">
                                        <div class="d-flex">
                                            <div class="flex-shrink-0">
                                                <div class="message-avatar user-avatar">
                                                    <img src="<?= getGravatarUrl($ticket['email']); ?>" alt="<?= $ticket['username']; ?>">
                                                </div>
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <div class="message-header">
                                                    <h6 class="text-secondary"><?= $ticket['username']; ?></h6>
                                                    <span class="user-badge"><?= __('Nội dung ban đầu'); ?></span>
                                                    <small class="message-time ms-auto" data-bs-toggle="tooltip" title="<?= timeAgo(strtotime($ticket['created_at'])); ?>"><?= date('H:i d/m/Y', strtotime($ticket['created_at'])); ?></small>
                                                </div>
                                                <div class="user-message">
                                                    <p class="mb-0"><?= nl2br(htmlspecialchars($ticket['content'])); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            <?php endif; ?>

                            <?php if (!empty($messages)): ?>
                                <?php foreach ($messages as $msg): ?>
                                    <?php if ($msg['sender_type'] == 'user'): ?>
                                        <!-- Tin nhắn từ user -->
                                        <li class="chat-list left" data-message-id="<?= $msg['id']; ?>">
                                            <div class="conversation-list">
                                                <div class="d-flex">
                                                    <div class="flex-shrink-0">
                                                        <div class="message-avatar user-avatar">
                                                            <img src="<?= getGravatarUrl($ticket['email']); ?>" alt="<?= $msg['sender_username'] ?: $ticket['username']; ?>">
                                                        </div>
                                                    </div>
                                                    <div class="flex-grow-1 ms-3">
                                                        <div class="message-header">
                                                            <h6 class="text-secondary"><?= $msg['sender_username'] ?: $ticket['username']; ?></h6>
                                                            <small class="message-time ms-auto" data-bs-toggle="tooltip" title="<?= timeAgo(strtotime($msg['created_at'])); ?>"><?= date('H:i d/m/Y', strtotime($msg['created_at'])); ?></small>
                                                        </div>
                                                        <div class="user-message">
                                                            <p class="mb-0"><?= nl2br(htmlspecialchars($msg['message'])); ?></p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </li>
                                    <?php else: ?>
                                        <!-- Tin nhắn từ admin -->
                                        <li class="chat-list right" data-message-id="<?= $msg['id']; ?>">
                                            <div class="conversation-list">
                                                <div class="d-flex">
                                                    <div class="flex-grow-1 me-3">
                                                        <div class="message-header justify-content-end">
                                                            <small class="message-time me-auto" data-bs-toggle="tooltip" title="<?= timeAgo(strtotime($msg['created_at'])); ?>"><?= date('H:i d/m/Y', strtotime($msg['created_at'])); ?></small>
                                                            <span class="admin-badge"><?= __('Admin Support'); ?></span>
                                                            <h6 class="text-muted"><?= $msg['admin_email'] ?: __('Admin'); ?></h6>
                                                        </div>
                                                        <div class="admin-message text-end">
                                                            <p class="mb-0"><?= nl2br(htmlspecialchars($msg['message'])); ?></p>
                                                        </div>
                                                    </div>
                                                    <div class="flex-shrink-0">
                                                        <div class="message-avatar admin-avatar">
                                                            <i class="ri-admin-line"></i>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <?php if (empty($messages) && empty($ticket['content'])): ?>
                                <li class="text-center py-4">
                                    <div class="empty-state">
                                        <i class="ri-message-line"></i>
                                        <h5 class="mt-3"><?= __('Chưa có tin nhắn nào'); ?></h5>
                                        <p class="mb-0"><?= __('Hãy gửi tin nhắn đầu tiên để bắt đầu cuộc hội thoại'); ?></p>
                                    </div>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <!-- Form trả lời -->
                    <div class="reply-form-container">
                        <form class="reply-ticket-form">
                            <input type="hidden" name="ticket_id" value="<?= $ticket['id']; ?>">
                            <div class="reply-form">
                                <textarea id="replyMessage" name="message" class="reply-textarea"
                                    rows="3" placeholder="<?= __('Nhập phản hồi của bạn...'); ?>" required></textarea>
                                <button type="submit" class="reply-btn">
                                    <i class="ri-send-plane-line me-1"></i><?= __('Gửi'); ?>
                                </button>
                            </div>
                            <div class="keyboard-shortcuts">
                                <i class="ri-information-line me-1"></i><?= __('Phím tắt gửi nhanh'); ?>:
                                <span class="kbd-key">⌘</span> + <span class="kbd-key">Enter</span>
                                <span class="text-muted mx-1"><?= __('hoặc'); ?></span>
                                <span class="kbd-key opacity-50">Ctrl</span> + <span class="kbd-key opacity-50">Enter</span>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Cột phải: Thông tin ticket và user -->
            <div class="col-xl-5">
                <!-- Thông tin ticket -->
                <div class="info-card">
                    <div class="info-card-header">
                        <i class="ri-information-line me-2"></i><?= __('Thông tin Ticket'); ?>
                    </div>
                    <div class="info-card-body">
                        <!-- Status & Actions -->
                        <div class="d-flex align-items-center justify-content-between mb-4">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-secondary-transparent fs-12">#<?= $ticket['id']; ?></span>
                                <?= display_status_support_tickets($ticket['status']); ?>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <i class="ri-settings-3-line"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    <?php if ($ticket['status'] != 'answered'): ?>
                                        <li><button class="dropdown-item" onclick="changeStatus(<?= $ticket['id']; ?>, 'answered')">
                                                <i class="ri-check-line me-2"></i><?= __('Đánh dấu đã trả lời'); ?>
                                            </button></li>
                                    <?php endif; ?>
                                    <?php if ($ticket['status'] != 'closed'): ?>
                                        <li><button class="dropdown-item" onclick="changeStatus(<?= $ticket['id']; ?>, 'closed')">
                                                <i class="ri-close-line me-2"></i><?= __('Đóng ticket'); ?>
                                            </button></li>
                                    <?php endif; ?>
                                    <?php if ($ticket['status'] == 'closed'): ?>
                                        <li><button class="dropdown-item" onclick="changeStatus(<?= $ticket['id']; ?>, 'open')">
                                                <i class="ri-play-line me-2"></i><?= __('Mở lại'); ?>
                                            </button></li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>

                        <!-- Tiêu đề -->
                        <div class="mb-3">
                            <label class="form-label text-muted mb-1"><?= __('Tiêu đề'); ?></label>
                            <h6 class="mb-0 fw-semibold"><?= htmlspecialchars($ticket['subject']); ?></h6>
                        </div>

                        <!-- Danh mục -->
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label text-muted mb-1"><?= __('Danh mục'); ?></label>
                                <div>
                                    <span class="badge bg-info-transparent">
                                        <i class="ri-tag-line me-1"></i><?= $config_category_support_tickets[$ticket['category']] ?? $ticket['category']; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="form-label text-muted mb-1"><?= __('Ưu tiên'); ?></label>
                                <div>
                                    <?php
                                    $priority_class = 'warning';
                                    $priority_icon = 'ri-flag-line';
                                    if ($ticket['category'] == 'order') {
                                        $priority_class = 'danger';
                                        $priority_icon = 'ri-flag-fill';
                                    }
                                    ?>
                                    <span class="badge bg-<?= $priority_class; ?>-transparent">
                                        <i class="<?= $priority_icon; ?> me-1"></i><?= ucfirst($ticket['category'] == 'order' ? 'Cao' : 'Thường'); ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Thời gian -->
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label text-muted mb-1"><?= __('Ngày tạo'); ?></label>
                                <div class="d-flex align-items-center">
                                    <i class="ri-calendar-line me-2 text-primary"></i>
                                    <div>
                                        <div class="fw-medium"><?= date('d/m/Y', strtotime($ticket['created_at'])); ?></div>
                                        <small class="text-muted"><?= date('H:i', strtotime($ticket['created_at'])); ?></small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="form-label text-muted mb-1"><?= __('Cập nhật'); ?></label>
                                <div class="d-flex align-items-center">
                                    <i class="ri-time-line me-2 text-success"></i>
                                    <div>
                                        <div class="fw-medium"><?= date('d/m/Y', strtotime($ticket['updated_at'])); ?></div>
                                        <small class="text-muted"><?= timeAgo(strtotime($ticket['updated_at'])); ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Chi tiết đơn hàng -->
                <?php if ($order_info): ?>
                    <?php
                    // Tính toán các giá trị số lượng cho product_orders
                    $quantity_ordered = intval($order_info['quantity'] ?? 1); // Số lượng đặt

                    // Tính toán tiền tệ cho product_orders
                    $total_price = floatval($order_info['total_price'] ?? 0); // Giá gốc
                    $sale_price = floatval($order_info['sale_price'] ?? 0); // Giá khuyến mãi
                    $cost_price = floatval($order_info['cost_price'] ?? 0); // Giá vốn
                    $discount_amount = floatval($order_info['discount_amount'] ?? 0); // Số tiền giảm giá

                    // Tính số tiền thực thanh toán
                    $final_amount = floatval($order_info['final_amount'] ?? 0);
                    if ($final_amount <= 0) {
                        $final_amount = ($sale_price > 0 && $sale_price < $total_price)
                            ? $sale_price
                            : ($total_price - $discount_amount);
                    }

                    // Lợi nhuận
                    $profit = $final_amount - $cost_price;

                    // Status text
                    $status_text = ucfirst($order_info['status'] ?? 'pending');

                    // Lấy tên sản phẩm/plan
                    $product_name = $order_info['product_name'] ?? '';
                    $plan_name = $order_info['plan_name'] ?? '';
                    $display_name = $plan_name ?: $product_name;
                    ?>
                    <div class="info-card">
                        <div class="info-card-header">
                            <i class="ri-shopping-cart-line me-2"></i><?= __('Chi tiết đơn hàng'); ?>
                        </div>
                        <div class="info-card-body">
                            <!-- Order ID và Status -->
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div>
                                    <span class="badge bg-secondary-transparent fs-12">#<?= $order_info['trans_id'] ?? $order_info['id']; ?></span>
                                </div>
                                <div>
                                    <?php
                                    $status = $order_info['status'] ?? 'pending';
                                    $status_class = 'secondary';
                                    $status_labels = [
                                        'pending' => __('Chờ xử lý'),
                                        'processing' => __('Đang xử lý'),
                                        'completed' => __('Hoàn thành'),
                                        'success' => __('Thành công'),
                                        'cancelled' => __('Đã hủy'),
                                        'refunded' => __('Đã hoàn tiền'),
                                    ];
                                    $status_text = $status_labels[$status] ?? ucfirst($status);
                                    switch ($status) {
                                        case 'completed':
                                        case 'success':
                                            $status_class = 'success';
                                            break;
                                        case 'processing':
                                            $status_class = 'info';
                                            break;
                                        case 'cancelled':
                                        case 'refunded':
                                            $status_class = 'danger';
                                            break;
                                        case 'pending':
                                            $status_class = 'warning';
                                            break;
                                    }
                                    ?>
                                    <span class="badge bg-<?= $status_class; ?>-transparent"><?= $status_text; ?></span>
                                </div>
                            </div>

                            <!-- Product/Plan info -->
                            <?php if ($display_name): ?>
                                <div class="mb-4">
                                    <div class="mb-2">
                                        <label class="form-label text-muted mb-1 small"><?= __('Sản phẩm'); ?></label>
                                        <div class="fw-medium"><?= htmlspecialchars($display_name); ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Chi tiết số lượng -->
                            <div class="mb-4">
                                <label class="form-label text-muted mb-2 small"><?= __('Số lượng'); ?></label>
                                <table class="table table-sm table-borderless mb-0">
                                    <tbody>
                                        <tr>
                                            <td class="text-muted" style="width: 50%;"><?= __('Số lượng đặt'); ?>:</td>
                                            <td class="text-end fw-medium"><?= number_format($quantity_ordered); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Thông tin tài chính -->
                            <div class="mb-4">
                                <label class="form-label text-muted mb-2 small"><?= __('Tài chính'); ?></label>
                                <table class="table table-sm table-borderless mb-0">
                                    <tbody>
                                        <tr>
                                            <td class="text-muted" style="width: 50%;"><?= __('Giá gốc'); ?>:</td>
                                            <td class="text-end fw-medium"><?= format_cash($total_price); ?></td>
                                        </tr>
                                        <?php if ($sale_price > 0 && $sale_price < $total_price): ?>
                                            <tr>
                                                <td class="text-muted"><?= __('Giá khuyến mãi'); ?>:</td>
                                                <td class="text-end fw-medium text-success"><?= format_cash($sale_price); ?></td>
                                            </tr>
                                        <?php endif; ?>
                                        <?php if ($discount_amount > 0): ?>
                                            <tr>
                                                <td class="text-muted"><?= __('Giảm giá'); ?>:</td>
                                                <td class="text-end fw-medium text-danger">-<?= format_cash($discount_amount); ?></td>
                                            </tr>
                                        <?php endif; ?>
                                        <tr>
                                            <td class="text-muted"><?= __('Đã thanh toán'); ?>:</td>
                                            <td class="text-end fw-medium text-primary"><?= format_cash($final_amount); ?></td>
                                        </tr>
                                        <?php if ($cost_price > 0): ?>
                                            <tr>
                                                <td class="text-muted"><?= __('Giá vốn'); ?>:</td>
                                                <td class="text-end fw-medium"><?= format_cash($cost_price); ?></td>
                                            </tr>
                                        <?php endif; ?>
                                        <?php if ($profit > 0 && $cost_price > 0): ?>
                                            <tr>
                                                <td class="text-muted"><?= __('Lợi nhuận'); ?>:</td>
                                                <td class="text-end fw-medium text-success"><?= format_cash($profit); ?></td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Thông tin bổ sung -->
                            <?php if (!empty($order_info['note']) || !empty($order_info['reason'])): ?>
                                <div class="mb-4">
                                    <?php if ($order_info['note']): ?>
                                        <div class="mb-2">
                                            <label class="form-label text-muted mb-1 small"><?= __('Ghi chú'); ?></label>
                                            <div class="small text-dark"><?= nl2br(htmlspecialchars($order_info['note'] ?? '')); ?></div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($order_info['reason']): ?>
                                        <div>
                                            <label class="form-label text-muted mb-1 small"><?= __('Lý do'); ?></label>
                                            <div class="small text-dark"><?= nl2br(htmlspecialchars($order_info['reason'] ?? '')); ?></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Order actions -->
                            <div class="d-grid gap-2">
                                <a href="<?= base_url_admin('order-edit&id=' . $order_info['id']); ?>" class="action-btn">
                                    <i class="ri-edit-line me-1"></i><?= __('Chỉnh sửa đơn hàng'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Thông tin user -->
                <div class="info-card">
                    <div class="info-card-header">
                        <i class="ri-user-line me-2"></i><?= __('Thông tin khách hàng'); ?>
                    </div>
                    <div class="info-card-body">
                        <!-- User info -->
                        <div class="d-flex align-items-center mb-3">
                            <div class="flex-shrink-0">
                                <div class="message-avatar user-avatar" style="width: 50px; height: 50px;">
                                    <img src="<?= getGravatarUrl($ticket['email']); ?>" alt="<?= $ticket['username']; ?>">
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-1 fw-semibold"><?= $ticket['username']; ?></h6>
                                <div class="text-muted mb-1"><?= $ticket['email']; ?></div>
                                <small class="badge bg-info-transparent">
                                    <i class="ri-vip-crown-line me-1"></i>
                                    <?php
                                    $user_rank = 'Thành viên';
                                    if ($ticket['money'] >= 1000000) $user_rank = 'VIP';
                                    if ($ticket['money'] >= 5000000) $user_rank = 'Diamond';
                                    echo $user_rank;
                                    ?>
                                </small>
                            </div>
                        </div>

                        <!-- User stats expanded -->
                        <?php
                        $user_orders = $CMSNT->get_row("SELECT COUNT(id) as total, SUM(total_price) as spent FROM `product_orders` WHERE user_id = '" . $ticket['user_id'] . "'");
                        $user_tickets = $CMSNT->get_row("SELECT COUNT(id) as total FROM support_tickets WHERE user_id = '" . $ticket['user_id'] . "'");
                        $update_date = $CMSNT->get_row("SELECT update_date FROM users WHERE id = '" . $ticket['user_id'] . "'");
                        ?>
                        <div class="stats-grid mb-3">
                            <div class="stat-card">
                                <div class="stat-value text-primary"><?= format_cash($ticket['money']); ?></div>
                                <div class="stat-label"><?= __('Số dư hiện tại'); ?></div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value text-success"><?= format_cash($user_orders['spent'] ?? 0); ?></div>
                                <div class="stat-label"><?= __('Tổng chi tiêu'); ?></div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value text-info"><?= $user_orders['total'] ?? 0; ?></div>
                                <div class="stat-label"><?= __('Đơn hàng'); ?></div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value text-warning"><?= $user_tickets['total']; ?></div>
                                <div class="stat-label"><?= __('Tickets'); ?></div>
                            </div>
                        </div>

                        <!-- Last activity -->
                        <?php if ($update_date && isset($update_date['update_date']) && $update_date['update_date']): ?>
                            <div class="mb-3">
                                <label class="form-label text-muted mb-1"><?= __('Hoạt động cuối'); ?></label>
                                <div class="d-flex align-items-center">
                                    <i class="ri-time-line me-2 text-muted"></i>
                                    <small><?= timeAgo(strtotime($update_date['update_date'])); ?></small>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Quick Actions -->
                        <div class="d-grid gap-2">
                            <a href="<?= base_url_admin('user-edit&id=' . $ticket['user_id']); ?>" class="action-btn">
                                <i class="ri-user-settings-line me-1"></i><?= __('Quản lý tài khoản'); ?>
                            </a>
                            <a href="<?= base_url_admin('product-orders&user_id=' . $ticket['user_id']); ?>" class="action-btn">
                                <i class="ri-shopping-bag-line me-1"></i><?= __('Lịch sử đơn hàng'); ?>
                            </a>
                            <a href="<?= base_url_admin('tickets&username=' . $ticket['username']); ?>" class="action-btn">
                                <i class="ri-file-list-line me-1"></i><?= __('Tất cả tickets'); ?>
                            </a>
                            <button class="action-btn" onclick="sendMessage('<?= $ticket['username']; ?>')">
                                <i class="ri-mail-send-line me-1"></i><?= __('Gửi thông báo'); ?>
                            </button>
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

<script>
    $(document).ready(function() {
        // Phát hiện hệ điều hành và cập nhật hiển thị phím tắt
        var isMac = navigator.platform.toUpperCase().indexOf('MAC') >= 0;
        if (isMac) {
            // Ẩn Ctrl+Enter và hiển thị rõ Cmd+Enter cho macOS
            $('.keyboard-shortcuts').html(`
            <i class="ri-information-line me-1"></i><?= __('Phím tắt gửi nhanh'); ?>:
            <span class="kbd-key">⌘</span> + <span class="kbd-key">Enter</span>
            <span class="text-muted mx-1">hoặc</span>
            <span class="kbd-key opacity-50">Ctrl</span> + <span class="kbd-key opacity-50">Enter</span>
        `);
        }

        // Tự động cuộn đến tin nhắn mới nhất
        function scrollToBottom() {
            // Tìm tin nhắn cuối cùng
            var lastMessage = $(".chat-conversation-list li:last-child");
            if (lastMessage.length) {
                // Scroll đến tin nhắn cuối cùng
                lastMessage[0].scrollIntoView({
                    behavior: 'smooth',
                    block: 'end'
                });
            }

            // Backup: scroll container nếu có
            var chatContainer = $(".chat-conversation");
            if (chatContainer.length) {
                setTimeout(function() {
                    chatContainer.scrollTop(chatContainer[0].scrollHeight);
                }, 100);
            }
        }

        // Cuộn đến cuối khi trang load với multiple attempts
        setTimeout(function() {
            scrollToBottom();
        }, 100);

        // Backup scroll khi DOM hoàn toàn load
        setTimeout(function() {
            scrollToBottom();
        }, 500);

        // Final scroll khi window load
        $(window).on('load', function() {
            setTimeout(scrollToBottom, 200);
        });

        // MutationObserver để theo dõi thay đổi trong chat list
        if (window.MutationObserver) {
            var chatList = document.querySelector('.chat-conversation-list');
            if (chatList) {
                var observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                            // Có tin nhắn mới được thêm, auto scroll
                            setTimeout(scrollToBottom, 300);
                        }
                    });
                });

                observer.observe(chatList, {
                    childList: true,
                    subtree: true
                });
            }
        }

        // Auto load tin nhắn mới
        var lastMessageId = 0;
        var isLoadingMessages = false;
        var justSentMessage = false;
        var lastAdminMessageTime = 0; // Track thời gian tin nhắn admin cuối

        // Lấy ID tin nhắn cuối cùng hiện tại
        function getLastMessageId() {
            var messages = $(".chat-conversation-list li[data-message-id]");
            var realLastId = 0;

            messages.each(function() {
                var msgId = $(this).attr('data-message-id');
                if (msgId && !msgId.startsWith('new-') && !msgId.startsWith('admin-')) {
                    var numId = parseInt(msgId);
                    if (!isNaN(numId) && numId > realLastId) {
                        realLastId = numId;
                    }
                }
            });

            return realLastId;
        }

        // Load tin nhắn mới
        function loadNewMessages() {
            // Skip nếu vừa gửi tin nhắn hoặc đang loading
            if (isLoadingMessages || justSentMessage) {
                return;
            }

            isLoadingMessages = true;
            var currentLastId = getLastMessageId();

            $.ajax({
                url: "<?= BASE_URL('ajaxs/admin/ticket.php'); ?>",
                type: "POST",
                data: {
                    action: 'get_new_messages_admin',
                    ticket_id: "<?= $ticket['id']; ?>",
                    last_message_id: currentLastId
                },
                dataType: "json",
                success: function(response) {
                    if (response.status == "success" && response.messages && response.messages.length > 0) {
                        var chatList = $(".chat-conversation-list");
                        var hasNewUserMessages = false;

                        $.each(response.messages, function(index, msg) {
                            // Chỉ xử lý tin nhắn từ USER
                            if (msg.sender_type !== 'user') {
                                return; // Skip tất cả tin nhắn admin
                            }

                            // Kiểm tra duplicate
                            if ($(`li[data-message-id="${msg.id}"]`).length > 0) {
                                return; // Skip nếu đã có
                            }

                            // Tạo HTML cho tin nhắn user
                            var messageHtml = `
                        <li class="chat-list left new-message" data-message-id="${msg.id}">
                            <div class="conversation-list">
                                <div class="d-flex">
                                    <div class="flex-shrink-0">
                                        <div class="message-avatar user-avatar">
                                            <img src="<?= getGravatarUrl($ticket['email']); ?>" alt="${msg.username}">
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <div class="message-header">
                                            <h6 class="text-secondary">${msg.username}</h6>
                                            <small class="message-time ms-auto" data-bs-toggle="tooltip" title="${msg.time_ago}">${msg.formatted_time}</small>
                                        </div>
                                        <div class="user-message">
                                            <p class="mb-0">${msg.message}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>`;

                            chatList.append(messageHtml);
                            hasNewUserMessages = true;
                        });

                        // Chỉ scroll nếu có tin nhắn user mới
                        if (hasNewUserMessages) {
                            setTimeout(scrollToBottom, 200);
                            $('[data-bs-toggle="tooltip"]').tooltip();
                        }
                    }
                },
                error: function() {
                    // Silent fail
                },
                complete: function() {
                    isLoadingMessages = false;
                }
            });
        }

        // Manual refresh
        window.loadNewMessages = loadNewMessages;

        // Polling tin nhắn mới mỗi 5 giây (tăng lên để giảm tần suất)
        setInterval(loadNewMessages, 5000);

        // Load ngay lần đầu sau 3 giây
        setTimeout(loadNewMessages, 3000);

        // Xử lý trả lời ticket
        $(".reply-ticket-form").on("submit", function(e) {
            e.preventDefault();

            var form = $(this);
            var message = $("#replyMessage").val().trim();
            var ticket_id = $("input[name='ticket_id']").val();
            var submitBtn = form.find("button[type=submit]");

            if (!message) {
                Swal.fire({
                    icon: "warning",
                    title: "<?= __('Cảnh báo'); ?>",
                    text: "<?= __('Vui lòng nhập nội dung phản hồi'); ?>"
                });
                return;
            }

            // Set flag để tránh auto-load trong 10 giây (tăng lên)
            justSentMessage = true;
            setTimeout(function() {
                justSentMessage = false;
            }, 10000);

            // Update thời gian tin nhắn admin cuối
            lastAdminMessageTime = Date.now();

            submitBtn.prop("disabled", true).html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span><?= __('Đang gửi...'); ?>');

            $.ajax({
                url: "<?= BASE_URL('ajaxs/admin/ticket.php'); ?>",
                type: "POST",
                data: {
                    action: 'replyTicket',
                    ticket_id: ticket_id,
                    message: message
                },
                dataType: "json",
                success: function(response) {
                    if (response.status == "success") {
                        // Thêm tin nhắn admin mới vào chat
                        var chatList = $(".chat-conversation-list");
                        var currentTime = new Date();
                        var timeString = currentTime.toLocaleTimeString('vi-VN', {
                            hour: '2-digit',
                            minute: '2-digit'
                        }) + ' ' + currentTime.toLocaleDateString('vi-VN');

                        var adminEmail = response.admin_email || '<?= __('Admin'); ?>';

                        // Sử dụng ID unique với timestamp để tránh conflict
                        var uniqueId = `admin-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;

                        var newMessageHtml = `
                    <li class="chat-list right new-message" data-message-id="${uniqueId}">
                        <div class="conversation-list">
                            <div class="d-flex">
                                <div class="flex-grow-1 me-3">
                                    <div class="message-header justify-content-end">
                                        <small class="message-time me-auto" title="Vừa gửi">${timeString}</small>
                                        <span class="admin-badge"><?= __('Admin Support'); ?></span>
                                        <h6 class="text-muted">${adminEmail}</h6>
                                    </div>
                                    <div class="admin-message text-end">
                                        <p class="mb-0">${message.replace(/\n/g, '<br>')}</p>
                                    </div>
                                </div>
                                <div class="flex-shrink-0">
                                    <div class="message-avatar admin-avatar">
                                        <i class="ri-admin-line"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </li>`;

                        chatList.append(newMessageHtml);
                        $("#replyMessage").val("");
                        setTimeout(scrollToBottom, 100);

                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "<?= __('Lỗi'); ?>",
                            text: response.msg
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: "error",
                        title: "<?= __('Lỗi kết nối'); ?>",
                        text: "<?= __('Không thể kết nối đến server'); ?>"
                    });
                },
                complete: function() {
                    submitBtn.prop("disabled", false).html('<i class="ri-send-plane-line me-1"></i><?= __('Gửi'); ?>');
                    // Reset flag sau khi hoàn thành (nhưng vẫn giữ delay)
                    setTimeout(function() {
                        justSentMessage = false;
                    }, 3000);
                }
            });
        });

        // Shortcut Ctrl+Enter (Windows/Linux) hoặc Cmd+Enter (macOS) để gửi
        $("#replyMessage").on("keydown", function(e) {
            // Kiểm tra cả Ctrl (Windows/Linux) và Cmd (macOS)
            var isShortcut = (e.ctrlKey || e.metaKey) && e.key === 'Enter';

            if (isShortcut) {
                e.preventDefault();
                e.stopPropagation();

                // Kiểm tra nếu đang gửi tin nhắn
                if ($(this).closest('form').find('button[type=submit]').prop('disabled')) {
                    return;
                }

                // Visual feedback mạnh mẽ hơn
                $(this).addClass('bg-success-subtle');

                // Thêm hiệu ứng ripple
                var ripple = $('<div class="position-absolute bg-success rounded-circle" style="width: 20px; height: 20px; opacity: 0.3; pointer-events: none; transform: scale(0);"></div>');
                $(this).parent().css('position', 'relative').append(ripple);

                ripple.css({
                    top: '50%',
                    left: '50%',
                    transform: 'translate(-50%, -50%) scale(0)',
                    transition: 'transform 0.6s ease-out, opacity 0.6s ease-out'
                });

                setTimeout(() => {
                    ripple.css({
                        transform: 'translate(-50%, -50%) scale(10)',
                        opacity: '0'
                    });
                }, 10);

                setTimeout(() => {
                    $(this).removeClass('bg-success-subtle');
                    ripple.remove();
                }, 600);

                $(".reply-ticket-form").submit();
            }

            // Thêm hint text khi ấn Ctrl/Cmd (nhưng chưa ấn Enter)
            var isModifierKey = e.ctrlKey || e.metaKey;
            if (isModifierKey && e.key !== 'Enter') {
                var hint = $(this).siblings('.ctrl-hint');
                if (hint.length === 0) {
                    var keyName = e.metaKey ? 'Cmd' : 'Ctrl';
                    hint = $('<small class="ctrl-hint text-success position-absolute" style="bottom: -20px; left: 0; font-size: 11px; opacity: 0.8;"><i class="ri-keyboard-line me-1"></i>Nhấn thêm Enter để gửi (' + keyName + '+Enter)</small>');
                    $(this).parent().css('position', 'relative').append(hint);

                    setTimeout(() => {
                        hint.fadeOut(300, function() {
                            $(this).remove();
                        });
                    }, 2000);
                }
            }
        });

        // Xóa hint khi thả phím Ctrl/Cmd
        $("#replyMessage").on("keyup", function(e) {
            if (e.key === 'Control' || e.key === 'Meta') {
                $(this).siblings('.ctrl-hint').fadeOut(200, function() {
                    $(this).remove();
                });
            }
        });

        // Focus event để hiển thị hint
        $("#replyMessage").on("focus", function() {
            if (!$(this).hasClass('focused-once')) {
                $(this).addClass('focused-once');

                // Phát hiện hệ điều hành để hiển thị phím tắt phù hợp
                var isMac = navigator.platform.toUpperCase().indexOf('MAC') >= 0;
                var shortcutText = isMac ? 'Cmd+Enter' : 'Ctrl+Enter';
                var shortcutIcon = isMac ? '⌘' : 'Ctrl';

                // Hiển thị tooltip hint lần đầu focus
                setTimeout(() => {
                    if ($(this).is(':focus')) {
                        var tooltip = $('<div class="custom-tooltip position-absolute bg-dark text-white px-2 py-1 rounded small" style="bottom: calc(100% + 8px); left: 50%; transform: translateX(-50%); z-index: 1000; opacity: 0;">💡 Mẹo: ' + shortcutText + ' để gửi nhanh</div>');
                        $(this).parent().css('position', 'relative').append(tooltip);

                        tooltip.animate({
                            opacity: 1
                        }, 300);

                        setTimeout(() => {
                            tooltip.animate({
                                opacity: 0
                            }, 300, function() {
                                $(this).remove();
                            });
                        }, 3000);
                    }
                }, 800);
            }
        });

        // Initialize tooltips
        $('[data-bs-toggle="tooltip"]').tooltip();
    });

    // Change status function
    function changeStatus(id, status) {
        let statusText = '';
        let alertIcon = 'question';

        switch (status) {
            case 'answered':
                statusText = '<?= __('đã trả lời'); ?>';
                alertIcon = 'warning';
                break;
            case 'closed':
                statusText = '<?= __('đóng'); ?>';
                alertIcon = 'warning';
                break;
            case 'open':
                statusText = '<?= __('mở lại'); ?>';
                alertIcon = 'warning';
                break;
        }

        Swal.fire({
            icon: alertIcon,
            title: "<?= __('Xác nhận thay đổi'); ?>",
            text: "<?= __('Bạn có chắc chắn muốn đánh dấu ticket này là'); ?> " + statusText + "?",
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: "<?= __('Xác nhận'); ?>",
            cancelButtonText: "<?= __('Hủy'); ?>"
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: "<?= BASE_URL('ajaxs/admin/ticket.php'); ?>",
                    type: 'POST',
                    dataType: "JSON",
                    data: {
                        action: 'changeStatusTicket',
                        id: id,
                        status: status
                    },
                    success: function(result) {
                        if (result.status == 'success') {
                            Swal.fire({
                                icon: "success",
                                title: "<?= __('Thành công'); ?>",
                                text: result.msg,
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: "error",
                                title: "<?= __('Lỗi'); ?>",
                                text: result.msg
                            });
                        }
                    }
                });
            }
        });
    }
</script>