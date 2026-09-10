<?php
/**
 * Base Supplier API
 * Abstract class chứa logic chung cho các API nguồn hàng
 * 
 * @package SHOPKEY
 * @author CMSNT
 * @version 1.0.0
 */

if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

require_once(__DIR__ . '/SupplierApiInterface.php');

abstract class BaseSupplierApi implements SupplierApiInterface
{
    /** @var array Thông tin supplier từ database */
    protected $supplier = [];
    
    /** @var int Timeout cho CURL requests (giây) */
    protected $timeout = 15;
    
    /** @var string User Agent */
    protected $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36';
    
    /**
     * {@inheritdoc}
     */
    public function setSupplier(array $supplier): SupplierApiInterface
    {
        $this->supplier = $supplier;
        return $this;
    }
    
    /**
     * Lấy domain của supplier
     */
    protected function getDomain(): string
    {
        return rtrim($this->supplier['domain'] ?? '', '/');
    }
    
    /**
     * Lấy proxy config
     */
    protected function getProxy(): string
    {
        return $this->supplier['proxy'] ?? '';
    }
    
    /**
     * Thực hiện CURL request
     * 
     * @param string $url URL endpoint
     * @param string $method HTTP method (GET, POST)
     * @param array $data Dữ liệu gửi đi
     * @param array $headers Custom headers
     * @return array ['success' => bool, 'data' => mixed, 'http_code' => int, 'error' => string]
     */
    protected function curlRequest(string $url, string $method = 'GET', array $data = [], array $headers = []): array
    {
        $curl = curl_init();
        
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HTTPHEADER => array_merge([
                'User-Agent: ' . $this->userAgent
            ], $headers),
        ];
        
        if ($method === 'POST') {
            $options[CURLOPT_CUSTOMREQUEST] = 'POST';
            $options[CURLOPT_POSTFIELDS] = $data;
        }
        
        curl_setopt_array($curl, $options);
        
        // Thiết lập proxy nếu có
        $this->setupProxy($curl);
        
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($curl);
        
        curl_close($curl);
        
        // Parse JSON response
        $parsed_data = null;
        if ($response) {
            $parsed_data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $parsed_data = ['raw' => $response];
            }
        }
        
        return [
            'success' => !empty($response) && $http_code >= 200 && $http_code < 500,
            'data' => $parsed_data,
            'http_code' => $http_code,
            'error' => $curl_error,
            'raw' => $response
        ];
    }
    
    /**
     * Thiết lập proxy cho CURL
     */
    protected function setupProxy($curl): void
    {
        $proxy = $this->getProxy();
        
        if (empty($proxy)) {
            return;
        }
        
        $proxy_parts = explode(':', $proxy);
        
        if (count($proxy_parts) == 4) {
            // Format: host:port:username:password
            curl_setopt($curl, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
            curl_setopt($curl, CURLOPT_PROXYUSERPWD, $proxy_parts[2] . ':' . $proxy_parts[3]);
        } elseif (count($proxy_parts) == 2) {
            // Format: host:port
            curl_setopt($curl, CURLOPT_PROXY, $proxy_parts[0] . ':' . $proxy_parts[1]);
        }
    }
    
    /**
     * Chuẩn hóa tài khoản nhận về
     * Override trong subclass nếu cần xử lý đặc biệt
     * 
     * @param array $raw_accounts Danh sách tài khoản thô từ API
     * @return array Danh sách tài khoản đã chuẩn hóa
     */
    protected function normalizeAccounts(array $raw_accounts): array
    {
        $normalized = [];
        foreach ($raw_accounts as $account) {
            if (is_string($account)) {
                $normalized[] = trim($account);
            } elseif (is_array($account)) {
                // Nếu API trả về object, cố gắng convert sang string
                $normalized[] = $this->accountToString($account);
            }
        }
        return array_filter($normalized);
    }
    
    /**
     * Chuyển đổi account object thành string
     * Override trong subclass nếu API có format đặc biệt
     */
    protected function accountToString($account): string
    {
        if (is_string($account)) {
            return $account;
        }
        
        if (is_array($account)) {
            // Thử các key phổ biến
            if (isset($account['account'])) {
                return $account['account'];
            }
            if (isset($account['data'])) {
                return $account['data'];
            }
            if (isset($account['value'])) {
                return $account['value'];
            }
            // Fallback: nối tất cả giá trị
            return implode('|', array_values($account));
        }
        
        return (string)$account;
    }
    
    /**
     * Log request để debug
     */
    protected function logRequest(string $action, array $params, $response): void
    {
        global $CMSNT;
        
        if ($CMSNT->site('debug_api_suppliers') != 1) {
            return;
        }
        
        error_log(sprintf(
            "[%s API] %s - Params: %s - Response: %s",
            $this->getType(),
            $action,
            json_encode($params),
            is_string($response) ? $response : json_encode($response)
        ));
    }
    
    /**
     * Kiểm tra kết nối API (mặc định dùng getBalance)
     */
    public function testConnection(): bool
    {
        $balance = $this->getBalance();
        return $balance !== null;
    }
    
    /**
     * Lấy thông tin đơn hàng - mặc định không hỗ trợ
     */
    public function getOrderDetail(string $api_trans_id): ?array
    {
        return null;
    }
    
    /**
     * Lấy tên hiển thị của API type
     * Override trong subclass để đặt tên khác
     * 
     * @return string
     */
    public function getDisplayName(): string
    {
        return $this->getType() . ' (Miễn phí)';
    }
    
    /**
     * Lấy cấu hình các trường cần hiển thị trong form
     * Override trong subclass để tùy chỉnh
     * 
     * @return array Config với keys:
     *  - fields: array các trường cần hiển thị ['username', 'password', 'api_key', 'api_secret', 'token', 'coupon']
     *  - show_sync_category: bool hiển thị option đồng bộ chuyên mục
     *  - show_child_sync: bool hiển thị option đồng bộ cấu trúc web con
     *  - show_auto_show: bool hiển thị option tự động hiển thị sản phẩm
     *  - show_proxy: bool hiển thị option proxy
     *  - show_rate: bool hiển thị option tỷ giá
     *  - api_key_hint: string gợi ý cho trường API key
     *  - api_secret_hint: string gợi ý cho trường API secret
     *  - required_fields: array các trường bắt buộc
     */
    public function getFieldsConfig(): array
    {
        // Config mặc định - các subclass override để tùy chỉnh
        return [
            'fields' => ['api_key'],
            'show_sync_category' => true,
            'show_child_sync' => false,
            'show_auto_show' => true,
            'show_proxy' => true,
            'show_rate' => true,
            'api_key_hint' => '',
            'api_secret_hint' => '',
            'required_fields' => ['api_key']
        ];
    }
    
    /**
     * Validate dữ liệu form trước khi test kết nối
     * Override trong subclass để validate đặc biệt
     * 
     * @param array $postData Dữ liệu từ $_POST
     * @return array ['valid' => bool, 'error' => string]
     */
    public function validateFormData(array $postData): array
    {
        $config = $this->getFieldsConfig();
        $required = $config['required_fields'] ?? [];
        
        foreach ($required as $field) {
            if (empty($postData[$field])) {
                $fieldLabels = [
                    'username' => 'Username',
                    'password' => 'Password',
                    'api_key' => 'API Key',
                    'api_secret' => 'API Secret',
                    'token' => 'Token'
                ];
                $label = $fieldLabels[$field] ?? $field;
                return ['valid' => false, 'error' => "Vui lòng nhập {$label}"];
            }
        }
        
        return ['valid' => true, 'error' => ''];
    }
}

