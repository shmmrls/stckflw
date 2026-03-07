<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/customer_auth_check.php';
requireLogin();

if ($_SESSION['role'] !== 'customer') {
    header('Location: ' . $baseUrl . '/grocery/grocery_dashboard.php');
    exit();
}

$conn = getDBConnection();
$user_id = getCurrentUserId();

// Get user's group
$group_stmt = $conn->prepare("
    SELECT g.group_id, g.group_name
    FROM group_members gm
    JOIN groups g ON gm.group_id = g.group_id
    WHERE gm.user_id = ?
    LIMIT 1
");
$group_stmt->bind_param("i", $user_id);
$group_stmt->execute();
$group_info = $group_stmt->get_result()->fetch_assoc();
$group_id = $group_info['group_id'] ?? null;
$group_name = $group_info['group_name'] ?? 'Personal';

// Get export type from query parameter
$export_type = $_GET['type'] ?? 'weekly';

// Validate export type
if (!in_array($export_type, ['weekly', 'monthly'])) {
    header('Location: reports.php');
    exit();
}

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $export_type . '_report_' . date('Y-m-d') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

if ($export_type === 'weekly') {
    // ─────────────────────────────────────────────────────────────────────────
    // WEEKLY EXPORT
    // ─────────────────────────────────────────────────────────────────────────
    
    // Add report header
    fputcsv($output, ['Weekly Inventory Report']);
    fputcsv($output, ['Group: ' . $group_name]);
    fputcsv($output, ['Generated: ' . date('F d, Y g:i A')]);
    fputcsv($output, []);
    
    // ── SUMMARY SECTION ──
    fputcsv($output, ['WEEKLY SUMMARY']);
    fputcsv($output, ['Metric', 'This Week', 'Last Week', 'Change', '% Change']);
    
    // Get weekly data
    $weekly_stmt = $conn->prepare("
        SELECT
            SUM(CASE WHEN ciu.update_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                      AND ciu.update_type = 'consumed'
                     THEN ABS(ciu.quantity_change) ELSE 0 END)  AS this_week_consumed,
            SUM(CASE WHEN ciu.update_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 14 DAY)
                                              AND DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                      AND ciu.update_type = 'consumed'
                     THEN ABS(ciu.quantity_change) ELSE 0 END)  AS last_week_consumed,
            SUM(CASE WHEN ciu.update_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                      AND ciu.update_type = 'added'
                     THEN ciu.quantity_change ELSE 0 END)        AS this_week_added,
            SUM(CASE WHEN ciu.update_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 14 DAY)
                                              AND DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                      AND ciu.update_type = 'added'
                     THEN ciu.quantity_change ELSE 0 END)        AS last_week_added,
            SUM(CASE WHEN ciu.update_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                      AND ciu.update_type IN ('spoiled','expired')
                     THEN ABS(ciu.quantity_change) ELSE 0 END)   AS this_week_wasted,
            SUM(CASE WHEN ciu.update_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 14 DAY)
                                              AND DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                      AND ciu.update_type IN ('spoiled','expired')
                     THEN ABS(ciu.quantity_change) ELSE 0 END)   AS last_week_wasted
        FROM customer_inventory_updates ciu
        JOIN customer_items ci ON ciu.item_id = ci.item_id
        WHERE ci.group_id = ?
    ");
    $weekly_stmt->bind_param("i", $group_id);
    $weekly_stmt->execute();
    $weekly = $weekly_stmt->get_result()->fetch_assoc();
    
    // Calculate changes
    $tw_consumed = floatval($weekly['this_week_consumed'] ?? 0);
    $lw_consumed = floatval($weekly['last_week_consumed'] ?? 0);
    $change_consumed = $tw_consumed - $lw_consumed;
    $pct_consumed = $lw_consumed > 0 ? round(($change_consumed / $lw_consumed) * 100, 1) : 0;
    
    $tw_added = floatval($weekly['this_week_added'] ?? 0);
    $lw_added = floatval($weekly['last_week_added'] ?? 0);
    $change_added = $tw_added - $lw_added;
    $pct_added = $lw_added > 0 ? round(($change_added / $lw_added) * 100, 1) : 0;
    
    $tw_wasted = floatval($weekly['this_week_wasted'] ?? 0);
    $lw_wasted = floatval($weekly['last_week_wasted'] ?? 0);
    $change_wasted = $tw_wasted - $lw_wasted;
    $pct_wasted = $lw_wasted > 0 ? round(($change_wasted / $lw_wasted) * 100, 1) : 0;
    
    fputcsv($output, ['Items Consumed', number_format($tw_consumed, 1), number_format($lw_consumed, 1), number_format($change_consumed, 1), $pct_consumed . '%']);
    fputcsv($output, ['Items Added', number_format($tw_added, 1), number_format($lw_added, 1), number_format($change_added, 1), $pct_added . '%']);
    fputcsv($output, ['Items Wasted', number_format($tw_wasted, 1), number_format($lw_wasted, 1), number_format($change_wasted, 1), $pct_wasted . '%']);
    fputcsv($output, ['Waste Reduction', '', '', number_format(max(0, $lw_wasted - $tw_wasted), 1) . ' units saved', '']);
    
    fputcsv($output, []);
    
    // ── DAILY BREAKDOWN ──
    fputcsv($output, ['DAILY BREAKDOWN (Last 7 Days)']);
    fputcsv($output, ['Date', 'Day', 'Consumed', 'Added', 'Wasted', 'Net Flow']);
    
    $daily_stmt = $conn->prepare("
        SELECT
            DATE(ciu.update_date) AS activity_date,
            SUM(CASE WHEN ciu.update_type = 'consumed' THEN ABS(ciu.quantity_change) ELSE 0 END) AS consumed,
            SUM(CASE WHEN ciu.update_type = 'added' THEN ciu.quantity_change ELSE 0 END) AS added,
            SUM(CASE WHEN ciu.update_type IN ('spoiled','expired') THEN ABS(ciu.quantity_change) ELSE 0 END) AS wasted
        FROM customer_inventory_updates ciu
        JOIN customer_items ci ON ciu.item_id = ci.item_id
        WHERE ci.group_id = ?
          AND ciu.update_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(ciu.update_date)
        ORDER BY activity_date DESC
    ");
    $daily_stmt->bind_param("i", $group_id);
    $daily_stmt->execute();
    $daily_data = $daily_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($daily_data as $day) {
        $date = date('M d, Y', strtotime($day['activity_date']));
        $day_name = date('l', strtotime($day['activity_date']));
        $consumed = floatval($day['consumed']);
        $added = floatval($day['added']);
        $wasted = floatval($day['wasted']);
        $net_flow = $added - $consumed - $wasted;
        
        fputcsv($output, [
            $date,
            $day_name,
            number_format($consumed, 1),
            number_format($added, 1),
            number_format($wasted, 1),
            number_format($net_flow, 1)
        ]);
    }
    
    fputcsv($output, []);
    
    // ── ITEMS EXPIRING THIS WEEK ──
    fputcsv($output, ['ITEMS EXPIRING IN NEXT 7 DAYS']);
    fputcsv($output, ['Item Name', 'Category', 'Quantity', 'Unit', 'Expiry Date', 'Days Left']);
    
    $expiry_stmt = $conn->prepare("
        SELECT
            ci.item_name,
            c.category_name,
            ci.quantity,
            ci.unit,
            ci.expiry_date,
            DATEDIFF(ci.expiry_date, CURDATE()) AS days_left
        FROM customer_items ci
        LEFT JOIN categories c ON ci.category_id = c.category_id
        WHERE ci.group_id = ?
          AND ci.quantity > 0
          AND ci.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
          AND ci.expiry_status != 'expired'
        ORDER BY ci.expiry_date ASC
    ");
    $expiry_stmt->bind_param("i", $group_id);
    $expiry_stmt->execute();
    $expiry_data = $expiry_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($expiry_data as $item) {
        fputcsv($output, [
            $item['item_name'],
            $item['category_name'] ?? '—',
            number_format($item['quantity'], 1),
            $item['unit'],
            date('M d, Y', strtotime($item['expiry_date'])),
            $item['days_left']
        ]);
    }
    
    if (empty($expiry_data)) {
        fputcsv($output, ['No items expiring in the next 7 days']);
    }
    
    fputcsv($output, []);
    
    // ── TOP WASTED ITEMS THIS WEEK ──
    fputcsv($output, ['TOP WASTED ITEMS (This Week)']);
    fputcsv($output, ['Item Name', 'Category', 'Total Wasted', 'Unit', 'Waste Events']);
    
    $waste_stmt = $conn->prepare("
        SELECT
            ci.item_name,
            c.category_name,
            ci.unit,
            SUM(ABS(ciu.quantity_change)) AS total_wasted,
            COUNT(*) AS waste_events
        FROM customer_inventory_updates ciu
        JOIN customer_items ci ON ciu.item_id = ci.item_id
        LEFT JOIN categories c ON ci.category_id = c.category_id
        WHERE ci.group_id = ?
          AND ciu.update_type IN ('spoiled','expired')
          AND ciu.update_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY ci.item_id
        ORDER BY total_wasted DESC
        LIMIT 10
    ");
    $waste_stmt->bind_param("i", $group_id);
    $waste_stmt->execute();
    $waste_data = $waste_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($waste_data as $item) {
        fputcsv($output, [
            $item['item_name'],
            $item['category_name'] ?? '—',
            number_format(floatval($item['total_wasted']), 1),
            $item['unit'],
            $item['waste_events']
        ]);
    }
    
    if (empty($waste_data)) {
        fputcsv($output, ['No waste recorded this week']);
    }
    
    $weekly_stmt->close();
    $daily_stmt->close();
    $expiry_stmt->close();
    $waste_stmt->close();
    
} else {
    // ─────────────────────────────────────────────────────────────────────────
    // MONTHLY EXPORT
    // ─────────────────────────────────────────────────────────────────────────
    
    // Add report header
    fputcsv($output, ['Monthly Inventory Report (Last 6 Months)']);
    fputcsv($output, ['Group: ' . $group_name]);
    fputcsv($output, ['Generated: ' . date('F d, Y g:i A')]);
    fputcsv($output, []);
    
    // ── MONTHLY SUMMARY ──
    fputcsv($output, ['MONTHLY SUMMARY']);
    fputcsv($output, ['Month', 'Items Added', 'Items Consumed', 'Items Wasted', 'Unique Items', 'Waste Rate', 'Efficiency']);
    
    $monthly_stmt = $conn->prepare("
        SELECT
            DATE_FORMAT(ciu.update_date, '%Y-%m') AS month_key,
            DATE_FORMAT(ciu.update_date, '%b %Y') AS month_label,
            SUM(CASE WHEN ciu.update_type = 'added' THEN ciu.quantity_change ELSE 0 END) AS items_added,
            SUM(CASE WHEN ciu.update_type = 'consumed' THEN ABS(ciu.quantity_change) ELSE 0 END) AS items_consumed,
            SUM(CASE WHEN ciu.update_type IN ('spoiled','expired') THEN ABS(ciu.quantity_change) ELSE 0 END) AS items_wasted,
            COUNT(DISTINCT ciu.item_id) AS unique_items
        FROM customer_inventory_updates ciu
        JOIN customer_items ci ON ciu.item_id = ci.item_id
        WHERE ci.group_id = ?
          AND ciu.update_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(ciu.update_date, '%Y-%m')
        ORDER BY month_key DESC
    ");
    $monthly_stmt->bind_param("i", $group_id);
    $monthly_stmt->execute();
    $monthly_data = $monthly_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($monthly_data as $month) {
        $total_flow = floatval($month['items_consumed']) + floatval($month['items_wasted']);
        $waste_rate = $total_flow > 0 ? round((floatval($month['items_wasted']) / $total_flow) * 100, 1) : 0;
        $efficiency = $total_flow > 0 ? round((floatval($month['items_consumed']) / $total_flow) * 100, 1) : 0;
        
        fputcsv($output, [
            $month['month_label'],
            number_format($month['items_added'], 1),
            number_format($month['items_consumed'], 1),
            number_format($month['items_wasted'], 1),
            $month['unique_items'],
            $waste_rate . '%',
            $efficiency . '%'
        ]);
    }
    
    fputcsv($output, []);
    
    // ── CATEGORY BREAKDOWN ──
    fputcsv($output, ['CATEGORY BREAKDOWN (All Time)']);
    fputcsv($output, ['Category', 'Total Items', 'Fresh', 'Near Expiry', 'Expired', 'Low Stock']);
    
    $cat_stmt = $conn->prepare("
        SELECT
            c.category_name,
            COUNT(ci.item_id) AS item_count,
            SUM(CASE WHEN ci.expiry_status = 'fresh' THEN 1 ELSE 0 END) AS fresh_count,
            SUM(CASE WHEN ci.expiry_status = 'near_expiry' THEN 1 ELSE 0 END) AS near_expiry_count,
            SUM(CASE WHEN ci.expiry_status = 'expired' THEN 1 ELSE 0 END) AS expired_count,
            SUM(CASE WHEN ci.quantity <= 1 THEN 1 ELSE 0 END) AS low_stock_count
        FROM categories c
        JOIN customer_items ci ON c.category_id = ci.category_id
        WHERE ci.group_id = ?
        GROUP BY c.category_id
        ORDER BY item_count DESC
    ");
    $cat_stmt->bind_param("i", $group_id);
    $cat_stmt->execute();
    $cat_data = $cat_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($cat_data as $cat) {
        fputcsv($output, [
            $cat['category_name'],
            $cat['item_count'],
            $cat['fresh_count'],
            $cat['near_expiry_count'],
            $cat['expired_count'],
            $cat['low_stock_count']
        ]);
    }
    
    fputcsv($output, []);
    
    // ── TOP WASTED ITEMS (ALL TIME) ──
    fputcsv($output, ['TOP WASTED ITEMS (All Time)']);
    fputcsv($output, ['Rank', 'Item Name', 'Category', 'Total Wasted', 'Unit', 'Waste Events', 'First Wasted', 'Last Wasted']);
    
    $top_waste_stmt = $conn->prepare("
        SELECT
            ci.item_name,
            c.category_name,
            ci.unit,
            SUM(ABS(ciu.quantity_change)) AS total_wasted,
            COUNT(*) AS waste_events,
            MIN(ciu.update_date) AS first_waste,
            MAX(ciu.update_date) AS last_waste
        FROM customer_inventory_updates ciu
        JOIN customer_items ci ON ciu.item_id = ci.item_id
        LEFT JOIN categories c ON ci.category_id = c.category_id
        WHERE ci.group_id = ?
          AND ciu.update_type IN ('spoiled','expired')
        GROUP BY ci.item_id
        ORDER BY total_wasted DESC
        LIMIT 20
    ");
    $top_waste_stmt->bind_param("i", $group_id);
    $top_waste_stmt->execute();
    $top_waste_data = $top_waste_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $rank = 1;
    foreach ($top_waste_data as $item) {
        fputcsv($output, [
            $rank++,
            $item['item_name'],
            $item['category_name'] ?? '—',
            number_format(floatval($item['total_wasted']), 1),
            $item['unit'],
            $item['waste_events'],
            date('M d, Y', strtotime($item['first_waste'])),
            date('M d, Y', strtotime($item['last_waste']))
        ]);
    }
    
    fputcsv($output, []);
    
    // ── OVERALL EFFICIENCY METRICS ──
    fputcsv($output, ['OVERALL EFFICIENCY METRICS']);
    fputcsv($output, ['Metric', 'Value']);
    
    $efficiency_stmt = $conn->prepare("
        SELECT
            SUM(CASE WHEN update_type = 'consumed' THEN ABS(quantity_change) ELSE 0 END) AS total_consumed,
            SUM(CASE WHEN update_type IN ('spoiled','expired') THEN ABS(quantity_change) ELSE 0 END) AS total_wasted,
            COUNT(DISTINCT CASE WHEN update_type = 'consumed' THEN ciu.item_id END) AS items_consumed_count,
            COUNT(DISTINCT CASE WHEN update_type IN ('spoiled','expired') THEN ciu.item_id END) AS items_wasted_count
        FROM customer_inventory_updates ciu
        JOIN customer_items ci ON ciu.item_id = ci.item_id
        WHERE ci.group_id = ?
    ");
    $efficiency_stmt->bind_param("i", $group_id);
    $efficiency_stmt->execute();
    $efficiency = $efficiency_stmt->get_result()->fetch_assoc();
    
    $total_flow = floatval($efficiency['total_consumed']) + floatval($efficiency['total_wasted']);
    $efficiency_pct = $total_flow > 0 ? round((floatval($efficiency['total_consumed']) / $total_flow) * 100, 1) : 0;
    $waste_pct = $total_flow > 0 ? round((floatval($efficiency['total_wasted']) / $total_flow) * 100, 1) : 0;
    
    fputcsv($output, ['Total Units Consumed', number_format(floatval($efficiency['total_consumed']), 1)]);
    fputcsv($output, ['Total Units Wasted', number_format(floatval($efficiency['total_wasted']), 1)]);
    fputcsv($output, ['Consumption Efficiency', $efficiency_pct . '%']);
    fputcsv($output, ['Waste Rate', $waste_pct . '%']);
    fputcsv($output, ['Unique Items Consumed', $efficiency['items_consumed_count']]);
    fputcsv($output, ['Unique Items Wasted', $efficiency['items_wasted_count']]);
    
    $monthly_stmt->close();
    $cat_stmt->close();
    $top_waste_stmt->close();
    $efficiency_stmt->close();
}

// Clean up
$group_stmt->close();
$conn->close();

// Close output
fclose($output);
exit();
?>