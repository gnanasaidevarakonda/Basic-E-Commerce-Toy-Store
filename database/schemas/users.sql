DROP TABLE IF EXISTS users;
CREATE TABLE users(
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(30) DEFAULT NULL,
    `role` ENUM("user", "manager", "admin") NOT NULL DEFAULT "user",
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `is_online` TINYINT(1) NOT NUll DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_users_email`(`email`),
    INDEX `idx_users_role`(`role`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;