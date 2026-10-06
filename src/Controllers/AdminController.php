<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;
use PDO;

class AdminController
{
    /**
     * GET /api/v1/admin/dashboard
     * Executive overview metrics for managers and admin.
     */
    public function dashboard(): void
    {
        $db = Database::getConnection();

        // 1. Total products count
        $totalProducts = (int) $db->query("SELECT COUNT(*) FROM products")->fetchColumn();

        // 2. Active products in public store
        $activeProducts = (int) $db->query("SELECT COUNT(*) FROM products WHERE is_active = 1")->fetchColumn();

        // 3. Low stock alert( stock <= 5 )
        $lowStockStmt = $db->query("SELECT COUNT(*) FROM products WHERE stock_quantity <= 5 AND is_active = 1");
        $lowStockCount = (int) $lowStockStmt->fetchColumn();

        // 4. Total categories
        $totalCategories = (int) $db->query("SELECT COUNT(*) FROM categories WHERE is_active = 1")->fetchColumn();

        // 5. Total staff managers
        $totalManagers = (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'manager'")->fetchColumn();

        Response::success([
            'metrics' => [
                'total_products' => $totalProducts,
                'active_products' => $activeProducts,
                'low_stock_alerts' => $lowStockCount,
                'total_categories' => $totalCategories,
                'total_managers' => $totalManagers
            ]
        ], 'Admin dashboard metrics retrieved successfully.');
    }

    /**
     * GET /api/v1/admin/products
     * Inventory list: Show all products (include lkow stock and inacive items).
     */
    public function index(): void
    {
        $db = Database::getConnection();
        $params = Request::getQueryParams();

        $sql = "SELECT p.id, p.category_id, c.name AS category_name, p.name, p.slug, p.sku, 
        p.brand, p.age_group, p.price, p.comapre_at_price, p.stock_quantity, p.is_featured, p.is_active, 
        p.created_at FROM products p INNER JOIN categories c ON  p.category_id = c.id";

        // optional filter: view low srock items only (?stock=low)
        if (($params['stock'] ?? '') === 'low') {
            $sql .= " WHERE p.stock_quantity <= 5";
        }
        $sql .= " ORDER BY p.id DESC";
        $stmt = $db->query($sql);
        $products = $stmt->fetchAll();

        Response::success([
            'count' => count($products),
            'products' => $products
        ], 'Admin product catalog retrieved successfully.');
    }

    /**
     * POST /api/v1/admin/products
     * Invemtory managers & super admin add a new toy to the catlog.
     */
    public function store(): void
    {
        $body = Request::getBody();
        $db = Database::getConnection();

        // 1. Sanitize & extract inputs
        $categoryId = (int) ($body['category_id'] ?? 0);
        $name = trim($body['name'] ?? '');
        $sku = strtoupper(trim($body['sku'] ?? ''));
        $brand = trim($body['brand'] ?? 'Toyverse');
        $ageGroup = $body['age_aroup'] ?? '3-5';
        $description = trim($body['description'] ?? '');
        $price = (float) ($body['price'] ?? 0);
        $comapreAtPrice = !empty($body['compare_at_price']) ? (float) $body['compare_at_price'] : null;
        $stockQuantity = max(0, (int) ($body['stock_qunatity'] ?? 0));
        $isFeatured = !empty($body['is_featured']) ? 1 : 0;
        $isActive = isset($body['is_active']) ? (int) $body['is_active'] : 1;

        // Auto-generate URL slug from products anme if not provided
        $slug = trim($body['slug'] ?? '');
        if (empty($slug)) {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
        }

        // 2. validate inputs
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

        // verify valid age group ENUM
        $validAgeGroups = ['0-2', '3-5', '6-8', '9-12', '12+'];
        if (!in_array($ageGroup, $validAgeGroups, true)) {
            $errors['age_group'] = 'Invalid age group. Allowed: 0-2, 3-5, 6-8, 9-12, 12+.';
        }

        // verify category exists in database
        $catStmt = $db->prepare("SELECT id FROM categories WHERE id = :id LIMIT 1");
        $catStmt->execute([':id' => $categoryId]);
        if (!$catStmt->fetch()) {
            $errors['category_id'] = 'Selected category does not exist.';
        }

        if (!empty($errors)) {
            Response::error('Validation failed.', 422, $errors);
        }

        // 3. verify uniqueness of SKU and Slug
        $checkStmt = $db->prepare("SELECT id FROM products WHERE sku = :sku OR slug = :slug LIMIT 1");
        $checkStmt->execute([':sku' => $sku, ':slug' => $slug]);
        if ($checkStmt->fetch()) {
            Response::error('A product with this sku or slug already exists.', 409);
        }

        // 4. Insert new toyu into MySQL
        $insertSql = "INSERT INTO products (category_id, name, slug, sku, brand, age_group, description, price, compare_at_price, stock_quantity, is_featured, is_active) 
        VALUES (:category_id, :name, :slug, :sku, :brand, :age_group, :description, :price, :comapre_at_price, :stock_quantity, :is_featured, :is_active)";

        $stmt = $db->prepare($insertSql);
        $stmt->execute([
            ':category_id' => $categoryId,
            ':name' => $name,
            ':slug' => $slug,
            ':sku' => $sku,
            ':brand' => $brand,
            ':age_group' => $ageGroup,
            ':description' => $description,
            ':price' => $price,
            ':compare_at_price' => $comapreAtPrice,
            ':stock_quantity' => $stockQuantity,
            ':is_featured' => $isFeatured,
            ':is_active' => $isActive
        ]);

        $newProductId = (int) $db->lastInsertId();

        // 5. Fetch fresh record
        $fetchStmt = $db->prepare("SELECT * FROM products WHERE id = :id LIMIT 1");
        $fetchStmt->execute([':id' => $newProductId]);
        $newProduct = $fetchStmt->fetch();

        Response::success($newProduct, 'Product created succesfully.', 201);
    }

    /**
     * PUT /api/v1/admin/product/{id}
     * Inventory managers & Super Admin update toy details, price, or stock levels.
     */
    public function update(string $id): void
    {
        $productId = (int) $id;
        $db = Database::getConnection();

        // 1. Verify product exists
        $fetchStmt = $db->prepare("SELECT * FROM products WHERE id = :id LIMIT 1");
        $fetchStmt->execute([':id' => $productId]);
        $existing = $fetchStmt->fetch();

        if (!$existing) {
            Response::error("Product with ID {$productId} not found.", 404);
        }

        $body = Request::getBody();


        // 2. Extract editable values (or keep existing)
        $categoryId = isset($body['category_id']) ? (int) $body['category_id'] :
            (int) $existing['category_id'];
        $name = isset($body['name']) ? trim($body['name']) : $existing['name'];
        $brand = isset($body['brand']) ? trim($body['brand']) : $existing['brand'];
        $ageGroup = $body['age_group'] ?? $existing['age_group'];
        $description = isset($body['description']) ? trim($body(['description'])) : $existing['description'];
        $price = isset($body['price']) ? (float) $body['price'] : (float) $existing['price'];
        $stockQuantity = isset($body['stock_quantity']) ? max(0, (int) $body['stock_quantity']) : (int) $existing['stock_quantity'];
        $isFeatured = isset($body['is_featured']) ? (int) $body['is_featured'] : (int) $existing['is_featured'];
        $isActive = isset($body['is_active']) ? (int) $body['is_active'] : (int) $existing['is_active'];


        // 3. Execute update statement

        $updateSql = "UPDATE products SET category_id = :category_id, name = :name, brand = :brand, 
        age_group = :age_group, description = :description, price = :price, stock_quantity = :stock_quantity, 
        is_featured = :is_featured, is_active = :is_active WHERE id = :id";

        $stmt = $db->prepare($updateSql);
        $stmt->execute([
            ':category_id' => $categoryId,
            ':name' => $name,
            ':brand' => $brand,
            ':age_group' => $ageGroup,
            ':description' => $description,
            ':price' => $price,
            ':stock_quantity' => $stockQuantity,
            ':is_featured' => $isFeatured,
            ':is_active' => $isActive,
            ':id' => $productId
        ]);


        // Fresh refreshed record
        $fetchStmt->execute([':id' => $productId]);
        $updatedProduct = $fetchStmt->fetch();

        Response::success($updatedProduct, 'Product updated succesfully.');
    }

    /**
     * DELETE /api/v1/admin/manager/{id}
     * SUPER ADMIN ONLY: Remove or revoke a manager's account.
     */
    public function deleteManager(string $id): void
    {
        $managerId = (int) $id;
        $db = Database::getConnection();

        // 1. Verify user exists and is strictly a manager (prevent deleteing admins)
        $stmt = $db->prepare("SELECT id, full_name, role FROM users WHERE id = :id LIMIt 1");
        $stmt->execute([':id' => $managerId]);
        $user = $stmt->fetch();

        if (!$user) {
            Response::error("Manager with ID {$managerId} not found.", 404);
        }

        if ($user['role'] !== 'manager') {
            Response::error("Cannot delete user with role '{$user['role']}'. only managers can be deleted here.", 403);
        }

        // 2. Delete the manager record
        $delStmt = $db->prepare("DELETE FROM users WHERE id = :id AND role = 'manager'");
        $delStmt->execute(['id' => $managerId]);

        Response::success([
            'manager_id' => $managerId
        ], "Manager '{$user['full_name']}' has been removed from staff.");
    }
}
