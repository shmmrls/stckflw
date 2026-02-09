-- Stock Flow Database - Insert Existing Data
-- This file contains INSERT statements for migrating existing data
-- Generation Time: Jan 25, 2026

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ========================================
-- INSERT CATEGORIES
-- ========================================

INSERT INTO `categories` (`category_id`, `category_name`, `description`, `created_at`) VALUES
(1, 'Dairy', 'Milk, cheese, yogurt, etc.', '2026-01-23 16:10:34'),
(2, 'Meat', 'Beef, pork, chicken, etc.', '2026-01-23 16:10:34'),
(3, 'Produce', 'Fruits and vegetables', '2026-01-23 16:10:34'),
(4, 'Frozen', 'Frozen foods', '2026-01-23 16:10:34'),
(5, 'Beverages', 'Drinks and beverages', '2026-01-23 16:10:34'),
(6, 'Pantry', 'Dry goods and canned items', '2026-01-23 16:10:34'),
(7, 'Bakery', 'Bread and baked goods', '2026-01-23 16:10:34');

-- ========================================
-- INSERT GROCERY STORES
-- ========================================

INSERT INTO `grocery_stores` (`store_id`, `store_name`, `business_address`, `contact_number`, `email`, `business_permit`, `is_verified`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Sample Grocery Store', '123 Main Street, Manila', '+63 912 345 6789', 'store@sample.com', NULL, 1, 1, '2026-01-25 07:42:04', '2026-01-25 07:42:04');

-- ========================================
-- INSERT USERS
-- ========================================

INSERT INTO `users` (`user_id`, `full_name`, `email`, `password`, `img_name`, `is_active`, `last_login`, `role`, `store_id`, `created_at`, `updated_at`) VALUES
(0, 'Admin Name', 'admin@store.com', 'hashed_password', NULL, 1, NULL, 'grocery_admin', 1, '2026-01-25 07:44:30', '2026-01-25 07:44:30'),
(1, 'Sample', 'sample@gmail.com', '$2y$10$WNC4cFXciSjhHG4NLk/43e0DggwSj9IAgBBToqw9mkzDX52.6qPl6', 'user_1_1769317418.png', 1, '2026-01-25 06:28:02', 'customer', NULL, '2026-01-25 01:19:59', '2026-01-25 06:28:02');

-- ========================================
-- INSERT GROUPS
-- ========================================

INSERT INTO `groups` (`group_id`, `group_name`, `group_type`, `created_by`, `invitation_code`, `created_at`) VALUES
(1, 'Sample', 'household', 1, '77787318', '2026-01-25 03:07:45');

-- ========================================
-- INSERT GROUP MEMBERS
-- ========================================

INSERT INTO `group_members` (`member_id`, `group_id`, `user_id`, `member_role`, `joined_at`) VALUES
(1, 1, 1, 'child', '2026-01-25 03:07:45');

-- ========================================
-- INSERT PRODUCT CATALOG
-- ========================================

INSERT INTO `product_catalog` (`catalog_id`, `barcode`, `product_name`, `brand`, `category_id`, `default_unit`, `typical_shelf_life_days`, `description`, `image_url`, `is_verified`, `created_at`, `updated_at`) VALUES
(1, '4800024405014', 'Alaska Evaporada Milk', 'Alaska', 1, 'can', 365, NULL, NULL, 1, '2026-01-24 06:13:50', '2026-01-24 06:13:50');

-- ========================================
-- INSERT BADGES
-- ========================================

INSERT INTO `badges` (`badge_id`, `badge_name`, `badge_description`, `badge_icon`, `points_required`, `created_at`) VALUES
(1, 'Newbie Organizer', 'Added your first 5 items', NULL, 25, '2026-01-23 16:10:34'),
(2, 'Waste Warrior', 'Logged 20 items before expiry', NULL, 100, '2026-01-23 16:10:34'),
(3, 'Inventory Master', 'Maintained inventory for 30 days', NULL, 200, '2026-01-23 16:10:34');

-- ========================================
-- INSERT USER POINTS
-- ========================================

INSERT INTO `user_points` (`user_id`, `total_points`, `last_updated`) VALUES
(1, 0, '2026-01-24 06:00:04');



-- ========================================
-- NOTES FOR MIGRATION
-- ========================================

-- The following tables had no data in the original dump:
-- - suppliers
-- - customer_items
-- - grocery_items
-- - customer_inventory_updates
-- - grocery_inventory_updates
-- - points_log
-- - user_badges

-- Views will be recreated automatically based on the new schema
-- No INSERT statements needed for views

COMMIT;