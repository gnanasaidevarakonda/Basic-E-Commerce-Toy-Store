<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\Product;
use App\Models\User;
use PDO;

class AdminController
{
    /**
     * GET /api/v1/admin/dashboard
     * Executive overview metrics for managers and super admins.
     */
    public function dashboard(): void
    {
        $db = Database::getConnection();

        // 1. Total products count
        $totalProducts = (int) $db->query("SELECT COUNT(*) FROM products")->fetchColumn();

        // 2. Active products in public store
        $activeProducts = (int) $db->query("SELECT COUNT(*) FROM products WHERE is_active = 1")->fetchColumn();

        // 3. Low stock alert (stock <= 5 items)
        $lowStockStmt = $db->query("SELECT COUNT(*) FROM products WHERE stock_quantity <= 5 AND is_active = 1");
        $lowStockCount = (int) $lowStockStmt->fetchColumn();

        // 4. Total categories
        $totalCategories = (int) $db->query("SELECT COUNT(*) FROM categories WHERE is_active = 1")->fetchColumn();

        // 5. Total staff managers
        $totalManagers = (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'manager'")->fetchColumn();

        Response::success([
            'metrics' => [
                'total_products'   => $totalProducts,
                'active_products'  => $activeProducts,
                'low_stock_alerts' => $lowStockCount,
                'total_categories' => $totalCategories,
                'total_managers'   => $totalManagers
            ]
        ], 'Admin dashboard metrics retrieved successfully.');
    }

    /**
     * GET /api/v1/admin/products
     * Inventory list: Shows all products (including low stock and inactive items).
     */
    public function index(): void
    {
        $db = Database::getConnection();
        $params = Request::getQueryParams();

        $sql = "SELECT p.id, p.category_id, c.name AS category_name, p.name, p.slug, 
                       p.sku, p.brand, p.age_group, p.price, p.compare_at_price, 
                       p.stock_quantity, p.is_featured, p.is_active, p.created_at
                FROM products p
                INNER JOIN categories c ON p.category_id = c.id";

        // Optional filter: view low stock items only (?stock=low)
        if (($params['stock'] ?? '') === 'low') {
            $sql .= " WHERE p.stock_quantity <= 5";
        }

        $sql .= " ORDER BY p.id DESC";

        $stmt = $db->query($sql);
        $products = $stmt->fetchAll();

        Response::success([
            'count'    => count($products),
            'products' => $products
        ], 'Admin product catalog retrieved successfully.');
    }

    /**
     * GET /api/v1/admin/products/{id}
     * Staff & Admins: View complete single product details (even if it's inactive).
     */
    public function show(string $id): void
    {
        $productId = (int) $id;
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT p.*, c.name AS category_name, c.slug AS category_slug 
        FROM products p INNER JOIN categories c ON p.category_id = c.id WHERE p.id = :id LIMIT 1");
        $stmt->execute([':id' => $productId]);
        $product = $stmt->fetch();

        if (!$product) {
            Response::error("Product with ID {$productId} not found.", 404);
        }

        // Attach all galary images
        $imgStmt = $db->prepare("SELECT id, image_url, is_primary, sort_order FROM product_images 
        WHERE product_id = :id ORDER BY sort_order ASC, id ASC");
        $imgStmt->execute([':id' => $productId]);
        $product['images'] = $imgStmt->fetchAll();

        Response::success($product, "Admin product details retrieved successfully.");
    }
    /**
     * PATCH /api/v1/admin/products/{id}/status
     * Staff & Admin: Quick 1-click toggle between active and Inactive
     */
    public function toggleStatus(string $id): void
    {
        $productId = (int) $id;
        $db = Database::getConnection();

        // 1. Fetch current status
        $stmt = $db->prepare("SELECT id, name, is_active FROM products WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $productId]);
        $product = $stmt->fetch();

        if (!$product) {
            Response::error("Product with ID {$productId} not found.", 404);
        }

        // 2. Invert active status
        $newStatus = ((int) $product['is_active'] === 1) ? 0 : 1;
        $update = $db->prepare("UPDATE products SET is_active = :status WHERE id = :id");
        $update->execute([':status' => $newStatus, ':id' => $productId]);
        $statusLabel = ($newStatus === 1) ? 'Active' : 'Inactive';
        Response::success([
            'product_id' => $productId,
            'is_active' => $newStatus
        ], "product '{$product['name']}' is now {$statusLabel}.");
    }
    /**
     * PATCH /api/v1/admin/products/{id}/featured
     * Staff & Admin: Quick 1-click toggle for Featured status
     */
    public function toggleFeatured(string $id): void
    {
        $productId = (int) $id;
        $db = Database::getConnection();

        // 1. Fetch current featured status
        $stmt = $db->prepare("SELECT id, name, is_featured FROM products WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $productId]);
        $product = $stmt->fetch();

        if (!$product) {
            Response::error("Product with ID {$productId} not found.", 404);
        }

        // 2. Invert featured status
        $newFeatured = ((int) $product['is_featured'] === 1) ? 0 : 1;
        $update = $db->prepare("UPDATE products SET is_featured = :featured WHERE id = :id");
        $update->execute([':featured' => $newFeatured, ':id' => $productId]);
        $label = ($newFeatured === 1) ? 'Featured on storefront' : 'Removed from featured';

        Response::success([
            'product_id' => $productId,
            'is_featured' => $newFeatured
        ], "Product '{$product['name']}': {$label}.");
    }

    /**
     * POST /api/v1/admin/products
     * Inventory Managers & Super Admins add a new toy to the catalog.
     */
    public function store(): void
    {
        $body = Request::getBody();
        $db   = Database::getConnection();

        // 1. Sanitize & extract inputs
        $categoryId     = (int) ($body['category_id'] ?? 0);
        $name           = trim($body['name'] ?? '');
        $sku            = strtoupper(trim($body['sku'] ?? ''));
        $brand          = trim($body['brand'] ?? 'ToyVerse');
        $ageGroup       = $body['age_group'] ?? '3-5';
        $description    = trim($body['description'] ?? '');
        $price          = (float) ($body['price'] ?? 0);
        $compareAtPrice = !empty($body['compare_at_price']) ? (float) $body['compare_at_price'] : null;
        $stockQuantity  = max(0, (int) ($body['stock_quantity'] ?? 0));
        $isFeatured     = !empty($body['is_featured']) ? 1 : 0;
        $isActive       = isset($body['is_active']) ? (int) $body['is_active'] : 1;

        // Auto-generate URL slug from product name if not provided
        $slug = trim($body['slug'] ?? '');
        if (empty($slug)) {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
        }

        // 2. Validate inputs
        $errors = [];

        if (empty($name)) {
            $errors['name'] = 'Product name is required.';
        }

        if (empty($sku)) {
            $errors['sku'] = 'Unique SKU identifier is required.';
        }

        if ($price <= 0) {
            $errors['price'] = 'Price must be greater than zero.';
        }

        // Verify valid age group ENUM
        $validAgeGroups = ['0-2', '3-5', '6-8', '9-12', '12+'];
        if (!in_array($ageGroup, $validAgeGroups, true)) {
            $errors['age_group'] = 'Invalid age group. Allowed: 0-2, 3-5, 6-8, 9-12, 12+.';
        }

        // Verify category exists in database
        $catStmt = $db->prepare("SELECT id FROM categories WHERE id = :id LIMIT 1");
        $catStmt->execute([':id' => $categoryId]);
        if (!$catStmt->fetch()) {
            $errors['category_id'] = 'Selected category does not exist.';
        }

        if (!empty($errors)) {
            Response::error('Validation failed.', 422, $errors);
        }

        // 3. Verify uniqueness of SKU and Slug
        $checkStmt = $db->prepare("SELECT id FROM products WHERE sku = :sku OR slug = :slug LIMIT 1");
        $checkStmt->execute([':sku' => $sku, ':slug' => $slug]);
        if ($checkStmt->fetch()) {
            Response::error('A product with this SKU or slug already exists.', 409);
        }

        // 4. Insert new toy into MySQL
        $insertSql = "INSERT INTO products (category_id, name, slug, sku, brand, age_group, 
                                            description, price, compare_at_price, stock_quantity, 
                                            is_featured, is_active)
                      VALUES (:category_id, :name, :slug, :sku, :brand, :age_group, 
                              :description, :price, :compare_at_price, :stock_quantity, 
                              :is_featured, :is_active)";

        $stmt = $db->prepare($insertSql);
        $stmt->execute([
            ':category_id'      => $categoryId,
            ':name'             => $name,
            ':slug'             => $slug,
            ':sku'              => $sku,
            ':brand'            => $brand,
            ':age_group'        => $ageGroup,
            ':description'      => $description,
            ':price'            => $price,
            ':compare_at_price' => $compareAtPrice,
            ':stock_quantity'   => $stockQuantity,
            ':is_featured'      => $isFeatured,
            ':is_active'        => $isActive
        ]);

        $newProductId = (int) $db->lastInsertId();

        // 5. Fetch fresh record
        $fetchStmt = $db->prepare("SELECT * FROM products WHERE id = :id LIMIT 1");
        $fetchStmt->execute([':id' => $newProductId]);
        $newProduct = $fetchStmt->fetch();

        Response::success($newProduct, 'Product created successfully.', 201);
    }

    /**
     * PUT /api/v1/admin/products/{id}
     * Inventory Managers & Super Admins update toy details, price, or stock levels.
     */
    public function update(string $id): void
    {
        $productId = (int) $id;
        $db        = Database::getConnection();

        // 1. Verify product exists
        $fetchStmt = $db->prepare("SELECT * FROM products WHERE id = :id LIMIT 1");
        $fetchStmt->execute([':id' => $productId]);
        $existing = $fetchStmt->fetch();

        if (!$existing) {
            Response::error("Product with ID {$productId} not found.", 404);
        }

        $body = Request::getBody();

        // 2. Extract editable values (or keep existing)
        $categoryId     = isset($body['category_id']) ? (int) $body['category_id'] : (int) $existing['category_id'];
        $name           = isset($body['name']) ? trim($body['name']) : $existing['name'];
        $brand          = isset($body['brand']) ? trim($body['brand']) : $existing['brand'];
        $ageGroup       = $body['age_group'] ?? $existing['age_group'];
        $description    = isset($body['description']) ? trim($body['description']) : $existing['description'];
        $price          = isset($body['price']) ? (float) $body['price'] : (float) $existing['price'];
        $stockQuantity  = isset($body['stock_quantity']) ? max(0, (int) $body['stock_quantity']) : (int) $existing['stock_quantity'];
        $isFeatured     = isset($body['is_featured']) ? (int) $body['is_featured'] : (int) $existing['is_featured'];
        $isActive       = isset($body['is_active']) ? (int) $body['is_active'] : (int) $existing['is_active'];

        // 3. Execute update statement
        $updateSql = "UPDATE products 
                      SET category_id = :category_id, name = :name, brand = :brand, 
                          age_group = :age_group, description = :description, 
                          price = :price, stock_quantity = :stock_quantity, 
                          is_featured = :is_featured, is_active = :is_active 
                      WHERE id = :id";

        $stmt = $db->prepare($updateSql);
        $stmt->execute([
            ':category_id'    => $categoryId,
            ':name'           => $name,
            ':brand'          => $brand,
            ':age_group'      => $ageGroup,
            ':description'    => $description,
            ':price'          => $price,
            ':stock_quantity' => $stockQuantity,
            ':is_featured'    => $isFeatured,
            ':is_active'      => $isActive,
            ':id'             => $productId
        ]);

        // Fetch refreshed record
        $fetchStmt->execute([':id' => $productId]);
        $updatedProduct = $fetchStmt->fetch();

        Response::success($updatedProduct, 'Product updated successfully.');
    }

    /**
     * DELETE /api/v1/admin/products/{id}
     * SUPER ADMIN ONLY: Safely deletes or deactivates a product.
     * Hard-deletes if 0 orders exist; soft-deletes if customer orders exist.
     */
    public function destroy(string $id): void
    {
        $productId = (int) $id;
        $db        = Database::getConnection();

        // 1. Verify product exists
        $stmt = $db->prepare("SELECT id, name FROM products WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $productId]);
        $product = $stmt->fetch();

        if (!$product) {
            Response::error("Product with ID {$productId} not found.", 404);
        }

        // 2. Check if product has any historical orders
        $orderCheck = $db->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = :id");
        $orderCheck->execute([':id' => $productId]);
        $orderCount = (int) $orderCheck->fetchColumn();

        if ($orderCount > 0) {
            // Case A: Product was bought before -> Soft-delete to preserve accounting
            $softDelete = $db->prepare("UPDATE products SET is_active = 0 WHERE id = :id");
            $softDelete->execute([':id' => $productId]);

            Response::success([
                'product_id' => $productId,
                'action'     => 'soft_delete'
            ], "Product has {$orderCount} historical order(s). It has been deactivated (soft-deleted) to protect sales records.");
        } else {
            // Case B: Unnecessary / mistake item with 0 orders -> Safe to permanently delete
            $imgDelete = $db->prepare("DELETE FROM product_images WHERE product_id = :id");
            $imgDelete->execute([':id' => $productId]);

            $hardDelete = $db->prepare("DELETE FROM products WHERE id = :id");
            $hardDelete->execute([':id' => $productId]);

            Response::success([
                'product_id' => $productId,
                'action'     => 'hard_delete'
            ], "Product '{$product['name']}' was permanently removed from the catalog.");
        }
    }

    /**
     * GET /api/v1/admin/managers
     * SUPER ADMIN ONLY: List all inventory managers.
     */
    public function listManagers(): void
    {
        $db = Database::getConnection();

        $stmt = $db->query("SELECT id, full_name, email, phone, role, is_active, created_at 
                            FROM users 
                            WHERE role = 'manager' 
                            ORDER BY id DESC");
        $managers = $stmt->fetchAll();

        Response::success([
            'count'    => count($managers),
            'managers' => $managers
        ], 'Inventory managers list retrieved successfully.');
    }

    /**
     * POST /api/v1/admin/managers
     * SUPER ADMIN ONLY: Onboard a new inventory manager.
     */
    public function createManager(): void
    {
        $body = Request::getBody();

        $fullName = trim($body['full_name'] ?? '');
        $email    = strtolower(trim($body['email'] ?? ''));
        $password = $body['password'] ?? '';
        $phone    = trim($body['phone'] ?? '');

        // 1. Validation
        $errors = [];
        if (empty($fullName)) {
            $errors['full_name'] = 'Full name is required.';
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'A valid email address is required.';
        }
        if (empty($password) || strlen($password) < 8) {
            $errors['password'] = 'Manager password must be at least 8 characters long.';
        }

        if (!empty($errors)) {
            Response::error('Validation failed.', 422, $errors);
        }

        // 2. Prevent duplicate email
        if (User::findByEmail($email)) {
            Response::error('An account with this email address already exists.', 409);
        }

        // 3. Create manager in database
        $userId = User::create([
            'full_name' => $fullName,
            'email'     => $email,
            'password'  => $password,
            'phone'     => !empty($phone) ? $phone : null,
            'role'      => 'manager'
        ]);

        $manager = User::findById($userId);

        Response::success($manager, 'Inventory manager account created successfully.', 201);
    }

    /**
     * DELETE /api/v1/admin/managers/{id}
     * SUPER ADMIN ONLY: Remove or revoke a manager's account.
     */
    public function deleteManager(string $id): void
    {
        $managerId = (int) $id;
        $db        = Database::getConnection();

        // 1. Verify user exists and is strictly a manager (prevent deleting admins)
        $stmt = $db->prepare("SELECT id, full_name, role FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $managerId]);
        $user = $stmt->fetch();

        if (!$user) {
            Response::error("Manager with ID {$managerId} not found.", 404);
        }

        if ($user['role'] !== 'manager') {
            Response::error("Cannot delete user with role '{$user['role']}'. Only managers can be deleted here.", 403);
        }

        // 2. Delete the manager record
        $delStmt = $db->prepare("DELETE FROM users WHERE id = :id AND role = 'manager'");
        $delStmt->execute([':id' => $managerId]);

        Response::success([
            'manager_id' => $managerId
        ], "Manager '{$user['full_name']}' has been removed from staff.");
    }
}
