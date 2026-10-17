DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
-- 1. Orders table (invoice header)
CREATE TABLE orders(
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_number` VARCHAR(50) NOT NULL UNIQUE,
    `user_id` INT DEFAULT NULL,
    `customer_name` VARCHAR(100) NOT NULL,
    `customer_email` VARCHAR(150) NOT NULL,
    `customer_phone` VARCHAR(30) NOT NULL,
    `shipping_address` TEXT NOT NULL,
    `city` VARCHAR(100) NOT NULL,
    `postal_code` VARCHAR(20) NOT NULL,
    `subtotal` DECIMAL(10, 2) NOT NULL,
    `shipping_fee` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `total_amount` DECIMAL(10, 2) NOT NULL,
    `status` ENUM(
        'pending',
        'paid',
        'processing',
        'shipped',
        'delivered',
        'cancelled'
    ) NOT NULL DEFAULT 'pending',
    `payment_status` ENUM('unpaid', 'paid', 'failed', 'refunded') NOT NULL DEFAULT 'unpaid',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_order_name`(`order_number`),
    INDEX `idx_order_user`(`user_id`),
    INDEX `idx_order_status`(`status`),
    INDEX `idx_order_email`(`customer_email`),
    CONSTRAINT `fk_orders_user` FOREIGN KEY(`user_id`) REFERENCES `users`(`id`) ON DELETE
    SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- 2. Orders items table (line items inside each order)
CREATE TABLE `order_items`(
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `product_name` VARCHAR(200) NOT NULL,
    `unit_price` DECIMAL(10, 2) NOT NULL,
    `quantity` INT NOT NULL,
    `total_price` DECIMAL(10, 2) NOT NULL,
    CONSTRAINT `fk_items_order` FOREIGN KEY(`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_items_product` FOREIGN KEY(`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;