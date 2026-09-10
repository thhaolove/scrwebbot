<?php

if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

/**
 * Class FlashSaleHandler
 * Xử lý tất cả logic liên quan đến Flash Sale
 */
class FlashSaleHandler extends DB
{
    /**
     * Lấy Flash Sale đang active cho một plan
     * 
     * @param int $plan_id ID gói sản phẩm
     * @param int|null $product_id ID sản phẩm (optional)
     * @return array|null Thông tin Flash Sale nếu có
     */
    public function getActiveFlashSaleForPlan(int $plan_id, ?int $product_id = null)
    {
        $current_time = gettime();

        // Tìm Flash Sale active cho plan cụ thể hoặc cho product
        $sql = "SELECT fs.*, fsi.flash_price, fsi.plan_id as item_plan_id, fsi.product_id as item_product_id
                FROM `flash_sales` fs
                INNER JOIN `flash_sale_items` fsi ON fs.id = fsi.flash_sale_id
                WHERE fs.status = 1 
                AND fs.start_time <= ?
                AND fs.end_time > ?
                AND (fs.quantity_limit = 0 OR fs.quantity_sold < fs.quantity_limit)
                AND (
                    fsi.plan_id = ?
                    OR (fsi.plan_id IS NULL AND fsi.product_id = ?)
                )
                ORDER BY fsi.plan_id DESC, fs.id DESC
                LIMIT 1";

        return $this->get_row_safe($sql, [$current_time, $current_time, $plan_id, $product_id]);
    }

    /**
     * Lấy tất cả Flash Sale đang active cho danh sách plan IDs
     * 
     * @param array $plan_ids Danh sách plan IDs
     * @param array $product_ids Danh sách product IDs
     * @return array Flash Sale items theo plan_id
     */
    public function getActiveFlashSalesForPlans(array $plan_ids, array $product_ids = [])
    {
        if (empty($plan_ids)) {
            return [];
        }

        $current_time = gettime();
        $placeholders_plan = implode(',', array_fill(0, count($plan_ids), '?'));
        $placeholders_product = !empty($product_ids) ? implode(',', array_fill(0, count($product_ids), '?')) : '';

        $sql = "SELECT fs.*, fsi.flash_price, fsi.plan_id as item_plan_id, fsi.product_id as item_product_id
                FROM `flash_sales` fs
                INNER JOIN `flash_sale_items` fsi ON fs.id = fsi.flash_sale_id
                WHERE fs.status = 1 
                AND fs.start_time <= ?
                AND fs.end_time > ?
                AND (fs.quantity_limit = 0 OR fs.quantity_sold < fs.quantity_limit)
                AND (
                    fsi.plan_id IN ($placeholders_plan)";

        $params = [$current_time, $current_time];
        $params = array_merge($params, $plan_ids);

        if (!empty($product_ids)) {
            $sql .= " OR (fsi.plan_id IS NULL AND fsi.product_id IN ($placeholders_product))";
            $params = array_merge($params, $product_ids);
        }

        $sql .= ")";

        $results = $this->get_list_safe($sql, $params);

        // Nhóm theo plan_id
        $flash_sales = [];
        if ($results) {
            foreach ($results as $row) {
                $key = $row['item_plan_id'] ?? 'product_' . $row['item_product_id'];
                if (!isset($flash_sales[$key])) {
                    $flash_sales[$key] = $row;
                }
            }
        }

        return $flash_sales;
    }

    /**
     * Tính giá Flash Sale
     * 
     * @param array $plan Thông tin gói sản phẩm
     * @param array $flash_sale Thông tin Flash Sale
     * @return float Giá sau khi áp dụng Flash Sale
     */
    public function calculateFlashSalePrice($plan, $flash_sale)
    {
        // Nếu có flash_price cố định, sử dụng luôn
        if (!empty($flash_sale['flash_price']) && $flash_sale['flash_price'] > 0) {
            return (float) $flash_sale['flash_price'];
        }

        // Lấy giá gốc (ưu tiên sale_price nếu có)
        $original_price = ($plan['sale_price'] > 0 && $plan['sale_price'] < $plan['price'])
            ? $plan['sale_price']
            : $plan['price'];

        $discount_amount = 0;

        if ($flash_sale['discount_type'] == 'percentage') {
            // Tính theo phần trăm
            $discount_amount = $original_price * ($flash_sale['discount_value'] / 100);

            // Áp dụng giới hạn tối đa nếu có
            if ($flash_sale['max_discount_amount'] > 0 && $discount_amount > $flash_sale['max_discount_amount']) {
                $discount_amount = $flash_sale['max_discount_amount'];
            }
        } else {
            // Số tiền cố định
            $discount_amount = $flash_sale['discount_value'];
        }

        $final_price = max(0, $original_price - $discount_amount);

        return $final_price;
    }

    /**
     * Kiểm tra user có thể mua Flash Sale không
     * 
     * @param int $user_id User ID
     * @param int $flash_sale_id Flash Sale ID
     * @param int $quantity Số lượng muốn mua
     * @return array ['can_buy' => bool, 'message' => string, 'remaining' => int]
     */
    public function canUserPurchase($user_id, $flash_sale_id, $quantity = 1)
    {
        // Lấy thông tin Flash Sale
        $flash_sale = $this->get_row_safe(
            "SELECT * FROM `flash_sales` WHERE `id` = ?",
            [$flash_sale_id]
        );

        if (!$flash_sale) {
            return [
                'can_buy' => false,
                'message' => __('Flash Sale không tồn tại'),
                'remaining' => 0
            ];
        }

        // Kiểm tra trạng thái
        if ($flash_sale['status'] != 1) {
            return [
                'can_buy' => false,
                'message' => __('Flash Sale đã kết thúc'),
                'remaining' => 0
            ];
        }

        // Kiểm tra thời gian
        $current_time = time();
        if (strtotime($flash_sale['start_time']) > $current_time) {
            return [
                'can_buy' => false,
                'message' => __('Flash Sale chưa bắt đầu'),
                'remaining' => 0
            ];
        }

        if (strtotime($flash_sale['end_time']) < $current_time) {
            return [
                'can_buy' => false,
                'message' => __('Flash Sale đã kết thúc'),
                'remaining' => 0
            ];
        }

        // Kiểm tra giới hạn tổng
        if ($flash_sale['quantity_limit'] > 0) {
            $remaining_total = $flash_sale['quantity_limit'] - $flash_sale['quantity_sold'];
            if ($remaining_total <= 0) {
                return [
                    'can_buy' => false,
                    'message' => __('Flash Sale đã hết số lượng'),
                    'remaining' => 0
                ];
            }

            if ($quantity > $remaining_total) {
                return [
                    'can_buy' => false,
                    'message' => sprintf(__('Flash Sale chỉ còn %d sản phẩm'), $remaining_total),
                    'remaining' => $remaining_total
                ];
            }
        }

        // Kiểm tra giới hạn mỗi user
        if ($flash_sale['per_user_limit'] > 0) {
            $user_purchased = $this->get_row_safe(
                "SELECT COALESCE(SUM(quantity), 0) as total FROM `flash_sale_purchases` WHERE `flash_sale_id` = ? AND `user_id` = ?",
                [$flash_sale_id, $user_id]
            );

            $already_bought = $user_purchased ? (int) $user_purchased['total'] : 0;
            $remaining_user = $flash_sale['per_user_limit'] - $already_bought;

            if ($remaining_user <= 0) {
                return [
                    'can_buy' => false,
                    'message' => __('Bạn đã mua hết lượt Flash Sale'),
                    'remaining' => 0
                ];
            }

            if ($quantity > $remaining_user) {
                return [
                    'can_buy' => false,
                    'message' => sprintf(__('Bạn chỉ được mua thêm %d sản phẩm'), $remaining_user),
                    'remaining' => $remaining_user
                ];
            }
        }

        return [
            'can_buy' => true,
            'message' => __('Có thể mua'),
            'remaining' => $flash_sale['quantity_limit'] > 0
                ? ($flash_sale['quantity_limit'] - $flash_sale['quantity_sold'])
                : PHP_INT_MAX
        ];
    }

    /**
     * Ghi nhận mua hàng Flash Sale
     * 
     * @param int $flash_sale_id Flash Sale ID
     * @param int $user_id User ID
     * @param int $order_id Order ID
     * @param int $quantity Số lượng
     * @return bool
     */
    public function recordPurchase($flash_sale_id, $user_id, $order_id, $quantity = 1)
    {
        $this->query("START TRANSACTION");

        try {
            // Ghi nhận purchase
            $isInsert = $this->insert("flash_sale_purchases", [
                'flash_sale_id' => $flash_sale_id,
                'user_id' => $user_id,
                'order_id' => $order_id,
                'quantity' => $quantity,
                'created_at' => gettime()
            ]);

            if (!$isInsert) {
                $this->query("ROLLBACK");
                return false;
            }

            // Cập nhật số lượng đã bán sử dụng method cong() và update()
            // Tăng quantity_sold
            $this->cong('flash_sales', 'quantity_sold', $quantity, "`id` = ?", [$flash_sale_id]);

            // Cập nhật updated_at
            $this->update('flash_sales', ['updated_at' => gettime()], "`id` = ?", [$flash_sale_id]);

            $this->query("COMMIT");
            return true;
        } catch (Exception $e) {
            $this->query("ROLLBACK");
            return false;
        }
    }

    /**
     * Hủy mua hàng Flash Sale (khi hủy đơn hàng)
     * 
     * @param int $order_id Order ID
     * @return bool
     */
    public function cancelPurchase($order_id)
    {
        // Lấy thông tin purchase
        $purchase = $this->get_row_safe(
            "SELECT * FROM `flash_sale_purchases` WHERE `order_id` = ?",
            [$order_id]
        );

        if (!$purchase) {
            return true; // Không có Flash Sale purchase
        }

        $this->query("START TRANSACTION");

        try {
            // Giảm số lượng đã bán - sử dụng tru() và update()
            $this->tru('flash_sales', 'quantity_sold', $purchase['quantity'], "`id` = ? AND `quantity_sold` >= ?", [$purchase['flash_sale_id'], $purchase['quantity']]);

            // Cập nhật updated_at
            $this->update('flash_sales', ['updated_at' => gettime()], "`id` = ?", [$purchase['flash_sale_id']]);

            // Xóa purchase record
            $this->remove("flash_sale_purchases", "`order_id` = ?", [$order_id]);

            $this->query("COMMIT");
            return true;
        } catch (Exception $e) {
            $this->query("ROLLBACK");
            return false;
        }
    }

    /**
     * Lấy danh sách Flash Sale (cho admin)
     * 
     * @param string|null $status Filter: 'active', 'upcoming', 'ended', null=all
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getFlashSales(?string $status = null, int $limit = 50, int $offset = 0)
    {
        $current_time = gettime();
        $where = "1=1";
        $params = [];

        if ($status === 'active') {
            $where .= " AND status = 1 AND start_time <= ? AND end_time > ?";
            $params = [$current_time, $current_time];
        } elseif ($status === 'upcoming') {
            $where .= " AND status = 1 AND start_time > ?";
            $params = [$current_time];
        } elseif ($status === 'ended') {
            $where .= " AND (status = 0 OR end_time <= ?)";
            $params = [$current_time];
        }

        $params[] = $limit;
        $params[] = $offset;

        return $this->get_list_safe(
            "SELECT * FROM `flash_sales` WHERE $where ORDER BY `created_at` DESC LIMIT ? OFFSET ?",
            $params
        );
    }

    /**
     * Đếm tổng Flash Sale
     * 
     * @param string|null $status
     * @return int
     */
    public function countFlashSales(?string $status = null)
    {
        $current_time = gettime();
        $where = "1=1";
        $params = [];

        if ($status === 'active') {
            $where .= " AND status = 1 AND start_time <= ? AND end_time > ?";
            $params = [$current_time, $current_time];
        } elseif ($status === 'upcoming') {
            $where .= " AND status = 1 AND start_time > ?";
            $params = [$current_time];
        } elseif ($status === 'ended') {
            $where .= " AND (status = 0 OR end_time <= ?)";
            $params = [$current_time];
        }

        $result = $this->get_row_safe(
            "SELECT COUNT(*) as total FROM `flash_sales` WHERE $where",
            $params
        );

        return $result ? (int) $result['total'] : 0;
    }

    /**
     * Lấy các items của Flash Sale
     * 
     * @param int $flash_sale_id
     * @return array
     */
    public function getFlashSaleItems($flash_sale_id)
    {
        return $this->get_list_safe(
            "SELECT fsi.*, 
                    p.name as product_name, p.image as product_image,
                    pp.name as plan_name, pp.price as plan_price, pp.sale_price as plan_sale_price
             FROM `flash_sale_items` fsi
             LEFT JOIN `products` p ON fsi.product_id = p.id
             LEFT JOIN `product_plans` pp ON fsi.plan_id = pp.id
             WHERE fsi.flash_sale_id = ?
             ORDER BY fsi.id ASC",
            [$flash_sale_id]
        );
    }

    /**
     * Lấy thông tin Flash Sale theo ID
     * 
     * @param int $id
     * @return array|null
     */
    public function getFlashSaleById($id)
    {
        return $this->get_row_safe(
            "SELECT * FROM `flash_sales` WHERE `id` = ?",
            [$id]
        );
    }
}
