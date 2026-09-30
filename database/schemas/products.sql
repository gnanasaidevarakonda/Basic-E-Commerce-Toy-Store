DROP TABLE IF EXISTS products;
CREATE TABLE products(
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT NOT NULL,
    `name` VARCHAR(200) NOT NULL,
    `slug` VARCHAR(200) NOT NULL UNIQUE,
    `sku` VARCHAR(100) NOT NULL UNIQUE,
    `brand` VARCHAR(100) NOT NULL,
    `age_group` VARCHAR(30) NOT NULL,
    `description` TEXT NOT NULL,
    `price` DECIMAL(10, 2) DEFAULT NULL,
    `compare_at_price` DECIMAL(10, 2) DEFAULT NULL,
    `stock_quantity` INT NOT NULL DEFAULT 0,
    `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_products_slug`(`slug`),
    INDEX `idx_products_category`(`category_id`),
    INDEX `idx_products_brand`(`brand`),
    INDEX `idx_products_price`(`price`),
    INDEX `idx_products_age`(`age_group`),
    CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE RESTRICT
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;