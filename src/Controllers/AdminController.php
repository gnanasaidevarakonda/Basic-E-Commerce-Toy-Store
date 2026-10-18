<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\Product;
use App\Models\User;
use App\Middlewares\AuthMiddleware;
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

        // 5. Total registered customers (users)
        $totalCustomers = (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();

        // 6. Total staff managers
        $totalManagers = (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'manager'")->fetchColumn();

        Response::success([
            'metrics' => [
                'total_products'   => $totalProducts,
                'active_products'  => $activeProducts,
                'low_stock_alerts' => $lowStockCount,
                'total_categories' => $totalCategories,
                'total_customers' => $totalCustomers,
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

        $sql =  "SELECT p.id, p.category_id, p.subcategory_id, 
                       c.name AS category_name, s.name AS subcategory_name, 
                       p.name, p.slug, p.sku, p.brand, p.age_group, 
                       p.price, p.compare_at_price, p.stock_quantity, 
                       p.is_featured, p.is_active, p.created_at
                FROM products p
                INNER JOIN categories c ON p.category_id = c.id
                LEFT JOIN subcategories s ON p.subcategory_id = s.id";

        // Optional filter: view low stock items only (?stock=low)
        if (($params['stock'] ?? '') === 'low') {
            $sql .= " WHERE p.stock_quantity <= 5 AND p.is_active =1";
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
        $stmt = $db->prepare("SELECT p.*, c.name AS category_name, c.slug AS category_slug,
                                     s.name AS subcategory_name, s.slug AS subcategory_slug
                              FROM products p 
                              INNER JOIN categories c ON p.category_id = c.id 
                              LEFT JOIN subcategories s ON p.subcategory_id = s.id 
                              WHERE p.id = :id LIMIT 1");
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
        $subcategoryId = !empty($body['subcategory_id']) ? (int) $body['subcategory_id'] : null;
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
        $validAgeGroups = ['0-2', '3-5', '6-8', '9-12', '13-17', '18+'];
        if (!in_array($ageGroup, $validAgeGroups, true)) {
            $errors['age_group'] = 'Invalid age group. Allowed: 0-2, 3-5, 6-8, 9-12, 13-17, 18+.';
        }

        // Verify category exists in database
        $catStmt = $db->prepare("SELECT id FROM categories WHERE id = :id LIMIT 1");
        $catStmt->execute([':id' => $categoryId]);
        if (!$catStmt->fetch()) {
            $errors['category_id'] = 'Selected category does not exist.';
        }

        // verify subcategory belongs to this category
        if ($subcategoryId !== null) {
            $subStmt = $db->prepare("SELECT id FROM subcategories WHERE id = :id AND category_id = :cat_id AND is_active = 1 LIMIT 1");
            $subStmt->execute([':id' => $subcategoryId, ':cat_id' => $categoryId]);
            if (!$subStmt->fetch()) {
                $errors['subcategory_id'] = 'Selected subcategory does not exist or does not belong to the selected category.';
            }
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
        $insertSql = "INSERT INTO products (category_id, subcategory_id, name, slug, sku, brand, age_group, 
                                            description, price, compare_at_price, stock_quantity, 
                                            is_featured, is_active)
                      VALUES (:category_id, :subcategory_id, :name, :slug, :sku, :brand, :age_group, 
                              :description, :price, :compare_at_price, :stock_quantity, 
                              :is_featured, :is_active)";

        $stmt = $db->prepare($insertSql);
        $stmt->execute([
            ':category_id'      => $categoryId,
            ':subcategory_id' => $subcategoryId,
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
        $subcategoryId = array_key_exists('subcategory_id', $body) ? (!empty($body['subcategory_id']) ? (int) $body['subcategory_id'] : null) : ($existing['subcategory_id'] ? (int) $existing['subcategory_id'] : null);
        $name           = isset($body['name']) ? trim($body['name']) : $existing['name'];
        $brand          = isset($body['brand']) ? trim($body['brand']) : $existing['brand'];
        $ageGroup       = $body['age_group'] ?? $existing['age_group'];
        $description    = isset($body['description']) ? trim($body['description']) : $existing['description'];
        $price          = isset($body['price']) ? (float) $body['price'] : (float) $existing['price'];
        $stockQuantity  = isset($body['stock_quantity']) ? max(0, (int) $body['stock_quantity']) : (int) $existing['stock_quantity'];
        $isFeatured     = isset($body['is_featured']) ? (int) $body['is_featured'] : (int) $existing['is_featured'];
        $isActive       = isset($body['is_active']) ? (int) $body['is_active'] : (int) $existing['is_active'];


        // Validate a subcategory relationship-=-
        if ($subcategoryId !== null) {
            $subStmt = $db->prepare("SELECT id FROM subcategories WHERE id = :id AND category_id = :cat_id AND is_active = 1 LIMIT 1");
            $subStmt->execute([':id' => $subcategoryId, ':cat_id' => $categoryId]);

            if (!$subStmt->fetch()) {
                Response::error('Selected subcategory does not exist or does not belong to the selected category.', 422);
            }
        }

        // 3. Execute update statement
        $updateSql = "UPDATE products 
                      SET category_id = :category_id, subcategory_id = :subcategory_id, 
                          name = :name, brand = :brand, age_group = :age_group, 
                          description = :description, price = :price, 
                          stock_quantity = :stock_quantity, is_featured = :is_featured, 
                          is_active = :is_active 
                      WHERE id = :id";

        $stmt = $db->prepare($updateSql);
        $stmt->execute([
            ':category_id'    => $categoryId,
            ':subcategory_id' => $subcategoryId,
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

        $caller = AuthMiddleware::user();
        if (($caller['role'] ?? '') !== 'admin') {
            Response::error('Forbidden: Super Admin Privileges required.', 403);
        }
        $db = Database::getConnection();

        $stmt = $db->query("SELECT id, full_name, email, phone, role, is_active, is_online, created_at 
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
     * GET /api/v1/admin/managers/{id}
     * SUPER ADMIN only: Inspect a single manager's profile
     */
    public function showManager(string $id): void
    {
        $caller = AuthMiddleware::user();
        if (($caller['role'] ?? '') !== 'admin') {
            Response::error('Forbidden: Super Admin Privilages required.', 403);
        }

        $managerId = (int) $id;
        $db = Database::getConnection();

        $stmt = $db->prepare("SELECT id, full_name, email, phone, role, is_active, is_online, created_at, updated_at 
        FROM users WHERE id=:id AND role = 'manager' LIMIT 1");
        $stmt->execute([':id' => $managerId]);
        $manager = $stmt->fetch();

        if (!$manager) {
            Response::error("Manager with ID {$managerId} not found.", 404);
        }

        Response::success($manager, ' Manager details retrieved succesfully.');
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
    /**
     * PATCH /api/v1/admin/managers/{id}/status
     * SUPER ADMIN ONLY: Dynamic Grant / Revoke access toggle for managers.
     */
    public function toggleManagerStatus(string $id): void
    {
        $managerId = (int) $id;
        $db        = Database::getConnection();

        // 1. Fetch target user
        $stmt = $db->prepare("SELECT id, full_name, role, is_active, is_online FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $managerId]);
        $manager = $stmt->fetch();

        if (!$manager) {
            Response::error("Manager with ID {$managerId} not found.", 404);
        }

        // 2. Privilege Guard: Only allow toggling manager accounts
        if ($manager['role'] !== 'manager') {
            Response::error("Forbidden: Only manager accounts can be toggled via this endpoint.", 403);
        }

        // 3. Prevent self-modification
        $callerId = AuthMiddleware::id();
        if ($callerId && (int) $callerId === $managerId) {
            Response::error("Forbidden: You cannot modify your own account.", 403);
        }

        // 4. Invert is_active status (1 -> 0, or 0 -> 1)
        $newActive = ((int) $manager['is_active'] === 1) ? 0 : 1;
        // When revoking access (0), immediately force is_online to 0
        $newOnline = ($newActive === 0) ? 0 : (int) $manager['is_online'];

        // Each parameter is strictly unique (:active, :online, :id) to comply with native PDO
        $updateStmt = $db->prepare("UPDATE users SET is_active = :active, is_online = :online WHERE id = :id");
        $updateStmt->execute([
            ':active' => $newActive,
            ':online' => $newOnline,
            ':id'     => $managerId
        ]);

        $actionText = ($newActive === 1) ? 'granted' : 'revoked';

        Response::success([
            'manager_id' => $managerId,
            'full_name'  => $manager['full_name'],
            'is_active'  => $newActive
        ], "Manager '{$manager['full_name']}' access has been {$actionText}.");
    }
    /**
     * GET /api/v1/admin/users
     * Staff & Admin: INspect all registered uers & customers with filters.
     */
    public function listUsers(): void
    {
        $db = Database::getConnection();
        $params = Request::getQueryParams();

        // Inspect who is calling the endpoint
        $caller = AuthMiddleware::user();
        $isSuperAdmin = ($caller['role'] ?? '') === 'admin';

        // 1. Base query: intentionally omit password_hash for security
        $sql = "SELECT id, full_name, email, phone, role, is_active, created_at, updated_at FROM users WHERE 1=1";
        $bind = [];

        // 2. Strictly lock user query to customer accounts only (role = 'user')
        // Managers and staff accounts are managed exclusively via /api/v1/admin/managers

        $sql .= " AND role = 'user'";

        // 3. Filter by Active Status
        if (!empty($params['status'])) {
            $status = strtolower(trim($params['status']));
            if ($status === 'active') {
                $sql .= " AND is_active =1";
            } elseif ($status === 'inactive') {
                $sql .= " AND is_active = 0";
            }
        }

        // 4. Keyword SEarch across name email and phone
        if (!empty($params['q'])) {
            $sql .= " AND (full_name LIKE :search_name OR email LIKE :search_email OR phone LIKE :search_phone)";
            $searchTerm = '%' . trim($params['q']) . '%';
            $bind[':search_name'] = $searchTerm;
            $bind[':search_email'] = $searchTerm;
            $bind[':search_phone'] = $searchTerm;
        }

        // 5. Order newest user first
        $sql .= " ORDER BY id DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($bind);
        $users = $stmt->fetchAll();

        Response::success([
            'count' => count($users),
            'users' => $users
        ], 'Users directory retrieved successfully.');
    }

    /**
     * GET /api/v1/admin/users/{id}
     * Staff & Admin: Inspect single user/customer data
     * operational staff: blocked with 403 if trying to inspect staff or admins
     * only superadmin has privilages to check any user
     */
    public function showUser(string $id): void
    {
        $userId = (int)$id;
        $db = Database::getConnection();

        // Inspect who is calling this endpoint
        $caller = AuthMiddleware::user();
        $isSuperAdmin = ($caller['role']) === 'admin';

        $stmt = $db->prepare("SELECT id, full_name, email, phone, role, is_active, created_at, updated_at 
        FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();

        if (!$user) {
            Response::error("User with ID {$userId} not found.", 404);
        }

        // Security Guard: Operational managers are srictly forbidden from checking other staff and admins
        if (!$isSuperAdmin && $user['role'] !== 'user') {
            Response::error("Forbidden: Operational staff are only permitted to inspect customer accounts.", 403);
        }

        Response::success($user, "User details retrieved successfully.");
    }

    /**
     * PATCH /api/v1/admin/users/{id}/status
     * Staff & Super Admin: 1-click toggle between Active (1) and Deactivated (0) for customer accounts.
     * 
     * Security & RBAC Guards:
     * 1. Target Account Guard: Only customers (role = 'user') can be toggled through this endpoint.
     *    Staff/managers or admins cannot be deactivated here.
     * 2. Self-Protection: A user cannot deactivate their own account.
     */
    public function toggleUserStatus(string $id): void
    {
        $userId = (int) $id;
        $db = Database::getConnection();

        // 1. Fetch target user
        $stmt = $db->prepare("SELECT id, full_name, role, is_active FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $userId]);
        $targetUser = $stmt->fetch();

        if (!$targetUser) {
            Response::error("Customer with ID {$userId} not found.", 404);
        }

        // 2. Strict Privilege Guard: Only allow toggling customer accounts
        if ($targetUser['role'] !== 'user') {
            Response::error("Forbidden: Only customer accounts can be deactivated via this endpoint.", 403);
        }

        // 3. Prevent self-deactivation
        $callerId = AuthMiddleware::id();
        if ($callerId && $callerId === $userId) {
            Response::error("Forbidden: You cannot modify the status of your own account.", 403);
        }

        // 4. Invert is_active status (1 -> 0, or 0 -> 1)
        $newStatus = ((int) $targetUser['is_active'] === 1) ? 0 : 1;

        $updateStmt = $db->prepare("UPDATE users SET is_active = :status WHERE id = :id");
        $updateStmt->execute([
            ':status' => $newStatus,
            ':id'     => $userId
        ]);

        $statusLabel = ($newStatus === 1) ? 'activated' : 'deactivated';

        Response::success([
            'user_id'   => $userId,
            'full_name' => $targetUser['full_name'],
            'is_active' => $newStatus
        ], "Customer account has been {$statusLabel} successfully.");
    }

    /**
     * BACKOFFICE SUBCATEGOIRY MANAGEMNET----
     * 
     * GET /api/v1/admin/subcategories
     * Staff & Admin: List all subcatgories with parent details and inventory count
     * 
     */
    public function listSubcategories(): void
    {
        $db = Database::getConnection();
        $sql = "SELECT s.*, c.name AS category_name, c.slug AS category_slug, 
                       COUNT(p.id) AS total_products
                FROM subcategories s
                INNER JOIN categories c ON s.category_id = c.id
                LEFT JOIN products p ON s.id = p.subcategory_id
                GROUP BY s.id
                ORDER BY c.name ASC, s.name ASC";

        $stmt = $db->query($sql);
        $subcategories = $stmt->fetchAll();

        Response::success([
            'count' => count($subcategories),
            'subcategories' => $subcategories,
        ], 'Admin subcategories retrieved successfully.');
    }

    /**
     * POST /api/v1/admin/subcategories
     * Staff & Admin: Create a new subcategory.
     */
    public function createSubcategory(): void
    {
        $body = Request::getBody();
        $db = Database::getConnection();

        $categoryId = (int) ($body['category_id'] ?? 0);
        $name = trim($body['name'] ?? '');
        $description = trim($body['description'] ?? '');
        $isActive = isset($body['is_active']) ? (int) $body['is_active'] : 1;

        // Auto-generate URL slug from subcategory name if not given
        $slug = trim($body['slug'] ?? '');
        if (empty($slug)) {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
        }

        $errors = [];
        if (empty($name)) {
            $errors['name'] = 'Subcategory name is required';
        }
        if ($categoryId <= 0) {
            $errors['category_id'] = 'Valid parent category ID is required.';
        }

        // Verify parent category actually exists
        $catStmt = $db->prepare("SELECT id FROM categories WHERE id = :id LIMIT 1");
        $catStmt->execute([':id' => $categoryId]);

        if (!$catStmt->fetch()) {
            $errors['category_id'] = "Parent category does not exist.";
        }

        //Verify Slug Uniqueness
        $slugStmt = $db->prepare("SELECT id FROM subcategories WHERE slug = :slug LIMIT 1");
        $slugStmt->execute([':slug' => $slug]);
        if ($slugStmt->fetch()) {
            $errors['slug'] = "A subcategory with this slug already exists.";
        }

        if (!empty($errors)) {
            Response::error('Validation failed.', 422, $errors);
        }

        $insertSql = "INSERT INTO subcategories (category_id, name, slug, description, is_active)
                      VALUES (:category_id, :name, :slug, :description, :is_active)";
        $stmt = $db->prepare($insertSql);
        $stmt->execute([
            ':category_id' => $categoryId,
            ':name' => $name,
            ':slug' => $slug,
            ':description' => $description,
            ':is_active' => $isActive
        ]);

        $newId = (int) $db->lastInsertId();
        $fetchStmt = $db->prepare("SELECT s.*, c.name AS category_name 
                                  FROM subcategories s 
                                  INNER JOIN categories c ON s.category_id = c.id 
                                  WHERE s.id = :id LIMIT 1");
        $fetchStmt->execute([':id' => $newId]);

        Response::success($fetchStmt->fetch(), 'Subcategory created successfully.', 201);
    }

    /**
     * DELETE /api/v1/admin/subcategories/{id}
     * Super Admin only: Remove a subcategory
     */
    public function deleteSubcategory(string $id): void
    {
        $subId = (int) $id;
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id, name FROM subcategories WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $subId]);
        $sub = $stmt->fetch();

        if (!$sub) {
            Response::error('Subcategory with ID {$subId} not found.', 404);
        }

        $delStmt = $db->prepare("DELETE FROM subcategories WHERE id = :id");
        $delStmt->execute([':id' => $subId]);

        Response::success([
            'deleted_id' => $subId,
            'deleted_name' => $sub['name']
        ], 'Subcategory deleted successfully.');
    }

    /**
     * POST /api/v1/admin/categories
     * Staff & Admin: Create a new main catalog category.
     */
    public function createCategory(): void
    {
        $body = Request::getBody();

        $name = trim($body['name'] ?? '');
        $description = trim($body['description'] ?? '');
        $imageUrl = trim($body['image_url'] ?? '');

        // Auto-generate URL slug from category name (e.g. "Action Figures" -> "action-figures")
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));

        $errors = [];
        if (empty($name)) {
            $errors['name'] = 'Category name is required.';
        }

        if (empty($slug)) {
            $errors['name'] = 'Valid category name is required to generate slug.';
        }

        $db = Database::getConnection();

        // Ensure category slug uniqueness in database
        $checkStmt = $db->prepare("SELECT id FROM categories WHERE slug = :slug LIMIT 1");
        $checkStmt->execute([':slug' => $slug]);
        if ($checkStmt->fetch()) {
            $errors['name'] = 'A category with this name or slug already exists.';
        }

        if (!empty($errors)) {
            Response::error('Validation failed.', 422, $errors);
        }

        $insertSql = "INSERT INTO categories (name, slug, description, image_url, is_active)
                      VALUES (:name, :slug, :description, :image_url, 1)";
        $insertStmt = $db->prepare($insertSql);
        $insertStmt->execute([
            ':name'        => $name,
            ':slug'        => $slug,
            ':description' => !empty($description) ? $description : null,
            ':image_url'   => !empty($imageUrl) ? $imageUrl : null,
        ]);

        $newId = (int) $db->lastInsertId();
        $fetchStmt = $db->prepare("SELECT * FROM categories WHERE id = :id LIMIT 1");
        $fetchStmt->execute([':id' => $newId]);

        Response::success($fetchStmt->fetch(), 'Category created successfully.', 201);
    }

    /**
     * POST /api/v1/admin/upload
     * Staff & Admin: Secure multipart image upload for catalog & categories.
     * Enforce magic-byte MIME validation, 10MB file limit, and unguessable random
     * filenames.
     */
    public function uploadImage(): void
    {
        // 1. Check if a file was provided in the multipart form data
        if (empty($_FILES['image'])) {
            Response::error('No Image file Provided in request.', 400);
        }

        $file = $_FILES['image'];

        // Decode native PHP upload error codes for transparent debugging
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessage = match ($file['error']) {
                UPLOAD_ERR_INI_SIZE => 'File size exceeds server upload limit (10mb).',
                UPLOAD_ERR_FORM_SIZE => 'File size exceeds form MAX_FILE_SIZE limit.',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded due to network issue.',
                UPLOAD_ERR_NO_FILE => 'No file was uploded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Server configuration error: Missing temporary upload folder.',
                UPLOAD_ERR_CANT_WRITE => 'Server failed to write file to disk. Check directory permissions.',
                default => 'An Unknown file upload error occured.'
            };

            Response::error($errorMessage, 400);
        }

        // Maximum upload limit: 10MB (10 * 1024 * 1024 bytes)
        $maxBytes = 10 * 1024 * 1024;
        if ($file['size'] > $maxBytes) {
            Response::error('File size exceeds the 10MB limit.', 422);
        }

        // Whitelist allowed MIME types and enforce verified file extensions
        $allowedMimes = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif'
        ];

        // Inspect file magic bytes rather than trusting client-provided extension
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);

        if (!isset($allowedMimes[$mime])) {
            Response::error("Invalid file format ({$mime}). Only JPG, PNG, WEBP, and GIF images are allowed.", 422);
        }

        $ext = $allowedMimes[$mime];
        $uploadDir = __DIR__ . '/../../public/uploads/categories';

        // Create folder recursively if it does not yet exist
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            Response::error('Server could not create upload directory.', 500);
        }

        // Generate unguessable random filename to prevent overwrites or traversal attacks
        $fileName = 'cat_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $targetPath = $uploadDir . '/' . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            Response::error('Failed to save uploaded file to disk.', 500);
        }

        // Return relative public path accessible via Nginx
        $publicUrl = '/uploads/categories/' . $fileName;

        Response::success([
            'url'      => $publicUrl,
            'filename' => $fileName,
            'mime' => $mime,
            'size'     => $file['size']
        ], 'Image uploaded successfully.', 200);
    }
}
