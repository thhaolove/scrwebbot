<?php
/**
 * API Key Service - Quản lý API Keys, Rate Limiting, IP Whitelist
 * 
 * @package SHOPKEY
 * @author CMSNT
 * @version 1.0.0
 */

if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

class ApiKeyService
{
    private $db;
    private $api_key_data = null;
    private $user_data = null;
    private $errors = [];
    
    // Rate limiting defaults
    const DEFAULT_RATE_LIMIT = 60;  // requests per minute
    const DEFAULT_DAILY_LIMIT = 10000; // requests per day
    
    // Response codes
    const ERROR_INVALID_KEY = 'INVALID_API_KEY';
    const ERROR_INVALID_SECRET = 'INVALID_API_SECRET';
    const ERROR_EXPIRED_KEY = 'EXPIRED_API_KEY';
    const ERROR_DISABLED_KEY = 'DISABLED_API_KEY';
    const ERROR_IP_NOT_ALLOWED = 'IP_NOT_ALLOWED';
    const ERROR_RATE_LIMIT = 'RATE_LIMIT_EXCEEDED';
    const ERROR_DAILY_LIMIT = 'DAILY_LIMIT_EXCEEDED';
    const ERROR_INSUFFICIENT_BALANCE = 'INSUFFICIENT_BALANCE';
    const ERROR_USER_BANNED = 'USER_BANNED';
    const ERROR_API_DISABLED = 'API_DISABLED';
    
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
    private function addError(string $code, string $message): void
    {
        $this->errors[] = [
            'code' => $code,
            'message' => $message
        ];
    }
    
    // API Key prefixes
    const API_KEY_PREFIX = 'sk_live_';
    const API_SECRET_PREFIX = 'sk_secret_';
    
    /**
     * Xác thực API Key và Secret
     * @param string $api_key
     * @param string $api_secret
     * @return bool
     */
    public function authenticate(string $api_key, string $api_secret): bool
    {
        // Kiểm tra tính năng API có được bật không
        if ($this->db->site('api_user_enabled') != 1) {
            $this->addError(self::ERROR_API_DISABLED, __('Tính năng API hiện đang tắt. Vui lòng liên hệ quản trị viên.'));
            return false;
        }
        
        // Validate input - hỗ trợ cả key có prefix và không có prefix (backward compatible)
        $key_valid = !empty($api_key) && (
            strlen($api_key) === 32 ||  // Old format: 32 chars
            (strlen($api_key) === 40 && strpos($api_key, self::API_KEY_PREFIX) === 0) // New format: sk_live_ + 32 chars
        );
        
        if (!$key_valid) {
            $this->addError(self::ERROR_INVALID_KEY, __('API Key không hợp lệ'));
            return false;
        }
        
        $secret_valid = !empty($api_secret) && (
            strlen($api_secret) === 64 ||  // Old format: 64 chars
            (strlen($api_secret) === 74 && strpos($api_secret, self::API_SECRET_PREFIX) === 0) // New format: sk_secret_ + 64 chars
        );
        
        if (!$secret_valid) {
            $this->addError(self::ERROR_INVALID_SECRET, __('API Secret không hợp lệ'));
            return false;
        }
        
        // Lấy API key từ database
        $key_data = $this->db->get_row_safe(
            "SELECT * FROM `api_keys` WHERE `api_key` = ?",
            [$api_key]
        );
        
        if (!$key_data) {
            $this->addError(self::ERROR_INVALID_KEY, __('API Key không tồn tại'));
            $this->logRequest($api_key, 'authenticate', 'failed', 'Invalid API Key');
            return false;
        }
        
        // Verify secret (sử dụng timing-safe comparison)
        if (!hash_equals($key_data['api_secret'], $api_secret)) {
            $this->addError(self::ERROR_INVALID_SECRET, __('API Secret không chính xác'));
            $this->logRequest($api_key, 'authenticate', 'failed', 'Invalid API Secret', null, null, (int)$key_data['user_id'], (int)$key_data['id']);
            return false;
        }
        
        // Kiểm tra status
        if ($key_data['status'] != 1) {
            $this->addError(self::ERROR_DISABLED_KEY, __('API Key đã bị vô hiệu hóa'));
            $this->logRequest($api_key, 'authenticate', 'failed', 'API Key disabled', null, null, (int)$key_data['user_id'], (int)$key_data['id']);
            return false;
        }
        
        // Kiểm tra ngày hết hạn
        if (!empty($key_data['expires_at']) && strtotime($key_data['expires_at']) < time()) {
            $this->addError(self::ERROR_EXPIRED_KEY, __('API Key đã hết hạn'));
            $this->logRequest($api_key, 'authenticate', 'failed', 'API Key expired', null, null, (int)$key_data['user_id'], (int)$key_data['id']);
            return false;
        }
        
        // Kiểm tra IP whitelist
        if (!$this->checkIPWhitelist($key_data)) {
            $this->addError(self::ERROR_IP_NOT_ALLOWED, __('IP của bạn không được phép truy cập API'));
            $this->logRequest($api_key, 'authenticate', 'failed', 'IP not in whitelist: ' . myip(), null, null, (int)$key_data['user_id'], (int)$key_data['id']);
            return false;
        }
        
        // Kiểm tra rate limit
        if (!$this->checkRateLimit($key_data)) {
            return false;
        }
        
        // Kiểm tra user
        $user = $this->db->get_row_safe(
            "SELECT * FROM `users` WHERE `id` = ?",
            [$key_data['user_id']]
        );
        
        if (!$user) {
            $this->addError(self::ERROR_USER_BANNED, __('Tài khoản không tồn tại'));
            return false;
        }
        
        if ($user['banned'] == 1) {
            $this->addError(self::ERROR_USER_BANNED, __('Tài khoản đã bị khóa'));
            $this->logRequest($api_key, 'authenticate', 'failed', 'User banned', null, null, (int)$key_data['user_id'], (int)$key_data['id']);
            return false;
        }
        
        // Cập nhật last_used_at
        $this->db->update('api_keys', [
            'last_used_at' => gettime(),
            'last_ip' => myip()
        ], "`id` = ?", [$key_data['id']]);
        
        $this->api_key_data = $key_data;
        $this->user_data = $user;
        
        return true;
    }
    
    /**
     * Kiểm tra IP whitelist
     */
    private function checkIPWhitelist(array $key_data): bool
    {
        if (empty($key_data['ip_whitelist'])) {
            return true; // Không có whitelist = cho phép tất cả
        }
        
        $whitelist = json_decode($key_data['ip_whitelist'], true);
        if (!is_array($whitelist) || empty($whitelist)) {
            return true;
        }
        
        $client_ip = myip();
        
        foreach ($whitelist as $allowed_ip) {
            // Hỗ trợ CIDR notation (e.g., 192.168.1.0/24)
            if (strpos($allowed_ip, '/') !== false) {
                if ($this->ipInRange($client_ip, $allowed_ip)) {
                    return true;
                }
            } else {
                if ($client_ip === $allowed_ip) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Kiểm tra IP có trong CIDR range không
     */
    private function ipInRange(string $ip, string $cidr): bool
    {
        list($subnet, $bits) = explode('/', $cidr);
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - (int)$bits);
        $subnet &= $mask;
        return ($ip & $mask) === $subnet;
    }
    
    /**
     * Kiểm tra rate limit
     */
    private function checkRateLimit(array $key_data): bool
    {
        $rate_limit = $key_data['rate_limit'] ?: self::DEFAULT_RATE_LIMIT;
        $daily_limit = $key_data['daily_limit'] ?: self::DEFAULT_DAILY_LIMIT;
        
        // Đếm requests trong phút qua
        $minute_ago = date('Y-m-d H:i:s', strtotime('-1 minute'));
        $minute_count = $this->db->num_rows_safe(
            "SELECT id FROM `api_logs` WHERE `api_key` = ? AND `created_at` >= ?",
            [$key_data['api_key'], $minute_ago]
        );
        
        if ($minute_count >= $rate_limit) {
            $this->addError(self::ERROR_RATE_LIMIT, sprintf(
                __('Vượt quá giới hạn %d request/phút. Vui lòng thử lại sau.'),
                $rate_limit
            ));
            $this->logRequest($key_data['api_key'], 'rate_limit', 'blocked', 'Rate limit exceeded', null, null, (int)$key_data['user_id'], (int)$key_data['id']);
            return false;
        }
        
        // Đếm requests trong ngày
        $today_start = date('Y-m-d 00:00:00');
        $day_count = $this->db->num_rows_safe(
            "SELECT id FROM `api_logs` WHERE `api_key` = ? AND `created_at` >= ?",
            [$key_data['api_key'], $today_start]
        );
        
        if ($day_count >= $daily_limit) {
            $this->addError(self::ERROR_DAILY_LIMIT, sprintf(
                __('Vượt quá giới hạn %d request/ngày. Vui lòng thử lại vào ngày mai.'),
                $daily_limit
            ));
            $this->logRequest($key_data['api_key'], 'daily_limit', 'blocked', 'Daily limit exceeded', null, null, (int)$key_data['user_id'], (int)$key_data['id']);
            return false;
        }
        
        return true;
    }
    
    /**
     * Ghi log API request
     * @param string $api_key API Key string
     * @param string $endpoint Endpoint được gọi
     * @param string $status Trạng thái (success, failed, blocked, etc.)
     * @param string|null $message Thông báo chi tiết
     * @param array|null $request_data Dữ liệu request
     * @param array|null $response_data Dữ liệu response
     * @param int|null $user_id User ID (nếu có)
     * @param int|null $api_key_id API Key ID (nếu có)
     */
    public function logRequest(string $api_key, string $endpoint, string $status, ?string $message = null, ?array $request_data = null, ?array $response_data = null, ?int $user_id = null, ?int $api_key_id = null): void
    {
        try {
            // Tự động lấy user_id và api_key_id từ dữ liệu đã authenticate nếu không được truyền vào
            if ($user_id === null && $this->user_data) {
                $user_id = (int)$this->user_data['id'];
            }
            if ($api_key_id === null && $this->api_key_data) {
                $api_key_id = (int)$this->api_key_data['id'];
            }
            
            $this->db->insert('api_logs', [
                'api_key' => $api_key,
                'api_key_id' => $api_key_id,
                'user_id' => $user_id,
                'endpoint' => $endpoint,
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
                'ip' => myip(),
                'user_agent' => substr(getUserAgent(), 0, 500),
                'request_data' => $request_data ? json_encode($request_data) : null,
                'response_data' => $response_data ? json_encode($response_data) : null,
                'status' => $status,
                'message' => $message,
                'created_at' => gettime()
            ]);
        } catch (Exception $e) {
            error_log('API log error: ' . $e->getMessage());
        }
    }
    
    /**
     * Lấy thông tin API Key
     */
    public function getApiKeyData(): ?array
    {
        return $this->api_key_data;
    }
    
    /**
     * Lấy thông tin User
     */
    public function getUserData(): ?array
    {
        return $this->user_data;
    }
    
    /**
     * Lấy User ID
     */
    public function getUserId(): ?int
    {
        return $this->user_data ? (int)$this->user_data['id'] : null;
    }
    
    /**
     * Kiểm tra quyền của API key
     */
    public function hasPermission(string $permission): bool
    {
        if (!$this->api_key_data) {
            return false;
        }
        
        $permissions = json_decode($this->api_key_data['permissions'], true) ?: [];
        
        // Quyền 'all' cho phép tất cả
        if (in_array('all', $permissions)) {
            return true;
        }
        
        return in_array($permission, $permissions);
    }
    
    /**
     * Tạo API Key mới cho user
     */
    /**
     * @return array|false
     */
    public function createApiKey(int $user_id, string $name, array $options = [])
    {
        // Kiểm tra user tồn tại
        $user = $this->db->get_row_safe("SELECT id FROM `users` WHERE `id` = ?", [$user_id]);
        if (!$user) {
            $this->addError('USER_NOT_FOUND', __('Người dùng không tồn tại'));
            return false;
        }
        
        // Generate API Key và Secret
        $api_key = $this->generateApiKey();
        $api_secret = $this->generateApiSecret();
        
        // Mặc định permissions
        $default_permissions = ['orders.create', 'orders.view', 'products.view', 'balance.view'];
        $permissions = $options['permissions'] ?? $default_permissions;
        
        // Insert vào database
        $key_id = $this->db->insert('api_keys', [
            'user_id' => $user_id,
            'name' => validate_string($name, 100) ?: 'API Key',
            'api_key' => $api_key,
            'api_secret' => $api_secret,
            'permissions' => json_encode($permissions),
            'ip_whitelist' => !empty($options['ip_whitelist']) ? json_encode($options['ip_whitelist']) : null,
            'rate_limit' => $options['rate_limit'] ?? self::DEFAULT_RATE_LIMIT,
            'daily_limit' => $options['daily_limit'] ?? self::DEFAULT_DAILY_LIMIT,
            'expires_at' => $options['expires_at'] ?? null,
            'status' => 1,
            'created_at' => gettime(),
            'updated_at' => gettime()
        ]);
        
        if (!$key_id) {
            $this->addError('CREATE_FAILED', __('Không thể tạo API Key'));
            return false;
        }
        
        return [
            'id' => $key_id,
            'api_key' => $api_key,
            'api_secret' => $api_secret, // Chỉ trả về 1 lần duy nhất
            'name' => $name,
            'permissions' => $permissions
        ];
    }
    
    /**
     * Generate API Key (32 ký tự hex)
     */
    public function generateApiKey(): string
    {
        return self::API_KEY_PREFIX . bin2hex(random_bytes(16));
    }
    
    /**
     * Generate API Secret (sk_secret_ + 64 ký tự hex = 74 ký tự)
     */
    public function generateApiSecret(): string
    {
        return self::API_SECRET_PREFIX . bin2hex(random_bytes(32));
    }
    
    /**
     * Vô hiệu hóa API Key
     */
    public function disableApiKey(int $key_id, int $user_id): bool
    {
        return $this->db->update('api_keys', [
            'status' => 0,
            'updated_at' => gettime()
        ], "`id` = ? AND `user_id` = ?", [$key_id, $user_id]);
    }
    
    /**
     * Xóa API Key
     */
    public function deleteApiKey(int $key_id, int $user_id): bool
    {
        return $this->db->remove('api_keys', "`id` = ? AND `user_id` = ?", [$key_id, $user_id]);
    }
    
    /**
     * Lấy danh sách API Keys của user
     */
    public function getUserApiKeys(int $user_id): array
    {
        return $this->db->get_list_safe(
            "SELECT `id`, `name`, `api_key`, `permissions`, `ip_whitelist`, `rate_limit`, `daily_limit`, 
                    `expires_at`, `status`, `last_used_at`, `last_ip`, `created_at`
             FROM `api_keys` 
             WHERE `user_id` = ? 
             ORDER BY `id` DESC",
            [$user_id]
        );
    }
    
    /**
     * Cập nhật API Key
     */
    public function updateApiKey(int $key_id, int $user_id, array $data): bool
    {
        $update_data = ['updated_at' => gettime()];
        
        if (isset($data['name'])) {
            $update_data['name'] = validate_string($data['name'], 100);
        }
        
        if (isset($data['permissions']) && is_array($data['permissions'])) {
            $update_data['permissions'] = json_encode($data['permissions']);
        }
        
        if (isset($data['ip_whitelist'])) {
            $update_data['ip_whitelist'] = is_array($data['ip_whitelist']) 
                ? json_encode($data['ip_whitelist']) 
                : null;
        }
        
        if (isset($data['rate_limit'])) {
            $update_data['rate_limit'] = max(1, min(1000, (int)$data['rate_limit']));
        }
        
        if (isset($data['daily_limit'])) {
            $update_data['daily_limit'] = max(100, min(1000000, (int)$data['daily_limit']));
        }
        
        if (isset($data['status'])) {
            $update_data['status'] = $data['status'] ? 1 : 0;
        }
        
        return $this->db->update('api_keys', $update_data, "`id` = ? AND `user_id` = ?", [$key_id, $user_id]);
    }
    
    /**
     * Regenerate API Secret
     */
    /**
     * @return string|false
     */
    public function regenerateSecret(int $key_id, int $user_id)
    {
        $new_secret = $this->generateApiSecret();
        
        $updated = $this->db->update('api_keys', [
            'api_secret' => $new_secret,
            'updated_at' => gettime()
        ], "`id` = ? AND `user_id` = ?", [$key_id, $user_id]);
        
        if (!$updated) {
            $this->addError('UPDATE_FAILED', __('Không thể cập nhật API Secret'));
            return false;
        }
        
        return $new_secret;
    }
    
    /**
     * Lấy thống kê sử dụng API
     */
    public function getUsageStats(string $api_key, string $period = 'day'): array
    {
        switch ($period) {
            case 'hour':
                $start_time = date('Y-m-d H:i:s', strtotime('-1 hour'));
                break;
            case 'day':
                $start_time = date('Y-m-d 00:00:00');
                break;
            case 'week':
                $start_time = date('Y-m-d 00:00:00', strtotime('-7 days'));
                break;
            case 'month':
                $start_time = date('Y-m-d 00:00:00', strtotime('-30 days'));
                break;
            default:
                $start_time = date('Y-m-d 00:00:00');
        }
        
        $total = $this->db->num_rows_safe(
            "SELECT id FROM `api_logs` WHERE `api_key` = ? AND `created_at` >= ?",
            [$api_key, $start_time]
        );
        
        $success = $this->db->num_rows_safe(
            "SELECT id FROM `api_logs` WHERE `api_key` = ? AND `created_at` >= ? AND `status` = 'success'",
            [$api_key, $start_time]
        );
        
        $failed = $this->db->num_rows_safe(
            "SELECT id FROM `api_logs` WHERE `api_key` = ? AND `created_at` >= ? AND `status` != 'success'",
            [$api_key, $start_time]
        );
        
        return [
            'period' => $period,
            'start_time' => $start_time,
            'total_requests' => $total,
            'success_requests' => $success,
            'failed_requests' => $failed,
            'success_rate' => $total > 0 ? round(($success / $total) * 100, 2) : 0
        ];
    }
    
    /**
     * Trả về response chuẩn cho API
     */
    public static function jsonResponse(bool $success, $data = null, ?string $message = null, int $http_code = 200): void
    {
        http_response_code($http_code);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        
        $response = [
            'success' => $success,
            'timestamp' => time(),
            'request_id' => uniqid('req_', true)
        ];
        
        if ($message !== null) {
            $response['message'] = $message;
        }
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    /**
     * Trả về response lỗi
     */
    public static function errorResponse(string $code, string $message, int $http_code = 400): void
    {
        self::jsonResponse(false, ['error_code' => $code], $message, $http_code);
    }
}

