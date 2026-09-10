<?php if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

// Kiểm tra FlashSaleHandler đã được load chưa
if (!class_exists('FlashSaleHandler')) {
    require_once(__DIR__ . '/../../../libs/database/flashsale.php');
}

$FlashSaleHandler = new FlashSaleHandler();

// Lấy Flash Sale đang active
$active_flash_sales = $FlashSaleHandler->getFlashSales('active', 5, 0);

// Nếu có Flash Sale active, lấy products
$flash_sale_products = [];
$flash_sale_info = null;

if (!empty($active_flash_sales)) {
    // Lấy Flash Sale đầu tiên (hoặc flash sale đang có sản phẩm)
    foreach ($active_flash_sales as $flash_sale) {
        $flash_sale_items = $FlashSaleHandler->getFlashSaleItems($flash_sale['id']);

        if (!empty($flash_sale_items)) {
            $flash_sale_info = $flash_sale;

            // Lấy thông tin product chi tiết cho mỗi item
            foreach ($flash_sale_items as $item) {
                $product = null;
                $plan = null;

                // Nếu item có plan_id, lấy product từ plan
                if (!empty($item['plan_id'])) {
                    // Lấy plan và product liên quan
                    $plan = $CMSNT->get_row_safe(
                        "SELECT pp.*, p.id as product_id, p.name as product_name, p.slug as product_slug, 
                                p.image as product_image, p.status as product_status
                         FROM `product_plans` pp
                         LEFT JOIN `products` p ON pp.product_id = p.id
                         WHERE pp.id = ? AND pp.status = 1 AND p.status = 1",
                        [$item['plan_id']]
                    );

                    if ($plan) {
                        $product = [
                            'id' => $plan['product_id'],
                            'name' => $plan['product_name'],
                            'slug' => $plan['product_slug'],
                            'image' => $plan['product_image']
                        ];
                    }
                }
                // Nếu item có product_id, lấy product trực tiếp
                elseif (!empty($item['product_id'])) {
                    $product = $CMSNT->get_row_safe(
                        "SELECT p.*
                         FROM `products` p
                         WHERE p.id = ? AND p.status = 1",
                        [$item['product_id']]
                    );

                    // Lấy plan có giá thấp nhất
                    if ($product) {
                        $plan = $CMSNT->get_row_safe(
                            "SELECT * FROM `product_plans` 
                             WHERE `product_id` = ? AND `status` = 1 
                             ORDER BY `price` ASC LIMIT 1",
                            [$product['id']]
                        );
                    }
                }

                // Nếu có product và plan, tính giá và thêm vào danh sách
                if ($product && $plan) {
                    // Sử dụng flash_price từ item nếu có, nếu không tính từ flash_sale
                    $item_for_calc = $flash_sale;
                    if (!empty($item['flash_price']) && $item['flash_price'] > 0) {
                        $item_for_calc['flash_price'] = $item['flash_price'];
                    }

                    $flash_price = $FlashSaleHandler->calculateFlashSalePrice($plan, $item_for_calc);
                    $original_price = ($plan['sale_price'] > 0 && $plan['sale_price'] < $plan['price'])
                        ? $plan['sale_price']
                        : $plan['price'];

                    // Tính phần trăm giảm
                    $discount_percent = 0;
                    if ($original_price > 0 && $flash_price < $original_price) {
                        $discount_percent = round((($original_price - $flash_price) / $original_price) * 100);
                    }

                    // Kiểm tra có hàng giao ngay không
                    $instant_stock = $CMSNT->get_row_safe(
                        "SELECT COUNT(*) as total FROM `product_stock` 
                         WHERE `plan_id` = ? AND `status` = 1",
                        [$plan['id']]
                    );
                    $is_instant = $instant_stock && $instant_stock['total'] > 0;

                    // Lấy rating
                    $rating_data = $CMSNT->get_row_safe(
                        "SELECT AVG(rating) as avg_rating, COUNT(*) as count 
                         FROM `product_reviews` 
                         WHERE `product_id` = ? AND `status` = 1",
                        [$product['id']]
                    );

                    // Lấy số đã bán
                    $sold_data = $CMSNT->get_row_safe(
                        "SELECT COUNT(*) as total FROM `product_orders` 
                         WHERE `product_id` = ? AND `status` = 'completed'",
                        [$product['id']]
                    );

                    // Kiểm tra xem product này đã được thêm chưa (tránh trùng lặp)
                    $already_added = false;
                    foreach ($flash_sale_products as $added_product) {
                        if ($added_product['id'] == $product['id']) {
                            $already_added = true;
                            break;
                        }
                    }

                    if (!$already_added) {
                        $flash_sale_products[] = [
                            'id' => $product['id'],
                            'name' => $product['name'],
                            'slug' => $product['slug'],
                            'image' => !empty($product['image']) ? BASE_URL($product['image']) : '',
                            'url' => base_url('product/' . $product['slug']),
                            'original_price' => $original_price,
                            'flash_price' => $flash_price,
                            'discount_percent' => $discount_percent,
                            'is_instant' => $is_instant,
                            'rating' => $rating_data ? round($rating_data['avg_rating'] ?? 0, 1) : 0,
                            'rating_count' => $rating_data ? (int)$rating_data['count'] : 0,
                            'sold' => $sold_data ? (int)$sold_data['total'] : 0,
                            'quantity_limit' => $flash_sale['quantity_limit'],
                            'quantity_sold' => $flash_sale['quantity_sold']
                        ];
                    }
                }
            }

            // Nếu đã có sản phẩm, thoát khỏi vòng lặp
            if (!empty($flash_sale_products)) {
                break;
            }
        }
    }
}

// Chỉ hiển thị widget nếu có sản phẩm Flash Sale
if (!empty($flash_sale_products) && $flash_sale_info):
    // Tính thời gian còn lại
    $end_time = strtotime($flash_sale_info['end_time']);
    $remaining_seconds = max(0, $end_time - time());
?>

    <!-- Flash Sale Products Widget -->
    <div class="flashsale-section" id="flashSaleWidget" data-end-time="<?= $end_time; ?>">
        <!-- Header -->
        <div class="flashsale-header">
            <div class="flashsale-header-content">
                <div class="flashsale-brand">
                    <div class="flashsale-logo">
                        <i class="fa-solid fa-bolt"></i>
                    </div>
                    <div class="flashsale-title-group">
                        <h3 class="flashsale-title"><?= __('FLASH SALE'); ?></h3>
                        <span class="flashsale-subtitle"><?= htmlspecialchars($flash_sale_info['name']); ?></span>
                    </div>
                </div>
            </div>

            <div class="flashsale-timer">
                <span class="timer-label"><?= __('Kết thúc sau'); ?></span>
                <div class="timer-countdown">
                    <div class="timer-block timer-days" id="countdownDaysBlock" style="display: none;">
                        <span class="timer-number" id="countdownDays">00</span>
                        <span class="timer-text"><?= __('Ngày'); ?></span>
                    </div>
                    <span class="timer-colon timer-days-colon" id="countdownDaysColon" style="display: none;">:</span>
                    <div class="timer-block">
                        <span class="timer-number" id="countdownHours">00</span>
                        <span class="timer-text"><?= __('Giờ'); ?></span>
                    </div>
                    <span class="timer-colon">:</span>
                    <div class="timer-block">
                        <span class="timer-number" id="countdownMinutes">00</span>
                        <span class="timer-text"><?= __('Phút'); ?></span>
                    </div>
                    <span class="timer-colon">:</span>
                    <div class="timer-block">
                        <span class="timer-number" id="countdownSeconds">00</span>
                        <span class="timer-text"><?= __('Giây'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Products Carousel -->
        <div class="flashsale-carousel-wrapper">
            <button class="flashsale-nav flashsale-nav-prev" onclick="scrollFlashSale(-1)" aria-label="Previous">
                <i class="fa-solid fa-chevron-left"></i>
            </button>

            <div class="flashsale-carousel" id="flashSaleCarousel">
                <?php foreach ($flash_sale_products as $index => $product): ?>
                    <div class="flashsale-item">
                        <div class="product-card">
                            <a href="<?= htmlspecialchars($product['url'], ENT_QUOTES, 'UTF-8'); ?>" class="product-card-link">
                                <!-- Image -->
                                <div class="product-card-image">
                                    <?php if (!empty($product['image'])): ?>
                                        <img
                                            class="lazy"
                                            data-src="<?= htmlspecialchars($product['image'], ENT_QUOTES, 'UTF-8'); ?>"
                                            src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 200 200'%3E%3Crect fill='%23f3f4f6' width='200' height='200'/%3E%3C/svg%3E"
                                            alt="<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                            loading="lazy">
                                    <?php else: ?>
                                        <div class="product-image-placeholder">
                                            <i class="fa-solid fa-image"></i>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Discount Badge -->
                                    <?php if ($product['discount_percent'] > 0): ?>
                                        <span class="product-discount-badge">-<?= $product['discount_percent']; ?>%</span>
                                    <?php endif; ?>
                                </div>

                                <!-- Content -->
                                <div class="product-card-content">
                                    <h4 class="product-card-title"><?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></h4>

                                    <div class="product-card-price">
                                        <span class="product-price-current"><?= format_currency($product['flash_price']); ?></span>
                                        <?php if ($product['original_price'] > $product['flash_price']): ?>
                                            <span class="product-price-original"><?= format_currency($product['original_price']); ?></span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Meta -->
                                    <div class="product-card-meta">
                                        <?php if ($product['rating'] > 0): ?>
                                            <span class="product-rating">
                                                <i class="fa-solid fa-star"></i>
                                                <span class="rating-value"><?= number_format($product['rating'], 1); ?></span>
                                                <span class="rating-count">(<?= $product['rating_count']; ?>)</span>
                                            </span>
                                        <?php endif; ?>

                                        <?php if ($CMSNT->site('isShowSold') == 1 && $product['sold'] > 0): ?>
                                            <span class="product-sold"><?= __('Đã bán'); ?> <?= $product['sold']; ?></span>
                                        <?php endif; ?>

                                        <span class="product-delivery <?= $product['is_instant'] ? 'instant' : ''; ?>">
                                            <i class="fa-solid <?= $product['is_instant'] ? 'fa-bolt' : 'fa-truck'; ?>"></i>
                                            <?= $product['is_instant'] ? __('Giao ngay') : __('Order'); ?>
                                        </span>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <button class="flashsale-nav flashsale-nav-next" onclick="scrollFlashSale(1)" aria-label="Next">
                <i class="fa-solid fa-chevron-right"></i>
            </button>
        </div>
    </div>

    <script src="<?= BASE_URL('mod/js/flashsale.js'); ?>"></script>

<?php endif; ?>