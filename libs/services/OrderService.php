<?php

/**
 * Order Service - Xử lý logic đơn hàng
 * Tái sử dụng cho cả Client và API
 * Hỗ trợ mua hàng từ API Supplier (SHOPCLONE6, SHOPCLONE7)
 * 
 * @package SHOPKEY
 * @author CMSNT
 * @version 1.1.0
 */

if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

require_once(__DIR__ . '/../../libs/database/users.php');
require_once(__DIR__ . '/../../libs/database/coupon.php');
require_once(__DIR__ . '/../../libs/database/flashsale.php');
require_once(__DIR__ . '/../../libs/database/affiliate.php');
require_once(__DIR__ . '/../../libs/SMTPMailer.php');
require_once(__DIR__ . '/../../libs/TelegramQueue.php');
require_once(__DIR__ . '/../../libs/suppliers/SupplierApiFactory.php');

class OrderService
{
    private $db;
    private $user;
    private $errors = [];
    private $validated_items = [];
    private $coupon = null;
    private $discount_amount = 0;
    private $original_total = 0;
    private $eligible_total = 0;
    private $final_payment = 0;

    // Giới hạn
    const MAX_CART_ITEMS = 20;
    const MAX_QUANTITY_PER_ITEM = 1000000;

    public function __construct()
    {
        global $CMSNT;
        $this->db = $CMSNT;
    }

    /**
     * Lấy danh sách lỗi
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Lấy lỗi đầu tiên
     */
    public function getFirstError(): string
    {
        return $this->errors[0] ?? '';
    }

    /**
     * Thêm lỗi
     */
    private function addError(string $message): void
    {
        $this->errors[] = $message;
    }

    /**
     * Xóa tất cả lỗi (dùng khi muốn bỏ qua lỗi và tiếp tục)
     */
    public function clearErrors(): void
    {
        $this->errors = [];
    }

    /**
     * Xác thực user qua token
     */
    public function authenticateByToken(string $token): bool
    {
        if (empty($token)) {
            $this->addError(__('Token không hợp lệ'));
            return false;
        }

        $token = validate_string($token, 255);
        if ($token === false) {
            $this->addError(__('Token không hợp lệ'));
            return false;
        }

        $user = $this->db->get_row_safe(
            "SELECT * FROM `users` WHERE `token` = ? AND `banned` = 0",
            [$token]
        );

        if (!$user) {
            $this->addError(__('Phiên đăng nhập không hợp lệ'));
            return false;
        }

        $this->user = $user;
        return true;
    }

    /**
     * Xác thực user qua ID (dùng cho API)
     */
    public function authenticateByUserId(int $user_id): bool
    {
        if ($user_id <= 0) {
            $this->addError(__('User ID không hợp lệ'));
            return false;
        }

        $user = $this->db->get_row_safe(
            "SELECT * FROM `users` WHERE `id` = ? AND `banned` = 0",
            [$user_id]
        );

        if (!$user) {
            $this->addError(__('Người dùng không tồn tại hoặc đã bị khóa'));
            return false;
        }

        $this->user = $user;
        return true;
    }

    /**
     * Lấy thông tin user hiện tại
     */
    public function getUser(): ?array
    {
        return $this->user;
    }

    /**
     * Validate giỏ hàng
     * @param array $cart_data Mảng các item trong giỏ hàng
     * @return bool
     */
    public function validateCart(array $cart_data): bool
    {
        if (empty($cart_data)) {
            $this->addError(__('Giỏ hàng trống'));
            return false;
        }

        if (count($cart_data) > self::MAX_CART_ITEMS) {
            $this->addError(sprintf(__('Giỏ hàng không được quá %d sản phẩm'), self::MAX_CART_ITEMS));
            return false;
        }

        $this->validated_items = [];
        $this->original_total = 0;

        foreach ($cart_data as $index => $item) {
            $validated = $this->validateCartItem($item, $index);
            if ($validated === false) {
                return false;
            }

            $this->validated_items[] = $validated;
            $this->original_total += $validated['item_total'];
        }

        return true;
    }

    /**
     * Validate một item trong giỏ hàng
     * Chỉ cần plan_id, product_id sẽ tự động lấy từ plan
     */
    /**
     * @return array|false
     */
    private function validateCartItem(array $item, int $index)
    {
        $plan_id = isset($item['plan_id']) ? validate_int($item['plan_id'], 1) : 0;
        $quantity = isset($item['quantity']) ? validate_int($item['quantity'], 1) : 1;
        $fields_data = isset($item['fields']) && is_array($item['fields']) ? $item['fields'] : [];

        if (!$plan_id) {
            $this->addError(__('Vui lòng cung cấp plan_id'));
            return false;
        }

        if ($quantity < 1 || $quantity > self::MAX_QUANTITY_PER_ITEM) {
            $this->addError(sprintf(__('Số lượng không hợp lệ (1-%d)'), self::MAX_QUANTITY_PER_ITEM));
            return false;
        }

        // Lấy thông tin gói (chỉ cần plan_id)
        $plan = $this->db->get_row_safe(
            "SELECT * FROM `product_plans` WHERE `id` = ? AND `status` = 1",
            [$plan_id]
        );

        if (!$plan) {
            $this->addError(sprintf(
                __('Gói "%s" không tồn tại hoặc đã ngừng bán'),
                $item['plan_name'] ?? '#' . $plan_id
            ));
            return false;
        }

        // Lấy product_id từ plan
        $product_id = $plan['product_id'];

        // Lấy thông tin sản phẩm
        $product = $this->db->get_row_safe(
            "SELECT * FROM `products` WHERE `id` = ? AND `status` = 1",
            [$product_id]
        );

        if (!$product) {
            $this->addError(sprintf(
                __('Sản phẩm của gói "%s" không tồn tại hoặc đã ngừng bán'),
                $plan['name'] ?? '#' . $plan_id
            ));
            return false;
        }

        // Validate fields
        $validated_fields = $this->validatePlanFields($plan_id, $fields_data, $product['name']);
        if ($validated_fields === false) {
            return false;
        }

        // Lấy giá gốc
        $original_price = $plan['price'];
        $original_sale_price = $plan['sale_price'];

        // Áp dụng user discount trước (giảm theo %)
        $user_discount_percent = isset($this->user['discount']) ? (float)$this->user['discount'] : 0;
        if ($user_discount_percent > 0) {
            $original_price = $original_price * (1 - $user_discount_percent / 100);
            if ($original_sale_price > 0) {
                $original_sale_price = $original_sale_price * (1 - $user_discount_percent / 100);
            }
        }

        // Tính giá (ưu tiên Flash Sale > Sale Price > Original Price, đã áp dụng user discount)
        $unit_price = ($original_sale_price > 0 && $original_sale_price < $original_price)
            ? $original_sale_price
            : $original_price;

        // Kiểm tra Flash Sale
        $flash_sale = null;
        $flash_sale_price = null;
        $FlashSaleHandler = new FlashSaleHandler();
        $active_flash_sale = $FlashSaleHandler->getActiveFlashSaleForPlan($plan_id, $product_id);

        if ($active_flash_sale) {
            // Kiểm tra user có thể mua Flash Sale không
            $can_purchase = $FlashSaleHandler->canUserPurchase(
                $this->user['id'],
                $active_flash_sale['id'],
                $quantity
            );

            if ($can_purchase['can_buy']) {
                // Tính giá Flash Sale dựa trên giá đã áp dụng user discount
                $plan_for_flash = [
                    'price' => $original_price,
                    'sale_price' => $original_sale_price
                ];
                $flash_sale_price = $FlashSaleHandler->calculateFlashSalePrice($plan_for_flash, $active_flash_sale);
                $unit_price = $flash_sale_price;
                $flash_sale = $active_flash_sale;
            }
        }

        $item_total = $unit_price * $quantity;

        // Kiểm tra plan có kết nối API Supplier không
        $is_api_product = false;
        $supplier = null;

        if (!empty($plan['supplier_id']) && !empty($plan['api_id'])) {
            $supplier = $this->db->get_row_safe(
                "SELECT * FROM `suppliers` WHERE `id` = ? AND `status` = 1",
                [$plan['supplier_id']]
            );

            if ($supplier) {
                $is_api_product = true;
            }
        }

        // Kiểm tra kho hàng nếu là gói giao ngay
        $is_instant = isset($plan['is_instant']) && $plan['is_instant'] == 1;

        if ($is_instant) {
            if ($is_api_product) {
                // Kiểm tra kho từ API (cột api_stock trong product_plans)
                $api_stock = isset($plan['api_stock']) ? (int)$plan['api_stock'] : 0;

                if ($api_stock < $quantity) {
                    $this->addError(sprintf(
                        __('Sản phẩm "%s" không đủ kho hàng từ API (còn %d)'),
                        html_entity_decode($product['name'], ENT_QUOTES, 'UTF-8'),
                        $api_stock
                    ));
                    return false;
                }
            } else {
                // Kiểm tra kho local
                $stock_count = $this->db->num_rows_safe(
                    "SELECT id FROM `product_stock` WHERE `plan_id` = ? AND `status` = 1",
                    [$plan_id]
                );

                if ($stock_count < $quantity) {
                    $this->addError(sprintf(
                        __('Sản phẩm "%s" không đủ kho hàng (còn %d)'),
                        html_entity_decode($product['name'], ENT_QUOTES, 'UTF-8'),
                        $stock_count
                    ));
                    return false;
                }
            }
        }

        return [
            'product' => $product,
            'plan' => $plan,
            'quantity' => $quantity,
            'unit_price' => $unit_price,
            'item_total' => $item_total,
            'fields_data' => $validated_fields,
            'is_instant' => $is_instant,
            'is_api_product' => $is_api_product,
            'supplier' => $supplier,
            'flash_sale' => $flash_sale,
            'coupon_eligible' => true // Mặc định đủ điều kiện
        ];
    }

    /**
     * Validate fields của gói
     */
    /**
     * @return array|false
     */
    private function validatePlanFields(int $plan_id, array $fields_data, string $product_name)
    {
        $plan_fields = $this->db->get_list_safe(
            "SELECT * FROM `product_fields` WHERE `plan_id` = ? ORDER BY `sort_order` ASC",
            [$plan_id]
        );

        $validated_fields = [];

        foreach ($plan_fields as $field) {
            $field_key = $field['field_key'];
            $field_value = isset($fields_data[$field_key]) ? trim($fields_data[$field_key]) : '';

            // Kiểm tra trường bắt buộc
            if ($field['is_required'] == 1 && empty($field_value)) {
                $this->addError(sprintf(
                    __('Sản phẩm "%s": Vui lòng nhập %s'),
                    html_entity_decode($product_name, ENT_QUOTES, 'UTF-8'),
                    html_entity_decode($field['label'], ENT_QUOTES, 'UTF-8')
                ));
                return false;
            }

            // Validate email
            if ($field['type'] == 'email' && !empty($field_value) && !filter_var($field_value, FILTER_VALIDATE_EMAIL)) {
                $this->addError(sprintf(
                    __('Sản phẩm "%s": %s không hợp lệ'),
                    html_entity_decode($product_name, ENT_QUOTES, 'UTF-8'),
                    html_entity_decode($field['label'], ENT_QUOTES, 'UTF-8')
                ));
                return false;
            }

            $validated_fields[$field_key] = $field_value;
        }

        return $validated_fields;
    }

    /**
     * Áp dụng mã giảm giá
     */
    public function applyCoupon(string $coupon_code): bool
    {
        if (empty($coupon_code)) {
            return true; // Không có coupon vẫn OK
        }

        $coupon_code = trim(strtoupper($coupon_code));
        $CouponHandler = new CouponHandler();

        // Lấy thông tin coupon
        $coupon = $CouponHandler->getCouponByCode($coupon_code);

        if (!$coupon) {
            $this->addError(__('Mã giảm giá không tồn tại hoặc đã bị vô hiệu hóa'));
            return false;
        }

        // Kiểm tra thời gian hiệu lực
        $current_time = time();
        if (!empty($coupon['start_date']) && strtotime($coupon['start_date']) > $current_time) {
            $this->addError(__('Mã giảm giá chưa đến thời gian sử dụng'));
            return false;
        }
        if (!empty($coupon['end_date']) && strtotime($coupon['end_date']) < $current_time) {
            $this->addError(__('Mã giảm giá đã hết hạn'));
            return false;
        }

        // Kiểm tra giới hạn sử dụng
        if ($coupon['usage_limit'] > 0 && $coupon['used_count'] >= $coupon['usage_limit']) {
            $this->addError(__('Mã giảm giá đã hết lượt sử dụng'));
            return false;
        }

        // Kiểm tra giới hạn mỗi user
        if ($coupon['user_limit'] > 0) {
            $user_usage = $this->db->get_row_safe(
                "SELECT COUNT(*) as count FROM `coupon_usages` WHERE `coupon_id` = ? AND `user_id` = ?",
                [$coupon['id'], $this->user['id']]
            );
            if ($user_usage && $user_usage['count'] >= $coupon['user_limit']) {
                $this->addError(__('Bạn đã sử dụng hết lượt cho mã giảm giá này'));
                return false;
            }
        }

        // Lấy danh sách product_ids và plan_ids của coupon
        $coupon_product_ids = !empty($coupon['product_ids']) ? json_decode($coupon['product_ids'], true) : null;
        $coupon_plan_ids = !empty($coupon['plan_ids']) ? json_decode($coupon['plan_ids'], true) : null;

        // Tính tổng tiền đủ điều kiện
        $this->eligible_total = 0;

        foreach ($this->validated_items as &$v_item) {
            $is_eligible = true;

            if ($coupon_product_ids !== null && !in_array($v_item['product']['id'], $coupon_product_ids)) {
                $is_eligible = false;
            }

            if ($coupon_plan_ids !== null && !in_array($v_item['plan']['id'], $coupon_plan_ids)) {
                $is_eligible = false;
            }

            $v_item['coupon_eligible'] = $is_eligible;

            if ($is_eligible) {
                $this->eligible_total += $v_item['item_total'];
            }
        }
        unset($v_item);

        // Kiểm tra có sản phẩm đủ điều kiện không
        if ($this->eligible_total <= 0) {
            $this->addError(__('Không có sản phẩm nào trong giỏ hàng đủ điều kiện áp dụng mã giảm giá này'));
            return false;
        }

        // Kiểm tra giá trị đơn hàng tối thiểu
        if ($coupon['min_order_amount'] > 0 && $this->eligible_total < $coupon['min_order_amount']) {
            $this->addError(sprintf(
                __('Đơn hàng cần đạt tối thiểu %s để áp dụng mã giảm giá này'),
                format_currency($coupon['min_order_amount'])
            ));
            return false;
        }

        // Tính discount
        $this->discount_amount = $CouponHandler->calculateDiscount($coupon, $this->eligible_total);
        $this->coupon = $coupon;

        return true;
    }

    /**
     * Kiểm tra số dư (pre-check, không có lock)
     * Lưu ý: Đây chỉ là kiểm tra sơ bộ, kiểm tra chính xác trong transaction
     */
    public function checkBalance(): bool
    {
        $this->final_payment = max(0, $this->original_total - $this->discount_amount);

        if ($this->user['money'] < $this->final_payment) {
            $this->addError(__('Số dư không đủ. Vui lòng nạp thêm tiền.'));
            return false;
        }

        return true;
    }

    /**
     * Thực hiện thanh toán và tạo đơn hàng
     * Sử dụng transaction với Row Locking để đảm bảo tính nhất quán
     * 
     * @param string $source Nguồn đơn hàng: 'web', 'api'
     * @param string|null $api_key API key nếu từ API
     * @return array|false
     */
    public function processCheckout(string $source = 'web', ?string $api_key = null)
    {
        if (empty($this->validated_items)) {
            $this->addError(__('Giỏ hàng trống'));
            return false;
        }

        if (!$this->user) {
            $this->addError(__('Chưa xác thực người dùng'));
            return false;
        }

        // Bắt đầu transaction cho toàn bộ quá trình
        $this->db->query("START TRANSACTION");

        try {
            // 1. Lock user row để ngăn race condition
            $userLocked = $this->db->get_row_safe(
                "SELECT * FROM `users` WHERE `id` = ? FOR UPDATE",
                [$this->user['id']]
            );

            if (!$userLocked) {
                $this->db->query("ROLLBACK");
                $this->addError(__('Không thể xử lý đơn hàng'));
                return false;
            }

            // 2. Kiểm tra số dư TRONG transaction (với dữ liệu locked)
            if ($userLocked['money'] < $this->final_payment) {
                $this->db->query("ROLLBACK");
                $this->addError(__('Số dư không đủ. Vui lòng nạp thêm tiền.'));
                return false;
            }

            // 3. Kiểm tra và lock stock cho sản phẩm giao ngay
            foreach ($this->validated_items as &$item) {
                if ($item['is_instant']) {
                    $is_api = isset($item['is_api_product']) && $item['is_api_product'] === true;

                    if ($is_api) {
                        // Với gói API: kiểm tra lại api_stock (không cần lock vì stock ở API bên ngoài)
                        $api_stock = isset($item['plan']['api_stock']) ? (int)$item['plan']['api_stock'] : 0;

                        if ($api_stock < $item['quantity']) {
                            $this->db->query("ROLLBACK");
                            $this->addError(sprintf(
                                __('Kho hàng API "%s" không đủ số lượng yêu cầu (có %d, cần %d)'),
                                html_entity_decode($item['product']['name'], ENT_QUOTES, 'UTF-8'),
                                $api_stock,
                                $item['quantity']
                            ));
                            return false;
                        }
                        // Không cần locked_stock cho API product vì stock ở bên ngoài
                    } else {
                        // Với gói local: lock stock từ product_stock
                        $stock_items = $this->db->get_list_safe(
                            "SELECT * FROM `product_stock` WHERE `plan_id` = ? AND `status` = 1 ORDER BY `id` ASC LIMIT ? FOR UPDATE",
                            [$item['plan']['id'], $item['quantity']]
                        );

                        if (count($stock_items) < $item['quantity']) {
                            $this->db->query("ROLLBACK");
                            $this->addError(sprintf(
                                __('Kho hàng "%s" không đủ số lượng yêu cầu (có %d, cần %d)'),
                                html_entity_decode($item['product']['name'], ENT_QUOTES, 'UTF-8'),
                                count($stock_items),
                                $item['quantity']
                            ));
                            return false;
                        }

                        // Lưu stock đã lock vào item
                        $item['locked_stock'] = $stock_items;
                    }
                }
            }
            unset($item); // Xóa reference

            // 4. Lock coupon nếu có để ngăn double usage
            if ($this->coupon) {
                $couponLocked = $this->db->get_row_safe(
                    "SELECT * FROM `coupons` WHERE `id` = ? FOR UPDATE",
                    [$this->coupon['id']]
                );

                if (!$couponLocked || $couponLocked['status'] != 1) {
                    $this->db->query("ROLLBACK");
                    $this->addError(__('Mã giảm giá không còn khả dụng'));
                    return false;
                }

                // Kiểm tra lại usage limit
                if ($couponLocked['usage_limit'] > 0) {
                    $currentUsage = $this->db->num_rows_safe(
                        "SELECT id FROM `coupon_usages` WHERE `coupon_id` = ?",
                        [$couponLocked['id']]
                    );
                    if ($currentUsage >= $couponLocked['usage_limit']) {
                        $this->db->query("ROLLBACK");
                        $this->addError(__('Mã giảm giá đã hết lượt sử dụng'));
                        return false;
                    }
                }
            }

            // 5. Trừ tiền user (trong transaction, đã được bảo vệ bởi FOR UPDATE)

            // Tính số dư mới trước khi cập nhật
            $newBalance = $userLocked['money'] - $this->final_payment;

            // Trừ tiền trực tiếp (an toàn vì đã lock row với FOR UPDATE)
            $deductResult = $this->db->tru(
                'users',
                'money',
                $this->final_payment,
                "`id` = ?",
                [$this->user['id']]
            );

            if (!$deductResult) {
                $this->db->query("ROLLBACK");
                $this->addError(__('Không thể trừ tiền. Vui lòng thử lại.'));
                return false;
            }

            // 6. Tạo đơn hàng cho từng sản phẩm (Hỗ trợ Partial Success)
            // QUAN TRỌNG: Với sản phẩm API, không thể rollback sau khi đã mua từ API bên ngoài
            // Nếu 1 item fail, vẫn phải tính tiền và ghi nhận các item đã thành công
            $created_orders = [];
            $failed_items = [];
            $actual_payment = 0; // Số tiền thực tế cho các đơn thành công

            foreach ($this->validated_items as $itemIndex => $item) {
                // Xóa lỗi trước đó để kiểm tra lỗi của item này
                $errorCountBefore = count($this->errors);

                $order = $this->createOrder($item, $userLocked, $source, $api_key);

                if ($order === false) {
                    // Ghi nhận item thất bại nhưng TIẾP TỤC xử lý các item khác
                    $newErrors = array_slice($this->errors, $errorCountBefore);
                    $failed_items[] = [
                        'product_name' => html_entity_decode($item['product']['name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                        'plan_name' => html_entity_decode($item['plan']['name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                        'quantity' => $item['quantity'],
                        'amount' => $item['item_total'],
                        'error' => implode('; ', $newErrors)
                    ];
                    continue; // Tiếp tục với item tiếp theo
                }

                $created_orders[] = $order;
                $actual_payment += $order['final_amount']; // Cộng dồn số tiền thực tế
            }

            // Kiểm tra: Nếu không có đơn hàng nào thành công
            if (empty($created_orders)) {
                $this->db->query("ROLLBACK");
                // Giữ lại tất cả lỗi đã ghi nhận
                return false;
            }

            // Tính lại số tiền thực tế cần trừ (chỉ cho các đơn thành công)
            // Nếu có coupon, tính lại discount theo tỷ lệ
            $actualDiscount = 0;
            if ($this->discount_amount > 0 && $this->original_total > 0) {
                $actualDiscount = round(($actual_payment / $this->original_total) * $this->discount_amount);
            }
            $actual_payment = max(0, $actual_payment - $actualDiscount);
            $newBalance = $userLocked['money'] - $actual_payment;

            // Hoàn lại số tiền chênh lệch nếu đã trừ nhiều hơn
            $refund_amount = $this->final_payment - $actual_payment;
            if ($refund_amount > 0) {
                $this->db->cong(
                    'users',
                    'money',
                    $refund_amount,
                    "`id` = ?",
                    [$this->user['id']]
                );
                $newBalance = $userLocked['money'] - $actual_payment;
            }

            // 7. Ghi log dongtien với mã đơn hàng cụ thể
            $order_trans_ids = array_column($created_orders, 'trans_id');
            $transid = ($source === 'api' ? 'API_' : 'CART_') . uniqid() . '_' . mt_rand(0, 9999999);
            $reason = ($source === 'api' ? '[API] ' : '') . __('Thanh toán đơn hàng') . ': #' . implode(', #', $order_trans_ids);

            if ($actualDiscount > 0) {
                $reason .= ' - ' . __('Giảm giá') . ': ' . format_currency($actualDiscount);
            }

            if (!empty($failed_items)) {
                $reason .= ' - ' . sprintf(__('%d sản phẩm thất bại'), count($failed_items));
            }

            $this->db->insert("dongtien", [
                'sotientruoc' => $userLocked['money'],
                'sotienthaydoi' => $actual_payment,
                'sotiensau' => $newBalance,
                'thoigian' => gettime(),
                'noidung' => $reason,
                'user_id' => $this->user['id'],
                'transid' => $transid
            ]);

            // 8. Ghi coupon usage trong transaction (chỉ ghi 1 lần cho toàn bộ checkout)
            if ($this->coupon && count($created_orders) > 0) {
                $first_order = $created_orders[0];
                $this->db->insert('coupon_usages', [
                    'coupon_id' => $this->coupon['id'],
                    'coupon_code' => $this->coupon['code'],
                    'user_id' => $this->user['id'],
                    'order_id' => $first_order['order_id'],
                    'order_trans_id' => $first_order['trans_id'],
                    'discount_amount' => $this->discount_amount,
                    'order_amount' => $this->original_total,
                    'used_at' => gettime()
                ]);
            }

            // 9. Commit transaction - tất cả thành công
            $this->db->query("COMMIT");

            // Các thao tác sau commit (không cần rollback nếu lỗi)
            // Cập nhật số lượng đã bán
            $this->updateSoldCount($created_orders);

            // Xử lý hoa hồng affiliate
            $this->processAffiliateCommission($userLocked, $created_orders);

            // Gửi email thông báo
            $this->sendOrderEmail($userLocked, $created_orders);

            // Queue Telegram notifications (async)
            $this->queueTelegramNotifications($userLocked, $created_orders, $newBalance);

            return [
                'orders' => $created_orders,
                'failed_items' => $failed_items, // Danh sách item thất bại (nếu có)
                'original_total' => $this->original_total,
                'discount_amount' => $actualDiscount,
                'coupon_code' => $this->coupon ? $this->coupon['code'] : '',
                'total_amount' => $actual_payment, // Số tiền thực tế đã trừ
                'new_balance' => $newBalance,
                'source' => $source,
                'partial_success' => !empty($failed_items) // Flag để frontend biết có lỗi partial
            ];
        } catch (Exception $e) {
            $this->db->query("ROLLBACK");
            $this->addError(__('Đã xảy ra lỗi, vui lòng thử lại'));
            error_log('OrderService error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Tạo đơn hàng đơn lẻ
     */
    /**
     * @return array|false
     */
    private function createOrder(array $item, array $user, string $source, ?string $api_key)
    {
        $product = $item['product'];
        $plan = $item['plan'];
        $quantity = $item['quantity'];
        $item_total = $item['item_total'];
        $validated_fields = $item['fields_data'];
        $is_instant = $item['is_instant'];
        $is_api_product = isset($item['is_api_product']) && $item['is_api_product'] === true;
        $supplier = $item['supplier'] ?? null;

        // Generate unique trans_id
        $trans_id = $this->generateTransId();

        // Tính discount cho item này trước
        $item_discount = 0;
        $is_coupon_eligible = isset($item['coupon_eligible']) && $item['coupon_eligible'] === true;

        if ($this->discount_amount > 0 && $this->eligible_total > 0 && $is_coupon_eligible) {
            $item_discount = round(($item_total / $this->eligible_total) * $this->discount_amount);
        }
        $item_final_amount = max(0, $item_total - $item_discount);

        // Xử lý theo loại: API Product hoặc Local Stock
        $order_status = 'pending';
        $stock_ids = [];
        $api_accounts = [];
        $api_trans_id = null;

        if ($is_api_product && $supplier) {
            // ========== MUA HÀNG TỪ API SUPPLIER ==========
            $api_result = $this->buyFromSupplierAPI($supplier, $plan, $quantity, $trans_id, $user, $validated_fields);

            if ($api_result === false) {
                // Lỗi đã được thêm trong hàm buyFromSupplierAPI
                return false;
            }

            $order_status = $api_result['order_status'] ?? 'completed';
            $api_accounts = $api_result['accounts'];
            $api_trans_id = $api_result['api_trans_id'];
        } elseif ($is_instant) {
            // ========== SỬ DỤNG KHO LOCAL ==========
            $stock_items = [];

            // Sử dụng stock đã được lock sẵn (từ processCheckout)
            if (isset($item['locked_stock']) && is_array($item['locked_stock'])) {
                $stock_items = $item['locked_stock'];
            } else {
                // Fallback: query lại (trường hợp gọi createOrder riêng lẻ)
                $stock_items = $this->db->get_list_safe(
                    "SELECT * FROM `product_stock` WHERE `plan_id` = ? AND `status` = 1 ORDER BY `id` ASC LIMIT ? FOR UPDATE",
                    [$plan['id'], $quantity]
                );
            }

            if (count($stock_items) < $quantity) {
                $this->addError(sprintf(
                    __('Kho hàng "%s" không đủ số lượng yêu cầu'),
                    html_entity_decode($product['name'], ENT_QUOTES, 'UTF-8')
                ));
                return false;
            }

            foreach ($stock_items as $stock) {
                $stock_ids[] = $stock['id'];
            }

            $order_status = 'completed';
        }

        // Tính giá vốn
        $unit_cost_price = isset($plan['cost_price']) ? floatval($plan['cost_price']) : 0;
        $total_cost_price = $unit_cost_price * $quantity;

        // Tạo đơn hàng
        $order_data = [
            'trans_id' => $trans_id,
            'user_id' => $user['id'],
            'product_id' => $product['id'],
            'plan_id' => $plan['id'],
            'product_name' => $product['name'] ?? '',  // Lưu tên sản phẩm tại thời điểm mua
            'plan_name' => $plan['name'] ?? '',        // Lưu tên gói tại thời điểm mua
            'quantity' => $quantity,
            'total_price' => $plan['price'] * $quantity,
            'sale_price' => $item_total,
            'cost_price' => $total_cost_price,         // Lưu giá vốn tại thời điểm mua
            'discount_amount' => $item_discount,
            'coupon_code' => $this->coupon ? $this->coupon['code'] : '',
            'final_amount' => $item_final_amount,
            'fields_data' => json_encode($validated_fields),
            'status' => $order_status,
            'payment_status' => 'paid',
            'buyer_ip' => myip(),
            'buyer_useragent' => substr(getUserAgent(), 0, 500),
            'is_protected' => ($user['status_view_order'] == 1) ? 1 : 0,
            'order_source' => $source,
            'api_key' => $api_key,
            'supplier_id' => $is_api_product ? $supplier['id'] : null,
            'api_trans_id' => $api_trans_id,
            'created_at' => gettime(),
            'updated_at' => gettime()
        ];

        $order_id = $this->db->insert('product_orders', $order_data);

        if (!$order_id) {
            $this->addError(__('Không thể tạo đơn hàng'));
            return false;
        }

        // Cập nhật stock local nếu có
        if (!$is_api_product && $is_instant && count($stock_ids) > 0) {
            $placeholders = implode(',', array_fill(0, count($stock_ids), '?'));
            $this->db->update('product_stock', [
                'status' => 0,
                'order_id' => $order_id,
                'updated_at' => gettime()
            ], "`id` IN ($placeholders)", $stock_ids);
        }

        // Ghi nhận Flash Sale purchase nếu có
        $flash_sale = isset($item['flash_sale']) ? $item['flash_sale'] : null;
        if ($flash_sale) {
            $FlashSaleHandler = new FlashSaleHandler();
            $FlashSaleHandler->recordPurchase(
                $flash_sale['id'],
                $user['id'],
                $order_id,
                $quantity
            );
        }

        // Lưu tài khoản từ API vào database
        if ($is_api_product && count($api_accounts) > 0) {
            foreach ($api_accounts as $account) {
                $account_value = check_string($account);

                // Lưu vào product_stock với order_id đã có
                $this->db->insert('product_stock', [
                    'plan_id' => $plan['id'],
                    'stock_value' => $account_value,
                    'status' => 0, // Đã bán
                    'order_id' => $order_id,
                    'created_at' => gettime(),
                    'updated_at' => gettime()
                ]);
            }
        }

        return [
            'order_id' => $order_id,
            'trans_id' => $trans_id,
            'product_id' => $product['id'],
            'product_name' => html_entity_decode($product['name'], ENT_QUOTES, 'UTF-8'),
            'plan_id' => $plan['id'],
            'plan_name' => html_entity_decode($plan['name'], ENT_QUOTES, 'UTF-8'),
            'quantity' => $quantity,
            'unit_price' => $item['unit_price'],
            'cost_price' => $total_cost_price,
            'total' => $item_total,
            'discount' => $item_discount,
            'final_amount' => $item_final_amount,
            'status' => $order_status
        ];
    }

    /**
     * Mua hàng từ API Supplier (Sử dụng SupplierApiFactory)
     * Hỗ trợ tất cả các loại API đã đăng ký trong Factory
     * 
     * @param array $supplier Thông tin supplier
     * @param array $plan Thông tin gói sản phẩm
     * @param int $quantity Số lượng mua
     * @param string $trans_id Mã giao dịch local
     * @param array $user Thông tin user
     * @param array $fields Dữ liệu fields (email, username, etc.)
     * @return array|false ['accounts' => [], 'api_trans_id' => string]
     */
    private function buyFromSupplierAPI(array $supplier, array $plan, int $quantity, string $trans_id, array $user, array $fields = [])
    {
        $api_id = $plan['api_id'];

        // Sử dụng Factory để mua hàng (truyền fields để gửi lên API nguồn)
        $result = SupplierApiFactory::buy($supplier, $api_id, $quantity, $fields);

        // Xử lý kết quả
        if (!$result->success) {
            // === GHI LOG LỖI CHI TIẾT VÀO BẢNG LOGS ===
            $logAction = sprintf(
                "[API ORDER ERROR] Supplier: %s (#%d) | Plan: %s (#%d, api_id: %s) | User: %s (#%d) | Trans: %s | Qty: %d | HTTP: %d | Code: %s | Error: %s | Raw: %s",
                $supplier['domain'] ?? 'N/A',
                $supplier['id'] ?? 0,
                $plan['name'] ?? 'N/A',
                $plan['id'] ?? 0,
                $api_id,
                $user['username'] ?? 'N/A',
                $user['id'] ?? 0,
                $trans_id,
                $quantity,
                $result->http_code,
                $result->error_code ?? 'UNKNOWN',
                $result->error_message ?? 'Không rõ lỗi',
                json_encode($result->raw_response ?? [], JSON_UNESCAPED_UNICODE)
            );

            $this->db->insert('logs', [
                'user_id' => 0,
                'ip' => myip(),
                'device' => getUserAgent(),
                'createdate' => gettime(),
                'action' => $logAction
            ]);

            // Gửi thông báo cho admin nếu cần
            if ($result->isOutOfMoney()) {
                $this->notifyAdminAPIError($supplier, $plan, $user, $result->http_code, 'out_of_money');
            } elseif ($result->isConnectionError()) {
                $this->notifyAdminAPIError($supplier, $plan, $user, $result->http_code, 'connection_error');
            }

            // Thêm lỗi với format rõ ràng
            $error_message = $result->error_message;
            if ($result->http_code > 0) {
                $this->addError(sprintf(__('[%s] %s'), $result->http_code, $error_message));
            } else {
                $this->addError(sprintf(__('[API] %s'), $error_message));
            }

            return false;
        }

        return [
            'accounts' => $result->accounts,
            'api_trans_id' => $result->api_trans_id,
            'order_status' => $result->order_status ?? 'completed'
        ];
    }

    /**
     * Gửi thông báo lỗi API cho admin qua Telegram
     */
    private function notifyAdminAPIError(array $supplier, array $plan, array $user, int $http_code, string $error_type): void
    {
        try {
            $template = '';

            if ($error_type == 'out_of_money') {
                $template = $this->db->site('noti_api_out_of_money');
            } else {
                $template = $this->db->site('noti_api_connection_error');
            }

            // Template trống = tắt thông báo
            if (empty($template)) {
                return;
            }

            $message = str_replace([
                '{domain}',
                '{username}',
                '{supplier_name}',
                '{product_name}',
                '{product_id}',
                '{plan_id}',
                '{pay}',
                '{amount}',
                '{ip}',
                '{time}',
                '{http_code}'
            ], [
                $_SERVER['SERVER_NAME'] ?? 'N/A',
                $user['username'] ?? 'N/A',
                $supplier['domain'] ?? 'N/A',
                $plan['name'] ?? 'N/A',
                $plan['product_id'] ?? 'N/A',
                $plan['id'] ?? 'N/A',
                format_currency($plan['price'] ?? 0),
                '1',
                myip(),
                gettime(),
                $http_code
            ], $template);

            // Gửi thông báo (hàm sendMessAdmin phải được định nghĩa)
            if (function_exists('sendMessAdmin')) {
                sendMessAdmin($message);
            }
        } catch (Exception $e) {
            error_log('API Error Notification failed: ' . $e->getMessage());
        }
    }

    /**
     * Queue thông báo Telegram cho đơn hàng
     * Gửi bất đồng bộ, không ảnh hưởng tốc độ checkout
     */
    private function queueTelegramNotifications(array $user, array $orders, float $newBalance): void
    {
        try {
            $telegramQueue = new TelegramQueue();

            // Chuẩn bị dữ liệu orders
            $ordersData = [];
            $pendingOrdersData = [];
            $totalAmount = 0;
            $pendingTotalAmount = 0;

            foreach ($orders as $order) {
                $orderItem = [
                    'trans_id' => $order['trans_id'] ?? '',
                    'product_name' => $order['product_name'] ?? '',
                    'plan_name' => $order['plan_name'] ?? '',
                    'quantity' => $order['quantity'] ?? 1,
                    'total' => $order['final_amount'] ?? 0,
                    'final_amount' => $order['final_amount'] ?? 0
                ];

                $ordersData[] = $orderItem;
                $totalAmount += ($order['final_amount'] ?? 0);

                // Tách đơn hàng ORDER (pending) để gửi thông báo riêng
                if (isset($order['status']) && $order['status'] === 'pending') {
                    $pendingOrdersData[] = $orderItem;
                    $pendingTotalAmount += ($order['final_amount'] ?? 0);
                }
            }

            // Cập nhật số dư mới vào user array
            $user['money'] = $newBalance;

            // Queue thông báo cho Admin (tất cả đơn hàng)
            $telegramQueue->queueOrderNotificationAdmin(
                $user,
                $ordersData,
                $totalAmount,
                $this->discount_amount,
                $this->coupon ? $this->coupon['code'] : ''
            );

            // Queue thông báo cho User (nếu đã liên kết Telegram)
            $telegramQueue->queueOrderNotificationUser(
                $user,
                $ordersData,
                $totalAmount,
                $this->discount_amount,
                $this->coupon ? $this->coupon['code'] : ''
            );

            // Queue thông báo riêng cho đơn hàng ORDER (pending) - cần xử lý thủ công
            if (!empty($pendingOrdersData)) {
                $telegramQueue->queuePendingOrderNotificationAdmin(
                    $user,
                    $pendingOrdersData,
                    $pendingTotalAmount
                );
            }
        } catch (Exception $e) {
            // Không để lỗi queue ảnh hưởng đến checkout
            error_log('Telegram Queue error: ' . $e->getMessage());
        }
    }

    /**
     * Generate mã đơn hàng unique
     */
    private function generateTransId(): string
    {
        $type = $this->db->site('random_transid_order_type') ?: 'string_number';
        $length = (int)($this->db->site('random_transid_order_length') ?: 7);
        $prefix = $this->db->site('prefix_transid_order') ?: '';

        $length = max(6, min(20, $length));

        switch ($type) {
            case 'string':
                $chars = 'QWERTYUIOPASDFGHJKLZXCVBNM';
                break;
            case 'number':
                $chars = '0123456789';
                break;
            case 'string_number':
            default:
                $chars = '123456789QWERTYUIOPASDFGHJKLZXCVBNM';
                break;
        }

        do {
            $random = random($chars, $length);
            $trans_id = $prefix . $random;
        } while ($this->db->num_rows_safe("SELECT id FROM `product_orders` WHERE `trans_id` = ?", [$trans_id]) > 0);

        return $trans_id;
    }

    /**
     * Cập nhật số lượng đã bán
     */
    private function updateSoldCount(array $created_orders): void
    {
        foreach ($created_orders as $order) {
            if ($order['status'] === 'completed') {
                $this->db->cong('products', 'sold', $order['quantity'], "`id` = ?", [$order['product_id']]);
            }
        }
    }

    /**
     * Xử lý hoa hồng affiliate
     */
    private function processAffiliateCommission(array $user, array $created_orders): void
    {
        try {
            $AffiliateHandler = new AffiliateHandler();

            if ($user['ref_id'] > 0 && $this->final_payment > 0) {
                foreach ($created_orders as $order) {
                    $AffiliateHandler->processOrderCommission(
                        $user['id'],
                        $order['order_id'],
                        $order['trans_id'],
                        $order['total']
                    );
                }
            }
        } catch (Exception $e) {
            error_log('Affiliate commission error: ' . $e->getMessage());
        }
    }

    /**
     * Gửi email thông báo đơn hàng
     */
    private function sendOrderEmail(array $user, array $created_orders): void
    {
        try {
            $SMTPMailer = new SMTPMailer();
            if ($SMTPMailer->isEnabled() && !empty($user['email'])) {
                $SMTPMailer->queueOrderSuccessEmail(
                    $user,
                    $created_orders,
                    $this->final_payment,
                    $this->discount_amount,
                    $this->coupon ? $this->coupon['code'] : ''
                );
            }
        } catch (Exception $e) {
            error_log('Order email queue error: ' . $e->getMessage());
        }
    }

    /**
     * Lấy thông tin chi tiết đơn hàng
     */
    public function getOrderDetails(int $order_id, ?int $user_id = null): ?array
    {
        $where = "`id` = ?";
        $params = [$order_id];

        if ($user_id !== null) {
            $where .= " AND `user_id` = ?";
            $params[] = $user_id;
        }

        $order = $this->db->get_row_safe("SELECT * FROM `product_orders` WHERE {$where}", $params);

        if (!$order) {
            return null;
        }

        // Lấy thông tin sản phẩm và gói
        $product = $this->db->get_row_safe("SELECT * FROM `products` WHERE `id` = ?", [$order['product_id']]);
        $plan = $this->db->get_row_safe("SELECT * FROM `product_plans` WHERE `id` = ?", [$order['plan_id']]);

        // Lấy stock items nếu đã giao
        $stock_items = [];
        if ($order['status'] === 'completed') {
            $stock_items = $this->db->get_list_safe(
                "SELECT `stock_value` FROM `product_stock` WHERE `order_id` = ?",
                [$order['id']]
            );
        }

        return [
            'order' => $order,
            'product' => $product,
            'plan' => $plan,
            'stock_items' => array_column($stock_items, 'stock_value'),
            'fields_data' => json_decode($order['fields_data'], true) ?: []
        ];
    }

    /**
     * Lấy danh sách đơn hàng của user
     */
    public function getUserOrders(int $user_id, int $page = 1, int $limit = 10, ?string $status = null): array
    {
        $offset = ($page - 1) * $limit;

        $where = "`user_id` = ?";
        $params = [$user_id];

        if ($status !== null) {
            $where .= " AND `status` = ?";
            $params[] = $status;
        }

        $total = $this->db->num_rows_safe("SELECT id FROM `product_orders` WHERE {$where}", $params);

        $params[] = $offset;
        $params[] = $limit;

        $orders = $this->db->get_list_safe(
            "SELECT po.*, p.name as product_name, pp.name as plan_name 
             FROM `product_orders` po
             LEFT JOIN `products` p ON po.product_id = p.id
             LEFT JOIN `product_plans` pp ON po.plan_id = pp.id
             WHERE {$where}
             ORDER BY po.id DESC
             LIMIT ?, ?",
            $params
        );

        return [
            'orders' => $orders,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ];
    }

    /**
     * Getters cho thông tin thanh toán
     */
    public function getOriginalTotal(): float
    {
        return $this->original_total;
    }

    public function getDiscountAmount(): float
    {
        return $this->discount_amount;
    }

    public function getFinalPayment(): float
    {
        return $this->final_payment;
    }

    public function getCoupon(): ?array
    {
        return $this->coupon;
    }

    public function getValidatedItems(): array
    {
        return $this->validated_items;
    }
}
