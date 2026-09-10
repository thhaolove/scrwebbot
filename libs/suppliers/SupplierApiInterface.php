<?php

/**
 * Supplier API Interface
 * Định nghĩa các method cần thiết cho việc tích hợp API nguồn hàng
 * 
 * @package SHOPKEY
 * @author CMSNT
 * @version 1.0.0
 */

if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

interface SupplierApiInterface
{
    /**
     * Lấy loại API (VD: SHOPCLONE6, SHOPCLONE7, ...)
     * @return string
     */
    public function getType(): string;

    /**
     * Thiết lập thông tin supplier
     * @param array $supplier Thông tin từ bảng suppliers
     * @return self
     */
    public function setSupplier(array $supplier): self;

    /**
     * Mua hàng từ API
     * 
     * @param string $api_product_id ID sản phẩm trên API nguồn
     * @param int $quantity Số lượng cần mua
     * @return SupplierApiResult Kết quả mua hàng
     */
    public function buyProduct(string $api_product_id, int $quantity): SupplierApiResult;

    /**
     * Lấy danh sách sản phẩm từ API
     * @return array|null
     */
    public function getProducts(): ?array;

    /**
     * Lấy số dư tài khoản API
     * @return float|null
     */
    public function getBalance(): ?float;

    /**
     * Kiểm tra kết nối API
     * @return bool
     */
    public function testConnection(): bool;

    /**
     * Lấy thông tin chi tiết đơn hàng từ API (nếu có)
     * @param string $api_trans_id Mã giao dịch trên API
     * @return array|null
     */
    public function getOrderDetail(string $api_trans_id): ?array;
}

/**
 * Class chứa kết quả mua hàng từ API
 */
class SupplierApiResult
{
    /** @var bool Thành công hay không */
    public $success = false;

    /** @var string|null Mã lỗi */
    public $error_code = null;

    /** @var string|null Thông báo lỗi */
    public $error_message = null;

    /** @var int HTTP status code */
    public $http_code = 0;

    /** @var string|null Mã giao dịch từ API */
    public $api_trans_id = null;

    /** @var array Danh sách tài khoản nhận được */
    public $accounts = [];

    /** @var array Raw response từ API */
    public $raw_response = [];

    /** @var string|null Trạng thái đơn hàng từ API (pending, completed) */
    public $order_status = null;

    /**
     * Tạo kết quả thành công
     * @param array $accounts Danh sách tài khoản
     * @param string|null $api_trans_id Mã giao dịch API
     * @param array $raw Raw response từ API
     * @param string|null $order_status Trạng thái đơn hàng (pending, completed)
     */
    public static function success(array $accounts, ?string $api_trans_id = null, array $raw = [], ?string $order_status = null): self
    {
        $result = new self();
        $result->success = true;
        $result->accounts = $accounts;
        $result->api_trans_id = $api_trans_id;
        $result->raw_response = $raw;
        $result->order_status = $order_status;
        return $result;
    }

    /**
     * Tạo kết quả lỗi
     */
    public static function error(string $code, string $message, int $http_code = 0, array $raw = []): self
    {
        $result = new self();
        $result->success = false;
        $result->error_code = $code;
        $result->error_message = $message;
        $result->http_code = $http_code;
        $result->raw_response = $raw;
        return $result;
    }

    /**
     * Kiểm tra có phải lỗi hết tiền không
     */
    public function isOutOfMoney(): bool
    {
        return $this->http_code == 402 || $this->error_code == 'OUT_OF_MONEY';
    }

    /**
     * Kiểm tra có phải lỗi kết nối không
     */
    public function isConnectionError(): bool
    {
        return $this->error_code == 'CONNECTION_ERROR' || $this->error_code == 'TIMEOUT';
    }
}
