<?php if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

// Xử lý lưu template Telegram
if (isset($_POST['SaveSettings'])) {
    if (checkPermission($getUser['admin'], 'edit_telegram_template') != true) {
        die('<script type="text/javascript">if(!alert("' . __('Bạn không có quyền sử dụng tính năng này') . '")){window.history.back();}</script>');
    }
    checkCSRF();

    if ($CMSNT->site('status_demo') != 0) {
        die('<script type="text/javascript">if(!alert("' . __('This function cannot be used because this is a demo site') . '")){window.history.back().location.reload();}</script>');
    }
    $CMSNT->insert("logs", [
        'user_id'       => $getUser['id'],
        'ip'            => myip(),
        'device'        => getUserAgent(),
        'createdate'    => gettime(),
        'action'        => __('Thay đổi template Telegram')
    ]);

    foreach ($_POST as $key => $value) {
        $CMSNT->update("settings", array(
            'value' => $value
        ), " `name` = '$key' ");
    }

    $my_text = $CMSNT->site('noti_action');
    $my_text = str_replace('{domain}', $_SERVER['SERVER_NAME'], $my_text);
    $my_text = str_replace('{username}', $getUser['username'], $my_text);
    $my_text = str_replace('{action}', __('Thay đổi template Telegram'), $my_text);
    $my_text = str_replace('{ip}', myip(), $my_text);
    $my_text = str_replace('{time}', gettime(), $my_text);
    sendMessAdmin($my_text);

    admin_msg_success("Lưu thành công!", "", 1000);
}

// Define telegram templates configuration
$telegram_templates = [
    [
        'id' => 'noti_order_success_admin',
        'icon' => 'fa fa-shopping-cart',
        'color' => 'success',
        'title' => __('Thông báo đơn hàng mới cho') . ' <span class="text-warning">Admin</span>',
        'setting_key' => 'noti_order_success_admin',
        'variables' => '{domain}, {username}, {order_count}, {total_amount}, {discount_amount}, {coupon_code}, {order_ids}, {order_details}, {new_balance}, {ip}, {time}',
        'default_content' => '🛒 *ĐƠN HÀNG MỚI*

👤 *Khách hàng:* {username}
📦 *Số đơn:* {order_count}
💰 *Tổng tiền:* {total_amount}
🎁 *Giảm giá:* {discount_amount}
💳 *Số dư còn:* {new_balance}
📋 *Mã đơn:* {order_ids}

🌐 {domain}
🕐 {time} | 📍 {ip}'
    ],
    [
        'id' => 'noti_order_success_user',
        'icon' => 'fa fa-check-circle',
        'color' => 'info',
        'title' => __('Thông báo mua hàng thành công cho') . ' <span class="text-warning">User</span>',
        'setting_key' => 'noti_order_success_user',
        'variables' => '{domain}, {username}, {order_count}, {total_amount}, {discount_amount}, {coupon_code}, {order_ids}, {new_balance}, {ip}, {time}',
        'default_content' => '✅ *MUA HÀNG THÀNH CÔNG!*

Xin chào *{username}*! 🎉
Cảm ơn bạn đã mua hàng tại {domain}

📦 *Số đơn:* {order_count}
💰 *Tổng tiền:* {total_amount}
💳 *Số dư còn:* {new_balance}
📋 *Mã đơn:* {order_ids}

🕐 {time}
Cảm ơn bạn đã tin tưởng! 💖'
    ],
    [
        'id' => 'noti_pending_order_admin',
        'icon' => 'fa fa-clock',
        'color' => 'warning',
        'title' => __('Thông báo đơn hàng ORDER cần xử lý cho') . ' <span class="text-warning">Admin</span>',
        'setting_key' => 'noti_pending_order_admin',
        'variables' => '{domain}, {username}, {order_count}, {total_amount}, {order_ids}, {order_details}, {ip}, {time}',
        'default_content' => '⏳ *ĐƠN HÀNG ORDER MỚI*

👤 *Khách hàng:* {username}
📦 *Số đơn ORDER:* {order_count}
💰 *Tổng tiền:* {total_amount}
📋 *Mã đơn:* {order_ids}

📝 *Chi tiết:*
{order_details}

⚠️ Vui lòng xử lý đơn hàng!
🌐 {domain}
🕐 {time} | 📍 {ip}'
    ],
    [
        'id' => 'noti_api_out_of_money',
        'icon' => 'fa fa-exclamation-triangle',
        'color' => 'danger',
        'title' => __('Thông báo API hết tiền cho') . ' <span class="text-warning">Admin</span>',
        'setting_key' => 'noti_api_out_of_money',
        'variables' => '{domain}, {username}, {supplier_name}, {product_name}, {product_id}, {plan_id}, {pay}, {amount}, {ip}, {time}, {http_code}',
        'default_content' => '🚨 *CẢNH BÁO: API HẾT TIỀN!*

⚠️ Nhà cung cấp *{supplier_name}* đã hết tiền!

👤 *Khách hàng:* {username}
📦 *Sản phẩm:* {product_name}
💰 *Số tiền:* {pay}
🔴 *HTTP Code:* {http_code}

⏰ Vui lòng nạp tiền API ngay!
🕐 {time} | 📍 {ip}'
    ],
    [
        'id' => 'noti_api_connection_error',
        'icon' => 'fa fa-plug',
        'color' => 'danger',
        'title' => __('Thông báo lỗi kết nối API cho') . ' <span class="text-warning">Admin</span>',
        'setting_key' => 'noti_api_connection_error',
        'variables' => '{domain}, {username}, {supplier_name}, {product_name}, {product_id}, {plan_id}, {pay}, {amount}, {ip}, {time}, {http_code}',
        'default_content' => '🔴 *LỖI KẾT NỐI API!*

⚠️ Không thể kết nối đến *{supplier_name}*

👤 *Khách hàng:* {username}
📦 *Sản phẩm:* {product_name}
💰 *Số tiền:* {pay}
🔴 *HTTP Code:* {http_code}

🔧 Vui lòng kiểm tra trạng thái nhà cung cấp!
🕐 {time} | 📍 {ip}'
    ],
    [
        'id' => 'noti_recharge',
        'icon' => 'fa fa-credit-card',
        'color' => 'warning',
        'title' => __('Thông báo nạp tiền cho') . ' <span class="text-danger">Admin</span>',
        'setting_key' => 'noti_recharge',
        'variables' => '{domain}, {username}, {trans_id}, {method}, {amount}, {price}, {time}',
        'default_content' => '💳 *NẠP TIỀN THÀNH CÔNG*

👤 *Khách hàng:* {username}
🆔 *Mã giao dịch:* {trans_id}
💰 *Số tiền:* {amount}
💵 *Thực nhận:* {price}
🏦 *Phương thức:* {method}

🕐 {time}
🌐 {domain}'
    ],
    [
        'id' => 'noti_action',
        'icon' => 'fa fa-tasks',
        'color' => 'info',
        'title' => __('Thông báo hành động cho') . ' <span class="text-danger">Admin</span>',
        'setting_key' => 'noti_action',
        'variables' => '{domain}, {username}, {action}, {ip}, {time}',
        'default_content' => '📝 *HÀNH ĐỘNG HỆ THỐNG*

👤 *Người dùng:* {username}
🔧 *Hành động:* {action}

🌐 {domain}
🕐 {time} | 📍 {ip}'
    ],
    [
        'id' => 'noti_affiliate_withdraw',
        'icon' => 'fa fa-money-bill-wave',
        'color' => 'info',
        'title' => __('Thông báo rút số dư hoa hồng cho') . ' <span class="text-danger">Admin</span>',
        'setting_key' => 'noti_affiliate_withdraw',
        'variables' => '{domain}, {username}, {bank}, {account_number}, {account_name}, {amount}, {ip}, {time}',
        'default_content' => '💸 *YÊU CẦU RÚT HOA HỒNG*

👤 *Người dùng:* {username}
💰 *Số tiền:* {amount}

🏦 *Thông tin ngân hàng:*
• Ngân hàng: {bank}
• Số TK: {account_number}
• Chủ TK: {account_name}

🌐 {domain}
🕐 {time} | 📍 {ip}'
    ],
    [
        'id' => 'support_tickets_telegram_message',
        'icon' => 'fa fa-ticket-alt',
        'color' => 'secondary',
        'title' => __('Thông báo khi có ticket mới cho') . ' <span class="text-danger">Admin</span>',
        'setting_key' => 'support_tickets_telegram_message',
        'variables' => '{domain}, {username}, {subject}, {content}, {status}, {category}, {quantity}, {ip}, {time}, {device}',
        'default_content' => '🎫 *TICKET MỚI*

👤 *Khách hàng:* {username}
📋 *Tiêu đề:* {subject}
📁 *Danh mục:* {category}

💬 *Nội dung:*
{content}

🌐 {domain}
🕐 {time} | 📍 {ip}'
    ],
    [
        'id' => 'support_tickets_telegram_message_reply',
        'icon' => 'fa fa-reply',
        'color' => 'secondary',
        'title' => __('Thông báo khi User trả lời ticket cho') . ' <span class="text-danger">Admin</span>',
        'setting_key' => 'support_tickets_telegram_message_reply',
        'variables' => '{domain}, {username}, {subject}, {message}, {category}, {ip}, {time}, {device}',
        'default_content' => '💬 *PHẢN HỒI TICKET*

👤 *Khách hàng:* {username}
📋 *Tiêu đề:* {subject}
📁 *Danh mục:* {category}

💬 *Nội dung:*
{message}

🕐 {time} | 📍 {ip}'
    ],
    [
        'id' => 'telegram_noti_login_user',
        'icon' => 'fa fa-sign-in-alt',
        'color' => 'secondary',
        'title' => __('Thông báo đăng nhập cho') . ' <span class="text-info">User</span>',
        'setting_key' => 'telegram_noti_login_user',
        'variables' => '{domain}, {username}, {ip}, {device}, {time}',
        'default_content' => '🔐 *ĐĂNG NHẬP MỚI*

Xin chào *{username}*!
Tài khoản của bạn vừa đăng nhập thành công.

📍 *IP:* {ip}
📱 *Thiết bị:* {device}
🕐 *Thời gian:* {time}

🌐 {domain}
Nếu không phải bạn, hãy đổi mật khẩu ngay! ⚠️'
    ],
    [
        'id' => 'noti_user_admin_reply_ticket',
        'icon' => 'fa fa-reply',
        'color' => 'secondary',
        'title' => __('Thông báo khi Admin reply ticket cho') . ' <span class="text-info">User</span>',
        'setting_key' => 'noti_user_admin_reply_ticket',
        'variables' => '{username}, {subject}, {message}, {time}',
        'default_content' => '📩 *ADMIN ĐÃ TRẢ LỜI TICKET*

Xin chào *{username}*!
Ticket *"{subject}"* đã có phản hồi mới:

💬 {message}

🕐 {time}'
    ],
    [
        'id' => 'noti_new_review',
        'icon' => 'fa fa-star',
        'color' => 'primary',
        'title' => __('Thông báo đánh giá sản phẩm mới cho') . ' <span class="text-warning">Admin</span>',
        'setting_key' => 'noti_new_review',
        'variables' => '{domain}, {username}, {product_name}, {rating}, {stars}, {title}, {content}, {time}',
        'default_content' => '⭐ *ĐÁNH GIÁ SẢN PHẨM MỚI*

👤 *Người đánh giá:* {username}
📦 *Sản phẩm:* {product_name}
⭐ *Đánh giá:* {stars} ({rating}/5)

📝 *Tiêu đề:* {title}
💬 *Nội dung:* {content}

🌐 {domain}
🕐 {time}'
    ]
];
?>
<div class="tab-pane text-muted show active" id="telegram-template" role="tabpanel">
    <h4>Nội dung thông báo Telegram</h4>
    <form action="" method="POST">
        <?= csrfField(); ?>
        <div class="row push mb-3">
            <div class="col-md-12">
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <div class="alert alert-info">
                            <strong><?= __('Lưu ý:'); ?></strong>
                            <?= __('Để mặc định nếu bạn không có nhu cầu tùy chỉnh. Xóa toàn bộ nội dung trong ô nếu không muốn bật thông báo.'); ?>
                        </div>
                    </div>

                    <?php foreach ($telegram_templates as $template): ?>
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-header bg-<?= $template['color']; ?> text-white d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">
                                        <i class="<?= $template['icon']; ?> me-2"></i>
                                        <?= $template['title']; ?>
                                    </h6>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-light btn-default-telegram"
                                            data-target="#<?= $template['setting_key']; ?>"
                                            data-default="<?= htmlspecialchars($template['default_content'], ENT_QUOTES); ?>"
                                            title="<?= __('Sử dụng mẫu mặc định'); ?>">
                                            <i class="ri-file-copy-line"></i>
                                        </button>
                                        <button type="button" class="btn btn-dark btn-ai-telegram"
                                            data-type="<?= $template['setting_key']; ?>"
                                            data-target="#<?= $template['setting_key']; ?>"
                                            title="<?= __('Tạo nội dung bằng AI'); ?>">
                                            <i class="ri-magic-line me-1"></i>AI
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <textarea class="form-control mb-2" rows="4"
                                        name="<?= $template['setting_key']; ?>"
                                        id="<?= $template['setting_key']; ?>"
                                        placeholder="<?= __('Nhập nội dung thông báo...'); ?>"><?= $CMSNT->site($template['setting_key']); ?></textarea>
                                    <small class="text-muted">
                                        <strong><?= __('Biến sử dụng:'); ?></strong>
                                        <?= $template['variables']; ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                </div>
            </div>
        </div>
        <button type="submit" name="SaveSettings" class="btn btn-primary w-100 mb-3">
            <i class="fa fa-fw fa-save me-1"></i> <?= __('Save'); ?>
        </button>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // AI button handler
        $('.btn-ai-telegram').click(function() {
            var btn = $(this);
            var type = btn.data('type');
            var target = btn.data('target');
            var targetElement = $(target);

            var originalContent = btn.html();
            btn.html('<i class="fa fa-spinner fa-spin"></i>').prop('disabled', true);

            $.ajax({
                url: '<?= base_url('ajaxs/admin/ai.php'); ?>',
                method: 'POST',
                dataType: 'JSON',
                data: {
                    action: 'generateTelegramNotification',
                    type: type
                },
                success: function(response) {
                    if (response.success) {
                        targetElement.val(response.content);
                        showMessage('<?= __('Đã tạo nội dung bằng AI'); ?>', 'success');
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: '<?= __('Có lỗi xảy ra'); ?>',
                            text: response.message || '<?= __('Không thể tạo nội dung'); ?>'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: '<?= __('Lỗi kết nối'); ?>',
                        text: '<?= __('Không thể kết nối đến server AI'); ?>'
                    });
                },
                complete: function() {
                    btn.html(originalContent).prop('disabled', false);
                }
            });
        });

        // Default Template button handler - NO AJAX, directly from data attribute
        $('.btn-default-telegram').click(function() {
            var btn = $(this);
            var target = btn.data('target');
            var defaultContent = btn.data('default');

            $(target).val(defaultContent);
            showMessage('<?= __('Đã áp dụng mẫu mặc định'); ?>', 'success');
        });
    });
</script>