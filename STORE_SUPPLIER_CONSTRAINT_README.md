# Store-Supplier Relationship Enforcement

This implementation addresses the database design gap where stores could create purchase orders from any supplier, regardless of whether they have a formal relationship through the `store_suppliers` table.

## Problem Statement

The original database design had a tradeoff: the `purchase_orders` table only had separate foreign keys to `grocery_stores` and `suppliers`, without enforcing that the store-supplier combination exists in the `store_suppliers` table. This meant any grocery admin could create a PO with any supplier in the system.

## Solution Overview

We've implemented a comprehensive solution with both database-level and application-level enforcement:

### 1. Database-Level Changes

**File: `database/add_store_supplier_constraint.sql`**

- **Composite Foreign Key Constraint**: Replaced separate FK constraints with a composite FK that references `store_suppliers(store_id, supplier_id)`
- **Updated Stored Procedure**: Modified `sp_generate_reorder_po` to validate store-supplier relationships
- **Database Triggers**: Added BEFORE INSERT/UPDATE triggers to prevent direct database circumvention
- **Helper View**: Created `valid_store_suppliers` view for easier application querying

### 2. Application-Level Validation

**File: `includes/store_supplier_validation.php`**

Key functions:
- `isStoreRegisteredWithSupplier()` - Check if relationship exists
- `getValidSuppliersForStore()` - Get all valid suppliers for a store
- `validatePurchaseOrderData()` - Validate PO data before creation
- `createValidatedPurchaseOrder()` - Create PO with built-in validation
- `registerStoreWithSupplier()` - Register new store-supplier relationships

### 3. User Interface Implementation

**Files:**
- `grocery/purchase_orders/create_purchase_order.php` - PO creation with validation
- `grocery/purchase_orders/register_supplier.php` - Supplier registration interface

### 4. Migration Tools

**File: `migrate_store_supplier_constraint.php`**

- Detects existing violations
- Provides cleanup options
- Guides through constraint application

## Implementation Steps

### Step 1: Run Migration Script
1. Access `migrate_store_supplier_constraint.php` in your browser
2. Review any violations found
3. Use the auto-fix options or manually resolve violations
4. Ensure no violations remain before proceeding

### Step 2: Apply Database Constraint
1. Execute the SQL script: `database/add_store_supplier_constraint.sql`
2. This will:
   - Drop existing separate FK constraints
   - Add the composite FK constraint
   - Update stored procedures
   - Add validation triggers
   - Create helper views

### Step 3: Update Application Code
1. Include the validation functions: `require_once 'includes/store_supplier_validation.php';`
2. Update PO creation forms to use validation
3. Update supplier selection to only show valid options

### Step 4: Test the Implementation
1. Try creating a PO with an unregistered supplier (should fail)
2. Register a new supplier relationship
3. Create a PO with the newly registered supplier (should succeed)
4. Test all edge cases and error conditions

## Code Examples

### Validating Before PO Creation
```php
require_once 'includes/store_supplier_validation.php';

$validation = validatePurchaseOrderData([
    'store_id' => $store_id,
    'supplier_id' => $supplier_id
]);

if (!$validation['valid']) {
    echo displayValidationError($validation['message']);
    return;
}

$po_result = createValidatedPurchaseOrder([
    'store_id' => $store_id,
    'supplier_id' => $supplier_id,
    'created_by' => $user_id,
    'notes' => $notes
]);
```

### Getting Valid Suppliers for Dropdown
```php
$valid_suppliers = getValidSuppliersForStore($store_id);

foreach ($valid_suppliers as $supplier) {
    echo "<option value='{$supplier['supplier_id']}'>";
    echo htmlspecialchars($supplier['supplier_name']);
    if ($supplier['preferred_supplier']) {
        echo " (Preferred)";
    }
    echo "</option>";
}
```

### Registering a New Supplier Relationship
```php
$result = registerStoreWithSupplier($store_id, $supplier_id, [
    'preferred_supplier' => 1,
    'credit_limit' => 10000.00,
    'notes' => 'Preferred supplier for dairy products'
]);
```

## Database Schema Changes

### Before (Problematic)
```sql
CREATE TABLE purchase_orders (
    po_id int PRIMARY KEY,
    store_id int REFERENCES grocery_stores(store_id),
    supplier_id int REFERENCES suppliers(supplier_id),
    -- other fields...
);
```

### After (Enforced)
```sql
CREATE TABLE purchase_orders (
    po_id int PRIMARY KEY,
    store_id int,
    supplier_id int,
    -- other fields...
    CONSTRAINT fk_po_store_supplier 
        FOREIGN KEY (store_id, supplier_id) 
        REFERENCES store_suppliers(store_id, supplier_id)
);
```

## Benefits

1. **Data Integrity**: Enforces referential integrity at the database level
2. **Business Logic Enforcement**: Prevents unauthorized PO creation
3. **Security**: Stores can only order from approved suppliers
4. **Audit Trail**: Clear record of store-supplier relationships
5. **Performance**: Efficient validation through proper indexing
6. **Maintainability**: Centralized validation logic

## Error Handling

The implementation provides comprehensive error handling:

- **Database Level**: SQLSTATE '45000' with descriptive messages
- **Application Level**: User-friendly error messages
- **UI Level**: Bootstrap alerts with clear instructions

## Testing Checklist

- [ ] Migration script runs without errors
- [ ] All violations are resolved
- [ ] Database constraint applies successfully
- [ ] PO creation with valid supplier works
- [ ] PO creation with invalid supplier fails appropriately
- [ ] Supplier registration works correctly
- [ ] Error messages are clear and helpful
- [ ] UI prevents invalid selections where possible

## Rollback Plan

If needed, you can rollback by:

1. Drop the composite foreign key constraint
2. Restore the original separate foreign key constraints
3. Remove the validation triggers
4. Revert stored procedure changes

## Future Enhancements

Consider adding:
- Workflow approval for new supplier relationships
- Credit limit enforcement
- Automatic supplier registration based on ordering patterns
- Supplier performance tracking integration
- Multi-level approval for high-value orders

## Support

For issues or questions:
1. Check the migration script output for errors
2. Review the database error logs
3. Test with the validation functions directly
4. Verify all foreign key relationships exist
