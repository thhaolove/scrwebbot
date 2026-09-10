<?php

/**
 * SHOPCLONE6 API Implementation
 * 
 * @package SHOPKEY
 * @author CMSNT
 * @version 1.0.0
 */

if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

require_once(__DIR__ . '/BaseSupplierApi.php');

class Shopclone6Api extends BaseSupplierApi
{
    /**
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return 'SHOPCLONE6';
    }

    /**
     * {@inheritdoc}
     */
    public function getDisplayName(): string
    {
        return 'SHOPCLONE5 & SHOPCLONE6 CMSNT (Miễn phí)';
    }

    /**
     * {@inheritdoc}
     */
    public function getFieldsConfig(): array
    {
        return [
            'fields' => ['username', 'password'],
            'show_sync_category' => true,
            'show_child_sync' => false,
            'show_auto_show' => false,
            'show_proxy' => true,
            'show_rate' => true,
            'api_key_hint' => '',
            'api_secret_hint' => '',
            'required_fields' => ['username', 'password']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function validateFormData(array $postData): array
    {
        if (empty($postData['username'])) {
            return ['valid' => false, 'error' => 'Vui lòng nhập Username'];
        }
        if (empty($postData['password'])) {
            return ['valid' => false, 'error' => 'Vui lòng nhập Password'];
        }
        return ['valid' => true, 'error' => ''];
    }

    /**
     * Lấy Username
     */
    protected function getUsername(): string
    {
        return $this->supplier['username'] ?? '';
    }

    /**
     * Lấy Password
     */
    protected function getPassword(): string
    {
        return $this->supplier['password'] ?? '';
    }

    /**
     * {@inheritdoc}
     */
    public function buyProduct(string $api_product_id, int $quantity, array $fields = []): SupplierApiResult
    {
        $domain = $this->getDomain();
        $username = $this->getUsername();
        $password = $this->getPassword();

        if (empty($domain) || empty($username) || empty($password)) {
            return SupplierApiResult::error('CONFIG_ERROR', __('Thiếu thông tin cấu hình API'));
        }

        // SHOPCLONE6 dùng GET request cho buy
        $url = $domain . '/api/BResource.php?username=' . urlencode($username)
            . '&password=' . urlencode($password)
            . '&id=' . urlencode($api_product_id)
            . '&amount=' . urlencode($quantity);

        $this->logRequest('buyProduct', ['url' => $url], null);

        // SHOPCLONE6 dùng GET request
        $response = $this->curlRequest($url, 'GET');

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
                $data['msg'] ?? ($data['message'] ?? __('Lỗi không xác định từ API')),
                $http_code,
                $data
            );
        }

        // Lấy danh sách tài khoản
        $accounts = [];
        $api_trans_id = $data['trans_id'] ?? ($data['order_id'] ?? null);

        // SHOPCLONE6 có thể trả về 'data' hoặc 'accounts'
        if (isset($data['data']) && is_array($data['data'])) {
            $accounts = $this->normalizeAccounts($data['data']);
        } elseif (isset($data['accounts']) && is_array($data['accounts'])) {
            $accounts = $this->normalizeAccounts($data['accounts']);
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
        $username = $this->getUsername();
        $password = $this->getPassword();

        if (empty($domain) || empty($username) || empty($password)) {
            return null;
        }

        $url = $domain . '/api/ListResource.php?username=' . urlencode($username) . '&password=' . urlencode($password);

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

        // SHOPCLONE6 trả về format: { status: "success", categories: [{ name: "...", accounts: [...] }] }
        $products = [];
        $categories = $data['categories'] ?? [];

        foreach ($categories as $category) {
            // SHOPCLONE6 dùng key 'accounts'
            $items = $category['accounts'] ?? ($category['products'] ?? []);

            if (is_array($items)) {
                foreach ($items as $item) {
                    $products[] = [
                        'id' => $item['id'] ?? null,
                        'name' => $item['name'] ?? '',
                        'price' => $item['price'] ?? 0,
                        'stock' => $item['amount'] ?? ($item['quantity'] ?? 0),
                        'category' => $category['name'] ?? ($category['category'] ?? ''),
                        'description' => $item['description'] ?? '',
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
        $username = $this->getUsername();
        $password = $this->getPassword();

        if (empty($domain) || empty($username) || empty($password)) {
            return null;
        }

        $url = $domain . '/api/GetBalance.php?username=' . urlencode($username) . '&password=' . urlencode($password);

        $response = $this->curlRequest($url, 'GET');

        if (!$response['success']) {
            return null;
        }

        $data = $response['data'];

        if (!$data || !is_array($data)) {
            return null;
        }

        // Kiểm tra nếu có error
        if (isset($data['status']) && $data['status'] == 'error') {
            return null;
        }

        if (isset($data['money'])) {
            return (float)$data['money'];
        }

        if (isset($data['balance'])) {
            return (float)$data['balance'];
        }

        return null;
    }
}
