<?php

/**
 * Affiliate Handler Class
 * Xử lý logic tiếp thị liên kết
 * 
 * @author CMSNT.CO
 * @version 2.0
 */

if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

class AffiliateHandler extends DB
{
    protected $_table_users = 'users';
    protected $_table_aff_log = 'aff_log';
    protected $_table_aff_withdraw = 'aff_withdraw';
    protected $_table_commissions = 'affiliate_commissions';
    protected $_table_clicks = 'affiliate_clicks';
    protected $_table_stats = 'affiliate_stats';

    public function __construct()
    {
        parent::connect();
    }

    public function __destruct()
    {
        parent::dis_connect();
    }
    
    // =====================================================
    // QUẢN LÝ REFERRAL CODE
    // =====================================================

    /**
     * Tạo ref_code mới cho user
     * @param int $user_id
     * @return string
     */
    public function generateRefCode($user_id)
    {
        $code = strtoupper(substr(md5($user_id . time() . mt_rand()), 0, 8));

        // Đảm bảo code là duy nhất
        while ($this->num_rows_safe("SELECT id FROM users WHERE ref_code = ?", [$code]) > 0) {
            $code = strtoupper(substr(md5($user_id . time() . mt_rand()), 0, 8));
        }

        // Cập nhật cho user
        $this->update('users', ['ref_code' => $code], "`id` = ?", [$user_id]);

        return $code;
    }

    /**
     * Lấy thông tin user từ ref_code
     * @param string $ref_code
     * @return array|false
     */
    public function getUserByRefCode($ref_code)
    {
        $ref_code = trim($ref_code);
        if (empty($ref_code)) {
            return false;
        }

        return $this->get_row_safe(
            "SELECT id, username, ref_code, ref_click, ref_ck FROM users WHERE ref_code = ? AND banned = 0",
            [$ref_code]
        );
    }

    /**
     * Lấy thông tin user từ ID (legacy support)
     * @param int $user_id
     * @return array|false
     */
    public function getUserById($user_id)
    {
        $user_id = intval($user_id);
        if ($user_id <= 0) {
            return false;
        }

        return $this->get_row_safe(
            "SELECT id, username, ref_code, ref_click, ref_ck FROM users WHERE id = ? AND banned = 0",
            [$user_id]
        );
    }
    
    // =====================================================
    // QUẢN LÝ CLICK
    // =====================================================

    /**
     * Ghi nhận click affiliate
     * @param int $user_id - ID người sở hữu link
     * @param string $ip_address
     * @param string $user_agent
     * @param string $referer
     * @return bool
     */
    public function recordClick(int $user_id, ?string $ip_address = null, ?string $user_agent = null, ?string $referer = null)
    {
        $user_id = intval($user_id);
        if ($user_id <= 0) {
            return false;
        }

        $ip_address = $ip_address ?: myip();
        $user_agent = $user_agent ?: getUserAgent();

        // Kiểm tra click unique (trong 24h)
        $is_unique = 1;
        $existing = $this->num_rows_safe(
            "SELECT id FROM affiliate_clicks WHERE user_id = ? AND ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            [$user_id, $ip_address]
        );

        if ($existing > 0) {
            $is_unique = 0;
        }

        // Insert click record
        $inserted = $this->insert('affiliate_clicks', [
            'user_id' => $user_id,
            'ip_address' => $ip_address,
            'user_agent' => substr($user_agent, 0, 500),
            'referer' => $referer ? substr($referer, 0, 500) : null,
            'is_unique' => $is_unique,
            'created_at' => gettime()
        ]);

        if ($inserted) {
            // Cập nhật tổng click cho user
            $this->cong('users', 'ref_click', 1, "`id` = ?", [$user_id]);
            return true;
        }

        return false;
    }

    /**
     * Lấy thống kê click theo khoảng thời gian
     * @param int $user_id
     * @param string $period - today, week, month, all
     * @return array
     */
    public function getClickStats($user_id, $period = 'all')
    {
        $user_id = intval($user_id);
        $where = "user_id = ?";
        $params = [$user_id];

        switch ($period) {
            case 'today':
                $where .= " AND DATE(created_at) = CURDATE()";
                break;
            case 'week':
                $where .= " AND YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)";
                break;
            case 'month':
                $where .= " AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())";
                break;
        }

        $result = $this->get_row_safe(
            "SELECT 
                COUNT(*) as total_clicks,
                SUM(is_unique) as unique_clicks
            FROM affiliate_clicks WHERE $where",
            $params
        );

        return [
            'total_clicks' => intval($result['total_clicks'] ?? 0),
            'unique_clicks' => intval($result['unique_clicks'] ?? 0)
        ];
    }
    
    // =====================================================
    // QUẢN LÝ HOA HỒNG
    // =====================================================

    /**
     * Thêm hoa hồng cho user
     * @param int $ref_id - ID người nhận hoa hồng
     * @param int $referral_id - ID người tạo hoa hồng
     * @param float $amount - Số tiền hoa hồng
     * @param string $reason - Lý do
     * @param string $type - Loại: recharge, order, signup
     * @param int $source_id - ID nguồn (order_id hoặc transaction_id)
     * @param string $source_trans_id - Mã giao dịch nguồn
     * @param float $source_amount - Số tiền nguồn
     * @param float $commission_rate - Tỷ lệ hoa hồng
     * @return bool
     */
    public function addCommission(int $ref_id, int $referral_id, float $amount, string $reason, string $type = 'recharge', ?int $source_id = null, ?string $source_trans_id = null, float $source_amount = 0, float $commission_rate = 0)
    {
        $ref_id = intval($ref_id);
        $referral_id = intval($referral_id);
        $amount = floatval($amount);

        if ($ref_id <= 0 || $amount <= 0) {
            return false;
        }

        // Kiểm tra số tiền tối thiểu
        $min_commission = $this->site('affiliate_min_commission') ?: 1000;
        if ($amount < $min_commission) {
            return false;
        }

        // Lấy số dư hiện tại
        $current_balance = floatval(getUser($ref_id, 'ref_price'));

        // Bắt đầu transaction
        $this->query("START TRANSACTION");

        try {
            // 1. Insert vào aff_log
            $log_inserted = $this->insert($this->_table_aff_log, [
                'user_id' => $ref_id,
                'type' => $type,
                'referral_id' => $referral_id,
                'sotientruoc' => $current_balance,
                'sotienthaydoi' => $amount,
                'sotienhientai' => $current_balance + $amount,
                'reason' => $reason,
                'create_gettime' => gettime()
            ]);

            if (!$log_inserted) {
                $this->query("ROLLBACK");
                return false;
            }

            // 2. Insert vào affiliate_commissions (chi tiết)
            $this->insert($this->_table_commissions, [
                'user_id' => $ref_id,
                'referral_id' => $referral_id,
                'type' => $type,
                'source_id' => $source_id,
                'source_trans_id' => $source_trans_id,
                'source_amount' => $source_amount,
                'commission_rate' => $commission_rate,
                'commission_amount' => $amount,
                'status' => 'approved',
                'created_at' => gettime()
            ]);

            // 3. Cập nhật số dư hoa hồng
            $this->cong('users', 'ref_price', $amount, "`id` = '$ref_id'");
            $this->cong('users', 'ref_total_price', $amount, "`id` = '$ref_id'");

            // 4. Cập nhật ref_amount cho người tạo hoa hồng
            $this->cong('users', 'ref_amount', $amount, "`id` = '$referral_id'");

            $this->query("COMMIT");

            // Gửi thông báo Telegram nếu cấu hình
            $this->sendCommissionNotification($ref_id, $referral_id, $amount, $type);

            return true;
        } catch (Exception $e) {
            $this->query("ROLLBACK");
            return false;
        }
    }

    /**
     * Tính và cộng hoa hồng từ nạp tiền
     * @param int $referral_id - ID người nạp tiền
     * @param float $recharge_amount - Số tiền nạp
     * @param string $trans_id - Mã giao dịch nạp tiền
     * @return bool
     */
    public function processRechargeCommission(int $referral_id, float $recharge_amount, ?string $trans_id = null)
    {
        // Kiểm tra affiliate có bật không
        if ($this->site('affiliate_status') != 1 || $this->site('affiliate_recharge_status') != 1) {
            return false;
        }

        // Lấy thông tin user và referrer
        $user = $this->get_row_safe("SELECT id, username, ref_id FROM users WHERE id = ?", [$referral_id]);
        if (!$user || $user['ref_id'] <= 0) {
            return false;
        }

        $referrer = $this->getUserById($user['ref_id']);
        if (!$referrer) {
            return false;
        }

        // Lấy tỷ lệ hoa hồng (ưu tiên cấu hình riêng cho user)
        $commission_rate = $this->site('affiliate_ck') ?: 0;
        if ($referrer['ref_ck'] > 0) {
            $commission_rate = $referrer['ref_ck'];
        }

        if ($commission_rate <= 0) {
            return false;
        }

        // Tính số tiền hoa hồng
        $commission_amount = $recharge_amount * $commission_rate / 100;

        $reason = __('Hoa hồng nạp tiền từ') . ' ' . $user['username'] . ' (' . format_currency($recharge_amount) . ')';

        return $this->addCommission(
            $referrer['id'],
            $user['id'],
            $commission_amount,
            $reason,
            'recharge',
            null,
            $trans_id,
            $recharge_amount,
            $commission_rate
        );
    }

    /**
     * Tính và cộng hoa hồng từ đơn hàng sản phẩm
     * @param int $referral_id - ID người mua hàng
     * @param int $order_id - ID đơn hàng
     * @param string $trans_id - Mã đơn hàng
     * @param float $order_amount - Giá trị đơn hàng
     * @return bool
     */
    public function processOrderCommission($referral_id, $order_id, $trans_id, $order_amount)
    {
        // Kiểm tra affiliate có bật không
        if ($this->site('affiliate_status') != 1 || $this->site('affiliate_order_status') != 1) {
            return false;
        }

        // Lấy thông tin user và referrer
        $user = $this->get_row_safe("SELECT id, username, ref_id FROM users WHERE id = ?", [$referral_id]);
        if (!$user || $user['ref_id'] <= 0) {
            return false;
        }

        $referrer = $this->getUserById($user['ref_id']);
        if (!$referrer) {
            return false;
        }

        // Lấy tỷ lệ hoa hồng đơn hàng
        $commission_rate = $this->site('affiliate_order_ck') ?: 0;

        // Kiểm tra nếu user có rate riêng (có thể thêm logic custom rate cho order)

        if ($commission_rate <= 0) {
            return false;
        }

        // Tính số tiền hoa hồng
        $commission_amount = $order_amount * $commission_rate / 100;

        $reason = __('Hoa hồng đơn hàng') . ' #' . $trans_id . ' ' . __('từ') . ' ' . $user['username'] . ' (' . format_currency($order_amount) . ')';

        $result = $this->addCommission(
            $referrer['id'],
            $user['id'],
            $commission_amount,
            $reason,
            'order',
            $order_id,
            $trans_id,
            $order_amount,
            $commission_rate
        );

        // Cập nhật commission_amount vào đơn hàng nếu thành công
        if ($result && $order_id > 0) {
            $this->update('product_orders', [
                'commission_amount' => $commission_amount,
                'commission_user_id' => $referrer['id']
            ], "`id` = ?", [$order_id]);
        }

        return $result;
    }

    /**
     * Trừ hoa hồng (khi rút tiền hoặc hoàn trả)
     * @param int $user_id
     * @param float $amount
     * @param string $reason
     * @param string $type - withdraw, refund
     * @return bool
     */
    public function removeCommission($user_id, $amount, $reason, $type = 'withdraw')
    {
        $user_id = intval($user_id);
        $amount = floatval($amount);

        if ($user_id <= 0 || $amount <= 0) {
            return false;
        }

        // Lấy số dư hiện tại
        $current_balance = floatval(getUser($user_id, 'ref_price'));

        if ($current_balance < $amount) {
            return false;
        }

        // Bắt đầu transaction
        $this->query("START TRANSACTION");

        try {
            // Insert log
            $log_inserted = $this->insert($this->_table_aff_log, [
                'user_id' => $user_id,
                'type' => $type,
                'referral_id' => null,
                'sotientruoc' => $current_balance,
                'sotienthaydoi' => $amount,
                'sotienhientai' => $current_balance - $amount,
                'reason' => $reason,
                'create_gettime' => gettime()
            ]);

            if (!$log_inserted) {
                $this->query("ROLLBACK");
                return false;
            }

            // Trừ số dư
            $this->tru('users', 'ref_price', $amount, "`id` = '$user_id'");

            $this->query("COMMIT");
            return true;
        } catch (Exception $e) {
            $this->query("ROLLBACK");
            return false;
        }
    }

    /**
     * Hoàn trả hoa hồng (khi hủy rút tiền)
     * @param int $user_id
     * @param float $amount
     * @param string $reason
     * @return bool
     */
    public function refundCommission($user_id, $amount, $reason)
    {
        $user_id = intval($user_id);
        $amount = floatval($amount);

        if ($user_id <= 0 || $amount <= 0) {
            return false;
        }

        // Lấy số dư hiện tại
        $current_balance = floatval(getUser($user_id, 'ref_price'));

        // Bắt đầu transaction
        $this->query("START TRANSACTION");

        try {
            // Insert log
            $log_inserted = $this->insert($this->_table_aff_log, [
                'user_id' => $user_id,
                'type' => 'refund',
                'referral_id' => null,
                'sotientruoc' => $current_balance,
                'sotienthaydoi' => $amount,
                'sotienhientai' => $current_balance + $amount,
                'reason' => $reason,
                'create_gettime' => gettime()
            ]);

            if (!$log_inserted) {
                $this->query("ROLLBACK");
                return false;
            }

            // Cộng số dư
            $this->cong('users', 'ref_price', $amount, "`id` = '$user_id'");

            $this->query("COMMIT");
            return true;
        } catch (Exception $e) {
            $this->query("ROLLBACK");
            return false;
        }
    }
    
    // =====================================================
    // QUẢN LÝ RÚT TIỀN
    // =====================================================

    /**
     * Tạo yêu cầu rút tiền
     * @param int $user_id
     * @param float $amount
     * @param string $bank
     * @param string $account_number
     * @param string $account_name
     * @return array ['status' => bool, 'msg' => string, 'trans_id' => string]
     */
    public function createWithdrawRequest($user_id, $amount, $bank, $account_number, $account_name)
    {
        $user_id = intval($user_id);
        $amount = floatval($amount);

        // Validate
        if ($user_id <= 0) {
            return ['status' => false, 'msg' => __('User không hợp lệ')];
        }

        $min_amount = $this->site('affiliate_min') ?: 100000;
        if ($amount < $min_amount) {
            return ['status' => false, 'msg' => __('Số tiền rút tối thiểu là') . ' ' . format_currency($min_amount)];
        }

        if (empty($bank) || empty($account_number) || empty($account_name)) {
            return ['status' => false, 'msg' => __('Vui lòng điền đầy đủ thông tin ngân hàng')];
        }

        // Lock user row
        $this->query("START TRANSACTION");

        try {
            $user = $this->get_row_safe("SELECT * FROM users WHERE id = ? FOR UPDATE", [$user_id]);

            if (!$user) {
                $this->query("ROLLBACK");
                return ['status' => false, 'msg' => __('Không tìm thấy user')];
            }

            if ($user['ref_price'] < $amount) {
                $this->query("ROLLBACK");
                return ['status' => false, 'msg' => __('Số dư hoa hồng không đủ')];
            }

            // Generate trans_id
            $trans_id = strtoupper(substr(md5(time() . mt_rand()), 0, 8));
            while ($this->num_rows_safe("SELECT id FROM aff_withdraw WHERE trans_id = ?", [$trans_id]) > 0) {
                $trans_id = strtoupper(substr(md5(time() . mt_rand()), 0, 8));
            }

            // Trừ số dư
            $this->removeCommission($user_id, $amount, __('Rút tiền hoa hồng') . ' #' . $trans_id, 'withdraw');

            // Tạo yêu cầu rút tiền
            $withdraw_id = $this->insert($this->_table_aff_withdraw, [
                'trans_id' => $trans_id,
                'user_id' => $user_id,
                'bank' => $bank,
                'stk' => $account_number,
                'name' => $account_name,
                'amount' => $amount,
                'status' => 'pending',
                'create_gettime' => gettime(),
                'update_gettime' => gettime()
            ]);

            if (!$withdraw_id) {
                $this->query("ROLLBACK");
                return ['status' => false, 'msg' => __('Không thể tạo yêu cầu rút tiền')];
            }

            $this->query("COMMIT");

            return [
                'status' => true,
                'msg' => __('Yêu cầu rút tiền đã được tạo thành công'),
                'trans_id' => $trans_id
            ];
        } catch (Exception $e) {
            $this->query("ROLLBACK");
            return ['status' => false, 'msg' => __('Đã xảy ra lỗi, vui lòng thử lại')];
        }
    }

    /**
     * Xử lý yêu cầu rút tiền (Admin)
     * @param int $withdraw_id
     * @param string $status - completed, cancel
     * @param string $reason
     * @param int $admin_id
     * @return bool
     */
    public function processWithdrawRequest($withdraw_id, $status, $reason = '', $admin_id = 0)
    {
        $withdraw = $this->get_row_safe("SELECT * FROM aff_withdraw WHERE id = ?", [$withdraw_id]);

        if (!$withdraw) {
            return false;
        }

        // Không cho phép thay đổi nếu đã cancel
        if ($withdraw['status'] == 'cancel') {
            return false;
        }

        // Nếu cancel thì hoàn tiền
        if ($status == 'cancel' && $withdraw['status'] != 'cancel') {
            $this->refundCommission(
                $withdraw['user_id'],
                $withdraw['amount'],
                __('Hoàn tiền do hủy yêu cầu rút tiền') . ' #' . $withdraw['trans_id']
            );
        }

        // Cập nhật trạng thái
        return $this->update($this->_table_aff_withdraw, [
            'status' => $status,
            'reason' => $reason,
            'processed_by' => $admin_id,
            'processed_at' => gettime(),
            'update_gettime' => gettime()
        ], "`id` = ?", [$withdraw_id]);
    }
    
    // =====================================================
    // THỐNG KÊ
    // =====================================================

    /**
     * Lấy thống kê affiliate của user
     * @param int $user_id
     * @return array
     */
    public function getUserStats($user_id)
    {
        $user_id = intval($user_id);

        $user = $this->get_row_safe(
            "SELECT ref_code, ref_click, ref_price, ref_total_price, ref_ck FROM users WHERE id = ?",
            [$user_id]
        );

        if (!$user) {
            return [];
        }

        // Đếm số người đã giới thiệu
        $total_referrals = $this->get_row_safe(
            "SELECT COUNT(*) as total FROM users WHERE ref_id = ?",
            [$user_id]
        )['total'] ?? 0;

        // Thống kê hoa hồng theo loại
        $commission_stats = $this->get_row_safe(
            "SELECT 
                SUM(CASE WHEN type = 'recharge' THEN commission_amount ELSE 0 END) as recharge_commission,
                SUM(CASE WHEN type = 'order' THEN commission_amount ELSE 0 END) as order_commission,
                COUNT(CASE WHEN type = 'order' THEN 1 END) as total_orders
            FROM affiliate_commissions WHERE user_id = ?",
            [$user_id]
        );

        // Tổng đã rút
        $total_withdrawn = $this->get_row_safe(
            "SELECT COALESCE(SUM(amount), 0) as total FROM aff_withdraw WHERE user_id = ? AND status = 'completed'",
            [$user_id]
        )['total'] ?? 0;

        // Thống kê click
        $click_stats = $this->getClickStats($user_id, 'all');

        return [
            'ref_code' => $user['ref_code'],
            'ref_click' => $user['ref_click'],
            'available_balance' => floatval($user['ref_price']),
            'total_earned' => floatval($user['ref_total_price']),
            'custom_rate' => floatval($user['ref_ck']),
            'total_referrals' => intval($total_referrals),
            'recharge_commission' => floatval($commission_stats['recharge_commission'] ?? 0),
            'order_commission' => floatval($commission_stats['order_commission'] ?? 0),
            'total_orders' => intval($commission_stats['total_orders'] ?? 0),
            'total_withdrawn' => floatval($total_withdrawn),
            'total_clicks' => $click_stats['total_clicks'],
            'unique_clicks' => $click_stats['unique_clicks']
        ];
    }

    /**
     * Lấy danh sách người được giới thiệu
     * @param int $user_id
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getReferrals($user_id, $limit = 10, $offset = 0)
    {
        return $this->get_list_safe(
            "SELECT id, username, create_date, total_money, ref_amount 
            FROM users 
            WHERE ref_id = ? 
            ORDER BY id DESC 
            LIMIT ?, ?",
            [$user_id, $offset, $limit]
        );
    }

    /**
     * Đếm số người được giới thiệu
     * @param int $user_id
     * @return int
     */
    public function countReferrals($user_id)
    {
        return $this->get_row_safe(
            "SELECT COUNT(*) as total FROM users WHERE ref_id = ?",
            [$user_id]
        )['total'] ?? 0;
    }

    /**
     * Lấy danh sách lịch sử hoa hồng
     * @param int $user_id
     * @param array $filters
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getCommissionHistory($user_id, $filters = [], $limit = 10, $offset = 0)
    {
        $where = "`user_id` = ?";
        $params = [$user_id];

        // Filter by type
        if (!empty($filters['type']) && in_array($filters['type'], ['recharge', 'order', 'withdraw', 'refund', 'manual', 'signup'])) {
            $where .= " AND `type` = ?";
            $params[] = $filters['type'];
        }

        // Filter by date range
        if (!empty($filters['start_date'])) {
            $where .= " AND `create_gettime` >= ?";
            $params[] = $filters['start_date'] . ' 00:00:00';
        }

        if (!empty($filters['end_date'])) {
            $where .= " AND `create_gettime` <= ?";
            $params[] = $filters['end_date'] . ' 23:59:59';
        }

        $params[] = $offset;
        $params[] = $limit;

        return $this->get_list_safe(
            "SELECT * FROM aff_log WHERE $where ORDER BY id DESC LIMIT ?, ?",
            $params
        );
    }

    /**
     * Đếm số bản ghi lịch sử hoa hồng
     * @param int $user_id
     * @param array $filters
     * @return int
     */
    public function countCommissionHistory($user_id, $filters = [])
    {
        $where = "`user_id` = ?";
        $params = [$user_id];

        if (!empty($filters['type']) && in_array($filters['type'], ['recharge', 'order', 'withdraw', 'refund', 'manual', 'signup'])) {
            $where .= " AND `type` = ?";
            $params[] = $filters['type'];
        }

        if (!empty($filters['start_date'])) {
            $where .= " AND `create_gettime` >= ?";
            $params[] = $filters['start_date'] . ' 00:00:00';
        }

        if (!empty($filters['end_date'])) {
            $where .= " AND `create_gettime` <= ?";
            $params[] = $filters['end_date'] . ' 23:59:59';
        }

        return $this->get_row_safe(
            "SELECT COUNT(*) as total FROM aff_log WHERE $where",
            $params
        )['total'] ?? 0;
    }

    /**
     * Lấy thống kê tổng quan cho Admin
     * @param string $period - today, week, month, all
     * @return array
     */
    public function getAdminStats($period = 'all')
    {
        $date_condition = "";

        switch ($period) {
            case 'today':
                $date_condition = "AND DATE(create_gettime) = CURDATE()";
                break;
            case 'week':
                $date_condition = "AND YEARWEEK(create_gettime, 1) = YEARWEEK(CURDATE(), 1)";
                break;
            case 'month':
                $date_condition = "AND YEAR(create_gettime) = YEAR(CURDATE()) AND MONTH(create_gettime) = MONTH(CURDATE())";
                break;
        }

        // Tổng hoa hồng đã trả
        $total_commission = $this->get_row_safe(
            "SELECT COALESCE(SUM(sotienthaydoi), 0) as total 
            FROM aff_log 
            WHERE type IN ('recharge', 'order', 'signup') $date_condition"
        )['total'] ?? 0;

        // Tổng tiền đã rút
        $total_withdrawn = $this->get_row_safe(
            "SELECT COALESCE(SUM(amount), 0) as total 
            FROM aff_withdraw 
            WHERE status = 'completed' " . str_replace('create_gettime', 'create_gettime', $date_condition)
        )['total'] ?? 0;

        // Số yêu cầu rút đang chờ
        $pending_withdrawals = $this->get_row_safe(
            "SELECT COUNT(*) as total FROM aff_withdraw WHERE status = 'pending'"
        )['total'] ?? 0;

        // Tổng số affiliates
        $total_affiliates = $this->get_row_safe(
            "SELECT COUNT(DISTINCT user_id) as total FROM aff_log"
        )['total'] ?? 0;

        return [
            'total_commission' => floatval($total_commission),
            'total_withdrawn' => floatval($total_withdrawn),
            'pending_withdrawals' => intval($pending_withdrawals),
            'total_affiliates' => intval($total_affiliates)
        ];
    }
    
    // =====================================================
    // HELPER FUNCTIONS
    // =====================================================

    /**
     * Gửi thông báo khi có hoa hồng mới
     * @param int $user_id
     * @param int $referral_id
     * @param float $amount
     * @param string $type
     */
    private function sendCommissionNotification($user_id, $referral_id, $amount, $type)
    {
        // Lấy thông tin user
        $user = $this->get_row_safe("SELECT telegram_chat_id, telegram_notification FROM users WHERE id = ?", [$user_id]);

        if ($user && $user['telegram_notification'] == 1 && !empty($user['telegram_chat_id'])) {
            $referral = $this->get_row_safe("SELECT username FROM users WHERE id = ?", [$referral_id]);

            $type_text = [
                'recharge' => __('Nạp tiền'),
                'order' => __('Đơn hàng'),
                'signup' => __('Đăng ký mới')
            ];

            $template = $this->site('noti_affiliate_commission') ?:
                "🎉 <b>HOA HỒNG MỚI</b>\n\n📌 Loại: {type}\n💰 Số tiền: {amount}\n👥 Từ: {referral_username}\n⏰ Thời gian: {time}";

            $message = str_replace(
                ['{domain}', '{type}', '{amount}', '{referral_username}', '{time}'],
                [$_SERVER['SERVER_NAME'] ?? '', $type_text[$type] ?? $type, format_currency($amount), $referral['username'] ?? '', gettime()],
                $template
            );

            sendMessTelegram($message, '', $user['telegram_chat_id']);
        }
    }

    /**
     * Lấy tỷ lệ hoa hồng cho user
     * @param int $user_id
     * @param string $type - recharge, order
     * @return float
     */
    public function getCommissionRate($user_id, $type = 'recharge')
    {
        if ($type == 'recharge') {
            $default_rate = $this->site('affiliate_ck') ?: 0;

            // Kiểm tra rate riêng
            $user = $this->get_row_safe("SELECT ref_ck FROM users WHERE id = ?", [$user_id]);
            if ($user && $user['ref_ck'] > 0) {
                return floatval($user['ref_ck']);
            }

            return floatval($default_rate);
        }

        if ($type == 'order') {
            return floatval($this->site('affiliate_order_ck') ?: 0);
        }

        return 0;
    }

    /**
     * Kiểm tra affiliate có được bật không
     * @return bool
     */
    public function isEnabled()
    {
        return $this->site('affiliate_status') == 1;
    }

    /**
     * Lấy link affiliate của user
     * @param int $user_id
     * @return string
     */
    public function getAffiliateLink($user_id)
    {
        $user = $this->get_row_safe("SELECT ref_code FROM users WHERE id = ?", [$user_id]);

        if (!$user || empty($user['ref_code'])) {
            // Generate nếu chưa có
            $ref_code = $this->generateRefCode($user_id);
        } else {
            $ref_code = $user['ref_code'];
        }

        return base_url('?aff=' . $ref_code);
    }
}
