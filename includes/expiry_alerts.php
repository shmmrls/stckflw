<?php
/**
 * Expiry Alerts & Low Stock Alert Generator
 * Queries customer_items / grocery_items using alert_flag + expiry_status columns
 * (auto-maintained by DB triggers before_customer_item_insert/update etc.)
 */

// ──────────────────────────────────────────────
// Expiry notifications
// ──────────────────────────────────────────────

/**
 * Generate expiry notifications for a user.
 * Handles both 'customer' (customer_items) and 'grocery_admin' (grocery_items) roles.
 */
function generateExpiryNotifications(
    $conn,
    int    $user_id,
    string $role,
    int    $days_before = 7
): int {
    $count = 0;

    if ($role === 'grocery_admin') {
        // Get store_id for this admin
        $s = $conn->prepare("SELECT store_id FROM users WHERE user_id = ?");
        $s->bind_param('i', $user_id);
        $s->execute();
        $row = $s->get_result()->fetch_assoc();
        if (!$row || !$row['store_id']) return 0;
        $store_id = (int) $row['store_id'];

        // Already-expired items
        $exp = $conn->prepare("
            SELECT gi.item_id, gi.item_name, gi.expiry_date, gi.quantity, gi.unit,
                   c.category_name
            FROM grocery_items gi
            LEFT JOIN categories c ON gi.category_id = c.category_id
            WHERE gi.store_id = ? AND gi.expiry_status = 'expired'
              AND gi.alert_flag = 1
            ORDER BY gi.expiry_date ASC
        ");
        $exp->bind_param('i', $store_id);
        $exp->execute();
        foreach ($exp->get_result()->fetch_all(MYSQLI_ASSOC) as $item) {
            $days_ago = abs((int) floor((strtotime('today') - strtotime($item['expiry_date'])) / 86400));
            $created  = createNotification(
                $conn, $user_id,
                NOTIF_TYPE_EXPIRY,
                'Expired: ' . $item['item_name'],
                'Stock of ' . $item['item_name'] . ' (' . $item['quantity'] . ' ' . $item['unit'] . ') expired '
                    . ($days_ago === 0 ? 'today' : $days_ago . ' day(s) ago')
                    . '. Category: ' . ($item['category_name'] ?? 'Uncategorised') . '.',
                NOTIF_PRIORITY_HIGH,
                $item['item_id'],
                'grocery_item'
            );
            if ($created) $count++;
        }

        // Near-expiry items within $days_before
        $near = $conn->prepare("
            SELECT gi.item_id, gi.item_name, gi.expiry_date, gi.quantity, gi.unit,
                   c.category_name,
                   DATEDIFF(gi.expiry_date, CURDATE()) AS days_left
            FROM grocery_items gi
            LEFT JOIN categories c ON gi.category_id = c.category_id
            WHERE gi.store_id = ? AND gi.expiry_status = 'near_expiry'
              AND gi.alert_flag = 1
              AND DATEDIFF(gi.expiry_date, CURDATE()) BETWEEN 0 AND ?
            ORDER BY days_left ASC
        ");
        $near->bind_param('ii', $store_id, $days_before);
        $near->execute();
        foreach ($near->get_result()->fetch_all(MYSQLI_ASSOC) as $item) {
            $dl = (int) $item['days_left'];
            $created = createNotification(
                $conn, $user_id,
                NOTIF_TYPE_EXPIRY,
                'Expiring Soon: ' . $item['item_name'],
                $item['item_name'] . ' (' . $item['quantity'] . ' ' . $item['unit'] . ') expires in '
                    . ($dl === 0 ? 'today' : $dl . ' day(s)')
                    . ' on ' . date('M j, Y', strtotime($item['expiry_date']))
                    . '. Category: ' . ($item['category_name'] ?? 'Uncategorised') . '.',
                $dl <= 2 ? NOTIF_PRIORITY_HIGH : NOTIF_PRIORITY_MEDIUM,
                $item['item_id'],
                'grocery_item'
            );
            if ($created) $count++;
        }

    } else {
        // customer role — use customer_items + group_id filter
        // Fetch groups the user belongs to
        $g = $conn->prepare("SELECT group_id FROM group_members WHERE user_id = ?");
        $g->bind_param('i', $user_id);
        $g->execute();
        $groups = array_column($g->get_result()->fetch_all(MYSQLI_ASSOC), 'group_id');
        if (empty($groups)) {
            // Fallback: items directly created by user
            $groups = [];
        }

        // Expired
        if (!empty($groups)) {
            $placeholders = implode(',', array_fill(0, count($groups), '?'));
            $types        = str_repeat('i', count($groups));

            $exp = $conn->prepare("
                SELECT ci.item_id, ci.item_name, ci.expiry_date, ci.quantity, ci.unit,
                       c.category_name
                FROM customer_items ci
                LEFT JOIN categories c ON ci.category_id = c.category_id
                WHERE ci.group_id IN ($placeholders)
                  AND ci.expiry_status = 'expired' AND ci.alert_flag = 1
                ORDER BY ci.expiry_date ASC
            ");
            $exp->bind_param($types, ...$groups);
            $exp->execute();
            foreach ($exp->get_result()->fetch_all(MYSQLI_ASSOC) as $item) {
                $days_ago = abs((int) floor((strtotime('today') - strtotime($item['expiry_date'])) / 86400));
                $created  = createNotification(
                    $conn, $user_id,
                    NOTIF_TYPE_EXPIRY,
                    'Expired: ' . $item['item_name'],
                    $item['item_name'] . ' (' . $item['quantity'] . ' ' . $item['unit'] . ') expired '
                        . ($days_ago === 0 ? 'today' : $days_ago . ' day(s) ago') . '.',
                    NOTIF_PRIORITY_HIGH,
                    $item['item_id'],
                    'customer_item'
                );
                if ($created) $count++;
            }

            // Near-expiry
            $near = $conn->prepare("
                SELECT ci.item_id, ci.item_name, ci.expiry_date, ci.quantity, ci.unit,
                       c.category_name,
                       DATEDIFF(ci.expiry_date, CURDATE()) AS days_left
                FROM customer_items ci
                LEFT JOIN categories c ON ci.category_id = c.category_id
                WHERE ci.group_id IN ($placeholders)
                  AND ci.expiry_status = 'near_expiry' AND ci.alert_flag = 1
                  AND DATEDIFF(ci.expiry_date, CURDATE()) BETWEEN 0 AND ?
                ORDER BY days_left ASC
            ");
            $allParams = array_merge($groups, [$days_before]);
            $near->bind_param($types . 'i', ...$allParams);
            $near->execute();
            foreach ($near->get_result()->fetch_all(MYSQLI_ASSOC) as $item) {
                $dl      = (int) $item['days_left'];
                $created = createNotification(
                    $conn, $user_id,
                    NOTIF_TYPE_EXPIRY,
                    'Expiring Soon: ' . $item['item_name'],
                    $item['item_name'] . ' expires in '
                        . ($dl === 0 ? 'today' : $dl . ' day(s)')
                        . ' on ' . date('M j, Y', strtotime($item['expiry_date'])) . '.',
                    $dl <= 2 ? NOTIF_PRIORITY_HIGH : NOTIF_PRIORITY_MEDIUM,
                    $item['item_id'],
                    'customer_item'
                );
                if ($created) $count++;
            }
        }
    }

    return $count;
}

// ──────────────────────────────────────────────
// Low stock notifications (grocery_admin only)
// Uses grocery_items.quantity <= reorder_level
// ──────────────────────────────────────────────
function generateLowStockNotifications($conn, int $user_id): int {
    $count = 0;

    $s = $conn->prepare("SELECT store_id FROM users WHERE user_id = ?");
    $s->bind_param('i', $user_id);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    if (!$row || !$row['store_id']) return 0;
    $store_id = (int) $row['store_id'];

    // Out of stock
    $oos = $conn->prepare("
        SELECT gi.item_id, gi.item_name, gi.quantity, gi.unit,
               gi.reorder_level, gi.reorder_quantity,
               c.category_name,
               s.supplier_name
        FROM grocery_items gi
        LEFT JOIN categories c  ON gi.category_id  = c.category_id
        LEFT JOIN suppliers  s  ON gi.supplier_id   = s.supplier_id
        WHERE gi.store_id = ? AND gi.quantity = 0
        ORDER BY gi.item_name ASC
    ");
    $oos->bind_param('i', $store_id);
    $oos->execute();
    foreach ($oos->get_result()->fetch_all(MYSQLI_ASSOC) as $item) {
        $supplier_hint = $item['supplier_name'] ? ' Supplier: ' . $item['supplier_name'] . '.' : '';
        $created = createNotification(
            $conn, $user_id,
            NOTIF_TYPE_LOW_STOCK,
            'Out of Stock: ' . $item['item_name'],
            $item['item_name'] . ' is completely out of stock.'
                . ' Suggested reorder qty: ' . $item['reorder_quantity'] . ' ' . $item['unit'] . '.'
                . $supplier_hint,
            NOTIF_PRIORITY_HIGH,
            $item['item_id'],
            'grocery_item_stock'
        );
        if ($created) $count++;
    }

    // Below reorder level (but not zero)
    $low = $conn->prepare("
        SELECT gi.item_id, gi.item_name, gi.quantity, gi.unit,
               gi.reorder_level, gi.reorder_quantity,
               c.category_name,
               s.supplier_name
        FROM grocery_items gi
        LEFT JOIN categories c  ON gi.category_id  = c.category_id
        LEFT JOIN suppliers  s  ON gi.supplier_id   = s.supplier_id
        WHERE gi.store_id = ?
          AND gi.quantity > 0
          AND gi.quantity <= gi.reorder_level
        ORDER BY (gi.quantity / gi.reorder_level) ASC
    ");
    $low->bind_param('i', $store_id);
    $low->execute();
    foreach ($low->get_result()->fetch_all(MYSQLI_ASSOC) as $item) {
        $pct     = round(($item['quantity'] / max($item['reorder_level'], 1)) * 100);
        $created = createNotification(
            $conn, $user_id,
            NOTIF_TYPE_LOW_STOCK,
            'Low Stock: ' . $item['item_name'],
            $item['item_name'] . ' is at ' . $item['quantity'] . ' ' . $item['unit']
                . ' (' . $pct . '% of reorder level ' . $item['reorder_level'] . ').'
                . ' Consider reordering ' . $item['reorder_quantity'] . ' ' . $item['unit'] . '.'
                . ($item['supplier_name'] ? ' Supplier: ' . $item['supplier_name'] . '.' : ''),
            $pct <= 25 ? NOTIF_PRIORITY_HIGH : NOTIF_PRIORITY_MEDIUM,
            $item['item_id'],
            'grocery_item_stock'
        );
        if ($created) $count++;
    }

    return $count;
}

// ──────────────────────────────────────────────
// Summary: expiring items count for dashboard widget
// ──────────────────────────────────────────────
function getExpirySummary($conn, int $user_id, string $role): array {
    $summary = [
        'expired'    => 0,
        'today'      => 0,
        'within_3'   => 0,
        'within_7'   => 0,
        'within_30'  => 0,
    ];

    if ($role === 'grocery_admin') {
        $s = $conn->prepare("SELECT store_id FROM users WHERE user_id = ?");
        $s->bind_param('i', $user_id);
        $s->execute();
        $row      = $s->get_result()->fetch_assoc();
        $store_id = $row['store_id'] ?? null;
        if (!$store_id) return $summary;

        $stmt = $conn->prepare("
            SELECT
                SUM(expiry_status = 'expired')                                 AS expired,
                SUM(DATEDIFF(expiry_date, CURDATE()) = 0)                      AS today,
                SUM(DATEDIFF(expiry_date, CURDATE()) BETWEEN 1 AND 3)          AS within_3,
                SUM(DATEDIFF(expiry_date, CURDATE()) BETWEEN 4 AND 7)          AS within_7,
                SUM(DATEDIFF(expiry_date, CURDATE()) BETWEEN 8 AND 30)         AS within_30
            FROM grocery_items WHERE store_id = ?
        ");
        $stmt->bind_param('i', $store_id);
    } else {
        $stmt = $conn->prepare("
            SELECT
                SUM(expiry_status = 'expired')                                 AS expired,
                SUM(DATEDIFF(expiry_date, CURDATE()) = 0)                      AS today,
                SUM(DATEDIFF(expiry_date, CURDATE()) BETWEEN 1 AND 3)          AS within_3,
                SUM(DATEDIFF(expiry_date, CURDATE()) BETWEEN 4 AND 7)          AS within_7,
                SUM(DATEDIFF(expiry_date, CURDATE()) BETWEEN 8 AND 30)         AS within_30
            FROM customer_items WHERE created_by = ?
        ");
        $stmt->bind_param('i', $user_id);
    }

    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    foreach ($summary as $key => $_) {
        $summary[$key] = (int) ($row[$key] ?? 0);
    }
    return $summary;
}