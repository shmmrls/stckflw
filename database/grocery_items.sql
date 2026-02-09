-- ============================================================
-- MIGRATION SCRIPT: Enhance grocery_items for Supplier Integration
-- ============================================================
-- This script adds optional columns to grocery_items to work
-- seamlessly with the new supplier system while maintaining
-- backward compatibility with existing data.
-- ============================================================

-- Step 1: Add new columns to grocery_items
ALTER TABLE `grocery_items`
ADD COLUMN `supplier_product_id` int(11) DEFAULT NULL COMMENT 'Links to specific supplier product with pricing' AFTER `supplier_id`,
ADD COLUMN `purchase_order_id` int(11) DEFAULT NULL COMMENT 'Purchase order this item came from' AFTER `supplier_product_id`,
ADD COLUMN `batch_number` varchar(100) DEFAULT NULL COMMENT 'Supplier batch/lot number for traceability' AFTER `purchase_order_id`,
ADD COLUMN `received_date` date DEFAULT NULL COMMENT 'Date items were received from supplier' AFTER `batch_number`;

-- Step 2: Add foreign key constraints
ALTER TABLE `grocery_items`
ADD CONSTRAINT `fk_grocery_items_supplier_product` 
    FOREIGN KEY (`supplier_product_id`) 
    REFERENCES `supplier_products` (`supplier_product_id`) 
    ON DELETE SET NULL,
ADD CONSTRAINT `fk_grocery_items_purchase_order` 
    FOREIGN KEY (`purchase_order_id`) 
    REFERENCES `purchase_orders` (`po_id`) 
    ON DELETE SET NULL;

-- Step 3: Add indexes for better query performance
ALTER TABLE `grocery_items`
ADD KEY `idx_grocery_items_supplier_product` (`supplier_product_id`),
ADD KEY `idx_grocery_items_purchase_order` (`purchase_order_id`),
ADD KEY `idx_grocery_items_batch` (`batch_number`),
ADD KEY `idx_grocery_items_received_date` (`received_date`);

-- ============================================================
-- Step 4: OPTIONAL - Migrate existing data
-- ============================================================
-- If you have existing grocery_items and want to link them
-- to supplier_products, you can run queries like this:

-- Example: Link existing items to supplier_products based on catalog_id
-- UPDATE grocery_items gi
-- JOIN supplier_products sp ON gi.catalog_id = sp.catalog_id 
--     AND gi.supplier_id = sp.supplier_id
-- SET gi.supplier_product_id = sp.supplier_product_id
-- WHERE gi.supplier_product_id IS NULL;

-- ============================================================
-- Step 5: Create useful views for reporting
-- ============================================================

-- View: Complete inventory with supplier details
CREATE OR REPLACE VIEW `v_grocery_inventory_details` AS
SELECT 
    gi.item_id,
    gi.item_name,
    gi.barcode,
    gi.sku,
    gi.quantity,
    gi.unit,
    gi.cost_price,
    gi.selling_price,
    (gi.selling_price - gi.cost_price) as profit_per_unit,
    (gi.selling_price - gi.cost_price) * gi.quantity as total_profit,
    gi.expiry_date,
    gi.expiry_status,
    gi.reorder_level,
    -- Product catalog info
    pc.product_name as catalog_product_name,
    pc.brand as catalog_brand,
    -- Category info
    c.category_name,
    -- Supplier info
    s.supplier_name,
    s.supplier_type,
    s.contact_number as supplier_contact,
    -- Supplier product info
    sp.supplier_sku,
    sp.minimum_order_quantity,
    sp.lead_time_days,
    -- Purchase order info
    po.po_number,
    po.order_date,
    gi.received_date,
    po.payment_status,
    -- Store info
    gs.store_name,
    -- Additional
    gi.batch_number,
    gi.date_added,
    gi.last_updated
FROM grocery_items gi
LEFT JOIN product_catalog pc ON gi.catalog_id = pc.catalog_id
LEFT JOIN categories c ON gi.category_id = c.category_id
LEFT JOIN suppliers s ON gi.supplier_id = s.supplier_id
LEFT JOIN supplier_products sp ON gi.supplier_product_id = sp.supplier_product_id
LEFT JOIN purchase_orders po ON gi.purchase_order_id = po.po_id
LEFT JOIN grocery_stores gs ON gi.store_id = gs.store_id;

-- View: Items needing reorder with supplier options
CREATE OR REPLACE VIEW `v_reorder_suggestions` AS
SELECT 
    gi.item_id,
    gi.item_name,
    gi.quantity as current_quantity,
    gi.reorder_level,
    gi.reorder_quantity as suggested_reorder_qty,
    (gi.reorder_level - gi.quantity) as shortage,
    -- Current supplier
    s.supplier_name as current_supplier,
    gi.cost_price as current_cost,
    -- Alternative suppliers with pricing
    (SELECT GROUP_CONCAT(
        CONCAT(s2.supplier_name, ': ₱', sp2.unit_price, ' (Min: ', sp2.minimum_order_quantity, ')')
        SEPARATOR ' | '
    )
    FROM supplier_products sp2
    JOIN suppliers s2 ON sp2.supplier_id = s2.supplier_id
    WHERE sp2.catalog_id = gi.catalog_id
      AND sp2.is_available = 1
    ORDER BY sp2.unit_price ASC
    ) as alternative_suppliers,
    -- Best price available
    (SELECT MIN(sp3.unit_price)
    FROM supplier_products sp3
    WHERE sp3.catalog_id = gi.catalog_id
      AND sp3.is_available = 1
    ) as best_available_price,
    gi.catalog_id,
    gi.store_id,
    c.category_name
FROM grocery_items gi
LEFT JOIN suppliers s ON gi.supplier_id = s.supplier_id
LEFT JOIN categories c ON gi.category_id = c.category_id
WHERE gi.quantity <= gi.reorder_level;

-- View: Supplier performance analysis
CREATE OR REPLACE VIEW `v_supplier_performance` AS
SELECT 
    s.supplier_id,
    s.supplier_name,
    s.supplier_type,
    s.rating as supplier_rating,
    COUNT(DISTINCT po.po_id) as total_purchase_orders,
    COUNT(DISTINCT gi.item_id) as total_items_supplied,
    SUM(gi.quantity) as total_quantity_supplied,
    SUM(gi.cost_price * gi.quantity) as total_purchase_value,
    SUM((gi.selling_price - gi.cost_price) * gi.quantity) as total_profit_generated,
    AVG(gi.selling_price - gi.cost_price) as avg_profit_per_unit,
    AVG(DATEDIFF(po.actual_delivery_date, po.expected_delivery_date)) as avg_delivery_delay_days,
    COUNT(DISTINCT CASE WHEN po.actual_delivery_date <= po.expected_delivery_date THEN po.po_id END) as on_time_deliveries,
    COUNT(DISTINCT CASE WHEN po.actual_delivery_date > po.expected_delivery_date THEN po.po_id END) as late_deliveries,
    MAX(po.order_date) as last_order_date,
    s.payment_terms,
    s.is_active
FROM suppliers s
LEFT JOIN purchase_orders po ON s.supplier_id = po.supplier_id
LEFT JOIN grocery_items gi ON gi.purchase_order_id = po.po_id
GROUP BY s.supplier_id;

-- View: Product price comparison across suppliers
CREATE OR REPLACE VIEW `v_product_price_comparison` AS
SELECT 
    pc.catalog_id,
    pc.product_name,
    pc.brand,
    c.category_name,
    -- Supplier details with pricing
    s.supplier_name,
    s.supplier_type,
    sp.supplier_sku,
    sp.unit_price,
    sp.unit_size,
    sp.minimum_order_quantity,
    sp.lead_time_days,
    sp.is_available,
    -- Price ranking
    RANK() OVER (PARTITION BY pc.catalog_id ORDER BY sp.unit_price ASC) as price_rank,
    -- Current inventory from this supplier
    (SELECT SUM(gi.quantity)
     FROM grocery_items gi
     WHERE gi.catalog_id = pc.catalog_id
       AND gi.supplier_id = s.supplier_id
    ) as current_stock_from_supplier
FROM product_catalog pc
JOIN supplier_products sp ON pc.catalog_id = sp.catalog_id
JOIN suppliers s ON sp.supplier_id = s.supplier_id
LEFT JOIN categories c ON pc.category_id = c.category_id
WHERE sp.is_available = 1
ORDER BY pc.product_name, sp.unit_price ASC;

-- ============================================================
-- Step 6: Create helpful stored procedures
-- ============================================================

-- Procedure: Get best supplier for a product
DELIMITER $$
CREATE PROCEDURE `sp_get_best_supplier_for_product`(
    IN p_catalog_id INT,
    IN p_required_quantity INT
)
BEGIN
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

-- Procedure: Create purchase order from low stock items
DELIMITER $$
CREATE PROCEDURE `sp_generate_reorder_po`(
    IN p_store_id INT,
    IN p_supplier_id INT,
    IN p_created_by INT,
    OUT p_po_id INT
)
BEGIN
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
DELIMITER ;

COMMIT;

-- ============================================================
-- VERIFICATION QUERIES
-- ============================================================

-- Check the new columns were added
-- DESCRIBE grocery_items;

-- Test the views
-- SELECT * FROM v_grocery_inventory_details LIMIT 5;
-- SELECT * FROM v_reorder_suggestions LIMIT 5;
-- SELECT * FROM v_supplier_performance;
-- SELECT * FROM v_product_price_comparison WHERE catalog_id = 1;

-- Test the stored procedure
-- CALL sp_get_best_supplier_for_product(1, 50);
-- CALL sp_generate_reorder_po(1, 3, 1, @new_po_id);
-- SELECT @new_po_id;