<?php
/**
 * AJAX handlers cho quản lý đánh giá sản phẩm (Admin)
 */

define("IN_SITE", true);
require_once(__DIR__."/../../libs/db.php");
require_once(__DIR__."/../../libs/lang.php");
require_once(__DIR__."/../../libs/helper.php");
require_once(__DIR__."/../../config.php");
require_once(__DIR__.'/../../libs/database/users.php');
require_once(__DIR__.'/../../models/is_admin.php');

if(!isset($_POST['action'])){
    die(json_encode([
        'status'    => 'error',
        'msg'       => __('The Request Not Found')
    ]));
}

// Kiểm tra CSRF token cho tất cả request
checkCSRFAjax();

if ($CMSNT->site('status_demo') != 0) {
    die(json_encode(['status' => 'error', 'msg' => __('Chức năng này không thể sử dụng trên website demo')]));
}

/**
 * Lấy chi tiết đánh giá sản phẩm
 */
if($_POST['action'] == 'getReviewDetail'){
    if(checkPermission($getUser['admin'], 'view_product_reviews') != true){
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }
    
    $review_id = validate_int($_POST['review_id'] ?? 0, 1);
    
    if($review_id === false){
        die(json_encode([
            'status'    => 'error',
            'msg'       => __('ID đánh giá không hợp lệ')
        ]));
    }
    
    $review = $CMSNT->get_row_safe(
        "SELECT r.*, 
                u.username, u.fullname, u.avatar as user_avatar,
                p.name as product_name, p.slug as product_slug,
                pp.name as plan_name,
                po.trans_id as order_trans_id
         FROM `product_reviews` r
         LEFT JOIN `users` u ON r.user_id = u.id
         LEFT JOIN `products` p ON r.product_id = p.id
         LEFT JOIN `product_plans` pp ON r.plan_id = pp.id
         LEFT JOIN `product_orders` po ON r.order_id = po.id
         WHERE r.id = ?",
        [$review_id]
    );
    
    if(!$review){
        die(json_encode([
            'status'    => 'error',
            'msg'       => __('Đánh giá không tồn tại')
        ]));
    }
    
    // Decode HTML entities for display
    $review['product_name'] = html_entity_decode($review['product_name'] ?? '', ENT_QUOTES, 'UTF-8');
    $review['plan_name'] = html_entity_decode($review['plan_name'] ?? '', ENT_QUOTES, 'UTF-8');
    
    die(json_encode([
        'status'    => 'success',
        'data'      => $review
    ]));
}

/**
 * Cập nhật trạng thái đánh giá sản phẩm (duyệt/từ chối)
 */
if($_POST['action'] == 'updateReviewStatus'){
    if(checkPermission($getUser['admin'], 'edit_product_reviews') != true){
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }
    
    // Validate review_id
    $review_id = isset($_POST['review_id']) ? validate_int($_POST['review_id'], 1) : 0;
    if (!$review_id) {
        die(json_encode(['status' => 'error', 'msg' => __('ID đánh giá không hợp lệ')]));
    }
    
    // Validate status
    $status = isset($_POST['status']) ? validate_string($_POST['status'], 20) : '';
    if (!in_array($status, ['approved', 'rejected'])) {
        die(json_encode(['status' => 'error', 'msg' => __('Trạng thái không hợp lệ')]));
    }
    
    // Kiểm tra review tồn tại
    $review = $CMSNT->get_row_safe("SELECT * FROM `product_reviews` WHERE `id` = ?", [$review_id]);
    if (!$review) {
        die(json_encode(['status' => 'error', 'msg' => __('Đánh giá không tồn tại')]));
    }
    
    // Lấy reject_reason nếu có
    $reject_reason = isset($_POST['reject_reason']) ? validate_string($_POST['reject_reason'], 500) : '';
    if ($reject_reason === false) $reject_reason = '';
    
    // Cập nhật trạng thái
    $update_data = [
        'status' => $status,
        'updated_at' => gettime()
    ];
    
    if ($status == 'rejected' && !empty($reject_reason)) {
        $update_data['reject_reason'] = $reject_reason;
    }
    
    $isUpdate = $CMSNT->update("product_reviews", $update_data, "`id` = ?", [$review_id]);
    
    if ($isUpdate) {
        // Cập nhật rating của sản phẩm
        updateProductRating($review['product_id']);
        
        // Ghi log
        $CMSNT->insert("logs", [
            'user_id'    => $getUser['id'],
            'ip'         => myip(),
            'device'     => getUserAgent(),
            'createdate' => gettime(),
            'action'     => sprintf(__('Cập nhật trạng thái đánh giá #%d thành %s'), $review_id, $status)
        ]);
        
        $status_text = $status == 'approved' ? __('duyệt') : __('từ chối');
        die(json_encode(['status' => 'success', 'msg' => sprintf(__('Đã %s đánh giá thành công'), $status_text)]));
    }
    
    die(json_encode(['status' => 'error', 'msg' => __('Cập nhật thất bại')]));
}

/**
 * Trả lời đánh giá sản phẩm
 */
if($_POST['action'] == 'replyReview'){
    if(checkPermission($getUser['admin'], 'edit_product_reviews') != true){
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }
    
    // Validate review_id
    $review_id = isset($_POST['review_id']) ? validate_int($_POST['review_id'], 1) : 0;
    if (!$review_id) {
        die(json_encode(['status' => 'error', 'msg' => __('ID đánh giá không hợp lệ')]));
    }
    
    // Validate reply content (cho phép nội dung rỗng để xóa reply)
    $reply_content = isset($_POST['admin_reply']) ? trim($_POST['admin_reply']) : '';
    // Nếu có nội dung thì validate
    if (!empty($reply_content)) {
        $reply_content = validate_string($reply_content, 2000);
        if ($reply_content === false) {
            die(json_encode(['status' => 'error', 'msg' => __('Nội dung trả lời không hợp lệ (tối đa 2000 ký tự)')]));
        }
    }
    
    // Kiểm tra review tồn tại
    $review = $CMSNT->get_row_safe("SELECT * FROM `product_reviews` WHERE `id` = ?", [$review_id]);
    if (!$review) {
        die(json_encode(['status' => 'error', 'msg' => __('Đánh giá không tồn tại')]));
    }
    
    // Cập nhật reply
    $update_data = [
        'admin_reply' => $reply_content,
        'admin_reply_by' => $getUser['id'],
        'admin_reply_at' => gettime(),
        'updated_at' => gettime()
    ];
    
    // Tự động chuyển trạng thái sang approved khi admin trả lời (nếu có nội dung)
    if (!empty($reply_content) && $review['status'] != 'approved') {
        $update_data['status'] = 'approved';
    }
    
    $isUpdate = $CMSNT->update("product_reviews", $update_data, "`id` = ?", [$review_id]);
    
    if ($isUpdate) {
        // Cập nhật rating nếu status thay đổi sang approved
        if (!empty($reply_content) && $review['status'] != 'approved') {
            updateProductRating($review['product_id']);
        }
        
        // Ghi log
        $CMSNT->insert("logs", [
            'user_id'    => $getUser['id'],
            'ip'         => myip(),
            'device'     => getUserAgent(),
            'createdate' => gettime(),
            'action'     => sprintf(__('Trả lời đánh giá #%d'), $review_id)
        ]);
        
        die(json_encode(['status' => 'success', 'msg' => __('Trả lời đánh giá thành công')]));
    }
    
    die(json_encode(['status' => 'error', 'msg' => __('Trả lời đánh giá thất bại')]));
}

/**
 * Xóa đánh giá sản phẩm
 */
if($_POST['action'] == 'deleteReview'){
    if(checkPermission($getUser['admin'], 'delete_product_reviews') != true){
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }
    
    $review_id = validate_int($_POST['review_id'] ?? 0, 1);
    
    if($review_id === false){
        die(json_encode([
            'status'    => 'error',
            'msg'       => __('ID đánh giá không hợp lệ')
        ]));
    }
    
    // Kiểm tra đánh giá tồn tại
    $review = $CMSNT->get_row_safe("SELECT * FROM `product_reviews` WHERE `id` = ?", [$review_id]);
    
    if(!$review){
        die(json_encode([
            'status'    => 'error',
            'msg'       => __('Đánh giá không tồn tại')
        ]));
    }
    
    // Xóa ảnh đính kèm nếu có
    if (!empty($review['images'])) {
        $images = json_decode($review['images'], true);
        if (is_array($images)) {
            foreach ($images as $img) {
                if (file_exists(__DIR__.'/../../'.$img)) {
                    @unlink(__DIR__.'/../../'.$img);
                }
            }
        }
    }
    
    // Lưu product_id trước khi xóa
    $product_id = $review['product_id'];
    
    // Xóa đánh giá
    $isDeleted = $CMSNT->remove("product_reviews", "`id` = ?", [$review_id]);
    
    if($isDeleted){
        // Cập nhật rating của sản phẩm
        updateProductRating($product_id);
        
        $CMSNT->insert("logs", [
            'user_id'    => $getUser['id'],
            'ip'         => myip(),
            'device'     => getUserAgent(),
            'createdate' => gettime(),
            'action'     => sprintf(__('Xóa đánh giá #%d của sản phẩm #%d'), $review_id, $product_id)
        ]);
        
        /** NOTE ACTION */
        $my_text = $CMSNT->site('noti_action');
        $my_text = str_replace('{domain}', $_SERVER['SERVER_NAME'], $my_text);
        $my_text = str_replace('{username}', $getUser['username'], $my_text);
        $my_text = str_replace('{action}', sprintf(__('Xóa đánh giá #%d'), $review_id), $my_text);
        $my_text = str_replace('{ip}', myip(), $my_text);    
        $my_text = str_replace('{time}', gettime(), $my_text);
        sendMessAdmin($my_text);
        
        die(json_encode([
            'status' => 'success', 
            'msg' => __('Xóa đánh giá thành công!')
        ]));
    }
    
    die(json_encode([
        'status' => 'error', 
        'msg' => __('Xóa đánh giá thất bại!')
    ]));
}

/**
 * Cập nhật cấu hình tính năng đánh giá sản phẩm
 */
if($_POST['action'] == 'updateReviewProductConfig'){
    if(checkPermission($getUser['admin'], 'edit_product_reviews') != true){
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }

    $status = isset($_POST['status']) && $_POST['status'] == '1' ? 1 : 0;

    // Kiểm tra xem setting đã tồn tại chưa
    $existing = $CMSNT->get_row_safe("SELECT * FROM `settings` WHERE `name` = ?", ['status_review_product']);
    
    if ($existing) {
        // Cập nhật nếu đã tồn tại
        $isUpdate = $CMSNT->update('settings', [
            'value' => $status
        ], "`name` = ?", ['status_review_product']);
    } else {
        // Thêm mới nếu chưa tồn tại
        $isUpdate = $CMSNT->insert('settings', [
            'name' => 'status_review_product',
            'value' => $status,
            'description' => 'Trạng thái tính năng đánh giá sản phẩm (0: Tắt, 1: Bật)'
        ]);
    }

    if ($isUpdate) {
        // Ghi log
        $CMSNT->insert('logs', [
            'user_id'       => $getUser['id'],
            'ip'            => myip(),
            'device'        => getUserAgent(),
            'createdate'    => gettime(),
            'action'        => sprintf(__('Cập nhật cấu hình đánh giá sản phẩm: %s'), $status == 1 ? __('Bật') : __('Tắt'))
        ]);
        
        die(json_encode(['status' => 'success', 'msg' => __('Lưu cấu hình thành công')]));
    }
    
    die(json_encode(['status' => 'error', 'msg' => __('Lưu cấu hình thất bại')]));
}

/**
 * Duyệt hàng loạt đánh giá
 */
if($_POST['action'] == 'bulkApproveReviews'){
    if(checkPermission($getUser['admin'], 'edit_product_reviews') != true){
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }
    
    if(empty($_POST['ids']) || !is_array($_POST['ids'])){
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng chọn ít nhất một đánh giá')]));
    }
    
    $ids = array_map('intval', $_POST['ids']);
    $ids = array_filter($ids, function($id) { return $id > 0; });
    
    if(empty($ids)){
        die(json_encode(['status' => 'error', 'msg' => __('Danh sách ID không hợp lệ')]));
    }
    
    $updateCount = 0;
    $productIds = [];
    
    foreach($ids as $id){
        $review = $CMSNT->get_row_safe("SELECT `product_id`, `status` FROM `product_reviews` WHERE `id` = ?", [$id]);
        
        if($review && $review['status'] != 'approved'){
            $isUpdate = $CMSNT->update("product_reviews", [
                'status' => 'approved',
                'updated_at' => gettime()
            ], "`id` = ?", [$id]);
            
            if($isUpdate){
                $updateCount++;
                $productIds[] = $review['product_id'];
            }
        }
    }
    
    // Cập nhật rating cho các sản phẩm liên quan
    $productIds = array_unique($productIds);
    foreach($productIds as $productId){
        updateProductRating($productId);
    }
    
    if($updateCount > 0){
        // Ghi log
        $CMSNT->insert("logs", [
            'user_id'    => $getUser['id'],
            'ip'         => myip(),
            'device'     => getUserAgent(),
            'createdate' => gettime(),
            'action'     => sprintf(__('Duyệt hàng loạt %d đánh giá'), $updateCount)
        ]);
        
        die(json_encode([
            'status' => 'success',
            'msg' => sprintf(__('Đã duyệt thành công %d đánh giá'), $updateCount)
        ]));
    }
    
    die(json_encode(['status' => 'error', 'msg' => __('Không có đánh giá nào được duyệt')]));
}

/**
 * Trả lời hàng loạt đánh giá
 */
if($_POST['action'] == 'bulkReplyReviews'){
    if(checkPermission($getUser['admin'], 'edit_product_reviews') != true){
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }
    
    if(empty($_POST['ids']) || !is_array($_POST['ids'])){
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng chọn ít nhất một đánh giá')]));
    }
    
    $reply_content = isset($_POST['admin_reply']) ? trim($_POST['admin_reply']) : '';
    if(empty($reply_content)){
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng nhập nội dung trả lời')]));
    }
    
    $reply_content = validate_string($reply_content, 2000);
    if($reply_content === false){
        die(json_encode(['status' => 'error', 'msg' => __('Nội dung trả lời không hợp lệ (tối đa 2000 ký tự)')]));
    }
    
    $ids = array_map('intval', $_POST['ids']);
    $ids = array_filter($ids, function($id) { return $id > 0; });
    
    if(empty($ids)){
        die(json_encode(['status' => 'error', 'msg' => __('Danh sách ID không hợp lệ')]));
    }
    
    $updateCount = 0;
    $productIds = [];
    
    foreach($ids as $id){
        $review = $CMSNT->get_row_safe("SELECT `product_id`, `status` FROM `product_reviews` WHERE `id` = ?", [$id]);
        
        if($review){
            $update_data = [
                'admin_reply' => $reply_content,
                'admin_reply_by' => $getUser['id'],
                'admin_reply_at' => gettime(),
                'updated_at' => gettime()
            ];
            
            // Tự động duyệt nếu chưa được duyệt
            if($review['status'] != 'approved'){
                $update_data['status'] = 'approved';
                $productIds[] = $review['product_id'];
            }
            
            $isUpdate = $CMSNT->update("product_reviews", $update_data, "`id` = ?", [$id]);
            
            if($isUpdate){
                $updateCount++;
            }
        }
    }
    
    // Cập nhật rating cho các sản phẩm liên quan
    $productIds = array_unique($productIds);
    foreach($productIds as $productId){
        updateProductRating($productId);
    }
    
    if($updateCount > 0){
        // Ghi log
        $CMSNT->insert("logs", [
            'user_id'    => $getUser['id'],
            'ip'         => myip(),
            'device'     => getUserAgent(),
            'createdate' => gettime(),
            'action'     => sprintf(__('Trả lời hàng loạt %d đánh giá'), $updateCount)
        ]);
        
        die(json_encode([
            'status' => 'success',
            'msg' => sprintf(__('Đã trả lời thành công %d đánh giá'), $updateCount)
        ]));
    }
    
    die(json_encode(['status' => 'error', 'msg' => __('Không có đánh giá nào được trả lời')]));
}

/**
 * Xóa hàng loạt đánh giá
 */
if($_POST['action'] == 'bulkDeleteReviews'){
    if(checkPermission($getUser['admin'], 'delete_product_reviews') != true){
        die(json_encode(['status' => 'error', 'msg' => __('Bạn không có quyền sử dụng tính năng này')]));
    }
    
    if(empty($_POST['ids']) || !is_array($_POST['ids'])){
        die(json_encode(['status' => 'error', 'msg' => __('Vui lòng chọn ít nhất một đánh giá')]));
    }
    
    $ids = array_map('intval', $_POST['ids']);
    $ids = array_filter($ids, function($id) { return $id > 0; });
    
    if(empty($ids)){
        die(json_encode(['status' => 'error', 'msg' => __('Danh sách ID không hợp lệ')]));
    }
    
    $deleteCount = 0;
    $productIds = [];
    
    foreach($ids as $id){
        $review = $CMSNT->get_row_safe("SELECT * FROM `product_reviews` WHERE `id` = ?", [$id]);
        
        if($review){
            // Xóa ảnh đính kèm nếu có
            if(!empty($review['images'])){
                $images = json_decode($review['images'], true);
                if(is_array($images)){
                    foreach($images as $img){
                        if(file_exists(__DIR__.'/../../'.$img)){
                            @unlink(__DIR__.'/../../'.$img);
                        }
                    }
                }
            }
            
            $productIds[] = $review['product_id'];
            
            $isDeleted = $CMSNT->remove("product_reviews", "`id` = ?", [$id]);
            
            if($isDeleted){
                $deleteCount++;
            }
        }
    }
    
    // Cập nhật rating cho các sản phẩm liên quan
    $productIds = array_unique($productIds);
    foreach($productIds as $productId){
        updateProductRating($productId);
    }
    
    if($deleteCount > 0){
        // Ghi log
        $CMSNT->insert("logs", [
            'user_id'    => $getUser['id'],
            'ip'         => myip(),
            'device'     => getUserAgent(),
            'createdate' => gettime(),
            'action'     => sprintf(__('Xóa hàng loạt %d đánh giá'), $deleteCount)
        ]);
        
        /** NOTE ACTION */
        $my_text = $CMSNT->site('noti_action');
        $my_text = str_replace('{domain}', $_SERVER['SERVER_NAME'], $my_text);
        $my_text = str_replace('{username}', $getUser['username'], $my_text);
        $my_text = str_replace('{action}', sprintf(__('Xóa hàng loạt %d đánh giá'), $deleteCount), $my_text);
        $my_text = str_replace('{ip}', myip(), $my_text);    
        $my_text = str_replace('{time}', gettime(), $my_text);
        sendMessAdmin($my_text);
        
        die(json_encode([
            'status' => 'success',
            'msg' => sprintf(__('Đã xóa thành công %d đánh giá'), $deleteCount)
        ]));
    }
    
    die(json_encode(['status' => 'error', 'msg' => __('Không có đánh giá nào được xóa')]));
}

die(json_encode([
    'status'    => 'error',
    'msg'       => __('Invalid data')
]));

