DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments`(
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `gateway` VARCHAR(50) NOT NULL,
    `transaction_id` VARCHAR(150) NOT NULL,
    `amount` DECIMAL(10, 2) NOT NULL,
    `currency` VARCHAR(10) NOT NULL DEFAULT 'INR',
    `status` ENUM('initiated', 'completed', 'failed', 'refunded') NOT NULL DEFAULT 'initiated',
    `raw_response` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_payments_order`(`order_id`),
    INDEX `idx_payments_trans`(`transaction_id`),
    CONSTRAINT `fk_payment_order` FOREIGN KEY(`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;