<?php if (!defined('IN_SITE')) {
    die('The Request Not Found');
}


// Lấy danh sách slider đang hoạt động
$sliders = $CMSNT->get_list_safe("SELECT * FROM `sliders` WHERE `status` = 1 ORDER BY `sort_order` ASC, `id` DESC", []);

// Lấy banner từ database
$banners_top = $CMSNT->get_list_safe("SELECT * FROM `banners` WHERE `status` = 1 AND `position` = ? ORDER BY `sort_order` ASC, `id` DESC LIMIT 1", ['top']);

// Lấy tất cả banner từ below_sliders
$banners_below_sliders = $CMSNT->get_list_safe("SELECT * FROM `banners` WHERE `status` = 1 AND `position` = ? ORDER BY `sort_order` ASC, `id` DESC", ['below_sliders']);


?>

<!-- Banner Top (nếu có) -->
<?php if (count($banners_top) > 0): ?>
    <div class="home-banner-top mb-3">
        <?php foreach ($banners_top as $banner): ?>
            <a href="<?= !empty($banner['link']) ? htmlspecialchars($banner['link'], ENT_QUOTES, 'UTF-8') : 'javascript:void(0);'; ?>"
                class="home-banner-link" <?= !empty($banner['link']) ? 'target="_blank"' : ''; ?>>
                <img src="<?= BASE_URL($banner['image']); ?>"
                    alt="<?= htmlspecialchars($banner['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="home-banner-img">
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Slider -->
<?php if (count($sliders) > 0): ?>
    <div class="home-slider-wrapper">
        <div class="home-slider-container" id="homeSlider">
            <div class="home-slider-track" id="homeSliderTrack">
                <?php foreach ($sliders as $index => $slider): ?>
                    <div class="home-slider-item" data-slide-index="<?= $index; ?>">
                        <?php if (!empty($slider['link'])): ?>
                            <a href="<?= htmlspecialchars($slider['link'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php endif; ?>
                            <img src="<?= BASE_URL($slider['image']); ?>"
                                alt="<?= htmlspecialchars($slider['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                draggable="false">
                            <?php if (!empty($slider['link'])): ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Navigation Buttons -->
            <?php if (count($sliders) > 1): ?>
                <button class="home-slider-nav prev" onclick="changeSlide(-1)">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
                <button class="home-slider-nav next" onclick="changeSlide(1)">
                    <i class="fa-solid fa-chevron-right"></i>
                </button>

                <!-- Indicators -->
                <div class="home-slider-indicators">
                    <?php foreach ($sliders as $index => $slider): ?>
                        <span class="home-slider-indicator <?= $index === 0 ? 'active' : ''; ?>"
                            onclick="goToSlide(<?= $index; ?>)"></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Banner dưới slider -->
<?php if (count($banners_below_sliders) > 0): ?>
    <div class="home-banner-below mt-3 d-none d-md-block">
        <div class="home-banner-carousel-wrapper">
            <button class="home-banner-carousel-nav prev" onclick="changeBannerSlide(-1)">
                <i class="fa-solid fa-chevron-left"></i>
            </button>
            <div class="home-banner-carousel-container" id="homeBannerCarousel">
                <div class="home-banner-carousel-track">
                    <?php foreach ($banners_below_sliders as $index => $banner): ?>
                        <div class="home-banner-carousel-item" data-banner-index="<?= $index; ?>">
                            <a href="<?= !empty($banner['link']) ? htmlspecialchars($banner['link'], ENT_QUOTES, 'UTF-8') : 'javascript:void(0);'; ?>"
                                class="home-banner-link" <?= !empty($banner['link']) ? 'target="_blank"' : ''; ?>>
                                <img src="<?= BASE_URL($banner['image']); ?>"
                                    alt="<?= htmlspecialchars($banner['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    class="home-banner-img"
                                    draggable="false">
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <button class="home-banner-carousel-nav next" onclick="changeBannerSlide(1)">
                <i class="fa-solid fa-chevron-right"></i>
            </button>
        </div>
    </div>
<?php endif; ?>