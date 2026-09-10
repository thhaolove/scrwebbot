<?php if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

// Xử lý lưu cài đặt kết nối
if (isset($_POST['SaveSettings'])) {
    // Kiểm tra quyền
    if (checkPermission($getUser['admin'], 'edit_connection') != true) {
        die('<script type="text/javascript">if(!alert("' . __('Bạn không có quyền sử dụng tính năng này') . '")){window.history.back();}</script>');
    }
    // Kiểm tra CSRF token
    checkCSRF();

    if ($CMSNT->site('status_demo') != 0) {
        die('<script type="text/javascript">if(!alert("' . __('This function cannot be used because this is a demo site') . '")){window.history.back().location.reload();}</script>');
    }
    $CMSNT->insert("logs", [
        'user_id'       => $getUser['id'],
        'ip'            => myip(),
        'device'        => getUserAgent(),
        'createdate'    => gettime(),
        'action'        => __('Thay đổi cài đặt kết nối')
    ]);

    foreach ($_POST as $key => $value) {
        $CMSNT->update("settings", array(
            'value' => $value
        ), " `name` = '$key' ");
    }

    $my_text = $CMSNT->site('noti_action');
    $my_text = str_replace('{domain}', $_SERVER['SERVER_NAME'], $my_text);
    $my_text = str_replace('{username}', $getUser['username'], $my_text);
    $my_text = str_replace('{action}', __('Thay đổi cài đặt kết nối'), $my_text);
    $my_text = str_replace('{ip}', myip(), $my_text);
    $my_text = str_replace('{time}', gettime(), $my_text);
    sendMessAdmin($my_text);

    admin_msg_success("Lưu thành công!", "", 1000);
}
?>
<div class="tab-pane text-muted show active" id="ket-noi" role="tabpanel">
    <h4><?= __('Kết nối'); ?></h4>
    <form action="" method="POST" class="settings-form">
        <?= csrfField(); ?>
        <div class="row g-4">
            <div class="col-lg-6">
                <!-- SMTP Configuration -->
                <div class="card custom-card border-0 shadow-sm">
                    <div class="card-header bg-primary-gradient border-bottom-0">
                        <div
                            class="card-title text-white mb-0 d-flex align-items-center">
                            <div class="avatar avatar-sm bg-white-transparent me-2">
                                <img src="<?= BASE_URL('assets/img/icon-smtp.png'); ?>"
                                    alt="SMTP" class="w-100 h-100">
                            </div>
                            <span
                                class="fw-semibold fs-15"><?= __('Cấu hình SMTP'); ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- SMTP Status -->
                        <div class="mb-4">
                            <label
                                class="form-label fw-medium d-flex align-items-center">
                                <i class="ri-toggle-line me-2 text-success"></i>
                                <?= __('Trạng thái SMTP'); ?>
                            </label>
                            <select class="form-select" name="smtp_status">
                                <option value="1"
                                    <?= $CMSNT->site('smtp_status') == 1 ? 'selected' : ''; ?>>
                                    <i class="ri-check-line"></i> <?= __('Bật'); ?>
                                </option>
                                <option value="0"
                                    <?= $CMSNT->site('smtp_status') == 0 ? 'selected' : ''; ?>>
                                    <i class="ri-close-line"></i> <?= __('Tắt'); ?>
                                </option>
                            </select>
                        </div>

                        <!-- SMTP Host -->
                        <div class="mb-3">
                            <label
                                class="form-label fw-medium d-flex align-items-center">
                                <i class="ri-server-line me-2 text-primary"></i>
                                <?= __('SMTP Host'); ?>
                            </label>
                            <input type="text" name="smtp_host" class="form-control"
                                placeholder="<?= __('VD: smtp.gmail.com'); ?>"
                                value="<?= $CMSNT->site('smtp_host'); ?>">
                        </div>

                        <!-- SMTP Encryption & Port -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label
                                        class="form-label fw-medium d-flex align-items-center">
                                        <i
                                            class="ri-shield-line me-2 text-warning"></i>
                                        <?= __('Mã hóa'); ?>
                                    </label>
                                    <input type="text" name="smtp_encryption"
                                        class="form-control"
                                        placeholder="<?= __('ssl/tls'); ?>"
                                        value="<?= $CMSNT->site('smtp_encryption'); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label
                                        class="form-label fw-medium d-flex align-items-center">
                                        <i class="ri-plug-line me-2 text-info"></i>
                                        <?= __('Cổng'); ?>
                                    </label>
                                    <input type="text" name="smtp_port"
                                        class="form-control"
                                        placeholder="<?= __('465, 587'); ?>"
                                        value="<?= $CMSNT->site('smtp_port'); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- SMTP Email -->
                        <div class="mb-3">
                            <label
                                class="form-label fw-medium d-flex align-items-center">
                                <i class="ri-mail-line me-2 text-danger"></i>
                                <?= __('Email SMTP'); ?>
                            </label>
                            <input type="email" name="smtp_email"
                                class="form-control"
                                placeholder="<?= __('VD: yourmail@gmail.com'); ?>"
                                value="<?= $CMSNT->site('smtp_email'); ?>">
                        </div>

                        <!-- SMTP Password -->
                        <div class="mb-3">
                            <label
                                class="form-label fw-medium d-flex align-items-center">
                                <i class="ri-key-line me-2 text-secondary"></i>
                                <?= __('Mật khẩu SMTP'); ?>
                            </label>
                            <input type="password" name="smtp_password"
                                class="form-control"
                                placeholder="<?= __('Nhập mật khẩu SMTP...'); ?>"
                                value="<?= $CMSNT->site('smtp_password'); ?>">
                        </div>
                        <!-- Help Link -->
                        <div class="alert alert-primary-transparent border-0">
                            <div class="d-flex align-items-center">
                                <div class="me-2">
                                    <i class="ri-information-line fs-16"></i>
                                </div>
                                <div>
                                    <strong><?= __('Cần trợ giúp?'); ?></strong><br>
                                    <a href="https://help.cmsnt.co/huong-dan/huong-dan-cau-hinh-smtp-vao-website/"
                                        target="_blank"
                                        class="text-primary fw-medium">
                                        <i
                                            class="ri-external-link-line me-1"></i><?= __('Xem hướng dẫn chi tiết tích hợp SMTP'); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Telegram Bot Configuration -->
                <div class="card custom-card border-0 shadow-sm mb-4">
                    <div class="card-header bg-secondary-gradient border-bottom-0">
                        <div
                            class="card-title text-white mb-0 d-flex align-items-center">
                            <div class="avatar avatar-sm bg-white-transparent me-2">
                                <img src="<?= BASE_URL('assets/img/icon-bot-telegram.avif'); ?>"
                                    alt="Telegram" class="w-100 h-100">
                            </div>
                            <span
                                class="fw-semibold fs-15"><?= __('Bot thông báo Telegram'); ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Telegram Status -->
                        <div class="mb-3">
                            <label
                                class="form-label fw-medium d-flex align-items-center">
                                <i class="ri-robot-line me-2 text-success"></i>
                                <?= __('Trạng thái Bot'); ?>
                            </label>
                            <select class="form-select" name="telegram_status">
                                <option
                                    <?= $CMSNT->site('telegram_status') == 1 ? 'selected' : ''; ?>
                                    value="1">
                                    <?= __('Bật'); ?>
                                </option>
                                <option
                                    <?= $CMSNT->site('telegram_status') == 0 ? 'selected' : ''; ?>
                                    value="0">
                                    <?= __('Tắt'); ?>
                                </option>
                            </select>
                        </div>

                        <!-- Bot Token -->
                        <div class="mb-3">
                            <label
                                class="form-label fw-medium d-flex align-items-center">
                                <i class="ri-key-line me-2 text-warning"></i>
                                <?= __('Bot Token'); ?>
                            </label>
                            <input type="password" name="telegram_token"
                                value="<?= $CMSNT->site('telegram_token'); ?>"
                                class="form-control"
                                placeholder="123456789:XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX">
                            <div class="form-text">
                                <a class="text-primary fw-medium"
                                    href="https://help.cmsnt.co/huong-dan/huong-dan-tich-hop-bot-telegram-vao-shopclone7/"
                                    target="_blank">
                                    <i
                                        class="ri-external-link-line me-1"></i><?= __('Xem hướng dẫn tạo bot'); ?>
                                </a>
                            </div>
                        </div>

                        <!-- Chat ID & Bot Username -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label
                                        class="form-label fw-medium d-flex align-items-center">
                                        <i
                                            class="ri-chat-3-line me-2 text-info"></i>
                                        <?= __('Chat ID'); ?>
                                    </label>
                                    <input type="text" name="telegram_chat_id"
                                        value="<?= $CMSNT->site('telegram_chat_id'); ?>"
                                        class="form-control"
                                        placeholder="-100XXXXXXXXX">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label
                                        class="form-label fw-medium d-flex align-items-center">
                                        <i
                                            class="ri-user-line me-2 text-primary"></i>
                                        <?= __('Bot Username'); ?>
                                    </label>
                                    <input type="text" name="telegram_bot_username"
                                        value="<?= $CMSNT->site('telegram_bot_username'); ?>"
                                        class="form-control"
                                        placeholder="@your_bot_name">
                                </div>
                            </div>
                        </div>

                        <!-- Webhook Secret -->
                        <div class="mb-3">
                            <label
                                class="form-label fw-medium d-flex align-items-center">
                                <i
                                    class="ri-shield-keyhole-line me-2 text-danger"></i>
                                <?= __('Webhook Secret'); ?>
                            </label>
                            <div class="input-group">
                                <input type="password"
                                    name="telegram_webhook_secret"
                                    id="telegram_webhook_secret"
                                    value="<?= $CMSNT->site('telegram_webhook_secret'); ?>"
                                    class="form-control"
                                    placeholder="<?= __('Để trống sẽ tự động tạo khi set webhook'); ?>">
                                <button type="button" class="btn btn-success-light"
                                    id="btn_set_webhook">
                                    <i class="ri-settings-line me-1"
                                        id="webhook_icon"></i>
                                    <i class="ri-loader-line spin"
                                        id="webhook_loading"
                                        style="display: none;"></i>
                                    <?= __('Set Webhook'); ?>
                                </button>
                            </div>
                            <div class="form-text text-danger">
                                <i class="ri-alert-line me-1"></i>
                                <?= __('Bảo mật quan trọng - không chia sẻ cho ai'); ?>
                            </div>
                            <div id="webhook_result" class="mt-2"></div>
                        </div>

                        <!-- Telegram API URL -->
                        <div class="mb-3">
                            <label
                                class="form-label fw-medium d-flex align-items-center">
                                <i class="ri-global-line me-2 text-secondary"></i>
                                <?= __('API Server'); ?>
                            </label>
                            <select class="form-select" name="telegram_url">
                                <option value="https://api.telegram.org/"
                                    <?= $CMSNT->site('telegram_url') == 'https://api.telegram.org/' ? 'selected' : ''; ?>>
                                    🌐 Official Telegram API
                                </option>
                                <option
                                    value="https://bypass-telegram.cmsnt.workers.dev/"
                                    <?= $CMSNT->site('telegram_url') == 'https://bypass-telegram.cmsnt.workers.dev/' ? 'selected' : ''; ?>>
                                    🚀 CMSNT Proxy Server
                                </option>
                            </select>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                <?= __('Sử dụng proxy nếu Telegram bị chặn tại Việt Nam'); ?>
                            </div>
                        </div>
                        <!-- Help Link -->
                        <div class="alert alert-primary-transparent border-0">
                            <div class="d-flex align-items-center">
                                <div class="me-2">
                                    <i class="ri-information-line fs-16"></i>
                                </div>
                                <div>
                                    <strong><?= __('Cần trợ giúp?'); ?></strong><br>
                                    <a href="https://help.cmsnt.co/huong-dan/smmpanel2-huong-dan-tich-hop-bot-telegram/"
                                        target="_blank"
                                        class="text-primary fw-medium">
                                        <i
                                            class="ri-external-link-line me-1"></i><?= __('Xem hướng dẫn chi tiết tích hợp Bot Telegram'); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Second Column -->
            <div class="col-lg-6">
                <!-- Google Analytics -->
                <div class="card custom-card border-0 shadow-sm mb-4">
                    <div class="card-header bg-success-gradient border-bottom-0">
                        <div
                            class="card-title text-white mb-0 d-flex align-items-center">
                            <div class="avatar avatar-sm bg-white-transparent me-2">
                                <img src="<?= BASE_URL('assets/img/icon-Google-Analytics.png'); ?>"
                                    alt="Analytics" class="w-100 h-100">
                            </div>
                            <span
                                class="fw-semibold fs-15"><?= __('Google Analytics'); ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label
                                        class="form-label fw-medium"><?= __('Trạng thái'); ?></label>
                                    <select class="form-select"
                                        name="google_analytics_status">
                                        <option
                                            <?= $CMSNT->site('google_analytics_status') == 1 ? 'selected' : ''; ?>
                                            value="1">
                                            <?= __('Bật'); ?>
                                        </option>
                                        <option
                                            <?= $CMSNT->site('google_analytics_status') == 0 ? 'selected' : ''; ?>
                                            value="0">
                                            <?= __('Tắt'); ?>
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label
                                        class="form-label fw-medium"><?= __('Measurement ID'); ?></label>
                                    <input type="text" name="google_analytics_id"
                                        placeholder="VD: G-XXXXXXXX"
                                        value="<?= $CMSNT->site('google_analytics_id'); ?>"
                                        class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Google Ads -->
                <div class="card custom-card border-0 shadow-sm mb-4">
                    <div class="card-header bg-warning-gradient border-bottom-0">
                        <div
                            class="card-title text-white mb-0 d-flex align-items-center">
                            <div class="avatar avatar-sm bg-white-transparent me-2">
                                <img src="<?= BASE_URL('mod/img/icon-google-ads.webp'); ?>"
                                    alt="Ads" class="w-100 h-100">
                            </div>
                            <span
                                class="fw-semibold fs-15"><?= __('Google Ads'); ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label
                                        class="form-label fw-medium"><?= __('Trạng thái'); ?></label>
                                    <select class="form-select"
                                        name="google_ads_status">
                                        <option
                                            <?= $CMSNT->site('google_ads_status') == 1 ? 'selected' : ''; ?>
                                            value="1">
                                            <?= __('Bật'); ?>
                                        </option>
                                        <option
                                            <?= $CMSNT->site('google_ads_status') == 0 ? 'selected' : ''; ?>
                                            value="0">
                                            <?= __('Tắt'); ?>
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label
                                        class="form-label fw-medium"><?= __('Google Ads ID'); ?></label>
                                    <input type="text" name="google_ads_id"
                                        placeholder="VD: AW-1234567890"
                                        value="<?= $CMSNT->site('google_ads_id'); ?>"
                                        class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Google Login -->
                <div class="card custom-card border-0 shadow-sm mb-4">
                    <div class="card-header bg-danger-gradient border-bottom-0">
                        <div
                            class="card-title text-white mb-0 d-flex align-items-center">
                            <div class="avatar avatar-sm bg-white-transparent me-2">
                                <img src="<?= BASE_URL('assets/img/icon-google-login.png'); ?>"
                                    alt="Google Login" class="w-100 h-100">
                            </div>
                            <span
                                class="fw-semibold fs-15"><?= __('Đăng nhập Google'); ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Status -->
                        <div class="mb-3">
                            <label
                                class="form-label fw-medium"><?= __('Trạng thái'); ?></label>
                            <select class="form-select" name="status_google_login">
                                <option
                                    <?= $CMSNT->site('status_google_login') == 1 ? 'selected' : ''; ?>
                                    value="1">
                                    <?= __('Bật'); ?>
                                </option>
                                <option
                                    <?= $CMSNT->site('status_google_login') == 0 ? 'selected' : ''; ?>
                                    value="0">
                                    <?= __('Tắt'); ?>
                                </option>
                            </select>
                        </div>

                        <!-- Client ID & Secret -->
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label
                                        class="form-label fw-medium"><?= __('Client ID'); ?></label>
                                    <input type="text" name="google_login_client_id"
                                        value="<?= $CMSNT->site('google_login_client_id'); ?>"
                                        class="form-control"
                                        placeholder="<?= __('Google OAuth Client ID'); ?>">
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label
                                        class="form-label fw-medium"><?= __('Client Secret'); ?></label>
                                    <input type="password"
                                        name="google_login_client_secret"
                                        value="<?= $CMSNT->site('google_login_client_secret'); ?>"
                                        class="form-control"
                                        placeholder="<?= __('Google OAuth Client Secret'); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Redirect URI -->
                        <div class="mb-3">
                            <label
                                class="form-label fw-medium"><?= __('Authorized redirect URIs'); ?></label>
                            <div class="input-group">
                                <input type="text" readonly
                                    value="<?= base_url('api/callback_google_login.php'); ?>"
                                    class="form-control bg-light"
                                    id="google-redirect-uri">
                                <button class="btn btn-primary-light" type="button"
                                    onclick="copyToClipboard('google-redirect-uri')"
                                    title="<?= __('Sao chép URL'); ?>">
                                    <i class="ri-file-copy-line"></i>
                                </button>
                            </div>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                <?= __('Nhập vào Authorized redirect URIs trong Google Cloud Console'); ?>
                            </div>
                        </div>
                        <!-- Help Link -->
                        <div class="alert alert-primary-transparent border-0">
                            <div class="d-flex align-items-center">
                                <div class="me-2">
                                    <i class="ri-information-line fs-16"></i>
                                </div>
                                <div>
                                    <strong><?= __('Cần trợ giúp?'); ?></strong><br>
                                    <a href="https://help.cmsnt.co/huong-dan/shopkey-huong-dan-cau-hinh-tinh-nang-dang-nhap-bang-google/"
                                        target="_blank"
                                        class="text-primary fw-medium">
                                        <i
                                            class="ri-external-link-line me-1"></i><?= __('Xem hướng dẫn chi tiết tích hợp đăng nhập bằng Google'); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- ChatGPT Configuration -->
                <div class="card custom-card border-0 shadow-sm mb-4">
                    <div class="card-header bg-info-gradient border-bottom-0">
                        <div
                            class="card-title text-white mb-0 d-flex align-items-center">
                            <div
                                class="avatar avatar-sm bg-white-transparent me-2 rounded">
                                <img src="https://i.imgur.com/5iOyCNW.png"
                                    alt="ChatGPT" class="w-100 h-100">
                            </div>
                            <span
                                class="fw-semibold fs-15"><?= __('Tích hợp ChatGPT'); ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- API Key -->
                        <div class="mb-4">
                            <label
                                class="form-label fw-medium d-flex align-items-center">
                                <i class="ri-key-line me-2 text-warning"></i>
                                <?= __('API Key'); ?>
                            </label>
                            <input type="password" name="chatgpt_api_key"
                                placeholder="VD: sk-XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX"
                                value="<?= $CMSNT->site('chatgpt_api_key'); ?>"
                                class="form-control">
                            <div class="form-text">
                                <i class="ri-shield-keyhole-line me-1"></i>
                                <?= __('Lấy API Key từ'); ?> <a
                                    href="https://platform.openai.com/api-keys"
                                    target="_blank"
                                    class="text-primary fw-medium">OpenAI
                                    Platform</a>
                            </div>
                        </div>

                        <!-- Model Selection -->
                        <div class="mb-3">
                            <label
                                class="form-label fw-medium d-flex align-items-center">
                                <i class="ri-cpu-line me-2 text-info"></i>
                                <?= __('Chọn Model AI'); ?>
                            </label>
                            <select class="form-select js-example-basic-single"
                                name="chatgpt_model">
                                <optgroup
                                    label="🔥 <?= __('Khuyến nghị - Mới nhất 2025'); ?>">
                                    <option value="gpt-4o"
                                        <?= $CMSNT->site('chatgpt_model') == 'gpt-4o' ? 'selected' : ''; ?>>
                                        🔥 GPT-4o - $2.50/$10.00 per 1M tokens (Flagship)
                                    </option>
                                    <option value="gpt-4o-mini"
                                        <?= $CMSNT->site('chatgpt_model') == 'gpt-4o-mini' ? 'selected' : ''; ?>>
                                        🔥 GPT-4o Mini - $0.15/$0.60 per 1M tokens (Tiết kiệm)
                                    </option>
                                </optgroup>

                                <optgroup
                                    label="🧠 <?= __('o-Series - Lý luận nâng cao'); ?>">
                                    <option value="o4-mini"
                                        <?= $CMSNT->site('chatgpt_model') == 'o4-mini' ? 'selected' : ''; ?>>
                                        o4-mini - $1.10/$4.40 per 1M tokens (Mới nhất)
                                    </option>
                                    <option value="o3-mini"
                                        <?= $CMSNT->site('chatgpt_model') == 'o3-mini' ? 'selected' : ''; ?>>
                                        o3-mini - $1.21/$4.84 per 1M tokens
                                    </option>
                                    <option value="o1"
                                        <?= $CMSNT->site('chatgpt_model') == 'o1' ? 'selected' : ''; ?>>
                                        o1 - $15.00/$60.00 per 1M tokens (Cao cấp)
                                    </option>
                                    <option value="o1-mini"
                                        <?= $CMSNT->site('chatgpt_model') == 'o1-mini' ? 'selected' : ''; ?>>
                                        o1-mini - $1.10/$4.40 per 1M tokens
                                    </option>
                                </optgroup>

                                <optgroup
                                    label="� <?= __('GPT-4 Legacy'); ?>">
                                    <option value="gpt-4o-2024-11-20"
                                        <?= $CMSNT->site('chatgpt_model') == 'gpt-4o-2024-11-20' ? 'selected' : ''; ?>>
                                        GPT-4o (2024-11-20) - $2.50/$10.00 per 1M tokens
                                    </option>
                                    <option value="gpt-4o-2024-08-06"
                                        <?= $CMSNT->site('chatgpt_model') == 'gpt-4o-2024-08-06' ? 'selected' : ''; ?>>
                                        GPT-4o (2024-08-06) - $2.50/$10.00 per 1M tokens
                                    </option>
                                    <option value="gpt-4-turbo"
                                        <?= $CMSNT->site('chatgpt_model') == 'gpt-4-turbo' ? 'selected' : ''; ?>>
                                        GPT-4 Turbo - $10.00/$30.00 per 1M tokens [Legacy]
                                    </option>
                                </optgroup>

                                <optgroup
                                    label="💡 <?= __('GPT-3.5 - Siêu tiết kiệm'); ?>">
                                    <option value="gpt-3.5-turbo"
                                        <?= $CMSNT->site('chatgpt_model') == 'gpt-3.5-turbo' ? 'selected' : ''; ?>>
                                        GPT-3.5 Turbo - $0.50/$1.50 per 1M tokens
                                    </option>
                                </optgroup>
                            </select>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                <?= __('o1 Series dành cho tác vụ phức tạp, GPT-4o cho đa năng, GPT-3.5 cho tiết kiệm chi phí'); ?>
                            </div>
                        </div>
                        <!-- Help Link -->
                        <div class="alert alert-primary-transparent border-0">
                            <div class="d-flex align-items-center">
                                <div class="me-2">
                                    <i class="ri-information-line fs-16"></i>
                                </div>
                                <div>
                                    <strong><?= __('Cần trợ giúp?'); ?></strong><br>
                                    <a href="https://help.cmsnt.co/huong-dan/smmpanel2-huong-dan-cau-hinh-chatgpt-de-su-dung-tinh-nang-ai/"
                                        target="_blank"
                                        class="text-primary fw-medium">
                                        <i
                                            class="ri-external-link-line me-1"></i><?= __('Xem hướng dẫn chi tiết tích hợp ChatGPT'); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Save Button -->
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-end">
                    <button type="submit" name="SaveSettings"
                        class="btn btn-primary btn-lg px-5">
                        <i class="ri-save-line me-2"></i>
                        <?= __('Lưu cấu hình'); ?>
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Xử lý nút Set Webhook
        $('#btn_set_webhook').click(function() {
            var btn = $(this);
            var input = $('#telegram_webhook_secret');
            var loading = $('#webhook_loading');
            var icon = $('#webhook_icon');
            var result = $('#webhook_result');

            // Disable button và show loading
            btn.prop('disabled', true);
            loading.show();
            icon.hide();
            result.html('');

            // Tạo secret token mới (64 ký tự hex)
            var newSecret = '';
            var chars = '0123456789abcdef';
            for (var i = 0; i < 64; i++) {
                newSecret += chars.charAt(Math.floor(Math.random() * chars.length));
            }

            // Cập nhật input với token mới
            input.val(newSecret);

            // Gọi AJAX để set webhook
            $.ajax({
                url: '<?= base_url('ajaxs/admin/update.php'); ?>',
                type: 'POST',
                data: {
                    action: 'set_webhook',
                    telegram_webhook_secret: newSecret
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        result.html(
                            '<div class="alert alert-success mt-2"><i class="fa fa-check"></i> ' +
                            response.msg + '</div>');

                        // Tự động lưu setting mới
                        $('input[name="telegram_webhook_secret"]').val(newSecret);

                        // Hiển thị thông tin webhook
                        if (response.webhook_url) {
                            result.append(
                                '<div class="alert alert-info mt-1"><strong><?= __("Webhook URL"); ?>:</strong><br><code>' +
                                response.webhook_url + '</code></div>');
                        }
                    } else {
                        result.html(
                            '<div class="alert alert-danger mt-2"><i class="fa fa-times"></i> ' +
                            response.msg + '</div>');
                    }
                },
                error: function() {
                    result.html(
                        '<div class="alert alert-danger mt-2"><i class="fa fa-times"></i> <?= __("Lỗi kết nối, vui lòng thử lại"); ?></div>'
                    );
                },
                complete: function() {
                    // Enable button và hide loading
                    btn.prop('disabled', false);
                    loading.hide();
                    icon.show();
                }
            });
        });
    });
</script>