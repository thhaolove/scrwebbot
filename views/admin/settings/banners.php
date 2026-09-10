<?php if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

// Xử lý form thêm banner
if (isset($_POST['submit']) && isset($_POST['action']) && $_POST['action'] == 'addBanner') {
    // Kiểm tra quyền
    if (checkPermission($getUser['admin'], 'edit_banners') != true) {
        die('<script type="text/javascript">if(!alert("' . __('Bạn không có quyền sử dụng tính năng này') . '")){window.history.back();}</script>');
    }
    // Kiểm tra CSRF token
    checkCSRF();

    if ($CMSNT->site('status_demo') != 0) {
        die('<script type="text/javascript">if(!alert("' . __('This function cannot be used because this is a demo site') . '")){window.history.back().location.reload();}</script>');
    }

    $title = validate_string($_POST['title'] ?? '', 255);
    $link = validate_string($_POST['link'] ?? '', 500);
    $position = validate_string($_POST['position'] ?? '', 50);
    $sort_order = validate_int($_POST['sort_order'] ?? 0, 0, 9999);
    $status = validate_int($_POST['status'] ?? 1, 0, 1);

    if ($title === false) {
        $title = '';
    }
    if ($link === false) {
        $link = null;
    }
    if ($position === false || !in_array($position, ['below_sliders', 'sidebar_left', 'sidebar_right', 'footer', 'top', 'content'])) {
        $position = 'below_sliders';
    }
    if ($sort_order === false) {
        $sort_order = 0;
    }
    if ($status === false) {
        $status = 1;
    }

    // Kiểm tra upload ảnh
    if (!isset($_FILES['banner_image']) || check_img('banner_image') != true) {
        die('<script type="text/javascript">if(!alert("' . __('Vui lòng chọn ảnh hợp lệ') . '")){window.history.back();}</script>');
    }

    // Upload ảnh
    $rand = random('0123456789QWERTYUIOPASDGHJKLZXCVBNM', 6);
    $ext = pathinfo($_FILES['banner_image']['name'], PATHINFO_EXTENSION);
    $uploads_dir = 'assets/storage/images/banner_' . $rand . '.' . $ext;
    $tmp_name = $_FILES['banner_image']['tmp_name'];
    $addimage = move_uploaded_file($tmp_name, __DIR__ . '/../../../' . $uploads_dir);

    if (!$addimage) {
        die('<script type="text/javascript">if(!alert("' . __('Upload ảnh thất bại!') . '")){window.history.back();}</script>');
    }

    $isInsert = $CMSNT->insert("banners", [
        'title'      => $title,
        'image'      => $uploads_dir,
        'link'       => $link,
        'position'   => $position,
        'sort_order' => $sort_order,
        'status'     => $status,
        'created_at' => gettime(),
        'updated_at' => gettime()
    ]);

    if ($isInsert) {
        $CMSNT->insert("logs", [
            'user_id'    => $getUser['id'],
            'ip'         => myip(),
            'device'     => getUserAgent(),
            'createdate' => gettime(),
            'action'     => __('Thêm banner mới')
        ]);

        /** NOTE ACTION */
        $my_text = $CMSNT->site('noti_action');
        $my_text = str_replace('{domain}', $_SERVER['SERVER_NAME'], $my_text);
        $my_text = str_replace('{username}', $getUser['username'], $my_text);
        $my_text = str_replace('{action}', __('Thêm banner mới'), $my_text);
        $my_text = str_replace('{ip}', myip(), $my_text);
        $my_text = str_replace('{time}', gettime(), $my_text);
        sendMessAdmin($my_text);

        admin_msg_success(__('Thêm banner thành công!'), base_url_admin('settings&tab=banners'), 2000);
    } else {
        admin_msg_error(__('Thêm banner thất bại!'), "", 3000);
    }
}


// Danh sách vị trí (phải định nghĩa trước khi sử dụng)
$position_list = [
    'below_sliders' => __('Dưới Slider'),
    'sidebar_left' => __('Cố định bên trái'),
    'sidebar_right' => __('Cố định bên phải'),
    'footer' => __('Footer'),
    // 'top' => __('Trên cùng'),
    // 'content' => __('Trong nội dung')
];

// Lấy tất cả banners để hiển thị theo card
$allBanners = $CMSNT->get_list("SELECT * FROM `banners` ORDER BY `position` ASC, `sort_order` ASC, `id` DESC");

// Nhóm banners theo position
$banners_by_position = [];
foreach ($position_list as $pos_key => $pos_label) {
    $banners_by_position[$pos_key] = [];
}
if (is_array($allBanners)) {
    foreach ($allBanners as $banner) {
        $pos = $banner['position'];
        if (isset($banners_by_position[$pos])) {
            $banners_by_position[$pos][] = $banner;
        }
    }
}

// Thống kê
$stats_all = $CMSNT->get_row_safe("SELECT COUNT(*) as total FROM `banners`", []);
$stats_active = $CMSNT->get_row_safe("SELECT COUNT(*) as total FROM `banners` WHERE `status` = 1", []);
$stats_inactive = $CMSNT->get_row_safe("SELECT COUNT(*) as total FROM `banners` WHERE `status` = 0", []);
?>

<div class="tab-pane text-muted show active" id="banners" role="tabpanel">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h4 class="mb-0">
            <i class="fa-solid fa-rectangle-ad me-1"></i><?= __('Quản lý Banner'); ?>
            <?php if ($CMSNT->site('is_show_banner') == '1'): ?>
                <span class="badge bg-success ms-2"><i class="fa-solid fa-check-circle me-1"></i><?= __('ON'); ?></span>
            <?php else: ?>
                <span class="badge bg-secondary ms-2"><i class="fa-solid fa-pause-circle me-1"></i><?= __('OFF'); ?></span>
            <?php endif; ?>
        </h4>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addBannerModal">
            <i class="fa-solid fa-plus me-1"></i><?= __('Thêm Banner'); ?>
        </button>
    </div>




    <!-- Danh sách banner theo vị trí (Card Layout) -->
    <div class="row">
        <?php
        $position_icons = [
            'top' => 'fa-arrow-up',
            'below_sliders' => 'fa-arrow-down',
            'sidebar_left' => 'fa-arrow-left',
            'sidebar_right' => 'fa-arrow-right',
            'content' => 'fa-file-text',
            'footer' => 'fa-window-minimize'
        ];
        $position_colors = [
            'top' => 'primary',
            'below_sliders' => 'info',
            'sidebar_left' => 'warning',
            'sidebar_right' => 'success',
            'content' => 'purple',
            'footer' => 'secondary'
        ];
        foreach ($position_list as $pos_key => $pos_label):
            $banners_in_position = $banners_by_position[$pos_key] ?? [];
            $count_active = 0;
            $count_inactive = 0;
            foreach ($banners_in_position as $b) {
                if ($b['status'] == 1) $count_active++;
                else $count_inactive++;
            }
        ?>
            <div class="col-xl-6 col-lg-6 col-md-12 mb-4">
                <div class="card custom-card border h-100">
                    <div class="card-header bg-<?= isset($position_colors[$pos_key]) ? $position_colors[$pos_key] : 'primary'; ?>-transparent">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center">
                                <div class="avatar avatar-sm bg-<?= isset($position_colors[$pos_key]) ? $position_colors[$pos_key] : 'primary'; ?> rounded-circle me-2">
                                    <i class="fa-solid <?= isset($position_icons[$pos_key]) ? $position_icons[$pos_key] : 'fa-rectangle-ad'; ?>"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-semibold"><?= $pos_label; ?></h6>
                                    <small class="text-muted">
                                        <?= count($banners_in_position); ?> <?= __('banner'); ?>
                                        (<?= $count_active; ?> <?= __('hoạt động'); ?>, <?= $count_inactive; ?> <?= __('tạm dừng'); ?>)
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-3" style="min-height: 200px; max-height: 400px; overflow-y: auto;">
                        <div id="banner-position-<?= $pos_key; ?>" class="banner-position-container" data-position="<?= $pos_key; ?>">
                            <?php if (count($banners_in_position) > 0): ?>
                                <?php foreach ($banners_in_position as $banner): ?>
                                    <div class="banner-item card"
                                        id="banner-<?= $banner['id']; ?>"
                                        data-banner-id="<?= $banner['id']; ?>"
                                        data-sort-order="<?= $banner['sort_order']; ?>"
                                        data-position="<?= $banner['position']; ?>">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="flex-shrink-0 handle-banner">
                                                    <i class="fa-solid fa-grip-vertical"></i>
                                                </div>
                                                <div class="flex-shrink-0">
                                                    <img src="<?= BASE_URL($banner['image']); ?>"
                                                        alt="<?= htmlspecialchars($banner['title'], ENT_QUOTES, 'UTF-8'); ?>"
                                                        class="rounded banner-thumbnail">
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <div class="flex-grow-1">
                                                            <h6 class="mb-1 fw-semibold">
                                                                <?= !empty($banner['title']) ? htmlspecialchars($banner['title'], ENT_QUOTES, 'UTF-8') : '<span class="text-muted fw-normal">' . __('Banner không có tiêu đề') . '</span>'; ?>
                                                            </h6>
                                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                                <span class="badge bg-info"><?= __('Thứ tự'); ?>: <?= $banner['sort_order']; ?></span>
                                                                <?php if ($banner['status'] == 1): ?>
                                                                    <span class="badge bg-success"><?= __('Hoạt động'); ?></span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-secondary"><?= __('Tạm dừng'); ?></span>
                                                                <?php endif; ?>
                                                                <?php if (!empty($banner['link'])): ?>
                                                                    <a href="<?= htmlspecialchars($banner['link'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                        target="_blank" class="text-primary small text-decoration-none">
                                                                        <i class="fas fa-external-link-alt me-1"></i><?= __('Xem link'); ?>
                                                                    </a>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <div class="btn-list flex-shrink-0">
                                                            <button onclick="editBanner('<?= $banner['id']; ?>')"
                                                                class="btn btn-sm btn-info" title="<?= __('Sửa'); ?>">
                                                                <i class="fa-solid fa-edit"></i>
                                                            </button>
                                                            <button onclick="removeBanner('<?= $banner['id']; ?>')"
                                                                class="btn btn-sm btn-danger" title="<?= __('Xóa'); ?>">
                                                                <i class="fa-solid fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="fa-solid fa-inbox fs-24 mb-2 d-block"></i>
                                    <small><?= __('Chưa có banner nào ở vị trí này'); ?></small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (count($allBanners) == 0): ?>
        <div class="card custom-card border">
            <div class="card-body text-center p-5">
                <div class="mb-3">
                    <i class="fa-solid fa-rectangle-ad fs-48 text-muted"></i>
                </div>
                <h5 class="text-muted"><?= __('Chưa có banner nào'); ?></h5>
                <p class="text-muted"><?= __('Hãy thêm banner mới để bắt đầu'); ?></p>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBannerModal">
                    <i class="fa-solid fa-plus me-1"></i><?= __('Thêm Banner đầu tiên'); ?>
                </button>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal thêm banner -->
<div class="modal fade" id="addBannerModal" tabindex="-1" aria-labelledby="addBannerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addBannerModalLabel">
                    <i class="fa-solid fa-plus-circle me-2"></i><?= __('Thêm Banner mới'); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST" enctype="multipart/form-data">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="addBanner">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-lg-12 mb-3">
                            <label class="form-label"><?= __('Ảnh Banner'); ?> <span class="text-danger">*</span></label>
                            <input type="file" class="form-control" name="banner_image" id="banner_image"
                                accept="image/*" required onchange="previewImage(this)">
                            <small class="text-muted"><?= __('Định dạng: PNG, JPG, JPEG, GIF, SVG, WEBP'); ?></small>
                            <div id="imagePreview" class="mt-3" style="display: none;">
                                <img id="previewImg" src="" alt="Preview" class="rounded border"
                                    style="max-width: 100%; max-height: 300px; object-fit: contain;">
                            </div>
                        </div>

                        <div class="col-lg-6 mb-3">
                            <label class="form-label"><?= __('Tiêu đề'); ?></label>
                            <input type="text" class="form-control" name="title"
                                placeholder="<?= __('Nhập tiêu đề banner (tùy chọn)'); ?>">
                        </div>

                        <div class="col-lg-6 mb-3">
                            <label class="form-label"><?= __('Vị trí hiển thị'); ?> <span class="text-danger">*</span></label>
                            <select class="form-select" name="position" required>
                                <?php foreach ($position_list as $pos_key => $pos_label): ?>
                                    <option value="<?= $pos_key; ?>" <?= $pos_key == 'below_sliders' ? 'selected' : ''; ?>>
                                        <?= $pos_label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted"><?= __('Chọn vị trí banner sẽ xuất hiện trên website'); ?></small>
                        </div>

                        <div class="col-lg-6 mb-3">
                            <label class="form-label"><?= __('Link'); ?></label>
                            <input type="text" class="form-control" name="link"
                                placeholder="<?= __('https://example.com (tùy chọn)'); ?>">
                            <small class="text-muted"><?= __('Link khi click vào banner'); ?></small>
                        </div>

                        <div class="col-lg-6 mb-3">
                            <label class="form-label"><?= __('Thứ tự hiển thị'); ?></label>
                            <input type="number" class="form-control" name="sort_order"
                                value="0" min="0" max="9999">
                            <small class="text-muted"><?= __('Số nhỏ hơn sẽ hiển thị trước'); ?></small>
                        </div>

                        <div class="col-lg-6 mb-3">
                            <label class="form-label"><?= __('Trạng thái'); ?></label>
                            <select class="form-select" name="status">
                                <option value="1" selected><?= __('Hoạt động'); ?></option>
                                <option value="0"><?= __('Tạm dừng'); ?></option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fa-solid fa-times me-1"></i><?= __('Hủy'); ?>
                    </button>
                    <button type="submit" name="submit" class="btn btn-primary">
                        <i class="fa-solid fa-save me-1"></i><?= __('Lưu'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal sửa banner -->
<div class="modal fade" id="editBannerModal" tabindex="-1" aria-labelledby="editBannerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editBannerModalLabel">
                    <i class="fa-solid fa-edit me-2"></i><?= __('Chỉnh sửa Banner'); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editBannerForm" action="" method="POST" enctype="multipart/form-data">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="editBanner">
                <input type="hidden" name="banner_id" id="edit_banner_id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-lg-12 mb-3">
                            <label class="form-label"><?= __('Ảnh Banner hiện tại'); ?></label>
                            <div class="mb-2">
                                <img id="edit_current_image" src="" alt="Current Banner" class="rounded border"
                                    style="max-width: 100%; max-height: 200px; object-fit: contain;">
                            </div>
                            <label class="form-label"><?= __('Thay đổi ảnh (tùy chọn)'); ?></label>
                            <input type="file" class="form-control" name="banner_image" id="edit_banner_image"
                                accept="image/*" onchange="previewEditImage(this)">
                            <small class="text-muted"><?= __('Để trống nếu không muốn thay đổi ảnh'); ?></small>
                            <div id="editImagePreview" class="mt-3" style="display: none;">
                                <img id="editPreviewImg" src="" alt="Preview" class="rounded border"
                                    style="max-width: 100%; max-height: 200px; object-fit: contain;">
                            </div>
                        </div>

                        <div class="col-lg-6 mb-3">
                            <label class="form-label"><?= __('Tiêu đề'); ?></label>
                            <input type="text" class="form-control" name="title" id="edit_title"
                                placeholder="<?= __('Nhập tiêu đề banner (tùy chọn)'); ?>">
                        </div>

                        <div class="col-lg-6 mb-3">
                            <label class="form-label"><?= __('Vị trí hiển thị'); ?> <span class="text-danger">*</span></label>
                            <select class="form-select" name="position" id="edit_position" required>
                                <?php foreach ($position_list as $pos_key => $pos_label): ?>
                                    <option value="<?= $pos_key; ?>"><?= $pos_label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-lg-6 mb-3">
                            <label class="form-label"><?= __('Link'); ?></label>
                            <input type="text" class="form-control" name="link" id="edit_link"
                                placeholder="<?= __('https://example.com (tùy chọn)'); ?>">
                        </div>

                        <div class="col-lg-6 mb-3">
                            <label class="form-label"><?= __('Thứ tự hiển thị'); ?></label>
                            <input type="number" class="form-control" name="sort_order" id="edit_sort_order"
                                value="0" min="0" max="9999">
                        </div>

                        <div class="col-lg-6 mb-3">
                            <label class="form-label"><?= __('Trạng thái'); ?></label>
                            <select class="form-select" name="status" id="edit_status">
                                <option value="1"><?= __('Hoạt động'); ?></option>
                                <option value="0"><?= __('Tạm dừng'); ?></option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fa-solid fa-times me-1"></i><?= __('Hủy'); ?>
                    </button>
                    <button type="button" class="btn btn-primary" onclick="submitEditBanner()">
                        <i class="fa-solid fa-save me-1"></i><?= __('Cập nhật'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .banner-item {
        transition: all 0.2s ease;
        border: 1px solid #e9ecef;
        background: #fff;
        border-radius: 8px;
        margin-bottom: 12px;
    }

    .banner-item:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        transform: translateY(-2px);
        border-color: #dee2e6;
    }

    .banner-item.sortable-ghost {
        opacity: 0.4;
        background: #f8f9fa;
        border: 2px dashed #adb5bd;
    }

    .banner-item.sortable-drag {
        opacity: 0.9;
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
        transform: rotate(2deg);
        z-index: 1000;
    }

    .banner-position-container {
        min-height: 80px;
        padding: 8px 0;
    }

    .handle-banner {
        cursor: move;
        padding: 8px 4px;
        color: #6c757d;
        transition: color 0.2s;
    }

    .handle-banner:hover {
        color: #495057;
    }

    .banner-item .card-body {
        padding: 12px 16px;
    }

    .banner-thumbnail {
        width: 100px;
        height: 60px;
        object-fit: cover;
        border: 1px solid #dee2e6;
        transition: transform 0.2s;
        border-radius: 6px;
    }

    .banner-item:hover .banner-thumbnail {
        transform: scale(1.02);
    }

    .banner-item .btn-list {
        display: flex;
        gap: 6px;
    }

    .banner-item .badge {
        font-size: 11px;
        padding: 4px 8px;
        font-weight: 500;
    }

    .banner-item h6 {
        font-size: 14px;
        line-height: 1.4;
        margin-bottom: 0;
    }

    .handle-banner {
        font-size: 18px;
    }

    .banner-item {
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    .banner-item.sortable-drag {
        transform: rotate(1deg);
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script>
    // Khởi tạo Sortable cho tất cả các vị trí banner - cho phép kéo thả giữa các card
    document.addEventListener('DOMContentLoaded', function() {
        var sortableInstances = {};
        var positionContainers = document.querySelectorAll('.banner-position-container');

        positionContainers.forEach(function(container) {
            var position = container.getAttribute('data-position');

            sortableInstances[position] = new Sortable(container, {
                group: 'banners',
                handle: '.handle-banner',
                animation: 150,
                ghostClass: 'sortable-ghost',
                dragClass: 'sortable-drag',
                onEnd: function(evt) {
                    var fromPosition = evt.from.getAttribute('data-position');
                    var toPosition = evt.to.getAttribute('data-position');
                    var bannerId = evt.item.getAttribute('data-banner-id');

                    evt.item.setAttribute('data-position', toPosition);
                    updateBannersOrderAndPosition(fromPosition, toPosition);
                }
            });
        });
    });

    function updateBannersOrderAndPosition(fromPosition, toPosition) {
        var updates = [];
        var positionContainers = document.querySelectorAll('.banner-position-container');

        positionContainers.forEach(function(container) {
            var position = container.getAttribute('data-position');
            var items = container.querySelectorAll('.banner-item');

            items.forEach(function(item, index) {
                var bannerId = item.getAttribute('data-banner-id');
                if (bannerId) {
                    updates.push({
                        id: parseInt(bannerId),
                        position: position,
                        sort_order: index
                    });
                    item.setAttribute('data-position', position);
                    item.setAttribute('data-sort-order', index);
                }
            });
        });

        $.ajax({
            url: "<?= BASE_URL("ajaxs/admin/update.php"); ?>",
            method: "POST",
            dataType: "JSON",
            data: {
                action: 'updateBannersOrderAndPosition',
                updates: JSON.stringify(updates)
            },
            success: function(result) {
                if (result.status == 'success') {
                    showMessage(result.msg || "<?= __('Đã cập nhật banner thành công!'); ?>", 'success');
                } else {
                    showMessage(result.msg, 'error');
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                }
            },
            error: function() {
                showMessage("<?= __('Đã xảy ra lỗi khi cập nhật'); ?>", 'error');
                setTimeout(function() {
                    location.reload();
                }, 2000);
            }
        });
    }

    function previewImage(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('previewImg').src = e.target.result;
                document.getElementById('imagePreview').style.display = 'block';
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    function previewEditImage(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('editPreviewImg').src = e.target.result;
                document.getElementById('editImagePreview').style.display = 'block';
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    function editBanner(bannerId) {
        $.ajax({
            url: "<?= BASE_URL('ajaxs/admin/view.php'); ?>",
            type: "POST",
            data: {
                action: "getBanner",
                id: bannerId
            },
            dataType: "json",
            success: function(response) {
                if (response.status == "success") {
                    var banner = response.data;
                    $('#edit_banner_id').val(banner.id);
                    $('#edit_title').val(banner.title);
                    $('#edit_link').val(banner.link);
                    $('#edit_position').val(banner.position);
                    $('#edit_sort_order').val(banner.sort_order);
                    $('#edit_status').val(banner.status);
                    $('#edit_current_image').attr('src', '<?= BASE_URL(); ?>' + banner.image);
                    $('#editImagePreview').hide();
                    $('#edit_banner_image').val('');
                    $('#editBannerModal').modal('show');
                } else {
                    showMessage(response.msg, 'error');
                }
            },
            error: function() {
                showMessage("<?= __('Có lỗi xảy ra. Vui lòng thử lại!'); ?>", 'error');
            }
        });
    }

    function submitEditBanner() {
        var formData = new FormData($('#editBannerForm')[0]);

        $.ajax({
            url: "<?= BASE_URL('ajaxs/admin/update.php'); ?>",
            type: "POST",
            data: formData,
            processData: false,
            contentType: false,
            dataType: "json",
            success: function(response) {
                if (response.status == "success") {
                    showMessage(response.msg, 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showMessage(response.msg, 'error');
                }
            },
            error: function() {
                showMessage("<?= __('Có lỗi xảy ra. Vui lòng thử lại!'); ?>", 'error');
            }
        });
    }

    function removeBanner(bannerId) {
        if (!confirm("<?= __('Bạn có chắc chắn muốn xóa banner này?'); ?>")) {
            return;
        }

        $.ajax({
            url: "<?= BASE_URL('ajaxs/admin/remove.php'); ?>",
            type: "POST",
            data: {
                action: "removeBanner",
                id: bannerId
            },
            dataType: "json",
            success: function(response) {
                if (response.status == "success") {
                    showMessage(response.msg, 'success');
                    $('#banner-' + bannerId).fadeOut(300, function() {
                        $(this).remove();
                    });
                } else {
                    showMessage(response.msg, 'error');
                }
            },
            error: function() {
                showMessage("<?= __('Có lỗi xảy ra. Vui lòng thử lại!'); ?>", 'error');
            }
        });
    }

    $('#addBannerModal').on('hidden.bs.modal', function() {
        $(this).find('form')[0].reset();
        $('#imagePreview').hide();
        $('#previewImg').attr('src', '');
    });
</script>