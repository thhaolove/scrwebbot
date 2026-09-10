<?php

if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

/**
 * Class CouponHandler
 * Xử lý tất cả logic liên quan đến mã giảm giá (coupon)
 */
class CouponHandler extends DB
{
    /**
     * Validate mã giảm giá
     * 
     * @param string $code Mã giảm giá
     * @param int $user_id ID người dùng
     * @param float $order_total Tổng tiền đơn hàng
     * @param int|null $product_id ID sản phẩm (null nếu không áp dụng)
     * @param string|null $plan_id ID gói (null nếu không áp dụng)
     * @return array ['success' => bool, 'message' => string, 'coupon' => array|null]
     */
    public function validateCoupon(string $code, int $user_id, float $order_total, ?int $product_id = null, ?int $plan_id = null)
    {
        // 1. Kiểm tra mã có tồn tại và đang active không?
        $coupon = $this->get_row_safe(
            "SELECT * FROM `coupons` WHERE `code` = ? AND `status` = 1",
            [strtoupper(trim($code))]
        );

        if (!$coupon) {
            return [
                'success' => false,
                'message' => 'Mã giảm giá không tồn tại hoặc đã bị vô hiệu hóa',
                'coupon' => null
            ];
        }

        // 2. Kiểm tra thời gian hiệu lực
        $current_time = time();

        if (!empty($coupon['start_date'])) {
            $start_time = strtotime($coupon['start_date']);
            if ($start_time > $current_time) {
                return [
                    'success' => false,
                    'message' => 'Mã giảm giá chưa đến thời gian sử dụng',
                    'coupon' => null
                ];
            }
        }

        if (!empty($coupon['end_date'])) {
            $end_time = strtotime($coupon['end_date']);
            if ($end_time < $current_time) {
                return [
                    'success' => false,
                    'message' => 'Mã giảm giá đã hết hạn',
                    'coupon' => null
                ];
            }
        }

        // 3. Kiểm tra giới hạn tổng số lần sử dụng
        if ($coupon['usage_limit'] > 0 && $coupon['used_count'] >= $coupon['usage_limit']) {
            return [
                'success' => false,
                'message' => 'Mã giảm giá đã hết lượt sử dụng',
                'coupon' => null
            ];
        }

        // 4. Kiểm tra giới hạn số lần mỗi user
        if ($coupon['user_limit'] > 0) {
            $user_usage = $this->get_row_safe(
                "SELECT COUNT(*) as count FROM `coupon_usages` WHERE `coupon_id` = ? AND `user_id` = ?",
                [$coupon['id'], $user_id]
            );

            if ($user_usage && $user_usage['count'] >= $coupon['user_limit']) {
                return [
                    'success' => false,
                    'message' => 'Bạn đã sử dụng hết lượt cho mã giảm giá này',
                    'coupon' => null
                ];
            }
        }

        // 5. Kiểm tra giá trị đơn hàng tối thiểu
        if ($coupon['min_order_amount'] > 0 && $order_total < $coupon['min_order_amount']) {
            return [
                'success' => false,
                'message' => 'Đơn hàng chưa đạt giá trị tối thiểu để áp dụng mã giảm giá',
                'coupon' => null
            ];
        }

        // 6. Kiểm tra sản phẩm/gói có được áp dụng không?
        $product_ids = !empty($coupon['product_ids']) ? json_decode($coupon['product_ids'], true) : null;
        $plan_ids = !empty($coupon['plan_ids']) ? json_decode($coupon['plan_ids'], true) : null;

        // Kiểm tra product_id nếu có
        if ($product_id !== null && $product_ids !== null && !in_array($product_id, $product_ids)) {
            return [
                'success' => false,
                'message' => 'Mã giảm giá không áp dụng cho sản phẩm này',
                'coupon' => null
            ];
        }

        // Kiểm tra plan_id nếu có
        if ($plan_id !== null && $plan_ids !== null && !in_array($plan_id, $plan_ids)) {
            return [
                'success' => false,
                'message' => 'Mã giảm giá không áp dụng cho gói này',
                'coupon' => null
            ];
        }

        // Tất cả điều kiện đều hợp lệ
        return [
            'success' => true,
            'message' => 'Mã giảm giá hợp lệ',
            'coupon' => $coupon
        ];
    }

    /**
     * Tính toán số tiền giảm giá
     * 
     * @param array $coupon Thông tin mã giảm giá
     * @param float $order_total Tổng tiền đơn hàng
     * @return float Số tiền được giảm
     */
    public function calculateDiscount($coupon, $order_total)
    {
        $discount_amount = 0;

        if ($coupon['type'] == 'percentage') {
            // Tính theo phần trăm
            $discount_amount = $order_total * ($coupon['value'] / 100);

            // Áp dụng giới hạn tối đa nếu có
            if ($coupon['max_discount_amount'] > 0 && $discount_amount > $coupon['max_discount_amount']) {
                $discount_amount = $coupon['max_discount_amount'];
            }
        } else {
            // Tính theo số tiền cố định
            $discount_amount = $coupon['value'];

            // Không được giảm quá tổng tiền đơn hàng
            if ($discount_amount > $order_total) {
                $discount_amount = $order_total;
            }
        }

        // Đảm bảo không âm
        return max(0, $discount_amount);
    }

    /**
     * Áp dụng mã giảm giá vào đơn hàng
     * Bao gồm: validate, tính discount, lưu lịch sử, cập nhật số lần sử dụng
     * 
     * @param string $code Mã giảm giá
     * @param int $user_id ID người dùng
     * @param float $order_total Tổng tiền đơn hàng
     * @param int|null $product_id ID sản phẩm
     * @param string|null $plan_id ID gói
     * @return array ['success' => bool, 'message' => string, 'discount_amount' => float, 'final_amount' => float, 'coupon' => array|null]
     */
    public function applyCoupon(string $code, int $user_id, float $order_total, ?int $product_id = null, ?int $plan_id = null)
    {
        // Bước 1: Validate mã giảm giá
        $validation = $this->validateCoupon($code, $user_id, $order_total, $product_id, $plan_id);

        if (!$validation['success']) {
            return [
                'success' => false,
                'message' => $validation['message'],
                'discount_amount' => 0,
                'final_amount' => $order_total,
                'coupon' => null
            ];
        }

        $coupon = $validation['coupon'];

        // Bước 2: Tính toán discount
        $discount_amount = $this->calculateDiscount($coupon, $order_total);
        $final_amount = max(0, $order_total - $discount_amount);

        return [
            'success' => true,
            'message' => 'Áp dụng mã giảm giá thành công',
            'discount_amount' => $discount_amount,
            'final_amount' => $final_amount,
            'coupon' => $coupon
        ];
    }

    /**
     * Ghi lại lịch sử sử dụng mã giảm giá sau khi thanh toán thành công
     * 
     * @param int $coupon_id ID mã giảm giá
     * @param string $coupon_code Mã giảm giá
     * @param int $user_id ID người dùng
     * @param int $order_id ID đơn hàng
     * @param string $order_trans_id Mã đơn hàng
     * @param float $discount_amount Số tiền đã giảm
     * @param float $order_amount Tổng tiền đơn hàng
     * @return bool
     */
    public function recordCouponUsage($coupon_id, $coupon_code, $user_id, $order_id, $order_trans_id, $discount_amount, $order_amount)
    {
        // Bắt đầu transaction
        $this->query("START TRANSACTION");

        try {
            // Ghi lại lịch sử sử dụng
            $isInsert = $this->insert("coupon_usages", [
                'coupon_id' => $coupon_id,
                'coupon_code' => $coupon_code,
                'user_id' => $user_id,
                'order_id' => $order_id,
                'order_trans_id' => $order_trans_id,
                'discount_amount' => $discount_amount,
                'order_amount' => $order_amount,
                'used_at' => gettime()
            ]);

            if (!$isInsert) {
                $this->query("ROLLBACK");
                return false;
            }

            // Cập nhật số lần sử dụng
            $coupon = $this->get_row_safe("SELECT `used_count` FROM `coupons` WHERE `id` = ?", [$coupon_id]);
            if ($coupon) {
                $isUpdate = $this->update(
                    "coupons",
                    [
                        'used_count' => $coupon['used_count'] + 1,
                        'updated_at' => gettime()
                    ],
                    "`id` = ?",
                    [$coupon_id]
                );

                if (!$isUpdate) {
                    $this->query("ROLLBACK");
                    return false;
                }
            }

            // Commit transaction
            $this->query("COMMIT");
            return true;
        } catch (Exception $e) {
            $this->query("ROLLBACK");
            return false;
        }
    }

    /**
     * Hủy việc sử dụng mã giảm giá (khi hủy đơn hàng)
     * 
     * @param int $order_id ID đơn hàng
     * @return bool
     */
    public function cancelCouponUsage($order_id)
    {
        // Lấy thông tin coupon usage
        $usage = $this->get_row_safe(
            "SELECT * FROM `coupon_usages` WHERE `order_id` = ?",
            [$order_id]
        );

        if (!$usage) {
            return true; // Không có coupon usage, không cần xử lý
        }

        // Bắt đầu transaction
        $this->query("START TRANSACTION");

        try {
            // Xóa record trong coupon_usages
            $isDelete = $this->remove("coupon_usages", "`order_id` = ?", [$order_id]);

            if (!$isDelete) {
                $this->query("ROLLBACK");
                return false;
            }

            // Giảm số lần sử dụng
            $coupon = $this->get_row_safe("SELECT `used_count` FROM `coupons` WHERE `id` = ?", [$usage['coupon_id']]);
            if ($coupon && $coupon['used_count'] > 0) {
                $isUpdate = $this->update(
                    "coupons",
                    [
                        'used_count' => max(0, $coupon['used_count'] - 1),
                        'updated_at' => gettime()
                    ],
                    "`id` = ?",
                    [$usage['coupon_id']]
                );

                if (!$isUpdate) {
                    $this->query("ROLLBACK");
                    return false;
                }
            }

            // Commit transaction
            $this->query("COMMIT");
            return true;
        } catch (Exception $e) {
            $this->query("ROLLBACK");
            return false;
        }
    }

    /**
     * Lấy thông tin mã giảm giá theo code
     * 
     * @param string $code Mã giảm giá
     * @return array|null
     */
    public function getCouponByCode($code)
    {
        return $this->get_row_safe(
            "SELECT * FROM `coupons` WHERE `code` = ?",
            [strtoupper(trim($code))]
        );
    }

    /**
     * Lấy lịch sử sử dụng mã giảm giá của user
     * 
     * @param int $user_id ID người dùng
     * @param int|null $coupon_id ID mã giảm giá (null để lấy tất cả)
     * @return array
     */
    public function getUserCouponUsage(int $user_id, ?int $coupon_id = null)
    {
        if ($coupon_id !== null) {
            return $this->get_list_safe(
                "SELECT * FROM `coupon_usages` WHERE `user_id` = ? AND `coupon_id` = ? ORDER BY `used_at` DESC",
                [$user_id, $coupon_id]
            );
        } else {
            return $this->get_list_safe(
                "SELECT * FROM `coupon_usages` WHERE `user_id` = ? ORDER BY `used_at` DESC",
                [$user_id]
            );
        }
    }

    /**
     * Đếm số lần user đã sử dụng một mã giảm giá
     * 
     * @param int $user_id ID người dùng
     * @param int $coupon_id ID mã giảm giá
     * @return int
     */
    public function getUserCouponUsageCount($user_id, $coupon_id)
    {
        $result = $this->get_row_safe(
            "SELECT COUNT(*) as count FROM `coupon_usages` WHERE `user_id` = ? AND `coupon_id` = ?",
            [$user_id, $coupon_id]
        );

        return $result ? (int)$result['count'] : 0;
    }
}
