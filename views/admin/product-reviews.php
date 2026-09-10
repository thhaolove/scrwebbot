<?php if (!defined('IN_SITE')) {
    die('The Request Not Found');
}
$body = [
    'title' => __('Quản lý đánh giá sản phẩm') . ' | ' . $CMSNT->site('title'),
    'desc'   => $CMSNT->site('description'),
    'keyword' => $CMSNT->site('keywords')
];
$body['header'] = '';
$body['footer'] = '';
require_once(__DIR__ . '/../../models/is_admin.php');
require_once(__DIR__ . '/header.php');
require_once(__DIR__ . '/sidebar.php');

// Kiểm tra quyền
if (checkPermission($getUser['admin'], 'view_product_reviews') != true) {
    $role_name = getRoleName('view_product_reviews');
    die('<script type="text/javascript">if(!alert("' . sprintf(__('Bạn không có quyền %s'), $role_name) . '")){window.history.back();}</script>');
}

// Phân trang an toàn
$limit = isset($_GET['limit']) ? (validate_int($_GET['limit'], 1, 1000) ?: 20) : 20;
$page = isset($_GET['page']) ? (validate_int($_GET['page'], 1, 1000000) ?: 1) : 1;
$from = ($page - 1) * $limit;

// Biến giữ giá trị hiển thị lại
$product_filter = 0;
$status_filter = '';
$rating_filter = '';
$search = '';
$date_from = '';
$date_to = '';
$order_trans_id = '';

// WHERE an toàn với prepared statements
$where_conditions = ["1 = 1"];
$where_params = [];

// Lọc theo sản phẩm
if (!empty($_GET['product_id'])) {
    $product_filter_input = validate_int($_GET['product_id'], 1);
    if ($product_filter_input !== false) {
        $product_filter = $product_filter_input;
        $where_conditions[] = 'r.`product_id` = ?';
        $where_params[] = $product_filter;
    }
}

// Lọc theo trạng thái
if (!empty($_GET['status'])) {
    $status_input = validate_string($_GET['status'], 20);
    if ($status_input !== false && in_array($status_input, ['pending', 'approved', 'rejected'])) {
        $status_filter = $status_input;
        $where_conditions[] = 'r.`status` = ?';
        $where_params[] = $status_filter;
    }
}

// Lọc theo số sao
if (!empty($_GET['rating'])) {
    $rating_input = validate_int($_GET['rating'], 1);
    if ($rating_input !== false && $rating_input >= 1 && $rating_input <= 5) {
        $rating_filter = $rating_input;
        $where_conditions[] = 'r.`rating` = ?';
        $where_params[] = $rating_filter;
    }
}

// Tìm kiếm theo nội dung hoặc username
if (!empty($_GET['search'])) {
    $search_input = validate_string($_GET['search'], 100, 1);
    if ($search_input !== false) {
        $search = $search_input;
        $where_conditions[] = '(r.`content` LIKE ? OR u.`username` LIKE ? OR r.`title` LIKE ?)';
        $search_param = '%' . $search . '%';
        $where_params[] = $search_param;
        $where_params[] = $search_param;
        $where_params[] = $search_param;
    }
}

// Lọc theo thời gian từ
if (!empty($_GET['date_from'])) {
    $date_from_input = validate_string($_GET['date_from'], 20);
    if ($date_from_input !== false && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from_input)) {
        $date_from = $date_from_input;
        $where_conditions[] = 'DATE(r.`created_at`) >= ?';
        $where_params[] = $date_from;
    }
}

// Lọc theo thời gian đến
if (!empty($_GET['date_to'])) {
    $date_to_input = validate_string($_GET['date_to'], 20);
    if ($date_to_input !== false && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to_input)) {
        $date_to = $date_to_input;
        $where_conditions[] = 'DATE(r.`created_at`) <= ?';
        $where_params[] = $date_to;
    }
}

// Lọc theo mã đơn hàng
if (!empty($_GET['order_trans_id'])) {
    $order_trans_id_input = validate_string($_GET['order_trans_id'], 50, 1);
    if ($order_trans_id_input !== false) {
        $order_trans_id = $order_trans_id_input;
        $where_conditions[] = 'po.`trans_id` LIKE ?';
        $where_params[] = '%' . $order_trans_id . '%';
    }
}

// Lọc theo có trả lời hay không
if (isset($_GET['has_reply']) && $_GET['has_reply'] !== '') {
    if ($_GET['has_reply'] == '1') {
        $where_conditions[] = 'r.`admin_reply` IS NOT NULL AND r.`admin_reply` != ""';
    } else {
        $where_conditions[] = '(r.`admin_reply` IS NULL OR r.`admin_reply` = "")';
    }
}

$where_sql = implode(' AND ', $where_conditions);

// Đếm tổng số bản ghi
$count_query = "SELECT COUNT(*) as total FROM `product_reviews` r 
                LEFT JOIN `users` u ON r.user_id = u.id 
                LEFT JOIN `product_orders` po ON r.order_id = po.id
                WHERE {$where_sql}";
$count_result = $CMSNT->get_row_safe($count_query, $where_params);
$total = $count_result ? (int)$count_result['total'] : 0;
$total_pages = ceil($total / $limit);

// Query lấy danh sách đánh giá
$query = "SELECT r.*, 
                 u.username, u.fullname as user_fullname, u.avatar as user_avatar,
                 p.name as product_name, p.slug as product_slug,
                 po.trans_id as order_trans_id
          FROM `product_reviews` r
          LEFT JOIN `users` u ON r.user_id = u.id
          LEFT JOIN `products` p ON r.product_id = p.id
          LEFT JOIN `product_orders` po ON r.order_id = po.id
          WHERE {$where_sql}
          ORDER BY r.`created_at` DESC
          LIMIT ?, ?";

$listParams = array_merge($where_params, [$from, $limit]);
$reviews = $CMSNT->get_list_safe($query, $listParams);

// Lấy danh sách sản phẩm để filter
$products_list = $CMSNT->get_list_safe("SELECT id, name FROM `products` WHERE `status` = 1 ORDER BY `name` ASC", []);

// Đếm số đánh giá chờ duyệt
$pending_count_row = $CMSNT->get_row_safe("SELECT COUNT(*) as cnt FROM `product_reviews` WHERE `status` = 'pending'", []);
$pending_count = $pending_count_row ? (int)$pending_count_row['cnt'] : 0;
?>

<div class="main-content app-content">
    <div class="container-fluid">
        <!-- Tiêu đề trang -->
        <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
            <h1 class="page-name fw-semibold fs-18 mb-0">
                <i class="fa-solid fa-star me-1"></i><?= __('Quản lý đánh giá sản phẩm'); ?>
                <?php if ($pending_count > 0): ?>
                    <span class="badge bg-warning-transparent ms-2"><?= $pending_count; ?> <?= __('chờ duyệt'); ?></span>
                <?php endif; ?>
            </h1>
            <div class="btn-list">
                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalReviewConfig">
                    <i class="fa-solid fa-cog me-1"></i><?= __('Cấu hình'); ?>
                </button>
            </div>
        </div>

        <!-- Bộ lọc -->
        <div class="card custom-card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center" style="cursor: pointer;" onclick="toggleReviewFilterForm()">
                <h6 class="mb-0">
                    <i class="fa-solid fa-filter me-2"></i><?= __('Bộ lọc tìm kiếm'); ?>
                    <?php
                    $active_filters = 0;
                    if ($product_filter > 0) $active_filters++;
                    if (!empty($status_filter)) $active_filters++;
                    if (!empty($rating_filter)) $active_filters++;
                    if (!empty($search)) $active_filters++;
                    if (!empty($date_from)) $active_filters++;
                    if (!empty($date_to)) $active_filters++;
                    if (!empty($order_trans_id)) $active_filters++;
                    if ($active_filters > 0): ?>
                        <span class="badge bg-primary ms-2"><?= $active_filters; ?> <?= __('bộ lọc đang áp dụng'); ?></span>
                    <?php endif; ?>
                </h6>
                <button type="button" class="btn btn-sm btn-light" id="toggleReviewFilterBtn">
                    <i class="fa-solid fa-chevron-down" id="reviewFilterIcon"></i>
                </button>
            </div>
            <div class="card-body" id="reviewFilterFormBody" style="display: none;">
                <form method="GET" action="<?= base_url(); ?>">
                    <input type="hidden" name="module" value="<?= $CMSNT->site('path_admin'); ?>">
                    <input type="hidden" name="action" value="product-reviews">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label"><?= __('Sản phẩm'); ?></label>
                            <select class="form-select" name="product_id">
                                <option value=""><?= __('Tất cả sản phẩm'); ?></option>
                                <?php foreach ($products_list as $prod): ?>
                                    <option value="<?= $prod['id']; ?>" <?= $product_filter == $prod['id'] ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars(html_entity_decode($prod['name'], ENT_QUOTES, 'UTF-8')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label"><?= __('Trạng thái'); ?></label>
                            <select class="form-select" name="status">
                                <option value=""><?= __('Tất cả'); ?></option>
                                <option value="pending" <?= $status_filter == 'pending' ? 'selected' : ''; ?>><?= __('Chờ duyệt'); ?></option>
                                <option value="approved" <?= $status_filter == 'approved' ? 'selected' : ''; ?>><?= __('Đã duyệt'); ?></option>
                                <option value="rejected" <?= $status_filter == 'rejected' ? 'selected' : ''; ?>><?= __('Từ chối'); ?></option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label"><?= __('Số sao'); ?></label>
                            <select class="form-select" name="rating">
                                <option value=""><?= __('Tất cả'); ?></option>
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <option value="<?= $i; ?>" <?= $rating_filter == $i ? 'selected' : ''; ?>><?= $i; ?> ⭐</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label"><?= __('Có trả lời'); ?></label>
                            <select class="form-select" name="has_reply">
                                <option value=""><?= __('Tất cả'); ?></option>
                                <option value="1" <?= (isset($_GET['has_reply']) && $_GET['has_reply'] == '1') ? 'selected' : ''; ?>><?= __('Đã trả lời'); ?></option>
                                <option value="0" <?= (isset($_GET['has_reply']) && $_GET['has_reply'] == '0') ? 'selected' : ''; ?>><?= __('Chưa trả lời'); ?></option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label"><?= __('Mã đơn hàng'); ?></label>
                            <input type="text" class="form-control" name="order_trans_id"
                                value="<?= htmlspecialchars($order_trans_id); ?>"
                                placeholder="<?= __('VD: ORD123...'); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label"><?= __('Tìm kiếm'); ?></label>
                            <input type="text" class="form-control" name="search"
                                value="<?= htmlspecialchars($search); ?>"
                                placeholder="<?= __('Username, nội dung...'); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label"><?= __('Từ ngày'); ?></label>
                            <input type="date" class="form-control" name="date_from" value="<?= htmlspecialchars($date_from); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label"><?= __('Đến ngày'); ?></label>
                            <input type="date" class="form-control" name="date_to" value="<?= htmlspecialchars($date_to); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label"><?= __('Số lượng/trang'); ?></label>
                            <select class="form-select" name="limit">
                                <option value="10" <?= $limit == 10 ? 'selected' : ''; ?>>10</option>
                                <option value="20" <?= $limit == 20 ? 'selected' : ''; ?>>20</option>
                                <option value="50" <?= $limit == 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?= $limit == 100 ? 'selected' : ''; ?>>100</option>
                                <option value="200" <?= $limit == 200 ? 'selected' : ''; ?>>200</option>
                            </select>
                        </div>
                        <div class="col-md-12 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fa-solid fa-filter me-1"></i><?= __('Lọc'); ?>
                            </button>
                            <a href="<?= base_url_admin('product-reviews'); ?>" class="btn btn-secondary">
                                <i class="fa-solid fa-times me-1"></i><?= __('Bỏ lọc'); ?>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Danh sách đánh giá -->
        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-body p-0">
                        <?php if (count($reviews) > 0): ?>
                            <!-- Thanh công cụ hàng loạt -->
                            <div id="bulkActionsToolbar" class="card-footer bg-light border-bottom d-none">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="text-muted">
                                            <span id="selectedCount">0</span> <?= __('đánh giá đã chọn'); ?>
                                        </span>
                                    </div>
                                    <div class="btn-list">
                                        <button type="button" id="btnBulkApprove" class="btn btn-sm btn-success d-none" onclick="bulkApproveReviews()">
                                            <i class="fa-solid fa-check me-1"></i><?= __('Duyệt đã chọn'); ?>
                                        </button>
                                        <button type="button" id="btnBulkReply" class="btn btn-sm btn-primary d-none" onclick="showBulkReplyModal()">
                                            <i class="fa-solid fa-reply me-1"></i><?= __('Trả lời đã chọn'); ?>
                                        </button>
                                        <button type="button" id="btnBulkDelete" class="btn btn-sm btn-danger d-none" onclick="bulkDeleteReviews()">
                                            <i class="fa-solid fa-trash me-1"></i><?= __('Xóa đã chọn'); ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover border text-nowrap">
                                    <thead>
                                        <tr>
                                            <th class="text-center" style="width: 50px;">
                                                <input type="checkbox" id="selectAllReviews" class="form-check-input" onchange="toggleSelectAll(this)" style="transform: scale(1.3); cursor: pointer;" title="<?= __('Chọn tất cả'); ?>">
                                            </th>
                                            <th><?= __('Người đánh giá'); ?></th>
                                            <th><?= __('Sản phẩm'); ?></th>
                                            <th><?= __('Đánh giá'); ?></th>
                                            <th><?= __('Nội dung'); ?></th>
                                            <th class="text-center"><?= __('Trạng thái'); ?></th>
                                            <th><?= __('Ngày tạo'); ?></th>
                                            <th><?= __('Thao tác'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reviews as $index => $review): ?>
                                            <tr id="review-row-<?= $review['id']; ?>">
                                                <td class="text-center">
                                                    <input type="checkbox" class="form-check-input review-checkbox" value="<?= $review['id']; ?>" onchange="updateBulkButtons()" style="transform: scale(1.3); cursor: pointer;">
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <?php if (!empty($review['user_avatar']) && file_exists($review['user_avatar'])): ?>
                                                            <img src="<?= base_url($review['user_avatar']); ?>" alt="" class="rounded-circle" style="width:35px;height:35px;object-fit:cover;">
                                                        <?php else: ?>
                                                            <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center" style="width:35px;height:35px;">
                                                                <i class="fa-solid fa-user text-white"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div>
                                                            <strong><?= htmlspecialchars(($review['user_fullname'] ?: $review['username']) ?? ''); ?></strong>
                                                            <br><small class="text-muted">@<?= htmlspecialchars($review['username'] ?? ''); ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <a href="<?= base_url('product/' . $review['product_slug']); ?>" target="_blank" class="text-primary">
                                                        <?= htmlspecialchars(html_entity_decode($review['product_name'] ?? '', ENT_QUOTES, 'UTF-8')); ?>
                                                    </a>
                                                    <?php if (!empty($review['order_trans_id'])): ?>
                                                        <br><small class="text-muted"><i class="fa-solid fa-receipt me-1"></i>#<?= htmlspecialchars($review['order_trans_id']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="text-warning">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="fa-solid fa-star<?= $i <= $review['rating'] ? '' : '-half-stroke'; ?>" style="<?= $i <= $review['rating'] ? '' : 'opacity:0.3'; ?>"></i>
                                                        <?php endfor; ?>
                                                    </div>
                                                    <?php if (!empty($review['title'])): ?>
                                                        <small class="fw-bold"><?= htmlspecialchars($review['title']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="max-width: 250px;">
                                                    <div class="text-wrap" style="max-height: 60px; overflow: hidden;" title="<?= htmlspecialchars($review['content'] ?? ''); ?>">
                                                        <?= htmlspecialchars(mb_substr($review['content'] ?? '', 0, 100)); ?>
                                                        <?= mb_strlen($review['content'] ?? '') > 100 ? '...' : ''; ?>
                                                    </div>
                                                    <?php if (!empty($review['admin_reply'])): ?>
                                                        <div class="mt-1">
                                                            <span class="badge bg-primary-transparent">
                                                                <i class="fa-solid fa-reply me-1"></i><?= __('Đã trả lời'); ?>
                                                            </span>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php
                                                    $status_class = [
                                                        'pending' => 'bg-warning-transparent',
                                                        'approved' => 'bg-success-transparent',
                                                        'rejected' => 'bg-danger-transparent'
                                                    ];
                                                    $status_text = [
                                                        'pending' => __('Chờ duyệt'),
                                                        'approved' => __('Đã duyệt'),
                                                        'rejected' => __('Từ chối')
                                                    ];
                                                    ?>
                                                    <span class="badge <?= $status_class[$review['status']] ?? 'bg-secondary-transparent'; ?>">
                                                        <?= $status_text[$review['status']] ?? $review['status']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small>
                                                        <i class="fa-regular fa-clock me-1"></i><?= $review['created_at']; ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="btn-list">
                                                        <button type="button" class="btn btn-sm btn-info" onclick="viewReview(<?= $review['id']; ?>, '<?= $review['status']; ?>', '<?= htmlspecialchars(addslashes($review['admin_reply'] ?? ''), ENT_QUOTES); ?>')" title="<?= __('Xem chi tiết'); ?>">
                                                            <i class="fa-solid fa-eye"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger" onclick="deleteReview(<?= $review['id']; ?>)" title="<?= __('Xóa'); ?>">
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Phân trang -->
                            <?php
                            // Tạo URL pagination với các tham số filter
                            $pagination_url = base_url_admin('product-reviews');
                            $pagination_url .= '&limit=' . $limit;
                            if ($product_filter > 0) $pagination_url .= '&product_id=' . $product_filter;
                            if (!empty($search)) $pagination_url .= '&search=' . urlencode($search);
                            if ($status_filter) $pagination_url .= '&status=' . $status_filter;
                            if ($rating_filter) $pagination_url .= '&rating=' . $rating_filter;
                            if ($date_from) $pagination_url .= '&date_from=' . urlencode($date_from);
                            if ($date_to) $pagination_url .= '&date_to=' . urlencode($date_to);
                            if (!empty($order_trans_id)) $pagination_url .= '&order_trans_id=' . urlencode($order_trans_id);
                            $pagination_url .= '&';

                            $urlDatatable = pagination($pagination_url, $from, $total, $limit);
                            ?>
                            <?php if ($total > $limit): ?>
                                <div class="card-footer">
                                    <div class="row">
                                        <div class="col-sm-12 col-md-5">
                                            <p class="dataTables_info"><?= __('Showing'); ?> <?= $limit; ?> <?= __('of'); ?> <?= number_format($total); ?> <?= __('Results'); ?></p>
                                        </div>
                                        <div class="col-sm-12 col-md-7">
                                            <?= $urlDatatable; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                        <?php else: ?>
                            <div class="alert alert-warning m-3">
                                <i class="fa-solid fa-exclamation-circle me-2"></i><?= __('Chưa có đánh giá nào.'); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal View Review -->
<div class="modal fade" id="modalViewReview" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __('Chi tiết đánh giá'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modalViewReviewBody">
                <!-- Content loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <input type="hidden" id="currentReviewId" value="">
                <input type="hidden" id="currentReviewStatus" value="">
                <input type="hidden" id="currentReviewReply" value="">
                <button type="button" class="btn btn-success" id="btnModalApprove" onclick="bootstrap.Modal.getInstance(document.getElementById('modalViewReview')).hide(); approveReview($('#currentReviewId').val())" style="display:none;">
                    <i class="fa-solid fa-check me-1"></i><?= __('Duyệt'); ?>
                </button>
                <button type="button" class="btn btn-warning" id="btnModalReject" onclick="bootstrap.Modal.getInstance(document.getElementById('modalViewReview')).hide(); rejectReview($('#currentReviewId').val())" style="display:none;">
                    <i class="fa-solid fa-ban me-1"></i><?= __('Từ chối'); ?>
                </button>
                <button type="button" class="btn btn-primary" id="btnModalReply" onclick="bootstrap.Modal.getInstance(document.getElementById('modalViewReview')).hide(); replyReview($('#currentReviewId').val(), $('#currentReviewReply').val())">
                    <i class="fa-solid fa-reply me-1"></i><?= __('Trả lời'); ?>
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('Đóng'); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Reply Review -->
<div class="modal fade" id="modalReplyReview" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __('Trả lời đánh giá'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="replyReviewId">
                <div class="mb-3">
                    <label class="form-label"><?= __('Nội dung trả lời'); ?></label>
                    <textarea class="form-control" id="replyContent" rows="4" placeholder="<?= __('Nhập nội dung trả lời...'); ?>"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('Hủy'); ?></button>
                <button type="button" class="btn btn-primary" onclick="submitReply()"><?= __('Gửi trả lời'); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Reject Review -->
<div class="modal fade" id="modalRejectReview" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __('Từ chối đánh giá'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="rejectReviewId">
                <div class="mb-3">
                    <label class="form-label"><?= __('Lý do từ chối'); ?></label>
                    <textarea class="form-control" id="rejectReason" rows="3" placeholder="<?= __('Nhập lý do từ chối (tùy chọn)...'); ?>"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('Hủy'); ?></button>
                <button type="button" class="btn btn-danger" onclick="submitReject()"><?= __('Từ chối'); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Bulk Reply Review -->
<div class="modal fade" id="modalBulkReplyReview" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fa-solid fa-reply me-2"></i><?= __('Trả lời hàng loạt'); ?>
                    <span class="badge bg-primary ms-2" id="bulkReplyCount">0</span> <?= __('đánh giá'); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info mb-3">
                    <i class="fa-solid fa-info-circle me-2"></i>
                    <?= __('Nội dung trả lời này sẽ được áp dụng cho tất cả các đánh giá đã chọn.'); ?>
                </div>
                <div class="mb-3">
                    <label class="form-label"><?= __('Nội dung trả lời'); ?> <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="bulkReplyContent" rows="5" placeholder="<?= __('Nhập nội dung trả lời...'); ?>"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('Hủy'); ?></button>
                <button type="button" class="btn btn-primary" id="btnSubmitBulkReply" onclick="submitBulkReply()">
                    <i class="fa-solid fa-paper-plane me-1"></i><?= __('Gửi trả lời'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Cấu hình Review -->
<div class="modal fade" id="modalReviewConfig" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-cog me-2"></i><?= __('Cấu hình đánh giá sản phẩm'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-center justify-content-between p-3 border rounded">
                    <div>
                        <h6 class="mb-1"><?= __('Tính năng đánh giá sản phẩm'); ?></h6>
                        <small class="text-muted"><?= __('Bật/Tắt cho phép khách hàng đánh giá sản phẩm sau khi mua'); ?></small>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="reviewStatusSwitch"
                            <?php echo ($CMSNT->site('status_review_product') == 1) ? 'checked' : ''; ?>
                            style="width: 50px; height: 25px; cursor: pointer;">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('Đóng'); ?></button>
                <button type="button" class="btn btn-primary" onclick="saveReviewConfig()"><?= __('Lưu cấu hình'); ?></button>
            </div>
        </div>
    </div>
</div>

<?php require_once(__DIR__ . '/footer.php'); ?>

<script>
    // Toggle filter form
    function toggleReviewFilterForm() {
        var body = document.getElementById('reviewFilterFormBody');
        var icon = document.getElementById('reviewFilterIcon');
        if (body.style.display === 'none') {
            body.style.display = 'block';
            icon.classList.remove('fa-chevron-down');
            icon.classList.add('fa-chevron-up');
            localStorage.setItem('reviewFilterOpen', 'true');
        } else {
            body.style.display = 'none';
            icon.classList.remove('fa-chevron-up');
            icon.classList.add('fa-chevron-down');
            localStorage.setItem('reviewFilterOpen', 'false');
        }
    }

    // Auto open filter form if there are active filters or user preference
    document.addEventListener('DOMContentLoaded', function() {
        var hasActiveFilters = <?= ($active_filters > 0) ? 'true' : 'false'; ?>;
        var savedState = localStorage.getItem('reviewFilterOpen');

        if (hasActiveFilters || savedState === 'true') {
            document.getElementById('reviewFilterFormBody').style.display = 'block';
            document.getElementById('reviewFilterIcon').classList.remove('fa-chevron-down');
            document.getElementById('reviewFilterIcon').classList.add('fa-chevron-up');
        }
    });
    // View review detail
    function viewReview(id, status, existingReply) {
        // Lưu thông tin review vào hidden inputs
        $('#currentReviewId').val(id);
        $('#currentReviewStatus').val(status);
        $('#currentReviewReply').val(existingReply || '');

        // Hiển thị/ẩn các button dựa trên status
        if (status == 'pending') {
            $('#btnModalApprove').show();
            $('#btnModalReject').show();
        } else {
            $('#btnModalApprove').hide();
            $('#btnModalReject').hide();
        }

        $('#modalViewReviewBody').html('<div class="text-center py-4"><i class="fa-solid fa-spinner fa-spin" style="font-size:2rem;"></i></div>');
        var modal = new bootstrap.Modal(document.getElementById('modalViewReview'));
        modal.show();

        $.ajax({
            url: "<?= BASE_URL('ajaxs/admin/reviews.php'); ?>",
            type: 'POST',
            dataType: 'JSON',
            data: {
                action: 'getReviewDetail',
                review_id: id
            },
            success: function(res) {
                if (res.status == 'success') {
                    var r = res.data;
                    var stars = '';
                    for (var i = 1; i <= 5; i++) {
                        stars += '<i class="fa-solid fa-star text-warning' + (i <= r.rating ? '' : '" style="opacity:0.3') + '"></i>';
                    }

                    var html = '<div class="review-detail">';
                    html += '<div class="d-flex align-items-center mb-3 gap-3">';
                    html += '<div>';
                    if (r.user_avatar) {
                        html += '<img src="<?= base_url(); ?>' + r.user_avatar + '" class="rounded-circle" style="width:50px;height:50px;object-fit:cover;">';
                    } else {
                        html += '<div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center" style="width:50px;height:50px;"><i class="fa-solid fa-user text-white"></i></div>';
                    }
                    html += '</div>';
                    html += '<div>';
                    html += '<strong>' + escapeHtml(r.fullname || r.username) + '</strong>';
                    html += '<br><small class="text-muted">@' + escapeHtml(r.username) + ' • ' + r.created_at + '</small>';
                    html += '</div>';
                    html += '</div>';

                    html += '<div class="mb-3">' + stars + ' <span class="ms-2 badge bg-primary-transparent">' + r.rating + '/5</span></div>';

                    if (r.title) {
                        html += '<h5 class="mb-2">' + escapeHtml(r.title) + '</h5>';
                    }

                    html += '<p class="mb-3" style="white-space: pre-wrap;">' + escapeHtml(r.content) + '</p>';

                    html += '<div class="border-top pt-3">';
                    html += '<div class="row">';
                    html += '<div class="col-md-6"><small class="text-muted"><i class="fa-solid fa-box me-1"></i><strong><?= addslashes(__('Sản phẩm')); ?>:</strong> ' + escapeHtml(r.product_name) + '</small></div>';
                    html += '<div class="col-md-6"><small class="text-muted"><i class="fa-solid fa-receipt me-1"></i><strong><?= addslashes(__('Đơn hàng')); ?>:</strong> #' + escapeHtml(r.order_trans_id) + '</small></div>';
                    html += '</div>';
                    html += '</div>';

                    if (r.admin_reply) {
                        html += '<div class="mt-3 p-3 bg-light rounded">';
                        html += '<strong class="text-primary"><i class="fa-solid fa-reply me-1"></i><?= addslashes(__('Phản hồi từ Shop')); ?></strong>';
                        html += '<p class="mb-0 mt-2" style="white-space: pre-wrap;">' + escapeHtml(r.admin_reply) + '</p>';
                        html += '</div>';
                    }

                    html += '</div>';

                    $('#modalViewReviewBody').html(html);
                } else {
                    $('#modalViewReviewBody').html('<div class="alert alert-danger">' + res.msg + '</div>');
                }
            },
            error: function() {
                $('#modalViewReviewBody').html('<div class="alert alert-danger"><?= addslashes(__('Đã xảy ra lỗi')); ?></div>');
            }
        });
    }

    // Approve review
    function approveReview(id) {
        cuteAlert({
            type: "question",
            title: "<?= addslashes(__('Xác nhận')); ?>",
            message: "<?= addslashes(__('Bạn có chắc muốn duyệt đánh giá này?')); ?>",
            confirmText: "<?= addslashes(__('Đồng ý')); ?>",
            cancelText: "<?= addslashes(__('Hủy')); ?>"
        }).then((e) => {
            if (e) {
                $.ajax({
                    url: "<?= BASE_URL('ajaxs/admin/reviews.php'); ?>",
                    type: 'POST',
                    dataType: 'JSON',
                    data: {
                        action: 'updateReviewStatus',
                        review_id: id,
                        status: 'approved'
                    },
                    success: function(res) {
                        if (res.status == 'success') {
                            showMessage(res.msg, 'success');
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            showMessage(res.msg, 'error');
                        }
                    },
                    error: function() {
                        showMessage('<?= addslashes(__('Đã xảy ra lỗi')); ?>', 'error');
                    }
                });
            }
        });
    }

    // Open reject modal
    function rejectReview(id) {
        $('#rejectReviewId').val(id);
        $('#rejectReason').val('');
        var modal = new bootstrap.Modal(document.getElementById('modalRejectReview'));
        modal.show();
    }

    // Submit reject
    function submitReject() {
        var id = $('#rejectReviewId').val();
        var reason = $('#rejectReason').val().trim();

        $.ajax({
            url: "<?= BASE_URL('ajaxs/admin/reviews.php'); ?>",
            type: 'POST',
            dataType: 'JSON',
            data: {
                action: 'updateReviewStatus',
                review_id: id,
                status: 'rejected',
                reason: reason
            },
            success: function(res) {
                if (res.status == 'success') {
                    showMessage(res.msg, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('modalRejectReview')).hide();
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showMessage(res.msg, 'error');
                }
            },
            error: function() {
                showMessage('<?= addslashes(__('Đã xảy ra lỗi')); ?>', 'error');
            }
        });
    }

    // Open reply modal
    function replyReview(id, existingReply) {
        $('#replyReviewId').val(id);
        $('#replyContent').val(existingReply || '');
        var modal = new bootstrap.Modal(document.getElementById('modalReplyReview'));
        modal.show();
    }

    // Submit reply
    function submitReply() {
        var id = $('#replyReviewId').val();
        var content = $('#replyContent').val().trim();

        $.ajax({
            url: "<?= BASE_URL('ajaxs/admin/reviews.php'); ?>",
            type: 'POST',
            dataType: 'JSON',
            data: {
                action: 'replyReview',
                review_id: id,
                admin_reply: content
            },
            success: function(res) {
                if (res.status == 'success') {
                    showMessage(res.msg, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('modalReplyReview')).hide();
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showMessage(res.msg, 'error');
                }
            },
            error: function() {
                showMessage('<?= addslashes(__('Đã xảy ra lỗi')); ?>', 'error');
            }
        });
    }

    // Delete review
    function deleteReview(id) {
        cuteAlert({
            type: "question",
            title: "<?= addslashes(__('Cảnh báo')); ?>",
            message: "<?= addslashes(__('Bạn có chắc muốn xóa đánh giá này? Hành động này không thể hoàn tác.')); ?>",
            confirmText: "<?= addslashes(__('Đồng ý')); ?>",
            cancelText: "<?= addslashes(__('Hủy')); ?>"
        }).then((e) => {
            if (e) {
                $.ajax({
                    url: "<?= BASE_URL('ajaxs/admin/reviews.php'); ?>",
                    type: 'POST',
                    dataType: 'JSON',
                    data: {
                        action: 'deleteReview',
                        review_id: id
                    },
                    success: function(res) {
                        if (res.status == 'success') {
                            showMessage(res.msg, 'success');
                            $('#review-row-' + id).fadeOut(300, function() {
                                $(this).remove();
                            });
                        } else {
                            showMessage(res.msg, 'error');
                        }
                    },
                    error: function() {
                        showMessage('<?= addslashes(__('Đã xảy ra lỗi')); ?>', 'error');
                    }
                });
            }
        });
    }

    // Save review config
    function saveReviewConfig() {
        var status = $('#reviewStatusSwitch').is(':checked') ? 1 : 0;

        $.ajax({
            url: "<?= BASE_URL('ajaxs/admin/reviews.php'); ?>",
            type: 'POST',
            dataType: 'JSON',
            data: {
                action: 'updateReviewProductConfig',
                status: status
            },
            success: function(res) {
                if (res.status == 'success') {
                    showMessage(res.msg, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('modalReviewConfig')).hide();
                } else {
                    showMessage(res.msg, 'error');
                }
            },
            error: function() {
                showMessage('<?= addslashes(__('Đã xảy ra lỗi')); ?>', 'error');
            }
        });
    }

    // Escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ==================== BULK ACTIONS ====================

    // Chọn tất cả / Bỏ chọn tất cả
    function toggleSelectAll(checkbox) {
        $('.review-checkbox').prop('checked', checkbox.checked);
        updateBulkButtons();
    }

    // Cập nhật hiển thị nút bulk action
    function updateBulkButtons() {
        var selectedCount = $('.review-checkbox:checked').length;
        $('#selectedCount').text(selectedCount);

        if (selectedCount > 0) {
            $('#bulkActionsToolbar').removeClass('d-none');
            $('#btnBulkApprove, #btnBulkReply, #btnBulkDelete').removeClass('d-none');
        } else {
            $('#bulkActionsToolbar').addClass('d-none');
            $('#btnBulkApprove, #btnBulkReply, #btnBulkDelete').addClass('d-none');
        }

        // Cập nhật trạng thái checkbox "Chọn tất cả"
        var totalCheckboxes = $('.review-checkbox').length;
        $('#selectAllReviews').prop('checked', selectedCount === totalCheckboxes && totalCheckboxes > 0);
    }

    // Lấy danh sách ID đã chọn
    function getSelectedReviewIds() {
        var selectedIds = [];
        $('.review-checkbox:checked').each(function() {
            selectedIds.push(parseInt($(this).val()));
        });
        return selectedIds;
    }

    // Duyệt hàng loạt
    function bulkApproveReviews() {
        var selectedIds = getSelectedReviewIds();

        if (selectedIds.length === 0) {
            showMessage('<?= addslashes(__("Vui lòng chọn ít nhất một đánh giá")); ?>', 'error');
            return;
        }

        cuteAlert({
            type: "question",
            title: "<?= addslashes(__('Xác nhận')); ?>",
            message: "<?= addslashes(__('Bạn có chắc muốn duyệt')); ?> " + selectedIds.length + " <?= addslashes(__('đánh giá đã chọn?')); ?>",
            confirmText: "<?= addslashes(__('Đồng ý')); ?>",
            cancelText: "<?= addslashes(__('Hủy')); ?>"
        }).then((e) => {
            if (e) {
                var $btn = $('#btnBulkApprove');
                $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-1"></i><?= addslashes(__("Đang xử lý...")); ?>');

                $.ajax({
                    url: "<?= BASE_URL('ajaxs/admin/reviews.php'); ?>",
                    type: 'POST',
                    dataType: 'JSON',
                    data: {
                        action: 'bulkApproveReviews',
                        ids: selectedIds,
                        csrf_token: getCSRFToken()
                    },
                    success: function(res) {
                        $btn.prop('disabled', false).html('<i class="fa-solid fa-check me-1"></i><?= addslashes(__("Duyệt đã chọn")); ?>');

                        if (res.status == 'success') {
                            showMessage(res.msg, 'success');
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            showMessage(res.msg, 'error');
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).html('<i class="fa-solid fa-check me-1"></i><?= addslashes(__("Duyệt đã chọn")); ?>');
                        showMessage('<?= addslashes(__('Đã xảy ra lỗi')); ?>', 'error');
                    }
                });
            }
        });
    }

    // Hiển thị modal trả lời hàng loạt
    function showBulkReplyModal() {
        var selectedIds = getSelectedReviewIds();

        if (selectedIds.length === 0) {
            showMessage('<?= addslashes(__("Vui lòng chọn ít nhất một đánh giá")); ?>', 'error');
            return;
        }

        $('#bulkReplyCount').text(selectedIds.length);
        $('#bulkReplyContent').val('');

        var modal = new bootstrap.Modal(document.getElementById('modalBulkReplyReview'));
        modal.show();
    }

    // Gửi trả lời hàng loạt
    function submitBulkReply() {
        var selectedIds = getSelectedReviewIds();
        var content = $('#bulkReplyContent').val().trim();

        if (selectedIds.length === 0) {
            showMessage('<?= addslashes(__("Vui lòng chọn ít nhất một đánh giá")); ?>', 'error');
            return;
        }

        if (!content) {
            showMessage('<?= addslashes(__("Vui lòng nhập nội dung trả lời")); ?>', 'error');
            $('#bulkReplyContent').focus();
            return;
        }

        var $btn = $('#btnSubmitBulkReply');
        $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-1"></i><?= addslashes(__("Đang gửi...")); ?>');

        $.ajax({
            url: "<?= BASE_URL('ajaxs/admin/reviews.php'); ?>",
            type: 'POST',
            dataType: 'JSON',
            data: {
                action: 'bulkReplyReviews',
                ids: selectedIds,
                admin_reply: content,
                csrf_token: getCSRFToken()
            },
            success: function(res) {
                $btn.prop('disabled', false).html('<i class="fa-solid fa-paper-plane me-1"></i><?= addslashes(__("Gửi trả lời")); ?>');

                if (res.status == 'success') {
                    showMessage(res.msg, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('modalBulkReplyReview')).hide();
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showMessage(res.msg, 'error');
                }
            },
            error: function() {
                $btn.prop('disabled', false).html('<i class="fa-solid fa-paper-plane me-1"></i><?= addslashes(__("Gửi trả lời")); ?>');
                showMessage('<?= addslashes(__('Đã xảy ra lỗi')); ?>', 'error');
            }
        });
    }

    // Xóa hàng loạt
    function bulkDeleteReviews() {
        var selectedIds = getSelectedReviewIds();

        if (selectedIds.length === 0) {
            showMessage('<?= addslashes(__("Vui lòng chọn ít nhất một đánh giá")); ?>', 'error');
            return;
        }

        cuteAlert({
            type: "question",
            title: "<?= addslashes(__('Cảnh báo')); ?>",
            message: "<?= addslashes(__('Bạn có chắc muốn xóa')); ?> " + selectedIds.length + " <?= addslashes(__('đánh giá đã chọn? Hành động này không thể hoàn tác.')); ?>",
            confirmText: "<?= addslashes(__('Đồng ý')); ?>",
            cancelText: "<?= addslashes(__('Hủy')); ?>"
        }).then((e) => {
            if (e) {
                var $btn = $('#btnBulkDelete');
                $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-1"></i><?= addslashes(__("Đang xóa...")); ?>');

                $.ajax({
                    url: "<?= BASE_URL('ajaxs/admin/reviews.php'); ?>",
                    type: 'POST',
                    dataType: 'JSON',
                    data: {
                        action: 'bulkDeleteReviews',
                        ids: selectedIds,
                        csrf_token: getCSRFToken()
                    },
                    success: function(res) {
                        $btn.prop('disabled', false).html('<i class="fa-solid fa-trash me-1"></i><?= addslashes(__("Xóa đã chọn")); ?>');

                        if (res.status == 'success') {
                            showMessage(res.msg, 'success');
                            // Xóa các row đã chọn
                            selectedIds.forEach(function(id) {
                                $('#review-row-' + id).fadeOut(300, function() {
                                    $(this).remove();
                                });
                            });
                            // Reset toolbar
                            updateBulkButtons();
                        } else {
                            showMessage(res.msg, 'error');
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).html('<i class="fa-solid fa-trash me-1"></i><?= addslashes(__("Xóa đã chọn")); ?>');
                        showMessage('<?= addslashes(__('Đã xảy ra lỗi')); ?>', 'error');
                    }
                });
            }
        });
    }
</script>