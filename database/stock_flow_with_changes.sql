-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 25, 2026 at 08:46 AM
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
(1, 'Newbie Organizer', 'Added your first 5 items', NULL, 25, '2026-01-23 16:10:34'),
(2, 'Waste Warrior', 'Logged 20 items before expiry', NULL, 100, '2026-01-23 16:10:34'),
(3, 'Inventory Master', 'Maintained inventory for 30 days', NULL, 200, '2026-01-23 16:10:34');

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
(118, 1, '4800024405014', 'lookup', 1, NULL, '2026-01-25 04:51:11');

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
-- Stand-in structure for view `customer_dashboard_summary`
-- (See below for the actual view)
--
CREATE TABLE `customer_dashboard_summary` (
`group_id` int(11)
,`group_name` varchar(255)
,`total_items` bigint(21)
,`near_expiry_items` decimal(22,0)
,`expired_items` decimal(22,0)
,`total_quantity` decimal(32,2)
);

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

-- --------------------------------------------------------

--
-- Stand-in structure for view `grocery_dashboard_summary`
-- (See below for the actual view)
--
CREATE TABLE `grocery_dashboard_summary` (
`total_items` bigint(21)
,`near_expiry_items` decimal(22,0)
,`expired_items` decimal(22,0)
,`total_quantity` decimal(32,2)
,`total_inventory_value` decimal(42,4)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `grocery_inventory_alerts`
-- (See below for the actual view)
--
CREATE TABLE `grocery_inventory_alerts` (
`item_id` int(11)
,`store_id` int(11)
,`store_name` varchar(255)
,`item_name` varchar(255)
,`quantity` decimal(10,2)
,`reorder_level` decimal(10,2)
,`expiry_date` date
,`expiry_status` enum('fresh','near_expiry','expired')
,`alert_type` varchar(11)
,`days_until_expiry` int(7)
);

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
-- Stand-in structure for view `grocery_store_dashboard`
-- (See below for the actual view)
--
CREATE TABLE `grocery_store_dashboard` (
`store_id` int(11)
,`store_name` varchar(255)
,`total_items` bigint(21)
,`near_expiry_items` decimal(22,0)
,`expired_items` decimal(22,0)
,`low_stock_items` decimal(22,0)
,`total_quantity` decimal(32,2)
,`total_inventory_value` decimal(42,4)
);

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
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `supplier_id` int(11) NOT NULL,
  `supplier_name` varchar(255) NOT NULL,
  `supplier_type` enum('grocery_store','market_vendor','distributor') NOT NULL,
  `contact_info` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(0, 'Admin Name', 'admin@store.com', 'hashed_password', NULL, 1, NULL, 'grocery_admin', 1, '2026-01-25 07:44:30', '2026-01-25 07:44:30'),
(1, 'Sample', 'sample@gmail.com', '$2y$10$WNC4cFXciSjhHG4NLk/43e0DggwSj9IAgBBToqw9mkzDX52.6qPl6', 'user_1_1769317418.png', 1, '2026-01-25 06:28:02', 'customer', NULL, '2026-01-25 01:19:59', '2026-01-25 06:28:02');

-- --------------------------------------------------------

--
-- Stand-in structure for view `user_activity`
-- (See below for the actual view)
--
CREATE TABLE `user_activity` (
`user_id` int(11)
,`full_name` varchar(255)
,`total_points` int(11)
,`badges_unlocked` bigint(21)
,`items_added` bigint(21)
);

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
-- Structure for view `customer_dashboard_summary`
--
DROP TABLE IF EXISTS `customer_dashboard_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `customer_dashboard_summary`  AS SELECT `g`.`group_id` AS `group_id`, `g`.`group_name` AS `group_name`, count(`ci`.`item_id`) AS `total_items`, sum(case when `ci`.`expiry_status` = 'near_expiry' then 1 else 0 end) AS `near_expiry_items`, sum(case when `ci`.`expiry_status` = 'expired' then 1 else 0 end) AS `expired_items`, sum(`ci`.`quantity`) AS `total_quantity` FROM (`groups` `g` left join `customer_items` `ci` on(`g`.`group_id` = `ci`.`group_id`)) GROUP BY `g`.`group_id`, `g`.`group_name` ;

-- --------------------------------------------------------

--
-- Structure for view `grocery_dashboard_summary`
--
DROP TABLE IF EXISTS `grocery_dashboard_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `grocery_dashboard_summary`  AS SELECT count(`gi`.`item_id`) AS `total_items`, sum(case when `gi`.`expiry_status` = 'near_expiry' then 1 else 0 end) AS `near_expiry_items`, sum(case when `gi`.`expiry_status` = 'expired' then 1 else 0 end) AS `expired_items`, sum(`gi`.`quantity`) AS `total_quantity`, sum(`gi`.`cost_price` * `gi`.`quantity`) AS `total_inventory_value` FROM `grocery_items` AS `gi` ;

-- --------------------------------------------------------

--
-- Structure for view `grocery_inventory_alerts`
--
DROP TABLE IF EXISTS `grocery_inventory_alerts`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `grocery_inventory_alerts`  AS SELECT `gi`.`item_id` AS `item_id`, `gi`.`store_id` AS `store_id`, `gs`.`store_name` AS `store_name`, `gi`.`item_name` AS `item_name`, `gi`.`quantity` AS `quantity`, `gi`.`reorder_level` AS `reorder_level`, `gi`.`expiry_date` AS `expiry_date`, `gi`.`expiry_status` AS `expiry_status`, CASE WHEN `gi`.`quantity` <= `gi`.`reorder_level` THEN 'LOW_STOCK' WHEN `gi`.`expiry_status` = 'expired' THEN 'EXPIRED' WHEN `gi`.`expiry_status` = 'near_expiry' THEN 'NEAR_EXPIRY' ELSE 'OK' END AS `alert_type`, to_days(`gi`.`expiry_date`) - to_days(curdate()) AS `days_until_expiry` FROM (`grocery_items` `gi` join `grocery_stores` `gs` on(`gi`.`store_id` = `gs`.`store_id`)) WHERE `gi`.`quantity` <= `gi`.`reorder_level` OR `gi`.`expiry_status` in ('near_expiry','expired') ORDER BY CASE WHEN `gi`.`expiry_status` = 'expired' THEN 1 WHEN `gi`.`expiry_status` = 'near_expiry' THEN 2 WHEN `gi`.`quantity` <= `gi`.`reorder_level` THEN 3 ELSE 4 END ASC, `gi`.`expiry_date` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `grocery_store_dashboard`
--
DROP TABLE IF EXISTS `grocery_store_dashboard`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `grocery_store_dashboard`  AS SELECT `gs`.`store_id` AS `store_id`, `gs`.`store_name` AS `store_name`, count(distinct `gi`.`item_id`) AS `total_items`, sum(case when `gi`.`expiry_status` = 'near_expiry' then 1 else 0 end) AS `near_expiry_items`, sum(case when `gi`.`expiry_status` = 'expired' then 1 else 0 end) AS `expired_items`, sum(case when `gi`.`quantity` <= `gi`.`reorder_level` then 1 else 0 end) AS `low_stock_items`, sum(`gi`.`quantity`) AS `total_quantity`, sum(`gi`.`cost_price` * `gi`.`quantity`) AS `total_inventory_value` FROM (`grocery_stores` `gs` left join `grocery_items` `gi` on(`gs`.`store_id` = `gi`.`store_id`)) GROUP BY `gs`.`store_id`, `gs`.`store_name` ;

-- --------------------------------------------------------

--
-- Structure for view `user_activity`
--
DROP TABLE IF EXISTS `user_activity`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `user_activity`  AS SELECT `u`.`user_id` AS `user_id`, `u`.`full_name` AS `full_name`, coalesce(`up`.`total_points`,0) AS `total_points`, count(distinct `ub`.`badge_id`) AS `badges_unlocked`, count(distinct `ci`.`item_id`) AS `items_added` FROM (((`users` `u` left join `user_points` `up` on(`u`.`user_id` = `up`.`user_id`)) left join `user_badges` `ub` on(`u`.`user_id` = `ub`.`user_id`)) left join `customer_items` `ci` on(`u`.`user_id` = `ci`.`created_by`)) WHERE `u`.`role` = 'customer' GROUP BY `u`.`user_id`, `u`.`full_name`, `up`.`total_points` ;

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
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_barcode_scan_date` (`scan_date`);

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
  ADD KEY `item_id` (`item_id`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `customer_items`
--
ALTER TABLE `customer_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_customer_items_group` (`group_id`),
  ADD KEY `idx_customer_items_category` (`category_id`),
  ADD KEY `idx_customer_items_expiry_status` (`expiry_status`),
  ADD KEY `idx_customer_items_expiry_date` (`expiry_date`),
  ADD KEY `idx_customer_items_alert_flag` (`alert_flag`),
  ADD KEY `idx_customer_items_barcode` (`barcode`);

--
-- Indexes for table `grocery_inventory_updates`
--
ALTER TABLE `grocery_inventory_updates`
  ADD PRIMARY KEY (`update_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_inventory_updates_store` (`store_id`);

--
-- Indexes for table `grocery_items`
--
ALTER TABLE `grocery_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_grocery_items_category` (`category_id`),
  ADD KEY `idx_grocery_items_supplier` (`supplier_id`),
  ADD KEY `idx_grocery_items_expiry_status` (`expiry_status`),
  ADD KEY `idx_grocery_items_expiry_date` (`expiry_date`),
  ADD KEY `idx_grocery_items_alert_flag` (`alert_flag`),
  ADD KEY `idx_grocery_items_sku` (`sku`),
  ADD KEY `idx_grocery_items_barcode` (`barcode`),
  ADD KEY `idx_grocery_items_store` (`store_id`);

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
  ADD KEY `created_by` (`created_by`);

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
  ADD KEY `item_id` (`item_id`),
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
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`supplier_id`);

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
  ADD KEY `badge_id` (`badge_id`);

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
  MODIFY `badge_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `barcode_scan_history`
--
ALTER TABLE `barcode_scan_history`
  MODIFY `scan_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=119;

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
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `supplier_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `grocery_inventory_updates`
--
ALTER TABLE `grocery_inventory_updates`
  ADD CONSTRAINT `fk_inventory_updates_store` FOREIGN KEY (`store_id`) REFERENCES `grocery_stores` (`store_id`) ON DELETE CASCADE;

--
-- Constraints for table `grocery_items`
--
ALTER TABLE `grocery_items`
  ADD CONSTRAINT `fk_grocery_items_store` FOREIGN KEY (`store_id`) REFERENCES `grocery_stores` (`store_id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_store` FOREIGN KEY (`store_id`) REFERENCES `grocery_stores` (`store_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
