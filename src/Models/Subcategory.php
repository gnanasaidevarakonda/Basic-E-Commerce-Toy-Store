<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Subcategory
{
    /**
     * Fetch allacive subcategories along with thier parent catgory name & product count
     */
    public static function all(): array
    {
        $db = Database::getConnection();

        $sql = "SELECT s.id, s.category_id, s.name, s.slug, s.description, s.image_url, c.name AS category_name, c.slug AS category_slug, COUNT(p.id) 
        AS total_products FROM subcategories s INNER JOIN categories c ON s.category_id = c.id LEFT JOIN products p ON s.id = p.subcategory_id 
        AND p.is_active =1 WHERE s.is_active =1 AND c.is_active = 1 GROUP BY s.id ORDER BY c.name ASC, 
        s.name ASC";

        $stmt = $db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Fetch all subcategories belongiong to a specific parent category slug.
     * Example: /api/v1/categories/building-blocks/subcategories
     */

    public static function findByCategorySlug(string $categorySlug): array
    {
        $db = Database::getConnection();

        $sql = "SELECT s.id, s.category_id, s.name, s.slug, s.description, s.image_url, 
        COUNT(p.id) AS total_products FROM subcategories s INNER JOIN categories c ON s.category_id = c.id LEFT JOIN 
        products p ON s.id = p.subcategory_id AND p.is_active =1 WHERE c.slug = :category_slug AND s.is_active =1 AND c.is_active = 1 
        GROUP BY s.id ORDER BY s.name ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute([':category_slug' => $categorySlug]);
        return $stmt->fetchAll();
    }

    // Find a single subcategory by it's slug.
    public static function findBySlug(string $slug): ?array
    {
        $db = Database::getConnection();
        $sql = "SELECT s.id, s.category_id, s.name, s.slug, s.description, s.image_url, s.created_at,
                       c.name AS category_name, c.slug AS category_slug
                FROM subcategories s
                INNER JOIN categories c ON s.category_id = c.id
                WHERE s.slug = :slug AND s.is_active = 1
                LIMIT 1";

        $stmt = $db->prepare($sql);
        $stmt->execute([':slug' => $slug]);
        $result = $stmt->fetch();

        return $result ?: null;
    }
}
