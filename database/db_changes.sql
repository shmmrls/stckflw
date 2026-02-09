-- Add grocery_stores table for store registration
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

-- Add store_id to users table for grocery admins
ALTER TABLE `users` 
ADD COLUMN `store_id` int(11) DEFAULT NULL AFTER `role`,
ADD KEY `idx_users_store` (`store_id`),
ADD CONSTRAINT `fk_users_store` FOREIGN KEY (`store_id`) REFERENCES `grocery_stores` (`store_id`) ON DELETE SET NULL;

-- Add store_id to grocery_items table
ALTER TABLE `grocery_items`
ADD COLUMN `store_id` int(11) NOT NULL AFTER `created_by`,
ADD KEY `idx_grocery_items_store` (`store_id`),
ADD CONSTRAINT `fk_grocery_items_store` FOREIGN KEY (`store_id`) REFERENCES `grocery_stores` (`store_id`) ON DELETE CASCADE;

-- Update grocery_items to make supplier_id optional and add reorder_level
ALTER TABLE `grocery_items`
MODIFY COLUMN `supplier_id` int(11) DEFAULT NULL,
ADD COLUMN `reorder_level` decimal(10,2) DEFAULT 10.00 AFTER `selling_price`,
ADD COLUMN `reorder_quantity` decimal(10,2) DEFAULT 50.00 AFTER `reorder_level`;

-- Update grocery_inventory_updates to include store_id
ALTER TABLE `grocery_inventory_updates`
ADD COLUMN `store_id` int(11) NOT NULL AFTER `item_id`,
ADD KEY `idx_inventory_updates_store` (`store_id`),
ADD CONSTRAINT `fk_inventory_updates_store` FOREIGN KEY (`store_id`) REFERENCES `grocery_stores` (`store_id`) ON DELETE CASCADE;

-- Create view for grocery store dashboard
CREATE OR REPLACE VIEW `grocery_store_dashboard` AS
SELECT 
    gs.store_id,
    gs.store_name,
    COUNT(DISTINCT gi.item_id) as total_items,
    SUM(CASE WHEN gi.expiry_status = 'near_expiry' THEN 1 ELSE 0 END) as near_expiry_items,
    SUM(CASE WHEN gi.expiry_status = 'expired' THEN 1 ELSE 0 END) as expired_items,
    SUM(CASE WHEN gi.quantity <= gi.reorder_level THEN 1 ELSE 0 END) as low_stock_items,
    SUM(gi.quantity) as total_quantity,
    SUM(gi.cost_price * gi.quantity) as total_inventory_value
FROM grocery_stores gs
LEFT JOIN grocery_items gi ON gs.store_id = gi.store_id
GROUP BY gs.store_id, gs.store_name;

-- Create view for inventory alerts
CREATE OR REPLACE VIEW `grocery_inventory_alerts` AS
SELECT 
    gi.item_id,
    gi.store_id,
    gs.store_name,
    gi.item_name,
    gi.quantity,
    gi.reorder_level,
    gi.expiry_date,
    gi.expiry_status,
    CASE 
        WHEN gi.quantity <= gi.reorder_level THEN 'LOW_STOCK'
        WHEN gi.expiry_status = 'expired' THEN 'EXPIRED'
        WHEN gi.expiry_status = 'near_expiry' THEN 'NEAR_EXPIRY'
        ELSE 'OK'
    END as alert_type,
    DATEDIFF(gi.expiry_date, CURDATE()) as days_until_expiry
FROM grocery_items gi
JOIN grocery_stores gs ON gi.store_id = gs.store_id
WHERE gi.quantity <= gi.reorder_level 
   OR gi.expiry_status IN ('near_expiry', 'expired')
ORDER BY 
    CASE 
        WHEN gi.expiry_status = 'expired' THEN 1
        WHEN gi.expiry_status = 'near_expiry' THEN 2
        WHEN gi.quantity <= gi.reorder_level THEN 3
        ELSE 4
    END,
    gi.expiry_date ASC;

-- Create trigger to check and update expiry status
DELIMITER $$
CREATE TRIGGER `before_grocery_item_update`
BEFORE UPDATE ON `grocery_items`
FOR EACH ROW
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

-- Create trigger for new grocery items
DELIMITER $$
CREATE TRIGGER `before_grocery_item_insert`
BEFORE INSERT ON `grocery_items`
FOR EACH ROW
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

-- Sample data for testing (optional)
INSERT INTO `grocery_stores` (`store_name`, `business_address`, `contact_number`, `email`, `is_verified`, `is_active`) 
VALUES 
('Sample Grocery Store', '123 Main Street, Manila', '+63 912 345 6789', 'store@sample.com', 1, 1);

-- Note: After creating a store, create admin user with:
-- INSERT INTO users (full_name, email, password, role, store_id) 
-- VALUES ('Admin Name', 'admin@store.com', 'hashed_password', 'grocery_admin', 1);