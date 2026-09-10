<?php if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

// Load user info
if ($CMSNT->site('isLoginRequiredToViewProduct') == 1) {
    require_once(__DIR__ . '/../../models/is_user.php');
} else {
    if (isSecureCookie('user_login') == true) {
        require_once(__DIR__ . '/../../models/is_user.php');
    }
}

$page_title = __('Giỏ hàng');

$body = [
    'title' => $page_title . ' | ' . $CMSNT->site('title'),
    'desc'  => $CMSNT->site('description'),
    'keyword' => $CMSNT->site('keywords')
];

$body['header'] = '
<link rel="stylesheet" href="' . base_url('mod/css/cart.css?v=10') . '">
';

$body['footer'] = '
<script src="' . base_url('mod/js/cart.js?v=10') . '"></script>
';

require_once(__DIR__ . '/header.php');
require_once(__DIR__ . '/nav.php');

// User info for JS
$user_balance = 0;
$user_token = '';
$is_logged_in = false;

if (isset($getUser) && $getUser) {
    $is_logged_in = true;
    $user_balance = (float)$getUser['money'];
    $user_token = $getUser['token'];
}

// Get current currency info for JS
$current_currency_id = getCurrency();
$current_currency = null;
foreach (get_currencies_cached() as $c) {
    if ($c['id'] == $current_currency_id) {
        $current_currency = $c;
        break;
    }
}
// Fallback to default currency
if (!$current_currency) {
    foreach (get_currencies_cached() as $c) {
        if ($c['default_currency'] == 1) {
            $current_currency = $c;
            break;
        }
    }
}

// Translations for JS
$translations = [
    'emptyCart' => __('Giỏ hàng trống'),
    'emptyCartDesc' => __('Hãy thêm sản phẩm vào giỏ hàng để mua sắm'),
    'viewProducts' => __('Xem sản phẩm'),
    'products' => __('sản phẩm'),
    'orderInfo' => __('Thông tin đơn hàng'),
    'productList' => __('Danh sách sản phẩm'),
    'orderSummary' => __('Tóm tắt đơn hàng'),
    'subtotal' => __('Tạm tính'),
    'discount' => __('Giảm giá'),
    'saleDiscount' => __('Giảm giá sản phẩm'),
    'total' => __('Tổng cộng'),
    'viewOrders' => __('Xem đơn hàng đã đặt'),
    'clearAll' => __('Xóa tất cả'),
    'remove' => __('Xóa'),
    'confirmClear' => __('Bạn có chắc muốn xóa tất cả sản phẩm trong giỏ hàng?'),
    'outOfStock' => __('Số lượng vượt quá kho hàng'),
    // Payment translations
    'payWithBalance' => __('Thanh toán đơn hàng'),
    'yourBalance' => __('Số dư của bạn'),
    'balanceAfter' => __('Số dư sau thanh toán'),
    'insufficientBalance' => __('Số dư không đủ'),
    'topUp' => __('Nạp thêm tiền'),
    'processing' => __('Đang xử lý...'),
    'checkoutSuccess' => __('Thanh toán thành công!'),
    'checkoutError' => __('Thanh toán thất bại'),
    'loginRequired' => __('Vui lòng đăng nhập để thanh toán'),
    'login' => __('Đăng nhập'),
    'confirmCheckout' => __('Xác nhận thanh toán'),
    'confirmCheckoutMsg' => __('Bạn có chắc muốn thanh toán đơn hàng này?'),
    'yes' => __('Có, thanh toán'),
    'no' => __('Không'),
    'orderCreated' => __('đơn hàng đã được tạo'),
    'viewOrderDetails' => __('Xem chi tiết đơn hàng'),
    // Coupon translations
    'couponCode' => __('Mã giảm giá'),
    'enterCouponCode' => __('Nhập mã giảm giá'),
    'apply' => __('Áp dụng'),
    'couponDiscount' => __('Giảm giá coupon'),
    'invalidCoupon' => __('Mã giảm giá không hợp lệ'),
    'couponNoLongerValid' => __('Mã giảm giá không còn hợp lệ'),
    'errorOccurred' => __('Đã xảy ra lỗi'),
    // Real-time price translations
    'loadingPrices' => __('Đang cập nhật giá...'),
    'refreshPrices' => __('Cập nhật giá'),
    'updating' => __('Đang cập nhật...'),
    'pricesUpdated' => __('Đã cập nhật giá mới nhất'),
    'priceIncreased' => __('Giá tăng'),
    'priceDecreased' => __('Giá giảm'),
    'itemUnavailable' => __('Sản phẩm không khả dụng'),
    'removeUnavailable' => __('Vui lòng xóa các sản phẩm không khả dụng để tiếp tục thanh toán'),
    'cannotCheckout' => __('Không thể thanh toán'),
    // Stepper translations
    'stepCart' => __('Giỏ hàng'),
    'stepConfirm' => __('Xác nhận'),
    'stepComplete' => __('Hoàn tất'),
    // Confirmation translations
    'confirmTitle' => __('Xác nhận thanh toán'),
    'confirmDesc' => __('Vui lòng kiểm tra lại thông tin đơn hàng trước khi thanh toán'),
    'confirmPayment' => __('Xác nhận thanh toán'),
    'goBack' => __('Quay lại'),
    'orderItems' => __('Sản phẩm đặt mua'),
    'paymentMethod' => __('Phương thức thanh toán'),
    'accountBalance' => __('Số dư tài khoản')
];
?>

<div class="cart-page">
    <div class="container">
        <!-- Checkout Stepper -->
        <div class="cart-stepper" id="cartStepper">
            <div class="cart-stepper-item active" data-step="1">
                <div class="stepper-icon">
                    <i class="fa-solid fa-cart-shopping"></i>
                </div>
                <span class="stepper-label"><?= __('Giỏ hàng'); ?></span>
            </div>
            <div class="stepper-line"></div>
            <div class="cart-stepper-item" data-step="2">
                <div class="stepper-icon">
                    <i class="fa-solid fa-clipboard-check"></i>
                </div>
                <span class="stepper-label"><?= __('Xác nhận'); ?></span>
            </div>
            <div class="stepper-line"></div>
            <div class="cart-stepper-item" data-step="3">
                <div class="stepper-icon">
                    <i class="fa-solid fa-check"></i>
                </div>
                <span class="stepper-label"><?= __('Hoàn tất'); ?></span>
            </div>
        </div>

        <!-- Cart Header -->
        <div class="cart-page-header">
            <div class="cart-page-header-bg"></div>
            <div class="cart-page-header-inner">
                <div class="cart-page-header-left">
                    <div class="cart-page-header-icon">
                        <i class="fa-solid fa-cart-shopping"></i>
                        <span class="cart-icon-badge" id="cartIconBadge">0</span>
                    </div>
                    <div class="cart-page-header-content">
                        <h1 class="cart-page-header-title"><?= __('Giỏ hàng'); ?></h1>
                        <p class="cart-page-header-count"><span id="cartItemCount">0</span> <?= __('sản phẩm'); ?></p>
                    </div>
                </div>
                <div class="cart-page-header-right">
                    <a href="<?= base_url('products'); ?>" class="cart-header-btn">
                        <i class="fa-solid fa-bag-shopping"></i>
                        <span><?= __('Tiếp tục mua sắm'); ?></span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Cart Content (rendered by JS) -->
        <div id="cartContainer"></div>
    </div>
</div>

<script>
    // Pass config to JS module
    window.CART_CONFIG = {
        BASE_URL: "<?= base_url(); ?>",
        TRANS: <?= json_encode($translations); ?>,
        ORDERS_URL: "<?= base_url('product-orders'); ?>",
        TOPUP_URL: "<?= base_url('recharge'); ?>",
        LOGIN_URL: "<?= base_url('client/login?redirect=' . urlencode(base_url('cart'))); ?>",
        IS_LOGGED_IN: <?= $is_logged_in ? 'true' : 'false'; ?>,
        USER_BALANCE: <?= $user_balance; ?>,
        USER_TOKEN: "<?= $user_token; ?>",
        CURRENCY: {
            symbol_left: "<?= htmlspecialchars($current_currency['symbol_left'] ?? '', ENT_QUOTES); ?>",
            symbol_right: "<?= htmlspecialchars($current_currency['symbol_right'] ?? '', ENT_QUOTES); ?>",
            rate: <?= (float)($current_currency['rate'] ?? 1); ?>,
            decimal: <?= (int)($current_currency['decimal_currency'] ?? 0); ?>,
            seperator: "<?= htmlspecialchars($current_currency['seperator'] ?? 'dot', ENT_QUOTES); ?>"
        }
    };
</script>


<?php require_once(__DIR__ . '/footer.php'); ?>