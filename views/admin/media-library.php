<?php if (!defined('IN_SITE')) {
    die('The Request Not Found');
}
$body = [
    'title' => __('Thư viện ảnh') . ' | ' . $CMSNT->site('title'),
    'desc'   => $CMSNT->site('description'),
    'keyword' => $CMSNT->site('keywords')
];
$body['header'] = '
<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/smoothness/jquery-ui.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/elfinder/2.1.65/css/elfinder.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/elfinder/2.1.65/css/theme.css">
<style>
    .elfinder {
        border: none !important;
        border-radius: 0 0 0.5rem 0.5rem;
    }
    .elfinder-navbar {
        background: var(--default-body-bg-color) !important;
    }
    .elfinder-workzone {
        background: var(--default-body-bg-color) !important;
    }
    .elfinder-cwd-view-icons .elfinder-cwd-file-wrapper {
        background: var(--custom-white) !important;
        border-radius: 0.5rem;
    }
    [data-theme-mode="dark"] .elfinder,
    [data-theme-mode="dark"] .elfinder-navbar,
    [data-theme-mode="dark"] .elfinder-workzone {
        background: #1a1d21 !important;
        color: #c5c5c5 !important;
    }
    [data-theme-mode="dark"] .elfinder-cwd-file .elfinder-cwd-filename {
        color: #c5c5c5 !important;
    }
    [data-theme-mode="dark"] .elfinder-button-search input {
        background: #2a2d31 !important;
        color: #c5c5c5 !important;
        border-color: #3a3d41 !important;
    }
</style>
';
$body['footer'] = '
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/elfinder/2.1.65/js/elfinder.min.js"></script>
<script>
$(document).ready(function() {
    $("#elfinder").elfinder({
        url: "' . BASE_URL("ajaxs/admin/elfinder-connector.php") . '",
        lang: "vi",
        height: 600,
        resizable: false,
        uiOptions: {
            toolbar: [
                ["home", "back", "forward", "up", "reload"],
                ["mkdir", "upload"],
                ["copy", "cut", "paste", "rm"],
                ["duplicate", "rename", "edit"],
                ["selectall", "selectnone", "selectinvert"],
                ["view", "sort"],
                ["search"],
                ["info", "help"]
            ]
        }
    });
});
</script>
';
require_once(__DIR__ . '/header.php');
require_once(__DIR__ . '/sidebar.php');
?>

<div class="main-content app-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0"><i class="fa-solid fa-images"></i> <?= __('Thư viện ảnh'); ?></h1>
            <div class="ms-md-1 ms-0">

            </div>
        </div>
        <!-- Page Header Close -->

        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card border border-dark">
                    <div class="card-header justify-content-between">
                        <div class="card-title">
                            <?= mb_strtoupper(__('Quản lý thư viện ảnh'), 'UTF-8'); ?>
                        </div>
                        <div class="d-flex gap-2">
                            <small class="text-muted">
                                <i class="fa-solid fa-circle-info me-1"></i>
                                <?= __('Chỉ chấp nhận: PNG, JPG, GIF, WEBP (tối đa 10MB)'); ?>
                            </small>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <!-- elFinder Container -->
                        <div id="elfinder" style="height: 600px;"></div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require_once(__DIR__ . '/footer.php'); ?>