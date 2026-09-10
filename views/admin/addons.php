<?php if (!defined('IN_SITE')) {
    die('The Request Not Found');
}
$body = [
    'title' => __('Danh sách Addons') . ' | ' . $CMSNT->site('title'),
    'desc'   => $CMSNT->site('description'),
    'keyword' => $CMSNT->site('keywords')
];
$body['header'] = '

 
';
$body['footer'] = '

 

';
require_once(__DIR__ . '/../../models/is_admin.php');
require_once(__DIR__ . '/header.php');
require_once(__DIR__ . '/sidebar.php');

if (checkPermission($getUser['admin'], 'view_addons') != true) {
    die('<script type="text/javascript">if(!alert("' . __('Bạn không có quyền sử dụng tính năng này') . '")){window.history.back();}</script>');
}

$addons = [
    // [
    //     'id'            => 'bot_telegram_quan_ly_he_thong',
    //     'title'         => 'Bot Telegram Quản Lý Hệ Thống',
    //     'subtitle'      => 'Quản lý website qua Telegram 24/7',
    //     'demo'          => 'https://media4.giphy.com/media/v1.Y2lkPTc5MGI3NjExM2pwOWhpM3VzMWdrbWVob3JsYTE1ZXA4cmdyMWQ2ZnVzMnlzYnIwZCZlcD12MV9pbnRlcm5hbF9naWZfYnlfaWQmY3Q9Zw/k2D0TgeCEUikFmItAC/giphy.gif',
    //     'features'      => [
    //         'Quản lý website trực tiếp qua Telegram.',
    //         'Xem thống kê, báo cáo doanh thu...',
    //         'Tương thích mọi thiết bị.',
    //         'Sử dụng lệnh /cmd, /help để mở danh sách lệnh.'
    //     ],
    //     'price'         => '500.000đ',
    //     'original_price'=> '1.000.000đ',
    //     'purchase_link' => 'https://client.cmsnt.co/cart.php?a=add&pid=75',
    //     'category'      => 'tool',
    //     'badge'         => 'hot'
    // ],
    [
        'id'            => 'xipay_china',
        'title'         => __('Tích Hợp Thanh Toán Qua XiPay (China)'),
        'subtitle'      => __('Hỗ trợ thanh toán qua AliPay & WeChatPay'),
        'demo'          => 'https://i.imgur.com/tEqnBN5.png',
        'description'   => __('Addon này cho phép bạn tích hợp cổng thanh toán XiPay trực tiếp vào website của mình. Với tích hợp này, khách hàng của bạn có thể thanh toán nhanh chóng qua AliPay hoặc WeChatPay một cách an toàn và tiện lợi.'),
        'features'      => [
            __('Thanh toán nhanh chóng qua XiPay.'),
            __('Hỗ trợ cả AliPay và WeChatPay.'),
            __('Dễ dàng tích hợp vào website hiện tại.')
        ],
        'price'         => '1.000.000đ',
        'original_price' => '1.500.000đ',
        'purchase_link' => 'https://client.cmsnt.co/cart.php?a=add&pid=98',
        'category'      => 'payment',
        'badge'         => 'sale'
    ],
    [
        'id'            => 'korapay_africa',
        'title'         => __('Tích Hợp Thanh Toán Qua Korapay (Africa)'),
        'subtitle'      => __('Hỗ trợ đa kênh thanh toán tại Africa như: Ngân hàng, thẻ tín dụng, ví điện tử & Mobile Money'),
        'demo'          => 'https://i.imgur.com/O9QQRc5.png',
        'description'   => __('Addon này cho phép bạn tích hợp cổng thanh toán Korapay trực tiếp vào website của mình. Khách hàng của bạn có thể thanh toán qua nhiều kênh như chuyển khoản ngân hàng, thẻ tín dụng, ví điện tử và Mobile Money. Giao dịch được xử lý an toàn và nhanh chóng, mang lại trải nghiệm thanh toán tiện lợi.'),
        'features'      => [
            __('Thanh toán đa kênh linh hoạt.'),
            __('Giao dịch được xử lý an toàn và nhanh chóng.'),
            __('Dễ dàng tích hợp và tùy chỉnh theo yêu cầu.')
        ],
        'price'         => '1.000.000đ',
        'original_price' => '1.500.000đ',
        'purchase_link' => 'https://client.cmsnt.co/cart.php?a=add&pid=99',
        'category'      => 'payment'
    ],
    [
        'id'            => 'tmweasyapi_thailand',
        'title'         => __('Tích Hợp Thanh Toán Qua Tmweasyapi (Thailand)'),
        'subtitle'      => __('Hỗ trợ đa kênh thanh toán tại Thái Lan: Bank, PromptPay, e-Wallet, TrueMoney Wallet'),
        'demo'          => 'https://i.imgur.com/8rnKeuE.png',
        'description'   => __('Addon này cho phép bạn tích hợp cổng thanh toán Tmweasyapi (Thailand) trực tiếp vào website của mình. Khách hàng của bạn có thể thanh toán qua nhiều kênh như ngân hàng nội địa Thái Lan, PromptPay QR code, TrueMoney, và ví điện tử khác. Giao dịch được xử lý an toàn và nhanh chóng, mang lại trải nghiệm thanh toán tiện lợi.'),
        'features'      => [
            __('Hỗ trợ thanh toán qua PromptPay, TrueMoney Wallet, mobile banking.'),
            __('Giao dịch bảo mật, xác nhận qua API tự động.'),
            __('Dễ dàng triển khai, quản lý các giao dịch.')
        ],
        'price'         => '1.000.000đ',
        'original_price' => '1.500.000đ',
        'purchase_link' => 'https://client.cmsnt.co/cart.php?a=add&pid=100',
        'category'      => 'payment'
    ],
    [
        'id'            => 'openpix_brazil',
        'title'         => __('Tích Hợp Thanh Toán Qua OpenPix (Brazil)'),
        'subtitle'      => __('Hỗ trợ thanh toán nhanh chóng và an toàn qua OpenPix'),
        'demo'          => 'https://i.imgur.com/YBkHmXi.png',
        'description'   => __('Addon này cho phép bạn tích hợp cổng thanh toán OpenPix trực tiếp vào website của mình. Khách hàng của bạn có thể thanh toán một cách nhanh chóng và an toàn. Giao dịch được xử lý an toàn và nhanh chóng, mang lại trải nghiệm thanh toán tiện lợi.'),
        'features'      => [
            __('Hỗ trợ thanh toán nhanh chóng qua OpenPix.'),
            __('Giao dịch bảo mật, xác nhận qua API tự động.'),
            __('Dễ dàng triển khai, quản lý các giao dịch.')
        ],
        'price'         => '1.000.000đ',
        'original_price' => '1.500.000đ',
        'purchase_link' => 'https://client.cmsnt.co/cart.php?a=add&pid=101',
        'category'      => 'payment'
    ],
    [
        'id'            => 'bakong_cambodia',
        'title'         => __('Tích Hợp Thanh Toán Qua Bakong Wallet (Cambodia)'),
        'subtitle'      => __('Hỗ trợ thanh toán nhanh chóng và an toàn qua Bakong Wallet'),
        'demo'          => 'https://i.imgur.com/lyY2Lzp.png',
        'description'   => __('Addon này cho phép bạn tích hợp cổng thanh toán Bakong Wallet trực tiếp vào website của mình. Khách hàng của bạn có thể thanh toán một cách nhanh chóng và an toàn. Giao dịch được xử lý an toàn và nhanh chóng, mang lại trải nghiệm thanh toán tiện lợi.'),
        'features'      => [
            __('Hỗ trợ thanh toán nhanh chóng qua Bakong Wallet.'),
            __('Giao dịch bảo mật, xác nhận qua API tự động.'),
            __('Dễ dàng triển khai, quản lý các giao dịch.')
        ],
        'price'         => '1.000.000đ',
        'original_price' => '1.500.000đ',
        'purchase_link' => 'https://client.cmsnt.co/cart.php?a=add&pid=102',
        'category'      => 'payment',
        'badge'         => 'sale'
    ]
];

// Định nghĩa các danh mục
$categories = [
    'all'        => __('Tất cả'),
    'payment'    => __('Thanh toán'),
    'tool'       => __('Công cụ')
];

// Lấy danh mục được chọn từ query string
$selectedCategory = isset($_GET['category']) ? $_GET['category'] : 'all';

// Lọc addon theo danh mục
$filteredAddons = [];
if ($selectedCategory === 'all') {
    $filteredAddons = $addons;
} else {
    foreach ($addons as $addon) {
        if (isset($addon['category']) && $addon['category'] === $selectedCategory) {
            $filteredAddons[] = $addon;
        }
    }
}
?>

<!-- CSS tùy chỉnh cho giao diện cửa hàng addon -->
<style>
    .addon-card {
        transition: all 0.3s ease;
        border: none;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        overflow: hidden;
    }

    .addon-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    }

    .addon-img-wrap {
        position: relative;
        overflow: hidden;
        height: 200px;
    }

    .addon-img {
        transition: transform 0.5s ease;
        object-fit: cover;
        height: 100%;
        width: 100%;
    }

    .addon-card:hover .addon-img {
        transform: scale(1.05);
    }

    .addon-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        z-index: 1;
    }

    .addon-features li {
        margin-bottom: 5px;
        position: relative;
        padding-left: 20px;
    }

    .addon-features li:before {
        content: "✓";
        position: absolute;
        left: 0;
        color: #10b981;
        font-weight: bold;
    }

    .addon-price-wrap {
        display: flex;
        align-items: flex-end;
        gap: 10px;
    }

    .addon-category-pills .nav-link.active {
        background-color: #6366f1;
        color: white;
    }

    .addon-category-pills .nav-link {
        color: #6c757d;
        font-weight: 500;
        border-radius: 30px;
        padding: 8px 15px;
        margin: 0 5px 10px 0;
    }

    .addon-buy-btn {
        transition: all 0.2s ease;
    }

    .addon-buy-btn:hover {
        transform: translateY(-2px);
    }

    .progress-bar {
        background-image: linear-gradient(to right, #6366f1, #8b5cf6);
    }
</style>

<div class="main-content app-content">
    <div class="container-fluid">
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0"><?= __('Cửa hàng Addons'); ?></h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="<?= base_url_admin('home'); ?>"><?= __('Trang chủ'); ?></a></li>
                        <li class="breadcrumb-item active" aria-current="page"><?= __('Cửa hàng Addons'); ?></li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card mb-4">
                    <div class="card-body">
                        <h5 class="fw-semibold mb-3"><?= __('Nâng cấp hệ thống của bạn với các Addon chuyên nghiệp'); ?></h5>
                        <p class="text-muted mb-4"><?= __('Khám phá bộ sưu tập các addon độc quyền để mở rộng chức năng cho website của bạn. Chúng tôi thường xuyên cập nhật các tính năng mới.'); ?></p>

                        <!-- Bộ lọc danh mục -->
                        <div class="mb-4">
                            <ul class="nav nav-pills addon-category-pills">
                                <?php foreach ($categories as $key => $name): ?>
                                    <li class="nav-item">
                                        <a class="nav-link <?= $selectedCategory === $key ? 'active' : '' ?>"
                                            href="<?= base_url_admin('addons&category=' . $key); ?>">
                                            <?= $name ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                        <!-- Thanh tìm kiếm -->
                        <div class="mb-4">
                            <div class="input-group">
                                <input type="text" class="form-control" id="addonSearch" placeholder="<?= __('Tìm kiếm addon...'); ?>">
                                <button class="btn btn-primary" type="button"><i class="fe fe-search me-1"></i> <?= __('Tìm kiếm'); ?></button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Hiển thị Addon -->
                <div class="row row-cards addon-list">
                    <?php if (count($filteredAddons) > 0): ?>
                        <?php foreach ($filteredAddons as $addon): ?>
                            <div class="col-sm-6 col-lg-4 col-xl-3 mb-4">
                                <div class="card h-100 addon-card">
                                    <?php if (isset($addon['demo'])): ?>
                                        <div class="addon-img-wrap">
                                            <img src="<?= $addon['demo']; ?>" class="addon-img" alt="<?= $addon['title']; ?>">
                                            <?php if (isset($addon['badge'])): ?>
                                                <div class="addon-badge">
                                                    <?php if ($addon['badge'] == 'hot'): ?>
                                                        <span class="badge bg-danger">HOT</span>
                                                    <?php elseif ($addon['badge'] == 'new'): ?>
                                                        <span class="badge bg-success">NEW</span>
                                                    <?php elseif ($addon['badge'] == 'sale'): ?>
                                                        <span class="badge bg-warning text-dark">SALE</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="card-body">
                                        <h5 class="card-title fw-semibold text-dark"><?= $addon['title']; ?></h5>
                                        <p class="text-muted mb-3"><?= $addon['subtitle']; ?></p>

                                        <?php if (isset($addon['description'])): ?>
                                            <p class="text-dark"><?= $addon['description']; ?></p>
                                        <?php endif; ?>

                                        <?php if (isset($addon['features']) && is_array($addon['features']) && count($addon['features']) > 0): ?>
                                            <div class="mb-3">
                                                <h6 class="fw-semibold mb-2"><?= __('Tính năng nổi bật'); ?>:</h6>
                                                <ul class="ps-3 addon-features text-dark">
                                                    <?php foreach ($addon['features'] as $feature): ?>
                                                        <li><?= $feature; ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (isset($addon['progress'])): ?>
                                            <div class="mb-3">
                                                <h6 class="mb-2"><?= __('Tiến độ phát triển'); ?>: <?= $addon['progress']; ?></h6>
                                                <div class="progress" style="height: 8px;">
                                                    <div class="progress-bar" role="progressbar" style="width: <?= $addon['progress']; ?>" aria-valuenow="<?= intval($addon['progress']); ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                            </div>
                                            <?php if (isset($addon['note'])): ?>
                                                <div class="alert alert-info py-2 px-3" role="alert">
                                                    <i class="fe fe-info me-1"></i> <?= $addon['note']; ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (isset($addon['price'])): ?>
                                        <div class="card-footer bg-transparent border-top-0">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div class="addon-price-wrap">
                                                    <h5 class="mb-0 text-primary fw-bold"><?= $addon['price']; ?></h5>
                                                    <?php if (isset($addon['original_price']) && $addon['original_price'] != $addon['price']): ?>
                                                        <small class="text-decoration-line-through text-muted"><?= $addon['original_price']; ?></small>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (isset($addon['purchase_link'])): ?>
                                                    <a href="<?= $addon['purchase_link']; ?>" target="_blank" class="btn btn-primary addon-buy-btn">
                                                        <i class="fe fe-shopping-cart me-1"></i> <?= __('Mua ngay'); ?>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body text-center py-4">
                                    <i class="fe fe-alert-circle fs-3 text-muted mb-3"></i>
                                    <h5><?= __('Không tìm thấy addon'); ?></h5>
                                    <p class="text-muted"><?= __('Không có addon nào phù hợp với bộ lọc hiện tại.'); ?></p>
                                    <a href="?category=all" class="btn btn-outline-primary"><?= __('Xem tất cả addon'); ?></a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Script lọc addon theo tìm kiếm -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('addonSearch');
        const addonCards = document.querySelectorAll('.addon-list .addon-card');

        searchInput.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();

            addonCards.forEach(card => {
                const parent = card.closest('.col-sm-6');
                const title = card.querySelector('.card-title').textContent.toLowerCase();
                const subtitle = card.querySelector('.text-muted').textContent.toLowerCase();

                if (title.includes(searchTerm) || subtitle.includes(searchTerm)) {
                    parent.style.display = '';
                } else {
                    parent.style.display = 'none';
                }
            });

            // Hiển thị thông báo nếu không có kết quả
            const visibleCards = document.querySelectorAll('.addon-list .col-sm-6[style=""]').length;
            const emptyMessage = document.querySelector('.addon-list .col-12');

            if (visibleCards === 0 && !emptyMessage) {
                const noResults = document.createElement('div');
                noResults.className = 'col-12';
                noResults.innerHTML = `
                <div class="card">
                    <div class="card-body text-center py-4">
                        <i class="fe fe-alert-circle fs-3 text-muted mb-3"></i>
                        <h5><?= __('Không tìm thấy kết quả'); ?></h5>
                        <p class="text-muted"><?= __('Không tìm thấy addon nào phù hợp với từ khóa'); ?> "${searchTerm}"</p>
                        <button class="btn btn-outline-primary" onclick="document.getElementById('addonSearch').value=''; this.closest('.col-12').remove(); document.querySelectorAll('.addon-list .col-sm-6').forEach(el => el.style.display = '');"><?= __('Xóa tìm kiếm'); ?></button>
                    </div>
                </div>
            `;
                document.querySelector('.addon-list').appendChild(noResults);
            } else if (visibleCards > 0 && emptyMessage) {
                emptyMessage.remove();
            }
        });
    });
</script>

<?php
require_once(__DIR__ . '/footer.php');
?>