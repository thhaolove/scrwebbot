<?php

/**
 * SHOPKEY API Implementation
 * Kết nối với API SHOPKEY (format giống website hiện tại)
 * 
 * API Endpoints:
 * - POST /api/v1/orders/create - Tạo đơn hàng
 * - GET /api/v1/orders/status - Trạng thái đơn hàng
 * - GET /api/v1/products/list - Danh sách sản phẩm
 * - GET /api/v1/account/balance - Số dư tài khoản
 * - GET /api/v1/account/info - Thông tin tài khoản
 * 
 * Authentication: X-API-Key + X-API-Secret headers
 * 
 * @package SHOPKEY
 * @author CMSNT
 * @version 1.0.0
 */

if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

require_once(__DIR__ . '/BaseSupplierApi.php');

class ShopkeyApi extends BaseSupplierApi
{
    /**
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return 'SHOPKEY';
    }

    /**
     * {@inheritdoc}
     */
    public function getDisplayName(): string
    {
        return 'SHOPKEY CMSNT (Miễn phí)';
    }

    /**
     * {@inheritdoc}
     */
    public function getFieldsConfig(): array
    {
        return [
            'fields' => ['api_key', 'api_secret', 'coupon'],
            'show_sync_category' => true,
            'show_child_sync' => true,
            'show_auto_show' => true,
            'show_proxy' => true,
            'show_rate' => true,
            'api_key_hint' => 'API Key bắt đầu bằng sk_live_',
            'api_secret_hint' => 'API Secret bắt đầu bằng sk_secret_',
            'required_fields' => ['api_key', 'api_secret']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function validateFormData(array $postData): array
    {
        if (empty($postData['api_key'])) {
            return ['valid' => false, 'error' => 'Vui lòng nhập API Key'];
        }
        // Kiểm tra api_secret (có thể được gửi từ field 'api_secret' hoặc 'password')
        $apiSecret = $postData['api_secret'] ?? ($postData['password'] ?? '');
        if (empty($apiSecret)) {
            return ['valid' => false, 'error' => 'Vui lòng nhập API Secret'];
        }
        return ['valid' => true, 'error' => ''];
    }

    /**
     * Lấy API Key (X-API-Key header)
     */
    protected function getApiKey(): string
    {
        return $this->supplier['api_key'] ?? '';
    }

    /**
     * Lấy API Secret (X-API-Secret header)
     * Sử dụng field 'password' hoặc 'api_secret' để lưu secret
     */
    protected function getApiSecret(): string
    {
        return $this->supplier['password'] ?? ($this->supplier['api_secret'] ?? '');
    }

    /**
     * Lấy Coupon code (nếu có)
     */
    protected function getCoupon(): string
    {
        return $this->supplier['coupon'] ?? '';
    }

    /**
     * Tạo headers xác thực cho SHOPKEY API
     */
    protected function getAuthHeaders(): array
    {
        return [
            'Content-Type: application/json',
            'X-API-Key: ' . $this->getApiKey(),
            'X-API-Secret: ' . $this->getApiSecret()
        ];
    }

    /**
     * Override curlRequest để thêm headers mặc định
     */
    protected function curlRequestWithAuth(string $url, string $method = 'GET', array $data = []): array
    {
        $domain = $this->getDomain();
        $proxy = $this->getProxy();
        $timeout = 30;

        $ch = curl_init();

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => $this->getAuthHeaders(),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        }

        // Proxy support
        if (!empty($proxy)) {
            $proxyParts = explode(':', $proxy);
            if (count($proxyParts) >= 2) {
                $options[CURLOPT_PROXY] = $proxyParts[0] . ':' . $proxyParts[1];
                if (count($proxyParts) >= 4) {
                    $options[CURLOPT_PROXYUSERPWD] = $proxyParts[2] . ':' . $proxyParts[3];
                }
            }
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        $data = json_decode($response, true);

        return [
            'success' => $http_code >= 200 && $http_code < 300 && $data !== null,
            'http_code' => $http_code,
            'data' => $data,
            'raw' => $response,
            'error' => $error
        ];
    }

    /**
     * {@inheritdoc}
     * 
     * SHOPKEY API: POST /api/v1/orders/create
     * Body: { items: [{plan_id, quantity, fields}], coupon_code }
     */
    public function buyProduct(string $api_product_id, int $quantity, array $fields = []): SupplierApiResult
    {
        $domain = $this->getDomain();
        $coupon = $this->getCoupon();

        if (empty($domain) || empty($this->getApiKey()) || empty($this->getApiSecret())) {
            return SupplierApiResult::error('CONFIG_ERROR', __('Thiếu thông tin cấu hình API (API Key hoặc API Secret)'));
        }

        $url = rtrim($domain, '/') . '/api/v1/orders/create';

        // Chuẩn bị item với fields
        $itemData = [
            'plan_id' => (int)$api_product_id,
            'quantity' => $quantity
        ];

        // Thêm fields nếu có (email, username, etc.)
        if (!empty($fields)) {
            $itemData['fields'] = $fields;
        }

        $data = [
            'items' => [$itemData]
        ];

        if (!empty($coupon)) {
            $data['coupon_code'] = $coupon;
        }

        $this->logRequest('buyProduct', $data, null);

        $response = $this->curlRequestWithAuth($url, 'POST', $data);

        $this->logRequest('buyProduct_response', [], $response);

        // Kiểm tra lỗi kết nối
        if (!$response['success'] && $response['http_code'] == 0) {
            return SupplierApiResult::error(
                'CONNECTION_ERROR',
                __('Không thể kết nối đến API nguồn hàng'),
                0,
                $response
            );
        }

        $responseData = $response['data'];
        $http_code = $response['http_code'];

        // Kiểm tra response
        if (!$responseData || !is_array($responseData)) {
            return SupplierApiResult::error(
                'INVALID_RESPONSE',
                __('API trả về dữ liệu không hợp lệ'),
                $http_code,
                $response
            );
        }

        // SHOPKEY format: { success: true/false, message: "...", data: {...} }
        if (!isset($responseData['success']) || $responseData['success'] !== true) {
            $error_code = $responseData['data']['error_code'] ?? 'API_ERROR';

            // Map error codes
            if ($error_code === 'INSUFFICIENT_BALANCE') {
                $error_code = 'OUT_OF_MONEY';
            }

            return SupplierApiResult::error(
                $error_code,
                $responseData['message'] ?? __('Lỗi không xác định từ API'),
                $http_code,
                $responseData
            );
        }

        // Lấy danh sách tài khoản từ orders
        $accounts = [];
        $api_trans_id = null;

        if (isset($responseData['data']['orders']) && is_array($responseData['data']['orders'])) {
            foreach ($responseData['data']['orders'] as $order) {
                $api_trans_id = $order['trans_id'] ?? $api_trans_id;
                $order_status = $order['status'] ?? 'pending';

                // Lấy delivery items nếu có (chỉ có khi order completed/instant)
                if (isset($order['delivery']['items']) && is_array($order['delivery']['items'])) {
                    foreach ($order['delivery']['items'] as $item) {
                        $accounts[] = is_array($item) ? ($item['content'] ?? json_encode($item)) : $item;
                    }
                }

                // Đánh dấu nếu là pending order (sản phẩm thủ công)
                if ($order_status === 'pending') {
                    $is_pending_order = true;
                }
            }
        }

        // Nếu là order completed nhưng chưa có accounts, thử gọi order status
        if (empty($accounts) && !empty($api_trans_id) && empty($is_pending_order)) {
            $orderDetail = $this->getOrderDetail($api_trans_id);
            if ($orderDetail && isset($orderDetail['delivery']['items'])) {
                foreach ($orderDetail['delivery']['items'] as $item) {
                    $accounts[] = is_array($item) ? ($item['content'] ?? json_encode($item)) : $item;
                }
            }
        }

        // Normalize accounts
        $accounts = $this->normalizeAccounts($accounts);

        // Kiểm tra số lượng tài khoản - CHỈ VỚI ĐƠN HÀNG KHÔNG PHẢI PENDING
        // Đơn hàng pending (sản phẩm thủ công) sẽ được cron sync sau
        if (empty($is_pending_order) && count($accounts) < $quantity) {
            return SupplierApiResult::error(
                'INSUFFICIENT_QUANTITY',
                sprintf(__('API trả về không đủ số lượng (nhận %d, yêu cầu %d)'), count($accounts), $quantity),
                $http_code,
                $responseData
            );
        }

        // Xác định order_status để trả về
        $final_order_status = !empty($is_pending_order) ? 'pending' : 'completed';

        return SupplierApiResult::success($accounts, $api_trans_id, $responseData, $final_order_status);
    }

    /**
     * Lấy TẤT CẢ sản phẩm từ API bằng cách lặp qua tất cả các trang
     * API trả về phân trang (default 10/trang, max 100/trang)
     * 
     * @param string $extraParams Query params bổ sung (VD: '&category_id=5')
     * @return array|null Mảng tất cả products (raw từ API), null nếu lỗi
     */
    private function fetchAllProducts(string $extraParams = ''): ?array
    {
        $domain = $this->getDomain();
        $allProducts = [];
        $page = 1;
        $limit = 100; // Max cho phép bởi API
        $maxPages = 50; // Giới hạn an toàn tránh vòng lặp vô hạn

        do {
            $url = rtrim($domain, '/') . '/api/v1/products/list?page=' . $page . '&limit=' . $limit . $extraParams;
            $response = $this->curlRequestWithAuth($url, 'GET');

            if (!$response['success']) {
                // Trang đầu tiên lỗi → return null (API sập)
                // Trang sau lỗi → trả về những gì đã lấy được
                return $page === 1 ? null : $allProducts;
            }

            $data = $response['data'];
            if (!$data || !is_array($data) || !isset($data['success']) || $data['success'] !== true) {
                return $page === 1 ? null : $allProducts;
            }

            $items = $data['data']['products'] ?? ($data['data'] ?? []);
            $allProducts = array_merge($allProducts, $items);

            $hasMore = $data['data']['pagination']['has_more'] ?? false;
            $page++;
        } while ($hasMore && $page <= $maxPages);

        return $allProducts;
    }

    /**
     * {@inheritdoc}
     * 
     * SHOPKEY API: GET /api/v1/products/list (phân trang, lặp lấy tất cả)
     */
    public function getProducts(): ?array
    {
        $domain = $this->getDomain();

        if (empty($domain) || empty($this->getApiKey()) || empty($this->getApiSecret())) {
            return null;
        }

        // Lấy tất cả sản phẩm từ API (tự động phân trang)
        $items = $this->fetchAllProducts();

        if ($items === null) {
            return null;
        }

        // SHOPKEY format: { products: [{id, name, category, categories, plans: [...]}] }
        $products = [];

        foreach ($items as $product) {
            $plans = $product['plans'] ?? [];

            // Lấy category name từ format mới
            $categoryName = '';
            if (!empty($product['categories']) && is_array($product['categories'])) {
                // Multi-category: lấy tên tất cả categories
                $catNames = array_column($product['categories'], 'name');
                $categoryName = implode(', ', $catNames);
            } elseif (!empty($product['category']['name'])) {
                // Single category object
                $categoryName = $product['category']['name'];
            } elseif (!empty($product['category_name'])) {
                // Legacy format
                $categoryName = $product['category_name'];
            }

            foreach ($plans as $plan) {
                $products[] = [
                    'id' => $plan['id'] ?? null,
                    'name' => ($product['name'] ?? '') . ' - ' . ($plan['name'] ?? ''),
                    'price' => $plan['final_price'] ?? ($plan['price'] ?? 0),
                    'stock' => $plan['stock_count'] ?? ($plan['stock'] ?? ($plan['is_instant'] ? 999 : 0)),
                    'category' => $categoryName,
                    'categories' => $product['categories'] ?? [],
                    'description' => $product['description'] ?? ($product['short_desc'] ?? ''),
                    'product_id' => $product['id'] ?? null,
                    'product_name' => $product['name'] ?? '',
                    'plan_name' => $plan['name'] ?? '',
                    'is_instant' => $plan['is_instant'] ?? false,
                    'duration_type' => $plan['duration_type'] ?? 'lifetime',
                    'duration_value' => $plan['duration_value'] ?? null,
                    'fields' => $plan['fields'] ?? [],
                    'raw' => $plan
                ];
            }
        }

        return $products;
    }

    /**
     * {@inheritdoc}
     * 
     * SHOPKEY API: GET /api/v1/account/balance
     */
    public function getBalance(): ?float
    {
        $domain = $this->getDomain();

        if (empty($domain) || empty($this->getApiKey()) || empty($this->getApiSecret())) {
            return null;
        }

        $url = rtrim($domain, '/') . '/api/v1/account/balance';

        $response = $this->curlRequestWithAuth($url, 'GET');

        if (!$response['success']) {
            return null;
        }

        $data = $response['data'];

        if (!$data || !is_array($data) || !isset($data['success']) || $data['success'] !== true) {
            return null;
        }

        // SHOPKEY format: { success: true, data: { balance: { current: 500000, ... } } }
        if (isset($data['data']['balance']['current'])) {
            return (float)$data['data']['balance']['current'];
        }

        // Fallback: nếu balance là số trực tiếp
        if (isset($data['data']['balance']) && is_numeric($data['data']['balance'])) {
            return (float)$data['data']['balance'];
        }

        return null;
    }

    /**
     * {@inheritdoc}
     * 
     * SHOPKEY API: GET /api/v1/orders/status?trans_id={trans_id}
     */
    public function getOrderDetail(string $api_trans_id): ?array
    {
        $domain = $this->getDomain();

        if (empty($domain) || empty($this->getApiKey()) || empty($this->getApiSecret()) || empty($api_trans_id)) {
            return null;
        }

        $url = rtrim($domain, '/') . '/api/v1/orders/status?trans_id=' . urlencode($api_trans_id);

        $response = $this->curlRequestWithAuth($url, 'GET');

        if (!$response['success']) {
            return null;
        }

        $data = $response['data'];

        if (!$data || !is_array($data) || !isset($data['success']) || $data['success'] !== true) {
            return null;
        }

        return $data['data'] ?? null;
    }

    /**
     * Lấy thông tin tài khoản API
     * 
     * SHOPKEY API: GET /api/v1/account/info
     */
    public function getAccountInfo(): ?array
    {
        $domain = $this->getDomain();

        if (empty($domain) || empty($this->getApiKey()) || empty($this->getApiSecret())) {
            return null;
        }

        $url = rtrim($domain, '/') . '/api/v1/account/info';

        $response = $this->curlRequestWithAuth($url, 'GET');

        if (!$response['success']) {
            return null;
        }

        $data = $response['data'];

        if (!$data || !is_array($data) || !isset($data['success']) || $data['success'] !== true) {
            return null;
        }

        return $data['data'] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function testConnection(): bool
    {
        $balance = $this->getBalance();
        return $balance !== null;
    }

    /**
     * Lấy cấu trúc đầy đủ từ API (categories, products, plans)
     * Dùng cho chế độ "Đồng bộ cấu trúc như web con"
     * 
     * @return array|null ['categories' => [...], 'products' => [...]]
     */
    public function getFullStructure(): ?array
    {
        $domain = $this->getDomain();

        if (empty($domain) || empty($this->getApiKey()) || empty($this->getApiSecret())) {
            return null;
        }

        // Lấy danh sách categories
        $url = rtrim($domain, '/') . '/api/v1/categories/list?include_children=1';
        $catResponse = $this->curlRequestWithAuth($url, 'GET');

        $categories = [];
        if ($catResponse['success'] && isset($catResponse['data']['success']) && $catResponse['data']['success'] === true) {
            // Response format: { success: true, data: { categories: [...] } }
            $categories = $catResponse['data']['data']['categories'] ?? [];
        }

        // Lấy danh sách products với đầy đủ thông tin (tự động phân trang lấy tất cả)
        $products = $this->fetchAllProducts() ?? [];

        return [
            'categories' => $categories,
            'products' => $products
        ];
    }

    /**
     * Lấy danh sách chuyên mục từ API
     * 
     * @return array|null
     */
    public function getCategories(): ?array
    {
        $domain = $this->getDomain();

        if (empty($domain) || empty($this->getApiKey()) || empty($this->getApiSecret())) {
            return null;
        }

        $url = rtrim($domain, '/') . '/api/v1/categories/list?include_children=1';

        $response = $this->curlRequestWithAuth($url, 'GET');

        if (!$response['success']) {
            return null;
        }

        $data = $response['data'];

        if (!$data || !is_array($data) || !isset($data['success']) || $data['success'] !== true) {
            return null;
        }

        // Response format: { success: true, data: { categories: [...] } }
        return $data['data']['categories'] ?? [];
    }
}
