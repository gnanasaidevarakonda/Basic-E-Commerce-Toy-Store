-- ====================================================================
-- ToyVerse E-Commerce Master Database Initialization & Seed Script
-- Automatically executed by Docker on fresh container startup
-- ====================================================================
SET FOREIGN_KEY_CHECKS = 0;
-- 1. USERS TABLE
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(30) DEFAULT NULL,
    `role` ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_users_email` (`email`),
    INDEX `idx_users_role` (`role`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- 2. CATEGORIES TABLE
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `description` TEXT DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_categories_slug` (`slug`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- 3. PRODUCTS TABLE
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `slug` VARCHAR(150) NOT NULL UNIQUE,
    `sku` VARCHAR(50) NOT NULL UNIQUE,
    `brand` VARCHAR(100) DEFAULT NULL,
    `age_group` ENUM('0-2', '3-5', '6-8', '9-12', '12+') NOT NULL DEFAULT '3-5',
    `description` TEXT DEFAULT NULL,
    `price` DECIMAL(10, 2) NOT NULL,
    `stock_quantity` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_products_category` (`category_id`),
    INDEX `idx_products_slug` (`slug`),
    INDEX `idx_products_age` (`age_group`),
    INDEX `idx_products_price` (`price`),
    FULLTEXT `ft_products_name_desc` (`name`, `description`),
    CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- 4. PRODUCT IMAGES TABLE
DROP TABLE IF EXISTS `product_images`;
CREATE TABLE `product_images` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT NOT NULL,
    `image_url` VARCHAR(255) NOT NULL,
    `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_product_images_product` (`product_id`),
    CONSTRAINT `fk_product_images_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- 5. ORDERS & ORDER ITEMS TABLES
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `order_number` VARCHAR(50) NOT NULL UNIQUE,
    `status` ENUM(
        'pending',
        'paid',
        'processing',
        'shipped',
        'delivered',
        'cancelled'
    ) NOT NULL DEFAULT 'pending',
    `total_amount` DECIMAL(10, 2) NOT NULL,
    `shipping_address` TEXT NOT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_orders_user` (`user_id`),
    INDEX `idx_orders_status` (`status`),
    CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
CREATE TABLE `order_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `unit_price` DECIMAL(10, 2) NOT NULL,
    `subtotal` DECIMAL(10, 2) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_order_items_order` (`order_id`),
    INDEX `idx_order_items_product` (`product_id`),
    CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- 6. PAYMENTS TABLE
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `transaction_id` VARCHAR(100) NOT NULL UNIQUE,
    `payment_method` ENUM('stripe', 'paypal', 'cod') NOT NULL DEFAULT 'stripe',
    `amount` DECIMAL(10, 2) NOT NULL,
    `currency` VARCHAR(10) NOT NULL DEFAULT 'USD',
    `status` ENUM('pending', 'completed', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
    `payment_details` JSON DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_payments_order` (`order_id`),
    CONSTRAINT `fk_payments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ====================================================================
-- SEED DATA
-- ====================================================================
-- Users Seed (Admin: admin123 | Customer: customer123)
INSERT INTO `users` (
        `id`,
        `full_name`,
        `email`,
        `password_hash`,
        `phone`,
        `role`,
        `is_active`
    )
VALUES (
        1,
        'Store-Admin',
        'admin@toystore.com',
        '$2y$10$Nfw9qh1lliq28KuFjI5.a.aNUff4iIld9voex790xnjADwNFsEN3m',
        '+919502398946',
        'admin',
        1
    ),
    (
        2,
        'Jane Customer',
        'customer@toystore.com',
        '$2y$10$GKMHp2VZgHUNwa8Hgo7n6en3Jtdpi3Kfe.lhQ44CvSBMVqgTtVRhG',
        '+1-555-0102',
        'user',
        1
    ) ON DUPLICATE KEY
UPDATE `id` = `id`;
-- Categories Seed
INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `is_active`)
VALUES (
        1,
        'Action Figures',
        'action-figures',
        'Superheroes, anime characters, and poseable figures.',
        1
    ),
    (
        2,
        'Building Blocks',
        'building-blocks',
        'Lego-compatible sets, architectural bricks, and STEM blocks.',
        1
    ),
    (
        3,
        'Board Games & Puzzles',
        'board-games',
        'Strategy board games, family puzzles, and brain teasers.',
        1
    ),
    (
        4,
        'Educational & STEM',
        'educational-stem',
        'Robotics, science kits, and early-childhood learning tools.',
        1
    ),
    (
        5,
        'Outdoor & Sports',
        'outdoor-sports',
        'Scooters, blasters, sports balls, and active outdoor toys.',
        1
    ),
    (
        6,
        'Dolls & Plushies',
        'dolls-plushies',
        'Soft cuddly stuffed animals, fashion dolls, and playsets.',
        1
    ) ON DUPLICATE KEY
UPDATE `id` = `id`;
-- Products Seed
INSERT INTO `products` (
        `id`,
        `category_id`,
        `name`,
        `slug`,
        `sku`,
        `brand`,
        `age_group`,
        `description`,
        `price`,
        `stock_quantity`,
        `is_active`
    )
VALUES (
        1,
        1,
        'CyberMech Prime Action Figure',
        'cybermech-prime',
        'TOY-ACT-001',
        'MechaForce',
        '6-8',
        'Highly articulated 10-inch futuristic warrior robot with plasma blaster accessory and LED eyes.',
        29.99,
        45,
        1
    ),
    (
        2,
        1,
        'Shadow Ninja Stealth Master',
        'shadow-ninja-stealth',
        'TOY-ACT-002',
        'ShinobiClan',
        '9-12',
        'Poseable ninja action figure with dual katana blades, grappling hook, and removable mask.',
        22.50,
        30,
        1
    ),
    (
        3,
        2,
        'Galactic Explorer Starship Bricks',
        'galactic-explorer-bricks',
        'TOY-BLD-001',
        'BrickForge',
        '9-12',
        '1,024 piece deep-space exploration cruiser with opening cockpit, crew quarters, and landing gear.',
        79.99,
        20,
        1
    ),
    (
        4,
        2,
        'Medieval Castle Fortress 800pc',
        'medieval-castle-fortress',
        'TOY-BLD-002',
        'CastleCraft',
        '6-8',
        'Classic brick fortress featuring working drawbridge, dungeon trapdoor, and 4 knight mini-figures.',
        59.99,
        15,
        1
    ),
    (
        5,
        3,
        'Island Colonizer Strategy Game',
        'island-colonizer-game',
        'TOY-BRD-001',
        'HexPlay',
        '12+',
        'Award-winning tabletop strategy game of trade, resource management, and settlement expansion.',
        44.99,
        50,
        1
    ),
    (
        6,
        3,
        'Mythic Dungeon Explorer RPG',
        'mythic-dungeon-explorer',
        'TOY-BRD-002',
        'DiceQuest',
        '12+',
        'Cooperative dungeon crawl board game featuring modular tiles, custom dice, and 6 hero classes.',
        49.99,
        25,
        1
    ),
    (
        7,
        4,
        'Solar Rover DIY Science Lab',
        'solar-rover-diy',
        'TOY-STM-001',
        'EcoTech',
        '6-8',
        'Build a real functioning solar-powered planetary rover. Teaches clean energy and mechanical gears.',
        19.99,
        60,
        1
    ),
    (
        8,
        4,
        'Programmable Spider Bot Kit',
        'programmable-spider-bot',
        'TOY-STM-002',
        'RoboLearn',
        '9-12',
        'Eight-legged bionic robotic spider programmable via smartphone Bluetooth with ultrasonic obstacle detection.',
        64.99,
        18,
        1
    ),
    (
        9,
        5,
        'Rapid-Fire Cyclone Dart Blaster',
        'rapid-fire-cyclone-blaster',
        'TOY-OUT-001',
        'BlastX',
        '6-8',
        'Motorized dart blaster with 24-round revolving drum, tactical stock, and 50 suction darts.',
        34.99,
        40,
        1
    ),
    (
        10,
        5,
        'All-Terrain Pro Stunt Scooter',
        'all-terrain-stunt-scooter',
        'TOY-OUT-002',
        'GlideAir',
        '9-12',
        'Heavy-duty aircraft-grade aluminum trick scooter with 110mm polyurethane wheels and ABEC-9 bearings.',
        89.99,
        12,
        1
    ),
    (
        11,
        6,
        'Aurora Starry Eyes Magic Doll',
        'aurora-magic-doll',
        'TOY-DOL-001',
        'DreamToys',
        '3-5',
        '14-inch fairytale princess doll with shimmering LED gown, brushable hair, and magical wand chime sounds.',
        32.50,
        35,
        1
    ),
    (
        12,
        6,
        'Fluffy Boba Bear Jumbo Plush',
        'fluffy-boba-bear-plush',
        'TOY-DOL-002',
        'CuddleClub',
        '0-2',
        'Ultra-soft hypoallergenic 18-inch teddy bear holding a miniature boba milk tea plush cup.',
        24.99,
        75,
        1
    ) ON DUPLICATE KEY
UPDATE `id` = `id`;
-- Product Images Seed
INSERT INTO `product_images` (
        `product_id`,
        `image_url`,
        `is_primary`,
        `sort_order`
    )
VALUES (
        1,
        'https://images.unsplash.com/photo-1566576912321-d58ddd7a6088?auto=format&fit=crop&w=800&q=80',
        1,
        0
    ),
    (
        2,
        'https://images.unsplash.com/photo-1607604276583-eef5d076aa5f?auto=format&fit=crop&w=800&q=80',
        1,
        0
    ),
    (
        3,
        'https://images.unsplash.com/photo-1585366119957-e9730b6d0f60?auto=format&fit=crop&w=800&q=80',
        1,
        0
    ),
    (
        4,
        'https://images.unsplash.com/photo-1518709268805-4e9042af9f23?auto=format&fit=crop&w=800&q=80',
        1,
        0
    ),
    (
        5,
        'https://images.unsplash.com/photo-1610890716171-6b1bb98ffd09?auto=format&fit=crop&w=800&q=80',
        1,
        0
    ),
    (
        6,
        'https://images.unsplash.com/photo-1632516643720-e7f5d7d6ecc9?auto=format&fit=crop&w=800&q=80',
        1,
        0
    ),
    (
        7,
        'https://images.unsplash.com/photo-1581092160607-ee22621dd758?auto=format&fit=crop&w=800&q=80',
        1,
        0
    ),
    (
        8,
        'https://images.unsplash.com/photo-1485827404703-89b55fcc595e?auto=format&fit=crop&w=800&q=80',
        1,
        0
    ),
    (
        9,
        'https://images.unsplash.com/photo-1596461404969-9ae70f2830c1?auto=format&fit=crop&w=800&q=80',
        1,
        0
    ),
    (
        10,
        'https://images.unsplash.com/photo-1520045892732-304bc3ac5d8e?auto=format&fit=crop&w=800&q=80',
        1,
        0
    ),
    (
        11,
        'https://images.unsplash.com/photo-1558877385-81a1c7e67d72?auto=format&fit=crop&w=800&q=80',
        1,
        0
    ),
    (
        12,
        'https://images.unsplash.com/photo-1559454403-b8fb88521f11?auto=format&fit=crop&w=800&q=80',
        1,
        0
    );
SET FOREIGN_KEY_CHECKS = 1;