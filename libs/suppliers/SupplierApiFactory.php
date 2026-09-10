<?php

/**
 * Supplier API Factory - AUTO DISCOVERY
 * Tự động tìm và load các API class từ thư mục
 * 
 * HƯỚNG DẪN THÊM API MỚI:
 * 1. Tạo file: libs/suppliers/YourNewApi.php
 * 2. Class name: YourNewApi (extends BaseSupplierApi)
 * 3. Method getType() phải trả về type (VD: 'YOURNEW')
 * 4. DONE! Không cần sửa file này
 * 
 * @package SHOPKEY
 * @author CMSNT
 * @version 2.0.0
 */

if (!defined('IN_SITE')) {
    die('The Request Not Found');
}

require_once(__DIR__ . '/SupplierApiInterface.php');
require_once(__DIR__ . '/BaseSupplierApi.php');

class SupplierApiFactory
{
    /**
     * Đăng ký các API class (auto-discovered + manual)
     * @var array
     */
    private static $apiClasses = [];

    /**
     * Đã auto-discover chưa
     * @var bool
     */
    private static $discovered = false;

    /**
     * Cache instances đã tạo
     * @var array
     */
    private static $instances = [];

    /**
     * Auto-discover tất cả API classes trong thư mục
     * Tìm các file kết thúc bằng Api.php và tự động đăng ký
     */
    private static function autoDiscover(): void
    {
        if (self::$discovered) {
            return;
        }

        $dir = __DIR__;
        $files = glob($dir . '/*Api.php');

        foreach ($files as $file) {
            $filename = basename($file);

            // Bỏ qua các file base/interface
            if (in_array($filename, ['BaseSupplierApi.php', 'SupplierApiInterface.php'])) {
                continue;
            }

            // Load file
            require_once($file);

            // Lấy tên class từ filename (VD: ShopkeyApi.php -> ShopkeyApi)
            $className = pathinfo($filename, PATHINFO_FILENAME);

            // Kiểm tra class tồn tại và là subclass của BaseSupplierApi
            if (class_exists($className) && is_subclass_of($className, 'BaseSupplierApi')) {
                // Tạo instance tạm để lấy type
                $tempInstance = new $className();
                $type = strtoupper($tempInstance->getType());

                if (!empty($type)) {
                    self::$apiClasses[$type] = $className;
                }
            }
        }

        self::$discovered = true;
    }

    /**
     * Tạo instance của Supplier API
     * 
     * @param array $supplier Thông tin supplier từ database
     * @return SupplierApiInterface|null
     */
    public static function create(array $supplier): ?SupplierApiInterface
    {
        // Auto-discover nếu chưa
        self::autoDiscover();

        $type = $supplier['type'] ?? '';

        if (empty($type)) {
            return null;
        }

        $type = strtoupper($type);

        if (!isset(self::$apiClasses[$type])) {
            return null;
        }

        $className = self::$apiClasses[$type];

        if (!class_exists($className)) {
            return null;
        }

        $instance = new $className();
        $instance->setSupplier($supplier);

        return $instance;
    }

    /**
     * Tạo hoặc lấy instance từ cache (Singleton per supplier)
     * 
     * @param array $supplier Thông tin supplier
     * @return SupplierApiInterface|null
     */
    public static function getInstance(array $supplier): ?SupplierApiInterface
    {
        $supplier_id = $supplier['id'] ?? 0;

        if ($supplier_id <= 0) {
            return self::create($supplier);
        }

        if (!isset(self::$instances[$supplier_id])) {
            self::$instances[$supplier_id] = self::create($supplier);
        }

        return self::$instances[$supplier_id];
    }

    /**
     * Xóa cache instance
     */
    public static function clearCache(?int $supplier_id = null): void
    {
        if ($supplier_id !== null) {
            unset(self::$instances[$supplier_id]);
        } else {
            self::$instances = [];
        }
    }

    /**
     * Kiểm tra loại API có được hỗ trợ không
     * 
     * @param string $type Loại API
     * @return bool
     */
    public static function isSupported(string $type): bool
    {
        self::autoDiscover();
        return isset(self::$apiClasses[strtoupper($type)]);
    }

    /**
     * Lấy danh sách các loại API được hỗ trợ
     * 
     * @return array
     */
    public static function getSupportedTypes(): array
    {
        self::autoDiscover();
        return array_keys(self::$apiClasses);
    }

    /**
     * Lấy thông tin tất cả API types
     * Dùng cho dropdown, settings, etc.
     * 
     * @return array [type => ['class' => className, 'name' => displayName]]
     */
    public static function getAllApiInfo(): array
    {
        self::autoDiscover();

        $info = [];
        foreach (self::$apiClasses as $type => $className) {
            $info[$type] = [
                'class' => $className,
                'type' => $type
            ];
        }
        return $info;
    }

    /**
     * Đăng ký API class mới (manual)
     * Cho phép plugin/module đăng ký API mà không cần tạo file
     * 
     * @param string $type Loại API
     * @param string $className Tên class
     */
    public static function register(string $type, string $className): void
    {
        self::$apiClasses[strtoupper($type)] = $className;
    }

    /**
     * Hủy đăng ký API class
     * 
     * @param string $type Loại API
     */
    public static function unregister(string $type): void
    {
        unset(self::$apiClasses[strtoupper($type)]);
        // Xóa instance cache nếu có
        foreach (self::$instances as $id => $instance) {
            if ($instance && $instance->getType() === strtoupper($type)) {
                unset(self::$instances[$id]);
            }
        }
    }

    /**
     * Quick method: Mua hàng từ supplier
     * 
     * @param array $supplier Thông tin supplier
     * @param string $api_product_id ID sản phẩm trên API
     * @param int $quantity Số lượng
     * @param array $fields Dữ liệu fields (email, username, etc.)
     * @return SupplierApiResult
     */
    public static function buy(array $supplier, string $api_product_id, int $quantity, array $fields = []): SupplierApiResult
    {
        $api = self::create($supplier);

        if (!$api) {
            return SupplierApiResult::error(
                'UNSUPPORTED_API',
                sprintf(__('Loại API "%s" không được hỗ trợ'), $supplier['type'] ?? 'unknown')
            );
        }

        return $api->buyProduct($api_product_id, $quantity, $fields);
    }

    /**
     * Quick method: Lấy danh sách sản phẩm
     * 
     * @param array $supplier Thông tin supplier
     * @return array|null
     */
    public static function getProducts(array $supplier): ?array
    {
        $api = self::create($supplier);
        return $api ? $api->getProducts() : null;
    }

    /**
     * Quick method: Lấy số dư
     * 
     * @param array $supplier Thông tin supplier
     * @return float|null
     */
    public static function getBalance(array $supplier): ?float
    {
        $api = self::create($supplier);
        return $api ? $api->getBalance() : null;
    }

    /**
     * Quick method: Test kết nối
     * 
     * @param array $supplier Thông tin supplier
     * @return bool
     */
    public static function testConnection(array $supplier): bool
    {
        $api = self::create($supplier);
        return $api ? $api->testConnection() : false;
    }

    /**
     * Force re-discover (dùng khi thêm API mới runtime)
     */
    public static function refresh(): void
    {
        self::$discovered = false;
        self::$apiClasses = [];
        self::autoDiscover();
    }

    /**
     * Lấy cấu hình fields cho tất cả API types
     * Dùng để render form động trong admin
     * 
     * @return array [type => ['display_name' => string, 'fields_config' => array]]
     */
    public static function getAllApiConfigs(): array
    {
        self::autoDiscover();

        $configs = [];
        foreach (self::$apiClasses as $type => $className) {
            if (class_exists($className)) {
                $instance = new $className();
                $configs[$type] = [
                    'display_name' => $instance->getDisplayName(),
                    'fields_config' => $instance->getFieldsConfig()
                ];
            }
        }
        return $configs;
    }

    /**
     * Lấy cấu hình fields cho một API type cụ thể
     * 
     * @param string $type API type
     * @return array|null Config hoặc null nếu không tìm thấy
     */
    public static function getApiConfig(string $type): ?array
    {
        self::autoDiscover();

        $type = strtoupper($type);
        if (!isset(self::$apiClasses[$type])) {
            return null;
        }

        $className = self::$apiClasses[$type];
        if (!class_exists($className)) {
            return null;
        }

        $instance = new $className();
        return [
            'display_name' => $instance->getDisplayName(),
            'fields_config' => $instance->getFieldsConfig()
        ];
    }

    /**
     * Validate form data cho một API type
     * 
     * @param string $type API type
     * @param array $postData Dữ liệu form
     * @return array ['valid' => bool, 'error' => string]
     */
    public static function validateFormData(string $type, array $postData): array
    {
        self::autoDiscover();

        $type = strtoupper($type);
        if (!isset(self::$apiClasses[$type])) {
            return ['valid' => false, 'error' => 'Loại API không được hỗ trợ'];
        }

        $className = self::$apiClasses[$type];
        if (!class_exists($className)) {
            return ['valid' => false, 'error' => 'Không tìm thấy class API'];
        }

        $instance = new $className();
        return $instance->validateFormData($postData);
    }
}
