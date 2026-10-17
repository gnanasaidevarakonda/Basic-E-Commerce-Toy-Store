DROP TABLE IF EXISTS `subcategories`;
CREATE TABLE `subcategories`(
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `description` TEXT DEFAULT NULL,
    `image_url` VARCHAR(255) DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_subcategories_category`(`category_id`),
    INDEX `idx_subcategories_slug`(`slug`),
    INDEX `idx_subcategories_active`(`is_active`),
    CONSTRAINT `fk_subcategories_category` FOREIGN KEY(`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;