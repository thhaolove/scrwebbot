<?php if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

/**
 * Widget: Recently Viewed Products
 * Hiển thị các sản phẩm người dùng đã xem gần đây
 * Sử dụng localStorage để lưu lịch sử xem (hoạt động cho cả khách và user đăng nhập)
 * Style giống với section Products để đồng nhất giao diện
 */

// Chỉ hiển thị widget nếu setting được bật
if ($CMSNT->site('is_show_recently_viewed') != 1) {
    return;
}
?>
<hr class="home-section-divider">

<!-- Recently Viewed Products Widget -->
<div class="recently-viewed-section home-products-section" id="recentlyViewedSection" style="display: none;">
    <!-- Section Header - Match Products Section Style -->
    <div class="products-section-header">
        <div class="products-section-header-left">
            <h3 class="products-section-title">
                <i class="fa-solid fa-clock-rotate-left" style="color: var(--primary); margin-right: 8px;"></i>
                <?= __('Đã xem gần đây'); ?>
            </h3>
            <p class="products-section-subtitle" id="recentlyViewedCount"><?= __('Các sản phẩm bạn đã xem trước đó'); ?></p>
        </div>
        <a href="<?= base_url('products'); ?>" class="btn-explore">
            <?= __('Xem tất cả'); ?>
            <i class="fa-solid fa-arrow-right"></i>
        </a>
    </div>

    <!-- Products Carousel -->
    <div class="recently-viewed-carousel-wrapper">
        <button class="recently-viewed-nav recently-viewed-nav-prev" onclick="scrollRecentlyViewed(-1)" aria-label="Previous">
            <i class="fa-solid fa-chevron-left"></i>
        </button>

        <div class="recently-viewed-carousel" id="recentlyViewedCarousel">
            <!-- Products will be loaded via JS -->
        </div>

        <button class="recently-viewed-nav recently-viewed-nav-next" onclick="scrollRecentlyViewed(1)" aria-label="Next">
            <i class="fa-solid fa-chevron-right"></i>
        </button>
    </div>
</div>