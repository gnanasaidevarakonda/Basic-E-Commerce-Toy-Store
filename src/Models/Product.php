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
        $baseSql = "FROM products p INNER JOIN categories c ON p.category_id = c.id LEFT JOIN subcategories s ON 
        p.subcategory_id = s.id LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1 WHERE 
        p.is_active = 1";

        $params = [];

        // 1. Filter by category slug
        if (!empty($filters['category'])) {
            $baseSql .= " AND c.slug = :category";
            $params[':category'] = $filters['category'];
        }

        // 1.5 filter by subcategorie slug
        if (!empty($filters['subcategory'])) {
            $baseSql .= " AND s.slug = :subcategory";
            $params[':subcategory'] = $filters['subcategory'];
        }

        // 2. Filter by Brand 
        if (!empty($filters['brand'])) {
            $baseSql .= " AND p.brand = :brand";
            $params[':brand'] = $filters['brand'];
        }

        // 3. Filter by Age Group
        if (!empty($filters['age'])) {
            $ageQuery = trim($filters['age']);
            if (is_numeric($ageQuery)) {
                $numAge = (int) $ageQuery;
                if ($numAge >= 0 && $numAge <= 2) {
                    $baseSql .= " AND p.age_group = '0-2'";
                } elseif ($numAge >= 3 && $numAge <= 5) {
                    $baseSql .= " AND p.age_group = '3-5'";
                } elseif ($numAge >= 6 && $numAge <= 8) {
                    $baseSql .= " AND p.age_group = '6-8'";
                } elseif ($numAge >= 9 && $numAge <= 12) {
                    $baseSql .= " AND p.age_group = '9-12'";
                } elseif ($numAge >= 13 && $numAge <= 17) {
                    $baseSql .= " AND p.age_group = '13-17'";
                } elseif ($numAge >= 18) {
                    $baseSql .= " AND p.age_group = '18+'";
                } else {
                    $baseSql .= " AND p.age_group = :age";
                    $params[':age'] = $ageQuery;
                }
            }
        }

        // 4. Filter by Minimum Price
        if (!empty($filters['min_price']) && is_numeric($filters['min_price'])) {
            $baseSql .= " AND p.price >= :min_price";
            $params[':min_price'] = (float) $filters['min_price'];
        }

        // 5. Filter by Maximum price
        if (!empty($filters['max_price']) && is_numeric($filters['max_price'])) {
            $baseSql .= " AND p.price <= :max_price";
            $params[':max_price'] = (float) $filters['max_price'];
        }

        // 6. Search in Toy Name or Description or Age Bracket
        if (!empty($filters['q'])) {
            $q = trim($filters['q']);
            $searchTerm = '%' . $q . '%';

            // If Someone searches for an age number (eg: "10"), match toys in that age bracket
            if (is_numeric($q)) {
                $numAge = (int) $q;
                $matchedAge = null;
                if ($numAge >= 0 && $numAge <= 2) $matchedAge = '0-2';
                elseif ($numAge >= 3 && $numAge <= 5) $matchedAge = '3-5';
                elseif ($numAge >= 6 && $numAge <= 8) $matchedAge = '6-8';
                elseif ($numAge >= 9 && $numAge <= 12) $matchedAge = '9-12';
                elseif ($numAge >= 13 && $numAge <= 17) $matchedAge = '13-17';
                elseif ($numAge >= 18) $matchedAge = '18+';

                if ($matchedAge) {
                    $baseSql .= " AND (p.name LIKE :search_name OR p.description LIKE :search_desc OR p.age_group = :search_age)";
                    $params[':search_age'] = $matchedAge;
                } else {
                    $baseSql .= " AND (p.name LIKE :search_name OR p.description LIKE :search_desc)";
                }

                $params[':search_name'] = $searchTerm;
                $params[':search_desc'] = $searchTerm;
            }
        }

        // 7. Filter Featured toys only
        if (isset($filters['featured']) && $filters['featured'] !== "") {
            $baseSql .= " AND p.is_featured = :featured";
            $params[':featured'] = (int) $filters['featured'];
        }




        // STEP A: Calculate Total count of matching toys
        $countStmt = $db->prepare("SELECT COUNT(DISTINCT p.id) AS total {$baseSql}");
        $countStmt->execute($params);
        $totalItems = (int) $countStmt->fetchColumn();


        // STEP B: Sorting...
        $sortOption = $filters['sort'] ?? 'newest';
        $orderBy = match ($sortOption) {
            'price_asc' => 'ORDER BY p.price ASC',
            'price_dsc', 'price_desc' => 'ORDER BY p.price DESC',
            'name_asc' => 'ORDER BY p.name ASC',
            'name_desc' => 'ORDER BY p.name DESC',
            default => 'ORDER BY p.created_at DESC',
        };


        // STEP C: pagination Calcculations.....
        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = max(1, min(50, (int) ($filters['limit'] ?? 12)));
        $offset = ($page - 1) * $limit;
        $totalPages = (int) ceil($totalItems / $limit);



        // STEP D: Fetch the products for current page

        $selectSql = "SELECT p.id, p.category_id, p.subcategory_id, p.name, p.slug, p.sku, 
                             p.brand, p.age_group, p.price, p.compare_at_price, p.stock_quantity, 
                             p.is_featured, c.name AS category_name, c.slug AS category_slug,
                             s.name AS subcategory_name, s.slug AS subcategory_slug,
                             COALESCE(MAX(pi.image_url), 'https://placehold.co/600x400?text=No+Image') AS primary_image 
                      {$baseSql}
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
        $sql = "SELECT p.id, p.category_id, p.subcategory_id,
                       c.name AS category_name, c.slug AS category_slug,
                       s.name AS subcategory_name, s.slug AS subcategory_slug,
                       p.name, p.slug, p.sku, p.brand, p.age_group, p.description, 
                       p.price, p.compare_at_price, p.stock_quantity, p.is_featured, p.created_at 
                FROM products p 
                INNER JOIN categories c ON p.category_id = c.id 
                LEFT JOIN subcategories s ON p.subcategory_id = s.id
                WHERE p.slug = :slug AND p.is_active = 1 
                LIMIT 1";

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
