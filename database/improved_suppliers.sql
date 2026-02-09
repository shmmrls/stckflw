-- ============================================================
-- IMPROVED SUPPLIERS STRUCTURE FOR GROCERY STORES
-- ============================================================
-- 
-- In a grocery store context:
-- - SUPPLIERS = Companies that supply products TO the store
-- - Examples: Nestle, Unilever, local distributors, wholesalers
-- - Stores order FROM suppliers to stock their inventory
-- ============================================================

-- Drop old suppliers table
DROP TABLE IF EXISTS `store_suppliers`;
DROP TABLE IF EXISTS `suppliers`;

-- --------------------------------------------------------

--
-- Improved Suppliers Table
--

CREATE TABLE `suppliers` (
  `supplier_id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`supplier_id`),
  KEY `idx_supplier_name` (`supplier_name`),
  KEY `idx_supplier_type` (`supplier_type`),
  KEY `idx_supplier_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Store-Supplier Relationship Table
-- (Many-to-Many: A store can have multiple suppliers, a supplier can supply to multiple stores)
--

CREATE TABLE `store_suppliers` (
  `store_supplier_id` int(11) NOT NULL AUTO_INCREMENT,
  `store_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `preferred_supplier` tinyint(1) DEFAULT 0 COMMENT 'Mark as preferred for automatic ordering',
  `credit_limit` decimal(10,2) DEFAULT NULL,
  `current_balance` decimal(10,2) DEFAULT 0.00,
  `last_order_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`store_supplier_id`),
  UNIQUE KEY `unique_store_supplier` (`store_id`,`supplier_id`),
  KEY `idx_store_suppliers_store` (`store_id`),
  KEY `idx_store_suppliers_supplier` (`supplier_id`),
  CONSTRAINT `fk_store_suppliers_store` FOREIGN KEY (`store_id`) REFERENCES `grocery_stores` (`store_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_store_suppliers_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Supplier Products Table
-- (Track which products each supplier can provide with their pricing)
--

CREATE TABLE `supplier_products` (
  `supplier_product_id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_id` int(11) NOT NULL,
  `catalog_id` int(11) DEFAULT NULL COMMENT 'Reference to product_catalog',
  `supplier_sku` varchar(100) DEFAULT NULL COMMENT 'Supplier\'s product code',
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`supplier_product_id`),
  KEY `idx_supplier_products_supplier` (`supplier_id`),
  KEY `idx_supplier_products_catalog` (`catalog_id`),
  KEY `idx_supplier_products_category` (`category_id`),
  CONSTRAINT `fk_supplier_products_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_supplier_products_catalog` FOREIGN KEY (`catalog_id`) REFERENCES `product_catalog` (`catalog_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_supplier_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Purchase Orders Table
-- (Track orders placed to suppliers)
--

CREATE TABLE `purchase_orders` (
  `po_id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`po_id`),
  UNIQUE KEY `po_number` (`po_number`),
  KEY `idx_po_store` (`store_id`),
  KEY `idx_po_supplier` (`supplier_id`),
  KEY `idx_po_status` (`status`),
  KEY `idx_po_order_date` (`order_date`),
  CONSTRAINT `fk_po_store` FOREIGN KEY (`store_id`) REFERENCES `grocery_stores` (`store_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_po_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_po_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Purchase Order Items Table
-- (Line items in each purchase order)
--

CREATE TABLE `purchase_order_items` (
  `po_item_id` int(11) NOT NULL AUTO_INCREMENT,
  `po_id` int(11) NOT NULL,
  `supplier_product_id` int(11) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `quantity_ordered` decimal(10,2) NOT NULL,
  `quantity_received` decimal(10,2) DEFAULT 0.00,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) GENERATED ALWAYS AS (`quantity_ordered` * `unit_price`) STORED,
  `expiry_date` date DEFAULT NULL COMMENT 'Expected expiry date of received items',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`po_item_id`),
  KEY `idx_po_items_po` (`po_id`),
  KEY `idx_po_items_product` (`supplier_product_id`),
  CONSTRAINT `fk_po_items_po` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`po_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_po_items_product` FOREIGN KEY (`supplier_product_id`) REFERENCES `supplier_products` (`supplier_product_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Sample Data for Suppliers
--

INSERT INTO `suppliers` (`supplier_name`, `supplier_type`, `company_name`, `contact_person`, `contact_number`, `email`, `address`, `payment_terms`, `minimum_order_amount`, `delivery_schedule`, `is_active`, `rating`) VALUES
('Nestle Philippines', 'manufacturer', 'Nestle Philippines Inc.', 'John Dela Cruz', '+63 2 8891 0000', 'orders@nestle.ph', 'Alabang, Muntinlupa City', 'Net 30', 10000.00, 'Mon/Wed/Fri', 1, 4.5),
('Unilever Philippines', 'manufacturer', 'Unilever Philippines Inc.', 'Maria Santos', '+63 2 8857 5000', 'orders@unilever.ph', 'Bonifacio Global City, Taguig', 'Net 30', 15000.00, 'Tue/Thu', 1, 4.7),
('Alaska Milk Corporation', 'manufacturer', 'Alaska Milk Corporation', 'Pedro Reyes', '+63 2 8359 3333', 'sales@alaskamilk.com', 'San Pedro, Laguna', 'Net 45', 8000.00, 'Daily', 1, 4.8),
('San Miguel Foods', 'manufacturer', 'San Miguel Foods Inc.', 'Ana Garcia', '+63 2 8632 3000', 'orders@smf.com.ph', 'Mandaluyong City', 'Net 30', 12000.00, 'Mon/Wed/Fri', 1, 4.6),
('CDO Foodsphere', 'manufacturer', 'CDO Foodsphere Inc.', 'Carlos Tan', '+63 2 8631 8000', 'sales@cdo.com.ph', 'Valenzuela City', 'Net 30', 9000.00, 'Tue/Thu/Sat', 1, 4.4),
('Metro Manila Distributors', 'distributor', 'Metro Manila Distributors Corp.', 'Roberto Cruz', '+63 917 123 4567', 'info@mmdistributors.com', 'Quezon City', 'Net 15', 5000.00, 'Daily', 1, 4.2),
('Mega Prime Foods', 'distributor', 'Mega Prime Foods Distribution', 'Lisa Mendoza', '+63 918 234 5678', 'orders@megaprime.ph', 'Pasig City', 'COD', 3000.00, 'Mon-Sat', 1, 4.3),
('Golden Harvest Wholesale', 'wholesaler', 'Golden Harvest Wholesale Inc.', 'Richard Lim', '+63 919 345 6789', 'wholesale@goldenharvest.ph', 'Makati City', 'Net 7', 7000.00, 'Weekly', 1, 4.0),
('Fresh Produce Suppliers PH', 'local_supplier', 'Fresh Produce Suppliers', 'Emma Rodriguez', '+63 920 456 7890', 'fresh@produce.ph', 'Baguio City', 'COD', 2000.00, 'Daily', 1, 4.5),
('Local Bakery Supplies', 'local_supplier', 'Local Bakery Supplies Co.', 'Mark Fernandez', '+63 921 567 8901', 'bakery@supplies.ph', 'Manila', 'Net 7', 1500.00, 'Daily', 1, 4.1);

-- --------------------------------------------------------

--
-- Sample Supplier Products
--

INSERT INTO `supplier_products` (`supplier_id`, `supplier_sku`, `product_name`, `brand`, `category_id`, `unit_price`, `unit_size`, `minimum_order_quantity`, `lead_time_days`, `is_available`) VALUES
-- Alaska Milk Corporation products
(3, 'ALASKA-EVAP-370', 'Alaska Evaporated Milk', 'Alaska', 1, 42.50, '370ml can', 24, 2, 1),
(3, 'ALASKA-COND-300', 'Alaska Condensed Milk', 'Alaska', 1, 48.00, '300ml can', 24, 2, 1),
(3, 'ALASKA-POWDER-150', 'Alaska Powdered Milk', 'Alaska', 1, 125.00, '150g pack', 12, 2, 1),

-- Nestle products
(1, 'NESTLE-BEAR-300', 'Bear Brand Milk', 'Bear Brand', 1, 38.00, '300ml can', 24, 3, 1),
(1, 'NESTLE-MILO-1KG', 'Milo Chocolate Drink', 'Milo', 5, 285.00, '1kg pack', 6, 3, 1),
(1, 'NESTLE-NESCAFE-200', 'Nescafe Classic', 'Nescafe', 5, 165.00, '200g jar', 12, 3, 1),

-- CDO products
(5, 'CDO-HOTDOG-500', 'CDO Hotdog', 'CDO', 2, 115.00, '500g pack', 10, 2, 1),
(5, 'CDO-TOCINO-500', 'CDO Sweet Tocino', 'CDO', 2, 125.00, '500g pack', 10, 2, 1),
(5, 'CDO-CORNED-150', 'CDO Corned Beef', 'CDO', 2, 42.00, '150g can', 24, 2, 1),

-- Unilever products
(2, 'DOVE-SOAP-135', 'Dove Beauty Bar', 'Dove', 6, 38.00, '135g bar', 12, 3, 1),
(2, 'KNORR-CUBE-60', 'Knorr Bouillon Cubes', 'Knorr', 6, 28.00, '60g pack', 24, 3, 1);

COMMIT;