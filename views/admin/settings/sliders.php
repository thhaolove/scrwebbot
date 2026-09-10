<?php if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

// Xử lý form thêm slider
if (isset($_POST['submit']) && isset($_POST['action']) && $_POST['action'] == 'addSlider') {
    // Kiểm tra quyền
    if (checkPermission($getUser['admin'], 'edit_sliders') != true) {
        die('<script type="text/javascript">if(!alert("' . __('Bạn không có quyền sử dụng tính năng này') . '")){window.history.back();}</script>');
    }
    // Kiểm tra CSRF token
    checkCSRF();

    if ($CMSNT->site('status_demo') != 0) {
        die('<script type="text/javascript">if(!alert("' . __('This function cannot be used because this is a demo site') . '")){window.history.back().location.reload();}</script>');
    }

    $title = validate_string($_POST['title'] ?? '', 255);
    $link = validate_string($_POST['link'] ?? '', 500);
    $sort_order = validate_int($_POST['sort_order'] ?? 0, 0, 9999);
    $status = validate_int($_POST['status'] ?? 1, 0, 1);

    if ($title === false) {
        $title = '';
    }
    if ($link === false) {
        $link = null;
    }
    if ($sort_order === false) {
        $sort_order = 0;
    }
    if ($status === false) {
        $status = 1;
    }

    // Kiểm tra upload ảnh
    if (!isset($_FILES['slider_image']) || check_img('slider_image') != true) {
        die('<script type="text/javascript">if(!alert("' . __('Vui lòng chọn ảnh hợp lệ') . '")){window.history.back();}</script>');
    }

    // Upload ảnh
    $rand = random('0123456789QWERTYUIOPASDGHJKLZXCVBNM', 6);
    $ext = pathinfo($_FILES['slider_image']['name'], PATHINFO_EXTENSION);
    $uploads_dir = 'assets/storage/images/slider_' . $rand . '.' . $ext;
    $tmp_name = $_FILES['slider_image']['tmp_name'];
    $addimage = move_uploaded_file($tmp_name, __DIR__ . '/../../../' . $uploads_dir);

    if (!$addimage) {
        die('<script type="text/javascript">if(!alert("' . __('Upload ảnh thất bại!') . '")){window.history.back();}</script>');
    }

    $isInsert = $CMSNT->insert("sliders", [
        'title'      => $title,
        'image'      => $uploads_dir,
        'link'       => $link,
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
            'action'     => __('Thêm slider mới')
        ]);

        /** NOTE ACTION */
        $my_text = $CMSNT->site('noti_action');
        $my_text = str_replace('{domain}', $_SERVER['SERVER_NAME'], $my_text);
        $my_text = str_replace('{username}', $getUser['username'], $my_text);
        $my_text = str_replace('{action}', __('Thêm slider mới'), $my_text);
        $my_text = str_replace('{ip}', myip(), $my_text);
        $my_text = str_replace('{time}', gettime(), $my_text);
        sendMessAdmin($my_text);

        admin_msg_success(__('Thêm slider thành công!'), base_url_admin('settings&tab=sliders'), 2000);
    } else {
        admin_msg_error(__('Thêm slider thất bại!'), "", 3000);
    }
}

// Lấy tất cả sliders
$sliders = $CMSNT->get_list("SELECT * FROM `sliders` ORDER BY `sort_order` ASC, `id` DESC");
?>

<div class="tab-pane text-muted show active" id="sliders" role="tabpanel">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h4 class="mb-0">
            <i class="fa-solid fa-images me-1"></i><?= __('Quản lý Slider'); ?>
            <?php if ($CMSNT->site('is_show_slider') == '1'): ?>
                <span class="badge bg-success ms-2"><i class="fa-solid fa-check-circle me-1"></i><?= __('ON'); ?></span>
            <?php else: ?>
                <span class="badge bg-secondary ms-2"><i class="fa-solid fa-pause-circle me-1"></i><?= __('OFF'); ?></span>
            <?php endif; ?>
        </h4>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addSliderModal">
            <i class="fa-solid fa-plus me-1"></i><?= __('Thêm Slider'); ?>
        </button>
    </div>

    <!-- Danh sách slider -->
    <div class="row">
        <div class="col-xl-12">
            <div class="card custom-card border">
                <div class="card-body p-0">
                    <?php if (count($sliders) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover border text-nowrap mb-0">
                                <thead>
                                    <tr>
                                        <th class="text-center" style="width: 50px;"><i class="fa-solid fa-grip-vertical"></i></th>
                                        <th style="width: 200px;"><?= __('Ảnh'); ?></th>
                                        <th><?= __('Tiêu đề'); ?></th>
                                        <th><?= __('Link'); ?></th>
                                        <th class="text-center" style="width: 100px;"><?= __('Thứ tự'); ?></th>
                                        <th class="text-center" style="width: 120px;"><?= __('Trạng thái'); ?></th>
                                        <th><?= __('Ngày tạo'); ?></th>
                                        <th class="text-center" style="width: 150px;"><?= __('Thao tác'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="sortable-sliders">
                                    <?php foreach ($sliders as $index => $slider): ?>
                                        <tr id="slider-<?= $slider['id']; ?>" data-slider-id="<?= $slider['id']; ?>" data-sort-order="<?= $slider['sort_order']; ?>">
                                            <td class="text-center handle-slider" style="cursor: move;">
                                                <i class="fa-solid fa-grip-vertical text-muted"></i>
                                            </td>
                                            <td>
                                                <img src="<?= BASE_URL($slider['image']); ?>"
                                                    alt="<?= htmlspecialchars($slider['title'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    class="rounded border"
                                                    style="width: 150px; height: 100px; object-fit: cover;">
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span class="fw-semibold">
                                                        <?= !empty($slider['title']) ? htmlspecialchars($slider['title'], ENT_QUOTES, 'UTF-8') : '<span class="text-muted">-</span>'; ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if (!empty($slider['link'])): ?>
                                                    <a href="<?= htmlspecialchars($slider['link'], ENT_QUOTES, 'UTF-8'); ?>"
                                                        target="_blank" class="text-primary">
                                                        <i class="fas fa-external-link-alt me-1"></i><?= __('Xem link'); ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-secondary"><?= $slider['sort_order']; ?></span>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($slider['status'] == 1): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fa-solid fa-check-circle me-1"></i><?= __('Hoạt động'); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">
                                                        <i class="fa-solid fa-pause-circle me-1"></i><?= __('Tạm dừng'); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small>
                                                    <i class="fa-regular fa-clock me-1"></i><?= date('d/m/Y H:i', strtotime($slider['created_at'])); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="btn-list justify-content-center">
                                                    <button onclick="editSlider('<?= $slider['id']; ?>')"
                                                        class="btn btn-sm btn-info">
                                                        <i class="fa-solid fa-edit me-1"></i><?= __('Sửa'); ?>
                                                    </button>
                                                    <button onclick="removeSlider('<?= $slider['id']; ?>')"
                                                        class="btn btn-sm btn-danger">
                                                        <i class="fa-solid fa-trash me-1"></i><?= __('Xóa'); ?>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center p-5">
                            <div class="mb-3">
                                <i class="fa-solid fa-images fs-48 text-muted"></i>
                            </div>
                            <h5 class="text-muted"><?= __('Chưa có slider nào'); ?></h5>
                            <p class="text-muted"><?= __('Hãy thêm slider mới để bắt đầu'); ?></p>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSliderModal">
                                <i class="fa-solid fa-plus me-1"></i><?= __('Thêm Slider đầu tiên'); ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal thêm slider -->
<div class="modal fade" id="addSliderModal" tabindex="-1" aria-labelledby="addSliderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addSliderModalLabel">
                    <i class="fa-solid fa-plus-circle me-2"></i><?= __('Thêm Slider mới'); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST" enctype="multipart/form-data">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="addSlider">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-lg-12 mb-3">
                            <label class="form-label"><?= __('Ảnh Slider'); ?> <span class="text-danger">*</span></label>
                            <input type="file" class="form-control" name="slider_image" id="slider_image"
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
                                placeholder="<?= __('Nhập tiêu đề slider (tùy chọn)'); ?>">
                        </div>

                        <div class="col-lg-6 mb-3">
                            <label class="form-label"><?= __('Link'); ?></label>
                            <input type="text" class="form-control" name="link"
                                placeholder="<?= __('https://example.com (tùy chọn)'); ?>">
                            <small class="text-muted"><?= __('Link khi click vào slider'); ?></small>
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

<!-- Modal sửa slider -->
<div class="modal fade" id="editSliderModal" tabindex="-1" aria-labelledby="editSliderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editSliderModalLabel">
                    <i class="fa-solid fa-edit me-2"></i><?= __('Chỉnh sửa Slider'); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editSliderForm" action="" method="POST" enctype="multipart/form-data">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="editSlider">
                <input type="hidden" name="slider_id" id="edit_slider_id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-lg-12 mb-3">
                            <label class="form-label"><?= __('Ảnh Slider hiện tại'); ?></label>
                            <div class="mb-2">
                                <img id="edit_current_image" src="" alt="Current Slider" class="rounded border"
                                    style="max-width: 100%; max-height: 200px; object-fit: contain;">
                            </div>
                            <label class="form-label"><?= __('Thay đổi ảnh (tùy chọn)'); ?></label>
                            <input type="file" class="form-control" name="slider_image" id="edit_slider_image"
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
                                placeholder="<?= __('Nhập tiêu đề slider (tùy chọn)'); ?>">
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
                    <button type="button" class="btn btn-primary" onclick="submitEditSlider()">
                        <i class="fa-solid fa-save me-1"></i><?= __('Cập nhật'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script>
    // Khởi tạo Sortable cho bảng sliders
    document.addEventListener('DOMContentLoaded', function() {
        var sortableSlidersEl = document.getElementById('sortable-sliders');
        if (sortableSlidersEl) {
            new Sortable(sortableSlidersEl, {
                handle: '.handle-slider',
                animation: 150,
                onEnd: function(evt) {
                    updateSlidersOrder();
                }
            });
        }
    });

    // Cập nhật thứ tự sliders
    function updateSlidersOrder() {
        var order = [];
        $('#sortable-sliders tr').each(function(index) {
            var sliderId = $(this).data('slider-id');
            if (sliderId) {
                order.push({
                    id: sliderId,
                    sort_order: index
                });
            }
        });

        $.ajax({
            url: "<?= BASE_URL("ajaxs/admin/update.php"); ?>",
            method: "POST",
            dataType: "JSON",
            data: {
                action: 'updateSlidersOrder',
                order: JSON.stringify(order)
            },
            success: function(result) {
                if (result.status == 'success') {
                    showMessage(result.msg || "<?= __('Đã lưu thứ tự slider thành công!'); ?>", 'success');
                    $('#sortable-sliders tr').each(function(index) {
                        $(this).attr('data-sort-order', index);
                    });
                } else {
                    showMessage(result.msg, 'error');
                }
            },
            error: function() {
                showMessage("<?= __('Đã xảy ra lỗi khi cập nhật thứ tự'); ?>", 'error');
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

    function editSlider(sliderId) {
        $.ajax({
            url: "<?= BASE_URL('ajaxs/admin/view.php'); ?>",
            type: "POST",
            data: {
                action: "getSlider",
                id: sliderId
            },
            dataType: "json",
            success: function(response) {
                if (response.status == "success") {
                    var slider = response.data;
                    $('#edit_slider_id').val(slider.id);
                    $('#edit_title').val(slider.title);
                    $('#edit_link').val(slider.link);
                    $('#edit_sort_order').val(slider.sort_order);
                    $('#edit_status').val(slider.status);
                    $('#edit_current_image').attr('src', '<?= BASE_URL(); ?>' + slider.image);
                    $('#editImagePreview').hide();
                    $('#edit_slider_image').val('');
                    $('#editSliderModal').modal('show');
                } else {
                    showMessage(response.msg, 'error');
                }
            },
            error: function() {
                showMessage("<?= __('Có lỗi xảy ra. Vui lòng thử lại!'); ?>", 'error');
            }
        });
    }

    function submitEditSlider() {
        var formData = new FormData($('#editSliderForm')[0]);

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

    function removeSlider(sliderId) {
        if (!confirm("<?= __('Bạn có chắc chắn muốn xóa slider này?'); ?>")) {
            return;
        }

        $.ajax({
            url: "<?= BASE_URL('ajaxs/admin/remove.php'); ?>",
            type: "POST",
            data: {
                action: "removeSlider",
                id: sliderId
            },
            dataType: "json",
            success: function(response) {
                if (response.status == "success") {
                    showMessage(response.msg, 'success');
                    $('#slider-' + sliderId).fadeOut(300, function() {
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

    $('#addSliderModal').on('hidden.bs.modal', function() {
        $(this).find('form')[0].reset();
        $('#imagePreview').hide();
        $('#previewImg').attr('src', '');
    });
</script>