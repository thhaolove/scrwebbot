<?php

define("IN_SITE", true);
require_once(__DIR__ . "/../../libs/db.php");
require_once(__DIR__ . "/../../libs/lang.php");
require_once(__DIR__ . "/../../libs/helper.php");
require_once(__DIR__ . "/../../config.php");
require_once(__DIR__ . '/../../libs/database/users.php');

if ($CMSNT->site('status') != 1) {
    $data = json_encode([
        'status'    => 'error',
        'msg'       => __('Hệ thống đang bảo trì!')
    ]);
    die($data);
}
if (!isset($_POST['action'])) {
    $data = json_encode([
        'status'    => 'error',
        'msg'       => __('The Request Not Found')
    ]);
    die($data);
}

// Kiểm tra CSRF token cho tất cả request
checkCSRFAjax();


/**
 * Gửi đánh giá sản phẩm
 */
if ($_POST['action'] == 'submitProductReview') {
    // Kiểm tra user đã đăng nhập
    $token = isset($_POST['token']) ? validate_string($_POST['token'], 255) : false;
    if (!$token) {
        die(json_encode([
            'status'    => 'error',
            'msg'       => __('Vui lòng đăng nhập để đánh giá')
        ]));
    }

    $getUser = $CMSNT->get_row_safe("SELECT * FROM `users` WHERE `token` = ? AND `banned` = 0", [$token]);
    if (!$getUser) {
        checkBlockIP('SCAN_TOKEN', 1);
        die(json_encode([
            'status'    => 'error',
            'msg'       => __('Phiên đăng nhập không hợp lệ')
        ]));
    }

    // Validate product_id
    $product_id = isset($_POST['product_id']) ? validate_int($_POST['product_id'], 1) : 0;
    if (!$product_id) {
        die(json_encode(['status' => 'error', 'msg' => __('Sản phẩm không hợp lệ')]));
    }

    // Kiểm tra sản phẩm có tồn tại không
    $product = $CMSNT->get_row_safe("SELECT `id`, `name` FROM `products` WHERE `id` = ? AND `status` = 1", [$product_id]);
    if (!$product) {
        die(json_encode(['status' => 'error', 'msg' => __('Sản phẩm không tồn tại')]));
    }

    // Validate order_id
    $order_id = isset($_POST['order_id']) ? validate_int($_POST['order_id'], 1) : 0;
    if (!$order_id) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng chọn đơn hàng để đánh giá')]));
    }

    // Kiểm tra đơn hàng có thuộc về user và đã hoàn thành chưa
    $order = $CMSNT->get_row_safe(
        "SELECT * FROM `product_orders` WHERE `id` = ? AND `user_id` = ? AND `product_id` = ? AND `status` = 'completed'",
        [$order_id, $getUser['id'], $product_id]
    );
    if (!$order) {
        die(json_encode(['status' => 'error', 'msg' => __('Đơn hàng không hợp lệ hoặc chưa hoàn thành')]));
    }

    // Kiểm tra đơn hàng đã được đánh giá chưa
    $existing_review = $CMSNT->get_row_safe(
        "SELECT `id` FROM `product_reviews` WHERE `order_id` = ?",
        [$order_id]
    );
    if ($existing_review) {
        die(json_encode(['status' => 'error', 'msg' => __('Bạn đã đánh giá đơn hàng này rồi')]));
    }

    // Validate rating
    $rating = isset($_POST['rating']) ? validate_int($_POST['rating'], 1) : 0;
    if ($rating < 1 || $rating > 5) {
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng chọn số sao đánh giá (1-5)')]));
    }

    // Validate title (optional)
    $title = isset($_POST['title']) ? validate_string($_POST['title'], 255) : '';
    if ($title === false) $title = '';
    $title = trim($title);

    // Validate content
    $content = isset($_POST['content']) ? validate_string($_POST['content'], 2000, 5) : false;
    if ($content === false || empty(trim($content))) {
        die(json_encode(['status' => 'error', 'msg' => __('Nội dung đánh giá không hợp lệ (5-2000 ký tự)')]));
    }
    $content = trim($content);

    // Insert review
    $review_data = [
        'order_id' => $order_id,
        'user_id' => $getUser['id'],
        'product_id' => $product_id,
        'plan_id' => $order['plan_id'],
        'rating' => $rating,
        'title' => $title,
        'content' => $content,
        'images' => null,
        'status' => 'pending', // Chờ duyệt
        'is_verified_purchase' => 1,
        'helpful_count' => 0,
        'created_at' => gettime(),
        'updated_at' => gettime()
    ];

    $review_id = $CMSNT->insert('product_reviews', $review_data);

    if (!$review_id) {
        die(json_encode(['status' => 'error', 'msg' => __('Không thể gửi đánh giá, vui lòng thử lại')]));
    }

    // Gửi thông báo Telegram vào queue (nếu có cấu hình)
    if (!empty($CMSNT->site('noti_new_review')) && !empty($CMSNT->site('telegram_chat_id'))) {
        require_once(__DIR__ . '/../../libs/TelegramQueue.php');
        $telegramQueue = new TelegramQueue();
        $telegramQueue->queueReviewNotificationAdmin(
            $getUser,
            $product,
            $rating,
            $title,
            $content
        );
    }

    die(json_encode([
        'status' => 'success',
        'msg' => __('Gửi đánh giá thành công! Đánh giá của bạn sẽ được hiển thị sau khi được duyệt.'),
        'review_id' => $review_id
    ]));
}

/**
 * Đánh dấu đánh giá hữu ích (Toggle: vote/bỏ vote)
 */
if ($_POST['action'] == 'markReviewHelpful') {
    // Kiểm tra user đã đăng nhập
    $token = isset($_POST['token']) ? validate_string($_POST['token'], 255) : false;
    if (!$token) {
        die(json_encode([
            'status'    => 'error',
            'msg'       => __('Vui lòng đăng nhập để sử dụng tính năng này')
        ]));
    }

    $getUser = $CMSNT->get_row_safe("SELECT * FROM `users` WHERE `token` = ? AND `banned` = 0", [$token]);
    if (!$getUser) {
        checkBlockIP('SCAN_TOKEN', 1);
        die(json_encode([
            'status'    => 'error',
            'msg'       => __('Phiên đăng nhập không hợp lệ')
        ]));
    }

    // Validate review_id
    $review_id = isset($_POST['review_id']) ? validate_int($_POST['review_id'], 1) : 0;
    if (!$review_id) {
        die(json_encode(['status' => 'error', 'msg' => __('Đánh giá không hợp lệ')]));
    }

    // Kiểm tra review có tồn tại và đã được duyệt không
    $review = $CMSNT->get_row_safe(
        "SELECT * FROM `product_reviews` WHERE `id` = ? AND `status` = 'approved'",
        [$review_id]
    );
    if (!$review) {
        die(json_encode(['status' => 'error', 'msg' => __('Đánh giá không tồn tại')]));
    }

    // Không thể vote cho đánh giá của chính mình
    if ($review['user_id'] == $getUser['id']) {
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không thể vote cho đánh giá của chính mình')]));
    }

    // Kiểm tra xem user đã vote cho review này chưa
    $existing_vote = $CMSNT->get_row_safe(
        "SELECT * FROM `review_helpful_votes` WHERE `review_id` = ? AND `user_id` = ?",
        [$review_id, $getUser['id']]
    );

    if ($existing_vote) {
        // Đã vote rồi -> Bỏ vote
        $CMSNT->remove("review_helpful_votes", "`review_id` = ? AND `user_id` = ?", [$review_id, $getUser['id']]);

        // Giảm helpful_count (đảm bảo không âm)
        $new_count = max(0, $review['helpful_count'] - 1);
        $CMSNT->update("product_reviews", ['helpful_count' => $new_count], "`id` = ?", [$review_id]);

        die(json_encode([
            'status' => 'success',
            'msg' => __('Đã bỏ vote'),
            'helpful_count' => $new_count,
            'voted' => false
        ]));
    } else {
        // Chưa vote -> Thêm vote
        $CMSNT->insert("review_helpful_votes", [
            'review_id' => $review_id,
            'user_id' => $getUser['id'],
            'created_at' => gettime()
        ]);

        // Tăng helpful_count
        $new_count = $review['helpful_count'] + 1;
        $CMSNT->update("product_reviews", ['helpful_count' => $new_count], "`id` = ?", [$review_id]);

        die(json_encode([
            'status' => 'success',
            'msg' => __('Cảm ơn phản hồi của bạn!'),
            'helpful_count' => $new_count,
            'voted' => true
        ]));
    }
}

/**
 * Load thêm đánh giá sản phẩm (AJAX pagination)
 */
if ($_POST['action'] == 'getMoreReviews') {
    $product_id = isset($_POST['product_id']) ? validate_int($_POST['product_id'], 1) : 0;
    $offset = isset($_POST['offset']) ? validate_int($_POST['offset'], 0, 10000) : 5;
    $limit = 5; // Hardcoded to prevent abuse

    if (!$product_id) {
        die(json_encode(['status' => 'error', 'msg' => __('Product ID không hợp lệ')]));
    }

    $reviews = $CMSNT->get_list_safe(
        "SELECT r.*, u.username, u.fullname as user_name, u.avatar, p.name as plan_name
         FROM `product_reviews` r
         LEFT JOIN `users` u ON r.user_id = u.id
         LEFT JOIN `product_plans` p ON r.plan_id = p.id
         WHERE r.product_id = ? AND r.status = 'approved'
         ORDER BY r.created_at DESC LIMIT ?, ?",
        [$product_id, $offset, $limit]
    );

    $user_voted_ids = [];
    if (isSecureCookie('user_login')) {
        $user_token = validate_alphanumeric($_COOKIE['user_login']);
        if ($user_token) {
            $getUser = $CMSNT->get_row_safe("SELECT `id` FROM `users` WHERE `token` = ?", [$user_token]);
            if ($getUser) {
                $voted = $CMSNT->get_list_safe("SELECT `review_id` FROM `review_helpful_votes` WHERE `user_id` = ?", [$getUser['id']]);
                foreach ($voted as $v) $user_voted_ids[] = (int)$v['review_id'];
            }
        }
    }

    $html = '';
    foreach ($reviews as $r) {
        $voted = in_array((int)$r['id'], $user_voted_ids);
        $html .= '<div class="review-item" data-review-id="' . $r['id'] . '">';
        $html .= '<div class="review-header"><div class="review-user"><div class="review-avatar">';
        $html .= (!empty($r['avatar']) && file_exists($r['avatar'])) ? '<img src="' . base_url($r['avatar']) . '" alt="">' : '<i class="fa-solid fa-user"></i>';
        $html .= '</div><div class="review-user-info"><span class="review-username">' . htmlspecialchars($r['user_name'] ?: $r['username']) . '</span>';
        if ($r['is_verified_purchase']) $html .= '<span class="verified-badge"><i class="fa-solid fa-check-circle"></i> ' . __('Đã mua hàng') . '</span>';
        $html .= '</div></div><div class="review-meta"><div class="review-rating-stars">';
        for ($i = 1; $i <= 5; $i++) $html .= '<i class="fa-' . ($i <= $r['rating'] ? 'solid' : 'regular') . ' fa-star"></i>';
        $html .= '</div><span class="review-date">' . date('d/m/Y', strtotime($r['created_at'])) . '</span></div></div>';
        if (!empty($r['plan_name'])) $html .= '<div class="review-plan"><i class="fa-solid fa-box"></i> ' . htmlspecialchars(html_entity_decode($r['plan_name'], ENT_QUOTES, 'UTF-8')) . '</div>';
        if (!empty($r['title'])) $html .= '<h4 class="review-title">' . htmlspecialchars($r['title']) . '</h4>';
        $html .= '<div class="review-content">' . nl2br(htmlspecialchars($r['content'])) . '</div>';
        if (!empty($r['images'])) {
            $imgs = json_decode($r['images'], true);
            if (is_array($imgs) && count($imgs) > 0) {
                $html .= '<div class="review-images">';
                foreach ($imgs as $img) $html .= '<div class="review-image-item"><img src="' . base_url($img) . '" alt=""></div>';
                $html .= '</div>';
            }
        }
        if (!empty($r['admin_reply'])) {
            $html .= '<div class="review-admin-reply"><div class="admin-reply-header"><i class="fa-solid fa-store"></i><span class="admin-reply-label">' . __('Phản hồi từ Shop') . '</span>';
            if (!empty($r['admin_reply_at'])) $html .= '<span class="admin-reply-date">' . date('d/m/Y', strtotime($r['admin_reply_at'])) . '</span>';
            $html .= '</div><div class="admin-reply-content">' . nl2br(htmlspecialchars($r['admin_reply'])) . '</div></div>';
        }
        $html .= '<div class="review-actions"><button type="button" class="btn-review-helpful' . ($voted ? ' voted' : '') . '" data-review-id="' . $r['id'] . '">';
        $html .= $voted ? '<i class="fa-solid fa-thumbs-down"></i><span>' . __('Bỏ vote') . '</span>' : '<i class="fa-regular fa-thumbs-up"></i><span>' . __('Hữu ích') . '</span>';
        if ($r['helpful_count'] > 0) $html .= '<span class="helpful-count">(' . $r['helpful_count'] . ')</span>';
        $html .= '</button></div></div>';
    }

    die(json_encode(['status' => 'success', 'html' => $html, 'count' => count($reviews), 'new_offset' => $offset + count($reviews)]));
}

die(json_encode([
    'status'    => 'error',
    'msg'       => __('Request does not exist')
]));
