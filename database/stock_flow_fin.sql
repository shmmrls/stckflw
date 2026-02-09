-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 09, 2026 at 02:55 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `stock_flow`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_generate_reorder_po` (IN `p_store_id` INT, IN `p_supplier_id` INT, IN `p_created_by` INT, OUT `p_po_id` INT)   BEGIN
    DECLARE v_po_number VARCHAR(50);
    DECLARE v_item_count INT;
    
    -- Generate PO number
    SET v_po_number = CONCAT('PO-', YEAR(CURDATE()), '-', 
                             LPAD((SELECT COALESCE(MAX(po_id), 0) + 1 FROM purchase_orders), 6, '0'));
    
    -- Create purchase order
    INSERT INTO purchase_orders (
        po_number, store_id, supplier_id, order_date, 
        expected_delivery_date, status, created_by
    ) VALUES (
        v_po_number, p_store_id, p_supplier_id, CURDATE(),
        DATE_ADD(CURDATE(), INTERVAL (SELECT COALESCE(AVG(lead_time_days), 3) 
                                       FROM supplier_products 
                                       WHERE supplier_id = p_supplier_id) DAY),
        'draft', p_created_by
    );
    
    SET p_po_id = LAST_INSERT_ID();
    
    -- Add items that need reordering
    INSERT INTO purchase_order_items (
        po_id, supplier_product_id, product_name, 
        quantity_ordered, unit_price
    )
    SELECT 
        p_po_id,
        sp.supplier_product_id,
        gi.item_name,
        GREATEST(gi.reorder_quantity, sp.minimum_order_quantity),
        sp.unit_price
    FROM grocery_items gi
    JOIN supplier_products sp ON gi.catalog_id = sp.catalog_id
    WHERE gi.store_id = p_store_id
      AND sp.supplier_id = p_supplier_id
      AND gi.quantity <= gi.reorder_level
      AND sp.is_available = 1;
    
    -- Update PO totals
    UPDATE purchase_orders
    SET total_amount = (SELECT SUM(total_price) FROM purchase_order_items WHERE po_id = p_po_id),
        grand_total = total_amount
    WHERE po_id = p_po_id;
    
    SELECT v_po_number as po_number, 
           (SELECT COUNT(*) FROM purchase_order_items WHERE po_id = p_po_id) as items_count;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_best_supplier_for_product` (IN `p_catalog_id` INT, IN `p_required_quantity` INT)   BEGIN
    SELECT 
        s.supplier_id,
        s.supplier_name,
        s.supplier_type,
        sp.supplier_product_id,
        sp.supplier_sku,
        sp.unit_price,
        sp.minimum_order_quantity,
        sp.lead_time_days,
        s.payment_terms,
        s.delivery_schedule,
        (sp.unit_price * p_required_quantity) as estimated_cost,
        CASE 
            WHEN p_required_quantity >= sp.minimum_order_quantity THEN 'Can Order'
            ELSE CONCAT('Need ', sp.minimum_order_quantity - p_required_quantity, ' more')
        END as order_status
    FROM supplier_products sp
    JOIN suppliers s ON sp.supplier_id = s.supplier_id
    WHERE sp.catalog_id = p_catalog_id
      AND sp.is_available = 1
      AND s.is_active = 1
    ORDER BY sp.unit_price ASC;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `badges`
--

CREATE TABLE `badges` (
  `badge_id` int(11) NOT NULL,
  `badge_name` varchar(100) NOT NULL,
  `badge_description` text DEFAULT NULL,
  `badge_icon` varchar(255) DEFAULT NULL,
  `points_required` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `badges`
--

INSERT INTO `badges` (`badge_id`, `badge_name`, `badge_description`, `badge_icon`, `points_required`, `created_at`) VALUES
(1, 'Newbie Organizer', 'Add 5 items to your inventory', NULL, 25, '2026-01-23 16:10:34'),
(2, 'Waste Warrior', 'Log 20+ consumption actions', NULL, 100, '2026-01-23 16:10:34'),
(3, 'Inventory Master', 'Earn 200+ points through tracking', NULL, 200, '2026-01-23 16:10:34'),
(4, 'Active Helper', 'Logged consumption 10 times', NULL, 100, '2026-02-09 11:14:17'),
(5, 'Power User', 'Earned 500 points from tracking', NULL, 500, '2026-02-09 11:14:17');

-- --------------------------------------------------------

--
-- Table structure for table `barcode_scan_history`
--

CREATE TABLE `barcode_scan_history` (
  `scan_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `barcode` varchar(100) NOT NULL,
  `scan_type` enum('add_item','consume_item','lookup') NOT NULL,
  `product_found` tinyint(1) DEFAULT 0,
  `item_id` int(11) DEFAULT NULL,
  `scan_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `barcode_scan_history`
--

INSERT INTO `barcode_scan_history` (`scan_id`, `user_id`, `barcode`, `scan_type`, `product_found`, `item_id`, `scan_date`) VALUES
(1, 0, '4800024405014', 'lookup', 1, NULL, '2026-01-24 06:17:44'),
(2, 0, '4800024405014', 'lookup', 1, NULL, '2026-01-24 06:31:52'),
(3, 0, '076950450479', 'lookup', 0, NULL, '2026-01-24 06:41:15'),
(4, 0, '2102175175144', 'lookup', 0, NULL, '2026-01-24 06:41:32'),
(5, 0, '; \"L%!/4', 'lookup', 0, NULL, '2026-01-24 06:44:27'),
(6, 0, '4800024405014', 'lookup', 1, NULL, '2026-01-24 06:44:37'),
(7, 0, '4800024405014', 'lookup', 1, NULL, '2026-01-24 06:45:36'),
(8, 0, '3574660026955', 'lookup', 0, NULL, '2026-01-24 06:53:41'),
(9, 0, 'HTTPS://DELIVR.COM/23BBC-QR', 'lookup', 0, NULL, '2026-01-24 06:54:21'),
(10, 0, 'HTTPS://DELIVR.COM/23BBC-QR', 'lookup', 0, NULL, '2026-01-24 06:54:26'),
(11, 0, '81044832', 'lookup', 0, NULL, '2026-01-24 06:54:34'),
(12, 0, '100627536488026', 'lookup', 0, NULL, '2026-01-25 02:25:50'),
(13, 0, '100627536488026', 'lookup', 0, NULL, '2026-01-25 02:25:52'),
(14, 0, '100627536488026', 'lookup', 0, NULL, '2026-01-25 02:25:55'),
(15, 0, '100627536488026', 'lookup', 0, NULL, '2026-01-25 02:25:58'),
(16, 0, '100627536488026', 'lookup', 0, NULL, '2026-01-25 02:25:59'),
(17, 0, '100627536488026', 'lookup', 0, NULL, '2026-01-25 02:26:00'),
(18, 0, '100627536488026', 'lookup', 0, NULL, '2026-01-25 02:26:00'),
(19, 0, '100627536488026', 'lookup', 0, NULL, '2026-01-25 02:26:01'),
(20, 0, '100627536488026', 'lookup', 0, NULL, '2026-01-25 02:26:02'),
(21, 0, '100627536488026', 'lookup', 0, NULL, '2026-01-25 02:26:02'),
(22, 0, '100627536488026', 'lookup', 0, NULL, '2026-01-25 02:26:03'),
(23, 0, '100627536488026', 'lookup', 0, NULL, '2026-01-25 02:26:04'),
(24, 0, '100627536488026', 'lookup', 0, NULL, '2026-01-25 02:26:04'),
(25, 0, '100627536488026', 'lookup', 0, NULL, '2026-01-25 02:26:05'),
(26, 0, '100627536488026', 'lookup', 0, NULL, '2026-01-25 02:26:07'),
(27, 0, '100627536488026', 'lookup', 0, NULL, '2026-01-25 02:26:08'),
(28, 0, '100627536488026', 'lookup', 0, NULL, '2026-01-25 02:26:10'),
(29, 0, '100627536488026', 'lookup', 0, NULL, '2026-01-25 02:26:11'),
(30, 0, '100627536488026', 'lookup', 0, NULL, '2026-01-25 02:26:13'),
(31, 0, '100627536488026', 'lookup', 0, NULL, '2026-01-25 02:26:14'),
(32, 0, '100627536488026', 'lookup', 0, NULL, '2026-01-25 02:26:16'),
(33, 0, '100627536488026', 'lookup', 0, NULL, '2026-01-25 02:26:17'),
(34, 0, '11505633392233', 'lookup', 0, NULL, '2026-01-25 02:26:39'),
(35, 0, '11505633392233', 'lookup', 0, NULL, '2026-01-25 02:26:42'),
(36, 0, '11505633392233', 'lookup', 0, NULL, '2026-01-25 02:26:44'),
(37, 0, '11505633392233', 'lookup', 0, NULL, '2026-01-25 02:26:47'),
(38, 0, '11505633392233', 'lookup', 0, NULL, '2026-01-25 02:26:50'),
(39, 0, '11505633392233', 'lookup', 0, NULL, '2026-01-25 02:26:52'),
(40, 0, '11505633392233', 'lookup', 0, NULL, '2026-01-25 02:26:54'),
(41, 0, '11505633392233', 'lookup', 0, NULL, '2026-01-25 02:26:56'),
(42, 0, '11505633392233', 'lookup', 0, NULL, '2026-01-25 02:26:59'),
(43, 0, '11505633392233', 'lookup', 0, NULL, '2026-01-25 02:27:02'),
(44, 0, '11505633392233', 'lookup', 0, NULL, '2026-01-25 02:27:05'),
(45, 0, '11505633392233', 'lookup', 0, NULL, '2026-01-25 02:27:08'),
(46, 0, '11505633392233', 'lookup', 0, NULL, '2026-01-25 02:27:11'),
(47, 0, '11505633392233', 'lookup', 0, NULL, '2026-01-25 02:27:14'),
(48, 0, '11505633392233', 'lookup', 0, NULL, '2026-01-25 02:27:17'),
(49, 0, '11505633392233', 'lookup', 0, NULL, '2026-01-25 02:27:20'),
(50, 0, '11505633392233', 'lookup', 0, NULL, '2026-01-25 02:27:23'),
(51, 0, '11505633392233', 'lookup', 0, NULL, '2026-01-25 02:27:26'),
(52, 0, '11505633392233', 'lookup', 0, NULL, '2026-01-25 02:27:29'),
(53, 0, '11505633392233', 'lookup', 0, NULL, '2026-01-25 02:27:32'),
(54, 0, '11505633392233', 'lookup', 0, NULL, '2026-01-25 02:27:35'),
(55, 0, '11505633392233', 'lookup', 0, NULL, '2026-01-25 02:27:38'),
(56, 0, '11505633392233', 'lookup', 0, NULL, '2026-01-25 02:27:41'),
(57, 0, '11505633392233', 'lookup', 0, NULL, '2026-01-25 02:27:45'),
(58, 0, '11505633392233', 'lookup', 0, NULL, '2026-01-25 02:27:48'),
(59, 0, '11505633392233', 'lookup', 0, NULL, '2026-01-25 02:27:51'),
(60, 0, '11505633392233', 'lookup', 0, NULL, '2026-01-25 02:27:54'),
(61, 0, '11505633392233', 'lookup', 0, NULL, '2026-01-25 02:27:57'),
(62, 0, '11505633392233', 'lookup', 0, NULL, '2026-01-25 02:28:00'),
(63, 0, '11505633392233', 'lookup', 0, NULL, '2026-01-25 02:28:03'),
(64, 0, '11505633392233', 'lookup', 0, NULL, '2026-01-25 02:28:06'),
(65, 0, '11505633392233', 'lookup', 0, NULL, '2026-01-25 02:28:09'),
(66, 0, '11505633392233', 'lookup', 0, NULL, '2026-01-25 02:28:12'),
(67, 0, '11505633392233', 'lookup', 0, NULL, '2026-01-25 02:28:15'),
(68, 0, '11505633392233', 'lookup', 0, NULL, '2026-01-25 02:28:18'),
(69, 0, '11505633392233', 'lookup', 0, NULL, '2026-01-25 02:28:21'),
(70, 0, '11505633392233', 'lookup', 0, NULL, '2026-01-25 02:28:23'),
(71, 0, '11505633392233', 'lookup', 0, NULL, '2026-01-25 02:28:23'),
(72, 0, '11505633392233', 'lookup', 0, NULL, '2026-01-25 02:28:24'),
(73, 0, '11505633392233', 'lookup', 0, NULL, '2026-01-25 02:28:25'),
(74, 0, '11505633392233', 'lookup', 0, NULL, '2026-01-25 02:28:25'),
(75, 0, '3574660026955', 'lookup', 0, NULL, '2026-01-25 02:28:58'),
(76, 0, 'P \"L##4', 'lookup', 0, NULL, '2026-01-25 02:29:28'),
(77, 0, '4800024405014', 'lookup', 1, NULL, '2026-01-25 02:29:47'),
(78, 0, '48517165586138', 'lookup', 0, NULL, '2026-01-25 02:30:17'),
(79, 0, '48517165586138', 'lookup', 0, NULL, '2026-01-25 02:30:19'),
(80, 0, '48517165586138', 'lookup', 0, NULL, '2026-01-25 02:30:22'),
(81, 0, '48517165586138', 'lookup', 0, NULL, '2026-01-25 02:30:25'),
(82, 0, '48517165586138', 'lookup', 0, NULL, '2026-01-25 02:30:28'),
(83, 0, '48517165586138', 'lookup', 0, NULL, '2026-01-25 02:30:31'),
(84, 0, '48517165586138', 'lookup', 0, NULL, '2026-01-25 02:30:34'),
(85, 0, '48517165586138', 'lookup', 0, NULL, '2026-01-25 02:30:37'),
(86, 0, '48517165586138', 'lookup', 0, NULL, '2026-01-25 02:30:40'),
(87, 0, '48517165586138', 'lookup', 0, NULL, '2026-01-25 02:30:43'),
(88, 0, '48517165586138', 'lookup', 0, NULL, '2026-01-25 02:30:46'),
(89, 0, '48517165586138', 'lookup', 0, NULL, '2026-01-25 02:30:49'),
(90, 0, '48517165586138', 'lookup', 0, NULL, '2026-01-25 02:30:52'),
(91, 0, '48517165586138', 'lookup', 0, NULL, '2026-01-25 02:30:55'),
(92, 0, '48517165586138', 'lookup', 0, NULL, '2026-01-25 02:30:58'),
(93, 0, '48517165586138', 'lookup', 0, NULL, '2026-01-25 02:31:01'),
(94, 0, '48517165586138', 'lookup', 0, NULL, '2026-01-25 02:31:04'),
(95, 0, '48517165586138', 'lookup', 0, NULL, '2026-01-25 02:31:07'),
(96, 0, '48517165586138', 'lookup', 0, NULL, '2026-01-25 02:31:10'),
(97, 0, '48517165586138', 'lookup', 0, NULL, '2026-01-25 02:31:13'),
(98, 0, '48517165586138', 'lookup', 0, NULL, '2026-01-25 02:31:16'),
(99, 0, '48517165586138', 'lookup', 0, NULL, '2026-01-25 02:31:19'),
(100, 0, '48517165586138', 'lookup', 0, NULL, '2026-01-25 02:31:22'),
(101, 0, '48517165586138', 'lookup', 0, NULL, '2026-01-25 02:31:25'),
(102, 0, 'P CL%!/%', 'lookup', 0, NULL, '2026-01-25 02:32:13'),
(103, 0, '78090447762297', 'lookup', 0, NULL, '2026-01-25 02:32:22'),
(104, 0, '78090447762297', 'lookup', 0, NULL, '2026-01-25 02:32:24'),
(105, 0, '78090447762297', 'lookup', 0, NULL, '2026-01-25 02:32:27'),
(106, 0, '78090447762297', 'lookup', 0, NULL, '2026-01-25 02:32:30'),
(107, 0, '78090447762297', 'lookup', 0, NULL, '2026-01-25 02:32:31'),
(108, 0, '78090447762297', 'lookup', 0, NULL, '2026-01-25 02:32:31'),
(109, 0, '78090447762297', 'lookup', 0, NULL, '2026-01-25 02:32:35'),
(110, 0, '78090447762297', 'lookup', 0, NULL, '2026-01-25 02:32:37'),
(111, 0, '78090447762297', 'lookup', 0, NULL, '2026-01-25 02:32:39'),
(112, 0, '78090447762297', 'lookup', 0, NULL, '2026-01-25 02:32:41'),
(113, 0, '78090447762297', 'lookup', 0, NULL, '2026-01-25 02:32:43'),
(114, 0, '4800024405014', 'lookup', 1, NULL, '2026-01-25 02:33:03'),
(115, 0, '4800024405014', 'lookup', 1, NULL, '2026-01-25 02:33:55'),
(116, 0, '4800024405014', 'lookup', 1, NULL, '2026-01-25 02:34:04'),
(117, 0, '3574660026955', 'lookup', 0, NULL, '2026-01-25 02:34:33'),
(118, 1, '4800024405014', 'lookup', 1, NULL, '2026-01-25 04:51:11'),
(119, 1, '4800024405014', 'lookup', 1, NULL, '2026-01-27 03:11:57'),
(120, 1, '3574660026955', 'lookup', 0, NULL, '2026-01-27 03:12:31'),
(121, 1, '4806521790750', 'lookup', 0, NULL, '2026-01-27 03:13:40'),
(122, 0, '3574660026955', 'lookup', 0, NULL, '2026-01-27 03:15:11'),
(123, 0, '4806521790750', 'lookup', 0, NULL, '2026-01-27 03:15:31'),
(124, 1, '4800024405014', 'lookup', 1, NULL, '2026-01-28 01:38:51'),
(125, 1, '3574660026955', 'lookup', 0, NULL, '2026-01-28 01:39:29');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`, `description`, `created_at`) VALUES
(1, 'Dairy', 'Milk, cheese, yogurt, etc.', '2026-01-23 16:10:34'),
(2, 'Meat', 'Beef, pork, chicken, etc.', '2026-01-23 16:10:34'),
(3, 'Produce', 'Fruits and vegetables', '2026-01-23 16:10:34'),
(4, 'Frozen', 'Frozen foods', '2026-01-23 16:10:34'),
(5, 'Beverages', 'Drinks and beverages', '2026-01-23 16:10:34'),
(6, 'Pantry', 'Dry goods and canned items', '2026-01-23 16:10:34'),
(7, 'Bakery', 'Bread and baked goods', '2026-01-23 16:10:34');

-- --------------------------------------------------------

--
-- Table structure for table `customer_inventory_updates`
--

CREATE TABLE `customer_inventory_updates` (
  `update_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `update_type` enum('added','consumed','spoiled','expired') NOT NULL,
  `quantity_change` decimal(10,2) NOT NULL,
  `updated_by` int(11) NOT NULL,
  `update_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_items`
--

CREATE TABLE `customer_items` (
  `item_id` int(11) NOT NULL,
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
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `customer_items`
--
DELIMITER $$
CREATE TRIGGER `before_customer_item_insert` BEFORE INSERT ON `customer_items` FOR EACH ROW BEGIN
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
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `before_customer_item_update` BEFORE UPDATE ON `customer_items` FOR EACH ROW BEGIN
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
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `grocery_inventory_updates`
--

CREATE TABLE `grocery_inventory_updates` (
  `update_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `update_type` enum('added','sold','spoiled','expired','returned') NOT NULL,
  `quantity_change` decimal(10,2) NOT NULL,
  `updated_by` int(11) NOT NULL,
  `update_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `grocery_items`
--

CREATE TABLE `grocery_items` (
  `item_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `catalog_id` int(11) DEFAULT NULL,
  `category_id` int(11) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `supplier_product_id` int(11) DEFAULT NULL COMMENT 'Links to specific supplier product with pricing',
  `purchase_order_id` int(11) DEFAULT NULL COMMENT 'Purchase order this item came from',
  `batch_number` varchar(100) DEFAULT NULL COMMENT 'Supplier batch/lot number for traceability',
  `received_date` date DEFAULT NULL COMMENT 'Date items were received from supplier',
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
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `grocery_items`
--
DELIMITER $$
CREATE TRIGGER `before_grocery_item_insert` BEFORE INSERT ON `grocery_items` FOR EACH ROW BEGIN
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
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `before_grocery_item_update` BEFORE UPDATE ON `grocery_items` FOR EACH ROW BEGIN
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
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `grocery_stores`
--

CREATE TABLE `grocery_stores` (
  `store_id` int(11) NOT NULL,
  `store_name` varchar(255) NOT NULL,
  `business_address` text NOT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `business_permit` varchar(255) DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `grocery_stores`
--

INSERT INTO `grocery_stores` (`store_id`, `store_name`, `business_address`, `contact_number`, `email`, `business_permit`, `is_verified`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Sample Grocery Store', '123 Main Street, Manila', '+63 912 345 6789', 'store@sample.com', NULL, 1, 1, '2026-01-25 07:42:04', '2026-01-25 07:42:04');

-- --------------------------------------------------------

--
-- Table structure for table `groups`
--

CREATE TABLE `groups` (
  `group_id` int(11) NOT NULL,
  `group_name` varchar(255) NOT NULL,
  `group_type` enum('household','co_living','small_business') NOT NULL,
  `created_by` int(11) NOT NULL,
  `invitation_code` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `groups`
--

INSERT INTO `groups` (`group_id`, `group_name`, `group_type`, `created_by`, `invitation_code`, `created_at`) VALUES
(1, 'Sample', 'household', 1, '77787318', '2026-01-25 03:07:45');

-- --------------------------------------------------------

--
-- Table structure for table `group_members`
--

CREATE TABLE `group_members` (
  `member_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `member_role` enum('parent','child','staff','member') DEFAULT 'member',
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `group_members`
--

INSERT INTO `group_members` (`member_id`, `group_id`, `user_id`, `member_role`, `joined_at`) VALUES
(1, 1, 1, 'child', '2026-01-25 03:07:45');

-- --------------------------------------------------------

--
-- Table structure for table `points_log`
--

CREATE TABLE `points_log` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action_type` enum('ADD_ITEM','CONSUME_ITEM','LOG_CONSUMPTION') NOT NULL,
  `points_earned` int(11) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `action_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_catalog`
--

CREATE TABLE `product_catalog` (
  `catalog_id` int(11) NOT NULL,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_catalog`
--

INSERT INTO `product_catalog` (`catalog_id`, `barcode`, `product_name`, `brand`, `category_id`, `default_unit`, `typical_shelf_life_days`, `description`, `image_url`, `is_verified`, `created_at`, `updated_at`) VALUES
(1, '4800024405014', 'Alaska Evaporada Milk', 'Alaska', 1, 'can', 365, NULL, NULL, 1, '2026-01-24 06:13:50', '2026-01-24 06:13:50');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `po_id` int(11) NOT NULL,
  `po_number` varchar(50) NOT NULL COMMENT 'Purchase Order Number',
  `store_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `order_date` date NOT NULL,
  `expected_delivery_date` date DEFAULT NULL,
  `actual_delivery_date` date DEFAULT NULL,
  `status` enum('draft','submitted','confirmed','partially_received','received','cancelled') DEFAULT 'draft',
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `grand_total` decimal(10,2) DEFAULT 0.00,
  `payment_status` enum('unpaid','partially_paid','paid') DEFAULT 'unpaid',
  `payment_terms` varchar(100) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order_items`
--

CREATE TABLE `purchase_order_items` (
  `po_item_id` int(11) NOT NULL,
  `po_id` int(11) NOT NULL,
  `supplier_product_id` int(11) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `quantity_ordered` decimal(10,2) NOT NULL,
  `quantity_received` decimal(10,2) DEFAULT 0.00,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) GENERATED ALWAYS AS (`quantity_ordered` * `unit_price`) STORED,
  `expiry_date` date DEFAULT NULL COMMENT 'Expected expiry date of received items',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `store_suppliers`
--

CREATE TABLE `store_suppliers` (
  `store_supplier_id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `preferred_supplier` tinyint(1) DEFAULT 0 COMMENT 'Mark as preferred for automatic ordering',
  `credit_limit` decimal(10,2) DEFAULT NULL,
  `current_balance` decimal(10,2) DEFAULT 0.00,
  `last_order_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `supplier_id` int(11) NOT NULL,
  `supplier_name` varchar(255) NOT NULL,
  `supplier_type` enum('manufacturer','distributor','wholesaler','local_supplier') NOT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `payment_terms` varchar(100) DEFAULT NULL COMMENT 'e.g., Net 30, Net 60, COD',
  `minimum_order_amount` decimal(10,2) DEFAULT NULL,
  `delivery_schedule` varchar(255) DEFAULT NULL COMMENT 'e.g., Mon/Wed/Fri, Weekly',
  `tin_number` varchar(50) DEFAULT NULL COMMENT 'Tax Identification Number',
  `is_active` tinyint(1) DEFAULT 1,
  `rating` decimal(3,2) DEFAULT NULL COMMENT 'Supplier rating 1-5',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`supplier_id`, `supplier_name`, `supplier_type`, `company_name`, `contact_person`, `contact_number`, `email`, `address`, `payment_terms`, `minimum_order_amount`, `delivery_schedule`, `tin_number`, `is_active`, `rating`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'Nestle Philippines', 'manufacturer', 'Nestle Philippines Inc.', 'John Dela Cruz', '+63 2 8891 0000', 'orders@nestle.ph', 'Alabang, Muntinlupa City', 'Net 30', 10000.00, 'Mon/Wed/Fri', NULL, 1, 4.50, NULL, '2026-02-09 13:51:53', '2026-02-09 13:51:53'),
(2, 'Unilever Philippines', 'manufacturer', 'Unilever Philippines Inc.', 'Maria Santos', '+63 2 8857 5000', 'orders@unilever.ph', 'Bonifacio Global City, Taguig', 'Net 30', 15000.00, 'Tue/Thu', NULL, 1, 4.70, NULL, '2026-02-09 13:51:53', '2026-02-09 13:51:53'),
(3, 'Alaska Milk Corporation', 'manufacturer', 'Alaska Milk Corporation', 'Pedro Reyes', '+63 2 8359 3333', 'sales@alaskamilk.com', 'San Pedro, Laguna', 'Net 45', 8000.00, 'Daily', NULL, 1, 4.80, NULL, '2026-02-09 13:51:53', '2026-02-09 13:51:53'),
(4, 'San Miguel Foods', 'manufacturer', 'San Miguel Foods Inc.', 'Ana Garcia', '+63 2 8632 3000', 'orders@smf.com.ph', 'Mandaluyong City', 'Net 30', 12000.00, 'Mon/Wed/Fri', NULL, 1, 4.60, NULL, '2026-02-09 13:51:53', '2026-02-09 13:51:53'),
(5, 'CDO Foodsphere', 'manufacturer', 'CDO Foodsphere Inc.', 'Carlos Tan', '+63 2 8631 8000', 'sales@cdo.com.ph', 'Valenzuela City', 'Net 30', 9000.00, 'Tue/Thu/Sat', NULL, 1, 4.40, NULL, '2026-02-09 13:51:53', '2026-02-09 13:51:53'),
(6, 'Metro Manila Distributors', 'distributor', 'Metro Manila Distributors Corp.', 'Roberto Cruz', '+63 917 123 4567', 'info@mmdistributors.com', 'Quezon City', 'Net 15', 5000.00, 'Daily', NULL, 1, 4.20, NULL, '2026-02-09 13:51:53', '2026-02-09 13:51:53'),
(7, 'Mega Prime Foods', 'distributor', 'Mega Prime Foods Distribution', 'Lisa Mendoza', '+63 918 234 5678', 'orders@megaprime.ph', 'Pasig City', 'COD', 3000.00, 'Mon-Sat', NULL, 1, 4.30, NULL, '2026-02-09 13:51:53', '2026-02-09 13:51:53'),
(8, 'Golden Harvest Wholesale', 'wholesaler', 'Golden Harvest Wholesale Inc.', 'Richard Lim', '+63 919 345 6789', 'wholesale@goldenharvest.ph', 'Makati City', 'Net 7', 7000.00, 'Weekly', NULL, 1, 4.00, NULL, '2026-02-09 13:51:53', '2026-02-09 13:51:53'),
(9, 'Fresh Produce Suppliers PH', 'local_supplier', 'Fresh Produce Suppliers', 'Emma Rodriguez', '+63 920 456 7890', 'fresh@produce.ph', 'Baguio City', 'COD', 2000.00, 'Daily', NULL, 1, 4.50, NULL, '2026-02-09 13:51:53', '2026-02-09 13:51:53'),
(10, 'Local Bakery Supplies', 'local_supplier', 'Local Bakery Supplies Co.', 'Mark Fernandez', '+63 921 567 8901', 'bakery@supplies.ph', 'Manila', 'Net 7', 1500.00, 'Daily', NULL, 1, 4.10, NULL, '2026-02-09 13:51:53', '2026-02-09 13:51:53');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_products`
--

CREATE TABLE `supplier_products` (
  `supplier_product_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `catalog_id` int(11) DEFAULT NULL COMMENT 'Reference to product_catalog',
  `supplier_sku` varchar(100) DEFAULT NULL COMMENT 'Supplier''s product code',
  `product_name` varchar(255) NOT NULL,
  `brand` varchar(255) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `unit_price` decimal(10,2) NOT NULL COMMENT 'Price per unit from supplier',
  `unit_size` varchar(50) DEFAULT NULL COMMENT 'e.g., 1L, 500g, 12pcs',
  `minimum_order_quantity` int(11) DEFAULT 1,
  `lead_time_days` int(11) DEFAULT NULL COMMENT 'Days needed for delivery',
  `is_available` tinyint(1) DEFAULT 1,
  `last_price_update` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supplier_products`
--

INSERT INTO `supplier_products` (`supplier_product_id`, `supplier_id`, `catalog_id`, `supplier_sku`, `product_name`, `brand`, `category_id`, `unit_price`, `unit_size`, `minimum_order_quantity`, `lead_time_days`, `is_available`, `last_price_update`, `notes`, `created_at`, `updated_at`) VALUES
(1, 3, NULL, 'ALASKA-EVAP-370', 'Alaska Evaporated Milk', 'Alaska', 1, 42.50, '370ml can', 24, 2, 1, NULL, NULL, '2026-02-09 13:51:53', '2026-02-09 13:51:53'),
(2, 3, NULL, 'ALASKA-COND-300', 'Alaska Condensed Milk', 'Alaska', 1, 48.00, '300ml can', 24, 2, 1, NULL, NULL, '2026-02-09 13:51:53', '2026-02-09 13:51:53'),
(3, 3, NULL, 'ALASKA-POWDER-150', 'Alaska Powdered Milk', 'Alaska', 1, 125.00, '150g pack', 12, 2, 1, NULL, NULL, '2026-02-09 13:51:53', '2026-02-09 13:51:53'),
(4, 1, NULL, 'NESTLE-BEAR-300', 'Bear Brand Milk', 'Bear Brand', 1, 38.00, '300ml can', 24, 3, 1, NULL, NULL, '2026-02-09 13:51:53', '2026-02-09 13:51:53'),
(5, 1, NULL, 'NESTLE-MILO-1KG', 'Milo Chocolate Drink', 'Milo', 5, 285.00, '1kg pack', 6, 3, 1, NULL, NULL, '2026-02-09 13:51:53', '2026-02-09 13:51:53'),
(6, 1, NULL, 'NESTLE-NESCAFE-200', 'Nescafe Classic', 'Nescafe', 5, 165.00, '200g jar', 12, 3, 1, NULL, NULL, '2026-02-09 13:51:53', '2026-02-09 13:51:53'),
(7, 5, NULL, 'CDO-HOTDOG-500', 'CDO Hotdog', 'CDO', 2, 115.00, '500g pack', 10, 2, 1, NULL, NULL, '2026-02-09 13:51:53', '2026-02-09 13:51:53'),
(8, 5, NULL, 'CDO-TOCINO-500', 'CDO Sweet Tocino', 'CDO', 2, 125.00, '500g pack', 10, 2, 1, NULL, NULL, '2026-02-09 13:51:53', '2026-02-09 13:51:53'),
(9, 5, NULL, 'CDO-CORNED-150', 'CDO Corned Beef', 'CDO', 2, 42.00, '150g can', 24, 2, 1, NULL, NULL, '2026-02-09 13:51:53', '2026-02-09 13:51:53'),
(10, 2, NULL, 'DOVE-SOAP-135', 'Dove Beauty Bar', 'Dove', 6, 38.00, '135g bar', 12, 3, 1, NULL, NULL, '2026-02-09 13:51:53', '2026-02-09 13:51:53'),
(11, 2, NULL, 'KNORR-CUBE-60', 'Knorr Bouillon Cubes', 'Knorr', 6, 28.00, '60g pack', 24, 3, 1, NULL, NULL, '2026-02-09 13:51:53', '2026-02-09 13:51:53');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(1000) NOT NULL,
  `img_name` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `role` enum('customer','grocery_admin') DEFAULT 'customer',
  `store_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `email`, `password`, `img_name`, `is_active`, `last_login`, `role`, `store_id`, `created_at`, `updated_at`) VALUES
(0, 'Admin Name', 'admin@store.com', '$2y$10$WNC4cFXciSjhHG4NLk/43e0DggwSj9IAgBBToqw9mkzDX52.6qPl6', NULL, 1, '2026-02-09 12:50:21', 'grocery_admin', 1, '2026-01-25 07:44:30', '2026-02-09 12:50:21'),
(1, 'Sample', 'sample@gmail.com', '$2y$10$WNC4cFXciSjhHG4NLk/43e0DggwSj9IAgBBToqw9mkzDX52.6qPl6', 'user_1_1769317418.png', 1, '2026-02-09 12:51:14', 'customer', NULL, '2026-01-25 01:19:59', '2026-02-09 12:51:14');

-- --------------------------------------------------------

--
-- Table structure for table `user_badges`
--

CREATE TABLE `user_badges` (
  `user_badge_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `badge_id` int(11) NOT NULL,
  `unlocked_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_points`
--

CREATE TABLE `user_points` (
  `user_id` int(11) NOT NULL,
  `total_points` int(11) DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_points`
--

INSERT INTO `user_points` (`user_id`, `total_points`, `last_updated`) VALUES
(1, 0, '2026-01-24 06:00:04');

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_grocery_inventory_details`
-- (See below for the actual view)
--
CREATE TABLE `v_grocery_inventory_details` (
`item_id` int(11)
,`item_name` varchar(255)
,`barcode` varchar(100)
,`sku` varchar(100)
,`quantity` decimal(10,2)
,`unit` varchar(50)
,`cost_price` decimal(10,2)
,`selling_price` decimal(10,2)
,`profit_per_unit` decimal(11,2)
,`total_profit` decimal(21,4)
,`expiry_date` date
,`expiry_status` enum('fresh','near_expiry','expired')
,`reorder_level` decimal(10,2)
,`catalog_product_name` varchar(255)
,`catalog_brand` varchar(255)
,`category_name` varchar(100)
,`supplier_name` varchar(255)
,`supplier_type` enum('manufacturer','distributor','wholesaler','local_supplier')
,`supplier_contact` varchar(50)
,`supplier_sku` varchar(100)
,`minimum_order_quantity` int(11)
,`lead_time_days` int(11)
,`po_number` varchar(50)
,`order_date` date
,`received_date` date
,`payment_status` enum('unpaid','partially_paid','paid')
,`store_name` varchar(255)
,`batch_number` varchar(100)
,`date_added` timestamp
,`last_updated` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_product_price_comparison`
-- (See below for the actual view)
--
CREATE TABLE `v_product_price_comparison` (
`catalog_id` int(11)
,`product_name` varchar(255)
,`brand` varchar(255)
,`category_name` varchar(100)
,`supplier_name` varchar(255)
,`supplier_type` enum('manufacturer','distributor','wholesaler','local_supplier')
,`supplier_sku` varchar(100)
,`unit_price` decimal(10,2)
,`unit_size` varchar(50)
,`minimum_order_quantity` int(11)
,`lead_time_days` int(11)
,`is_available` tinyint(1)
,`price_rank` bigint(21)
,`current_stock_from_supplier` decimal(32,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_reorder_suggestions`
-- (See below for the actual view)
--
CREATE TABLE `v_reorder_suggestions` (
`item_id` int(11)
,`item_name` varchar(255)
,`current_quantity` decimal(10,2)
,`reorder_level` decimal(10,2)
,`suggested_reorder_qty` decimal(10,2)
,`shortage` decimal(11,2)
,`current_supplier` varchar(255)
,`current_cost` decimal(10,2)
,`alternative_suppliers` mediumtext
,`best_available_price` decimal(10,2)
,`catalog_id` int(11)
,`store_id` int(11)
,`category_name` varchar(100)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_supplier_performance`
-- (See below for the actual view)
--
CREATE TABLE `v_supplier_performance` (
`supplier_id` int(11)
,`supplier_name` varchar(255)
,`supplier_type` enum('manufacturer','distributor','wholesaler','local_supplier')
,`supplier_rating` decimal(3,2)
,`total_purchase_orders` bigint(21)
,`total_items_supplied` bigint(21)
,`total_quantity_supplied` decimal(32,2)
,`total_purchase_value` decimal(42,4)
,`total_profit_generated` decimal(43,4)
,`avg_profit_per_unit` decimal(15,6)
,`avg_delivery_delay_days` decimal(10,4)
,`on_time_deliveries` bigint(21)
,`late_deliveries` bigint(21)
,`last_order_date` date
,`payment_terms` varchar(100)
,`is_active` tinyint(1)
);

-- --------------------------------------------------------

--
-- Structure for view `v_grocery_inventory_details`
--
DROP TABLE IF EXISTS `v_grocery_inventory_details`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_grocery_inventory_details`  AS SELECT `gi`.`item_id` AS `item_id`, `gi`.`item_name` AS `item_name`, `gi`.`barcode` AS `barcode`, `gi`.`sku` AS `sku`, `gi`.`quantity` AS `quantity`, `gi`.`unit` AS `unit`, `gi`.`cost_price` AS `cost_price`, `gi`.`selling_price` AS `selling_price`, `gi`.`selling_price`- `gi`.`cost_price` AS `profit_per_unit`, (`gi`.`selling_price` - `gi`.`cost_price`) * `gi`.`quantity` AS `total_profit`, `gi`.`expiry_date` AS `expiry_date`, `gi`.`expiry_status` AS `expiry_status`, `gi`.`reorder_level` AS `reorder_level`, `pc`.`product_name` AS `catalog_product_name`, `pc`.`brand` AS `catalog_brand`, `c`.`category_name` AS `category_name`, `s`.`supplier_name` AS `supplier_name`, `s`.`supplier_type` AS `supplier_type`, `s`.`contact_number` AS `supplier_contact`, `sp`.`supplier_sku` AS `supplier_sku`, `sp`.`minimum_order_quantity` AS `minimum_order_quantity`, `sp`.`lead_time_days` AS `lead_time_days`, `po`.`po_number` AS `po_number`, `po`.`order_date` AS `order_date`, `gi`.`received_date` AS `received_date`, `po`.`payment_status` AS `payment_status`, `gs`.`store_name` AS `store_name`, `gi`.`batch_number` AS `batch_number`, `gi`.`date_added` AS `date_added`, `gi`.`last_updated` AS `last_updated` FROM ((((((`grocery_items` `gi` left join `product_catalog` `pc` on(`gi`.`catalog_id` = `pc`.`catalog_id`)) left join `categories` `c` on(`gi`.`category_id` = `c`.`category_id`)) left join `suppliers` `s` on(`gi`.`supplier_id` = `s`.`supplier_id`)) left join `supplier_products` `sp` on(`gi`.`supplier_product_id` = `sp`.`supplier_product_id`)) left join `purchase_orders` `po` on(`gi`.`purchase_order_id` = `po`.`po_id`)) left join `grocery_stores` `gs` on(`gi`.`store_id` = `gs`.`store_id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `v_product_price_comparison`
--
DROP TABLE IF EXISTS `v_product_price_comparison`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_product_price_comparison`  AS SELECT `pc`.`catalog_id` AS `catalog_id`, `pc`.`product_name` AS `product_name`, `pc`.`brand` AS `brand`, `c`.`category_name` AS `category_name`, `s`.`supplier_name` AS `supplier_name`, `s`.`supplier_type` AS `supplier_type`, `sp`.`supplier_sku` AS `supplier_sku`, `sp`.`unit_price` AS `unit_price`, `sp`.`unit_size` AS `unit_size`, `sp`.`minimum_order_quantity` AS `minimum_order_quantity`, `sp`.`lead_time_days` AS `lead_time_days`, `sp`.`is_available` AS `is_available`, rank() over ( partition by `pc`.`catalog_id` order by `sp`.`unit_price`) AS `price_rank`, (select sum(`gi`.`quantity`) from `grocery_items` `gi` where `gi`.`catalog_id` = `pc`.`catalog_id` and `gi`.`supplier_id` = `s`.`supplier_id`) AS `current_stock_from_supplier` FROM (((`product_catalog` `pc` join `supplier_products` `sp` on(`pc`.`catalog_id` = `sp`.`catalog_id`)) join `suppliers` `s` on(`sp`.`supplier_id` = `s`.`supplier_id`)) left join `categories` `c` on(`pc`.`category_id` = `c`.`category_id`)) WHERE `sp`.`is_available` = 1 ORDER BY `pc`.`product_name` ASC, `sp`.`unit_price` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `v_reorder_suggestions`
--
DROP TABLE IF EXISTS `v_reorder_suggestions`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_reorder_suggestions`  AS SELECT `gi`.`item_id` AS `item_id`, `gi`.`item_name` AS `item_name`, `gi`.`quantity` AS `current_quantity`, `gi`.`reorder_level` AS `reorder_level`, `gi`.`reorder_quantity` AS `suggested_reorder_qty`, `gi`.`reorder_level`- `gi`.`quantity` AS `shortage`, `s`.`supplier_name` AS `current_supplier`, `gi`.`cost_price` AS `current_cost`, (select group_concat(concat(`s2`.`supplier_name`,': ₱',`sp2`.`unit_price`,' (Min: ',`sp2`.`minimum_order_quantity`,')') separator ' | ') from (`supplier_products` `sp2` join `suppliers` `s2` on(`sp2`.`supplier_id` = `s2`.`supplier_id`)) where `sp2`.`catalog_id` = `gi`.`catalog_id` and `sp2`.`is_available` = 1 order by `sp2`.`unit_price`) AS `alternative_suppliers`, (select min(`sp3`.`unit_price`) from `supplier_products` `sp3` where `sp3`.`catalog_id` = `gi`.`catalog_id` and `sp3`.`is_available` = 1) AS `best_available_price`, `gi`.`catalog_id` AS `catalog_id`, `gi`.`store_id` AS `store_id`, `c`.`category_name` AS `category_name` FROM ((`grocery_items` `gi` left join `suppliers` `s` on(`gi`.`supplier_id` = `s`.`supplier_id`)) left join `categories` `c` on(`gi`.`category_id` = `c`.`category_id`)) WHERE `gi`.`quantity` <= `gi`.`reorder_level` ;

-- --------------------------------------------------------

--
-- Structure for view `v_supplier_performance`
--
DROP TABLE IF EXISTS `v_supplier_performance`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_supplier_performance`  AS SELECT `s`.`supplier_id` AS `supplier_id`, `s`.`supplier_name` AS `supplier_name`, `s`.`supplier_type` AS `supplier_type`, `s`.`rating` AS `supplier_rating`, count(distinct `po`.`po_id`) AS `total_purchase_orders`, count(distinct `gi`.`item_id`) AS `total_items_supplied`, sum(`gi`.`quantity`) AS `total_quantity_supplied`, sum(`gi`.`cost_price` * `gi`.`quantity`) AS `total_purchase_value`, sum((`gi`.`selling_price` - `gi`.`cost_price`) * `gi`.`quantity`) AS `total_profit_generated`, avg(`gi`.`selling_price` - `gi`.`cost_price`) AS `avg_profit_per_unit`, avg(to_days(`po`.`actual_delivery_date`) - to_days(`po`.`expected_delivery_date`)) AS `avg_delivery_delay_days`, count(distinct case when `po`.`actual_delivery_date` <= `po`.`expected_delivery_date` then `po`.`po_id` end) AS `on_time_deliveries`, count(distinct case when `po`.`actual_delivery_date` > `po`.`expected_delivery_date` then `po`.`po_id` end) AS `late_deliveries`, max(`po`.`order_date`) AS `last_order_date`, `s`.`payment_terms` AS `payment_terms`, `s`.`is_active` AS `is_active` FROM ((`suppliers` `s` left join `purchase_orders` `po` on(`s`.`supplier_id` = `po`.`supplier_id`)) left join `grocery_items` `gi` on(`gi`.`purchase_order_id` = `po`.`po_id`)) GROUP BY `s`.`supplier_id` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `badges`
--
ALTER TABLE `badges`
  ADD PRIMARY KEY (`badge_id`);

--
-- Indexes for table `barcode_scan_history`
--
ALTER TABLE `barcode_scan_history`
  ADD PRIMARY KEY (`scan_id`),
  ADD KEY `idx_barcode_scan_user` (`user_id`),
  ADD KEY `idx_barcode_scan_date` (`scan_date`),
  ADD KEY `idx_barcode_scan_barcode` (`barcode`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `category_name` (`category_name`);

--
-- Indexes for table `customer_inventory_updates`
--
ALTER TABLE `customer_inventory_updates`
  ADD PRIMARY KEY (`update_id`),
  ADD KEY `idx_customer_updates_item` (`item_id`),
  ADD KEY `idx_customer_updates_user` (`updated_by`),
  ADD KEY `idx_customer_updates_date` (`update_date`);

--
-- Indexes for table `customer_items`
--
ALTER TABLE `customer_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `idx_customer_items_created_by` (`created_by`),
  ADD KEY `idx_customer_items_group` (`group_id`),
  ADD KEY `idx_customer_items_category` (`category_id`),
  ADD KEY `idx_customer_items_catalog` (`catalog_id`),
  ADD KEY `idx_customer_items_expiry_status` (`expiry_status`),
  ADD KEY `idx_customer_items_expiry_date` (`expiry_date`),
  ADD KEY `idx_customer_items_alert_flag` (`alert_flag`),
  ADD KEY `idx_customer_items_barcode` (`barcode`);

--
-- Indexes for table `grocery_inventory_updates`
--
ALTER TABLE `grocery_inventory_updates`
  ADD PRIMARY KEY (`update_id`),
  ADD KEY `idx_grocery_updates_item` (`item_id`),
  ADD KEY `idx_grocery_updates_user` (`updated_by`),
  ADD KEY `idx_grocery_updates_store` (`store_id`),
  ADD KEY `idx_grocery_updates_date` (`update_date`);

--
-- Indexes for table `grocery_items`
--
ALTER TABLE `grocery_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `idx_grocery_items_created_by` (`created_by`),
  ADD KEY `idx_grocery_items_category` (`category_id`),
  ADD KEY `idx_grocery_items_supplier` (`supplier_id`),
  ADD KEY `idx_grocery_items_catalog` (`catalog_id`),
  ADD KEY `idx_grocery_items_expiry_status` (`expiry_status`),
  ADD KEY `idx_grocery_items_expiry_date` (`expiry_date`),
  ADD KEY `idx_grocery_items_alert_flag` (`alert_flag`),
  ADD KEY `idx_grocery_items_sku` (`sku`),
  ADD KEY `idx_grocery_items_barcode` (`barcode`),
  ADD KEY `idx_grocery_items_store` (`store_id`),
  ADD KEY `idx_grocery_items_supplier_product` (`supplier_product_id`),
  ADD KEY `idx_grocery_items_purchase_order` (`purchase_order_id`),
  ADD KEY `idx_grocery_items_batch` (`batch_number`),
  ADD KEY `idx_grocery_items_received_date` (`received_date`);

--
-- Indexes for table `grocery_stores`
--
ALTER TABLE `grocery_stores`
  ADD PRIMARY KEY (`store_id`),
  ADD UNIQUE KEY `store_name` (`store_name`),
  ADD KEY `idx_store_active` (`is_active`);

--
-- Indexes for table `groups`
--
ALTER TABLE `groups`
  ADD PRIMARY KEY (`group_id`),
  ADD UNIQUE KEY `invitation_code` (`invitation_code`),
  ADD KEY `idx_groups_created_by` (`created_by`);

--
-- Indexes for table `group_members`
--
ALTER TABLE `group_members`
  ADD PRIMARY KEY (`member_id`),
  ADD UNIQUE KEY `unique_group_user` (`group_id`,`user_id`),
  ADD KEY `idx_group_members_user` (`user_id`),
  ADD KEY `idx_group_members_group` (`group_id`);

--
-- Indexes for table `points_log`
--
ALTER TABLE `points_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_points_log_user` (`user_id`),
  ADD KEY `idx_points_log_date` (`action_date`);

--
-- Indexes for table `product_catalog`
--
ALTER TABLE `product_catalog`
  ADD PRIMARY KEY (`catalog_id`),
  ADD UNIQUE KEY `barcode` (`barcode`),
  ADD KEY `idx_product_catalog_category` (`category_id`),
  ADD KEY `idx_product_catalog_barcode` (`barcode`);

--
-- Indexes for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`po_id`),
  ADD UNIQUE KEY `po_number` (`po_number`),
  ADD KEY `idx_po_store` (`store_id`),
  ADD KEY `idx_po_supplier` (`supplier_id`),
  ADD KEY `idx_po_status` (`status`),
  ADD KEY `idx_po_order_date` (`order_date`),
  ADD KEY `fk_po_created_by` (`created_by`);

--
-- Indexes for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD PRIMARY KEY (`po_item_id`),
  ADD KEY `idx_po_items_po` (`po_id`),
  ADD KEY `idx_po_items_product` (`supplier_product_id`);

--
-- Indexes for table `store_suppliers`
--
ALTER TABLE `store_suppliers`
  ADD PRIMARY KEY (`store_supplier_id`),
  ADD UNIQUE KEY `unique_store_supplier` (`store_id`,`supplier_id`),
  ADD KEY `idx_store_suppliers_store` (`store_id`),
  ADD KEY `idx_store_suppliers_supplier` (`supplier_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`supplier_id`),
  ADD KEY `idx_supplier_name` (`supplier_name`),
  ADD KEY `idx_supplier_type` (`supplier_type`),
  ADD KEY `idx_supplier_active` (`is_active`);

--
-- Indexes for table `supplier_products`
--
ALTER TABLE `supplier_products`
  ADD PRIMARY KEY (`supplier_product_id`),
  ADD KEY `idx_supplier_products_supplier` (`supplier_id`),
  ADD KEY `idx_supplier_products_catalog` (`catalog_id`),
  ADD KEY `idx_supplier_products_category` (`category_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_email` (`email`),
  ADD KEY `idx_users_role` (`role`),
  ADD KEY `idx_users_store` (`store_id`);

--
-- Indexes for table `user_badges`
--
ALTER TABLE `user_badges`
  ADD PRIMARY KEY (`user_badge_id`),
  ADD UNIQUE KEY `unique_user_badge` (`user_id`,`badge_id`),
  ADD KEY `idx_user_badges_user` (`user_id`),
  ADD KEY `idx_user_badges_badge` (`badge_id`);

--
-- Indexes for table `user_points`
--
ALTER TABLE `user_points`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `badges`
--
ALTER TABLE `badges`
  MODIFY `badge_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `barcode_scan_history`
--
ALTER TABLE `barcode_scan_history`
  MODIFY `scan_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=126;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `customer_inventory_updates`
--
ALTER TABLE `customer_inventory_updates`
  MODIFY `update_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_items`
--
ALTER TABLE `customer_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `grocery_inventory_updates`
--
ALTER TABLE `grocery_inventory_updates`
  MODIFY `update_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `grocery_items`
--
ALTER TABLE `grocery_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `grocery_stores`
--
ALTER TABLE `grocery_stores`
  MODIFY `store_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `groups`
--
ALTER TABLE `groups`
  MODIFY `group_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `group_members`
--
ALTER TABLE `group_members`
  MODIFY `member_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `points_log`
--
ALTER TABLE `points_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_catalog`
--
ALTER TABLE `product_catalog`
  MODIFY `catalog_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `po_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  MODIFY `po_item_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `store_suppliers`
--
ALTER TABLE `store_suppliers`
  MODIFY `store_supplier_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `supplier_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `supplier_products`
--
ALTER TABLE `supplier_products`
  MODIFY `supplier_product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `user_badges`
--
ALTER TABLE `user_badges`
  MODIFY `user_badge_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `barcode_scan_history`
--
ALTER TABLE `barcode_scan_history`
  ADD CONSTRAINT `fk_barcode_scan_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `customer_inventory_updates`
--
ALTER TABLE `customer_inventory_updates`
  ADD CONSTRAINT `fk_customer_updates_item` FOREIGN KEY (`item_id`) REFERENCES `customer_items` (`item_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_customer_updates_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `customer_items`
--
ALTER TABLE `customer_items`
  ADD CONSTRAINT `fk_customer_items_catalog` FOREIGN KEY (`catalog_id`) REFERENCES `product_catalog` (`catalog_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_customer_items_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`),
  ADD CONSTRAINT `fk_customer_items_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_customer_items_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`group_id`) ON DELETE CASCADE;

--
-- Constraints for table `grocery_inventory_updates`
--
ALTER TABLE `grocery_inventory_updates`
  ADD CONSTRAINT `fk_grocery_updates_item` FOREIGN KEY (`item_id`) REFERENCES `grocery_items` (`item_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_grocery_updates_store` FOREIGN KEY (`store_id`) REFERENCES `grocery_stores` (`store_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_grocery_updates_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `grocery_items`
--
ALTER TABLE `grocery_items`
  ADD CONSTRAINT `fk_grocery_items_catalog` FOREIGN KEY (`catalog_id`) REFERENCES `product_catalog` (`catalog_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_grocery_items_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`),
  ADD CONSTRAINT `fk_grocery_items_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `fk_grocery_items_purchase_order` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`po_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_grocery_items_store` FOREIGN KEY (`store_id`) REFERENCES `grocery_stores` (`store_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_grocery_items_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_grocery_items_supplier_product` FOREIGN KEY (`supplier_product_id`) REFERENCES `supplier_products` (`supplier_product_id`) ON DELETE SET NULL;

--
-- Constraints for table `groups`
--
ALTER TABLE `groups`
  ADD CONSTRAINT `fk_groups_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `group_members`
--
ALTER TABLE `group_members`
  ADD CONSTRAINT `fk_group_members_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`group_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_group_members_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `points_log`
--
ALTER TABLE `points_log`
  ADD CONSTRAINT `fk_points_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `product_catalog`
--
ALTER TABLE `product_catalog`
  ADD CONSTRAINT `fk_product_catalog_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE SET NULL;

--
-- Constraints for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD CONSTRAINT `fk_po_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `fk_po_store` FOREIGN KEY (`store_id`) REFERENCES `grocery_stores` (`store_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_po_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`);

--
-- Constraints for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD CONSTRAINT `fk_po_items_po` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`po_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_po_items_product` FOREIGN KEY (`supplier_product_id`) REFERENCES `supplier_products` (`supplier_product_id`) ON DELETE SET NULL;

--
-- Constraints for table `store_suppliers`
--
ALTER TABLE `store_suppliers`
  ADD CONSTRAINT `fk_store_suppliers_store` FOREIGN KEY (`store_id`) REFERENCES `grocery_stores` (`store_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_store_suppliers_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON DELETE CASCADE;

--
-- Constraints for table `supplier_products`
--
ALTER TABLE `supplier_products`
  ADD CONSTRAINT `fk_supplier_products_catalog` FOREIGN KEY (`catalog_id`) REFERENCES `product_catalog` (`catalog_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_supplier_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_supplier_products_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_store` FOREIGN KEY (`store_id`) REFERENCES `grocery_stores` (`store_id`) ON DELETE SET NULL;

--
-- Constraints for table `user_badges`
--
ALTER TABLE `user_badges`
  ADD CONSTRAINT `fk_user_badges_badge` FOREIGN KEY (`badge_id`) REFERENCES `badges` (`badge_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_user_badges_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_points`
--
ALTER TABLE `user_points`
  ADD CONSTRAINT `fk_user_points_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
