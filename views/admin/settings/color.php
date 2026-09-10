<?php if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

// Xử lý lưu cài đặt màu sắc
if (isset($_POST['SaveColorSettings'])) {
    // Kiểm tra quyền
    if (checkPermission($getUser['admin'], 'edit_color') != true) {
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
        'action'        => __('Thay đổi màu sắc giao diện')
    ]);

    foreach ($_POST as $key => $value) {
        $CMSNT->update("settings", array(
            'value' => $value
        ), " `name` = '$key' ");
    }

    $my_text = $CMSNT->site('noti_action');
    $my_text = str_replace('{domain}', $_SERVER['SERVER_NAME'], $my_text);
    $my_text = str_replace('{username}', $getUser['username'], $my_text);
    $my_text = str_replace('{action}', __('Thay đổi màu sắc giao diện'), $my_text);
    $my_text = str_replace('{ip}', myip(), $my_text);
    $my_text = str_replace('{time}', gettime(), $my_text);
    sendMessAdmin($my_text);

    admin_msg_success("Lưu thành công!", "", 1000);
}
?>

<div class="tab-pane text-muted show active" id="color-settings" role="tabpanel">
    <h4><?= __('Tùy chỉnh màu sắc'); ?></h4>
    <form action="" method="POST">
        <?= csrfField(); ?>

        <!-- Theme Color Section - Professional Color Picker -->
        <div class="card border-0 shadow-sm overflow-hidden mb-4">
            <!-- Header with gradient -->
            <div class="card-header border-0 py-3" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="d-flex align-items-center w-100">
                    <div class="d-flex align-items-center flex-grow-1">
                        <div class="me-3 p-2 rounded-circle" style="background: rgba(255,255,255,0.2);">
                            <i class="fas fa-palette text-white" style="font-size: 20px;"></i>
                        </div>
                        <div>
                            <h6 class="card-title mb-0 text-white fw-bold text-uppercase">
                                <?= __('Theme Colors'); ?>
                            </h6>
                            <small class="text-white opacity-75"><?= __('Thiết lập màu chủ đạo cho giao diện website'); ?></small>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-light" onclick="resetToDefaultColors()" title="<?= __('Khôi phục mặc định'); ?>">
                        <i class="fas fa-undo-alt me-1"></i><?= __('Mặc định'); ?>
                    </button>
                </div>
            </div>

            <div class="card-body p-4">
                <!-- Live Preview Banner -->
                <div class="mb-4">
                    <label class="form-label fw-semibold mb-2">
                        <i class="fas fa-eye me-1"></i><?= __('Xem trước Gradient'); ?>
                    </label>
                    <div id="gradientPreview" class="rounded-3 p-4 text-center text-white"
                        style="background: linear-gradient(135deg, <?= $CMSNT->site('theme_color') ?: '#405189'; ?> 0%, <?= $CMSNT->site('theme_color1') ?: '#0ab39c'; ?> 100%); min-height: 100px; transition: all 0.3s ease;">
                        <h5 class="mb-1 fw-bold text-white"><?= __('Giao diện của bạn'); ?></h5>
                        <p class="mb-0 opacity-75"><?= __('Gradient được tạo từ 2 màu chủ đạo'); ?></p>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- Theme Color Primary -->
                    <div class="col-lg-6">
                        <div class="color-picker-card h-100 p-3 rounded-3 border" style="background: linear-gradient(180deg, #f8f9fa 0%, #ffffff 100%);">
                            <div class="d-flex align-items-start mb-3">
                                <div class="color-preview-box me-3" id="previewBox_theme_color"
                                    style="width: 60px; height: 60px; border-radius: 12px; background: <?= $CMSNT->site('theme_color') ?: '#405189'; ?>; box-shadow: 0 4px 15px rgba(0,0,0,0.2); cursor: pointer;"
                                    onclick="document.querySelector('.pickr-theme_color .pcr-button').click()"></div>
                                <div class="flex-grow-1">
                                    <h6 class="fw-bold mb-1"><?= __('Theme Color'); ?></h6>
                                    <p class="text-muted small mb-2"><?= __('Màu chủ đạo cho buttons, links, headers'); ?></p>
                                    <div class="d-flex align-items-center gap-2">
                                        <code id="hexValue_theme_color" class="px-2 py-1 bg-dark text-white rounded small"><?= $CMSNT->site('theme_color') ?: '#405189'; ?></code>
                                        <button type="button" class="btn btn-xs btn-outline-secondary" onclick="copyColorValue('theme_color')" title="Copy">
                                            <i class="far fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="color-picker-wrapper">
                                <div class="color-picker-input pickr-theme_color" data-name="theme_color"></div>
                                <input type="hidden" name="theme_color" id="input_theme_color" value="<?= $CMSNT->site('theme_color'); ?>">
                            </div>

                            <!-- Quick Presets -->
                            <div class="mt-3">
                                <label class="small text-muted mb-2 d-block"><?= __('Màu đề xuất'); ?></label>
                                <div class="d-flex flex-wrap gap-1 preset-colors" data-target="theme_color">
                                    <div class="preset-color" style="background: #405189;" data-color="#405189" title="Default Blue"></div>
                                    <div class="preset-color" style="background: #3577f1;" data-color="#3577f1" title="Sky Blue"></div>
                                    <div class="preset-color" style="background: #0ab39c;" data-color="#0ab39c" title="Teal"></div>
                                    <div class="preset-color" style="background: #f06548;" data-color="#f06548" title="Coral"></div>
                                    <div class="preset-color" style="background: #f7b84b;" data-color="#f7b84b" title="Amber"></div>
                                    <div class="preset-color" style="background: #564ab1;" data-color="#564ab1" title="Purple"></div>
                                    <div class="preset-color" style="background: #e83e8c;" data-color="#e83e8c" title="Pink"></div>
                                    <div class="preset-color" style="background: #212529;" data-color="#212529" title="Dark"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Theme Color Secondary -->
                    <div class="col-lg-6">
                        <div class="color-picker-card h-100 p-3 rounded-3 border" style="background: linear-gradient(180deg, #f8f9fa 0%, #ffffff 100%);">
                            <div class="d-flex align-items-start mb-3">
                                <div class="color-preview-box me-3" id="previewBox_theme_color1"
                                    style="width: 60px; height: 60px; border-radius: 12px; background: <?= $CMSNT->site('theme_color1') ?: '#0ab39c'; ?>; box-shadow: 0 4px 15px rgba(0,0,0,0.2); cursor: pointer;"
                                    onclick="document.querySelector('.pickr-theme_color1 .pcr-button').click()"></div>
                                <div class="flex-grow-1">
                                    <h6 class="fw-bold mb-1"><?= __('Theme Color 1'); ?></h6>
                                    <p class="text-muted small mb-2"><?= __('Màu phụ cho gradient, hover effects'); ?></p>
                                    <div class="d-flex align-items-center gap-2">
                                        <code id="hexValue_theme_color1" class="px-2 py-1 bg-dark text-white rounded small"><?= $CMSNT->site('theme_color1') ?: '#0ab39c'; ?></code>
                                        <button type="button" class="btn btn-xs btn-outline-secondary" onclick="copyColorValue('theme_color1')" title="Copy">
                                            <i class="far fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="color-picker-wrapper">
                                <div class="color-picker-input pickr-theme_color1" data-name="theme_color1"></div>
                                <input type="hidden" name="theme_color1" id="input_theme_color1" value="<?= $CMSNT->site('theme_color1'); ?>">
                            </div>

                            <!-- Quick Presets -->
                            <div class="mt-3">
                                <label class="small text-muted mb-2 d-block"><?= __('Màu đề xuất'); ?></label>
                                <div class="d-flex flex-wrap gap-1 preset-colors" data-target="theme_color1">
                                    <div class="preset-color" style="background: #0ab39c;" data-color="#0ab39c" title="Teal"></div>
                                    <div class="preset-color" style="background: #20c997;" data-color="#20c997" title="Green"></div>
                                    <div class="preset-color" style="background: #764ba2;" data-color="#764ba2" title="Violet"></div>
                                    <div class="preset-color" style="background: #fd7e14;" data-color="#fd7e14" title="Orange"></div>
                                    <div class="preset-color" style="background: #6610f2;" data-color="#6610f2" title="Indigo"></div>
                                    <div class="preset-color" style="background: #299cdb;" data-color="#299cdb" title="Cyan"></div>
                                    <div class="preset-color" style="background: #f06548;" data-color="#f06548" title="Red"></div>
                                    <div class="preset-color" style="background: #495057;" data-color="#495057" title="Gray"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Popular Gradients -->
                <div class="mt-4">
                    <label class="form-label fw-semibold mb-2">
                        <i class="fas fa-magic me-1"></i><?= __('Gradient phổ biến'); ?>
                    </label>

                    <!-- Row 1: Ocean & Teal (Matching current theme) -->
                    <small class="text-muted d-block mb-2"><?= __('Ocean & Teal'); ?></small>
                    <div class="row g-2 mb-3" id="popularGradients">
                        <div class="col-auto">
                            <div class="gradient-preset rounded-3" data-primary="#405189" data-secondary="#0ab39c" title="Default Theme"
                                style="width: 50px; height: 50px; background: linear-gradient(135deg, #405189 0%, #0ab39c 100%); cursor: pointer; transition: transform 0.2s;"></div>
                        </div>
                        <div class="col-auto">
                            <div class="gradient-preset rounded-3" data-primary="#00B4D8" data-secondary="#0077B6" title="Ocean Breeze"
                                style="width: 50px; height: 50px; background: linear-gradient(135deg, #00B4D8 0%, #0077B6 100%); cursor: pointer; transition: transform 0.2s;"></div>
                        </div>
                        <div class="col-auto">
                            <div class="gradient-preset rounded-3" data-primary="#11998e" data-secondary="#38ef7d" title="Fresh Mint"
                                style="width: 50px; height: 50px; background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); cursor: pointer; transition: transform 0.2s;"></div>
                        </div>
                        <div class="col-auto">
                            <div class="gradient-preset rounded-3" data-primary="#1A2980" data-secondary="#26D0CE" title="Midnight Royal"
                                style="width: 50px; height: 50px; background: linear-gradient(135deg, #1A2980 0%, #26D0CE 100%); cursor: pointer; transition: transform 0.2s;"></div>
                        </div>
                        <div class="col-auto">
                            <div class="gradient-preset rounded-3" data-primary="#2193b0" data-secondary="#6dd5ed" title="Cool Blues"
                                style="width: 50px; height: 50px; background: linear-gradient(135deg, #2193b0 0%, #6dd5ed 100%); cursor: pointer; transition: transform 0.2s;"></div>
                        </div>
                        <div class="col-auto">
                            <div class="gradient-preset rounded-3" data-primary="#00c6ff" data-secondary="#0072ff" title="Azure Sky"
                                style="width: 50px; height: 50px; background: linear-gradient(135deg, #00c6ff 0%, #0072ff 100%); cursor: pointer; transition: transform 0.2s;"></div>
                        </div>
                        <div class="col-auto">
                            <div class="gradient-preset rounded-3" data-primary="#43cea2" data-secondary="#185a9d" title="Sea Weed"
                                style="width: 50px; height: 50px; background: linear-gradient(135deg, #43cea2 0%, #185a9d 100%); cursor: pointer; transition: transform 0.2s;"></div>
                        </div>
                        <div class="col-auto">
                            <div class="gradient-preset rounded-3" data-primary="#20E2D7" data-secondary="#F9FEA5" title="Aqua Splash"
                                style="width: 50px; height: 50px; background: linear-gradient(135deg, #20E2D7 0%, #F9FEA5 100%); cursor: pointer; transition: transform 0.2s;"></div>
                        </div>
                    </div>

                    <!-- Row 2: Warm & Vibrant -->
                    <small class="text-muted d-block mb-2"><?= __('Warm & Vibrant'); ?></small>
                    <div class="row g-2 mb-3">
                        <div class="col-auto">
                            <div class="gradient-preset rounded-3" data-primary="#FF9F0A" data-secondary="#FF375F" title="Sunset Glow"
                                style="width: 50px; height: 50px; background: linear-gradient(135deg, #FF9F0A 0%, #FF375F 100%); cursor: pointer; transition: transform 0.2s;"></div>
                        </div>
                        <div class="col-auto">
                            <div class="gradient-preset rounded-3" data-primary="#fc4a1a" data-secondary="#f7b733" title="Sunrise"
                                style="width: 50px; height: 50px; background: linear-gradient(135deg, #fc4a1a 0%, #f7b733 100%); cursor: pointer; transition: transform 0.2s;"></div>
                        </div>
                        <div class="col-auto">
                            <div class="gradient-preset rounded-3" data-primary="#ee0979" data-secondary="#ff6a00" title="Burning Orange"
                                style="width: 50px; height: 50px; background: linear-gradient(135deg, #ee0979 0%, #ff6a00 100%); cursor: pointer; transition: transform 0.2s;"></div>
                        </div>
                        <div class="col-auto">
                            <div class="gradient-preset rounded-3" data-primary="#f12711" data-secondary="#f5af19" title="Flare"
                                style="width: 50px; height: 50px; background: linear-gradient(135deg, #f12711 0%, #f5af19 100%); cursor: pointer; transition: transform 0.2s;"></div>
                        </div>
                        <div class="col-auto">
                            <div class="gradient-preset rounded-3" data-primary="#FF512F" data-secondary="#DD2476" title="Bloody Mary"
                                style="width: 50px; height: 50px; background: linear-gradient(135deg, #FF512F 0%, #DD2476 100%); cursor: pointer; transition: transform 0.2s;"></div>
                        </div>
                        <div class="col-auto">
                            <div class="gradient-preset rounded-3" data-primary="#EB5757" data-secondary="#000000" title="Red to Black"
                                style="width: 50px; height: 50px; background: linear-gradient(135deg, #EB5757 0%, #000000 100%); cursor: pointer; transition: transform 0.2s;"></div>
                        </div>
                        <div class="col-auto">
                            <div class="gradient-preset rounded-3" data-primary="#FFE259" data-secondary="#FFA751" title="Mango"
                                style="width: 50px; height: 50px; background: linear-gradient(135deg, #FFE259 0%, #FFA751 100%); cursor: pointer; transition: transform 0.2s;"></div>
                        </div>
                        <div class="col-auto">
                            <div class="gradient-preset rounded-3" data-primary="#F7971E" data-secondary="#FFD200" title="Citrus"
                                style="width: 50px; height: 50px; background: linear-gradient(135deg, #F7971E 0%, #FFD200 100%); cursor: pointer; transition: transform 0.2s;"></div>
                        </div>
                    </div>

                    <!-- Row 3: Purple & Pink -->
                    <small class="text-muted d-block mb-2"><?= __('Purple & Pink'); ?></small>
                    <div class="row g-2 mb-3">
                        <div class="col-auto">
                            <div class="gradient-preset rounded-3" data-primary="#667eea" data-secondary="#764ba2" title="Cosmic Fusion"
                                style="width: 50px; height: 50px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); cursor: pointer; transition: transform 0.2s;"></div>
                        </div>
                        <div class="col-auto">
                            <div class="gradient-preset rounded-3" data-primary="#8E2DE2" data-secondary="#4A00E0" title="Neon Life"
                                style="width: 50px; height: 50px; background: linear-gradient(135deg, #8E2DE2 0%, #4A00E0 100%); cursor: pointer; transition: transform 0.2s;"></div>
                        </div>
                        <div class="col-auto">
                            <div class="gradient-preset rounded-3" data-primary="#f953c6" data-secondary="#b91d73" title="Pink Flavour"
                                style="width: 50px; height: 50px; background: linear-gradient(135deg, #f953c6 0%, #b91d73 100%); cursor: pointer; transition: transform 0.2s;"></div>
                        </div>
                        <div class="col-auto">
                            <div class="gradient-preset rounded-3" data-primary="#a18cd1" data-secondary="#fbc2eb" title="Soft Lilac"
                                style="width: 50px; height: 50px; background: linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%); cursor: pointer; transition: transform 0.2s;"></div>
                        </div>
                        <div class="col-auto">
                            <div class="gradient-preset rounded-3" data-primary="#DA22FF" data-secondary="#9733EE" title="Electric Violet"
                                style="width: 50px; height: 50px; background: linear-gradient(135deg, #DA22FF 0%, #9733EE 100%); cursor: pointer; transition: transform 0.2s;"></div>
                        </div>
                        <div class="col-auto">
                            <div class="gradient-preset rounded-3" data-primary="#C33764" data-secondary="#1D2671" title="Royal Wine"
                                style="width: 50px; height: 50px; background: linear-gradient(135deg, #C33764 0%, #1D2671 100%); cursor: pointer; transition: transform 0.2s;"></div>
                        </div>
                        <div class="col-auto">
                            <div class="gradient-preset rounded-3" data-primary="#FF0099" data-secondary="#493240" title="Pinky"
                                style="width: 50px; height: 50px; background: linear-gradient(135deg, #FF0099 0%, #493240 100%); cursor: pointer; transition: transform 0.2s;"></div>
                        </div>
                        <div class="col-auto">
                            <div class="gradient-preset rounded-3" data-primary="#7F00FF" data-secondary="#E100FF" title="Deep Purple"
                                style="width: 50px; height: 50px; background: linear-gradient(135deg, #7F00FF 0%, #E100FF 100%); cursor: pointer; transition: transform 0.2s;"></div>
                        </div>
                    </div>

                    <!-- Row 4: Professional & Dark -->
                    <small class="text-muted d-block mb-2"><?= __('Professional & Dark'); ?></small>
                    <div class="row g-2 mb-3">
                        <div class="col-auto">
                            <div class="gradient-preset rounded-3" data-primary="#0f2027" data-secondary="#2c5364" title="Dark Ocean"
                                style="width: 50px; height: 50px; background: linear-gradient(135deg, #0f2027 0%, #2c5364 100%); cursor: pointer; transition: transform 0.2s;"></div>
                        </div>
                        <div class="col-auto">
                            <div class="gradient-preset rounded-3" data-primary="#232526" data-secondary="#414345" title="Midnight City"
                                style="width: 50px; height: 50px; background: linear-gradient(135deg, #232526 0%, #414345 100%); cursor: pointer; transition: transform 0.2s;"></div>
                        </div>
                        <div class="col-auto">
                            <div class="gradient-preset rounded-3" data-primary="#2C3E50" data-secondary="#4CA1AF" title="Gone Beach"
                                style="width: 50px; height: 50px; background: linear-gradient(135deg, #2C3E50 0%, #4CA1AF 100%); cursor: pointer; transition: transform 0.2s;"></div>
                        </div>
                        <div class="col-auto">
                            <div class="gradient-preset rounded-3" data-primary="#373B44" data-secondary="#4286f4" title="Stellar"
                                style="width: 50px; height: 50px; background: linear-gradient(135deg, #373B44 0%, #4286f4 100%); cursor: pointer; transition: transform 0.2s;"></div>
                        </div>
                        <div class="col-auto">
                            <div class="gradient-preset rounded-3" data-primary="#434343" data-secondary="#000000" title="Dark Matter"
                                style="width: 50px; height: 50px; background: linear-gradient(135deg, #434343 0%, #000000 100%); cursor: pointer; transition: transform 0.2s;"></div>
                        </div>
                        <div class="col-auto">
                            <div class="gradient-preset rounded-3" data-primary="#16222A" data-secondary="#3A6073" title="Mirage"
                                style="width: 50px; height: 50px; background: linear-gradient(135deg, #16222A 0%, #3A6073 100%); cursor: pointer; transition: transform 0.2s;"></div>
                        </div>
                        <div class="col-auto">
                            <div class="gradient-preset rounded-3" data-primary="#1F1C2C" data-secondary="#928DAB" title="Dusk"
                                style="width: 50px; height: 50px; background: linear-gradient(135deg, #1F1C2C 0%, #928DAB 100%); cursor: pointer; transition: transform 0.2s;"></div>
                        </div>
                        <div class="col-auto">
                            <div class="gradient-preset rounded-3" data-primary="#200122" data-secondary="#6f0000" title="Dark Wine"
                                style="width: 50px; height: 50px; background: linear-gradient(135deg, #200122 0%, #6f0000 100%); cursor: pointer; transition: transform 0.2s;"></div>
                        </div>
                    </div>

                    <!-- Row 5: Nature & Fresh -->
                    <small class="text-muted d-block mb-2"><?= __('Nature & Fresh'); ?></small>
                    <div class="row g-2">
                        <div class="col-auto">
                            <div class="gradient-preset rounded-3" data-primary="#56ab2f" data-secondary="#a8e063" title="Fresh Grass"
                                style="width: 50px; height: 50px; background: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%); cursor: pointer; transition: transform 0.2s;"></div>
                        </div>
                        <div class="col-auto">
                            <div class="gradient-preset rounded-3" data-primary="#134E5E" data-secondary="#71B280" title="Moss"
                                style="width: 50px; height: 50px; background: linear-gradient(135deg, #134E5E 0%, #71B280 100%); cursor: pointer; transition: transform 0.2s;"></div>
                        </div>
                        <div class="col-auto">
                            <div class="gradient-preset rounded-3" data-primary="#1D976C" data-secondary="#93F9B9" title="Summer Green"
                                style="width: 50px; height: 50px; background: linear-gradient(135deg, #1D976C 0%, #93F9B9 100%); cursor: pointer; transition: transform 0.2s;"></div>
                        </div>
                        <div class="col-auto">
                            <div class="gradient-preset rounded-3" data-primary="#2AF598" data-secondary="#009EFD" title="Oasis"
                                style="width: 50px; height: 50px; background: linear-gradient(135deg, #2AF598 0%, #009EFD 100%); cursor: pointer; transition: transform 0.2s;"></div>
                        </div>
                        <div class="col-auto">
                            <div class="gradient-preset rounded-3" data-primary="#0BA360" data-secondary="#3CBA92" title="Emerald"
                                style="width: 50px; height: 50px; background: linear-gradient(135deg, #0BA360 0%, #3CBA92 100%); cursor: pointer; transition: transform 0.2s;"></div>
                        </div>
                        <div class="col-auto">
                            <div class="gradient-preset rounded-3" data-primary="#36D1DC" data-secondary="#5B86E5" title="Cool Sky"
                                style="width: 50px; height: 50px; background: linear-gradient(135deg, #36D1DC 0%, #5B86E5 100%); cursor: pointer; transition: transform 0.2s;"></div>
                        </div>
                        <div class="col-auto">
                            <div class="gradient-preset rounded-3" data-primary="#00b09b" data-secondary="#96c93d" title="Relaxing"
                                style="width: 50px; height: 50px; background: linear-gradient(135deg, #00b09b 0%, #96c93d 100%); cursor: pointer; transition: transform 0.2s;"></div>
                        </div>
                        <div class="col-auto">
                            <div class="gradient-preset rounded-3" data-primary="#02AAB0" data-secondary="#00CDAC" title="Green Beach"
                                style="width: 50px; height: 50px; background: linear-gradient(135deg, #02AAB0 0%, #00CDAC 100%); cursor: pointer; transition: transform 0.2s;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" name="SaveColorSettings" class="btn btn-primary w-100 mb-3">
            <i class="fa fa-fw fa-save me-1"></i> <?= __('Save'); ?>
        </button>
    </form>
</div>

<style>
    /* Color Picker Styles */
    .preset-color {
        width: 28px;
        height: 28px;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s ease;
        border: 2px solid transparent;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    .preset-color:hover {
        transform: scale(1.15);
        border-color: #fff;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
    }

    .gradient-preset:hover {
        transform: scale(1.1);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
    }

    .color-preview-box {
        transition: all 0.3s ease;
    }

    .color-preview-box:hover {
        transform: scale(1.05);
    }

    .color-picker-card {
        transition: all 0.3s ease;
    }

    .color-picker-card:hover {
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    }

    /* Customize Pickr appearance */
    .pcr-app {
        border-radius: 12px !important;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2) !important;
    }

    .pcr-button {
        width: 100% !important;
        height: 45px !important;
        border-radius: 8px !important;
        border: 2px solid #e9ecef !important;
        transition: all 0.3s ease !important;
    }

    .pcr-button:hover {
        border-color: #667eea !important;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2) !important;
    }

    .pcr-button::after {
        content: 'Chọn màu' !important;
        position: absolute !important;
        right: 10px !important;
        top: 50% !important;
        transform: translateY(-50%) !important;
        font-size: 12px !important;
        color: #6c757d !important;
        background: rgba(255, 255, 255, 0.9) !important;
        padding: 2px 8px !important;
        border-radius: 4px !important;
    }

    .btn-xs {
        padding: 0.2rem 0.4rem;
        font-size: 0.75rem;
    }
</style>

<script>
    // Store Pickr instances globally
    window.colorPickrInstances = {};

    // Update gradient preview
    function updateGradientPreview() {
        const color1 = document.getElementById('input_theme_color')?.value || '#405189';
        const color2 = document.getElementById('input_theme_color1')?.value || '#0ab39c';
        const preview = document.getElementById('gradientPreview');
        if (preview) {
            preview.style.background = `linear-gradient(135deg, ${color1} 0%, ${color2} 100%)`;
        }
    }

    // Update color display and preview box
    function updateColorUI(fieldName, color) {
        const hexDisplay = document.getElementById('hexValue_' + fieldName);
        const previewBox = document.getElementById('previewBox_' + fieldName);
        const hiddenInput = document.getElementById('input_' + fieldName);

        if (hexDisplay) hexDisplay.textContent = color;
        if (previewBox) previewBox.style.background = color;
        if (hiddenInput) hiddenInput.value = color;

        updateGradientPreview();
    }

    // Copy color value to clipboard
    window.copyColorValue = function(fieldName) {
        const hexDisplay = document.getElementById('hexValue_' + fieldName);
        if (hexDisplay) {
            navigator.clipboard.writeText(hexDisplay.textContent).then(() => {
                const btn = hexDisplay.nextElementSibling;
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check text-success"></i>';
                setTimeout(() => {
                    btn.innerHTML = originalHtml;
                }, 1500);
            });
        }
    };

    // Reset to default colors
    window.resetToDefaultColors = function() {
        Swal.fire({
            title: '<?= __('Xác nhận'); ?>',
            text: '<?= __('Khôi phục màu sắc về mặc định?'); ?>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<?= __('Đồng ý'); ?>',
            cancelButtonText: '<?= __('Hủy'); ?>'
        }).then((result) => {
            if (result.isConfirmed) {
                const defaults = {
                    theme_color: '#405189',
                    theme_color1: '#0ab39c'
                };
                Object.keys(defaults).forEach(name => {
                    if (window.colorPickrInstances[name]) {
                        window.colorPickrInstances[name].setColor(defaults[name]);
                    }
                    updateColorUI(name, defaults[name]);
                });
                Swal.fire({
                    icon: 'success',
                    title: '<?= __('Đã khôi phục'); ?>',
                    timer: 1500,
                    showConfirmButton: false
                });
            }
        });
    };

    // Color Picker initialization
    document.addEventListener('DOMContentLoaded', function() {
        const swatches = [
            '#405189', '#0ab39c', '#f06548', '#f7b84b', '#299cdb', '#564ab1',
            '#3577f1', '#e83e8c', '#20c997', '#fd7e14', '#6610f2', '#6c757d',
            '#495057', '#343a40', '#212529', '#11998e', '#667eea', '#764ba2'
        ];

        document.querySelectorAll('.color-picker-input').forEach(function(el) {
            const fieldName = el.getAttribute('data-name');
            const hiddenInput = document.getElementById('input_' + fieldName);
            let defaultColor = hiddenInput ? (hiddenInput.value || '#405189') : '#405189';

            const pickr = Pickr.create({
                el: el,
                theme: 'monolith',
                default: defaultColor,
                swatches: swatches,
                comparison: true,
                components: {
                    preview: true,
                    opacity: false,
                    hue: true,
                    interaction: {
                        hex: true,
                        rgba: true,
                        hsla: false,
                        hsva: false,
                        cmyk: false,
                        input: true,
                        clear: false,
                        save: true
                    }
                },
                strings: {
                    save: '<?= __('Chọn'); ?>'
                }
            });

            // Store instance globally
            window.colorPickrInstances[fieldName] = pickr;

            // Real-time color change
            pickr.on('change', (color) => {
                if (color) {
                    const hexColor = color.toHEXA().toString();
                    updateColorUI(fieldName, hexColor);
                }
            });

            pickr.on('save', (color, instance) => {
                if (color) {
                    const hexColor = color.toHEXA().toString();
                    updateColorUI(fieldName, hexColor);
                }
                pickr.hide();
            });
        });

        // Handle preset color clicks
        document.querySelectorAll('.preset-colors .preset-color').forEach(function(el) {
            el.addEventListener('click', function() {
                const color = this.getAttribute('data-color');
                const target = this.parentElement.getAttribute('data-target');

                if (window.colorPickrInstances[target]) {
                    window.colorPickrInstances[target].setColor(color);
                }
                updateColorUI(target, color);

                // Visual feedback
                this.style.transform = 'scale(1.3)';
                setTimeout(() => {
                    this.style.transform = '';
                }, 200);
            });
        });

        // Handle gradient preset clicks
        document.querySelectorAll('.gradient-preset').forEach(function(el) {
            el.addEventListener('click', function() {
                const primary = this.getAttribute('data-primary');
                const secondary = this.getAttribute('data-secondary');

                if (window.colorPickrInstances['theme_color']) {
                    window.colorPickrInstances['theme_color'].setColor(primary);
                }
                if (window.colorPickrInstances['theme_color1']) {
                    window.colorPickrInstances['theme_color1'].setColor(secondary);
                }

                updateColorUI('theme_color', primary);
                updateColorUI('theme_color1', secondary);

                // Visual feedback
                this.style.transform = 'scale(1.15)';
                setTimeout(() => {
                    this.style.transform = '';
                }, 300);

                // Toast notification
                showMessage('<?= __('Đã áp dụng gradient'); ?>: ' + this.getAttribute('title'), 'success');
            });
        });
    });
</script>