-- Improved Stock Flow Database Schema with Proper Relationships
-- Generation Time: Jan 25, 2026

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ========================================
-- CORE TABLES
-- ========================================

-- Categories (referenced by items)
CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `category_name` (`category_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Grocery Stores
CREATE TABLE `grocery_stores` (
  `store_id` int(11) NOT NULL AUTO_INCREMENT,
  `store_name` varchar(255) NOT NULL,
  `business_address` text NOT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `business_permit` varchar(255) DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`store_id`),
  UNIQUE KEY `store_name` (`store_name`),
  KEY `idx_store_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Users (One-to-Many with Groups, Stores)
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(1000) NOT NULL,
  `img_name` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `role` enum('customer','grocery_admin') DEFAULT 'customer',
  `store_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_users_email` (`email`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_store` (`store_id`),
  CONSTRAINT `fk_users_store` FOREIGN KEY (`store_id`) REFERENCES `grocery_stores` (`store_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Groups (One user creates, many users can join)
CREATE TABLE `groups` (
  `group_id` int(11) NOT NULL AUTO_INCREMENT,
  `group_name` varchar(255) NOT NULL,
  `group_type` enum('household','co_living','small_business') NOT NULL,
  `created_by` int(11) NOT NULL,
  `invitation_code` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`group_id`),
  UNIQUE KEY `invitation_code` (`invitation_code`),
  KEY `idx_groups_created_by` (`created_by`),
  CONSTRAINT `fk_groups_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- MANY-TO-MANY RELATIONSHIP TABLES
-- ========================================

-- Group Members (Many-to-Many: Users <-> Groups)
CREATE TABLE `group_members` (
  `member_id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `member_role` enum('parent','child','staff','member') DEFAULT 'member',
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`member_id`),
  UNIQUE KEY `unique_group_user` (`group_id`,`user_id`),
  KEY `idx_group_members_user` (`user_id`),
  KEY `idx_group_members_group` (`group_id`),
  CONSTRAINT `fk_group_members_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`group_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_group_members_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Suppliers
CREATE TABLE `suppliers` (
  `supplier_id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_name` varchar(255) NOT NULL,
  `supplier_type` enum('grocery_store','market_vendor','distributor') NOT NULL,
  `contact_info` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`supplier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Store Suppliers (Many-to-Many: Stores <-> Suppliers)
CREATE TABLE `store_suppliers` (
  `store_supplier_id` int(11) NOT NULL AUTO_INCREMENT,
  `store_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`store_supplier_id`),
  UNIQUE KEY `unique_store_supplier` (`store_id`, `supplier_id`),
  KEY `idx_store_suppliers_store` (`store_id`),
  KEY `idx_store_suppliers_supplier` (`supplier_id`),
  CONSTRAINT `fk_store_suppliers_store` FOREIGN KEY (`store_id`) REFERENCES `grocery_stores` (`store_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_store_suppliers_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- PRODUCT AND INVENTORY TABLES
-- ========================================

-- Product Catalog (One-to-Many with Items)
CREATE TABLE `product_catalog` (
  `catalog_id` int(11) NOT NULL AUTO_INCREMENT,
  `barcode` varchar(100) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `brand` varchar(255) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `default_unit` varchar(50) DEFAULT 'pcs',
  `typical_shelf_life_days` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`catalog_id`),
  UNIQUE KEY `barcode` (`barcode`),
  KEY `idx_product_catalog_category` (`category_id`),
  KEY `idx_product_catalog_barcode` (`barcode`),
  CONSTRAINT `fk_product_catalog_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Customer Items (One-to-Many: Group, User, Category, Product)
CREATE TABLE `customer_items` (
  `item_id` int(11) NOT NULL AUTO_INCREMENT,
  `item_name` varchar(255) NOT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `catalog_id` int(11) DEFAULT NULL,
  `category_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit` varchar(50) NOT NULL,
  `purchase_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `expiry_status` enum('fresh','near_expiry','expired') DEFAULT 'fresh',
  `alert_flag` tinyint(1) DEFAULT 0,
  `purchased_from` varchar(255) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `date_added` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`item_id`),
  KEY `idx_customer_items_created_by` (`created_by`),
  KEY `idx_customer_items_group` (`group_id`),
  KEY `idx_customer_items_category` (`category_id`),
  KEY `idx_customer_items_catalog` (`catalog_id`),
  KEY `idx_customer_items_expiry_status` (`expiry_status`),
  KEY `idx_customer_items_expiry_date` (`expiry_date`),
  KEY `idx_customer_items_alert_flag` (`alert_flag`),
  KEY `idx_customer_items_barcode` (`barcode`),
  CONSTRAINT `fk_customer_items_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_customer_items_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`group_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_customer_items_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_customer_items_catalog` FOREIGN KEY (`catalog_id`) REFERENCES `product_catalog` (`catalog_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Grocery Items (One-to-Many: Store, User, Category, Supplier, Product)
CREATE TABLE `grocery_items` (
  `item_id` int(11) NOT NULL AUTO_INCREMENT,
  `item_name` varchar(255) NOT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `catalog_id` int(11) DEFAULT NULL,
  `category_id` int(11) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit` varchar(50) NOT NULL,
  `purchase_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `expiry_status` enum('fresh','near_expiry','expired') DEFAULT 'fresh',
  `alert_flag` tinyint(1) DEFAULT 0,
  `cost_price` decimal(10,2) DEFAULT NULL,
  `selling_price` decimal(10,2) DEFAULT NULL,
  `reorder_level` decimal(10,2) DEFAULT 10.00,
  `reorder_quantity` decimal(10,2) DEFAULT 50.00,
  `sku` varchar(100) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `date_added` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`item_id`),
  KEY `idx_grocery_items_created_by` (`created_by`),
  KEY `idx_grocery_items_category` (`category_id`),
  KEY `idx_grocery_items_supplier` (`supplier_id`),
  KEY `idx_grocery_items_catalog` (`catalog_id`),
  KEY `idx_grocery_items_expiry_status` (`expiry_status`),
  KEY `idx_grocery_items_expiry_date` (`expiry_date`),
  KEY `idx_grocery_items_alert_flag` (`alert_flag`),
  KEY `idx_grocery_items_sku` (`sku`),
  KEY `idx_grocery_items_barcode` (`barcode`),
  KEY `idx_grocery_items_store` (`store_id`),
  CONSTRAINT `fk_grocery_items_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_grocery_items_store` FOREIGN KEY (`store_id`) REFERENCES `grocery_stores` (`store_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_grocery_items_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_grocery_items_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_grocery_items_catalog` FOREIGN KEY (`catalog_id`) REFERENCES `product_catalog` (`catalog_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- ACTIVITY AND TRACKING TABLES
-- ========================================

-- Customer Inventory Updates (One-to-Many: Item, User)
CREATE TABLE `customer_inventory_updates` (
  `update_id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `update_type` enum('added','consumed','spoiled','expired') NOT NULL,
  `quantity_change` decimal(10,2) NOT NULL,
  `updated_by` int(11) NOT NULL,
  `update_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`update_id`),
  KEY `idx_customer_updates_item` (`item_id`),
  KEY `idx_customer_updates_user` (`updated_by`),
  KEY `idx_customer_updates_date` (`update_date`),
  CONSTRAINT `fk_customer_updates_item` FOREIGN KEY (`item_id`) REFERENCES `customer_items` (`item_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_customer_updates_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Grocery Inventory Updates (One-to-Many: Item, User, Store)
CREATE TABLE `grocery_inventory_updates` (
  `update_id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `update_type` enum('added','sold','spoiled','expired','returned') NOT NULL,
  `quantity_change` decimal(10,2) NOT NULL,
  `updated_by` int(11) NOT NULL,
  `update_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`update_id`),
  KEY `idx_grocery_updates_item` (`item_id`),
  KEY `idx_grocery_updates_user` (`updated_by`),
  KEY `idx_grocery_updates_store` (`store_id`),
  KEY `idx_grocery_updates_date` (`update_date`),
  CONSTRAINT `fk_grocery_updates_item` FOREIGN KEY (`item_id`) REFERENCES `grocery_items` (`item_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_grocery_updates_store` FOREIGN KEY (`store_id`) REFERENCES `grocery_stores` (`store_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_grocery_updates_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Barcode Scan History (One-to-Many: User, Item)
CREATE TABLE `barcode_scan_history` (
  `scan_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `barcode` varchar(100) NOT NULL,
  `scan_type` enum('add_item','consume_item','lookup') NOT NULL,
  `product_found` tinyint(1) DEFAULT 0,
  `item_id` int(11) DEFAULT NULL,
  `scan_date` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`scan_id`),
  KEY `idx_barcode_scan_user` (`user_id`),
  KEY `idx_barcode_scan_date` (`scan_date`),
  KEY `idx_barcode_scan_barcode` (`barcode`),
  CONSTRAINT `fk_barcode_scan_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- GAMIFICATION TABLES
-- ========================================

-- Badges
CREATE TABLE `badges` (
  `badge_id` int(11) NOT NULL AUTO_INCREMENT,
  `badge_name` varchar(100) NOT NULL,
  `badge_description` text DEFAULT NULL,
  `badge_icon` varchar(255) DEFAULT NULL,
  `points_required` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`badge_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- User Points (One-to-One: User)
CREATE TABLE `user_points` (
  `user_id` int(11) NOT NULL,
  `total_points` int(11) DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_user_points_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- User Badges (Many-to-Many: Users <-> Badges)
CREATE TABLE `user_badges` (
  `user_badge_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `badge_id` int(11) NOT NULL,
  `unlocked_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_badge_id`),
  UNIQUE KEY `unique_user_badge` (`user_id`,`badge_id`),
  KEY `idx_user_badges_user` (`user_id`),
  KEY `idx_user_badges_badge` (`badge_id`),
  CONSTRAINT `fk_user_badges_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_badges_badge` FOREIGN KEY (`badge_id`) REFERENCES `badges` (`badge_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Points Log (One-to-Many: User, Item)
CREATE TABLE `points_log` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action_type` enum('ADD_ITEM','CONSUME_ITEM','LOG_CONSUMPTION') NOT NULL,
  `points_earned` int(11) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `action_date` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `idx_points_log_user` (`user_id`),
  KEY `idx_points_log_date` (`action_date`),
  CONSTRAINT `fk_points_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- TRIGGERS
-- ========================================

DELIMITER $$
CREATE TRIGGER `before_grocery_item_insert` BEFORE INSERT ON `grocery_items` FOR EACH ROW 
BEGIN
    DECLARE days_until_expiry INT;
    SET days_until_expiry = DATEDIFF(NEW.expiry_date, CURDATE());
    
    IF days_until_expiry < 0 THEN
        SET NEW.expiry_status = 'expired';
        SET NEW.alert_flag = 1;
    ELSEIF days_until_expiry <= 7 THEN
        SET NEW.expiry_status = 'near_expiry';
        SET NEW.alert_flag = 1;
    ELSE
        SET NEW.expiry_status = 'fresh';
        SET NEW.alert_flag = 0;
    END IF;
END$$

CREATE TRIGGER `before_grocery_item_update` BEFORE UPDATE ON `grocery_items` FOR EACH ROW 
BEGIN
    DECLARE days_until_expiry INT;
    SET days_until_expiry = DATEDIFF(NEW.expiry_date, CURDATE());
    
    IF days_until_expiry < 0 THEN
        SET NEW.expiry_status = 'expired';
        SET NEW.alert_flag = 1;
    ELSEIF days_until_expiry <= 7 THEN
        SET NEW.expiry_status = 'near_expiry';
        SET NEW.alert_flag = 1;
    ELSE
        SET NEW.expiry_status = 'fresh';
        SET NEW.alert_flag = 0;
    END IF;
END$$

CREATE TRIGGER `before_customer_item_insert` BEFORE INSERT ON `customer_items` FOR EACH ROW 
BEGIN
    DECLARE days_until_expiry INT;
    SET days_until_expiry = DATEDIFF(NEW.expiry_date, CURDATE());
    
    IF days_until_expiry < 0 THEN
        SET NEW.expiry_status = 'expired';
        SET NEW.alert_flag = 1;
    ELSEIF days_until_expiry <= 7 THEN
        SET NEW.expiry_status = 'near_expiry';
        SET NEW.alert_flag = 1;
    ELSE
        SET NEW.expiry_status = 'fresh';
        SET NEW.alert_flag = 0;
    END IF;
END$$

CREATE TRIGGER `before_customer_item_update` BEFORE UPDATE ON `customer_items` FOR EACH ROW 
BEGIN
    DECLARE days_until_expiry INT;
    SET days_until_expiry = DATEDIFF(NEW.expiry_date, CURDATE());
    
    IF days_until_expiry < 0 THEN
        SET NEW.expiry_status = 'expired';
        SET NEW.alert_flag = 1;
    ELSEIF days_until_expiry <= 7 THEN
        SET NEW.expiry_status = 'near_expiry';
        SET NEW.alert_flag = 1;
    ELSE
        SET NEW.expiry_status = 'fresh';
        SET NEW.alert_flag = 0;
    END IF;
END$$
DELIMITER ;

COMMIT;