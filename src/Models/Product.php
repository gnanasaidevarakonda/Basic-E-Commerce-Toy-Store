<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Product
{
    public static function paginate(array $filters = []): array
    {
        $db = Database::getConnection();

        // Base query with joins for category and primary thumbnail image
        $baseSql = "FROM products p INNER JOIN categories c ON p.category_id  = c.id LEFT JOIN
        product_images pi ON p.id=pi.product_id AND pi.is_primary =1 WHERE p.is_active =1";

        $params = [];

        // 1. Filter by category slug
        if (!empty($filters['category'])) {
            $baseSql .= " AND c.slug = :category";
            $params[':category'] = $filters['category'];
        }

        // 2. Filter by Brand 
        if (!empty($filters['brand'])) {
            $baseSql .= "AND p.brand = :brand";
            $params['brand'] = $filters['brand'];
        }

        // 3. Filter by Age Group
        if (!empty($filters['age'])) {
            $baseSql .= "AND p.age_group = :age";
            $params['age'] = $filters['age'];
        }

        // 4. Filter by Minimum Price
        if (!empty($filters['min_price']) && is_numeric($filters['min_price'])) {
            $baseSql .= "AND p.price >= :min_price";
            $params[':min_price'] = (float) $filters['min_price'];
        }

        // 5. Filter by Maximum price
        if (!empty($filters['max_price']) && is_numeric($filters['max_price'])) {
            $baseSql .= "AND p.price <= :max_price";
            $params[':max_price'] = (float) $filters['max_price'];
        }

        // 6. Search in Toy Name or Description
        if (!empty($filters['q'])) {
            $baseSql .= "AND (p.name LIKE :search OR p.description LIKE :search)";
            $params['search'] = '%' . trim($filters['q']) . '%';
        }

        // 7. Filter Featured toys only
        if (isset($filters['featured']) && $filters['featured'] !== "") {
            $baseSql .= "AND p.is_featured = :featured";
            $params['featured'] = (int) $filters['featured'];
        }




        // STEP A: Calculate Total count of matching toys
        $countStmt = $db->prepare("SELECT COUNT(DISTINCT p.id) AS total {$baseSql}");
        $countStmt->execute($params);
        $totalItems = (int) $countStmt->fetchColumn();


        // STEP B: Sorting...
        $sortOption = $filters['sort'] ?? 'newest';
        $orderBy = match ($sortOption) {
            'price_asc' => 'ORDER BY p.price ASC',
            'price_dsc' => 'ORDER BY p.price DESC',
            'name_asc' => 'ORDER BY p.name ASC',
            default => 'ORDER BY p.created_at DESC',
        };


        // STEP C: pagination Calcculations.....
        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = max(1, min(50, (int) ($filters['limit'] ?? 12)));
        $offset = ($page - 1) * $limit;
        $totalPages = (int) ceil($totalItems / $limit);



        // STEP D: Fetch the products for current page

        $selectSql = "SELECT p.id, p.name, p.slug, p.sku, p.brand, p.age_group,
        p.price, p.compare_at_price, p.stock_quantity, p.is_featured, c.name AS category_name,
        c.slug AS category_slug, COALESCE(MAX(pi.image_url), 'https://placehold.co/600x400?text=No+Image') AS primary_image {$baseSql}
        GROUP BY p.id {$orderBy} LIMIT {$limit} OFFSET {$offset}";

        $stmt = $db->prepare($selectSql);
        $stmt->execute($params);
        $items = $stmt->fetchAll();

        // Return a complete pagination envelope
        return [
            'items' => $items,
            'pagination' => [
                'current-page' => $page,
                'per-page' => $limit,
                'total-items' => $totalItems,
                'has-next' => $page < $totalPages,
                'has-prev' => $page > 1,
            ]
        ];
    }


    // 2. Find a single product by slug with it's complete photo gallery
    public static function findBySlug(string $slug): ?array
    {
        $db = Database::getConnection();

        // 1. Fetch product details
        $sql = "SELECT p.id, p.category_id, c.name AS category_name, c.slug AS category_slug,
        p.name, p.slug, p.sku, p.brand, p.age_group, p.description, p.price, p.compare_at_price, p.stock_quantity, p.is_featured, p.created_at 
        FROM products p INNER JOIN categories c ON p.category_id =c.id WHERE p.slug=:slug AND p.is_active =1 LIMIT 1";

        $stmt = $db->prepare($sql);
        $stmt->execute([':slug' => $slug]);
        $product = $stmt->fetch();

        if (!$product) {
            return null;
        }


        // 2. Fetch all gallery images for this product
        $imgsql = "SELECT id, image_url, is_primary, sort_order 
        FROM product_images WHERE product_id = :product_id ORDER BY sort_order ASC, id ASC";

        $imgStmt = $db->prepare($imgsql);
        $imgStmt->execute([':product_id' => $product['id']]);
        $product['images'] = $imgStmt->fetchAll();

        return $product;
    }
}
