<?php if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

// Xử lý lưu hình ảnh
if (isset($_POST['SaveImages'])) {
    // Kiểm tra quyền
    if (checkPermission($getUser['admin'], 'edit_theme') != true) {
        die('<script type="text/javascript">if(!alert("' . __('Bạn không có quyền sử dụng tính năng này') . '")){window.history.back();}</script>');
    }
    // Kiểm tra CSRF token
    checkCSRF();

    if ($CMSNT->site('status_demo') != 0) {
        die('<script type="text/javascript">if(!alert("' . __('This function cannot be used because this is a demo site') . '")){window.history.back().location.reload();}</script>');
    }
    if (check_img('logo_light') == true) {
        unlink($CMSNT->site('logo_light'));
        $rand = random('0123456789QWERTYUIOPASDGHJKLZXCVBNM', 3);
        $uploads_dir = 'assets/storage/images/logo_light_' . $rand . '.png';
        $tmp_name = $_FILES['logo_light']['tmp_name'];
        $addlogo = move_uploaded_file($tmp_name, $uploads_dir);
        if ($addlogo) {
            $CMSNT->update('settings', [
                'value'  => $uploads_dir
            ], " `name` = 'logo_light' ");
        }
    }
    if (check_img('logo_dark') == true) {
        unlink($CMSNT->site('logo_dark'));
        $rand = random('0123456789QWERTYUIOPASDGHJKLZXCVBNM', 3);
        $uploads_dir = 'assets/storage/images/logo_dark_' . $rand . '.png';
        $tmp_name = $_FILES['logo_dark']['tmp_name'];
        $addlogo = move_uploaded_file($tmp_name, $uploads_dir);
        if ($addlogo) {
            $CMSNT->update('settings', [
                'value'  => $uploads_dir
            ], " `name` = 'logo_dark' ");
        }
    }
    if (check_img('favicon') == true) {
        unlink($CMSNT->site('favicon'));
        $rand = random('0123456789QWERTYUIOPASDGHJKLZXCVBNM', 3);
        $uploads_dir = 'assets/storage/images/favicon_' . $rand . '.png';
        $tmp_name = $_FILES['favicon']['tmp_name'];
        $addlogo = move_uploaded_file($tmp_name, $uploads_dir);
        if ($addlogo) {
            $CMSNT->update('settings', [
                'value'  => $uploads_dir
            ], " `name` = 'favicon' ");
        }
    }
    if (check_img('image') == true) {
        unlink($CMSNT->site('image'));
        $rand = random('0123456789QWERTYUIOPASDGHJKLZXCVBNM', 3);
        $uploads_dir = 'assets/storage/images/image_' . $rand . '.png';
        $tmp_name = $_FILES['image']['tmp_name'];
        $addlogo = move_uploaded_file($tmp_name, $uploads_dir);
        if ($addlogo) {
            $CMSNT->update('settings', [
                'value'  => $uploads_dir
            ], " `name` = 'image' ");
        }
    }
    if (check_img('avatar') == true) {
        unlink($CMSNT->site('avatar'));
        $rand = random('0123456789QWERTYUIOPASDGHJKLZXCVBNM', 3);
        $uploads_dir = 'assets/storage/images/avatar' . $rand . '.png';
        $tmp_name = $_FILES['avatar']['tmp_name'];
        $addlogo = move_uploaded_file($tmp_name, $uploads_dir);
        if ($addlogo) {
            $CMSNT->update('settings', [
                'value'  => $uploads_dir
            ], " `name` = 'avatar' ");
        }
    }



    $CMSNT->insert("logs", [
        'user_id'       => $getUser['id'],
        'ip'            => myip(),
        'device'        => getUserAgent(),
        'createdate'    => gettime(),
        'action'        => __('Thay đổi ảnh giao diện website')
    ]);
    /** NOTE ACTION */
    $my_text = $CMSNT->site('noti_action');
    $my_text = str_replace('{domain}', $_SERVER['SERVER_NAME'], $my_text);
    $my_text = str_replace('{username}', $getUser['username'], $my_text);
    $my_text = str_replace('{action}', __("Thay đổi ảnh giao diện website"), $my_text);
    $my_text = str_replace('{ip}', myip(), $my_text);
    $my_text = str_replace('{time}', gettime(), $my_text);
    sendMessAdmin($my_text);

    admin_msg_success("Lưu thành công!", "", 1000);
}
?>

<div class="tab-pane text-muted show active" id="theme" role="tabpanel">
    <h4><?= __('Hình ảnh'); ?></h4>
    <form action="" method="POST" enctype="multipart/form-data">
        <?php echo csrfField(); ?>
        <div class="row">
            <!-- Logo Light -->
            <div class="col-lg-6 mb-4">
                <div class="form-group">
                    <label for="logo_light" class="form-label">Logo Light</label>
                    <input type="file" class="form-control" name="logo_light" id="logo_light">
                </div>
                <div class="mt-2">
                    <img width="250px" class="bg-light rounded p-3"
                        src="<?= BASE_URL($CMSNT->site('logo_light')); ?>" alt="Logo Light">
                </div>
            </div>

            <!-- Logo Dark -->
            <div class="col-lg-6 mb-4">
                <div class="form-group">
                    <label for="logo_dark" class="form-label">Logo Dark</label>
                    <input type="file" class="form-control" name="logo_dark" id="logo_dark">
                </div>
                <div class="mt-2">
                    <img width="250px" class="bg-light rounded p-3"
                        src="<?= BASE_URL($CMSNT->site('logo_dark')); ?>" alt="Logo Dark">
                </div>
            </div>

            <!-- Favicon -->
            <div class="col-lg-6 mb-4">
                <div class="form-group">
                    <label for="favicon" class="form-label">Favicon</label>
                    <input type="file" class="form-control" name="favicon" id="favicon">
                </div>
                <div class="mt-2">
                    <img width="50px" class="rounded-circle"
                        src="<?= BASE_URL($CMSNT->site('favicon')); ?>" alt="Favicon">
                </div>
            </div>

            <!-- Image -->
            <div class="col-lg-6 mb-4">
                <div class="form-group">
                    <label for="image" class="form-label">Image</label>
                    <input type="file" class="form-control" name="image" id="image">
                </div>
                <div class="mt-2">
                    <img width="250px" class="rounded"
                        src="<?= BASE_URL($CMSNT->site('image')); ?>" alt="Image">
                </div>
            </div>


            <!-- Avatar -->
            <div class="col-lg-6 mb-4">
                <div class="form-group">
                    <label for="avatar" class="form-label">Avatar</label>
                    <input type="file" class="form-control" name="avatar" id="avatar">
                </div>
                <div class="mt-2">
                    <img width="250px" class="rounded-circle"
                        src="<?= BASE_URL($CMSNT->site('avatar')); ?>" alt="Avatar">
                </div>
            </div>


        </div>
        <button type="submit" name="SaveImages" class="btn btn-primary w-100 mb-3">
            <i class="fa fa-fw fa-save me-1"></i> <?= __('Save'); ?>
        </button>
    </form>
</div>