<?php

/**
 * SHOPCLONE7 API Implementation
 * 
 * @package SHOPKEY
 * @author CMSNT
 * @version 1.0.0
 */

if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

require_once(__DIR__ . '/BaseSupplierApi.php');

class Shopclone7Api extends BaseSupplierApi
{
    /**
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return 'SHOPCLONE7';
    }

    /**
     * {@inheritdoc}
     */
    public function getDisplayName(): string
    {
        return 'SHOPCLONE7 CMSNT (Miễn phí)';
    }

    /**
     * {@inheritdoc}
     */
    public function getFieldsConfig(): array
    {
        return [
            'fields' => ['api_key', 'coupon'],
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
     * {@inheritdoc}
     */
    public function validateFormData(array $postData): array
    {
        if (empty($postData['api_key'])) {
            return ['valid' => false, 'error' => 'Vui lòng nhập API Key'];
        }
        return ['valid' => true, 'error' => ''];
    }

    /**
     * Lấy API Key
     */
    protected function getApiKey(): string
    {
        return $this->supplier['api_key'] ?? '';
    }

    /**
     * Lấy Coupon code (nếu có)
     */
    protected function getCoupon(): string
    {
        return $this->supplier['coupon'] ?? '';
    }

    /**
     * {@inheritdoc}
     */
    public function buyProduct(string $api_product_id, int $quantity, array $fields = []): SupplierApiResult
    {
        $domain = $this->getDomain();
        $api_key = $this->getApiKey();
        $coupon = $this->getCoupon();

        if (empty($domain) || empty($api_key)) {
            return SupplierApiResult::error('CONFIG_ERROR', __('Thiếu thông tin cấu hình API'));
        }

        $url = $domain . '/api/buy_product';

        $params = [
            'action' => 'buyProduct',
            'api_key' => $api_key,
            'id' => $api_product_id,
            'amount' => $quantity,
            'coupon' => $coupon
        ];

        $this->logRequest('buyProduct', $params, null);

        $response = $this->curlRequest($url, 'POST', $params);

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

        $data = $response['data'];
        $http_code = $response['http_code'];

        // Kiểm tra response
        if (!$data || !is_array($data)) {
            return SupplierApiResult::error(
                'INVALID_RESPONSE',
                __('API trả về dữ liệu không hợp lệ'),
                $http_code,
                $response
            );
        }

        // Kiểm tra status error
        if (isset($data['status']) && $data['status'] == 'error') {
            $error_code = 'API_ERROR';

            // Kiểm tra lỗi hết tiền
            if ($http_code == 402) {
                $error_code = 'OUT_OF_MONEY';
            }

            return SupplierApiResult::error(
                $error_code,
                $data['msg'] ?? __('Lỗi không xác định từ API'),
                $http_code,
                $data
            );
        }

        // Lấy danh sách tài khoản
        $accounts = [];
        $api_trans_id = $data['trans_id'] ?? null;

        if (isset($data['data']) && is_array($data['data'])) {
            $accounts = $this->normalizeAccounts($data['data']);
        }

        // Kiểm tra số lượng tài khoản
        if (count($accounts) < $quantity) {
            return SupplierApiResult::error(
                'INSUFFICIENT_QUANTITY',
                sprintf(__('API trả về không đủ số lượng (nhận %d, yêu cầu %d)'), count($accounts), $quantity),
                $http_code,
                $data
            );
        }

        return SupplierApiResult::success($accounts, $api_trans_id, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function getProducts(): ?array
    {
        $domain = $this->getDomain();
        $api_key = $this->getApiKey();

        if (empty($domain) || empty($api_key)) {
            return null;
        }

        $url = $domain . '/api/products.php?api_key=' . urlencode($api_key);

        $response = $this->curlRequest($url, 'GET');

        if (!$response['success']) {
            return null;
        }

        $data = $response['data'];

        if (!$data || !is_array($data)) {
            return null;
        }

        // Kiểm tra status từ API
        if (isset($data['status']) && $data['status'] != 'success') {
            return null;
        }

        // SHOPCLONE7 trả về format: { status: "success", categories: [{ name: "...", products: [...] }] }
        $products = [];
        $categories = $data['categories'] ?? $data;

        foreach ($categories as $category) {
            // SHOPCLONE7 dùng key 'products'
            $items = $category['products'] ?? ($category['items'] ?? []);

            if (is_array($items)) {
                foreach ($items as $item) {
                    $products[] = [
                        'id' => $item['id'] ?? null,
                        'name' => $item['name'] ?? '',
                        'price' => $item['price'] ?? 0,
                        'stock' => $item['amount'] ?? 0,
                        'category' => $category['name'] ?? ($category['category'] ?? ''),
                        'description' => $item['short_desc'] ?? ($item['description'] ?? ''),
                        'raw' => $item
                    ];
                }
            }
        }

        return $products;
    }

    /**
     * {@inheritdoc}
     */
    public function getBalance(): ?float
    {
        $domain = $this->getDomain();
        $api_key = $this->getApiKey();

        if (empty($domain) || empty($api_key)) {
            return null;
        }

        $url = $domain . '/api/profile.php?api_key=' . urlencode($api_key);

        $response = $this->curlRequest($url, 'GET');

        if (!$response['success']) {
            return null;
        }

        $data = $response['data'];

        if (!$data || !is_array($data)) {
            return null;
        }

        // Kiểm tra status
        if (isset($data['status']) && $data['status'] != 'success') {
            return null;
        }

        // Format: { status: "success", data: { money: ... } }
        if (isset($data['data']['money'])) {
            return (float)$data['data']['money'];
        }

        // Fallback: check direct keys
        if (isset($data['money'])) {
            return (float)$data['money'];
        }

        if (isset($data['balance'])) {
            return (float)$data['balance'];
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getOrderDetail(string $api_trans_id): ?array
    {
        $domain = $this->getDomain();
        $api_key = $this->getApiKey();

        if (empty($domain) || empty($api_key) || empty($api_trans_id)) {
            return null;
        }

        // Shopclone7 API: /api/order.php?api_key={key}&order={trans_id}
        $url = $domain . '/api/order.php?api_key=' . urlencode($api_key) . '&order=' . urlencode($api_trans_id);

        $response = $this->curlRequest($url, 'GET');

        if (!$response['success']) {
            return null;
        }

        $data = $response['data'];

        // Response format: { status: "success", msg: "...", trans_id: "...", data: ["account1", "account2"] }
        if (!$data || !is_array($data) || ($data['status'] ?? '') !== 'success') {
            return null;
        }

        // Chuẩn hóa response để tương thích với syncPendingOrders
        // Shopclone7 trả về data[] trực tiếp, cần map sang delivery.items
        return [
            'trans_id' => $data['trans_id'] ?? $api_trans_id,
            'status' => 'completed', // Nếu API trả về success thì đơn đã hoàn thành
            'delivery' => [
                'items' => $data['data'] ?? []
            ]
        ];
    }
}
