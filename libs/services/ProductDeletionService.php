<?php

/**
 * Product Deletion Service - Xử lý xóa sản phẩm, gói, trường, chuyên mục
 * Hỗ trợ cascade deletion và transaction/rollback
 * 
 * @package SHOPKEY
 * @author CMSNT
 * @version 1.0.0
 */

class ProductDeletionService
{
    private $db;
    private $errors = [];
    private $deletedImages = [];
    private $basePath;

    /**
     * Constructor
     * @param DB $db Database instance
     */
    public function __construct($db)
    {
        $this->db = $db;
        $this->basePath = realpath(__DIR__ . '/../../');
    }

    // =====================================================
    // ERROR HANDLING
    // =====================================================

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
    public function getFirstError(): ?string
    {
        return $this->errors[0] ?? null;
    }

    /**
     * Thêm lỗi
     */
    private function addError(string $message): void
    {
        $this->errors[] = $message;
    }

    /**
     * Xóa tất cả lỗi
     */
    public function clearErrors(): void
    {
        $this->errors = [];
    }

    // =====================================================
    // IMAGE HANDLING
    // =====================================================

    /**
     * Xóa file ảnh an toàn
     * Chỉ xóa ảnh upload riêng (product_*), không xóa ảnh từ thư viện elFinder
     * 
     * @param string|null $imagePath Đường dẫn tương đối của ảnh
     * @return bool True nếu xóa thành công hoặc ảnh không cần xóa
     * @throws Exception Nếu ảnh tồn tại nhưng không thể xóa
     */
    private function deleteImage(?string $imagePath): bool
    {
        if (empty($imagePath)) {
            return true;
        }

        // Không xóa ảnh từ thư viện elFinder (library/)
        if (strpos($imagePath, 'library/') !== false) {
            return true; // Skip ảnh từ thư viện
        }

        $fullPath = $this->basePath . '/' . $imagePath;

        // Nếu file không tồn tại, bỏ qua
        if (!file_exists($fullPath) || !is_file($fullPath)) {
            return true;
        }

        // Thử xóa file
        if (!@unlink($fullPath)) {
            throw new Exception(__('Không thể xóa file ảnh: ') . $imagePath);
        }

        // Lưu lại để log
        $this->deletedImages[] = $imagePath;
        return true;
    }

    /**
     * Lấy danh sách ảnh đã xóa
     */
    public function getDeletedImages(): array
    {
        return $this->deletedImages;
    }

    // =====================================================
    // DELETE FIELD
    // =====================================================

    /**
     * Xóa 1 trường trong product_fields
     * @param int $fieldId ID của field
     * @param bool $useTransaction Có sử dụng transaction không
     * @return bool
     */
    public function deleteField(int $fieldId, bool $useTransaction = true): bool
    {
        $this->clearErrors();

        // Lấy thông tin field
        $field = $this->db->get_row_safe(
            "SELECT * FROM `product_fields` WHERE `id` = ?",
            [$fieldId]
        );

        if (!$field) {
            $this->addError(__('Trường không tồn tại'));
            return false;
        }

        if ($useTransaction) {
            $this->db->beginTransaction();
        }

        try {
            // Xóa field
            $result = $this->db->remove_safe("product_fields", " `id` = ? ", [$fieldId]);

            if (!$result) {
                throw new Exception(__('Không thể xóa trường'));
            }

            if ($useTransaction) {
                $this->db->commit();
            }

            return true;
        } catch (Exception $e) {
            if ($useTransaction) {
                $this->db->rollBack();
            }
            $this->addError($e->getMessage());
            return false;
        }
    }

    /**
     * Xóa tất cả fields của 1 plan
     * @param int $planId ID của plan
     * @return int Số fields đã xóa
     */
    private function deleteFieldsByPlan(int $planId): int
    {
        $fields = $this->db->get_list_safe(
            "SELECT `id` FROM `product_fields` WHERE `plan_id` = ?",
            [$planId]
        );

        if (empty($fields)) {
            return 0;
        }

        $result = $this->db->remove_safe("product_fields", " `plan_id` = ? ", [$planId]);

        if (!$result) {
            throw new Exception(__('Không thể xóa các trường của gói'));
        }

        return count($fields);
    }

    /**
     * Xóa tất cả stock chưa bán của 1 plan
     * Chỉ xóa stock có status = 1 (chưa bán), stock đã bán (status = 0) được giữ lại để khách hàng xem lại
     * @param int $planId ID của plan
     * @return int Số stock đã xóa
     */
    private function deleteStockByPlanId(int $planId): int
    {
        $stockCount = $this->db->num_rows_safe(
            "SELECT id FROM `product_stock` WHERE `plan_id` = ? AND `status` = 1",
            [$planId]
        );

        if ($stockCount > 0) {
            $this->db->remove_safe("product_stock", " `plan_id` = ? AND `status` = 1 ", [$planId]);
        }

        return $stockCount;
    }

    // =====================================================
    // DELETE PLAN
    // =====================================================

    /**
     * Xóa 1 gói sản phẩm + fields + stock liên quan
     * @param int $planId ID của plan
     * @param bool $deleteImage Có xóa ảnh không
     * @param bool $useTransaction Có sử dụng transaction không
     * @return array ['success' => bool, 'deleted_fields' => int, 'deleted_stock' => int]
     */
    public function deletePlan(int $planId, bool $deleteImage = true, bool $useTransaction = true): array
    {
        $this->clearErrors();
        $deletedFields = 0;
        $deletedStock = 0;

        // Lấy thông tin plan
        $plan = $this->db->get_row_safe(
            "SELECT * FROM `product_plans` WHERE `id` = ?",
            [$planId]
        );

        if (!$plan) {
            $this->addError(__('Gói sản phẩm không tồn tại'));
            return ['success' => false, 'deleted_fields' => 0, 'deleted_stock' => 0];
        }

        if ($useTransaction) {
            $this->db->beginTransaction();
        }

        try {
            // 1. Xóa tất cả fields của plan
            $deletedFields = $this->deleteFieldsByPlan($planId);

            // 2. Xóa tất cả stock của plan
            $deletedStock = $this->deleteStockByPlanId($planId);

            // 3. Xóa ảnh của plan (nếu có)
            if ($deleteImage && !empty($plan['image'])) {
                $this->deleteImage($plan['image']);
            }

            // 4. Xóa plan
            $result = $this->db->remove_safe("product_plans", " `id` = ? ", [$planId]);

            if (!$result) {
                throw new Exception(__('Không thể xóa gói sản phẩm'));
            }

            if ($useTransaction) {
                $this->db->commit();
            }

            return [
                'success' => true,
                'deleted_fields' => $deletedFields,
                'deleted_stock' => $deletedStock,
                'plan' => $plan
            ];
        } catch (Exception $e) {
            if ($useTransaction) {
                $this->db->rollBack();
            }
            $this->addError($e->getMessage());
            return ['success' => false, 'deleted_fields' => 0, 'deleted_stock' => 0];
        }
    }

    /**
     * Xóa tất cả plans của 1 product
     * @param int $productId ID của product
     * @param bool $deleteImages Có xóa ảnh không
     * @return array ['deleted_plans' => int, 'deleted_fields' => int, 'deleted_stock' => int]
     */
    private function deletePlansByProduct(int $productId, bool $deleteImages = true): array
    {
        $plans = $this->db->get_list_safe(
            "SELECT * FROM `product_plans` WHERE `product_id` = ?",
            [$productId]
        );

        $deletedPlans = 0;
        $deletedFields = 0;
        $deletedStock = 0;

        if (empty($plans)) {
            return ['deleted_plans' => 0, 'deleted_fields' => 0, 'deleted_stock' => 0];
        }

        foreach ($plans as $plan) {
            // Xóa fields của plan
            $deletedFields += $this->deleteFieldsByPlan($plan['id']);

            // Xóa stock của plan
            $deletedStock += $this->deleteStockByPlanId($plan['id']);

            // Xóa ảnh của plan
            if ($deleteImages && !empty($plan['image'])) {
                $this->deleteImage($plan['image']);
            }
        }

        // Xóa tất cả plans
        $result = $this->db->remove_safe("product_plans", " `product_id` = ? ", [$productId]);

        if (!$result) {
            throw new Exception(__('Không thể xóa các gói sản phẩm'));
        }

        return [
            'deleted_plans' => count($plans),
            'deleted_fields' => $deletedFields,
            'deleted_stock' => $deletedStock
        ];
    }

    // =====================================================
    // DELETE PRODUCT
    // =====================================================

    /**
     * Xóa 1 sản phẩm + plans + fields
     * @param int $productId ID của product
     * @param bool $deleteImages Có xóa ảnh không
     * @param bool $useTransaction Có sử dụng transaction không
     * @return array ['success' => bool, 'deleted_plans' => int, 'deleted_fields' => int]
     */
    public function deleteProduct(int $productId, bool $deleteImages = true, bool $useTransaction = true): array
    {
        $this->clearErrors();
        $this->deletedImages = [];

        // Lấy thông tin product
        $product = $this->db->get_row_safe(
            "SELECT * FROM `products` WHERE `id` = ?",
            [$productId]
        );

        if (!$product) {
            $this->addError(__('Sản phẩm không tồn tại'));
            return ['success' => false, 'deleted_plans' => 0, 'deleted_fields' => 0];
        }

        if ($useTransaction) {
            $this->db->beginTransaction();
        }

        try {
            // 1. Xóa tất cả plans + fields của product
            $planResult = $this->deletePlansByProduct($productId, $deleteImages);

            // 2. Xóa product favorites
            $this->db->remove_safe("product_favorites", " `product_id` = ? ", [$productId]);

            // 3. Xóa product reviews
            $this->db->remove_safe("product_reviews", " `product_id` = ? ", [$productId]);

            // 2. Xóa ảnh sản phẩm (nếu có)
            if ($deleteImages && !empty($product['image'])) {
                $this->deleteImage($product['image']);
            }

            // 3. Xóa product
            $result = $this->db->remove_safe("products", " `id` = ? ", [$productId]);

            if (!$result) {
                throw new Exception(__('Không thể xóa sản phẩm'));
            }

            if ($useTransaction) {
                $this->db->commit();
            }

            return [
                'success' => true,
                'deleted_plans' => $planResult['deleted_plans'],
                'deleted_fields' => $planResult['deleted_fields'],
                'product' => $product
            ];
        } catch (Exception $e) {
            if ($useTransaction) {
                $this->db->rollBack();
            }
            $this->addError($e->getMessage());
            return ['success' => false, 'deleted_plans' => 0, 'deleted_fields' => 0];
        }
    }

    // =====================================================
    // DELETE CATEGORY
    // =====================================================

    /**
     * Xóa 1 chuyên mục + products + plans + fields
     * @param int $categoryId ID của category
     * @param bool $deleteImages Có xóa ảnh không
     * @param bool $deleteProducts Có xóa products con không (false = chỉ set category_id = 0)
     * @param bool $useTransaction Có sử dụng transaction không
     * @return array ['success' => bool, 'deleted_products' => int, 'deleted_plans' => int, 'deleted_fields' => int]
     */
    public function deleteCategory(
        int $categoryId,
        bool $deleteImages = true,
        bool $deleteProducts = false,
        bool $useTransaction = true
    ): array {
        $this->clearErrors();
        $this->deletedImages = [];

        // Lấy thông tin category
        $category = $this->db->get_row_safe(
            "SELECT * FROM `categories` WHERE `id` = ?",
            [$categoryId]
        );

        if (!$category) {
            $this->addError(__('Chuyên mục không tồn tại'));
            return ['success' => false, 'deleted_products' => 0, 'deleted_plans' => 0, 'deleted_fields' => 0];
        }

        if ($useTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $deletedProducts = 0;
            $deletedPlans = 0;
            $deletedFields = 0;

            // Lấy danh sách products thuộc category (multi-category support)
            $products = $this->db->get_list_safe(
                "SELECT * FROM `products` WHERE FIND_IN_SET(?, `category_ids`) > 0",
                [$categoryId]
            );

            if (!empty($products)) {
                if ($deleteProducts) {
                    // Xóa tất cả products + plans + fields
                    foreach ($products as $prod) {
                        $planResult = $this->deletePlansByProduct($prod['id'], $deleteImages);
                        $deletedPlans += $planResult['deleted_plans'];
                        $deletedFields += $planResult['deleted_fields'];

                        // Xóa ảnh product
                        if ($deleteImages && !empty($prod['image'])) {
                            $this->deleteImage($prod['image']);
                        }
                    }

                    // Xóa tất cả products đã loop qua
                    foreach ($products as $prod) {
                        // Xóa product favorites
                        $this->db->remove_safe("product_favorites", " `product_id` = ? ", [$prod['id']]);
                        // Xóa product reviews
                        $this->db->remove_safe("product_reviews", " `product_id` = ? ", [$prod['id']]);
                        $this->db->remove_safe("products", " `id` = ? ", [$prod['id']]);
                    }
                    $deletedProducts = count($products);
                } else {
                    // Xóa category này khỏi category_ids của sản phẩm (multi-category)
                    foreach ($products as $prod) {
                        $catIds = array_filter(explode(',', $prod['category_ids'] ?? ''));
                        $catIds = array_diff($catIds, [(string)$categoryId]);
                        $newCatIds = implode(',', $catIds);
                        $this->db->update_safe(
                            "products",
                            ['category_ids' => $newCatIds],
                            " `id` = ? ",
                            [$prod['id']]
                        );
                    }
                }
            }

            // Xóa ảnh category (icon)
            if ($deleteImages && !empty($category['icon'])) {
                $this->deleteImage($category['icon']);
            }

            // Xóa category
            $result = $this->db->remove_safe("categories", " `id` = ? ", [$categoryId]);

            if (!$result) {
                throw new Exception(__('Không thể xóa chuyên mục'));
            }

            if ($useTransaction) {
                $this->db->commit();
            }

            return [
                'success' => true,
                'deleted_products' => $deletedProducts,
                'deleted_plans' => $deletedPlans,
                'deleted_fields' => $deletedFields,
                'category' => $category
            ];
        } catch (Exception $e) {
            if ($useTransaction) {
                $this->db->rollBack();
            }
            $this->addError($e->getMessage());
            return ['success' => false, 'deleted_products' => 0, 'deleted_plans' => 0, 'deleted_fields' => 0];
        }
    }

    // =====================================================
    // BULK DELETE
    // =====================================================

    /**
     * Xóa nhiều plans cùng lúc
     * @param array $planIds Mảng các plan ID
     * @param bool $deleteImages Có xóa ảnh không
     * @return array ['success' => bool, 'deleted_plans' => int, 'deleted_fields' => int, 'deleted_stock' => int]
     */
    public function deletePlans(array $planIds, bool $deleteImages = true): array
    {
        $this->clearErrors();
        $this->deletedImages = [];

        if (empty($planIds)) {
            return ['success' => true, 'deleted_plans' => 0, 'deleted_fields' => 0, 'deleted_stock' => 0];
        }

        $this->db->beginTransaction();

        try {
            $deletedPlans = 0;
            $deletedFields = 0;
            $deletedStock = 0;

            foreach ($planIds as $planId) {
                $plan = $this->db->get_row_safe(
                    "SELECT * FROM `product_plans` WHERE `id` = ?",
                    [$planId]
                );

                if ($plan) {
                    // Xóa fields
                    $deletedFields += $this->deleteFieldsByPlan($planId);

                    // Xóa stock
                    $deletedStock += $this->deleteStockByPlanId($planId);

                    // Xóa ảnh
                    if ($deleteImages && !empty($plan['image'])) {
                        $this->deleteImage($plan['image']);
                    }

                    // Xóa plan
                    $this->db->remove_safe("product_plans", " `id` = ? ", [$planId]);
                    $deletedPlans++;
                }
            }

            $this->db->commit();

            return [
                'success' => true,
                'deleted_plans' => $deletedPlans,
                'deleted_fields' => $deletedFields,
                'deleted_stock' => $deletedStock
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->addError($e->getMessage());
            return ['success' => false, 'deleted_plans' => 0, 'deleted_fields' => 0, 'deleted_stock' => 0];
        }
    }

    /**
     * Xóa nhiều products cùng lúc
     * @param array $productIds Mảng các product ID
     * @param bool $deleteImages Có xóa ảnh không
     * @return array ['success' => bool, 'deleted_products' => int, 'deleted_plans' => int, 'deleted_fields' => int]
     */
    public function deleteProducts(array $productIds, bool $deleteImages = true): array
    {
        $this->clearErrors();
        $this->deletedImages = [];

        if (empty($productIds)) {
            return ['success' => true, 'deleted_products' => 0, 'deleted_plans' => 0, 'deleted_fields' => 0];
        }

        $this->db->beginTransaction();

        try {
            $deletedProducts = 0;
            $deletedPlans = 0;
            $deletedFields = 0;

            foreach ($productIds as $productId) {
                $product = $this->db->get_row_safe(
                    "SELECT * FROM `products` WHERE `id` = ?",
                    [$productId]
                );

                if ($product) {
                    // Xóa plans + fields
                    $planResult = $this->deletePlansByProduct($productId, $deleteImages);
                    $deletedPlans += $planResult['deleted_plans'];
                    $deletedFields += $planResult['deleted_fields'];

                    // Xóa ảnh product
                    if ($deleteImages && !empty($product['image'])) {
                        $this->deleteImage($product['image']);
                    }

                    // Xóa product favorites
                    $this->db->remove_safe("product_favorites", " `product_id` = ? ", [$productId]);

                    // Xóa product reviews
                    $this->db->remove_safe("product_reviews", " `product_id` = ? ", [$productId]);

                    // Xóa product
                    $this->db->remove_safe("products", " `id` = ? ", [$productId]);
                    $deletedProducts++;
                }
            }

            $this->db->commit();

            return [
                'success' => true,
                'deleted_products' => $deletedProducts,
                'deleted_plans' => $deletedPlans,
                'deleted_fields' => $deletedFields
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->addError($e->getMessage());
            return ['success' => false, 'deleted_products' => 0, 'deleted_plans' => 0, 'deleted_fields' => 0];
        }
    }

    // =====================================================
    // DELETE PRODUCT STOCK
    // =====================================================

    /**
     * Xóa hoặc reset stock theo order_id
     * @param int $orderId ID của order
     * @param bool $deleteStock true = xóa hoàn toàn, false = reset về trạng thái available
     * @return int Số stock đã xử lý
     */
    private function deleteStockByOrderId(int $orderId, bool $deleteStock = true): int
    {
        // Đếm số stock liên quan
        $stockCount = $this->db->num_rows_safe(
            "SELECT id FROM `product_stock` WHERE `order_id` = ?",
            [$orderId]
        );

        if ($stockCount == 0) {
            return 0;
        }

        if ($deleteStock) {
            // Xóa hoàn toàn stock
            $this->db->remove_safe("product_stock", " `order_id` = ? ", [$orderId]);
            return $stockCount;
        } else {
            // Chỉ reset stock về trạng thái available (không xóa)
            $this->db->update_safe(
                "product_stock",
                ['order_id' => NULL, 'status' => 1],
                " `order_id` = ? ",
                [$orderId]
            );
            return 0; // Không xóa nên return 0
        }
    }

    // =====================================================
    // DELETE PRODUCT ORDER
    // =====================================================

    /**
     * Xóa 1 đơn hàng sản phẩm + stock liên quan
     * @param int $orderId ID của order
     * @param bool $deleteStock true = xóa stock hoàn toàn, false = chỉ reset stock về trạng thái available
     * @param bool $useTransaction Có sử dụng transaction không
     * @return array ['success' => bool, 'deleted_stock' => int, 'order' => array|null]
     */
    public function deleteProductOrder(int $orderId, bool $deleteStock = true, bool $useTransaction = true): array
    {
        $this->clearErrors();

        // Lấy thông tin order
        $order = $this->db->get_row_safe(
            "SELECT * FROM `product_orders` WHERE `id` = ?",
            [$orderId]
        );

        if (!$order) {
            $this->addError(__('Đơn hàng không tồn tại'));
            return ['success' => false, 'deleted_stock' => 0, 'order' => null];
        }

        if ($useTransaction) {
            $this->db->beginTransaction();
        }

        try {
            // Xóa/reset stock liên quan
            $deletedStock = $this->deleteStockByOrderId($orderId, $deleteStock);

            // Xóa flash sale purchase nếu có
            $this->db->remove_safe("flash_sale_purchases", " `order_id` = ? ", [$orderId]);

            // Xóa order
            $result = $this->db->remove_safe("product_orders", " `id` = ? ", [$orderId]);

            if (!$result) {
                throw new Exception(__('Không thể xóa đơn hàng'));
            }

            if ($useTransaction) {
                $this->db->commit();
            }

            return [
                'success' => true,
                'deleted_stock' => $deletedStock,
                'order' => $order
            ];
        } catch (Exception $e) {
            if ($useTransaction) {
                $this->db->rollBack();
            }
            $this->addError($e->getMessage());
            return ['success' => false, 'deleted_stock' => 0, 'order' => null];
        }
    }

    /**
     * Xóa nhiều đơn hàng sản phẩm cùng lúc
     * @param array $orderIds Mảng các order ID
     * @param bool $deleteStock true = xóa stock hoàn toàn, false = chỉ reset stock về trạng thái available
     * @return array ['success' => bool, 'deleted_orders' => int, 'deleted_stock' => int]
     */
    public function deleteProductOrders(array $orderIds, bool $deleteStock = true): array
    {
        $this->clearErrors();

        if (empty($orderIds)) {
            return ['success' => true, 'deleted_orders' => 0, 'deleted_stock' => 0];
        }

        $this->db->beginTransaction();

        try {
            $deletedOrders = 0;
            $deletedStock = 0;

            foreach ($orderIds as $orderId) {
                $order = $this->db->get_row_safe(
                    "SELECT * FROM `product_orders` WHERE `id` = ?",
                    [$orderId]
                );

                if ($order) {
                    // Xóa/reset stock liên quan
                    $deletedStock += $this->deleteStockByOrderId($orderId, $deleteStock);

                    // Xóa flash sale purchase nếu có
                    $this->db->remove_safe("flash_sale_purchases", " `order_id` = ? ", [$orderId]);

                    // Xóa order
                    $this->db->remove_safe("product_orders", " `id` = ? ", [$orderId]);
                    $deletedOrders++;
                }
            }

            $this->db->commit();

            return [
                'success' => true,
                'deleted_orders' => $deletedOrders,
                'deleted_stock' => $deletedStock
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->addError($e->getMessage());
            return ['success' => false, 'deleted_orders' => 0, 'deleted_stock' => 0];
        }
    }

    // =====================================================
    // DELETE SUPPLIER
    // =====================================================

    /**
     * Xóa tất cả plans của 1 supplier
     * @param int $supplierId ID của supplier
     * @param bool $deleteImages Có xóa ảnh không
     * @return array ['deleted_plans' => int, 'deleted_fields' => int, 'deleted_stock' => int]
     */
    private function deletePlansBySupplier(int $supplierId, bool $deleteImages = true): array
    {
        $plans = $this->db->get_list_safe(
            "SELECT * FROM `product_plans` WHERE `supplier_id` = ?",
            [$supplierId]
        );

        $deletedPlans = 0;
        $deletedFields = 0;
        $deletedStock = 0;

        if (empty($plans)) {
            return ['deleted_plans' => 0, 'deleted_fields' => 0, 'deleted_stock' => 0];
        }

        foreach ($plans as $plan) {
            // Xóa fields của plan
            $deletedFields += $this->deleteFieldsByPlan($plan['id']);

            // Xóa stock của plan
            $stockCount = $this->db->num_rows_safe(
                "SELECT id FROM `product_stock` WHERE `plan_id` = ?",
                [$plan['id']]
            );
            $this->db->remove_safe("product_stock", " `plan_id` = ? ", [$plan['id']]);
            $deletedStock += $stockCount;

            // Xóa ảnh của plan
            if ($deleteImages && !empty($plan['image'])) {
                $this->deleteImage($plan['image']);
            }
        }

        // Xóa tất cả plans
        $this->db->remove_safe("product_plans", " `supplier_id` = ? ", [$supplierId]);

        return [
            'deleted_plans' => count($plans),
            'deleted_fields' => $deletedFields,
            'deleted_stock' => $deletedStock
        ];
    }

    /**
     * Xóa tất cả products của 1 supplier
     * @param int $supplierId ID của supplier
     * @param bool $deleteImages Có xóa ảnh không
     * @return int Số products đã xóa
     */
    private function deleteProductsBySupplier(int $supplierId, bool $deleteImages = true): int
    {
        $products = $this->db->get_list_safe(
            "SELECT * FROM `products` WHERE `supplier_id` = ?",
            [$supplierId]
        );

        if (empty($products)) {
            return 0;
        }

        foreach ($products as $product) {
            // Xóa ảnh product
            if ($deleteImages && !empty($product['image'])) {
                $this->deleteImage($product['image']);
            }
        }

        // Xóa tất cả products
        $this->db->remove_safe("products", " `supplier_id` = ? ", [$supplierId]);

        return count($products);
    }

    /**
     * Xóa tất cả categories của 1 supplier
     * @param int $supplierId ID của supplier
     * @param bool $deleteImages Có xóa ảnh không
     * @return int Số categories đã xóa
     */
    private function deleteCategoriesBySupplier(int $supplierId, bool $deleteImages = true): int
    {
        $categories = $this->db->get_list_safe(
            "SELECT * FROM `categories` WHERE `supplier_id` = ?",
            [$supplierId]
        );

        if (empty($categories)) {
            return 0;
        }

        foreach ($categories as $category) {
            // Xóa icon category
            if ($deleteImages && !empty($category['icon'])) {
                $this->deleteImage($category['icon']);
            }
        }

        // Xóa tất cả categories
        $this->db->remove_safe("categories", " `supplier_id` = ? ", [$supplierId]);

        return count($categories);
    }

    /**
     * Xóa 1 supplier + tất cả dữ liệu liên quan (categories, products, plans, fields, stock)
     * @param int $supplierId ID của supplier
     * @param bool $deleteImages Có xóa ảnh không
     * @param bool $useTransaction Có sử dụng transaction không
     * @return array ['success' => bool, 'deleted_categories' => int, 'deleted_products' => int, 'deleted_plans' => int, 'deleted_fields' => int, 'deleted_stock' => int, 'supplier' => array|null]
     */
    public function deleteSupplier(int $supplierId, bool $deleteImages = true, bool $useTransaction = true): array
    {
        $this->clearErrors();
        $this->deletedImages = [];

        // Lấy thông tin supplier
        $supplier = $this->db->get_row_safe(
            "SELECT * FROM `suppliers` WHERE `id` = ?",
            [$supplierId]
        );

        if (!$supplier) {
            $this->addError(__('Supplier không tồn tại'));
            return [
                'success' => false,
                'deleted_categories' => 0,
                'deleted_products' => 0,
                'deleted_plans' => 0,
                'deleted_fields' => 0,
                'deleted_stock' => 0,
                'supplier' => null
            ];
        }

        if ($useTransaction) {
            $this->db->beginTransaction();
        }

        try {
            // 1. Xóa tất cả plans + fields + stock của supplier
            $planResult = $this->deletePlansBySupplier($supplierId, $deleteImages);

            // 2. Xóa tất cả products của supplier
            $deletedProducts = $this->deleteProductsBySupplier($supplierId, $deleteImages);

            // 3. Xóa tất cả categories của supplier
            $deletedCategories = $this->deleteCategoriesBySupplier($supplierId, $deleteImages);

            // 4. Xóa supplier
            $result = $this->db->remove_safe("suppliers", " `id` = ? ", [$supplierId]);

            if (!$result) {
                throw new Exception(__('Không thể xóa Supplier'));
            }

            if ($useTransaction) {
                $this->db->commit();
            }

            return [
                'success' => true,
                'deleted_categories' => $deletedCategories,
                'deleted_products' => $deletedProducts,
                'deleted_plans' => $planResult['deleted_plans'],
                'deleted_fields' => $planResult['deleted_fields'],
                'deleted_stock' => $planResult['deleted_stock'],
                'supplier' => $supplier
            ];
        } catch (Exception $e) {
            if ($useTransaction) {
                $this->db->rollBack();
            }
            $this->addError($e->getMessage());
            return [
                'success' => false,
                'deleted_categories' => 0,
                'deleted_products' => 0,
                'deleted_plans' => 0,
                'deleted_fields' => 0,
                'deleted_stock' => 0,
                'supplier' => null
            ];
        }
    }
}
