<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Category
{


    # 1. Get all Categories with the count of toys in each category
    public static function all(): array
    {
        $db = Database::getConnection();

        $sql = "SELECT c.id, c.name, c.slug, c.description, c.image_url, COUNT(p.id) AS total_products
        FROM categories c LEFT JOIN products p ON c.id = p.category_id AND p.is_active =1 WHERE c.is_active =1
        GROUP BY c.id ORDER BY c.name ASC";

        $stmt = $db->query($sql);
        return $stmt->fetchAll();
    }


    # 2. Find a single category URL by it's slug
    public static function findBySlug(string $slug): ?array
    {
        $db = Database::getConnection();
        $sql = "SELECT id, name, slug, description, image_url, created_at FROM categories WHERE slug = :slug AND is_active =1 LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([':slug' => $slug]);

        $category = $stmt->fetch();
        return $category ?: null;
    }
}
