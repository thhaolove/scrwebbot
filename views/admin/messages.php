<?php if (!defined('IN_SITE')) {
    die('The Request Not Found');
}


$body = [
    'title'   => __('Support Tickets') . ' | ' . $CMSNT->site('title'),
    'desc'    => $CMSNT->site('description'),
    'keyword' => $CMSNT->site('keywords')
];

$body['header'] = '
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<style>
.chat-info {
    width: 360px;
    background: var(--bs-card-bg);
}
.chat-info .chat-users-tab {
    max-height: calc(100vh - 220px);
    overflow-y: auto;
}
@media (max-width: 1400px) {
    .chat-info .chat-users-tab {
        max-height: 800px;
    }
}
.chat-info .ticket-active {
    background: rgba(105,108,255,.08);
    border-inline-start: 3px solid var(--primary-color);
}
.chat-info .ticket-active .fw-semibold,
.chat-info .ticket-active .chat-msg {
    color: var(--primary-color);
}
.chat-info .checkforactive {
    transition: background-color 0.16s ease, box-shadow 0.16s ease;
}
.chat-info .checkforactive:hover {
    background-color: rgba(63, 99, 255, 0.08);
    box-shadow: 0 10px 24px rgba(15, 34, 58, 0.1);
}
.chat-info .checkforactive.ticket-active:hover {
    background-color: rgba(105, 108, 255, 0.12);
    box-shadow: none;
}
[data-bs-theme="dark"] .chat-info .checkforactive:hover,
[data-theme-mode="dark"] .chat-info .checkforactive:hover {
    background-color: rgba(99, 102, 241, 0.18);
    box-shadow: 0 10px 26px rgba(8, 15, 45, 0.45);
}
[data-bs-theme="dark"] .chat-info .checkforactive.ticket-active:hover,
[data-theme-mode="dark"] .chat-info .checkforactive.ticket-active:hover {
    background-color: rgba(99, 102, 241, 0.24);
    box-shadow: none;
}
.chat-info .chat-msg {
    max-width: 200px;
}
.chat-info .ticket-item-btn {
    display: block;
    width: 100%;
    border: none;
    background: transparent;
    padding: 0;
    text-align: left;
    color: inherit;
    font: inherit;
    cursor: pointer;
}
.chat-info .ticket-item-btn:focus-visible {
    outline: 2px solid var(--primary-color);
    outline-offset: 2px;
}
.chat-info .ticket-item-btn:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}
.main-chat-area {
    flex: 1;
    background: var(--bs-card-bg);
    display: flex;
    flex-direction: column;
    min-height: 680px;
}
.chat-content {
    flex: 1;
    overflow-y: auto;
    padding: 1.25rem;
}
.chat-content ul {
    margin-bottom: 0;
}
.chat-item-end .main-chat-msg,
.chat-item-end .main-chat-msg div {
    text-align: left;
}
.message-context-menu {
    position: fixed;
    z-index: 2000;
    min-width: 190px;
    background-color: #ffffff;
    border: 1px solid rgba(17, 25, 40, 0.1);
    border-radius: 0.75rem;
    box-shadow: 0 18px 45px rgba(15, 23, 42, 0.18);
    display: none;
    overflow: hidden;
}
.message-context-menu__group {
    border-top: 1px solid rgba(17, 25, 40, 0.05);
    padding-top: 0.2rem;
    margin-top: 0.2rem;
}
.message-context-menu__group:first-child {
    border-top: none;
    padding-top: 0;
    margin-top: 0;
}
.message-context-menu button {
    display: flex;
    align-items: center;
    width: 100%;
    background: transparent;
    border: none;
    padding: 0.55rem 1rem;
    font-size: 13px;
    color: #1f2937;
    gap: 0.55rem;
    transition: background-color 0.12s ease, color 0.12s ease;
}
.message-context-menu button i {
    font-size: 16px;
    opacity: 0.88;
}
.message-context-menu button:hover {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.16), rgba(59, 130, 246, 0.12));
    color: #1d4ed8;
}
.message-context-menu button:hover i {
    opacity: 1;
}
.message-context-menu button.text-danger {
    color: #dc2626;
}
.message-context-menu button.text-danger:hover {
    background: linear-gradient(135deg, rgba(220, 38, 38, 0.18), rgba(248, 113, 113, 0.12));
    color: #b91c1c;
}
[data-bs-theme="dark"] .message-context-menu,
[data-theme-mode="dark"] .message-context-menu {
    background-color: #151b27;
    border-color: rgba(255, 255, 255, 0.04);
    box-shadow: 0 18px 48px rgba(8, 15, 32, 0.7);
}
[data-bs-theme="dark"] .message-context-menu button,
[data-theme-mode="dark"] .message-context-menu button {
    color: #e2e8f0;
}
[data-bs-theme="dark"] .message-context-menu button:hover,
[data-theme-mode="dark"] .message-context-menu button:hover {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.28), rgba(59, 130, 246, 0.18));
    color: #c7d2fe;
}
[data-bs-theme="dark"] .message-context-menu button.text-danger,
[data-theme-mode="dark"] .message-context-menu button.text-danger {
    color: #f87171;
}
[data-bs-theme="dark"] .message-context-menu button.text-danger:hover,
[data-theme-mode="dark"] .message-context-menu button.text-danger:hover {
    background: linear-gradient(135deg, rgba(248, 113, 113, 0.28), rgba(252, 165, 165, 0.18));
    color: #fecaca;
}
.chat-day-label {
    text-align: center;
    margin: 1rem 0;
}
.chat-day-label span {
    display: inline-block;
    background: var(--bs-light);
    padding: 0.25rem 0.75rem;
    border-radius: 50rem;
    font-size: 12px;
    color: var(--bs-secondary-color);
} 
.chat-footer {
    border-top: 1px solid var(--bs-border-color);
    padding: 1rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    background: var(--bs-card-bg);
}
.chat-footer form {
    display: contents;
}
.emoji-dropdown {
    position: relative;
}
.emoji-panel {
    position: absolute;
    bottom: 100%;
    right: 0;
    width: 420px;
    max-height: 260px;
    overflow-y: auto;
    padding: 0.75rem;
    background: #ffffff;
    border: 1px solid #d6d6d6;
    border-radius: 0.75rem;
    box-shadow: 0 10px 30px rgba(15,34,58,.1);
    display: none;
    z-index: 30;
}
.emoji-panel.active {
    display: block;
}
.emoji-panel .emoji-grid {
    display: grid;
    grid-template-columns: repeat(8, 1fr);
    gap: 0.35rem;
}
.emoji-panel button {
    border: none;
    background: transparent;
    font-size: 1.4rem;
    line-height: 1;
    padding: 0.35rem;
    transition: transform 0.1s ease;
}
.emoji-panel button:hover {
    transform: scale(1.2);
}
[data-bs-theme="dark"] .emoji-panel,
[data-theme-mode="dark"] .emoji-panel {
    background: #1f2430;
    border-color: #31394b;
    box-shadow: 0 12px 35px rgba(8, 15, 45, 0.6);
}
[data-bs-theme="dark"] .emoji-panel button,
[data-theme-mode="dark"] .emoji-panel button {
    color: #e7ecf7;
}
[data-bs-theme="dark"] .emoji-panel button:hover,
[data-theme-mode="dark"] .emoji-panel button:hover {
    background-color: #2e3647;
    border-radius: 8px;
}
.quick-reply-input-wrapper {
    position: relative;
}
.quick-replies-suggestions {
    position: absolute;
    left: 0;
    right: 0;
    bottom: calc(100% + 6px);
    background: var(--bs-card-bg);
    border: 1px solid var(--bs-border-color);
    border-radius: 0.75rem;
    box-shadow: 0 10px 30px rgba(15,34,58,.12);
    max-height: 260px;
    overflow-y: auto;
    display: none;
    z-index: 40;
}
.quick-replies-suggestions.active {
    display: block;
}
.quick-reply-item {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.35rem;
    padding: 0.75rem;
    cursor: pointer;
    transition: background-color 0.15s ease, border-color 0.15s ease;
    border-bottom: 1px solid var(--bs-border-color);
    background-color: #fff;
}
.quick-replies-suggestions .quick-reply-item:last-child {
    border-bottom: none;
}
.quick-reply-item:hover,
.quick-reply-item.active {
    background-color: var(--bs-primary-bg-subtle, #eef2ff);
    border-color: rgba(var(--bs-primary-rgb), .35);
}
.quick-reply-item .qr-command {
    display: inline-flex;
    align-items: center;
    padding: 0.15rem 0.6rem;
    border-radius: 50rem;
    background-color: rgba(var(--bs-primary-rgb), .12);
    font-weight: 600;
    font-size: 12px;
    color: var(--bs-primary);
}
.quick-reply-item .qr-content {
    font-size: 13px;
    line-height: 1.5;
    color: var(--bs-secondary-color);
    white-space: normal;
    text-align: left;
    width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    max-height: calc(1.5em * 2);
}
#quickRepliesList {
    max-height: 360px;
    overflow-y: auto;
}
[data-bs-theme="dark"] #quickRepliesList::-webkit-scrollbar,
#quickRepliesList::-webkit-scrollbar {
    width: 6px;
}
[data-bs-theme="dark"] #quickRepliesList::-webkit-scrollbar-thumb,
#quickRepliesList::-webkit-scrollbar-thumb {
    background-color: rgba(99, 102, 241, 0.4);
    border-radius: 10px;
}
[data-bs-theme="dark"] #quickRepliesList::-webkit-scrollbar-thumb {
    background-color: rgba(148, 163, 184, 0.5);
}
[data-bs-theme="dark"] .quick-replies-suggestions,
[data-theme-mode="dark"] .quick-replies-suggestions {
    background-color: #1f2430;
    border-color: #31394b;
    box-shadow: 0 12px 35px rgba(8, 15, 45, 0.6);
}
[data-bs-theme="dark"] .quick-reply-item,
[data-theme-mode="dark"] .quick-reply-item {
    background-color: #252b3a;
    border-color: #343c4f;
}
[data-bs-theme="dark"] .quick-reply-item:hover,
[data-bs-theme="dark"] .quick-reply-item.active,
[data-theme-mode="dark"] .quick-reply-item:hover,
[data-theme-mode="dark"] .quick-reply-item.active {
    background-color: #354160;
    border-color: #4c5e88;
}
[data-bs-theme="dark"] .quick-reply-item .qr-command,
[data-theme-mode="dark"] .quick-reply-item .qr-command {
    background-color: rgba(93, 126, 255, 0.28);
    color: #9fb5ff;
}
[data-bs-theme="dark"] .quick-reply-item .qr-content,
[data-theme-mode="dark"] .quick-reply-item .qr-content {
    color: #e7ecf7;
}
.section-title-bar {
    display: block;
    width: calc(100% + 1rem);
    padding: 0.5rem 0.75rem;
    background-color: #f4f5f7;
    border-radius: 0.5rem;
    color: var(--bs-body-color);
    margin-left: -0.5rem;
}
[data-bs-theme="dark"] .section-title-bar,
[data-theme-mode="dark"] .section-title-bar {
    background-color: #262b38;
    color: #e7ecf7;
}
.chat-user-details {
    width: 300px;
    background: var(--bs-card-bg);
    max-height: calc(100vh - 120px);
    overflow-y: auto;
    padding-bottom: 1rem;
}
.translate-section-box {
    background-color: #ffffff;
    color: #1f2937;
    border-color: #e2e8f0 !important;
}
[data-bs-theme="dark"] .translate-section-box,
[data-theme-mode="dark"] .translate-section-box {
    background-color: #1f2430;
    color: #e2e8f0;
    border-color: rgba(255, 255, 255, 0.08) !important;
}
.messages-mobile-notice {
    display: none;
}
@media (max-width: 991.98px) {
    .messages-desktop {
        display: none !important;
    }
    .messages-mobile-notice {
        display: flex !important;
        align-items: center;
        justify-content: center;
        min-height: calc(100vh - 140px);
        padding: 1.5rem 0;
    }
    .messages-mobile-notice .mobile-notice-card {
        width: min(360px, 90%);
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 18px;
        box-shadow: 0 16px 40px rgba(15, 23, 42, 0.12);
        padding: 1.75rem 1.5rem;
        text-align: center;
    }
    .messages-mobile-notice .mobile-notice-icon {
        width: 62px;
        height: 62px;
        border-radius: 16px;
        background: rgba(99, 102, 241, 0.12);
        color: #4f46e5;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 26px;
        margin: 0 auto 1.25rem;
    }
    .messages-mobile-notice .mobile-notice-title {
        font-weight: 700;
        font-size: 1.05rem;
        margin-bottom: 0.5rem;
        color: #1f2937;
    }
    .messages-mobile-notice .mobile-notice-text {
        color: #4b5563;
        font-size: 0.92rem;
        margin-bottom: 1.25rem;
    }
    .messages-mobile-notice .mobile-notice-actions .btn {
        min-width: 180px;
    }
    [data-bs-theme="dark"] .messages-mobile-notice .mobile-notice-card,
    [data-theme-mode="dark"] .messages-mobile-notice .mobile-notice-card {
        background: #1f2430;
        border-color: rgba(148, 163, 184, 0.18);
        box-shadow: 0 18px 45px rgba(8, 15, 32, 0.45);
    }
    [data-bs-theme="dark"] .messages-mobile-notice .mobile-notice-title,
    [data-theme-mode="dark"] .messages-mobile-notice .mobile-notice-title {
        color: #e7ecf7;
    }
    [data-bs-theme="dark"] .messages-mobile-notice .mobile-notice-text,
    [data-theme-mode="dark"] .messages-mobile-notice .mobile-notice-text {
        color: #cbd5f5;
    }
    [data-bs-theme="dark"] .messages-mobile-notice .mobile-notice-icon,
    [data-theme-mode="dark"] .messages-mobile-notice .mobile-notice-icon {
        background: rgba(99, 102, 241, 0.24);
        color: #c7d2fe;
    }
}
.shared-files li + li,
.chat-media + .chat-media {
    margin-top: 0.75rem;
}
.shared-file-icon {
    width: 38px;
    height: 38px;
    border-radius: 8px;
    background: rgba(var(--bs-primary-rgb), .1);
    color: var(--bs-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}
.chat-media img {
    width: 100%;
    border-radius: 12px;
}
.chat-stats-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 12px;
    color: var(--bs-secondary-color);
}
.loading-wrapper {
    padding: 2rem 1rem;
    text-align: center;
    color: var(--bs-secondary-color);
}
.loading-wrapper .spinner-border {
    width: 2.5rem;
    height: 2.5rem;
}
.chat-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 3rem 1rem;
    color: var(--bs-secondary-color);
}
.chat-empty i,
.chat-placeholder-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
    color: var(--bs-border-color);
}
@media (max-width: 1200px) {
    .chat-user-details {
        display: none;
    }
}
</style>
';

$body['footer'] = '
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
';

require_once(__DIR__ . '/../../models/is_admin.php');
require_once(__DIR__ . '/header.php');
require_once(__DIR__ . '/sidebar.php');

$canManageTickets = checkPermission($getUser['admin'], 'edit_ticket');
$canConfigTicket = checkPermission($getUser['admin'], 'config_ticket');

if (checkPermission($getUser['admin'], 'view_ticket') != true) {
    die('<script type="text/javascript">if(!alert("' . __('Bạn không có quyền sử dụng tính năng này') . '")){window.history.back();}</script>');
}
?>

<div class="main-content app-content">
    <div class="container-fluid">

        <div class="messages-mobile-notice">
            <div class="mobile-notice-card">
                <div class="mobile-notice-icon">
                    <i class="ri-smartphone-line"></i>
                </div>
                <div class="mobile-notice-title">
                    <?= __('Trang Messages hiện chưa hỗ trợ trên thiết bị di động.'); ?>
                </div>
                <p class="mobile-notice-text mb-0">
                    <?= __('Vui lòng truy cập trang Tickets để theo dõi và xử lý yêu cầu.'); ?>
                </p>
                <div class="mobile-notice-actions mt-3">
                    <a href="<?= base_url_admin('tickets'); ?>" class="btn btn-primary">
                        <i class="ri-external-link-line me-1"></i><?= __('Đi tới trang Tickets'); ?>
                    </a>
                </div>
            </div>
        </div>

        <div class="messages-desktop">
            <div class="main-chart-wrapper p-2 gap-2 d-lg-flex">
                <div class="chat-info border position-relative">
                    <div class="d-flex align-items-center justify-content-between w-100 p-3 border-bottom">
                        <div>
                            <h5 class="fw-semibold mb-0"><?= __('Messages'); ?></h5>
                            <span class="chat-stats-badge mt-1" id="ticketsSummary">
                                <i class="ri-message-2-line"></i> <?= __('Đang tải'); ?>...
                            </span>
                        </div>
                        <?php if ($canConfigTicket): ?>
                            <button type="button" class="btn btn-icon btn-secondary-light btn-wave waves-light waves-effect" id="openTicketSettingsModal">
                                <i class="ri-settings-3-line"></i>
                            </button>
                        <?php endif; ?>
                    </div>

                    <div id="ticketsLoading" class="loading-wrapper">
                        <div class="spinner-border text-primary mb-3" role="status"></div>
                        <div><?= __('Đang tải danh sách tickets...'); ?></div>
                    </div>

                    <div id="ticketsEmpty" class="chat-empty d-none">
                        <i class="ri-inbox-line"></i>
                        <p class="mb-0"><?= __('Không có dữ liệu'); ?></p>
                    </div>

                    <div id="ticketsListWrapper" class="d-none">
                        <ul class="list-unstyled mb-0 mt-2 chat-users-tab" id="ticketsList"></ul>
                    </div>
                </div>

                <div class="main-chat-area border">
                    <div id="chatPlaceholder" class="chat-content d-flex align-items-center justify-content-center text-muted">
                        <div class="text-center">
                            <i class="ri-chat-3-line chat-placeholder-icon d-block"></i>
                            <p class="mb-0"><?= __('Chọn một ticket để xem hội thoại'); ?></p>
                        </div>
                    </div>

                    <div id="chatLoading" class="chat-content d-none align-items-center justify-content-center text-muted">
                        <div class="text-center">
                            <div class="spinner-border text-primary mb-3" role="status"></div>
                            <p class="mb-0"><?= __('Đang tải hội thoại...'); ?></p>
                        </div>
                    </div>

                    <div id="chatPanel" class="d-none">
                        <div class="d-flex align-items-center p-2 border-bottom flex-wrap gap-2" id="chatHeader">
                            <div class="me-2 lh-1">
                                <span class="avatar avatar-lg me-2 avatar-rounded" id="chatUserAvatarWrapper">
                                    <img id="chatUserAvatar" src="" alt="">
                                </span>
                            </div>
                            <div class="flex-fill">
                                <p class="mb-0 fw-semibold fs-14">
                                    <a href="#" id="chatUserLink" class="chatnameperson text-decoration-none" target="_blank"></a>
                                </p>
                                <p class="text-muted mb-0 chatpersonstatus" id="chatUserStatus">-</p>
                            </div>
                            <div class="d-flex flex-wrap align-items-center gap-2" id="chatActionButtons">
                                <?php if ($canManageTickets): ?>
                                    <button type="button" class="btn btn-outline-light btn-icon" data-action="change-status" data-status="answered" data-bs-toggle="tooltip"
                                        data-bs-placement="top" title="<?= __('Đánh dấu đã trả lời'); ?>"><i class="ri-check-line"></i></button>
                                    <button type="button" class="btn btn-outline-light btn-icon" data-action="change-status" data-status="closed" data-bs-toggle="tooltip"
                                        data-bs-placement="top" title="<?= __('Đóng ticket'); ?>"><i class="ri-close-line"></i></button>
                                <?php endif; ?>
                                <div class="dropdown">
                                    <button class="btn btn-icon btn-outline-light btn-wave waves-light" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="ti ti-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <?php if ($canManageTickets): ?>
                                            <li><button class="dropdown-item text-danger" type="button" id="deleteTicketBtn"><i class="ri-delete-bin-line me-1"></i><?= __('Xóa'); ?></button></li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="chat-content" id="main-chat-content">
                            <ul class="list-unstyled" id="conversationTimeline"></ul>
                        </div>

                        <div class="chat-footer">
                            <?php if ($canManageTickets): ?>
                                <form id="replyForm" class="reply-ticket-form w-100 d-flex gap-2 align-items-center">
                                    <input type="hidden" id="replyTicketId" name="ticket_id" value="">
                                    <div class="flex-grow-1 quick-reply-input-wrapper">
                                        <textarea class="form-control" placeholder="<?= __('Nhập nội dung sau đó Nhấn Ctrl + Enter (Cmd + Enter trên macOS) để gửi nhanh.'); ?>" id="replyMessage" autocomplete="off" rows="1" disabled></textarea>
                                        <div class="quick-replies-suggestions shadow-sm" id="quickRepliesSuggestion"></div>
                                    </div>
                                    <div class="emoji-dropdown">
                                        <button type="button" class="btn btn-icon btn-success-light" id="emojiBtn" disabled><i class="ri-emotion-line"></i></button>
                                        <div class="emoji-panel" id="emojiPanel">
                                            <div class="emoji-grid" id="emojiGrid">
                                                <button type="button" data-emoji="😀">😀</button>
                                                <button type="button" data-emoji="😁">😁</button>
                                                <button type="button" data-emoji="😂">😂</button>
                                                <button type="button" data-emoji="🤣">🤣</button>
                                                <button type="button" data-emoji="😃">😃</button>
                                                <button type="button" data-emoji="😄">😄</button>
                                                <button type="button" data-emoji="😅">😅</button>
                                                <button type="button" data-emoji="😆">😆</button>
                                                <button type="button" data-emoji="😉">😉</button>
                                                <button type="button" data-emoji="😊">😊</button>
                                                <button type="button" data-emoji="😍">😍</button>
                                                <button type="button" data-emoji="🥰">🥰</button>
                                                <button type="button" data-emoji="😘">😘</button>
                                                <button type="button" data-emoji="😗">😗</button>
                                                <button type="button" data-emoji="😙">😙</button>
                                                <button type="button" data-emoji="😚">😚</button>
                                                <button type="button" data-emoji="😋">😋</button>
                                                <button type="button" data-emoji="😜">😜</button>
                                                <button type="button" data-emoji="🤪">🤪</button>
                                                <button type="button" data-emoji="😝">😝</button>
                                                <button type="button" data-emoji="🤑">🤑</button>
                                                <button type="button" data-emoji="🤗">🤗</button>
                                                <button type="button" data-emoji="🤭">🤭</button>
                                                <button type="button" data-emoji="🤫">🤫</button>
                                                <button type="button" data-emoji="🤔">🤔</button>
                                                <button type="button" data-emoji="🤨">🤨</button>
                                                <button type="button" data-emoji="😐">😐</button>
                                                <button type="button" data-emoji="😑">😑</button>
                                                <button type="button" data-emoji="😶">😶</button>
                                                <button type="button" data-emoji="🙄">🙄</button>
                                                <button type="button" data-emoji="😏">😏</button>
                                                <button type="button" data-emoji="😣">😣</button>
                                                <button type="button" data-emoji="😥">😥</button>
                                                <button type="button" data-emoji="😮">😮</button>
                                                <button type="button" data-emoji="🤐">🤐</button>
                                                <button type="button" data-emoji="😯">😯</button>
                                                <button type="button" data-emoji="😪">😪</button>
                                                <button type="button" data-emoji="😴">😴</button>
                                                <button type="button" data-emoji="😌">😌</button>
                                                <button type="button" data-emoji="😛">😛</button>
                                                <button type="button" data-emoji="😓">😓</button>
                                                <button type="button" data-emoji="😥">😥</button>
                                                <button type="button" data-emoji="😨">😨</button>
                                                <button type="button" data-emoji="😰">😰</button>
                                                <button type="button" data-emoji="😱">😱</button>
                                                <button type="button" data-emoji="🥵">🥵</button>
                                                <button type="button" data-emoji="🥶">🥶</button>
                                                <button type="button" data-emoji="🥳">🥳</button>
                                                <button type="button" data-emoji="😡">😡</button>
                                                <button type="button" data-emoji="😢">😢</button>
                                                <button type="button" data-emoji="😭">😭</button>
                                                <button type="button" data-emoji="😤">😤</button>
                                                <button type="button" data-emoji="😠">😠</button>
                                                <button type="button" data-emoji="😷">😷</button>
                                                <button type="button" data-emoji="🤒">🤒</button>
                                                <button type="button" data-emoji="🤕">🤕</button>
                                                <button type="button" data-emoji="🤢">🤢</button>
                                                <button type="button" data-emoji="🤮">🤮</button>
                                                <button type="button" data-emoji="🤧">🤧</button>
                                                <button type="button" data-emoji="😇">😇</button>
                                                <button type="button" data-emoji="🤠">🤠</button>
                                                <button type="button" data-emoji="😎">😎</button>
                                                <button type="button" data-emoji="🤓">🤓</button>
                                                <button type="button" data-emoji="🧐">🧐</button>
                                                <button type="button" data-emoji="😕">😕</button>
                                                <button type="button" data-emoji="😟">😟</button>
                                                <button type="button" data-emoji="🙁">🙁</button>
                                                <button type="button" data-emoji="☹️">☹️</button>
                                                <button type="button" data-emoji="🙂">🙂</button>
                                                <button type="button" data-emoji="🙃">🙃</button>
                                                <button type="button" data-emoji="🤩">🤩</button>
                                                <button type="button" data-emoji="🤤">🤤</button>
                                                <button type="button" data-emoji="🥴">🥴</button>
                                                <button type="button" data-emoji="😵">😵</button>
                                                <button type="button" data-emoji="😵‍💫">😵‍💫</button>
                                                <button type="button" data-emoji="😳">😳</button>
                                                <button type="button" data-emoji="🥹">🥹</button>
                                                <button type="button" data-emoji="🥺">🥺</button>
                                                <button type="button" data-emoji="🤯">🤯</button>
                                                <button type="button" data-emoji="🤬">🤬</button>
                                                <button type="button" data-emoji="🤯">🤯</button>
                                                <button type="button" data-emoji="🤡">🤡</button>
                                                <button type="button" data-emoji="💀">💀</button>
                                                <button type="button" data-emoji="☠️">☠️</button>
                                                <button type="button" data-emoji="👻">👻</button>
                                                <button type="button" data-emoji="💩">💩</button>
                                                <button type="button" data-emoji="🤖">🤖</button>
                                                <button type="button" data-emoji="🎃">🎃</button>
                                                <button type="button" data-emoji="🎯">🎯</button>
                                                <button type="button" data-emoji="🏆">🏆</button>
                                                <button type="button" data-emoji="💼">💼</button>
                                                <button type="button" data-emoji="📈">📈</button>
                                                <button type="button" data-emoji="📉">📉</button>
                                                <button type="button" data-emoji="📌">📌</button>
                                                <button type="button" data-emoji="📎">📎</button>
                                                <button type="button" data-emoji="🔒">🔒</button>
                                                <button type="button" data-emoji="🔑">🔑</button>
                                                <button type="button" data-emoji="🛠️">🛠️</button>
                                                <button type="button" data-emoji="🧰">🧰</button>
                                                <button type="button" data-emoji="⚙️">⚙️</button>
                                                <button type="button" data-emoji="🧠">🧠</button>
                                                <button type="button" data-emoji="🕒">🕒</button>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-outline-secondary d-flex align-items-center" id="openQuickRepliesManager">
                                        <i class="ri-list-check-2 me-1"></i><?= __('Quick Replies'); ?>
                                    </button>
                                    <button type="submit" class="btn btn-primary btn-icon btn-send" id="replySubmitBtn" disabled><i class="ri-send-plane-2-line"></i></button>
                                </form>
                            <?php else: ?>
                                <div class="alert alert-warning mb-0 w-100"><i class="ri-error-warning-line me-1"></i><?= __('Bạn không có quyền sử dụng tính năng này'); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="chat-user-details border">
                    <div id="sidebarPlaceholder" class="p-4 text-center text-muted">
                        <i class="ri-information-line chat-placeholder-icon d-block"></i>
                        <p class="mb-0"><?= __('Chọn ticket để xem thông tin khách hàng và đơn hàng'); ?></p>
                    </div>
                    <div id="sidebarLoading" class="p-4 text-center text-muted d-none">
                        <div class="spinner-border text-primary mb-3" role="status"></div>
                        <p class="mb-0"><?= __('Đang tải thông tin chi tiết...'); ?></p>
                    </div>
                    <div id="sidebarContent" class="d-none">
                        <div class="section-title-bar fw-semibold mb-3"><?= __('Ticket Info'); ?></div>
                        <ul class="list-unstyled mb-4" id="ticketInfoList">
                            <li class="d-flex justify-content-between mb-2"><span><?= __('ID'); ?>:</span> <span class="fw-semibold" id="ticketInfoId">-</span></li>
                            <li class="d-flex justify-content-between mb-2"><span><?= __('Trạng thái'); ?>:</span> <span id="ticketInfoStatus">-</span></li>
                            <li class="d-flex justify-content-between mb-2"><span><?= __('Danh mục'); ?>:</span> <span id="ticketInfoCategory">-</span></li>
                            <li class="d-flex justify-content-between mb-2"><span><?= __('Ngày tạo'); ?>:</span> <span id="ticketInfoCreated">-</span></li>
                            <li class="d-flex justify-content-between"><span><?= __('Cập nhật'); ?>:</span> <span id="ticketInfoUpdated">-</span></li>
                        </ul>

                        <div class="section-title-bar fw-semibold mb-3"><?= __('Customer Info'); ?></div>
                        <ul class="list-unstyled mb-4" id="customerInfoList">
                            <li class="d-flex justify-content-between mb-2"><span><?= __('Số dư'); ?>:</span> <span id="customerBalance">-</span></li>
                            <li class="d-flex justify-content-between mb-2"><span><?= __('Tổng chi tiêu'); ?>:</span> <span id="customerSpent">-</span></li>
                            <li class="d-flex justify-content-between mb-2"><span><?= __('Đơn hàng'); ?>:</span> <span id="customerOrders">-</span></li>
                            <li class="d-flex justify-content-between mb-2"><span><?= __('Tickets'); ?>:</span> <span id="customerTickets">-</span></li>
                            <li class="d-flex justify-content-between" id="customerLastActiveWrapper"><span><?= __('Hoạt động cuối'); ?>:</span> <span id="customerLastActive">-</span></li>
                            <li class="mt-2 text-end" id="customerEditLinkWrapper"><a href="#" class="btn btn-outline-primary btn-sm" id="customerEditLink" target="_blank"><i class="ri-user-settings-line me-1"></i><?= __('Chỉnh sửa thành viên'); ?></a></li>
                        </ul>

                        <div class="section-title-bar fw-semibold mb-3"><?= __('Order Info'); ?></div>
                        <div id="orderInfoContainer">
                            <ul class="list-unstyled mb-4 d-none" id="orderInfoList">
                                <li class="d-flex justify-content-between mb-2"><span><?= __('Mã đơn hàng'); ?>:</span> <span id="orderTransId">-</span></li>
                                <li class="d-flex justify-content-between mb-2"><span><?= __('Trạng thái'); ?>:</span> <span id="orderStatus">-</span></li>
                                <li class="d-flex justify-content-between mb-2 d-none" id="orderServiceWrapper"><span><?= __('Tên gói'); ?>:</span> <span id="orderServiceName">-</span></li>
                                <li class="d-flex justify-content-between mb-2"><span><?= __('Số lượng đặt'); ?>:</span> <span id="orderQuantity">-</span></li>
                                <li class="d-flex justify-content-between mb-2"><span><?= __('Đã thanh toán'); ?>:</span> <span id="orderPay">-</span></li>
                                <li class="d-flex justify-content-between mb-2" id="orderProfitWrapper"><span><?= __('Lợi nhuận'); ?>:</span> <span id="orderProfit">-</span></li>
                                <li class="mt-2 text-end"><a href="#" class="btn btn-outline-primary btn-sm" id="orderEditLink" target="_blank"><i class="ri-edit-line me-1"></i><?= __('Chỉnh sửa đơn hàng'); ?></a></li>
                            </ul>
                            <div class="alert alert-light border mb-4 d-none" role="alert" id="orderInfoEmpty">
                                <i class="ri-information-line me-2"></i><?= __('No order information available'); ?>
                            </div>
                        </div>

                        <?php if ($canManageTickets): ?>
                            <div class="section-title-bar fw-semibold mb-2"><?= __('Admin Note'); ?></div>
                            <div class="mb-4">
                                <textarea class="form-control" id="adminNoteTextarea" rows="4" placeholder="<?= __('Nhập ghi chú nội bộ...'); ?>"></textarea>
                                <small class="text-muted d-block mt-1"><?= __('Ghi chú này chỉ hiển thị với admin.'); ?></small>
                                <div class="d-flex align-items-center gap-2 mt-2">
                                    <span class="text-muted d-none" id="adminNoteSaving">
                                        <span class="spinner-border spinner-border-sm align-middle me-1"></span><?= __('Đang lưu...'); ?>
                                    </span>
                                    <span class="text-success d-none" id="adminNoteStatus">
                                        <i class="ri-check-line me-1"></i><?= __('Đã lưu ghi chú'); ?>
                                    </span>
                                    <span class="text-danger d-none" id="adminNoteError">
                                        <i class="ri-close-line me-1"></i><?= __('Lưu ghi chú thất bại'); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<audio id="newMessageSound" src="<?= base_url('assets/audio/facebook-messenger.mp3'); ?>"></audio>

<!-- Pre-fetch tickets ngay lập tức - không đợi script chính parse xong -->
<script>
    (function() {
        var csrfToken = getCSRFToken(); // Hàm đã được định nghĩa trong header.php
        if (!csrfToken) return;

        var prefetchData = new FormData();
        prefetchData.append('action', 'getSupportTicketsOverview');
        prefetchData.append('selected_id', '');
        prefetchData.append('csrf_token', csrfToken);

        window.__prefetchedTicketsPromise = fetch("<?= BASE_URL('ajaxs/admin/ticket.php'); ?>", {
            method: 'POST',
            body: prefetchData,
            credentials: 'same-origin'
        }).then(function(r) {
            return r.json();
        }).then(function(data) {
            window.__prefetchedTickets = data;
            return data;
        }).catch(function() {
            return null;
        });
    })();
</script>

<div id="messageContextMenu" class="message-context-menu">
    <button type="button" data-action="copy">
        <i class="ri-file-copy-line"></i> <?= __('Sao chép'); ?>
    </button>
    <button type="button" data-action="translate">
        <i class="ri-translate-2"></i> <?= __('Dịch'); ?>
    </button>
    <button type="button" data-action="recall" class="text-danger">
        <i class="ri-arrow-go-back-line"></i> <?= __('Thu hồi'); ?>
    </button>
</div>

<div class="modal fade" id="translateModal" tabindex="-1" aria-labelledby="translateModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="translateModalTitle"><?= __('Dịch tin nhắn'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= __('Đóng'); ?>"></button>
            </div>
            <div class="modal-body">
                <div id="translateLanguageWrapper" class="mb-3">
                    <label for="translateLanguageSelect" class="form-label mb-1"><?= __('Chọn ngôn ngữ đích'); ?></label>
                    <select id="translateLanguageSelect" class="form-select" data-placeholder="<?= __('Chọn ngôn ngữ'); ?>" style="width: 100%;"></select>
                    <small class="text-muted d-block mt-1"><?= __('Bạn chỉ cần chọn một lần, hệ thống sẽ ghi nhớ.'); ?></small>
                </div>
                <div id="translateErrorAlert" class="alert alert-danger d-none" role="alert"></div>
                <div id="translateLoading" class="text-center py-4 d-none">
                    <div class="spinner-border text-primary mb-3" role="status"></div>
                    <div><?= __('Đang dịch...'); ?></div>
                </div>
                <div id="translateResult" class="d-none">
                    <div class="mb-3">
                        <h6 class="fw-semibold mb-2"><?= __('Nguyên văn'); ?></h6>
                        <div class="border rounded-3 p-3 translate-section-box" id="translateOriginalContent"></div>
                    </div>
                    <div>
                        <h6 class="fw-semibold mb-2 d-flex align-items-center gap-2 mb-3">
                            <span><?= __('Bản dịch'); ?></span>
                            <span class="badge bg-primary" id="translateResultLang"></span>
                        </h6>
                        <div class="border rounded-3 p-3 translate-section-box" id="translateTranslatedContent"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="ri-close-line me-1"></i><?= __('Đóng'); ?>
                </button>
                <button type="button" class="btn btn-outline-primary" id="translateCopyBtn" disabled>
                    <i class="ri-file-copy-2-line me-1"></i><?= __('Sao chép bản dịch'); ?>
                </button>
                <button type="button" class="btn btn-primary" id="translateConfirmBtn">
                    <i class="ri-translate-2 me-1"></i><?= __('Dịch'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="quickRepliesModal" tabindex="-1" aria-labelledby="quickRepliesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="quickRepliesModalLabel"><i class="ri-list-check-2 me-2"></i><?= __('Quản lý Quick Replies'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= __('Đóng'); ?>"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0 text-muted"><?= __('Danh sách câu trả lời nhanh'); ?></h6>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="addQuickReplyBtn">
                        <i class="ri-add-line me-1"></i><?= __('Thêm câu trả lời'); ?>
                    </button>
                </div>
                <div id="quickRepliesEmpty" class="alert alert-light border d-none">
                    <i class="ri-information-line me-1"></i><?= __('Chưa có câu trả lời nhanh nào. Hãy thêm mới bên dưới.'); ?>
                </div>
                <ul class="list-group mb-4" id="quickRepliesList"></ul>
                <div class="border rounded-3 p-3">
                    <form id="quickRepliesForm">
                        <input type="hidden" id="quickReplyId" value="">
                        <div class="row g-3">
                            <div class="col-lg-4">
                                <label for="quickReplyCommand" class="form-label mb-1"><?= __('Lệnh (ví dụ: /gia)'); ?></label>
                                <input type="text" class="form-control" id="quickReplyCommand" maxlength="64" placeholder="/command" required>
                            </div>
                            <div class="col-lg-8">
                                <label for="quickReplyContent" class="form-label mb-1"><?= __('Nội dung trả lời'); ?></label>
                                <textarea id="quickReplyContent" class="form-control" rows="3" placeholder="<?= __('Nhập nội dung sẽ chèn vào khung chat'); ?>" required></textarea>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end gap-2 mt-3">
                            <button type="button" class="btn btn-outline-secondary" id="resetQuickReplyForm"><i class="ri-refresh-line me-1"></i><?= __('Hủy'); ?></button>
                            <button type="submit" class="btn btn-primary" id="quickReplySubmitBtn">
                                <i class="ri-save-3-line me-1"></i><?= __('Lưu câu trả lời'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="ticketSettingsModal" tabindex="-1" aria-labelledby="ticketSettingsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ticketSettingsModalLabel"><?= __('Cấu hình Ticket'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= __('Đóng'); ?>"></button>
            </div>
            <div class="modal-body">
                <form id="ticketSettingsForm">
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <label class="form-label" for="support_tickets_status"><?= __('Trạng thái'); ?></label>
                            <select class="form-select" id="support_tickets_status" name="support_tickets_status">
                                <option value="1" <?= $CMSNT->site('support_tickets_status') == 1 ? 'selected' : ''; ?>><?= __('ON'); ?></option>
                                <option value="0" <?= $CMSNT->site('support_tickets_status') == 0 ? 'selected' : ''; ?>><?= __('OFF'); ?></option>
                            </select>
                        </div>
                        <div class="col-lg-6">
                            <label class="form-label" for="support_tickets_order_history"><?= __('Cho phép User tạo ticket tại lịch sử đơn hàng'); ?></label>
                            <select class="form-select" id="support_tickets_order_history" name="support_tickets_order_history">
                                <option value="1" <?= $CMSNT->site('support_tickets_order_history') == 1 ? 'selected' : ''; ?>><?= __('ON'); ?></option>
                                <option value="0" <?= $CMSNT->site('support_tickets_order_history') == 0 ? 'selected' : ''; ?>><?= __('OFF'); ?></option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="support_tickets_telegram_chat_id"><?= __('Chat ID Telegram nhận thông báo khi có Ticket mới'); ?></label>
                            <input type="text" class="form-control" id="support_tickets_telegram_chat_id" name="support_tickets_telegram_chat_id" value="<?= $CMSNT->site('support_tickets_telegram_chat_id'); ?>">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="ri-close-line me-1"></i><?= __('Đóng'); ?>
                </button>
                <button type="submit" class="btn btn-primary d-flex align-items-center gap-2" form="ticketSettingsForm" id="ticketSettingsSubmitBtn">
                    <i class="ri-save-3-line"></i><span><?= __('Lưu'); ?></span>
                </button>
            </div>
        </div>
    </div>
</div>

<?php
require_once(__DIR__ . '/footer.php');
?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Khởi tạo trạng thái chính: quyền hạn, text hiển thị, timer, bộ nhớ quick reply, caret, v.v.
        const CAN_REPLY = <?= $canManageTickets ? 'true' : 'false'; ?>;
        const CAN_EDIT_STATUS = <?= $canManageTickets ? 'true' : 'false'; ?>;
        const CAN_CONFIG_TICKET = <?= $canConfigTicket ? 'true' : 'false'; ?>;
        const CURRENT_ADMIN_ID = <?= (int)$getUser['id']; ?>;
        const TEXTS = {
            loadingTickets: <?= json_encode(__('Đang tải danh sách tickets...')); ?>,
            loadingChat: <?= json_encode(__('Đang tải hội thoại...')); ?>,
            noData: <?= json_encode(__('Không có dữ liệu')); ?>,
            selectTicket: <?= json_encode(__('Chọn một ticket để xem hội thoại')); ?>,
            success: <?= json_encode(__('Thành công')); ?>,
            error: <?= json_encode(__('Lỗi')); ?>,
            confirmDelete: <?= json_encode(__('Bạn có chắc chắn muốn xóa ticket này không? Thao tác này không thể hoàn tác.')); ?>,
            deleted: <?= json_encode(__('Xóa ticket thành công')); ?>,
            statusUpdated: <?= json_encode(__('Cập nhật trạng thái thành công')); ?>,
            replySuccess: <?= json_encode(__('Gửi tin nhắn thành công')); ?>,
            noteSaving: <?= json_encode(__('Đang lưu...')); ?>,
            noteSaved: <?= json_encode(__('Đã lưu ghi chú')); ?>,
            noteSaveError: <?= json_encode(__('Không thể lưu ghi chú')); ?>,
            settingsSaving: <?= json_encode(__('Đang lưu cấu hình...')); ?>,
            settingsSaved: <?= json_encode(__('Đã lưu cấu hình ticket thành công.')); ?>,
            settingsError: <?= json_encode(__('Không thể lưu cấu hình. Vui lòng thử lại.')); ?>,
            recall: <?= json_encode(__('Thu hồi')); ?>,
            recallConfirm: <?= json_encode(__('Bạn có chắc chắn muốn thu hồi tin nhắn này?')); ?>,
            recallSuccess: <?= json_encode(__('Thu hồi tin nhắn thành công')); ?>,
            recallError: <?= json_encode(__('Không thể thu hồi tin nhắn. Vui lòng thử lại.')); ?>,
            copy: <?= json_encode(__('Sao chép')); ?>,
            copySuccess: <?= json_encode(__('Đã sao chép nội dung tin nhắn')); ?>,
            copyError: <?= json_encode(__('Không thể sao chép nội dung. Vui lòng thử lại.')); ?>,
            cancel: <?= json_encode(__('Hủy')); ?>,
            translate: <?= json_encode(__('Dịch')); ?>,
            translating: <?= json_encode(__('Đang dịch...')); ?>,
            translateError: <?= json_encode(__('Không thể dịch tin nhắn này')); ?>,
            translateModalTitle: <?= json_encode(__('Dịch tin nhắn')); ?>,
            translateSelectLabel: <?= json_encode(__('Chọn ngôn ngữ đích')); ?>,
            translateSelectPlaceholder: <?= json_encode(__('Chọn ngôn ngữ')); ?>,
            translateRememberHint: <?= json_encode(__('Bạn chỉ cần chọn một lần, hệ thống sẽ ghi nhớ.')); ?>,
            translateSelectRequired: <?= json_encode(__('Vui lòng chọn ngôn ngữ cần dịch.')); ?>,
            translateCopy: <?= json_encode(__('Sao chép bản dịch')); ?>,
            translateCopySuccess: <?= json_encode(__('Đã sao chép bản dịch')); ?>,
            translateCopyError: <?= json_encode(__('Không thể sao chép bản dịch')); ?>,
            translateInvalid: <?= json_encode(__('Tin nhắn cần dịch không hợp lệ')); ?>,
            translateEmpty: <?= json_encode(__('Tin nhắn trống, không thể dịch')); ?>,
            translateOriginal: <?= json_encode(__('Nguyên văn')); ?>,
            translateTranslated: <?= json_encode(__('Bản dịch')); ?>,
            close: <?= json_encode(__('Đóng')); ?>,
            orderLinkCopySuccess: <?= json_encode(__('Đã sao chép liên kết đơn hàng.')); ?>,
            orderLinkCopyError: <?= json_encode(__('Không thể sao chép liên kết đơn hàng.')); ?>
        };

        // State dùng chung cho toàn bộ màn hình quản lý ticket
        const state = {
            selectedTicketId: null,
            lastMessageId: 0,
            autoRefreshTimer: null,
            messageTimer: null,
            isLoadingTickets: false,
            isLoadingMessages: false,
            lastTimelineDate: null,
            adminNoteTimer: null,
            adminNoteSaving: false,
            adminNoteCurrent: '',
            quickReplies: [],
            quickRepliesLoaded: false,
            quickRepliesLoading: false,
            quickRepliesSuggestionItems: [],
            quickRepliesHighlightIndex: -1,
            replyCaretPos: 0,
            ticketIndicators: {},
            ticketsInitialized: false,
            translateLangCache: '',
            translateMessageText: '',
            translateAjax: null,
            translateLastResult: '',
            translateCurrentLang: ''
        };

        const refreshInterval = 5000;
        const TRANSLATE_LANG_STORAGE_KEY = `ticketTranslateLang_${CURRENT_ADMIN_ID}`;

        // Cache các selector để tránh query lặp lại khi thao tác DOM nhiều lần
        const $ticketsLoading = $('#ticketsLoading');
        const $ticketsEmpty = $('#ticketsEmpty');
        const $ticketsListWrapper = $('#ticketsListWrapper');
        const $ticketsList = $('#ticketsList');
        const $ticketsSummary = $('#ticketsSummary');
        const $chatPlaceholder = $('#chatPlaceholder');
        const $chatLoading = $('#chatLoading');
        const $chatPanel = $('#chatPanel');
        const $conversationTimeline = $('#conversationTimeline');
        const $replyForm = $('#replyForm');
        const $replyMessage = $('#replyMessage');
        const $replySubmitBtn = $('#replySubmitBtn');
        const $emojiBtn = $('#emojiBtn');
        const $replyTicketId = $('#replyTicketId');
        const $sidebarPlaceholder = $('#sidebarPlaceholder');
        const $sidebarLoading = $('#sidebarLoading');
        const $sidebarContent = $('#sidebarContent');
        const $adminNoteTextarea = $('#adminNoteTextarea');
        const $adminNoteSaving = $('#adminNoteSaving');
        const $adminNoteStatus = $('#adminNoteStatus');
        const $adminNoteError = $('#adminNoteError');
        const $emojiPanel = $('#emojiPanel');
        const $emojiGrid = $('#emojiGrid');
        const $quickRepliesSuggestion = $('#quickRepliesSuggestion');
        const $openQuickRepliesManager = $('#openQuickRepliesManager');
        const $openTicketSettingsModalBtn = $('#openTicketSettingsModal');
        const $ticketSettingsModal = $('#ticketSettingsModal');
        const $ticketSettingsForm = $('#ticketSettingsForm');
        const $ticketSettingsSubmitBtn = $('#ticketSettingsSubmitBtn');
        const $supportTicketsStatus = $('#support_tickets_status');
        const $supportTicketsOrderHistory = $('#support_tickets_order_history');
        const $supportTicketsTelegramChatId = $('#support_tickets_telegram_chat_id');
        const $quickRepliesModal = $('#quickRepliesModal');
        const $orderServiceWrapper = $('#orderServiceWrapper');
        const $orderServiceName = $('#orderServiceName');

        const $quickRepliesList = $('#quickRepliesList');
        const $quickRepliesEmpty = $('#quickRepliesEmpty');
        const $quickRepliesForm = $('#quickRepliesForm');
        const $quickReplyId = $('#quickReplyId');
        const $quickReplyCommand = $('#quickReplyCommand');
        const $quickReplyContent = $('#quickReplyContent');
        const $quickReplySubmitBtn = $('#quickReplySubmitBtn');
        const $resetQuickReplyForm = $('#resetQuickReplyForm');
        const $addQuickReplyBtn = $('#addQuickReplyBtn');
        const newMessageSound = document.getElementById('newMessageSound');
        const $messageContextMenu = $('#messageContextMenu');
        const $contextCopyBtn = $messageContextMenu.find('[data-action="copy"]');
        const $contextRecallBtn = $messageContextMenu.find('[data-action="recall"]');
        const $contextTranslateBtn = $messageContextMenu.find('[data-action="translate"]');
        const $translateModal = $('#translateModal');
        const $translateLanguageWrapper = $('#translateLanguageWrapper');
        const $translateLanguageSelect = $('#translateLanguageSelect');
        const $translateLoading = $('#translateLoading');
        const $translateResult = $('#translateResult');
        const $translateOriginalContent = $('#translateOriginalContent');
        const $translateTranslatedContent = $('#translateTranslatedContent');
        const $translateResultLang = $('#translateResultLang');
        const $translateErrorAlert = $('#translateErrorAlert');
        const $translateConfirmBtn = $('#translateConfirmBtn');
        const $translateCopyBtn = $('#translateCopyBtn');
        const translateConfirmInitialHtml = $translateConfirmBtn.length ? $translateConfirmBtn.html() : '';
        const ticketSettingsModalInstance = ($ticketSettingsModal.length && typeof bootstrap !== 'undefined') ? new bootstrap.Modal($ticketSettingsModal[0]) : null;
        const ticketSettingsSubmitDefaultHtml = $ticketSettingsSubmitBtn.length ? $ticketSettingsSubmitBtn.html() : '';
        let contextMenuTarget = null;

        const translationLanguages = [{
                id: 'en',
                text: 'English'
            },
            {
                id: 'vi',
                text: 'Vietnamese'
            },
            {
                id: 'zh-CN',
                text: 'Chinese (Simplified)'
            },
            {
                id: 'zh-TW',
                text: 'Chinese (Traditional)'
            },
            {
                id: 'ja',
                text: 'Japanese'
            },
            {
                id: 'ko',
                text: 'Korean'
            },
            {
                id: 'fr',
                text: 'French'
            },
            {
                id: 'de',
                text: 'German'
            },
            {
                id: 'es',
                text: 'Spanish'
            },
            {
                id: 'pt',
                text: 'Portuguese'
            },
            {
                id: 'pt-BR',
                text: 'Portuguese (Brazil)'
            },
            {
                id: 'ru',
                text: 'Russian'
            },
            {
                id: 'ar',
                text: 'Arabic'
            },
            {
                id: 'id',
                text: 'Indonesian'
            },
            {
                id: 'th',
                text: 'Thai'
            },
            {
                id: 'ms',
                text: 'Malay'
            },
            {
                id: 'hi',
                text: 'Hindi'
            },
            {
                id: 'tr',
                text: 'Turkish'
            },
            {
                id: 'it',
                text: 'Italian'
            },
            {
                id: 'nl',
                text: 'Dutch'
            },
            {
                id: 'pl',
                text: 'Polish'
            },
            {
                id: 'sv',
                text: 'Swedish'
            },
            {
                id: 'da',
                text: 'Danish'
            },
            {
                id: 'fi',
                text: 'Finnish'
            },
            {
                id: 'no',
                text: 'Norwegian'
            },
            {
                id: 'cs',
                text: 'Czech'
            },
            {
                id: 'sk',
                text: 'Slovak'
            },
            {
                id: 'uk',
                text: 'Ukrainian'
            },
            {
                id: 'bg',
                text: 'Bulgarian'
            },
            {
                id: 'ro',
                text: 'Romanian'
            },
            {
                id: 'hu',
                text: 'Hungarian'
            },
            {
                id: 'el',
                text: 'Greek'
            },
            {
                id: 'sr',
                text: 'Serbian'
            },
            {
                id: 'hr',
                text: 'Croatian'
            },
            {
                id: 'sl',
                text: 'Slovenian'
            },
            {
                id: 'lt',
                text: 'Lithuanian'
            },
            {
                id: 'lv',
                text: 'Latvian'
            },
            {
                id: 'et',
                text: 'Estonian'
            },
            {
                id: 'he',
                text: 'Hebrew'
            },
            {
                id: 'fa',
                text: 'Persian'
            },
            {
                id: 'ur',
                text: 'Urdu'
            },
            {
                id: 'bn',
                text: 'Bengali'
            },
            {
                id: 'ta',
                text: 'Tamil'
            },
            {
                id: 'te',
                text: 'Telugu'
            },
            {
                id: 'mr',
                text: 'Marathi'
            },
            {
                id: 'kn',
                text: 'Kannada'
            },
            {
                id: 'ml',
                text: 'Malayalam'
            },
            {
                id: 'pa',
                text: 'Punjabi'
            },
            {
                id: 'sw',
                text: 'Swahili'
            },
            {
                id: 'fil',
                text: 'Filipino'
            },
            {
                id: 'my',
                text: 'Burmese'
            },
            {
                id: 'km',
                text: 'Khmer'
            },
            {
                id: 'lo',
                text: 'Lao'
            },
            {
                id: 'si',
                text: 'Sinhala'
            },
            {
                id: 'ca',
                text: 'Catalan'
            }
        ];

        if ($translateLanguageSelect.length) {
            $translateLanguageSelect.empty();
            $translateLanguageSelect.append(new Option(TEXTS.translateSelectPlaceholder, '', true, false));
            translationLanguages.forEach(item => {
                $translateLanguageSelect.append(new Option(item.text, item.id, false, false));
            });

            if (typeof $.fn.select2 === 'function') {
                $translateLanguageSelect.select2({
                    dropdownParent: $translateModal,
                    width: '100%',
                    placeholder: TEXTS.translateSelectPlaceholder,
                    allowClear: true,
                    minimumResultsForSearch: 0
                });
                $translateLanguageSelect.val(null).trigger('change');
            } else {
                $translateLanguageSelect.val('');
            }
        }

        function playNotificationSound() {
            if (!newMessageSound) return;
            try {
                newMessageSound.currentTime = 0;
                const playPromise = newMessageSound.play();
                if (playPromise && typeof playPromise.catch === 'function') {
                    playPromise.catch(() => {});
                }
            } catch (error) {
                console.warn('Unable to play notification sound', error);
            }
        }

        function hideMessageContextMenu() {
            $messageContextMenu.hide();
            contextMenuTarget = null;
        }

        let quickRepliesModalInstance = null;
        if ($quickRepliesModal.length && typeof bootstrap !== 'undefined') {
            quickRepliesModalInstance = new bootstrap.Modal($quickRepliesModal[0]);
        }

        if (CAN_CONFIG_TICKET && $ticketSettingsModal.length && ticketSettingsModalInstance) {
            const ticketSettingsSubmitLoadingHtml = `<span class="spinner-border spinner-border-sm me-1"></span>${TEXTS.settingsSaving}`;

            if ($openTicketSettingsModalBtn.length) {
                $openTicketSettingsModalBtn.on('click', function() {
                    ticketSettingsModalInstance.show();
                });
            }

            if ($ticketSettingsForm.length) {
                $ticketSettingsForm.on('submit', function(e) {
                    e.preventDefault();
                    if (!$ticketSettingsSubmitBtn.length || $ticketSettingsSubmitBtn.prop('disabled')) {
                        return;
                    }

                    const payload = {
                        action: 'update_ticket_settings',
                        support_tickets_status: $supportTicketsStatus.length ? $supportTicketsStatus.val() : '',
                        support_tickets_order_history: $supportTicketsOrderHistory.length ? $supportTicketsOrderHistory.val() : '',
                        support_tickets_telegram_chat_id: $supportTicketsTelegramChatId.length ? $supportTicketsTelegramChatId.val() : ''
                    };

                    $ticketSettingsSubmitBtn.prop('disabled', true).html(ticketSettingsSubmitLoadingHtml);

                    $.ajax({
                        url: <?= json_encode(base_url('ajaxs/admin/update.php')); ?>,
                        method: 'POST',
                        dataType: 'json',
                        data: payload
                    }).done(function(resp) {
                        if (resp.status === 'success') {
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'success',
                                    title: TEXTS.success,
                                    text: resp.msg || TEXTS.settingsSaved,
                                    timer: 1500,
                                    showConfirmButton: false
                                });
                            }
                            ticketSettingsModalInstance.hide();
                        } else {
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'error',
                                    title: TEXTS.error,
                                    text: resp.msg || TEXTS.settingsError
                                });
                            }
                        }
                    }).fail(function() {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'error',
                                title: TEXTS.error,
                                text: TEXTS.settingsError
                            });
                        }
                    }).always(function() {
                        if ($ticketSettingsSubmitBtn.length) {
                            $ticketSettingsSubmitBtn.prop('disabled', false).html(ticketSettingsSubmitDefaultHtml);
                        }
                    });
                });
            }
        } else if ($openTicketSettingsModalBtn.length) {
            $openTicketSettingsModalBtn.prop('disabled', true).attr('disabled', true).addClass('disabled');
        }

        // Tự focus vào textarea trả lời nếu có quyền và input đã sẵn sàng
        function focusReplyInput() {
            if (!CAN_REPLY || !$replyMessage.length) return;
            if ($replyMessage.prop('disabled')) return;
            $replyMessage.trigger('focus');
            const input = $replyMessage.get(0);
            if (input && typeof input.setSelectionRange === 'function') {
                const length = $replyMessage.val().length;
                input.setSelectionRange(length, length);
            }
        }

        // Tiện ích escape HTML để render text an toàn
        function escapeHtml(str) {
            return $('<div>').text(str || '').html();
        }

        function getMessagePlainText($item) {
            if (!$item || !$item.length) {
                return '';
            }
            const $msg = $item.find('.main-chat-msg');
            if (!$msg.length) {
                return '';
            }
            const rawHtml = $msg.html() || '';
            if (!rawHtml) {
                return ($msg.text() || '').trim();
            }
            const normalizedHtml = rawHtml
                .replace(/<br\s*\/?>/gi, '\n')
                .replace(/<\/div>\s*<div>/gi, '\n');
            const temp = $('<div>').html(normalizedHtml);
            const text = temp.text().replace(/\u00a0/g, ' ');
            return (text || '').trim();
        }

        function getStoredTranslateLang() {
            if (state.translateLangCache) {
                return state.translateLangCache;
            }
            try {
                const stored = localStorage.getItem(TRANSLATE_LANG_STORAGE_KEY);
                if (stored) {
                    const normalized = stored.trim();
                    if (normalized && /^[a-zA-Z\-]{2,10}$/.test(normalized)) {
                        state.translateLangCache = normalized;
                        return normalized;
                    }
                }
            } catch (error) {
                console.warn('Unable to access localStorage', error);
            }
            return '';
        }

        function setStoredTranslateLang(lang) {
            const normalized = (lang || '').trim();
            if (!normalized || !/^[a-zA-Z\-]{2,10}$/.test(normalized)) {
                return;
            }
            state.translateLangCache = normalized;
            try {
                localStorage.setItem(TRANSLATE_LANG_STORAGE_KEY, normalized);
            } catch (error) {
                console.warn('Unable to persist translate language', error);
            }
        }

        function copyTextToClipboard(text) {
            if (!text) {
                return Promise.resolve(false);
            }
            if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                return navigator.clipboard.writeText(text).then(() => true).catch(() => false);
            }
            return new Promise(resolve => {
                const textarea = $('<textarea>').val(text).css({
                    position: 'fixed',
                    top: '-1000px',
                    left: '-1000px'
                });
                $('body').append(textarea);
                textarea.focus();
                textarea.select();
                let success = false;
                try {
                    success = document.execCommand('copy');
                } catch (error) {
                    success = false;
                }
                textarea.remove();
                resolve(success);
            });
        }

        function ensureLanguageOption(lang) {
            if (!lang || !$translateLanguageSelect.length) return;
            if (!$translateLanguageSelect.find(`option[value="${lang}"]`).length) {
                const match = translationLanguages.find(item => item.id === lang);
                const optionLabel = match ? match.text : lang.toUpperCase();
                $translateLanguageSelect.append(new Option(optionLabel, lang, false, false));
                if (typeof $.fn.select2 === 'function' && $translateLanguageSelect.data('select2')) {
                    $translateLanguageSelect.trigger('change.select2');
                }
            }
        }

        function clearTranslateError() {
            if ($translateErrorAlert.length) {
                $translateErrorAlert.addClass('d-none').text('');
            }
        }

        function displayTranslateError(message) {
            if ($translateErrorAlert.length) {
                $translateErrorAlert.removeClass('d-none').text(message || TEXTS.translateError);
            } else if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: TEXTS.error,
                    text: message || TEXTS.translateError
                });
            }
        }

        function resetTranslateModalView() {
            if ($translateLoading.length) {
                $translateLoading.addClass('d-none');
            }
            if ($translateResult.length) {
                $translateResult.addClass('d-none');
            }
            if ($translateOriginalContent.length) {
                $translateOriginalContent.empty();
            }
            if ($translateTranslatedContent.length) {
                $translateTranslatedContent.empty();
            }
            if ($translateResultLang.length) {
                $translateResultLang.text('');
            }
            if ($translateCopyBtn.length) {
                $translateCopyBtn.prop('disabled', true).data('translated', '');
            }
        }

        function setTranslateLoading(isLoading) {
            if (!$translateConfirmBtn.length) return;
            if (isLoading) {
                if ($translateLoading.length) {
                    $translateLoading.removeClass('d-none');
                }
                $translateConfirmBtn.prop('disabled', true).html(`<span class="spinner-border spinner-border-sm me-1"></span>${TEXTS.translating}`);
            } else {
                if ($translateLoading.length) {
                    $translateLoading.addClass('d-none');
                }
                $translateConfirmBtn.prop('disabled', false).html(translateConfirmInitialHtml);
            }
        }

        function updateTranslateResult(translatedText, lang) {
            if ($translateResult.length) {
                $translateResult.removeClass('d-none');
            }
            if ($translateOriginalContent.length) {
                const originalHtml = escapeHtml(state.translateMessageText).replace(/\n/g, '<br>');
                $translateOriginalContent.html(originalHtml);
            }
            if ($translateTranslatedContent.length) {
                const translatedHtml = escapeHtml(translatedText).replace(/\n/g, '<br>');
                $translateTranslatedContent.html(translatedHtml);
            }
            if ($translateResultLang.length) {
                $translateResultLang.text((lang || '').toUpperCase());
            }
            if ($translateCopyBtn.length) {
                $translateCopyBtn.prop('disabled', !translatedText).data('translated', translatedText);
            }
        }

        function startTranslation(targetLang) {
            if (!state.translateMessageText) {
                displayTranslateError(TEXTS.translateEmpty);
                return;
            }
            const lang = (targetLang || '').trim();
            if (!lang) {
                displayTranslateError(TEXTS.translateSelectRequired);
                return;
            }
            if (!/^[a-zA-Z\-]{2,10}$/.test(lang)) {
                displayTranslateError(TEXTS.translateInvalid);
                return;
            }

            ensureLanguageOption(lang);
            if ($translateLanguageSelect.length) {
                if ($translateLanguageSelect.data('select2')) {
                    $translateLanguageSelect.val(lang).trigger('change.select2');
                } else {
                    $translateLanguageSelect.val(lang).trigger('change');
                }
            }

            if (state.translateAjax && typeof state.translateAjax.abort === 'function') {
                state.translateAjax.abort();
            }

            clearTranslateError();
            resetTranslateModalView();
            setTranslateLoading(true);
            setStoredTranslateLang(lang);
            state.translateLangCache = lang;
            state.translateCurrentLang = lang;
            state.translateAjax = $.ajax({
                url: "<?= BASE_URL('ajaxs/admin/ticket.php'); ?>",
                type: "POST",
                dataType: "json",
                data: {
                    action: 'translateMessage',
                    message: state.translateMessageText,
                    target_lang: lang
                },
                success: function(resp) {
                    if (resp && resp.status === 'success' && resp.translated_text) {
                        state.translateLastResult = resp.translated_text;
                        const responseLang = resp.target_lang ? resp.target_lang : lang;
                        updateTranslateResult(resp.translated_text, responseLang);
                    } else if (resp && resp.msg) {
                        displayTranslateError(resp.msg);
                    } else {
                        displayTranslateError(TEXTS.translateError);
                    }
                },
                error: function() {
                    displayTranslateError(TEXTS.translateError);
                },
                complete: function() {
                    setTranslateLoading(false);
                    state.translateAjax = null;
                }
            });
        }

        function openTranslateModal(messageText) {
            if (!messageText) {
                displayTranslateError(TEXTS.translateEmpty);
                return;
            }
            state.translateMessageText = messageText;
            clearTranslateError();
            resetTranslateModalView();
            setTranslateLoading(false);

            const storedLang = getStoredTranslateLang();
            if ($translateLanguageSelect.length) {
                if (storedLang) {
                    ensureLanguageOption(storedLang);
                    if ($translateLanguageSelect.data('select2')) {
                        $translateLanguageSelect.val(storedLang).trigger('change.select2');
                    } else {
                        $translateLanguageSelect.val(storedLang);
                    }
                } else {
                    if ($translateLanguageSelect.data('select2')) {
                        $translateLanguageSelect.val(null).trigger('change.select2');
                    } else {
                        $translateLanguageSelect.val('');
                    }
                }
            }

            if ($translateModal.length) {
                let modalInstance = null;
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    modalInstance = bootstrap.Modal.getOrCreateInstance($translateModal[0]);
                    modalInstance.show();
                } else if (typeof $translateModal.modal === 'function') {
                    $translateModal.modal('show');
                }
            }

            if (storedLang) {
                setTimeout(() => startTranslation(storedLang), 150);
            }
        }

        // Lưu lại vị trí caret khi nhập để dùng cho quick reply/emoji
        function captureReplyCaret() {
            const input = $replyMessage.get(0);
            if (!input) return;
            state.replyCaretPos = input.selectionStart || 0;
        }

        // Phát hiện token `/command` ngay trước caret
        function getSlashToken(value, caret) {
            const before = value.slice(0, caret);
            const lastSlash = before.lastIndexOf('/');
            if (lastSlash === -1) return null;
            if (lastSlash > 0) {
                const charBefore = before.charAt(lastSlash - 1);
                if (charBefore && !/\s/.test(charBefore)) {
                    return null;
                }
            }
            const token = before.slice(lastSlash + 1);
            if (token.includes(' ') || token.includes('\n') || token.includes('\t')) {
                return null;
            }
            return {
                start: lastSlash,
                end: caret,
                query: token
            };
        }

        // Lọc quick reply theo token hiện tại (tối đa 10 item)
        function filterQuickReplies(query) {
            const normalized = (query || '').toLowerCase();
            const items = state.quickReplies || [];
            if (!normalized.length) {
                return items.slice(0, 10);
            }
            return items.filter(item => item.command.toLowerCase().indexOf(normalized) === 0).slice(0, 10);
        }

        // Ẩn gợi ý quick reply và reset state highlight
        function hideQuickRepliesSuggestion() {
            if ($quickRepliesSuggestion.length) {
                $quickRepliesSuggestion.removeClass('active').empty();
            }
            state.quickRepliesSuggestionItems = [];
            state.quickRepliesHighlightIndex = -1;
        }

        // Render danh sách gợi ý quick reply dưới input
        function renderQuickRepliesSuggestion(items) {
            if (!$quickRepliesSuggestion.length) return;
            if (!items.length) {
                hideQuickRepliesSuggestion();
                return;
            }

            const html = items.map((item, index) => `
            <div class="quick-reply-item ${index === 0 ? 'active' : ''}" data-id="${item.id}">
                <span class="qr-command">/${escapeHtml(item.command)}</span>
                <span class="qr-content">${escapeHtml(item.content)}</span>
            </div>
        `).join('');
            $quickRepliesSuggestion.html(html).addClass('active');
            state.quickRepliesSuggestionItems = items;
            state.quickRepliesHighlightIndex = 0;
        }

        // Điều hướng highlight quick reply bằng phím lên/xuống
        function updateQuickRepliesHighlight(direction) {
            if (!state.quickRepliesSuggestionItems.length) return;
            if (!$quickRepliesSuggestion.length) return;
            const maxIndex = state.quickRepliesSuggestionItems.length - 1;
            if (direction === 'next') {
                state.quickRepliesHighlightIndex = state.quickRepliesHighlightIndex >= maxIndex ? 0 : state.quickRepliesHighlightIndex + 1;
            } else if (direction === 'prev') {
                state.quickRepliesHighlightIndex = state.quickRepliesHighlightIndex <= 0 ? maxIndex : state.quickRepliesHighlightIndex - 1;
            }
            $quickRepliesSuggestion.children('.quick-reply-item').removeClass('active')
                .eq(state.quickRepliesHighlightIndex).addClass('active');
        }

        // Điều khiển hiển thị quick reply khi người dùng nhập trong textarea
        function handleQuickRepliesSuggestion() {
            if (!CAN_REPLY || !$replyMessage.length) {
                hideQuickRepliesSuggestion();
                return;
            }
            if (!state.quickRepliesLoaded) {
                ensureQuickRepliesLoaded(() => handleQuickRepliesSuggestion());
                return;
            }
            const input = $replyMessage.get(0);
            if (!input) return;
            const value = $replyMessage.val();
            const caret = input.selectionStart || value.length;
            state.replyCaretPos = caret;
            const token = getSlashToken(value, caret);
            if (!token) {
                hideQuickRepliesSuggestion();
                return;
            }
            const matches = filterQuickReplies(token.query);
            renderQuickRepliesSuggestion(matches);
        }

        // Chèn nội dung quick reply vào textarea tại vị trí caret
        function applyQuickReply(item) {
            if (!item || !$replyMessage.length) return;
            const input = $replyMessage.get(0);
            if (!input) return;
            const value = $replyMessage.val();
            const caret = input.selectionStart || value.length;
            const token = getSlashToken(value, caret);
            let newValue;
            let newCaret;
            if (token) {
                newValue = value.slice(0, token.start) + item.content + value.slice(token.end);
                newCaret = token.start + item.content.length;
            } else {
                newValue = value + item.content;
                newCaret = newValue.length;
            }
            $replyMessage.val(newValue);
            if (typeof input.setSelectionRange === 'function') {
                input.setSelectionRange(newCaret, newCaret);
            }
            captureReplyCaret();
            hideQuickRepliesSuggestion();
            $replyMessage.trigger('input').focus();
        }

        // Đảm bảo đã load quick reply; nếu chưa thì load và gọi callback
        function ensureQuickRepliesLoaded(callback) {
            if (state.quickRepliesLoaded && !state.quickRepliesLoading) {
                if (typeof callback === 'function') callback();
                return;
            }
            fetchQuickReplies({
                callback
            });
        }

        // Load quick reply từ server (chỉ của admin hiện tại)
        function fetchQuickReplies(options = {}) {
            if (state.quickRepliesLoading) return;
            state.quickRepliesLoading = true;
            $.ajax({
                url: "<?= BASE_URL('ajaxs/admin/ticket.php'); ?>",
                type: "POST",
                dataType: "json",
                data: {
                    action: 'listQuickReplies'
                },
                success: function(resp) {
                    if (resp && resp.status === 'success' && Array.isArray(resp.data)) {
                        refreshQuickRepliesCache(resp.data);
                    }
                },
                complete: function() {
                    state.quickRepliesLoading = false;
                    if (typeof options.callback === 'function') {
                        options.callback();
                    }
                }
            });
        }

        // Render danh sách quick reply trong modal quản lý
        function renderQuickRepliesList() {
            if (!$quickRepliesList.length) return;
            if (!state.quickReplies || !state.quickReplies.length) {
                $quickRepliesList.empty();
                $quickRepliesEmpty.removeClass('d-none');
                return;
            }
            $quickRepliesEmpty.addClass('d-none');
            const html = state.quickReplies.map(item => `
            <li class="list-group-item d-flex justify-content-between align-items-start" data-id="${item.id}">
                <div class="me-3">
                    <div class="fw-semibold text-primary">/${escapeHtml(item.command)}</div>
                    <div class="text-muted small mt-1">${escapeHtml(item.content)}</div>
                </div>
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-primary btn-sm edit-quick-reply" data-id="${item.id}">
                        <i class="ri-edit-2-line"></i>
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm delete-quick-reply" data-id="${item.id}">
                        <i class="ri-delete-bin-line"></i>
                    </button>
                </div>
            </li>
        `).join('');
            $quickRepliesList.html(html);
        }

        // Reset form quick reply (modal) về trạng thái thêm mới
        function resetQuickReplyFormFields() {
            if (!$quickRepliesForm.length) return;
            $quickReplyId.val('');
            $quickReplyCommand.val('');
            $quickReplyContent.val('');
            $quickReplySubmitBtn.html('<i class="ri-save-3-line me-1"></i><?= __('Lưu câu trả lời'); ?>');
            $quickRepliesForm.data('mode', 'create');
        }

        // Đổ dữ liệu vào form khi chỉnh sửa quick reply
        function setQuickReplyForm(reply) {
            if (!reply) {
                resetQuickReplyFormFields();
                return;
            }
            $quickReplyId.val(reply.id);
            $quickReplyCommand.val('/' + reply.command);
            $quickReplyContent.val(reply.content);
            $quickReplySubmitBtn.html('<i class="ri-save-3-line me-1"></i><?= __('Cập nhật'); ?>');
            $quickRepliesForm.data('mode', 'edit');
        }

        // Cập nhật cache quick reply và render lại UI
        function refreshQuickRepliesCache(data) {
            if (!Array.isArray(data)) return;
            state.quickReplies = data.slice().sort((a, b) => a.command.localeCompare(b.command));
            state.quickRepliesLoaded = true;
            renderQuickRepliesList();
            handleQuickRepliesSuggestion();
        }

        // Reset dòng hội thoại khi chuyển ticket
        function resetConversationTimeline() {
            $conversationTimeline.empty();
            state.lastTimelineDate = null;
            state.lastMessageId = 0;
        }

        // Đảm bảo hiển thị nhãn ngày khi có tin nhắn mới
        function ensureDayLabel(dateLabel) {
            if (!dateLabel) return;
            const parts = dateLabel.split('/');
            if (parts.length !== 3) return;
            const iso = [parts[2], parts[1], parts[0]].join('-');
            const lastLabel = $conversationTimeline.find('.chat-day-label').last();
            if (!lastLabel.length || lastLabel.data('chat-date') !== iso) {
                $conversationTimeline.append(
                    `<li class="chat-day-label" data-chat-date="${iso}"><span>${escapeHtml(dateLabel)}</span></li>`
                );
            }
            state.lastTimelineDate = iso;
        }

        function updateTicketNotifications(groups) {
            if (!Array.isArray(groups)) return;
            const nextIndicators = {};
            let hasNew = false;

            groups.forEach(group => {
                (group.tickets || []).forEach(ticket => {
                    const id = String(ticket.id);
                    const showBadge = !!ticket.show_badge;
                    const prev = state.ticketIndicators[id];
                    nextIndicators[id] = showBadge;

                    if (state.ticketsInitialized) {
                        if (prev === undefined) {
                            hasNew = true;
                        } else if (!prev && showBadge) {
                            hasNew = true;
                        }
                    }
                });
            });

            state.ticketIndicators = nextIndicators;
            if (!state.ticketsInitialized) {
                state.ticketsInitialized = true;
            } else if (hasNew) {
                playNotificationSound();
            }
        }

        // Append một tin nhắn vào timeline và cập nhật lastMessageId
        function appendMessage(msg) {
            if (!msg || !msg.id) return false;
            if ($conversationTimeline.find(`[data-message-id="${msg.id}"]`).length) {
                return false;
            }

            ensureDayLabel(msg.date || msg.date_label || '');

            const timeLabel = escapeHtml(msg.time || (msg.formatted_time ? msg.formatted_time.split(' ')[0] : ''));
            const username = escapeHtml(msg.username || '');
            const avatar = escapeHtml(msg.avatar || '');
            const content = msg.message || '';

            const canRecallAttr = msg.can_recall ? '1' : '0';

            if (msg.sender_type === 'user') {
                const userOnlineClass = msg.user_online ? 'online' : 'offline';
                $conversationTimeline.append(`
                        <li class="chat-item-start" data-message-id="${msg.id}" data-can-recall="${canRecallAttr}" data-sender="user">
                            <div class="chat-list-inner">
                                <div class="chat-user-profile">
                            <span class="avatar avatar-md ${userOnlineClass} avatar-rounded chatstatusperson">
                                <img src="${avatar}" alt="${username}">
                                    </span>
                                </div>
                                <div class="ms-3">
                                    <span class="chatting-user-info">
                                <span class="chatnameperson">${username}</span> <span class="msg-sent-time">${timeLabel}</span>
                                    </span>
                                    <div class="main-chat-msg">
                                <div>${content}</div>
                                    </div>
                                </div>
                            </div>
                </li>
            `);
            } else {
                $conversationTimeline.append(`
                <li class="chat-item-end" data-message-id="${msg.id}" data-can-recall="${canRecallAttr}" data-sender="admin">
                    <div class="chat-list-inner">
                        <div class="me-3">
                            <span class="chatting-user-info">
                                <span class="msg-sent-time"><span class="chat-read-mark align-middle d-inline-flex"><i class="ri-check-double-line"></i></span>${timeLabel}</span> ${username}
                            </span>
                            <div class="main-chat-msg">
                                <div>${content}</div>
                            </div>
                        </div>
                        <div class="chat-user-profile">
                            <span class="avatar avatar-md avatar-rounded">
                                <img src="${avatar}" alt="${username}">
                            </span>
                        </div>
                    </div>
                </li>
            `);
            }

            const numericId = parseInt(msg.id, 10);
            if (!isNaN(numericId)) {
                state.lastMessageId = Math.max(state.lastMessageId, numericId);
            }
            return true;
        }

        // Cuộn xuống cuối khung chat
        function scrollToBottom() {
            const container = $('#main-chat-content');
            if (container.length) {
                container.stop().animate({
                    scrollTop: container[0].scrollHeight
                }, 300);
            }
        }

        // Cập nhật thống kê số ticket hiển thị
        function updateTicketsSummary(groups, stats) {
            const totalDisplayed = groups.reduce((sum, group) => sum + (group.tickets ? group.tickets.length : 0), 0);
            const totalTickets = stats && stats.total ? stats.total : totalDisplayed;
            $ticketsSummary.html(`<i class="ri-message-2-line"></i> <?= __('Hiển thị'); ?> ${totalDisplayed} / ${totalTickets}`);
        }

        // Render danh sách ticket theo nhóm (chưa trả lời / đã trả lời / đóng)
        function renderTicketsList(groups, selectedId) {
            let html = '';
            groups.forEach(group => {
                html += `<li class="pb-0"><p class="text-muted fs-11 fw-semibold mb-2 op-7">${escapeHtml(group.title || '')}</p></li>`;
                if (!group.tickets || group.tickets.length === 0) {
                    html += `<li class="chat-empty"><i class="${escapeHtml(group.emptyIcon || '')}"></i><p class="mb-0">${escapeHtml(group.emptyText || TEXTS.noData)}</p></li>`;
                    return;
                }
                group.tickets.forEach(ticket => {
                    const liClasses = ['checkforactive'];
                    if (group.itemClass) liClasses.push(group.itemClass);
                    if (ticket.is_closed && (!group.itemClass || group.itemClass.indexOf('chat-inactive') === -1)) {
                        liClasses.push('chat-inactive');
                    }
                    if (selectedId && parseInt(ticket.id, 10) === parseInt(selectedId, 10)) {
                        liClasses.push('ticket-active');
                    }
                    const onlineClass = ticket.is_online ? 'online' : 'offline';
                    const extraStatus = (ticket.status_class && ticket.status_class !== onlineClass) ? ` ${escapeHtml(ticket.status_class)}` : '';
                    const statusClass = onlineClass + extraStatus;
                    const messageClass = escapeHtml(ticket.message_class || '');
                    const badgeHtml = ticket.show_badge ? `<span class="badge bg-success-transparent rounded-circle float-end ms-2">${escapeHtml(String(ticket.admin_replies || 0))}</span>` : '';
                    const categoryText = ticket.category_text ? escapeHtml(ticket.category_text) : '';
                    const categoryBadgeClass = (ticket.category_badge_class || '')
                        .split(/\s+/)
                        .filter(Boolean)
                        .map(cls => escapeHtml(cls))
                        .join(' ') || 'bg-secondary text-white';
                    const categoryBadge = categoryText ?
                        `<span class="badge ${categoryBadgeClass}">${categoryText}</span>` :
                        '';
                    html += `
                <li class="${liClasses.join(' ')}">
                    <button type="button" class="ticket-item ticket-item-btn" data-ticket-id="${ticket.id}">
                        <div class="d-flex align-items-top">
                            <div class="me-1 lh-1">
                                <span class="avatar avatar-md ${statusClass} me-2 avatar-rounded">
                                    <img src="${escapeHtml(ticket.avatar || '')}" alt="${escapeHtml(ticket.username || '')}">
                                </span>
                            </div>
                            <div class="flex-fill">
                                <p class="mb-0 fw-semibold">
                                    ${escapeHtml(ticket.subject || '')}
                                    <span class="float-end text-muted fw-normal fs-11">${escapeHtml(ticket.last_time || '')}</span>
                                </p>
                                <p class="fs-12 mb-0 ${messageClass}">
                                    <span class="chat-msg text-truncate">${escapeHtml(ticket.preview || TEXTS.noData)}</span>
                                    ${badgeHtml}
                                    <span class="chat-read-icon float-end align-middle ms-2"><i class="${escapeHtml(ticket.status_icon || '')}"></i></span>
                                </p>
                                ${categoryBadge ? `<div class="mt-1">${categoryBadge}</div>` : ''}
                            </div>
                        </div>
                    </button>
                        </li>`;
                });
            });
            $ticketsList.html(html);
        }

        // Xử lý dữ liệu ticket nhận từ server sau khi load
        function handleTicketsResponse(response) {
            state.isLoadingTickets = false;
            $ticketsLoading.addClass('d-none');

            if (!response || response.status !== 'success') {
                $ticketsEmpty.removeClass('d-none');
                $ticketsListWrapper.addClass('d-none');
                return;
            }

            const groups = response.groups || [];
            const stats = response.stats || {};
            const hasTickets = groups.some(group => group.tickets && group.tickets.length > 0);

            updateTicketNotifications(groups);

            if (!hasTickets) {
                $ticketsEmpty.removeClass('d-none');
                $ticketsListWrapper.addClass('d-none');
            } else {
                $ticketsEmpty.addClass('d-none');
                $ticketsListWrapper.removeClass('d-none');
                renderTicketsList(groups, state.selectedTicketId);
                updateTicketsSummary(groups, stats);
            }

            const ticketExists = groups.some(group => (group.tickets || []).some(ticket => parseInt(ticket.id, 10) === state.selectedTicketId));
            if (!ticketExists) {
                state.selectedTicketId = null;
            }

            let defaultTicketId = response.selected_id || null;
            if (!defaultTicketId) {
                for (const group of groups) {
                    if (group.tickets && group.tickets.length > 0) {
                        defaultTicketId = group.tickets[0].id;
                        break;
                    }
                }
            }

            if (state.selectedTicketId) {
                highlightTicket(state.selectedTicketId);
            } else if (defaultTicketId) {
                selectTicket(defaultTicketId);
            } else {
                showChatPlaceholder();
            }
        }

        // Gọi API load danh sách ticket (overview)
        function fetchTickets(options = {}) {
            if (state.isLoadingTickets) return;
            state.isLoadingTickets = true;

            if (!options.quiet) {
                $ticketsLoading.removeClass('d-none');
                $ticketsEmpty.addClass('d-none');
                $ticketsListWrapper.addClass('d-none');
            }

            // Sử dụng data đã pre-fetch nếu có (load lần đầu nhanh hơn)
            if (!options.quiet && window.__prefetchedTicketsPromise) {
                var prefetchPromise = window.__prefetchedTicketsPromise;
                window.__prefetchedTicketsPromise = null; // Chỉ dùng 1 lần

                prefetchPromise.then(function(data) {
                    if (data) {
                        handleTicketsResponse(data);
                    } else {
                        // Fallback nếu pre-fetch thất bại
                        doFetchTickets(options);
                    }
                }).catch(function() {
                    doFetchTickets(options);
                });
                return;
            }

            doFetchTickets(options);
        }

        // Thực hiện AJAX fetch tickets
        function doFetchTickets(options) {
            $.ajax({
                url: "<?= BASE_URL('ajaxs/admin/ticket.php'); ?>",
                type: "POST",
                dataType: "json",
                data: {
                    action: 'getSupportTicketsOverview',
                    selected_id: state.selectedTicketId
                },
                success: handleTicketsResponse,
                error: function() {
                    state.isLoadingTickets = false;
                    $ticketsLoading.addClass('d-none');
                    $ticketsEmpty.removeClass('d-none');
                }
            });
        }

        // Highlight ticket hiện tại trong danh sách
        function highlightTicket(ticketId) {
            $('.ticket-item').each(function() {
                const parent = $(this).closest('li');
                if (parseInt($(this).data('ticket-id'), 10) === parseInt(ticketId, 10)) {
                    parent.addClass('ticket-active');
                } else {
                    parent.removeClass('ticket-active');
                }
            });
        }

        // Đưa UI về trạng thái placeholder khi chưa chọn ticket
        function showChatPlaceholder() {
            clearInterval(state.messageTimer);
            state.messageTimer = null;
            if (state.adminNoteTimer) {
                clearTimeout(state.adminNoteTimer);
                state.adminNoteTimer = null;
            }
            state.adminNoteSaving = false;
            state.adminNoteCurrent = '';
            resetConversationTimeline();
            $chatPanel.addClass('d-none');
            $chatLoading.addClass('d-none');
            $chatPlaceholder.removeClass('d-none');
            $sidebarContent.addClass('d-none');
            $sidebarLoading.addClass('d-none');
            $sidebarPlaceholder.removeClass('d-none');
            closeEmojiPanel();
            if ($adminNoteTextarea.length) {
                $adminNoteTextarea.val('').prop('disabled', true);
            }
            if ($adminNoteSaving.length) {
                $adminNoteSaving.addClass('d-none');
            }
            if ($adminNoteStatus.length) {
                $adminNoteStatus.addClass('d-none');
            }
            if ($adminNoteError.length) {
                $adminNoteError.addClass('d-none');
            }
        }

        // Đóng bảng emoji nếu đang mở
        function closeEmojiPanel() {
            if ($emojiPanel.length) {
                $emojiPanel.removeClass('active');
            }
        }

        // Hiển thị trạng thái loading cho khung chat
        function showChatLoading() {
            $chatPlaceholder.addClass('d-none');
            $chatPanel.addClass('d-none');
            $chatLoading.removeClass('d-none');
        }

        // Hiển thị trạng thái loading cho sidebar
        function showSidebarLoading() {
            $sidebarPlaceholder.addClass('d-none');
            $sidebarContent.addClass('d-none');
            $sidebarLoading.removeClass('d-none');
            if ($adminNoteTextarea.length) {
                $adminNoteTextarea.prop('disabled', true);
            }
            if ($adminNoteSaving.length) {
                $adminNoteSaving.addClass('d-none');
            }
            if ($adminNoteStatus.length) {
                $adminNoteStatus.addClass('d-none');
            }
            if ($adminNoteError.length) {
                $adminNoteError.addClass('d-none');
            }
        }

        // Render thông tin ticket + khách hàng + đơn hàng ở sidebar
        function renderSidebar(data) {
            if (!data) return;
            $('#ticketInfoId').text('#' + (data.ticket.id || '-'));
            $('#ticketInfoStatus').html(data.ticket.status_html || escapeHtml(data.ticket.status_text || ''));
            $('#ticketInfoCategory').text(data.ticket.category_text || '-');
            $('#ticketInfoCreated').text(data.ticket.created_at || '-');
            $('#ticketInfoUpdated').text(data.ticket.updated_at || '-');

            $('#customerBalance').text(data.customer.balance || '-');
            $('#customerSpent').text(data.customer.spent || '-');
            $('#customerOrders').text(data.customer.orders || 0);
            $('#customerTickets').text(data.customer.tickets || 0);
            if (data.customer.last_active) {
                $('#customerLastActive').text(data.customer.last_active);
                $('#customerLastActiveWrapper').show();
            } else {
                $('#customerLastActiveWrapper').hide();
            }
            if (data.customer.edit_link) {
                $('#customerEditLink').attr('href', data.customer.edit_link);
                $('#customerEditLinkWrapper').removeClass('d-none');
            } else {
                $('#customerEditLinkWrapper').addClass('d-none');
            }

            if (data.order && data.order.exists) {
                $('#orderInfoList').removeClass('d-none');
                $('#orderInfoEmpty').addClass('d-none');
                $('#orderTransId').text('#' + (data.order.trans_id || ''));
                if (data.order.status_html) {
                    $('#orderStatus').html(data.order.status_html);
                } else {
                    $('#orderStatus').text(data.order.status_text || data.order.status || '');
                }
                if ($orderServiceWrapper.length) {
                    if (data.order.service_name) {
                        $orderServiceName.text(data.order.service_name);
                        $orderServiceWrapper.removeClass('d-none');
                    } else {
                        $orderServiceName.text('-');
                        $orderServiceWrapper.addClass('d-none');
                    }
                }
                $('#orderQuantity').text(data.order.quantity || 0);
                $('#orderPay').text(data.order.pay || '');
                if (data.order.profit) {
                    $('#orderProfit').text(data.order.profit);
                    $('#orderProfitWrapper').show();
                } else {
                    $('#orderProfitWrapper').hide();
                }
                $('#orderEditLink').attr('href', data.order.edit_link || '#');
            } else {
                $('#orderInfoList').addClass('d-none');
                $('#orderInfoEmpty').removeClass('d-none');
                if ($orderServiceWrapper.length) {
                    $orderServiceName.text('-');
                    $orderServiceWrapper.addClass('d-none');
                }
            }

            if ($adminNoteTextarea.length) {
                const note = data.ticket.admin_note || '';
                state.adminNoteCurrent = note;
                $adminNoteTextarea.val(note).prop('disabled', false);
            }
            if ($adminNoteSaving.length) {
                $adminNoteSaving.addClass('d-none');
            }
            if ($adminNoteStatus.length) {
                $adminNoteStatus.addClass('d-none').text(TEXTS.noteSaved);
            }
            if ($adminNoteError.length) {
                $adminNoteError.addClass('d-none').text(TEXTS.noteSaveError);
            }
        }

        // Render phần header (avatar, status) của ticket
        function renderChatHeader(data) {
            $('#chatUserAvatar').attr('src', data.ticket.avatar || '');
            $('#chatUserAvatar').attr('alt', data.ticket.username || '');
            const userOnline = (data.ticket && data.ticket.is_online) || (data.customer && data.customer.online) ? true : false;
            $('#chatUserAvatarWrapper').removeClass('online offline').addClass(userOnline ? 'online' : 'offline');
            $('#chatUserLink').text(data.ticket.username || '');
            $('#chatUserLink').attr('href', data.ticket.user_edit_link || '#');
            $('#chatUserStatus').html(data.ticket.status_html || escapeHtml(data.ticket.status_text || ''));

            if (CAN_REPLY) {
                $replyTicketId.val(data.ticket.id);
                $replyMessage.val('').prop('disabled', false);
                $replySubmitBtn.prop('disabled', false);
                $emojiBtn.prop('disabled', false);
                requestAnimationFrame(focusReplyInput);
            }
        }

        // Render danh sách tin nhắn trong ticket
        function renderConversation(messages) {
            resetConversationTimeline();
            if (Array.isArray(messages)) {
                messages.forEach(msg => appendMessage(msg));
            }
            setTimeout(scrollToBottom, 200);
            setTimeout(focusReplyInput, 220);
        }

        // Load chi tiết ticket: hội thoại + sidebar + thiết lập timer
        function loadTicketDetail(ticketId, options = {}) {
            if (!ticketId) return;
            showChatLoading();
            showSidebarLoading();
            resetConversationTimeline();
            clearInterval(state.messageTimer);
            state.messageTimer = null;
            if (state.adminNoteTimer) {
                clearTimeout(state.adminNoteTimer);
                state.adminNoteTimer = null;
            }
            state.adminNoteSaving = false;

            $.ajax({
                url: "<?= BASE_URL('ajaxs/admin/ticket.php'); ?>",
                type: "POST",
                dataType: "json",
                data: {
                    action: 'get_ticket_conversation_admin',
                    ticket_id: ticketId
                },
                success: function(response) {
                    if (!response || response.status !== 'success') {
                        showChatPlaceholder();
                        if (response && response.msg) {
                            Swal.fire({
                                icon: 'error',
                                title: TEXTS.error,
                                text: response.msg
                            });
                        }
                        return;
                    }
                    renderChatHeader(response);
                    renderConversation(response.messages || []);
                    renderSidebar(response);

                    $chatLoading.addClass('d-none');
                    $chatPlaceholder.addClass('d-none');
                    $chatPanel.removeClass('d-none');
                    $sidebarLoading.addClass('d-none');
                    $sidebarPlaceholder.addClass('d-none');
                    $sidebarContent.removeClass('d-none');
                    setTimeout(focusReplyInput, 150);

                    if (response.messages && response.messages.length) {
                        const last = response.messages[response.messages.length - 1];
                        const numericId = parseInt(last.id, 10);
                        if (!isNaN(numericId)) {
                            state.lastMessageId = numericId;
                        }
                    }

                    state.messageTimer = setInterval(pollNewMessages, refreshInterval);
                    if (!options.skipScroll) {
                        setTimeout(scrollToBottom, 200);
                    }
                },
                error: function() {
                    showChatPlaceholder();
                }
            });
        }

        // Poll tin nhắn mới định kỳ khi đang xem ticket
        function pollNewMessages() {
            if (!state.selectedTicketId || state.isLoadingMessages) return;
            state.isLoadingMessages = true;
            $.ajax({
                url: "<?= BASE_URL('ajaxs/admin/ticket.php'); ?>",
                type: "POST",
                dataType: "json",
                data: {
                    action: 'get_new_messages_admin',
                    ticket_id: state.selectedTicketId,
                    last_message_id: state.lastMessageId
                },
                success: function(response) {
                    if (response && response.status === 'success' && Array.isArray(response.messages)) {
                        let appended = false;
                        let hasIncomingUserMessage = false;
                        response.messages.forEach(msg => {
                            if (appendMessage(msg)) {
                                appended = true;
                                if (msg.sender_type !== 'admin') {
                                    hasIncomingUserMessage = true;
                                }
                            }
                        });
                        if (appended) {
                            if (typeof window.refreshTicketsOverview === 'function') {
                                window.refreshTicketsOverview();
                            }
                            setTimeout(scrollToBottom, 200);
                            if (hasIncomingUserMessage) {
                                playNotificationSound();
                            }
                        }
                    }
                },
                complete: function() {
                    state.isLoadingMessages = false;
                }
            });
        }

        // Chọn ticket trong danh sách (click) và load nội dung
        function selectTicket(ticketId, options = {}) {
            if (!ticketId) return;
            ticketId = parseInt(ticketId, 10);
            const isSame = state.selectedTicketId === ticketId;
            state.selectedTicketId = ticketId;
            highlightTicket(ticketId);
            if (isSame && options.skipLoad) {
                return;
            }
            loadTicketDetail(ticketId, options);
        }

        // Gọi API đổi trạng thái ticket và refresh UI
        function changeTicketStatus(status) {
            if (!CAN_EDIT_STATUS || !state.selectedTicketId) return;
            let statusText = '';
            switch (status) {
                case 'answered':
                    statusText = <?= json_encode(__('đã trả lời')); ?>;
                    break;
                case 'closed':
                    statusText = <?= json_encode(__('đóng')); ?>;
                    break;
                case 'open':
                    statusText = <?= json_encode(__('mở lại')); ?>;
                    break;
                default:
                    statusText = status;
            }
            Swal.fire({
                icon: 'question',
                title: <?= json_encode(__('Xác nhận thay đổi')); ?>,
                text: <?= json_encode(__('Bạn có chắc chắn muốn đánh dấu ticket này là')); ?> + ' ' + statusText + '?',
                showCancelButton: true,
                confirmButtonText: <?= json_encode(__('Xác nhận')); ?>,
                cancelButtonText: <?= json_encode(__('Hủy')); ?>
            }).then(result => {
                if (!result.isConfirmed) return;
                $.ajax({
                    url: "<?= BASE_URL('ajaxs/admin/ticket.php'); ?>",
                    type: "POST",
                    dataType: "json",
                    data: {
                        action: 'changeStatusTicket',
                        id: state.selectedTicketId,
                        status: status
                    },
                    success: function(resp) {
                        if (resp && resp.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: TEXTS.success,
                                text: resp.msg || TEXTS.statusUpdated,
                                timer: 1500,
                                showConfirmButton: false
                            });
                            fetchTickets({
                                quiet: true
                            });
                            loadTicketDetail(state.selectedTicketId, {
                                skipScroll: true
                            });
                        } else if (resp && resp.msg) {
                            Swal.fire({
                                icon: 'error',
                                title: TEXTS.error,
                                text: resp.msg
                            });
                        }
                    }
                });
            });
        }

        // Gọi API xóa ticket + hiển thị confirm
        function deleteTicket(ticketId) {
            if (!CAN_EDIT_STATUS || !ticketId) return;
            Swal.fire({
                icon: 'warning',
                title: <?= json_encode(__('Xác nhận xóa')); ?>,
                text: TEXTS.confirmDelete,
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: <?= json_encode(__('Xóa')); ?>,
                cancelButtonText: <?= json_encode(__('Hủy')); ?>
            }).then(result => {
                if (!result.isConfirmed) return;
                $.ajax({
                    url: "<?= BASE_URL('ajaxs/admin/ticket.php'); ?>",
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'deleteTicket',
                        id: ticketId
                    },
                    success: function(resp) {
                        if (resp && resp.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: TEXTS.success,
                                text: resp.msg || TEXTS.deleted,
                                timer: 1500,
                                showConfirmButton: false
                            });
                            showChatPlaceholder();
                            fetchTickets();
                        } else if (resp && resp.msg) {
                            Swal.fire({
                                icon: 'error',
                                title: TEXTS.error,
                                text: resp.msg
                            });
                        }
                    }
                });
            });
        }

        $ticketsList.on('click', '.ticket-item', function(e) {
            e.preventDefault();
            const ticketId = $(this).data('ticket-id');
            selectTicket(ticketId);
            setTimeout(focusReplyInput, 150);
        });

        $('#chatActionButtons').on('click', '[data-action="change-status"]', function() {
            const status = $(this).data('status');
            changeTicketStatus(status);
        });

        $('#deleteTicketBtn').on('click', function() {
            deleteTicket(state.selectedTicketId);
        });

        // Submit trả lời ticket (Ctrl+Enter gửi nhanh)
        if (CAN_REPLY && $replyForm.length) {
            $replyForm.on('submit', function(e) {
                e.preventDefault();
                if (!state.selectedTicketId) return;
                const message = $replyMessage.val().trim();
                if (!message) {
                    Swal.fire({
                        icon: 'warning',
                        title: <?= json_encode(__('Cảnh báo')); ?>,
                        text: <?= json_encode(__('Vui lòng nhập nội dung phản hồi')); ?>
                    });
                    return;
                }
                $replySubmitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span><?= __('Đang gửi...'); ?>');
                $.ajax({
                    url: "<?= BASE_URL('ajaxs/admin/ticket.php'); ?>",
                    type: "POST",
                    dataType: "json",
                    data: {
                        action: 'replyTicket',
                        ticket_id: state.selectedTicketId,
                        message: message
                    },
                    success: function(resp) {
                        if (resp && resp.status === 'success') {
                            if (resp.message) {
                                appendMessage(resp.message);
                                setTimeout(scrollToBottom, 150);
                            }
                            $replyMessage.val('');
                            if (typeof window.refreshTicketsOverview === 'function') {
                                window.refreshTicketsOverview();
                            }
                        } else if (resp && resp.msg) {
                            Swal.fire({
                                icon: 'error',
                                title: TEXTS.error,
                                text: resp.msg
                            });
                        }
                    },
                    complete: function() {
                        $replySubmitBtn.prop('disabled', false).html('<i class="ri-send-plane-2-line"></i>');
                    }
                });
            });

            // Lắng nghe phím điều hướng/Enter/Escape khi gợi ý quick reply hiển thị
            $replyMessage.on('keydown', function(e) {
                captureReplyCaret();
                const hasSuggestions = $quickRepliesSuggestion.hasClass('active') && state.quickRepliesSuggestionItems.length;
                if (hasSuggestions) {
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        updateQuickRepliesHighlight('next');
                        return;
                    }
                    if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        updateQuickRepliesHighlight('prev');
                        return;
                    }
                    if (e.key === 'Enter') {
                        const item = state.quickRepliesSuggestionItems[state.quickRepliesHighlightIndex];
                        if (item) {
                            e.preventDefault();
                            applyQuickReply(item);
                            return;
                        }
                    }
                    if (e.key === 'Escape') {
                        hideQuickRepliesSuggestion();
                        return;
                    }
                }
                if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                    e.preventDefault();
                    $replyForm.trigger('submit');
                    return;
                }
            });

            // Mỗi lần input thay đổi -> cập nhật caret + gợi ý quick reply
            $replyMessage.on('input', function() {
                captureReplyCaret();
                handleQuickRepliesSuggestion();
            });

            // Khi focus/click vào textarea -> cập nhật caret + load gợi ý (nếu có)
            $replyMessage.on('click focus', function() {
                captureReplyCaret();
                handleQuickRepliesSuggestion();
            });
        }

        $conversationTimeline.on('contextmenu', 'li[data-message-id]', function(e) {
            const messageId = $(this).data('message-id');
            if (!messageId) return;
            e.preventDefault();
            hideMessageContextMenu();
            contextMenuTarget = $(this);

            const canRecall = (contextMenuTarget.data('can-recall') === 1 || contextMenuTarget.data('can-recall') === '1') && CAN_REPLY;
            if (canRecall) {
                $contextRecallBtn.show();
            } else {
                $contextRecallBtn.hide();
            }

            const isUserMessage = contextMenuTarget.hasClass('chat-item-start') || contextMenuTarget.data('sender') === 'user';
            if (isUserMessage) {
                $contextTranslateBtn.show();
            } else {
                $contextTranslateBtn.hide();
            }

            const menuWidth = $messageContextMenu.outerWidth();
            const menuHeight = $messageContextMenu.outerHeight();
            const scrollLeft = $(window).scrollLeft();
            const scrollTop = $(window).scrollTop();
            const winWidth = $(window).width();
            const winHeight = $(window).height();

            let posX = e.pageX;
            let posY = e.pageY;

            if (posX + menuWidth > scrollLeft + winWidth) {
                posX = scrollLeft + winWidth - menuWidth - 8;
            }
            if (posY + menuHeight > scrollTop + winHeight) {
                posY = scrollTop + winHeight - menuHeight - 8;
            }

            posX = Math.max(scrollLeft + 4, posX);
            posY = Math.max(scrollTop + 4, posY);

            $messageContextMenu.css({
                top: posY,
                left: posX
            }).show();
        });

        $contextCopyBtn.on('click', function() {
            if (!contextMenuTarget) return;
            const text = contextMenuTarget.find('.main-chat-msg').text().trim();
            hideMessageContextMenu();
            if (!text) {
                if (typeof showMessage === 'function') {
                    showMessage(TEXTS.copyError, 'error');
                }
                return;
            }

            const handleSuccess = () => {
                if (typeof showMessage === 'function') {
                    showMessage(TEXTS.copySuccess, 'success');
                }
            };
            const handleError = () => {
                if (typeof showMessage === 'function') {
                    showMessage(TEXTS.copyError, 'error');
                }
            };

            if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                navigator.clipboard.writeText(text).then(handleSuccess).catch(() => {
                    const textarea = $('<textarea>').val(text).css({
                        position: 'fixed',
                        top: '-1000px',
                        left: '-1000px'
                    });
                    $('body').append(textarea);
                    textarea.focus();
                    textarea.select();
                    try {
                        const result = document.execCommand('copy');
                        if (result) {
                            handleSuccess();
                        } else {
                            handleError();
                        }
                    } catch (err) {
                        handleError();
                    } finally {
                        textarea.remove();
                    }
                });
            } else {
                const textarea = $('<textarea>').val(text).css({
                    position: 'fixed',
                    top: '-1000px',
                    left: '-1000px'
                });
                $('body').append(textarea);
                textarea.focus();
                textarea.select();
                try {
                    const result = document.execCommand('copy');
                    if (result) {
                        handleSuccess();
                    } else {
                        handleError();
                    }
                } catch (err) {
                    handleError();
                } finally {
                    textarea.remove();
                }
            }
        });

        $contextTranslateBtn.on('click', function() {
            if (!contextMenuTarget) return;
            const messageText = getMessagePlainText(contextMenuTarget);
            hideMessageContextMenu();
            if (!messageText) {
                if (typeof showMessage === 'function') {
                    showMessage(TEXTS.translateEmpty, 'error');
                } else {
                    displayTranslateError(TEXTS.translateEmpty);
                }
                return;
            }
            if (messageText.length > 5000) {
                if (typeof showMessage === 'function') {
                    showMessage(TEXTS.translateInvalid, 'error');
                } else {
                    displayTranslateError(TEXTS.translateInvalid);
                }
                return;
            }
            openTranslateModal(messageText);
        });

        if ($translateConfirmBtn.length) {
            $translateConfirmBtn.on('click', function() {
                const lang = String($translateLanguageSelect.val() || '').trim();
                if (!lang) {
                    displayTranslateError(TEXTS.translateSelectRequired);
                    return;
                }
                startTranslation(lang);
            });
        }

        if ($translateCopyBtn.length) {
            $translateCopyBtn.on('click', function() {
                const translated = $(this).data('translated') || state.translateLastResult || '';
                copyTextToClipboard(translated).then(success => {
                    if (typeof showMessage === 'function') {
                        showMessage(success ? TEXTS.translateCopySuccess : TEXTS.translateCopyError, success ? 'success' : 'error');
                    } else if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: success ? 'success' : 'error',
                            title: success ? TEXTS.success : TEXTS.error,
                            text: success ? TEXTS.translateCopySuccess : TEXTS.translateCopyError
                        });
                    }
                });
            });
        }

        if ($translateLanguageSelect.length) {
            $translateLanguageSelect.on('change', function() {
                clearTranslateError();
                resetTranslateModalView();
                state.translateLastResult = '';
                state.translateCurrentLang = '';
            });
        }

        if ($translateModal.length) {
            $translateModal.on('hidden.bs.modal', function() {
                if (state.translateAjax && typeof state.translateAjax.abort === 'function') {
                    state.translateAjax.abort();
                    state.translateAjax = null;
                }
                state.translateMessageText = '';
                state.translateLastResult = '';
                setTranslateLoading(false);
                resetTranslateModalView();
                clearTranslateError();
            });
        }

        $contextRecallBtn.on('click', function() {
            if (!contextMenuTarget) return;
            const messageId = contextMenuTarget.data('message-id');
            if (!messageId) {
                hideMessageContextMenu();
                return;
            }
            hideMessageContextMenu();
            Swal.fire({
                icon: 'warning',
                title: TEXTS.recall,
                text: TEXTS.recallConfirm,
                showCancelButton: true,
                confirmButtonText: TEXTS.recall,
                cancelButtonText: TEXTS.cancel,
                confirmButtonColor: '#d33'
            }).then(result => {
                if (!result.isConfirmed) return;
                $.ajax({
                    url: "<?= BASE_URL('ajaxs/admin/ticket.php'); ?>",
                    type: "POST",
                    dataType: "json",
                    data: {
                        action: 'recallAdminMessage',
                        message_id: messageId,
                        ticket_id: state.selectedTicketId
                    },
                    success: function(resp) {
                        if (resp && resp.status === 'success') {
                            $conversationTimeline.find(`[data-message-id="${messageId}"]`).remove();
                            if (typeof showMessage === 'function') {
                                showMessage(resp.msg || TEXTS.recallSuccess, 'success');
                            }
                            if (typeof window.refreshTicketsOverview === 'function') {
                                window.refreshTicketsOverview();
                            }
                        } else {
                            if (typeof showMessage === 'function') {
                                showMessage((resp && resp.msg) || TEXTS.recallError, 'error');
                            }
                        }
                    },
                    error: function() {
                        if (typeof showMessage === 'function') {
                            showMessage(TEXTS.recallError, 'error');
                        }
                    }
                });
            });
        });

        $(document).on('click', function(e) {
            if (!$(e.target).closest('#messageContextMenu').length) {
                hideMessageContextMenu();
            }
        });

        $(window).on('scroll resize', hideMessageContextMenu);
        $('#main-chat-content').on('scroll', hideMessageContextMenu);

        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                hideMessageContextMenu();
            }
        });

        $ticketsList.on('mouseenter', '.ticket-item', function() {
            $(this).closest('li').addClass('hover');
        }).on('mouseleave', '.ticket-item', function() {
            $(this).closest('li').removeClass('hover');
        });

        // Thực hiện lưu admin note (debounce) sau khi người dùng nhập
        function commitAdminNoteSave(noteValue) {
            if (!CAN_EDIT_STATUS || !$adminNoteTextarea.length || !$adminNoteTextarea.is(':visible')) return;
            if (!state.selectedTicketId) return;
            if (state.adminNoteSaving) return;

            const note = typeof noteValue === 'string' ? noteValue : $adminNoteTextarea.val();
            if (note === state.adminNoteCurrent) return;

            state.adminNoteSaving = true;
            if ($adminNoteError.length) {
                $adminNoteError.addClass('d-none');
            }
            if ($adminNoteStatus.length) {
                $adminNoteStatus.addClass('d-none');
            }
            if ($adminNoteSaving.length) {
                $adminNoteSaving.removeClass('d-none');
            }

            $.ajax({
                url: "<?= BASE_URL('ajaxs/admin/ticket.php'); ?>",
                type: "POST",
                dataType: "json",
                data: {
                    action: 'saveAdminNote',
                    ticket_id: state.selectedTicketId,
                    admin_note: note
                },
                success: function(resp) {
                    if (resp && resp.status === 'success') {
                        state.adminNoteCurrent = note;
                        if ($adminNoteStatus.length) {
                            $adminNoteStatus.removeClass('d-none').text(TEXTS.noteSaved);
                            setTimeout(() => $adminNoteStatus.addClass('d-none'), 2000);
                        }
                    } else {
                        if ($adminNoteError.length) {
                            $adminNoteError.removeClass('d-none').text((resp && resp.msg) || TEXTS.noteSaveError);
                            setTimeout(() => $adminNoteError.addClass('d-none'), 2500);
                        }
                    }
                },
                error: function() {
                    if ($adminNoteError.length) {
                        $adminNoteError.removeClass('d-none').text(TEXTS.noteSaveError);
                        setTimeout(() => $adminNoteError.addClass('d-none'), 2500);
                    }
                },
                complete: function() {
                    state.adminNoteSaving = false;
                    if ($adminNoteSaving.length) {
                        $adminNoteSaving.addClass('d-none');
                    }
                }
            });
        }

        // Đặt timer để lưu admin note sau khi người dùng ngừng gõ
        function queueAdminNoteSave() {
            if (!CAN_EDIT_STATUS || !$adminNoteTextarea.length) return;
            if (state.adminNoteTimer) {
                clearTimeout(state.adminNoteTimer);
            }
            state.adminNoteTimer = setTimeout(function() {
                state.adminNoteTimer = null;
                commitAdminNoteSave();
            }, 800);
        }

        if ($adminNoteTextarea.length && CAN_EDIT_STATUS) {
            $adminNoteTextarea.on('input', function() {
                queueAdminNoteSave();
            }).on('blur', function() {
                if (state.adminNoteTimer) {
                    clearTimeout(state.adminNoteTimer);
                    state.adminNoteTimer = null;
                }
                commitAdminNoteSave();
            });
        }

        // Sự kiện chọn quick reply trong gợi ý bằng chuột
        if ($quickRepliesSuggestion.length) {
            $quickRepliesSuggestion.on('mousedown', '.quick-reply-item', function(e) {
                e.preventDefault();
                const id = $(this).data('id');
                const item = state.quickRepliesSuggestionItems.find(q => String(q.id) === String(id));
                if (item) {
                    applyQuickReply(item);
                }
            }).on('mouseenter', '.quick-reply-item', function() {
                const index = $(this).index();
                state.quickRepliesHighlightIndex = index;
                $(this).addClass('active').siblings().removeClass('active');
            });
        }

        // Nút mở modal quản lý quick reply
        if ($openQuickRepliesManager.length && quickRepliesModalInstance) {
            $openQuickRepliesManager.on('click', function() {
                ensureQuickRepliesLoaded(() => {
                    renderQuickRepliesList();
                    resetQuickReplyFormFields();
                });
                quickRepliesModalInstance.show();
            });
        }

        // Khi bấm "Thêm câu trả lời" trong modal -> reset form và focus vào lệnh
        if ($addQuickReplyBtn.length) {
            $addQuickReplyBtn.on('click', function() {
                resetQuickReplyFormFields();
                $quickReplyCommand.trigger('focus');
            });
        }

        // Khi bấm "Hủy" trong modal -> reset form
        if ($resetQuickReplyForm.length) {
            $resetQuickReplyForm.on('click', function() {
                resetQuickReplyFormFields();
            });
        }

        // Xử lý submit form (tạo/cập nhật) quick reply
        if ($quickRepliesForm.length) {
            $quickRepliesForm.on('submit', function(e) {
                e.preventDefault();
                if (state.quickRepliesLoading) return;
                let command = $quickReplyCommand.val().trim();
                const content = $quickReplyContent.val().trim();
                if (!command || !content) {
                    Swal.fire({
                        icon: 'warning',
                        title: TEXTS.error,
                        text: <?= json_encode(__('Vui lòng nhập đầy đủ lệnh và nội dung.')); ?>
                    });
                    return;
                }
                if (command.startsWith('/')) {
                    command = command.substring(1);
                }
                if (!/^[a-zA-Z0-9_\-]+$/.test(command)) {
                    Swal.fire({
                        icon: 'warning',
                        title: TEXTS.error,
                        text: <?= json_encode(__('Lệnh chỉ được phép chứa chữ, số, dấu gạch dưới hoặc gạch ngang.')); ?>
                    });
                    return;
                }
                const mode = $quickRepliesForm.data('mode') === 'edit' ? 'edit' : 'create';
                const action = mode === 'edit' ? 'updateQuickReply' : 'createQuickReply';
                const id = $quickReplyId.val();
                state.quickRepliesLoading = true;
                $quickReplySubmitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span><?= __('Đang xử lý...'); ?>');
                $.ajax({
                    url: "<?= BASE_URL('ajaxs/admin/ticket.php'); ?>",
                    type: "POST",
                    dataType: "json",
                    data: {
                        action: action,
                        id: id,
                        command: command,
                        content: content
                    },
                    success: function(resp) {
                        if (resp && resp.status === 'success' && resp.data) {
                            if (mode === 'edit') {
                                const idx = state.quickReplies.findIndex(item => String(item.id) === String(resp.data.id));
                                if (idx !== -1) {
                                    state.quickReplies[idx] = resp.data;
                                }
                            } else {
                                state.quickReplies.push(resp.data);
                            }
                            state.quickReplies = state.quickReplies.sort((a, b) => a.command.localeCompare(b.command));
                            renderQuickRepliesList();
                            handleQuickRepliesSuggestion();
                            resetQuickReplyFormFields();
                            Swal.fire({
                                icon: 'success',
                                title: TEXTS.success,
                                text: resp.msg || <?= json_encode(__('Lưu câu trả lời nhanh thành công.')); ?>,
                                timer: 1500,
                                showConfirmButton: false
                            });
                        } else if (resp && resp.msg) {
                            Swal.fire({
                                icon: 'error',
                                title: TEXTS.error,
                                text: resp.msg
                            });
                        }
                    },
                    complete: function() {
                        state.quickRepliesLoading = false;
                        $quickReplySubmitBtn.prop('disabled', false).html('<i class="ri-save-3-line me-1"></i><?= __('Lưu câu trả lời'); ?>');
                    }
                });
            });
        }

        // Bắt sự kiện click nút chỉnh sửa quick reply
        $(document).on('click', '.edit-quick-reply', function() {
            const id = $(this).data('id');
            const item = state.quickReplies.find(r => String(r.id) === String(id));
            if (item) {
                setQuickReplyForm(item);
            }
        });

        // Bắt sự kiện click nút xóa quick reply
        $(document).on('click', '.delete-quick-reply', function() {
            const id = $(this).data('id');
            if (!id) return;
            Swal.fire({
                icon: 'warning',
                title: <?= json_encode(__('Xác nhận xóa')); ?>,
                text: <?= json_encode(__('Bạn có chắc chắn muốn xóa câu trả lời nhanh này?')); ?>,
                showCancelButton: true,
                confirmButtonText: <?= json_encode(__('Xóa')); ?>,
                cancelButtonText: <?= json_encode(__('Hủy')); ?>
            }).then(result => {
                if (!result.isConfirmed) return;
                $.ajax({
                    url: "<?= BASE_URL('ajaxs/admin/ticket.php'); ?>",
                    type: "POST",
                    dataType: "json",
                    data: {
                        action: 'deleteQuickReply',
                        id: id
                    },
                    success: function(resp) {
                        if (resp && resp.status === 'success') {
                            state.quickReplies = state.quickReplies.filter(item => String(item.id) !== String(id));
                            renderQuickRepliesList();
                            hideQuickRepliesSuggestion();
                            Swal.fire({
                                icon: 'success',
                                title: TEXTS.success,
                                text: resp.msg || <?= json_encode(__('Đã xóa thành công.')); ?>,
                                timer: 1500,
                                showConfirmButton: false
                            });
                        } else if (resp && resp.msg) {
                            Swal.fire({
                                icon: 'error',
                                title: TEXTS.error,
                                text: resp.msg
                            });
                        }
                    }
                });
            });
        });

        // Khi modal quick reply mở -> đảm bảo dữ liệu mới nhất và focus input lệnh
        if ($quickRepliesModal.length) {
            $quickRepliesModal.on('shown.bs.modal', function() {
                ensureQuickRepliesLoaded(() => {
                    renderQuickRepliesList();
                });
                $quickReplyCommand.trigger('focus');
            });
        }

        // Điều khiển panel emoji
        if ($emojiBtn.length && $emojiPanel.length) {
            $emojiBtn.on('click', function(e) {
                if ($emojiBtn.prop('disabled')) return;
                e.stopPropagation();
                $emojiPanel.toggleClass('active');
            });

            $emojiPanel.on('click', function(e) {
                e.stopPropagation();
            });

            if ($emojiGrid.length) {
                $emojiGrid.on('click', 'button[data-emoji]', function() {
                    if ($replyMessage.prop('disabled')) return;
                    const emoji = $(this).data('emoji');
                    const input = $replyMessage.get(0);
                    if (!input) return;
                    const start = input.selectionStart || input.value.length;
                    const end = input.selectionEnd || input.value.length;
                    const current = $replyMessage.val();
                    const nextValue = current.slice(0, start) + emoji + current.slice(end);
                    $replyMessage.val(nextValue);
                    const newPos = start + emoji.length;
                    if (typeof input.setSelectionRange === 'function') {
                        input.setSelectionRange(newPos, newPos);
                    }
                    closeEmojiPanel();
                    $replyMessage.trigger('input').focus();
                });
            }
        }

        // Đóng emoji/quick replies khi click ra ngoài
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.emoji-dropdown').length) {
                closeEmojiPanel();
            }
            if (!$(e.target).closest('.quick-reply-input-wrapper').length) {
                hideQuickRepliesSuggestion();
            }
        });

        // Khởi động lần đầu: load quick replies + danh sách ticket và thiết lập auto-refresh
        state.translateLangCache = getStoredTranslateLang();
        ensureQuickRepliesLoaded();

        fetchTickets();
        state.autoRefreshTimer = setInterval(() => fetchTickets({
            quiet: true
        }), refreshInterval);
        window.refreshTicketsOverview = () => fetchTickets({
            quiet: true
        });

    });
</script>