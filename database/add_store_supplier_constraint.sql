-- Add database constraint to enforce store-supplier relationship in purchase orders
-- This ensures that a store can only create purchase orders from suppliers they are registered with

-- Step 1: Add a composite foreign key constraint that references store_suppliers
-- This will enforce that (store_id, supplier_id) combination exists in store_suppliers

-- First, we need to drop the existing separate foreign key constraints
ALTER TABLE purchase_orders 
DROP FOREIGN KEY fk_po_store,
DROP FOREIGN KEY fk_po_supplier;

-- Add the composite foreign key constraint to store_suppliers
ALTER TABLE purchase_orders 
ADD CONSTRAINT `fk_po_store_supplier` 
FOREIGN KEY (`store_id`, `supplier_id`) 
REFERENCES `store_suppliers` (`store_id`, `supplier_id`) 
ON DELETE CASCADE;

-- Step 2: Update the stored procedure to validate store-supplier relationship
DELIMITER $$

-- Drop the existing procedure if it exists
DROP PROCEDURE IF EXISTS `sp_generate_reorder_po`$$

-- Recreate the procedure with store-supplier validation
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_generate_reorder_po` (IN `p_store_id` INT, IN `p_supplier_id` INT, IN `p_created_by` INT, OUT `p_po_id` INT)  
BEGIN
    DECLARE v_po_number VARCHAR(50);
    DECLARE v_item_count INT;
    DECLARE v_store_supplier_exists INT;
    
    -- Check if store-supplier relationship exists
    SELECT COUNT(*) INTO v_store_supplier_exists
    FROM store_suppliers 
    WHERE store_id = p_store_id 
      AND supplier_id = p_supplier_id 
      AND is_active = 1;
    
    -- If relationship doesn't exist, raise an error
    IF v_store_supplier_exists = 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Store is not registered with this supplier or relationship is not active';
    END IF;
    
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

DELIMITER ;

-- Step 3: Create a trigger to validate store-supplier relationship for any direct INSERT/UPDATE operations
DELIMITER $$

-- Drop existing triggers if they exist
DROP TRIGGER IF EXISTS `before_purchase_order_insert`$$
DROP TRIGGER IF EXISTS `before_purchase_order_update`$$

-- Create BEFORE INSERT trigger
CREATE TRIGGER `before_purchase_order_insert`
BEFORE INSERT ON `purchase_orders`
FOR EACH ROW
BEGIN
    DECLARE v_store_supplier_exists INT;
    
    -- Check if store-supplier relationship exists
    SELECT COUNT(*) INTO v_store_supplier_exists
    FROM store_suppliers 
    WHERE store_id = NEW.store_id 
      AND supplier_id = NEW.supplier_id 
      AND is_active = 1;
    
    -- If relationship doesn't exist, block the insertion
    IF v_store_supplier_exists = 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Store is not registered with this supplier or relationship is not active';
    END IF;
END$$

-- Create BEFORE UPDATE trigger (in case someone tries to change supplier)
CREATE TRIGGER `before_purchase_order_update`
BEFORE UPDATE ON `purchase_orders`
FOR EACH ROW
BEGIN
    DECLARE v_store_supplier_exists INT;
    
    -- Only check if store_id or supplier_id is being changed
    IF NEW.store_id != OLD.store_id OR NEW.supplier_id != OLD.supplier_id THEN
        -- Check if store-supplier relationship exists
        SELECT COUNT(*) INTO v_store_supplier_exists
        FROM store_suppliers 
        WHERE store_id = NEW.store_id 
          AND supplier_id = NEW.supplier_id 
          AND is_active = 1;
        
        -- If relationship doesn't exist, block the update
        IF v_store_supplier_exists = 0 THEN
            SIGNAL SQLSTATE '45000' 
            SET MESSAGE_TEXT = 'Store is not registered with this supplier or relationship is not active';
        END IF;
    END IF;
END$$

DELIMITER ;

-- Step 4: Create a view to show only valid store-supplier combinations for easier application querying
CREATE OR REPLACE VIEW `valid_store_suppliers` AS
SELECT 
    ss.store_supplier_id,
    ss.store_id,
    gs.store_name,
    ss.supplier_id,
    s.supplier_name,
    ss.is_active,
    ss.preferred_supplier,
    ss.credit_limit,
    ss.current_balance,
    ss.last_order_date,
    ss.notes,
    ss.created_at,
    ss.updated_at
FROM store_suppliers ss
JOIN grocery_stores gs ON ss.store_id = gs.store_id
JOIN suppliers s ON ss.supplier_id = s.supplier_id
WHERE ss.is_active = 1
  AND gs.is_active = 1
  AND s.is_active = 1;

-- Step 5: Add application-level validation helper query
-- This query can be used by the application to validate before attempting to create a PO
/*
-- Application validation query:
SELECT COUNT(*) as is_valid
FROM valid_store_suppliers 
WHERE store_id = ? AND supplier_id = ?;
*/

-- Summary of changes:
-- 1. Replaced separate FK constraints with composite FK to store_suppliers table
-- 2. Updated stored procedure with validation
-- 3. Added triggers to prevent direct database circumvention
-- 4. Created view for easier application querying
-- 5. Provided helper query for application-level validation

-- Note: After applying these changes, any existing purchase orders that violate 
-- the store-supplier relationship will need to be cleaned up before the constraint can be applied.
-- Use the following query to identify problematic records:
/*
SELECT po.po_id, po.po_number, po.store_id, po.supplier_id
FROM purchase_orders po
LEFT JOIN store_suppliers ss ON po.store_id = ss.store_id AND po.supplier_id = ss.supplier_id
WHERE ss.store_supplier_id IS NULL;
*/
