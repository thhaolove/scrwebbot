<?php if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

if ($CMSNT->site('isLoginRequiredToViewProduct') == 1) {
    require_once(__DIR__ . '/../../models/is_user.php');
} else {
    if (isSecureCookie('user_login') == true) {
        require_once(__DIR__ . '/../../models/is_user.php');
    }
}


// Lấy tham số từ URL - hỗ trợ cả slug và ID
$category_slug = isset($_GET['category']) ? check_string($_GET['category']) : '';
$parent_slug = isset($_GET['parent']) ? check_string($_GET['parent']) : '';
$search_keyword = isset($_GET['keyword']) ? trim(check_string($_GET['keyword'])) : '';

$category_id = 0;
$parent_id = 0;

// Lấy thông tin category nếu có
$current_category = null;
$page_title = __('Tất cả sản phẩm');

// Nếu có keyword tìm kiếm, cập nhật tiêu đề
if (!empty($search_keyword)) {
    $page_title = __('Kết quả tìm kiếm: ') . $search_keyword;
}

// Xử lý category slug hoặc ID
if (!empty($category_slug)) {
    // Nếu là số thì tìm theo ID, nếu không thì tìm theo slug
    if (is_numeric($category_slug)) {
        $category_id = validate_int($category_slug, 1) ?: 0;
        $current_category = $CMSNT->get_row_safe("SELECT * FROM `categories` WHERE `id` = ? AND `status` = 'show'", [$category_id]);
    } else {
        $current_category = $CMSNT->get_row_safe("SELECT * FROM `categories` WHERE `slug` = ? AND `status` = 'show'", [$category_slug]);
        if ($current_category) {
            $category_id = (int)$current_category['id'];
        }
    }
    if ($current_category) {
        $page_title = html_entity_decode($current_category['name'], ENT_QUOTES, 'UTF-8');
    }
} elseif (!empty($parent_slug)) {
    // Xử lý parent slug hoặc ID
    if (is_numeric($parent_slug)) {
        $parent_id = validate_int($parent_slug, 1) ?: 0;
        $current_category = $CMSNT->get_row_safe("SELECT * FROM `categories` WHERE `id` = ? AND `parent_id` = 0 AND `status` = 'show'", [$parent_id]);
    } else {
        $current_category = $CMSNT->get_row_safe("SELECT * FROM `categories` WHERE `slug` = ? AND `parent_id` = 0 AND `status` = 'show'", [$parent_slug]);
        if ($current_category) {
            $parent_id = (int)$current_category['id'];
        }
    }
    if ($current_category) {
        $page_title = html_entity_decode($current_category['name'], ENT_QUOTES, 'UTF-8');
    }
}

// Lấy danh sách chuyên mục cha
$parent_categories = get_categories_parent_cached();
// Lấy danh sách chuyên mục con
$child_categories = get_categories_not_parent_cached();

$body = [
    'title' => $page_title . ' | ' . $CMSNT->site('title'),
    'desc'  => $CMSNT->site('description'),
    'keyword' => $CMSNT->site('keywords')
];

$body['header'] = '
<link rel="stylesheet" href="' . base_url('mod/css/product.css') . '">
<link rel="stylesheet" href="' . base_url('mod/css/products-filter.css') . '">
';

// Lấy slug của category hiện tại
$current_category_slug = $current_category ? $current_category['slug'] : '';

$body['footer'] = '
<script>
    var BASE_URL = "' . base_url() . '";
    var USER_TOKEN = "' . (isset($getUser) ? addslashes($getUser['token']) : '') . '";
    var INITIAL_CATEGORY_ID = ' . ($category_id ?: 0) . ';
    var INITIAL_PARENT_ID = ' . ($parent_id ?: 0) . ';
    var INITIAL_CATEGORY_SLUG = "' . addslashes($current_category_slug) . '";
    var INITIAL_KEYWORD = "' . addslashes($search_keyword) . '";
    function base_url(path) { return BASE_URL + (path || ""); }
    // Translation strings
    var TRANSLATIONS = {
        sold: "' . addslashes(__('Đã bán')) . '",
        deliveryInstant: "' . addslashes(__('Giao ngay')) . '",
        deliveryOrder: "' . addslashes(__('Order')) . '"
    };
    var IS_SHOW_SOLD = ' . ($CMSNT->site('isShowSold') == 1 ? 'true' : 'false') . ';
</script>
<script src="' . base_url('mod/js/products-filter.js?v=2') . '"></script>
';



require_once(__DIR__ . '/header.php');
require_once(__DIR__ . '/nav.php');
?>

<!-- Page Header with Breadcrumb -->
<div class="page-header-modern page-header-compact">
    <div class="container">
        <nav class="breadcrumb-modern">
            <a href="<?= base_url(); ?>"><i class="fa-solid fa-home"></i> <?= __('Trang chủ'); ?></a>
            <span class="separator">›</span>
            <?php if ($current_category): ?>
                <a href="<?= base_url('products'); ?>"><?= __('Sản phẩm'); ?></a>
                <span class="separator">›</span>
                <span class="current"><?= htmlspecialchars(html_entity_decode($current_category['name'], ENT_QUOTES, 'UTF-8')); ?></span>
            <?php else: ?>
                <span class="current"><?= __('Sản phẩm'); ?></span>
            <?php endif; ?>
        </nav>
        <h1 class="page-title-modern">
            <?php if ($current_category && $current_category['icon'] != null && file_exists($current_category['icon'])): ?>
                <img src="<?= base_url($current_category['icon']); ?>" alt="" class="page-title-icon">
            <?php else: ?>
                <i class="fa-solid fa-shopping-bag"></i>
            <?php endif; ?>
            <?= htmlspecialchars($page_title); ?>
        </h1>
        <p class="page-subtitle-modern">
            <?php
            if ($current_category && !empty($current_category['description'])) {
                echo htmlspecialchars($current_category['description']);
            } else {
                echo __('Khám phá bộ sưu tập sản phẩm chất lượng cao được chọn lọc dành riêng cho bạn');
            }
            ?>
        </p>
    </div>
</div>

<div class="products-page">
    <div class="container">

        <!-- Filter Section -->
        <div class="products-filter-section">
            <div class="products-filter-wrapper">
                <!-- Category Filter -->
                <div class="filter-group">
                    <label class="filter-label"><?= __('Danh mục'); ?></label>
                    <div class="filter-select-wrapper">
                        <select id="filterParentCategory" class="filter-select">
                            <option value=""><?= __('Tất cả'); ?></option>
                            <?php foreach ($parent_categories as $cat): ?>
                                <option value="<?= $cat['id']; ?>" data-slug="<?= htmlspecialchars($cat['slug']); ?>" <?= $parent_id == $cat['id'] ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars(html_entity_decode($cat['name'], ENT_QUOTES, 'UTF-8')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fa-solid fa-chevron-down filter-select-icon"></i>
                    </div>
                </div>

                <!-- Sub Category Filter -->
                <div class="filter-group">
                    <label class="filter-label"><?= __('Thể loại'); ?></label>
                    <div class="filter-select-wrapper">
                        <select id="filterChildCategory" class="filter-select">
                            <option value=""><?= __('Tất cả'); ?></option>
                            <?php foreach ($child_categories as $cat): ?>
                                <option value="<?= $cat['id']; ?>" data-parent="<?= $cat['parent_id']; ?>" data-slug="<?= htmlspecialchars($cat['slug']); ?>" <?= $category_id == $cat['id'] ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars(html_entity_decode($cat['name'], ENT_QUOTES, 'UTF-8')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fa-solid fa-chevron-down filter-select-icon"></i>
                    </div>
                </div>

                <!-- Price Range Filter -->
                <div class="filter-group filter-group-price">
                    <label class="filter-label"><?= __('Mức giá'); ?></label>
                    <div class="filter-price-inputs">
                        <input type="text" id="filterPriceMin" class="filter-input" placeholder="<?= __('Mức giá từ'); ?>">
                        <span class="filter-price-separator">-</span>
                        <input type="text" id="filterPriceMax" class="filter-input" placeholder="<?= __('Mức giá đến'); ?>">
                    </div>
                </div>

                <!-- Sort Filter -->
                <div class="filter-group">
                    <label class="filter-label"><?= __('Sắp xếp'); ?></label>
                    <div class="filter-select-wrapper">
                        <select id="filterSort" class="filter-select">
                            <option value="default"><?= __('Mặc định'); ?></option>
                            <option value="price_asc"><?= __('Giá tăng dần'); ?></option>
                            <option value="price_desc"><?= __('Giá giảm dần'); ?></option>
                            <option value="newest"><?= __('Mới nhất'); ?></option>
                            <option value="name_asc"><?= __('Tên A-Z'); ?></option>
                            <option value="name_desc"><?= __('Tên Z-A'); ?></option>
                        </select>
                        <i class="fa-solid fa-chevron-down filter-select-icon"></i>
                    </div>
                </div>

                <!-- Filter Buttons -->
                <div class="filter-group filter-group-buttons">
                    <div class="filter-buttons">
                        <button type="button" id="btnApplyFilter" class="btn-filter">
                            <i class="fa-solid fa-filter"></i>
                            <?= __('Lọc'); ?>
                        </button>
                        <button type="button" id="btnResetFilter" class="btn-reset-filter">
                            <i class="fa-solid fa-rotate-left"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Products Results Info -->
        <div class="products-results-info" id="productsResultsInfo">
            <span class="results-count">
                <span id="resultsCount">0</span> <?= __('sản phẩm'); ?>
            </span>
        </div>

        <!-- Products Grid -->
        <div class="products-grid" id="productsGrid"></div>

        <!-- Loading Skeleton -->
        <div class="products-loading" id="productsLoading" style="display: none;">
            <div class="products-grid">
                <?php for ($i = 0; $i < 12; $i++): ?>
                    <div class="product-card skeleton">
                        <div class="skeleton-image"></div>
                        <div class="skeleton-content">
                            <div class="skeleton-title"></div>
                            <div class="skeleton-price"></div>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Empty State -->
        <div class="products-empty-state" id="productsEmptyState" style="display: none;">
            <div class="empty-state-icon">
                <i class="fa-solid fa-box-open"></i>
            </div>
            <div class="empty-state-text">
                <h4><?= __('Không tìm thấy sản phẩm'); ?></h4>
                <p><?= __('Không có sản phẩm nào phù hợp với bộ lọc của bạn. Hãy thử điều chỉnh bộ lọc.'); ?></p>
            </div>
        </div>

        <!-- Load More Button -->
        <div class="products-load-more" id="productsLoadMore" style="display: none;">
            <button type="button" class="btn-load-more" id="btnLoadMore">
                <span id="loadMoreSpinner" style="display: none;"><i class="fa-solid fa-spinner fa-spin me-2"></i></span>
                <span id="loadMoreText"><?= __('Tải thêm sản phẩm'); ?></span>
            </button>
        </div>
    </div>
</div>

<?php require_once(__DIR__ . '/footer.php'); ?>