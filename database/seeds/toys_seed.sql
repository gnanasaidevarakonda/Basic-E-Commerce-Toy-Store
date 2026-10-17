-- Disable foreign key checks while inserting initial data
SET FOREIGN_KEY_CHECKS = 0;
-- Clean existing data
TRUNCATE TABLE `product_images`;
TRUNCATE TABLE `order_items`;
TRUNCATE TABLE `payments`;
TRUNCATE TABLE `orders`;
TRUNCATE TABLE `products`;
TRUNCATE TABLE `categories`;
TRUNCATE TABLE `users`;
-- 1. users seed data
INSERT INTO `users`(
        `id`,
        `full_name`,
        `email`,
        `password_hash`,
        `phone`,
        `role`
    )
VALUES (
        1,
        'Store-Admin',
        'admin@toystore.com',
        '$2y$10$DwVkNyxTKCPvumoGlntdyevGx.A4KDPh1rMvlFxkbsv3UHoF7zIvK',
        '+919502398946',
        'admin'
    ),
    (
        2,
        'Priya',
        'customer@toystore.com',
        '$2y$10$GpSDQ4NmEvWCxTIubN4jae9wQPoL93qa6wjwusA6WMRzT5bwx6yi2',
        '+919123787931',
        'user'
    );
-- 2. categories seed data
INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `image_url`)
VALUES (
        1,
        'Action Figures',
        'action-figures',
        'Superheroes, sci-fi collectibles, and poseable adventure figures.',
        'https://images.unsplash.com/photo-1608889825205-eebdb9fc5806?w=600'
    ),
    (
        2,
        'Building Sets',
        'building-sets',
        'Bricks, modular engineering kits, and architectural blocks.',
        'https://images.unsplash.com/photo-1585366119957-e9730b6d0f60?w=600'
    ),
    (
        3,
        'Board Games & Puzzles',
        'board-games-puzzles',
        'Classic family games, tabletop strategy, and 1000pc jigsaw puzzles.',
        'https://images.unsplash.com/photo-1610890716171-6b1bb98ffd09?w=600'
    ),
    (
        4,
        'STEM & Learning',
        'stem-learning',
        'Science lab kits, coding robots, chemistry sets, and solar gadgets.',
        'https://images.unsplash.com/photo-1531482615713-2afd69097998?w=600'
    ),
    (
        5,
        'Plush & Soft Toys',
        'plush-soft-toys',
        'Cuddly teddy bears, anime plushies, and organic baby companions.',
        'https://images.unsplash.com/photo-1559454403-b8fb88521f11?w=600'
    ),
    (
        6,
        'RC & Vehicles',
        'rc-vehicles',
        'High-speed remote control drift cars, monster trucks, and stunt drones.',
        'https://images.unsplash.com/photo-1594787318286-3d835c1d207f?w=600'
    );
-- 3. PRODUCTS seed data
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
        `compare_at_price`,
        `stock_quantity`,
        `is_featured`,
        `is_active`
    )
VALUES -- Building Sets
    (
        1,
        2,
        'Galactic Space Explorer Station (1250 Pcs)',
        'galactic-space-station',
        'TOY-BLD-001',
        'BrickCraft',
        '9-12',
        'Detailed 1,250-piece orbital research station featuring modular living pods, docking bays, and 5 astronaut minifigures.',
        3499.00,
        3999.00,
        25,
        1,
        1
    ),
    (
        2,
        2,
        'CyberCity Hypercar Creator Kit',
        'cybercity-hypercar-kit',
        'TOY-BLD-002',
        'SpeedBuild',
        '6-8',
        'Build a sleek futuristic supercar with working suspension, opening gullwing doors, and steerable rubber wheels.',
        1499.00,
        1799.00,
        40,
        1,
        1
    ),
    -- Action Figures
    (
        3,
        1,
        'Titan Warrior Mech: Iron Vanguard',
        'titan-warrior-iron-vanguard',
        'TOY-ACT-001',
        'MechaForce',
        '6-8',
        'Fully articulated 12-inch heavy combat mech with interchangeable plasma cannon arms, LED cockpit, and die-cast joints.',
        1899.00,
        2199.00,
        18,
        1,
        1
    ),
    (
        4,
        1,
        'Cosmic Ranger: Commander Nova',
        'cosmic-ranger-nova',
        'TOY-ACT-002',
        'HeroVerse',
        '3-5',
        'Durable, child-safe superhero action figure with realistic fabric cape, combat sound effects, and light-up chest emblem.',
        799.00,
        NULL,
        60,
        0,
        1
    ),
    -- STEM & Learning
    (
        5,
        4,
        'RoboCoder Programmable Smart Rover',
        'robocoder-smart-rover',
        'TOY-STM-001',
        'TechLab Kids',
        '9-12',
        'Smart robotic rover that teaches kids real Python and block-coding logic. Equipped with ultrasonic obstacle avoidance and line sensors.',
        2999.00,
        3499.00,
        15,
        1,
        1
    ),
    (
        6,
        4,
        'Volcano Eruption & Crystal Chemistry Lab',
        'crystal-chemistry-lab',
        'TOY-STM-002',
        'GeoScience',
        '6-8',
        'Hands-on geology lab with safe non-toxic chemicals to grow sparkling crystal clusters and simulate realistic volcanic eruptions.',
        999.00,
        1299.00,
        50,
        0,
        1
    ),
    -- Board Games
    (
        7,
        3,
        'Kingdom Quest: Tabletop Dungeon Crawler',
        'kingdom-quest-tabletop',
        'TOY-BRD-001',
        'EpicBoard',
        '9-12',
        'Cooperative strategy dungeon crawler game for 2-4 players. Explore modular dungeons, defeat mythical beasts, and collect epic loot.',
        1999.00,
        2499.00,
        22,
        1,
        1
    ),
    (
        8,
        3,
        'Wildlife Safari Memory & Match Puzzle',
        'wildlife-safari-match',
        'TOY-BRD-002',
        'LittlePlay',
        '3-5',
        'Colorful wooden tile matching game that boosts memory, pattern recognition, and animal vocabulary for toddlers.',
        599.00,
        NULL,
        75,
        0,
        1
    ),
    -- Plush & Soft Toys
    (
        9,
        5,
        'Barnaby Giant Cuddle Bear 36-Inch',
        'barnaby-giant-cuddle-bear',
        'TOY-PLS-001',
        'CozyHug',
        '0-2',
        'Ultra-plush, hypoallergenic 3-foot tall teddy bear made with premium cloud-soft microfiber fur and reinforced stitching.',
        1599.00,
        1999.00,
        30,
        1,
        1
    ),
    (
        10,
        5,
        'Starry Night Sleepy Sloth with White Noise',
        'starry-sleepy-sloth',
        'TOY-PLS-002',
        'SweetDreams',
        '0-2',
        'Soothing baby nursery plush equipped with gentle heartbeat vibrations, warm nightlight belly, and 8 relaxing lullabies.',
        1199.00,
        1499.00,
        35,
        0,
        1
    ),
    -- RC & Vehicles
    (
        11,
        6,
        'Vortex All-Terrain 4WD RC Rock Crawler',
        'vortex-4wd-rc-crawler',
        'TOY-RC-001',
        'TurboTrack',
        '9-12',
        '1:14 scale 4-wheel drive RC monster truck with independent oil shocks, waterproof electronics, and 45km/h top speed.',
        2799.00,
        3299.00,
        14,
        1,
        1
    ),
    (
        12,
        6,
        'Acrobat 360 Stunt Twister Drone',
        'acrobat-360-stunt-drone',
        'TOY-RC-002',
        'SkyPilot',
        '6-8',
        'Crash-proof indoor mini drone featuring one-key 360-degree flips, altitude hold, and protective safety propeller cages.',
        1299.00,
        NULL,
        45,
        0,
        1
    );
-- 4. product images seed data
INSERT INTO `product_images` (
        `product_id`,
        `image_url`,
        `is_primary`,
        `sort_order`
    )
VALUES (
        1,
        'https://images.unsplash.com/photo-1585366119957-e9730b6d0f60?w=800',
        1,
        1
    ),
    (
        2,
        'https://images.unsplash.com/photo-1596461404969-9ae70f2830c1?w=800',
        1,
        1
    ),
    (
        3,
        'https://images.unsplash.com/photo-1608889825205-eebdb9fc5806?w=800',
        1,
        1
    ),
    (
        4,
        'https://images.unsplash.com/photo-1566576912321-d58ddd7a6088?w=800',
        1,
        1
    ),
    (
        5,
        'https://images.unsplash.com/photo-1531482615713-2afd69097998?w=800',
        1,
        1
    ),
    (
        6,
        'https://images.unsplash.com/photo-1567427017947-545c5f8d16ad?w=800',
        1,
        1
    ),
    (
        7,
        'https://images.unsplash.com/photo-1610890716171-6b1bb98ffd09?w=800',
        1,
        1
    ),
    (
        8,
        'https://images.unsplash.com/photo-1587654780291-39c9404d746b?w=800',
        1,
        1
    ),
    (
        9,
        'https://images.unsplash.com/photo-1559454403-b8fb88521f11?w=800',
        1,
        1
    ),
    (
        10,
        'https://images.unsplash.com/photo-1513151233558-d860c5398176?w=800',
        1,
        1
    ),
    (
        11,
        'https://images.unsplash.com/photo-1594787318286-3d835c1d207f?w=800',
        1,
        1
    ),
    (
        12,
        'https://images.unsplash.com/photo-1527977966376-1c8408f9f108?w=800',
        1,
        1
    );
SET FOREIGN_KEY_CHECKS = 1;